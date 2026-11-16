
<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/database.php';

$error = '';

// Get the product ID from the URL.
$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$productId || $productId < 1) {
    exit('Invalid product ID.');
}

// Retrieve the existing product.
$stmt = $pdo->prepare(
    "SELECT product_id, category_id, product_name, description,
            quantity, unit_price, minimum_quantity
     FROM products
     WHERE product_id = ?"
);

$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    exit('Product not found.');
}

// Retrieve product categories.
$stmt = $pdo->query(
    "SELECT category_id, category_name
     FROM categories
     ORDER BY category_name ASC"
);

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Update the product when the form is submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $productName = trim($_POST['product_name'] ?? '');
    $categoryId = filter_input(
        INPUT_POST,
        'category_id',
        FILTER_VALIDATE_INT
    );

    $description = trim($_POST['description'] ?? '');

    $unitPrice = $_POST['unit_price'] ?? '';

    $minimumQuantity = filter_input(
        INPUT_POST,
        'minimum_quantity',
        FILTER_VALIDATE_INT
    );

    if (
        $productName === '' ||
        !$categoryId ||
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
                // Update product details.
                $update = $pdo->prepare(
                    "UPDATE products
                     SET category_id = ?,
                         product_name = ?,
                         description = ?,
                         unit_price = ?,
                         minimum_quantity = ?
                     WHERE product_id = ?"
                );

                $update->execute([
                    $categoryId,
                    $productName,
                    $description !== '' ? $description : null,
                    (float) $unitPrice,
                    $minimumQuantity,
                    $productId
                ]);

                header(
                    'Location: manage_products.php?updated=1'
                );
                exit;

            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = 'The product could not be updated.';
            }
        }
    }

    // Preserve entered values if validation fails.
    $product['product_name'] = $productName;
    $product['category_id'] = $categoryId ?: '';
    $product['description'] = $description;
    $product['unit_price'] = $unitPrice;
    $product['minimum_quantity'] = $minimumQuantity ?? '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Product</title>

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

        a {
            color: #243b53;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Edit Product</h1>

    <p>Update the information for this product.</p>

    <p>
        <a href="manage_products.php">
            &larr; Back to Manage Products
        </a>
    </p>

    <?php if ($error !== ''): ?>
        <p class="error">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <form method="POST"
          action="edit_product.php?id=<?= (int) $productId ?>">

        <label for="product_name">Product Name</label>
        <input
            type="text"
            id="product_name"
            name="product_name"
            maxlength="150"
            value="<?= htmlspecialchars((string) $product['product_name']) ?>"
            required
        >

        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" required>

            <option value="">Select a category</option>

            <?php foreach ($categories as $category): ?>
                <option
                    value="<?= (int) $category['category_id'] ?>"
                    <?= (int) $product['category_id'] === (int) $category['category_id']
                        ? 'selected'
                        : '' ?>
                >
                    <?= htmlspecialchars($category['category_name']) ?>
                </option>
            <?php endforeach; ?>

        </select>

        <label for="description">Description</label>
        <textarea
            id="description"
            name="description"
            rows="3"
        ><?= htmlspecialchars((string) ($product['description'] ?? '')) ?></textarea>

        <label for="unit_price">Unit Price (FCFA)</label>
        <input
            type="number"
            id="unit_price"
            name="unit_price"
            min="0"
            step="0.01"
            value="<?= htmlspecialchars((string) $product['unit_price']) ?>"
            required
        >

        <label for="minimum_quantity">Minimum Stock Quantity</label>
        <input
            type="number"
            id="minimum_quantity"
            name="minimum_quantity"
            min="0"
            step="1"
            value="<?= htmlspecialchars((string) $product['minimum_quantity']) ?>"
            required
        >

        <button type="submit">Update Product</button>

    </form>

</div>

</body>
</html>
