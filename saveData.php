<?php
include 'connection.php';

$ref = $database->getReference('bookings');
$newBooking = $ref->push([
    'name' => 'Juan Dela Cruz',
    'court' => 'Court A',
    'date' => '2025-03-15',
    'time' => '10:00 AM'
]);

echo 'Booking ID: ' . $newBooking->getKey();
