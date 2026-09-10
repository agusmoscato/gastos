<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

if (current_user_id()) { header('Location: /index.php'); exit; }

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresá un email válido.';
    } else {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Por seguridad mostramos siempre el mismo mensaje, exista o no el email.
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
            $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
                ->execute([$hash, $expires, $user['id']]);
            $resetUrl = app_base_url() . '/reset-password.php?token=' . $token;
            send_reset_email($email, $resetUrl);
        }
        $sent = true;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recuperar contraseña · mi libreta</title>
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32.png">
<link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
<link rel="stylesheet" href="/assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body class="auth-body">
  <div class="auth-card">
    <div class="auth-title">mi libreta</div>
    <div class="auth-subtitle">Recuperar contraseña</div>

    <?php if ($sent): ?>
      <div class="banner" style="margin-bottom:16px">
        <div class="banner-text">Si ese email tiene una cuenta, te mandamos un link para elegir una nueva contraseña. Revisá también la carpeta de spam.</div>
      </div>
      <div class="auth-switch"><a href="/login.php">Volver a iniciar sesión</a></div>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="auth-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post" novalidate>
        <div class="field">
          <label class="label">Email</label>
          <input class="text-input" type="email" name="email" required autofocus value="<?= htmlspecialchars($email ?? '') ?>" placeholder="tu@email.com">
        </div>
        <button class="primary-btn" type="submit">Enviar link de recuperación</button>
      </form>
      <div class="auth-switch"><a href="/login.php">Volver a iniciar sesión</a></div>
    <?php endif; ?>
  </div>
</body>
</html>
