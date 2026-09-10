<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$id = (int) ($data['id'] ?? 0);
if ($id <= 0) json_response(['error' => 'Falta el id'], 422);

$pdo = get_pdo();
$check = $pdo->prepare('SELECT id FROM due_dates WHERE id = ? AND user_id = ?');
$check->execute([$id, $uid]);
if (!$check->fetch()) json_response(['error' => 'No encontrado'], 404);

// El historial de pagos (due_date_payments) se borra en cascada, pero los
// GASTOS que ya se generaron a partir de esos pagos quedan intactos
// (expenses.recurring... no aplica acá; el vinculo es solo informativo).
$pdo->prepare('DELETE FROM due_dates WHERE id = ? AND user_id = ?')->execute([$id, $uid]);

json_response(['ok' => true]);
