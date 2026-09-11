<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$pdo = get_pdo();

function fetch_all_safe(PDO $pdo, string $sql, array $params): array {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return []; // tabla todavía no migrada: se exporta vacío en vez de romper el backup
    }
}

$backup = [
    'exportedAt' => date('c'),
    'categories' => fetch_all_safe($pdo, 'SELECT id, name, color FROM categories WHERE user_id = ?', [$uid]),
    'expenses' => fetch_all_safe($pdo, 'SELECT id, category_id, amount, description, expense_date, month, installment_id, installment_no, recurring_id FROM expenses WHERE user_id = ?', [$uid]),
    'budgets' => fetch_all_safe($pdo, 'SELECT category_id, month, amount FROM budgets WHERE user_id = ?', [$uid]),
    'monthSettings' => fetch_all_safe($pdo, 'SELECT month, income FROM month_settings WHERE user_id = ?', [$uid]),
    'incomes' => fetch_all_safe($pdo, 'SELECT id, category_id, amount, description, income_date, month FROM incomes WHERE user_id = ?', [$uid])
        ?: fetch_all_safe($pdo, 'SELECT id, amount, description, income_date, month FROM incomes WHERE user_id = ?', [$uid]),
    'templates' => fetch_all_safe($pdo, 'SELECT id, name, amount, category_id FROM templates WHERE user_id = ?', [$uid]),
    'installmentPurchases' => fetch_all_safe($pdo, 'SELECT id, category_id, description, total_amount, num_installments, first_month FROM installment_purchases WHERE user_id = ?', [$uid]),
    'recurringExpenses' => fetch_all_safe($pdo, 'SELECT id, category_id, name, amount, start_month, day_of_month, active FROM recurring_expenses WHERE user_id = ?', [$uid]),
    'incomeTemplates' => fetch_all_safe($pdo, 'SELECT id, name, amount, category_id FROM income_templates WHERE user_id = ?', [$uid]),
    'recurringIncomes' => fetch_all_safe($pdo, 'SELECT id, category_id, name, amount, start_month, day_of_month, active FROM recurring_incomes WHERE user_id = ?', [$uid]),
];

$filename = 'mi-libreta-backup-' . date('Y-m-d') . '.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
