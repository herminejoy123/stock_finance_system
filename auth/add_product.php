```php
<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth_check.php';

$error = '';
$success = '';

// Load available categories
$categoryStmt = $pdo->query(
    "SELECT category_id, category_name
     FROM categories
     ORDER BY category_name"
);

$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $productName = trim($_POST['product_name'] ?? '');
    $categoryId = filter_input(
        INPUT_POST,
        'category_id',
        FILTER_VALIDATE_INT
    );
    $description = trim($_POST['description'] ?? '');
    $quantity = filter_input(
        INPUT_POST,
        'quantity',
        FILTER_VALIDATE_INT
    );
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

        // Confirm that the selected category exists
        $checkCategory = $pdo->prepare(
            "SELECT COUNT(*)
             FROM categories
             WHERE category_id = ?"
        );

        $checkCategory->execute([$categoryId]);

        if (!$checkCategory->fetchColumn()) {

            $error = 'Please select a valid category.';

        } else {

            try {

                $stmt = $pdo->prepare(
                    "INSERT INTO products
                    (category_id, product_name, description,
                     quantity, unit_price, minimum_quantity)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $categoryId,
                    $productName,
                    $description !== '' ? $description : null,
                    $quantity,
                    $unitPrice,
                    $minimumQuantity
                ]);

                header('Location: manage_products.php?added=1');
                exit;

            } catch (PDOException $e) {

                error_log($e->getMessage());
                $error = 'Unable to save the product. Please try again.';
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

    <title>Add Product | Stock Management</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 650px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        h1 {
            color: #1f3c5b;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input, select, textarea {
            box-sizing: border-box;
            width: 100%;
            padding: 11px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            margin-top: 20px;
            padding: 12px 18px;
            background: #1f6feb;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .error {
            color: #b42318;
            background: #fee4e2;
            padding: 10px;
        }

        .back {
            display: inline-block;
            margin-top: 15px;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Add New Product</h1>
    <p>Register a product and its initial stock quantity.</p>

    <?php if ($error !== ''): ?>
        <p class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="add_product.php">

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
            <option value="">-- Select Category --</option>

            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['category_id'] ?>">
                    <?= htmlspecialchars(
                        $category['category_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
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
            value="0"
            required
        >

        <label for="unit_price">Unit Price (FCFA)</label>
        <input
            type="number"
            id="unit_price"
            name="unit_price"
            min="0"
            step="0.01"
            value="0.00"
            required
        >

        <label for="minimum_quantity">Minimum Stock Level</label>
        <input
            type="number"
            id="minimum_quantity"
            name="minimum_quantity"
            min="0"
            step="1"
            value="5"
            required
        >

        <button type="submit">Save Product</button>

    </form>

    <a class="back" href="index.php">← Back to Dashboard</a>

</div>

</body>
</html>
```
