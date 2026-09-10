<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$pdo = get_pdo();

try {
    $stmt = $pdo->prepare(
        'SELECT r.id, r.name, r.amount, r.start_month, r.day_of_month, r.active,
                r.category_id, c.name AS category_name, c.color AS category_color
         FROM recurring_expenses r
         LEFT JOIN categories c ON c.id = r.category_id
         WHERE r.user_id = ?
         ORDER BY r.active DESC, r.created_at ASC'
    );
    $stmt->execute([$uid]);
    $result = $stmt->fetchAll();
} catch (Throwable $e) {
    json_response(['error' => 'Falta actualizar la base de datos: corré sql/upgrade_v5.sql (tabla de gastos fijos no encontrada).'], 500);
}

$rows = array_map(function ($r) {
    return [
        'id' => (int) $r['id'],
        'name' => $r['name'],
        'amount' => (float) $r['amount'],
        'startMonth' => $r['start_month'],
        'dayOfMonth' => (int) $r['day_of_month'],
        'active' => (bool) $r['active'],
        'categoryId' => $r['category_id'] !== null ? (int) $r['category_id'] : null,
        'categoryName' => $r['category_name'] ?? 'Otros',
        'categoryColor' => $r['category_color'] ?? '#8B7355',
    ];
}, $result);

json_response(['recurring' => $rows]);
