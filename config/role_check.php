<?php

/**
 * Restrict access according to the user's role.
 *
 * Role 1: Administrator
 * Role 2: Stock Manager
 */
function require_roles(array $allowedRoleIds): void
{
    $roleId = (int) ($_SESSION['role_id'] ?? 0);

    if (!in_array($roleId, $allowedRoleIds, true)) {
        http_response_code(403);
        exit('Access denied. You do not have permission to access this page.');
    }
}