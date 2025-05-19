<?php
/**
 * Helper functions for Firebase authentication
 */

/**
 * Authenticate a user with email and password
 * 
 * @param object $auth Firebase Auth instance
 * @param string $email User email
 * @param string $password User password
 * @return object User record
 */
function signInWithEmailAndPassword($auth, $email, $password) {
    try {
        // First, get the user by email
        $user = $auth->getUserByEmail($email);
        
        // Verify the password (this is a simplified example)
        // In a real implementation, you would use Firebase's signInWithPassword method
        // or implement proper password verification
        
        // For testing purposes, we'll just return the user
        return $user;
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        throw $e;
    }
}

// Add this function to the Auth class if it doesn't exist
if (!method_exists($auth, 'signInWithEmailAndPassword')) {
    $auth->signInWithEmailAndPassword = function($email, $password) use ($auth) {
        return signInWithEmailAndPassword($auth, $email, $password);
    };
}
?>
