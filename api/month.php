<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();

$month = $_GET['month'] ?? current_month();
if (!is_valid_month($month)) {
    json_response(['error' => 'Mes inválido'], 400);
}

$pdo = get_pdo();

try {
    materialize_recurring_expenses($pdo, $uid, $month);
} catch (Throwable $e) {
    // Si falta correr sql/upgrade_v5.sql, que no se rompa todo el mes por esto;
    // simplemente no se generan los gastos fijos hasta que se corra la migración.
}

try {
    materialize_recurring_incomes($pdo, $uid, $month);
} catch (Throwable $e) {
    // Si falta correr sql/upgrade_v9.sql (tabla recurring_incomes o columna
    // incomes.recurring_income_id), el mes se sigue mostrando igual; solo no
    // se generan los ingresos fijos hasta que se corra la migración.
}

// Filtros de la lista de ingresos (los del modal de ingresos). Son propios,
// separados de los de movimientos, porque cada lista tiene su buscador.
$incomeSearch = trim($_GET['income_q'] ?? '');
$incomeCategoryFilter = $_GET['income_category_id'] ?? '';

$incomeEntries = [];
$income = 0.0;
try {
    // Intento completo: lista de ingresos con su categoría (necesita upgrade_v8).
    try {
        $incomeStmt = $pdo->prepare(
            'SELECT i.id, i.amount, i.description, i.income_date, i.category_id,
                    c.name AS category_name, c.color AS category_color
             FROM incomes i
             LEFT JOIN categories c ON c.id = i.category_id
             WHERE i.user_id = ? AND i.month = ?
             ORDER BY i.income_date DESC, i.id DESC'
        );
        $incomeStmt->execute([$uid, $month]);
        $incomeRows = $incomeStmt->fetchAll();
    } catch (Throwable $e) {
        // Falta sql/upgrade_v8.sql (no existe incomes.category_id): la lista de
        // ingresos sigue andando igual, solo que sin la etiqueta de categoría.
        $incomeStmt = $pdo->prepare('SELECT id, amount, description, income_date FROM incomes WHERE user_id = ? AND month = ? ORDER BY income_date DESC, id DESC');
        $incomeStmt->execute([$uid, $month]);
        $incomeRows = array_map(function ($r) {
            $r['category_id'] = null;
            $r['category_name'] = null;
            $r['category_color'] = null;
            return $r;
        }, $incomeStmt->fetchAll());
    }
    // El total del mes ($income) se calcula SIEMPRE con todos los ingresos:
    // el buscador y el filtro solo achican la lista que se muestra, no el
    // número del ticket. Por eso filtramos acá y no en el SQL (la lista de
    // ingresos de un mes es corta, no hace falta otra consulta).
    foreach ($incomeRows as $r) {
        $income += (float) $r['amount'];

        if ($incomeSearch !== '' && stripos((string) $r['description'], $incomeSearch) === false) continue;
        if ($incomeCategoryFilter !== '' && (string) $r['category_id'] !== (string) $incomeCategoryFilter) continue;

        $incomeEntries[] = [
            'id' => (int) $r['id'],
            'amount' => (float) $r['amount'],
            'desc' => $r['description'],
            'date' => $r['income_date'],
            'categoryId' => $r['category_id'] !== null ? (int) $r['category_id'] : null,
            'categoryName' => $r['category_name'],
            'categoryColor' => $r['category_color'],
        ];
    }
} catch (Throwable $e) {
    // Todavía no se corrió sql/upgrade_v7.sql (no existe la tabla incomes):
    // mostramos el valor viejo de un solo ingreso por mes (si existía) y la
    // lista queda vacía por ahora.
    $legacyStmt = $pdo->prepare('SELECT income FROM month_settings WHERE user_id = ? AND month = ?');
    $legacyStmt->execute([$uid, $month]);
    $legacyIncome = $legacyStmt->fetchColumn();
    $income = $legacyIncome !== false ? (float) $legacyIncome : 0;
}

$budgetsStmt = $pdo->prepare('SELECT category_id, amount FROM budgets WHERE user_id = ? AND month = ?');
$budgetsStmt->execute([$uid, $month]);
$budgets = [];
foreach ($budgetsStmt->fetchAll() as $row) {
    $budgets[$row['category_id']] = (float) $row['amount'];
}

$search = trim($_GET['q'] ?? '');
$categoryFilter = $_GET['category_id'] ?? '';

$sql = "SELECT e.id, e.category_id, e.amount, e.description, e.expense_date,
               e.installment_no, ip.num_installments, e.recurring_id
        FROM expenses e
        LEFT JOIN installment_purchases ip ON ip.id = e.installment_id
        WHERE e.user_id = ? AND e.month = ?";
$params = [$uid, $month];
if ($search !== '') {
    $sql .= ' AND e.description LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($categoryFilter !== '') {
    $sql .= ' AND e.category_id = ?';
    $params[] = $categoryFilter;
}
$sql .= ' ORDER BY e.expense_date DESC, e.id DESC';

try {
    $expStmt = $pdo->prepare($sql);
    $expStmt->execute($params);
    $rawExpenses = $expStmt->fetchAll();
} catch (Throwable $e) {
    // Alguna columna nueva (recurring_id, etc.) todavía no existe porque falta
    // correr una migración. Reintentamos con la consulta clásica para que la
    // app siga funcionando igual, aunque sin esas funciones nuevas por ahora.
    $fallbackSql = "SELECT e.id, e.category_id, e.amount, e.description, e.expense_date
                     FROM expenses e WHERE e.user_id = ? AND e.month = ?";
    $fallbackParams = [$uid, $month];
    if ($search !== '') { $fallbackSql .= ' AND e.description LIKE ?'; $fallbackParams[] = '%' . $search . '%'; }
    if ($categoryFilter !== '') { $fallbackSql .= ' AND e.category_id = ?'; $fallbackParams[] = $categoryFilter; }
    $fallbackSql .= ' ORDER BY e.expense_date DESC, e.id DESC';
    $fallbackStmt = $pdo->prepare($fallbackSql);
    $fallbackStmt->execute($fallbackParams);
    $rawExpenses = array_map(function ($e) {
        $e['installment_no'] = null;
        $e['num_installments'] = null;
        $e['recurring_id'] = null;
        return $e;
    }, $fallbackStmt->fetchAll());
}

$expenses = array_map(function ($e) {
    return [
        'id' => (int) $e['id'],
        'categoryId' => $e['category_id'] !== null ? (int) $e['category_id'] : null,
        'amount' => (float) $e['amount'],
        'desc' => $e['description'],
        'date' => $e['expense_date'],
        'installmentNo' => $e['installment_no'] !== null ? (int) $e['installment_no'] : null,
        'installmentTotal' => $e['num_installments'] !== null ? (int) $e['num_installments'] : null,
        'recurringId' => $e['recurring_id'] !== null ? (int) $e['recurring_id'] : null,
    ];
}, $rawExpenses);

json_response([
    'month' => $month,
    'income' => $income,
    'incomeEntries' => $incomeEntries,
    'budgets' => $budgets,
    'expenses' => $expenses,
]);
