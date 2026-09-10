-- =============================================================
-- Actualización para instalaciones que ya tenían la app funcionando
-- (agrega la función de "recuperar contraseña").
-- Si es una instalación NUEVA, no hace falta correr esto: ya está
-- incluido en sql/schema.sql.
--
-- En phpMyAdmin de tu base > pestaña SQL, pegar y ejecutar:
-- =============================================================

ALTER TABLE users
  ADD COLUMN reset_token VARCHAR(64) NULL,
  ADD COLUMN reset_expires DATETIME NULL;
