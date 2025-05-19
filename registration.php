<?php
// Start session
session_start();

require_once 'connection.php';
require_once 'firebase.php';

$error = "";
$success = ""; 
$captchaError = "";

// Check if we're handling a verification token from the URL
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verify the token and create the account
    try {
        // Get the stored user data from the token
        $verificationRef = $database->getReference('email_verifications/' . $token);
        $verificationData = $verificationRef->getValue();
        
        if (!$verificationData) {
            $error = "Invalid or expired verification link. Please try registering again.";
        } else if (time() > $verificationData['expires']) {
            $error = "Verification link has expired. Please try registering again.";
            // Clean up expired token
            $verificationRef->remove();
        } else {
            // Token is valid, create the user account
            $userData = $verificationData['user_data'];
            
            try {
                // Create user with email and password
                $userProperties = [
                    'email' => $userData['email'],
                    'password' => $userData['password'],
                    'displayName' => $userData['name'],
                    'emailVerified' => true // Set email as verified
                ];
                
                $createdUser = $auth->createUser($userProperties);
                $userId = $createdUser->uid;
                
                // Save additional user data to database
                $dbUserData = [
                    'name' => $userData['name'],
                    'address' => $userData['address'],
                    'cpNumber' => $userData['cpNumber'],
                    'email' => $userData['email'],
                    'emailVerified' => true,
                    'createdAt' => date('Y-m-d H:i:s')
                ];
                
                $reference = $database->getReference('users/' . $userId);
                $reference->set($dbUserData);
                
                // Initialize user_courts structure
                $userCourtsRef = $database->getReference('user_courts/' . $userId);
                $userCourtsRef->set([
                    'setup_completed' => false,
                    'courts' => []
                ]);
                
                // Clean up verification data
                $verificationRef->remove();
                
                $success = "Your email has been verified and your account has been created successfully! You can now log in.";
            } catch (Exception $authError) {
                error_log("Auth error: " . $authError->getMessage());
                
                // Check for specific error messages
                if (strpos($authError->getMessage(), 'EMAIL_EXISTS') !== false) {
                    $error = "Email already in use. Please use a different email or try to login.";
                } else if (strpos($authError->getMessage(), 'WEAK_PASSWORD') !== false) {
                    $error = "Password is too weak. Please use a stronger password.";
                } else if (strpos($authError->getMessage(), 'INVALID_EMAIL') !== false) {
                    $error = "The email address is not valid. Please check and try again.";
                } else {
                    $error = "Registration failed: " . $authError->getMessage();
                }
            }
        }
    } catch (Exception $e) {
        error_log("Verification error: " . $e->getMessage());
        $error = "An error occurred during verification. Please try again.";
    }
}
// Handle registration form submission
else if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify captcha first
    if (!isset($_POST['captcha']) || !isset($_SESSION['captcha']) || $_POST['captcha'] != $_SESSION['captcha']) {
        $captchaError = "Invalid captcha code";
    } else {
        $name = $_POST["name"] ?? "";
        $address = $_POST["address"] ?? "";
        $cpNumber = $_POST["cpNumber"] ?? "";
        $email = $_POST["email"] ?? "";
        $password = $_POST["password"] ?? "";
        $confirmPassword = $_POST["confirmPassword"] ?? "";
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format. Please enter a valid email address.";
        }
        else if (empty($name) || empty($address) || 
            empty($cpNumber) || empty($email) || empty($password) || empty($confirmPassword)) {
            $error = "All fields are required";
        } else if ($password !== $confirmPassword) {
            $error = "Passwords do not match";
        } else {
            try {
                // Check if email already exists in Firebase
                try {
                    $user = $auth->getUserByEmail($email);
                    $error = "Email already in use. Please use a different email or try to login.";
                } catch (Exception $userNotFoundError) {
                    // Email doesn't exist, continue with registration
                    
                    // Generate a unique token for email verification
                    $token = bin2hex(random_bytes(32));
                    
                    // Store user data temporarily with the token
                    $verificationData = [
                        'user_data' => [
                            'name' => $name,
                            'address' => $address,
                            'cpNumber' => $cpNumber,
                            'email' => $email,
                            'password' => $password
                        ],
                        'created' => time(),
                        'expires' => time() + 86400, // 24 hours expiration
                    ];
                    
                    $database->getReference('email_verifications/' . $token)->set($verificationData);
                    
                    // Send verification email
                    $verificationLink = "https://" . $_SERVER['HTTP_HOST'] . "/registration.php?token=" . $token;
                    
                    // Configure email settings
                    $actionCodeSettings = [
                        'url' => $verificationLink,
                        'handleCodeInApp' => false,
                    ];
                    
                    // Send custom verification email
                    $emailSent = sendCustomVerificationEmail($email, $name, $verificationLink);
                    
                    if ($emailSent) {
                        $success = "Please check your email to verify your account. The verification link will expire in 24 hours.";
                    } else {
                        $error = "Failed to send verification email. Please try again.";
                    }
                }
            } catch (Exception $e) {
                error_log("General error: " . $e->getMessage());
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
    
    // Generate new captcha after submission
    $_SESSION['captcha'] = generateCaptcha();
}

// Function to generate captcha
function generateCaptcha() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $captcha = '';
    $length = 6;
    
    for ($i = 0; $i < $length; $i++) {
        $captcha .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $captcha;
}

// Generate captcha if not exists
if (!isset($_SESSION['captcha'])) {
    $_SESSION['captcha'] = generateCaptcha();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dribble - Create Account</title>
    <link rel="icon" href="asset/imoticon.png" type="image/png">
    <link rel="stylesheet" href="asset/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Modern captcha styling */
        .captcha-container {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .captcha-box {
            background: linear-gradient(145deg, #f0f0f0, #e6e6e6);
            border-radius: 8px;
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .captcha-code {
            font-family: 'Courier New', monospace;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 5px;
            color: #333;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.1);
            user-select: none;
            position: relative;
            background: rgba(255,255,255,0.8);
            padding: 8px 15px;
            border-radius: 4px;
        }
        
        .captcha-code::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: repeating-linear-gradient(
                45deg,
                rgba(0,0,0,0.02),
                rgba(0,0,0,0.02) 10px,
                rgba(0,0,0,0.04) 10px,
                rgba(0,0,0,0.04) 20px
            );
            z-index: -1;
            border-radius: 4px;
        }
        
        .refresh-captcha {
            background: #f8f8f8;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.2s;
            color: #555;
        }
        
        .refresh-captcha:hover {
            background: #ebebeb;
            color: #333;
        }
        
        .captcha-input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .captcha-error {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }
        
        /* Verification message styling */
        .verification-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border-left: 4px solid #28a745;
        }
        
        /* Verification success container */
        .verification-success-container {
            text-align: center;
            padding: 30px 20px;
        }
        
        .verification-icon {
            font-size: 60px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .verification-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .verification-subtitle {
            font-size: 16px;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        
        .login-button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .login-button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo">
            <a href="index.php"><img src="asset/drib.png" alt="Dribble"></a>
        </div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="#features">Features</a>
            <a href="#how-it-works">How It Works</a>
            <a href="login.php" class="login-btn">Sign In</a>
            <a href="registration.php" class="register-btn">Join Now</a>
        </div>
        <div class="menu-toggle">
            <i class="fas fa-bars"></i>
        </div>
    </nav>
    
    <div class="register-wrapper">
        <div class="register-container">
            <?php if (isset($_GET['token']) && !empty($_GET['token']) && !empty($success)): ?>
                <!-- Email Verification Success -->
                <div class="verification-success-container">
                    <div class="verification-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="verification-title">Email Verified!</div>
                    <div class="verification-subtitle">
                        Your email has been successfully verified and your account has been created.
                        You can now log in to access your account.
                    </div>
                    <a href="login.php" class="login-button">Go to Login</a>
                </div>
            <?php else: ?>
                <div class="login-header">CREATE ACCOUNT</div>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="success-message">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Registration Form -->
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input type="text" name="name" placeholder="Name" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <i class="fa-solid fa-location-dot input-icon"></i>
                            <input type="text" name="address" placeholder="Address" required>
                        </div>
                        
                        <div class="form-group">
                            <i class="fa-solid fa-phone input-icon"></i>
                            <input type="text" name="cpNumber" placeholder="Contact Number" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <i class="fa-solid fa-envelope input-icon"></i>
                        <input type="email" name="email" placeholder="Email Address" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="password" id="password" placeholder="Password" required>
                            <i class="fa-solid fa-eye password-toggle" id="togglePassword"></i>
                        </div>
                        
                        <div class="form-group">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="confirmPassword" id="confirmPassword" placeholder="Confirm Password" required>
                            <i class="fa-solid fa-eye password-toggle" id="toggleConfirmPassword"></i>
                        </div>
                    </div>
                    
                    <!-- Modern Captcha -->
                    <div class="captcha-container">
                        <div class="captcha-box">
                            <div class="captcha-code" id="captchaCode"><?php echo $_SESSION['captcha']; ?></div>
                            <button type="button" class="refresh-captcha" id="refreshCaptcha">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <input type="text" name="captcha" class="captcha-input" placeholder="Enter the code above" required>
                        <?php if (!empty($captchaError)): ?>
                            <div class="captcha-error"><?php echo $captchaError; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="signin-button">REGISTER</button>
                    
                    <div class="reg">
                        <a href="login.php">Already have an account? Sign in</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Popup Message -->
    <div id="popupMessage" class="popup" style="display: none;">
        <div class="popup-content">
            <p>Please check your email to verify your account.</p>
        </div>
    </div>

    <script>
        <?php if (!empty($success) && !isset($_GET['token'])): ?>
            // Show popup for email verification instructions
            document.getElementById("popupMessage").style.display = "flex";
            setTimeout(function() {
                document.getElementById("popupMessage").style.display = "none";
            }, 5000);
        <?php endif; ?>
        
        <?php if (!empty($success) && isset($_GET['token'])): ?>
            // Redirect to login after successful verification
            setTimeout(function() {
                window.location.href = 'login.php'; 
            }, 5000);
        <?php endif; ?>

        // Toggle password visibility for password field
        document.getElementById('togglePassword')?.addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = this;
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });

        // Toggle password visibility for confirm password field
        document.getElementById('toggleConfirmPassword')?.addEventListener('click', function() {
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const toggleIcon = this;
            
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                confirmPasswordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });
        
        // Mobile menu toggle
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.nav-links').classList.toggle('active');
        });
        
        // Refresh captcha
        document.getElementById('refreshCaptcha')?.addEventListener('click', function() {
            fetch('refresh-captcha.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('captchaCode').textContent = data;
                });
        });
        //MAY ERROR PARIN SA CAPTCHA TSKA DIPA PUMAPASOK SA DATABASE 
    </script>
</body>
</html>
