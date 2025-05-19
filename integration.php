<?php
// Start session to maintain conversation state if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize conversation state if not exists
if (!isset($_SESSION['conversation_state'])) {
    $_SESSION['conversation_state'] = 'greeting';
    $_SESSION['booking_data'] = [];
}

// Process AJAX requests for the chatbot
if (isset($_POST['action']) && $_POST['action'] === 'chat') {
    $userMessage = trim($_POST['message']);
    
    // Process the message based on conversation state
    require_once 'chatbot_logic.php';
    $response = processMessage($userMessage, $_SESSION['conversation_state'], $_SESSION['booking_data']);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'message' => $response,
        'state' => $_SESSION['conversation_state']
    ]);
    exit;
}
?>
