
<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$roleId = (int) ($_SESSION['role_id'] ?? 0);

if ($userId <= 0) {
    die('Please log in before managing orders.');
}

if (!in_array($roleId, [1, 2], true)) {
    http_response_code(403);
    exit('Access denied.');
}

/*
|--------------------------------------------------------------------------
| CREATE ORDER
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'create_order'
) {
    $supplierName = trim($_POST['supplier_name'] ?? '');
    $orderDate = $_POST['order_date'] ?? '';
    $expectedDate = $_POST['expected_delivery_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    $productIds = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if ($supplierName === '' || $orderDate === '') {
        $error = 'Please enter the supplier name and order date.';
    } elseif (
        $expectedDate !== ''
        && $expectedDate < $orderDate
    ) {
        $error = 'Expected delivery cannot be before the order date.';
    } elseif (
        !is_array($productIds)
        || !is_array($quantities)
        || count($productIds) === 0
        || count($productIds) !== count($quantities)
    ) {
        $error = 'Please select at least one product and enter its quantity.';
    } else {
        $items = [];
        $seenProducts = [];
        $totalAmount = 0;

        try {
            foreach ($productIds as $index => $rawProductId) {
                $productId = filter_var(
                    $rawProductId,
                    FILTER_VALIDATE_INT
                );

                $quantity = filter_var(
                    $quantities[$index] ?? null,
                    FILTER_VALIDATE_INT
                );

                if (
                    !$productId
                    || !$quantity
                    || $quantity < 1
                    || isset($seenProducts[$productId])
                ) {
                    throw new RuntimeException(
                        'Each product must be selected only once, with a quantity of at least 1.'
                    );
                }

                $seenProducts[$productId] = true;

                $stmt = $pdo->prepare(
                    "SELECT product_id, product_name, purchase_price
                     FROM products
                     WHERE product_id = ?"
                );
                $stmt->execute([$productId]);

                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new RuntimeException(
                        'One of the selected products does not exist.'
                    );
                }

                $unitCost = (float) $product['purchase_price'];

                if ($unitCost < 0) {
                    throw new RuntimeException(
                        'The purchase price of a product cannot be negative.'
                    );
                }

                $lineTotal = $quantity * $unitCost;
                $totalAmount += $lineTotal;

                $items[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost
                ];
            }

            if (count($items) === 0) {
                throw new RuntimeException(
                    'Please add at least one product.'
                );
            }

            $pdo->beginTransaction();

            $orderNumber = 'ORD-' . date('YmdHis') . '-'
                . strtoupper(bin2hex(random_bytes(3)));

            $stmt = $pdo->prepare(
                "INSERT INTO orders
                (
                    order_number,
                    supplier_name,
                    order_date,
                    expected_delivery_date,
                    status,
                    total_amount,
                    notes,
                    created_by
                )
                VALUES (?, ?, ?, ?, 'Pending', ?, ?, ?)"
            );

            $stmt->execute([
                $orderNumber,
                $supplierName,
                $orderDate,
                $expectedDate !== '' ? $expectedDate : null,
                $totalAmount,
                $notes !== '' ? $notes : null,
                $userId
            ]);

            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                "INSERT INTO order_items
                (
                    order_id,
                    product_id,
                    quantity_ordered,
                    quantity_received,
                    unit_cost
                )
                VALUES (?, ?, ?, 0, ?)"
            );

            foreach ($items as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_cost']
                ]);
            }

            $pdo->commit();

            $message = 'Order created successfully. Order number: '
                . $orderNumber;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Unable to create the order. Please check your database configuration.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE ORDER STATUS
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'update_status'
) {
    $orderId = filter_var(
        $_POST['order_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $newStatus = $_POST['status'] ?? '';

    $allowedStatuses = [
        'Approved',
        'Cancelled'
    ];

    try {

    // Only the Administrator can approve or cancel orders.
    if ($roleId !== 1) {
        throw new RuntimeException(
            'Access denied. Only the Administrator can approve or cancel orders.'
        );
    }

    if (!$orderId || !in_array($newStatus, $allowedStatuses, true)) {
        throw new RuntimeException('Invalid order or status.');
    }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT status
             FROM orders
             WHERE order_id = ?
             FOR UPDATE"
        );
        $stmt->execute([$orderId]);

        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        if ($order['status'] !== 'Pending') {
            throw new RuntimeException(
                'Only pending orders can be approved or cancelled.'
            );
        }

        $stmt = $pdo->prepare(
            "UPDATE orders SET status = ? WHERE order_id = ?"
        );
        $stmt->execute([$newStatus, $orderId]);

        $pdo->commit();

        $message = 'Order status updated to ' . $newStatus . '.';

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to update the order status.';
    }
}

/*
|--------------------------------------------------------------------------
| RECEIVE ORDER
|--------------------------------------------------------------------------
| Only Approved orders can be received.
| Stock is increased only by the quantity actually delivered.
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'receive_order'
) {
    $orderId = filter_var(
        $_POST['order_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $receivedQuantities = $_POST['received_quantity'] ?? [];

    try {
        if (
            !$orderId
            || !is_array($receivedQuantities)
            || count($receivedQuantities) === 0
        ) {
            throw new RuntimeException(
                'Please provide the quantities delivered.'
            );
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT order_id, order_number, status
             FROM orders
             WHERE order_id = ?
             FOR UPDATE"
        );
        $stmt->execute([$orderId]);

        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        if ($order['status'] !== 'Approved') {
            throw new RuntimeException(
                'Only approved orders can be received.'
            );
        }

        $stmt = $pdo->prepare(
            "SELECT
                oi.order_item_id,
                oi.product_id,
                oi.quantity_ordered,
                oi.quantity_received,
                p.product_name
             FROM order_items oi
             INNER JOIN products p
                ON p.product_id = oi.product_id
             WHERE oi.order_id = ?
             ORDER BY oi.order_item_id
             FOR UPDATE"
        );
        $stmt->execute([$orderId]);

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($items) === 0) {
            throw new RuntimeException(
                'This order has no products.'
            );
        }

        $stockStmt = $pdo->prepare(
            "UPDATE products
             SET quantity = quantity + ?
             WHERE product_id = ?"
        );

        $itemStmt = $pdo->prepare(
            "UPDATE order_items
             SET quantity_received = quantity_received + ?
             WHERE order_item_id = ?"
        );

        $movementStmt = $pdo->prepare(
            "INSERT INTO stock_movements
            (
                product_id,
                user_id,
                movement_type,
                quantity,
                notes,
                reason
            )
            VALUES (?, ?, 'ENTRY', ?, ?, ?)"
        );

        $hasDelivery = false;
        $allItemsComplete = true;

        foreach ($items as $item) {
            $itemId = (int) $item['order_item_id'];
            $productId = (int) $item['product_id'];
            $ordered = (int) $item['quantity_ordered'];
            $alreadyReceived = (int) $item['quantity_received'];

            $rawReceived = $receivedQuantities[$itemId] ?? '0';

            $received = filter_var(
                $rawReceived,
                FILTER_VALIDATE_INT
            );

            if ($received === false || $received < 0) {
                throw new RuntimeException(
                    'Received quantities must be whole numbers of zero or more.'
                );
            }

            if ($alreadyReceived + $received > $ordered) {
                throw new RuntimeException(
                    'The received quantity for '
                    . $item['product_name']
                    . ' exceeds the quantity ordered.'
                );
            }

            if ($received > 0) {
                $hasDelivery = true;

                $stockStmt->execute([
                    $received,
                    $productId
                ]);

                $itemStmt->execute([
                    $received,
                    $itemId
                ]);

                $notes = 'Supplier order received: '
                    . $order['order_number'];

                $reason = 'Supplier order receipt';

                $movementStmt->execute([
                    $productId,
                    $userId,
                    $received,
                    $notes,
                    $reason
                ]);
            }

            if ($alreadyReceived + $received < $ordered) {
                $allItemsComplete = false;
            }
        }

        if (!$hasDelivery) {
            throw new RuntimeException(
                'Enter a quantity greater than zero for at least one delivered product.'
            );
        }

        /*
         * This version only marks an order Received when every
         * ordered unit has been delivered.
         *
         * Partial deliveries are recorded in order_items and stock
         * movements, while the order remains Approved.
         */
        if ($allItemsComplete) {
            $stmt = $pdo->prepare(
                "UPDATE orders SET status = 'Received'
                 WHERE order_id = ?"
            );
            $stmt->execute([$orderId]);

            $message = 'All products have been received. Stock quantities and movement history have been updated.';
        } else {
            $message = 'Partial delivery recorded. Stock has been updated for the quantities received. The order remains Approved until all products arrive.';
        }

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to receive this order. No stock changes were saved.';
    }
}

/*
|--------------------------------------------------------------------------
| LOAD PRODUCTS
|--------------------------------------------------------------------------
*/
$stmt = $pdo->query(
    "SELECT product_id, product_name, purchase_price, quantity
     FROM products
     ORDER BY product_name ASC"
);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| LOAD ORDERS
|--------------------------------------------------------------------------
*/
$stmt = $pdo->query(
    "SELECT
        o.order_id,
        o.order_number,
        o.supplier_name,
        o.order_date,
        o.expected_delivery_date,
        o.status,
        o.total_amount,
        o.notes,
        u.full_name AS created_by_name,
        COALESCE(SUM(oi.quantity_ordered), 0) AS total_units_ordered,
        COALESCE(SUM(oi.quantity_received), 0) AS total_units_received
     FROM orders o
     LEFT JOIN users u
        ON u.user_id = o.created_by
     LEFT JOIN order_items oi
        ON oi.order_id = o.order_id
     GROUP BY
        o.order_id,
        o.order_number,
        o.supplier_name,
        o.order_date,
        o.expected_delivery_date,
        o.status,
        o.total_amount,
        o.notes,
        u.full_name
     ORDER BY o.order_id DESC"
);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalOrders = count($orders);
$pendingOrders = 0;
$approvedOrders = 0;
$totalOrderValue = 0;

foreach ($orders as $order) {
    if ($order['status'] === 'Pending') {
        $pendingOrders++;
    }

    if ($order['status'] === 'Approved') {
        $approvedOrders++;
    }

    if ($order['status'] !== 'Cancelled') {
        $totalOrderValue += (float) $order['total_amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Supplier Order Management</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 25px;
            background: #f4f6f9;
            color: #222;
        }

        h1, h2, h3 {
            color: #243b55;
        }

        .card {
            background: white;
            padding: 18px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,.08);
        }

        .statistics {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .statistics .card {
            flex: 1;
            min-width: 170px;
        }

        label {
            display: block;
            margin-top: 12px;
            font-weight: bold;
        }

        input, textarea, select {
            width: 100%;
            padding: 9px;
            margin-top: 5px;
            box-sizing: border-box;
        }

        button {
            padding: 9px 13px;
            margin-top: 10px;
            background: #243b55;
            color: white;
            border: 0;
            border-radius: 4px;
            cursor: pointer;
        }

        .danger {
            background: #a93226;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #243b55;
            color: white;
        }

        .table-container {
            overflow-x: auto;
        }

        .success {
            color: #19692c;
            font-weight: bold;
        }

        .error {
            color: #b42318;
            font-weight: bold;
        }

        .muted {
            color: #666;
            font-size: 13px;
        }

        .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 12px;
            margin-bottom: 10px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>

<?php
$dashboard = ($roleId === 1)
    ? 'admin_dashboard.php'
    : 'stock_manager_dashboard.php';

$dashboardName = ($roleId === 1)
    ? 'Administrator Dashboard'
    : 'Stock Manager Dashboard';
?>

<a class="back-link" href="<?= htmlspecialchars($dashboard) ?>">
    &larr; Back to <?= htmlspecialchars($dashboardName) ?>
</a>

<h1>Supplier Order Management</h1>
<p>Create supplier orders, approve purchases, and record deliveries.</p>

<?php if ($message !== ''): ?>
    <p class="success">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <p class="error">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </p>
<?php endif; ?>

<div class="statistics">
    <div class="card">
        <h3>Total Orders</h3>
        <p><?= $totalOrders ?></p>
    </div>

    <div class="card">
        <h3>Pending Orders</h3>
        <p><?= $pendingOrders ?></p>
    </div>

    <div class="card">
        <h3>Approved Orders</h3>
        <p><?= $approvedOrders ?></p>
    </div>

    <div class="card">
        <h3>Active Order Value</h3>
        <p><?= number_format($totalOrderValue, 2) ?> FCFA</p>
    </div>
</div>

<div class="card">
    <h2>Create a New Order</h2>

    <?php if (count($products) === 0): ?>
        <p class="error">
            No products are registered yet. Add products before creating an order.
        </p>
    <?php else: ?>

    <form method="POST" action="" id="orderForm">
        
        <input type="hidden" name="action" value="create_order">

        <label for="supplier_name">Supplier Name *</label>
        <input
            type="text"
            id="supplier_name"
            name="supplier_name"
            maxlength="150"
            required
        >

        <label for="order_date">Order Date *</label>
        <input
            type="date"
            id="order_date"
            name="order_date"
            value="<?= date('Y-m-d') ?>"
            required
        >

        <label for="expected_delivery_date">
            Expected Delivery Date
        </label>
        <input
            type="date"
            id="expected_delivery_date"
            name="expected_delivery_date"
        >

        <h3>Products to Order</h3>

        <div id="productRows">
            <div class="item-row">
                <div>
                    <label>Product *</label>
                    <select name="product_id[]" class="product-select" required>
                        <option value="">Select a product</option>

                        <?php foreach ($products as $product): ?>
                            <option
                                value="<?= (int) $product['product_id'] ?>"
                                data-cost="<?= htmlspecialchars((string) $product['purchase_price'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>
                                — Purchase cost:
                                <?= number_format((float) $product['purchase_price'], 2) ?> FCFA
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Quantity *</label>
                    <input
                        type="number"
                        name="quantity[]"
                        min="1"
                        step="1"
                        value="1"
                        required
                    >
                </div>
            </div>
        </div>

        <button type="button" id="addProduct">
            + Add Another Product
        </button>

        <label for="notes">Order Notes</label>
        <textarea id="notes" name="notes" rows="3"></textarea>

        <p class="muted">
            The total order amount is calculated using each product's
            current purchase price when the order is saved.
        </p>

        <button type="submit">Create Order</button>
    </form>

    <script>
        const productRows = document.getElementById('productRows');
        const addProductButton = document.getElementById('addProduct');

        addProductButton.addEventListener('click', function () {
            const firstRow = productRows.querySelector('.item-row');
            const newRow = firstRow.cloneNode(true);

            newRow.querySelector('.product-select').selectedIndex = 0;
            newRow.querySelector('input[name="quantity[]"]').value = 1;

            productRows.appendChild(newRow);
        });
    </script>

    <?php endif; ?>
</div>

<div class="card">
    <h2>Recorded Orders</h2>

    <?php if (count($orders) > 0): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Supplier</th>
                        <th>Order Date</th>
                        <th>Expected Delivery</th>
                        <th>Order Total</th>
                        <th>Delivery Progress</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($order['supplier_name'], ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($order['order_date'], ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $order['expected_delivery_date'] ?? 'Not specified',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= number_format((float) $order['total_amount'], 2) ?>
                            FCFA
                        </td>

                        <td>
                            <?= (int) $order['total_units_received'] ?>
                            /
                            <?= (int) $order['total_units_ordered'] ?>
                            units received
                        </td>

                        <td>
                            <?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>
                        </td>

                      <td>
    <a href="payments.php?order_id=<?= (int) $order['order_id'] ?>"
       style="display:inline-block;
              padding:8px 12px;
              margin-bottom:8px;
              background:#198754;
              color:white;
              text-decoration:none;
              border-radius:5px;">
        💳 Pay Now
    </a>

    <?php if (
    $order['status'] === 'Pending'
    && $roleId === 1
): ?>

                                <form method="POST" action="">
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="update_status"
                                    >

                                    <input
                                        type="hidden"
                                        name="order_id"
                                        value="<?= (int) $order['order_id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="Approved"
                                    >

                                    <button type="submit">
                                        Approve Order
                                    </button>
                                </form>

                                <form method="POST" action=""
                                      onsubmit="return confirm('Cancel this order?');">
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="update_status"
                                    >

                                    <input
                                        type="hidden"
                                        name="order_id"
                                        value="<?= (int) $order['order_id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="Cancelled"
                                    >

                                    <button type="submit" class="danger">
                                        Cancel Order
                                    </button>
                                </form>

                            <?php elseif (
    $order['status'] === 'Pending'
    && $roleId === 2
): ?>

    <span class="muted">
        Awaiting Administrator approval.
    </span>

<?php elseif ($order['status'] === 'Approved'): ?>

                                <?php
                                $itemsStmt = $pdo->prepare(
                                    "SELECT
                                        oi.order_item_id,
                                        oi.quantity_ordered,
                                        oi.quantity_received,
                                        p.product_name
                                     FROM order_items oi
                                     INNER JOIN products p
                                        ON p.product_id = oi.product_id
                                     WHERE oi.order_id = ?
                                     ORDER BY oi.order_item_id"
                                );

                                $itemsStmt->execute([
                                    (int) $order['order_id']
                                ]);

                                $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>

                                <form method="POST" action=""
                                      onsubmit="return confirm('Record these delivered quantities and update stock?');">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="receive_order"
                                    >

                                    <input
                                        type="hidden"
                                        name="order_id"
                                        value="<?= (int) $order['order_id'] ?>"
                                    >

                                    <?php foreach ($orderItems as $item): ?>
                                        <label>
                                            <?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </label>

                                        <p class="muted">
                                            Ordered:
                                            <?= (int) $item['quantity_ordered'] ?>;
                                            Already received:
                                            <?= (int) $item['quantity_received'] ?>;
                                            Remaining:
                                            <?= (int) $item['quantity_ordered']
                                                - (int) $item['quantity_received'] ?>
                                        </p>

                                        <input
                                            type="number"
                                            name="received_quantity[<?= (int) $item['order_item_id'] ?>]"
                                            min="0"
                                            max="<?= (int) $item['quantity_ordered']
                                                - (int) $item['quantity_received'] ?>"
                                            value="0"
                                            required
                                        >
                                    <?php endforeach; ?>

                                    <button type="submit">
                                        Record Delivery
                                    </button>
                                </form>

                            <?php else: ?>

                                <span class="muted">No further actions</span>

                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>No orders have been recorded yet.</p>
    <?php endif; ?>
</div>

</body>
</html>

