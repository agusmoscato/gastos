<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();

// type=expense (por defecto) o type=income: mismo CSV, misma cabecera,
// solo cambia la tabla de la que sale.
$type = ($_GET['type'] ?? 'expense') === 'income' ? 'income' : 'expense';
$isIncome = $type === 'income';
$table = $isIncome ? 'incomes' : 'expenses';
$dateColumn = $isIncome ? 'income_date' : 'expense_date';

$month = $_GET['month'] ?? '';
$all = ($month === 'all');
if (!$all && !is_valid_month($month)) {
    json_response(['error' => 'Mes inválido'], 400);
}

$pdo = get_pdo();
$sql = "SELECT t.$dateColumn AS row_date, t.amount, t.description, COALESCE(c.name, \"Otros\") AS category
        FROM $table t LEFT JOIN categories c ON c.id = t.category_id
        WHERE t.user_id = ?";
$params = [$uid];
if (!$all) {
    $sql .= ' AND t.month = ?';
    $params[] = $month;
}
$sql .= " ORDER BY t.$dateColumn ASC, t.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$prefix = $isIncome ? 'ingresos' : 'gastos';
$filename = $all ? "$prefix-todos.csv" : "$prefix-$month.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel lea bien los acentos
fputcsv($out, ['Fecha', 'Categoría', 'Descripción', 'Monto'], ';');
foreach ($rows as $r) {
    fputcsv($out, [$r['row_date'], $r['category'], $r['description'], $r['amount']], ';');
}
fclose($out);
exit;
