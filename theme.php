<?php

// Handle theme toggle
if (isset($_POST['toggle_theme'])) {
    // If theme is already set, toggle it
    if (isset($_SESSION['theme'])) {
        $_SESSION['theme'] = $_SESSION['theme'] === 'dark' ? 'light' : 'dark';
    } else {
        // Default to dark if not set
        $_SESSION['theme'] = 'dark';
    }
    
    // Redirect back to the referring page
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

// Set default theme if not set
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light'; // Default theme
}

// Function to get current theme
function getCurrentTheme() {
    return $_SESSION['theme'] ?? 'light';
}

// Function to check if dark mode is active
function isDarkMode() {
    return getCurrentTheme() === 'dark';
}
