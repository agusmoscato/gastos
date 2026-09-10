<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$id = (int) ($data['id'] ?? 0);
if ($id <= 0) json_response(['error' => 'Falta el id'], 422);

$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT category_id, amount, description, expense_date, month FROM expenses WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $uid]);
$row = $stmt->fetch();
if (!$row) json_response(['error' => 'Gasto no encontrado'], 404);

$pdo->prepare('DELETE FROM expenses WHERE id = ? AND user_id = ?')->execute([$id, $uid]);

json_response([
    'ok' => true,
    'deleted' => [
        'categoryId' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
        'amount' => (float) $row['amount'],
        'desc' => $row['description'],
        'date' => $row['expense_date'],
        'month' => $row['month'],
    ],
]);
