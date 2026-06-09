<?php
require 'includes/Database/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Add 'unit' column if it doesn't exist
    try {
        $db->exec("ALTER TABLE products ADD COLUMN unit VARCHAR(50) DEFAULT 'unite' AFTER stock_quantity");
        echo "Successfully added 'unit' column.\n";
    } catch (PDOException $e) {
        echo "Info (unit column): " . $e->getMessage() . "\n";
    }

    // Add 'expiry_date' column if it doesn't exist
    try {
        $db->exec("ALTER TABLE products ADD COLUMN expiry_date DATE NULL AFTER unit");
        echo "Successfully added 'expiry_date' column.\n";
    } catch (PDOException $e) {
        echo "Info (expiry_date column): " . $e->getMessage() . "\n";
    }

    // Create barcode_registry table if it doesn't exist
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS barcode_registry (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                barcode VARCHAR(100) UNIQUE NOT NULL,
                type VARCHAR(20) DEFAULT 'EAN13',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            )
        ");
        echo "Successfully created/verified 'barcode_registry' table.\n";
    } catch (PDOException $e) {
        echo "Info (barcode_registry table): " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "Connection error: " . $e->getMessage();
}
