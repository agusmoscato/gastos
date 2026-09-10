<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$id = (int) ($data['id'] ?? 0);
if ($id <= 0) json_response(['error' => 'Falta el id'], 422);

$pdo = get_pdo();
$check = $pdo->prepare('SELECT id FROM expenses WHERE id = ? AND user_id = ?');
$check->execute([$id, $uid]);
if (!$check->fetch()) json_response(['error' => 'Gasto no encontrado'], 404);

$fields = [];
$params = [];

if (isset($data['amount'])) {
    $amount = (float) $data['amount'];
    if ($amount <= 0) json_response(['error' => 'El monto tiene que ser mayor a cero'], 422);
    $fields[] = 'amount = ?';
    $params[] = $amount;
}
if (isset($data['desc'])) {
    $fields[] = 'description = ?';
    $params[] = trim((string) $data['desc']);
}
if (array_key_exists('categoryId', $data)) {
    $categoryId = $data['categoryId'] !== '' && $data['categoryId'] !== null ? (int) $data['categoryId'] : null;
    if ($categoryId !== null) {
        $c = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
        $c->execute([$categoryId, $uid]);
        if (!$c->fetch()) json_response(['error' => 'Categoría inválida'], 422);
    }
    $fields[] = 'category_id = ?';
    $params[] = $categoryId;
}
if (isset($data['date'])) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) json_response(['error' => 'Fecha inválida'], 422);
    $fields[] = 'expense_date = ?';
    $params[] = $data['date'];
}

if (empty($fields)) json_response(['error' => 'Nada para actualizar'], 422);

$params[] = $id;
$params[] = $uid;
$sql = 'UPDATE expenses SET ' . implode(', ', $fields) . ' WHERE id = ? AND user_id = ?';
$pdo->prepare($sql)->execute($params);

json_response(['ok' => true]);
