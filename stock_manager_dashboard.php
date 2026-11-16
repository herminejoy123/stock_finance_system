<?php

require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/database.php';

// Only Stock Managers can access this dashboard.
if ((int) $_SESSION['role_id'] !== 2) {
    http_response_code(403);
    exit('Access denied. Stock Manager permission is required.');
}

$fullName = $_SESSION['full_name'] ?? 'Stock Manager';
$userId = (int) $_SESSION['user_id'];

// Today's sales statistics.
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_sales,
        COALESCE(SUM(total_amount), 0) AS total_revenue,
        COALESCE(SUM(total_profit), 0) AS total_profit
    FROM sales
    WHERE user_id = :user_id
    AND DATE(created_at) = CURDATE()
");

$stmt->execute(['user_id' => $userId]);
$dailyStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Total products.
$totalProducts = $pdo->query("
    SELECT COUNT(*) FROM products
")->fetchColumn();

// Total remaining stock units.
$totalStock = $pdo->query("
    SELECT COALESCE(SUM(quantity), 0)
    FROM products
")->fetchColumn();

// Low-stock products.
$lowStockCount = $pdo->query("
    SELECT COUNT(*)
    FROM products
    WHERE quantity <= minimum_quantity
")->fetchColumn();

// Retrieve product inventory.
$stmt = $pdo->query("
    SELECT product_id, product_name,
           quantity, minimum_quantity
    FROM products
    ORDER BY product_name ASC
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retrieve today's sales.
$stmt = $pdo->prepare("
    SELECT sale_id, invoice_number,
           client_name, total_amount,
           total_profit, created_at
    FROM sales
    WHERE user_id = :user_id
    AND DATE(created_at) = CURDATE()
    ORDER BY created_at DESC
");

$stmt->execute(['user_id' => $userId]);
$dailySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare seven-day chart data.
$chartLabels = [];
$chartRevenue = [];
$chartProfit = [];

for ($i = 6; $i >= 0; $i--) {

    $date = date('Y-m-d', strtotime("-$i days"));

    $chartLabels[$date] = date('D, M j', strtotime($date));
    $chartRevenue[$date] = 0;
    $chartProfit[$date] = 0;
}

// Retrieve sales performance.
$stmt = $pdo->prepare("
    SELECT
        DATE(created_at) AS sale_date,
        SUM(total_amount) AS daily_revenue,
        SUM(total_profit) AS daily_profit
    FROM sales
    WHERE user_id = :user_id
      AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND DATE(created_at) <= CURDATE()
    GROUP BY DATE(created_at)
    ORDER BY sale_date ASC
");

$stmt->execute(['user_id' => $userId]);

$weeklySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($weeklySales as $sale) {

    $date = $sale['sale_date'];

    $chartRevenue[$date] = (float) $sale['daily_revenue'];
    $chartProfit[$date] = (float) $sale['daily_profit'];
}

// Retrieve recent sales.
$stmt = $pdo->prepare("
    SELECT
        sale_id,
        invoice_number,
        client_name,
        total_amount,
        total_profit,
        created_at
    FROM sales
    WHERE user_id = :user_id
    ORDER BY created_at DESC
    LIMIT 5
");

$stmt->execute(['user_id' => $userId]);
$recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Security output function.
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$currentPage = basename($_SERVER['PHP_SELF']);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Stock Manager Dashboard | StockFinance</title>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>

        /* GENERAL PAGE DESIGN */

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 0;
            color: #334155;
            overflow-x: hidden;
        }

        /* SIDEBAR */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;

            width: 270px;
            height: 100vh;

            background: #172b4d;
            color: white;

            padding: 25px 16px;
            overflow-y: auto;

            z-index: 1000;
        }

        .sidebar h2 {
            text-align: center;
            margin: 0 0 5px;
            color: white;
            font-size: 25px;
        }

        .brand-subtitle {
            text-align: center;
            color: #b8c7dc;
            font-size: 14px;
            margin-bottom: 40px;
        }

        .menu-title {
            color: #94a9c7;
            font-size: 11px;
            font-weight: bold;

            margin: 25px 10px 10px;

            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sidebar a {
            display: block;

            padding: 13px 14px;
            margin: 5px 0;

            color: #e2e8f0;
            text-decoration: none;

            border-radius: 8px;
            font-size: 15px;

            transition: 0.2s;
        }

        .sidebar a:hover {
            background: #334e68;
            color: white;
        }

        .sidebar a.active {
            background: #2563eb;
            color: white;
            font-weight: bold;
        }

        .sidebar .logout {
            margin-top: 30px;
            color: #ffb4b4;
        }

        /* MAIN CONTENT */

        .main-content {
            margin-left: 270px;

            width: calc(100% - 270px);

            min-height: 100vh;

            padding: 35px 40px;
        }

        .dashboard-container {
            max-width: 1450px;
            margin: 0 auto;
        }

        /* DASHBOARD HEADER */

        .header {
            background: white;

            padding: 30px 20px;

            border-radius: 12px;
            margin-bottom: 30px;

            text-align: center;

            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .header h1 {
            color: #172b4d;
            font-size: 28px;
            margin: 0 0 12px;
        }

        .header p {
            color: #64748b;
            font-size: 15px;
            margin: 0;
        }

        /* SECTION HEADINGS */

        .page-heading {
            text-align: center;

            color: #172b4d;

            font-size: 23px;
            margin: 30px 0 25px;
        }

        /* STATISTICS CARDS */

        .cards {
            display: grid;

            grid-template-columns: repeat(3, minmax(0, 1fr));

            gap: 22px;

            margin-bottom: 30px;
        }

        .card {
            background: white;

            padding: 25px 18px;

            border-radius: 12px;

            text-align: center;

            box-shadow: 0 3px 12px rgba(0,0,0,0.05);

            border-top: 4px solid #2563eb;

            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-4px);
        }

        .card h3 {
            font-size: 15px;
            color: #64748b;

            margin: 0 0 15px;
        }

        .card p {
            font-size: 25px;
            font-weight: bold;

            color: #172b4d;

            margin: 0;

            overflow-wrap: anywhere;
        }

        /* DASHBOARD SECTIONS */

        .section {
            background: white;

            padding: 28px;

            border-radius: 12px;

            margin-bottom: 30px;

            box-shadow: 0 3px 12px rgba(0,0,0,0.05);

            scroll-margin-top: 20px;
        }

        .section h2 {
            text-align: center;

            color: #172b4d;

            font-size: 21px;

            margin-top: 0;
            margin-bottom: 15px;
        }

        .section > p {
            text-align: center;

            color: #64748b;

            font-size: 14px;

            margin-bottom: 25px;
        }

        /* CHART */

        .chart-container {
            position: relative;

            height: 350px;
            width: 100%;

            max-width: 1200px;

            margin: 0 auto;
        }

        /* TABLE DESIGN */

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            margin-top: 15px;

            min-width: 600px;
        }

        th {
            background: #172b4d;

            color: white;

            padding: 15px 12px;

            text-align: center;

            font-size: 14px;
        }

        td {
            padding: 14px 12px;

            border-bottom: 1px solid #e2e8f0;

            text-align: center;

            font-size: 14px;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        /* STOCK STATUS */

        .low {
            background: #fee2e2;

            color: #b91c1c;

            padding: 7px 12px;

            border-radius: 20px;

            font-size: 12px;
            font-weight: bold;

            display: inline-block;
        }

        .normal {
            background: #dcfce7;

            color: #15803d;

            padding: 7px 12px;

            border-radius: 20px;

            font-size: 12px;
            font-weight: bold;

            display: inline-block;
        }

        /* INVOICE LINK */

        td a {
            color: #2563eb;

            text-decoration: none;

            font-weight: bold;
        }

        td a:hover {
            text-decoration: underline;
        }

        /* EMPTY TABLE MESSAGE */

        .empty {
            text-align: center;

            color: #64748b;

            padding: 25px;
        }

        /* RESPONSIVE DESIGN */

        @media (max-width: 1100px) {

            .main-content {
                padding: 25px;
            }

            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 768px) {

            .sidebar {
                position: relative;

                width: 100%;
                height: auto;

                padding: 20px;
            }

            .main-content {
                margin-left: 0;

                width: 100%;

                padding: 20px 15px;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 23px;
            }

            .section {
                padding: 18px;
            }

            .chart-container {
                height: 280px;
            }

        }

    </style>

</head>

<body>

<!-- STOCK MANAGER SIDEBAR -->

<aside class="sidebar">

    <h2>StockFinance</h2>

    <p class="brand-subtitle">Management System</p>

    <p class="menu-title">Main Menu</p>

    <a href="stock_manager_dashboard.php"
       class="<?= $currentPage === 'stock_manager_dashboard.php' ? 'active' : '' ?>">
        🏠 Dashboard
    </a>

    <p class="menu-title">Stock Management</p>

    <a href="stock_manager_dashboard.php#product-stock-monitoring">
        📦 Product Stock Monitoring
    </a>

    <p class="menu-title">Sales Management</p>

    <a href="create_sale.php"
       class="<?= $currentPage === 'create_sale.php' ? 'active' : '' ?>">
        🧾 Create Sale / Invoice
    </a>

    <a href="sales_history.php"
       class="<?= $currentPage === 'sales_history.php' ? 'active' : '' ?>">
        📋 Sales History
    </a>

    <p class="menu-title">Supplier Management</p>

    <a href="orders.php"
       class="<?= $currentPage === 'orders.php' ? 'active' : '' ?>">
        🚚 Supplier Orders
    </a>

    <a href="payments.php"
       class="<?= $currentPage === 'payments.php' ? 'active' : '' ?>">
        💳 Payment Management
    </a>

    <p class="menu-title">Account</p>

    <a href="auth/logout.php" class="logout">
        🚪 Logout
    </a>

</aside>


<!-- MAIN DASHBOARD CONTENT -->

<main class="main-content">

<div class="dashboard-container">


    <!-- DASHBOARD HEADER -->

    <div class="header">

        <h1>Stock Manager Dashboard</h1>

        <p>
            Welcome, <?= e($fullName) ?>!
            Monitor your daily sales and stock activities.
        </p>

    </div>


    <!-- TODAY'S OVERVIEW -->

    <h2 class="page-heading">Today's Overview</h2>

    <div class="cards">

        <div class="card">

            <h3>My Sales Today</h3>

            <p><?= (int) $dailyStats['total_sales'] ?></p>

        </div>


        <div class="card">

            <h3>My Daily Revenue</h3>

            <p>
                <?= number_format((float) $dailyStats['total_revenue'], 2) ?>
                FCFA
            </p>

        </div>


        <div class="card">

            <h3>My Daily Profit</h3>

            <p>
                <?= number_format((float) $dailyStats['total_profit'], 2) ?>
                FCFA
            </p>

        </div>


        <div class="card">

            <h3>Total Products</h3>

            <p><?= (int) $totalProducts ?></p>

        </div>


        <div class="card">

            <h3>Remaining Stock Units</h3>

            <p><?= (int) $totalStock ?></p>

        </div>


        <div class="card">

            <h3>Low-Stock Products</h3>

            <p><?= (int) $lowStockCount ?></p>

        </div>

    </div>


    <!-- SALES PERFORMANCE CHART -->

    <div class="section">

        <h2>7-Day Sales Performance</h2>

        <p>
            Overview of your daily sales revenue and profit
            over the last seven days.
        </p>

        <div class="chart-container">

            <canvas id="salesPerformanceChart"></canvas>

        </div>

    </div>


    <!-- PRODUCT STOCK MONITORING -->

    <div class="section" id="product-stock-monitoring">

        <h2>Product Stock Monitoring</h2>

        <p>
            Monitor available product quantities and minimum stock levels.
        </p>

        <div class="table-container">

            <table>

                <thead>

                    <tr>
                        <th>Product</th>
                        <th>Available Quantity</th>
                        <th>Minimum Quantity</th>
                        <th>Status</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($products as $product): ?>

                    <?php

                    $isLow = (int) $product['quantity']
                        <= (int) $product['minimum_quantity'];

                    ?>

                    <tr>

                        <td><?= e($product['product_name']) ?></td>

                        <td><?= (int) $product['quantity'] ?></td>

                        <td><?= (int) $product['minimum_quantity'] ?></td>

                        <td>

                            <?php if ($isLow): ?>

                                <span class="low">
                                    Low Stock - Reorder Needed
                                </span>

                            <?php else: ?>

                                <span class="normal">
                                    Available
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- MY SALES TODAY -->

    <div class="section">

        <h2>My Sales Today</h2>

        <p>
            View the invoices and sales transactions you recorded today.
        </p>

        <div class="table-container">

            <table>

                <thead>

                    <tr>
                        <th>Invoice Number</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Profit</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (count($dailySales) > 0): ?>

                    <?php foreach ($dailySales as $sale): ?>

                        <tr>

                            <td><?= e($sale['invoice_number']) ?></td>

                            <td><?= e($sale['client_name']) ?></td>

                            <td>
                                <?= number_format((float) $sale['total_amount'], 2) ?>
                                FCFA
                            </td>

                            <td>
                                <?= number_format((float) $sale['total_profit'], 2) ?>
                                FCFA
                            </td>

                            <td><?= e($sale['created_at']) ?></td>

                            <td>

                                <a href="create_sale.php?invoice_id=<?= (int) $sale['sale_id'] ?>">
                                    View Invoice
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="6" class="empty">
                            You have not recorded any sales today.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


</div>

</main>


<!-- SALES PERFORMANCE CHART SCRIPT -->

<script>

const chartLabels = <?= json_encode(array_values($chartLabels)) ?>;

const chartRevenue = <?= json_encode(array_values($chartRevenue)) ?>;

const chartProfit = <?= json_encode(array_values($chartProfit)) ?>;

const ctx = document.getElementById('salesPerformanceChart');

new Chart(ctx, {

    type: 'line',

    data: {

        labels: chartLabels,

        datasets: [

            {
                label: 'Revenue (FCFA)',

                data: chartRevenue,

                borderColor: '#0d6efd',

                backgroundColor: 'rgba(13, 110, 253, 0.10)',

                borderWidth: 3,

                tension: 0.3,

                fill: true
            },

            {
                label: 'Profit (FCFA)',

                data: chartProfit,

                borderColor: '#198754',

                backgroundColor: 'rgba(25, 135, 84, 0.10)',

                borderWidth: 3,

                tension: 0.3,

                fill: true
            }

        ]

    },

    options: {

        responsive: true,

        maintainAspectRatio: false,

        plugins: {

            legend: {
                display: true,
                position: 'top'
            },

            tooltip: {

                callbacks: {

                    label: function(context) {

                        return context.dataset.label + ': ' +
                            Number(context.raw).toLocaleString() + ' FCFA';

                    }

                }

            }

        },

        scales: {

            y: {

                beginAtZero: true,

                ticks: {

                    callback: function(value) {

                        return Number(value).toLocaleString() + ' FCFA';

                    }

                }

            }

        }

    }

});

</script>

</body>
</html>
