<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$hiddenSections = $data['hiddenSections'] ?? null;
$notificationsEnabled = $data['notificationsEnabled'] ?? null;

$pdo = get_pdo();
$existsStmt = $pdo->prepare('SELECT user_id FROM user_settings WHERE user_id = ?');
$existsStmt->execute([$uid]);
$exists = (bool) $existsStmt->fetchColumn();

if (!$exists) {
    $pdo->prepare('INSERT INTO user_settings (user_id, hidden_sections, notifications_enabled) VALUES (?, ?, ?)')
        ->execute([$uid, json_encode([]), 0]);
}

$fields = [];
$params = [];
if (is_array($hiddenSections)) {
    $allowed = array_values(array_filter($hiddenSections, 'is_string'));
    $fields[] = 'hidden_sections = ?';
    $params[] = json_encode($allowed);
}
if ($notificationsEnabled !== null) {
    $fields[] = 'notifications_enabled = ?';
    $params[] = $notificationsEnabled ? 1 : 0;
}

if (!empty($fields)) {
    $params[] = $uid;
    $pdo->prepare('UPDATE user_settings SET ' . implode(', ', $fields) . ' WHERE user_id = ?')->execute($params);
}

json_response(['ok' => true]);
