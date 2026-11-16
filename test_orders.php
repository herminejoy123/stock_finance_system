
<?php
echo "<h2>Supplier Order System Diagnostic</h2>";

echo "<p>PHP is working correctly.</p>";

echo "<p>Current folder: " . __DIR__ . "</p>";

echo "<p>order.php exists: ";
echo file_exists(__DIR__ . '/order.php') ? "YES" : "NO";
echo "</p>";

echo "<p>orders.php exists: ";
echo file_exists(__DIR__ . '/orders.php') ? "YES" : "NO";
echo "</p>";

echo "<p>Database configuration exists: ";
echo file_exists(__DIR__ . '/config/database.php') ? "YES" : "NO";
echo "</p>";

echo "<p>Authentication file exists: ";
echo file_exists(__DIR__ . '/config/auth_check.php') ? "YES" : "NO";
echo "</p>";
?>
