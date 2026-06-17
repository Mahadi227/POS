-- Per-store customers (POS cart / cashier)
ALTER TABLE customers ADD COLUMN store_id INT NULL AFTER loyalty_points;

UPDATE customers c
SET store_id = (
    SELECT s.store_id FROM sales s
    WHERE s.customer_id = c.id AND s.deleted_at IS NULL
    ORDER BY s.id DESC
    LIMIT 1
)
WHERE store_id IS NULL;

UPDATE customers SET store_id = (SELECT id FROM stores WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1)
WHERE store_id IS NULL;

CREATE INDEX idx_customers_store ON customers(store_id);
