<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';

// Retrieve product categories.
$stmt = $pdo->query(
    "SELECT category_id, category_name
     FROM categories
     ORDER BY category_name ASC"
);

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productName = trim($_POST['product_name'] ?? '');
$categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
$description = trim($_POST['description'] ?? '');
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
$unitPrice = $_POST['unit_price'] ?? '';
$minimumQuantity = filter_input(
    INPUT_POST,
    'minimum_quantity',
    FILTER_VALIDATE_INT
);
    if (
        $productName === '' ||
        !$categoryId ||
        $quantity === false ||
        $quantity === null ||
        $quantity < 0 ||
        !is_numeric($unitPrice) ||
        !is_finite((float) $unitPrice) ||
        (float) $unitPrice < 0 ||
        $minimumQuantity === false ||
        $minimumQuantity === null ||
        $minimumQuantity < 0
    ) {
        $error = 'Please enter valid product information.';
    } else {
        // Confirm that the selected category exists.
        $check = $pdo->prepare(
            "SELECT category_id
             FROM categories
             WHERE category_id = ?"
        );

        $check->execute([$categoryId]);

        if (!$check->fetchColumn()) {
            $error = 'Please select a valid category.';
        } else {
            try {
                $insert = $pdo->prepare(
                    "INSERT INTO products
                    (
                        category_id,
                        product_name,
                        description,
                        quantity,
                        unit_price,
                        minimum_quantity
                    )
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                $insert->execute([
                    $categoryId,
                    $productName,
                    $description !== '' ? $description : null,
                    $quantity,
                    (float) $unitPrice,
                    $minimumQuantity
                ]);

                // Redirect to prevent duplicate submissions.
                header('Location: /stock_finance_system/manage_products.php?added=1');
exit;
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = 'The product could not be saved. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Product</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #243b53;
            padding: 25px;
            margin: 0;
        }

        .container {
            max-width: 650px;
            margin: 25px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
        }

        h1 {
            margin-top: 0;
        }

        label {
            display: block;
            margin-top: 16px;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccd5df;
            border-radius: 5px;
            font-size: 15px;
        }

        textarea {
            resize: vertical;
        }

        button {
            margin-top: 22px;
            padding: 12px 18px;
            background: #243b53;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 15px;
        }

        button:hover {
            background: #334e68;
        }

        .error {
            background: #f8d7da;
            color: #842029;
            padding: 12px;
            border-radius: 5px;
        }

        .back-link {
            color: #243b53;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Add New Product</h1>

    <p>Register a product in your inventory.</p>

    <p>
        <a class="back-link" href="manage_products.php">
            &larr; Back to Manage Products
        </a>
    </p>

    <?php if ($error !== ''): ?>
        <p class="error">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="/stock_finance_system/add_product.php">

        <label for="product_name">Product Name</label>
        <input
            type="text"
            id="product_name"
            name="product_name"
            maxlength="150"
            required
        >

        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" required>
            <option value="">Select a category</option>

            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['category_id'] ?>">
                    <?= htmlspecialchars($category['category_name']) ?>
                </option>
            <?php endforeach; ?>

        </select>

        <label for="description">Description</label>
        <textarea
            id="description"
            name="description"
            rows="3"
        ></textarea>

        <label for="quantity">Initial Quantity</label>
        <input
            type="number"
            id="quantity"
            name="quantity"
            min="0"
            step="1"
            required
        >

        <label for="unit_price">Unit Price (FCFA)</label>
        <input
            type="number"
            id="unit_price"
            name="unit_price"
            min="0"
            step="0.01"
            required
        >

        <label for="minimum_quantity">Minimum Stock Quantity</label>
        <input
            type="number"
            id="minimum_quantity"
            name="minimum_quantity"
            min="0"
            value="5"
            step="1"
            required
        >

        <button type="submit">Save Product</button>

    </form>

</div>

</body>
</html>