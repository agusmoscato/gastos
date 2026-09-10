-- =============================================================
-- Actualización: ingresos por categoría.
-- Agrega la columna category_id a la tabla incomes, reutilizando
-- la misma tabla categories que ya usan los gastos. Así podés
-- etiquetar de dónde viene cada ingreso (sueldo, freelance, venta...)
-- con el mismo sistema de categorías y colores.
--
-- Si es una instalación NUEVA, no hace falta correr esto: ya está
-- incluido en sql/schema.sql.
--
-- Es seguro correr este script más de una vez.
-- En phpMyAdmin > pestaña SQL, pegar y ejecutar TODO de una vez:
-- =============================================================

ALTER TABLE incomes ADD COLUMN IF NOT EXISTS category_id INT UNSIGNED NULL AFTER user_id;

-- La FK se agrega solo si todavía no existe, así este script se puede
-- correr más de una vez sin tirar error de "duplicate".
-- ON DELETE SET NULL: si borrás una categoría, sus ingresos quedan
-- intactos, solo se les saca la etiqueta de categoría.
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'incomes'
    AND CONSTRAINT_NAME = 'fk_incomes_category'
);
SET @ddl := IF(@fk_exists = 0,
  'ALTER TABLE incomes ADD CONSTRAINT fk_incomes_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================================
-- DIAGNÓSTICO: corré esto para confirmar que se aplicó bien:
--
--   SELECT COLUMN_NAME FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'incomes'
--     AND COLUMN_NAME = 'category_id';
--
--   SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'incomes'
--     AND CONSTRAINT_NAME = 'fk_incomes_category';
-- =============================================================
