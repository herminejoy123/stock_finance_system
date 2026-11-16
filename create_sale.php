
<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/database.php';

// Allow only Administrators and Stock Managers.
if (!in_array((int) $_SESSION['role_id'], [1, 2], true)) {
    http_response_code(403);
    exit('Access denied. You do not have permission to record sales.');
}
$message = '';
$error = '';
$invoiceId = null;
$selectedClientId = '';

$clients = $pdo->query(
    "SELECT client_id, client_name, phone, email
     FROM clients
     ORDER BY client_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$products = $pdo->query(
    "SELECT product_id, product_name, quantity,
            unit_price, purchase_price
     FROM products
     ORDER BY product_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Handle submitted sale.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $selectedClientId = filter_var($_POST['client_id'] ?? null, FILTER_VALIDATE_INT);
    $productIds = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if (!$selectedClientId || $selectedClientId < 1) {
        $error = 'Please select a registered client.';
    } elseif (!is_array($productIds) || !is_array($quantities)) {
        $error = 'Invalid product information.';
    } elseif (count($productIds) === 0) {
        $error = 'Please add at least one product.';
    } elseif (count($productIds) !== count($quantities)) {
        $error = 'Product and quantity information do not match.';
    } else {

        $requestedProducts = [];

        foreach ($productIds as $index => $id) {

            $productId = filter_var(
                $id,
                FILTER_VALIDATE_INT
            );

            $quantity = filter_var(
                $quantities[$index] ?? null,
                FILTER_VALIDATE_INT
            );

            if (
                !$productId || $productId < 1 ||
                !$quantity || $quantity < 1
            ) {
                $error = 'Select a product and enter a valid quantity.';
                break;
            }

            // Do not allow the same product on multiple lines.
            if (isset($requestedProducts[$productId])) {
                $error = 'A product cannot appear more than once. '
                       . 'Please combine its quantities into one line.';
                break;
            }

            $requestedProducts[$productId] = $quantity;
        }

        if (!$error) {
            try {
                $pdo->beginTransaction();

                $totalAmount = 0;
                $totalCost = 0;
                $saleItems = [];

                $findClient = $pdo->prepare(
                    "SELECT client_id, client_name FROM clients WHERE client_id = :client_id"
                );
                $findClient->execute(['client_id' => $selectedClientId]);
                $selectedClient = $findClient->fetch(PDO::FETCH_ASSOC);
                if (!$selectedClient) {
                    throw new Exception('The selected client was not found. Please choose a registered client.');
                }
                $clientName = $selectedClient['client_name'];

                // Lock and check each product's current stock.
                $checkProduct = $pdo->prepare(
                    "SELECT product_id, product_name, quantity,
                            unit_price, purchase_price
                     FROM products
                     WHERE product_id = :id
                     FOR UPDATE"
                );

                foreach ($requestedProducts as $productId => $quantity) {

                    $checkProduct->execute(['id' => $productId]);

                    $product = $checkProduct->fetch(PDO::FETCH_ASSOC);

                    if (!$product) {
                        throw new Exception(
                            'One of the selected products no longer exists.'
                        );
                    }

                    $available = (int) $product['quantity'];

                    if ($quantity > $available) {
                        throw new Exception(
                            'Insufficient stock! You requested '
                            . $quantity . ' units of '
                            . $product['product_name']
                            . ', but only ' . $available
                            . ' units are available.'
                        );
                    }

                    $sellingPrice = (float) $product['unit_price'];
                    $purchasePrice = (float) $product['purchase_price'];

                    $lineTotal = $sellingPrice * $quantity;
                    $lineCost = $purchasePrice * $quantity;
                    $lineProfit = $lineTotal - $lineCost;

                    $totalAmount += $lineTotal;
                    $totalCost += $lineCost;

                    $saleItems[] = [
                        'product_id' => (int) $product['product_id'],
                        'quantity' => $quantity,
                        'selling_price' => $sellingPrice,
                        'purchase_price' => $purchasePrice,
                        'line_total' => $lineTotal,
                        'line_cost' => $lineCost,
                        'line_profit' => $lineProfit
                    ];
                }

                $totalProfit = $totalAmount - $totalCost;

                // Create the sale and invoice number.
                $invoiceNumber = 'INV-' . date('YmdHis')
                               . '-' . bin2hex(random_bytes(3));
                               if (!isset($_SESSION['user_id'], $_SESSION['role_id'])) {
    die('Error: User session not found. Please log in again.');
}

                $insertSale = $pdo->prepare(
                    "INSERT INTO sales
                     (invoice_number, user_id, client_id, client_name, total_amount,
                      total_cost, total_profit)
                     VALUES
                     (:invoice, :user_id, :client_id, :client_name, :amount, :cost, :profit)"
                );

                $insertSale->execute([
                    'invoice' => $invoiceNumber,
                    'user_id' => $_SESSION['user_id'],
                    'client_id' => (int) $selectedClient['client_id'],
                    'client_name' => $clientName,
                    'amount' => $totalAmount,
                    'cost' => $totalCost,
                    'profit' => $totalProfit
                ]);

                $saleId = (int) $pdo->lastInsertId();

                $insertItem = $pdo->prepare(
                    "INSERT INTO sale_items
                     (sale_id, product_id, quantity,
                      unit_selling_price, unit_purchase_price,
                      line_total, line_cost, line_profit)
                     VALUES
                     (:sale_id, :product_id, :quantity,
                      :selling_price, :purchase_price,
                      :line_total, :line_cost, :line_profit)"
                );

                $updateStock = $pdo->prepare(
                    "UPDATE products
                     SET quantity = quantity - :quantity
                     WHERE product_id = :id
                       AND quantity >= :required_quantity"
                );

                foreach ($saleItems as $item) {

                    $insertItem->execute([
                        'sale_id' => $saleId,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'selling_price' => $item['selling_price'],
                        'purchase_price' => $item['purchase_price'],
                        'line_total' => $item['line_total'],
                        'line_cost' => $item['line_cost'],
                        'line_profit' => $item['line_profit']
                    ]);

                    $updateStock->execute([
                        'quantity' => $item['quantity'],
                        'id' => $item['product_id'],
                        'required_quantity' => $item['quantity']
                    ]);

                    if ($updateStock->rowCount() !== 1) {
                        throw new Exception(
                            'Stock changed during processing. '
                            . 'Please try again.'
                        );
                    }
                }

                $pdo->commit();

                // Show the invoice after a successful sale.
                header(
                    'Location: create_sale.php?invoice_id=' . $saleId
                );
                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}

// Retrieve invoice details when a sale has been saved.
$invoice = null;
$invoiceItems = [];

if (isset($_GET['invoice_id'])) {

    $requestedInvoiceId = filter_var(
        $_GET['invoice_id'],
        FILTER_VALIDATE_INT
    );

    if ($requestedInvoiceId && $requestedInvoiceId > 0) {

    $sql = "
        SELECT s.*,
               c.phone AS client_phone,
               c.email AS client_email,
               c.address AS client_address
        FROM sales s
        LEFT JOIN clients c ON c.client_id = s.client_id
        WHERE s.sale_id = :id
    ";

    $params = [
        'id' => $requestedInvoiceId
    ];

    // Administrator can view all invoices.
    if ((int) $_SESSION['role_id'] !== 1) {

        // Stock Manager can only view their own invoices.
        $sql .= " AND s.user_id = :user_id";

        $params['user_id'] = (int) $_SESSION['user_id'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invoice) {

        $stmt = $pdo->prepare(
            "SELECT si.*, p.product_name
             FROM sale_items si
             INNER JOIN products p
                 ON si.product_id = p.product_id
             WHERE si.sale_id = :id
             ORDER BY si.sale_item_id ASC"
        );

        $stmt->execute(['id' => $requestedInvoiceId]);

               $invoiceItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Sale and Invoice</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
            color: #333;
        }

        .container {
            max-width: 1000px;
            margin: 20px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        h1, h2 {
            color: #243b55;
        }

        .product-row {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 12px;
            margin-bottom: 12px;
            align-items: center;
        }

        select, input {
            width: 100%;
            box-sizing: border-box;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button, .button {
            padding: 11px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .add-button {
            background: #198754;
            color: white;
            margin-bottom: 20px;
        }

        .submit-button {
            background: #0d6efd;
            color: white;
        }

        .remove-button {
            background: #dc3545;
            color: white;
        }

        .error {
            color: #842029;
            background: #f8d7da;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .success {
            color: #0f5132;
            background: #d1e7dd;
            padding: 12px;
            border-radius: 5px;
        }

        .summary {
            background: #f1f5f9;
            padding: 18px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .summary p {
            margin: 10px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #e9eef5;
        }

        .invoice-total {
            font-size: 20px;
            font-weight: bold;
        }

        @media (max-width: 600px) {
            .product-row {
                grid-template-columns: 1fr;
            }

            body {
                padding: 10px;
            }

            .container {
                padding: 15px;
            }
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                background: white;
                padding: 0;
            }

            .container {
                margin: 0;
                max-width: none;
            }
        }
    </style>
</head>

<body>

<div class="container">

<?php if ($invoice): ?>

    <h1>Sales Invoice</h1>

    <p>
        <strong>Invoice Number:</strong>
        <?= htmlspecialchars($invoice['invoice_number']) ?>
    </p>

    <p>
        <strong>Client Name:</strong>
        <?= htmlspecialchars($invoice['client_name'] ?? 'Walk-in Customer', ENT_QUOTES, 'UTF-8') ?>
    </p>

    <?php if (!empty($invoice['client_phone'])): ?>
        <p><strong>Telephone:</strong> <?= htmlspecialchars($invoice['client_phone'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (!empty($invoice['client_email'])): ?>
        <p><strong>Email:</strong> <?= htmlspecialchars($invoice['client_email'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (!empty($invoice['client_address'])): ?>
        <p><strong>Address:</strong> <?= htmlspecialchars($invoice['client_address'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <p>
        <strong>Date:</strong>
        <?= htmlspecialchars($invoice['created_at']) ?>
    </p>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Unit Selling Price</th>
                <th>Total</th>
            </tr>
        </thead>

        <tbody>
        <?php foreach ($invoiceItems as $item): ?>
            <tr>
                <td>
                    <?= htmlspecialchars($item['product_name']) ?>
                </td>

                <td><?= (int) $item['quantity'] ?></td>

                <td>
                    <?= number_format(
                        (float) $item['unit_selling_price'],
                        2
                    ) ?> FCFA
                </td>

                <td>
                    <?= number_format(
                        (float) $item['line_total'],
                        2
                    ) ?> FCFA
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="summary">
        <p>
            Total selling amount:
            <strong>
                <?= number_format(
                    (float) $invoice['total_amount'],
                    2
                ) ?> FCFA
            </strong>
        </p>

        <p>
            Total purchase cost:
            <?= number_format(
                (float) $invoice['total_cost'],
                2
            ) ?> FCFA
        </p>

        <p>
            Estimated company profit:
            <strong>
                <?= number_format(
                    (float) $invoice['total_profit'],
                    2
                ) ?> FCFA
            </strong>
        </p>
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="submit-button">
            Print Invoice
        </button>

        <a href="create_sale.php" class="button add-button">
            Create Another Sale
        </a>
    </div>

<?php else: ?>

    <h1>Create Sale / Invoice</h1>

    <p>Select products and enter the quantities to sell.</p>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <p></p>
    <?php endif; ?>

    <?php if (empty($clients)): ?>

        <p>No clients are registered yet. Please
            <a href="manage_clients.php">register a client</a>
            before creating an invoice.
        </p>

    <?php elseif (empty($products)): ?>

        <p>No products are registered in the database yet.</p>

    <?php else: ?>

        <form method="POST" id="saleForm">

            <label for="client_id"><strong>Choose Client *</strong></label>
            <select id="client_id" name="client_id" required style="margin: 8px 0 20px;">
                <option value="">-- Select a registered client --</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= (int) $client['client_id'] ?>"
                        <?= (string) $selectedClientId === (string) $client['client_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($client['client_name'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($client['phone'])): ?>
                            — <?= htmlspecialchars($client['phone'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($clients)): ?>
                <p class="error">No clients are registered yet.
                    <a href="manage_clients.php">Register a client first</a>.
                </p>
            <?php endif; ?>

            <div class="product-row">
                <strong>Product</strong>
                <strong>Quantity</strong>
                <span></span>
            </div>

            <div id="productRows"></div>

            <button
                type="button"
                class="add-button"
                id="addProduct">
                + Add Product
            </button>

            <div class="summary">
                <h2>Invoice Summary</h2>

                <p>
                    Total selling amount:
                    <strong id="totalAmount">0.00 FCFA</strong>
                </p>

                <p>
                    Total purchase cost:
                    <strong id="totalCost">0.00 FCFA</strong>
                </p>

                <p>
                    Estimated company profit:
                    <strong id="totalProfit">0.00 FCFA</strong>
                </p>

                <p>
                    <small>
                        Estimates update as you select products and quantities.
                        The server recalculates all prices when you submit.
                    </small>
                </p>
            </div>

            <button type="submit" class="submit-button">
                Generate Invoice
            </button>

        </form>

    <?php endif; ?>

<?php endif; ?>

</div>

<script>
const products = <?= json_encode(
    array_map(
        function ($product) {
            return [
                'id' => (int) $product['product_id'],
                'name' => $product['product_name'],
                'quantity' => (int) $product['quantity'],
                'selling_price' => (float) $product['unit_price'],
                'purchase_price' => (float) $product['purchase_price']
            ];
        },
        $products
    ),
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
) ?>;

const productRows = document.getElementById('productRows');
const addProductButton = document.getElementById('addProduct');
const saleForm = document.getElementById('saleForm');

function formatMoney(amount) {
    return amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }) + ' FCFA';
}

function addRow() {
    const row = document.createElement('div');
    row.className = 'product-row';

    const select = document.createElement('select');
    select.name = 'product_id[]';
    select.required = true;

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Select a product';
    select.appendChild(placeholder);

    products.forEach(product => {
        const option = document.createElement('option');
        option.value = product.id;
        option.textContent =
            product.name + ' — ' +
            product.quantity + ' available';
        select.appendChild(option);
    });

    const quantity = document.createElement('input');
    quantity.type = 'number';
    quantity.name = 'quantity[]';
    quantity.min = '1';
    quantity.step = '1';
    quantity.placeholder = 'Enter quantity';
    quantity.required = true;

    const removeButton = document.createElement('button');
    removeButton.type = 'button';
    removeButton.className = 'remove-button';
    removeButton.textContent = 'Remove';

    select.addEventListener('change', updateSummary);
    quantity.addEventListener('input', updateSummary);

    removeButton.addEventListener('click', () => {
        row.remove();

        if (productRows.children.length === 0) {
            addRow();
        }

        updateSummary();
    });

    row.appendChild(select);
    row.appendChild(quantity);
    row.appendChild(removeButton);

    productRows.appendChild(row);

    updateSummary();
}

function updateSummary() {
    let totalAmount = 0;
    let totalCost = 0;

    const rows = productRows.querySelectorAll('.product-row');

    rows.forEach(row => {
        const productId = row.querySelector('select').value;
        const quantity = Number(
            row.querySelector('input').value
        );

        const product = products.find(
            item => String(item.id) === productId
        );

        if (product && quantity > 0) {
            totalAmount += product.selling_price * quantity;
            totalCost += product.purchase_price * quantity;
        }
    });

    document.getElementById('totalAmount').textContent =
        formatMoney(totalAmount);

    document.getElementById('totalCost').textContent =
        formatMoney(totalCost);

    document.getElementById('totalProfit').textContent =
        formatMoney(totalAmount - totalCost);
}

addProductButton?.addEventListener('click', addRow);

// Start with one product line.
if (productRows) {
    addRow();
}

// Warn before submission if requested stock exceeds availability.
saleForm?.addEventListener('submit', function (event) {
    const rows = productRows.querySelectorAll('.product-row');
    const requested = {};

    for (const row of rows) {
        const productId = row.querySelector('select').value;
        const quantity = Number(row.querySelector('input').value);

        const product = products.find(
            item => String(item.id) === productId
        );

        if (!product || !Number.isInteger(quantity) || quantity < 1) {
            continue;
        }

        requested[productId] =
            (requested[productId] || 0) + quantity;
    }

    for (const productId in requested) {
        const product = products.find(
            item => String(item.id) === productId
        );

        if (product && requested[productId] > product.quantity) {
            event.preventDefault();

            alert(
                'Insufficient stock! You requested ' +
                requested[productId] + ' units of ' +
                product.name + ', but only ' +
                product.quantity + ' units are available.'
            );

            return;
        }
    }
});
</script>

</body>
</html>
