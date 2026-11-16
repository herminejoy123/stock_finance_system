
<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/database.php';

// Retrieve products that have reached or fallen below
// their minimum stock levels.
$sql = "
    SELECT
        product_id,
        product_name,
        quantity,
        minimum_quantity,
        unit_price,
        (quantity * unit_price) AS stock_value
    FROM products
    WHERE quantity <= minimum_quantity
    ORDER BY quantity ASC, product_name ASC
";

$stmt = $pdo->query($sql);
$lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAlerts = count($lowStockProducts);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Low-Stock Alerts</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #333;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        h1 {
            color: #163a5f;
        }

        .back {
            display: inline-block;
            margin-bottom: 15px;
            color: #1769aa;
            text-decoration: none;
        }

        .summary {
            background: #fff3cd;
            border-left: 5px solid #e0a800;
            padding: 18px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .summary strong {
            font-size: 24px;
            display: block;
            margin-top: 8px;
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
            background: #163a5f;
            color: white;
        }

        .warning {
            color: #c0392b;
            font-weight: bold;
        }

        .success {
            padding: 18px;
            background: #d4edda;
            color: #155724;
            border-radius: 5px;
        }

        .action {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background: #1769aa;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        @media (max-width: 700px) {
            .container {
                padding: 12px;
                overflow-x: auto;
            }

            table {
                min-width: 650px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <a class="back" href="index.php">
        &larr; Back to Stock Manager Dashboard
    </a>

    <h1>Low-Stock Alerts</h1>

    <p>
        Monitor products that need replenishment to avoid running out of stock.
    </p>

    <div class="summary">
        Total products requiring attention:
        <strong><?= $totalAlerts ?></strong>
    </div>

    <?php if ($totalAlerts > 0): ?>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Available Quantity</th>
                    <th>Minimum Quantity</th>
                    <th>Unit Price</th>
                    <th>Stock Value</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($lowStockProducts as $product): ?>

                    <tr>
                        <td>
                            <?= htmlspecialchars($product['product_name']) ?>
                        </td>

                        <td class="warning">
                            <?= (int) $product['quantity'] ?>
                        </td>

                        <td>
                            <?= (int) $product['minimum_quantity'] ?>
                        </td>

                        <td>
                            <?= number_format((float) $product['unit_price'], 2) ?>
                            FCFA
                        </td>

                        <td>
                            <?= number_format((float) $product['stock_value'], 2) ?>
                            FCFA
                        </td>

                        <td class="warning">
                            Low Stock
                        </td>
                    </tr>

                <?php endforeach; ?>
            </tbody>
        </table>

        <p>
            <strong>Recommendation:</strong>
            Review these products and arrange replenishment where necessary.
        </p>

    <?php else: ?>

        <div class="success">
            <strong>All good!</strong>
            No products have reached their minimum stock levels.
        </div>

    <?php endif; ?>

    <a class="action" href="manage_products.php">
        Manage Products
    </a>

</div>

</body>
</html>

