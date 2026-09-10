-- =============================================================
-- Actualización: cargar varios ingresos por mes (en vez de un
-- solo número fijo), a medida que te va entrando la plata.
-- Si es una instalación NUEVA, no hace falta correr esto: ya está
-- incluido en sql/schema.sql.
--
-- Es seguro correr este script más de una vez.
-- En phpMyAdmin > pestaña SQL, pegar y ejecutar TODO de una vez:
-- =============================================================

CREATE TABLE IF NOT EXISTS incomes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  description VARCHAR(255) DEFAULT '',
  income_date DATE NOT NULL,
  month CHAR(7) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_incomes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_month (user_id, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migra lo que ya tenías cargado como un solo ingreso por mes (tabla vieja
-- month_settings) a la nueva lista, como una entrada de "Ingreso del mes"
-- por cada mes que ya tenía un valor. No duplica si corrés esto de nuevo.
INSERT INTO incomes (user_id, amount, description, income_date, month)
SELECT ms.user_id, ms.income, 'Ingreso del mes', CONCAT(ms.month, '-01'), ms.month
FROM month_settings ms
WHERE ms.income > 0
  AND NOT EXISTS (
    SELECT 1 FROM incomes i WHERE i.user_id = ms.user_id AND i.month = ms.month
  );

-- =============================================================
-- DIAGNÓSTICO:
--   SHOW TABLES LIKE 'incomes';
--   SELECT COUNT(*) FROM incomes;
-- =============================================================
