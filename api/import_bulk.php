<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

// type=expense (por defecto) o type=income: el CSV es el mismo
// (Fecha;Categoría;Descripción;Monto), solo cambia dónde se guarda.
$type = ($data['type'] ?? 'expense') === 'income' ? 'income' : 'expense';
$isIncome = $type === 'income';

$rows = $data['rows'] ?? [];
if (!is_array($rows) || count($rows) === 0) {
    json_response(['error' => 'No hay filas para importar'], 422);
}
if (count($rows) > 3000) {
    json_response(['error' => 'Demasiadas filas de una vez (máximo 3000). Partilo en tandas más chicas.'], 422);
}

$pdo = get_pdo();

// Categorías existentes del usuario, por nombre en minúscula, para no duplicar.
function safe_lower(string $s): string {
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

$catStmt = $pdo->prepare('SELECT id, name FROM categories WHERE user_id = ?');
$catStmt->execute([$uid]);
$categoryByName = [];
foreach ($catStmt->fetchAll() as $c) {
    $categoryByName[safe_lower(trim($c['name']))] = (int) $c['id'];
}

$insertCategory = $pdo->prepare('INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)');
$insertRow = $pdo->prepare(
    $isIncome
        ? 'INSERT INTO incomes (user_id, category_id, amount, description, income_date, month) VALUES (?, ?, ?, ?, ?, ?)'
        : 'INSERT INTO expenses (user_id, category_id, amount, description, expense_date, month) VALUES (?, ?, ?, ?, ?, ?)'
);

$imported = 0;
$skipped = 0;
$createdCategories = [];
$colorIndex = count($categoryByName);

$pdo->beginTransaction();
try {
    foreach ($rows as $row) {
        $dateStr = trim((string) ($row['date'] ?? ''));
        $categoryName = trim((string) ($row['category'] ?? ''));
        $desc = trim((string) ($row['desc'] ?? ''));
        $amount = (float) ($row['amount'] ?? 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr) || $amount <= 0) {
            $skipped++;
            continue;
        }

        $categoryId = null;
        if ($categoryName !== '') {
            $key = safe_lower($categoryName);
            if (isset($categoryByName[$key])) {
                $categoryId = $categoryByName[$key];
            } else {
                $color = PALETTE[$colorIndex % count(PALETTE)];
                $colorIndex++;
                $insertCategory->execute([$uid, $categoryName, $color]);
                $categoryId = (int) $pdo->lastInsertId();
                $categoryByName[$key] = $categoryId;
                $createdCategories[] = $categoryName;
            }
        }

        $month = substr($dateStr, 0, 7);
        $insertRow->execute([$uid, $categoryId, $amount, $desc, $dateStr, $month]);
        $imported++;
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(['error' => 'No se pudo importar: ' . $e->getMessage()], 500);
}

json_response([
    'imported' => $imported,
    'skipped' => $skipped,
    'createdCategories' => array_values(array_unique($createdCategories)),
]);
