
<?php
require_once __DIR__ . '/../config/database.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (
        $fullName === '' ||
        $username === '' ||
        $password === '' ||
        $confirmPassword === ''
    ) {
        $message = 'Please fill in all fields.';
    } elseif ($password !== $confirmPassword) {
        $message = 'Passwords do not match.';
    } elseif (strlen($password) < 10) {
        $message = 'Password must contain at least 10 characters.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT user_id FROM users WHERE username = :username"
            );

            $stmt->execute(['username' => $username]);

            if ($stmt->fetch()) {
                $message = 'This username already exists.';
            } else {
                // Assign new registrations the default non-admin role.
                $stmt = $pdo->prepare(
                    "SELECT role_id FROM roles
                     WHERE role_name = 'Stock Manager'
                     LIMIT 1"
                );

                $stmt->execute();
                $role = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$role) {
                    $message = 'Default user role not found.';
                } else {
                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $pdo->prepare(
                        "INSERT INTO users
                        (full_name, username, password_hash, role_id, is_active)
                        VALUES
                        (:full_name, :username, :password_hash, :role_id, 0)"
                    );

                    $stmt->execute([
                        'full_name' => $fullName,
                        'username' => $username,
                        'password_hash' => $passwordHash,
                        'role_id' => $role['role_id']
                    ]);

                    $message =
                        'Registration submitted. An administrator must activate your account.';
                }
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = 'Registration failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Stock Finance System</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .form-box {
            width: 100%;
            max-width: 420px;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        h2 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 7px;
            font-weight: bold;
            color: #334155;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
        }

        input:focus {
            outline: 2px solid #93c5fd;
            border-color: #2563eb;
        }

        button {
            width: 100%;
            padding: 13px;
            margin-top: 22px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #1d4ed8;
        }

        .message {
            background: #eff6ff;
            color: #1e40af;
            padding: 10px;
            border-radius: 6px;
            margin-top: 15px;
            overflow-wrap: anywhere;
        }

        .link {
            text-align: center;
            margin-top: 20px;
        }

        a {
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="form-box">
        <h2>Create Account</h2>

        <p class="subtitle">
            Stock and Finance Management System
        </p>

        <form method="POST" action="">
            <label for="full_name">Full Name</label>
            <input
                type="text"
                id="full_name"
                name="full_name"
                maxlength="150"
                autocomplete="name"
                required
            >

            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                maxlength="100"
                autocomplete="username"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                minlength="10"
                autocomplete="new-password"
                required
            >

            <label for="confirm_password">Confirm Password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="10"
                autocomplete="new-password"
                required
            >

            <button type="submit">Register</button>
        </form>

        <?php if ($message !== ''): ?>
            <div class="message">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <p class="link">
            Already have an account?
            <a href="login.php">Login here</a>
        </p>
    </div>
</body>
</html>

