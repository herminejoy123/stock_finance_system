<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$error = '';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$roleId = (int) ($_SESSION['role_id'] ?? 0);

// Protect payment forms against cross-site request forgery.
if (empty($_SESSION['payment_csrf_token'])) {
    $_SESSION['payment_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['payment_csrf_token'];

if (!in_array($roleId, [1, 2], true)) {
    http_response_code(403);
    exit('Access denied.');
}

$dashboard = ($roleId === 1) ? 'admin_dashboard.php' : 'stock_manager_dashboard.php';
$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
$saleId = filter_input(INPUT_GET, 'sale_id', FILTER_VALIDATE_INT);

$selectedOrder = null;
$selectedSale = null;
$supplierPaymentHistory = [];
$paymentHistory = [];
$invoices = [];

if (!function_exists('escape')) {
    function escape($value): string {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatMoney')) {
    function formatMoney($amount): string {
        return number_format((float) $amount, 2) . ' FCFA';
    }
}

// PROCESS CUSTOMER OR SUPPLIER PAYMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentType = $_POST['payment_type'] ?? 'customer';
    $amountInput = trim((string) ($_POST['amount_paid'] ?? ''));
    $method = trim((string) ($_POST['payment_method'] ?? ''));
    $transactionReference = trim((string) ($_POST['transaction_reference'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $paymentDateInput = trim((string) ($_POST['payment_date'] ?? ''));
    $postedCsrfToken = (string) ($_POST['csrf_token'] ?? '');

    $allowedMethods = ['Cash', 'Mobile Money', 'Bank Transfer', 'Other'];
    $amountValid = $amountInput !== ''
        && is_numeric($amountInput)
        && is_finite((float) $amountInput)
        && (float) $amountInput > 0;

    if (!hash_equals($csrfToken, $postedCsrfToken)) {
        $error = 'Your form session has expired. Refresh the page and try again.';
    } elseif ($userId < 1) {
        $error = 'Your session is invalid. Please log in again.';
    } elseif (!$amountValid) {
        $error = 'Enter a valid payment amount greater than zero.';
    } elseif (!in_array($method, $allowedMethods, true)) {
        $error = 'Please select a valid payment method.';
    } elseif (strlen($transactionReference) > 100) {
        $error = 'Transaction reference cannot exceed 100 characters.';
    } elseif (strlen($notes) > 500) {
        $error = 'Notes cannot exceed 500 characters.';
    } else {
        $amount = round((float) $amountInput, 2);

        // Accept a date selected by the user; otherwise use the current date/time.
        $paymentDate = date('Y-m-d H:i:s');
        if ($paymentDateInput !== '') {
            $dateObject = DateTime::createFromFormat('Y-m-d\\TH:i', $paymentDateInput);
            $dateErrors = DateTime::getLastErrors();
            if (
                !$dateObject ||
                ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            ) {
                $error = 'Please enter a valid payment date and time.';
            } else {
                $paymentDate = $dateObject->format('Y-m-d H:i:s');
            }
        }

        if ($error === '') {
        try {
            $pdo->beginTransaction();

            if ($paymentType === 'supplier') {
                $postedOrderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$postedOrderId || $postedOrderId < 1) {
                    throw new RuntimeException('Please select a valid supplier order.');
                }

                $stmt = $pdo->prepare(
                    "SELECT order_id, order_number, supplier_name, total_amount, status
                     FROM orders WHERE order_id = ? FOR UPDATE"
                );
                $stmt->execute([$postedOrderId]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$order) {
                    throw new RuntimeException('Supplier order not found.');
                }
                if (!in_array($order['status'], ['Approved', 'Received'], true)) {
                    throw new RuntimeException(
                        'Payments can only be recorded for approved or received orders.'
                    );
                }

                $stmt = $pdo->prepare(
                    "SELECT COALESCE(SUM(amount_paid), 0)
                     FROM supplier_payments WHERE order_id = ?"
                );
                $stmt->execute([$postedOrderId]);
                $alreadyPaid = (float) $stmt->fetchColumn();
                $balance = round((float) $order['total_amount'] - $alreadyPaid, 2);

                if ($balance <= 0) {
                    throw new RuntimeException('This supplier order has already been fully paid.');
                }
                if ($amount > $balance) {
                    throw new RuntimeException(
                        'Payment exceeds the remaining balance of ' . formatMoney($balance) . '.'
                    );
                }

                $paymentReference = 'SUP-' . date('YmdHis') . '-' .
                    strtoupper(bin2hex(random_bytes(4)));

                $stmt = $pdo->prepare(
                    "INSERT INTO supplier_payments
                     (order_id, payment_reference, amount_paid, payment_method,
                      transaction_reference, payment_date, notes, recorded_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $postedOrderId,
                    $paymentReference,
                    number_format($amount, 2, '.', ''),
                    $method,
                    $transactionReference !== '' ? $transactionReference : null,
                    $paymentDate,
                    $notes !== '' ? $notes : null,
                    $userId
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('The supplier payment was not inserted. Please try again.');
                }

                $pdo->commit();
                header('Location: payments.php?order_id=' . $postedOrderId . '&success=supplier');
                exit;

            } elseif ($paymentType === 'customer') {
                $postedSaleId = filter_var($_POST['sale_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$postedSaleId || $postedSaleId < 1) {
                    throw new RuntimeException('Please select a valid customer invoice.');
                }

                $stmt = $pdo->prepare(
                    "SELECT sale_id, total_amount FROM sales WHERE sale_id = ?" .
                    ($roleId === 1 ? "" : " AND user_id = ?") . " FOR UPDATE"
                );
                $stmt->execute($roleId === 1 ? [$postedSaleId] : [$postedSaleId, $userId]);
                $sale = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$sale) {
                    throw new RuntimeException('Customer invoice not found.');
                }

                $stmt = $pdo->prepare(
                    "SELECT COALESCE(SUM(amount_paid), 0)
                     FROM payments WHERE sale_id = ?"
                );
                $stmt->execute([$postedSaleId]);
                $alreadyPaid = (float) $stmt->fetchColumn();
                $balance = round((float) $sale['total_amount'] - $alreadyPaid, 2);

                if ($balance <= 0) {
                    throw new RuntimeException('This invoice has already been fully paid.');
                }
                if ($amount > $balance) {
                    throw new RuntimeException(
                        'Payment exceeds the remaining balance of ' . formatMoney($balance) . '.'
                    );
                }

                $paymentReference = 'PAY-' . date('YmdHis') . '-' .
                    strtoupper(bin2hex(random_bytes(4)));

                $stmt = $pdo->prepare(
                    "INSERT INTO payments
                     (sale_id, payment_reference, amount_paid, payment_method,
                      transaction_reference, payment_date, notes, recorded_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $postedSaleId,
                    $paymentReference,
                    number_format($amount, 2, '.', ''),
                    $method,
                    $transactionReference !== '' ? $transactionReference : null,
                    $paymentDate,
                    $notes !== '' ? $notes : null,
                    $userId
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('The customer payment was not inserted. Please try again.');
                }

                $pdo->commit();
                header('Location: payments.php?sale_id=' . $postedSaleId . '&success=customer');
                exit;
            } else {
                throw new RuntimeException('Invalid payment type.');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof RuntimeException) {
                $error = $exception->getMessage();
            } else {
                error_log('Payment processing error (type=' . ($paymentType ?? 'unknown') . ', user=' . $userId . '): ' . $exception->getMessage());
                // Detailed message is useful while testing locally with WAMP.
                $error = 'Database error: ' . $exception->getMessage();
            }
        }
        }
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'supplier') {
        $message = 'Supplier payment recorded successfully!';
    } elseif ($_GET['success'] === 'customer') {
        $message = 'Customer payment recorded successfully!';
    }
}

// LOAD ALL CUSTOMER INVOICES
try {
    $invoiceSql = "SELECT s.sale_id, s.invoice_number, s.client_name, s.created_at,
                          s.total_amount, COALESCE(SUM(p.amount_paid), 0) AS total_paid
                   FROM sales s
                   LEFT JOIN payments p ON p.sale_id = s.sale_id";
    if ($roleId !== 1) {
        $invoiceSql .= " WHERE s.user_id = :user_id";
    }
    $invoiceSql .= " GROUP BY s.sale_id, s.invoice_number, s.client_name, s.created_at, s.total_amount
                     ORDER BY s.created_at DESC";

    $stmt = $pdo->prepare($invoiceSql);
    if ($roleId !== 1) {
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    $invoices = [];
    $error = 'Unable to load invoices. Check the sales and payments tables.';
}

// LOAD SELECTED CUSTOMER INVOICE AND HISTORY
if ($saleId && $saleId > 0) {
    try {
        $selectedSaleSql = "SELECT s.sale_id, s.invoice_number, s.client_name, s.created_at,
                                   s.total_amount, COALESCE(SUM(p.amount_paid), 0) AS total_paid
                            FROM sales s
                            LEFT JOIN payments p ON p.sale_id = s.sale_id
                            WHERE s.sale_id = ?";
        if ($roleId !== 1) {
            $selectedSaleSql .= " AND s.user_id = ?";
        }
        $selectedSaleSql .= " GROUP BY s.sale_id, s.invoice_number, s.client_name, s.created_at, s.total_amount";

        $stmt = $pdo->prepare($selectedSaleSql);
        $stmt->execute($roleId === 1 ? [$saleId] : [$saleId, $userId]);
        $selectedSale = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($selectedSale) {
            $stmt = $pdo->prepare(
                "SELECT p.payment_reference, p.amount_paid, p.payment_method,
                        p.transaction_reference, p.payment_date, p.notes,
                        u.full_name AS recorded_by_name
                 FROM payments p
                 LEFT JOIN users u ON u.user_id = p.recorded_by
                 WHERE p.sale_id = ?
                 ORDER BY p.payment_date DESC, p.payment_id DESC"
            );
            $stmt->execute([$saleId]);
            $paymentHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $exception) {
        error_log('Customer payment history error: ' . $exception->getMessage());
        $error = 'Unable to load customer invoice payment history.';
    }
}

// LOAD SELECTED SUPPLIER ORDER AND HISTORY
if ($orderId && $orderId > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT o.order_id, o.order_number, o.supplier_name, o.order_date,
                    o.status, o.total_amount,
                    COALESCE(SUM(sp.amount_paid), 0) AS total_paid
             FROM orders o
             LEFT JOIN supplier_payments sp ON sp.order_id = o.order_id
             WHERE o.order_id = ?
             GROUP BY o.order_id, o.order_number, o.supplier_name,
                      o.order_date, o.status, o.total_amount"
        );
        $stmt->execute([$orderId]);
        $selectedOrder = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($selectedOrder) {
            $stmt = $pdo->prepare(
                "SELECT sp.payment_reference, sp.amount_paid, sp.payment_method,
                        sp.transaction_reference, sp.payment_date, sp.notes,
                        u.full_name AS recorded_by_name
                 FROM supplier_payments sp
                 LEFT JOIN users u ON u.user_id = sp.recorded_by
                 WHERE sp.order_id = ?
                 ORDER BY sp.payment_date DESC, sp.supplier_payment_id DESC"
            );
            $stmt->execute([$orderId]);
            $supplierPaymentHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $exception) {
        error_log('Supplier payment history error: ' . $exception->getMessage());
        $error = 'Unable to load supplier order payments. Check the orders and supplier_payments tables.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Management</title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:25px;background:#f4f6f9;color:#273449;font-family:Arial,sans-serif}
.container{max-width:1200px;margin:auto}
.card{background:white;padding:22px;margin:20px 0;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.07)}
.header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.muted{color:#687386}
.success,.error{padding:12px;border-radius:6px;margin:12px 0}
.success{background:#e4f7e9;color:#176534}
.error{background:#fdeaea;color:#a32121}
.button,button{display:inline-block;padding:9px 13px;border:none;border-radius:5px;background:#1769aa;color:white;text-decoration:none;cursor:pointer;font-size:14px}
.secondary{background:#687386}
.table-container{overflow-x:auto}
table{width:100%;border-collapse:collapse;margin-top:15px}
th,td{padding:12px;border-bottom:1px solid #e5e9ef;text-align:left}
th{background:#eef2f7}
.status{display:inline-block;padding:5px 9px;border-radius:15px;font-size:12px;font-weight:bold}
.paid{background:#e4f7e9;color:#176534}
.partial{background:#fff1c9;color:#805c00}
.unpaid{background:#fdeaea;color:#a32121}
.summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:20px 0}
.metric{background:#f1f4f8;padding:15px;border-radius:7px}
.metric small{display:block;margin-bottom:8px;color:#687386}
.metric strong{font-size:18px}
label{display:block;font-weight:bold;margin:12px 0 6px}
input,select,textarea{width:100%;padding:10px;border:1px solid #cbd3dd;border-radius:5px;font:inherit}
textarea{min-height:75px}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
@media(max-width:600px){body{padding:12px}.card{padding:15px}}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Payment Management</h1>
            <p class="muted">Manage customer invoice payments and supplier order payments.</p>
        </div>
        <a class="button secondary" href="<?= escape($dashboard) ?>">Back to Dashboard</a>
    </div>

    <?php if ($message): ?><div class="success"><?= escape($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= escape($error) ?></div><?php endif; ?>

    <!-- SUPPLIER ORDER PAYMENT -->
    <?php if ($orderId && $selectedOrder): ?>
        <?php
        $orderTotal = (float) $selectedOrder['total_amount'];
        $supplierTotalPaid = (float) $selectedOrder['total_paid'];
        $supplierBalance = max(0, round($orderTotal - $supplierTotalPaid, 2));
        $supplierStatus = $supplierBalance <= 0 ? 'Paid' :
            ($supplierTotalPaid > 0 ? 'Partially Paid' : 'Unpaid');
        $supplierStatusClass = $supplierBalance <= 0 ? 'paid' :
            ($supplierTotalPaid > 0 ? 'partial' : 'unpaid');
        $canPaySupplier = in_array($selectedOrder['status'], ['Approved', 'Received'], true);
        ?>
        <section class="card">
            <div class="header">
                <div>
                    <h2>Supplier Order <?= escape($selectedOrder['order_number']) ?></h2>
                    <p class="muted">
                        Supplier: <?= escape($selectedOrder['supplier_name']) ?><br>
                        Order Date: <?= escape($selectedOrder['order_date']) ?><br>
                        Order Status: <?= escape($selectedOrder['status']) ?>
                    </p>
                </div>
                <span class="status <?= $supplierStatusClass ?>"><?= escape($supplierStatus) ?></span>
            </div>
            <div class="summary">
                <div class="metric"><small>Order Total</small><strong><?= formatMoney($orderTotal) ?></strong></div>
                <div class="metric"><small>Total Paid</small><strong><?= formatMoney($supplierTotalPaid) ?></strong></div>
                <div class="metric"><small>Remaining Balance</small><strong><?= formatMoney($supplierBalance) ?></strong></div>
            </div>

            <?php if ($supplierBalance > 0 && $canPaySupplier): ?>
                <h2>Record Supplier Payment</h2>
                <form method="post" action="payments.php?order_id=<?= (int) $selectedOrder['order_id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                    <input type="hidden" name="payment_type" value="supplier">
                    <input type="hidden" name="order_id" value="<?= (int) $selectedOrder['order_id'] ?>">
                    <div class="form-grid">
                        <div>
                            <label for="supplier_amount_paid">Amount Paid (FCFA)</label>
                            <input type="number" id="supplier_amount_paid" name="amount_paid" min="0.01"
                                   max="<?= number_format($supplierBalance, 2, '.', '') ?>" step="0.01" required>
                        </div>
                        <div>
                            <label for="supplier_payment_method">Payment Method</label>
                            <select id="supplier_payment_method" name="payment_method" required>
                                <option value="">Select method</option>
                                <option value="Cash">Cash</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="supplier_transaction_reference">Transaction Reference (Optional)</label>
                            <input type="text" id="supplier_transaction_reference" name="transaction_reference"
                                   maxlength="100" placeholder="e.g. Mobile Money reference">
                        </div>
                    </div>
                    <label for="supplier_payment_date">Payment Date and Time</label>
                    <input type="datetime-local" id="supplier_payment_date" name="payment_date"
                           value="<?= escape(date('Y-m-d\\TH:i')) ?>" required>

                    <label for="supplier_notes">Notes (Optional)</label>
                    <textarea id="supplier_notes" name="notes" maxlength="500"
                              placeholder="Additional payment information"></textarea>
                    <br><br>
                    <button type="submit">Save Supplier Payment</button>
                    <a href="payments.php" class="button secondary">Back to Payments</a>
                </form>
            <?php elseif ($supplierBalance <= 0): ?>
                <p class="success">This supplier order is fully paid.</p>
            <?php else: ?>
                <p class="error">Payments can only be recorded for approved or received orders.</p>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Supplier Payment History</h2>
            <?php if (!$supplierPaymentHistory): ?>
                <p class="muted">No payments have been recorded for this supplier order.</p>
            <?php else: ?>
                <div class="table-container"><table>
                    <thead><tr><th>Reference</th><th>Date</th><th>Amount</th><th>Method</th><th>Transaction ID</th><th>Recorded By</th></tr></thead>
                    <tbody>
                    <?php foreach ($supplierPaymentHistory as $payment): ?>
                        <tr>
                            <td><?= escape($payment['payment_reference']) ?></td>
                            <td><?= escape($payment['payment_date']) ?></td>
                            <td><?= formatMoney($payment['amount_paid']) ?></td>
                            <td><?= escape($payment['payment_method']) ?></td>
                            <td><?= escape($payment['transaction_reference'] ?: '—') ?></td>
                            <td><?= escape($payment['recorded_by_name'] ?: 'Unknown') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </section>
    <?php elseif ($orderId): ?>
        <div class="error">The selected supplier order was not found.</div>
    <?php endif; ?>

    <!-- CUSTOMER INVOICE PAYMENT -->
    <?php if ($selectedSale): ?>
        <?php
        $invoiceTotal = (float) $selectedSale['total_amount'];
        $customerTotalPaid = (float) $selectedSale['total_paid'];
        $customerBalance = max(0, round($invoiceTotal - $customerTotalPaid, 2));
        if ($customerBalance <= 0) {
            $customerStatus = 'Paid'; $customerStatusClass = 'paid';
        } elseif ($customerTotalPaid > 0) {
            $customerStatus = 'Partially Paid'; $customerStatusClass = 'partial';
        } else {
            $customerStatus = 'Unpaid'; $customerStatusClass = 'unpaid';
        }
        ?>
        <section class="card">
            <div class="header">
                <div>
                    <h2>Invoice <?= escape($selectedSale['invoice_number']) ?></h2>
                    <p class="muted">Client: <?= escape($selectedSale['client_name']) ?><br>
                    Date: <?= escape($selectedSale['created_at']) ?></p>
                </div>
                <span class="status <?= $customerStatusClass ?>"><?= escape($customerStatus) ?></span>
            </div>
            <div class="summary">
                <div class="metric"><small>Invoice Total</small><strong><?= formatMoney($invoiceTotal) ?></strong></div>
                <div class="metric"><small>Total Paid</small><strong><?= formatMoney($customerTotalPaid) ?></strong></div>
                <div class="metric"><small>Remaining Balance</small><strong><?= formatMoney($customerBalance) ?></strong></div>
            </div>

            <?php if ($customerBalance > 0): ?>
                <h2>Record a Customer Payment</h2>
                <form method="post" action="payments.php?sale_id=<?= (int) $selectedSale['sale_id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                    <input type="hidden" name="payment_type" value="customer">
                    <input type="hidden" name="sale_id" value="<?= (int) $selectedSale['sale_id'] ?>">
                    <div class="form-grid">
                        <div>
                            <label for="customer_amount_paid">Amount Received (FCFA)</label>
                            <input type="number" id="customer_amount_paid" name="amount_paid" min="0.01"
                                   max="<?= number_format($customerBalance, 2, '.', '') ?>" step="0.01" required>
                        </div>
                        <div>
                            <label for="customer_payment_method">Payment Method</label>
                            <select id="customer_payment_method" name="payment_method" required>
                                <option value="">Select method</option>
                                <option value="Cash">Cash</option><option value="Mobile Money">Mobile Money</option>
                                <option value="Bank Transfer">Bank Transfer</option><option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="customer_transaction_reference">Transaction Reference (Optional)</label>
                            <input type="text" id="customer_transaction_reference" name="transaction_reference"
                                   maxlength="100" placeholder="e.g. Mobile Money reference">
                        </div>
                    </div>
                    <label for="customer_payment_date">Payment Date and Time</label>
                    <input type="datetime-local" id="customer_payment_date" name="payment_date"
                           value="<?= escape(date('Y-m-d\\TH:i')) ?>" required>

                    <label for="customer_notes">Notes (Optional)</label>
                    <textarea id="customer_notes" name="notes" maxlength="500"
                              placeholder="Additional payment information"></textarea>
                    <br><br>
                    <button type="submit">Save Customer Payment</button>
                    <a href="payments.php" class="button secondary">Cancel</a>
                </form>
            <?php else: ?>
                <p class="success">This invoice is fully paid. No further payment is required.</p>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Customer Payment History</h2>
            <?php if (!$paymentHistory): ?>
                <p class="muted">No payments have been recorded for this invoice.</p>
            <?php else: ?>
                <div class="table-container"><table>
                    <thead><tr><th>Reference</th><th>Date</th><th>Amount</th><th>Method</th><th>Transaction ID</th><th>Recorded By</th></tr></thead>
                    <tbody>
                    <?php foreach ($paymentHistory as $payment): ?>
                        <tr>
                            <td><?= escape($payment['payment_reference']) ?></td>
                            <td><?= escape($payment['payment_date']) ?></td>
                            <td><?= formatMoney($payment['amount_paid']) ?></td>
                            <td><?= escape($payment['payment_method']) ?></td>
                            <td><?= escape($payment['transaction_reference'] ?: '—') ?></td>
                            <td><?= escape($payment['recorded_by_name'] ?: 'Unknown') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </section>
    <?php elseif ($saleId): ?>
        <div class="error">The selected invoice was not found.</div>
    <?php endif; ?>

    <!-- ALL CUSTOMER INVOICES -->
    <section class="card">
        <h2>All Customer Invoices</h2>
        <p class="muted">Select an invoice to record a payment or review its payment history.</p>
        <?php if (!$invoices): ?>
            <p>No invoices found. Create a sale before recording a payment.</p>
        <?php else: ?>
            <div class="table-container"><table>
                <thead><tr><th>Invoice Number</th><th>Client</th><th>Date</th><th>Invoice Total</th><th>Total Paid</th><th>Balance</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <?php
                    $total = (float) $invoice['total_amount'];
                    $paid = (float) $invoice['total_paid'];
                    $remaining = max(0, round($total - $paid, 2));
                    if ($remaining <= 0) {
                        $invoiceStatus = 'Paid'; $invoiceClass = 'paid';
                    } elseif ($paid > 0) {
                        $invoiceStatus = 'Partially Paid'; $invoiceClass = 'partial';
                    } else {
                        $invoiceStatus = 'Unpaid'; $invoiceClass = 'unpaid';
                    }
                    ?>
                    <tr>
                        <td><?= escape($invoice['invoice_number']) ?></td>
                        <td><?= escape($invoice['client_name']) ?></td>
                        <td><?= escape($invoice['created_at']) ?></td>
                        <td><?= formatMoney($total) ?></td>
                        <td><?= formatMoney($paid) ?></td>
                        <td><?= formatMoney($remaining) ?></td>
                        <td><span class="status <?= $invoiceClass ?>"><?= escape($invoiceStatus) ?></span></td>
                        <td><a class="button" href="payments.php?sale_id=<?= (int) $invoice['sale_id'] ?>">Manage Payment</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
