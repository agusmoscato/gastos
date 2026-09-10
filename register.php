<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user_id()) { header('Location: /index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!recaptcha_verify($_POST['g-recaptcha-response'] ?? null)) {
        $error = 'Confirmá que no sos un robot.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresá un email válido.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña necesita al menos 6 caracteres.';
    } elseif ($password !== $password2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Ese email ya tiene una cuenta. Iniciá sesión.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
                $stmt->execute([$email, $hash]);
                $userId = (int) $pdo->lastInsertId();
                create_default_categories($pdo, $userId);
                $pdo->commit();

                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_email'] = $email;
                issue_remember_token($pdo, $userId);
                header('Location: /index.php');
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'No se pudo crear la cuenta. Probá de nuevo.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Crear cuenta · mi libreta</title>
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
    <div class="auth-subtitle">Creá tu cuenta para empezar</div>

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
        <input class="text-input" type="password" name="password" required minlength="6" placeholder="mínimo 6 caracteres">
      </div>
      <div class="field">
        <label class="label">Repetir contraseña</label>
        <input class="text-input" type="password" name="password2" required minlength="6" placeholder="repetila">
      </div>
      <?php if (recaptcha_enabled()): ?>
        <div class="field"><div class="g-recaptcha" data-sitekey="<?= htmlspecialchars(RECAPTCHA_SITE_KEY) ?>"></div></div>
      <?php endif; ?>
      <button class="primary-btn" type="submit">Crear cuenta</button>
    </form>

    <div class="auth-switch">¿Ya tenés cuenta? <a href="/login.php">Iniciá sesión</a></div>
  </div>
</body>
</html>
