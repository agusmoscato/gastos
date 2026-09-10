<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();

$pdo = get_pdo();
$today = current_month();

try {
    $stmt = $pdo->prepare(
        "SELECT ip.id, ip.description, ip.total_amount, ip.num_installments, ip.first_month,
                ip.category_id, c.name AS category_name, c.color AS category_color,
                SUM(CASE WHEN e.month <= ? THEN 1 ELSE 0 END) AS paid_count
         FROM installment_purchases ip
         LEFT JOIN categories c ON c.id = ip.category_id
         LEFT JOIN expenses e ON e.installment_id = ip.id
         WHERE ip.user_id = ?
         GROUP BY ip.id, ip.description, ip.total_amount, ip.num_installments, ip.first_month, ip.category_id, c.name, c.color
         ORDER BY ip.first_month DESC, ip.id DESC"
    );
    $stmt->execute([$today, $uid]);
    $result = $stmt->fetchAll();
} catch (Throwable $e) {
    json_response(['error' => 'Falta actualizar la base de datos: corré sql/upgrade_v4.sql (tabla de compras en cuotas no encontrada).'], 500);
}

$rows = array_map(function ($r) {
    return [
        'id' => (int) $r['id'],
        'desc' => $r['description'],
        'totalAmount' => (float) $r['total_amount'],
        'numInstallments' => (int) $r['num_installments'],
        'firstMonth' => $r['first_month'],
        'categoryId' => $r['category_id'] !== null ? (int) $r['category_id'] : null,
        'categoryName' => $r['category_name'] ?? 'Otros',
        'categoryColor' => $r['category_color'] ?? '#8B7355',
        'paidCount' => min((int) $r['paid_count'], (int) $r['num_installments']),
    ];
}, $result);

json_response(['installments' => $rows]);
