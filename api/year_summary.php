<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();

$year = (int) ($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) $year = (int) date('Y');
$yearPrefix = sprintf('%04d-', $year);

$pdo = get_pdo();

$monthsStmt = $pdo->prepare('SELECT month, SUM(amount) AS total FROM expenses WHERE user_id = ? AND month LIKE ? GROUP BY month');
$monthsStmt->execute([$uid, $yearPrefix . '%']);
$spentByMonth = [];
foreach ($monthsStmt->fetchAll() as $r) { $spentByMonth[$r['month']] = (float) $r['total']; }

$incomeByMonth = [];
try {
    $incomeStmt = $pdo->prepare('SELECT month, SUM(amount) AS total FROM incomes WHERE user_id = ? AND month LIKE ? GROUP BY month');
    $incomeStmt->execute([$uid, $yearPrefix . '%']);
    foreach ($incomeStmt->fetchAll() as $r) { $incomeByMonth[$r['month']] = (float) $r['total']; }
} catch (Throwable $e) {
    $legacyStmt = $pdo->prepare('SELECT month, income FROM month_settings WHERE user_id = ? AND month LIKE ?');
    $legacyStmt->execute([$uid, $yearPrefix . '%']);
    foreach ($legacyStmt->fetchAll() as $r) { $incomeByMonth[$r['month']] = (float) $r['income']; }
}

$months = [];
$totalSpent = 0.0;
$totalIncome = 0.0;
for ($m = 1; $m <= 12; $m++) {
    $id = sprintf('%04d-%02d', $year, $m);
    $spent = $spentByMonth[$id] ?? 0;
    $income = $incomeByMonth[$id] ?? 0;
    $totalSpent += $spent;
    $totalIncome += $income;
    $months[] = ['month' => $id, 'label' => month_label($id), 'spent' => $spent, 'income' => $income];
}

$catStmt = $pdo->prepare(
    "SELECT COALESCE(c.id, 0) AS id, COALESCE(c.name, 'Otros') AS name, COALESCE(c.color, '#8B7355') AS color, SUM(e.amount) AS total
     FROM expenses e LEFT JOIN categories c ON c.id = e.category_id
     WHERE e.user_id = ? AND e.month LIKE ?
     GROUP BY COALESCE(c.id, 0), COALESCE(c.name, 'Otros'), COALESCE(c.color, '#8B7355')
     ORDER BY total DESC"
);
$catStmt->execute([$uid, $yearPrefix . '%']);
$categories = array_map(function ($r) {
    return ['id' => (int) $r['id'], 'name' => $r['name'], 'color' => $r['color'], 'spent' => (float) $r['total']];
}, $catStmt->fetchAll());

json_response([
    'year' => $year,
    'months' => $months,
    'categories' => $categories,
    'totalSpent' => $totalSpent,
    'totalIncome' => $totalIncome,
]);
