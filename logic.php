<?php
/**
 * Process user messages based on conversation state
 * 
 * @param string $message User message
 * @param string $state Current conversation state
 * @param array $bookingData Current booking data
 * @return string Bot response
 */
function processMessage($message, &$state, &$bookingData) {
    $response = '';
    
    switch ($state) {
        case 'greeting':
            // Collect name
            $bookingData['name'] = $message;
            $response = "Nice to meet you, {$message}! Which court would you like to book? (e.g., Tennis Court 1, Basketball Court 2)";
            $state = 'court_selection';
            break;
            
        case 'court_selection':
            // Collect court
            $bookingData['court'] = $message;
            $response = "Great choice! What date would you like to book {$message}? (You can type like 'May 10' or 'tomorrow')";
            $state = 'date_selection';
            break;
            
        case 'date_selection':
            // Parse and validate natural date format
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
            // Parse and validate natural time format
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
            // Parse and validate natural time format for end time
            $parsedTime = parseNaturalTime($message);
            if ($parsedTime && isEndTimeAfterStartTime($bookingData['start_time'], $parsedTime)) {
                $bookingData['end_time'] = $parsedTime;
                
                // Generate booking summary
                $response = generateBookingSummary($bookingData);
                $state = 'confirmation';
            } else if (!$parsedTime) {
                $response = "I'm sorry, I couldn't understand that time. Please try again with a format like '2:30pm' or '14:30'.";
            } else {
                $response = "The end time must be after the start time. Please enter a valid end time.";
            }
            break;
            
        case 'confirmation':
            // Process confirmation, edit, or cancel
            $lowerMessage = strtolower($message);
            
            if (strpos($lowerMessage, 'confirm') !== false || $lowerMessage === 'yes') {
                // Save booking to Firebase (in production)
                $startTimeFormatted = date('g:i A', strtotime($bookingData['start_time']));
                $endTimeFormatted = date('g:i A', strtotime($bookingData['end_time']));
                $dateFormatted = date('F j, Y', strtotime($bookingData['date']));
                
                $response = "Excellent! Your booking has been confirmed. Court {$bookingData['court']} is reserved for {$bookingData['name']} on {$dateFormatted} from {$startTimeFormatted} to {$endTimeFormatted}. Thank you for using our booking service! Is there anything else I can help you with?";
                $state = 'completed';
                
                // In a real implementation, this is where you would save to Firebase
                // saveToFirebase($bookingData);
                
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
            // Handle which field to edit
            $lowerMessage = strtolower($message);
            
            if ($lowerMessage === 'name') {
                $response = "What is your name?";
                $state = 'edit_name';
            } else if ($lowerMessage === 'court') {
                $response = "Which court would you like to book?";
                $state = 'edit_court';
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
            // Handle post-booking conversation
            $lowerMessage = strtolower($message);
            
            if (strpos($lowerMessage, 'yes') !== false || strpos($lowerMessage, 'another') !== false) {
                $response = "Great! Let's start a new booking. What's your name?";
                $state = 'greeting';
                $bookingData = [];
            } else if (strpos($lowerMessage, 'no') !== false || strpos($lowerMessage, 'thank') !== false) {
                $response = "Thank you for using our court booking service! Have a wonderful day. If you need to book again, just let me know.";
            } else {
                $response = "Is there anything else I can help you with? If you'd like to make another booking, just let me know.";
            }
            break;
            
        default:
            $response = "I'm sorry, I didn't understand that. Let's start over. What's your name?";
            $state = 'greeting';
            $bookingData = [];
    }
    
    // Update session variables
    $_SESSION['conversation_state'] = $state;
    $_SESSION['booking_data'] = $bookingData;
    
    return $response;
}

/**
 * Parse natural date format (e.g., "May 10", "tomorrow", "next Monday")
 * 
 * @param string $dateStr Natural date string
 * @return string|false Formatted date (Y-m-d) or false if invalid
 */
function parseNaturalDate($dateStr) {
    $dateStr = trim(strtolower($dateStr));
    
    // Try to parse with strtotime first (handles "tomorrow", "next Monday", etc.)
    $timestamp = strtotime($dateStr);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    // Try to parse month and day format (e.g., "May 10")
    $months = [
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
        'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
        'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12
    ];
    
    // Match patterns like "May 10" or "10 May"
    if (preg_match('/([a-z]+)\s+(\d{1,2})/i', $dateStr, $matches)) {
        $monthStr = strtolower($matches[1]);
        $day = (int)$matches[2];
        
        if (isset($months[$monthStr]) && $day >= 1 && $day <= 31) {
            $month = $months[$monthStr];
            $year = date('Y');
            
            // If the date is in the past, assume next year
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
            
            // If the date is in the past, assume next year
            $dateObj = DateTime::createFromFormat('Y-n-j', "$year-$month-$day");
            if ($dateObj && $dateObj < new DateTime()) {
                $year++;
            }
            
            return date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
        }
    }
    
    return false;
}

/**
 * Parse natural time format (e.g., "1pm", "13:30", "1:30 PM")
 * 
 * @param string $timeStr Natural time string
 * @return string|false Formatted time (H:i) or false if invalid
 */
function parseNaturalTime($timeStr) {
    $timeStr = trim(strtolower($timeStr));
    
    // Try to parse with strtotime first
    $timestamp = strtotime("today $timeStr");
    if ($timestamp !== false) {
        return date('H:i', $timestamp);
    }
    
    // Try to match common time patterns
    
    // Match "1pm", "2:30pm", etc.
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
    
    // Match 24-hour format "13:30", "14", etc.
    if (preg_match('/^(\d{1,2})(?::(\d{2}))?$/', $timeStr, $matches)) {
        $hour = (int)$matches[1];
        $minute = isset($matches[2]) ? (int)$matches[2] : 0;
        
        if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
            return sprintf('%02d:%02d', $hour, $minute);
        }
    }
    
    return false;
}

/**
 * Check if end time is after start time
 */
function isEndTimeAfterStartTime($startTime, $endTime) {
    $start = DateTime::createFromFormat('H:i', $startTime);
    $end = DateTime::createFromFormat('H:i', $endTime);
    
    return $end > $start;
}

/**
 * Generate booking summary
 */
function generateBookingSummary($bookingData) {
    // Format date and times for display
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

/**
 * Save booking data to Firebase (placeholder function)
 */
function saveToFirebase($bookingData) {
    // In a real implementation, this would use the Firebase PHP SDK
    // or make API calls to Firebase to save the booking data
    
    // Example implementation would be:
    // $database = new FirebaseDatabase();
    // $database->getReference('bookings')->push($bookingData);
}
?>
