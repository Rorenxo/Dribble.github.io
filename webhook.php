<?php
header('Content-Type: application/json');
require_once 'connection.php';


$requestData = json_decode(file_get_contents('php://input'), true);
$action = $requestData['action'] ?? '';
$path = $requestData['path'] ?? '';
$user_id = $requestData['user_id'] ?? '';
$data = $requestData['data'] ?? [];


$response = ['success' => false];


switch ($action) {
    case 'get_courts':

        $userCourtsRef = $database->getReference('user_courts/' . $user_id);
        $userCourts = $userCourtsRef->getValue();
        $courts = $userCourts['courts'] ?? [];
        
        $response = [
            'success' => true,
            'courts' => $courts
        ];
        break;
        
    case 'create_booking':
        $bookingsRef = $database->getReference('bookings/' . $user_id);
        $newBookingRef = $bookingsRef->push($data);
        
        $response = [
            'success' => true,
            'booking_id' => $newBookingRef->getKey(),
            'message' => 'Booking created successfully'
        ];
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Invalid action'];
}


echo json_encode($response);