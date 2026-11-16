<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/database.php';

// Get the logged-in user's information
$fullName = $_SESSION['full_name'] ?? 'User';
$username = $_SESSION['username'] ?? '';

// Count registered products
$totalProducts = 0;

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $totalProducts = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    // The products table may not have been created yet.
    $totalProducts = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Stock and Finance Management</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #333;
        }

        header {
            background: #163a5f;
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        header h1 {
            margin: 0;
            font-size: 22px;
        }

        header a {
            color: white;
            text-decoration: none;
            background: #d9534f;
            padding: 10px 15px;
            border-radius: 5px;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .welcome {
            margin-bottom: 25px;
        }

        .welcome h2 {
            margin-bottom: 8px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .card h3 {
            margin-top: 0;
            color: #163a5f;
        }

        .card p {
            color: #666;
            line-height: 1.5;
        }

        .card a {
            display: inline-block;
            margin-top: 10px;
            color: #1769aa;
            text-decoration: none;
            font-weight: bold;
        }

        .notice {
            margin-top: 25px;
            padding: 15px;
            background: #fff3cd;
            border-radius: 6px;
            line-height: 1.5;
        }

        footer {
            text-align: center;
            padding: 25px;
            color: #666;
        }
    </style>
</head>

<body>

<header>
    <h1>Stock & Finance Management System</h1>

    <a href="auth/logout.php">Logout</a>
</header>

<main class="container">

    <section class="welcome">
        <h2>
            Welcome, <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>!
        </h2>

        <p>
            Monitor your stock activities and access your management tools
            from one place.
        </p>
    </section>

    <section class="cards">

        <div class="card">
            <h3>Products</h3>
            <p>
                Total registered products:
                <strong><?= $totalProducts ?></strong>
            </p>
            <a href="manage_products.php">Manage Products</a>
        </div>

        <div class="card">
            <h3>Stock Entries</h3>
            <p>Record and monitor products received into the stock.</p>
            <a href="stock_movement.php">Record Stock Entry</a>
        </div>

        <div class="card">
            <h3>Stock Exits</h3>
            <p>Record products issued or sold and monitor quantities.</p>
            <a href="stock_movement.php">Record Stock Exit</a>
        </div>

        <div class="card">
            <h3>Low-Stock Alerts</h3>
            <p>Identify products whose quantities have reached their minimum levels.</p>
            <a href="low_stock_alerts.php">View Stock Alerts</a>
        </div>

        <div class="card">
            <h3>Stock Reports</h3>
            <p>Review stock movements and inventory performance.</p>
            <a href="stock_history.php">View Reports</a>
        </div>

        <div class="card">
            <h3>Finance Management</h3>
            <p>Access the financial management section of the system.</p>
            <a href="#">Manage Finances</a>
        </div>

    </section>

    <div class="notice">
        <strong>Development note:</strong>
        The dashboard layout is ready, but the management links will become
        functional as we develop their corresponding pages.
    </div>

</main>

<footer>
    Stock and Finance Management System &copy; <?= date('Y') ?>
</footer>

</body>
</html>