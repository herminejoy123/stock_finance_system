<?php

session_start();

require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    try {

        $stmt = $pdo->prepare(
    "SELECT
        u.user_id,
        u.full_name,
        u.username,
        u.password_hash,
        u.role_id,
        u.is_active,
        r.role_name
     FROM users u
     INNER JOIN roles r ON u.role_id = r.role_id
     WHERE u.username = :username"
);

        $stmt->execute(['username' => $username]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {

            if ((int) $user['is_active'] !== 1) {

                $error = "Your account is inactive. Contact the administrator.";

            } else {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['user_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['username'] = $user['username'];
$_SESSION['role_id'] = $user['role_id'];
$_SESSION['role_name'] = $user['role_name'];

                if ((int) $user['role_id'] === 1) {

// Administrator
header('Location: ../admin_dashboard.php');


} elseif ((int) $user['role_id'] === 2) {


// Stock Manager
header('Location: ../stock_manager_dashboard.php');


} else {


// Unknown role
session_unset();
session_destroy();

header('Location: login.php');
exit;


}

exit;

            }

        } else {

            $error = "Invalid username or password.";

        }

    } catch (PDOException $e) {

        $error = "A database error occurred. Please try again.";
        error_log($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Stock Finance System</title>

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
            max-width: 400px;
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
            margin-top: 16px;
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
        <h2>Welcome Back</h2>

        <p class="subtitle">
            Stock and Finance Management System
        </p>

       <form method="POST" action="login.php">
            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                autocomplete="username"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >

            <button type="submit">Login</button>
        </form>

        <?php if (!empty($error)): ?>
    <div class="message">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>
        <p class="link">
            Don't have an account?
            <a href="register.php">Register here</a>
        </p>
    </div>
</body>
</html>

