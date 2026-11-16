
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

    // Retrieve customer payments (income)
    $incomeStmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(payment_date, '$groupFormat') AS period,
            SUM(amount_paid) AS total
        FROM payments
        WHERE payment_date >= :start_date
        GROUP BY period
        ORDER BY period ASC
    ");

    $incomeStmt->execute(['start_date' => $startDate]);

    $incomeData = $incomeStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Retrieve business expenses
    $expenseStmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(expense_date, '$groupFormat') AS period,
            SUM(amount) AS total
        FROM expenses
        WHERE expense_date >= :start_date
        GROUP BY period
        ORDER BY period ASC
    ");

    $expenseStmt->execute(['start_date' => $startDate]);

    $expenseData = $expenseStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Generate chart labels
    $labels = [];

    if ($period === '7days' || $period === '30days') {

        $days = $period === '7days' ? 7 : 30;

        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = date('Y-m-d', strtotime("-$i days"));
        }

    } else {

        $months = $period === '6months' ? 6 : (int) date('n');

        for ($i = $months - 1; $i >= 0; $i--) {
            $labels[] = date('Y-m', strtotime("-$i months"));
        }
    }

    $income = [];
    $expenses = [];

    foreach ($labels as $label) {
        $income[] = (float) ($incomeData[$label] ?? 0);
        $expenses[] = (float) ($expenseData[$label] ?? 0);
    }

    $totalIncome = array_sum($income);
    $totalExpenses = array_sum($expenses);

    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'income' => $income,
        'expenses' => $expenses,
        'totalIncome' => $totalIncome,
        'totalExpenses' => $totalExpenses,
        'netCashFlow' => $totalIncome - $totalExpenses
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'error' => 'Unable to retrieve finance data.'
    ]);
}

