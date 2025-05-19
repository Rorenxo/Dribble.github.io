<?php
session_start();
if (!isset($_SESSION['user_id'])) {
   header("Location: login.php");
   exit();
}
require_once 'connection.php';

function verifyFirebaseConnection() {
    global $database;
    
    try {
        $testRef = $database->getReference('connection_test');
        $timestamp = date('Y-m-d H:i:s');
        $testRef->set([
            'connection_test' => true, 
            'timestamp' => $timestamp,
            'message' => 'Testing connection'
        ]);
        
        // Verify we can read back the data
        $result = $testRef->getValue();
        
        if ($result && isset($result['timestamp']) && $result['timestamp'] === $timestamp) {
            error_log('Firebase connection verified successfully');
            return true;
        } else {
            error_log('Firebase connection test failed: Could not read back test data');
            return false;
        }
    } catch (Exception $e) {
        error_log('Firebase connection error: ' . $e->getMessage());
        return false;
    }
}

// Call the verification function
$firebaseConnected = verifyFirebaseConnection();
if (!$firebaseConnected) {
    error_log('WARNING: Firebase connection could not be verified. Data saving may fail.');
}

// Test Firebase connection
try {
    $testRef = $database->getReference('test');
    $testRef->set(['connection_test' => true, 'timestamp' => date('Y-m-d H:i:s')]);
    error_log('Firebase connection successful');
} catch (Exception $e) {
    error_log('Firebase connection error: ' . $e->getMessage());
}

if (!isset($_SESSION['conversation_state'])) {
    $_SESSION['conversation_state'] = 'greeting';
    $_SESSION['booking_data'] = [];
}

if (isset($_POST['action']) && $_POST['action'] === 'chat') {
    $userMessage = trim($_POST['message']);
    $response = processMessage($userMessage, $_SESSION['conversation_state'], $_SESSION['booking_data']);
    
    header('Content-Type: application/json');
    echo json_encode([
        'message' => $response,
        'state' => $_SESSION['conversation_state'],
        'showCourtButtons' => $_SESSION['conversation_state'] === 'court_selection'
    ]);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'reset') {
    $_SESSION['conversation_state'] = 'greeting';
    $_SESSION['booking_data'] = [];
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => "Hello! I'm dribble. I'll help you reserve a court. What's the name of the reservist?"
    ]);
    exit;
}

// Handle court selection
if (isset($_POST['action']) && $_POST['action'] === 'select_court') {
    $courtId = isset($_POST['court_id']) ? $_POST['court_id'] : '';
    $courtName = isset($_POST['court_name']) ? $_POST['court_name'] : '';
    
    // Log the received data for debugging
    error_log("Court selection request received - ID: $courtId, Name: $courtName");
    
    if (!empty($courtId) && !empty($courtName)) {
        try {
            // Verify Firebase connection before proceeding
            $testRef = $database->getReference('test_connection');
            $testRef->set(['timestamp' => date('Y-m-d H:i:s')]);
            
            // Store the court data in the session
            $_SESSION['booking_data']['court'] = $courtName;
            $_SESSION['booking_data']['court_id'] = $courtId;
            $_SESSION['conversation_state'] = 'date_selection';
            
            $response = "Great choice! You've selected {$courtName}. What date would you like to book? (You can type like 'May 10' or 'tomorrow')";
            
            // Log the successful selection
            error_log("Court selection successful - ID: $courtId, Name: $courtName");
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $response,
                'state' => $_SESSION['conversation_state']
            ]);
            exit;
        } catch (Exception $e) {
            // Log the Firebase error
            error_log("Firebase error during court selection: " . $e->getMessage());
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => "I'm sorry, I couldn't process that court selection due to a database error. Please try again.",
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
    
    // Log the failed selection
    error_log("Court selection failed - ID: $courtId, Name: $courtName");
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "I'm sorry, I couldn't process that court selection. Please try again."
    ]);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'get_courts') {
    $user_id = $_SESSION['user_id'];
    $userCourtsRef = $database->getReference('user_courts/' . $user_id);
    $userCourts = $userCourtsRef->getValue();
    
    $courts = [];
    if ($userCourts && isset($userCourts['courts'])) {
        foreach ($userCourts['courts'] as $index => $court) {
            if (!isset($court['status']) || $court['status'] === 'available') {
                // Use the court's ID if it exists, otherwise use the index
                $courtId = isset($court['id']) ? $court['id'] : $index;
                $courts[] = [
                    'id' => $courtId,
                    'name' => $court['name']
                ];
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'courts' => $courts
    ]);
    exit;
}

function processMessage($message, &$state, &$bookingData) {
    global $database;
    $response = '';
    
    switch ($state) {
        case 'greeting':
            $bookingData['name'] = $message;
            $response = "So the name is, {$message}!,Now please select a court from the available options below:";
            $state = 'court_selection';
            break;
            
        case 'court_selection':
            $bookingData['court'] = $message;
            $response = "Great choice! What date would you like to book {$message}? (You can type like 'May 10' or 'tomorrow')";
            $state = 'date_selection';
            break;
            
        case 'date_selection':
            $parsedDate = parseNaturalDate($message);
            if ($parsedDate) {
                $bookingData['date'] = $parsedDate;
                $formattedDate = date('F j, Y', strtotime($parsedDate));
                $response = "Perfect! You've selected {$formattedDate}. What time would you like to start? (You can type like '1pm' or '13:30')";
                $state = 'start_time';
            } else {
                $response = "I'm sorry, I couldn't understand that date. Please try again with a format like 'May 10' or 'next Monday'.";
            }
            break;
            
        case 'start_time':
            $parsedTime = parseNaturalTime($message);
            if ($parsedTime) {
                $bookingData['start_time'] = $parsedTime;
                $formattedTime = date('g:i A', strtotime($parsedTime));
                $response = "Great! Starting at {$formattedTime}. And what time would you like to end? (You can type like '2:30pm' or '14:30')";
                $state = 'end_time';
            } else {
                $response = "I'm sorry, I couldn't understand that time. Please try again with a format like '1pm' or '13:30'.";
            }
            break;
            
        case 'end_time':
            $parsedTime = parseNaturalTime($message);
            if ($parsedTime && isEndTimeAfterStartTime($bookingData['start_time'], $parsedTime)) {
                $bookingData['end_time'] = $parsedTime;
                
                $response = generateBookingSummary($bookingData);
                $state = 'confirmation';
            } else if (!$parsedTime) {
                $response = "I'm sorry, I couldn't understand that time. Please try again with a format like '2:30pm' or '14:30'.";
            } else {
                $response = "The end time must be after the start time. Please enter a valid end time.";
            }
            break;
            
        case 'confirmation':
            $lowerMessage = strtolower($message);
            
            if (strpos($lowerMessage, 'confirm') !== false || $lowerMessage === 'yes') {
                $startTimeFormatted = date('g:i A', strtotime($bookingData['start_time']));
                $endTimeFormatted = date('g:i A', strtotime($bookingData['end_time']));
                $dateFormatted = date('F j, Y', strtotime($bookingData['date']));
                
                // Log the booking data before saving
                error_log('Booking data before saving: ' . json_encode($bookingData));
                
                // Check if court_id exists
                if (empty($bookingData['court_id'])) {
                    error_log('Warning: court_id is empty in booking data, setting default value');
                    $bookingData['court_id'] = '0'; // Set a default value
                }
                
                // Verify Firebase connection before saving
                $connectionTest = verifyFirebaseConnection();
                if (!$connectionTest) {
                    error_log('Firebase connection test failed before saving booking');
                    $response = "I'm sorry, there seems to be an issue with our database connection. Please try again later.";
                    break;
                }
                
                // Save the booking to Firebase
                $saveResult = saveBookingToFirebase($bookingData);
                
                if ($saveResult) {
                    $response = "Excellent! Your booking has been confirmed. Court {$bookingData['court']} is reserved for {$bookingData['name']} on {$dateFormatted} from {$startTimeFormatted} to {$endTimeFormatted}. Thank you for using our booking service! Is there anything else I can help you with?";
                    
                    // Also save a copy to a backup location for redundancy
                    try {
                        $backupRef = $database->getReference('bookings_backup/' . $_SESSION['user_id']);
                        $backupKey = uniqid('backup_');
                        $backupRef->getChild($backupKey)->set([
                            'customer_name' => $bookingData['name'],
                            'court' => $bookingData['court'],
                            'court_id' => $bookingData['court_id'],
                            'date' => $bookingData['date'],
                            'start_time' => date('Y-m-d H:i:s', strtotime($bookingData['date'] . ' ' . $bookingData['start_time'])),
                            'end_time' => date('Y-m-d H:i:s', strtotime($bookingData['date'] . ' ' . $bookingData['end_time'])),
                            'status' => 'confirmed',
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        error_log('Backup booking saved successfully with key: ' . $backupKey);
                    } catch (Exception $e) {
                        error_log('Error saving backup booking: ' . $e->getMessage());
                        // Continue even if backup fails
                    }
                } else {
                    error_log('Failed to save booking to Firebase');
                    
                    // Try one more direct approach
                    try {
                        $directRef = $database->getReference('direct_bookings/' . $_SESSION['user_id'] . '/' . uniqid());
                        $directRef->set([
                            'customer_name' => $bookingData['name'],
                            'court' => $bookingData['court'],
                            'court_id' => $bookingData['court_id'],
                            'date' => $bookingData['date'],
                            'start_time' => $bookingData['start_time'],
                            'end_time' => $bookingData['end_time'],
                            'status' => 'confirmed',
                            'created_at' => date('Y-m-d H:i:s'),
                            'direct_save' => true
                        ]);
                        
                        error_log('Direct booking save attempt completed');
                        $response = "Your booking has been confirmed, but there might have been a syncing issue. Court {$bookingData['court']} is reserved for {$bookingData['name']} on {$dateFormatted} from {$startTimeFormatted} to {$endTimeFormatted}. Thank you for using our booking service!";
                    } catch (Exception $e) {
                        error_log('Direct booking save also failed: ' . $e->getMessage());
                        $response = "I'm sorry, there was an issue saving your booking. Please try again later.";
                    }
                }
                
                $state = 'completed';
                
            } else if (strpos($lowerMessage, 'edit') !== false) {
                $response = "No problem! What would you like to edit? Please type: 'name', 'court', 'date', 'start time', or 'end time'.";
                $state = 'edit_selection';
            } else if (strpos($lowerMessage, 'cancel') !== false) {
                $response = "I've cancelled this booking request. Is there anything else I can help you with?";
                $state = 'greeting';
                $bookingData = [];
            } else {
                $response = "I didn't understand that. Please type 'confirm' to proceed with the booking, 'edit' to make changes, or 'cancel' to cancel the booking.";
            }
            break;
            
        case 'edit_selection':
            $lowerMessage = strtolower($message);
            
            if ($lowerMessage === 'name') {
                $response = "What is the name?";
                $state = 'edit_name';
            } else if ($lowerMessage === 'court') {
                $response = "Please select a court from the available options below:";
                $state = 'court_selection';
            } else if ($lowerMessage === 'date') {
                $response = "On what date would you like to book? (You can type like 'May 10' or 'tomorrow')";
                $state = 'edit_date';
            } else if ($lowerMessage === 'start time') {
                $response = "What time would you like to start? (You can type like '1pm' or '13:30')";
                $state = 'edit_start_time';
            } else if ($lowerMessage === 'end time') {
                $response = "What time would you like to end? (You can type like '2:30pm' or '14:30')";
                $state = 'edit_end_time';
            } else {
                $response = "I didn't understand that. Please type one of the following: 'name', 'court', 'date', 'start time', or 'end time'.";
            }
            break;
            
        case 'edit_name':
            $bookingData['name'] = $message;
            $response = generateBookingSummary($bookingData);
            $state = 'confirmation';
            break;
            
        case 'edit_court':
            $bookingData['court'] = $message;
            $response = generateBookingSummary($bookingData);
            $state = 'confirmation';
            break;
            
        case 'edit_date':
            $parsedDate = parseNaturalDate($message);
            if ($parsedDate) {
                $bookingData['date'] = $parsedDate;
                $response = generateBookingSummary($bookingData);
                $state = 'confirmation';
            } else {
                $response = "I'm sorry, I couldn't understand that date. Please try again with a format like 'May 10' or 'next Monday'.";
            }
            break;
            
        case 'edit_start_time':
            $parsedTime = parseNaturalTime($message);
            if ($parsedTime) {
                $bookingData['start_time'] = $parsedTime;
                $response = generateBookingSummary($bookingData);
                $state = 'confirmation';
            } else {
                $response = "I'm sorry, I couldn't understand that time. Please try again with a format like '1pm' or '13:30'.";
            }
            break;
            
        case 'edit_end_time':
            $parsedTime = parseNaturalTime($message);
            if ($parsedTime && isEndTimeAfterStartTime($bookingData['start_time'], $parsedTime)) {
                $bookingData['end_time'] = $parsedTime;
                $response = generateBookingSummary($bookingData);
                $state = 'confirmation';
            } else if (!$parsedTime) {
                $response = "I'm sorry, I couldn't understand that time. Please try again with a format like '2:30pm' or '14:30'.";
            } else {
                $response = "The end time must be after the start time. Please enter a valid end time.";
            }
            break;
            
        case 'completed':
            $lowerMessage = strtolower($message);
            
            if (strpos($lowerMessage, 'yes') !== false || strpos($lowerMessage, 'another') !== false) {
                $response = "Great! Let's start a new booking. What's the name of the reservist?";
                $state = 'greeting';
                $bookingData = [];
            } else if (strpos($lowerMessage, 'no') !== false || strpos($lowerMessage, 'thank') !== false) {
                $response = "Thank you for using our court booking service! Have a wonderful day. If you need to book again, just let me know.";
            } else {
                $response = "Is there anything else I can help you with? If you'd like to make another booking, just let me know.";
            }
            break;
            
        default:
            $response = "I'm sorry, I didn't understand that. Let's start over. What's the name of the reservist?";
            $state = 'greeting';
            $bookingData = [];
    }
    
    $_SESSION['conversation_state'] = $state;
    $_SESSION['booking_data'] = $bookingData;
    
    return $response;
}

function saveBookingToFirebase($bookingData) {
    global $database;
    
    try {
        // Validate required booking data
        if (empty($bookingData['name']) || empty($bookingData['court']) || 
            empty($bookingData['date']) || empty($bookingData['start_time']) || 
            empty($bookingData['end_time'])) {
            error_log('Missing required booking data fields: ' . json_encode($bookingData));
            return false;
        }
        
        $user_id = $_SESSION['user_id'];
        if (empty($user_id)) {
            error_log('User ID not found in session');
            return false;
        }
        
        // Format the data for Firebase
        $formattedData = [
            'customer_name' => $bookingData['name'],
            'court' => $bookingData['court'],
            'court_id' => $bookingData['court_id'] ?? '0', // Default to '0' if not set
            'date' => $bookingData['date'],
            'start_time' => date('Y-m-d H:i:s', strtotime($bookingData['date'] . ' ' . $bookingData['start_time'])),
            'end_time' => date('Y-m-d H:i:s', strtotime($bookingData['date'] . ' ' . $bookingData['end_time'])),
            'status' => 'confirmed',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Log the data being saved
        error_log('Attempting to save booking data: ' . json_encode($formattedData));
        
        // Test Firebase connection before saving
        $testRef = $database->getReference('connection_test');
        $testRef->set(['timestamp' => date('Y-m-d H:i:s')]);
        
        // Make sure we're using the correct path to the bookings node
        $bookingsRef = $database->getReference('bookings/' . $user_id);
        
        // Use set instead of push if there are issues with push
        $newBookingKey = uniqid('booking_');
        $bookingsRef->getChild($newBookingKey)->set($formattedData);
        
        // Verify the data was saved by reading it back
        $savedData = $database->getReference('bookings/' . $user_id . '/' . $newBookingKey)->getValue();
        
        if ($savedData) {
            error_log('Verified booking data was saved correctly with key: ' . $newBookingKey);
            return true;
        } else {
            // Try an alternative approach if the first method failed
            error_log('First save attempt failed, trying alternative method');
            
            // Try direct path setting
            $database->getReference('bookings/' . $user_id . '/' . $newBookingKey)->set($formattedData);
            
            // Check again
            $savedData = $database->getReference('bookings/' . $user_id . '/' . $newBookingKey)->getValue();
            
            if ($savedData) {
                error_log('Alternative save method successful');
                return true;
            }
            
            error_log('Could not verify booking data was saved after multiple attempts');
            return false;
        }
        
    } catch (Exception $e) {
        error_log('Error saving booking to Firebase: ' . $e->getMessage());
        error_log('Error trace: ' . $e->getTraceAsString());
        return false;
    }
}

function debugFirebaseConnection() {
    global $database;
    
    try {
        $testData = [
            'test_message' => 'Testing Firebase connection',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $testRef = $database->getReference('debug/connection_test');
        $testRef->set($testData);
        
        $result = $testRef->getValue();
        
        if ($result && isset($result['test_message'])) {
            error_log('Firebase connection test successful: ' . json_encode($result));
            return true;
        } else {
            error_log('Firebase connection test failed: Could not read back test data');
            return false;
        }
    } catch (Exception $e) {
        error_log('Firebase connection test error: ' . $e->getMessage());
        return false;
    }
}

// Call this function to test the connection
debugFirebaseConnection();

function checkCourtAvailability($courtId, $date, $startTime, $endTime) {
    global $database;
    
    try {
        $user_id = $_SESSION['user_id'];
        
        $requestStart = strtotime($date . ' ' . $startTime);
        $requestEnd = strtotime($date . ' ' . $endTime);
        
        $bookingsRef = $database->getReference('bookings/' . $user_id)
            ->orderByChild('court_id')
            ->equalTo($courtId);
        
        $bookings = $bookingsRef->getValue();
        
        if (!$bookings) {
            return true;
        }
        
        foreach ($bookings as $booking) {
            if (date('Y-m-d', strtotime($booking['start_time'])) !== $date) {
                continue;
            }
            
            if (isset($booking['status']) && $booking['status'] === 'cancelled') {
                continue;
            }
            
            $bookingStart = strtotime($booking['start_time']);
            $bookingEnd = strtotime($booking['end_time']);
            
            if (
                ($requestStart >= $bookingStart && $requestStart < $bookingEnd) ||
                ($requestEnd > $bookingStart && $requestEnd <= $bookingEnd) ||
                ($requestStart <= $bookingStart && $requestEnd >= $bookingEnd)
            ) {
                return false;
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log('Error checking court availability: ' . $e->getMessage());
        return false;
    }
}

function parseNaturalDate($dateStr) {
    $dateStr = trim(strtolower($dateStr));
    
    $timestamp = strtotime($dateStr);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    $months = [
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
        'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
        'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12
    ];
    
    if (preg_match('/([a-z]+)\s+(\d{1,2})/i', $dateStr, $matches)) {
        $monthStr = strtolower($matches[1]);
        $day = (int)$matches[2];
        
        if (isset($months[$monthStr]) && $day >= 1 && $day <= 31) {
            $month = $months[$monthStr];
            $year = date('Y');
            
            $dateObj = DateTime::createFromFormat('Y-n-j', "$year-$month-$day");
            if ($dateObj && $dateObj < new DateTime()) {
                $year++;
            }
            
            return date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
        }
    } else if (preg_match('/(\d{1,2})\s+([a-z]+)/i', $dateStr, $matches)) {
        $day = (int)$matches[1];
        $monthStr = strtolower($matches[2]);
        
        if (isset($months[$monthStr]) && $day >= 1 && $day <= 31) {
            $month = $months[$monthStr];
            $year = date('Y');
            
            $dateObj = DateTime::createFromFormat('Y-n-j', "$year-$month-$day");
            if ($dateObj && $dateObj < new DateTime()) {
                $year++;
            }
            
            return date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
        }
    }
    
    return false;
}

function parseNaturalTime($timeStr) {
    $timeStr = trim(strtolower($timeStr));
    
    $timestamp = strtotime("today $timeStr");
    if ($timestamp !== false) {
        return date('H:i', $timestamp);
    }
    
    if (preg_match('/(\d{1,2})(?::(\d{2}))?(?:\s*)?(am|pm)/i', $timeStr, $matches)) {
        $hour = (int)$matches[1];
        $minute = isset($matches[2]) ? (int)$matches[2] : 0;
        $meridiem = strtolower($matches[3]);
        
        if ($meridiem === 'pm' && $hour < 12) {
            $hour += 12;
        } else if ($meridiem === 'am' && $hour === 12) {
            $hour = 0;
        }
        
        if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
            return sprintf('%02d:%02d', $hour, $minute);
        }
    }
    
    if (preg_match('/^(\d{1,2})(?::(\d{2}))?$/', $timeStr, $matches)) {
        $hour = (int)$matches[1];
        $minute = isset($matches[2]) ? (int)$matches[2] : 0;
        
        if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
            return sprintf('%02d:%02d', $hour, $minute);
        }
    }
    
    return false;
}

function isEndTimeAfterStartTime($startTime, $endTime) {
    $start = DateTime::createFromFormat('H:i', $startTime);
    $end = DateTime::createFromFormat('H:i', $endTime);
    
    return $end > $start;
}

function generateBookingSummary($bookingData) {
    $dateFormatted = date('F j, Y', strtotime($bookingData['date']));
    $startTimeFormatted = date('g:i A', strtotime($bookingData['start_time']));
    $endTimeFormatted = date('g:i A', strtotime($bookingData['end_time']));
    
    $summary = "Here's a summary of your booking:

Name: {$bookingData['name']}
Court: {$bookingData['court']}
Date: {$dateFormatted}
Time: {$startTimeFormatted} to {$endTimeFormatted}

Is this correct? Please type 'confirm' to proceed, 'edit' to make changes, or 'cancel' to cancel the booking.";

    return $summary;
}

$user_id = $_SESSION['user_id'];

$userCourtsRef = $database->getReference('user_courts/' . $user_id);
$userCourts = $userCourtsRef->getValue();

if ($userCourts === null || !isset($userCourts['setup_completed']) || $userCourts['setup_completed'] !== true) {
   header("Location: setup.php");
   exit();
}

$userRef = $database->getReference('users/' . $user_id);
$userData = $userRef->getValue();

$courts = $userCourts['courts'] ?? [];

$courtsForJs = [];
foreach ($courts as $index => $court) {
    // Use the court's ID if it exists, otherwise use the index
    $courtId = isset($court['id']) ? $court['id'] : $index;
    $courtsForJs[] = [
        'id' => $courtId,
        'name' => $court['name'],
        'address' => $court['address'],
        'status' => $court['status'] ?? 'available'
    ];
}
$courtsJson = json_encode($courtsForJs);

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Dribble</title>
    <link rel="icon" href="asset/imoticon.png" type="image/png">
    <link rel="stylesheet" href="asset/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/main.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/navbar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/chatbot.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        .court-selection-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            margin-bottom: 5px;
        }
        
        .court-button {
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 16px;
            padding: 8px 12px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .court-button:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .court-button:active {
            transform: translateY(0);
        }
    </style>
</head>
<b>
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

<div class="sidebar-nav" id="sidebarNav">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
       <div class="nav-item">
           <a href="Dashboard.php" class="nav-link <?php echo $current_page == 'Dashboard.php' ? 'active' : ''; ?>">
               <i class="fas fa-home"></i>
               <span>Home</span>
           </a>
       </div>
       <div class="nav-item">
           <a href="stats.php" class="nav-link <?php echo $current_page == 'stats.php' ? 'active' : ''; ?>">
               <i class="fa-solid fa-chart-simple"></i>
               <span>Stats</span>
           </a>
       </div>
       <div class="nav-item">
           <a href="calendar.php" class="nav-link <?php echo $current_page == 'calendar.php' ? 'active' : ''; ?>">
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
</div>
   <div class="main-content" id="mainContent">
       <div class="dashboard-layout">
           <div class="court-container">
               <div class="dashboard-header">
                   <div>
                       <p class="dashboard-subtitle">Your Court</p>
                   </div>
                   <div class="quick-actions">
                       <a href="calendar.php" class="btn-action">
                           <i class="fas fa-calendar-plus"></i>
                           <span>New Booking</span>
                       </a>
                   </div>
               </div>
               
               <div class="courts-grid">
                   <?php if (empty($courts)): ?>
                       <div class="empty-courts">
                           <i class="fas fa-basketball-ball"></i>
                           <p>No courts found. Please add courts in the settings.</p>
                           <a href="settings.php" class="btn-primary">Go to Settings</a>
                       </div>
                   <?php else: ?>
                    <?php foreach ($courts as $index => $court): ?>
    <?php 
        $courtStatus = isset($court['status']) ? $court['status'] : 'available';
        $isDisabled = in_array($courtStatus, ['unavailable', 'disabled', 'maintenance']);
        $statusText = '';
        
        switch ($courtStatus) {
            case 'unavailable':
                $statusText = 'Unavailable';
                break;
            case 'maintenance':
                $statusText = 'Under Maintenance';
                break;
            case 'disabled':
                $statusText = 'Disabled';
                break;
        }
    ?>
    <div class="court-card <?php echo $courtStatus; ?>">
        <div class="court-image">
            <?php if (!empty($court['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($court['image_url']); ?>" alt="<?php echo htmlspecialchars($court['name']); ?>" onerror="this.onerror=null; this.src='asset/images/courts/placeholder.jpg';">
            <?php else: ?>
                <div class="no-image">
                    <i class="fas fa-basketball-ball"></i>
                    <span>No Image</span>
                </div>
            <?php endif; ?>
            <?php if ($isDisabled): ?>
                <div class="court-status-badge"><?php echo $statusText; ?></div>
            <?php endif; ?>
        </div>
        <div class="court-details">
            <h3><?php echo htmlspecialchars($court['name']); ?></h3>
            <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($court['address']); ?></p>
            <div class="court-actions">
                <?php if (!$isDisabled): ?>
                    <a href="#" class="btn-view" onclick="viewBookings(<?php echo isset($court['id']) ? $court['id'] : $index; ?>, '<?php echo htmlspecialchars($court['name']); ?>')">
                        <i class="fas fa-calendar-alt"></i> View Bookings
                    </a>
                <?php else: ?>
                    <span class="btn-view" style="opacity: 0.5; cursor: not-allowed;">
                        <i class="fas fa-calendar-alt"></i> View Bookings
                    </span>
                <?php endif; ?>
                <a href="settings.php#court-<?php echo $index; ?>" class="btn-edit">
                    <i class="fas fa-edit"></i> Edit
                </a>
            </div>
        </div>
    </div>
                    <?php endforeach; ?>
                   <?php endif; ?>
               </div>
           </div>

           <div class="chat-container">
               <div class="chatbot-container">
                   <div class="chatbot-wrapper">
                       <div class="chatbot-header">
                           <div class="chatbot-header-content">
                               <h1>dribble AI</h1>
                               <p>Scheduler companion</p>
                           </div>
                           <button id="resetChat" class="chatbot-reset" title="Reset conversation">
                               <i class="fas fa-redo-alt"></i>
                           </button>
                       </div>
                       
                       <div id="chat-container" class="chatbot-messages">
                           <div class="message bot-message">
                               <div class="message-avatar">
                                   <img src="asset/ball.jpg" alt="Bot Avatar">
                               </div>
                               <div class="message-content">
                                   <p>Hello! I'm dribble. I'll help you reserve a court. What's the name of the reservist?</p>
                               </div>
                           </div>
                       </div>
                       
                       <div class="chatbot-input">
                           <form id="chat-form">
                               <input 
                                   type="text" 
                                   name="message" 
                                   id="user-input" 
                                   placeholder="Type your message..." 
                                   required
                               >
                               <button type="submit">
                                   <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                       <line x1="22" y1="2" x2="11" y2="13"></line>
                                       <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                   </svg>
                               </button>
                           </form>
                       </div>
                   </div>
               </div>
           </div>
       </div>
   </div>

   <input type="hidden" id="user_id" value="<?php echo $user_id; ?>">

   <div id="bookingModal" class="booking-modal">
       <div class="booking-modal-content">
           <div class="booking-modal-header">
               <h2 id="bookingModalTitle">Court Bookings</h2>
               <span class="booking-close">&times;</span>
           </div>
           <div class="booking-date-filter">
               <label for="bookingDateFilter">Filter by date:</label>
               <input type="date" id="bookingDateFilter" value="<?php echo date('Y-m-d'); ?>">
           </div>
           <div id="bookingList" class="booking-list">
               <div class="no-bookings">
                   <i class="fas fa-spinner fa-spin"></i>
                   <p>Loading bookings...</p>
               </div>
           </div>
       </div>
   </div>

   <script src="asset/theme.js?v=<?php echo time(); ?>"></script>
   <script>
       document.addEventListener('DOMContentLoaded', function() {
           const sidebarToggle = document.getElementById('sidebarToggle');
           const sidebarNav = document.getElementById('sidebarNav');
           const mainContent = document.getElementById('mainContent');
           
           sidebarToggle.addEventListener('click', function() {
               sidebarNav.classList.toggle('expanded');
               mainContent.classList.toggle('expanded');
           });
           
           const bookingModal = document.getElementById('bookingModal');
           const bookingModalTitle = document.getElementById('bookingModalTitle');
           const bookingList = document.getElementById('bookingList');
           const bookingDateFilter = document.getElementById('bookingDateFilter');
           const bookingClose = document.querySelector('.booking-close');
           
           bookingClose.addEventListener('click', function() {
               bookingModal.style.display = 'none';
           });
           
           window.addEventListener('click', function(event) {
               if (event.target == bookingModal) {
                   bookingModal.style.display = 'none';
               }
           });
           
           bookingDateFilter.addEventListener('change', function() {
               const courtIndex = bookingModal.getAttribute('data-court-index');
               if (courtIndex) {
                   loadBookingsForCourt(courtIndex);
               }
           });
           
           function formatDateTime(dateTimeStr) {
               const date = new Date(dateTimeStr);
               return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
           }
           
           function getStatusClass(status) {
               status = status.toLowerCase();
               if (status === 'confirmed') return 'booking-status-confirmed';
               if (status === 'pending') return 'booking-status-pending';
               if (status === 'cancelled') return 'booking-status-cancelled';
               return '';
           }
           
           function loadBookingsForCourt(courtIndex) {
               const selectedDate = bookingDateFilter.value;
               
               bookingList.innerHTML = `
                   <div class="no-bookings">
                       <i class="fas fa-spinner fa-spin"></i>
                       <p>Loading bookings...</p>
                   </div>
               `;
               
               fetch(`fetch-bookings.php?courtIndex=${courtIndex}&date=${selectedDate}`)
                   .then(response => response.json())
                   .then(bookings => {
                       if (bookings && bookings.length > 0) {
                           bookings.sort((a, b) => new Date(a.start_time) - new Date(b.start_time));
                           
                           let bookingsHtml = '';
                           bookings.forEach(booking => {
                               const startTime = formatDateTime(booking.start_time);
                               const endTime = formatDateTime(booking.end_time);
                               
                               bookingsHtml += `
                                   <div class="booking-item">
                                       <h3>${booking.customer_name}</h3>
                                       <p><i class="fas fa-clock"></i> ${startTime} - ${endTime}</p>
                                       <p>
                                           <span class="booking-status ${getStatusClass(booking.status)}">
                                               ${booking.status}
                                           </span>
                                       </p>
                                   </div>
                               `;
                           });
                           
                           bookingList.innerHTML = bookingsHtml;
                       } else {
                           bookingList.innerHTML = `
                               <div class="no-bookings">
                                   <i class="fas fa-calendar-times"></i>
                                   <p>No bookings found for this date</p>
                               </div>
                           `;
                       }
                   })
                   .catch(error => {
                       console.error('Error fetching bookings:', error);
                       bookingList.innerHTML = `
                           <div class="no-bookings">
                               <i class="fas fa-exclamation-triangle"></i>
                               <p>Error loading bookings. Please try again.</p>
                           </div>
                       `;
                   });
           }
           
           window.viewBookings = function(courtIndex, courtName) {
               bookingModalTitle.textContent = `Bookings for ${courtName}`;
               bookingModal.setAttribute('data-court-index', courtIndex);
               
               bookingDateFilter.value = new Date().toISOString().split('T')[0];
               
               loadBookingsForCourt(courtIndex);
               
               bookingModal.style.display = 'block';
           };
           
           function confirmLogout() {
               if (confirm("Are you sure you want to logout?")) {
                   window.location.href = "logout.php";
               }
           }
           
           const chatContainer = document.getElementById('chat-container');
           const chatForm = document.getElementById('chat-form');
           const userInput = document.getElementById('user-input');
           const resetChat = document.getElementById('resetChat');
           
           function scrollToBottom() {
               chatContainer.scrollTop = chatContainer.scrollHeight;
           }
           
           scrollToBottom();
           
           function loadAndDisplayCourtButtons() {
               fetch('Dashboard.php', {
                   method: 'POST',
                   headers: {
                       'Content-Type': 'application/x-www-form-urlencoded',
                   },
                   body: 'action=get_courts'
               })
               .then(response => response.json())
               .then(data => {
                   if (data.success && data.courts && data.courts.length > 0) {
                       let buttonsHtml = '<div class="court-selection-buttons">';
                       
                       data.courts.forEach(court => {
                           buttonsHtml += `
                               <button type="button" class="court-button" 
                                   data-court-id="${court.id}" 
                                   data-court-name="${escapeHtml(court.name)}"
                                   onclick="selectCourt('${court.id}', '${escapeHtml(court.name).replace(/'/g, "\\'").replace(/"/g, '\\"')}')">
                                   ${escapeHtml(court.name)}
                               </button>
                           `;
                       });
                       
                       buttonsHtml += '</div>';
                       const lastMessage = chatContainer.querySelector('.message:last-child .message-content');
                       if (lastMessage) {
                           lastMessage.insertAdjacentHTML('beforeend', buttonsHtml);
                       }
                       
                       scrollToBottom();
                   }
               })
               .catch(error => {
                   console.error('Error loading courts:', error);
               });
           }
           
           window.selectCourt = function(courtId, courtName) {
               // Log for debugging
               console.log("Court selected:", courtId, courtName);
               
               // Remove court buttons
               const courtButtons = document.querySelector('.court-selection-buttons');
               if (courtButtons) {
                   courtButtons.remove();
               }
               
               // Add user message
               const userMessageHtml = `
                   <div class="message user-message">
                       <div class="message-content">
                           <p>${escapeHtml(courtName)}</p>
                       </div>
                       <div class="message-avatar">
                           <img src="asset/user.png" alt="User Avatar">
                       </div>
                   </div>
               `;
               
               chatContainer.insertAdjacentHTML('beforeend', userMessageHtml);
               scrollToBottom();
               
               // Show typing indicator
               const typingIndicatorHtml = `
                   <div class="typing-indicator" id="typing-indicator">
                       <span></span>
                       <span></span>
                       <span></span>
                   </div>
               `;
               
               chatContainer.insertAdjacentHTML('beforeend', typingIndicatorHtml);
               scrollToBottom();
               
               // Prepare form data instead of URL encoding
               const formData = new FormData();
               formData.append('action', 'select_court');
               formData.append('court_id', courtId);
               formData.append('court_name', courtName);
               
               // Send request
               fetch('Dashboard.php', {
                   method: 'POST',
                   body: formData
               })
               .then(response => {
                   if (!response.ok) {
                       throw new Error('Network response was not ok');
                   }
                   return response.json();
               })
               .then(data => {
                   // Log response for debugging
                   console.log("Court selection response:", data);
                   
                   // Remove typing indicator
                   const typingIndicator = document.getElementById('typing-indicator');
                   if (typingIndicator) {
                       typingIndicator.remove();
                   }
                   
                   // Add bot response
                   const botMessageHtml = `
                       <div class="message bot-message">
                           <div class="message-avatar">
                               <img src="asset/ball.jpg" alt="Bot Avatar">
                           </div>
                           <div class="message-content">
                               <p>${data.message}</p>
                           </div>
                       </div>
                   `;
                   
                   chatContainer.insertAdjacentHTML('beforeend', botMessageHtml);
                   scrollToBottom();
               })
               .catch(error => {
                   console.error('Error selecting court:', error);
                   
                   // Remove typing indicator
                   const typingIndicator = document.getElementById('typing-indicator');
                   if (typingIndicator) {
                       typingIndicator.remove();
                   }
                   
                   // Show error message
                   const errorMessageHtml = `
                       <div class="message bot-message">
                           <div class="message-avatar">
                               <img src="asset/ball.jpg" alt="Bot Avatar">
                           </div>
                           <div class="message-content">
                               <p>Sorry, I'm having trouble processing your selection. Please try again.</p>
                           </div>
                       </div>
                   `;
                   
                   chatContainer.insertAdjacentHTML('beforeend', errorMessageHtml);
                   scrollToBottom();
               });
           };
           
           chatForm.addEventListener('submit', function(e) {
               e.preventDefault();
               
               const message = userInput.value.trim();
               if (!message) return;
               
               const userMessageHtml = `
                   <div class="message user-message">
                       <div class="message-content">
                           <p>${escapeHtml(message)}</p>
                       </div>
                       <div class="message-avatar">
                           <img src="asset/user.png" alt="User Avatar">
                       </div>
                   </div>
               `;
               
               chatContainer.insertAdjacentHTML('beforeend', userMessageHtml);
               scrollToBottom();
               
               userInput.value = '';
               
               const typingIndicatorHtml = `
                   <div class="typing-indicator" id="typing-indicator">
                       <span></span>
                       <span></span>
                       <span></span>
                   </div>
               `;
               
               chatContainer.insertAdjacentHTML('beforeend', typingIndicatorHtml);
               scrollToBottom();
               
               fetch('Dashboard.php', {
                   method: 'POST',
                   headers: {
                       'Content-Type': 'application/x-www-form-urlencoded',
                   },
                   body: `action=chat&message=${encodeURIComponent(message)}`
               })
               .then(response => response.json())
               .then(data => {
                   const typingIndicator = document.getElementById('typing-indicator');
                   if (typingIndicator) {
                       typingIndicator.remove();
                   }
                   
                   const botMessageHtml = `
                       <div class="message bot-message">
                           <div class="message-avatar">
                               <img src="asset/ball.jpg" alt="Bot Avatar">
                           </div>
                           <div class="message-content">
                               <p>${data.message}</p>
                           </div>
                       </div>
                   `;
                   
                   chatContainer.insertAdjacentHTML('beforeend', botMessageHtml);
                   
                   if (data.showCourtButtons) {
                       loadAndDisplayCourtButtons();
                   }
                   
                   scrollToBottom();
               })
               .catch(error => {
                   console.error('Error:', error);
                   
                   const typingIndicator = document.getElementById('typing-indicator');
                   if (typingIndicator) {
                       typingIndicator.remove();
                   }
                   
                   const errorMessageHtml = `
                       <div class="message bot-message">
                           <div class="message-avatar">
                               <img src="asset/ball.jpg" alt="Bot Avatar">
                           </div>
                           <div class="message-content">
                               <p>Sorry, I'm having trouble connecting right now. Please try again later.</p>
                           </div>
                       </div>
                   `;
                   
                   chatContainer.insertAdjacentHTML('beforeend', errorMessageHtml);
                   scrollToBottom();
               });
           });
           
           resetChat.addEventListener('click', function() {
               if (confirm("Are you sure you want to reset the conversation?")) {
                   fetch('Dashboard.php', {
                       method: 'POST',
                       headers: {
                           'Content-Type': 'application/x-www-form-urlencoded',
                       },
                       body: 'action=reset'
                   })
                   .then(response => response.json())
                   .then(data => {
                       chatContainer.innerHTML = '';
                       
                       const initialMessageHtml = `
                           <div class="message bot-message">
                               <div class="message-avatar">
                                   <img src="asset/ball.jpg" alt="Bot Avatar">
                               </div>
                               <div class="message-content">
                                   <p>${data.message}</p>
                               </div>
                           </div>
                       `;
                       
                       chatContainer.innerHTML = initialMessageHtml;
                       scrollToBottom();
                   })
                   .catch(error => {
                       console.error('Error resetting chat:', error);
                       alert('Error resetting chat. Please try again.');
                   });
               }
           });
           
           function escapeHtml(unsafe) {
               return unsafe
                   .replace(/&/g, "&amp;")
                   .replace(/</g, "&lt;")
                   .replace(/>/g, "&gt;")
                   .replace(/"/g, "&quot;")
                   .replace(/'/g, "&#039;");
           }
           
           // Add debugging functionality
           function monitorBookingProcess() {
               // Create a debug panel
               const debugPanel = document.createElement('div');
               debugPanel.style.position = 'fixed';
               debugPanel.style.bottom = '10px';
               debugPanel.style.right = '10px';
               debugPanel.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
               debugPanel.style.color = 'white';
               debugPanel.style.padding = '10px';
               debugPanel.style.borderRadius = '5px';
               debugPanel.style.maxWidth = '300px';
               debugPanel.style.maxHeight = '200px';
               debugPanel.style.overflow = 'auto';
               debugPanel.style.zIndex = '9999';
               debugPanel.style.display = 'none';
               debugPanel.innerHTML = '<h4>Booking Debug</h4><div id="debug-content"></div>';
               
               // Add a toggle button - HIDDEN BY DEFAULT
               const toggleButton = document.createElement('button');
               toggleButton.textContent = 'Debug';
               toggleButton.style.position = 'fixed';
               toggleButton.style.bottom = '10px';
               toggleButton.style.right = '10px';
               toggleButton.style.zIndex = '10000';
               toggleButton.style.padding = '5px 10px';
               toggleButton.style.backgroundColor = '#007bff';
               toggleButton.style.color = 'white';
               toggleButton.style.border = 'none';
               toggleButton.style.borderRadius = '3px';
               toggleButton.style.cursor = 'pointer';
               toggleButton.style.display = 'none'; // Hide the debug button
               
               toggleButton.addEventListener('click', function() {
                   if (debugPanel.style.display === 'none') {
                       debugPanel.style.display = 'block';
                       toggleButton.textContent = 'Hide Debug';
                   } else {
                       debugPanel.style.display = 'none';
                       toggleButton.textContent = 'Debug';
                   }
               });
               
               document.body.appendChild(debugPanel);
               document.body.appendChild(toggleButton);
               
               // Function to log to debug panel
               window.logDebug = function(message) {
                   const content = document.getElementById('debug-content');
                   const time = new Date().toLocaleTimeString();
                   content.innerHTML += `<div><small>${time}</small>: ${message}</div>`;
                   content.scrollTop = content.scrollHeight;
               };

               // Override fetch for monitoring
               const originalFetch = window.fetch;
               window.fetch = function() {
                   const url = arguments[0];
                   const options = arguments[1] || {};
                   
                   logDebug(`Fetching: ${url}`);
                   if (options.body) {
                       try {
                           const params = new URLSearchParams(options.body);
                           const action = params.get('action');
                           if (action) {
                               logDebug(`Action: ${action}`);
                               
                               if (action === 'select_court') {
                                   const courtId = params.get('court_id');
                                   const courtName = params.get('court_name');
                                   logDebug(`Selected court: ${courtName} (ID: ${courtId})`);
                               }
                               
                               if (action === 'chat' && params.get('message')) {
                                   logDebug(`Message: ${params.get('message')}`);
                               }
                           }
                       } catch (e) {
                           logDebug(`Error parsing body: ${e.message}`);
                       }
                   }
                   
                   return originalFetch.apply(this, arguments)
                       .then(response => {
                           logDebug(`Response status: ${response.status}`);
                           return response.clone().text().then(text => {
                               try {
                                   const data = JSON.parse(text);
                                   logDebug(`Response: ${JSON.stringify(data).substring(0, 100)}...`);
                               } catch (e) {
                                   logDebug(`Response is not JSON: ${text.substring(0, 50)}...`);
                               }
                               return response;
                           });
                       })
                       .catch(error => {
                           logDebug(`Fetch error: ${error.message}`);
                           throw error;
                       });
               };
               
               logDebug('Booking process monitor initialized');
           }
           monitorBookingProcess();
       });
   </script>
</body>
</html>
