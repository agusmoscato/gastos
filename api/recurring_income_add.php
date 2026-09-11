<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$name = trim((string) ($data['name'] ?? ''));
$amount = (float) ($data['amount'] ?? 0);
$categoryId = isset($data['categoryId']) && $data['categoryId'] !== '' ? (int) $data['categoryId'] : null;
$startMonth = (string) ($data['startMonth'] ?? current_month());
$dayOfMonth = (int) ($data['dayOfMonth'] ?? 1);

if ($name === '') json_response(['error' => 'Poné un nombre para el ingreso fijo'], 422);
if ($amount <= 0) json_response(['error' => 'El monto tiene que ser mayor a cero'], 422);
if (!is_valid_month($startMonth)) json_response(['error' => 'Mes inválido'], 422);
if ($dayOfMonth < 1 || $dayOfMonth > 31) $dayOfMonth = 1;

$pdo = get_pdo();
if ($categoryId !== null) {
    $check = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
    $check->execute([$categoryId, $uid]);
    if (!$check->fetch()) json_response(['error' => 'Categoría inválida'], 422);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO recurring_incomes (user_id, category_id, name, amount, start_month, day_of_month, active)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    );
    $stmt->execute([$uid, $categoryId, $name, $amount, $startMonth, $dayOfMonth]);
} catch (Throwable $e) {
    json_response(['error' => 'Falta actualizar la base de datos: corré sql/upgrade_v9.sql (tabla de ingresos fijos no encontrada).'], 500);
}

json_response(['id' => (int) $pdo->lastInsertId()], 201);
