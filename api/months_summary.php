<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();

$count = min(12, max(2, (int) ($_GET['count'] ?? 6)));
$anchor = $_GET['month'] ?? current_month();
if (!is_valid_month($anchor)) $anchor = current_month();

$months = [];
for ($i = $count - 1; $i >= 0; $i--) {
    $months[] = shift_month($anchor, -$i);
}

$pdo = get_pdo();
$placeholders = implode(',', array_fill(0, count($months), '?'));

$spentStmt = $pdo->prepare("SELECT month, SUM(amount) AS total FROM expenses WHERE user_id = ? AND month IN ($placeholders) GROUP BY month");
$spentStmt->execute(array_merge([$uid], $months));
$spentMap = [];
foreach ($spentStmt->fetchAll() as $row) {
    $spentMap[$row['month']] = (float) $row['total'];
}

$incomeMap = [];
try {
    $incomeStmt = $pdo->prepare("SELECT month, SUM(amount) AS total FROM incomes WHERE user_id = ? AND month IN ($placeholders) GROUP BY month");
    $incomeStmt->execute(array_merge([$uid], $months));
    foreach ($incomeStmt->fetchAll() as $row) {
        $incomeMap[$row['month']] = (float) $row['total'];
    }
} catch (Throwable $e) {
    $legacyStmt = $pdo->prepare("SELECT month, income FROM month_settings WHERE user_id = ? AND month IN ($placeholders)");
    $legacyStmt->execute(array_merge([$uid], $months));
    foreach ($legacyStmt->fetchAll() as $row) {
        $incomeMap[$row['month']] = (float) $row['income'];
    }
}

$result = array_map(function ($m) use ($spentMap, $incomeMap) {
    return [
        'month' => $m,
        'label' => month_label($m),
        'spent' => $spentMap[$m] ?? 0,
        'income' => $incomeMap[$m] ?? 0,
    ];
}, $months);

json_response(['months' => $result]);
