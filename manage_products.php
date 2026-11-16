<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/database.php';

// Determine the current user's role.
$roleName = $_SESSION['role_name'] ?? '';
$isAdmin = (strtolower(trim($roleName)) === 'administrator');

// Retrieve products and their categories.
$sql = "
    SELECT
        p.product_id,
        p.product_name,
        p.quantity,
        p.unit_price,
        p.minimum_quantity,
        c.category_name
    FROM products p
    INNER JOIN categories c
        ON p.category_id = c.category_id
    ORDER BY p.product_name ASC
";

$stmt = $pdo->query($sql);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate inventory statistics.
$totalProducts = count($products);
$totalQuantity = 0;
$lowStockCount = 0;
$inventoryValue = 0;

foreach ($products as $product) {
    $totalQuantity += (int) $product['quantity'];

    if (
        (int) $product['quantity']
        <= (int) $product['minimum_quantity']
    ) {
        $lowStockCount++;
    }

    $inventoryValue +=
        (int) $product['quantity']
        * (float) $product['unit_price'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Products</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
            color: #243b53;
        }

        .container {
            max-width: 1150px;
            margin: auto;
        }

        .header,
        .card,
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .statistics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }

        .card h3 {
            margin-top: 0;
            font-size: 15px;
            color: #627d98;
        }

        .card p {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 0;
        }

        .warning {
            color: #dc3545;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e1e5eb;
        }

        th {
            background: #243b53;
            color: white;
        }

        .low-stock {
            color: #dc3545;
            font-weight: bold;
        }

        .in-stock {
            color: #198754;
            font-weight: bold;
        }

        .back-link {
            color: #243b53;
            text-decoration: none;
            font-weight: bold;
        }

        .table-container {
            overflow-x: auto;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }
        }
    </style>
</head>

<body>
    <?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="container">

    <div class="header">
        <h1>Manage Products</h1>

        <p>
            Monitor products, inventory quantities, and low-stock items.
        </p>
        <a class="back-link"
   href="<?= $isAdmin ? 'admin_dashboard.php' : 'stock_manager_dashboard.php' ?>">
    &larr; Back to <?= $isAdmin ? 'Administrator' : 'Stock Manager' ?> Dashboard
</a>
    </div>

    <div class="statistics">

        <div class="card">
            <h3>Total Products</h3>
            <p><?= $totalProducts ?></p>
        </div>

        <div class="card">
            <h3>Total Units in Stock</h3>
            <p><?= $totalQuantity ?></p>
        </div>

        <div class="card">
            <h3>Low-Stock Products</h3>
            <p class="warning"><?= $lowStockCount ?></p>
        </div>

        <div class="card">
            <h3>Inventory Value</h3>
            <p><?= number_format($inventoryValue, 2) ?> FCFA</p>
        </div>

    </div>

    <div class="table-container">

        <h2>Product List</h2>
<?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>
    <p style="color: green; font-weight: bold;">
        Product deleted successfully!
    </p>
<?php endif; ?>
<?php if (empty($products)): ?>

```
<p>No products have been registered yet.</p>
```

<?php else: ?>


<table>
    <thead>
        <tr>
            <th>Product Name</th>
            <th>Category</th>
            <th>Quantity</th>
            <th>Minimum Quantity</th>
            <th>Unit Price</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($products as $product): ?>

            <?php
            $isLowStock = (int) $product['quantity']
                <= (int) $product['minimum_quantity'];
            ?>

            <tr>
                <td>
                    <?= htmlspecialchars($product['product_name']) ?>
                </td>

                <td>
                    <?= htmlspecialchars($product['category_name']) ?>
                </td>

                <td>
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
                    <?php if ($isLowStock): ?>
                        <span class="low-stock">Low Stock</span>
                    <?php else: ?>
                        <span class="in-stock">In Stock</span>
                    <?php endif; ?>
                </td>

                <td>
    <td>
    <a href="edit_product.php?id=<?= (int) $product['product_id'] ?>">
        Edit
    </a>

    <?php if ($isAdmin): ?>
        |
        <a href="delete_product.php?id=<?= (int) $product['product_id'] ?>"
           style="color: #dc3545;"
           onclick="return confirm('Are you sure you want to delete this product?');">
            Delete
        </a>
    <?php endif; ?>
</td>


            </tr>

        <?php endforeach; ?>
    </tbody>
</table>


<?php endif; ?>

    </div>

</div>

</body>
</html>