<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}
require_once 'connection.php';

$user_id = $_SESSION['user_id'];

// Get court index from query parameter
$courtIndex = isset($_GET['courtIndex']) ? intval($_GET['courtIndex']) : null;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if ($courtIndex === null) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Court index is required']);
    exit();
}

$bookingsRef = $database->getReference('bookings/' . $user_id);
$allBookings = $bookingsRef->getValue() ?: [];


$filteredBookings = [];
foreach ($allBookings as $key => $booking) {

    if ($booking['court_id'] == $courtIndex) {

        $bookingDate = substr($booking['start_time'], 0, 10);
        if ($bookingDate === $date) {
            $booking['id'] = $key;
            $filteredBookings[] = $booking;
        }
    }
}

header('Content-Type: application/json');
echo json_encode($filteredBookings);
exit();
?>
