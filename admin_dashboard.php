
<?php
require_once __DIR__ . '/config/admin_check.php';
require_once __DIR__ . '/config/database.php';

// ==========================================
// 1. USER STATISTICS
// ==========================================

$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = (int) $stmt->fetchColumn();

$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM users
     WHERE role_id = 2 AND is_active = 1"
);
$activeStockManagers = (int) $stmt->fetchColumn();


// ==========================================
// 2. INVENTORY STATISTICS
// ==========================================

$stmt = $pdo->query("SELECT COUNT(*) FROM products");
$totalProducts = (int) $stmt->fetchColumn();

$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM products
     WHERE quantity <= minimum_quantity"
);
$lowStockProducts = (int) $stmt->fetchColumn();

$stmt = $pdo->query(
    "SELECT COALESCE(SUM(quantity * purchase_price), 0)
     FROM products"
);
$inventoryValue = (float) $stmt->fetchColumn();


// ==========================================
// 3. SALES STATISTICS
// ==========================================

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total_transactions,
        COALESCE(SUM(total_amount), 0) AS total_revenue,
        COALESCE(SUM(total_cost), 0) AS total_purchase_cost,
        COALESCE(SUM(total_profit), 0) AS total_gross_profit
    FROM sales
");

$salesStats = $stmt->fetch(PDO::FETCH_ASSOC);


// ==========================================
// 4. SUPPLIER ORDER STATISTICS
// ==========================================

// Count supplier orders.
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE status <> 'Cancelled'
");
$totalSupplierOrders = (int) $stmt->fetchColumn();


// Calculate total supplier payments.
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount_paid), 0)
    FROM supplier_payments
");

$totalSupplierPayments = (float) $stmt->fetchColumn();


// Calculate outstanding supplier balance.
// Cancelled orders are excluded.
$stmt = $pdo->query("
    SELECT COALESCE(
        SUM(
            GREATEST(
                o.total_amount -
                COALESCE(p.total_paid, 0),
                0
            )
        ),
        0
    )
    FROM orders o
    LEFT JOIN (
        SELECT
            order_id,
            SUM(amount_paid) AS total_paid
        FROM supplier_payments
        GROUP BY order_id
    ) p ON o.order_id = p.order_id
    WHERE o.status <> 'Cancelled'
");

$outstandingSupplierBalance = (float) $stmt->fetchColumn();


// ==========================================
// 5. RECENT SALES
// ==========================================

$stmt = $pdo->query("
    SELECT
        sale_id,
        invoice_number,
        client_name,
        total_amount,
        total_profit,
        created_at
    FROM sales
    ORDER BY created_at DESC, sale_id DESC
    LIMIT 5
");

$recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ==========================================
// 6. MONTHLY BUSINESS PERFORMANCE
// ==========================================

$chartLabels = [];
$chartRevenue = [];
$chartProfit = [];

// Prepare all days from the beginning of this month to today.
$daysInMonth = (int) date('j');

for ($i = 1; $i <= $daysInMonth; $i++) {

    $date = date('Y-m-') . str_pad($i, 2, '0', STR_PAD_LEFT);

    $chartLabels[$date] = date('M j', strtotime($date));
    $chartRevenue[$date] = 0;
    $chartProfit[$date] = 0;
}

// Retrieve combined daily performance from all users.
$stmt = $pdo->query("
    SELECT
        DATE(created_at) AS sale_date,
        SUM(total_amount) AS daily_revenue,
        SUM(total_profit) AS daily_profit
    FROM sales
    WHERE YEAR(created_at) = YEAR(CURDATE())
      AND MONTH(created_at) = MONTH(CURDATE())
    GROUP BY DATE(created_at)
    ORDER BY sale_date ASC
");

$monthlySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Populate the chart data.
foreach ($monthlySales as $sale) {

    $date = $sale['sale_date'];

    $chartRevenue[$date] = (float) $sale['daily_revenue'];
    $chartProfit[$date] = (float) $sale['daily_profit'];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Administrator Dashboard</title>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>

        * {
            box-sizing: border-box;
        }

      body {
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #243b53;
}

/* SIDEBAR */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    background: #172b4d;
    color: white;
    padding: 25px 15px;
    overflow-y: auto;
    z-index: 1000;
}

.sidebar h2 {
    text-align: center;
    margin-bottom: 5px;
    color: white;
}

.sidebar .brand-subtitle {
    text-align: center;
    color: #b8c7dc;
    font-size: 13px;
    margin-bottom: 35px;
}

.sidebar .menu-title {
    color: #94a9c7;
    font-size: 11px;
    font-weight: bold;
    margin: 25px 10px 10px;
    text-transform: uppercase;
}

.sidebar a {
    display: block;
    padding: 13px 15px;
    margin: 5px 0;
    color: #e2e8f0;
    text-decoration: none;
    border-radius: 7px;
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
    max-width: 1200px;
    margin-left: 250px;
    padding: 25px;
}

/* RESPONSIVE DESIGN */
@media (max-width: 768px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main-content {
        margin-left: 0;
        padding: 15px;
    }
}

        .header,
        .card,
        .menu {
            background: white;
            padding: 22px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }

        .header h1 {
            margin-top: 0;
        }

        .statistics {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(210px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .card h3 {
            color: #627d98;
            font-size: 15px;
        }

        .card p {
            font-size: 23px;
            font-weight: bold;
            margin-bottom: 0;
            overflow-wrap: anywhere;
        }

        .warning {
            color: #dc3545;
        }

        .success {
            color: #198754;
        }

        .menu a {
            display: inline-block;
            margin: 8px 12px 8px 0;
            padding: 12px 18px;
            background: #243b53;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .menu a:hover {
            background: #334e68;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            padding: 12px;
            background: #243b53;
            color: white;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }

        .view-button {
            display: inline-block;
            padding: 8px 12px;
            background: #243b53;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        @media (max-width: 600px) {

            body {
                padding: 12px;
            }

            .card p {
                font-size: 20px;
            }

        }

    </style>

</head>

<body>



<div class="main-content">
    <?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- ADMINISTRATOR SIDEBAR -->
<aside class="sidebar">

    <h2>StockFinance</h2>

    <p class="brand-subtitle">
        Management System
    </p>

    <p class="menu-title">Main Menu</p>

    <a href="admin_dashboard.php"
       class="<?= $currentPage === 'admin_dashboard.php' ? 'active' : '' ?>">
        🏠 Dashboard
    </a>

    <p class="menu-title">User Management</p>

    <a href="manage_users.php"
       class="<?= $currentPage === 'manage_users.php' ? 'active' : '' ?>">
        👥 Manage Users
    </a>

    <p class="menu-title">Stock Management</p>

    <a href="manage_products.php"
       class="<?= $currentPage === 'manage_products.php' ? 'active' : '' ?>">
        📦 Products
    </a>

    <a href="categories.php"
       class="<?= $currentPage === 'categories.php' ? 'active' : '' ?>">
        🗂️ Categories
    </a>

    <p class="menu-title">Sales & Finance</p>

    <a href="create_sale.php"
       class="<?= $currentPage === 'create_sale.php' ? 'active' : '' ?>">
        🧾 Create Sale / Invoice
    </a>

    <a href="sales_history.php"
       class="<?= $currentPage === 'sales_history.php' ? 'active' : '' ?>">
        📋 Sales History
    </a>

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

    <!-- WELCOME SECTION -->

    <div class="header">

        <h1>Administrator Dashboard</h1>

        <p>
            Welcome,
            <?= htmlspecialchars(
                $_SESSION['full_name'] ?? 'Administrator'
            ) ?>!
        </p>

        <p>
            Monitor users, inventory, sales and business
            financial performance from one place.
        </p>

    </div>


    <!-- USER AND INVENTORY STATISTICS -->

    <h2>System and Inventory Overview</h2>

    <div class="statistics">

        <div class="card">
            <h3>Total Registered Users</h3>
            <p><?= $totalUsers ?></p>
        </div>

        <div class="card">
            <h3>Active Stock Managers</h3>
            <p><?= $activeStockManagers ?></p>
        </div>

        <div class="card">
            <h3>Total Products</h3>
            <p><?= $totalProducts ?></p>
        </div>

        <div class="card">
            <h3>Low-Stock Products</h3>
            <p class="warning">
                <?= $lowStockProducts ?>
            </p>
        </div>

        <div class="card">
            <h3>Estimated Inventory Value</h3>
            <p>
                <?= number_format($inventoryValue, 2) ?>
                FCFA
            </p>
        </div>

    </div>


    <!-- SALES STATISTICS -->

    <h2>Sales Summary Statistics</h2>

    <div class="statistics">

        <div class="card">
            <h3>Total Sales Revenue</h3>
            <p>
                <?= number_format(
                    (float) $salesStats['total_revenue'],
                    2
                ) ?>
                FCFA
            </p>
        </div>

        <div class="card">
            <h3>Total Transactions</h3>
            <p>
                <?= number_format(
                    (int) $salesStats['total_transactions']
                ) ?>
            </p>
        </div>

        <div class="card">
            <h3>Total Purchase Cost</h3>
            <p>
                <?= number_format(
                    (float) $salesStats['total_purchase_cost'],
                    2
                ) ?>
                FCFA
            </p>
        </div>

        <div class="card">
            <h3>Total Gross Profit</h3>
            <p class="success">
                <?= number_format(
                    (float) $salesStats['total_gross_profit'],
                    2
                ) ?>
                FCFA
            </p>
        </div>

    </div>


    <!-- MONTHLY BUSINESS PERFORMANCE CHART -->

    <div class="menu">

        <h2>Monthly Business Performance</h2>

        <p>
            Overview of combined daily sales revenue and gross profit
            from all users during the current month.
        </p>

        <div style="position: relative; height: 350px; width: 100%;">

            <canvas id="businessPerformanceChart"></canvas>

        </div>

    </div>


    <!-- SUPPLIER FINANCIAL STATISTICS -->

    <h2>Supplier Financial Overview</h2>

    <div class="statistics">

        <div class="card">
            <h3>Total Supplier Orders</h3>
            <p>
                <?= number_format($totalSupplierOrders) ?>
            </p>
        </div>

        <div class="card">
            <h3>Total Supplier Payments</h3>
            <p>
                <?= number_format(
                    $totalSupplierPayments,
                    2
                ) ?>
                FCFA
            </p>
        </div>

        <div class="card">
            <h3>Outstanding Supplier Balance</h3>
            <p class="warning">
                <?= number_format(
                    $outstandingSupplierBalance,
                    2
                ) ?>
                FCFA
            </p>
        </div>

    </div>


    <!-- RECENT SALES -->

    <div class="menu">

        <h2>Recent Sales</h2>

        <?php if (!empty($recentSales)): ?>

            <div style="overflow-x:auto;">

                <table>

                    <thead>

                        <tr>
                            <th>Invoice Number</th>
                            <th>Client Name</th>
                            <th>Date</th>
                            <th>Total Amount</th>
                            <th>Gross Profit</th>
                            <th>Action</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($recentSales as $sale): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        $sale['invoice_number']
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $sale['client_name']
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $sale['created_at']
                                    ) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (float) $sale['total_amount'],
                                        2
                                    ) ?>
                                    FCFA
                                </td>

                                <td>
                                    <?= number_format(
                                        (float) $sale['total_profit'],
                                        2
                                    ) ?>
                                    FCFA
                                </td>

                                <td>

                                    <a
                                        href="create_sale.php?invoice_id=<?= (int) $sale['sale_id'] ?>"
                                        class="view-button"
                                    >
                                        View Invoice
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p>No sales have been recorded yet.</p>

        <?php endif; ?>

    </div>


</div>

<script>

// Retrieve monthly chart data from PHP.
const chartLabels = <?= json_encode(array_values($chartLabels)) ?>;
const chartRevenue = <?= json_encode(array_values($chartRevenue)) ?>;
const chartProfit = <?= json_encode(array_values($chartProfit)) ?>;

// Get the chart container.
const ctx = document.getElementById('businessPerformanceChart');

// Create the monthly business performance chart.
new Chart(ctx, {

    type: 'line',

    data: {

        labels: chartLabels,

        datasets: [

            {
                label: 'Total Revenue (FCFA)',
                data: chartRevenue,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.10)',
                borderWidth: 3,
                tension: 0.3,
                fill: true
            },

            {
                label: 'Gross Profit (FCFA)',
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
                            Number(context.raw).toLocaleString() +
                            ' FCFA';

                    }

                }

            }

        },

        scales: {

            y: {

                beginAtZero: true,

                ticks: {

                    callback: function(value) {

                        return Number(value).toLocaleString() +
                            ' FCFA';

                    }

                }

            }

        }

    }

});

</script>

</body>
</html>
