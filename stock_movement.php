
<?php

require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/role_check.php';

// Allow Administrators and Stock Managers.
require_roles([1, 2]);

require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';
$products = [];

// Retrieve products for the dropdown.
try {
    $stmt = $pdo->query("
        SELECT product_id, product_name, quantity
        FROM products
        ORDER BY product_name ASC
    ");

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Product retrieval error: " . $e->getMessage());
    $error = 'Unable to load products. Please check the database connection.';
}

// Process the stock movement form.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {

    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $movementType = $_POST['movement_type'] ?? '';
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $reason = trim($_POST['reason'] ?? '');

    if (
        !$productId ||
        !in_array($movementType, ['ENTRY', 'EXIT'], true) ||
        !$quantity ||
        $quantity < 1 ||
        $reason === '' ||
        strlen($reason) > 255
    ) {
        $error = 'Please enter valid movement details.';
    } else {

        try {
            $pdo->beginTransaction();

            // Lock the selected product while updating its stock.
            $stmt = $pdo->prepare("
                SELECT product_name, quantity
                FROM products
                WHERE product_id = :product_id
                FOR UPDATE
            ");

            $stmt->execute(['product_id' => $productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception('The selected product was not found.');
            }

            $currentQuantity = (int) $product['quantity'];

            // Prevent stock from becoming negative.
            if (
                $movementType === 'EXIT' &&
                $quantity > $currentQuantity
            ) {
                throw new Exception(
                    'Insufficient stock for ' .
                    $product['product_name'] .
                    '. Available quantity: ' .
                    $currentQuantity . '.'
                );
            }

            // Calculate the new stock quantity.
            if ($movementType === 'ENTRY') {
                $newQuantity = $currentQuantity + $quantity;
            } else {
                $newQuantity = $currentQuantity - $quantity;
            }

            // Update the product quantity.
            $stmt = $pdo->prepare("
                UPDATE products
                SET quantity = :quantity
                WHERE product_id = :product_id
            ");

            $stmt->execute([
                'quantity' => $newQuantity,
                'product_id' => $productId
            ]);

            // Record the movement in the history table.
            $stmt = $pdo->prepare("
                INSERT INTO stock_movements
                    (product_id, user_id, movement_type, quantity, reason)
                VALUES
                    (:product_id, :user_id, :movement_type, :quantity, :reason)
            ");

            $stmt->execute([
                'product_id' => $productId,
                'user_id' => $_SESSION['user_id'],
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'reason' => $reason
            ]);

            // Save both changes together.
            $pdo->commit();

            $message = 'Stock movement recorded successfully.';

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("Stock movement error: " . $e->getMessage());

            // Show validation messages, but hide technical database details.
            if ($e instanceof PDOException) {
                $error = 'A database error occurred while recording the movement. Check the PHP error log.';
            } else {
                $error = $e->getMessage();
            }
        }
    }
}

// Refresh product quantities after processing the form.
try {
    $stmt = $pdo->query("
        SELECT product_id, product_name, quantity
        FROM products
        ORDER BY product_name ASC
    ");

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Product refresh error: " . $e->getMessage());

    if ($error === '') {
        $error = 'Unable to refresh the product list.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Stock Movement</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #243b53;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 650px;
            margin: 30px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-top: 0;
        }

        label {
            display: block;
            margin-top: 18px;
            font-weight: bold;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 11px;
            margin-top: 7px;
            border: 1px solid #ccd6e0;
            border-radius: 5px;
            font-size: 15px;
        }

        button,
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 18px;
            background: #243b53;
            color: white;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
        }

        button:hover,
        .back-link:hover {
            background: #334e68;
        }

        .message {
            background: #e8f8ed;
            color: #176b35;
            padding: 12px;
            border-radius: 5px;
        }

        .error {
            background: #fdeaea;
            color: #a61b1b;
            padding: 12px;
            border-radius: 5px;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Stock Movement</h1>

    <p>Record incoming or outgoing stock and update product quantities.</p>

    <?php if ($message !== ''): ?>
        <p class="message">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="stock_movement.php">

        <label for="product_id">Select Product</label>

        <select name="product_id" id="product_id" required>
            <option value="">-- Choose a product --</option>

            <?php foreach ($products as $product): ?>
                <option value="<?= (int) $product['product_id'] ?>">
                    <?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>
                    (Available: <?= (int) $product['quantity'] ?>)
                </option>
            <?php endforeach; ?>

        </select>

        <label for="movement_type">Movement Type</label>

        <select name="movement_type" id="movement_type" required>
            <option value="">-- Choose movement type --</option>
            <option value="ENTRY">Stock Entry</option>
            <option value="EXIT">Stock Exit</option>
        </select>

        <label for="quantity">Quantity</label>

        <input
            type="number"
            name="quantity"
            id="quantity"
            min="1"
            step="1"
            required
        >

        <label for="reason">Reason</label>

        <textarea
            name="reason"
            id="reason"
            rows="3"
            maxlength="255"
            placeholder="Example: Received from supplier"
            required
        ></textarea>

        <button type="submit">Record Stock Movement</button>

    </form>

    <a href="index.php" class="back-link">Back to Dashboard</a>

</div>

</body>
</html>

