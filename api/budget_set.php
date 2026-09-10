<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$month = (string) ($data['month'] ?? '');
$categoryId = (int) ($data['categoryId'] ?? 0);
$amount = (float) ($data['amount'] ?? 0);

if (!is_valid_month($month)) json_response(['error' => 'Mes inválido'], 422);
if ($amount < 0) json_response(['error' => 'El presupuesto no puede ser negativo'], 422);

$pdo = get_pdo();
$check = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
$check->execute([$categoryId, $uid]);
if (!$check->fetch()) json_response(['error' => 'Categoría inválida'], 422);

$stmt = $pdo->prepare(
    'INSERT INTO budgets (user_id, category_id, month, amount) VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE amount = VALUES(amount)'
);
$stmt->execute([$uid, $categoryId, $month, $amount]);

json_response(['ok' => true]);
