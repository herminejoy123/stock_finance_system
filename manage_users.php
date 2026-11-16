<?php
require_once __DIR__ . '/config/admin_check.php';
require_once __DIR__ . '/config/database.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $newStatus = filter_input(INPUT_POST, 'new_status', FILTER_VALIDATE_INT);

    if (
        $userId &&
        ($newStatus === 0 || $newStatus === 1) &&
        $userId !== (int) $_SESSION['user_id']
    ) {
        // Only allow changes to Stock Manager accounts.
        $update = $pdo->prepare(
            "UPDATE users
             SET is_active = ?
             WHERE user_id = ?
             AND role_id = 2"
        );

        $update->execute([$newStatus, $userId]);

        if ($update->rowCount() > 0) {
            $message = 'User status updated successfully.';
        } else {
            $message = 'No changes made. Check the user account.';
        }
    } else {
        $message = 'Invalid request.';
    }
}

// Retrieve users and their roles.
$query = $pdo->query(
    "SELECT u.user_id, u.full_name, u.username,
            u.role_id, u.is_active, u.created_at, r.role_name
     FROM users u
     INNER JOIN roles r ON u.role_id = r.role_id
     ORDER BY u.user_id ASC"
);
$users = $query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        h1 {
            color: #243b53;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #243b53;
            color: white;
        }

        button {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .activate {
            background: #198754;
            color: white;
        }

        .deactivate {
            background: #dc3545;
            color: white;
        }

        .message {
            background: #e2f0d9;
            padding: 10px;
            margin-top: 15px;
        }

        a {
            color: #243b53;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Manage Users</h1>

    <p>View and manage registered system users.</p>

    <p><a href="admin_dashboard.php">← Back to Administrator Dashboard</a></p>

    <?php if ($message !== ''): ?>
        <p class="message">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= (int) $user['user_id'] ?></td>

                    <td>
                        <?= htmlspecialchars($user['full_name']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($user['username']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($user['role_name']) ?>
                    </td>

                    <td>
                        <?= (int) $user['is_active'] === 1
                            ? 'Active'
                            : 'Inactive' ?>
                    </td>

                    <td>
                        <?php if ((int) $user['role_id'] === 2): ?>
                            <form method="POST">
                                <input
                                    type="hidden"
                                    name="user_id"
                                    value="<?= (int) $user['user_id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="new_status"
                                    value="<?= (int) $user['is_active'] === 1
                                        ? 0
                                        : 1 ?>"
                                >

                                <button
                                    type="submit"
                                    class="<?= (int) $user['is_active'] === 1
                                        ? 'deactivate'
                                        : 'activate' ?>"
                                >
                                    <?= (int) $user['is_active'] === 1
                                        ? 'Deactivate'
                                        : 'Activate' ?>
                                </button>
                            </form>
                        <?php else: ?>
                            Administrator
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>

</body>
</html>