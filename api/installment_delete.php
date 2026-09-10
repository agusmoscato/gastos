<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$id = (int) ($data['id'] ?? 0);
if ($id <= 0) json_response(['error' => 'Falta el id'], 422);

$pdo = get_pdo();
$check = $pdo->prepare('SELECT id FROM installment_purchases WHERE id = ? AND user_id = ?');
$check->execute([$id, $uid]);
if (!$check->fetch()) json_response(['error' => 'No encontrada'], 404);

// El ON DELETE CASCADE de expenses.installment_id borra de paso
// todas las cuotas generadas (pasadas y futuras).
$pdo->prepare('DELETE FROM installment_purchases WHERE id = ? AND user_id = ?')->execute([$id, $uid]);

json_response(['ok' => true]);
