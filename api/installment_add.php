<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$data = require_csrf_api();

$desc = trim((string) ($data['desc'] ?? ''));
$totalAmount = (float) ($data['totalAmount'] ?? 0);
$numInstallments = (int) ($data['numInstallments'] ?? 0);
$categoryId = isset($data['categoryId']) && $data['categoryId'] !== '' ? (int) $data['categoryId'] : null;
$firstMonth = (string) ($data['firstMonth'] ?? current_month());
$day = (int) ($data['day'] ?? date('j'));

if ($desc === '') json_response(['error' => 'Poné una descripción para la compra'], 422);
if ($totalAmount <= 0) json_response(['error' => 'El monto total tiene que ser mayor a cero'], 422);
if ($numInstallments < 2 || $numInstallments > 60) json_response(['error' => 'La cantidad de cuotas tiene que ser entre 2 y 60'], 422);
if (!is_valid_month($firstMonth)) json_response(['error' => 'Mes inválido'], 422);
if ($day < 1 || $day > 31) $day = 1;

$pdo = get_pdo();
if ($categoryId !== null) {
    $check = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
    $check->execute([$categoryId, $uid]);
    if (!$check->fetch()) json_response(['error' => 'Categoría inválida'], 422);
}

// Repartimos el total en cuotas iguales; la última se ajusta para que la suma
// dé exactamente el monto total (evita diferencias de centavos por redondeo).
$base = round($totalAmount / $numInstallments, 2);
$lastAmount = round($totalAmount - $base * ($numInstallments - 1), 2);

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO installment_purchases (user_id, category_id, description, total_amount, num_installments, first_month)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$uid, $categoryId, $desc, $totalAmount, $numInstallments, $firstMonth]);
    $installmentId = (int) $pdo->lastInsertId();

    $insExpense = $pdo->prepare(
        'INSERT INTO expenses (user_id, category_id, amount, description, expense_date, month, installment_id, installment_no)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );

    for ($i = 0; $i < $numInstallments; $i++) {
        $month = shift_month($firstMonth, $i);
        $amount = ($i === $numInstallments - 1) ? $lastAmount : $base;
        [$y, $m] = explode('-', $month);
        $lastDay = (int) date('t', strtotime("$y-$m-01"));
        $dd = min($day, $lastDay);
        $expenseDate = sprintf('%s-%02d', $month, $dd);
        $insExpense->execute([$uid, $categoryId, $amount, $desc, $expenseDate, $month, $installmentId, $i + 1]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(['error' => 'No se pudo crear la compra en cuotas'], 500);
}

json_response(['id' => $installmentId], 201);
