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

$pdo->prepare('DELETE FROM incomes WHERE id = ? AND user_id = ?')->execute([$id, $uid]);

json_response(['ok' => true]);
