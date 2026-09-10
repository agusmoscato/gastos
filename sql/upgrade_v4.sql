-- =============================================================
-- Actualización para poder cargar compras en cuotas.
-- Si es una instalación NUEVA, no hace falta correr esto: ya está
-- incluido en sql/schema.sql.
--
-- Es seguro correr este script más de una vez (no rompe nada si ya
-- lo habías corrido antes, o si quedó a mitad de camino).
--
-- En phpMyAdmin de tu base > pestaña SQL, pegar y ejecutar TODO
-- este archivo de una vez:
-- =============================================================

CREATE TABLE IF NOT EXISTS installment_purchases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  total_amount DECIMAL(12,2) NOT NULL,
  num_installments SMALLINT UNSIGNED NOT NULL,
  first_month CHAR(7) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_installments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_installments_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE expenses ADD COLUMN IF NOT EXISTS installment_id INT UNSIGNED NULL;
ALTER TABLE expenses ADD COLUMN IF NOT EXISTS installment_no SMALLINT UNSIGNED NULL;

-- Esta última puede tirar un error de "duplicate" si ya existía la relación;
-- en ese caso está todo bien, ignoralo y segui.
ALTER TABLE expenses ADD CONSTRAINT fk_expenses_installment
  FOREIGN KEY (installment_id) REFERENCES installment_purchases(id) ON DELETE CASCADE;

-- =============================================================
-- DIAGNÓSTICO: si después de correr esto seguís con problemas,
-- corré esta consulta y fijate si devuelve las 2 columnas y la tabla:
--
--   SELECT COLUMN_NAME FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses'
--     AND COLUMN_NAME IN ('installment_id', 'installment_no');
--
--   SHOW TABLES LIKE 'installment_purchases';
-- =============================================================
