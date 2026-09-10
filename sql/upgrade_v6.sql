-- =============================================================
-- Actualización: recordatorio de vencimientos (tarjeta, alquiler,
-- servicios, seguro...) con aviso de cuándo vence cada uno.
-- Si es una instalación NUEVA, no hace falta correr esto: ya está
-- incluido en sql/schema.sql.
--
-- Es seguro correr este script más de una vez.
-- En phpMyAdmin > pestaña SQL, pegar y ejecutar TODO de una vez:
-- =============================================================

CREATE TABLE IF NOT EXISTS due_dates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  amount DECIMAL(12,2) NULL,
  due_day TINYINT UNSIGNED NOT NULL,
  recurring TINYINT(1) NOT NULL DEFAULT 1,
  one_time_month CHAR(7) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_duedates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_duedates_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS due_date_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  due_date_id INT UNSIGNED NOT NULL,
  month CHAR(7) NOT NULL,
  paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  expense_id INT UNSIGNED NULL,
  UNIQUE KEY uniq_due_month (due_date_id, month),
  CONSTRAINT fk_duepay_duedate FOREIGN KEY (due_date_id) REFERENCES due_dates(id) ON DELETE CASCADE,
  CONSTRAINT fk_duepay_expense FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- DIAGNÓSTICO:
--   SHOW TABLES LIKE 'due_dates';
--   SHOW TABLES LIKE 'due_date_payments';
-- =============================================================
