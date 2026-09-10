<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$name = trim((string) ($data['name'] ?? ''));
$color = (string) ($data['color'] ?? '#8B7355');

if ($name === '') json_response(['error' => 'El nombre no puede estar vacío'], 422);
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) $color = '#8B7355';

$pdo = get_pdo();
$stmt = $pdo->prepare('INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)');
$stmt->execute([$uid, $name, $color]);

json_response(['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'color' => $color], 201);
