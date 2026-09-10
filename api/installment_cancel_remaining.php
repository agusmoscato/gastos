<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$id = (int) ($data['id'] ?? 0);
if ($id <= 0) json_response(['error' => 'Falta el id'], 422);

$pdo = get_pdo();
$check = $pdo->prepare('SELECT id FROM installment_purchases WHERE id = ? AND user_id = ?');
$check->execute([$id, $uid]);
if (!$check->fetch()) json_response(['error' => 'No encontrada'], 404);

$today = current_month();

$pdo->beginTransaction();
try {
    // Cuántas cuotas ya "pasaron" (mes actual o anterior) — esas quedan.
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM expenses WHERE installment_id = ? AND month <= ?');
    $countStmt->execute([$id, $today]);
    $paidCount = (int) $countStmt->fetchColumn();

    // Borramos únicamente las cuotas futuras (todavía no llegó ese mes).
    $pdo->prepare('DELETE FROM expenses WHERE installment_id = ? AND month > ?')->execute([$id, $today]);

    // "Cerramos" la compra en la cantidad de cuotas que efectivamente quedaron.
    $newTotal = max($paidCount, 1);
    $pdo->prepare('UPDATE installment_purchases SET num_installments = ? WHERE id = ?')->execute([$newTotal, $id]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(['error' => 'No se pudo cancelar'], 500);
}

json_response(['ok' => true, 'newTotal' => $newTotal]);
