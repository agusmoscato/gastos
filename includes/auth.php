<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/remember.php';

if (session_status() === PHP_SESSION_NONE) {
    // Cookie de sesión más duradera (30 días) en vez de "hasta cerrar el navegador".
    // El login persistente real lo da igual el token de "recordarme" de abajo,
    // por si el servidor limpia la sesión antes de esos 30 días.
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Si no hay sesión pero el navegador manda la cookie de "recordarme",
// la restablecemos sola antes de que el resto de la página se ejecute.
if (empty($_SESSION['user_id'])) {
    attempt_remember_login();
}

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function current_user_email(): ?string {
    return $_SESSION['user_email'] ?? null;
}

// Para páginas normales (redirige al login si no hay sesión)
function require_login(): void {
    if (!current_user_id()) {
        header('Location: /login.php');
        exit;
    }
}

// Para endpoints de la API (devuelve JSON 401 en vez de redirigir)
function require_login_api(): int {
    $uid = current_user_id();
    if (!$uid) {
        json_response(['error' => 'No autenticado'], 401);
    }
    return $uid;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_valid(?string $token): bool {
    return isset($_SESSION['csrf_token']) && $token && hash_equals($_SESSION['csrf_token'], $token);
}
