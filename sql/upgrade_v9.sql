-- =============================================================
-- Actualización: ingresos a la par de los gastos.
--   * income_templates  -> "ingresos frecuentes" (cargar un ingreso de un toque)
--   * recurring_incomes -> ingresos fijos mensuales (sueldo, alquiler que cobrás...)
--   * incomes.recurring_income_id -> vínculo del ingreso generado con su
--     ingreso fijo, para no duplicarlo cada vez que se abre el mes.
--
-- Si es una instalación NUEVA, no hace falta correr esto: ya está
-- incluido en sql/schema.sql.
--
-- Es seguro correr este script más de una vez.
-- En phpMyAdmin > pestaña SQL, pegar y ejecutar TODO de una vez:
-- =============================================================

-- "Ingresos frecuentes": mismo concepto que templates, pero para ingresos.
CREATE TABLE IF NOT EXISTS income_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NULL,
  name VARCHAR(80) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_income_templates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_income_templates_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ingresos fijos mensuales: mismo concepto que recurring_expenses.
CREATE TABLE IF NOT EXISTS recurring_incomes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  start_month CHAR(7) NOT NULL,
  day_of_month TINYINT UNSIGNED NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_recurring_incomes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_recurring_incomes_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE incomes ADD COLUMN IF NOT EXISTS recurring_income_id INT UNSIGNED NULL AFTER month;

-- La FK se agrega solo si todavía no existe, así este script se puede
-- correr más de una vez sin tirar error de "duplicate".
-- ON DELETE SET NULL: si borrás el ingreso fijo, los ingresos que ya generó
-- quedan en el historial, solo se deja de generar nuevos.
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'incomes'
    AND CONSTRAINT_NAME = 'fk_incomes_recurring_income'
);
SET @ddl := IF(@fk_exists = 0,
  'ALTER TABLE incomes ADD CONSTRAINT fk_incomes_recurring_income FOREIGN KEY (recurring_income_id) REFERENCES recurring_incomes(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================================
-- DIAGNÓSTICO: corré esto para confirmar que se aplicó bien:
--
--   SHOW TABLES LIKE 'income_templates';
--   SHOW TABLES LIKE 'recurring_incomes';
--
--   SELECT COLUMN_NAME FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'incomes'
--     AND COLUMN_NAME = 'recurring_income_id';
--
--   SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'incomes'
--     AND CONSTRAINT_NAME = 'fk_incomes_recurring_income';
-- =============================================================
