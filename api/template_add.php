<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$name = trim((string) ($data['name'] ?? ''));
$amount = (float) ($data['amount'] ?? 0);
$categoryId = isset($data['categoryId']) && $data['categoryId'] !== '' ? (int) $data['categoryId'] : null;

if ($name === '') json_response(['error' => 'Poné un nombre para la plantilla'], 422);
if ($amount <= 0) json_response(['error' => 'El monto tiene que ser mayor a cero'], 422);

$pdo = get_pdo();
if ($categoryId !== null) {
    $check = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
    $check->execute([$categoryId, $uid]);
    if (!$check->fetch()) json_response(['error' => 'Categoría inválida'], 422);
}

$stmt = $pdo->prepare('INSERT INTO templates (user_id, category_id, name, amount) VALUES (?, ?, ?, ?)');
$stmt->execute([$uid, $categoryId, $name, $amount]);

json_response(['id' => (int) $pdo->lastInsertId()], 201);
