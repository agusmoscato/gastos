<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$id = (int) ($data['id'] ?? 0);
$amount = isset($data['amount']) && $data['amount'] !== '' ? (float) $data['amount'] : null;
$categoryId = isset($data['categoryId']) && $data['categoryId'] !== '' ? (int) $data['categoryId'] : null;
$date = (string) ($data['date'] ?? date('Y-m-d'));
$createExpense = !array_key_exists('createExpense', $data) || !empty($data['createExpense']);

if ($id <= 0) json_response(['error' => 'Falta el id'], 422);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_response(['error' => 'Fecha inválida'], 422);

$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT * FROM due_dates WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $uid]);
$due = $stmt->fetch();
if (!$due) json_response(['error' => 'No encontrado'], 404);

$cycleMonth = $due['recurring'] ? current_month() : ($due['one_time_month'] ?: current_month());

$dupStmt = $pdo->prepare('SELECT id FROM due_date_payments WHERE due_date_id = ? AND month = ?');
$dupStmt->execute([$id, $cycleMonth]);
if ($dupStmt->fetch()) json_response(['error' => 'Ya estaba marcado como pagado este mes'], 422);

$finalAmount = $amount ?? ($due['amount'] !== null ? (float) $due['amount'] : null);
$finalCategory = $categoryId ?? ($due['category_id'] !== null ? (int) $due['category_id'] : null);

if ($createExpense && ($finalAmount === null || $finalAmount <= 0)) {
    json_response(['error' => 'Necesito un monto para cargar el gasto'], 422);
}

$pdo->beginTransaction();
try {
    $expenseId = null;
    if ($createExpense) {
        $insExpense = $pdo->prepare(
            'INSERT INTO expenses (user_id, category_id, amount, description, expense_date, month) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insExpense->execute([$uid, $finalCategory, $finalAmount, $due['name'], $date, substr($date, 0, 7)]);
        $expenseId = (int) $pdo->lastInsertId();
    }

    $insPay = $pdo->prepare(
        'INSERT INTO due_date_payments (due_date_id, month, expense_id) VALUES (?, ?, ?)'
    );
    $insPay->execute([$id, $cycleMonth, $expenseId]);

    // Los vencimientos de una sola vez se pausan solos una vez pagados.
    if (!$due['recurring']) {
        $pdo->prepare('UPDATE due_dates SET active = 0 WHERE id = ?')->execute([$id]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(['error' => 'No se pudo marcar como pagado'], 500);
}

json_response(['ok' => true, 'expenseId' => $expenseId]);
