<?php
session_start();

if (isset($_SESSION['current_user'])) {
    $username = $_SESSION['current_user'];
    
    // Release control if user has it
    if (isset($_SESSION['control']) && $_SESSION['control'] === $username) {
        require_once 'config.php';
        logActivity($username, 'Logged out');
        unset($_SESSION['control']);
        unset($_SESSION['control_time']);
    }
    
    // Clear user session
    unset($_SESSION['current_user']);
}

// Destroy session
session_destroy();

header('Location: index.php');
exit;
?>