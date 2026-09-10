<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$month = (string) ($data['month'] ?? '');
$fromMonth = (string) ($data['fromMonth'] ?? '');
if (!is_valid_month($month) || !is_valid_month($fromMonth)) {
    json_response(['error' => 'Mes inválido'], 422);
}

$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT category_id, amount FROM budgets WHERE user_id = ? AND month = ? AND amount > 0');
$stmt->execute([$uid, $fromMonth]);
$rows = $stmt->fetchAll();

$ins = $pdo->prepare(
    'INSERT INTO budgets (user_id, category_id, month, amount) VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE amount = VALUES(amount)'
);
foreach ($rows as $r) {
    $ins->execute([$uid, $r['category_id'], $month, $r['amount']]);
}

json_response(['ok' => true, 'copied' => count($rows)]);
