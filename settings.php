<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'connection.php';
require_once 'upload.php';

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Get user data
$userRef = $database->getReference('users/' . $user_id);
$userData = $userRef->getValue();

// Get Firebase Auth service
$auth = app('firebase.auth');

// Get Firebase UID from user data or session
$firebase_uid = $userData['firebase_uid'] ?? $_SESSION['firebase_uid'] ?? null;

// If we don't have the Firebase UID stored, try to get it by email
if (!$firebase_uid && isset($userData['email'])) {
    try {
        $userRecord = $auth->getUserByEmail($userData['email']);
        $firebase_uid = $userRecord->uid;
        
        // Store the Firebase UID for future use
        $userRef->update(['firebase_uid' => $firebase_uid]);
        $_SESSION['firebase_uid'] = $firebase_uid;
    } catch (Exception $e) {
        error_log("Could not get Firebase UID: " . $e->getMessage());
    }
}

// Get courts data
$userCourtsRef = $database->getReference('user_courts/' . $user_id);
$userCourts = $userCourtsRef->getValue();
$courts = $userCourts['courts'] ?? [];

// Get settings data
$settingsRef = $database->getReference('settings/' . $user_id);
$settings = $settingsRef->getValue() ?? [];

$success_message = '';
$error_message = '';

// Handle profile update
if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $old_email = trim($_POST['old_email']);
    $new_email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $cpNumber = trim($_POST['cpNumber']);
    
    if (!empty($name) && !empty($new_email)) {
        try {
            // Check if email is being changed
            $email_changed = ($old_email !== $new_email);
            
            // Update user data in Firebase Realtime Database
            $updates = [
                'name' => $name,
                'email' => $new_email,
                'address' => $address,
                'cpNumber' => $cpNumber
            ];
            
            // If email is changing, update in Firebase Auth
            if ($email_changed) {
                try {
                    // Get the Firebase user by old email
                    $userRecord = $auth->getUserByEmail($old_email);
                    
                    // Update the email in Firebase Auth
                    $auth->updateUser($userRecord->uid, [
                        'email' => $new_email,
                        'displayName' => $name
                    ]);
                    
                    // Store the Firebase UID for future use
                    $updates['firebase_uid'] = $userRecord->uid;
                    $_SESSION['firebase_uid'] = $userRecord->uid;
                    
                    $success_message = "Profile and authentication email updated successfully!";
                } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
                    error_log("User not found in Firebase Auth: " . $e->getMessage());
                    $error_message = "Error: User not found in authentication system.";
                } catch (\Kreait\Firebase\Exception\Auth\EmailExists $e) {
                    error_log("Email already exists: " . $e->getMessage());
                    $error_message = "Error: This email is already in use by another account.";
                } catch (Exception $e) {
                    error_log("Error updating email in Firebase Auth: " . $e->getMessage());
                    $error_message = "Error updating email in authentication: " . $e->getMessage();
                }
            } else {
                // Email not changing, just update the profile
                $success_message = "Profile updated successfully!";
            }
            
            // If no error occurred or if email wasn't changed, update the database
            if (empty($error_message)) {
                $userRef->update($updates);
                
                // Refresh user data
                $userData = $userRef->getValue();
            }
        } catch (Exception $e) {
            error_log("General error updating profile: " . $e->getMessage());
            $error_message = "Error updating profile: " . $e->getMessage();
        }
    } else {
        $error_message = "Name and email are required!";
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required!";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New password and confirmation do not match!";
    } elseif (strlen($new_password) < 6) {
        $error_message = "Password must be at least 6 characters long!";
    } else {
        // If we have the Firebase UID, try to update the password directly
        if ($firebase_uid) {
            try {
                // Direct update of password in Firebase Auth
                $auth->updateUser($firebase_uid, [
                    'password' => $new_password
                ]);
                
                // Set a flag to redirect to logout after displaying success message
                $_SESSION['password_changed'] = true;
                $success_message = "Password changed successfully! You will be logged out for security reasons.";
            } catch (Exception $e) {
                error_log("Error updating password: " . $e->getMessage());
                $error_message = "Error changing password: " . $e->getMessage();
            }
        } else {
            $error_message = "Could not update password (Firebase UID not found).";
        }
    }
}

// Handle court update
if (isset($_POST['update_court'])) {
    $court_index = intval($_POST['court_index']);
    $court_name = trim($_POST['court_name']);
    $court_address = trim($_POST['court_address']);
    $court_status = trim($_POST['court_status'] ?? 'available');
    
    if (!empty($court_name) && !empty($court_address)) {
        // Preserve the court ID if it exists, or create one if it doesn't
        if (!isset($courts[$court_index]['id'])) {
            // Find the highest existing court ID
            $highest_id = -1;
            foreach ($courts as $court) {
                if (isset($court['id']) && is_numeric($court['id']) && $court['id'] > $highest_id) {
                    $highest_id = (int)$court['id'];
                }
            }
            $courts[$court_index]['id'] = $highest_id + 1;
            error_log("Assigned new ID " . ($highest_id + 1) . " to existing court without ID");
        }
        
        // Update court data
        $courts[$court_index]['name'] = $court_name;
        $courts[$court_index]['address'] = $court_address;
        $courts[$court_index]['status'] = $court_status;
        
        // Handle image upload if present
        if (isset($_FILES['court_image']) && $_FILES['court_image']['error'] == 0) {
            // Debug information
            error_log("Uploading image for court index: " . $court_index . ", ID: " . $courts[$court_index]['id']);
            error_log("File info: " . print_r($_FILES['court_image'], true));
            
            // Delete old image if it exists
            if (!empty($courts[$court_index]['image_url'])) {
                error_log("Deleting old image: " . $courts[$court_index]['image_url']);
                deleteImageFromFirebase($courts[$court_index]['image_url']);
            }
            
            // Upload new image
            $image_url = uploadImageToFirebase($_FILES['court_image'], $user_id, "court_" . $courts[$court_index]['id']);
            if ($image_url) {
                error_log("New image URL: " . $image_url);
                $courts[$court_index]['image_url'] = $image_url;
            } else {
                error_log("Failed to upload image");
                $error_message = "Failed to upload image. Please try again.";
            }
        }
        
        // Save updated courts data
        $userCourtsRef->update([
            'courts' => $courts
        ]);
        
        $success_message = "Court updated successfully!";
        
        // Refresh courts data
        $userCourts = $userCourtsRef->getValue();
        $courts = $userCourts['courts'] ?? [];
    } else {
        $error_message = "Court name and address are required!";
    }
}

// Handle adding a new court
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_court') {
    $court_name = trim($_POST['court_name']);
    $court_address = trim($_POST['court_address']);
    $court_status = trim($_POST['court_status'] ?? 'available');
    
    if (!empty($court_name) && !empty($court_address)) {
        // Find the highest existing court ID
        $highest_id = -1;
        foreach ($courts as $court) {
            if (isset($court['id']) && is_numeric($court['id']) && $court['id'] > $highest_id) {
                $highest_id = (int)$court['id'];
            }
        }
        
        // Create new court data with a unique ID
        $new_court_id = $highest_id + 2;
        $newCourt = [
            'id' => $new_court_id,
            'name' => $court_name,
            'address' => $court_address,
            'status' => $court_status
        ];
        
        error_log("Creating new court with ID: " . $new_court_id);
        
        // Handle image upload if present
        if (isset($_FILES['court_image']) && $_FILES['court_image']['error'] == 0) {
            // Upload new image
            $image_url = uploadImageToFirebase($_FILES['court_image'], $user_id, "court_" . $new_court_id);
            if ($image_url) {
                $newCourt['image_url'] = $image_url;
            }
        }
        
        // Add new court to courts array
        $courts[] = $newCourt;
        
        // Save updated courts data
        try {
            // First, ensure the user_courts node exists
            if (!$userCourts) {
                $userCourtsRef->set([
                    'setup_completed' => true,
                    'courts' => [$newCourt]
                ]);
                error_log("Created new user_courts node for user: " . $user_id . " with court ID: " . $new_court_id);
            } else {
                // Update existing courts array
                $userCourtsRef->update([
                    'courts' => $courts,
                    'setup_completed' => true
                ]);
                error_log("Updated courts for user: " . $user_id . " with new court: " . $court_name . " (ID: " . $new_court_id . ")");
            }
            
            // Verify the data was saved
            $verifyRef = $database->getReference('user_courts/' . $user_id);
            $verifyData = $verifyRef->getValue();
            
            if ($verifyData && isset($verifyData['courts']) && count($verifyData['courts']) === count($courts)) {
                error_log("Court data verified successfully");
                $success_message = "New court added successfully!";
            } else {
                error_log("Court data verification failed");
                $error_message = "Court was added but verification failed. Please refresh the page.";
            }
        } catch (Exception $e) {
            error_log("Error saving court to Firebase: " . $e->getMessage());
            $error_message = "Error adding court: " . $e->getMessage();
        }
        
        // Refresh courts data
        $userCourts = $userCourtsRef->getValue();
        $courts = $userCourts['courts'] ?? [];
    } else {
        $error_message = "Court name and address are required!";
    }
}

// Handle feature settings
if (isset($_POST['update_features'])) {
    $piggyBankEnabled = isset($_POST['piggy_bank_enabled']);
    $hideManualBooking = isset($_POST['hide_manual_booking']);
    
    // Update settings
    $settingsRef->update([
        'piggy_bank_enabled' => $piggyBankEnabled,
        'hide_manual_booking' => $hideManualBooking
    ]);
    
    $settings['piggy_bank_enabled'] = $piggyBankEnabled;
    $settings['hide_manual_booking'] = $hideManualBooking;
    
    $success_message = "Features updated successfully!";
}

// Handle court deletion
if (isset($_POST['delete_court'])) {
    $court_index = intval($_POST['court_index']);
    
    // Check if the court exists
    if (isset($courts[$court_index])) {
        $court_name = $courts[$court_index]['name'];
        $court_id = $courts[$court_index]['id'] ?? $court_index;
        
        error_log("Deleting court: " . $court_name . " (ID: " . $court_id . ")");
        
        // Delete court image if it exists
        if (!empty($courts[$court_index]['image_url'])) {
            deleteImageFromFirebase($courts[$court_index]['image_url']);
            error_log("Deleted image for court: " . $court_name);
        }
        
        // Remove the court from the array
        unset($courts[$court_index]);
        
        // Reindex the array to avoid gaps
        $courts = array_values($courts);
        
        // Save updated courts data
        try {
            $userCourtsRef->update([
                'courts' => $courts
            ]);
            
            error_log("Court deleted successfully: " . $court_name . " (ID: " . $court_id . ")");
            $success_message = "Court deleted successfully!";
            
            // Refresh courts data
            $userCourts = $userCourtsRef->getValue();
            $courts = $userCourts['courts'] ?? [];
        } catch (Exception $e) {
            error_log("Error deleting court from Firebase: " . $e->getMessage());
            $error_message = "Error deleting court: " . $e->getMessage();
        }
    } else {
        $error_message = "Court not found!";
    }
}


if (isset($_POST['delete_account'])) {
    $delete_confirmation = trim($_POST['delete_confirmation']);
    $delete_password = trim($_POST['delete_password']);
    
    if ($delete_confirmation !== 'DELETE') {
        $error_message = "Please type 'DELETE' to confirm account deletion.";
    } elseif (empty($delete_password)) {
        $error_message = "Please enter your password.";
    } else {
        try {
            if (!$firebase_uid) {
                throw new Exception("Firebase UID not found. Cannot delete account.");
            }
            
            error_log("Deleting user data for user ID: " . $user_id);
            
            $userCourtsRef->remove();
            error_log("Deleted user courts");
            
            $bookingsRef = $database->getReference('bookings/' . $user_id);
            $bookingsRef->remove();
            error_log("Deleted user bookings");

            $backupBookingsRef = $database->getReference('bookings_backup/' . $user_id);
            $backupBookingsRef->remove();
            error_log("Deleted backup bookings");
            
            $directBookingsRef = $database->getReference('direct_bookings/' . $user_id);
            $directBookingsRef->remove();
            error_log("Deleted direct bookings");
            
            $settingsRef->remove();
            error_log("Deleted user settings");
            
            $userRef->remove();
            error_log("Deleted user profile");
            
            try {
                $auth->deleteUser($firebase_uid);
                error_log("Deleted user from Firebase Authentication");
            } catch (Exception $e) {
                error_log("Error deleting user from Firebase Authentication: " . $e->getMessage());

            }

            session_unset();
            session_destroy();
            
            header("Location: login.php?message=account_deleted");
            exit();
        } catch (Exception $e) {
            error_log("Error deleting account: " . $e->getMessage());
            $error_message = "Error deleting account: " . $e->getMessage();
        }
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Dribble</title>
    <link rel="icon" href="asset/imoticon.png" type="image/png">
    <link rel="stylesheet" href="asset/navbar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/main.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/settings.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .add-court-button {
            margin-bottom: 15px;
        }

        .court-form {
            background-color: var(--card-bg);
            border-radius: var(--radius);
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
        }

        .court-form h3 {
            font-size: 16px;
            margin-top: 0;
            margin-bottom: 15px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-secondary {
            background-color: var(--gray);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-secondary:hover {
            background-color: var(--gray-dark);
        }
        
        .password-field {
            position: relative;
        }
        
        .password-field input {
            padding-right: 40px;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
        }
        
        .password-toggle:hover {
            color: var(--primary);
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-danger:hover {
            background-color: #bd2130;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .alert i {
            margin-right: 10px;
        }
        
        .court-id {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: normal;
            margin-left: 5px;
        }
    </style>
</head>
<body>

<!-- NAVIGATION BAR -->
<div class="navbar-container">
    <div class="navbar-left">
        <button class="navbar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    <div class="navbar-right">
        <div class="navbar-logo">
            <img src="asset/alt.png" alt="Logo">
        </div>
        <div class="navbar-right">
           <button id="themeToggle" class="theme-toggle">
               <i id="themeIcon" class="fas fa-moon"></i>
           </button>
       </div>
    </div>
</div>

<!-- SIDEBAR NAVIGATION -->
<div class="sidebar-nav" id="sidebarNav">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="nav-item">
        <a href="Dashboard.php" class="nav-link">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
    </div>
    <div class="nav-item">
        <a href="stats.php" class="nav-link">
            <i class="fa-solid fa-chart-simple"></i>
            <span>Stats</span>
        </a>
    </div>
    <div class="nav-item">
        <a href="calendar.php" class="nav-link">
            <i class="fa-solid fa-calendar-days"></i>
            <span>Calendar</span>
        </a>
    </div>
    <div class="nav-item">
        <a href="settings.php" class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
    </div>
    <div class="nav-item">
        <a href="#" onclick="confirmLogout()" class="nav-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>
<div class="main-content" id="mainContent">
    <div class="container">
        <?php if (!empty($success_message)): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="settings-container">
            <!-- SIDEBAR WITH SETTINGS MENU -->
            <div class="settings-sidebar">
                <h2>Settings</h2>
                <ul class="settings-menu">
                    <li><a href="#profile-section" class="active" data-section="profile-section"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="#security-section" data-section="security-section"><i class="fas fa-lock"></i> Security</a></li>
                    <li><a href="#courts-section" data-section="courts-section"><i class="fas fa-basketball-ball"></i> Courts</a></li>
                    <li><a href="#features-section" data-section="features-section"><i class="fas fa-sliders-h"></i> Features</a></li>
                    <li><a href="#delete-account-section" data-section="delete-account-section"><i class="fas fa-user-slash"></i> Delete Account</a></li>
                </ul>
            </div>
            
            <!-- SETTINGS CONTENT -->
            <div class="settings-content">
                <!-- Profile Section -->
                <section id="profile-section" class="settings-card">
                    <div class="settings-header">
                        <h2><i class="fas fa-user"></i> Profile Settings</h2>
                    </div>
                    <div class="settings-body">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($userData['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="old_email">Current Email</label>
                                <input type="email" id="old_email" name="old_email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                                
                            </div>
                            <div class="form-group">
                                <label for="email">New Email Address</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($userData['address'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="cpNumber">Contact Number</label>
                                <input type="text" id="cpNumber" name="cpNumber" value="<?php echo htmlspecialchars($userData['cpNumber'] ?? ''); ?>">
                            </div>
                            <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
                        </form>
                    </div>
                </section>
                
                <!-- Security Section -->
                <section id="security-section" class="settings-card" style="display: none;">
                    <div class="settings-header">
                        <h2><i class="fas fa-lock"></i> Security Settings</h2>
                    </div>
                    <div class="settings-body">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <div class="password-field">
                                    <input type="password" id="current_password" name="current_password" required>
                                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('current_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <div class="password-field">
                                    <input type="password" id="new_password" name="new_password" required>
                                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('new_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <div class="password-field">
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" name="change_password" class="btn-primary">Change Password</button>
                        </form>
                    </div>
                </section>
                
                <!-- Courts Section -->
                <section id="courts-section" class="settings-card" style="display: none;">
                    <div class="settings-header">
                        <h2><i class="fas fa-basketball-ball"></i> Manage Courts</h2>
                    </div>
                    <div class="settings-body">
                        <div class="add-court-button">
                            <button type="button" class="btn-primary" onclick="showAddCourtForm()">
                                <i class="fas fa-plus-circle"></i> Add New Court
                            </button>
                        </div>

                        <div id="add-court-form" class="court-form" style="display: none;">
                            <h3>Add New Court</h3>
                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="add_court">
                                
                                <div class="form-group">
                                    <label for="new_court_image">Court Image</label>
                                    <input type="file" id="new_court_image" name="court_image" accept="image/*">
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_court_name">Court Name</label>
                                    <input type="text" id="new_court_name" name="court_name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_court_address">Court Address</label>
                                    <input type="text" id="new_court_address" name="court_address" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_court_status">Court Status</label>
                                    <select id="new_court_status" name="court_status" class="status-select">
                                        <option value="available" selected>Available</option>
                                        <option value="unavailable">Unavailable</option>
                                        <option value="maintenance">Under Maintenance</option>
                                        <option value="disabled">Disabled</option>
                                    </select>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="button" class="btn-secondary" onclick="hideAddCourtForm()">Cancel</button>
                                    <button type="submit" class="btn-primary">Add Court</button>
                                </div>
                            </form>
                        </div>
                        <?php if (empty($courts)): ?>
                            <div class="empty-courts">
                                <p>No courts found. Please add courts.</p>
                            </div>
                        <?php else: ?>
                            <div class="courts-accordion">
                                <?php foreach ($courts as $index => $court): ?>
                                    <div class="court-item" id="court-<?php echo $index; ?>">
                                        <div class="court-header" onclick="toggleCourt(<?php echo $index; ?>)">
                                            <h3>
                                                <?php echo htmlspecialchars($court['name']); ?>
                                                <small class="court-id">(ID: <?php echo isset($court['id']) ? $court['id'] : $index; ?>)</small>
                                                <?php if (isset($court['status']) && $court['status'] != 'available'): ?>
                                                    <span class="court-status-badge court-status-<?php echo $court['status']; ?>">
                                                        <?php 
                                                        switch($court['status']) {
                                                            case 'unavailable':
                                                                echo 'Unavailable';
                                                                break;
                                                            case 'maintenance':
                                                                echo 'Under Maintenance';
                                                                break;
                                                            case 'disabled':
                                                                echo 'Disabled';
                                                                break;
                                                            default:
                                                                echo 'Available';
                                                        }
                                                        ?>
                                                    </span>
                                                <?php endif; ?>
                                            </h3>
                                            <i class="fas fa-chevron-down"></i>
                                        </div>
                                        <div class="court-content" id="court-content-<?php echo $index; ?>">
                                            <form method="POST" action="" enctype="multipart/form-data">
                                                <input type="hidden" name="court_index" value="<?php echo $index; ?>">
                                                
                                                <div class="court-image-preview">
                                                    <?php if (!empty($court['image_url'])): ?>
                                                        <img src="<?php echo htmlspecialchars($court['image_url']); ?>" alt="Court Image" onerror="this.onerror=null; this.src='asset/images/courts/placeholder.jpg';">
                                                    <?php else: ?>
                                                        <div class="no-image">
                                                            <i class="fas fa-image"></i>
                                                            <span>No Image</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="court_status_<?php echo $index; ?>">Court Status</label>
                                                    <select id="court_status_<?php echo $index; ?>" name="court_status" class="status-select">
                                                        <option value="available" <?php echo (!isset($court['status']) || $court['status'] == 'available') ? 'selected' : ''; ?>>Available</option>
                                                        <option value="unavailable" <?php echo (isset($court['status']) && $court['status'] == 'unavailable') ? 'selected' : ''; ?>>Unavailable</option>
                                                        <option value="maintenance" <?php echo (isset($court['status']) && $court['status'] == 'maintenance') ? 'selected' : ''; ?>>Under Maintenance</option>
                                                        <option value="disabled" <?php echo (isset($court['status']) && $court['status'] == 'disabled') ? 'selected' : ''; ?>>Disabled</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="court_image_<?php echo $index; ?>">Court Image</label>
                                                    <input type="file" id="court_image_<?php echo $index; ?>" name="court_image" accept="image/*">
                                                    <small class="form-text">Upload a new image to replace the current one</small>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="court_name_<?php echo $index; ?>">Court Name</label>
                                                    <input type="text" id="court_name_<?php echo $index; ?>" name="court_name" value="<?php echo htmlspecialchars($court['name']); ?>" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="court_address_<?php echo $index; ?>">Court Address</label>
                                                    <input type="text" id="court_address_<?php echo $index; ?>" name="court_address" value="<?php echo htmlspecialchars($court['address']); ?>" required>
                                                </div>
                                                
                                                <button type="submit" name="update_court" class="btn-primary">Update Court</button>
                                                <button type="button" class="btn-danger" style="margin-left: 10px;" onclick="confirmCourtDeletion(<?php echo $index; ?>, '<?php echo htmlspecialchars($court['name']); ?>')">
                                                    <i class="fas fa-trash-alt"></i> Delete Court
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                
                <!-- Features Section -->
                <section id="features-section" class="settings-card" style="display: none;">
                    <div class="settings-header">
                        <h2><i class="fas fa-sliders-h"></i> Features Settings</h2>
                    </div>
                    <div class="settings-body">
                        <form method="POST" action="">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" id="piggy_bank_enabled" name="piggy_bank_enabled" <?php echo ($settings['piggy_bank_enabled'] ?? true) ? 'checked' : ''; ?>>
                                    <label for="piggy_bank_enabled">Enable Piggy Bank Feature (Alkansya)</label>
                                </div>
                                <p class="form-help">Track your earnings and savings with the piggy bank feature on the stats page.</p>
                            </div>
                            
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" id="hide_manual_booking" name="hide_manual_booking" <?php echo ($settings['hide_manual_booking'] ?? false) ? 'checked' : ''; ?>>
                                    <label for="hide_manual_booking">Hide Manual Booking Form</label>
                                </div>
                                <p class="form-help">Hide the manual booking form in the calendar page. Only bookings from Botpress will be shown.</p>
                            </div>

                            <button type="submit" name="update_features" class="btn-primary">Save Features</button>
                        </form>
                    </div>
                </section>
                
                <!-- Account Deletion Section -->
                <section id="delete-account-section" class="settings-card" style="display: none;">
                    <div class="settings-header">
                        <h2><i class="fas fa-user-slash"></i> Delete Account</h2>
                    </div>
                    <div class="settings-body">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> Deleting your account is permanent and cannot be undone. All your data, including courts and bookings, will be permanently deleted.
                        </div>
                        
                        <form method="POST" action="" onsubmit="return confirmAccountDeletion()">
                            <div class="form-group">
                                <label for="delete_confirmation">Type "DELETE" to confirm</label>
                                <input type="text" id="delete_confirmation" name="delete_confirmation" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="delete_password">Enter your password</label>
                                <div class="password-field">
                                    <input type="password" id="delete_password" name="delete_password" required>
                                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('delete_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" name="delete_account" class="btn-danger">Delete My Account</button>
                        </form>
                    </div>
                </section>
            </div>
               <form id="delete-court-form" method="POST" action="" style="display: none;">
                   <input type="hidden" name="delete_court" value="1">
                   <input type="hidden" name="court_index" id="delete_court_index" value="">
               </form>

        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="asset/theme.js?v=<?php echo time(); ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        <?php if (isset($_SESSION['password_changed']) && $_SESSION['password_changed']): ?>
            setTimeout(function() {
                window.location.href = "logout.php";
            }, 2000); 
            
            <?php unset($_SESSION['password_changed']); ?>
        <?php endif; ?>
        
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarNav = document.getElementById('sidebarNav');
        const mainContent = document.getElementById('mainContent');
        
        sidebarToggle.addEventListener('click', function() {
            sidebarNav.classList.toggle('expanded');
            mainContent.classList.toggle('expanded');
        });
        
        const menuItems = document.querySelectorAll('.settings-menu a');
        const sections = document.querySelectorAll('.settings-content section');
        
        menuItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                
                menuItems.forEach(i => i.classList.remove('active'));
                

                this.classList.add('active');
                
                const targetId = this.getAttribute('data-section');
                
                sections.forEach(section => {
                    section.style.display = 'none';
                });
                
                document.getElementById(targetId).style.display = 'block';
            });
        });
        
        sections.forEach((section, index) => {
            section.style.display = index === 0 ? 'block' : 'none';
        });

        if (window.location.hash) {
            const targetId = window.location.hash.substring(1);
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                const menuItem = document.querySelector(`.settings-menu a[data-section="${targetId}"]`);
                
                if (menuItem) {
                    menuItem.click();
                }
                
                setTimeout(() => {
                    targetElement.scrollIntoView({ behavior: 'smooth' });
                }, 100);
            }
        }

        if (window.location.hash && window.location.hash.startsWith('#court-')) {
            const courtId = window.location.hash.substring(1);
            const courtElement = document.getElementById(courtId);
            
            if (courtElement) {
                const courtsMenuItem = document.querySelector('.settings-menu a[data-section="courts-section"]');
                if (courtsMenuItem) {
                    courtsMenuItem.click();
                }
                
                const courtIndex = courtId.split('-')[1];
                toggleCourt(courtIndex);
                
                setTimeout(() => {
                    courtElement.scrollIntoView({ behavior: 'smooth' });
                }, 200);
            }
        }
    });
    
    function toggleCourt(index) {
        const content = document.getElementById(`court-content-${index}`);
        if (content) {
            content.classList.toggle('active');
        }
    }
    
    function confirmLogout() {
        if (confirm("Are you sure you want to logout?")) {
            window.location.href = "logout.php";
        }
    }

    const courtImageInputs = document.querySelectorAll('input[type="file"][name="court_image"]');
    
    courtImageInputs.forEach(input => {
        input.addEventListener('change', function() {
            const previewContainer = this.closest('.court-content').querySelector('.court-image-preview');
            
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewContainer.innerHTML = `<img src="${e.target.result}" alt="Court Image Preview">`;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });

    function showAddCourtForm() {
        document.getElementById('add-court-form').style.display = 'block';
    }

    function hideAddCourtForm() {
        document.getElementById('add-court-form').style.display = 'none';
    }
    
    function togglePasswordVisibility(inputId) {
        const passwordInput = document.getElementById(inputId);
        const icon = passwordInput.nextElementSibling.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    
    function confirmCourtDeletion(index, courtName) {
        if (confirm(`Are you sure you want to delete the court "${courtName}"? This action cannot be undone.`)) {
            // Set the court index in the hidden form
            document.getElementById('delete_court_index').value = index;
 
            document.getElementById('delete-court-form').submit();
        }
    }

    function confirmAccountDeletion() {
        const confirmation = document.getElementById('delete_confirmation').value;
        const password = document.getElementById('delete_password').value;
        
        if (confirmation !== 'DELETE') {
            alert("Please type 'DELETE' to confirm account deletion.");
            return false;
        }
        
        if (!password) {
            alert("Please enter your password.");
            return false;
        }
        
        return confirm("WARNING: This will permanently delete your account and all associated data. This action CANNOT be undone. Are you absolutely sure?");
    }
</script>

</body>
</html>
