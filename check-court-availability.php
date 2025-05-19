<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}


$user_id = $_SESSION['user_id'];


$court_id = $_GET['court_id'] ?? null;
$date = $_GET['date'] ?? null;
$start_time = $_GET['start_time'] ?? null;
$end_time = $_GET['end_time'] ?? null;


if (!$court_id || !$date || !$start_time || !$end_time) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}


if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid date format']);
    exit;
}


if (!preg_match('/^\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid time format']);
    exit;
}

try {

    $start_datetime = $date . 'T' . $start_time . ':00';
    $end_datetime = $date . 'T' . $end_time . ':00';
    

    $bookingsRef = $database->getReference('bookings/' . $user_id);
    $allBookings = $bookingsRef->getValue() ?: [];
    

    $isAvailable = true;
    foreach ($allBookings as $key => $booking) {
        // Skip bookings for other courts
        if ($booking['court_id'] != $court_id) {
            continue;
        }
        

        $bookingDate = substr($booking['start_time'], 0, 10);
        if ($bookingDate !== $date) {
            continue;
        }
        
        $booking_start = $booking['start_time'];
        $booking_end = $booking['end_time'];
        
        if (($start_datetime < $booking_end) && ($end_datetime > $booking_start)) {
            $isAvailable = false;
            break;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'court_id' => $court_id,
        'date' => $date,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'is_available' => $isAvailable
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error checking court availability: ' . $e->getMessage()]);
}
?>
