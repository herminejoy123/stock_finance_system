<?php

require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/admin_check.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';

// Display messages after redirects.
if (isset($_SESSION['category_message'])) {
    $message = $_SESSION['category_message'];
    unset($_SESSION['category_message']);
}

if (isset($_SESSION['category_error'])) {
    $error = $_SESSION['category_error'];
    unset($_SESSION['category_error']);
}

// Secure output function.
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// ADD CATEGORY
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_category'])) {

    $categoryName = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($categoryName === '') {

        $error = 'Please enter a category name.';

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO categories (category_name, description)
                VALUES (:category_name, :description)
            ");

            $stmt->execute([
                'category_name' => $categoryName,
                'description' => $description !== '' ? $description : null
            ]);

            $_SESSION['category_message'] =
                'Category added successfully!';

            header('Location: categories.php');
            exit;

        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {
                $error = 'This category may already exist.';
            } else {
                $error = 'Unable to add the category. Please try again.';
            }

        }
    }
}

// EDIT CATEGORY
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_category'])) {

    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $categoryName = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($categoryId <= 0 || $categoryName === '') {

        $error = 'Please provide a valid category name.';

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE categories
                SET category_name = :category_name,
                    description = :description
                WHERE category_id = :category_id
            ");

            $stmt->execute([
                'category_name' => $categoryName,
                'description' => $description !== '' ? $description : null,
                'category_id' => $categoryId
            ]);

            $_SESSION['category_message'] =
                'Category updated successfully!';

            header('Location: categories.php');
            exit;

        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {
                $error = 'This category name may already exist.';
            } else {
                $error = 'Unable to update the category.';
            }

        }
    }
}

// DELETE CATEGORY
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_category'])) {

    $categoryId = (int) ($_POST['category_id'] ?? 0);

    if ($categoryId <= 0) {

        $error = 'Invalid category selected.';

    } else {

        // Check whether products use this category.
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM products
            WHERE category_id = :category_id
        ");

        $stmt->execute(['category_id' => $categoryId]);

        $productCount = (int) $stmt->fetchColumn();

        if ($productCount > 0) {

            $error = 'This category cannot be deleted because it is assigned to '
                . $productCount . ' product(s).';

        } else {

            try {

                $stmt = $pdo->prepare("
                    DELETE FROM categories
                    WHERE category_id = :category_id
                ");

                $stmt->execute(['category_id' => $categoryId]);

                $_SESSION['category_message'] =
                    'Category deleted successfully!';

                header('Location: categories.php');
                exit;

            } catch (PDOException $e) {

                $error = 'Unable to delete this category.';

            }
        }
    }
}

// LOAD CATEGORY FOR EDITING
$editCategory = null;

if (isset($_GET['edit'])) {

    $editId = (int) $_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM categories
        WHERE category_id = :category_id
    ");

    $stmt->execute(['category_id' => $editId]);

    $editCategory = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editCategory) {
        $error = 'Category not found.';
    }
}

// RETRIEVE ALL CATEGORIES
$stmt = $pdo->query("
    SELECT
        c.category_id,
        c.category_name,
        c.description,
        c.created_at,
        COUNT(p.product_id) AS product_count
    FROM categories c
    LEFT JOIN products p
        ON c.category_id = p.category_id
    GROUP BY
        c.category_id,
        c.category_name,
        c.description,
        c.created_at
    ORDER BY c.category_name ASC
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = basename($_SERVER['PHP_SELF']);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Category Management | StockFinance</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #334155;
        }

        /* SIDEBAR */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 270px;
            height: 100vh;
            background: #172b4d;
            padding: 25px 16px;
            overflow-y: auto;
            color: white;
        }

        .sidebar h2 {
            text-align: center;
            margin: 0 0 5px;
        }

        .brand-subtitle {
            text-align: center;
            color: #b8c7dc;
            margin-bottom: 35px;
        }

        .menu-title {
            color: #94a9c7;
            font-size: 11px;
            font-weight: bold;
            margin: 25px 10px 10px;
            text-transform: uppercase;
        }

        .sidebar a {
            display: block;
            padding: 13px 14px;
            margin: 5px 0;
            color: #e2e8f0;
            text-decoration: none;
            border-radius: 8px;
        }

        .sidebar a:hover {
            background: #334e68;
        }

        .sidebar a.active {
            background: #2563eb;
            color: white;
            font-weight: bold;
        }

        .sidebar .logout {
            color: #ffb4b4;
            margin-top: 30px;
        }

        /* MAIN CONTENT */

        .main-content {
            margin-left: 270px;
            padding: 35px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .header h1 {
            color: #172b4d;
            margin-top: 0;
        }

        .header p {
            color: #64748b;
            margin-bottom: 0;
        }

        .content-box {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .content-box h2 {
            color: #172b4d;
            margin-top: 0;
        }

        /* FORM */

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 14px;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: #2563eb;
        }

        button {
            border: none;
            padding: 11px 18px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #64748b;
            color: white;
            text-decoration: none;
            padding: 11px 18px;
            border-radius: 7px;
            display: inline-block;
        }

        /* TABLE */

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th {
            background: #172b4d;
            color: white;
            padding: 14px;
            text-align: center;
        }

        td {
            padding: 13px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .btn-edit {
            background: #2563eb;
            color: white;
            text-decoration: none;
            padding: 7px 12px;
            border-radius: 5px;
            display: inline-block;
            margin: 3px;
        }

        .btn-delete {
            background: #dc2626;
            color: white;
            padding: 7px 12px;
            margin: 3px;
        }

        .message {
            background: #dcfce7;
            color: #166534;
            padding: 14px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 14px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .empty {
            padding: 25px;
            color: #64748b;
        }

        @media(max-width: 768px) {

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

    </style>

</head>

<body>

<!-- ADMIN SIDEBAR -->

<aside class="sidebar">

    <h2>StockFinance</h2>

    <p class="brand-subtitle">Management System</p>

    <p class="menu-title">Main Menu</p>

    <a href="admin_dashboard.php">
        🏠 Dashboard
    </a>

    <a href="manage_users.php">
        👥 Manage Users
    </a>

    <p class="menu-title">Stock Management</p>

    <a href="manage_products.php">
        📦 Products
    </a>

    <a href="categories.php" class="active">
        🗂️ Categories
    </a>

    <p class="menu-title">Sales Management</p>

    <a href="create_sale.php">
        🧾 Create Sale / Invoice
    </a>

    <a href="sales_history.php">
        📋 Sales History
    </a>

    <p class="menu-title">Supplier Management</p>

    <a href="orders.php">
        🚚 Supplier Orders
    </a>

    <a href="payments.php">
        💳 Payment Management
    </a>

    <p class="menu-title">Account</p>

    <a href="auth/logout.php" class="logout">
        🚪 Logout
    </a>

</aside>


<!-- MAIN CONTENT -->

<main class="main-content">

<div class="container">

    <div class="header">

        <h1>Category Management</h1>

        <p>
            Create, organise and manage product categories.
        </p>

    </div>


    <?php if ($message): ?>

        <div class="message">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <!-- ADD / EDIT CATEGORY FORM -->

    <div class="content-box">

        <h2>
            <?= $editCategory ? 'Edit Category' : 'Add New Category' ?>
        </h2>

        <form method="POST" action="categories.php">

            <?php if ($editCategory): ?>

                <input
                    type="hidden"
                    name="category_id"
                    value="<?= (int) $editCategory['category_id'] ?>"
                >

            <?php endif; ?>


            <div class="form-group">

                <label>Category Name</label>

                <input
                    type="text"
                    name="category_name"
                    maxlength="100"
                    placeholder="Enter category name"
                    value="<?= e($editCategory['category_name'] ?? '') ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>Description</label>

                <textarea
                    name="description"
                    placeholder="Enter category description"
                ><?= e($editCategory['description'] ?? '') ?></textarea>

            </div>


            <?php if ($editCategory): ?>

                <button
                    type="submit"
                    name="update_category"
                    class="btn-primary"
                >
                    Update Category
                </button>

                <a href="categories.php" class="btn-secondary">
                    Cancel
                </a>

            <?php else: ?>

                <button
                    type="submit"
                    name="add_category"
                    class="btn-primary"
                >
                    Add Category
                </button>

            <?php endif; ?>

        </form>

    </div>


    <!-- CATEGORY LIST -->

    <div class="content-box">

        <h2>Existing Categories</h2>

        <div class="table-container">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Products</th>
                        <th>Date Created</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (count($categories) > 0): ?>

                    <?php foreach ($categories as $category): ?>

                        <tr>

                            <td>
                                <?= (int) $category['category_id'] ?>
                            </td>

                            <td>
                                <?= e($category['category_name']) ?>
                            </td>

                            <td>
                                <?= e($category['description'] ?? 'No description') ?>
                            </td>

                            <td>
                                <?= (int) $category['product_count'] ?>
                            </td>

                            <td>
                                <?= e($category['created_at']) ?>
                            </td>

                            <td>

                                <a
                                    href="categories.php?edit=<?= (int) $category['category_id'] ?>"
                                    class="btn-edit"
                                >
                                    Edit
                                </a>


                                <form
                                    method="POST"
                                    action="categories.php"
                                    style="display:inline;"
                                    onsubmit="return confirm('Are you sure you want to delete this category?');"
                                >

                                    <input
                                        type="hidden"
                                        name="category_id"
                                        value="<?= (int) $category['category_id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="delete_category"
                                        class="btn-delete"
                                    >
                                        Delete
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="6" class="empty">
                            No categories have been registered yet.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</main>

</body>
</html>