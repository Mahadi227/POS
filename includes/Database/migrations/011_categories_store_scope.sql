-- Per-store product categories
ALTER TABLE categories ADD COLUMN store_id INT NULL AFTER parent_id;

UPDATE categories c
SET store_id = (
    SELECT p.store_id FROM products p
    WHERE p.category_id = c.id AND p.deleted_at IS NULL
    ORDER BY p.id ASC
    LIMIT 1
)
WHERE store_id IS NULL;

UPDATE categories SET store_id = (SELECT id FROM stores WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1)
WHERE store_id IS NULL;

CREATE INDEX idx_categories_store ON categories(store_id);
