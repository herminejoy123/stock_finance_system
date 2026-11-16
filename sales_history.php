<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/database.php';

// Identify the logged-in user.
$userId = (int) $_SESSION['user_id'];
$roleId = (int) $_SESSION['role_id'];

$search = trim($_GET['search'] ?? '');

// Build the sales query.
$sql = "SELECT
            sale_id,
            invoice_number,
            client_name,
            total_amount,
            total_cost,
            total_profit,
            created_at
        FROM sales
        WHERE 1=1";

$params = [];

// Stock Managers can only see their own sales.
if ($roleId === 2) {
    $sql .= " AND user_id = :user_id";
    $params['user_id'] = $userId;
}

// Apply invoice number or client name search.
if ($search !== '') {
    $sql .= " AND (
                invoice_number LIKE :search
                OR client_name LIKE :search
              )";

    $params['search'] = '%' . $search . '%';
}

// Display the most recent sales first.
$sql .= " ORDER BY created_at DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }

        h1 { color: #243b55; }

        .toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 20px 0;
        }

        input[type="search"] {
            flex: 1;
            min-width: 220px;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button, .button {
            padding: 11px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            background: #0d6efd;
            color: white;
        }

        .secondary { background: #198754; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 11px;
            text-align: left;
        }

        th { background: #e9eef5; }

        .amount { white-space: nowrap; }

        .empty {
            background: #fff3cd;
            padding: 14px;
            border-radius: 5px;
        }

        @media (max-width: 800px) {
            .container { padding: 14px; overflow-x: auto; }
            body { padding: 8px; }
        }
    </style>
</head>

<body>
<div class="container">

    <h1>Sales History</h1>

    <p>
        <?php if ($roleId === 1): ?>
            View and manage sales recorded by all users.
        <?php else: ?>
            View and search invoices for sales you have recorded.
        <?php endif; ?>
    </p>

    <form method="GET" class="toolbar">

        <input
            type="search"
            name="search"
            placeholder="Search invoice number or client name"
            value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
        >

        <button type="submit">Search</button>

        <a class="button secondary" href="create_sale.php">
            + Create Sale
        </a>

        <?php if ($roleId === 1): ?>
            <a class="button secondary" href="admin_dashboard.php">
                Back to Dashboard
            </a>
        <?php else: ?>
            <a class="button secondary" href="stock_manager_dashboard.php">
                Back to Dashboard
            </a>
        <?php endif; ?>

    </form>

    <?php if (empty($sales)): ?>

        <p class="empty">No sales were found.</p>

    <?php else: ?>

        <table>
            <thead>
                <tr>
                    <th>Invoice Number</th>
                    <th>Client Name</th>
                    <th>Date</th>
                    <th>Total Selling Amount</th>
                    <th>Total Purchase Cost</th>
                    <th>Gross Profit</th>
                    <th>Invoice</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($sales as $sale): ?>
                <tr>
                    <td><?= htmlspecialchars($sale['invoice_number'], ENT_QUOTES, 'UTF-8') ?></td>

                    <td><?= htmlspecialchars($sale['client_name'] ?? 'Walk-in Customer', ENT_QUOTES, 'UTF-8') ?></td>

                    <td><?= htmlspecialchars($sale['created_at'], ENT_QUOTES, 'UTF-8') ?></td>

                    <td class="amount">
                        <?= number_format((float)$sale['total_amount'], 2) ?> FCFA
                    </td>

                    <td class="amount">
                        <?= number_format((float)$sale['total_cost'], 2) ?> FCFA
                    </td>

                    <td class="amount">
                        <?= number_format((float)$sale['total_profit'], 2) ?> FCFA
                    </td>

                    <td>
                        <a class="button"
                           href="create_sale.php?invoice_id=<?= (int)$sale['sale_id'] ?>">
                            View Invoice
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p>Showing up to 200 most recent sales.</p>

    <?php endif; ?>

</div>
</body>
</html>