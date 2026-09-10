<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$month = (string) ($data['month'] ?? '');
$income = (float) ($data['income'] ?? 0);
if (!is_valid_month($month)) json_response(['error' => 'Mes inválido'], 422);
if ($income < 0) json_response(['error' => 'El ingreso no puede ser negativo'], 422);

$pdo = get_pdo();
$stmt = $pdo->prepare(
    'INSERT INTO month_settings (user_id, month, income) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE income = VALUES(income)'
);
$stmt->execute([$uid, $month, $income]);

json_response(['ok' => true]);
