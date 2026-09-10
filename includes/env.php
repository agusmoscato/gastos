<?php
/**
 * Carga variables desde un archivo .env que vive FUERA de public_html.
 *
 * Por qué: así, aunque compartas/subas la carpeta public_html entera
 * (zip, email, un repo de git, un backup, etc.), tu contraseña real de
 * la base de datos nunca viaja adentro. El .env se queda solo en el
 * servidor, en una carpeta que el navegador no puede pisar.
 *
 * En Hostinger: tu cuenta tiene una carpeta "home" y adentro está
 * public_html. Este archivo busca el .env un nivel arriba de
 * public_html, es decir directamente en tu home (fuera del webroot).
 */

function load_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Sacar comillas si el valor las tiene ("algo" o 'algo')
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, $default = null)
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

// Ubicación recomendada: un nivel arriba de public_html (fuera del webroot).
load_env(dirname(__DIR__, 2) . '/.env');

// Red de seguridad: si por algún motivo el .env quedó dentro de
// public_html, igual lo lee (pero .htaccess ya bloquea el acceso web
// directo a ese archivo — ver más abajo).
load_env(dirname(__DIR__) . '/.env');
