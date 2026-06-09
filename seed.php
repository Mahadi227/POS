<?php
// seed.php - Run once to seed roles and demo users

require_once __DIR__ . '/includes/Database/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Starting Database Seed...\n";

    // Disable FK checks temporarily to allow truncating
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("TRUNCATE TABLE users;");
    $db->exec("TRUNCATE TABLE roles;");
    $db->exec("TRUNCATE TABLE stores;");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 1. Seed Roles
    $roles = ['Super Admin', 'Admin', 'Manager', 'Cashier', 'Staff'];
    $stmtRole = $db->prepare("INSERT INTO roles (id, name, description) VALUES (?, ?, ?)");
    foreach ($roles as $index => $role) {
        $stmtRole->execute([$index + 1, $role, "Role for $role"]);
    }
    echo "Roles seeded.\n";

    // 2. Seed Stores (succursales)
    $stores = [
        [1, 'DKR-HQ', 'Dakar Siège', 'Plateau, Dakar', '+221 33 000 0001'],
        [2, 'DKR-ALM', 'Dakar Almadies', 'Almadies, Dakar', '+221 33 000 0002'],
        [3, 'THS-CTR', 'Thiès Centre', 'Centre-ville, Thiès', '+221 33 000 0003'],
    ];
    $stmtStore = $db->prepare('INSERT INTO stores (id, code, name, location, phone, tax_rate, currency, is_active) VALUES (?, ?, ?, ?, ?, 18, ?, 1)');
    foreach ($stores as $s) {
        $stmtStore->execute([$s[0], $s[1], $s[2], $s[3], $s[4], 'FCFA']);
    }
    echo "Stores seeded (3 succursales).\n";

    // 3. Seed Users
    $password = 'password123';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $pin = password_hash('1234', PASSWORD_BCRYPT);

    $users = [
        ['name' => 'Super Admin Demo', 'email' => 'superadmin@pos.com', 'role_id' => 1, 'store_id' => null],
        ['name' => 'Admin Demo', 'email' => 'admin@pos.com', 'role_id' => 2, 'store_id' => 1],
        ['name' => 'Manager Demo', 'email' => 'manager@pos.com', 'role_id' => 3, 'store_id' => 2],
        ['name' => 'Cashier Demo', 'email' => 'cashier@pos.com', 'role_id' => 4, 'store_id' => 1],
        ['name' => 'Staff Demo', 'email' => 'staff@pos.com', 'role_id' => 5, 'store_id' => 3]
    ];

    $stmtUser = $db->prepare("INSERT INTO users (name, email, password_hash, pin_hash, role_id, store_id, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
    
    foreach ($users as $u) {
        $stmtUser->execute([$u['name'], $u['email'], $hash, $pin, $u['role_id'], $u['store_id']]);
    }
    
    echo "Demo users seeded successfully!\n";
    echo "Password for all accounts is: password123\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
