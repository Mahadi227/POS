-- Correctifs tableau de bord admin (phpMyAdmin → base pos_system_db → SQL)
-- Si une ligne échoue "Duplicate column", ignorez-la (colonne déjà présente).

ALTER TABLE customers ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Décommenter si nécessaire :
-- ALTER TABLE sales ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
-- ALTER TABLE products ADD COLUMN min_stock_level INT DEFAULT 5;
