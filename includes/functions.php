<?php
require_once __DIR__ . '/auth.php';

// Subir este número cada vez que se suba una actualización de assets/app.js
// o assets/style.css. Al cambiar, la URL de esos archivos cambia (?v=N) y
// el navegador (y cualquier service worker viejo que haya quedado activo)
// los va a pedir de nuevo sí o sí, en vez de servir una versión vieja cacheada.
const APP_VERSION = 'v12';

function json_response($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function json_body(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// Lee el body JSON y valida el token CSRF (header X-CSRF-Token o campo "csrf").
// Corta la ejecución con 403 si no es válido.
function require_csrf_api(): array {
    $data = json_body();
    $token = $data['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!csrf_valid($token)) {
        json_response(['error' => 'Token de seguridad inválido. Recargá la página e intentá de nuevo.'], 403);
    }
    return $data;
}

const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function month_label(string $id): string {
    [$y, $m] = explode('-', $id);
    return MESES[intval($m) - 1] . ' ' . $y;
}

function shift_month(string $id, int $delta): string {
    [$y, $m] = array_map('intval', explode('-', $id));
    $d = new DateTime(sprintf('%04d-%02d-01', $y, $m));
    $d->modify(($delta >= 0 ? '+' : '') . $delta . ' month');
    return $d->format('Y-m');
}

function current_month(): string {
    return date('Y-m');
}

function is_valid_month(string $m): bool {
    return (bool) preg_match('/^\d{4}-\d{2}$/', $m);
}

const DEFAULT_CATEGORIES = [
    ['name' => 'Comida', 'color' => '#AE4B3C'],
    ['name' => 'Deporte', 'color' => '#2F6F4E'],
    ['name' => 'Salidas', 'color' => '#C99A3B'],
    ['name' => 'Transporte', 'color' => '#3B7A8C'],
    ['name' => 'Cuotas / Seguros', 'color' => '#7A5C8C'],
    ['name' => 'Otros', 'color' => '#8B7355'],
];

const PALETTE = ['#2F6F4E','#AE4B3C','#C99A3B','#3B7A8C','#7A5C8C','#B5651D','#4A6B8A','#6B8E4E'];

function create_default_categories(PDO $pdo, int $userId): void {
    $stmt = $pdo->prepare('INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)');
    foreach (DEFAULT_CATEGORIES as $c) {
        $stmt->execute([$userId, $c['name'], $c['color']]);
    }
}

// ---------------------------------------------------------------------
// Límite de intentos de login (protección básica de fuerza bruta)
// ---------------------------------------------------------------------
const LOGIN_MAX_ATTEMPTS = 8;
const LOGIN_WINDOW_MINUTES = 15;

function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// true si este email/IP ya se pasó del límite de intentos fallidos recientes
function login_is_rate_limited(string $email): bool {
    $pdo = get_pdo();
    $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE (email = ? OR ip = ?) AND attempted_at > ?');
    $stmt->execute([$email, client_ip(), $since]);
    return ((int) $stmt->fetchColumn()) >= LOGIN_MAX_ATTEMPTS;
}

function login_register_attempt(string $email): void {
    $pdo = get_pdo();
    $pdo->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)')->execute([$email, client_ip()]);
}

function login_clear_attempts(string $email): void {
    $pdo = get_pdo();
    $pdo->prepare('DELETE FROM login_attempts WHERE email = ? OR ip = ?')->execute([$email, client_ip()]);
}

// ---------------------------------------------------------------------
// reCAPTCHA (opcional; si no hay claves configuradas, siempre pasa)
// ---------------------------------------------------------------------
function recaptcha_enabled(): bool {
    return defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== '';
}

function recaptcha_verify(?string $token): bool {
    if (!recaptcha_enabled()) return true;
    if (!$token) return false;

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => RECAPTCHA_SECRET_KEY,
            'response' => $token,
            'remoteip' => client_ip(),
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    if ($result === false) return false;

    $data = json_decode($result, true);
    return !empty($data['success']);
}

// ---------------------------------------------------------------------
// Gastos fijos recurrentes: al pedir un mes, generamos el gasto de ese
// mes para cada gasto fijo activo que ya debería existir, si todavía
// no fue generado (evita duplicados con un simple SELECT antes).
// ---------------------------------------------------------------------
function materialize_recurring_expenses(PDO $pdo, int $uid, string $month): void {
    $stmt = $pdo->prepare(
        'SELECT id, category_id, name, amount, day_of_month FROM recurring_expenses
         WHERE user_id = ? AND active = 1 AND start_month <= ?'
    );
    $stmt->execute([$uid, $month]);
    $recurring = $stmt->fetchAll();
    if (!$recurring) return;

    $checkStmt = $pdo->prepare('SELECT id FROM expenses WHERE recurring_id = ? AND month = ?');
    $insertStmt = $pdo->prepare(
        'INSERT INTO expenses (user_id, category_id, amount, description, expense_date, month, recurring_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    [$y, $m] = array_map('intval', explode('-', $month));
    $lastDay = (int) date('t', strtotime(sprintf('%04d-%02d-01', $y, $m)));

    foreach ($recurring as $r) {
        $checkStmt->execute([$r['id'], $month]);
        if ($checkStmt->fetch()) continue; // ya generado, no duplicar

        $day = min((int) $r['day_of_month'], $lastDay);
        $expenseDate = sprintf('%s-%02d', $month, $day);
        $insertStmt->execute([$uid, $r['category_id'], $r['amount'], $r['name'], $expenseDate, $month, $r['id']]);
    }
}

