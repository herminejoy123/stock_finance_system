<?php

require_once __DIR__ . '/config/database.php';

$username = 'admin';
$fullname = 'System Administrator';

// Replace this with a strong password of your choice.
$password = 'NewJoy1129#';

$roleName = 'Administrator';

try {
    // Find the Administrator role.
    $stmt = $pdo->prepare(
        "SELECT role_id FROM roles WHERE role_name = :role_name"
    );

    $stmt->execute(['role_name' => $roleName]);
    $role = $stmt->fetch();

    if (!$role) {
        exit(
            "Administrator role not found. Check the role table first."
        );
    }

    // Check whether the username already exists.
    $stmt = $pdo->prepare(
        "SELECT user_id FROM users WHERE username = :username"
    );

    $stmt->execute(['username' => $username]);

    if ($stmt->fetch()) {
        exit("The username already exists. No account was created.");
    }

    // Generate a secure password hash.
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Create the Administrator account.
    $stmt = $pdo->prepare(
    "INSERT INTO users (full_name, username, password_hash, role_id, is_active)
     VALUES (:full_name, :username, :password_hash, :role_id, 1)"
);

$stmt->execute([
    'full_name' => $fullname,
    'username' => $username,
    'password_hash' => $passwordHash,
    'role_id' => $role['role_id']
]);
    echo "Administrator account created successfully.";

} catch (PDOException $e) {
    echo "<h3>Database Error</h3>";
    echo "<pre>" . htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    ) . "</pre>";
}