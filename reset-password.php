<?php
require_once __DIR__ . '/includes/functions.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$error = '';
$success = false;
$validToken = false;
$user = null;

if ($token) {
    $hash = hash('sha256', $token);
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, reset_expires FROM users WHERE reset_token = ?');
    $stmt->execute([$hash]);
    $user = $stmt->fetch();
    if ($user && $user['reset_expires'] && strtotime($user['reset_expires']) > time()) {
        $validToken = true;
    }
}

if ($validToken && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    if (strlen($password) < 6) {
        $error = 'La contraseña necesita al menos 6 caracteres.';
    } elseif ($password !== $password2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
            ->execute([$newHash, $user['id']]);
        $pdo->prepare('DELETE FROM auth_tokens WHERE user_id = ?')->execute([$user['id']]);
        $success = true;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Elegir nueva contraseña · mi libreta</title>
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32.png">
<link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
<link rel="stylesheet" href="/assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body class="auth-body">
  <div class="auth-card">
    <div class="auth-title">mi libreta</div>

    <?php if ($success): ?>
      <div class="auth-subtitle">Contraseña actualizada</div>
      <div class="banner" style="margin-bottom:16px"><div class="banner-text">Ya podés iniciar sesión con tu nueva contraseña.</div></div>
      <a class="primary-btn" style="display:block;text-align:center;text-decoration:none" href="/login.php">Iniciar sesión</a>

    <?php elseif (!$validToken): ?>
      <div class="auth-subtitle">Link inválido o vencido</div>
      <div class="auth-error">Este link ya no sirve. Pedí uno nuevo.</div>
      <a class="primary-btn" style="display:block;text-align:center;text-decoration:none" href="/forgot-password.php">Pedir un link nuevo</a>

    <?php else: ?>
      <div class="auth-subtitle">Elegí tu nueva contraseña</div>
      <?php if ($error): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="field">
          <label class="label">Nueva contraseña</label>
          <input class="text-input" type="password" name="password" required minlength="6" autofocus placeholder="mínimo 6 caracteres">
        </div>
        <div class="field">
          <label class="label">Repetirla</label>
          <input class="text-input" type="password" name="password2" required minlength="6" placeholder="repetila">
        </div>
        <button class="primary-btn" type="submit">Guardar nueva contraseña</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
