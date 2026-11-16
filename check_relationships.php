<?php
require_once __DIR__ . '/config/database.php';

echo "<h1>Supplier Order Database Check</h1>";

// 1. Check storage engines
echo "<h2>1. Table Storage Engines</h2>";

$tables = ['users', 'products', 'orders', 'order_items', 'supplier_payments'];

foreach ($tables as $table) {
    $stmt = $pdo->prepare("
        SELECT ENGINE
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
    ");

    $stmt->execute([$table]);
    $engine = $stmt->fetchColumn();

    echo htmlspecialchars($table) . ": "
        . htmlspecialchars($engine ?: 'Table not found')
        . "<br>";
}

// 2. Check records without matching parent records
echo "<h2>2. Orphan Record Checks</h2>";

$checks = [
    "Orders with missing users" => "
        SELECT COUNT(*)
        FROM orders o
        LEFT JOIN users u ON o.created_by = u.user_id
        WHERE u.user_id IS NULL
    ",

    "Order items with missing orders" => "
        SELECT COUNT(*)
        FROM order_items oi
        LEFT JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_id IS NULL
    ",

    "Order items with missing products" => "
        SELECT COUNT(*)
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE p.product_id IS NULL
    ",

    "Supplier payments with missing orders" => "
        SELECT COUNT(*)
        FROM supplier_payments sp
        LEFT JOIN orders o ON sp.order_id = o.order_id
        WHERE o.order_id IS NULL
    "
];

foreach ($checks as $label => $sql) {
    $count = $pdo->query($sql)->fetchColumn();

    echo htmlspecialchars($label) . ": "
        . (int) $count . "<br>";
}

echo "<h2>Database check completed.</h2>";
echo "<h2>3. Foreign Key Verification</h2>";

$stmt = $pdo->query("
    SELECT
        TABLE_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN (
        'orders',
        'order_items',
        'supplier_payments'
    )
    AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY TABLE_NAME
");

$foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$foreignKeys) {
    echo "No foreign keys found.";
} else {
    echo "<pre>";
    print_r($foreignKeys);
    echo "</pre>";
}
?>