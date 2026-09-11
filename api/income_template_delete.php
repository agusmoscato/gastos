<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$id = (int) ($data['id'] ?? 0);
if ($id <= 0) json_response(['error' => 'Falta el id'], 422);

$pdo = get_pdo();
$pdo->prepare('DELETE FROM income_templates WHERE id = ? AND user_id = ?')->execute([$id, $uid]);

json_response(['ok' => true]);
