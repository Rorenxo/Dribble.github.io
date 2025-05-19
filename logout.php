<?php
session_start();
session_unset(); // Clear session variables
session_destroy(); // Destroy the session

// Clear session cookie (important for persistent login issues)
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

header("Location: login.php");
exit();
?>
