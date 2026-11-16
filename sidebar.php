<?php
// Determine the current user's role.
$roleName = $_SESSION['role_name']
    ?? $_SESSION['role']
    ?? $_SESSION['user_role']
    ?? '';

$roleId = $_SESSION['role_id'] ?? null;

$isAdmin = (
    strtolower(trim((string) $roleName)) === 'administrator'
    || (string) $roleId === '1'
);

// Identify the current page for the active menu item.
$currentPage = basename($_SERVER['PHP_SELF']);

// Dashboard depends on the user's role.
$dashboard = $isAdmin
    ? 'admin_dashboard.php'
    : 'stock_manager_dashboard.php';

// Reusable function for menu links.
function sidebarLink($file, $label, $icon, $currentPage)
{
    $active = ($currentPage === $file) ? 'active' : '';

    echo '<a class="sidebar-link ' . $active . '" href="' .
        htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '">';

    echo '<span class="sidebar-icon">' . $icon . '</span>';
    echo '<span>' .
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
        '</span>';

    echo '</a>';
}
?>

<link rel="stylesheet" href="assets/css/sidebar.css">

<aside class="app-sidebar">

    <div class="sidebar-brand">
        <div class="brand-icon">📦</div>

        <div class="brand-text">
            <h2>StockWise</h2>
            <p>Stock & Finance</p>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <?= $isAdmin ? 'A' : 'S' ?>
        </div>

        <div>
            <strong>
                <?= $isAdmin ? 'Administrator' : 'Stock Manager' ?>
            </strong>
            <small>Management Portal</small>
        </div>
    </div>

    <nav class="sidebar-navigation">

        <p class="menu-heading">MAIN MENU</p>

        <?php
        sidebarLink(
            $dashboard,
            'Dashboard',
            '🏠',
            $currentPage
        );
        ?>

        <p class="menu-heading">INVENTORY</p>

        <?php
        sidebarLink(
            'manage_products.php',
            'Manage Products',
            '📦',
            $currentPage
        );

        sidebarLink(
            'stock_movement.php',
            'Stock Entries & Exits',
            '🔄',
            $currentPage
        );

        sidebarLink(
            'low_stock_alerts.php',
            'Low Stock Alerts',
            '⚠️',
            $currentPage
        );

        sidebarLink(
            'stock_history.php',
            'Stock History & Reports',
            '📊',
            $currentPage
        );

        sidebarLink(
            'orders.php',
            'Purchase Orders',
            '🛒',
            $currentPage
        );
        ?>

        <p class="menu-heading">SALES & FINANCE</p>

        <?php
        sidebarLink(
            'create_sale.php',
            'Create Sale / Invoice',
            '🧾',
            $currentPage
        );

        sidebarLink(
            'sales_history.php',
            'Sales History',
            '💰',
            $currentPage
        );

        sidebarLink(
            'manage_clients.php',
            'Manage Clients',
            '👥',
            $currentPage
        );

        sidebarLink(
            'payments.php',
            'Payment Management',
            '💳',
            $currentPage
        );
        ?>

        <?php if ($isAdmin): ?>

            <p class="menu-heading">ADMINISTRATION</p>

            <?php
            sidebarLink(
                'manage_users.php',
                'Manage Users',
                '👤',
                $currentPage
            );
            ?>

        <?php endif; ?>

        <p class="menu-heading">ACCOUNT</p>

        <?php
        sidebarLink(
            'auth/logout.php',
            'Logout',
            '🚪',
            $currentPage
        );
        ?>

    </nav>

    <div class="sidebar-footer">
        <p>StockWise Management System</p>
        <small>Inventory • Sales • Finance</small>
    </div>

</aside>