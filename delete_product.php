<?php
require_once __DIR__ . '/config/admin_check.php';
require_once __DIR__ . '/config/database.php';

// Get and validate the product ID.
$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$productId || $productId < 1) {
    exit('Invalid product ID.');
}

// Handle the deletion after confirmation.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['confirm'] ?? '') === 'yes') {
        $stmt = $pdo->prepare(
            "DELETE FROM products WHERE product_id = :id"
        );

        $stmt->execute(['id' => $productId]);

        if ($stmt->rowCount() > 0) {
            header('Location: manage_products.php?deleted=1');
            exit;
        }

        exit('The product was not deleted. It may no longer exist.');
    }

    header('Location: manage_products.php');
    exit;
}

// Retrieve the product for the confirmation page.
$stmt = $pdo->prepare(
    "SELECT product_id, product_name
     FROM products
     WHERE product_id = :id"
);

$stmt->execute(['id' => $productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    exit('Product not found.');
}
?>

<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>Delete Product</title>

<style>
    body {
        font-family: Arial, sans-serif;
        background: #f4f6f9;
        padding: 30px;
    }

    .confirmation {
        max-width: 500px;
        margin: 60px auto;
        padding: 25px;
        background: white;
        border-radius: 10px;
        text-align: center;
    }

    .delete-button {
        background: #dc3545;
        color: white;
        padding: 10px 18px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }

    .cancel-button {
        display: inline-block;
        background: #6c757d;
        color: white;
        padding: 10px 18px;
        border-radius: 5px;
        text-decoration: none;
        margin-left: 8px;
    }
</style>


</head>

<body>
    <div class="confirmation">
        <h2>Confirm Product Deletion</h2>


    <p>
        Are you sure you want to delete
        <strong>
            <?= htmlspecialchars(
                $product['product_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>?
    </p>

    <p>This action cannot be undone.</p>

    <form method="POST">
        <button
            type="submit"
            name="confirm"
            value="yes"
            class="delete-button">
            Yes, Delete
        </button>

        <a href="manage_products.php" class="cancel-button">
            Cancel
        </a>
    </form>
</div>


</body>
</html>
