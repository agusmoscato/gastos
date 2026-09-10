<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$id = (int) ($data['id'] ?? 0);
if ($id <= 0) json_response(['error' => 'Falta el id'], 422);

$pdo = get_pdo();
$check = $pdo->prepare('SELECT id FROM incomes WHERE id = ? AND user_id = ?');
$check->execute([$id, $uid]);
if (!$check->fetch()) json_response(['error' => 'No encontrado'], 404);

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
        $catCheck = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
        $catCheck->execute([$categoryId, $uid]);
        if (!$catCheck->fetch()) json_response(['error' => 'Categoría inválida'], 422);
    }
    $fields[] = 'category_id = ?';
    $params[] = $categoryId;
}
if (isset($data['date'])) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) json_response(['error' => 'Fecha inválida'], 422);
    $fields[] = 'income_date = ?';
    $params[] = $data['date'];
    $fields[] = 'month = ?';
    $params[] = substr($data['date'], 0, 7);
}

if (empty($fields)) json_response(['error' => 'Nada para actualizar'], 422);

$params[] = $id;
$params[] = $uid;

try {
    $pdo->prepare('UPDATE incomes SET ' . implode(', ', $fields) . ' WHERE id = ? AND user_id = ?')->execute($params);
} catch (Throwable $e) {
    // Todavía no se corrió sql/upgrade_v8.sql: reintentamos sin tocar la
    // categoría para que el resto de la edición (monto, fecha, texto) igual funcione.
    $idx = array_search('category_id = ?', $fields, true);
    if ($idx === false) throw $e;
    array_splice($fields, $idx, 1);
    array_splice($params, $idx, 1);
    if (count($fields) === 0) json_response(['ok' => true]);
    $pdo->prepare('UPDATE incomes SET ' . implode(', ', $fields) . ' WHERE id = ? AND user_id = ?')->execute($params);
}

json_response(['ok' => true]);
