<?php
/**
 * Entry Point for Secure File Search
 * Redirects to login.php or file_manager.php based on user authentication
 */

session_start();

// Check if the user is already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: file_manager.php');
    exit;
} else {
    header('Location: login.php');
    exit;
}
