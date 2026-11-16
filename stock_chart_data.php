
<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

$period = $_GET['period'] ?? '30days';

$allowedPeriods = ['7days', '30days', '6months', 'year'];

if (!in_array($period, $allowedPeriods, true)) {
    $period = '30days';
}

try {
    if ($period === '7days') {
        $startDate = date('Y-m-d', strtotime('-6 days'));
        $groupFormat = '%Y-%m-%d';
    } elseif ($period === '30days') {
        $startDate = date('Y-m-d', strtotime('-29 days'));
        $groupFormat = '%Y-%m-%d';
    } elseif ($period === '6months') {
        $startDate = date('Y-m-01', strtotime('-5 months'));
        $groupFormat = '%Y-%m';
    } else {
        $startDate = date('Y-01-01');
        $groupFormat = '%Y-%m';
    }

    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(movement_date, '$groupFormat') AS period,
            SUM(CASE WHEN movement_type = 'ENTRY'
                THEN quantity ELSE 0 END) AS entries,
            SUM(CASE WHEN movement_type = 'EXIT'
                THEN quantity ELSE 0 END) AS exits
        FROM stock_movements
        WHERE movement_date >= :start_date
        GROUP BY period
        ORDER BY period ASC
    ");

    $stmt->execute(['start_date' => $startDate]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $entries = [];
    $exits = [];

    foreach ($rows as $row) {
        $labels[] = $row['period'];
        $entries[] = (int) $row['entries'];
        $exits[] = (int) $row['exits'];
    }

    echo json_encode([
        'labels' => $labels,
        'entries' => $entries,
        'exits' => $exits
    ]);

} catch (PDOException $e) {
    http_response_code(500);

    echo json_encode([
        'error' => 'Unable to retrieve stock data.'
    ]);
}

