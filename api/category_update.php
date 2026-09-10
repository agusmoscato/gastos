<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$id = (int) ($data['id'] ?? 0);
$name = trim((string) ($data['name'] ?? ''));
$color = (string) ($data['color'] ?? '');

if ($id <= 0) json_response(['error' => 'Falta el id'], 422);
if ($name === '') json_response(['error' => 'El nombre no puede estar vacío'], 422);
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) json_response(['error' => 'Color inválido'], 422);

$pdo = get_pdo();
$check = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
$check->execute([$id, $uid]);
if (!$check->fetch()) json_response(['error' => 'Categoría no encontrada'], 404);

$pdo->prepare('UPDATE categories SET name = ?, color = ? WHERE id = ? AND user_id = ?')
    ->execute([$name, $color, $id, $uid]);

json_response(['ok' => true]);
