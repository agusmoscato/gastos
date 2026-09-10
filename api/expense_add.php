<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$amount = (float) ($data['amount'] ?? 0);
$categoryId = isset($data['categoryId']) && $data['categoryId'] !== '' ? (int) $data['categoryId'] : null;
$desc = trim((string) ($data['desc'] ?? ''));
$date = (string) ($data['date'] ?? '');
$month = (string) ($data['month'] ?? '');

if ($amount <= 0) json_response(['error' => 'El monto tiene que ser mayor a cero'], 422);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_response(['error' => 'Fecha inválida'], 422);
if (!is_valid_month($month)) json_response(['error' => 'Mes inválido'], 422);

$pdo = get_pdo();

if ($categoryId !== null) {
    $check = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
    $check->execute([$categoryId, $uid]);
    if (!$check->fetch()) json_response(['error' => 'Categoría inválida'], 422);
}

$stmt = $pdo->prepare('INSERT INTO expenses (user_id, category_id, amount, description, expense_date, month) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute([$uid, $categoryId, $amount, $desc, $date, $month]);

json_response(['id' => (int) $pdo->lastInsertId()], 201);
