<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();

$month = $_GET['month'] ?? '';
$all = ($month === 'all');
if (!$all && !is_valid_month($month)) {
    json_response(['error' => 'Mes inválido'], 400);
}

$pdo = get_pdo();
$sql = 'SELECT e.expense_date, e.amount, e.description, COALESCE(c.name, "Otros") AS category
        FROM expenses e LEFT JOIN categories c ON c.id = e.category_id
        WHERE e.user_id = ?';
$params = [$uid];
if (!$all) {
    $sql .= ' AND e.month = ?';
    $params[] = $month;
}
$sql .= ' ORDER BY e.expense_date ASC, e.id ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filename = $all ? 'gastos-todos.csv' : "gastos-$month.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel lea bien los acentos
fputcsv($out, ['Fecha', 'Categoría', 'Descripción', 'Monto'], ';');
foreach ($rows as $r) {
    fputcsv($out, [$r['expense_date'], $r['category'], $r['description'], $r['amount']], ';');
}
fclose($out);
exit;
