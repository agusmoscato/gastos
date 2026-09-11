<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$id = (int) ($data['id'] ?? 0);
if ($id <= 0) json_response(['error' => 'Falta el id'], 422);

$pdo = get_pdo();
$check = $pdo->prepare('SELECT id FROM recurring_incomes WHERE id = ? AND user_id = ?');
$check->execute([$id, $uid]);
if (!$check->fetch()) json_response(['error' => 'No encontrado'], 404);

// ON DELETE SET NULL en incomes.recurring_income_id: los ingresos que ya
// generó quedan intactos, solo se borra la "receta" que los generaba.
$pdo->prepare('DELETE FROM recurring_incomes WHERE id = ? AND user_id = ?')->execute([$id, $uid]);

json_response(['ok' => true]);
