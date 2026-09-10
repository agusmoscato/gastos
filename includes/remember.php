<?php
require_once __DIR__ . '/db.php';

const REMEMBER_COOKIE = 'libreta_remember';
const REMEMBER_DAYS = 30;

function remember_cookie_options(int $expiresAt): array {
    return [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

// Crea un token nuevo, lo guarda (hasheado) en la base y lo manda como cookie.
function issue_remember_token(PDO $pdo, int $userId): void {
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $validatorHash = hash('sha256', $validator);
    $expiresAt = time() + REMEMBER_DAYS * 86400;
    $expiresSql = date('Y-m-d H:i:s', $expiresAt);

    $pdo->prepare('INSERT INTO auth_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $selector, $validatorHash, $expiresSql]);

    setcookie(REMEMBER_COOKIE, $selector . ':' . $validator, remember_cookie_options($expiresAt));
}

// Borra el token actual (de la base y la cookie). Se usa al hacer logout.
function clear_remember_token(): void {
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
        if (count($parts) === 2) {
            try {
                get_pdo()->prepare('DELETE FROM auth_tokens WHERE selector = ?')->execute([$parts[0]]);
            } catch (Throwable $e) { /* si falla no pasa nada, igual borramos la cookie */ }
        }
    }
    setcookie(REMEMBER_COOKIE, '', remember_cookie_options(time() - 3600));
}

// Si no hay sesión activa pero existe la cookie de "recordarme", intenta
// restablecer la sesión sola. Devuelve true si logró loguear al usuario.
function attempt_remember_login(): bool {
    if (empty($_COOKIE[REMEMBER_COOKIE])) return false;

    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
    if (count($parts) !== 2) { clear_remember_token(); return false; }
    [$selector, $validator] = $parts;

    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT user_id, validator_hash, expires_at FROM auth_tokens WHERE selector = ?');
        $stmt->execute([$selector]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }

    if (!$row || strtotime($row['expires_at']) < time()) {
        clear_remember_token();
        return false;
    }
    if (!hash_equals($row['validator_hash'], hash('sha256', $validator))) {
        // El validador no coincide: posible robo de cookie. Invalidamos todo por las dudas.
        $pdo->prepare('DELETE FROM auth_tokens WHERE user_id = ?')->execute([$row['user_id']]);
        clear_remember_token();
        return false;
    }

    $userStmt = $pdo->prepare('SELECT id, email FROM users WHERE id = ?');
    $userStmt->execute([$row['user_id']]);
    $user = $userStmt->fetch();
    if (!$user) { clear_remember_token(); return false; }

    // Token usado: lo rotamos (uno nuevo cada vez) para que uno robado deje de servir.
    $pdo->prepare('DELETE FROM auth_tokens WHERE selector = ?')->execute([$selector]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_email'] = $user['email'];

    issue_remember_token($pdo, (int) $user['id']);
    return true;
}
