<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: /stock_finance_system/auth/login.php");
        exit;
    }
}

function requireRole(string $requiredRole): void
{
    requireLogin();

    if (($_SESSION['role_name'] ?? '') !== $requiredRole) {
        http_response_code(403);
        exit("Access denied. You do not have permission to view this page.");
    }
}
?>
