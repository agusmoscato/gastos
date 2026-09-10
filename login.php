<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user_id()) { header('Location: /index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (login_is_rate_limited($email)) {
        $error = 'Demasiados intentos. Esperá unos minutos y probá de nuevo.';
    } elseif (!recaptcha_verify($_POST['g-recaptcha-response'] ?? null)) {
        $error = 'Confirmá que no sos un robot.';
    } else {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id, email, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            login_register_attempt($email);
            $error = 'Email o contraseña incorrectos.';
        } else {
            login_clear_attempts($email);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_email'] = $user['email'];
            if (($_POST['remember'] ?? '0') === '1') {
                issue_remember_token($pdo, (int) $user['id']);
            }
            header('Location: /index.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sesión · mi libreta</title>
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32.png">
<link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
<link rel="stylesheet" href="/assets/style.css?v=<?= APP_VERSION ?>">
<?php if (recaptcha_enabled()): ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
</head>
<body class="auth-body">
  <div class="auth-card">
    <div class="auth-title">mi libreta</div>
    <div class="auth-subtitle">Entrá para ver tus gastos</div>

    <?php if ($error): ?>
      <div class="auth-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="field">
        <label class="label">Email</label>
        <input class="text-input" type="email" name="email" required value="<?= htmlspecialchars($email ?? '') ?>" placeholder="tu@email.com">
      </div>
      <div class="field">
        <label class="label">Contraseña</label>
        <input class="text-input" type="password" name="password" required placeholder="tu contraseña">
      </div>
      <label class="remember-row">
        <input type="hidden" name="remember" value="0">
        <input type="checkbox" name="remember" value="1" checked>
        <span>Mantener la sesión iniciada en este dispositivo</span>
      </label>
      <?php if (recaptcha_enabled()): ?>
        <div class="field"><div class="g-recaptcha" data-sitekey="<?= htmlspecialchars(RECAPTCHA_SITE_KEY) ?>"></div></div>
      <?php endif; ?>
      <button class="primary-btn" type="submit">Entrar</button>
    </form>

    <div class="auth-switch" style="margin-top:10px"><a href="/forgot-password.php">¿Olvidaste tu contraseña?</a></div>
    <div class="auth-switch">¿No tenés cuenta? <a href="/register.php">Creá una</a></div>
  </div>
</body>
</html>
