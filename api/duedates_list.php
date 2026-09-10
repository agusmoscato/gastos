<?php
require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_api();
$pdo = get_pdo();

$today = new DateTime(date('Y-m-d'));
$currentMonth = current_month();

try {
    $stmt = $pdo->prepare(
        'SELECT d.id, d.name, d.amount, d.due_day, d.recurring, d.one_time_month, d.active,
                d.category_id, c.name AS category_name, c.color AS category_color
         FROM due_dates d
         LEFT JOIN categories c ON c.id = d.category_id
         WHERE d.user_id = ? AND d.active = 1
         ORDER BY d.due_day ASC'
    );
    $stmt->execute([$uid]);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    json_response(['error' => 'Falta actualizar la base de datos: corré sql/upgrade_v6.sql (tabla de vencimientos no encontrada).'], 500);
}

$payStmt = $pdo->prepare('SELECT id FROM due_date_payments WHERE due_date_id = ? AND month = ?');

$result = [];
foreach ($rows as $r) {
    $cycleMonth = $r['recurring'] ? $currentMonth : ($r['one_time_month'] ?: $currentMonth);
    [$y, $m] = array_map('intval', explode('-', $cycleMonth));
    $lastDay = (int) date('t', strtotime(sprintf('%04d-%02d-01', $y, $m)));
    $day = min((int) $r['due_day'], $lastDay);
    $dueDateStr = sprintf('%s-%02d', $cycleMonth, $day);
    $dueDate = new DateTime($dueDateStr);
    $daysUntil = (int) $today->diff($dueDate)->format('%r%a');

    $payStmt->execute([$r['id'], $cycleMonth]);
    $paid = (bool) $payStmt->fetch();

    if ($paid) {
        $status = 'paid';
    } elseif ($daysUntil < 0) {
        $status = 'overdue';
    } elseif ($daysUntil <= 5) {
        $status = 'soon';
    } else {
        $status = 'upcoming';
    }

    $result[] = [
        'id' => (int) $r['id'],
        'name' => $r['name'],
        'amount' => $r['amount'] !== null ? (float) $r['amount'] : null,
        'dueDay' => (int) $r['due_day'],
        'recurring' => (bool) $r['recurring'],
        'oneTimeMonth' => $r['one_time_month'],
        'categoryId' => $r['category_id'] !== null ? (int) $r['category_id'] : null,
        'categoryName' => $r['category_name'] ?? 'Otros',
        'categoryColor' => $r['category_color'] ?? '#8B7355',
        'cycleMonth' => $cycleMonth,
        'dueDate' => $dueDateStr,
        'daysUntil' => $daysUntil,
        'paid' => $paid,
        'status' => $status,
    ];
}

// Los más urgentes primero: vencidos, después "pronto", después el resto por fecha.
$order = ['overdue' => 0, 'soon' => 1, 'upcoming' => 2, 'paid' => 3];
usort($result, function ($a, $b) use ($order) {
    $byStatus = $order[$a['status']] <=> $order[$b['status']];
    return $byStatus !== 0 ? $byStatus : ($a['daysUntil'] <=> $b['daysUntil']);
});

json_response(['dueDates' => $result]);
