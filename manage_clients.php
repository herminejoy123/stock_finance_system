
<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientName = trim($_POST['client_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($clientName === '') {
        $error = 'Please enter the client name.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO clients (client_name, phone, email, address)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->execute([
                $clientName,
                $phone !== '' ? $phone : null,
                $email !== '' ? $email : null,
                $address !== '' ? $address : null
            ]);

            $message = 'Client registered successfully!';
        } catch (PDOException $e) {
            $error = 'Unable to register this client. Please check the database.';
        }
    }
}

$stmt = $pdo->query(
    "SELECT client_id, client_name, phone, email, address, created_at
     FROM clients
     ORDER BY client_name ASC"
);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Clients</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        .card {
            background: white;
            padding: 22px;
            margin-bottom: 22px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        h1, h2 {
            color: #263746;
        }

        label {
            display: block;
            margin-top: 12px;
            font-weight: bold;
        }

        input, textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            margin-top: 16px;
            padding: 11px 18px;
            border: none;
            border-radius: 5px;
            background: #176b45;
            color: white;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background: #176b45;
            color: white;
        }

        .success {
            color: #176b45;
            font-weight: bold;
        }

        .error {
            color: #b42318;
            font-weight: bold;
        }

        .back {
            display: inline-block;
            margin-bottom: 18px;
        }

        .table-wrapper {
            overflow-x: auto;
        }
    </style>
</head>

<body>
<div class="container">

    <a class="back" href="index.php">← Back to Dashboard</a>

    <h1>Client Management</h1>
    <p>Register clients once, then select them when creating invoices.</p>

    <div class="card">
        <h2>Register a New Client</h2>

        <?php if ($message !== ''): ?>
            <p class="success">
                <?= htmlspecialchars($message) ?>
            </p>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <p class="error">
                <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="manage_clients.php">
            <label for="client_name">Client Name *</label>
            <input
                type="text"
                id="client_name"
                name="client_name"
                maxlength="150"
                required
            >

            <label for="phone">Telephone Number</label>
            <input
                type="text"
                id="phone"
                name="phone"
                maxlength="30"
            >

            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                maxlength="150"
            >

            <label for="address">Address</label>
            <textarea id="address" name="address" rows="3"></textarea>

            <button type="submit">Register Client</button>
        </form>
    </div>

    <div class="card">
        <h2>Registered Clients</h2>

        <?php if (count($clients) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client Name</th>
                        <th>Telephone</th>
                        <th>Email</th>
                        <th>Address</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><?= (int) $client['client_id'] ?></td>
                            <td>
                                <?= htmlspecialchars($client['client_name']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($client['phone'] ?? '') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($client['email'] ?? '') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($client['address'] ?? '') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No clients registered yet. Use the form above to add your first client.</p>
        <?php endif; ?>
    </div>

</div>
</body>
</html>

