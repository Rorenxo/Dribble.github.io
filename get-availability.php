<?php
// This file will check court availability for a specific date
session_start();
require_once 'connection.php';

// Set headers to accept JSON requests
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Get date from query string
$date = $_GET['date'] ?? date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid date format']);
    exit;
}

try {
    // Get all courts
    $userCourtsRef = $database->getReference('user_courts/' . $user_id);
    $userCourts = $userCourtsRef->getValue();
    $courts = $userCourts['courts'] ?? [];
    $totalCourts = count($courts);
    
    // Get all bookings for the date
    $bookingsRef = $database->getReference('bookings/' . $user_id);
    $allBookings = $bookingsRef->getValue() ?: [];
    
    // Count bookings for the date
    $dateBookings = [];
    foreach ($allBookings as $key => $booking) {
        $bookingDate = substr($booking['start_time'], 0, 10);
        if ($bookingDate === $date) {
            $dateBookings[] = $booking;
        }
    }
    
    // Count unique courts booked for the date
    $bookedCourts = [];
    foreach ($dateBookings as $booking) {
        if (!in_array($booking['court_id'], $bookedCourts)) {
            $bookedCourts[] = $booking['court_id'];
        }
    }
    
    $bookedCount = count($bookedCourts);
    $availableCount = $totalCourts - $bookedCount;
    
    // Return availability data
    echo json_encode([
        'status' => 'success',
        'date' => $date,
        'total' => $totalCourts,
        'booked' => $bookedCount,
        'available' => $availableCount,
        'booked_courts' => $bookedCourts
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error checking availability: ' . $e->getMessage()]);
}
?>
