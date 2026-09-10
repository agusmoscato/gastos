<?php
// Este archivo YA NO tiene contraseñas reales adentro.
// Los datos de conexión ahora viven en un archivo .env fuera de
// public_html (ver .env.example en la raíz para el formato exacto y
// el README para cómo crearlo en Hostinger).
//
// Esto es a propósito: así podés compartir/subir esta carpeta
// (zip, git, etc.) sin exponer tu contraseña real de MySQL.

require_once __DIR__ . '/env.php';

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', ''));
define('DB_USER', env('DB_USER', ''));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// Google reCAPTCHA (opcional, protege el registro y el login de bots).
// Sacá tus claves gratis en https://www.google.com/recaptcha/admin
// Elegí "reCAPTCHA v2" > "Casilla \"No soy un robot\"" y agregá tu dominio.
// Si dejás RECAPTCHA_SITE_KEY vacío, el captcha simplemente no aparece
// (la app funciona igual, solo que sin esa protección extra).
define('RECAPTCHA_SITE_KEY', env('RECAPTCHA_SITE_KEY', ''));
define('RECAPTCHA_SECRET_KEY', env('RECAPTCHA_SECRET_KEY', ''));

if (DB_NAME === '' || DB_USER === '') {
    // Falta el .env o está mal ubicado: mensaje claro en vez de un
    // error críptico de MySQL más adelante.
    http_response_code(500);
    die('Falta configurar la base de datos: creá el archivo .env (ver .env.example) con tus datos de Hostinger.');
}
