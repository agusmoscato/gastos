-- =============================================================
-- Actualización grande: panel de configuración, gastos fijos,
-- cancelar cuotas restantes, backup completo, rate limiting de login.
-- Si es una instalación NUEVA, no hace falta correr esto: ya está
-- incluido en sql/schema.sql.
--
-- Es seguro correr este script más de una vez.
-- En phpMyAdmin > pestaña SQL, pegar y ejecutar TODO de una vez:
-- =============================================================

-- Preferencias por usuario: qué paneles mostrar, notificaciones
CREATE TABLE IF NOT EXISTS user_settings (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  hidden_sections TEXT NULL,
  notifications_enabled TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_user_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Gastos fijos mensuales (Netflix, gimnasio, alquiler...) sin fecha de fin
CREATE TABLE IF NOT EXISTS recurring_expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  start_month CHAR(7) NOT NULL,
  day_of_month TINYINT UNSIGNED NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_recurring_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_recurring_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE expenses ADD COLUMN IF NOT EXISTS recurring_id INT UNSIGNED NULL;

-- SET NULL (no CASCADE): si borrás la definición del gasto fijo, el
-- historial de gastos que ya generó queda intacto.
ALTER TABLE expenses ADD CONSTRAINT fk_expenses_recurring
  FOREIGN KEY (recurring_id) REFERENCES recurring_expenses(id) ON DELETE SET NULL;

-- Registro de intentos de login, para el límite de intentos fallidos
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email_time (email, attempted_at),
  INDEX idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- DIAGNÓSTICO: corré esto para confirmar que se aplicó todo bien:
--
--   SHOW TABLES LIKE 'user_settings';
--   SHOW TABLES LIKE 'recurring_expenses';
--   SHOW TABLES LIKE 'login_attempts';
--   SELECT COLUMN_NAME FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses'
--     AND COLUMN_NAME = 'recurring_id';
-- =============================================================
