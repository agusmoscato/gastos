<?php

function app_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'];
}

function send_reset_email(string $to, string $resetUrl): bool {
    $subject = "Recuperar contraseña - mi libreta";
    $body = "Pediste restablecer tu contraseña en mi libreta.\n\n"
        . "Entrá a este link para elegir una nueva (válido por 1 hora):\n"
        . $resetUrl . "\n\n"
        . "Si no fuiste vos, ignorá este mensaje: tu contraseña actual sigue funcionando.";

    $host = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    // El host no puede tener puerto en la dirección de "From"
    $host = preg_replace('/:\d+$/', '', $host);

    $headers = "From: mi libreta <no-reply@{$host}>\r\n"
        . "Content-Type: text/plain; charset=utf-8\r\n";

    return @mail($to, $subject, $body, $headers);
}
