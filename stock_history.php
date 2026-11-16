```php
<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/database.php';

$sql = "
    SELECT
        sm.movement_id,
        p.product_name,
        sm.movement_type,
        sm.quantity,
        sm.movement_date,
        sm.reason,
        u.full_name
    FROM stock_movements sm
    INNER JOIN products p ON sm.product_id = p.product_id
    INNER JOIN users u ON sm.user_id = u.user_id
    ORDER BY sm.movement_date DESC
";

$stmt = $pdo->query($sql);
$movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Movement History</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 1100px;
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

        .entry {
            color: green;
            font-weight: bold;
        }

        .exit {
            color: #d9534f;
            font-weight: bold;
        }

        .back {
            display: inline-block;
            margin-bottom: 15px;
            text-decoration: none;
            color: #087f8c;
        }

        .empty {
            padding: 20px;
            background: #fff3cd;
            border-radius: 5px;
        }
    </style>
</head>

<body>
<div class="container">

    <a class="back" href="index.php">
        &larr; Back to Stock Manager Dashboard
    </a>

    <h1>Stock Movement History</h1>
    <p>View all recorded stock entries and exits.</p>

    <?php if (count($movements) > 0): ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Movement Type</th>
                    <th>Quantity</th>
                    <th>Reason</th>
                    <th>Recorded By</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($movements as $movement): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars((string) $movement['movement_id']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($movement['product_name']) ?>
                        </td>

                        <td class="<?= $movement['movement_type'] === 'ENTRY' ? 'entry' : 'exit' ?>">
                            <?= htmlspecialchars($movement['movement_type']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars((string) $movement['quantity']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($movement['reason'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($movement['full_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($movement['movement_date']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>

        <p class="empty">
            No stock movements have been recorded yet.
        </p>

    <?php endif; ?>

</div>
</body>
</html>
```
