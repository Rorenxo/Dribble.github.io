<?php
session_start();
require_once 'connection.php';
require_once 'firebase.php';

$response = ['success' => false];


if (isset($_SESSION['phone_number']) && !empty($_SESSION['phone_number'])) {
    $phoneNumber = $_SESSION['phone_number'];
    
 
    $verificationResult = sendPhoneVerificationCode($auth, $phoneNumber);
    
    if ($verificationResult) {

        $_SESSION['verification_session_info'] = $verificationResult;
        $response['success'] = true;
    }
}
header('Content-Type: application/json');
echo json_encode($response);
?>
