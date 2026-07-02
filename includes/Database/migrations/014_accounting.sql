-- 014_accounting.sql — Enterprise accounting & finance module (GL, treasury, AR/AP, audit)

CREATE TABLE IF NOT EXISTS acc_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NULL COMMENT 'NULL = global/system account',
    code VARCHAR(20) NOT NULL,
    name VARCHAR(150) NOT NULL,
    account_type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
    account_subtype VARCHAR(50) NULL,
    parent_id INT NULL,
    normal_balance ENUM('debit','credit') NOT NULL DEFAULT 'debit',
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_acc_code_store (code, store_id),
    KEY idx_acc_type (account_type),
    KEY idx_acc_store (store_id),
    FOREIGN KEY (parent_id) REFERENCES acc_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acc_journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    entry_no VARCHAR(40) NOT NULL,
    entry_date DATE NOT NULL,
    reference_type VARCHAR(50) NULL COMMENT 'sale, expense, payment, manual, purchase, inventory',
    reference_id INT NULL,
    description VARCHAR(255) NOT NULL,
    status ENUM('draft','posted','void') NOT NULL DEFAULT 'posted',
    created_by INT NULL,
    posted_at TIMESTAMP NULL,
    sync_status ENUM('synced','pending','failed') NOT NULL DEFAULT 'synced',
    local_uuid VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_entry_no (entry_no),
    KEY idx_je_store_date (store_id, entry_date),
    KEY idx_je_ref (reference_type, reference_id),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acc_journal_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id INT NOT NULL,
    account_id INT NOT NULL,
    debit DECIMAL(14,2) NOT NULL DEFAULT 0,
    credit DECIMAL(14,2) NOT NULL DEFAULT 0,
    memo VARCHAR(255) NULL,
    line_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_jl_entry (journal_entry_id),
    KEY idx_jl_account (account_id),
    FOREIGN KEY (journal_entry_id) REFERENCES acc_journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES acc_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acc_cash_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    account_id INT NULL,
    opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    current_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (account_id) REFERENCES acc_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acc_cash_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cash_account_id INT NOT NULL,
    store_id INT NOT NULL,
    transaction_type ENUM('deposit','withdrawal','opening','closing','transfer','sale','expense') NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    balance_after DECIMAL(14,2) NULL,
    reference VARCHAR(100) NULL,
    notes TEXT NULL,
    transaction_date DATE NOT NULL,
    created_by INT NULL,
    journal_entry_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cash_tx_date (store_id, transaction_date),
    FOREIGN KEY (cash_account_id) REFERENCES acc_cash_accounts(id),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (journal_entry_id) REFERENCES acc_journal_entries(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acc_bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(50) NULL,
    account_id INT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'FCFA',
    opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    current_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (account_id) REFERENCES acc_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acc_bank_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_account_id INT NOT NULL,
    store_id INT NOT NULL,
    transaction_type ENUM('deposit','withdrawal','transfer','fee','reconciliation') NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    reference VARCHAR(100) NULL,
    reconciled TINYINT(1) NOT NULL DEFAULT 0,
    transaction_date DATE NOT NULL,
    created_by INT NULL,
    journal_entry_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_bank_tx_date (store_id, transaction_date),
    FOREIGN KEY (bank_account_id) REFERENCES acc_bank_accounts(id),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acc_mobile_money_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    provider ENUM('mtn','orange','moov','airtel','vodafone','other') NOT NULL DEFAULT 'mtn',
    label VARCHAR(100) NOT NULL,
    phone_number VARCHAR(30) NULL,
    account_id INT NULL,
    current_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (account_id) REFERENCES acc_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acc_mobile_money_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mobile_account_id INT NOT NULL,
    store_id INT NOT NULL,
    direction ENUM('in','out') NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    external_ref VARCHAR(100) NULL,
    reference VARCHAR(100) NULL,
    transaction_date DATE NOT NULL,
    created_by INT NULL,
    journal_entry_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mm_tx_date (store_id, transaction_date),
    FOREIGN KEY (mobile_account_id) REFERENCES acc_mobile_money_accounts(id),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acc_receivables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    customer_id INT NULL,
    sale_id INT NULL,
    invoice_no VARCHAR(50) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
    due_date DATE NULL,
    status ENUM('open','partial','paid','overdue','written_off') NOT NULL DEFAULT 'open',
    notes TEXT NULL,
    journal_entry_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ar_store_status (store_id, status),
    KEY idx_ar_customer (customer_id),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acc_payables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    supplier_id INT NULL,
    invoice_no VARCHAR(50) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
    due_date DATE NULL,
    status ENUM('open','partial','paid','overdue') NOT NULL DEFAULT 'open',
    reference_type VARCHAR(50) NULL,
    reference_id INT NULL,
    notes TEXT NULL,
    journal_entry_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ap_store_status (store_id, status),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acc_expense_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    category VARCHAR(80) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    description TEXT NULL,
    expense_date DATE NOT NULL,
    payment_method ENUM('cash','bank','mobile_money') NOT NULL DEFAULT 'cash',
    payment_account_id INT NULL COMMENT 'cash/bank/mobile record id',
    account_id INT NULL COMMENT 'expense GL account',
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_by INT NOT NULL,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    journal_entry_id INT NULL,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_exp_store_date (store_id, expense_date),
    KEY idx_exp_status (status),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (account_id) REFERENCES acc_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acc_accounting_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NULL,
    user_id INT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_acc_log_store (store_id, created_at),
    KEY idx_acc_log_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acc_offline_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    action VARCHAR(80) NOT NULL,
    payload JSON NOT NULL,
    local_uuid VARCHAR(64) NOT NULL,
    status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP NULL,
    UNIQUE KEY uq_offline_uuid (local_uuid),
    KEY idx_offline_status (status),
    FOREIGN KEY (store_id) REFERENCES stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Chart of Accounts (global, store_id NULL)
INSERT IGNORE INTO acc_accounts (store_id, code, name, account_type, account_subtype, normal_balance, is_system, description) VALUES
(NULL, '1000', 'Assets', 'asset', 'header', 'debit', 1, 'Asset accounts'),
(NULL, '1010', 'Cash', 'asset', 'cash', 'debit', 1, 'Cash on hand and registers'),
(NULL, '1020', 'Bank Accounts', 'asset', 'bank', 'debit', 1, 'Bank balances'),
(NULL, '1030', 'Mobile Money', 'asset', 'mobile_money', 'debit', 1, 'Mobile money wallets'),
(NULL, '1040', 'Inventory', 'asset', 'inventory', 'debit', 1, 'Stock and warehouse value'),
(NULL, '1050', 'Accounts Receivable', 'asset', 'receivable', 'debit', 1, 'Customer credit balances'),
(NULL, '2000', 'Liabilities', 'liability', 'header', 'credit', 1, 'Liability accounts'),
(NULL, '2010', 'Accounts Payable', 'liability', 'payable', 'credit', 1, 'Supplier invoices payable'),
(NULL, '2020', 'Loans', 'liability', 'loan', 'credit', 1, 'Borrowings'),
(NULL, '2030', 'Taxes Payable', 'liability', 'tax', 'credit', 1, 'Tax obligations'),
(NULL, '3000', 'Equity', 'equity', 'header', 'credit', 1, 'Owner equity'),
(NULL, '3010', 'Owner Capital', 'equity', 'capital', 'credit', 1, 'Capital contributions'),
(NULL, '3020', 'Retained Earnings', 'equity', 'retained', 'credit', 1, 'Accumulated profits'),
(NULL, '4000', 'Revenue', 'revenue', 'header', 'credit', 1, 'Income accounts'),
(NULL, '4010', 'Product Sales', 'revenue', 'sales', 'credit', 1, 'POS product revenue'),
(NULL, '4020', 'Service Revenue', 'revenue', 'service', 'credit', 1, 'Service income'),
(NULL, '5000', 'Expenses', 'expense', 'header', 'debit', 1, 'Operating expenses'),
(NULL, '5010', 'Rent', 'expense', 'rent', 'debit', 1, 'Rent and lease'),
(NULL, '5020', 'Electricity', 'expense', 'utilities', 'debit', 1, 'Power utilities'),
(NULL, '5030', 'Internet', 'expense', 'utilities', 'debit', 1, 'Internet and telecom'),
(NULL, '5040', 'Salaries', 'expense', 'payroll', 'debit', 1, 'Staff salaries'),
(NULL, '5050', 'Cost of Goods Sold', 'expense', 'cogs', 'debit', 1, 'Inventory cost of sales'),
(NULL, '5060', 'Transportation', 'expense', 'transport', 'debit', 1, 'Fuel and transport'),
(NULL, '5070', 'Maintenance', 'expense', 'maintenance', 'debit', 1, 'Repairs and maintenance'),
(NULL, '5080', 'Marketing', 'expense', 'marketing', 'debit', 1, 'Advertising'),
(NULL, '5090', 'Miscellaneous Expenses', 'expense', 'misc', 'debit', 1, 'Other expenses');
