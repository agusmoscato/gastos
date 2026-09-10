<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$name = trim((string) ($data['name'] ?? ''));
$amount = isset($data['amount']) && $data['amount'] !== '' ? (float) $data['amount'] : null;
$categoryId = isset($data['categoryId']) && $data['categoryId'] !== '' ? (int) $data['categoryId'] : null;
$dueDay = (int) ($data['dueDay'] ?? 0);
$recurring = !empty($data['recurring']);
$oneTimeMonth = (string) ($data['oneTimeMonth'] ?? '');

if ($name === '') json_response(['error' => 'Poné un nombre'], 422);
if ($dueDay < 1 || $dueDay > 31) json_response(['error' => 'El día del mes tiene que ser entre 1 y 31'], 422);
if ($amount !== null && $amount <= 0) json_response(['error' => 'El monto tiene que ser mayor a cero'], 422);
if (!$recurring && !is_valid_month($oneTimeMonth)) json_response(['error' => 'Elegí el mes en que vence'], 422);

$pdo = get_pdo();
if ($categoryId !== null) {
    $check = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
    $check->execute([$categoryId, $uid]);
    if (!$check->fetch()) json_response(['error' => 'Categoría inválida'], 422);
}

$stmt = $pdo->prepare(
    'INSERT INTO due_dates (user_id, category_id, name, amount, due_day, recurring, one_time_month, active)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
);
$stmt->execute([$uid, $categoryId, $name, $amount, $dueDay, $recurring ? 1 : 0, $recurring ? null : $oneTimeMonth]);

json_response(['id' => (int) $pdo->lastInsertId()], 201);
