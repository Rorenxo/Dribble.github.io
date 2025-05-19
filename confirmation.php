// Replace the confirmation case in your processMessage function with this improved version
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
            error_log('Warning: court_id is empty in booking data');
        }
        
        // Save the booking to Firebase
        $saveResult = saveBookingToFirebase($bookingData);
        
        if ($saveResult) {
            $response = "Excellent! Your booking has been confirmed. Court {$bookingData['court']} is reserved for {$bookingData['name']} on {$dateFormatted} from {$startTimeFormatted} to {$endTimeFormatted}. Thank you for using our booking service! Is there anything else I can help you with?";
            
            // Also save a copy to a backup location for redundancy
            try {
                $backupRef = $database->getReference('bookings_backup/' . $_SESSION['user_id']);
                $backupRef->push([
                    'customer_name' => $bookingData['name'],
                    'court' => $bookingData['court'],
                    'court_id' => $bookingData['court_id'] ?? '',
                    'date' => $bookingData['date'],
                    'start_time' => date('Y-m-d H:i:s', strtotime($bookingData['date'] . ' ' . $bookingData['start_time'])),
                    'end_time' => date('Y-m-d H:i:s', strtotime($bookingData['date'] . ' ' . $bookingData['end_time'])),
                    'status' => 'confirmed',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                error_log('Backup booking saved successfully');
            } catch (Exception $e) {
                error_log('Error saving backup booking: ' . $e->getMessage());
            }
        } else {
            error_log('Failed to save booking to Firebase');
            $response = "I'm sorry, there was an issue saving your booking. Please try again later.";
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