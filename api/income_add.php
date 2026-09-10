<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$amount = (float) ($data['amount'] ?? 0);
$categoryId = isset($data['categoryId']) && $data['categoryId'] !== '' ? (int) $data['categoryId'] : null;
$desc = trim((string) ($data['desc'] ?? ''));
$date = (string) ($data['date'] ?? date('Y-m-d'));

if ($amount <= 0) json_response(['error' => 'El monto tiene que ser mayor a cero'], 422);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_response(['error' => 'Fecha inválida'], 422);

$month = substr($date, 0, 7);
$pdo = get_pdo();

if ($categoryId !== null) {
    $check = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
    $check->execute([$categoryId, $uid]);
    if (!$check->fetch()) json_response(['error' => 'Categoría inválida'], 422);
}

try {
    $stmt = $pdo->prepare('INSERT INTO incomes (user_id, category_id, amount, description, income_date, month) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$uid, $categoryId, $amount, $desc, $date, $month]);
} catch (Throwable $e) {
    // Todavía no se corrió sql/upgrade_v8.sql (falta la columna category_id):
    // guardamos el ingreso igual, sin categoría, para no bloquear la carga.
    $stmt = $pdo->prepare('INSERT INTO incomes (user_id, amount, description, income_date, month) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$uid, $amount, $desc, $date, $month]);
}

json_response(['id' => (int) $pdo->lastInsertId()], 201);
