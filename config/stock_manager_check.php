<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require the user to be logged in.
if (!isset($_SESSION['user_id'], $_SESSION['role_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Allow only Stock Managers (role_id = 2).
if ((int) $_SESSION['role_id'] !== 2) {
    http_response_code(403);
    exit('Access denied. Stock Manager permission is required.');
}
