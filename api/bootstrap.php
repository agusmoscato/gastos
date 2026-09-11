<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$pdo = get_pdo();

$cats = $pdo->prepare('SELECT id, name, color FROM categories WHERE user_id = ? ORDER BY created_at ASC, id ASC');
$cats->execute([$uid]);

$tpls = $pdo->prepare('SELECT id, name, amount, category_id FROM templates WHERE user_id = ? ORDER BY id ASC');
$tpls->execute([$uid]);

// income_templates es una tabla nueva (v9): si todavía no se corrió esa
// migración, la app arranca igual y simplemente no hay ingresos frecuentes.
// Ojo: con PDO sin emular prepares, el "table doesn't exist" salta en el
// prepare(), así que el try tiene que envolverlo también.
$incomeTemplates = [];
try {
    $incomeTpls = $pdo->prepare('SELECT id, name, amount, category_id FROM income_templates WHERE user_id = ? ORDER BY id ASC');
    $incomeTpls->execute([$uid]);
    $incomeTemplates = $incomeTpls->fetchAll();
} catch (Throwable $e) {
    // sin migración todavía: la tarjeta de ingresos frecuentes queda vacía
}

// Settings es una tabla nueva (v5): si todavía no se corrió esa migración,
// que la app arranque igual con las preferencias por defecto en vez de romper.
$hiddenSections = [];
$notificationsEnabled = false;
try {
    $settingsStmt = $pdo->prepare('SELECT hidden_sections, notifications_enabled FROM user_settings WHERE user_id = ?');
    $settingsStmt->execute([$uid]);
    $settingsRow = $settingsStmt->fetch();
    if ($settingsRow && $settingsRow['hidden_sections']) {
        $decoded = json_decode($settingsRow['hidden_sections'], true);
        if (is_array($decoded)) $hiddenSections = $decoded;
    }
    $notificationsEnabled = $settingsRow ? (bool) $settingsRow['notifications_enabled'] : false;
} catch (Throwable $e) {
    // sin migración todavía: seguimos con los valores por defecto de arriba
}

json_response([
    'categories' => $cats->fetchAll(),
    'templates' => $tpls->fetchAll(),
    'incomeTemplates' => $incomeTemplates,
    'csrf' => csrf_token(),
    'settings' => [
        'hiddenSections' => $hiddenSections,
        'notificationsEnabled' => $notificationsEnabled,
    ],
    'recaptchaSiteKey' => recaptcha_enabled() ? RECAPTCHA_SITE_KEY : null,
]);
