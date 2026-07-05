-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 29, 2026 at 08:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pos_system_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `acc_accounting_logs`
--

CREATE TABLE `acc_accounting_logs` (
  `id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `acc_accounting_logs`
--

INSERT INTO `acc_accounting_logs` (`id`, `store_id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 7, 1, 'expense_created', 'expense', 1, '{\"amount\":500}', '127.0.0.1', '2026-06-18 06:49:31'),
(2, 7, 1, 'journal_posted', 'journal_entry', 1, '{\"entry_no\":\"JE-7-20260618-0001\",\"description\":\"Expense: salaries\"}', '127.0.0.1', '2026-06-18 06:50:15'),
(3, 7, 1, 'expense_approved', 'expense', 1, NULL, '127.0.0.1', '2026-06-18 06:50:15'),
(4, 7, 1, 'journal_posted', 'journal_entry', 2, '{\"entry_no\":\"JE-7-20260621-0001\",\"description\":\"POS sale R7-20260620231011-1\"}', '127.0.0.1', '2026-06-20 23:11:04'),
(5, 7, 1, 'auto_post_sale', 'sale', 272, '{\"total\":6895.8,\"payment_method\":\"cash\",\"journal_entry_id\":2}', '127.0.0.1', '2026-06-20 23:11:04'),
(6, 7, 1, 'journal_posted', 'journal_entry', 3, '{\"entry_no\":\"JE-7-20260621-0002\",\"description\":\"POS sale R7-20260620231225-1\"}', '127.0.0.1', '2026-06-20 23:12:25'),
(7, 7, 1, 'auto_post_sale', 'sale', 273, '{\"total\":34560,\"payment_method\":\"cash\",\"journal_entry_id\":3}', '127.0.0.1', '2026-06-20 23:12:25'),
(8, 7, 1, 'journal_posted', 'journal_entry', 4, '{\"entry_no\":\"JE-7-20260621-0003\",\"description\":\"POS sale R7-20260620231313-1\"}', '127.0.0.1', '2026-06-20 23:13:14'),
(9, 7, 1, 'auto_post_sale', 'sale', 274, '{\"total\":432,\"payment_method\":\"cash\",\"journal_entry_id\":4}', '127.0.0.1', '2026-06-20 23:13:14'),
(10, 7, 1, 'journal_posted', 'journal_entry', 5, '{\"entry_no\":\"JE-7-20260621-0004\",\"description\":\"POS sale R7-20260621000752-1\"}', '127.0.0.1', '2026-06-21 00:07:53'),
(11, 7, 1, 'auto_post_sale', 'sale', 275, '{\"total\":972,\"payment_method\":\"cash\",\"journal_entry_id\":5}', '127.0.0.1', '2026-06-21 00:07:53'),
(12, 7, 1, 'journal_posted', 'journal_entry', 6, '{\"entry_no\":\"JE-7-20260621-0005\",\"description\":\"POS sale R7-20260621182845-1\"}', '172.20.10.1', '2026-06-21 18:28:46'),
(13, 7, 1, 'auto_post_sale', 'sale', 276, '{\"total\":6372,\"payment_method\":\"mobile_money\",\"journal_entry_id\":6}', '172.20.10.1', '2026-06-21 18:28:46'),
(14, 1, 1, 'journal_posted', 'journal_entry', 7, '{\"entry_no\":\"JE-1-20260628-0001\",\"description\":\"POS sale R1-20260628205229-1\"}', '172.20.10.1', '2026-06-28 20:52:31'),
(15, 1, 1, 'auto_post_sale', 'sale', 277, '{\"total\":1317368,\"payment_method\":\"card\",\"journal_entry_id\":7}', '172.20.10.1', '2026-06-28 20:52:31');

-- --------------------------------------------------------

--
-- Table structure for table `acc_accounts`
--

CREATE TABLE `acc_accounts` (
  `id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL COMMENT 'NULL = global/system account',
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `account_type` enum('asset','liability','equity','revenue','expense') NOT NULL,
  `account_subtype` varchar(50) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `normal_balance` enum('debit','credit') NOT NULL DEFAULT 'debit',
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `acc_accounts`
--

INSERT INTO `acc_accounts` (`id`, `store_id`, `code`, `name`, `account_type`, `account_subtype`, `parent_id`, `normal_balance`, `is_system`, `is_active`, `description`, `created_at`, `updated_at`) VALUES
(1, NULL, '1000', 'Assets', 'asset', 'header', NULL, 'debit', 1, 1, 'Asset accounts', '2026-06-18 05:56:46', NULL),
(2, NULL, '1010', 'Cash', 'asset', 'cash', NULL, 'debit', 1, 1, 'Cash on hand and registers', '2026-06-18 05:56:46', NULL),
(3, NULL, '1020', 'Bank Accounts', 'asset', 'bank', NULL, 'debit', 1, 1, 'Bank balances', '2026-06-18 05:56:46', NULL),
(4, NULL, '1030', 'Mobile Money', 'asset', 'mobile_money', NULL, 'debit', 1, 1, 'Mobile money wallets', '2026-06-18 05:56:46', NULL),
(5, NULL, '1040', 'Inventory', 'asset', 'inventory', NULL, 'debit', 1, 1, 'Stock and warehouse value', '2026-06-18 05:56:46', NULL),
(6, NULL, '1050', 'Accounts Receivable', 'asset', 'receivable', NULL, 'debit', 1, 1, 'Customer credit balances', '2026-06-18 05:56:46', NULL),
(7, NULL, '2000', 'Liabilities', 'liability', 'header', NULL, 'credit', 1, 1, 'Liability accounts', '2026-06-18 05:56:46', NULL),
(8, NULL, '2010', 'Accounts Payable', 'liability', 'payable', NULL, 'credit', 1, 1, 'Supplier invoices payable', '2026-06-18 05:56:46', NULL),
(9, NULL, '2020', 'Loans', 'liability', 'loan', NULL, 'credit', 1, 1, 'Borrowings', '2026-06-18 05:56:46', NULL),
(10, NULL, '2030', 'Taxes Payable', 'liability', 'tax', NULL, 'credit', 1, 1, 'Tax obligations', '2026-06-18 05:56:46', NULL),
(11, NULL, '3000', 'Equity', 'equity', 'header', NULL, 'credit', 1, 1, 'Owner equity', '2026-06-18 05:56:46', NULL),
(12, NULL, '3010', 'Owner Capital', 'equity', 'capital', NULL, 'credit', 1, 1, 'Capital contributions', '2026-06-18 05:56:46', NULL),
(13, NULL, '3020', 'Retained Earnings', 'equity', 'retained', NULL, 'credit', 1, 1, 'Accumulated profits', '2026-06-18 05:56:46', NULL),
(14, NULL, '4000', 'Revenue', 'revenue', 'header', NULL, 'credit', 1, 1, 'Income accounts', '2026-06-18 05:56:46', NULL),
(15, NULL, '4010', 'Product Sales', 'revenue', 'sales', NULL, 'credit', 1, 1, 'POS product revenue', '2026-06-18 05:56:46', NULL),
(16, NULL, '4020', 'Service Revenue', 'revenue', 'service', NULL, 'credit', 1, 1, 'Service income', '2026-06-18 05:56:46', NULL),
(17, NULL, '5000', 'Expenses', 'expense', 'header', NULL, 'debit', 1, 1, 'Operating expenses', '2026-06-18 05:56:46', NULL),
(18, NULL, '5010', 'Rent', 'expense', 'rent', NULL, 'debit', 1, 1, 'Rent and lease', '2026-06-18 05:56:46', NULL),
(19, NULL, '5020', 'Electricity', 'expense', 'utilities', NULL, 'debit', 1, 1, 'Power utilities', '2026-06-18 05:56:46', NULL),
(20, NULL, '5030', 'Internet', 'expense', 'utilities', NULL, 'debit', 1, 1, 'Internet and telecom', '2026-06-18 05:56:46', NULL),
(21, NULL, '5040', 'Salaries', 'expense', 'payroll', NULL, 'debit', 1, 1, 'Staff salaries', '2026-06-18 05:56:46', NULL),
(22, NULL, '5050', 'Cost of Goods Sold', 'expense', 'cogs', NULL, 'debit', 1, 1, 'Inventory cost of sales', '2026-06-18 05:56:46', NULL),
(23, NULL, '5060', 'Transportation', 'expense', 'transport', NULL, 'debit', 1, 1, 'Fuel and transport', '2026-06-18 05:56:46', NULL),
(24, NULL, '5070', 'Maintenance', 'expense', 'maintenance', NULL, 'debit', 1, 1, 'Repairs and maintenance', '2026-06-18 05:56:46', NULL),
(25, NULL, '5080', 'Marketing', 'expense', 'marketing', NULL, 'debit', 1, 1, 'Advertising', '2026-06-18 05:56:46', NULL),
(26, NULL, '5090', 'Miscellaneous Expenses', 'expense', 'misc', NULL, 'debit', 1, 1, 'Other expenses', '2026-06-18 05:56:46', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `acc_bank_accounts`
--

CREATE TABLE `acc_bank_accounts` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'FCFA',
  `opening_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_bank_transactions`
--

CREATE TABLE `acc_bank_transactions` (
  `id` int(11) NOT NULL,
  `bank_account_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `transaction_type` enum('deposit','withdrawal','transfer','fee','reconciliation') NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `reconciled` tinyint(1) NOT NULL DEFAULT 0,
  `transaction_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_cash_accounts`
--

CREATE TABLE `acc_cash_accounts` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `opening_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `acc_cash_accounts`
--

INSERT INTO `acc_cash_accounts` (`id`, `store_id`, `name`, `account_id`, `opening_balance`, `current_balance`, `is_active`, `created_at`) VALUES
(1, 7, 'Main Cash Register', NULL, 0.00, 42859.80, 1, '2026-06-20 23:11:04');

-- --------------------------------------------------------

--
-- Table structure for table `acc_cash_transactions`
--

CREATE TABLE `acc_cash_transactions` (
  `id` int(11) NOT NULL,
  `cash_account_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `transaction_type` enum('deposit','withdrawal','opening','closing','transfer','sale','expense') NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `balance_after` decimal(14,2) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `acc_cash_transactions`
--

INSERT INTO `acc_cash_transactions` (`id`, `cash_account_id`, `store_id`, `transaction_type`, `amount`, `balance_after`, `reference`, `notes`, `transaction_date`, `created_by`, `journal_entry_id`, `created_at`) VALUES
(1, 1, 7, 'sale', 6895.80, 6895.80, 'R7-20260620231011-1', NULL, '2026-06-21', 1, 2, '2026-06-20 23:11:04'),
(2, 1, 7, 'sale', 34560.00, 41455.80, 'R7-20260620231225-1', NULL, '2026-06-21', 1, 3, '2026-06-20 23:12:25'),
(3, 1, 7, 'sale', 432.00, 41887.80, 'R7-20260620231313-1', NULL, '2026-06-21', 1, 4, '2026-06-20 23:13:14'),
(4, 1, 7, 'sale', 972.00, 42859.80, 'R7-20260621000752-1', NULL, '2026-06-21', 1, 5, '2026-06-21 00:07:53');

-- --------------------------------------------------------

--
-- Table structure for table `acc_expense_records`
--

CREATE TABLE `acc_expense_records` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `category` varchar(80) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `description` text DEFAULT NULL,
  `expense_date` date NOT NULL,
  `payment_method` enum('cash','bank','mobile_money') NOT NULL DEFAULT 'cash',
  `payment_account_id` int(11) DEFAULT NULL COMMENT 'cash/bank/mobile record id',
  `account_id` int(11) DEFAULT NULL COMMENT 'expense GL account',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `journal_entry_id` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `acc_expense_records`
--

INSERT INTO `acc_expense_records` (`id`, `store_id`, `category`, `amount`, `description`, `expense_date`, `payment_method`, `payment_account_id`, `account_id`, `status`, `created_by`, `approved_by`, `approved_at`, `journal_entry_id`, `deleted_at`, `created_at`) VALUES
(1, 7, 'salaries', 500.00, '', '2026-06-05', 'cash', NULL, NULL, 'approved', 1, 1, '2026-06-18 06:50:15', 1, NULL, '2026-06-18 06:49:31');

-- --------------------------------------------------------

--
-- Table structure for table `acc_journal_entries`
--

CREATE TABLE `acc_journal_entries` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `entry_no` varchar(40) NOT NULL,
  `entry_date` date NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'sale, expense, payment, manual, purchase, inventory',
  `reference_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `status` enum('draft','posted','void') NOT NULL DEFAULT 'posted',
  `created_by` int(11) DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `sync_status` enum('synced','pending','failed') NOT NULL DEFAULT 'synced',
  `local_uuid` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `acc_journal_entries`
--

INSERT INTO `acc_journal_entries` (`id`, `store_id`, `entry_no`, `entry_date`, `reference_type`, `reference_id`, `description`, `status`, `created_by`, `posted_at`, `sync_status`, `local_uuid`, `created_at`) VALUES
(1, 7, 'JE-7-20260618-0001', '2026-06-18', 'expense', 1, 'Expense: salaries', 'posted', 1, '2026-06-18 06:50:15', 'synced', NULL, '2026-06-18 06:50:15'),
(2, 7, 'JE-7-20260621-0001', '2026-06-21', 'sale', 272, 'POS sale R7-20260620231011-1', 'posted', 1, '2026-06-20 23:11:04', 'synced', NULL, '2026-06-20 23:11:04'),
(3, 7, 'JE-7-20260621-0002', '2026-06-21', 'sale', 273, 'POS sale R7-20260620231225-1', 'posted', 1, '2026-06-20 23:12:25', 'synced', NULL, '2026-06-20 23:12:25'),
(4, 7, 'JE-7-20260621-0003', '2026-06-21', 'sale', 274, 'POS sale R7-20260620231313-1', 'posted', 1, '2026-06-20 23:13:14', 'synced', NULL, '2026-06-20 23:13:14'),
(5, 7, 'JE-7-20260621-0004', '2026-06-21', 'sale', 275, 'POS sale R7-20260621000752-1', 'posted', 1, '2026-06-21 00:07:53', 'synced', NULL, '2026-06-21 00:07:53'),
(6, 7, 'JE-7-20260621-0005', '2026-06-21', 'sale', 276, 'POS sale R7-20260621182845-1', 'posted', 1, '2026-06-21 18:28:46', 'synced', NULL, '2026-06-21 18:28:46'),
(7, 1, 'JE-1-20260628-0001', '2026-06-28', 'sale', 277, 'POS sale R1-20260628205229-1', 'posted', 1, '2026-06-28 20:52:31', 'synced', NULL, '2026-06-28 20:52:31');

-- --------------------------------------------------------

--
-- Table structure for table `acc_journal_lines`
--

CREATE TABLE `acc_journal_lines` (
  `id` int(11) NOT NULL,
  `journal_entry_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `debit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `memo` varchar(255) DEFAULT NULL,
  `line_order` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `acc_journal_lines`
--

INSERT INTO `acc_journal_lines` (`id`, `journal_entry_id`, `account_id`, `debit`, `credit`, `memo`, `line_order`) VALUES
(1, 1, 21, 500.00, 0.00, 'salaries', 0),
(2, 1, 2, 0.00, 500.00, 'Payment', 1),
(3, 2, 2, 6895.80, 0.00, 'Sale R7-20260620231011-1', 0),
(4, 2, 15, 0.00, 6895.80, 'Product sales', 1),
(5, 2, 22, 26410.00, 0.00, 'COGS', 2),
(6, 2, 5, 0.00, 26410.00, 'Inventory reduction', 3),
(7, 3, 2, 34560.00, 0.00, 'Sale R7-20260620231225-1', 0),
(8, 3, 15, 0.00, 34560.00, 'Product sales', 1),
(9, 3, 22, 200000.00, 0.00, 'COGS', 2),
(10, 3, 5, 0.00, 200000.00, 'Inventory reduction', 3),
(11, 4, 2, 432.00, 0.00, 'Sale R7-20260620231313-1', 0),
(12, 4, 15, 0.00, 432.00, 'Product sales', 1),
(13, 4, 22, 300.00, 0.00, 'COGS', 2),
(14, 4, 5, 0.00, 300.00, 'Inventory reduction', 3),
(15, 5, 2, 972.00, 0.00, 'Sale R7-20260621000752-1', 0),
(16, 5, 15, 0.00, 972.00, 'Product sales', 1),
(17, 5, 22, 600.00, 0.00, 'COGS', 2),
(18, 5, 5, 0.00, 600.00, 'Inventory reduction', 3),
(19, 6, 4, 6372.00, 0.00, 'Sale R7-20260621182845-1', 0),
(20, 6, 15, 0.00, 6372.00, 'Product sales', 1),
(21, 6, 22, 6350.00, 0.00, 'COGS', 2),
(22, 6, 5, 0.00, 6350.00, 'Inventory reduction', 3),
(23, 7, 3, 1317368.00, 0.00, 'Sale R1-20260628205229-1', 0),
(24, 7, 15, 0.00, 1317368.00, 'Product sales', 1),
(25, 7, 22, 846800.00, 0.00, 'COGS', 2),
(26, 7, 5, 0.00, 846800.00, 'Inventory reduction', 3);

-- --------------------------------------------------------

--
-- Table structure for table `acc_mobile_money_accounts`
--

CREATE TABLE `acc_mobile_money_accounts` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `provider` enum('mtn','orange','moov','airtel','vodafone','other') NOT NULL DEFAULT 'mtn',
  `label` varchar(100) NOT NULL,
  `phone_number` varchar(30) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `current_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_mobile_money_transactions`
--

CREATE TABLE `acc_mobile_money_transactions` (
  `id` int(11) NOT NULL,
  `mobile_account_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `direction` enum('in','out') NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `external_ref` varchar(100) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_offline_queue`
--

CREATE TABLE `acc_offline_queue` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `action` varchar(80) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `local_uuid` varchar(64) NOT NULL,
  `status` enum('pending','synced','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `synced_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_payables`
--

CREATE TABLE `acc_payables` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `amount_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `status` enum('open','partial','paid','overdue') NOT NULL DEFAULT 'open',
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_receivables`
--

CREATE TABLE `acc_receivables` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `amount_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `status` enum('open','partial','paid','overdue','written_off') NOT NULL DEFAULT 'open',
  `notes` text DEFAULT NULL,
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `barcode_registry`
--

CREATE TABLE `barcode_registry` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `barcode` varchar(100) NOT NULL,
  `type` varchar(20) DEFAULT 'EAN13',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `batch_tracking`
--

CREATE TABLE `batch_tracking` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_number` varchar(80) NOT NULL,
  `barcode` varchar(120) DEFAULT NULL,
  `serial_number` varchar(120) DEFAULT NULL,
  `manufacturing_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit_cost` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `status` enum('active','expired','recalled','depleted') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_tracking`
--

INSERT INTO `batch_tracking` (`id`, `warehouse_id`, `product_id`, `batch_number`, `barcode`, `serial_number`, `manufacturing_date`, `expiry_date`, `quantity`, `unit_cost`, `status`, `created_at`, `updated_at`) VALUES
(1, 4, 97, 'skelewu', NULL, NULL, NULL, '2026-06-22', 700, 7999.0000, 'active', '2026-06-20 11:12:19', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(50) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cashier_presence`
--

CREATE TABLE `cashier_presence` (
  `user_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT 1,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `last_page` varchar(120) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cashier_presence`
--

INSERT INTO `cashier_presence` (`user_id`, `store_id`, `is_online`, `last_seen_at`, `last_page`, `updated_at`) VALUES
(1, 1, 1, '2026-06-28 20:55:28', 'returns.php', '2026-06-28 20:55:28'),
(1, 4, 1, '2026-06-16 03:18:22', NULL, '2026-06-16 03:18:22'),
(1, 5, 1, '2026-06-16 03:17:05', NULL, '2026-06-16 03:17:05'),
(1, 6, 1, '2026-06-16 03:19:51', NULL, '2026-06-16 03:19:51'),
(1, 7, 1, '2026-06-21 18:30:11', 'pos.php', '2026-06-21 18:30:11'),
(1, 11, 1, '2026-06-21 18:31:25', 'pos.php', '2026-06-21 18:31:25'),
(1, 12, 1, '2026-06-28 21:47:31', NULL, '2026-06-28 21:47:31'),
(3, 1, 1, '2026-06-17 04:48:02', 'dashboard.php', '2026-06-17 04:48:02'),
(4, 1, 1, '2026-06-20 09:54:53', 'returns.php', '2026-06-20 09:54:53'),
(5, 1, 1, '2026-06-19 07:58:50', 'dashboard.php', '2026-06-19 07:58:50'),
(13, 7, 1, '2026-06-16 03:23:22', 'pos.php', '2026-06-16 03:23:22');

-- --------------------------------------------------------

--
-- Table structure for table `cashier_shifts`
--

CREATE TABLE `cashier_shifts` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `register_id` int(11) DEFAULT NULL,
  `session_id` int(11) DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `opening_float` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expected_cash` decimal(12,2) DEFAULT NULL,
  `counted_cash` decimal(12,2) DEFAULT NULL,
  `variance` decimal(12,2) DEFAULT NULL,
  `total_sales` decimal(12,2) DEFAULT 0.00,
  `transaction_count` int(11) DEFAULT 0,
  `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cashier_shifts`
--

INSERT INTO `cashier_shifts` (`id`, `store_id`, `user_id`, `register_id`, `session_id`, `status`, `opening_float`, `expected_cash`, `counted_cash`, `variance`, `total_sales`, `transaction_count`, `opened_at`, `closed_at`, `notes`) VALUES
(1, 1, 1, NULL, NULL, 'closed', 0.00, 43014800.00, 43014800.00, 0.00, 43014800.00, 3, '2026-06-16 19:14:21', '2026-06-16 19:28:10', NULL),
(2, 1, 1, NULL, NULL, 'open', 100000.00, NULL, NULL, NULL, 1317368.00, 1, '2026-06-16 19:42:55', NULL, NULL),
(5, 1, 3, NULL, NULL, 'closed', 30000.00, 946900.00, 946900.00, 0.00, 1340900.00, 6, '2026-06-16 19:50:48', '2026-06-16 21:12:36', NULL),
(6, 1, 3, NULL, NULL, 'closed', 0.00, 21511110.00, 21511110.00, 0.00, 21511110.00, 7, '2026-06-16 21:13:53', '2026-06-17 04:35:14', NULL),
(7, 1, 4, NULL, NULL, 'closed', 0.00, 2968000.00, 2968000.00, 0.00, 2968000.00, 3, '2026-06-17 00:52:48', '2026-06-17 04:01:06', NULL),
(8, 7, 1, NULL, NULL, 'open', 0.00, NULL, NULL, NULL, 57447.80, 7, '2026-06-17 02:58:11', NULL, NULL),
(21, 11, 1, NULL, NULL, 'closed', 0.00, 1879.75, 1879.75, 0.00, 3336.50, 16, '2026-06-17 09:01:55', '2026-06-18 04:31:14', NULL),
(22, 11, 1, NULL, NULL, 'closed', 0.00, 540.75, 540.75, 0.00, 540.75, 1, '2026-06-18 04:49:34', '2026-06-18 04:50:31', NULL),
(23, 11, 1, NULL, NULL, 'closed', 0.00, 231.75, 231.75, 0.00, 231.75, 1, '2026-06-18 04:54:47', '2026-06-18 05:06:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cash_movements`
--

CREATE TABLE `cash_movements` (
  `id` bigint(20) NOT NULL,
  `store_id` int(11) NOT NULL,
  `register_id` int(11) DEFAULT NULL,
  `session_id` int(11) DEFAULT NULL,
  `movement_type` enum('opening_cash','sale','refund','expense','deposit','withdrawal','transfer_out','transfer_in','closing_cash','adjustment') NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_after` decimal(12,2) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `payment_method` varchar(30) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sync_status` enum('synced','pending','conflict') NOT NULL DEFAULT 'synced',
  `local_uuid` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cash_movements`
--

INSERT INTO `cash_movements` (`id`, `store_id`, `register_id`, `session_id`, `movement_type`, `amount`, `balance_after`, `reference_type`, `reference_id`, `payment_method`, `reason`, `created_by`, `created_at`, `sync_status`, `local_uuid`) VALUES
(1, 11, 6, 1, 'opening_cash', 25000.00, 25000.00, NULL, NULL, NULL, 'Session opening', 1, '2026-06-18 04:44:20', 'synced', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cash_reconciliation`
--

CREATE TABLE `cash_reconciliation` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `register_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `expected_cash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `physical_cash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `difference` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `manager_note` text DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cash_registers`
--

CREATE TABLE `cash_registers` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `register_code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `opening_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cash_registers`
--

INSERT INTO `cash_registers` (`id`, `store_id`, `register_code`, `name`, `assigned_user_id`, `status`, `opening_balance`, `current_balance`, `config`, `last_activity_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'CR1-4378', 'C1', 4, 'active', 0.00, 0.00, NULL, NULL, '2026-06-17 05:56:58', NULL, NULL),
(3, 1, 'CR1-8136', 'C2', NULL, 'active', 25000.00, 25000.00, NULL, NULL, '2026-06-17 06:02:33', NULL, NULL),
(4, 1, 'CR1-1651', 'C3', NULL, 'active', 0.00, 0.00, NULL, NULL, '2026-06-17 06:05:59', NULL, NULL),
(5, 1, 'CR1-0450', 'C4', NULL, 'inactive', 0.00, 0.00, NULL, NULL, '2026-06-17 06:07:26', NULL, NULL),
(6, 11, 'CR11-1325', 'C1', NULL, 'active', 0.00, 25000.00, NULL, '2026-06-18 04:44:20', '2026-06-18 04:42:28', '2026-06-18 04:44:20', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cash_register_logs`
--

CREATE TABLE `cash_register_logs` (
  `id` bigint(20) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `register_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cash_register_logs`
--

INSERT INTO `cash_register_logs` (`id`, `store_id`, `register_id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES
(2, 1, 1, 1, 'register_created', 'cash_register', 1, '{\"name\":\"C1\"}', '127.0.0.1', '2026-06-17 05:56:58'),
(3, 1, 3, 1, 'register_created', 'cash_register', 3, '{\"name\":\"C2\"}', '172.20.10.1', '2026-06-17 06:02:34'),
(4, 1, 4, 1, 'register_created', 'cash_register', 4, '{\"name\":\"C3\"}', '172.20.10.1', '2026-06-17 06:06:00'),
(5, 1, 5, 1, 'register_created', 'cash_register', 5, '{\"name\":\"C4\"}', '172.20.10.1', '2026-06-17 06:07:26'),
(6, 11, 6, 1, 'register_created', 'cash_register', 6, '{\"name\":\"C1\"}', '127.0.0.1', '2026-06-18 04:42:28'),
(7, 11, 6, 1, 'session_opened', 'cash_register_session', 1, NULL, '127.0.0.1', '2026-06-18 04:44:20'),
(8, 11, 6, 1, 'register_opened', 'notification', NULL, '{\"register_name\":\"C1\",\"opening_balance\":25000,\"message\":\"Cash register \\\"C1\\\" opened with 25,000 GHS\",\"notify\":true}', '127.0.0.1', '2026-06-18 04:44:20');

-- --------------------------------------------------------

--
-- Table structure for table `cash_register_sessions`
--

CREATE TABLE `cash_register_sessions` (
  `id` int(11) NOT NULL,
  `register_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shift_type` enum('morning','afternoon','evening','night','custom') NOT NULL DEFAULT 'morning',
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `opening_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `closing_balance` decimal(12,2) DEFAULT NULL,
  `expected_cash` decimal(12,2) DEFAULT NULL,
  `counted_cash` decimal(12,2) DEFAULT NULL,
  `variance` decimal(12,2) DEFAULT NULL,
  `total_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `card_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `mobile_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `refunds` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `transaction_count` int(11) NOT NULL DEFAULT 0,
  `cashier_shift_id` int(11) DEFAULT NULL,
  `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `opening_notes` text DEFAULT NULL,
  `closing_notes` text DEFAULT NULL,
  `opened_by` int(11) DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cash_register_sessions`
--

INSERT INTO `cash_register_sessions` (`id`, `register_id`, `store_id`, `user_id`, `shift_type`, `status`, `opening_balance`, `closing_balance`, `expected_cash`, `counted_cash`, `variance`, `total_sales`, `cash_sales`, `card_sales`, `mobile_sales`, `refunds`, `expenses`, `transaction_count`, `cashier_shift_id`, `opened_at`, `closed_at`, `opening_notes`, `closing_notes`, `opened_by`, `closed_by`) VALUES
(1, 6, 11, 1, 'morning', 'open', 25000.00, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, '2026-06-18 04:44:20', NULL, NULL, NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cash_transfers`
--

CREATE TABLE `cash_transfers` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `transfer_type` enum('register_to_register','register_to_safe','safe_to_register','branch_to_branch','warehouse_to_branch') NOT NULL,
  `from_register_id` int(11) DEFAULT NULL,
  `to_register_id` int(11) DEFAULT NULL,
  `from_store_id` int(11) DEFAULT NULL,
  `to_store_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','in_transit','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `store_id`, `name`, `description`, `deleted_at`) VALUES
(1, NULL, 1, 'Accessoires Informatiques', 'Claviers, souris, câbles', NULL),
(3, NULL, 1, 'Air condition', '', NULL),
(5, NULL, 1, 'Alimentaire ', '', NULL),
(6, NULL, 1, 'Phone accessories', '', NULL),
(7, NULL, 6, 'Motorcycle', '', NULL),
(8, NULL, 1, 'Electronic', '', NULL),
(9, NULL, 1, 'Furniture', '', NULL),
(10, NULL, 11, 'Kebabs', '', NULL),
(11, NULL, 1, 'Kebabs', '', NULL),
(12, NULL, 11, 'Drinks', '', NULL),
(13, NULL, 11, 'Beverages', NULL, NULL),
(14, NULL, 11, 'Groceries', NULL, '2026-06-17 08:44:01'),
(15, NULL, 12, 'Hygiène & Beauté', '•	Savons\n	•	Shampooings\n	•	Dentifrices\n	•	Brosses à dents\n	•	Déodorants\n	•	Parfums\n	•	Rasoirs\n	•	Produits cosmétiques\n	•	Crèmes', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `loyalty_points` int(11) DEFAULT 0,
  `store_id` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `loyalty_points`, `store_id`, `deleted_at`, `created_at`) VALUES
(1, 'Moussa', '0599944839', NULL, 0, 1, NULL, '2026-06-16 07:14:26'),
(2, 'Landry', '0599944870', 'landry@client.com', 0, 1, NULL, '2026-06-16 07:14:26'),
(3, 'The Rock', '0599944826', 'rock@pos.com', 0, 11, NULL, '2026-06-17 09:07:27');

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` bigint(20) NOT NULL,
  `slug` varchar(128) NOT NULL,
  `language` varchar(8) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `expense_date` date NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_login_attempts`
--

CREATE TABLE `failed_login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `email` varchar(255) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `locked_until` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `failed_login_attempts`
--

INSERT INTO `failed_login_attempts` (`id`, `ip_address`, `email`, `attempts`, `last_attempt`, `locked_until`) VALUES
(2, '127.0.0.1', 'Chida@gmail.com', 1, '2026-05-20 09:56:37', NULL),
(3, '172.20.10.1', 'mahadibusinessglobal227@gmail.com', 3, '2026-05-25 23:12:34', NULL),
(5, '172.20.10.1', 'casshier@pos.com', 4, '2026-05-24 12:56:54', NULL),
(6, '172.20.10.1', 'cashier@po', 1, '2026-05-24 12:57:27', NULL),
(9, '127.0.0.1', 'super_admin@colistrak.com', 3, '2026-06-28 22:15:32', NULL),
(11, '127.0.0.1', 'cahier@pos.com', 2, '2026-06-03 15:35:22', NULL),
(13, '172.20.10.1', 'desmon@pos.com', 3, '2026-06-15 21:09:13', NULL),
(14, '172.20.10.1', 'super_admin@colistrak.com', 3, '2026-06-23 18:55:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `goods_receipts`
--

CREATE TABLE `goods_receipts` (
  `id` int(11) NOT NULL,
  `grn_number` varchar(50) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `purchase_order_id` int(11) DEFAULT NULL,
  `status` enum('pending','inspecting','accepted','rejected','completed') NOT NULL DEFAULT 'pending',
  `inspection_status` enum('pending','passed','failed','partial') NOT NULL DEFAULT 'pending',
  `total_items` int(11) NOT NULL DEFAULT 0,
  `total_value` decimal(14,2) NOT NULL DEFAULT 0.00,
  `received_by` int(11) DEFAULT NULL,
  `inspected_by` int(11) DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `inspected_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `sync_status` enum('synced','pending','conflict') NOT NULL DEFAULT 'synced',
  `local_uuid` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `goods_receipts`
--

INSERT INTO `goods_receipts` (`id`, `grn_number`, `warehouse_id`, `supplier_id`, `purchase_order_id`, `status`, `inspection_status`, `total_items`, `total_value`, `received_by`, `inspected_by`, `received_at`, `inspected_at`, `notes`, `sync_status`, `local_uuid`) VALUES
(1, 'GRN-20260620-3B49A8', 4, NULL, NULL, 'completed', 'passed', 1, 4000000.00, 1, 1, '2026-06-20 10:23:38', '2026-06-20 10:23:53', 'Supplier: Mahadi Business Global\nElectronic', 'synced', NULL),
(2, 'GRN-20260620-3C369D', 4, NULL, NULL, 'completed', 'passed', 2, 2299600.00, 1, 1, '2026-06-20 10:30:39', '2026-06-20 10:31:00', NULL, 'synced', NULL),
(3, 'GRN-20260620-26921E', 4, NULL, NULL, 'completed', 'passed', 3, 1824900.00, 1, 1, '2026-06-20 11:02:52', '2026-06-20 11:03:17', NULL, 'synced', NULL),
(4, 'GRN-20260620-32F128', 4, NULL, NULL, 'completed', 'passed', 1, 200.00, 1, 1, '2026-06-20 11:08:38', '2026-06-20 11:08:46', NULL, 'synced', NULL),
(5, 'GRN-20260620-790196', 4, NULL, NULL, 'completed', 'passed', 2, 9098600.00, 1, 1, '2026-06-20 11:12:19', '2026-06-20 11:12:19', 'Supplier: Mahadi Business Global\nElectronic', 'synced', NULL),
(6, 'GRN-20260620-231C25', 4, NULL, NULL, 'rejected', 'failed', 3, 2708.00, 1, 1, '2026-06-20 11:19:37', '2026-06-20 11:24:23', NULL, 'synced', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `goods_receipt_items`
--

CREATE TABLE `goods_receipt_items` (
  `id` int(11) NOT NULL,
  `goods_receipt_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `quantity_expected` int(11) NOT NULL DEFAULT 0,
  `quantity_received` int(11) NOT NULL DEFAULT 0,
  `quantity_damaged` int(11) NOT NULL DEFAULT 0,
  `unit_cost` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `batch_number` varchar(80) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `barcode` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `goods_receipt_items`
--

INSERT INTO `goods_receipt_items` (`id`, `goods_receipt_id`, `product_id`, `batch_id`, `location_id`, `quantity_expected`, `quantity_received`, `quantity_damaged`, `unit_cost`, `batch_number`, `expiry_date`, `barcode`) VALUES
(1, 1, 24, NULL, NULL, 200, 200, 0, 20000.0000, NULL, NULL, NULL),
(2, 2, 96, NULL, NULL, 300, 300, 0, 4999.0000, NULL, NULL, NULL),
(3, 2, 97, NULL, NULL, 100, 100, 0, 7999.0000, NULL, NULL, NULL),
(4, 3, 98, NULL, NULL, 100, 100, 0, 6999.0000, NULL, NULL, NULL),
(5, 3, 99, NULL, NULL, 400, 400, 0, 2500.0000, NULL, NULL, NULL),
(6, 3, 100, NULL, NULL, 50, 50, 0, 2500.0000, NULL, NULL, NULL),
(7, 4, 101, NULL, NULL, 1, 1, 0, 200.0000, NULL, NULL, NULL),
(8, 5, 96, NULL, NULL, 700, 700, 0, 4999.0000, NULL, NULL, NULL),
(9, 5, 97, NULL, NULL, 700, 700, 0, 7999.0000, 'skelewu', '2026-06-22', NULL),
(10, 6, 101, NULL, NULL, 1, 1, 1, 200.0000, NULL, NULL, NULL),
(11, 6, 102, NULL, NULL, 1, 1, 1, 8.0000, NULL, NULL, NULL),
(12, 6, 103, NULL, NULL, 1, 1, 0, 2500.0000, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `help_articles`
--

CREATE TABLE `help_articles` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` smallint(5) UNSIGNED NOT NULL,
  `slug` varchar(80) NOT NULL,
  `article_type` enum('article','guide','manual') NOT NULL DEFAULT 'article',
  `title_en` varchar(200) NOT NULL,
  `title_fr` varchar(200) NOT NULL,
  `summary_en` text DEFAULT NULL,
  `summary_fr` text DEFAULT NULL,
  `body_en` mediumtext NOT NULL,
  `body_fr` mediumtext NOT NULL,
  `module` varchar(40) DEFAULT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`roles`)),
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `help_articles`
--

INSERT INTO `help_articles` (`id`, `category_id`, `slug`, `article_type`, `title_en`, `title_fr`, `summary_en`, `summary_fr`, `body_en`, `body_fr`, `module`, `roles`, `sort_order`, `is_published`, `created_at`, `updated_at`) VALUES
(1, 2, 'guide-receiving', 'guide', 'Receiving Products', 'Réception produits', 'Step-by-step goods receipt workflow.', 'Flux de réception pas à pas.', '<ol><li>Open <strong>Receiving → Receive Stock</strong>.</li><li>Select supplier delivery or PO.</li><li>Scan barcodes or search products.</li><li>Enter quantities and batch/expiry if required.</li><li>Complete quality inspection when enabled.</li><li>Confirm receipt — inventory updates automatically.</li></ol>', '<ol><li>Ouvrez <strong>Réception → Réception stock</strong>.</li><li>Sélectionnez la livraison ou le bon de commande.</li><li>Scannez ou recherchez les produits.</li><li>Saisissez quantités et lot/péremption.</li><li>Effectuez le contrôle qualité si activé.</li><li>Confirmez — le stock est mis à jour.</li></ol>', 'receiving', NULL, 1, 1, '2026-06-19 08:54:33', NULL),
(2, 3, 'guide-dispatch', 'guide', 'Dispatching Products', 'Expédition produits', 'From pick list to delivery confirmation.', 'Du prélèvement à la confirmation livraison.', '<ol><li>Open dispatch orders and generate pick list.</li><li>Pick items and mark packed.</li><li>Run final verification scan.</li><li>Create shipment with driver/vehicle details.</li><li>Confirm delivery when complete.</li></ol>', '<ol><li>Ouvrez les ordres d\'expédition et générez la liste de prélèvement.</li><li>Prélevez et marquez emballé.</li><li>Effectuez la vérification finale.</li><li>Créez l\'expédition avec chauffeur/véhicule.</li><li>Confirmez la livraison.</li></ol>', 'dispatch', '[\"dispatch_officer\",\"warehouse_manager\"]', 2, 1, '2026-06-19 08:54:33', NULL),
(3, 4, 'guide-transfers', 'guide', 'Creating Transfers', 'Créer des transferts', 'Move stock between warehouses or branches.', 'Déplacer le stock entre entrepôts ou succursales.', '<ol><li>Go to <strong>Transfers → Warehouse Transfer</strong>.</li><li>Select source and destination.</li><li>Add products and quantities.</li><li>Submit for approval if required.</li><li>Track status in incoming/outgoing lists.</li></ol>', '<ol><li>Allez à <strong>Transferts → Transfert entrepôt</strong>.</li><li>Sélectionnez source et destination.</li><li>Ajoutez produits et quantités.</li><li>Soumettez pour approbation si requis.</li><li>Suivez le statut dans les listes.</li></ol>', 'transfers', NULL, 3, 1, '2026-06-19 08:54:33', NULL),
(4, 9, 'guide-stock-count', 'guide', 'Performing Inventory Count', 'Effectuer un comptage', 'Cycle count and physical inventory.', 'Comptage cyclique et inventaire physique.', '<ol><li>Open <strong>Inventory → Stock Count</strong>.</li><li>Create or open an count session.</li><li>Scan or enter counted quantities.</li><li>Review variances.</li><li>Submit for approval.</li></ol>', '<ol><li>Ouvrez <strong>Inventaire → Comptage stock</strong>.</li><li>Créez ou ouvrez une session.</li><li>Scannez ou saisissez les quantités.</li><li>Examinez les écarts.</li><li>Soumettez pour approbation.</li></ol>', 'inventory', NULL, 4, 1, '2026-06-19 08:54:33', NULL),
(5, 1, 'guide-search', 'guide', 'Searching Products', 'Rechercher des produits', 'Global search and scanner lookup.', 'Recherche globale et scanner.', '<p>Use the top search bar or <strong>Inventory → Barcode Scanner</strong>. Enter SKU, barcode, batch, or product name. Results show warehouse, quantity, and location.</p>', '<p>Utilisez la barre de recherche ou <strong>Inventaire → Scanner</strong>. Saisissez SKU, code-barres, lot ou nom. Les résultats affichent entrepôt, quantité et emplacement.</p>', 'inventory', NULL, 5, 1, '2026-06-19 08:54:33', NULL),
(6, 5, 'guide-labels', 'guide', 'Printing Barcode Labels', 'Imprimer des étiquettes', 'Generate and print product labels.', 'Générer et imprimer des étiquettes.', '<p>From product detail or batch tracking, choose <strong>Print Label</strong>. Configure prefix and format in Settings → Barcode. Use standard label printer or PDF export.</p>', '<p>Depuis le détail produit ou lot, choisissez <strong>Imprimer étiquette</strong>. Configurez préfixe et format dans Paramètres → Code-barres.</p>', 'barcode', NULL, 6, 1, '2026-06-19 08:54:33', NULL),
(7, 6, 'guide-qr', 'guide', 'Scanning QR Codes', 'Scanner les codes QR', 'QR scanning for batches and locations.', 'Scan QR pour lots et emplacements.', '<p>Open the scanner page, select QR mode, and scan. QR codes may encode batch ID, location bin, or product URL.</p>', '<p>Ouvrez le scanner, mode QR, et scannez. Les QR peuvent encoder lot, emplacement ou URL produit.</p>', 'qr', NULL, 7, 1, '2026-06-19 08:54:33', NULL),
(8, 13, 'guide-reports', 'guide', 'Viewing Reports', 'Consulter les rapports', 'Access warehouse analytics and exports.', 'Accéder aux analyses et exports.', '<p>Navigate to <strong>Reports</strong> for inventory, movements, receiving, dispatch, transfers, performance, valuation, damage, and expiry reports. Filter by warehouse and period; export CSV, Excel, or PDF.</p>', '<p>Allez à <strong>Rapports</strong> pour inventaire, mouvements, réception, expédition, transferts, performance, valorisation, dommages et péremption. Filtrez et exportez.</p>', 'reports', NULL, 8, 1, '2026-06-19 08:54:33', NULL),
(9, 12, 'guide-notifications', 'guide', 'Managing Notifications', 'Gérer les notifications', 'Alerts and notification preferences.', 'Alertes et préférences.', '<p>Visit <strong>Notifications</strong> for inbox. Configure channels in Profile or Settings — email, SMS, push, WhatsApp, and warehouse-specific alerts.</p>', '<p>Consultez <strong>Notifications</strong>. Configurez les canaux dans Profil ou Paramètres.</p>', 'notifications', NULL, 9, 1, '2026-06-19 08:54:33', NULL),
(10, 14, 'guide-offline', 'guide', 'Working Offline', 'Travailler hors ligne', 'PWA offline queue and sync.', 'File d\'attente PWA et synchronisation.', '<p>When offline, operations queue locally. A badge shows cached data. Reconnect to sync automatically. Check Settings → Offline for conflict strategy and sync frequency.</p>', '<p>Hors ligne, les opérations sont mises en file. Reconnectez-vous pour synchroniser. Voir Paramètres → Sync hors ligne.</p>', 'offline', NULL, 10, 1, '2026-06-19 08:54:33', NULL),
(11, 1, 'manual-inventory', 'manual', 'Warehouse User Guide', 'Guide utilisateur entrepôt', 'PDF-ready manual for inventory.', 'Manuel PDF pour inventory.', '<h2>Warehouse User Guide</h2><p>Download this guide for offline reference. Content mirrors in-app help articles for the inventory module.</p>', '<h2>Guide utilisateur entrepôt</h2><p>Téléchargez ce guide pour référence hors ligne. Contenu aligné sur l\'aide in-app du module inventory.</p>', 'inventory', NULL, 90, 1, '2026-06-19 08:54:33', NULL),
(12, 1, 'manual-inventory-detail', 'manual', 'Inventory Guide', 'Guide inventaire', 'PDF-ready manual for inventory.', 'Manuel PDF pour inventory.', '<h2>Inventory Guide</h2><p>Download this guide for offline reference. Content mirrors in-app help articles for the inventory module.</p>', '<h2>Guide inventaire</h2><p>Téléchargez ce guide pour référence hors ligne. Contenu aligné sur l\'aide in-app du module inventory.</p>', 'inventory', NULL, 90, 1, '2026-06-19 08:54:33', NULL),
(13, 2, 'manual-receiving', 'manual', 'Receiving Guide', 'Guide réception', 'PDF-ready manual for receiving.', 'Manuel PDF pour receiving.', '<h2>Receiving Guide</h2><p>Download this guide for offline reference. Content mirrors in-app help articles for the receiving module.</p>', '<h2>Guide réception</h2><p>Téléchargez ce guide pour référence hors ligne. Contenu aligné sur l\'aide in-app du module receiving.</p>', 'receiving', NULL, 90, 1, '2026-06-19 08:54:33', NULL),
(14, 3, 'manual-dispatch', 'manual', 'Dispatch Guide', 'Guide expédition', 'PDF-ready manual for dispatch.', 'Manuel PDF pour dispatch.', '<h2>Dispatch Guide</h2><p>Download this guide for offline reference. Content mirrors in-app help articles for the dispatch module.</p>', '<h2>Guide expédition</h2><p>Téléchargez ce guide pour référence hors ligne. Contenu aligné sur l\'aide in-app du module dispatch.</p>', 'dispatch', NULL, 90, 1, '2026-06-19 08:54:33', NULL),
(15, 4, 'manual-transfers', 'manual', 'Transfer Guide', 'Guide transferts', 'PDF-ready manual for transfers.', 'Manuel PDF pour transfers.', '<h2>Transfer Guide</h2><p>Download this guide for offline reference. Content mirrors in-app help articles for the transfers module.</p>', '<h2>Guide transferts</h2><p>Téléchargez ce guide pour référence hors ligne. Contenu aligné sur l\'aide in-app du module transfers.</p>', 'transfers', NULL, 90, 1, '2026-06-19 08:54:33', NULL),
(16, 5, 'manual-barcode', 'manual', 'Barcode Guide', 'Guide code-barres', 'PDF-ready manual for barcode.', 'Manuel PDF pour barcode.', '<h2>Barcode Guide</h2><p>Download this guide for offline reference. Content mirrors in-app help articles for the barcode module.</p>', '<h2>Guide code-barres</h2><p>Téléchargez ce guide pour référence hors ligne. Contenu aligné sur l\'aide in-app du module barcode.</p>', 'barcode', NULL, 90, 1, '2026-06-19 08:54:33', NULL),
(17, 14, 'manual-offline', 'manual', 'Offline Guide', 'Guide hors ligne', 'PDF-ready manual for offline.', 'Manuel PDF pour offline.', '<h2>Offline Guide</h2><p>Download this guide for offline reference. Content mirrors in-app help articles for the offline module.</p>', '<h2>Guide hors ligne</h2><p>Téléchargez ce guide pour référence hors ligne. Contenu aligné sur l\'aide in-app du module offline.</p>', 'offline', NULL, 90, 1, '2026-06-19 08:54:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `help_categories`
--

CREATE TABLE `help_categories` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `slug` varchar(60) NOT NULL,
  `icon` varchar(40) NOT NULL DEFAULT 'help',
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `name_en` varchar(120) NOT NULL,
  `name_fr` varchar(120) NOT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`roles`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `help_categories`
--

INSERT INTO `help_categories` (`id`, `slug`, `icon`, `sort_order`, `name_en`, `name_fr`, `roles`, `is_active`) VALUES
(1, 'inventory', 'inventory_2', 10, 'Inventory', 'Inventaire', NULL, 1),
(2, 'receiving', 'move_to_inbox', 20, 'Receiving Goods', 'Réception', '[\"receiving_officer\",\"warehouse_manager\",\"storekeeper\"]', 1),
(3, 'dispatch', 'local_shipping', 30, 'Dispatch', 'Expédition', '[\"dispatch_officer\",\"warehouse_manager\"]', 1),
(4, 'transfers', 'swap_horiz', 40, 'Warehouse Transfers', 'Transferts', NULL, 1),
(5, 'barcode', 'qr_code_scanner', 50, 'Barcode Scanner', 'Scanner code-barres', NULL, 1),
(6, 'qr', 'qr_code_2', 55, 'QR Code Scanner', 'Scanner QR', NULL, 1),
(7, 'batch', 'layers', 60, 'Batch Tracking', 'Suivi lots', NULL, 1),
(8, 'serial', 'tag', 65, 'Serial Numbers', 'Numéros de série', NULL, 1),
(9, 'stock_count', 'fact_check', 70, 'Stock Count', 'Comptage stock', NULL, 1),
(10, 'adjustment', 'tune', 75, 'Inventory Adjustment', 'Ajustements', NULL, 1),
(11, 'locations', 'place', 80, 'Warehouse Locations', 'Emplacements', NULL, 1),
(12, 'notifications', 'notifications', 90, 'Notifications', 'Notifications', NULL, 1),
(13, 'reports', 'assessment', 100, 'Reports', 'Rapports', NULL, 1),
(14, 'offline', 'cloud_off', 110, 'Offline Mode', 'Mode hors ligne', NULL, 1),
(15, 'profile', 'account_circle', 120, 'User Profile', 'Profil utilisateur', NULL, 1),
(16, 'settings', 'settings', 130, 'Settings', 'Paramètres', '[\"warehouse_manager\",\"admin\",\"super_admin\"]', 1),
(17, 'security', 'shield', 140, 'Security', 'Sécurité', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `help_faq`
--

CREATE TABLE `help_faq` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` smallint(5) UNSIGNED DEFAULT NULL,
  `question_en` varchar(255) NOT NULL,
  `question_fr` varchar(255) NOT NULL,
  `answer_en` text NOT NULL,
  `answer_fr` text NOT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`roles`)),
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `help_faq`
--

INSERT INTO `help_faq` (`id`, `category_id`, `question_en`, `question_fr`, `answer_en`, `answer_fr`, `roles`, `sort_order`, `is_published`) VALUES
(1, 2, 'How do I receive products?', 'Comment réceptionner des produits?', 'Go to Receiving → Receive Stock, select the delivery, scan or add items, complete inspection, and confirm the goods receipt note (GRN).', 'Allez à Réception → Réception stock, sélectionnez la livraison, scannez ou ajoutez les articles, contrôlez et confirmez le GRN.', NULL, 1, 1),
(2, 4, 'How do I transfer stock?', 'Comment transférer du stock?', 'Use Transfers → Warehouse Transfer. Choose source/destination, add lines, submit, and track approval status.', 'Utilisez Transferts → Transfert entrepôt. Choisissez source/destination, ajoutez les lignes et suivez l\'approbation.', NULL, 2, 1),
(3, 9, 'How do I perform stock counting?', 'Comment effectuer un comptage?', 'Open Inventory → Stock Count, start a session, enter counted quantities, review variances, and submit.', 'Ouvrez Inventaire → Comptage, démarrez une session, saisissez les quantités et soumettez.', NULL, 3, 1),
(4, 5, 'How do I scan barcodes?', 'Comment scanner des code-barres?', 'Open Inventory → Barcode Scanner. Allow camera access or use a USB scanner in the search field.', 'Ouvrez Inventaire → Scanner. Autorisez la caméra ou utilisez un scanner USB.', NULL, 4, 1),
(5, 5, 'How do I print labels?', 'Comment imprimer des étiquettes?', 'From product or batch screens, click Print Label. Configure barcode type and prefix under Settings → Barcode.', 'Depuis produit ou lot, cliquez Imprimer étiquette. Configurez le type dans Paramètres → Code-barres.', NULL, 5, 1),
(6, 14, 'How do I work offline?', 'Comment travailler hors ligne?', 'The portal caches data locally. Continue receiving, counting, or viewing reports offline; changes sync when online.', 'Le portail met en cache les données. Continuez hors ligne ; les changements se synchronisent en ligne.', NULL, 6, 1),
(7, 14, 'How do I recover synchronized data?', 'Comment récupérer les données synchronisées?', 'Reconnect to the network and click Refresh. Pending items appear in Sync Monitor. Conflicts follow the strategy in Settings → Offline.', 'Reconnectez le réseau et actualisez. Les éléments en attente apparaissent dans Sync Monitor.', NULL, 7, 1),
(8, 10, 'How do I report damaged products?', 'Comment signaler des produits endommagés?', 'Use Inventory → Stock Adjustments, select damage reason, enter quantity, and submit for approval if required.', 'Utilisez Inventaire → Ajustements, motif dommage, quantité, et soumettez si approbation requise.', NULL, 8, 1),
(9, 11, 'How do I locate products inside the warehouse?', 'Comment localiser des produits?', 'Search by SKU/barcode or open Warehouse Locations to browse zones, aisles, racks, and bins.', 'Recherchez par SKU/code-barres ou parcourez Emplacements entrepôt (zones, allées, racks, bacs).', NULL, 9, 1);

-- --------------------------------------------------------

--
-- Table structure for table `help_support_tickets`
--

CREATE TABLE `help_support_tickets` (
  `id` int(10) UNSIGNED NOT NULL,
  `ticket_number` varchar(30) NOT NULL,
  `user_id` int(11) NOT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `role_slug` varchar(60) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `category` varchar(60) NOT NULL DEFAULT 'general',
  `priority` enum('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  `description` text NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `ticket_type` enum('support','problem') NOT NULL DEFAULT 'support',
  `problem_type` varchar(60) DEFAULT NULL,
  `status` enum('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `help_system_updates`
--

CREATE TABLE `help_system_updates` (
  `id` int(10) UNSIGNED NOT NULL,
  `version` varchar(30) NOT NULL,
  `title_en` varchar(200) NOT NULL,
  `title_fr` varchar(200) NOT NULL,
  `body_en` text NOT NULL,
  `body_fr` text NOT NULL,
  `update_type` enum('feature','improvement','bugfix','maintenance') NOT NULL DEFAULT 'feature',
  `published_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_published` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `help_system_updates`
--

INSERT INTO `help_system_updates` (`id`, `version`, `title_en`, `title_fr`, `body_en`, `body_fr`, `update_type`, `published_at`, `is_published`) VALUES
(1, '2.4.0', 'Warehouse Help Center', 'Centre d\'aide entrepôt', 'New searchable help center with guides, FAQ, tickets, and offline docs.', 'Nouveau centre d\'aide avec recherche, guides, FAQ, tickets et docs hors ligne.', 'feature', '2026-06-19 08:54:33', 1),
(2, '2.3.0', 'Enterprise Reports Suite', 'Suite de rapports entreprise', 'Inventory, movement, receiving, dispatch, transfer, performance, valuation, damage, and expiry reports.', 'Rapports inventaire, mouvements, réception, expédition, transferts, performance, valorisation, dommages et péremption.', 'feature', '2026-06-19 08:54:33', 1),
(3, '2.2.1', 'Offline sync improvements', 'Améliorations sync hors ligne', 'Faster conflict resolution and clearer pending queue status.', 'Résolution de conflits plus rapide et file d\'attente plus claire.', 'improvement', '2026-06-19 08:54:33', 1);

-- --------------------------------------------------------

--
-- Table structure for table `help_ticket_replies`
--

CREATE TABLE `help_ticket_replies` (
  `id` int(10) UNSIGNED NOT NULL,
  `ticket_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_staff` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `help_tutorial_videos`
--

CREATE TABLE `help_tutorial_videos` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` smallint(5) UNSIGNED DEFAULT NULL,
  `title_en` varchar(200) NOT NULL,
  `title_fr` varchar(200) NOT NULL,
  `description_en` text DEFAULT NULL,
  `description_fr` text DEFAULT NULL,
  `video_type` enum('youtube','hosted') NOT NULL DEFAULT 'youtube',
  `video_url` varchar(500) NOT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `duration_seconds` int(10) UNSIGNED DEFAULT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`roles`)),
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `help_tutorial_videos`
--

INSERT INTO `help_tutorial_videos` (`id`, `category_id`, `title_en`, `title_fr`, `description_en`, `description_fr`, `video_type`, `video_url`, `thumbnail_url`, `duration_seconds`, `roles`, `sort_order`, `is_published`) VALUES
(1, 2, 'Receiving Workflow Overview', 'Aperçu réception', 'Introduction to supplier deliveries and GRN.', 'Introduction aux livraisons et GRN.', 'youtube', 'https://www.youtube.com/embed/dQw4w9WgXcQ', NULL, 420, NULL, 1, 1),
(2, 3, 'Dispatch & Shipping', 'Expédition et livraison', 'Pick, pack, ship process.', 'Processus prélèvement, emballage, expédition.', 'youtube', 'https://www.youtube.com/embed/dQw4w9WgXcQ', NULL, 360, NULL, 2, 1),
(3, 5, 'Barcode Scanning Tips', 'Conseils scan code-barres', 'Fast scanning techniques.', 'Techniques de scan rapide.', 'youtube', 'https://www.youtube.com/embed/dQw4w9WgXcQ', NULL, 180, NULL, 3, 1);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_counts`
--

CREATE TABLE `inventory_counts` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `count_number` varchar(50) NOT NULL,
  `count_type` enum('cycle','full','spot') NOT NULL DEFAULT 'cycle',
  `status` enum('draft','in_progress','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `scheduled_date` date DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_ledger`
--

CREATE TABLE `inventory_ledger` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `movement_type` enum('purchase','sale','return','transfer_in','transfer_out','adjustment','damaged','expired','manual_edit') NOT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `reference_type` varchar(100) DEFAULT NULL,
  `opening_stock` int(11) NOT NULL DEFAULT 0,
  `stock_in` int(11) NOT NULL DEFAULT 0,
  `stock_out` int(11) NOT NULL DEFAULT 0,
  `current_stock` int(11) NOT NULL DEFAULT 0,
  `purchase_price` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `selling_price` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `opening_stock_value` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `stock_out_value` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `current_stock_value` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `estimated_profit` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `notes` text DEFAULT NULL,
  `movement_date` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_ledger`
--

INSERT INTO `inventory_ledger` (`id`, `product_id`, `store_id`, `warehouse_id`, `user_id`, `movement_type`, `reference_id`, `reference_type`, `opening_stock`, `stock_in`, `stock_out`, `current_stock`, `purchase_price`, `selling_price`, `opening_stock_value`, `stock_out_value`, `current_stock_value`, `estimated_profit`, `notes`, `movement_date`, `created_at`) VALUES
(1, 1, 1, NULL, 1, 'adjustment', '1', 'inventory_log', 0, 100, 0, -52, 3500.0000, 5000.0000, 0.0000, 0.0000, -182000.0000, 0.0000, 'Imported from inventory_logs #1', '2026-05-10 04:43:56', '2026-06-15 18:18:52'),
(2, 1, 1, NULL, 1, 'sale', '4', 'inventory_log', 0, 0, 40, -52, 3500.0000, 5000.0000, 0.0000, 200000.0000, -182000.0000, 60000.0000, 'Imported from inventory_logs #4', '2026-05-10 05:32:49', '2026-06-15 18:18:52'),
(3, 1, 1, NULL, 1, 'sale', '6', 'inventory_log', 0, 0, 5, -52, 3500.0000, 5000.0000, 0.0000, 25000.0000, -182000.0000, 7500.0000, 'Imported from inventory_logs #6', '2026-05-10 05:45:04', '2026-06-15 18:18:52'),
(4, 1, 1, NULL, 1, 'sale', '7', 'inventory_log', 0, 0, 4, -52, 3500.0000, 5000.0000, 0.0000, 20000.0000, -182000.0000, 6000.0000, 'Imported from inventory_logs #7', '2026-05-10 05:45:18', '2026-06-15 18:18:52'),
(5, 1, 1, NULL, 1, 'sale', '8', 'inventory_log', 0, 0, 1, -52, 3500.0000, 5000.0000, 0.0000, 5000.0000, -182000.0000, 1500.0000, 'Imported from inventory_logs #8', '2026-05-10 05:45:37', '2026-06-15 18:18:52'),
(6, 1, 1, NULL, 1, 'sale', '9', 'inventory_log', 0, 0, 7, -52, 3500.0000, 5000.0000, 0.0000, 35000.0000, -182000.0000, 10500.0000, 'Imported from inventory_logs #9', '2026-05-10 05:57:51', '2026-06-15 18:18:52'),
(7, 1, 1, NULL, 1, 'sale', '12', 'inventory_log', 0, 0, 1, -52, 3500.0000, 5000.0000, 0.0000, 5000.0000, -182000.0000, 1500.0000, 'Imported from inventory_logs #12', '2026-05-10 08:20:50', '2026-06-15 18:18:52'),
(8, 1, 1, NULL, 1, 'sale', '21', 'inventory_log', 42, 0, 94, -52, 3500.0000, 5000.0000, 147000.0000, 470000.0000, -182000.0000, 141000.0000, 'Imported from inventory_logs #21', '2026-05-13 13:49:50', '2026-06-15 18:18:52'),
(9, 2, 1, NULL, 1, 'adjustment', '2', 'inventory_log', 0, 500, 0, 490, 5000.0000, 7500.0000, 0.0000, 0.0000, 2450000.0000, 0.0000, 'Imported from inventory_logs #2', '2026-05-10 04:44:57', '2026-06-15 18:18:52'),
(10, 2, 1, NULL, 1, 'sale', '3', 'inventory_log', 495, 0, 5, 490, 5000.0000, 7500.0000, 2475000.0000, 37500.0000, 2450000.0000, 12500.0000, 'Imported from inventory_logs #3', '2026-05-10 05:31:11', '2026-06-15 18:18:52'),
(11, 2, 1, NULL, 1, 'sale', '5', 'inventory_log', 495, 0, 5, 490, 5000.0000, 7500.0000, 2475000.0000, 37500.0000, 2450000.0000, 12500.0000, 'Imported from inventory_logs #5', '2026-05-10 05:32:49', '2026-06-15 18:18:52'),
(12, 3, 1, NULL, 1, 'adjustment', '10', 'inventory_log', 0, 100, 0, 100, 1500.0000, 2000.0000, 0.0000, 0.0000, 150000.0000, 0.0000, 'Imported from inventory_logs #10', '2026-05-10 06:08:45', '2026-06-15 18:18:52'),
(13, 8, 1, NULL, 1, 'adjustment', '11', 'inventory_log', 0, 500, 0, 478, 600.0000, 1000.0000, 0.0000, 0.0000, 286800.0000, 0.0000, 'Imported from inventory_logs #11', '2026-05-10 06:42:36', '2026-06-15 18:18:52'),
(14, 8, 1, NULL, 1, 'sale', '13', 'inventory_log', 488, 0, 10, 478, 600.0000, 1000.0000, 292800.0000, 10000.0000, 286800.0000, 4000.0000, 'Imported from inventory_logs #13', '2026-05-10 08:20:50', '2026-06-15 18:18:52'),
(15, 8, 1, NULL, 1, 'sale', '14', 'inventory_log', 484, 0, 6, 478, 600.0000, 1000.0000, 290400.0000, 6000.0000, 286800.0000, 2400.0000, 'Imported from inventory_logs #14', '2026-05-10 23:24:08', '2026-06-15 18:18:52'),
(16, 8, 1, NULL, 1, 'sale', '25', 'inventory_log', 484, 0, 6, 478, 600.0000, 1000.0000, 290400.0000, 6000.0000, 286800.0000, 2400.0000, 'Imported from inventory_logs #25', '2026-05-13 13:49:50', '2026-06-15 18:18:52'),
(17, 9, 1, NULL, 1, 'adjustment', '15', 'inventory_log', 0, 100, 0, 78, 150000.0000, 200000.0000, 0.0000, 0.0000, 11700000.0000, 0.0000, 'Imported from inventory_logs #15', '2026-05-13 13:12:07', '2026-06-15 18:18:52'),
(18, 9, 1, NULL, 1, 'sale', '16', 'inventory_log', 88, 0, 10, 78, 150000.0000, 200000.0000, 13200000.0000, 2000000.0000, 11700000.0000, 500000.0000, 'Imported from inventory_logs #16', '2026-05-13 13:21:38', '2026-06-15 18:18:52'),
(19, 9, 1, NULL, 1, 'sale', '26', 'inventory_log', 82, 0, 4, 78, 150000.0000, 200000.0000, 12300000.0000, 800000.0000, 11700000.0000, 200000.0000, 'Imported from inventory_logs #26', '2026-05-13 13:49:50', '2026-06-15 18:18:52'),
(20, 9, 1, NULL, 1, 'sale', '30', 'inventory_log', 83, 0, 5, 78, 150000.0000, 200000.0000, 12450000.0000, 1000000.0000, 11700000.0000, 250000.0000, 'Imported from inventory_logs #30', '2026-05-17 16:29:02', '2026-06-15 18:18:52'),
(21, 9, 1, NULL, 1, 'sale', '34', 'inventory_log', 83, 0, 5, 78, 150000.0000, 200000.0000, 12450000.0000, 1000000.0000, 11700000.0000, 250000.0000, 'Imported from inventory_logs #34', '2026-05-17 17:53:49', '2026-06-15 18:18:52'),
(22, 9, 1, NULL, 1, 'sale', '36', 'inventory_log', 84, 0, 6, 78, 150000.0000, 200000.0000, 12600000.0000, 1200000.0000, 11700000.0000, 300000.0000, 'Imported from inventory_logs #36', '2026-05-18 11:11:24', '2026-06-15 18:18:52'),
(23, 9, 1, NULL, 1, 'sale', '42', 'inventory_log', 209, 0, 131, 78, 150000.0000, 200000.0000, 31350000.0000, 26200000.0000, 11700000.0000, 6550000.0000, 'Imported from inventory_logs #42', '2026-05-18 12:18:08', '2026-06-15 18:18:52'),
(24, 9, 1, NULL, 2, 'adjustment', '73', 'inventory_log', 8, 70, 0, 78, 150000.0000, 200000.0000, 1200000.0000, 0.0000, 11700000.0000, 0.0000, 'Imported from inventory_logs #73', '2026-05-22 22:39:08', '2026-06-15 18:18:52'),
(25, 9, 1, NULL, 2, 'adjustment', '74', 'inventory_log', 37, 41, 0, 78, 150000.0000, 200000.0000, 5550000.0000, 0.0000, 11700000.0000, 0.0000, 'Imported from inventory_logs #74', '2026-05-22 22:39:49', '2026-06-15 18:18:52'),
(26, 9, 1, NULL, 2, 'sale', '75', 'inventory_log', 80, 0, 2, 78, 150000.0000, 200000.0000, 12000000.0000, 400000.0000, 11700000.0000, 100000.0000, 'Imported from inventory_logs #75', '2026-05-22 22:50:03', '2026-06-15 18:18:52'),
(27, 9, 1, NULL, 4, 'sale', '79', 'inventory_log', 79, 0, 1, 78, 150000.0000, 200000.0000, 11850000.0000, 200000.0000, 11700000.0000, 50000.0000, 'Imported from inventory_logs #79', '2026-05-23 00:10:28', '2026-06-15 18:18:52'),
(28, 9, 1, NULL, 4, 'sale', '83', 'inventory_log', 79, 0, 1, 78, 150000.0000, 200000.0000, 11850000.0000, 200000.0000, 11700000.0000, 50000.0000, 'Imported from inventory_logs #83', '2026-05-23 00:51:36', '2026-06-15 18:18:52'),
(29, 9, 1, NULL, 4, 'sale', '95', 'inventory_log', 84, 0, 6, 78, 150000.0000, 200000.0000, 12600000.0000, 1200000.0000, 11700000.0000, 300000.0000, 'Imported from inventory_logs #95', '2026-05-23 05:42:40', '2026-06-15 18:18:52'),
(30, 9, 1, NULL, 3, 'sale', '97', 'inventory_log', 79, 0, 1, 78, 150000.0000, 200000.0000, 11850000.0000, 200000.0000, 11700000.0000, 50000.0000, 'Imported from inventory_logs #97', '2026-05-23 15:43:38', '2026-06-15 18:18:52'),
(31, 9, 1, NULL, 4, 'sale', '107', 'inventory_log', 87, 0, 9, 78, 150000.0000, 200000.0000, 13050000.0000, 1800000.0000, 11700000.0000, 450000.0000, 'Imported from inventory_logs #107', '2026-05-23 20:19:44', '2026-06-15 18:18:52'),
(32, 9, 1, NULL, 4, 'adjustment', '109', 'inventory_log', 77, 1, 0, 78, 150000.0000, 200000.0000, 11550000.0000, 0.0000, 11700000.0000, 0.0000, 'Imported from inventory_logs #109', '2026-05-23 22:40:01', '2026-06-15 18:18:52'),
(33, 9, 1, NULL, 4, 'sale', '117', 'inventory_log', 79, 0, 1, 78, 150000.0000, 200000.0000, 11850000.0000, 200000.0000, 11700000.0000, 50000.0000, 'Imported from inventory_logs #117', '2026-05-24 00:30:34', '2026-06-15 18:18:52'),
(34, 9, 1, NULL, 1, 'sale', '128', 'inventory_log', 108, 0, 30, 78, 150000.0000, 200000.0000, 16200000.0000, 6000000.0000, 11700000.0000, 1500000.0000, 'Imported from inventory_logs #128', '2026-05-24 12:13:07', '2026-06-15 18:18:52'),
(35, 9, 1, NULL, 1, 'adjustment', '185', 'inventory_log', 28, 50, 0, 78, 150000.0000, 200000.0000, 4200000.0000, 0.0000, 11700000.0000, 0.0000, 'Imported from inventory_logs #185', '2026-06-02 02:53:09', '2026-06-15 18:18:52'),
(36, 9, 1, NULL, 4, 'sale', '208', 'inventory_log', 79, 0, 1, 78, 150000.0000, 200000.0000, 11850000.0000, 200000.0000, 11700000.0000, 50000.0000, 'Imported from inventory_logs #208', '2026-06-03 04:57:52', '2026-06-15 18:18:52'),
(37, 9, 1, NULL, 4, 'sale', '228', 'inventory_log', 87, 0, 9, 78, 150000.0000, 200000.0000, 13050000.0000, 1800000.0000, 11700000.0000, 450000.0000, 'Imported from inventory_logs #228', '2026-06-03 07:23:48', '2026-06-15 18:18:52'),
(38, 9, 1, NULL, 4, 'sale', '241', 'inventory_log', 79, 0, 1, 78, 150000.0000, 200000.0000, 11850000.0000, 200000.0000, 11700000.0000, 50000.0000, 'Imported from inventory_logs #241', '2026-06-03 15:39:15', '2026-06-15 18:18:52'),
(39, 9, 1, NULL, 1, 'sale', '250', 'inventory_log', 80, 0, 2, 78, 150000.0000, 200000.0000, 12000000.0000, 400000.0000, 11700000.0000, 100000.0000, 'Imported from inventory_logs #250', '2026-06-04 16:20:34', '2026-06-15 18:18:52'),
(40, 9, 1, NULL, 4, 'sale', '260', 'inventory_log', 82, 0, 4, 78, 150000.0000, 200000.0000, 12300000.0000, 800000.0000, 11700000.0000, 200000.0000, 'Imported from inventory_logs #260', '2026-06-04 21:49:43', '2026-06-15 18:18:52'),
(41, 9, 1, NULL, 4, 'sale', '267', 'inventory_log', 81, 0, 3, 78, 150000.0000, 200000.0000, 12150000.0000, 600000.0000, 11700000.0000, 150000.0000, 'Imported from inventory_logs #267', '2026-06-05 02:30:39', '2026-06-15 18:18:52'),
(42, 9, 1, NULL, 1, 'adjustment', '272', 'inventory_log', 58, 20, 0, 78, 150000.0000, 200000.0000, 8700000.0000, 0.0000, 11700000.0000, 0.0000, 'Imported from inventory_logs #272', '2026-06-05 02:50:17', '2026-06-15 18:18:52'),
(43, 9, 1, NULL, 1, 'sale', '273', 'inventory_log', 123, 0, 45, 78, 150000.0000, 200000.0000, 18450000.0000, 9000000.0000, 11700000.0000, 2250000.0000, 'Imported from inventory_logs #273', '2026-06-05 02:51:28', '2026-06-15 18:18:52'),
(44, 9, 1, NULL, 2, 'adjustment', '274', 'inventory_log', 0, 100, 0, 78, 150000.0000, 200000.0000, 0.0000, 0.0000, 11700000.0000, 0.0000, 'Imported from inventory_logs #274', '2026-06-05 03:01:19', '2026-06-15 18:18:52'),
(45, 9, 1, NULL, 2, 'sale', '275', 'inventory_log', 104, 0, 26, 78, 150000.0000, 200000.0000, 15600000.0000, 5200000.0000, 11700000.0000, 1300000.0000, 'Imported from inventory_logs #275', '2026-06-05 03:08:25', '2026-06-15 18:18:52'),
(46, 9, 1, NULL, 4, 'sale', '311', 'inventory_log', 79, 0, 1, 78, 150000.0000, 200000.0000, 11850000.0000, 200000.0000, 11700000.0000, 50000.0000, 'Imported from inventory_logs #311', '2026-06-09 01:35:26', '2026-06-15 18:18:52'),
(47, 10, 1, NULL, 1, 'adjustment', '17', 'inventory_log', 0, 100, 0, 87, 150000.0000, 18000.0000, 0.0000, 0.0000, 13050000.0000, 0.0000, 'Imported from inventory_logs #17', '2026-05-13 13:26:27', '2026-06-15 18:18:52'),
(48, 10, 1, NULL, 1, 'sale', '24', 'inventory_log', 93, 0, 6, 87, 150000.0000, 18000.0000, 13950000.0000, 108000.0000, 13050000.0000, -792000.0000, 'Imported from inventory_logs #24', '2026-05-13 13:49:50', '2026-06-15 18:18:52'),
(49, 10, 1, NULL, 1, 'sale', '27', 'inventory_log', 91, 0, 4, 87, 150000.0000, 18000.0000, 13650000.0000, 72000.0000, 13050000.0000, -528000.0000, 'Imported from inventory_logs #27', '2026-05-13 13:52:59', '2026-06-15 18:18:52'),
(50, 10, 1, NULL, 1, 'sale', '32', 'inventory_log', 89, 0, 2, 87, 150000.0000, 18000.0000, 13350000.0000, 36000.0000, 13050000.0000, -264000.0000, 'Imported from inventory_logs #32', '2026-05-17 16:44:33', '2026-06-15 18:18:52'),
(51, 10, 1, NULL, 1, 'sale', '44', 'inventory_log', 88, 0, 1, 87, 150000.0000, 18000.0000, 13200000.0000, 18000.0000, 13050000.0000, -132000.0000, 'Imported from inventory_logs #44', '2026-05-18 12:43:42', '2026-06-15 18:18:52'),
(52, 10, 1, NULL, 4, 'sale', '51', 'inventory_log', 94, 0, 7, 87, 150000.0000, 18000.0000, 14100000.0000, 126000.0000, 13050000.0000, -924000.0000, 'Imported from inventory_logs #51', '2026-05-22 19:51:56', '2026-06-15 18:18:52'),
(53, 10, 1, NULL, 4, 'sale', '56', 'inventory_log', 88, 0, 1, 87, 150000.0000, 18000.0000, 13200000.0000, 18000.0000, 13050000.0000, -132000.0000, 'Imported from inventory_logs #56', '2026-05-22 20:24:02', '2026-06-15 18:18:52'),
(54, 10, 1, NULL, 4, 'sale', '62', 'inventory_log', 96, 0, 9, 87, 150000.0000, 18000.0000, 14400000.0000, 162000.0000, 13050000.0000, -1188000.0000, 'Imported from inventory_logs #62', '2026-05-22 20:25:48', '2026-06-15 18:18:52'),
(55, 10, 1, NULL, 2, 'adjustment', '72', 'inventory_log', 77, 10, 0, 87, 150000.0000, 18000.0000, 11550000.0000, 0.0000, 13050000.0000, 0.0000, 'Imported from inventory_logs #72', '2026-05-22 22:33:12', '2026-06-15 18:18:52'),
(56, 10, 1, NULL, 4, 'sale', '87', 'inventory_log', 117, 0, 30, 87, 150000.0000, 18000.0000, 17550000.0000, 540000.0000, 13050000.0000, -3960000.0000, 'Imported from inventory_logs #87', '2026-05-23 03:00:10', '2026-06-15 18:18:52'),
(57, 10, 1, NULL, 4, 'sale', '88', 'inventory_log', 88, 0, 1, 87, 150000.0000, 18000.0000, 13200000.0000, 18000.0000, 13050000.0000, -132000.0000, 'Imported from inventory_logs #88', '2026-05-23 05:12:38', '2026-06-15 18:18:52'),
(58, 10, 1, NULL, 4, 'sale', '90', 'inventory_log', 88, 0, 1, 87, 150000.0000, 18000.0000, 13200000.0000, 18000.0000, 13050000.0000, -132000.0000, 'Imported from inventory_logs #90', '2026-05-23 05:13:00', '2026-06-15 18:18:52'),
(59, 10, 1, NULL, 4, 'sale', '93', 'inventory_log', 88, 0, 1, 87, 150000.0000, 18000.0000, 13200000.0000, 18000.0000, 13050000.0000, -132000.0000, 'Imported from inventory_logs #93', '2026-05-23 05:13:39', '2026-06-15 18:18:52'),
(60, 10, 1, NULL, 4, 'sale', '122', 'inventory_log', 88, 0, 1, 87, 150000.0000, 18000.0000, 13200000.0000, 18000.0000, 13050000.0000, -132000.0000, 'Imported from inventory_logs #122', '2026-05-24 00:30:34', '2026-06-15 18:18:52'),
(61, 10, 6, NULL, 7, 'sale', '125', 'inventory_log', 133, 0, 46, 87, 150000.0000, 18000.0000, 19950000.0000, 828000.0000, 13050000.0000, -6072000.0000, 'Imported from inventory_logs #125', '2026-05-24 03:15:01', '2026-06-15 18:18:52'),
(62, 10, 6, NULL, 7, 'sale', '126', 'inventory_log', 94, 0, 7, 87, 150000.0000, 18000.0000, 14100000.0000, 126000.0000, 13050000.0000, -924000.0000, 'Imported from inventory_logs #126', '2026-05-24 03:15:05', '2026-06-15 18:18:52'),
(63, 10, 1, NULL, 1, 'adjustment', '184', 'inventory_log', 30, 57, 0, 87, 150000.0000, 18000.0000, 4500000.0000, 0.0000, 13050000.0000, 0.0000, 'Imported from inventory_logs #184', '2026-06-02 02:52:57', '2026-06-15 18:18:52'),
(64, 10, 1, NULL, 4, 'sale', '193', 'inventory_log', 88, 0, 1, 87, 150000.0000, 18000.0000, 13200000.0000, 18000.0000, 13050000.0000, -132000.0000, 'Imported from inventory_logs #193', '2026-06-03 02:09:01', '2026-06-15 18:18:52'),
(65, 10, 1, NULL, 4, 'sale', '214', 'inventory_log', 88, 0, 1, 87, 150000.0000, 18000.0000, 13200000.0000, 18000.0000, 13050000.0000, -132000.0000, 'Imported from inventory_logs #214', '2026-06-03 05:51:45', '2026-06-15 18:18:52'),
(66, 10, 1, NULL, 4, 'sale', '222', 'inventory_log', 88, 0, 1, 87, 150000.0000, 18000.0000, 13200000.0000, 18000.0000, 13050000.0000, -132000.0000, 'Imported from inventory_logs #222', '2026-06-03 07:23:48', '2026-06-15 18:18:52'),
(67, 10, 1, NULL, 4, 'sale', '232', 'inventory_log', 90, 0, 3, 87, 150000.0000, 18000.0000, 13500000.0000, 54000.0000, 13050000.0000, -396000.0000, 'Imported from inventory_logs #232', '2026-06-03 08:06:50', '2026-06-15 18:18:52'),
(68, 10, 1, NULL, 4, 'sale', '238', 'inventory_log', 88, 0, 1, 87, 150000.0000, 18000.0000, 13200000.0000, 18000.0000, 13050000.0000, -132000.0000, 'Imported from inventory_logs #238', '2026-06-03 15:39:15', '2026-06-15 18:18:52'),
(69, 10, 1, NULL, 4, 'sale', '245', 'inventory_log', 90, 0, 3, 87, 150000.0000, 18000.0000, 13500000.0000, 54000.0000, 13050000.0000, -396000.0000, 'Imported from inventory_logs #245', '2026-06-04 15:22:26', '2026-06-15 18:18:52'),
(70, 10, 1, NULL, 4, 'sale', '265', 'inventory_log', 92, 0, 5, 87, 150000.0000, 18000.0000, 13800000.0000, 90000.0000, 13050000.0000, -660000.0000, 'Imported from inventory_logs #265', '2026-06-05 02:29:02', '2026-06-15 18:18:52'),
(71, 10, 1, NULL, 2, 'sale', '277', 'inventory_log', 122, 0, 35, 87, 150000.0000, 18000.0000, 18300000.0000, 630000.0000, 13050000.0000, -4620000.0000, 'Imported from inventory_logs #277', '2026-06-05 03:08:25', '2026-06-15 18:18:52'),
(72, 10, 1, NULL, 1, 'adjustment', '278', 'inventory_log', 0, 90, 0, 87, 150000.0000, 18000.0000, 0.0000, 0.0000, 13050000.0000, 0.0000, 'Imported from inventory_logs #278', '2026-06-05 03:27:40', '2026-06-15 18:18:52'),
(73, 10, 1, NULL, 4, 'sale', '421', 'inventory_log', 88, 0, 1, 87, 150000.0000, 18000.0000, 13200000.0000, 18000.0000, 13050000.0000, -132000.0000, 'Imported from inventory_logs #421', '2026-06-14 19:15:27', '2026-06-15 18:18:52'),
(74, 10, 1, NULL, 4, 'sale', '424', 'inventory_log', 88, 0, 1, 87, 150000.0000, 18000.0000, 13200000.0000, 18000.0000, 13050000.0000, -132000.0000, 'Imported from inventory_logs #424', '2026-06-15 17:01:23', '2026-06-15 18:18:52'),
(75, 10, 1, NULL, 4, 'sale', '431', 'inventory_log', 88, 0, 1, 87, 150000.0000, 18000.0000, 13200000.0000, 18000.0000, 13050000.0000, -132000.0000, 'Imported from inventory_logs #431', '2026-06-15 17:41:51', '2026-06-15 18:18:52'),
(76, 11, 1, NULL, 1, 'adjustment', '18', 'inventory_log', 0, 100, 0, 20, 100000.0000, 140000.0000, 0.0000, 0.0000, 2000000.0000, 0.0000, 'Imported from inventory_logs #18', '2026-05-13 13:27:59', '2026-06-15 18:18:52'),
(77, 11, 1, NULL, 1, 'sale', '23', 'inventory_log', 23, 0, 3, 20, 100000.0000, 140000.0000, 2300000.0000, 420000.0000, 2000000.0000, 120000.0000, 'Imported from inventory_logs #23', '2026-05-13 13:49:50', '2026-06-15 18:18:52'),
(78, 11, 1, NULL, 1, 'sale', '31', 'inventory_log', 22, 0, 2, 20, 100000.0000, 140000.0000, 2200000.0000, 280000.0000, 2000000.0000, 80000.0000, 'Imported from inventory_logs #31', '2026-05-17 16:44:33', '2026-06-15 18:18:52'),
(79, 11, 1, NULL, 1, 'sale', '33', 'inventory_log', 22, 0, 2, 20, 100000.0000, 140000.0000, 2200000.0000, 280000.0000, 2000000.0000, 80000.0000, 'Imported from inventory_logs #33', '2026-05-17 17:02:27', '2026-06-15 18:18:52'),
(80, 11, 1, NULL, 1, 'sale', '45', 'inventory_log', 22, 0, 2, 20, 100000.0000, 140000.0000, 2200000.0000, 280000.0000, 2000000.0000, 80000.0000, 'Imported from inventory_logs #45', '2026-05-18 17:28:17', '2026-06-15 18:18:52'),
(81, 11, 1, NULL, 4, 'sale', '49', 'inventory_log', 29, 0, 9, 20, 100000.0000, 140000.0000, 2900000.0000, 1260000.0000, 2000000.0000, 360000.0000, 'Imported from inventory_logs #49', '2026-05-22 19:49:05', '2026-06-15 18:18:52'),
(82, 11, 1, NULL, 4, 'sale', '50', 'inventory_log', 36, 0, 16, 20, 100000.0000, 140000.0000, 3600000.0000, 2240000.0000, 2000000.0000, 640000.0000, 'Imported from inventory_logs #50', '2026-05-22 19:51:56', '2026-06-15 18:18:52'),
(83, 11, 1, NULL, 4, 'sale', '53', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #53', '2026-05-22 19:53:10', '2026-06-15 18:18:52'),
(84, 11, 1, NULL, 4, 'sale', '55', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #55', '2026-05-22 20:24:02', '2026-06-15 18:18:52'),
(85, 11, 1, NULL, 4, 'sale', '61', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #61', '2026-05-22 20:25:48', '2026-06-15 18:18:52'),
(86, 11, 1, NULL, 4, 'sale', '63', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #63', '2026-05-22 20:37:04', '2026-06-15 18:18:52'),
(87, 11, 1, NULL, 4, 'sale', '65', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #65', '2026-05-22 20:38:23', '2026-06-15 18:18:52'),
(88, 11, 1, NULL, 4, 'sale', '66', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #66', '2026-05-22 20:57:29', '2026-06-15 18:18:52'),
(89, 11, 1, NULL, 4, 'sale', '89', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #89', '2026-05-23 05:12:38', '2026-06-15 18:18:52'),
(90, 11, 1, NULL, 4, 'sale', '91', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #91', '2026-05-23 05:13:00', '2026-06-15 18:18:52'),
(91, 11, 1, NULL, 4, 'sale', '92', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #92', '2026-05-23 05:13:39', '2026-06-15 18:18:52'),
(92, 11, 1, NULL, 4, 'sale', '110', 'inventory_log', 27, 0, 7, 20, 100000.0000, 140000.0000, 2700000.0000, 980000.0000, 2000000.0000, 280000.0000, 'Imported from inventory_logs #110', '2026-05-24 00:30:34', '2026-06-15 18:18:52'),
(93, 11, 1, NULL, 4, 'sale', '114', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #114', '2026-05-24 00:30:34', '2026-06-15 18:18:52'),
(94, 11, 1, NULL, 4, 'sale', '121', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #121', '2026-05-24 00:30:34', '2026-06-15 18:18:52'),
(95, 11, 1, NULL, 4, 'sale', '132', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #132', '2026-05-24 13:04:05', '2026-06-15 18:18:52'),
(96, 11, 1, NULL, 4, 'sale', '133', 'inventory_log', 22, 0, 2, 20, 100000.0000, 140000.0000, 2200000.0000, 280000.0000, 2000000.0000, 80000.0000, 'Imported from inventory_logs #133', '2026-05-24 13:04:40', '2026-06-15 18:18:52'),
(97, 11, 1, NULL, 4, 'sale', '138', 'inventory_log', 24, 0, 4, 20, 100000.0000, 140000.0000, 2400000.0000, 560000.0000, 2000000.0000, 160000.0000, 'Imported from inventory_logs #138', '2026-05-25 22:23:42', '2026-06-15 18:18:52'),
(98, 11, 1, NULL, 3, 'sale', '142', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #142', '2026-05-25 23:07:47', '2026-06-15 18:18:52'),
(99, 11, 1, NULL, 4, 'sale', '143', 'inventory_log', 30, 0, 10, 20, 100000.0000, 140000.0000, 3000000.0000, 1400000.0000, 2000000.0000, 400000.0000, 'Imported from inventory_logs #143', '2026-05-25 23:08:32', '2026-06-15 18:18:52'),
(100, 11, 1, NULL, 2, 'sale', '144', 'inventory_log', 23, 0, 3, 20, 100000.0000, 140000.0000, 2300000.0000, 420000.0000, 2000000.0000, 120000.0000, 'Imported from inventory_logs #144', '2026-05-26 01:03:44', '2026-06-15 18:18:52'),
(101, 11, 1, NULL, 4, 'sale', '145', 'inventory_log', 27, 0, 7, 20, 100000.0000, 140000.0000, 2700000.0000, 980000.0000, 2000000.0000, 280000.0000, 'Imported from inventory_logs #145', '2026-05-26 01:14:30', '2026-06-15 18:18:52'),
(102, 11, 1, NULL, 4, 'sale', '146', 'inventory_log', 23, 0, 3, 20, 100000.0000, 140000.0000, 2300000.0000, 420000.0000, 2000000.0000, 120000.0000, 'Imported from inventory_logs #146', '2026-05-26 01:18:12', '2026-06-15 18:18:52'),
(103, 11, 1, NULL, 4, 'sale', '148', 'inventory_log', 25, 0, 5, 20, 100000.0000, 140000.0000, 2500000.0000, 700000.0000, 2000000.0000, 200000.0000, 'Imported from inventory_logs #148', '2026-05-26 01:19:07', '2026-06-15 18:18:52'),
(104, 11, 1, NULL, 4, 'sale', '149', 'inventory_log', 25, 0, 5, 20, 100000.0000, 140000.0000, 2500000.0000, 700000.0000, 2000000.0000, 200000.0000, 'Imported from inventory_logs #149', '2026-05-26 01:19:44', '2026-06-15 18:18:52'),
(105, 11, 1, NULL, 4, 'sale', '150', 'inventory_log', 27, 0, 7, 20, 100000.0000, 140000.0000, 2700000.0000, 980000.0000, 2000000.0000, 280000.0000, 'Imported from inventory_logs #150', '2026-05-26 01:20:05', '2026-06-15 18:18:52'),
(106, 11, 1, NULL, 1, 'adjustment', '183', 'inventory_log', 0, 50, 0, 20, 100000.0000, 140000.0000, 0.0000, 0.0000, 2000000.0000, 0.0000, 'Imported from inventory_logs #183', '2026-06-02 02:52:44', '2026-06-15 18:18:52'),
(107, 11, 1, NULL, 4, 'sale', '194', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #194', '2026-06-03 02:09:13', '2026-06-15 18:18:52'),
(108, 11, 1, NULL, 4, 'sale', '197', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #197', '2026-06-03 02:09:26', '2026-06-15 18:18:52'),
(109, 11, 1, NULL, 4, 'sale', '202', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #202', '2026-06-03 02:09:50', '2026-06-15 18:18:52'),
(110, 11, 1, NULL, 4, 'sale', '205', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #205', '2026-06-03 04:57:52', '2026-06-15 18:18:52'),
(111, 11, 1, NULL, 4, 'sale', '211', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #211', '2026-06-03 05:28:23', '2026-06-15 18:18:52'),
(112, 11, 1, NULL, 4, 'sale', '215', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #215', '2026-06-03 05:51:45', '2026-06-15 18:18:52'),
(113, 11, 1, NULL, 4, 'sale', '219', 'inventory_log', 24, 0, 4, 20, 100000.0000, 140000.0000, 2400000.0000, 560000.0000, 2000000.0000, 160000.0000, 'Imported from inventory_logs #219', '2026-06-03 06:08:46', '2026-06-15 18:18:52'),
(114, 11, 1, NULL, 4, 'sale', '223', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #223', '2026-06-03 07:23:48', '2026-06-15 18:18:52'),
(115, 11, 1, NULL, 4, 'sale', '234', 'inventory_log', 24, 0, 4, 20, 100000.0000, 140000.0000, 2400000.0000, 560000.0000, 2000000.0000, 160000.0000, 'Imported from inventory_logs #234', '2026-06-03 08:08:06', '2026-06-15 18:18:52'),
(116, 11, 1, NULL, 4, 'sale', '237', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #237', '2026-06-03 15:39:15', '2026-06-15 18:18:52'),
(117, 11, 1, NULL, 4, 'sale', '242', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #242', '2026-06-04 15:22:26', '2026-06-15 18:18:52'),
(118, 11, 1, NULL, 4, 'sale', '252', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #252', '2026-06-04 21:40:43', '2026-06-15 18:18:52'),
(119, 11, 1, NULL, 4, 'sale', '257', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #257', '2026-06-04 21:48:02', '2026-06-15 18:18:52'),
(120, 11, 1, NULL, 4, 'sale', '259', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #259', '2026-06-04 21:49:03', '2026-06-15 18:18:52'),
(121, 11, 1, NULL, 2, 'sale', '276', 'inventory_log', 25, 0, 5, 20, 100000.0000, 140000.0000, 2500000.0000, 700000.0000, 2000000.0000, 200000.0000, 'Imported from inventory_logs #276', '2026-06-05 03:08:25', '2026-06-15 18:18:52'),
(122, 11, 1, NULL, 1, 'sale', '301', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #301', '2026-06-06 16:49:49', '2026-06-15 18:18:52'),
(123, 11, 1, NULL, 4, 'sale', '420', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #420', '2026-06-14 19:15:27', '2026-06-15 18:18:52'),
(124, 11, 1, NULL, 4, 'sale', '425', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #425', '2026-06-15 17:01:23', '2026-06-15 18:18:52'),
(125, 11, 1, NULL, 4, 'sale', '428', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #428', '2026-06-15 17:01:59', '2026-06-15 18:18:52'),
(126, 11, 1, NULL, 4, 'sale', '432', 'inventory_log', 21, 0, 1, 20, 100000.0000, 140000.0000, 2100000.0000, 140000.0000, 2000000.0000, 40000.0000, 'Imported from inventory_logs #432', '2026-06-15 17:41:51', '2026-06-15 18:18:52'),
(127, 12, 1, NULL, 1, 'adjustment', '19', 'inventory_log', 0, 1000, 0, 960, 3500.0000, 5000.0000, 0.0000, 0.0000, 3360000.0000, 0.0000, 'Imported from inventory_logs #19', '2026-05-13 13:30:58', '2026-06-15 18:18:52'),
(128, 12, 1, NULL, 1, 'sale', '20', 'inventory_log', 963, 0, 3, 960, 3500.0000, 5000.0000, 3370500.0000, 15000.0000, 3360000.0000, 4500.0000, 'Imported from inventory_logs #20', '2026-05-13 13:46:55', '2026-06-15 18:18:52'),
(129, 12, 1, NULL, 1, 'sale', '22', 'inventory_log', 963, 0, 3, 960, 3500.0000, 5000.0000, 3370500.0000, 15000.0000, 3360000.0000, 4500.0000, 'Imported from inventory_logs #22', '2026-05-13 13:49:50', '2026-06-15 18:18:52'),
(130, 12, 1, NULL, 1, 'sale', '28', 'inventory_log', 963, 0, 3, 960, 3500.0000, 5000.0000, 3370500.0000, 15000.0000, 3360000.0000, 4500.0000, 'Imported from inventory_logs #28', '2026-05-17 15:38:38', '2026-06-15 18:18:52'),
(131, 12, 1, NULL, 1, 'sale', '29', 'inventory_log', 962, 0, 2, 960, 3500.0000, 5000.0000, 3367000.0000, 10000.0000, 3360000.0000, 3000.0000, 'Imported from inventory_logs #29', '2026-05-17 16:29:02', '2026-06-15 18:18:52'),
(132, 12, 1, NULL, 1, 'sale', '35', 'inventory_log', 980, 0, 20, 960, 3500.0000, 5000.0000, 3430000.0000, 100000.0000, 3360000.0000, 30000.0000, 'Imported from inventory_logs #35', '2026-05-18 11:10:11', '2026-06-15 18:18:52'),
(133, 12, 1, NULL, 4, 'sale', '47', 'inventory_log', 961, 0, 1, 960, 3500.0000, 5000.0000, 3363500.0000, 5000.0000, 3360000.0000, 1500.0000, 'Imported from inventory_logs #47', '2026-05-22 19:48:38', '2026-06-15 18:18:52'),
(134, 12, 1, NULL, 4, 'sale', '52', 'inventory_log', 962, 0, 2, 960, 3500.0000, 5000.0000, 3367000.0000, 10000.0000, 3360000.0000, 3000.0000, 'Imported from inventory_logs #52', '2026-05-22 19:51:57', '2026-06-15 18:18:52'),
(135, 12, 1, NULL, 4, 'sale', '54', 'inventory_log', 961, 0, 1, 960, 3500.0000, 5000.0000, 3363500.0000, 5000.0000, 3360000.0000, 1500.0000, 'Imported from inventory_logs #54', '2026-05-22 19:53:10', '2026-06-15 18:18:52'),
(136, 12, 1, NULL, 4, 'sale', '57', 'inventory_log', 961, 0, 1, 960, 3500.0000, 5000.0000, 3363500.0000, 5000.0000, 3360000.0000, 1500.0000, 'Imported from inventory_logs #57', '2026-05-22 20:24:02', '2026-06-15 18:18:52'),
(137, 12, 1, NULL, 4, 'sale', '64', 'inventory_log', 963, 0, 3, 960, 3500.0000, 5000.0000, 3370500.0000, 15000.0000, 3360000.0000, 4500.0000, 'Imported from inventory_logs #64', '2026-05-22 20:37:04', '2026-06-15 18:18:52'),
(138, 12, 1, NULL, 4, 'sale', '68', 'inventory_log', 961, 0, 1, 960, 3500.0000, 5000.0000, 3363500.0000, 5000.0000, 3360000.0000, 1500.0000, 'Imported from inventory_logs #68', '2026-05-22 21:15:19', '2026-06-15 18:18:52'),
(139, 13, 1, NULL, 1, 'adjustment', '37', 'inventory_log', 0, 200, 0, 65, 1500.0000, 2000.0000, 0.0000, 0.0000, 97500.0000, 0.0000, 'Imported from inventory_logs #37', '2026-05-18 11:28:44', '2026-06-15 18:18:52'),
(140, 13, 1, NULL, 1, 'sale', '39', 'inventory_log', 70, 0, 5, 65, 1500.0000, 2000.0000, 105000.0000, 10000.0000, 97500.0000, 2500.0000, 'Imported from inventory_logs #39', '2026-05-18 12:16:18', '2026-06-15 18:18:52'),
(141, 13, 1, NULL, 1, 'sale', '40', 'inventory_log', 70, 0, 5, 65, 1500.0000, 2000.0000, 105000.0000, 10000.0000, 97500.0000, 2500.0000, 'Imported from inventory_logs #40', '2026-05-18 12:16:55', '2026-06-15 18:18:52'),
(142, 13, 1, NULL, 1, 'sale', '41', 'inventory_log', 70, 0, 5, 65, 1500.0000, 2000.0000, 105000.0000, 10000.0000, 97500.0000, 2500.0000, 'Imported from inventory_logs #41', '2026-05-18 12:17:12', '2026-06-15 18:18:52'),
(143, 13, 1, NULL, 4, 'sale', '58', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #58', '2026-05-22 20:24:02', '2026-06-15 18:18:52'),
(144, 13, 1, NULL, 4, 'sale', '70', 'inventory_log', 69, 0, 4, 65, 1500.0000, 2000.0000, 103500.0000, 8000.0000, 97500.0000, 2000.0000, 'Imported from inventory_logs #70', '2026-05-22 21:51:34', '2026-06-15 18:18:52'),
(145, 13, 1, NULL, 3, 'sale', '100', 'inventory_log', 95, 0, 30, 65, 1500.0000, 2000.0000, 142500.0000, 60000.0000, 97500.0000, 15000.0000, 'Imported from inventory_logs #100', '2026-05-23 15:43:38', '2026-06-15 18:18:52'),
(146, 13, 1, NULL, 4, 'sale', '106', 'inventory_log', 115, 0, 50, 65, 1500.0000, 2000.0000, 172500.0000, 100000.0000, 97500.0000, 25000.0000, 'Imported from inventory_logs #106', '2026-05-23 20:19:44', '2026-06-15 18:18:52'),
(147, 13, 1, NULL, 4, 'sale', '112', 'inventory_log', 67, 0, 2, 65, 1500.0000, 2000.0000, 100500.0000, 4000.0000, 97500.0000, 1000.0000, 'Imported from inventory_logs #112', '2026-05-24 00:30:34', '2026-06-15 18:18:52'),
(148, 13, 1, NULL, 4, 'sale', '113', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #113', '2026-05-24 00:30:34', '2026-06-15 18:18:52'),
(149, 13, 1, NULL, 4, 'sale', '116', 'inventory_log', 68, 0, 3, 65, 1500.0000, 2000.0000, 102000.0000, 6000.0000, 97500.0000, 1500.0000, 'Imported from inventory_logs #116', '2026-05-24 00:30:34', '2026-06-15 18:18:52'),
(150, 13, 1, NULL, 4, 'sale', '123', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #123', '2026-05-24 00:30:34', '2026-06-15 18:18:52'),
(151, 13, 6, NULL, 7, 'sale', '135', 'inventory_log', 68, 0, 3, 65, 1500.0000, 2000.0000, 102000.0000, 6000.0000, 97500.0000, 1500.0000, 'Imported from inventory_logs #135', '2026-05-24 17:26:11', '2026-06-15 18:18:52'),
(152, 13, 1, NULL, 4, 'sale', '137', 'inventory_log', 145, 0, 80, 65, 1500.0000, 2000.0000, 217500.0000, 160000.0000, 97500.0000, 40000.0000, 'Imported from inventory_logs #137', '2026-05-25 22:23:42', '2026-06-15 18:18:52'),
(153, 13, 1, NULL, 2, 'adjustment', '156', 'inventory_log', 0, 100, 0, 65, 1500.0000, 2000.0000, 0.0000, 0.0000, 97500.0000, 0.0000, 'Imported from inventory_logs #156', '2026-05-26 01:53:59', '2026-06-15 18:18:52'),
(154, 13, 1, NULL, 4, 'sale', '157', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #157', '2026-05-26 02:17:45', '2026-06-15 18:18:52'),
(155, 13, 1, NULL, 4, 'sale', '158', 'inventory_log', 67, 0, 2, 65, 1500.0000, 2000.0000, 100500.0000, 4000.0000, 97500.0000, 1000.0000, 'Imported from inventory_logs #158', '2026-05-26 02:21:54', '2026-06-15 18:18:52'),
(156, 13, 1, NULL, 4, 'sale', '161', 'inventory_log', 67, 0, 2, 65, 1500.0000, 2000.0000, 100500.0000, 4000.0000, 97500.0000, 1000.0000, 'Imported from inventory_logs #161', '2026-05-26 20:42:54', '2026-06-15 18:18:52'),
(157, 13, 1, NULL, 4, 'sale', '162', 'inventory_log', 67, 0, 2, 65, 1500.0000, 2000.0000, 100500.0000, 4000.0000, 97500.0000, 1000.0000, 'Imported from inventory_logs #162', '2026-05-26 20:43:09', '2026-06-15 18:18:52'),
(158, 13, 1, NULL, 4, 'sale', '163', 'inventory_log', 68, 0, 3, 65, 1500.0000, 2000.0000, 102000.0000, 6000.0000, 97500.0000, 1500.0000, 'Imported from inventory_logs #163', '2026-05-26 20:58:15', '2026-06-15 18:18:52'),
(159, 13, 1, NULL, 4, 'sale', '172', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #172', '2026-06-02 02:25:21', '2026-06-15 18:18:52'),
(160, 13, 1, NULL, 4, 'sale', '173', 'inventory_log', 67, 0, 2, 65, 1500.0000, 2000.0000, 100500.0000, 4000.0000, 97500.0000, 1000.0000, 'Imported from inventory_logs #173', '2026-06-02 02:25:21', '2026-06-15 18:18:52'),
(161, 13, 1, NULL, 3, 'sale', '178', 'inventory_log', 69, 0, 4, 65, 1500.0000, 2000.0000, 103500.0000, 8000.0000, 97500.0000, 2000.0000, 'Imported from inventory_logs #178', '2026-06-02 02:27:22', '2026-06-15 18:18:52'),
(162, 13, 1, NULL, 3, 'adjustment', '180', 'inventory_log', 64, 1, 0, 65, 1500.0000, 2000.0000, 96000.0000, 0.0000, 97500.0000, 0.0000, 'Imported from inventory_logs #180', '2026-06-02 02:28:45', '2026-06-15 18:18:52'),
(163, 13, 1, NULL, 3, 'adjustment', '181', 'inventory_log', 64, 1, 0, 65, 1500.0000, 2000.0000, 96000.0000, 0.0000, 97500.0000, 0.0000, 'Imported from inventory_logs #181', '2026-06-02 02:31:25', '2026-06-15 18:18:52'),
(164, 13, 1, NULL, 3, 'adjustment', '182', 'inventory_log', 64, 1, 0, 65, 1500.0000, 2000.0000, 96000.0000, 0.0000, 97500.0000, 0.0000, 'Imported from inventory_logs #182', '2026-06-02 02:33:00', '2026-06-15 18:18:52'),
(165, 13, 1, NULL, 4, 'sale', '186', 'inventory_log', 68, 0, 3, 65, 1500.0000, 2000.0000, 102000.0000, 6000.0000, 97500.0000, 1500.0000, 'Imported from inventory_logs #186', '2026-06-03 02:07:12', '2026-06-15 18:18:52'),
(166, 13, 1, NULL, 4, 'sale', '188', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #188', '2026-06-03 02:07:12', '2026-06-15 18:18:52'),
(167, 13, 1, NULL, 4, 'sale', '192', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #192', '2026-06-03 02:07:12', '2026-06-15 18:18:52'),
(168, 13, 1, NULL, 4, 'sale', '195', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #195', '2026-06-03 02:09:13', '2026-06-15 18:18:52'),
(169, 13, 1, NULL, 4, 'sale', '198', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #198', '2026-06-03 02:09:26', '2026-06-15 18:18:52'),
(170, 13, 1, NULL, 4, 'sale', '201', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #201', '2026-06-03 02:09:50', '2026-06-15 18:18:52'),
(171, 13, 1, NULL, 4, 'sale', '206', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #206', '2026-06-03 04:57:52', '2026-06-15 18:18:52'),
(172, 13, 1, NULL, 4, 'sale', '216', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #216', '2026-06-03 05:51:45', '2026-06-15 18:18:52'),
(173, 13, 1, NULL, 4, 'sale', '224', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #224', '2026-06-03 07:23:48', '2026-06-15 18:18:52'),
(174, 13, 1, NULL, 4, 'sale', '236', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #236', '2026-06-03 15:39:15', '2026-06-15 18:18:52'),
(175, 13, 1, NULL, 1, 'sale', '249', 'inventory_log', 69, 0, 4, 65, 1500.0000, 2000.0000, 103500.0000, 8000.0000, 97500.0000, 2000.0000, 'Imported from inventory_logs #249', '2026-06-04 16:20:34', '2026-06-15 18:18:52'),
(176, 13, 1, NULL, 4, 'sale', '254', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #254', '2026-06-04 21:40:43', '2026-06-15 18:18:52'),
(177, 13, 1, NULL, 1, 'sale', '261', 'inventory_log', 67, 0, 2, 65, 1500.0000, 2000.0000, 100500.0000, 4000.0000, 97500.0000, 1000.0000, 'Imported from inventory_logs #261', '2026-06-04 21:52:11', '2026-06-15 18:18:52'),
(178, 13, 1, NULL, 1, 'sale', '300', 'inventory_log', 75, 0, 10, 65, 1500.0000, 2000.0000, 112500.0000, 20000.0000, 97500.0000, 5000.0000, 'Imported from inventory_logs #300', '2026-06-06 16:49:49', '2026-06-15 18:18:52'),
(179, 13, 1, NULL, 4, 'sale', '422', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #422', '2026-06-14 19:15:27', '2026-06-15 18:18:52'),
(180, 13, 1, NULL, 4, 'sale', '426', 'inventory_log', 66, 0, 1, 65, 1500.0000, 2000.0000, 99000.0000, 2000.0000, 97500.0000, 500.0000, 'Imported from inventory_logs #426', '2026-06-15 17:01:23', '2026-06-15 18:18:52'),
(181, 14, 1, NULL, 1, 'adjustment', '38', 'inventory_log', 0, 1000, 0, 882, 500.0000, 400000.0000, 0.0000, 0.0000, 441000.0000, 0.0000, 'Imported from inventory_logs #38', '2026-05-18 11:40:50', '2026-06-15 18:18:52'),
(182, 14, 1, NULL, 1, 'sale', '43', 'inventory_log', 884, 0, 2, 882, 500.0000, 400000.0000, 442000.0000, 800000.0000, 441000.0000, 799000.0000, 'Imported from inventory_logs #43', '2026-05-18 12:43:42', '2026-06-15 18:18:52'),
(183, 14, 1, NULL, 4, 'sale', '60', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #60', '2026-05-22 20:24:02', '2026-06-15 18:18:52'),
(184, 14, 1, NULL, 4, 'sale', '67', 'inventory_log', 889, 0, 7, 882, 500.0000, 400000.0000, 444500.0000, 2800000.0000, 441000.0000, 2796500.0000, 'Imported from inventory_logs #67', '2026-05-22 21:12:20', '2026-06-15 18:18:52'),
(185, 14, 1, NULL, 4, 'sale', '81', 'inventory_log', 891, 0, 9, 882, 500.0000, 400000.0000, 445500.0000, 3600000.0000, 441000.0000, 3595500.0000, 'Imported from inventory_logs #81', '2026-05-23 00:51:36', '2026-06-15 18:18:52'),
(186, 14, 1, NULL, 1, 'adjustment', '85', 'inventory_log', 878, 4, 0, 882, 500.0000, 400000.0000, 439000.0000, 0.0000, 441000.0000, 0.0000, 'Imported from inventory_logs #85', '2026-05-23 02:23:52', '2026-06-15 18:18:52'),
(187, 14, 1, NULL, 1, 'adjustment', '86', 'inventory_log', 878, 4, 0, 882, 500.0000, 400000.0000, 439000.0000, 0.0000, 441000.0000, 0.0000, 'Imported from inventory_logs #86', '2026-05-23 02:26:32', '2026-06-15 18:18:52'),
(188, 14, 1, NULL, 4, 'sale', '96', 'inventory_log', 891, 0, 9, 882, 500.0000, 400000.0000, 445500.0000, 3600000.0000, 441000.0000, 3595500.0000, 'Imported from inventory_logs #96', '2026-05-23 05:42:40', '2026-06-15 18:18:52'),
(189, 14, 1, NULL, 3, 'sale', '101', 'inventory_log', 891, 0, 9, 882, 500.0000, 400000.0000, 445500.0000, 3600000.0000, 441000.0000, 3595500.0000, 'Imported from inventory_logs #101', '2026-05-23 15:43:38', '2026-06-15 18:18:52'),
(190, 14, 1, NULL, 4, 'sale', '105', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #105', '2026-05-23 20:19:44', '2026-06-15 18:18:52'),
(191, 14, 1, NULL, 4, 'sale', '115', 'inventory_log', 884, 0, 2, 882, 500.0000, 400000.0000, 442000.0000, 800000.0000, 441000.0000, 799000.0000, 'Imported from inventory_logs #115', '2026-05-24 00:30:34', '2026-06-15 18:18:52'),
(192, 14, 1, NULL, 4, 'sale', '120', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #120', '2026-05-24 00:30:34', '2026-06-15 18:18:52'),
(193, 14, 1, NULL, 4, 'sale', '134', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #134', '2026-05-24 13:05:33', '2026-06-15 18:18:52'),
(194, 14, 1, NULL, 2, 'sale', '151', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #151', '2026-05-26 01:24:49', '2026-06-15 18:18:52'),
(195, 14, 1, NULL, 2, 'sale', '152', 'inventory_log', 885, 0, 3, 882, 500.0000, 400000.0000, 442500.0000, 1200000.0000, 441000.0000, 1198500.0000, 'Imported from inventory_logs #152', '2026-05-26 01:37:26', '2026-06-15 18:18:52'),
(196, 14, 1, NULL, 2, 'sale', '153', 'inventory_log', 884, 0, 2, 882, 500.0000, 400000.0000, 442000.0000, 800000.0000, 441000.0000, 799000.0000, 'Imported from inventory_logs #153', '2026-05-26 01:37:46', '2026-06-15 18:18:52'),
(197, 14, 1, NULL, 4, 'sale', '155', 'inventory_log', 892, 0, 10, 882, 500.0000, 400000.0000, 446000.0000, 4000000.0000, 441000.0000, 3995000.0000, 'Imported from inventory_logs #155', '2026-05-26 01:46:03', '2026-06-15 18:18:52'),
(198, 14, 1, NULL, 4, 'sale', '159', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #159', '2026-05-26 02:21:54', '2026-06-15 18:18:52'),
(199, 14, 1, NULL, 4, 'sale', '164', 'inventory_log', 891, 0, 9, 882, 500.0000, 400000.0000, 445500.0000, 3600000.0000, 441000.0000, 3595500.0000, 'Imported from inventory_logs #164', '2026-05-26 20:59:26', '2026-06-15 18:18:52'),
(200, 14, 1, NULL, 1, 'sale', '167', 'inventory_log', 887, 0, 5, 882, 500.0000, 400000.0000, 443500.0000, 2000000.0000, 441000.0000, 1997500.0000, 'Imported from inventory_logs #167', '2026-05-29 16:05:31', '2026-06-15 18:18:52'),
(201, 14, 1, NULL, 4, 'sale', '171', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #171', '2026-06-02 02:25:21', '2026-06-15 18:18:52'),
(202, 14, 1, NULL, 4, 'sale', '174', 'inventory_log', 884, 0, 2, 882, 500.0000, 400000.0000, 442000.0000, 800000.0000, 441000.0000, 799000.0000, 'Imported from inventory_logs #174', '2026-06-02 02:25:21', '2026-06-15 18:18:52'),
(203, 14, 1, NULL, 3, 'sale', '179', 'inventory_log', 885, 0, 3, 882, 500.0000, 400000.0000, 442500.0000, 1200000.0000, 441000.0000, 1198500.0000, 'Imported from inventory_logs #179', '2026-06-02 02:27:22', '2026-06-15 18:18:52'),
(204, 14, 1, NULL, 4, 'sale', '187', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #187', '2026-06-03 02:07:12', '2026-06-15 18:18:52'),
(205, 14, 1, NULL, 4, 'sale', '189', 'inventory_log', 885, 0, 3, 882, 500.0000, 400000.0000, 442500.0000, 1200000.0000, 441000.0000, 1198500.0000, 'Imported from inventory_logs #189', '2026-06-03 02:07:12', '2026-06-15 18:18:52'),
(206, 14, 1, NULL, 4, 'sale', '196', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #196', '2026-06-03 02:09:13', '2026-06-15 18:18:52'),
(207, 14, 1, NULL, 4, 'sale', '199', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #199', '2026-06-03 02:09:26', '2026-06-15 18:18:52'),
(208, 14, 1, NULL, 4, 'sale', '200', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #200', '2026-06-03 02:09:50', '2026-06-15 18:18:52'),
(209, 14, 1, NULL, 4, 'sale', '203', 'inventory_log', 904, 0, 22, 882, 500.0000, 400000.0000, 452000.0000, 8800000.0000, 441000.0000, 8789000.0000, 'Imported from inventory_logs #203', '2026-06-03 02:11:24', '2026-06-15 18:18:52'),
(210, 14, 1, NULL, 1, 'sale', '204', 'inventory_log', 884, 0, 2, 882, 500.0000, 400000.0000, 442000.0000, 800000.0000, 441000.0000, 799000.0000, 'Imported from inventory_logs #204', '2026-06-03 04:22:03', '2026-06-15 18:18:52'),
(211, 14, 1, NULL, 4, 'sale', '207', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #207', '2026-06-03 04:57:52', '2026-06-15 18:18:52'),
(212, 14, 1, NULL, 4, 'sale', '213', 'inventory_log', 884, 0, 2, 882, 500.0000, 400000.0000, 442000.0000, 800000.0000, 441000.0000, 799000.0000, 'Imported from inventory_logs #213', '2026-06-03 05:44:41', '2026-06-15 18:18:52'),
(213, 14, 1, NULL, 4, 'sale', '217', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #217', '2026-06-03 05:51:45', '2026-06-15 18:18:52'),
(214, 14, 1, NULL, 4, 'sale', '225', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #225', '2026-06-03 07:23:48', '2026-06-15 18:18:52'),
(215, 14, 1, NULL, 4, 'sale', '231', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #231', '2026-06-03 08:06:50', '2026-06-15 18:18:52'),
(216, 14, 1, NULL, 4, 'sale', '233', 'inventory_log', 884, 0, 2, 882, 500.0000, 400000.0000, 442000.0000, 800000.0000, 441000.0000, 799000.0000, 'Imported from inventory_logs #233', '2026-06-03 08:07:14', '2026-06-15 18:18:52'),
(217, 14, 1, NULL, 4, 'sale', '239', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #239', '2026-06-03 15:39:15', '2026-06-15 18:18:52'),
(218, 14, 1, NULL, 4, 'sale', '243', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #243', '2026-06-04 15:22:26', '2026-06-15 18:18:52'),
(219, 14, 1, NULL, 4, 'sale', '253', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #253', '2026-06-04 21:40:43', '2026-06-15 18:18:52'),
(220, 14, 1, NULL, 4, 'sale', '256', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #256', '2026-06-04 21:47:44', '2026-06-15 18:18:52');
INSERT INTO `inventory_ledger` (`id`, `product_id`, `store_id`, `warehouse_id`, `user_id`, `movement_type`, `reference_id`, `reference_type`, `opening_stock`, `stock_in`, `stock_out`, `current_stock`, `purchase_price`, `selling_price`, `opening_stock_value`, `stock_out_value`, `current_stock_value`, `estimated_profit`, `notes`, `movement_date`, `created_at`) VALUES
(221, 14, 1, NULL, 4, 'sale', '258', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #258', '2026-06-04 21:49:03', '2026-06-15 18:18:52'),
(222, 14, 1, NULL, 1, 'sale', '299', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #299', '2026-06-06 16:49:49', '2026-06-15 18:18:52'),
(223, 14, 1, NULL, 4, 'sale', '423', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #423', '2026-06-14 19:15:27', '2026-06-15 18:18:52'),
(224, 14, 1, NULL, 4, 'sale', '427', 'inventory_log', 883, 0, 1, 882, 500.0000, 400000.0000, 441500.0000, 400000.0000, 441000.0000, 399500.0000, 'Imported from inventory_logs #427', '2026-06-15 17:01:23', '2026-06-15 18:18:52'),
(225, 15, 1, NULL, 1, 'adjustment', '46', 'inventory_log', 0, 40, 0, 0, 1500.0000, 5000.0000, 0.0000, 0.0000, 0.0000, 0.0000, 'Imported from inventory_logs #46', '2026-05-22 15:13:41', '2026-06-15 18:18:52'),
(226, 15, 1, NULL, 4, 'sale', '48', 'inventory_log', 2, 0, 2, 0, 1500.0000, 5000.0000, 3000.0000, 10000.0000, 0.0000, 7000.0000, 'Imported from inventory_logs #48', '2026-05-22 19:48:38', '2026-06-15 18:18:52'),
(227, 15, 1, NULL, 4, 'sale', '59', 'inventory_log', 1, 0, 1, 0, 1500.0000, 5000.0000, 1500.0000, 5000.0000, 0.0000, 3500.0000, 'Imported from inventory_logs #59', '2026-05-22 20:24:02', '2026-06-15 18:18:52'),
(228, 15, 1, NULL, 4, 'sale', '69', 'inventory_log', 3, 0, 3, 0, 1500.0000, 5000.0000, 4500.0000, 15000.0000, 0.0000, 10500.0000, 'Imported from inventory_logs #69', '2026-05-22 21:31:37', '2026-06-15 18:18:52'),
(229, 15, 1, NULL, 4, 'sale', '71', 'inventory_log', 4, 0, 4, 0, 1500.0000, 5000.0000, 6000.0000, 20000.0000, 0.0000, 14000.0000, 'Imported from inventory_logs #71', '2026-05-22 21:59:23', '2026-06-15 18:18:52'),
(230, 15, 1, NULL, 2, 'sale', '76', 'inventory_log', 1, 0, 1, 0, 1500.0000, 5000.0000, 1500.0000, 5000.0000, 0.0000, 3500.0000, 'Imported from inventory_logs #76', '2026-05-22 22:50:03', '2026-06-15 18:18:52'),
(231, 15, 1, NULL, 4, 'sale', '82', 'inventory_log', 1, 0, 1, 0, 1500.0000, 5000.0000, 1500.0000, 5000.0000, 0.0000, 3500.0000, 'Imported from inventory_logs #82', '2026-05-23 00:51:36', '2026-06-15 18:18:52'),
(232, 15, 1, NULL, 4, 'sale', '94', 'inventory_log', 2, 0, 2, 0, 1500.0000, 5000.0000, 3000.0000, 10000.0000, 0.0000, 7000.0000, 'Imported from inventory_logs #94', '2026-05-23 05:20:00', '2026-06-15 18:18:52'),
(233, 15, 1, NULL, 3, 'sale', '99', 'inventory_log', 8, 0, 8, 0, 1500.0000, 5000.0000, 12000.0000, 40000.0000, 0.0000, 28000.0000, 'Imported from inventory_logs #99', '2026-05-23 15:43:38', '2026-06-15 18:18:52'),
(234, 15, 1, NULL, 4, 'sale', '108', 'inventory_log', 1, 0, 1, 0, 1500.0000, 5000.0000, 1500.0000, 5000.0000, 0.0000, 3500.0000, 'Imported from inventory_logs #108', '2026-05-23 20:22:24', '2026-06-15 18:18:52'),
(235, 15, 1, NULL, 4, 'sale', '118', 'inventory_log', 4, 0, 4, 0, 1500.0000, 5000.0000, 6000.0000, 20000.0000, 0.0000, 14000.0000, 'Imported from inventory_logs #118', '2026-05-24 00:30:34', '2026-06-15 18:18:52'),
(236, 15, 1, NULL, 4, 'sale', '127', 'inventory_log', 13, 0, 13, 0, 1500.0000, 5000.0000, 19500.0000, 65000.0000, 0.0000, 45500.0000, 'Imported from inventory_logs #127', '2026-05-24 12:10:57', '2026-06-15 18:18:52'),
(237, 16, 1, NULL, 1, 'adjustment', '77', 'inventory_log', 0, 300, 0, 130, 500.0000, 4000.0000, 0.0000, 0.0000, 65000.0000, 0.0000, 'Imported from inventory_logs #77', '2026-05-23 00:07:29', '2026-06-15 18:18:52'),
(238, 16, 1, NULL, 4, 'sale', '78', 'inventory_log', 131, 0, 1, 130, 500.0000, 4000.0000, 65500.0000, 4000.0000, 65000.0000, 3500.0000, 'Imported from inventory_logs #78', '2026-05-23 00:10:28', '2026-06-15 18:18:52'),
(239, 16, 1, NULL, 4, 'sale', '80', 'inventory_log', 131, 0, 1, 130, 500.0000, 4000.0000, 65500.0000, 4000.0000, 65000.0000, 3500.0000, 'Imported from inventory_logs #80', '2026-05-23 00:11:29', '2026-06-15 18:18:52'),
(240, 16, 1, NULL, 3, 'sale', '98', 'inventory_log', 133, 0, 3, 130, 500.0000, 4000.0000, 66500.0000, 12000.0000, 65000.0000, 10500.0000, 'Imported from inventory_logs #98', '2026-05-23 15:43:38', '2026-06-15 18:18:52'),
(241, 16, 1, NULL, 4, 'sale', '111', 'inventory_log', 135, 0, 5, 130, 500.0000, 4000.0000, 67500.0000, 20000.0000, 65000.0000, 17500.0000, 'Imported from inventory_logs #111', '2026-05-24 00:30:34', '2026-06-15 18:18:52'),
(242, 16, 1, NULL, 4, 'sale', '119', 'inventory_log', 131, 0, 1, 130, 500.0000, 4000.0000, 65500.0000, 4000.0000, 65000.0000, 3500.0000, 'Imported from inventory_logs #119', '2026-05-24 00:30:34', '2026-06-15 18:18:52'),
(243, 16, 1, NULL, 4, 'sale', '124', 'inventory_log', 139, 0, 9, 130, 500.0000, 4000.0000, 69500.0000, 36000.0000, 65000.0000, 31500.0000, 'Imported from inventory_logs #124', '2026-05-24 01:09:55', '2026-06-15 18:18:52'),
(244, 16, 1, NULL, 4, 'sale', '139', 'inventory_log', 131, 0, 1, 130, 500.0000, 4000.0000, 65500.0000, 4000.0000, 65000.0000, 3500.0000, 'Imported from inventory_logs #139', '2026-05-25 22:23:42', '2026-06-15 18:18:52'),
(245, 16, 1, NULL, 4, 'sale', '140', 'inventory_log', 131, 0, 1, 130, 500.0000, 4000.0000, 65500.0000, 4000.0000, 65000.0000, 3500.0000, 'Imported from inventory_logs #140', '2026-05-25 22:25:33', '2026-06-15 18:18:52'),
(246, 16, 1, NULL, 4, 'sale', '141', 'inventory_log', 131, 0, 1, 130, 500.0000, 4000.0000, 65500.0000, 4000.0000, 65000.0000, 3500.0000, 'Imported from inventory_logs #141', '2026-05-25 22:25:59', '2026-06-15 18:18:52'),
(247, 16, 1, NULL, 4, 'sale', '147', 'inventory_log', 230, 0, 100, 130, 500.0000, 4000.0000, 115000.0000, 400000.0000, 65000.0000, 350000.0000, 'Imported from inventory_logs #147', '2026-05-26 01:18:42', '2026-06-15 18:18:52'),
(248, 16, 1, NULL, 2, 'sale', '154', 'inventory_log', 137, 0, 7, 130, 500.0000, 4000.0000, 68500.0000, 28000.0000, 65000.0000, 24500.0000, 'Imported from inventory_logs #154', '2026-05-26 01:44:30', '2026-06-15 18:18:52'),
(249, 16, 1, NULL, 4, 'sale', '168', 'inventory_log', 150, 0, 20, 130, 500.0000, 4000.0000, 75000.0000, 80000.0000, 65000.0000, 70000.0000, 'Imported from inventory_logs #168', '2026-06-02 02:25:21', '2026-06-15 18:18:52'),
(250, 16, 1, NULL, 4, 'sale', '177', 'inventory_log', 133, 0, 3, 130, 500.0000, 4000.0000, 66500.0000, 12000.0000, 65000.0000, 10500.0000, 'Imported from inventory_logs #177', '2026-06-02 02:25:21', '2026-06-15 18:18:52'),
(251, 16, 1, NULL, 4, 'sale', '191', 'inventory_log', 131, 0, 1, 130, 500.0000, 4000.0000, 65500.0000, 4000.0000, 65000.0000, 3500.0000, 'Imported from inventory_logs #191', '2026-06-03 02:07:12', '2026-06-15 18:18:52'),
(252, 16, 1, NULL, 4, 'sale', '210', 'inventory_log', 131, 0, 1, 130, 500.0000, 4000.0000, 65500.0000, 4000.0000, 65000.0000, 3500.0000, 'Imported from inventory_logs #210', '2026-06-03 04:57:52', '2026-06-15 18:18:52'),
(253, 16, 1, NULL, 4, 'sale', '227', 'inventory_log', 131, 0, 1, 130, 500.0000, 4000.0000, 65500.0000, 4000.0000, 65000.0000, 3500.0000, 'Imported from inventory_logs #227', '2026-06-03 07:23:48', '2026-06-15 18:18:52'),
(254, 16, 1, NULL, 4, 'sale', '255', 'inventory_log', 134, 0, 4, 130, 500.0000, 4000.0000, 67000.0000, 16000.0000, 65000.0000, 14000.0000, 'Imported from inventory_logs #255', '2026-06-04 21:40:43', '2026-06-15 18:18:52'),
(255, 16, 1, NULL, 4, 'sale', '429', 'inventory_log', 140, 0, 10, 130, 500.0000, 4000.0000, 70000.0000, 40000.0000, 65000.0000, 35000.0000, 'Imported from inventory_logs #429', '2026-06-15 17:22:21', '2026-06-15 18:18:52'),
(256, 16, 1, NULL, 4, 'adjustment', '430', 'inventory_log', 129, 1, 0, 130, 500.0000, 4000.0000, 64500.0000, 0.0000, 65000.0000, 0.0000, 'Imported from inventory_logs #430', '2026-06-15 17:29:07', '2026-06-15 18:18:52'),
(257, 16, 1, NULL, 4, 'sale', '433', 'inventory_log', 131, 0, 1, 130, 500.0000, 4000.0000, 65500.0000, 4000.0000, 65000.0000, 3500.0000, 'Imported from inventory_logs #433', '2026-06-15 17:41:51', '2026-06-15 18:18:52'),
(258, 17, 1, NULL, 1, 'adjustment', '84', 'inventory_log', 0, 1000, 0, 26, 3000.0000, 5000.0000, 0.0000, 0.0000, 78000.0000, 0.0000, 'Imported from inventory_logs #84', '2026-05-23 01:10:18', '2026-06-15 18:18:52'),
(259, 17, 1, NULL, 3, 'sale', '102', 'inventory_log', 1026, 0, 1000, 26, 3000.0000, 5000.0000, 3078000.0000, 5000000.0000, 78000.0000, 2000000.0000, 'Imported from inventory_logs #102', '2026-05-23 15:43:38', '2026-06-15 18:18:52'),
(260, 17, 1, NULL, 3, 'sale', '103', 'inventory_log', 226, 0, 200, 26, 3000.0000, 5000.0000, 678000.0000, 1000000.0000, 78000.0000, 400000.0000, 'Imported from inventory_logs #103', '2026-05-23 15:43:38', '2026-06-15 18:18:52'),
(261, 17, 1, NULL, 3, 'adjustment', '104', 'inventory_log', 0, 300, 0, 26, 3000.0000, 5000.0000, 0.0000, 0.0000, 78000.0000, 0.0000, 'Imported from inventory_logs #104', '2026-05-23 15:52:43', '2026-06-15 18:18:52'),
(262, 17, 1, NULL, 1, 'sale', '129', 'inventory_log', 76, 0, 50, 26, 3000.0000, 5000.0000, 228000.0000, 250000.0000, 78000.0000, 100000.0000, 'Imported from inventory_logs #129', '2026-05-24 12:13:27', '2026-06-15 18:18:52'),
(263, 17, 1, NULL, 4, 'sale', '160', 'inventory_log', 28, 0, 2, 26, 3000.0000, 5000.0000, 84000.0000, 10000.0000, 78000.0000, 4000.0000, 'Imported from inventory_logs #160', '2026-05-26 20:42:54', '2026-06-15 18:18:52'),
(264, 17, 1, NULL, 4, 'sale', '165', 'inventory_log', 29, 0, 3, 26, 3000.0000, 5000.0000, 87000.0000, 15000.0000, 78000.0000, 6000.0000, 'Imported from inventory_logs #165', '2026-05-26 21:00:01', '2026-06-15 18:18:52'),
(265, 17, 1, NULL, 4, 'sale', '166', 'inventory_log', 31, 0, 5, 26, 3000.0000, 5000.0000, 93000.0000, 25000.0000, 78000.0000, 10000.0000, 'Imported from inventory_logs #166', '2026-05-29 15:57:35', '2026-06-15 18:18:52'),
(266, 17, 1, NULL, 4, 'sale', '170', 'inventory_log', 27, 0, 1, 26, 3000.0000, 5000.0000, 81000.0000, 5000.0000, 78000.0000, 2000.0000, 'Imported from inventory_logs #170', '2026-06-02 02:25:21', '2026-06-15 18:18:52'),
(267, 17, 1, NULL, 4, 'sale', '176', 'inventory_log', 28, 0, 2, 26, 3000.0000, 5000.0000, 84000.0000, 10000.0000, 78000.0000, 4000.0000, 'Imported from inventory_logs #176', '2026-06-02 02:25:21', '2026-06-15 18:18:52'),
(268, 17, 1, NULL, 4, 'sale', '190', 'inventory_log', 28, 0, 2, 26, 3000.0000, 5000.0000, 84000.0000, 10000.0000, 78000.0000, 4000.0000, 'Imported from inventory_logs #190', '2026-06-03 02:07:12', '2026-06-15 18:18:52'),
(269, 17, 1, NULL, 4, 'sale', '209', 'inventory_log', 27, 0, 1, 26, 3000.0000, 5000.0000, 81000.0000, 5000.0000, 78000.0000, 2000.0000, 'Imported from inventory_logs #209', '2026-06-03 04:57:52', '2026-06-15 18:18:52'),
(270, 17, 1, NULL, 4, 'sale', '212', 'inventory_log', 27, 0, 1, 26, 3000.0000, 5000.0000, 81000.0000, 5000.0000, 78000.0000, 2000.0000, 'Imported from inventory_logs #212', '2026-06-03 05:28:23', '2026-06-15 18:18:52'),
(271, 17, 1, NULL, 4, 'sale', '226', 'inventory_log', 27, 0, 1, 26, 3000.0000, 5000.0000, 81000.0000, 5000.0000, 78000.0000, 2000.0000, 'Imported from inventory_logs #226', '2026-06-03 07:23:48', '2026-06-15 18:18:52'),
(272, 17, 1, NULL, 4, 'sale', '230', 'inventory_log', 28, 0, 2, 26, 3000.0000, 5000.0000, 84000.0000, 10000.0000, 78000.0000, 4000.0000, 'Imported from inventory_logs #230', '2026-06-03 08:06:50', '2026-06-15 18:18:52'),
(273, 17, 1, NULL, 4, 'sale', '313', 'inventory_log', 28, 0, 2, 26, 3000.0000, 5000.0000, 84000.0000, 10000.0000, 78000.0000, 4000.0000, 'Imported from inventory_logs #313', '2026-06-09 01:35:26', '2026-06-15 18:18:52'),
(274, 17, 1, NULL, 4, 'sale', '314', 'inventory_log', 27, 0, 1, 26, 3000.0000, 5000.0000, 81000.0000, 5000.0000, 78000.0000, 2000.0000, 'Imported from inventory_logs #314', '2026-06-09 01:35:27', '2026-06-15 18:18:52'),
(275, 17, 1, NULL, 1, 'sale', '397', 'inventory_log', 27, 0, 1, 26, 3000.0000, 5000.0000, 81000.0000, 5000.0000, 78000.0000, 2000.0000, 'Imported from inventory_logs #397', '2026-06-10 23:38:35', '2026-06-15 18:18:52'),
(276, 24, 6, NULL, 1, 'adjustment', '130', 'inventory_log', 0, 100, 0, 59, 15000.0000, 20000.0000, 0.0000, 0.0000, 885000.0000, 0.0000, 'Imported from inventory_logs #130', '2026-05-24 12:21:49', '2026-06-15 18:18:52'),
(277, 24, 6, NULL, 8, 'sale', '131', 'inventory_log', 61, 0, 2, 59, 15000.0000, 20000.0000, 915000.0000, 40000.0000, 885000.0000, 10000.0000, 'Imported from inventory_logs #131', '2026-05-24 12:23:40', '2026-06-15 18:18:52'),
(278, 24, 6, NULL, 7, 'sale', '136', 'inventory_log', 67, 0, 8, 59, 15000.0000, 20000.0000, 1005000.0000, 160000.0000, 885000.0000, 40000.0000, 'Imported from inventory_logs #136', '2026-05-24 17:31:52', '2026-06-15 18:18:52'),
(279, 24, 1, NULL, 4, 'sale', '169', 'inventory_log', 69, 0, 10, 59, 15000.0000, 20000.0000, 1035000.0000, 200000.0000, 885000.0000, 50000.0000, 'Imported from inventory_logs #169', '2026-06-02 02:25:21', '2026-06-15 18:18:52'),
(280, 24, 1, NULL, 4, 'sale', '175', 'inventory_log', 61, 0, 2, 59, 15000.0000, 20000.0000, 915000.0000, 40000.0000, 885000.0000, 10000.0000, 'Imported from inventory_logs #175', '2026-06-02 02:25:21', '2026-06-15 18:18:52'),
(281, 24, 1, NULL, 1, 'sale', '218', 'inventory_log', 60, 0, 1, 59, 15000.0000, 20000.0000, 900000.0000, 20000.0000, 885000.0000, 5000.0000, 'Imported from inventory_logs #218', '2026-06-03 05:56:39', '2026-06-15 18:18:52'),
(282, 24, 1, NULL, 1, 'adjustment', '220', 'inventory_log', 56, 3, 0, 59, 15000.0000, 20000.0000, 840000.0000, 0.0000, 885000.0000, 0.0000, 'Imported from inventory_logs #220', '2026-06-03 06:29:46', '2026-06-15 18:18:52'),
(283, 24, 1, NULL, 1, 'sale', '221', 'inventory_log', 60, 0, 1, 59, 15000.0000, 20000.0000, 900000.0000, 20000.0000, 885000.0000, 5000.0000, 'Imported from inventory_logs #221', '2026-06-03 06:30:40', '2026-06-15 18:18:52'),
(284, 24, 6, NULL, 1, 'sale', '246', 'inventory_log', 68, 0, 9, 59, 15000.0000, 20000.0000, 1020000.0000, 180000.0000, 885000.0000, 45000.0000, 'Imported from inventory_logs #246', '2026-06-04 15:54:00', '2026-06-15 18:18:52'),
(285, 24, 6, NULL, 1, 'sale', '247', 'inventory_log', 60, 0, 1, 59, 15000.0000, 20000.0000, 900000.0000, 20000.0000, 885000.0000, 5000.0000, 'Imported from inventory_logs #247', '2026-06-04 16:04:33', '2026-06-15 18:18:52'),
(286, 24, 6, NULL, 1, 'sale', '248', 'inventory_log', 60, 0, 1, 59, 15000.0000, 20000.0000, 900000.0000, 20000.0000, 885000.0000, 5000.0000, 'Imported from inventory_logs #248', '2026-06-04 16:08:18', '2026-06-15 18:18:52'),
(287, 24, 6, NULL, 1, 'adjustment', '251', 'inventory_log', 58, 1, 0, 59, 15000.0000, 20000.0000, 870000.0000, 0.0000, 885000.0000, 0.0000, 'Imported from inventory_logs #251', '2026-06-04 16:32:33', '2026-06-15 18:18:52'),
(288, 24, 6, NULL, 7, 'sale', '262', 'inventory_log', 68, 0, 9, 59, 15000.0000, 20000.0000, 1020000.0000, 180000.0000, 885000.0000, 45000.0000, 'Imported from inventory_logs #262', '2026-06-04 21:59:03', '2026-06-15 18:18:52'),
(289, 24, 6, NULL, 1, 'sale', '269', 'inventory_log', 60, 0, 1, 59, 15000.0000, 20000.0000, 900000.0000, 20000.0000, 885000.0000, 5000.0000, 'Imported from inventory_logs #269', '2026-06-05 02:40:42', '2026-06-15 18:18:52'),
(290, 25, 1, NULL, 1, 'adjustment', '229', 'inventory_log', 0, 100, 0, 45, 150.0000, 300.0000, 0.0000, 0.0000, 6750.0000, 0.0000, 'Imported from inventory_logs #229', '2026-06-03 07:32:46', '2026-06-15 18:18:52'),
(291, 25, 1, NULL, 4, 'sale', '235', 'inventory_log', 50, 0, 5, 45, 150.0000, 300.0000, 7500.0000, 1500.0000, 6750.0000, 750.0000, 'Imported from inventory_logs #235', '2026-06-03 15:37:01', '2026-06-15 18:18:52'),
(292, 25, 1, NULL, 4, 'sale', '240', 'inventory_log', 50, 0, 5, 45, 150.0000, 300.0000, 7500.0000, 1500.0000, 6750.0000, 750.0000, 'Imported from inventory_logs #240', '2026-06-03 15:39:15', '2026-06-15 18:18:52'),
(293, 25, 1, NULL, 4, 'sale', '244', 'inventory_log', 85, 0, 40, 45, 150.0000, 300.0000, 12750.0000, 12000.0000, 6750.0000, 6000.0000, 'Imported from inventory_logs #244', '2026-06-04 15:22:26', '2026-06-15 18:18:52'),
(294, 25, 1, NULL, 4, 'sale', '312', 'inventory_log', 50, 0, 5, 45, 150.0000, 300.0000, 7500.0000, 1500.0000, 6750.0000, 750.0000, 'Imported from inventory_logs #312', '2026-06-09 01:35:26', '2026-06-15 18:18:52'),
(295, 26, 1, NULL, 1, 'adjustment', '263', 'inventory_log', 0, 1000, 0, 800, 1500.0000, 3000.0000, 0.0000, 0.0000, 1200000.0000, 0.0000, 'Imported from inventory_logs #263', '2026-06-05 01:48:09', '2026-06-15 18:18:52'),
(296, 26, 1, NULL, 4, 'sale', '266', 'inventory_log', 1300, 0, 500, 800, 1500.0000, 3000.0000, 1950000.0000, 1500000.0000, 1200000.0000, 750000.0000, 'Imported from inventory_logs #266', '2026-06-05 02:29:02', '2026-06-15 18:18:52'),
(297, 26, 1, NULL, 4, 'adjustment', '268', 'inventory_log', 500, 300, 0, 800, 1500.0000, 3000.0000, 750000.0000, 0.0000, 1200000.0000, 0.0000, 'Imported from inventory_logs #268', '2026-06-05 02:33:14', '2026-06-15 18:18:52'),
(298, 27, 1, NULL, 1, 'adjustment', '264', 'inventory_log', 0, 3000, 0, 3000, 2000.0000, 4500.0000, 0.0000, 0.0000, 6000000.0000, 0.0000, 'Imported from inventory_logs #264', '2026-06-05 01:56:20', '2026-06-15 18:18:52'),
(299, 28, 6, NULL, 1, 'adjustment', '270', 'inventory_log', 0, 100, 0, 70, 30000.0000, 75000.0000, 0.0000, 0.0000, 2100000.0000, 0.0000, 'Imported from inventory_logs #270', '2026-06-05 02:44:04', '2026-06-15 18:18:52'),
(300, 28, 6, NULL, 1, 'sale', '271', 'inventory_log', 93, 0, 23, 70, 30000.0000, 75000.0000, 2790000.0000, 1725000.0000, 2100000.0000, 1035000.0000, 'Imported from inventory_logs #271', '2026-06-05 02:46:59', '2026-06-15 18:18:52'),
(301, 28, 6, NULL, 1, 'sale', '302', 'inventory_log', 77, 0, 7, 70, 30000.0000, 75000.0000, 2310000.0000, 525000.0000, 2100000.0000, 315000.0000, 'Imported from inventory_logs #302', '2026-06-06 16:58:26', '2026-06-15 18:18:52'),
(302, 29, 1, NULL, 1, 'adjustment', '279', 'inventory_log', 0, 40, 0, 40, 2000.0000, 5000.0000, 0.0000, 0.0000, 80000.0000, 0.0000, 'Imported from inventory_logs #279', '2026-06-05 03:44:02', '2026-06-15 18:18:52'),
(303, 30, 1, NULL, 1, 'adjustment', '280', 'inventory_log', 0, 300, 0, 300, 1800.0000, 3800.0000, 0.0000, 0.0000, 540000.0000, 0.0000, 'Imported from inventory_logs #280', '2026-06-05 15:40:30', '2026-06-15 18:18:52'),
(304, 31, 1, NULL, 1, 'adjustment', '281', 'inventory_log', 0, 50, 0, 50, 7000.0000, 15000.0000, 0.0000, 0.0000, 350000.0000, 0.0000, 'Imported from inventory_logs #281', '2026-06-05 15:46:05', '2026-06-15 18:18:52'),
(305, 32, 1, NULL, 1, 'adjustment', '282', 'inventory_log', 0, 300, 0, 300, 998.0000, 3000.0000, 0.0000, 0.0000, 299400.0000, 0.0000, 'Imported from inventory_logs #282', '2026-06-05 15:49:42', '2026-06-15 18:18:52'),
(306, 35, 1, NULL, 1, 'adjustment', '283', 'inventory_log', 0, 50, 0, 50, 300000.0000, 800000.0000, 0.0000, 0.0000, 15000000.0000, 0.0000, 'Imported from inventory_logs #283', '2026-06-05 15:56:01', '2026-06-15 18:18:52'),
(307, 38, 1, NULL, 1, 'adjustment', '284', 'inventory_log', 0, 300, 0, 300, 12000.0000, 25000.0000, 0.0000, 0.0000, 3600000.0000, 0.0000, 'Imported from inventory_logs #284', '2026-06-05 20:24:57', '2026-06-15 18:18:52'),
(308, 39, 1, NULL, 1, 'adjustment', '285', 'inventory_log', 0, 70, 0, 70, 6000.0000, 15000.0000, 0.0000, 0.0000, 420000.0000, 0.0000, 'Imported from inventory_logs #285', '2026-06-05 20:29:47', '2026-06-15 18:18:52'),
(309, 40, 1, NULL, 1, 'adjustment', '286', 'inventory_log', 0, 10, 0, 10, 600000.0000, 1200000.0000, 0.0000, 0.0000, 6000000.0000, 0.0000, 'Imported from inventory_logs #286', '2026-06-05 20:33:41', '2026-06-15 18:18:52'),
(310, 41, 1, NULL, 1, 'adjustment', '287', 'inventory_log', 0, 50, 0, 49, 8000.0000, 17000.0000, 0.0000, 0.0000, 392000.0000, 0.0000, 'Imported from inventory_logs #287', '2026-06-05 20:42:18', '2026-06-15 18:18:52'),
(311, 41, 1, NULL, 4, 'sale', '306', 'inventory_log', 50, 0, 1, 49, 8000.0000, 17000.0000, 400000.0000, 17000.0000, 392000.0000, 9000.0000, 'Imported from inventory_logs #306', '2026-06-09 01:35:26', '2026-06-15 18:18:52'),
(312, 42, 1, NULL, 1, 'adjustment', '288', 'inventory_log', 0, 300, 0, 299, 3998.0000, 12000.0000, 0.0000, 0.0000, 1195402.0000, 0.0000, 'Imported from inventory_logs #288', '2026-06-05 20:44:58', '2026-06-15 18:18:52'),
(313, 42, 1, NULL, 4, 'sale', '305', 'inventory_log', 300, 0, 1, 299, 3998.0000, 12000.0000, 1199400.0000, 12000.0000, 1195402.0000, 8002.0000, 'Imported from inventory_logs #305', '2026-06-09 01:35:26', '2026-06-15 18:18:52'),
(314, 43, 1, NULL, 1, 'adjustment', '289', 'inventory_log', 0, 300, 0, 300, 12000.0000, 27000.0000, 0.0000, 0.0000, 3600000.0000, 0.0000, 'Imported from inventory_logs #289', '2026-06-05 20:47:04', '2026-06-15 18:18:52'),
(315, 44, 1, NULL, 1, 'adjustment', '290', 'inventory_log', 0, 200, 0, 199, 11000.0000, 28000.0000, 0.0000, 0.0000, 2189000.0000, 0.0000, 'Imported from inventory_logs #290', '2026-06-05 20:48:24', '2026-06-15 18:18:52'),
(316, 44, 1, NULL, 4, 'sale', '309', 'inventory_log', 200, 0, 1, 199, 11000.0000, 28000.0000, 2200000.0000, 28000.0000, 2189000.0000, 17000.0000, 'Imported from inventory_logs #309', '2026-06-09 01:35:26', '2026-06-15 18:18:52'),
(317, 46, 1, NULL, 1, 'adjustment', '291', 'inventory_log', 0, 300, 0, 299, 23000.0000, 40000.0000, 0.0000, 0.0000, 6877000.0000, 0.0000, 'Imported from inventory_logs #291', '2026-06-05 20:51:23', '2026-06-15 18:18:52'),
(318, 46, 1, NULL, 4, 'sale', '308', 'inventory_log', 300, 0, 1, 299, 23000.0000, 40000.0000, 6900000.0000, 40000.0000, 6877000.0000, 17000.0000, 'Imported from inventory_logs #308', '2026-06-09 01:35:26', '2026-06-15 18:18:52'),
(319, 47, 1, NULL, 1, 'adjustment', '292', 'inventory_log', 0, 30, 0, 29, 8000.0000, 18000.0000, 0.0000, 0.0000, 232000.0000, 0.0000, 'Imported from inventory_logs #292', '2026-06-05 20:53:20', '2026-06-15 18:18:52'),
(320, 47, 1, NULL, 4, 'sale', '307', 'inventory_log', 30, 0, 1, 29, 8000.0000, 18000.0000, 240000.0000, 18000.0000, 232000.0000, 10000.0000, 'Imported from inventory_logs #307', '2026-06-09 01:35:26', '2026-06-15 18:18:52'),
(321, 48, 1, NULL, 1, 'adjustment', '293', 'inventory_log', 0, 50, 0, 50, 8000.0000, 20000.0000, 0.0000, 0.0000, 400000.0000, 0.0000, 'Imported from inventory_logs #293', '2026-06-05 20:55:49', '2026-06-15 18:18:52'),
(322, 49, 1, NULL, 1, 'adjustment', '294', 'inventory_log', 0, 200, 0, 200, 35000.0000, 75000.0000, 0.0000, 0.0000, 7000000.0000, 0.0000, 'Imported from inventory_logs #294', '2026-06-05 20:57:43', '2026-06-15 18:18:52'),
(323, 50, 1, NULL, 1, 'adjustment', '295', 'inventory_log', 0, 40, 0, 40, 13000.0000, 23000.0000, 0.0000, 0.0000, 520000.0000, 0.0000, 'Imported from inventory_logs #295', '2026-06-05 20:59:54', '2026-06-15 18:18:52'),
(324, 51, 1, NULL, 1, 'adjustment', '296', 'inventory_log', 0, 400, 0, 400, 15000.0000, 30000.0000, 0.0000, 0.0000, 6000000.0000, 0.0000, 'Imported from inventory_logs #296', '2026-06-05 21:02:55', '2026-06-15 18:18:52'),
(325, 52, 1, NULL, 1, 'adjustment', '297', 'inventory_log', 0, 70, 0, 70, 37000.0000, 70000.0000, 0.0000, 0.0000, 2590000.0000, 0.0000, 'Imported from inventory_logs #297', '2026-06-05 21:12:56', '2026-06-15 18:18:52'),
(326, 53, 1, NULL, 1, 'adjustment', '298', 'inventory_log', 0, 30, 0, 29, 45000.0000, 6999.0000, 0.0000, 0.0000, 1305000.0000, 0.0000, 'Imported from inventory_logs #298', '2026-06-05 21:15:44', '2026-06-15 18:18:52'),
(327, 53, 1, NULL, 4, 'sale', '310', 'inventory_log', 30, 0, 1, 29, 45000.0000, 6999.0000, 1350000.0000, 6999.0000, 1305000.0000, -38001.0000, 'Imported from inventory_logs #310', '2026-06-09 01:35:26', '2026-06-15 18:18:52'),
(328, 55, 6, NULL, 1, 'adjustment', '303', 'inventory_log', 0, 100, 0, 80, 1000.0000, 2500.0000, 0.0000, 0.0000, 80000.0000, 0.0000, 'Imported from inventory_logs #303', '2026-06-06 17:02:55', '2026-06-15 18:18:52'),
(329, 55, 6, NULL, 1, 'sale', '304', 'inventory_log', 100, 0, 20, 80, 1000.0000, 2500.0000, 100000.0000, 50000.0000, 80000.0000, 30000.0000, 'Imported from inventory_logs #304', '2026-06-06 17:07:51', '2026-06-15 18:18:52'),
(330, 56, 7, NULL, 1, 'adjustment', '315', 'inventory_log', 198, 100, 0, 298, 2500.0000, 400.0000, 495000.0000, 0.0000, 745000.0000, 0.0000, 'Imported from inventory_logs #315', '2026-06-09 02:26:29', '2026-06-15 18:18:52'),
(331, 56, 7, NULL, 1, 'sale', '319', 'inventory_log', 299, 0, 1, 298, 2500.0000, 400.0000, 747500.0000, 400.0000, 745000.0000, -2100.0000, 'Imported from inventory_logs #319', '2026-06-09 02:48:34', '2026-06-15 18:18:52'),
(332, 56, 7, NULL, 1, 'sale', '326', 'inventory_log', 300, 0, 2, 298, 2500.0000, 400.0000, 750000.0000, 800.0000, 745000.0000, -4200.0000, 'Imported from inventory_logs #326', '2026-06-09 03:15:57', '2026-06-15 18:18:52'),
(333, 56, 7, NULL, 1, 'sale', '330', 'inventory_log', 299, 0, 1, 298, 2500.0000, 400.0000, 747500.0000, 400.0000, 745000.0000, -2100.0000, 'Imported from inventory_logs #330', '2026-06-09 03:18:35', '2026-06-15 18:18:52'),
(334, 56, 7, NULL, 1, 'sale', '334', 'inventory_log', 299, 0, 1, 298, 2500.0000, 400.0000, 747500.0000, 400.0000, 745000.0000, -2100.0000, 'Imported from inventory_logs #334', '2026-06-09 03:21:44', '2026-06-15 18:18:52'),
(335, 56, 7, NULL, 9, 'sale', '341', 'inventory_log', 303, 0, 5, 298, 2500.0000, 400.0000, 757500.0000, 2000.0000, 745000.0000, -10500.0000, 'Imported from inventory_logs #341', '2026-06-09 04:06:25', '2026-06-15 18:18:52'),
(336, 56, 7, NULL, 1, 'adjustment', '360', 'inventory_log', 88, 210, 0, 298, 2500.0000, 400.0000, 220000.0000, 0.0000, 745000.0000, 0.0000, 'Imported from inventory_logs #360', '2026-06-09 19:34:52', '2026-06-15 18:18:52'),
(337, 56, 7, NULL, 1, 'sale', '393', 'inventory_log', 299, 0, 1, 298, 2500.0000, 400.0000, 747500.0000, 400.0000, 745000.0000, -2100.0000, 'Imported from inventory_logs #393', '2026-06-10 00:26:29', '2026-06-15 18:18:52'),
(338, 56, 7, NULL, 1, 'sale', '410', 'inventory_log', 299, 0, 1, 298, 2500.0000, 400.0000, 747500.0000, 400.0000, 745000.0000, -2100.0000, 'Imported from inventory_logs #410', '2026-06-11 03:18:38', '2026-06-15 18:18:52'),
(339, 57, 7, NULL, 1, 'adjustment', '316', 'inventory_log', 0, 200, 0, 134, 350.0000, 500.0000, 0.0000, 0.0000, 46900.0000, 0.0000, 'Imported from inventory_logs #316', '2026-06-09 02:36:43', '2026-06-15 18:18:52'),
(340, 57, 7, NULL, 1, 'sale', '321', 'inventory_log', 142, 0, 8, 134, 350.0000, 500.0000, 49700.0000, 4000.0000, 46900.0000, 1200.0000, 'Imported from inventory_logs #321', '2026-06-09 02:55:08', '2026-06-15 18:18:52'),
(341, 57, 7, NULL, 1, 'adjustment', '323', 'inventory_log', 126, 8, 0, 134, 350.0000, 500.0000, 44100.0000, 0.0000, 46900.0000, 0.0000, 'Imported from inventory_logs #323', '2026-06-09 03:11:40', '2026-06-15 18:18:52'),
(342, 57, 7, NULL, 1, 'sale', '324', 'inventory_log', 164, 0, 30, 134, 350.0000, 500.0000, 57400.0000, 15000.0000, 46900.0000, 4500.0000, 'Imported from inventory_logs #324', '2026-06-09 03:15:57', '2026-06-15 18:18:52'),
(343, 57, 7, NULL, 1, 'sale', '327', 'inventory_log', 135, 0, 1, 134, 350.0000, 500.0000, 47250.0000, 500.0000, 46900.0000, 150.0000, 'Imported from inventory_logs #327', '2026-06-09 03:18:35', '2026-06-15 18:18:52'),
(344, 57, 7, NULL, 1, 'sale', '332', 'inventory_log', 143, 0, 9, 134, 350.0000, 500.0000, 50050.0000, 4500.0000, 46900.0000, 1350.0000, 'Imported from inventory_logs #332', '2026-06-09 03:19:43', '2026-06-15 18:18:52'),
(345, 57, 7, NULL, 1, 'sale', '336', 'inventory_log', 135, 0, 1, 134, 350.0000, 500.0000, 47250.0000, 500.0000, 46900.0000, 150.0000, 'Imported from inventory_logs #336', '2026-06-09 03:21:44', '2026-06-15 18:18:52'),
(346, 57, 7, NULL, 1, 'sale', '338', 'inventory_log', 138, 0, 4, 134, 350.0000, 500.0000, 48300.0000, 2000.0000, 46900.0000, 600.0000, 'Imported from inventory_logs #338', '2026-06-09 03:21:57', '2026-06-15 18:18:52'),
(347, 57, 7, NULL, 9, 'sale', '351', 'inventory_log', 135, 0, 1, 134, 350.0000, 500.0000, 47250.0000, 500.0000, 46900.0000, 150.0000, 'Imported from inventory_logs #351', '2026-06-09 18:52:30', '2026-06-15 18:18:52'),
(348, 57, 7, NULL, 9, 'sale', '354', 'inventory_log', 138, 0, 4, 134, 350.0000, 500.0000, 48300.0000, 2000.0000, 46900.0000, 600.0000, 'Imported from inventory_logs #354', '2026-06-09 18:54:23', '2026-06-15 18:18:52'),
(349, 57, 7, NULL, 12, 'sale', '366', 'inventory_log', 135, 0, 1, 134, 350.0000, 500.0000, 47250.0000, 500.0000, 46900.0000, 150.0000, 'Imported from inventory_logs #366', '2026-06-09 20:58:10', '2026-06-15 18:18:52'),
(350, 57, 7, NULL, 12, 'sale', '371', 'inventory_log', 136, 0, 2, 134, 350.0000, 500.0000, 47600.0000, 1000.0000, 46900.0000, 300.0000, 'Imported from inventory_logs #371', '2026-06-09 21:02:58', '2026-06-15 18:18:52'),
(351, 57, 7, NULL, 13, 'sale', '375', 'inventory_log', 135, 0, 1, 134, 350.0000, 500.0000, 47250.0000, 500.0000, 46900.0000, 150.0000, 'Imported from inventory_logs #375', '2026-06-09 22:29:28', '2026-06-15 18:18:52'),
(352, 57, 7, NULL, 13, 'adjustment', '376', 'inventory_log', 133, 1, 0, 134, 350.0000, 500.0000, 46550.0000, 0.0000, 46900.0000, 0.0000, 'Imported from inventory_logs #376', '2026-06-09 22:30:30', '2026-06-15 18:18:52'),
(353, 57, 7, NULL, 13, 'sale', '377', 'inventory_log', 136, 0, 2, 134, 350.0000, 500.0000, 47600.0000, 1000.0000, 46900.0000, 300.0000, 'Imported from inventory_logs #377', '2026-06-09 23:17:56', '2026-06-15 18:18:52'),
(354, 57, 7, NULL, 1, 'sale', '381', 'inventory_log', 137, 0, 3, 134, 350.0000, 500.0000, 47950.0000, 1500.0000, 46900.0000, 450.0000, 'Imported from inventory_logs #381', '2026-06-09 23:43:11', '2026-06-15 18:18:52'),
(355, 57, 7, NULL, 1, 'sale', '382', 'inventory_log', 135, 0, 1, 134, 350.0000, 500.0000, 47250.0000, 500.0000, 46900.0000, 150.0000, 'Imported from inventory_logs #382', '2026-06-09 23:45:15', '2026-06-15 18:18:52'),
(356, 57, 7, NULL, 1, 'sale', '383', 'inventory_log', 135, 0, 1, 134, 350.0000, 500.0000, 47250.0000, 500.0000, 46900.0000, 150.0000, 'Imported from inventory_logs #383', '2026-06-09 23:45:55', '2026-06-15 18:18:52'),
(357, 57, 7, NULL, 1, 'sale', '392', 'inventory_log', 137, 0, 3, 134, 350.0000, 500.0000, 47950.0000, 1500.0000, 46900.0000, 450.0000, 'Imported from inventory_logs #392', '2026-06-10 00:26:29', '2026-06-15 18:18:52'),
(358, 57, 7, NULL, 1, 'sale', '396', 'inventory_log', 135, 0, 1, 134, 350.0000, 500.0000, 47250.0000, 500.0000, 46900.0000, 150.0000, 'Imported from inventory_logs #396', '2026-06-10 00:27:45', '2026-06-15 18:18:52'),
(359, 57, 7, NULL, 1, 'sale', '409', 'inventory_log', 135, 0, 1, 134, 350.0000, 500.0000, 47250.0000, 500.0000, 46900.0000, 150.0000, 'Imported from inventory_logs #409', '2026-06-11 03:18:38', '2026-06-15 18:18:52'),
(360, 57, 7, NULL, 1, 'sale', '413', 'inventory_log', 135, 0, 1, 134, 350.0000, 500.0000, 47250.0000, 500.0000, 46900.0000, 150.0000, 'Imported from inventory_logs #413', '2026-06-11 03:20:22', '2026-06-15 18:18:52'),
(361, 58, 7, NULL, 1, 'adjustment', '317', 'inventory_log', 243, 50, 0, 293, 150.0000, 200.0000, 36450.0000, 0.0000, 43950.0000, 0.0000, 'Imported from inventory_logs #317', '2026-06-09 02:39:35', '2026-06-15 18:18:52'),
(362, 58, 7, NULL, 1, 'sale', '320', 'inventory_log', 294, 0, 1, 293, 150.0000, 200.0000, 44100.0000, 200.0000, 43950.0000, 50.0000, 'Imported from inventory_logs #320', '2026-06-09 02:48:34', '2026-06-15 18:18:52'),
(363, 58, 7, NULL, 1, 'sale', '322', 'inventory_log', 295, 0, 2, 293, 150.0000, 200.0000, 44250.0000, 400.0000, 43950.0000, 100.0000, 'Imported from inventory_logs #322', '2026-06-09 02:57:33', '2026-06-15 18:18:52'),
(364, 58, 7, NULL, 1, 'sale', '329', 'inventory_log', 294, 0, 1, 293, 150.0000, 200.0000, 44100.0000, 200.0000, 43950.0000, 50.0000, 'Imported from inventory_logs #329', '2026-06-09 03:18:35', '2026-06-15 18:18:52'),
(365, 58, 7, NULL, 1, 'sale', '333', 'inventory_log', 299, 0, 6, 293, 150.0000, 200.0000, 44850.0000, 1200.0000, 43950.0000, 300.0000, 'Imported from inventory_logs #333', '2026-06-09 03:20:50', '2026-06-15 18:18:52'),
(366, 58, 7, NULL, 1, 'sale', '335', 'inventory_log', 294, 0, 1, 293, 150.0000, 200.0000, 44100.0000, 200.0000, 43950.0000, 50.0000, 'Imported from inventory_logs #335', '2026-06-09 03:21:44', '2026-06-15 18:18:52'),
(367, 58, 7, NULL, 1, 'sale', '339', 'inventory_log', 296, 0, 3, 293, 150.0000, 200.0000, 44400.0000, 600.0000, 43950.0000, 150.0000, 'Imported from inventory_logs #339', '2026-06-09 03:22:10', '2026-06-15 18:18:52'),
(368, 58, 7, NULL, 1, 'sale', '340', 'inventory_log', 296, 0, 3, 293, 150.0000, 200.0000, 44400.0000, 600.0000, 43950.0000, 150.0000, 'Imported from inventory_logs #340', '2026-06-09 03:22:22', '2026-06-15 18:18:52'),
(369, 58, 7, NULL, 9, 'sale', '352', 'inventory_log', 294, 0, 1, 293, 150.0000, 200.0000, 44100.0000, 200.0000, 43950.0000, 50.0000, 'Imported from inventory_logs #352', '2026-06-09 18:52:30', '2026-06-15 18:18:52'),
(370, 58, 7, NULL, 9, 'sale', '355', 'inventory_log', 295, 0, 2, 293, 150.0000, 200.0000, 44250.0000, 400.0000, 43950.0000, 100.0000, 'Imported from inventory_logs #355', '2026-06-09 18:55:29', '2026-06-15 18:18:52'),
(371, 58, 7, NULL, 1, 'adjustment', '359', 'inventory_log', 23, 270, 0, 293, 150.0000, 200.0000, 3450.0000, 0.0000, 43950.0000, 0.0000, 'Imported from inventory_logs #359', '2026-06-09 19:34:31', '2026-06-15 18:18:52'),
(372, 58, 7, NULL, 1, 'sale', '394', 'inventory_log', 296, 0, 3, 293, 150.0000, 200.0000, 44400.0000, 600.0000, 43950.0000, 150.0000, 'Imported from inventory_logs #394', '2026-06-10 00:26:29', '2026-06-15 18:18:52'),
(373, 58, 7, NULL, 1, 'sale', '408', 'inventory_log', 294, 0, 1, 293, 150.0000, 200.0000, 44100.0000, 200.0000, 43950.0000, 50.0000, 'Imported from inventory_logs #408', '2026-06-11 03:18:38', '2026-06-15 18:18:52'),
(374, 58, 7, NULL, 1, 'sale', '414', 'inventory_log', 294, 0, 1, 293, 150.0000, 200.0000, 44100.0000, 200.0000, 43950.0000, 50.0000, 'Imported from inventory_logs #414', '2026-06-11 03:22:13', '2026-06-15 18:18:52'),
(375, 58, 7, NULL, 1, 'sale', '417', 'inventory_log', 294, 0, 1, 293, 150.0000, 200.0000, 44100.0000, 200.0000, 43950.0000, 50.0000, 'Imported from inventory_logs #417', '2026-06-14 18:39:48', '2026-06-15 18:18:52'),
(376, 58, 7, NULL, 1, 'sale', '419', 'inventory_log', 294, 0, 1, 293, 150.0000, 200.0000, 44100.0000, 200.0000, 43950.0000, 50.0000, 'Imported from inventory_logs #419', '2026-06-14 18:40:35', '2026-06-15 18:18:52'),
(377, 59, 7, NULL, 1, 'adjustment', '318', 'inventory_log', 0, 80, 0, 71, 150.0000, 200.0000, 0.0000, 0.0000, 10650.0000, 0.0000, 'Imported from inventory_logs #318', '2026-06-09 02:43:16', '2026-06-15 18:18:52'),
(378, 59, 7, NULL, 1, 'sale', '325', 'inventory_log', 76, 0, 5, 71, 150.0000, 200.0000, 11400.0000, 1000.0000, 10650.0000, 250.0000, 'Imported from inventory_logs #325', '2026-06-09 03:15:57', '2026-06-15 18:18:52'),
(379, 59, 7, NULL, 1, 'sale', '328', 'inventory_log', 72, 0, 1, 71, 150.0000, 200.0000, 10800.0000, 200.0000, 10650.0000, 50.0000, 'Imported from inventory_logs #328', '2026-06-09 03:18:35', '2026-06-15 18:18:52'),
(380, 59, 7, NULL, 1, 'sale', '331', 'inventory_log', 75, 0, 4, 71, 150.0000, 200.0000, 11250.0000, 800.0000, 10650.0000, 200.0000, 'Imported from inventory_logs #331', '2026-06-09 03:19:43', '2026-06-15 18:18:52'),
(381, 59, 7, NULL, 1, 'sale', '337', 'inventory_log', 72, 0, 1, 71, 150.0000, 200.0000, 10800.0000, 200.0000, 10650.0000, 50.0000, 'Imported from inventory_logs #337', '2026-06-09 03:21:44', '2026-06-15 18:18:52'),
(382, 59, 7, NULL, 9, 'sale', '353', 'inventory_log', 72, 0, 1, 71, 150.0000, 200.0000, 10800.0000, 200.0000, 10650.0000, 50.0000, 'Imported from inventory_logs #353', '2026-06-09 18:52:30', '2026-06-15 18:18:52'),
(383, 59, 7, NULL, 9, 'sale', '356', 'inventory_log', 73, 0, 2, 71, 150.0000, 200.0000, 10950.0000, 400.0000, 10650.0000, 100.0000, 'Imported from inventory_logs #356', '2026-06-09 18:59:14', '2026-06-15 18:18:52'),
(384, 59, 7, NULL, 1, 'adjustment', '357', 'inventory_log', 69, 2, 0, 71, 150.0000, 200.0000, 10350.0000, 0.0000, 10650.0000, 0.0000, 'Imported from inventory_logs #357', '2026-06-09 19:20:15', '2026-06-15 18:18:52'),
(385, 59, 7, NULL, 1, 'adjustment', '358', 'inventory_log', 59, 12, 0, 71, 150.0000, 200.0000, 8850.0000, 0.0000, 10650.0000, 0.0000, 'Imported from inventory_logs #358', '2026-06-09 19:34:09', '2026-06-15 18:18:52'),
(386, 59, 7, NULL, 1, 'sale', '385', 'inventory_log', 72, 0, 1, 71, 150.0000, 200.0000, 10800.0000, 200.0000, 10650.0000, 50.0000, 'Imported from inventory_logs #385', '2026-06-10 00:21:38', '2026-06-15 18:18:52'),
(387, 59, 7, NULL, 1, 'sale', '395', 'inventory_log', 75, 0, 4, 71, 150.0000, 200.0000, 11250.0000, 800.0000, 10650.0000, 200.0000, 'Imported from inventory_logs #395', '2026-06-10 00:26:29', '2026-06-15 18:18:52'),
(388, 59, 7, NULL, 1, 'sale', '407', 'inventory_log', 72, 0, 1, 71, 150.0000, 200.0000, 10800.0000, 200.0000, 10650.0000, 50.0000, 'Imported from inventory_logs #407', '2026-06-11 03:18:38', '2026-06-15 18:18:52'),
(389, 59, 7, NULL, 1, 'sale', '411', 'inventory_log', 72, 0, 1, 71, 150.0000, 200.0000, 10800.0000, 200.0000, 10650.0000, 50.0000, 'Imported from inventory_logs #411', '2026-06-11 03:20:22', '2026-06-15 18:18:52'),
(390, 59, 7, NULL, 1, 'sale', '416', 'inventory_log', 72, 0, 1, 71, 150.0000, 200.0000, 10800.0000, 200.0000, 10650.0000, 50.0000, 'Imported from inventory_logs #416', '2026-06-14 18:39:48', '2026-06-15 18:18:52'),
(391, 59, 7, NULL, 1, 'sale', '418', 'inventory_log', 72, 0, 1, 71, 150.0000, 200.0000, 10800.0000, 200.0000, 10650.0000, 50.0000, 'Imported from inventory_logs #418', '2026-06-14 18:40:35', '2026-06-15 18:18:52'),
(392, 60, 7, NULL, 1, 'adjustment', '342', 'inventory_log', 0, 400, 0, 392, 200.0000, 300.0000, 0.0000, 0.0000, 78400.0000, 0.0000, 'Imported from inventory_logs #342', '2026-06-09 16:49:26', '2026-06-15 18:18:52'),
(393, 60, 7, NULL, 1, 'sale', '348', 'inventory_log', 395, 0, 3, 392, 200.0000, 300.0000, 79000.0000, 900.0000, 78400.0000, 300.0000, 'Imported from inventory_logs #348', '2026-06-09 17:31:05', '2026-06-15 18:18:52'),
(394, 60, 7, NULL, 1, 'adjustment', '349', 'inventory_log', 389, 3, 0, 392, 200.0000, 300.0000, 77800.0000, 0.0000, 78400.0000, 0.0000, 'Imported from inventory_logs #349', '2026-06-09 17:40:57', '2026-06-15 18:18:52'),
(395, 60, 7, NULL, 9, 'sale', '350', 'inventory_log', 393, 0, 1, 392, 200.0000, 300.0000, 78600.0000, 300.0000, 78400.0000, 100.0000, 'Imported from inventory_logs #350', '2026-06-09 18:52:30', '2026-06-15 18:18:52'),
(396, 60, 7, NULL, 1, 'adjustment', '361', 'inventory_log', 391, 1, 0, 392, 200.0000, 300.0000, 78200.0000, 0.0000, 78400.0000, 0.0000, 'Imported from inventory_logs #361', '2026-06-09 19:35:01', '2026-06-15 18:18:52'),
(397, 60, 7, NULL, 12, 'sale', '367', 'inventory_log', 393, 0, 1, 392, 200.0000, 300.0000, 78600.0000, 300.0000, 78400.0000, 100.0000, 'Imported from inventory_logs #367', '2026-06-09 20:58:10', '2026-06-15 18:18:52'),
(398, 60, 7, NULL, 12, 'sale', '368', 'inventory_log', 393, 0, 1, 392, 200.0000, 300.0000, 78600.0000, 300.0000, 78400.0000, 100.0000, 'Imported from inventory_logs #368', '2026-06-09 20:58:11', '2026-06-15 18:18:52'),
(399, 60, 7, NULL, 12, 'sale', '372', 'inventory_log', 394, 0, 2, 392, 200.0000, 300.0000, 78800.0000, 600.0000, 78400.0000, 200.0000, 'Imported from inventory_logs #372', '2026-06-09 21:03:52', '2026-06-15 18:18:52'),
(400, 60, 7, NULL, 1, 'sale', '386', 'inventory_log', 393, 0, 1, 392, 200.0000, 300.0000, 78600.0000, 300.0000, 78400.0000, 100.0000, 'Imported from inventory_logs #386', '2026-06-10 00:26:29', '2026-06-15 18:18:52'),
(401, 60, 7, NULL, 1, 'sale', '401', 'inventory_log', 395, 0, 3, 392, 200.0000, 300.0000, 79000.0000, 900.0000, 78400.0000, 300.0000, 'Imported from inventory_logs #401', '2026-06-11 03:18:38', '2026-06-15 18:18:52'),
(402, 61, 7, NULL, 1, 'adjustment', '343', 'inventory_log', 0, 200, 0, 98, 200.0000, 380.0000, 0.0000, 0.0000, 19600.0000, 0.0000, 'Imported from inventory_logs #343', '2026-06-09 16:53:25', '2026-06-15 18:18:52'),
(403, 61, 7, NULL, 9, 'sale', '362', 'inventory_log', 99, 0, 1, 98, 200.0000, 380.0000, 19800.0000, 380.0000, 19600.0000, 180.0000, 'Imported from inventory_logs #362', '2026-06-09 19:39:12', '2026-06-15 18:18:52'),
(404, 61, 7, NULL, 9, 'sale', '363', 'inventory_log', 128, 0, 30, 98, 200.0000, 380.0000, 25600.0000, 11400.0000, 19600.0000, 5400.0000, 'Imported from inventory_logs #363', '2026-06-09 19:40:00', '2026-06-15 18:18:52'),
(405, 61, 7, NULL, 12, 'sale', '369', 'inventory_log', 298, 0, 200, 98, 200.0000, 380.0000, 59600.0000, 76000.0000, 19600.0000, 36000.0000, 'Imported from inventory_logs #369', '2026-06-09 20:58:11', '2026-06-15 18:18:52'),
(406, 61, 7, NULL, 1, 'adjustment', '373', 'inventory_log', 0, 100, 0, 98, 200.0000, 380.0000, 0.0000, 0.0000, 19600.0000, 0.0000, 'Imported from inventory_logs #373', '2026-06-09 21:10:38', '2026-06-15 18:18:52'),
(407, 61, 7, NULL, 1, 'adjustment', '374', 'inventory_log', 67, 31, 0, 98, 200.0000, 380.0000, 13400.0000, 0.0000, 19600.0000, 0.0000, 'Imported from inventory_logs #374', '2026-06-09 21:11:18', '2026-06-15 18:18:52'),
(408, 61, 7, NULL, 1, 'sale', '387', 'inventory_log', 99, 0, 1, 98, 200.0000, 380.0000, 19800.0000, 380.0000, 19600.0000, 180.0000, 'Imported from inventory_logs #387', '2026-06-10 00:26:29', '2026-06-15 18:18:52'),
(409, 61, 7, NULL, 1, 'sale', '404', 'inventory_log', 99, 0, 1, 98, 200.0000, 380.0000, 19800.0000, 380.0000, 19600.0000, 180.0000, 'Imported from inventory_logs #404', '2026-06-11 03:18:38', '2026-06-15 18:18:52'),
(410, 62, 7, NULL, 1, 'adjustment', '344', 'inventory_log', 0, 100, 0, 86, 180.0000, 300.0000, 0.0000, 0.0000, 15480.0000, 0.0000, 'Imported from inventory_logs #344', '2026-06-09 16:55:51', '2026-06-15 18:18:52'),
(411, 62, 7, NULL, 9, 'sale', '364', 'inventory_log', 90, 0, 4, 86, 180.0000, 300.0000, 16200.0000, 1200.0000, 15480.0000, 480.0000, 'Imported from inventory_logs #364', '2026-06-09 19:41:06', '2026-06-15 18:18:52'),
(412, 62, 7, NULL, 12, 'sale', '370', 'inventory_log', 87, 0, 1, 86, 180.0000, 300.0000, 15660.0000, 300.0000, 15480.0000, 120.0000, 'Imported from inventory_logs #370', '2026-06-09 21:02:05', '2026-06-15 18:18:52'),
(413, 62, 7, NULL, 13, 'sale', '378', 'inventory_log', 87, 0, 1, 86, 180.0000, 300.0000, 15660.0000, 300.0000, 15480.0000, 120.0000, 'Imported from inventory_logs #378', '2026-06-09 23:31:05', '2026-06-15 18:18:52'),
(414, 62, 7, NULL, 13, 'sale', '380', 'inventory_log', 90, 0, 4, 86, 180.0000, 300.0000, 16200.0000, 1200.0000, 15480.0000, 480.0000, 'Imported from inventory_logs #380', '2026-06-09 23:32:55', '2026-06-15 18:18:52'),
(415, 62, 7, NULL, 1, 'sale', '388', 'inventory_log', 87, 0, 1, 86, 180.0000, 300.0000, 15660.0000, 300.0000, 15480.0000, 120.0000, 'Imported from inventory_logs #388', '2026-06-10 00:26:29', '2026-06-15 18:18:52'),
(416, 62, 7, NULL, 1, 'sale', '400', 'inventory_log', 87, 0, 1, 86, 180.0000, 300.0000, 15660.0000, 300.0000, 15480.0000, 120.0000, 'Imported from inventory_logs #400', '2026-06-11 03:15:16', '2026-06-15 18:18:52'),
(417, 62, 7, NULL, 1, 'sale', '405', 'inventory_log', 87, 0, 1, 86, 180.0000, 300.0000, 15660.0000, 300.0000, 15480.0000, 120.0000, 'Imported from inventory_logs #405', '2026-06-11 03:18:38', '2026-06-15 18:18:52'),
(418, 62, 7, NULL, 1, 'sale', '412', 'inventory_log', 87, 0, 1, 86, 180.0000, 300.0000, 15660.0000, 300.0000, 15480.0000, 120.0000, 'Imported from inventory_logs #412', '2026-06-11 03:20:22', '2026-06-15 18:18:52'),
(419, 63, 7, NULL, 1, 'adjustment', '345', 'inventory_log', 0, 300, 0, 277, 40.0000, 75.0000, 0.0000, 0.0000, 11080.0000, 0.0000, 'Imported from inventory_logs #345', '2026-06-09 16:57:54', '2026-06-15 18:18:52'),
(420, 63, 7, NULL, 13, 'sale', '379', 'inventory_log', 297, 0, 20, 277, 40.0000, 75.0000, 11880.0000, 1500.0000, 11080.0000, 700.0000, 'Imported from inventory_logs #379', '2026-06-09 23:32:02', '2026-06-15 18:18:52'),
(421, 63, 7, NULL, 1, 'sale', '389', 'inventory_log', 278, 0, 1, 277, 40.0000, 75.0000, 11120.0000, 75.0000, 11080.0000, 35.0000, 'Imported from inventory_logs #389', '2026-06-10 00:26:29', '2026-06-15 18:18:52'),
(422, 63, 7, NULL, 1, 'sale', '399', 'inventory_log', 278, 0, 1, 277, 40.0000, 75.0000, 11120.0000, 75.0000, 11080.0000, 35.0000, 'Imported from inventory_logs #399', '2026-06-11 03:15:16', '2026-06-15 18:18:52'),
(423, 63, 7, NULL, 1, 'sale', '406', 'inventory_log', 278, 0, 1, 277, 40.0000, 75.0000, 11120.0000, 75.0000, 11080.0000, 35.0000, 'Imported from inventory_logs #406', '2026-06-11 03:18:38', '2026-06-15 18:18:52'),
(424, 64, 7, NULL, 1, 'adjustment', '346', 'inventory_log', 0, 300, 0, 298, 8.0000, 20.0000, 0.0000, 0.0000, 2384.0000, 0.0000, 'Imported from inventory_logs #346', '2026-06-09 16:59:40', '2026-06-15 18:18:52'),
(425, 64, 7, NULL, 1, 'sale', '391', 'inventory_log', 299, 0, 1, 298, 8.0000, 20.0000, 2392.0000, 20.0000, 2384.0000, 12.0000, 'Imported from inventory_logs #391', '2026-06-10 00:26:29', '2026-06-15 18:18:52'),
(426, 64, 7, NULL, 1, 'sale', '402', 'inventory_log', 299, 0, 1, 298, 8.0000, 20.0000, 2392.0000, 20.0000, 2384.0000, 12.0000, 'Imported from inventory_logs #402', '2026-06-11 03:18:38', '2026-06-15 18:18:52'),
(427, 65, 7, NULL, 1, 'adjustment', '347', 'inventory_log', 0, 500, 0, 490, 70.0000, 150.0000, 0.0000, 0.0000, 34300.0000, 0.0000, 'Imported from inventory_logs #347', '2026-06-09 17:02:03', '2026-06-15 18:18:52'),
(428, 65, 7, NULL, 12, 'sale', '365', 'inventory_log', 491, 0, 1, 490, 70.0000, 150.0000, 34370.0000, 150.0000, 34300.0000, 80.0000, 'Imported from inventory_logs #365', '2026-06-09 20:58:10', '2026-06-15 18:18:52'),
(429, 65, 7, NULL, 1, 'sale', '384', 'inventory_log', 491, 0, 1, 490, 70.0000, 150.0000, 34370.0000, 150.0000, 34300.0000, 80.0000, 'Imported from inventory_logs #384', '2026-06-09 23:57:38', '2026-06-15 18:18:52'),
(430, 65, 7, NULL, 1, 'sale', '390', 'inventory_log', 491, 0, 1, 490, 70.0000, 150.0000, 34370.0000, 150.0000, 34300.0000, 80.0000, 'Imported from inventory_logs #390', '2026-06-10 00:26:29', '2026-06-15 18:18:52'),
(431, 65, 7, NULL, 1, 'sale', '398', 'inventory_log', 492, 0, 2, 490, 70.0000, 150.0000, 34440.0000, 300.0000, 34300.0000, 160.0000, 'Imported from inventory_logs #398', '2026-06-11 03:14:25', '2026-06-15 18:18:52'),
(432, 65, 7, NULL, 1, 'sale', '403', 'inventory_log', 491, 0, 1, 490, 70.0000, 150.0000, 34370.0000, 150.0000, 34300.0000, 80.0000, 'Imported from inventory_logs #403', '2026-06-11 03:18:38', '2026-06-15 18:18:52'),
(433, 65, 7, NULL, 1, 'sale', '415', 'inventory_log', 494, 0, 4, 490, 70.0000, 150.0000, 34580.0000, 600.0000, 34300.0000, 320.0000, 'Imported from inventory_logs #415', '2026-06-11 03:23:58', '2026-06-15 18:18:52'),
(512, 16, 1, NULL, 1, 'manual_edit', '438', 'inventory_log', 130, 20, 0, 150, 500.0000, 4000.0000, 65000.0000, 0.0000, 75000.0000, 0.0000, 'Stock adjusted via inventory (restock) — log #438', '2026-06-15 19:30:16', '2026-06-15 19:30:16'),
(513, 9, 1, NULL, 4, 'sale', '443', 'inventory_log', 131, 0, 1, 130, 150000.0000, 200000.0000, 19650000.0000, 200000.0000, 19500000.0000, 50000.0000, 'Sale #211 — receipt R1-20260615195659-4', '2026-06-15 19:57:00', '2026-06-15 19:57:00'),
(514, 26, 1, NULL, 4, 'sale', '444', 'inventory_log', 800, 0, 1, 799, 1500.0000, 3000.0000, 1200000.0000, 3000.0000, 1198500.0000, 1500.0000, 'Sale #212 — receipt R1-20260615200210-4', '2026-06-15 20:02:10', '2026-06-15 20:02:10'),
(515, 29, 1, NULL, 4, 'sale', '445', 'inventory_log', 40, 0, 1, 39, 2000.0000, 5000.0000, 80000.0000, 5000.0000, 78000.0000, 3000.0000, 'Sale #212 — receipt R1-20260615200210-4', '2026-06-15 20:02:10', '2026-06-15 20:02:10'),
(516, 9, 1, NULL, 4, 'sale', '448', 'inventory_log', 69, 0, 9, 60, 150000.0000, 200000.0000, 10350000.0000, 1800000.0000, 9000000.0000, 450000.0000, 'Sale #213 — receipt R1-20260615200722-4', '2026-06-15 20:07:23', '2026-06-15 20:07:23'),
(517, 9, 1, NULL, 1, 'manual_edit', '449', 'inventory_log', 60, 40, 0, 100, 150000.0000, 200000.0000, 9000000.0000, 0.0000, 15000000.0000, 0.0000, 'Stock adjusted via inventory (restock) — log #449', '2026-06-15 20:08:52', '2026-06-15 20:08:52'),
(518, 9, 1, NULL, 4, 'sale', '450', 'inventory_log', 100, 0, 50, 50, 150000.0000, 200000.0000, 15000000.0000, 10000000.0000, 7500000.0000, 2500000.0000, 'Sale #214 — receipt R1-20260615201028-4', '2026-06-15 20:10:29', '2026-06-15 20:10:29'),
(519, 47, 1, NULL, 1, 'manual_edit', '451', 'inventory_log', 29, 71, 0, 100, 8000.0000, 18000.0000, 232000.0000, 0.0000, 800000.0000, 0.0000, 'Stock adjusted via inventory (restock) — log #451', '2026-06-15 20:11:45', '2026-06-15 20:11:45'),
(520, 57, 7, NULL, 13, 'manual_edit', '453', 'inventory_log', 134, 21, 0, 155, 350.0000, 500.0000, 46900.0000, 0.0000, 54250.0000, 0.0000, 'Stock adjusted via inventory (restock) — log #453', '2026-06-15 21:17:16', '2026-06-15 21:17:16'),
(521, 57, 7, NULL, 13, 'sale', '454', 'inventory_log', 155, 0, 5, 150, 350.0000, 500.0000, 54250.0000, 2500.0000, 52500.0000, 750.0000, 'Sale #215 — receipt R7-20260615215247-13', '2026-06-15 21:52:47', '2026-06-15 21:52:47'),
(522, 59, 7, NULL, 13, 'sale', '455', 'inventory_log', 71, 0, 1, 70, 150.0000, 200.0000, 10650.0000, 200.0000, 10500.0000, 50.0000, 'Sale #216 — receipt R7-20260615215259-13', '2026-06-15 21:52:59', '2026-06-15 21:52:59'),
(523, 42, 1, NULL, 4, 'sale', '458', 'inventory_log', 299, 0, 99, 200, 3998.0000, 12000.0000, 1195402.0000, 1188000.0000, 799600.0000, 792198.0000, 'Sale #217 — receipt R1-20260615231317-4', '2026-06-15 23:13:17', '2026-06-15 23:13:17'),
(524, 53, 1, NULL, 4, 'sale', '459', 'inventory_log', 29, 0, 9, 20, 45000.0000, 6999.0000, 1305000.0000, 62991.0000, 900000.0000, -342009.0000, 'Sale #218 — receipt R1-20260615231445-4', '2026-06-15 23:14:46', '2026-06-15 23:14:46');
INSERT INTO `inventory_ledger` (`id`, `product_id`, `store_id`, `warehouse_id`, `user_id`, `movement_type`, `reference_id`, `reference_type`, `opening_stock`, `stock_in`, `stock_out`, `current_stock`, `purchase_price`, `selling_price`, `opening_stock_value`, `stock_out_value`, `current_stock_value`, `estimated_profit`, `notes`, `movement_date`, `created_at`) VALUES
(525, 42, 1, NULL, 4, 'damaged', '217', 'sale_return', 200, 0, 9, 200, 3998.0000, 12000.0000, 799600.0000, 108000.0000, 799600.0000, 72018.0000, 'Return damage — sale #217 (R1-20260615231317-4)', '2026-06-15 23:16:29', '2026-06-15 23:16:29'),
(526, 10, 1, NULL, 1, 'sale', '460', 'inventory_log', 87, 0, 7, 80, 150000.0000, 18000.0000, 13050000.0000, 126000.0000, 12000000.0000, -924000.0000, 'Sale #219 — receipt R1-20260616012143-1', '2026-06-16 01:21:44', '2026-06-16 01:21:44'),
(527, 14, 1, NULL, 1, 'sale', '461', 'inventory_log', 882, 0, 2, 880, 500.0000, 400000.0000, 441000.0000, 800000.0000, 440000.0000, 799000.0000, 'Sale #220 — receipt R1-20260616012636-1', '2026-06-16 01:26:36', '2026-06-16 01:26:36'),
(528, 41, 1, NULL, 1, 'sale', '462', 'inventory_log', 49, 0, 9, 40, 8000.0000, 17000.0000, 392000.0000, 153000.0000, 320000.0000, 81000.0000, 'Sale #221 — receipt R1-20260616013048-1', '2026-06-16 01:30:48', '2026-06-16 01:30:48'),
(529, 17, 1, NULL, 4, 'sale', '463', 'inventory_log', 26, 0, 1, 25, 3000.0000, 5000.0000, 78000.0000, 5000.0000, 75000.0000, 2000.0000, 'Sale #222 — receipt R1-20260616014122-4', '2026-06-16 01:41:23', '2026-06-16 01:41:23'),
(530, 56, 7, NULL, 1, 'sale', '464', 'inventory_log', 298, 0, 2, 296, 2500.0000, 400.0000, 745000.0000, 800.0000, 740000.0000, -4200.0000, 'Sale #223 — receipt R7-20260616020849-1', '2026-06-16 02:08:49', '2026-06-16 02:08:49'),
(531, 17, 1, NULL, 4, 'sale', '465', 'inventory_log', 25, 0, 1, 24, 3000.0000, 5000.0000, 75000.0000, 5000.0000, 72000.0000, 2000.0000, 'Sale #224 — receipt R1-20260616023839-4', '2026-06-16 02:38:40', '2026-06-16 02:38:40'),
(532, 17, 1, NULL, 4, 'sale', '466', 'inventory_log', 24, 0, 1, 23, 3000.0000, 5000.0000, 72000.0000, 5000.0000, 69000.0000, 2000.0000, 'Sale #225 — receipt R1-20260616023851-4', '2026-06-16 02:38:52', '2026-06-16 02:38:52'),
(533, 17, 1, NULL, 4, 'sale', '467', 'inventory_log', 23, 0, 3, 20, 3000.0000, 5000.0000, 69000.0000, 15000.0000, 60000.0000, 6000.0000, 'Sale #226 — receipt R1-20260616023909-4', '2026-06-16 02:39:09', '2026-06-16 02:39:09'),
(534, 46, 1, NULL, 4, 'sale', '468', 'inventory_log', 299, 0, 9, 290, 23000.0000, 40000.0000, 6877000.0000, 360000.0000, 6670000.0000, 153000.0000, 'Sale #227 — receipt R1-20260616024005-4', '2026-06-16 02:40:05', '2026-06-16 02:40:05'),
(535, 44, 1, NULL, 4, 'sale', '469', 'inventory_log', 199, 0, 9, 190, 11000.0000, 28000.0000, 2189000.0000, 252000.0000, 2090000.0000, 153000.0000, 'Sale #228 — receipt R1-20260616025340-4', '2026-06-16 02:53:41', '2026-06-16 02:53:41'),
(536, 13, 1, NULL, 3, 'sale', '470', 'inventory_log', 65, 0, 5, 60, 1500.0000, 2000.0000, 97500.0000, 10000.0000, 90000.0000, 2500.0000, 'Sale #229 — receipt R1-20260616030902-3', '2026-06-16 03:09:03', '2026-06-16 03:09:03'),
(537, 44, 1, NULL, 4, 'adjustment', '471', 'inventory_log', 190, 9, 0, 199, 11000.0000, 28000.0000, 2090000.0000, 0.0000, 2189000.0000, 0.0000, 'Synced from inventory_logs #471 (restock)', '2026-06-16 03:25:15', '2026-06-16 03:25:15'),
(538, 25, 1, NULL, 4, 'sale', '472', 'inventory_log', 45, 0, 5, 40, 150.0000, 300.0000, 6750.0000, 1500.0000, 6000.0000, 750.0000, 'Sale #230 — receipt R1-20260616034520-4', '2026-06-16 03:45:21', '2026-06-16 03:45:21'),
(539, 44, 1, NULL, 4, 'sale', '473', 'inventory_log', 199, 0, 9, 190, 11000.0000, 28000.0000, 2189000.0000, 252000.0000, 2090000.0000, 153000.0000, 'Sale #231 — receipt R1-20260616034900-4', '2026-06-16 03:49:01', '2026-06-16 03:49:01'),
(540, 27, 1, NULL, 1, 'sale', '474', 'inventory_log', 3000, 0, 1000, 2000, 2000.0000, 4500.0000, 6000000.0000, 4500000.0000, 4000000.0000, 2500000.0000, 'Sale #232 — receipt R1-20260616191600-1', '2026-06-16 19:15:59', '2026-06-16 19:15:59'),
(541, 14, 1, NULL, 1, 'sale', '475', 'inventory_log', 880, 0, 80, 800, 500.0000, 400000.0000, 440000.0000, 32000000.0000, 400000.0000, 31960000.0000, 'Sale #233 — receipt R1-20260616191902-1', '2026-06-16 19:19:01', '2026-06-16 19:19:01'),
(542, 46, 1, NULL, 1, 'sale', '476', 'inventory_log', 290, 0, 40, 250, 23000.0000, 40000.0000, 6670000.0000, 1600000.0000, 5750000.0000, 680000.0000, 'Sale #233 — receipt R1-20260616191902-1', '2026-06-16 19:19:01', '2026-06-16 19:19:01'),
(543, 44, 1, NULL, 1, 'sale', '477', 'inventory_log', 190, 0, 40, 150, 11000.0000, 28000.0000, 2090000.0000, 1120000.0000, 1650000.0000, 680000.0000, 'Sale #233 — receipt R1-20260616191902-1', '2026-06-16 19:19:01', '2026-06-16 19:19:01'),
(544, 14, 1, NULL, 1, 'sale', '478', 'inventory_log', 800, 0, 3, 797, 500.0000, 400000.0000, 400000.0000, 1200000.0000, 398500.0000, 1198500.0000, 'Sale #234 — receipt R1-20260616192733-1', '2026-06-16 19:27:32', '2026-06-16 19:27:32'),
(545, 46, 1, NULL, 1, 'sale', '479', 'inventory_log', 250, 0, 4, 246, 23000.0000, 40000.0000, 5750000.0000, 160000.0000, 5658000.0000, 68000.0000, 'Sale #234 — receipt R1-20260616192733-1', '2026-06-16 19:27:32', '2026-06-16 19:27:32'),
(546, 46, 1, NULL, 3, 'sale', '480', 'inventory_log', 246, 0, 6, 240, 23000.0000, 40000.0000, 5658000.0000, 240000.0000, 5520000.0000, 102000.0000, 'Sale #235 — receipt R1-20260616195147-3', '2026-06-16 19:51:46', '2026-06-16 19:51:46'),
(547, 10, 1, NULL, 3, 'sale', '481', 'inventory_log', 80, 0, 5, 75, 150000.0000, 18000.0000, 12000000.0000, 90000.0000, 11250000.0000, -660000.0000, 'Sale #236 — receipt R1-20260616195223-3', '2026-06-16 19:52:21', '2026-06-16 19:52:21'),
(548, 10, 1, NULL, 3, 'sale', '482', 'inventory_log', 75, 0, 5, 70, 150000.0000, 18000.0000, 11250000.0000, 90000.0000, 10500000.0000, -660000.0000, 'Sale #237 — receipt R1-20260616195301-3', '2026-06-16 19:52:59', '2026-06-16 19:52:59'),
(549, 14, 1, NULL, 3, 'sale', '483', 'inventory_log', 797, 0, 1, 796, 500.0000, 400000.0000, 398500.0000, 400000.0000, 398000.0000, 399500.0000, 'Sale #238 — receipt R1-20260616195345-3', '2026-06-16 19:53:44', '2026-06-16 19:53:44'),
(550, 27, 1, NULL, 3, 'sale', '484', 'inventory_log', 2000, 0, 10, 1990, 2000.0000, 4500.0000, 4000000.0000, 45000.0000, 3980000.0000, 25000.0000, 'Sale #239 — receipt R1-20260616195536-3', '2026-06-16 19:55:35', '2026-06-16 19:55:35'),
(551, 14, 1, NULL, 3, 'sale', '485', 'inventory_log', 796, 0, 1, 795, 500.0000, 400000.0000, 398000.0000, 400000.0000, 397500.0000, 399500.0000, 'Sale #240 — receipt R1-20260616195714-3', '2026-06-16 19:57:13', '2026-06-16 19:57:13'),
(552, 14, 1, NULL, 3, 'adjustment', '486', 'inventory_log', 795, 1, 0, 796, 500.0000, 400000.0000, 397500.0000, 0.0000, 398000.0000, 0.0000, 'Synced from inventory_logs #486 (restock)', '2026-06-16 20:00:42', '2026-06-16 20:00:42'),
(553, 14, 1, NULL, 3, 'sale', '487', 'inventory_log', 796, 0, 1, 795, 500.0000, 400000.0000, 398000.0000, 400000.0000, 397500.0000, 399500.0000, 'Sale #241 — receipt R1-20260616211401-3', '2026-06-16 21:13:59', '2026-06-16 21:13:59'),
(554, 14, 1, NULL, 4, 'sale', '488', 'inventory_log', 795, 0, 5, 790, 500.0000, 400000.0000, 397500.0000, 2000000.0000, 395000.0000, 1997500.0000, 'Sale #242 — receipt R1-20260617005306-4', '2026-06-17 00:53:07', '2026-06-17 00:53:07'),
(555, 14, 1, NULL, 4, 'sale', '489', 'inventory_log', 790, 0, 1, 789, 500.0000, 400000.0000, 395000.0000, 400000.0000, 394500.0000, 399500.0000, 'Sale #243 — receipt R1-20260617021742-4', '2026-06-17 02:17:40', '2026-06-17 02:17:40'),
(556, 14, 1, NULL, 4, 'sale', '490', 'inventory_log', 789, 0, 1, 788, 500.0000, 400000.0000, 394500.0000, 400000.0000, 394000.0000, 399500.0000, 'Sale #244 — receipt R1-20260617021755-4', '2026-06-17 02:17:53', '2026-06-17 02:17:53'),
(557, 61, 7, NULL, 1, 'sale', '491', 'inventory_log', 98, 0, 8, 90, 200.0000, 380.0000, 19600.0000, 3040.0000, 18000.0000, 1440.0000, 'Sale #245 — receipt R7-20260617025826-1', '2026-06-17 02:58:24', '2026-06-17 02:58:24'),
(558, 62, 7, NULL, 1, 'sale', '492', 'inventory_log', 86, 0, 6, 80, 180.0000, 300.0000, 15480.0000, 1800.0000, 14400.0000, 720.0000, 'Sale #245 — receipt R7-20260617025826-1', '2026-06-17 02:58:24', '2026-06-17 02:58:24'),
(559, 56, 7, NULL, 1, 'sale', '493', 'inventory_log', 296, 0, 6, 290, 2500.0000, 400.0000, 740000.0000, 2400.0000, 725000.0000, -12600.0000, 'Sale #245 — receipt R7-20260617025826-1', '2026-06-17 02:58:24', '2026-06-17 02:58:24'),
(560, 58, 7, NULL, 1, 'sale', '494', 'inventory_log', 293, 0, 3, 290, 150.0000, 200.0000, 43950.0000, 600.0000, 43500.0000, 150.0000, 'Sale #245 — receipt R7-20260617025826-1', '2026-06-17 02:58:24', '2026-06-17 02:58:24'),
(561, 59, 7, NULL, 1, 'sale', '495', 'inventory_log', 70, 0, 1, 69, 150.0000, 200.0000, 10500.0000, 200.0000, 10350.0000, 50.0000, 'Sale #246 — receipt R7-20260617032721-1', '2026-06-17 03:27:19', '2026-06-17 03:27:19'),
(562, 14, 1, NULL, 4, 'damaged', '244', 'sale_return', 788, 0, 1, 788, 500.0000, 400000.0000, 394000.0000, 400000.0000, 394000.0000, 399500.0000, 'Return damage — sale #244 (R1-20260617021755-4)', '2026-06-17 04:08:00', '2026-06-17 04:08:00'),
(563, 14, 1, NULL, 3, 'sale', '496', 'inventory_log', 788, 0, 5, 783, 500.0000, 400000.0000, 394000.0000, 2000000.0000, 391500.0000, 1997500.0000, 'Sale #247 — receipt R1-20260617041554-3', '2026-06-17 04:15:54', '2026-06-17 04:15:54'),
(564, 26, 1, NULL, 3, 'sale', '497', 'inventory_log', 800, 0, 230, 570, 1500.0000, 3000.0000, 1200000.0000, 690000.0000, 855000.0000, 345000.0000, 'Sale #248 — receipt R1-20260617041637-3', '2026-06-17 04:16:38', '2026-06-17 04:16:38'),
(565, 14, 1, NULL, 3, 'sale', '498', 'inventory_log', 783, 0, 40, 743, 500.0000, 400000.0000, 391500.0000, 16000000.0000, 371500.0000, 15980000.0000, 'Sale #249 — receipt R1-20260617041717-3', '2026-06-17 04:17:17', '2026-06-17 04:17:17'),
(566, 25, 1, NULL, 1, 'adjustment', '499', 'inventory_log', 40, 5, 0, 45, 150.0000, 300.0000, 6000.0000, 0.0000, 6750.0000, 0.0000, 'Stock adjusted via inventory (restock) — log #499', '2026-06-17 04:20:17', '2026-06-17 04:20:17'),
(567, 14, 1, NULL, 3, 'sale', '500', 'inventory_log', 743, 0, 3, 740, 500.0000, 400000.0000, 371500.0000, 1200000.0000, 370000.0000, 1198500.0000, 'Sale #250 — receipt R1-20260617042751-3', '2026-06-17 04:27:51', '2026-06-17 04:27:51'),
(568, 25, 1, NULL, 3, 'sale', '501', 'inventory_log', 45, 0, 5, 40, 150.0000, 300.0000, 6750.0000, 1500.0000, 6000.0000, 750.0000, 'Sale #251 — receipt R1-20260617042824-3', '2026-06-17 04:28:24', '2026-06-17 04:28:24'),
(569, 13, 1, NULL, 3, 'sale', '502', 'inventory_log', 60, 0, 1, 59, 1500.0000, 2000.0000, 90000.0000, 2000.0000, 88500.0000, 500.0000, 'Sale #252 — receipt R1-20260617042929-3', '2026-06-17 04:29:29', '2026-06-17 04:29:29'),
(570, 75, 11, NULL, 1, 'sale', '509', 'inventory_log', 120, 0, 1, 119, 7.0000, 10.0000, 840.0000, 10.0000, 833.0000, 3.0000, 'Sale #253 — receipt R11-20260617090203-1', '2026-06-17 09:02:01', '2026-06-17 09:02:01'),
(571, 75, 11, NULL, 1, 'sale', '510', 'inventory_log', 119, 0, 1, 118, 7.0000, 10.0000, 833.0000, 10.0000, 826.0000, 3.0000, 'Sale #254 — receipt R11-20260617090400-1', '2026-06-17 09:03:58', '2026-06-17 09:03:58'),
(572, 75, 11, NULL, 1, 'sale', '511', 'inventory_log', 118, 0, 1, 117, 7.0000, 10.0000, 826.0000, 10.0000, 819.0000, 3.0000, 'Sale #255 — receipt R11-20260617090810-1', '2026-06-17 09:08:08', '2026-06-17 09:08:08'),
(573, 74, 11, NULL, 1, 'sale', '512', 'inventory_log', 100, 0, 2, 98, 11.0000, 20.0000, 1100.0000, 40.0000, 1078.0000, 18.0000, 'Sale #256 — receipt R11-20260617093716-1', '2026-06-17 09:37:17', '2026-06-17 09:37:17'),
(574, 75, 11, NULL, 1, 'sale', '513', 'inventory_log', 117, 0, 1, 116, 7.0000, 10.0000, 819.0000, 10.0000, 812.0000, 3.0000, 'Sale #257 — receipt R11-20260617094634-1', '2026-06-17 09:46:34', '2026-06-17 09:46:34'),
(575, 74, 11, NULL, 1, 'sale', '514', 'inventory_log', 98, 0, 1, 97, 11.0000, 20.0000, 1078.0000, 20.0000, 1067.0000, 9.0000, 'Sale #257 — receipt R11-20260617094634-1', '2026-06-17 09:46:34', '2026-06-17 09:46:34'),
(576, 73, 11, NULL, 1, 'sale', '515', 'inventory_log', 100, 0, 1, 99, 8.0000, 15.0000, 800.0000, 15.0000, 792.0000, 7.0000, 'Sale #257 — receipt R11-20260617094634-1', '2026-06-17 09:46:34', '2026-06-17 09:46:34'),
(577, 77, 11, NULL, 1, 'sale', '516', 'inventory_log', 10, 0, 1, 9, 60.0000, 160.0000, 600.0000, 160.0000, 540.0000, 100.0000, 'Sale #257 — receipt R11-20260617094634-1', '2026-06-17 09:46:34', '2026-06-17 09:46:34'),
(578, 78, 11, NULL, 1, 'sale', '517', 'inventory_log', 100, 0, 1, 99, 9.0000, 15.0000, 900.0000, 15.0000, 891.0000, 6.0000, 'Sale #257 — receipt R11-20260617094634-1', '2026-06-17 09:46:34', '2026-06-17 09:46:34'),
(579, 78, 11, NULL, 1, 'sale', '518', 'inventory_log', 99, 0, 1, 98, 9.0000, 15.0000, 891.0000, 15.0000, 882.0000, 6.0000, 'Sale #258 — receipt R11-20260617095622-1', '2026-06-17 09:56:20', '2026-06-17 09:56:20'),
(580, 75, 11, NULL, 1, 'sale', '519', 'inventory_log', 116, 0, 1, 115, 7.0000, 10.0000, 812.0000, 10.0000, 805.0000, 3.0000, 'Sale #259 — receipt R11-20260617102839-1', '2026-06-17 10:28:39', '2026-06-17 10:28:39'),
(581, 75, 11, NULL, 1, 'sale', '520', 'inventory_log', 115, 0, 1, 114, 7.0000, 10.0000, 805.0000, 10.0000, 798.0000, 3.0000, 'Sale #260 — receipt R11-20260617105123-1', '2026-06-17 10:51:21', '2026-06-17 10:51:21'),
(582, 73, 11, NULL, 1, 'sale', '538', 'inventory_log', 99, 0, 4, 95, 8.0000, 15.0000, 792.0000, 60.0000, 760.0000, 28.0000, 'Sale #261 — receipt R11-20260617185957-1', '2026-06-17 18:59:57', '2026-06-17 18:59:57'),
(583, 79, 11, NULL, 1, 'sale', '539', 'inventory_log', 120, 0, 1, 119, 10.0000, 15.0000, 1200.0000, 15.0000, 1190.0000, 5.0000, 'Sale #261 — receipt R11-20260617185957-1', '2026-06-17 18:59:57', '2026-06-17 18:59:57'),
(584, 80, 11, NULL, 1, 'sale', '540', 'inventory_log', 120, 0, 1, 119, 10.0000, 15.0000, 1200.0000, 15.0000, 1190.0000, 5.0000, 'Sale #261 — receipt R11-20260617185957-1', '2026-06-17 18:59:57', '2026-06-17 18:59:57'),
(585, 90, 11, NULL, 1, 'sale', '541', 'inventory_log', 120, 0, 1, 119, 10.0000, 15.0000, 1200.0000, 15.0000, 1190.0000, 5.0000, 'Sale #261 — receipt R11-20260617185957-1', '2026-06-17 18:59:57', '2026-06-17 18:59:57'),
(586, 79, 11, NULL, 1, 'sale', '542', 'inventory_log', 119, 0, 3, 116, 10.0000, 15.0000, 1190.0000, 45.0000, 1160.0000, 15.0000, 'Sale #262 — receipt R11-20260618022909-1', '2026-06-18 02:29:09', '2026-06-18 02:29:09'),
(587, 75, 11, NULL, 1, 'sale', '543', 'inventory_log', 114, 0, 82, 32, 7.0000, 10.0000, 798.0000, 820.0000, 224.0000, 246.0000, 'Sale #262 — receipt R11-20260618022909-1', '2026-06-18 02:29:09', '2026-06-18 02:29:09'),
(588, 74, 11, NULL, 1, 'sale', '544', 'inventory_log', 97, 0, 8, 89, 11.0000, 20.0000, 1067.0000, 160.0000, 979.0000, 72.0000, 'Sale #262 — receipt R11-20260618022909-1', '2026-06-18 02:29:09', '2026-06-18 02:29:09'),
(589, 78, 11, NULL, 1, 'sale', '545', 'inventory_log', 98, 0, 1, 97, 9.0000, 15.0000, 882.0000, 15.0000, 873.0000, 6.0000, 'Sale #262 — receipt R11-20260618022909-1', '2026-06-18 02:29:09', '2026-06-18 02:29:09'),
(590, 81, 11, NULL, 1, 'sale', '546', 'inventory_log', 120, 0, 1, 119, 10.0000, 15.0000, 1200.0000, 15.0000, 1190.0000, 5.0000, 'Sale #262 — receipt R11-20260618022909-1', '2026-06-18 02:29:09', '2026-06-18 02:29:09'),
(591, 82, 11, NULL, 1, 'sale', '547', 'inventory_log', 120, 0, 1, 119, 28.0000, 35.0000, 3360.0000, 35.0000, 3332.0000, 7.0000, 'Sale #262 — receipt R11-20260618022909-1', '2026-06-18 02:29:09', '2026-06-18 02:29:09'),
(592, 77, 11, NULL, 1, 'sale', '548', 'inventory_log', 9, 0, 3, 6, 60.0000, 160.0000, 540.0000, 480.0000, 360.0000, 300.0000, 'Sale #262 — receipt R11-20260618022909-1', '2026-06-18 02:29:09', '2026-06-18 02:29:09'),
(593, 73, 11, NULL, 1, 'sale', '549', 'inventory_log', 95, 0, 2, 93, 8.0000, 15.0000, 760.0000, 30.0000, 744.0000, 14.0000, 'Sale #262 — receipt R11-20260618022909-1', '2026-06-18 02:29:09', '2026-06-18 02:29:09'),
(594, 95, 11, NULL, 1, 'sale', '550', 'inventory_log', 120, 0, 1, 119, 10.0000, 15.0000, 1200.0000, 15.0000, 1190.0000, 5.0000, 'Sale #263 — receipt R11-20260618024825-1', '2026-06-18 02:48:26', '2026-06-18 02:48:26'),
(595, 94, 11, NULL, 1, 'sale', '551', 'inventory_log', 120, 0, 1, 119, 30.0000, 45.0000, 3600.0000, 45.0000, 3570.0000, 15.0000, 'Sale #263 — receipt R11-20260618024825-1', '2026-06-18 02:48:26', '2026-06-18 02:48:26'),
(596, 93, 11, NULL, 1, 'sale', '552', 'inventory_log', 120, 0, 1, 119, 30.0000, 45.0000, 3600.0000, 45.0000, 3570.0000, 15.0000, 'Sale #263 — receipt R11-20260618024825-1', '2026-06-18 02:48:26', '2026-06-18 02:48:26'),
(597, 86, 11, NULL, 1, 'sale', '553', 'inventory_log', 120, 0, 1, 119, 10.0000, 15.0000, 1200.0000, 15.0000, 1190.0000, 5.0000, 'Sale #263 — receipt R11-20260618024825-1', '2026-06-18 02:48:26', '2026-06-18 02:48:26'),
(598, 87, 11, NULL, 1, 'sale', '554', 'inventory_log', 120, 0, 1, 119, 10.0000, 15.0000, 1200.0000, 15.0000, 1190.0000, 5.0000, 'Sale #263 — receipt R11-20260618024825-1', '2026-06-18 02:48:26', '2026-06-18 02:48:26'),
(599, 88, 11, NULL, 1, 'sale', '555', 'inventory_log', 120, 0, 2, 118, 10.0000, 15.0000, 1200.0000, 30.0000, 1180.0000, 10.0000, 'Sale #263 — receipt R11-20260618024825-1', '2026-06-18 02:48:26', '2026-06-18 02:48:26'),
(600, 90, 11, NULL, 1, 'sale', '556', 'inventory_log', 119, 0, 1, 118, 10.0000, 15.0000, 1190.0000, 15.0000, 1180.0000, 5.0000, 'Sale #263 — receipt R11-20260618024825-1', '2026-06-18 02:48:26', '2026-06-18 02:48:26'),
(601, 91, 11, NULL, 1, 'sale', '557', 'inventory_log', 120, 0, 1, 119, 10.0000, 15.0000, 1200.0000, 15.0000, 1190.0000, 5.0000, 'Sale #263 — receipt R11-20260618024825-1', '2026-06-18 02:48:26', '2026-06-18 02:48:26'),
(602, 77, 11, NULL, 1, 'sale', '558', 'inventory_log', 6, 0, 1, 5, 60.0000, 160.0000, 360.0000, 160.0000, 300.0000, 100.0000, 'Sale #263 — receipt R11-20260618024825-1', '2026-06-18 02:48:26', '2026-06-18 02:48:26'),
(603, 75, 11, NULL, 1, 'sale', '559', 'inventory_log', 32, 0, 1, 31, 7.0000, 10.0000, 224.0000, 10.0000, 217.0000, 3.0000, 'Sale #264 — receipt R11-20260618024938-1', '2026-06-18 02:49:38', '2026-06-18 02:49:38'),
(604, 74, 11, NULL, 1, 'sale', '560', 'inventory_log', 89, 0, 1, 88, 11.0000, 20.0000, 979.0000, 20.0000, 968.0000, 9.0000, 'Sale #264 — receipt R11-20260618024938-1', '2026-06-18 02:49:38', '2026-06-18 02:49:38'),
(605, 73, 11, NULL, 1, 'sale', '561', 'inventory_log', 93, 0, 1, 92, 8.0000, 15.0000, 744.0000, 15.0000, 736.0000, 7.0000, 'Sale #264 — receipt R11-20260618024938-1', '2026-06-18 02:49:38', '2026-06-18 02:49:38'),
(606, 81, 11, NULL, 1, 'sale', '562', 'inventory_log', 119, 0, 1, 118, 10.0000, 15.0000, 1190.0000, 15.0000, 1180.0000, 5.0000, 'Sale #264 — receipt R11-20260618024938-1', '2026-06-18 02:49:38', '2026-06-18 02:49:38'),
(607, 82, 11, NULL, 1, 'sale', '563', 'inventory_log', 119, 0, 1, 118, 28.0000, 35.0000, 3332.0000, 35.0000, 3304.0000, 7.0000, 'Sale #264 — receipt R11-20260618024938-1', '2026-06-18 02:49:38', '2026-06-18 02:49:38'),
(608, 83, 11, NULL, 1, 'sale', '564', 'inventory_log', 120, 0, 1, 119, 28.0000, 35.0000, 3360.0000, 35.0000, 3332.0000, 7.0000, 'Sale #264 — receipt R11-20260618024938-1', '2026-06-18 02:49:38', '2026-06-18 02:49:38'),
(609, 84, 11, NULL, 1, 'sale', '565', 'inventory_log', 120, 0, 1, 119, 10.0000, 15.0000, 1200.0000, 15.0000, 1190.0000, 5.0000, 'Sale #264 — receipt R11-20260618024938-1', '2026-06-18 02:49:38', '2026-06-18 02:49:38'),
(610, 85, 11, NULL, 1, 'sale', '566', 'inventory_log', 120, 0, 25, 95, 10.0000, 15.0000, 1200.0000, 375.0000, 950.0000, 125.0000, 'Sale #264 — receipt R11-20260618024938-1', '2026-06-18 02:49:38', '2026-06-18 02:49:38'),
(611, 78, 11, NULL, 1, 'sale', '567', 'inventory_log', 97, 0, 3, 94, 9.0000, 15.0000, 873.0000, 45.0000, 846.0000, 18.0000, 'Sale #264 — receipt R11-20260618024938-1', '2026-06-18 02:49:38', '2026-06-18 02:49:38'),
(612, 87, 11, NULL, 1, 'sale', '568', 'inventory_log', 119, 0, 3, 116, 10.0000, 15.0000, 1190.0000, 45.0000, 1160.0000, 15.0000, 'Sale #264 — receipt R11-20260618024938-1', '2026-06-18 02:49:38', '2026-06-18 02:49:38'),
(613, 86, 11, NULL, 1, 'sale', '569', 'inventory_log', 119, 0, 2, 117, 10.0000, 15.0000, 1190.0000, 30.0000, 1170.0000, 10.0000, 'Sale #264 — receipt R11-20260618024938-1', '2026-06-18 02:49:38', '2026-06-18 02:49:38'),
(614, 94, 11, NULL, 1, 'sale', '570', 'inventory_log', 119, 0, 8, 111, 30.0000, 45.0000, 3570.0000, 360.0000, 3330.0000, 120.0000, 'Sale #264 — receipt R11-20260618024938-1', '2026-06-18 02:49:38', '2026-06-18 02:49:38'),
(615, 93, 11, NULL, 1, 'sale', '571', 'inventory_log', 119, 0, 1, 118, 30.0000, 45.0000, 3570.0000, 45.0000, 3540.0000, 15.0000, 'Sale #264 — receipt R11-20260618024938-1', '2026-06-18 02:49:38', '2026-06-18 02:49:38'),
(616, 88, 11, NULL, 1, 'sale', '572', 'inventory_log', 118, 0, 1, 117, 10.0000, 15.0000, 1180.0000, 15.0000, 1170.0000, 5.0000, 'Sale #265 — receipt R11-20260618025713-1', '2026-06-18 02:57:13', '2026-06-18 02:57:13'),
(617, 89, 11, NULL, 1, 'sale', '573', 'inventory_log', 120, 0, 1, 119, 10.0000, 15.0000, 1200.0000, 15.0000, 1190.0000, 5.0000, 'Sale #265 — receipt R11-20260618025713-1', '2026-06-18 02:57:13', '2026-06-18 02:57:13'),
(618, 90, 11, NULL, 1, 'sale', '574', 'inventory_log', 118, 0, 1, 117, 10.0000, 15.0000, 1180.0000, 15.0000, 1170.0000, 5.0000, 'Sale #265 — receipt R11-20260618025713-1', '2026-06-18 02:57:13', '2026-06-18 02:57:13'),
(619, 75, 11, NULL, 1, 'sale', '575', 'inventory_log', 31, 0, 1, 30, 7.0000, 10.0000, 217.0000, 10.0000, 210.0000, 3.0000, 'Sale #266 — receipt R11-20260618033141-1', '2026-06-18 03:31:41', '2026-06-18 03:31:41'),
(620, 79, 11, NULL, 1, 'sale', '576', 'inventory_log', 116, 0, 1, 115, 10.0000, 15.0000, 1160.0000, 15.0000, 1150.0000, 5.0000, 'Sale #266 — receipt R11-20260618033141-1', '2026-06-18 03:31:41', '2026-06-18 03:31:41'),
(621, 81, 11, NULL, 1, 'sale', '577', 'inventory_log', 118, 0, 3, 115, 10.0000, 15.0000, 1180.0000, 45.0000, 1150.0000, 15.0000, 'Sale #266 — receipt R11-20260618033141-1', '2026-06-18 03:31:41', '2026-06-18 03:31:41'),
(622, 82, 11, NULL, 1, 'sale', '578', 'inventory_log', 118, 0, 3, 115, 28.0000, 35.0000, 3304.0000, 105.0000, 3220.0000, 21.0000, 'Sale #266 — receipt R11-20260618033141-1', '2026-06-18 03:31:41', '2026-06-18 03:31:41'),
(623, 93, 11, NULL, 1, 'sale', '579', 'inventory_log', 118, 0, 3, 115, 30.0000, 45.0000, 3540.0000, 135.0000, 3450.0000, 45.0000, 'Sale #266 — receipt R11-20260618033141-1', '2026-06-18 03:31:41', '2026-06-18 03:31:41'),
(624, 73, 11, NULL, 1, 'sale', '580', 'inventory_log', 92, 0, 2, 90, 8.0000, 15.0000, 736.0000, 30.0000, 720.0000, 14.0000, 'Sale #267 — receipt R11-20260618033238-1', '2026-06-18 03:32:38', '2026-06-18 03:32:38'),
(625, 78, 11, NULL, 1, 'sale', '581', 'inventory_log', 94, 0, 4, 90, 9.0000, 15.0000, 846.0000, 60.0000, 810.0000, 24.0000, 'Sale #267 — receipt R11-20260618033238-1', '2026-06-18 03:32:38', '2026-06-18 03:32:38'),
(626, 81, 11, NULL, 1, 'sale', '582', 'inventory_log', 115, 0, 1, 114, 10.0000, 15.0000, 1150.0000, 15.0000, 1140.0000, 5.0000, 'Sale #268 — receipt R11-20260618035755-1', '2026-06-18 03:57:56', '2026-06-18 03:57:56'),
(627, 87, 11, NULL, 1, 'sale', '583', 'inventory_log', 116, 0, 1, 115, 10.0000, 15.0000, 1160.0000, 15.0000, 1150.0000, 5.0000, 'Sale #269 — receipt R11-20260618035900-1', '2026-06-18 03:59:01', '2026-06-18 03:59:01'),
(628, 90, 11, NULL, 1, 'sale', '584', 'inventory_log', 117, 0, 7, 110, 10.0000, 15.0000, 1170.0000, 105.0000, 1100.0000, 35.0000, 'Sale #270 — receipt R11-20260618045018-1', '2026-06-18 04:50:19', '2026-06-18 04:50:19'),
(629, 86, 11, NULL, 1, 'sale', '585', 'inventory_log', 117, 0, 7, 110, 10.0000, 15.0000, 1170.0000, 105.0000, 1100.0000, 35.0000, 'Sale #270 — receipt R11-20260618045018-1', '2026-06-18 04:50:19', '2026-06-18 04:50:19'),
(630, 83, 11, NULL, 1, 'sale', '586', 'inventory_log', 119, 0, 9, 110, 28.0000, 35.0000, 3332.0000, 315.0000, 3080.0000, 63.0000, 'Sale #270 — receipt R11-20260618045018-1', '2026-06-18 04:50:19', '2026-06-18 04:50:19'),
(631, 80, 11, NULL, 1, 'sale', '587', 'inventory_log', 119, 0, 9, 110, 10.0000, 15.0000, 1190.0000, 135.0000, 1100.0000, 45.0000, 'Sale #271 — receipt R11-20260618045450-1', '2026-06-18 04:54:50', '2026-06-18 04:54:50'),
(632, 81, 11, NULL, 1, 'sale', '588', 'inventory_log', 114, 0, 4, 110, 10.0000, 15.0000, 1140.0000, 60.0000, 1100.0000, 20.0000, 'Sale #271 — receipt R11-20260618045450-1', '2026-06-18 04:54:50', '2026-06-18 04:54:50'),
(633, 88, 11, NULL, 1, 'sale', '589', 'inventory_log', 117, 0, 2, 115, 10.0000, 15.0000, 1170.0000, 30.0000, 1150.0000, 10.0000, 'Sale #271 — receipt R11-20260618045450-1', '2026-06-18 04:54:50', '2026-06-18 04:54:50'),
(634, 24, 6, 4, 1, 'purchase', '1', 'goods_receipt', 0, 200, 0, 200, 20000.0000, 20000.0000, 0.0000, 0.0000, 4000000.0000, 0.0000, NULL, '2026-06-20 10:23:53', '2026-06-20 10:23:53'),
(635, 96, 7, 4, 1, 'purchase', '2', 'goods_receipt', 0, 300, 0, 300, 4999.0000, 4999.0000, 0.0000, 0.0000, 1499700.0000, 0.0000, NULL, '2026-06-20 10:31:00', '2026-06-20 10:31:00'),
(636, 97, 7, 4, 1, 'purchase', '2', 'goods_receipt', 0, 100, 0, 100, 7999.0000, 7999.0000, 0.0000, 0.0000, 799900.0000, 0.0000, NULL, '2026-06-20 10:31:00', '2026-06-20 10:31:00'),
(637, 98, 7, 4, 1, 'purchase', '3', 'goods_receipt', 0, 100, 0, 100, 6999.0000, 6999.0000, 0.0000, 0.0000, 699900.0000, 0.0000, NULL, '2026-06-20 11:03:17', '2026-06-20 11:03:17'),
(638, 99, 7, 4, 1, 'purchase', '3', 'goods_receipt', 0, 400, 0, 400, 2500.0000, 2500.0000, 0.0000, 0.0000, 1000000.0000, 0.0000, NULL, '2026-06-20 11:03:17', '2026-06-20 11:03:17'),
(639, 100, 7, 4, 1, 'purchase', '3', 'goods_receipt', 0, 50, 0, 50, 2500.0000, 2500.0000, 0.0000, 0.0000, 125000.0000, 0.0000, NULL, '2026-06-20 11:03:17', '2026-06-20 11:03:17'),
(640, 101, 7, 4, 1, 'purchase', '4', 'goods_receipt', 0, 1, 0, 1, 200.0000, 200.0000, 0.0000, 0.0000, 200.0000, 0.0000, NULL, '2026-06-20 11:08:46', '2026-06-20 11:08:46'),
(641, 96, 7, 4, 1, 'purchase', '5', 'goods_receipt', 300, 700, 0, 1000, 4999.0000, 4999.0000, 1499700.0000, 0.0000, 4999000.0000, 0.0000, NULL, '2026-06-20 11:12:19', '2026-06-20 11:12:19'),
(642, 97, 7, 4, 1, 'purchase', '5', 'goods_receipt', 100, 700, 0, 800, 7999.0000, 7999.0000, 799900.0000, 0.0000, 6399200.0000, 0.0000, NULL, '2026-06-20 11:12:19', '2026-06-20 11:12:19'),
(643, 56, 7, NULL, 1, 'sale', '590', 'inventory_log', 290, 0, 10, 280, 2500.0000, 400.0000, 725000.0000, 4000.0000, 700000.0000, -21000.0000, 'Sale #272 — receipt R7-20260620231011-1', '2026-06-20 23:11:04', '2026-06-20 23:11:04'),
(644, 63, 7, NULL, 1, 'sale', '591', 'inventory_log', 277, 0, 7, 270, 40.0000, 75.0000, 11080.0000, 525.0000, 10800.0000, 245.0000, 'Sale #272 — receipt R7-20260620231011-1', '2026-06-20 23:11:04', '2026-06-20 23:11:04'),
(645, 62, 7, NULL, 1, 'sale', '592', 'inventory_log', 80, 0, 1, 79, 180.0000, 300.0000, 14400.0000, 300.0000, 14220.0000, 120.0000, 'Sale #272 — receipt R7-20260620231011-1', '2026-06-20 23:11:04', '2026-06-20 23:11:04'),
(646, 61, 7, NULL, 1, 'sale', '593', 'inventory_log', 90, 0, 2, 88, 200.0000, 380.0000, 18000.0000, 760.0000, 17600.0000, 360.0000, 'Sale #272 — receipt R7-20260620231011-1', '2026-06-20 23:11:04', '2026-06-20 23:11:04'),
(647, 60, 7, NULL, 1, 'sale', '594', 'inventory_log', 300, 0, 2, 298, 200.0000, 300.0000, 60000.0000, 600.0000, 59600.0000, 200.0000, 'Sale #272 — receipt R7-20260620231011-1', '2026-06-20 23:11:04', '2026-06-20 23:11:04'),
(648, 59, 7, NULL, 1, 'sale', '595', 'inventory_log', 69, 0, 1, 68, 150.0000, 200.0000, 10350.0000, 200.0000, 10200.0000, 50.0000, 'Sale #272 — receipt R7-20260620231011-1', '2026-06-20 23:11:04', '2026-06-20 23:11:04'),
(649, 56, 7, NULL, 1, 'sale', '596', 'inventory_log', 280, 0, 80, 200, 2500.0000, 400.0000, 700000.0000, 32000.0000, 500000.0000, -168000.0000, 'Sale #273 — receipt R7-20260620231225-1', '2026-06-20 23:12:25', '2026-06-20 23:12:25'),
(650, 59, 7, NULL, 1, 'sale', '597', 'inventory_log', 68, 0, 2, 66, 150.0000, 200.0000, 10200.0000, 400.0000, 9900.0000, 100.0000, 'Sale #274 — receipt R7-20260620231313-1', '2026-06-20 23:13:14', '2026-06-20 23:13:14'),
(651, 101, 7, NULL, 1, 'adjustment', '598', 'inventory_log', 0, 1, 0, 1, 200.0000, 200.0000, 0.0000, 0.0000, 200.0000, 0.0000, 'Stock adjusted via inventory (restock) — log #598', '2026-06-20 23:15:36', '2026-06-20 23:15:36'),
(652, 98, 7, 4, 1, 'transfer_out', '1', 'warehouse_dispatch', 100, 0, 10, 90, 6999.0000, 6999.0000, 699900.0000, 69990.0000, 629910.0000, 0.0000, NULL, '2026-06-20 23:23:08', '2026-06-20 23:23:08'),
(653, 97, 7, 4, 1, 'transfer_out', '1', 'warehouse_dispatch', 800, 0, 10, 790, 7999.0000, 7999.0000, 6399200.0000, 79990.0000, 6319210.0000, 0.0000, NULL, '2026-06-20 23:23:08', '2026-06-20 23:23:08'),
(654, 60, 7, NULL, 1, 'sale', '599', 'inventory_log', 298, 0, 3, 295, 200.0000, 300.0000, 59600.0000, 900.0000, 59000.0000, 300.0000, 'Sale #275 — receipt R7-20260621000752-1', '2026-06-21 00:07:53', '2026-06-21 00:07:53'),
(655, 56, 7, NULL, 1, 'sale', '600', 'inventory_log', 200, 0, 1, 199, 2500.0000, 400.0000, 500000.0000, 400.0000, 497500.0000, -2100.0000, 'Sale #276 — receipt R7-20260621182845-1', '2026-06-21 18:28:46', '2026-06-21 18:28:46'),
(656, 57, 7, NULL, 1, 'sale', '601', 'inventory_log', 100, 0, 10, 90, 350.0000, 500.0000, 35000.0000, 5000.0000, 31500.0000, 1500.0000, 'Sale #276 — receipt R7-20260621182845-1', '2026-06-21 18:28:46', '2026-06-21 18:28:46'),
(657, 59, 7, NULL, 1, 'sale', '602', 'inventory_log', 66, 0, 1, 65, 150.0000, 200.0000, 9900.0000, 200.0000, 9750.0000, 50.0000, 'Sale #276 — receipt R7-20260621182845-1', '2026-06-21 18:28:46', '2026-06-21 18:28:46'),
(658, 60, 7, NULL, 1, 'sale', '603', 'inventory_log', 295, 0, 1, 294, 200.0000, 300.0000, 59000.0000, 300.0000, 58800.0000, 100.0000, 'Sale #276 — receipt R7-20260621182845-1', '2026-06-21 18:28:46', '2026-06-21 18:28:46'),
(659, 9, 1, NULL, 1, 'sale', '607', 'inventory_log', 50, 0, 5, 45, 150000.0000, 200000.0000, 7500000.0000, 1000000.0000, 6750000.0000, 250000.0000, 'Sale #277 — receipt R1-20260628205229-1', '2026-06-28 20:52:30', '2026-06-28 20:52:30'),
(660, 31, 1, NULL, 1, 'sale', '608', 'inventory_log', 50, 0, 1, 49, 7000.0000, 15000.0000, 350000.0000, 15000.0000, 343000.0000, 8000.0000, 'Sale #277 — receipt R1-20260628205229-1', '2026-06-28 20:52:30', '2026-06-28 20:52:30'),
(661, 30, 1, NULL, 1, 'sale', '609', 'inventory_log', 300, 0, 1, 299, 1800.0000, 3800.0000, 540000.0000, 3800.0000, 538200.0000, 2000.0000, 'Sale #277 — receipt R1-20260628205229-1', '2026-06-28 20:52:30', '2026-06-28 20:52:30'),
(662, 44, 1, NULL, 1, 'sale', '610', 'inventory_log', 150, 0, 8, 142, 11000.0000, 28000.0000, 1650000.0000, 224000.0000, 1562000.0000, 136000.0000, 'Sale #277 — receipt R1-20260628205229-1', '2026-06-28 20:52:30', '2026-06-28 20:52:30');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `change_amount` int(11) NOT NULL,
  `reason` enum('sale','restock','damage','correction','transfer') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_logs`
--

INSERT INTO `inventory_logs` (`id`, `store_id`, `product_id`, `user_id`, `change_amount`, `reason`, `created_at`) VALUES
(1, 1, 1, 1, 100, 'restock', '2026-05-10 04:43:56'),
(2, 1, 2, 1, 500, 'restock', '2026-05-10 04:44:57'),
(3, 1, 2, 1, -5, 'sale', '2026-05-10 05:31:11'),
(4, 1, 1, 1, -40, 'sale', '2026-05-10 05:32:49'),
(5, 1, 2, 1, -5, 'sale', '2026-05-10 05:32:49'),
(6, 1, 1, 1, -5, 'sale', '2026-05-10 05:45:04'),
(7, 1, 1, 1, -4, 'sale', '2026-05-10 05:45:18'),
(8, 1, 1, 1, -1, 'sale', '2026-05-10 05:45:37'),
(9, 1, 1, 1, -7, 'sale', '2026-05-10 05:57:51'),
(10, 1, 3, 1, 100, 'restock', '2026-05-10 06:08:45'),
(11, 1, 8, 1, 500, 'restock', '2026-05-10 06:42:36'),
(12, 1, 1, 1, -1, 'sale', '2026-05-10 08:20:50'),
(13, 1, 8, 1, -10, 'sale', '2026-05-10 08:20:50'),
(14, 1, 8, 1, -6, 'sale', '2026-05-10 23:24:08'),
(15, 1, 9, 1, 100, 'restock', '2026-05-13 13:12:07'),
(16, 1, 9, 1, -10, 'sale', '2026-05-13 13:21:38'),
(17, 1, 10, 1, 100, 'restock', '2026-05-13 13:26:27'),
(18, 1, 11, 1, 100, 'restock', '2026-05-13 13:27:59'),
(19, 1, 12, 1, 1000, 'restock', '2026-05-13 13:30:58'),
(20, 1, 12, 1, -3, 'sale', '2026-05-13 13:46:55'),
(21, 1, 1, 1, -94, 'sale', '2026-05-13 13:49:50'),
(22, 1, 12, 1, -3, 'sale', '2026-05-13 13:49:50'),
(23, 1, 11, 1, -3, 'sale', '2026-05-13 13:49:50'),
(24, 1, 10, 1, -6, 'sale', '2026-05-13 13:49:50'),
(25, 1, 8, 1, -6, 'sale', '2026-05-13 13:49:50'),
(26, 1, 9, 1, -4, 'sale', '2026-05-13 13:49:50'),
(27, 1, 10, 1, -4, 'sale', '2026-05-13 13:52:59'),
(28, 1, 12, 1, -3, 'sale', '2026-05-17 15:38:38'),
(29, 1, 12, 1, -2, 'sale', '2026-05-17 16:29:02'),
(30, 1, 9, 1, -5, 'sale', '2026-05-17 16:29:02'),
(31, 1, 11, 1, -2, 'sale', '2026-05-17 16:44:33'),
(32, 1, 10, 1, -2, 'sale', '2026-05-17 16:44:33'),
(33, 1, 11, 1, -2, 'sale', '2026-05-17 17:02:27'),
(34, 1, 9, 1, -5, 'sale', '2026-05-17 17:53:49'),
(35, 1, 12, 1, -20, 'sale', '2026-05-18 11:10:11'),
(36, 1, 9, 1, -6, 'sale', '2026-05-18 11:11:24'),
(37, 1, 13, 1, 200, 'restock', '2026-05-18 11:28:44'),
(38, 1, 14, 1, 1000, 'restock', '2026-05-18 11:40:50'),
(39, 1, 13, 1, -5, 'sale', '2026-05-18 12:16:18'),
(40, 1, 13, 1, -5, 'sale', '2026-05-18 12:16:55'),
(41, 1, 13, 1, -5, 'sale', '2026-05-18 12:17:12'),
(42, 1, 9, 1, -131, 'sale', '2026-05-18 12:18:08'),
(43, 1, 14, 1, -2, 'sale', '2026-05-18 12:43:42'),
(44, 1, 10, 1, -1, 'sale', '2026-05-18 12:43:42'),
(45, 1, 11, 1, -2, 'sale', '2026-05-18 17:28:17'),
(46, 1, 15, 1, 40, 'restock', '2026-05-22 15:13:41'),
(47, 1, 12, 4, -1, 'sale', '2026-05-22 19:48:38'),
(48, 1, 15, 4, -2, 'sale', '2026-05-22 19:48:38'),
(49, 1, 11, 4, -9, 'sale', '2026-05-22 19:49:05'),
(50, 1, 11, 4, -16, 'sale', '2026-05-22 19:51:56'),
(51, 1, 10, 4, -7, 'sale', '2026-05-22 19:51:56'),
(52, 1, 12, 4, -2, 'sale', '2026-05-22 19:51:57'),
(53, 1, 11, 4, -1, 'sale', '2026-05-22 19:53:10'),
(54, 1, 12, 4, -1, 'sale', '2026-05-22 19:53:10'),
(55, 1, 11, 4, -1, 'sale', '2026-05-22 20:24:02'),
(56, 1, 10, 4, -1, 'sale', '2026-05-22 20:24:02'),
(57, 1, 12, 4, -1, 'sale', '2026-05-22 20:24:02'),
(58, 1, 13, 4, -1, 'sale', '2026-05-22 20:24:02'),
(59, 1, 15, 4, -1, 'sale', '2026-05-22 20:24:02'),
(60, 1, 14, 4, -1, 'sale', '2026-05-22 20:24:02'),
(61, 1, 11, 4, -1, 'sale', '2026-05-22 20:25:48'),
(62, 1, 10, 4, -9, 'sale', '2026-05-22 20:25:48'),
(63, 1, 11, 4, -1, 'sale', '2026-05-22 20:37:04'),
(64, 1, 12, 4, -3, 'sale', '2026-05-22 20:37:04'),
(65, 1, 11, 4, -1, 'sale', '2026-05-22 20:38:23'),
(66, 1, 11, 4, -1, 'sale', '2026-05-22 20:57:29'),
(67, 1, 14, 4, -7, 'sale', '2026-05-22 21:12:20'),
(68, 1, 12, 4, -1, 'sale', '2026-05-22 21:15:19'),
(69, 1, 15, 4, -3, 'sale', '2026-05-22 21:31:37'),
(70, 1, 13, 4, -4, 'sale', '2026-05-22 21:51:34'),
(71, 1, 15, 4, -4, 'sale', '2026-05-22 21:59:23'),
(72, 1, 10, 2, 10, 'restock', '2026-05-22 22:33:12'),
(73, 1, 9, 2, 70, 'restock', '2026-05-22 22:39:08'),
(74, 1, 9, 2, 41, 'restock', '2026-05-22 22:39:49'),
(75, 1, 9, 2, -2, 'sale', '2026-05-22 22:50:03'),
(76, 1, 15, 2, -1, 'sale', '2026-05-22 22:50:03'),
(77, 1, 16, 1, 300, 'restock', '2026-05-23 00:07:29'),
(78, 1, 16, 4, -1, 'sale', '2026-05-23 00:10:28'),
(79, 1, 9, 4, -1, 'sale', '2026-05-23 00:10:28'),
(80, 1, 16, 4, -1, 'sale', '2026-05-23 00:11:29'),
(81, 1, 14, 4, -9, 'sale', '2026-05-23 00:51:36'),
(82, 1, 15, 4, -1, 'sale', '2026-05-23 00:51:36'),
(83, 1, 9, 4, -1, 'sale', '2026-05-23 00:51:36'),
(84, 1, 17, 1, 1000, 'restock', '2026-05-23 01:10:18'),
(85, 1, 14, 1, 4, 'restock', '2026-05-23 02:23:52'),
(86, 1, 14, 1, 4, 'restock', '2026-05-23 02:26:32'),
(87, 1, 10, 4, -30, 'sale', '2026-05-23 03:00:10'),
(88, 1, 10, 4, -1, 'sale', '2026-05-23 05:12:38'),
(89, 1, 11, 4, -1, 'sale', '2026-05-23 05:12:38'),
(90, 1, 10, 4, -1, 'sale', '2026-05-23 05:13:00'),
(91, 1, 11, 4, -1, 'sale', '2026-05-23 05:13:00'),
(92, 1, 11, 4, -1, 'sale', '2026-05-23 05:13:39'),
(93, 1, 10, 4, -1, 'sale', '2026-05-23 05:13:39'),
(94, 1, 15, 4, -2, 'sale', '2026-05-23 05:20:00'),
(95, 1, 9, 4, -6, 'sale', '2026-05-23 05:42:40'),
(96, 1, 14, 4, -9, 'sale', '2026-05-23 05:42:40'),
(97, 1, 9, 3, -1, 'sale', '2026-05-23 15:43:38'),
(98, 1, 16, 3, -3, 'sale', '2026-05-23 15:43:38'),
(99, 1, 15, 3, -8, 'sale', '2026-05-23 15:43:38'),
(100, 1, 13, 3, -30, 'sale', '2026-05-23 15:43:38'),
(101, 1, 14, 3, -9, 'sale', '2026-05-23 15:43:38'),
(102, 1, 17, 3, -1000, 'sale', '2026-05-23 15:43:38'),
(103, 1, 17, 3, -200, 'sale', '2026-05-23 15:43:38'),
(104, 1, 17, 3, 300, 'restock', '2026-05-23 15:52:43'),
(105, 1, 14, 4, -1, 'sale', '2026-05-23 20:19:44'),
(106, 1, 13, 4, -50, 'sale', '2026-05-23 20:19:44'),
(107, 1, 9, 4, -9, 'sale', '2026-05-23 20:19:44'),
(108, 1, 15, 4, -1, 'sale', '2026-05-23 20:22:24'),
(109, 1, 9, 4, 1, 'restock', '2026-05-23 22:40:01'),
(110, 1, 11, 4, -7, 'sale', '2026-05-24 00:30:34'),
(111, 1, 16, 4, -5, 'sale', '2026-05-24 00:30:34'),
(112, 1, 13, 4, -2, 'sale', '2026-05-24 00:30:34'),
(113, 1, 13, 4, -1, 'sale', '2026-05-24 00:30:34'),
(114, 1, 11, 4, -1, 'sale', '2026-05-24 00:30:34'),
(115, 1, 14, 4, -2, 'sale', '2026-05-24 00:30:34'),
(116, 1, 13, 4, -3, 'sale', '2026-05-24 00:30:34'),
(117, 1, 9, 4, -1, 'sale', '2026-05-24 00:30:34'),
(118, 1, 15, 4, -4, 'sale', '2026-05-24 00:30:34'),
(119, 1, 16, 4, -1, 'sale', '2026-05-24 00:30:34'),
(120, 1, 14, 4, -1, 'sale', '2026-05-24 00:30:34'),
(121, 1, 11, 4, -1, 'sale', '2026-05-24 00:30:34'),
(122, 1, 10, 4, -1, 'sale', '2026-05-24 00:30:34'),
(123, 1, 13, 4, -1, 'sale', '2026-05-24 00:30:34'),
(124, 1, 16, 4, -9, 'sale', '2026-05-24 01:09:55'),
(125, 6, 10, 7, -46, 'sale', '2026-05-24 03:15:01'),
(126, 6, 10, 7, -7, 'sale', '2026-05-24 03:15:05'),
(127, 1, 15, 4, -13, 'sale', '2026-05-24 12:10:57'),
(128, 1, 9, 1, -30, 'sale', '2026-05-24 12:13:07'),
(129, 1, 17, 1, -50, 'sale', '2026-05-24 12:13:27'),
(130, 6, 24, 1, 100, 'restock', '2026-05-24 12:21:49'),
(131, 6, 24, 8, -2, 'sale', '2026-05-24 12:23:40'),
(132, 1, 11, 4, -1, 'sale', '2026-05-24 13:04:05'),
(133, 1, 11, 4, -2, 'sale', '2026-05-24 13:04:40'),
(134, 1, 14, 4, -1, 'sale', '2026-05-24 13:05:33'),
(135, 6, 13, 7, -3, 'sale', '2026-05-24 17:26:11'),
(136, 6, 24, 7, -8, 'sale', '2026-05-24 17:31:52'),
(137, 1, 13, 4, -80, 'sale', '2026-05-25 22:23:42'),
(138, 1, 11, 4, -4, 'sale', '2026-05-25 22:23:42'),
(139, 1, 16, 4, -1, 'sale', '2026-05-25 22:23:42'),
(140, 1, 16, 4, -1, 'sale', '2026-05-25 22:25:33'),
(141, 1, 16, 4, -1, 'sale', '2026-05-25 22:25:59'),
(142, 1, 11, 3, -1, 'sale', '2026-05-25 23:07:47'),
(143, 1, 11, 4, -10, 'sale', '2026-05-25 23:08:32'),
(144, 1, 11, 2, -3, 'sale', '2026-05-26 01:03:44'),
(145, 1, 11, 4, -7, 'sale', '2026-05-26 01:14:30'),
(146, 1, 11, 4, -3, 'sale', '2026-05-26 01:18:12'),
(147, 1, 16, 4, -100, 'sale', '2026-05-26 01:18:42'),
(148, 1, 11, 4, -5, 'sale', '2026-05-26 01:19:07'),
(149, 1, 11, 4, -5, 'sale', '2026-05-26 01:19:44'),
(150, 1, 11, 4, -7, 'sale', '2026-05-26 01:20:05'),
(151, 1, 14, 2, -1, 'sale', '2026-05-26 01:24:49'),
(152, 1, 14, 2, -3, 'sale', '2026-05-26 01:37:26'),
(153, 1, 14, 2, -2, 'sale', '2026-05-26 01:37:46'),
(154, 1, 16, 2, -7, 'sale', '2026-05-26 01:44:30'),
(155, 1, 14, 4, -10, 'sale', '2026-05-26 01:46:03'),
(156, 1, 13, 2, 100, 'restock', '2026-05-26 01:53:59'),
(157, 1, 13, 4, -1, 'sale', '2026-05-26 02:17:45'),
(158, 1, 13, 4, -2, 'sale', '2026-05-26 02:21:54'),
(159, 1, 14, 4, -1, 'sale', '2026-05-26 02:21:54'),
(160, 1, 17, 4, -2, 'sale', '2026-05-26 20:42:54'),
(161, 1, 13, 4, -2, 'sale', '2026-05-26 20:42:54'),
(162, 1, 13, 4, -2, 'sale', '2026-05-26 20:43:09'),
(163, 1, 13, 4, -3, 'sale', '2026-05-26 20:58:15'),
(164, 1, 14, 4, -9, 'sale', '2026-05-26 20:59:26'),
(165, 1, 17, 4, -3, 'sale', '2026-05-26 21:00:01'),
(166, 1, 17, 4, -5, 'sale', '2026-05-29 15:57:35'),
(167, 1, 14, 1, -5, 'sale', '2026-05-29 16:05:31'),
(168, 1, 16, 4, -20, 'sale', '2026-06-02 02:25:21'),
(169, 1, 24, 4, -10, 'sale', '2026-06-02 02:25:21'),
(170, 1, 17, 4, -1, 'sale', '2026-06-02 02:25:21'),
(171, 1, 14, 4, -1, 'sale', '2026-06-02 02:25:21'),
(172, 1, 13, 4, -1, 'sale', '2026-06-02 02:25:21'),
(173, 1, 13, 4, -2, 'sale', '2026-06-02 02:25:21'),
(174, 1, 14, 4, -2, 'sale', '2026-06-02 02:25:21'),
(175, 1, 24, 4, -2, 'sale', '2026-06-02 02:25:21'),
(176, 1, 17, 4, -2, 'sale', '2026-06-02 02:25:21'),
(177, 1, 16, 4, -3, 'sale', '2026-06-02 02:25:21'),
(178, 1, 13, 3, -4, 'sale', '2026-06-02 02:27:22'),
(179, 1, 14, 3, -3, 'sale', '2026-06-02 02:27:22'),
(180, 1, 13, 3, 1, 'restock', '2026-06-02 02:28:45'),
(181, 1, 13, 3, 1, 'restock', '2026-06-02 02:31:25'),
(182, 1, 13, 3, 1, 'restock', '2026-06-02 02:33:00'),
(183, 1, 11, 1, 50, 'restock', '2026-06-02 02:52:44'),
(184, 1, 10, 1, 57, 'restock', '2026-06-02 02:52:57'),
(185, 1, 9, 1, 50, 'restock', '2026-06-02 02:53:09'),
(186, 1, 13, 4, -3, 'sale', '2026-06-03 02:07:12'),
(187, 1, 14, 4, -1, 'sale', '2026-06-03 02:07:12'),
(188, 1, 13, 4, -1, 'sale', '2026-06-03 02:07:12'),
(189, 1, 14, 4, -3, 'sale', '2026-06-03 02:07:12'),
(190, 1, 17, 4, -2, 'sale', '2026-06-03 02:07:12'),
(191, 1, 16, 4, -1, 'sale', '2026-06-03 02:07:12'),
(192, 1, 13, 4, -1, 'sale', '2026-06-03 02:07:12'),
(193, 1, 10, 4, -1, 'sale', '2026-06-03 02:09:01'),
(194, 1, 11, 4, -1, 'sale', '2026-06-03 02:09:13'),
(195, 1, 13, 4, -1, 'sale', '2026-06-03 02:09:13'),
(196, 1, 14, 4, -1, 'sale', '2026-06-03 02:09:13'),
(197, 1, 11, 4, -1, 'sale', '2026-06-03 02:09:26'),
(198, 1, 13, 4, -1, 'sale', '2026-06-03 02:09:26'),
(199, 1, 14, 4, -1, 'sale', '2026-06-03 02:09:26'),
(200, 1, 14, 4, -1, 'sale', '2026-06-03 02:09:50'),
(201, 1, 13, 4, -1, 'sale', '2026-06-03 02:09:50'),
(202, 1, 11, 4, -1, 'sale', '2026-06-03 02:09:50'),
(203, 1, 14, 4, -22, 'sale', '2026-06-03 02:11:24'),
(204, 1, 14, 1, -2, 'sale', '2026-06-03 04:22:03'),
(205, 1, 11, 4, -1, 'sale', '2026-06-03 04:57:52'),
(206, 1, 13, 4, -1, 'sale', '2026-06-03 04:57:52'),
(207, 1, 14, 4, -1, 'sale', '2026-06-03 04:57:52'),
(208, 1, 9, 4, -1, 'sale', '2026-06-03 04:57:52'),
(209, 1, 17, 4, -1, 'sale', '2026-06-03 04:57:52'),
(210, 1, 16, 4, -1, 'sale', '2026-06-03 04:57:52'),
(211, 1, 11, 4, -1, 'sale', '2026-06-03 05:28:23'),
(212, 1, 17, 4, -1, 'sale', '2026-06-03 05:28:23'),
(213, 1, 14, 4, -2, 'sale', '2026-06-03 05:44:41'),
(214, 1, 10, 4, -1, 'sale', '2026-06-03 05:51:45'),
(215, 1, 11, 4, -1, 'sale', '2026-06-03 05:51:45'),
(216, 1, 13, 4, -1, 'sale', '2026-06-03 05:51:45'),
(217, 1, 14, 4, -1, 'sale', '2026-06-03 05:51:45'),
(218, 1, 24, 1, -1, 'sale', '2026-06-03 05:56:39'),
(219, 1, 11, 4, -4, 'sale', '2026-06-03 06:08:46'),
(220, 1, 24, 1, 3, 'restock', '2026-06-03 06:29:46'),
(221, 1, 24, 1, -1, 'sale', '2026-06-03 06:30:40'),
(222, 1, 10, 4, -1, 'sale', '2026-06-03 07:23:48'),
(223, 1, 11, 4, -1, 'sale', '2026-06-03 07:23:48'),
(224, 1, 13, 4, -1, 'sale', '2026-06-03 07:23:48'),
(225, 1, 14, 4, -1, 'sale', '2026-06-03 07:23:48'),
(226, 1, 17, 4, -1, 'sale', '2026-06-03 07:23:48'),
(227, 1, 16, 4, -1, 'sale', '2026-06-03 07:23:48'),
(228, 1, 9, 4, -9, 'sale', '2026-06-03 07:23:48'),
(229, 1, 25, 1, 100, 'restock', '2026-06-03 07:32:46'),
(230, 1, 17, 4, -2, 'sale', '2026-06-03 08:06:50'),
(231, 1, 14, 4, -1, 'sale', '2026-06-03 08:06:50'),
(232, 1, 10, 4, -3, 'sale', '2026-06-03 08:06:50'),
(233, 1, 14, 4, -2, 'sale', '2026-06-03 08:07:14'),
(234, 1, 11, 4, -4, 'sale', '2026-06-03 08:08:06'),
(235, 1, 25, 4, -5, 'sale', '2026-06-03 15:37:01'),
(236, 1, 13, 4, -1, 'sale', '2026-06-03 15:39:15'),
(237, 1, 11, 4, -1, 'sale', '2026-06-03 15:39:15'),
(238, 1, 10, 4, -1, 'sale', '2026-06-03 15:39:15'),
(239, 1, 14, 4, -1, 'sale', '2026-06-03 15:39:15'),
(240, 1, 25, 4, -5, 'sale', '2026-06-03 15:39:15'),
(241, 1, 9, 4, -1, 'sale', '2026-06-03 15:39:15'),
(242, 1, 11, 4, -1, 'sale', '2026-06-04 15:22:26'),
(243, 1, 14, 4, -1, 'sale', '2026-06-04 15:22:26'),
(244, 1, 25, 4, -40, 'sale', '2026-06-04 15:22:26'),
(245, 1, 10, 4, -3, 'sale', '2026-06-04 15:22:26'),
(246, 6, 24, 1, -9, 'sale', '2026-06-04 15:54:00'),
(247, 6, 24, 1, -1, 'sale', '2026-06-04 16:04:33'),
(248, 6, 24, 1, -1, 'sale', '2026-06-04 16:08:18'),
(249, 1, 13, 1, -4, 'sale', '2026-06-04 16:20:34'),
(250, 1, 9, 1, -2, 'sale', '2026-06-04 16:20:34'),
(251, 6, 24, 1, 1, 'restock', '2026-06-04 16:32:33'),
(252, 1, 11, 4, -1, 'sale', '2026-06-04 21:40:43'),
(253, 1, 14, 4, -1, 'sale', '2026-06-04 21:40:43'),
(254, 1, 13, 4, -1, 'sale', '2026-06-04 21:40:43'),
(255, 1, 16, 4, -4, 'sale', '2026-06-04 21:40:43'),
(256, 1, 14, 4, -1, 'sale', '2026-06-04 21:47:44'),
(257, 1, 11, 4, -1, 'sale', '2026-06-04 21:48:02'),
(258, 1, 14, 4, -1, 'sale', '2026-06-04 21:49:03'),
(259, 1, 11, 4, -1, 'sale', '2026-06-04 21:49:03'),
(260, 1, 9, 4, -4, 'sale', '2026-06-04 21:49:43'),
(261, 1, 13, 1, -2, 'sale', '2026-06-04 21:52:11'),
(262, 6, 24, 7, -9, 'sale', '2026-06-04 21:59:03'),
(263, 1, 26, 1, 1000, 'restock', '2026-06-05 01:48:09'),
(264, 1, 27, 1, 3000, 'restock', '2026-06-05 01:56:20'),
(265, 1, 10, 4, -5, 'sale', '2026-06-05 02:29:02'),
(266, 1, 26, 4, -500, 'sale', '2026-06-05 02:29:02'),
(267, 1, 9, 4, -3, 'sale', '2026-06-05 02:30:39'),
(268, 1, 26, 4, 300, 'restock', '2026-06-05 02:33:14'),
(269, 6, 24, 1, -1, 'sale', '2026-06-05 02:40:42'),
(270, 6, 28, 1, 100, 'restock', '2026-06-05 02:44:04'),
(271, 6, 28, 1, -23, 'sale', '2026-06-05 02:46:59'),
(272, 1, 9, 1, 20, 'restock', '2026-06-05 02:50:17'),
(273, 1, 9, 1, -45, 'sale', '2026-06-05 02:51:28'),
(274, 1, 9, 2, 100, 'restock', '2026-06-05 03:01:19'),
(275, 1, 9, 2, -26, 'sale', '2026-06-05 03:08:25'),
(276, 1, 11, 2, -5, 'sale', '2026-06-05 03:08:25'),
(277, 1, 10, 2, -35, 'sale', '2026-06-05 03:08:25'),
(278, 1, 10, 1, 90, 'restock', '2026-06-05 03:27:40'),
(279, 1, 29, 1, 40, 'restock', '2026-06-05 03:44:02'),
(280, 1, 30, 1, 300, 'restock', '2026-06-05 15:40:30'),
(281, 1, 31, 1, 50, 'restock', '2026-06-05 15:46:05'),
(282, 1, 32, 1, 300, 'restock', '2026-06-05 15:49:42'),
(283, 1, 35, 1, 50, 'restock', '2026-06-05 15:56:01'),
(284, 1, 38, 1, 300, 'restock', '2026-06-05 20:24:57'),
(285, 1, 39, 1, 70, 'restock', '2026-06-05 20:29:47'),
(286, 1, 40, 1, 10, 'restock', '2026-06-05 20:33:41'),
(287, 1, 41, 1, 50, 'restock', '2026-06-05 20:42:18'),
(288, 1, 42, 1, 300, 'restock', '2026-06-05 20:44:58'),
(289, 1, 43, 1, 300, 'restock', '2026-06-05 20:47:04'),
(290, 1, 44, 1, 200, 'restock', '2026-06-05 20:48:24'),
(291, 1, 46, 1, 300, 'restock', '2026-06-05 20:51:23'),
(292, 1, 47, 1, 30, 'restock', '2026-06-05 20:53:20'),
(293, 1, 48, 1, 50, 'restock', '2026-06-05 20:55:49'),
(294, 1, 49, 1, 200, 'restock', '2026-06-05 20:57:43'),
(295, 1, 50, 1, 40, 'restock', '2026-06-05 20:59:54'),
(296, 1, 51, 1, 400, 'restock', '2026-06-05 21:02:55'),
(297, 1, 52, 1, 70, 'restock', '2026-06-05 21:12:56'),
(298, 1, 53, 1, 30, 'restock', '2026-06-05 21:15:44'),
(299, 1, 14, 1, -1, 'sale', '2026-06-06 16:49:49'),
(300, 1, 13, 1, -10, 'sale', '2026-06-06 16:49:49'),
(301, 1, 11, 1, -1, 'sale', '2026-06-06 16:49:49'),
(302, 6, 28, 1, -7, 'sale', '2026-06-06 16:58:26'),
(303, 6, 55, 1, 100, 'restock', '2026-06-06 17:02:55'),
(304, 6, 55, 1, -20, 'sale', '2026-06-06 17:07:51'),
(305, 1, 42, 4, -1, 'sale', '2026-06-09 01:35:26'),
(306, 1, 41, 4, -1, 'sale', '2026-06-09 01:35:26'),
(307, 1, 47, 4, -1, 'sale', '2026-06-09 01:35:26'),
(308, 1, 46, 4, -1, 'sale', '2026-06-09 01:35:26'),
(309, 1, 44, 4, -1, 'sale', '2026-06-09 01:35:26'),
(310, 1, 53, 4, -1, 'sale', '2026-06-09 01:35:26'),
(311, 1, 9, 4, -1, 'sale', '2026-06-09 01:35:26'),
(312, 1, 25, 4, -5, 'sale', '2026-06-09 01:35:26'),
(313, 1, 17, 4, -2, 'sale', '2026-06-09 01:35:26'),
(314, 1, 17, 4, -1, 'sale', '2026-06-09 01:35:27'),
(315, 7, 56, 1, 100, 'restock', '2026-06-09 02:26:29'),
(316, 7, 57, 1, 200, 'restock', '2026-06-09 02:36:43'),
(317, 7, 58, 1, 50, 'restock', '2026-06-09 02:39:35'),
(318, 7, 59, 1, 80, 'restock', '2026-06-09 02:43:16'),
(319, 7, 56, 1, -1, 'sale', '2026-06-09 02:48:34'),
(320, 7, 58, 1, -1, 'sale', '2026-06-09 02:48:34'),
(321, 7, 57, 1, -8, 'sale', '2026-06-09 02:55:08'),
(322, 7, 58, 1, -2, 'sale', '2026-06-09 02:57:33'),
(323, 7, 57, 1, 8, 'restock', '2026-06-09 03:11:40'),
(324, 7, 57, 1, -30, 'sale', '2026-06-09 03:15:57'),
(325, 7, 59, 1, -5, 'sale', '2026-06-09 03:15:57'),
(326, 7, 56, 1, -2, 'sale', '2026-06-09 03:15:57'),
(327, 7, 57, 1, -1, 'sale', '2026-06-09 03:18:35'),
(328, 7, 59, 1, -1, 'sale', '2026-06-09 03:18:35'),
(329, 7, 58, 1, -1, 'sale', '2026-06-09 03:18:35'),
(330, 7, 56, 1, -1, 'sale', '2026-06-09 03:18:35'),
(331, 7, 59, 1, -4, 'sale', '2026-06-09 03:19:43'),
(332, 7, 57, 1, -9, 'sale', '2026-06-09 03:19:43'),
(333, 7, 58, 1, -6, 'sale', '2026-06-09 03:20:50'),
(334, 7, 56, 1, -1, 'sale', '2026-06-09 03:21:44'),
(335, 7, 58, 1, -1, 'sale', '2026-06-09 03:21:44'),
(336, 7, 57, 1, -1, 'sale', '2026-06-09 03:21:44'),
(337, 7, 59, 1, -1, 'sale', '2026-06-09 03:21:44'),
(338, 7, 57, 1, -4, 'sale', '2026-06-09 03:21:57'),
(339, 7, 58, 1, -3, 'sale', '2026-06-09 03:22:10'),
(340, 7, 58, 1, -3, 'sale', '2026-06-09 03:22:22'),
(341, 7, 56, 9, -5, 'sale', '2026-06-09 04:06:25'),
(342, 7, 60, 1, 400, 'restock', '2026-06-09 16:49:26'),
(343, 7, 61, 1, 200, 'restock', '2026-06-09 16:53:25'),
(344, 7, 62, 1, 100, 'restock', '2026-06-09 16:55:51'),
(345, 7, 63, 1, 300, 'restock', '2026-06-09 16:57:54'),
(346, 7, 64, 1, 300, 'restock', '2026-06-09 16:59:40'),
(347, 7, 65, 1, 500, 'restock', '2026-06-09 17:02:03'),
(348, 7, 60, 1, -3, 'sale', '2026-06-09 17:31:05'),
(349, 7, 60, 1, 3, 'restock', '2026-06-09 17:40:57'),
(350, 7, 60, 9, -1, 'sale', '2026-06-09 18:52:30'),
(351, 7, 57, 9, -1, 'sale', '2026-06-09 18:52:30'),
(352, 7, 58, 9, -1, 'sale', '2026-06-09 18:52:30'),
(353, 7, 59, 9, -1, 'sale', '2026-06-09 18:52:30'),
(354, 7, 57, 9, -4, 'sale', '2026-06-09 18:54:23'),
(355, 7, 58, 9, -2, 'sale', '2026-06-09 18:55:29'),
(356, 7, 59, 9, -2, 'sale', '2026-06-09 18:59:14'),
(357, 7, 59, 1, 2, 'restock', '2026-06-09 19:20:15'),
(358, 7, 59, 1, 12, 'restock', '2026-06-09 19:34:09'),
(359, 7, 58, 1, 270, 'restock', '2026-06-09 19:34:31'),
(360, 7, 56, 1, 210, 'restock', '2026-06-09 19:34:52'),
(361, 7, 60, 1, 1, 'restock', '2026-06-09 19:35:01'),
(362, 7, 61, 9, -1, 'sale', '2026-06-09 19:39:12'),
(363, 7, 61, 9, -30, 'sale', '2026-06-09 19:40:00'),
(364, 7, 62, 9, -4, 'sale', '2026-06-09 19:41:06'),
(365, 7, 65, 12, -1, 'sale', '2026-06-09 20:58:10'),
(366, 7, 57, 12, -1, 'sale', '2026-06-09 20:58:10'),
(367, 7, 60, 12, -1, 'sale', '2026-06-09 20:58:10'),
(368, 7, 60, 12, -1, 'sale', '2026-06-09 20:58:11'),
(369, 7, 61, 12, -200, 'sale', '2026-06-09 20:58:11'),
(370, 7, 62, 12, -1, 'sale', '2026-06-09 21:02:05'),
(371, 7, 57, 12, -2, 'sale', '2026-06-09 21:02:58'),
(372, 7, 60, 12, -2, 'sale', '2026-06-09 21:03:52'),
(373, 7, 61, 1, 100, 'restock', '2026-06-09 21:10:38'),
(374, 7, 61, 1, 31, 'restock', '2026-06-09 21:11:18'),
(375, 7, 57, 13, -1, 'sale', '2026-06-09 22:29:28'),
(376, 7, 57, 13, 1, 'restock', '2026-06-09 22:30:30'),
(377, 7, 57, 13, -2, 'sale', '2026-06-09 23:17:56'),
(378, 7, 62, 13, -1, 'sale', '2026-06-09 23:31:05'),
(379, 7, 63, 13, -20, 'sale', '2026-06-09 23:32:02'),
(380, 7, 62, 13, -4, 'sale', '2026-06-09 23:32:55'),
(381, 7, 57, 1, -3, 'sale', '2026-06-09 23:43:11'),
(382, 7, 57, 1, -1, 'sale', '2026-06-09 23:45:15'),
(383, 7, 57, 1, -1, 'sale', '2026-06-09 23:45:55'),
(384, 7, 65, 1, -1, 'sale', '2026-06-09 23:57:38'),
(385, 7, 59, 1, -1, 'sale', '2026-06-10 00:21:38'),
(386, 7, 60, 1, -1, 'sale', '2026-06-10 00:26:29'),
(387, 7, 61, 1, -1, 'sale', '2026-06-10 00:26:29'),
(388, 7, 62, 1, -1, 'sale', '2026-06-10 00:26:29'),
(389, 7, 63, 1, -1, 'sale', '2026-06-10 00:26:29'),
(390, 7, 65, 1, -1, 'sale', '2026-06-10 00:26:29'),
(391, 7, 64, 1, -1, 'sale', '2026-06-10 00:26:29'),
(392, 7, 57, 1, -3, 'sale', '2026-06-10 00:26:29'),
(393, 7, 56, 1, -1, 'sale', '2026-06-10 00:26:29'),
(394, 7, 58, 1, -3, 'sale', '2026-06-10 00:26:29'),
(395, 7, 59, 1, -4, 'sale', '2026-06-10 00:26:29'),
(396, 7, 57, 1, -1, 'sale', '2026-06-10 00:27:45'),
(397, 1, 17, 1, -1, 'sale', '2026-06-10 23:38:35'),
(398, 7, 65, 1, -2, 'sale', '2026-06-11 03:14:25'),
(399, 7, 63, 1, -1, 'sale', '2026-06-11 03:15:16'),
(400, 7, 62, 1, -1, 'sale', '2026-06-11 03:15:16'),
(401, 7, 60, 1, -3, 'sale', '2026-06-11 03:18:38'),
(402, 7, 64, 1, -1, 'sale', '2026-06-11 03:18:38'),
(403, 7, 65, 1, -1, 'sale', '2026-06-11 03:18:38'),
(404, 7, 61, 1, -1, 'sale', '2026-06-11 03:18:38'),
(405, 7, 62, 1, -1, 'sale', '2026-06-11 03:18:38'),
(406, 7, 63, 1, -1, 'sale', '2026-06-11 03:18:38'),
(407, 7, 59, 1, -1, 'sale', '2026-06-11 03:18:38'),
(408, 7, 58, 1, -1, 'sale', '2026-06-11 03:18:38'),
(409, 7, 57, 1, -1, 'sale', '2026-06-11 03:18:38'),
(410, 7, 56, 1, -1, 'sale', '2026-06-11 03:18:38'),
(411, 7, 59, 1, -1, 'sale', '2026-06-11 03:20:22'),
(412, 7, 62, 1, -1, 'sale', '2026-06-11 03:20:22'),
(413, 7, 57, 1, -1, 'sale', '2026-06-11 03:20:22'),
(414, 7, 58, 1, -1, 'sale', '2026-06-11 03:22:13'),
(415, 7, 65, 1, -4, 'sale', '2026-06-11 03:23:58'),
(416, 7, 59, 1, -1, 'sale', '2026-06-14 18:39:48'),
(417, 7, 58, 1, -1, 'sale', '2026-06-14 18:39:48'),
(418, 7, 59, 1, -1, 'sale', '2026-06-14 18:40:35'),
(419, 7, 58, 1, -1, 'sale', '2026-06-14 18:40:35'),
(420, 1, 11, 4, -1, 'sale', '2026-06-14 19:15:27'),
(421, 1, 10, 4, -1, 'sale', '2026-06-14 19:15:27'),
(422, 1, 13, 4, -1, 'sale', '2026-06-14 19:15:27'),
(423, 1, 14, 4, -1, 'sale', '2026-06-14 19:15:27'),
(424, 1, 10, 4, -1, 'sale', '2026-06-15 17:01:23'),
(425, 1, 11, 4, -1, 'sale', '2026-06-15 17:01:23'),
(426, 1, 13, 4, -1, 'sale', '2026-06-15 17:01:23'),
(427, 1, 14, 4, -1, 'sale', '2026-06-15 17:01:23'),
(428, 1, 11, 4, -1, 'sale', '2026-06-15 17:01:59'),
(429, 1, 16, 4, -10, 'sale', '2026-06-15 17:22:21'),
(430, 1, 16, 4, 1, 'restock', '2026-06-15 17:29:07'),
(431, 1, 10, 4, -1, 'sale', '2026-06-15 17:41:51'),
(432, 1, 11, 4, -1, 'sale', '2026-06-15 17:41:51'),
(433, 1, 16, 4, -1, 'sale', '2026-06-15 17:41:51'),
(434, 7, 60, 1, -92, 'transfer', '2026-06-15 18:39:42'),
(435, 6, 70, 1, 92, 'transfer', '2026-06-15 18:39:42'),
(436, 7, 60, 1, -1, 'sale', '2026-06-15 18:47:39'),
(437, 7, 60, 1, 1, 'restock', '2026-06-15 19:17:52'),
(438, 1, 16, 1, 20, 'restock', '2026-06-15 19:30:16'),
(439, 1, 16, 4, -5, 'sale', '2026-06-15 19:31:26'),
(440, 1, 16, 4, -5, 'sale', '2026-06-15 19:33:20'),
(441, 1, 9, 4, -1, 'sale', '2026-06-15 19:39:35'),
(442, 1, 9, 4, -7, 'sale', '2026-06-15 19:50:15'),
(443, 1, 9, 4, -1, 'sale', '2026-06-15 19:57:00'),
(444, 1, 26, 4, -1, 'sale', '2026-06-15 20:02:10'),
(445, 1, 29, 4, -1, 'sale', '2026-06-15 20:02:10'),
(446, 1, 26, 4, 1, 'restock', '2026-06-15 20:03:41'),
(447, 1, 29, 4, 1, 'restock', '2026-06-15 20:03:41'),
(448, 1, 9, 4, -9, 'sale', '2026-06-15 20:07:23'),
(449, 1, 9, 1, 40, 'restock', '2026-06-15 20:08:52'),
(450, 1, 9, 4, -50, 'sale', '2026-06-15 20:10:29'),
(451, 1, 47, 1, 71, 'restock', '2026-06-15 20:11:45'),
(452, 1, 71, 1, 50, 'restock', '2026-06-15 20:25:19'),
(453, 7, 57, 13, 21, 'restock', '2026-06-15 21:17:16'),
(454, 7, 57, 13, -5, 'sale', '2026-06-15 21:52:47'),
(455, 7, 59, 13, -1, 'sale', '2026-06-15 21:52:59'),
(456, 7, 57, 1, -50, 'transfer', '2026-06-15 23:04:14'),
(457, 6, 72, 1, 50, 'transfer', '2026-06-15 23:04:14'),
(458, 1, 42, 4, -99, 'sale', '2026-06-15 23:13:17'),
(459, 1, 53, 4, -9, 'sale', '2026-06-15 23:14:45'),
(460, 1, 10, 1, -7, 'sale', '2026-06-16 01:21:43'),
(461, 1, 14, 1, -2, 'sale', '2026-06-16 01:26:36'),
(462, 1, 41, 1, -9, 'sale', '2026-06-16 01:30:48'),
(463, 1, 17, 4, -1, 'sale', '2026-06-16 01:41:23'),
(464, 7, 56, 1, -2, 'sale', '2026-06-16 02:08:49'),
(465, 1, 17, 4, -1, 'sale', '2026-06-16 02:38:39'),
(466, 1, 17, 4, -1, 'sale', '2026-06-16 02:38:52'),
(467, 1, 17, 4, -3, 'sale', '2026-06-16 02:39:09'),
(468, 1, 46, 4, -9, 'sale', '2026-06-16 02:40:05'),
(469, 1, 44, 4, -9, 'sale', '2026-06-16 02:53:40'),
(470, 1, 13, 3, -5, 'sale', '2026-06-16 03:09:03'),
(471, 1, 44, 4, 9, 'restock', '2026-06-16 03:25:15'),
(472, 1, 25, 4, -5, 'sale', '2026-06-16 03:45:21'),
(473, 1, 44, 4, -9, 'sale', '2026-06-16 03:49:01'),
(474, 1, 27, 1, -1000, 'sale', '2026-06-16 19:15:59'),
(475, 1, 14, 1, -80, 'sale', '2026-06-16 19:19:01'),
(476, 1, 46, 1, -40, 'sale', '2026-06-16 19:19:01'),
(477, 1, 44, 1, -40, 'sale', '2026-06-16 19:19:01'),
(478, 1, 14, 1, -3, 'sale', '2026-06-16 19:27:32'),
(479, 1, 46, 1, -4, 'sale', '2026-06-16 19:27:32'),
(480, 1, 46, 3, -6, 'sale', '2026-06-16 19:51:46'),
(481, 1, 10, 3, -5, 'sale', '2026-06-16 19:52:21'),
(482, 1, 10, 3, -5, 'sale', '2026-06-16 19:52:59'),
(483, 1, 14, 3, -1, 'sale', '2026-06-16 19:53:44'),
(484, 1, 27, 3, -10, 'sale', '2026-06-16 19:55:35'),
(485, 1, 14, 3, -1, 'sale', '2026-06-16 19:57:13'),
(486, 1, 14, 3, 1, 'restock', '2026-06-16 20:00:42'),
(487, 1, 14, 3, -1, 'sale', '2026-06-16 21:13:59'),
(488, 1, 14, 4, -5, 'sale', '2026-06-17 00:53:07'),
(489, 1, 14, 4, -1, 'sale', '2026-06-17 02:17:40'),
(490, 1, 14, 4, -1, 'sale', '2026-06-17 02:17:53'),
(491, 7, 61, 1, -8, 'sale', '2026-06-17 02:58:24'),
(492, 7, 62, 1, -6, 'sale', '2026-06-17 02:58:24'),
(493, 7, 56, 1, -6, 'sale', '2026-06-17 02:58:24'),
(494, 7, 58, 1, -3, 'sale', '2026-06-17 02:58:24'),
(495, 7, 59, 1, -1, 'sale', '2026-06-17 03:27:19'),
(496, 1, 14, 3, -5, 'sale', '2026-06-17 04:15:54'),
(497, 1, 26, 3, -230, 'sale', '2026-06-17 04:16:38'),
(498, 1, 14, 3, -40, 'sale', '2026-06-17 04:17:17'),
(499, 1, 25, 1, 5, 'restock', '2026-06-17 04:20:17'),
(500, 1, 14, 3, -3, 'sale', '2026-06-17 04:27:51'),
(501, 1, 25, 3, -5, 'sale', '2026-06-17 04:28:24'),
(502, 1, 13, 3, -1, 'sale', '2026-06-17 04:29:29'),
(503, 11, 73, 1, 100, 'restock', '2026-06-17 06:53:06'),
(504, 11, 74, 1, 100, 'restock', '2026-06-17 07:06:37'),
(505, 11, 75, 1, 120, 'restock', '2026-06-17 07:25:07'),
(506, 11, 76, 1, 45, 'restock', '2026-06-17 07:25:07'),
(507, 11, 77, 1, 10, 'restock', '2026-06-17 08:01:45'),
(508, 11, 78, 1, 100, 'restock', '2026-06-17 08:12:44'),
(509, 11, 75, 1, -1, 'sale', '2026-06-17 09:02:01'),
(510, 11, 75, 1, -1, 'sale', '2026-06-17 09:03:58'),
(511, 11, 75, 1, -1, 'sale', '2026-06-17 09:08:08'),
(512, 11, 74, 1, -2, 'sale', '2026-06-17 09:37:16'),
(513, 11, 75, 1, -1, 'sale', '2026-06-17 09:46:34'),
(514, 11, 74, 1, -1, 'sale', '2026-06-17 09:46:34'),
(515, 11, 73, 1, -1, 'sale', '2026-06-17 09:46:34'),
(516, 11, 77, 1, -1, 'sale', '2026-06-17 09:46:34'),
(517, 11, 78, 1, -1, 'sale', '2026-06-17 09:46:34'),
(518, 11, 78, 1, -1, 'sale', '2026-06-17 09:56:20'),
(519, 11, 75, 1, -1, 'sale', '2026-06-17 10:28:39'),
(520, 11, 75, 1, -1, 'sale', '2026-06-17 10:51:21'),
(521, 11, 79, 1, 120, 'restock', '2026-06-17 18:42:10'),
(522, 11, 80, 1, 120, 'restock', '2026-06-17 18:42:10'),
(523, 11, 81, 1, 120, 'restock', '2026-06-17 18:42:10'),
(524, 11, 82, 1, 120, 'restock', '2026-06-17 18:42:10'),
(525, 11, 83, 1, 120, 'restock', '2026-06-17 18:42:10'),
(526, 11, 84, 1, 120, 'restock', '2026-06-17 18:42:10'),
(527, 11, 85, 1, 120, 'restock', '2026-06-17 18:42:10'),
(528, 11, 86, 1, 120, 'restock', '2026-06-17 18:42:10'),
(529, 11, 87, 1, 120, 'restock', '2026-06-17 18:42:10'),
(530, 11, 88, 1, 120, 'restock', '2026-06-17 18:42:10'),
(531, 11, 89, 1, 120, 'restock', '2026-06-17 18:42:10'),
(532, 11, 90, 1, 120, 'restock', '2026-06-17 18:42:10'),
(533, 11, 91, 1, 120, 'restock', '2026-06-17 18:42:10'),
(534, 11, 92, 1, 120, 'restock', '2026-06-17 18:42:10'),
(535, 11, 93, 1, 120, 'restock', '2026-06-17 18:42:10'),
(536, 11, 94, 1, 120, 'restock', '2026-06-17 18:42:10'),
(537, 11, 95, 1, 120, 'restock', '2026-06-17 18:42:10'),
(538, 11, 73, 1, -4, 'sale', '2026-06-17 18:59:57'),
(539, 11, 79, 1, -1, 'sale', '2026-06-17 18:59:57'),
(540, 11, 80, 1, -1, 'sale', '2026-06-17 18:59:57'),
(541, 11, 90, 1, -1, 'sale', '2026-06-17 18:59:57'),
(542, 11, 79, 1, -3, 'sale', '2026-06-18 02:29:09'),
(543, 11, 75, 1, -82, 'sale', '2026-06-18 02:29:09'),
(544, 11, 74, 1, -8, 'sale', '2026-06-18 02:29:09'),
(545, 11, 78, 1, -1, 'sale', '2026-06-18 02:29:09'),
(546, 11, 81, 1, -1, 'sale', '2026-06-18 02:29:09'),
(547, 11, 82, 1, -1, 'sale', '2026-06-18 02:29:09'),
(548, 11, 77, 1, -3, 'sale', '2026-06-18 02:29:09'),
(549, 11, 73, 1, -2, 'sale', '2026-06-18 02:29:09'),
(550, 11, 95, 1, -1, 'sale', '2026-06-18 02:48:26'),
(551, 11, 94, 1, -1, 'sale', '2026-06-18 02:48:26'),
(552, 11, 93, 1, -1, 'sale', '2026-06-18 02:48:26'),
(553, 11, 86, 1, -1, 'sale', '2026-06-18 02:48:26'),
(554, 11, 87, 1, -1, 'sale', '2026-06-18 02:48:26'),
(555, 11, 88, 1, -2, 'sale', '2026-06-18 02:48:26'),
(556, 11, 90, 1, -1, 'sale', '2026-06-18 02:48:26'),
(557, 11, 91, 1, -1, 'sale', '2026-06-18 02:48:26'),
(558, 11, 77, 1, -1, 'sale', '2026-06-18 02:48:26'),
(559, 11, 75, 1, -1, 'sale', '2026-06-18 02:49:38'),
(560, 11, 74, 1, -1, 'sale', '2026-06-18 02:49:38'),
(561, 11, 73, 1, -1, 'sale', '2026-06-18 02:49:38'),
(562, 11, 81, 1, -1, 'sale', '2026-06-18 02:49:38'),
(563, 11, 82, 1, -1, 'sale', '2026-06-18 02:49:38'),
(564, 11, 83, 1, -1, 'sale', '2026-06-18 02:49:38'),
(565, 11, 84, 1, -1, 'sale', '2026-06-18 02:49:38'),
(566, 11, 85, 1, -25, 'sale', '2026-06-18 02:49:38'),
(567, 11, 78, 1, -3, 'sale', '2026-06-18 02:49:38'),
(568, 11, 87, 1, -3, 'sale', '2026-06-18 02:49:38'),
(569, 11, 86, 1, -2, 'sale', '2026-06-18 02:49:38'),
(570, 11, 94, 1, -8, 'sale', '2026-06-18 02:49:38'),
(571, 11, 93, 1, -1, 'sale', '2026-06-18 02:49:38'),
(572, 11, 88, 1, -1, 'sale', '2026-06-18 02:57:13'),
(573, 11, 89, 1, -1, 'sale', '2026-06-18 02:57:13'),
(574, 11, 90, 1, -1, 'sale', '2026-06-18 02:57:13'),
(575, 11, 75, 1, -1, 'sale', '2026-06-18 03:31:41'),
(576, 11, 79, 1, -1, 'sale', '2026-06-18 03:31:41'),
(577, 11, 81, 1, -3, 'sale', '2026-06-18 03:31:41'),
(578, 11, 82, 1, -3, 'sale', '2026-06-18 03:31:41'),
(579, 11, 93, 1, -3, 'sale', '2026-06-18 03:31:41'),
(580, 11, 73, 1, -2, 'sale', '2026-06-18 03:32:38'),
(581, 11, 78, 1, -4, 'sale', '2026-06-18 03:32:38'),
(582, 11, 81, 1, -1, 'sale', '2026-06-18 03:57:55'),
(583, 11, 87, 1, -1, 'sale', '2026-06-18 03:59:01'),
(584, 11, 90, 1, -7, 'sale', '2026-06-18 04:50:19'),
(585, 11, 86, 1, -7, 'sale', '2026-06-18 04:50:19'),
(586, 11, 83, 1, -9, 'sale', '2026-06-18 04:50:19'),
(587, 11, 80, 1, -9, 'sale', '2026-06-18 04:54:50'),
(588, 11, 81, 1, -4, 'sale', '2026-06-18 04:54:50'),
(589, 11, 88, 1, -2, 'sale', '2026-06-18 04:54:50'),
(590, 7, 56, 1, -10, 'sale', '2026-06-20 23:11:04'),
(591, 7, 63, 1, -7, 'sale', '2026-06-20 23:11:04'),
(592, 7, 62, 1, -1, 'sale', '2026-06-20 23:11:04'),
(593, 7, 61, 1, -2, 'sale', '2026-06-20 23:11:04'),
(594, 7, 60, 1, -2, 'sale', '2026-06-20 23:11:04'),
(595, 7, 59, 1, -1, 'sale', '2026-06-20 23:11:04'),
(596, 7, 56, 1, -80, 'sale', '2026-06-20 23:12:25'),
(597, 7, 59, 1, -2, 'sale', '2026-06-20 23:13:14'),
(598, 7, 101, 1, 1, 'restock', '2026-06-20 23:15:36'),
(599, 7, 60, 1, -3, 'sale', '2026-06-21 00:07:53'),
(600, 7, 56, 1, -1, 'sale', '2026-06-21 18:28:46'),
(601, 7, 57, 1, -10, 'sale', '2026-06-21 18:28:46'),
(602, 7, 59, 1, -1, 'sale', '2026-06-21 18:28:46'),
(603, 7, 60, 1, -1, 'sale', '2026-06-21 18:28:46'),
(604, 12, 104, 1, 300, 'restock', '2026-06-28 03:01:37'),
(605, 12, 105, 1, 300, 'restock', '2026-06-28 03:07:13'),
(606, 12, 106, 1, 100, 'restock', '2026-06-28 03:11:27'),
(607, 1, 9, 1, -5, 'sale', '2026-06-28 20:52:30'),
(608, 1, 31, 1, -1, 'sale', '2026-06-28 20:52:30'),
(609, 1, 30, 1, -1, 'sale', '2026-06-28 20:52:30'),
(610, 1, 44, 1, -8, 'sale', '2026-06-28 20:52:30'),
(611, 1, 13, 1, -9, 'transfer', '2026-06-28 21:03:52'),
(612, 7, 107, 1, 9, 'transfer', '2026-06-28 21:03:52');

-- --------------------------------------------------------

--
-- Table structure for table `login_activity`
--

CREATE TABLE `login_activity` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` enum('success','failed') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_activity`
--

INSERT INTO `login_activity` (`id`, `user_id`, `ip_address`, `user_agent`, `status`, `created_at`) VALUES
(1, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 02:56:38'),
(2, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 03:03:15'),
(3, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 03:03:43'),
(4, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 04:52:58'),
(5, 6, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-23 05:11:04'),
(6, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-23 05:11:41'),
(7, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-23 05:40:50'),
(8, 2, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-23 05:45:12'),
(9, 1, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-23 05:48:31'),
(10, 1, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-23 05:48:40'),
(11, 1, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-23 05:53:26'),
(12, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 15:01:36'),
(13, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 15:12:42'),
(14, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 15:19:28'),
(15, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 16:15:54'),
(16, 5, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 16:18:46'),
(17, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-23 16:25:03'),
(18, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-23 19:02:23'),
(19, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-23 20:15:57'),
(20, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-23 20:17:17'),
(21, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-23 22:36:21'),
(22, 1, '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-05-23 23:21:41'),
(23, 4, '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-05-24 00:29:44'),
(24, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 01:03:17'),
(25, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 01:04:38'),
(26, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 01:05:29'),
(27, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-24 01:08:08'),
(28, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 02:28:39'),
(29, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 02:43:26'),
(30, 1, '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-05-24 02:45:17'),
(31, 7, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 03:04:15'),
(32, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 03:18:30'),
(33, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 12:07:07'),
(34, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 12:11:54'),
(35, 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 12:18:48'),
(36, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 12:24:40'),
(37, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 12:28:44'),
(38, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-24 12:57:34'),
(39, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-24 12:57:42'),
(40, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-24 13:01:28'),
(41, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 13:02:42'),
(42, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-24 13:03:25'),
(43, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 15:04:33'),
(44, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 15:11:48'),
(45, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 17:20:41'),
(46, 7, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 17:23:46'),
(47, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 17:36:44'),
(48, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 00:24:21'),
(49, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 00:45:50'),
(50, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 00:46:16'),
(51, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 12:23:16'),
(52, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 12:24:19'),
(53, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 22:06:17'),
(54, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-25 22:19:02'),
(55, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-25 22:19:08'),
(56, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 22:24:52'),
(57, 6, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-25 23:02:18'),
(58, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-25 23:03:24'),
(59, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 23:05:39'),
(60, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 23:09:30'),
(61, 6, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-25 23:12:34'),
(62, 3, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-25 23:13:25'),
(63, 1, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-25 23:16:10'),
(64, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-26 00:59:49'),
(65, 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-05-26 01:07:46'),
(66, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-26 01:47:42'),
(67, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-26 01:48:35'),
(68, 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-05-26 02:14:10'),
(69, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-26 20:38:15'),
(70, 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-05-26 21:09:31'),
(71, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-26 21:10:21'),
(72, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-26 21:11:26'),
(73, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-27 00:02:02'),
(74, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-27 00:04:49'),
(75, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-27 01:14:40'),
(76, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-29 15:52:10'),
(77, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-29 16:00:03'),
(78, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-29 16:06:34'),
(79, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-29 16:07:02'),
(80, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-29 16:16:32'),
(81, 5, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-29 16:18:35'),
(82, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-30 22:28:58'),
(83, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-30 23:55:26'),
(84, 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-01 00:30:14'),
(85, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-02 02:15:44'),
(86, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-02 02:23:42'),
(87, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-02 02:26:40'),
(88, 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'failed', '2026-06-02 02:35:22'),
(89, 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-02 02:35:37'),
(90, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-02 02:52:04'),
(91, 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-03 01:59:07'),
(92, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 02:01:02'),
(93, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 02:13:56'),
(94, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 04:02:41'),
(95, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 04:16:58'),
(96, 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-03 04:45:10'),
(97, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 05:07:06'),
(98, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 05:20:53'),
(99, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-03 06:07:29'),
(100, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-03 06:26:16'),
(101, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-03 07:16:45'),
(102, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-03 07:21:37'),
(103, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 07:34:37'),
(104, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 07:58:11'),
(105, 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 08:02:10'),
(106, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 08:03:40'),
(107, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 08:04:23'),
(108, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-03 08:04:43'),
(109, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 14:19:31'),
(110, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 15:36:01'),
(111, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 15:41:13'),
(112, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 16:14:25'),
(113, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 19:56:03'),
(114, 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-03 20:53:30'),
(115, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-03 20:55:37'),
(116, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 22:09:52'),
(117, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-03 22:11:14'),
(118, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 15:05:46'),
(119, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 15:23:58'),
(120, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-04 15:24:42'),
(121, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 15:50:00'),
(122, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-04 19:52:45'),
(123, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 19:53:40'),
(124, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 19:57:01'),
(125, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 20:10:41'),
(126, 4, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-04 21:27:14'),
(127, 1, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-04 21:50:46'),
(128, 7, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 21:58:26'),
(129, 1, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-04 22:26:25'),
(130, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 23:01:16'),
(131, 1, '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-05 01:29:50'),
(132, 4, '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-05 02:21:13'),
(133, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-05 02:36:32'),
(134, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-05 02:55:19'),
(135, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-05 02:59:31'),
(136, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-05 03:13:22'),
(137, 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-05 03:28:20'),
(138, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-05 15:37:51'),
(139, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-05 20:22:10'),
(140, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-05 21:23:48'),
(141, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-06 04:20:41'),
(142, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-06 16:41:34'),
(143, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-06 17:13:43'),
(144, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-06 17:15:26'),
(145, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-06 17:20:26'),
(146, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-06 19:22:06'),
(147, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 01:34:04'),
(148, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 02:18:51'),
(149, 1, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-09 02:45:23'),
(150, 1, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-09 03:31:07'),
(151, 9, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-09 03:53:32'),
(152, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 16:41:06'),
(153, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 18:40:29'),
(154, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-09 18:48:10'),
(155, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-09 19:36:35'),
(156, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 20:38:11'),
(157, 12, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 20:39:43'),
(158, 12, '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-09 20:55:06'),
(159, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-09 20:59:09'),
(160, 1, '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-09 21:15:44'),
(161, 13, '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-09 21:17:45'),
(162, 13, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-09 21:24:20'),
(163, 12, '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-09 21:27:33'),
(164, 13, '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-09 21:53:20'),
(165, 13, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-09 21:57:12'),
(166, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 23:23:18'),
(167, 13, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 23:24:55'),
(168, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 23:37:54'),
(169, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-10 00:38:52'),
(170, 13, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-10 00:41:41'),
(171, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-10 00:55:07'),
(172, 13, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-10 00:57:54'),
(173, 13, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-10 01:02:40'),
(174, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-10 23:34:11'),
(175, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-11 01:16:44'),
(176, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-11 03:07:31'),
(177, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-11 03:25:08'),
(178, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-12 01:56:34'),
(179, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'failed', '2026-06-14 18:35:28'),
(180, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-14 18:35:38'),
(181, 4, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-14 19:08:18'),
(182, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-14 19:20:13'),
(183, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-15 16:54:51'),
(184, 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-15 16:58:47'),
(185, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-15 17:50:37'),
(186, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-15 17:57:47'),
(187, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-15 18:47:05'),
(188, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-15 19:02:21'),
(189, 4, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-15 19:26:34'),
(190, 4, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-15 20:42:47'),
(191, 13, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-15 21:09:30'),
(192, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-15 21:55:22'),
(193, 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-15 23:11:34'),
(194, 13, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 00:30:48'),
(195, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 00:39:37'),
(196, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 01:04:34'),
(197, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 01:10:11'),
(198, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 01:42:42'),
(199, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 01:57:03'),
(200, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 02:06:05'),
(201, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 02:11:14'),
(202, 4, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 02:37:54'),
(203, 3, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 02:57:12'),
(204, 3, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 02:59:48'),
(205, 1, '::1', 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', 'success', '2026-06-16 03:12:10'),
(206, 13, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 03:13:56'),
(207, 4, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 03:23:36'),
(208, 1, '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 05:44:07'),
(209, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 06:35:58'),
(210, 13, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-16 08:22:19'),
(211, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 16:58:19'),
(212, 13, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 17:14:02'),
(213, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 18:02:32'),
(214, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 18:17:09'),
(215, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 18:39:57'),
(216, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 18:55:24'),
(217, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 19:01:23'),
(218, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 19:04:07'),
(219, 3, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 19:43:28'),
(220, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 19:48:26'),
(221, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 20:06:21'),
(222, 3, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 21:10:38'),
(223, 3, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 21:14:30'),
(224, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 21:19:55'),
(225, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 21:22:27'),
(226, 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 00:34:47'),
(227, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 01:07:47'),
(228, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-17 01:45:45'),
(229, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-17 01:58:33'),
(230, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-17 02:01:37'),
(231, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 02:03:32'),
(232, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 02:03:55'),
(233, 4, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:14:37'),
(234, 4, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:19:12'),
(235, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:24:12'),
(236, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:25:14'),
(237, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:32:09'),
(238, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-17 02:38:29'),
(239, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:48:54'),
(240, 3, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 03:47:49'),
(241, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 03:52:25'),
(242, 4, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 03:58:02'),
(243, 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 03:59:57'),
(244, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 04:09:09'),
(245, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 04:27:12'),
(246, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 04:39:08'),
(247, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 04:48:21'),
(248, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 05:12:51'),
(249, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 05:38:43'),
(250, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 05:48:39'),
(251, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 06:35:59'),
(252, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 07:58:41'),
(253, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 08:10:18'),
(254, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 08:27:06'),
(255, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 09:10:12'),
(256, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 09:24:12'),
(257, 13, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 09:59:19'),
(258, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 10:35:50'),
(259, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 10:39:18'),
(260, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 10:55:09'),
(261, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 11:01:42'),
(262, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 18:11:01'),
(263, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 18:44:17'),
(264, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 18:52:38'),
(265, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 01:24:02'),
(266, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 02:36:44'),
(267, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 03:00:31'),
(268, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 03:04:08'),
(269, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 03:04:30'),
(270, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 03:59:23'),
(271, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 04:03:40'),
(272, 3, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 05:07:03'),
(273, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-18 06:25:25'),
(274, 3, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 06:27:40'),
(275, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 06:30:06'),
(276, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 06:47:38'),
(277, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 07:52:13'),
(278, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 17:56:51'),
(279, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 18:28:01'),
(280, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 19:57:26'),
(281, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 20:46:04'),
(282, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 21:25:37'),
(283, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 21:26:01'),
(284, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 22:34:30'),
(285, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 23:04:10'),
(286, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 02:34:58'),
(287, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-19 05:34:21'),
(288, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-19 05:46:25'),
(289, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 06:04:17'),
(290, 5, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 07:56:10'),
(291, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 07:59:15'),
(292, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-19 08:42:24'),
(293, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 08:54:16'),
(294, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 09:03:15'),
(295, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-19 09:18:30'),
(296, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-19 09:21:04'),
(297, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-19 09:44:18'),
(298, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 17:45:25'),
(299, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-20 07:46:54'),
(300, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-20 08:47:07'),
(301, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-20 09:29:31'),
(302, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-20 09:30:02'),
(303, 4, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-20 09:39:37'),
(304, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'success', '2026-06-20 09:43:20'),
(305, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'success', '2026-06-20 17:05:00'),
(306, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'success', '2026-06-20 23:08:36'),
(307, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-20 23:14:16'),
(308, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'success', '2026-06-21 00:05:57'),
(309, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 00:47:33');
INSERT INTO `login_activity` (`id`, `user_id`, `ip_address`, `user_agent`, `status`, `created_at`) VALUES
(310, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 03:38:42'),
(311, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'success', '2026-06-21 04:26:23'),
(312, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'success', '2026-06-21 06:51:59'),
(313, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 17:48:41'),
(314, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 17:52:49'),
(315, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 17:54:23'),
(316, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 18:20:33'),
(317, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 18:31:49'),
(318, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 18:32:38'),
(319, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-23 18:55:43'),
(320, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-23 22:54:54'),
(321, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-24 03:03:31'),
(322, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-24 04:21:46'),
(323, 1, '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 02:42:08'),
(324, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 02:45:23'),
(325, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 03:14:35'),
(326, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 03:15:27'),
(327, 1, '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:19:10'),
(328, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:38:15'),
(329, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:48:32'),
(330, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:48:56'),
(331, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:55:49'),
(332, 1, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:56:14');

-- --------------------------------------------------------

--
-- Table structure for table `manager_approvals`
--

CREATE TABLE `manager_approvals` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `type` enum('return','discount','void','stock_adjustment') NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'sale, return, product, etc.',
  `reference_id` int(11) DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `reason` text DEFAULT NULL,
  `manager_note` text DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `manager_approvals`
--

INSERT INTO `manager_approvals` (`id`, `store_id`, `type`, `status`, `reference_type`, `reference_id`, `requested_by`, `reviewed_by`, `amount`, `reason`, `manager_note`, `payload`, `created_at`, `reviewed_at`) VALUES
(1, 1, 'return', 'approved', 'sale', 240, 3, 3, 400000.00, 'other', '', '{\"sale_id\":240,\"items\":[{\"product_id\":14,\"quantity\":1,\"condition\":\"restock\"}],\"reason\":\"other\",\"refund_method\":\"cash\",\"notes\":\"\",\"receipt_no\":\"R1-20260616195714-3\"}', '2026-06-16 19:59:38', '2026-06-16 20:00:42'),
(2, 1, 'return', 'pending', 'sale', 244, 4, NULL, 400000.00, 'defective', NULL, '{\"sale_id\":244,\"items\":[{\"product_id\":14,\"quantity\":1,\"condition\":\"damaged\"}],\"reason\":\"defective\",\"refund_method\":\"cash\",\"notes\":\"\",\"receipt_no\":\"R1-20260617021755-4\"}', '2026-06-17 04:03:57', NULL),
(3, 1, 'return', 'rejected', 'sale', 244, 4, 3, 400000.00, 'customer_request', 'test', '{\"sale_id\":244,\"items\":[{\"product_id\":14,\"quantity\":1,\"condition\":\"damaged\"}],\"reason\":\"customer_request\",\"refund_method\":\"cash\",\"notes\":\"\",\"receipt_no\":\"R1-20260617021755-4\"}', '2026-06-17 04:04:42', '2026-06-17 04:07:36'),
(4, 1, 'return', 'rejected', 'sale', 244, 4, 3, 400000.00, 'customer_request', '', '{\"sale_id\":244,\"items\":[{\"product_id\":14,\"quantity\":1,\"condition\":\"restock\"}],\"reason\":\"customer_request\",\"refund_method\":\"cash\",\"notes\":\"\",\"receipt_no\":\"R1-20260617021755-4\"}', '2026-06-17 04:05:50', '2026-06-17 04:06:51');

-- --------------------------------------------------------

--
-- Table structure for table `manager_audit_log`
--

CREATE TABLE `manager_audit_log` (
  `id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `manager_audit_log`
--

INSERT INTO `manager_audit_log` (`id`, `store_id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 3, 'approval_approved', 'manager_approval', 1, NULL, '127.0.0.1', '2026-06-16 20:00:42'),
(2, 1, 3, 'approval_rejected', 'manager_approval', 4, NULL, '127.0.0.1', '2026-06-17 04:06:51'),
(3, 1, 3, 'approval_rejected', 'manager_approval', 3, NULL, '127.0.0.1', '2026-06-17 04:07:36');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) NOT NULL,
  `uuid` char(36) NOT NULL,
  `user_id` int(11) NOT NULL,
  `template_slug` varchar(100) DEFAULT NULL,
  `type_slug` varchar(40) NOT NULL DEFAULT 'info',
  `category_slug` varchar(50) NOT NULL,
  `module` varchar(40) NOT NULL DEFAULT 'system',
  `priority` enum('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  `severity` enum('info','success','warning','error','critical') NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `action_url` varchar(500) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `store_id` int(10) UNSIGNED DEFAULT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `warehouse_id` int(10) UNSIGNED DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` timestamp NULL DEFAULT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `pinned_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `uuid`, `user_id`, `template_slug`, `type_slug`, `category_slug`, `module`, `priority`, `severity`, `title`, `message`, `payload`, `action_url`, `entity_type`, `entity_id`, `store_id`, `branch_id`, `warehouse_id`, `is_read`, `read_at`, `is_archived`, `archived_at`, `is_pinned`, `pinned_at`, `deleted_at`, `created_at`, `updated_at`, `expires_at`) VALUES
(1, '5c863a85-315f-4e07-96ba-75823b1e995a', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617041554-3 completed for 2 120 000 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617041554-3 completed for 2 120 000 FCFA.\",\"message_fr\":\"Vente R1-20260617041554-3 terminée pour 2 120 000 FCFA.\",\"params\":{\"reference\":\"R1-20260617041554-3\",\"amount\":\"2 120 000 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 1, '2026-06-17 04:26:08', 1, '2026-06-17 04:23:53', 0, NULL, NULL, '2026-06-17 04:15:54', '2026-06-17 04:26:08', NULL),
(2, '8a78eed4-2a8c-492a-94df-8d68147b5063', 2, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617041554-3 completed for 2 120 000 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617041554-3 completed for 2 120 000 FCFA.\",\"message_fr\":\"Vente R1-20260617041554-3 terminée pour 2 120 000 FCFA.\",\"params\":{\"reference\":\"R1-20260617041554-3\",\"amount\":\"2 120 000 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:15:54', NULL, NULL),
(3, 'b7d6341f-4842-4569-9205-2b1f773d4e51', 3, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617041554-3 completed for 2 120 000 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617041554-3 completed for 2 120 000 FCFA.\",\"message_fr\":\"Vente R1-20260617041554-3 terminée pour 2 120 000 FCFA.\",\"params\":{\"reference\":\"R1-20260617041554-3\",\"amount\":\"2 120 000 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:15:54', NULL, NULL),
(4, 'd69f5929-9741-413e-ac65-3eeb267aac73', 6, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617041554-3 completed for 2 120 000 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617041554-3 completed for 2 120 000 FCFA.\",\"message_fr\":\"Vente R1-20260617041554-3 terminée pour 2 120 000 FCFA.\",\"params\":{\"reference\":\"R1-20260617041554-3\",\"amount\":\"2 120 000 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:15:54', NULL, NULL),
(5, 'ff86cf73-85b1-4ce6-b6a9-06b90872b04a', 1, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 2 120 000 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 2 120 000 FCFA recorded.\",\"message_fr\":\"Vente importante de 2 120 000 FCFA enregistrée.\",\"params\":{\"amount\":\"2 120 000 FCFA\",\"reference\":\"R1-20260617041554-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 1, '2026-06-17 04:26:08', 1, '2026-06-17 04:23:56', 0, NULL, NULL, '2026-06-17 04:15:54', '2026-06-17 04:26:08', NULL),
(6, '05145374-05f3-4e2f-b81e-d7dd8111d606', 2, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 2 120 000 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 2 120 000 FCFA recorded.\",\"message_fr\":\"Vente importante de 2 120 000 FCFA enregistrée.\",\"params\":{\"amount\":\"2 120 000 FCFA\",\"reference\":\"R1-20260617041554-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:15:54', NULL, NULL),
(7, '08b68ebe-394f-49a2-9cc9-1c46b1f479fc', 3, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 2 120 000 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 2 120 000 FCFA recorded.\",\"message_fr\":\"Vente importante de 2 120 000 FCFA enregistrée.\",\"params\":{\"amount\":\"2 120 000 FCFA\",\"reference\":\"R1-20260617041554-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:15:54', NULL, NULL),
(8, 'b3e96e25-3a8b-45f0-903f-b301bb224977', 6, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 2 120 000 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 2 120 000 FCFA recorded.\",\"message_fr\":\"Vente importante de 2 120 000 FCFA enregistrée.\",\"params\":{\"amount\":\"2 120 000 FCFA\",\"reference\":\"R1-20260617041554-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:15:54', NULL, NULL),
(9, 'b982c912-8401-4e39-a46e-58dd54213632', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617041637-3 completed for 731 400 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617041637-3 completed for 731 400 FCFA.\",\"message_fr\":\"Vente R1-20260617041637-3 terminée pour 731 400 FCFA.\",\"params\":{\"reference\":\"R1-20260617041637-3\",\"amount\":\"731 400 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 1, '2026-06-17 04:24:55', 0, NULL, 0, NULL, NULL, '2026-06-17 04:16:38', '2026-06-17 04:24:55', NULL),
(10, '4809a463-e9a9-4090-b374-206ff8b1d0b6', 2, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617041637-3 completed for 731 400 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617041637-3 completed for 731 400 FCFA.\",\"message_fr\":\"Vente R1-20260617041637-3 terminée pour 731 400 FCFA.\",\"params\":{\"reference\":\"R1-20260617041637-3\",\"amount\":\"731 400 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:16:38', NULL, NULL),
(11, '076779b2-c223-433c-b84a-beaaf490c647', 3, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617041637-3 completed for 731 400 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617041637-3 completed for 731 400 FCFA.\",\"message_fr\":\"Vente R1-20260617041637-3 terminée pour 731 400 FCFA.\",\"params\":{\"reference\":\"R1-20260617041637-3\",\"amount\":\"731 400 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:16:38', NULL, NULL),
(12, 'eeb54dc2-8278-474c-bec0-f812e59a9dc6', 6, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617041637-3 completed for 731 400 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617041637-3 completed for 731 400 FCFA.\",\"message_fr\":\"Vente R1-20260617041637-3 terminée pour 731 400 FCFA.\",\"params\":{\"reference\":\"R1-20260617041637-3\",\"amount\":\"731 400 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:16:38', NULL, NULL),
(13, 'e55e4979-472f-4760-be94-7c3a08d7d18f', 1, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 731 400 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 731 400 FCFA recorded.\",\"message_fr\":\"Vente importante de 731 400 FCFA enregistrée.\",\"params\":{\"amount\":\"731 400 FCFA\",\"reference\":\"R1-20260617041637-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 1, '2026-06-17 04:23:41', 1, '2026-06-17 04:23:46', 0, NULL, NULL, '2026-06-17 04:16:38', '2026-06-17 04:23:46', NULL),
(14, '7da44f7d-6551-48e7-84f4-1095c048c60f', 2, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 731 400 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 731 400 FCFA recorded.\",\"message_fr\":\"Vente importante de 731 400 FCFA enregistrée.\",\"params\":{\"amount\":\"731 400 FCFA\",\"reference\":\"R1-20260617041637-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:16:38', NULL, NULL),
(15, '5ebef4d6-8df7-4543-bcb7-4ab713be2034', 3, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 731 400 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 731 400 FCFA recorded.\",\"message_fr\":\"Vente importante de 731 400 FCFA enregistrée.\",\"params\":{\"amount\":\"731 400 FCFA\",\"reference\":\"R1-20260617041637-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:16:38', NULL, NULL),
(16, '11d7686a-a578-4ad6-974a-03b2b1f6f192', 6, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 731 400 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 731 400 FCFA recorded.\",\"message_fr\":\"Vente importante de 731 400 FCFA enregistrée.\",\"params\":{\"amount\":\"731 400 FCFA\",\"reference\":\"R1-20260617041637-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:16:38', NULL, NULL),
(17, 'b63da9aa-a8f5-4f79-b20d-4980e0323e67', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617041717-3 completed for 16 960 000 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617041717-3 completed for 16 960 000 FCFA.\",\"message_fr\":\"Vente R1-20260617041717-3 terminée pour 16 960 000 FCFA.\",\"params\":{\"reference\":\"R1-20260617041717-3\",\"amount\":\"16 960 000 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 1, '2026-06-17 04:25:36', 0, NULL, 0, NULL, NULL, '2026-06-17 04:17:17', '2026-06-17 04:25:36', NULL),
(18, 'd755234a-b489-4f5f-8479-f02ed813134d', 2, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617041717-3 completed for 16 960 000 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617041717-3 completed for 16 960 000 FCFA.\",\"message_fr\":\"Vente R1-20260617041717-3 terminée pour 16 960 000 FCFA.\",\"params\":{\"reference\":\"R1-20260617041717-3\",\"amount\":\"16 960 000 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:17:17', NULL, NULL),
(19, '09b08922-92e2-40dc-848f-b9405f7a1d1f', 3, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617041717-3 completed for 16 960 000 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617041717-3 completed for 16 960 000 FCFA.\",\"message_fr\":\"Vente R1-20260617041717-3 terminée pour 16 960 000 FCFA.\",\"params\":{\"reference\":\"R1-20260617041717-3\",\"amount\":\"16 960 000 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:17:17', NULL, NULL),
(20, '5d6be428-df08-4eab-9e7d-24c36b736b24', 6, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617041717-3 completed for 16 960 000 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617041717-3 completed for 16 960 000 FCFA.\",\"message_fr\":\"Vente R1-20260617041717-3 terminée pour 16 960 000 FCFA.\",\"params\":{\"reference\":\"R1-20260617041717-3\",\"amount\":\"16 960 000 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:17:17', NULL, NULL),
(21, '9c1a1fb6-18a5-4356-bf50-f5fe76008001', 1, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 16 960 000 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 16 960 000 FCFA recorded.\",\"message_fr\":\"Vente importante de 16 960 000 FCFA enregistrée.\",\"params\":{\"amount\":\"16 960 000 FCFA\",\"reference\":\"R1-20260617041717-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 1, '2026-06-17 04:25:38', 0, NULL, 0, NULL, NULL, '2026-06-17 04:17:17', '2026-06-17 04:39:55', NULL),
(22, 'ca5e0b1c-ef14-4233-bd6a-e60a67e955bf', 2, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 16 960 000 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 16 960 000 FCFA recorded.\",\"message_fr\":\"Vente importante de 16 960 000 FCFA enregistrée.\",\"params\":{\"amount\":\"16 960 000 FCFA\",\"reference\":\"R1-20260617041717-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:17:17', NULL, NULL),
(23, 'a63db9da-d845-412d-95b3-953522bc745c', 3, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 16 960 000 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 16 960 000 FCFA recorded.\",\"message_fr\":\"Vente importante de 16 960 000 FCFA enregistrée.\",\"params\":{\"amount\":\"16 960 000 FCFA\",\"reference\":\"R1-20260617041717-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:17:17', NULL, NULL),
(24, 'ea1fd789-4203-4ccb-9c6c-3499c0ff013b', 6, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 16 960 000 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 16 960 000 FCFA recorded.\",\"message_fr\":\"Vente importante de 16 960 000 FCFA enregistrée.\",\"params\":{\"amount\":\"16 960 000 FCFA\",\"reference\":\"R1-20260617041717-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:17:17', NULL, NULL),
(25, 'eadac6dc-b0dd-42f2-b6f7-b8e6cb47bbb7', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617042751-3 completed for 1 272 000 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617042751-3 completed for 1 272 000 FCFA.\",\"message_fr\":\"Vente R1-20260617042751-3 terminée pour 1 272 000 FCFA.\",\"params\":{\"reference\":\"R1-20260617042751-3\",\"amount\":\"1 272 000 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 1, '2026-06-17 04:39:47', 0, NULL, 0, NULL, NULL, '2026-06-17 04:27:51', '2026-06-17 04:39:47', NULL),
(26, '2bcf13f7-0524-4e33-803d-e90deb5d9e0d', 2, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617042751-3 completed for 1 272 000 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617042751-3 completed for 1 272 000 FCFA.\",\"message_fr\":\"Vente R1-20260617042751-3 terminée pour 1 272 000 FCFA.\",\"params\":{\"reference\":\"R1-20260617042751-3\",\"amount\":\"1 272 000 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:27:51', NULL, NULL),
(27, '0f532dba-db4d-4a16-93f4-52ccdbdca07d', 3, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617042751-3 completed for 1 272 000 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617042751-3 completed for 1 272 000 FCFA.\",\"message_fr\":\"Vente R1-20260617042751-3 terminée pour 1 272 000 FCFA.\",\"params\":{\"reference\":\"R1-20260617042751-3\",\"amount\":\"1 272 000 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:27:52', NULL, NULL),
(28, '95439e92-7465-4146-a104-dad6150fafa8', 6, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617042751-3 completed for 1 272 000 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617042751-3 completed for 1 272 000 FCFA.\",\"message_fr\":\"Vente R1-20260617042751-3 terminée pour 1 272 000 FCFA.\",\"params\":{\"reference\":\"R1-20260617042751-3\",\"amount\":\"1 272 000 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:27:52', NULL, NULL),
(29, '63409166-0f96-4bb4-a957-1d6003d28f7c', 1, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 1 272 000 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 1 272 000 FCFA recorded.\",\"message_fr\":\"Vente importante de 1 272 000 FCFA enregistrée.\",\"params\":{\"amount\":\"1 272 000 FCFA\",\"reference\":\"R1-20260617042751-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 1, '2026-06-17 04:39:47', 0, NULL, 0, NULL, NULL, '2026-06-17 04:27:52', '2026-06-17 04:39:47', NULL),
(30, 'f8fdf865-9570-48a5-be70-cf6411c6db81', 2, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 1 272 000 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 1 272 000 FCFA recorded.\",\"message_fr\":\"Vente importante de 1 272 000 FCFA enregistrée.\",\"params\":{\"amount\":\"1 272 000 FCFA\",\"reference\":\"R1-20260617042751-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:27:52', NULL, NULL),
(31, 'b86da176-8cbe-48dd-93e1-d35e5e5d2f62', 3, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 1 272 000 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 1 272 000 FCFA recorded.\",\"message_fr\":\"Vente importante de 1 272 000 FCFA enregistrée.\",\"params\":{\"amount\":\"1 272 000 FCFA\",\"reference\":\"R1-20260617042751-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:27:52', NULL, NULL),
(32, 'ca9835f3-6317-44a5-8ccd-e10fddee3e12', 6, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Large Sale', 'Large sale of 1 272 000 FCFA recorded.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 1 272 000 FCFA recorded.\",\"message_fr\":\"Vente importante de 1 272 000 FCFA enregistrée.\",\"params\":{\"amount\":\"1 272 000 FCFA\",\"reference\":\"R1-20260617042751-3\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:27:52', NULL, NULL),
(33, 'b6eaf2d2-d7d0-4d69-89b3-81e6a31972d1', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617042824-3 completed for 1 590 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617042824-3 completed for 1 590 FCFA.\",\"message_fr\":\"Vente R1-20260617042824-3 terminée pour 1 590 FCFA.\",\"params\":{\"reference\":\"R1-20260617042824-3\",\"amount\":\"1 590 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 1, '2026-06-17 04:39:47', 0, NULL, 0, NULL, NULL, '2026-06-17 04:28:24', '2026-06-17 04:39:47', NULL),
(34, '95cb83ba-76e3-454c-af4b-4503eba35b17', 2, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617042824-3 completed for 1 590 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617042824-3 completed for 1 590 FCFA.\",\"message_fr\":\"Vente R1-20260617042824-3 terminée pour 1 590 FCFA.\",\"params\":{\"reference\":\"R1-20260617042824-3\",\"amount\":\"1 590 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:28:24', NULL, NULL),
(35, '1ece3418-f0ab-4ff6-b431-ee3db401b7d5', 3, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617042824-3 completed for 1 590 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617042824-3 completed for 1 590 FCFA.\",\"message_fr\":\"Vente R1-20260617042824-3 terminée pour 1 590 FCFA.\",\"params\":{\"reference\":\"R1-20260617042824-3\",\"amount\":\"1 590 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:28:24', NULL, NULL),
(36, 'ef432451-e863-4fca-9a71-c1483974be6f', 6, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617042824-3 completed for 1 590 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617042824-3 completed for 1 590 FCFA.\",\"message_fr\":\"Vente R1-20260617042824-3 terminée pour 1 590 FCFA.\",\"params\":{\"reference\":\"R1-20260617042824-3\",\"amount\":\"1 590 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:28:24', NULL, NULL),
(37, 'c62d03c5-2ca7-4690-8853-ed8bbbfed439', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617042929-3 completed for 2 120 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617042929-3 completed for 2 120 FCFA.\",\"message_fr\":\"Vente R1-20260617042929-3 terminée pour 2 120 FCFA.\",\"params\":{\"reference\":\"R1-20260617042929-3\",\"amount\":\"2 120 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 1, '2026-06-17 04:39:47', 0, NULL, 0, NULL, NULL, '2026-06-17 04:29:29', '2026-06-17 04:39:47', NULL),
(38, '0690723c-6262-4243-aca1-03cb7c017f08', 2, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617042929-3 completed for 2 120 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617042929-3 completed for 2 120 FCFA.\",\"message_fr\":\"Vente R1-20260617042929-3 terminée pour 2 120 FCFA.\",\"params\":{\"reference\":\"R1-20260617042929-3\",\"amount\":\"2 120 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:29:29', NULL, NULL),
(39, 'c6370afe-538e-4a1b-b636-073313a29363', 3, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617042929-3 completed for 2 120 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617042929-3 completed for 2 120 FCFA.\",\"message_fr\":\"Vente R1-20260617042929-3 terminée pour 2 120 FCFA.\",\"params\":{\"reference\":\"R1-20260617042929-3\",\"amount\":\"2 120 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:29:29', NULL, NULL),
(40, 'a057e35f-a50e-4a07-84aa-58f2abd4c582', 6, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R1-20260617042929-3 completed for 2 120 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260617042929-3 completed for 2 120 FCFA.\",\"message_fr\":\"Vente R1-20260617042929-3 terminée pour 2 120 FCFA.\",\"params\":{\"reference\":\"R1-20260617042929-3\",\"amount\":\"2 120 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-17 04:29:29', NULL, NULL),
(41, '79b2c57b-040f-4cf9-ac1b-33758b136003', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260617090203-1 terminée pour 10 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260617090203-1 completed for 10 FCFA.\",\"message_fr\":\"Vente R11-20260617090203-1 terminée pour 10 FCFA.\",\"params\":{\"reference\":\"R11-20260617090203-1\",\"amount\":\"10 FCFA\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-17 09:54:13', 0, NULL, 0, NULL, NULL, '2026-06-17 09:02:01', '2026-06-17 09:54:13', NULL),
(42, 'b69ccc8a-25e4-437f-a585-05b2a3426daf', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260617090400-1 terminée pour 10 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260617090400-1 completed for 10 FCFA.\",\"message_fr\":\"Vente R11-20260617090400-1 terminée pour 10 FCFA.\",\"params\":{\"reference\":\"R11-20260617090400-1\",\"amount\":\"10 FCFA\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-17 09:54:13', 0, NULL, 0, NULL, NULL, '2026-06-17 09:03:58', '2026-06-17 09:54:13', NULL),
(43, '53c48099-4782-482f-81f7-393da476c045', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260617090810-1 terminée pour 10 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260617090810-1 completed for 10 FCFA.\",\"message_fr\":\"Vente R11-20260617090810-1 terminée pour 10 FCFA.\",\"params\":{\"reference\":\"R11-20260617090810-1\",\"amount\":\"10 FCFA\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-17 09:54:13', 0, NULL, 0, NULL, NULL, '2026-06-17 09:08:08', '2026-06-17 09:54:13', NULL),
(44, 'a356f368-f76b-4c6e-bc2a-ea1605388541', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260617093716-1 terminée pour 41 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260617093716-1 completed for 41 FCFA.\",\"message_fr\":\"Vente R11-20260617093716-1 terminée pour 41 FCFA.\",\"params\":{\"reference\":\"R11-20260617093716-1\",\"amount\":\"41 FCFA\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-17 09:54:13', 0, NULL, 0, NULL, NULL, '2026-06-17 09:37:17', '2026-06-17 09:54:13', NULL),
(45, 'e4ba78be-0599-455f-86e2-2fb12abd4df7', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260617094634-1 terminée pour 227 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260617094634-1 completed for 227 GHS.\",\"message_fr\":\"Vente R11-20260617094634-1 terminée pour 227 GHS.\",\"params\":{\"reference\":\"R11-20260617094634-1\",\"amount\":\"227 GHS\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-17 09:54:13', 0, NULL, 0, NULL, NULL, '2026-06-17 09:46:34', '2026-06-17 09:54:13', NULL),
(46, '0cebca81-8e98-405e-9314-9d0553ec307d', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260617095622-1 terminée pour 15 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260617095622-1 completed for 15 GHS.\",\"message_fr\":\"Vente R11-20260617095622-1 terminée pour 15 GHS.\",\"params\":{\"reference\":\"R11-20260617095622-1\",\"amount\":\"15 GHS\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-17 10:56:11', 0, NULL, 0, NULL, NULL, '2026-06-17 09:56:20', '2026-06-17 10:56:11', NULL),
(47, '7cb8ca50-a986-4a3f-816b-b19b04804412', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260617102839-1 terminée pour 10 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260617102839-1 completed for 10 GHS.\",\"message_fr\":\"Vente R11-20260617102839-1 terminée pour 10 GHS.\",\"params\":{\"reference\":\"R11-20260617102839-1\",\"amount\":\"10 GHS\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-17 10:56:11', 0, NULL, 0, NULL, NULL, '2026-06-17 10:28:39', '2026-06-17 10:56:11', NULL),
(48, '7da7bfb4-1063-4dce-bdc1-4619dbaf92c4', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260617105123-1 terminée pour 10 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260617105123-1 completed for 10 GHS.\",\"message_fr\":\"Vente R11-20260617105123-1 terminée pour 10 GHS.\",\"params\":{\"reference\":\"R11-20260617105123-1\",\"amount\":\"10 GHS\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-17 10:56:11', 0, NULL, 0, NULL, NULL, '2026-06-17 10:51:21', '2026-06-17 10:56:11', NULL),
(49, '032932a9-8e08-4b8c-8472-8f293890118a', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260617185957-1 terminée pour 108 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260617185957-1 completed for 108 GHS.\",\"message_fr\":\"Vente R11-20260617185957-1 terminée pour 108 GHS.\",\"params\":{\"reference\":\"R11-20260617185957-1\",\"amount\":\"108 GHS\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-18 03:57:08', 0, NULL, 0, NULL, NULL, '2026-06-17 18:59:57', '2026-06-18 03:57:08', NULL),
(50, '3b8897ba-d92f-400b-86d7-bf934b6015e0', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260618022909-1 terminée pour 1 328 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260618022909-1 completed for 1 328 GHS.\",\"message_fr\":\"Vente R11-20260618022909-1 terminée pour 1 328 GHS.\",\"params\":{\"reference\":\"R11-20260618022909-1\",\"amount\":\"1 328 GHS\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-18 03:57:08', 0, NULL, 0, NULL, NULL, '2026-06-18 02:29:09', '2026-06-18 03:57:08', NULL),
(51, '767a8c5c-2761-48be-b0d4-e1b90d8a7a4b', 1, 'inventory.low_stock', 'warning', 'inventory_low_stock', 'inventory', 'high', 'warning', 'Alerte stock faible', 'Le stock du produit Full chicken est faible (5 restants).', '{\"title_en\":\"Low Stock Alert\",\"title_fr\":\"Alerte stock faible\",\"message_en\":\"Low stock detected for Full chicken (5 remaining).\",\"message_fr\":\"Le stock du produit Full chicken est faible (5 restants).\",\"params\":{\"product\":\"Full chicken\",\"qty\":\"5\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-18 03:57:08', 0, NULL, 0, NULL, NULL, '2026-06-18 02:48:26', '2026-06-18 03:57:08', NULL),
(52, '67ef1401-9edb-4495-9c67-29b17aec3538', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260618024938-1 terminée pour 1 076 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260618024938-1 completed for 1 076 GHS.\",\"message_fr\":\"Vente R11-20260618024938-1 terminée pour 1 076 GHS.\",\"params\":{\"reference\":\"R11-20260618024938-1\",\"amount\":\"1 076 GHS\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-18 03:57:08', 0, NULL, 0, NULL, NULL, '2026-06-18 02:49:39', '2026-06-18 03:57:08', NULL),
(53, '96312e61-2712-4b1e-97cb-a0c4bd670099', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260618025713-1 terminée pour 46 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260618025713-1 completed for 46 GHS.\",\"message_fr\":\"Vente R11-20260618025713-1 terminée pour 46 GHS.\",\"params\":{\"reference\":\"R11-20260618025713-1\",\"amount\":\"46 GHS\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-18 03:57:08', 0, NULL, 0, NULL, NULL, '2026-06-18 02:57:13', '2026-06-18 03:57:08', NULL),
(54, '98a8ed59-2af8-48c5-83f9-c914ffc4baf2', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260618033141-1 terminée pour 319 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260618033141-1 completed for 319 GHS.\",\"message_fr\":\"Vente R11-20260618033141-1 terminée pour 319 GHS.\",\"params\":{\"reference\":\"R11-20260618033141-1\",\"amount\":\"319 GHS\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-18 03:57:08', 0, NULL, 0, NULL, NULL, '2026-06-18 03:31:41', '2026-06-18 03:57:08', NULL),
(55, '7ecfb0ff-844a-4f2e-a104-87b32a1a2ea1', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260618033238-1 terminée pour 93 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260618033238-1 completed for 93 GHS.\",\"message_fr\":\"Vente R11-20260618033238-1 terminée pour 93 GHS.\",\"params\":{\"reference\":\"R11-20260618033238-1\",\"amount\":\"93 GHS\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-18 03:57:08', 0, NULL, 0, NULL, NULL, '2026-06-18 03:32:39', '2026-06-18 03:57:08', NULL),
(56, '877b9a7c-6156-4350-87ca-48dd80139f24', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R11-20260618035755-1 completed for 15 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260618035755-1 completed for 15 GHS.\",\"message_fr\":\"Vente R11-20260618035755-1 terminée pour 15 GHS.\",\"params\":{\"reference\":\"R11-20260618035755-1\",\"amount\":\"15 GHS\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-18 20:46:25', 0, NULL, 0, NULL, NULL, '2026-06-18 03:57:56', '2026-06-18 20:46:25', NULL),
(57, '292cd23d-8d05-4cce-ad9a-67b2e1235869', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R11-20260618035900-1 completed for 15 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260618035900-1 completed for 15 GHS.\",\"message_fr\":\"Vente R11-20260618035900-1 terminée pour 15 GHS.\",\"params\":{\"reference\":\"R11-20260618035900-1\",\"amount\":\"15 GHS\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-18 20:46:25', 0, NULL, 0, NULL, NULL, '2026-06-18 03:59:01', '2026-06-18 20:46:25', NULL),
(58, '7a7a0424-f73b-4805-bfa5-c3600d057f48', 1, 'cash_register.opened', 'info', 'cash_register', 'cash_register', 'normal', 'info', 'Caisse ouverte', 'Caisse C1 ouverte avec 25,000 GHS.', '{\"title_en\":\"Register Opened\",\"title_fr\":\"Caisse ouverte\",\"message_en\":\"Register C1 opened with 25,000 GHS.\",\"message_fr\":\"Caisse C1 ouverte avec 25,000 GHS.\",\"params\":{\"register\":\"C1\",\"amount\":\"25,000 GHS\",\"variance\":\"\"}}', '/public/admin/cash_registers/register_details.php?id=6', 'cash_register', 6, 11, NULL, NULL, 1, '2026-06-18 20:46:25', 0, NULL, 0, NULL, NULL, '2026-06-18 04:44:20', '2026-06-18 20:46:25', NULL),
(59, '10cc87d6-e4a5-4b11-829d-86a46ba4eda2', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260618045018-1 terminée pour 541 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260618045018-1 completed for 541 GHS.\",\"message_fr\":\"Vente R11-20260618045018-1 terminée pour 541 GHS.\",\"params\":{\"reference\":\"R11-20260618045018-1\",\"amount\":\"541 GHS\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-18 20:46:25', 0, NULL, 0, NULL, NULL, '2026-06-18 04:50:19', '2026-06-18 20:46:25', NULL),
(60, '12a380f1-4a50-4c6a-a7a6-e220b642e05c', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R11-20260618045450-1 terminée pour 232 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R11-20260618045450-1 completed for 232 GHS.\",\"message_fr\":\"Vente R11-20260618045450-1 terminée pour 232 GHS.\",\"params\":{\"reference\":\"R11-20260618045450-1\",\"amount\":\"232 GHS\"}}', NULL, NULL, NULL, 11, NULL, NULL, 1, '2026-06-18 20:46:25', 0, NULL, 0, NULL, NULL, '2026-06-18 04:54:51', '2026-06-18 20:46:25', NULL),
(61, '11f81df1-c395-4d1b-a3c6-1311df33f8d7', 1, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#1 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#1 completed.\",\"message_fr\":\"Réception GRN#1 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#1 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#1\"}}', NULL, NULL, NULL, NULL, NULL, 4, 1, '2026-06-21 03:32:04', 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:39', '2026-06-21 03:32:04', NULL),
(62, '18330424-503a-4a16-802c-a0a2b507f6a3', 2, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#1 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#1 completed.\",\"message_fr\":\"Réception GRN#1 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#1 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#1\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:40', NULL, NULL),
(63, '3b949132-bf6e-49db-b073-450652c74fe3', 3, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#1 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#1 completed.\",\"message_fr\":\"Réception GRN#1 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#1 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#1\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:40', NULL, NULL),
(64, '55c070c0-6819-4499-bc90-ce441650286c', 6, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#1 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#1 completed.\",\"message_fr\":\"Réception GRN#1 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#1 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#1\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:40', NULL, NULL),
(65, '2c76d398-60da-4339-9bbb-b971a9be1ff1', 8, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#1 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#1 completed.\",\"message_fr\":\"Réception GRN#1 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#1 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#1\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:40', NULL, NULL),
(66, '46e2ac2f-8837-41bd-b1c5-a4b53c6f1bdd', 10, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#1 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#1 completed.\",\"message_fr\":\"Réception GRN#1 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#1 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#1\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:40', NULL, NULL),
(67, '5ae562f6-5812-461e-b1b8-f92df0e98b9e', 11, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#1 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#1 completed.\",\"message_fr\":\"Réception GRN#1 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#1 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#1\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:40', NULL, NULL),
(68, '1f3e3050-93f1-429d-a296-98db4ee366bd', 13, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#1 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#1 completed.\",\"message_fr\":\"Réception GRN#1 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#1 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#1\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:40', NULL, NULL),
(69, 'e8b42d4c-8ec0-4f1a-bb7b-d9424130948e', 1, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3B49A8 completed.\",\"message_fr\":\"Réception GRN-20260620-3B49A8 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3B49A8 — 4,000,000 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3B49A8\"}}', NULL, NULL, NULL, NULL, NULL, 4, 1, '2026-06-21 03:32:04', 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:53', '2026-06-21 03:32:04', NULL),
(70, '8b4ae530-1c21-47c8-8186-5d683064b016', 2, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3B49A8 completed.\",\"message_fr\":\"Réception GRN-20260620-3B49A8 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3B49A8 — 4,000,000 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3B49A8\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:53', NULL, NULL),
(71, '5ce13c8f-d012-44dd-bd06-db96d2d6495f', 3, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3B49A8 completed.\",\"message_fr\":\"Réception GRN-20260620-3B49A8 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3B49A8 — 4,000,000 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3B49A8\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:53', NULL, NULL),
(72, '560f556a-d70e-4aa5-9acf-b30589c8c03e', 6, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3B49A8 completed.\",\"message_fr\":\"Réception GRN-20260620-3B49A8 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3B49A8 — 4,000,000 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3B49A8\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:53', NULL, NULL),
(73, 'f3740c54-88d6-480d-9a5a-1f5ee53af122', 8, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3B49A8 completed.\",\"message_fr\":\"Réception GRN-20260620-3B49A8 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3B49A8 — 4,000,000 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3B49A8\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:53', NULL, NULL),
(74, '150fa296-c0a0-4bf0-b9df-2f31161c2fad', 10, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3B49A8 completed.\",\"message_fr\":\"Réception GRN-20260620-3B49A8 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3B49A8 — 4,000,000 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3B49A8\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:53', NULL, NULL),
(75, '4957a59e-0241-49c5-bd85-6f34eac7b774', 11, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3B49A8 completed.\",\"message_fr\":\"Réception GRN-20260620-3B49A8 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3B49A8 — 4,000,000 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3B49A8\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:53', NULL, NULL),
(76, '493f13c7-4dd1-4b3b-8640-2ad7c52729ef', 13, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3B49A8 completed.\",\"message_fr\":\"Réception GRN-20260620-3B49A8 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3B49A8 — 4,000,000 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3B49A8\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:23:53', NULL, NULL),
(77, 'a00dd7ba-c24c-4c7e-8313-ea83a619eb33', 1, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#2 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#2 completed.\",\"message_fr\":\"Réception GRN#2 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#2 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#2\"}}', NULL, NULL, NULL, NULL, NULL, 4, 1, '2026-06-21 03:32:04', 0, NULL, 0, NULL, NULL, '2026-06-20 10:30:39', '2026-06-21 03:32:04', NULL),
(78, '1b02bd2d-22cb-4667-bbf9-1415ce59ec73', 2, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#2 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#2 completed.\",\"message_fr\":\"Réception GRN#2 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#2 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#2\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:30:39', NULL, NULL),
(79, 'e346d349-432e-4111-a903-17db0b80637c', 3, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#2 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#2 completed.\",\"message_fr\":\"Réception GRN#2 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#2 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#2\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:30:39', NULL, NULL),
(80, '9f5ca9a8-0636-4a34-bbf0-9cb6a02aaa81', 6, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#2 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#2 completed.\",\"message_fr\":\"Réception GRN#2 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#2 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#2\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:30:39', NULL, NULL),
(81, '9e1497e2-4f24-4715-8bc1-bd82b2f96a85', 8, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#2 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#2 completed.\",\"message_fr\":\"Réception GRN#2 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#2 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#2\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:30:39', NULL, NULL),
(82, '04af4795-a72b-40a6-ae66-6bd41029506e', 10, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#2 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#2 completed.\",\"message_fr\":\"Réception GRN#2 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#2 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#2\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:30:39', NULL, NULL),
(83, '6ac6c055-4358-4d0e-a0da-8a688b78df5f', 11, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#2 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#2 completed.\",\"message_fr\":\"Réception GRN#2 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#2 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#2\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:30:39', NULL, NULL);
INSERT INTO `notifications` (`id`, `uuid`, `user_id`, `template_slug`, `type_slug`, `category_slug`, `module`, `priority`, `severity`, `title`, `message`, `payload`, `action_url`, `entity_type`, `entity_id`, `store_id`, `branch_id`, `warehouse_id`, `is_read`, `read_at`, `is_archived`, `archived_at`, `is_pinned`, `pinned_at`, `deleted_at`, `created_at`, `updated_at`, `expires_at`) VALUES
(84, 'b03d62e6-9275-43db-b03a-4565422e8605', 13, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#2 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#2 completed.\",\"message_fr\":\"Réception GRN#2 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#2 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#2\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:30:39', NULL, NULL),
(85, 'b4d87015-b1b6-4dda-9198-75b3f55e11db', 1, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3C369D completed.\",\"message_fr\":\"Réception GRN-20260620-3C369D terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3C369D — 2,299,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3C369D\"}}', NULL, NULL, NULL, NULL, NULL, 4, 1, '2026-06-21 03:32:04', 0, NULL, 0, NULL, NULL, '2026-06-20 10:31:00', '2026-06-21 03:32:04', NULL),
(86, '4989f05b-236b-4ce0-8ca5-4f6430697ea7', 2, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3C369D completed.\",\"message_fr\":\"Réception GRN-20260620-3C369D terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3C369D — 2,299,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3C369D\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:31:00', NULL, NULL),
(87, 'b60e568b-b840-42b3-a7ed-7090762c16d2', 3, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3C369D completed.\",\"message_fr\":\"Réception GRN-20260620-3C369D terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3C369D — 2,299,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3C369D\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:31:00', NULL, NULL),
(88, 'b82755dc-0e33-4e05-9623-06df5af777d9', 6, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3C369D completed.\",\"message_fr\":\"Réception GRN-20260620-3C369D terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3C369D — 2,299,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3C369D\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:31:00', NULL, NULL),
(89, 'f2a8384c-87fd-41df-ab4b-d7c476b85bdc', 8, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3C369D completed.\",\"message_fr\":\"Réception GRN-20260620-3C369D terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3C369D — 2,299,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3C369D\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:31:00', NULL, NULL),
(90, 'e2979c14-1fbe-4af7-b6e0-67d623e1370d', 10, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3C369D completed.\",\"message_fr\":\"Réception GRN-20260620-3C369D terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3C369D — 2,299,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3C369D\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:31:00', NULL, NULL),
(91, '33ae2a0f-f90b-43b9-bd90-4e82a47f887e', 11, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3C369D completed.\",\"message_fr\":\"Réception GRN-20260620-3C369D terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3C369D — 2,299,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3C369D\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:31:00', NULL, NULL),
(92, 'b5c70f5a-22a5-4b49-9ef5-07d2669e5749', 13, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-3C369D completed.\",\"message_fr\":\"Réception GRN-20260620-3C369D terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-3C369D — 2,299,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-3C369D\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 10:31:00', NULL, NULL),
(93, '248eee7b-0a4d-449a-893b-3489b03a3151', 1, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#3 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#3 completed.\",\"message_fr\":\"Réception GRN#3 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#3 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#3\"}}', NULL, NULL, NULL, NULL, NULL, 4, 1, '2026-06-21 03:32:04', 0, NULL, 0, NULL, NULL, '2026-06-20 11:02:53', '2026-06-21 03:32:04', NULL),
(94, 'a3972738-b3f1-4eba-9238-77a155945f88', 2, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#3 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#3 completed.\",\"message_fr\":\"Réception GRN#3 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#3 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#3\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:02:53', NULL, NULL),
(95, '2bb4d978-f78b-4e8b-885a-b8845d6a230b', 3, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#3 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#3 completed.\",\"message_fr\":\"Réception GRN#3 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#3 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#3\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:02:53', NULL, NULL),
(96, '14e7a22b-b007-41b5-a9de-cd2364541943', 6, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#3 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#3 completed.\",\"message_fr\":\"Réception GRN#3 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#3 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#3\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:02:53', NULL, NULL),
(97, '1154a9e5-e43f-495c-a5ff-16ba4cf8e12b', 8, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#3 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#3 completed.\",\"message_fr\":\"Réception GRN#3 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#3 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#3\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:02:53', NULL, NULL),
(98, 'dc9f0e4a-2b5b-4ff6-ab4b-ea3179b5c841', 10, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#3 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#3 completed.\",\"message_fr\":\"Réception GRN#3 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#3 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#3\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:02:53', NULL, NULL),
(99, '36b7cebf-5a76-47e6-a25a-bf5f79ddfbe1', 11, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#3 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#3 completed.\",\"message_fr\":\"Réception GRN#3 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#3 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#3\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:02:53', NULL, NULL),
(100, 'fc7e51fd-b8a0-4e23-b112-06390d0cfe5d', 13, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#3 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#3 completed.\",\"message_fr\":\"Réception GRN#3 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#3 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#3\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:02:53', NULL, NULL),
(101, 'a56993ef-4485-4abf-affc-4473856799d8', 1, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-26921E completed.\",\"message_fr\":\"Réception GRN-20260620-26921E terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-26921E — 1,824,900 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-26921E\"}}', NULL, NULL, NULL, NULL, NULL, 4, 1, '2026-06-21 03:32:04', 0, NULL, 0, NULL, NULL, '2026-06-20 11:03:17', '2026-06-21 03:32:04', NULL),
(102, 'f724bd04-a30e-4a88-8ff2-e6827d288fc0', 2, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-26921E completed.\",\"message_fr\":\"Réception GRN-20260620-26921E terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-26921E — 1,824,900 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-26921E\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:03:17', NULL, NULL),
(103, 'f7476865-4155-4ba1-a8dd-76a489db375b', 3, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-26921E completed.\",\"message_fr\":\"Réception GRN-20260620-26921E terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-26921E — 1,824,900 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-26921E\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:03:17', NULL, NULL),
(104, 'b5bc56bd-3d44-40d4-a919-b3306ef86546', 6, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-26921E completed.\",\"message_fr\":\"Réception GRN-20260620-26921E terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-26921E — 1,824,900 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-26921E\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:03:17', NULL, NULL),
(105, '36ec075e-9a1a-4174-8252-4d1f0b6beec5', 8, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-26921E completed.\",\"message_fr\":\"Réception GRN-20260620-26921E terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-26921E — 1,824,900 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-26921E\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:03:17', NULL, NULL),
(106, '5112d222-adb0-4d00-8182-a0bfd5974e06', 10, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-26921E completed.\",\"message_fr\":\"Réception GRN-20260620-26921E terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-26921E — 1,824,900 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-26921E\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:03:17', NULL, NULL),
(107, 'b9f0f880-6a7c-4142-b19d-38696c1252e0', 11, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-26921E completed.\",\"message_fr\":\"Réception GRN-20260620-26921E terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-26921E — 1,824,900 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-26921E\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:03:17', NULL, NULL),
(108, 'ca140ac7-52e2-4b26-9b72-6ed8aefc76ae', 13, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-26921E completed.\",\"message_fr\":\"Réception GRN-20260620-26921E terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-26921E — 1,824,900 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-26921E\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:03:17', NULL, NULL),
(109, '2a14ce1f-ca2b-4fb1-8733-ece85e86037e', 1, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#4 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#4 completed.\",\"message_fr\":\"Réception GRN#4 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#4 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#4\"}}', NULL, NULL, NULL, NULL, NULL, 4, 1, '2026-06-21 03:32:04', 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:39', '2026-06-21 03:32:04', NULL),
(110, '0760276f-b562-47c1-8bd7-2e45fcb5d2d9', 2, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#4 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#4 completed.\",\"message_fr\":\"Réception GRN#4 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#4 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#4\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:39', NULL, NULL),
(111, '98a8e566-7431-49c5-8d03-c31fdc325b93', 3, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#4 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#4 completed.\",\"message_fr\":\"Réception GRN#4 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#4 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#4\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:39', NULL, NULL),
(112, '56be3e3f-a471-44b7-8dd8-c2f372179836', 6, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#4 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#4 completed.\",\"message_fr\":\"Réception GRN#4 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#4 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#4\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:39', NULL, NULL),
(113, 'b95a54bb-f20b-41b4-b3c5-5ed3173c57be', 8, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#4 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#4 completed.\",\"message_fr\":\"Réception GRN#4 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#4 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#4\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:39', NULL, NULL),
(114, '9583c237-7264-48c7-87bb-17a7d385515b', 10, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#4 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#4 completed.\",\"message_fr\":\"Réception GRN#4 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#4 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#4\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:39', NULL, NULL),
(115, 'fd8869bd-4983-429b-9c2a-efa16a26818c', 11, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#4 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#4 completed.\",\"message_fr\":\"Réception GRN#4 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#4 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#4\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:39', NULL, NULL),
(116, 'c430efd1-89d3-4885-a557-a2922c633a1f', 13, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#4 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#4 completed.\",\"message_fr\":\"Réception GRN#4 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#4 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#4\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:39', NULL, NULL),
(117, '82164445-9d62-47b2-a00d-ed6bd4660955', 1, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-32F128 completed.\",\"message_fr\":\"Réception GRN-20260620-32F128 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-32F128 — 200 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-32F128\"}}', NULL, NULL, NULL, NULL, NULL, 4, 1, '2026-06-21 03:32:04', 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:46', '2026-06-21 03:32:04', NULL),
(118, '55106aa2-ae51-4516-9a50-2231079971a8', 2, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-32F128 completed.\",\"message_fr\":\"Réception GRN-20260620-32F128 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-32F128 — 200 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-32F128\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:46', NULL, NULL),
(119, '8473f604-702d-47bc-bd85-445317dc786a', 3, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-32F128 completed.\",\"message_fr\":\"Réception GRN-20260620-32F128 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-32F128 — 200 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-32F128\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:46', NULL, NULL),
(120, '38b683fb-6eb2-4ccb-b171-d29b60662c69', 6, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-32F128 completed.\",\"message_fr\":\"Réception GRN-20260620-32F128 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-32F128 — 200 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-32F128\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:46', NULL, NULL),
(121, 'e9b535b7-a631-4fcd-b2eb-a9802cb0e955', 8, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-32F128 completed.\",\"message_fr\":\"Réception GRN-20260620-32F128 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-32F128 — 200 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-32F128\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:46', NULL, NULL),
(122, '68a091ff-e6f4-4515-8b3c-a9316ace5b03', 10, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-32F128 completed.\",\"message_fr\":\"Réception GRN-20260620-32F128 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-32F128 — 200 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-32F128\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:46', NULL, NULL),
(123, '5c084488-edc2-47c1-b128-81f733ec44fe', 11, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-32F128 completed.\",\"message_fr\":\"Réception GRN-20260620-32F128 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-32F128 — 200 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-32F128\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:46', NULL, NULL),
(124, '6210bdcc-7624-4c5a-8789-1c9bb6fc1da8', 13, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-32F128 completed.\",\"message_fr\":\"Réception GRN-20260620-32F128 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-32F128 — 200 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-32F128\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:08:46', NULL, NULL),
(125, 'ffcbb796-ae5c-487f-991e-56bf032cb537', 1, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#5 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#5 completed.\",\"message_fr\":\"Réception GRN#5 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#5 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#5\"}}', NULL, NULL, NULL, NULL, NULL, 4, 1, '2026-06-28 21:27:52', 1, '2026-06-21 03:31:59', 0, NULL, NULL, '2026-06-20 11:12:19', '2026-06-28 21:27:52', NULL),
(126, 'fa9adbff-9640-49e4-b558-b023ca908556', 2, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#5 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#5 completed.\",\"message_fr\":\"Réception GRN#5 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#5 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#5\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:12:19', NULL, NULL),
(127, '4ef09c2c-6f7f-42ef-a1d5-fd02fe57ff8c', 3, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#5 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#5 completed.\",\"message_fr\":\"Réception GRN#5 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#5 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#5\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:12:19', NULL, NULL),
(128, 'd1d565e6-2933-4074-a116-a47d9169d351', 6, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#5 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#5 completed.\",\"message_fr\":\"Réception GRN#5 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#5 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#5\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:12:19', NULL, NULL),
(129, '625b86f1-05f0-4b7d-b417-10b701e691f8', 8, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#5 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#5 completed.\",\"message_fr\":\"Réception GRN#5 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#5 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#5\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:12:19', NULL, NULL),
(130, 'd8dc34de-8745-4a77-b4a6-048fdeef4474', 10, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#5 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#5 completed.\",\"message_fr\":\"Réception GRN#5 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#5 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#5\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:12:19', NULL, NULL),
(131, 'c55a0bb0-daae-4bd2-a00f-fee1b2423eb2', 11, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#5 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#5 completed.\",\"message_fr\":\"Réception GRN#5 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#5 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#5\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:12:19', NULL, NULL),
(132, '35750f9b-7e32-461e-9f9e-164cce0e001c', 13, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#5 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#5 completed.\",\"message_fr\":\"Réception GRN#5 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#5 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#5\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:12:19', NULL, NULL),
(133, 'b37cc6d4-8325-4736-a69d-806fb02bce97', 1, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-790196 completed.\",\"message_fr\":\"Réception GRN-20260620-790196 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-790196 — 9,098,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-790196\"}}', NULL, NULL, NULL, NULL, NULL, 4, 1, '2026-06-21 03:32:04', 0, NULL, 0, NULL, NULL, '2026-06-20 11:12:19', '2026-06-21 03:32:04', NULL),
(134, '3a51fe7a-705f-4ff3-83ba-36bc667f9653', 2, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-790196 completed.\",\"message_fr\":\"Réception GRN-20260620-790196 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-790196 — 9,098,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-790196\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:12:19', NULL, NULL),
(135, '15d2aebc-a609-4389-aabe-0a9606368784', 3, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-790196 completed.\",\"message_fr\":\"Réception GRN-20260620-790196 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-790196 — 9,098,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-790196\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:12:19', NULL, NULL),
(136, '3f95598f-feea-4e62-8596-209c677c6ae0', 6, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-790196 completed.\",\"message_fr\":\"Réception GRN-20260620-790196 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-790196 — 9,098,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-790196\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:12:19', NULL, NULL),
(137, 'a86bfdad-a4a8-4edb-aeb4-1951ba2f0849', 8, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-790196 completed.\",\"message_fr\":\"Réception GRN-20260620-790196 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-790196 — 9,098,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-790196\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:12:19', NULL, NULL),
(138, '0b204078-6cc6-4a10-a0ce-3816e6475758', 10, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-790196 completed.\",\"message_fr\":\"Réception GRN-20260620-790196 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-790196 — 9,098,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-790196\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:12:19', NULL, NULL),
(139, '4dee98d5-6fea-4a3a-adbb-321de7d6dc90', 11, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-790196 completed.\",\"message_fr\":\"Réception GRN-20260620-790196 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-790196 — 9,098,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-790196\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:12:19', NULL, NULL),
(140, '338d6971-b573-42be-8e1f-b9f27e192ebc', 13, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN-20260620-790196 completed.\",\"message_fr\":\"Réception GRN-20260620-790196 terminée.\",\"params\":{\"product\":\"Goods received GRN-20260620-790196 — 9,098,600 FCFA\",\"qty\":\"\",\"reference\":\"GRN-20260620-790196\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:12:19', NULL, NULL),
(141, '46f552e2-ce10-4d61-b414-d7deda4c8bd3', 1, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#6 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#6 completed.\",\"message_fr\":\"Réception GRN#6 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#6 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#6\"}}', NULL, NULL, NULL, NULL, NULL, 4, 1, '2026-06-21 03:31:47', 0, NULL, 1, '2026-06-21 03:39:39', NULL, '2026-06-20 11:19:37', '2026-06-21 03:39:39', NULL),
(142, 'fab81917-3286-4a5b-a9dc-d7c380cb5e6a', 2, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#6 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#6 completed.\",\"message_fr\":\"Réception GRN#6 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#6 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#6\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:19:37', NULL, NULL),
(143, '268c6095-329e-4d4a-a52a-20c8027f10ee', 3, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#6 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#6 completed.\",\"message_fr\":\"Réception GRN#6 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#6 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#6\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:19:37', NULL, NULL),
(144, 'd6d4f44f-bf98-4f75-8da5-a5bfcf22c6ff', 6, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#6 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#6 completed.\",\"message_fr\":\"Réception GRN#6 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#6 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#6\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:19:37', NULL, NULL),
(145, '49866367-7f36-437f-9733-012e329bc8ac', 8, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#6 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#6 completed.\",\"message_fr\":\"Réception GRN#6 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#6 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#6\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:19:37', NULL, NULL),
(146, '59ece758-e8de-496f-8fd9-2490a797d616', 10, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#6 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#6 completed.\",\"message_fr\":\"Réception GRN#6 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#6 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#6\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:19:37', NULL, NULL),
(147, '6bc7a813-39f8-4cc7-b73a-3a89571af960', 11, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#6 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#6 completed.\",\"message_fr\":\"Réception GRN#6 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#6 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#6\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:19:37', NULL, NULL),
(148, '63b213c6-4603-42c9-a4b8-57435f75a493', 13, 'warehouse.receiving_completed', 'success', 'inventory_low_stock', 'inventory', 'normal', 'info', 'Receiving Completed', 'Goods receipt GRN#6 completed.', '{\"title_en\":\"Receiving Completed\",\"title_fr\":\"Réception terminée\",\"message_en\":\"Goods receipt GRN#6 completed.\",\"message_fr\":\"Réception GRN#6 terminée.\",\"params\":{\"product\":\"Incoming delivery GRN#6 pending inspection\",\"qty\":\"\",\"reference\":\"GRN#6\"}}', NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 11:19:37', NULL, NULL),
(149, '652fa958-0b9e-47f3-b64e-85cd194f1799', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260620231011-1 completed for 6 896 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260620231011-1 completed for 6 896 GHS.\",\"message_fr\":\"Vente R7-20260620231011-1 terminée pour 6 896 GHS.\",\"params\":{\"reference\":\"R7-20260620231011-1\",\"amount\":\"6 896 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 1, '2026-06-20 23:14:52', 0, NULL, 0, NULL, NULL, '2026-06-20 23:11:05', '2026-06-20 23:14:52', NULL),
(150, '0eae660a-b02a-46ab-b4d2-e12f9a348a47', 10, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260620231011-1 completed for 6 896 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260620231011-1 completed for 6 896 GHS.\",\"message_fr\":\"Vente R7-20260620231011-1 terminée pour 6 896 GHS.\",\"params\":{\"reference\":\"R7-20260620231011-1\",\"amount\":\"6 896 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 23:11:05', NULL, NULL),
(151, '87b30302-5031-40b3-becc-4c41d34ca861', 11, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260620231011-1 completed for 6 896 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260620231011-1 completed for 6 896 GHS.\",\"message_fr\":\"Vente R7-20260620231011-1 terminée pour 6 896 GHS.\",\"params\":{\"reference\":\"R7-20260620231011-1\",\"amount\":\"6 896 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 23:11:05', NULL, NULL),
(152, 'db85e849-8be2-4f37-aeb4-7e183b989af3', 13, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260620231011-1 completed for 6 896 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260620231011-1 completed for 6 896 GHS.\",\"message_fr\":\"Vente R7-20260620231011-1 terminée pour 6 896 GHS.\",\"params\":{\"reference\":\"R7-20260620231011-1\",\"amount\":\"6 896 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 23:11:05', NULL, NULL),
(153, 'b1cea65c-d0c3-4fc3-a708-a4bb238b0e7f', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260620231225-1 completed for 34 560 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260620231225-1 completed for 34 560 GHS.\",\"message_fr\":\"Vente R7-20260620231225-1 terminée pour 34 560 GHS.\",\"params\":{\"reference\":\"R7-20260620231225-1\",\"amount\":\"34 560 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 1, '2026-06-20 23:16:45', 0, NULL, 0, NULL, NULL, '2026-06-20 23:12:26', '2026-06-20 23:16:45', NULL),
(154, 'd0f4a894-8195-4a65-97f3-3f8119633586', 10, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260620231225-1 completed for 34 560 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260620231225-1 completed for 34 560 GHS.\",\"message_fr\":\"Vente R7-20260620231225-1 terminée pour 34 560 GHS.\",\"params\":{\"reference\":\"R7-20260620231225-1\",\"amount\":\"34 560 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 23:12:26', NULL, NULL),
(155, '0ca72ebd-62d7-4d21-8f36-50ac1c2a6f3c', 11, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260620231225-1 completed for 34 560 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260620231225-1 completed for 34 560 GHS.\",\"message_fr\":\"Vente R7-20260620231225-1 terminée pour 34 560 GHS.\",\"params\":{\"reference\":\"R7-20260620231225-1\",\"amount\":\"34 560 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 23:12:26', NULL, NULL),
(156, '233f38c6-c607-4f72-8d58-f2b19b3610a9', 13, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260620231225-1 completed for 34 560 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260620231225-1 completed for 34 560 GHS.\",\"message_fr\":\"Vente R7-20260620231225-1 terminée pour 34 560 GHS.\",\"params\":{\"reference\":\"R7-20260620231225-1\",\"amount\":\"34 560 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 23:12:26', NULL, NULL),
(157, '4b9072d7-03a0-4d1c-b8ec-5d0196ef0bea', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260620231313-1 completed for 432 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260620231313-1 completed for 432 GHS.\",\"message_fr\":\"Vente R7-20260620231313-1 terminée pour 432 GHS.\",\"params\":{\"reference\":\"R7-20260620231313-1\",\"amount\":\"432 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 1, '2026-06-20 23:14:31', 0, NULL, 0, NULL, NULL, '2026-06-20 23:13:14', '2026-06-20 23:14:31', NULL),
(158, '4f1a98d2-8308-4090-80ea-6060de5a2f1c', 10, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260620231313-1 completed for 432 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260620231313-1 completed for 432 GHS.\",\"message_fr\":\"Vente R7-20260620231313-1 terminée pour 432 GHS.\",\"params\":{\"reference\":\"R7-20260620231313-1\",\"amount\":\"432 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 23:13:14', NULL, NULL),
(159, '271eb26f-5715-49a7-b824-c7e252d353ba', 11, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260620231313-1 completed for 432 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260620231313-1 completed for 432 GHS.\",\"message_fr\":\"Vente R7-20260620231313-1 terminée pour 432 GHS.\",\"params\":{\"reference\":\"R7-20260620231313-1\",\"amount\":\"432 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 23:13:14', NULL, NULL),
(160, '1a2b9a8f-b8f1-49be-a4a7-c41c5b6402fe', 13, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260620231313-1 completed for 432 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260620231313-1 completed for 432 GHS.\",\"message_fr\":\"Vente R7-20260620231313-1 terminée pour 432 GHS.\",\"params\":{\"reference\":\"R7-20260620231313-1\",\"amount\":\"432 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-20 23:13:14', NULL, NULL),
(161, '41c48d1d-c7fe-4e83-90ac-0a9fb1145efc', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260621000752-1 completed for 972 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260621000752-1 completed for 972 GHS.\",\"message_fr\":\"Vente R7-20260621000752-1 terminée pour 972 GHS.\",\"params\":{\"reference\":\"R7-20260621000752-1\",\"amount\":\"972 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 1, '2026-06-21 03:16:56', 0, NULL, 0, NULL, NULL, '2026-06-21 00:07:53', '2026-06-21 03:16:56', NULL),
(162, 'cf2b18a5-bdbd-4511-b16b-3255b55a805e', 10, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260621000752-1 completed for 972 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260621000752-1 completed for 972 GHS.\",\"message_fr\":\"Vente R7-20260621000752-1 terminée pour 972 GHS.\",\"params\":{\"reference\":\"R7-20260621000752-1\",\"amount\":\"972 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-21 00:07:53', NULL, NULL),
(163, '8835d885-0152-40e8-bcfe-a6804300b60c', 11, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260621000752-1 completed for 972 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260621000752-1 completed for 972 GHS.\",\"message_fr\":\"Vente R7-20260621000752-1 terminée pour 972 GHS.\",\"params\":{\"reference\":\"R7-20260621000752-1\",\"amount\":\"972 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-21 00:07:53', NULL, NULL);
INSERT INTO `notifications` (`id`, `uuid`, `user_id`, `template_slug`, `type_slug`, `category_slug`, `module`, `priority`, `severity`, `title`, `message`, `payload`, `action_url`, `entity_type`, `entity_id`, `store_id`, `branch_id`, `warehouse_id`, `is_read`, `read_at`, `is_archived`, `archived_at`, `is_pinned`, `pinned_at`, `deleted_at`, `created_at`, `updated_at`, `expires_at`) VALUES
(164, 'c2c55405-59ea-4a6c-be45-89d4ec31786b', 13, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Sale Completed', 'Sale R7-20260621000752-1 completed for 972 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260621000752-1 completed for 972 GHS.\",\"message_fr\":\"Vente R7-20260621000752-1 terminée pour 972 GHS.\",\"params\":{\"reference\":\"R7-20260621000752-1\",\"amount\":\"972 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-21 00:07:53', NULL, NULL),
(165, 'f491a7a5-ae8d-4ae8-8bd9-5f27999ca039', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R7-20260621182845-1 terminée pour 6 372 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260621182845-1 completed for 6 372 GHS.\",\"message_fr\":\"Vente R7-20260621182845-1 terminée pour 6 372 GHS.\",\"params\":{\"reference\":\"R7-20260621182845-1\",\"amount\":\"6 372 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-21 18:28:46', NULL, NULL),
(166, '0615bb9d-b651-469b-97a5-49957f72807a', 10, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R7-20260621182845-1 terminée pour 6 372 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260621182845-1 completed for 6 372 GHS.\",\"message_fr\":\"Vente R7-20260621182845-1 terminée pour 6 372 GHS.\",\"params\":{\"reference\":\"R7-20260621182845-1\",\"amount\":\"6 372 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-21 18:28:46', NULL, NULL),
(167, '1b1f9bf5-6d48-4106-8865-d25d996ef0e2', 11, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R7-20260621182845-1 terminée pour 6 372 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260621182845-1 completed for 6 372 GHS.\",\"message_fr\":\"Vente R7-20260621182845-1 terminée pour 6 372 GHS.\",\"params\":{\"reference\":\"R7-20260621182845-1\",\"amount\":\"6 372 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-21 18:28:46', NULL, NULL),
(168, '46352600-430e-48b2-8e82-0936ac49977d', 13, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R7-20260621182845-1 terminée pour 6 372 GHS.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R7-20260621182845-1 completed for 6 372 GHS.\",\"message_fr\":\"Vente R7-20260621182845-1 terminée pour 6 372 GHS.\",\"params\":{\"reference\":\"R7-20260621182845-1\",\"amount\":\"6 372 GHS\"}}', NULL, NULL, NULL, 7, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-21 18:28:46', NULL, NULL),
(169, 'ba81482e-25e4-4888-b390-baf64516e8e6', 1, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R1-20260628205229-1 terminée pour 1 317 368 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260628205229-1 completed for 1 317 368 FCFA.\",\"message_fr\":\"Vente R1-20260628205229-1 terminée pour 1 317 368 FCFA.\",\"params\":{\"reference\":\"R1-20260628205229-1\",\"amount\":\"1 317 368 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 1, '2026-06-28 21:27:52', 0, NULL, 0, NULL, NULL, '2026-06-28 20:52:31', '2026-06-28 21:27:52', NULL),
(170, 'e394984e-e351-4f22-a808-34c7e80e8503', 2, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R1-20260628205229-1 terminée pour 1 317 368 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260628205229-1 completed for 1 317 368 FCFA.\",\"message_fr\":\"Vente R1-20260628205229-1 terminée pour 1 317 368 FCFA.\",\"params\":{\"reference\":\"R1-20260628205229-1\",\"amount\":\"1 317 368 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-28 20:52:31', NULL, NULL),
(171, 'b2a1011f-57ad-4a93-95fa-bb6317f469ba', 3, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R1-20260628205229-1 terminée pour 1 317 368 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260628205229-1 completed for 1 317 368 FCFA.\",\"message_fr\":\"Vente R1-20260628205229-1 terminée pour 1 317 368 FCFA.\",\"params\":{\"reference\":\"R1-20260628205229-1\",\"amount\":\"1 317 368 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-28 20:52:31', NULL, NULL),
(172, 'ced9b95e-62ef-4f8d-a0d7-5fa166cae5fe', 6, 'pos.sale_completed', 'success', 'pos_sale', 'pos', 'low', 'success', 'Vente terminée', 'Vente R1-20260628205229-1 terminée pour 1 317 368 FCFA.', '{\"title_en\":\"Sale Completed\",\"title_fr\":\"Vente terminée\",\"message_en\":\"Sale R1-20260628205229-1 completed for 1 317 368 FCFA.\",\"message_fr\":\"Vente R1-20260628205229-1 terminée pour 1 317 368 FCFA.\",\"params\":{\"reference\":\"R1-20260628205229-1\",\"amount\":\"1 317 368 FCFA\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-28 20:52:31', NULL, NULL),
(173, 'ae98f4d9-5d0f-465e-80a9-99dfc659e055', 1, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Vente importante', 'Vente importante de 1 317 368 FCFA enregistrée.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 1 317 368 FCFA recorded.\",\"message_fr\":\"Vente importante de 1 317 368 FCFA enregistrée.\",\"params\":{\"amount\":\"1 317 368 FCFA\",\"reference\":\"R1-20260628205229-1\"}}', NULL, NULL, NULL, 1, NULL, NULL, 1, '2026-06-28 21:27:52', 0, NULL, 0, NULL, NULL, '2026-06-28 20:52:31', '2026-06-28 21:27:52', NULL),
(174, 'bb0ae64d-540e-421e-b10f-099e7ece4888', 2, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Vente importante', 'Vente importante de 1 317 368 FCFA enregistrée.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 1 317 368 FCFA recorded.\",\"message_fr\":\"Vente importante de 1 317 368 FCFA enregistrée.\",\"params\":{\"amount\":\"1 317 368 FCFA\",\"reference\":\"R1-20260628205229-1\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-28 20:52:31', NULL, NULL),
(175, '7547e437-de91-4c30-aa90-2e2608e661a8', 3, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Vente importante', 'Vente importante de 1 317 368 FCFA enregistrée.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 1 317 368 FCFA recorded.\",\"message_fr\":\"Vente importante de 1 317 368 FCFA enregistrée.\",\"params\":{\"amount\":\"1 317 368 FCFA\",\"reference\":\"R1-20260628205229-1\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-28 20:52:31', NULL, NULL),
(176, 'dff843f9-c2c4-4cc4-946a-21c0657b5898', 6, 'pos.large_sale', 'info', 'pos_sale', 'pos', 'normal', 'info', 'Vente importante', 'Vente importante de 1 317 368 FCFA enregistrée.', '{\"title_en\":\"Large Sale\",\"title_fr\":\"Vente importante\",\"message_en\":\"Large sale of 1 317 368 FCFA recorded.\",\"message_fr\":\"Vente importante de 1 317 368 FCFA enregistrée.\",\"params\":{\"amount\":\"1 317 368 FCFA\",\"reference\":\"R1-20260628205229-1\"}}', NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '2026-06-28 20:52:31', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications_legacy`
--

CREATE TABLE `notifications_legacy` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `language` varchar(8) DEFAULT NULL,
  `type` varchar(64) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_categories`
--

CREATE TABLE `notification_categories` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `slug` varchar(50) NOT NULL,
  `module` varchar(40) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `name_fr` varchar(100) NOT NULL,
  `icon` varchar(40) DEFAULT 'folder',
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_categories`
--

INSERT INTO `notification_categories` (`id`, `slug`, `module`, `name_en`, `name_fr`, `icon`, `sort_order`) VALUES
(1, 'inventory_low_stock', 'inventory', 'Low Stock', 'Stock faible', 'inventory_2', 10),
(2, 'inventory_out_of_stock', 'inventory', 'Out of Stock', 'Rupture de stock', 'remove_shopping_cart', 11),
(3, 'inventory_adjustment', 'inventory', 'Stock Adjustment', 'Ajustement de stock', 'tune', 12),
(4, 'inventory_expired', 'inventory', 'Expired Products', 'Produits expirés', 'event_busy', 13),
(5, 'warehouse_transfer', 'warehouse', 'Transfer', 'Transfert', 'swap_horiz', 20),
(6, 'warehouse_receiving', 'warehouse', 'Receiving', 'Réception', 'local_shipping', 21),
(7, 'warehouse_dispatch', 'warehouse', 'Dispatch', 'Expédition', 'outbound', 22),
(8, 'pos_sale', 'pos', 'Sales', 'Ventes', 'point_of_sale', 30),
(9, 'pos_refund', 'pos', 'Refunds', 'Remboursements', 'undo', 31),
(10, 'accounting_expense', 'accounting', 'Expenses', 'Dépenses', 'receipt_long', 40),
(11, 'accounting_invoice', 'accounting', 'Invoices', 'Factures', 'description', 41),
(12, 'cash_register', 'cash_register', 'Cash Register', 'Caisse', 'payments', 50),
(13, 'user_management', 'users', 'User Management', 'Gestion utilisateurs', 'people', 60),
(14, 'system', 'system', 'System', 'Système', 'settings', 70),
(15, 'purchase', 'purchasing', 'Purchase Orders', 'Bons de commande', 'shopping_bag', 80),
(16, 'security', 'system', 'Security', 'Sécurité', 'security', 90);

-- --------------------------------------------------------

--
-- Table structure for table `notification_channels`
--

CREATE TABLE `notification_channels` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `slug` varchar(30) NOT NULL,
  `name_en` varchar(60) NOT NULL,
  `name_fr` varchar(60) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_channels`
--

INSERT INTO `notification_channels` (`id`, `slug`, `name_en`, `name_fr`, `is_active`) VALUES
(1, 'in_app', 'In-App', 'Dans l\'application', 1),
(2, 'browser', 'Browser', 'Navigateur', 1),
(3, 'push', 'Push (PWA)', 'Push (PWA)', 1),
(4, 'email', 'Email', 'Courriel', 1),
(5, 'sms', 'SMS', 'SMS', 1),
(6, 'whatsapp', 'WhatsApp', 'WhatsApp', 1),
(7, 'webhook', 'Webhook', 'Webhook', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` bigint(20) NOT NULL,
  `notification_id` bigint(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `channel_slug` varchar(30) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'success',
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_logs`
--

INSERT INTO `notification_logs` (`id`, `notification_id`, `user_id`, `channel_slug`, `action`, `status`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-17 02:38:45'),
(2, NULL, 1, NULL, 'preferences_updated', 'success', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-17 02:39:08'),
(3, NULL, 1, NULL, 'preferences_updated', 'success', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-17 02:39:30'),
(4, 1, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:15:54'),
(5, 2, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:15:54'),
(6, 3, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:15:54'),
(7, 4, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:15:54'),
(8, 5, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:15:54'),
(9, 6, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:15:54'),
(10, 7, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:15:54'),
(11, 8, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:15:54'),
(12, 9, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:16:38'),
(13, 10, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:16:38'),
(14, 11, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:16:38'),
(15, 12, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:16:38'),
(16, 13, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:16:38'),
(17, 14, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:16:38'),
(18, 15, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:16:38'),
(19, 16, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:16:38'),
(20, 17, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:17:17'),
(21, 18, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:17:17'),
(22, 19, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:17:17'),
(23, 20, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:17:17'),
(24, 21, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:17:17'),
(25, 22, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:17:17'),
(26, 23, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:17:17'),
(27, 24, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:17:17'),
(28, 13, 1, 'in_app', 'read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 04:23:41'),
(29, 9, 1, 'in_app', 'read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 04:24:55'),
(30, 17, 1, 'in_app', 'read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 04:25:36'),
(31, 21, 1, 'in_app', 'read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 04:25:38'),
(32, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 04:26:08'),
(33, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 04:26:09'),
(34, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 04:26:15'),
(35, 25, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:27:51'),
(36, 26, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:27:52'),
(37, 27, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:27:52'),
(38, 28, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:27:52'),
(39, 29, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:27:52'),
(40, 30, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:27:52'),
(41, 31, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:27:52'),
(42, 32, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:27:52'),
(43, 33, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:28:24'),
(44, 34, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:28:24'),
(45, 35, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:28:24'),
(46, 36, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:28:24'),
(47, 37, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:29:29'),
(48, 38, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:29:29'),
(49, 39, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:29:29'),
(50, 40, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:29:29'),
(51, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 04:39:47'),
(52, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 04:39:50'),
(53, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 04:39:51'),
(54, 21, 1, 'in_app', 'read', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 04:48:40'),
(55, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 06:46:23'),
(56, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 06:46:26'),
(57, 41, 1, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 09:02:01'),
(58, 42, 1, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 09:03:58'),
(59, 43, 1, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 09:08:08'),
(60, 44, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 09:37:17'),
(61, 45, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 09:46:34'),
(62, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 09:54:13'),
(63, 46, 1, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 09:56:20'),
(64, NULL, 1, NULL, 'preferences_updated', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 10:26:31'),
(65, 47, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 10:28:39'),
(66, 48, 1, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 10:51:21'),
(67, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-17 10:56:11'),
(68, 49, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-17 18:59:57'),
(69, 50, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-18 02:29:09'),
(70, 51, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-18 02:48:26'),
(71, 52, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-18 02:49:39'),
(72, 53, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-18 02:57:13'),
(73, 54, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-18 03:31:41'),
(74, 55, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-18 03:32:39'),
(75, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-18 03:57:08'),
(76, 56, 1, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-18 03:57:56'),
(77, 57, 1, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-18 03:59:01'),
(78, 58, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-18 04:44:20'),
(79, 59, 1, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-18 04:50:19'),
(80, 60, 1, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-18 04:54:51'),
(81, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-18 20:46:25'),
(82, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-18 20:50:13'),
(83, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-18 20:50:17'),
(84, 61, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:39'),
(85, 62, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:40'),
(86, 63, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:40'),
(87, 64, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:40'),
(88, 65, 8, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:40'),
(89, 66, 10, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:40'),
(90, 67, 11, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:40'),
(91, 68, 13, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:40'),
(92, 69, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:53'),
(93, 70, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:53'),
(94, 71, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:53'),
(95, 72, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:53'),
(96, 73, 8, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:53'),
(97, 74, 10, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:53'),
(98, 75, 11, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:53'),
(99, 76, 13, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:23:53'),
(100, 77, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:30:39'),
(101, 78, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:30:39'),
(102, 79, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:30:39'),
(103, 80, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:30:39'),
(104, 81, 8, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:30:39'),
(105, 82, 10, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:30:39'),
(106, 83, 11, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:30:39'),
(107, 84, 13, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:30:39'),
(108, 85, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:31:00'),
(109, 86, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:31:00'),
(110, 87, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:31:00'),
(111, 88, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:31:00'),
(112, 89, 8, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:31:00'),
(113, 90, 10, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:31:00'),
(114, 91, 11, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:31:00'),
(115, 92, 13, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 10:31:00'),
(116, 93, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:02:53'),
(117, 94, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:02:53'),
(118, 95, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:02:53'),
(119, 96, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:02:53'),
(120, 97, 8, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:02:53'),
(121, 98, 10, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:02:53'),
(122, 99, 11, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:02:53'),
(123, 100, 13, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:02:53'),
(124, 101, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:03:17'),
(125, 102, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:03:17'),
(126, 103, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:03:17'),
(127, 104, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:03:17'),
(128, 105, 8, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:03:17'),
(129, 106, 10, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:03:17'),
(130, 107, 11, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:03:17'),
(131, 108, 13, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:03:17'),
(132, 109, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:39'),
(133, 110, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:39'),
(134, 111, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:39'),
(135, 112, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:39'),
(136, 113, 8, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:39'),
(137, 114, 10, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:39'),
(138, 115, 11, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:39'),
(139, 116, 13, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:39'),
(140, 117, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:46'),
(141, 118, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:46'),
(142, 119, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:46'),
(143, 120, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:46'),
(144, 121, 8, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:46'),
(145, 122, 10, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:46'),
(146, 123, 11, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:46'),
(147, 124, 13, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:08:46'),
(148, 125, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(149, 126, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(150, 127, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(151, 128, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(152, 129, 8, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(153, 130, 10, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(154, 131, 11, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(155, 132, 13, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(156, 133, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(157, 134, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(158, 135, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(159, 136, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(160, 137, 8, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(161, 138, 10, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(162, 139, 11, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(163, 140, 13, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:12:19'),
(164, 141, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:19:37'),
(165, 142, 2, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:19:37'),
(166, 143, 3, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:19:37'),
(167, 144, 6, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:19:37'),
(168, 145, 8, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:19:37'),
(169, 146, 10, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:19:37'),
(170, 147, 11, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:19:37'),
(171, 148, 13, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 11:19:37'),
(172, 149, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 23:11:05'),
(173, 150, 10, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 23:11:05'),
(174, 151, 11, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 23:11:05'),
(175, 152, 13, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 23:11:05'),
(176, 153, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 23:12:26'),
(177, 154, 10, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 23:12:26'),
(178, 155, 11, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 23:12:26'),
(179, 156, 13, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 23:12:26'),
(180, 157, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 23:13:14'),
(181, 158, 10, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 23:13:14'),
(182, 159, 11, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 23:13:14'),
(183, 160, 13, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-20 23:13:14'),
(184, 157, 1, 'in_app', 'read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-20 23:14:31'),
(185, 149, 1, 'in_app', 'read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-20 23:14:52'),
(186, 153, 1, 'in_app', 'read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-20 23:16:45'),
(187, 161, 1, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-21 00:07:53'),
(188, 162, 10, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-21 00:07:53'),
(189, 163, 11, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-21 00:07:53'),
(190, 164, 13, 'in_app', 'created', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-21 00:07:53'),
(191, 161, 1, 'in_app', 'read', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-21 03:16:56'),
(192, 141, 1, 'in_app', 'read', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-21 03:31:47'),
(193, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-21 03:32:04'),
(194, 165, 1, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-21 18:28:46'),
(195, 166, 10, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-21 18:28:46'),
(196, 167, 11, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-21 18:28:46'),
(197, 168, 13, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-21 18:28:46'),
(198, 169, 1, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-28 20:52:31'),
(199, 170, 2, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-28 20:52:31'),
(200, 171, 3, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-28 20:52:31'),
(201, 172, 6, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-28 20:52:31'),
(202, 173, 1, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-28 20:52:31'),
(203, 174, 2, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-28 20:52:31'),
(204, 175, 3, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-28 20:52:31'),
(205, 176, 6, 'in_app', 'created', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-28 20:52:31'),
(206, NULL, 1, 'in_app', 'mark_all_read', 'success', NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', '2026-06-28 21:27:52');

-- --------------------------------------------------------

--
-- Table structure for table `notification_preferences`
--

CREATE TABLE `notification_preferences` (
  `user_id` int(11) NOT NULL,
  `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sms_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `push_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `whatsapp_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `whatsapp_phone` varchar(20) DEFAULT NULL,
  `browser_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sound_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `quiet_hours_start` time DEFAULT NULL,
  `quiet_hours_end` time DEFAULT NULL,
  `min_priority` enum('low','normal','high','critical') NOT NULL DEFAULT 'low',
  `language` varchar(5) NOT NULL DEFAULT 'en',
  `category_filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`category_filters`)),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_preferences`
--

INSERT INTO `notification_preferences` (`user_id`, `email_enabled`, `sms_enabled`, `push_enabled`, `whatsapp_enabled`, `whatsapp_phone`, `browser_enabled`, `sound_enabled`, `quiet_hours_start`, `quiet_hours_end`, `min_priority`, `language`, `category_filters`, `updated_at`) VALUES
(1, 1, 1, 1, 1, '+233599944826', 1, 1, '00:00:00', '00:00:00', 'normal', 'en', NULL, '2026-06-17 10:26:31');

-- --------------------------------------------------------

--
-- Table structure for table `notification_queue`
--

CREATE TABLE `notification_queue` (
  `id` bigint(20) NOT NULL,
  `notification_id` bigint(20) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `channel_slug` varchar(30) NOT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `status` enum('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 3,
  `error_message` text DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_queue`
--

INSERT INTO `notification_queue` (`id`, `notification_id`, `user_id`, `channel_slug`, `recipient`, `subject`, `body`, `payload`, `status`, `attempts`, `max_attempts`, `error_message`, `scheduled_at`, `sent_at`, `created_at`) VALUES
(1, 5, 1, 'browser', NULL, 'Large Sale', 'Large sale of 2 120 000 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:15:54'),
(2, 6, 2, 'browser', NULL, 'Large Sale', 'Large sale of 2 120 000 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:15:54'),
(3, 7, 3, 'browser', NULL, 'Large Sale', 'Large sale of 2 120 000 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:15:54'),
(4, 8, 6, 'browser', NULL, 'Large Sale', 'Large sale of 2 120 000 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:15:54'),
(5, 13, 1, 'browser', NULL, 'Large Sale', 'Large sale of 731 400 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:16:38'),
(6, 14, 2, 'browser', NULL, 'Large Sale', 'Large sale of 731 400 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:16:38'),
(7, 15, 3, 'browser', NULL, 'Large Sale', 'Large sale of 731 400 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:16:38'),
(8, 16, 6, 'browser', NULL, 'Large Sale', 'Large sale of 731 400 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:16:38'),
(9, 21, 1, 'browser', NULL, 'Large Sale', 'Large sale of 16 960 000 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:17:17'),
(10, 22, 2, 'browser', NULL, 'Large Sale', 'Large sale of 16 960 000 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:17:17'),
(11, 23, 3, 'browser', NULL, 'Large Sale', 'Large sale of 16 960 000 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:17:17'),
(12, 24, 6, 'browser', NULL, 'Large Sale', 'Large sale of 16 960 000 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:17:17'),
(13, 29, 1, 'browser', NULL, 'Large Sale', 'Large sale of 1 272 000 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:27:52'),
(14, 30, 2, 'browser', NULL, 'Large Sale', 'Large sale of 1 272 000 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:27:52'),
(15, 31, 3, 'browser', NULL, 'Large Sale', 'Large sale of 1 272 000 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:27:52'),
(16, 32, 6, 'browser', NULL, 'Large Sale', 'Large sale of 1 272 000 FCFA recorded.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-17 04:27:52'),
(17, 51, 1, 'browser', NULL, 'Alerte stock faible', 'Le stock du produit Full chicken est faible (5 restants).', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-18 02:48:26'),
(18, 58, 1, 'browser', NULL, 'Caisse ouverte', 'Caisse C1 ouverte avec 25,000 GHS.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-18 04:44:20'),
(19, 61, 1, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#1 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:39'),
(20, 62, 2, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#1 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:40'),
(21, 63, 3, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#1 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:40'),
(22, 64, 6, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#1 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:40'),
(23, 65, 8, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#1 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:40'),
(24, 66, 10, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#1 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:40'),
(25, 67, 11, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#1 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:40'),
(26, 68, 13, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#1 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:40'),
(27, 69, 1, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:53'),
(28, 70, 2, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:53'),
(29, 71, 3, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:53'),
(30, 72, 6, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:53'),
(31, 73, 8, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:53'),
(32, 74, 10, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:53'),
(33, 75, 11, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:53'),
(34, 76, 13, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3B49A8 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:23:53'),
(35, 77, 1, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#2 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:30:39'),
(36, 78, 2, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#2 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:30:39'),
(37, 79, 3, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#2 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:30:39'),
(38, 80, 6, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#2 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:30:39'),
(39, 81, 8, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#2 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:30:39'),
(40, 82, 10, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#2 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:30:39'),
(41, 83, 11, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#2 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:30:39'),
(42, 84, 13, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#2 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:30:39'),
(43, 85, 1, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:31:00'),
(44, 86, 2, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:31:00'),
(45, 87, 3, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:31:00'),
(46, 88, 6, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:31:00'),
(47, 89, 8, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:31:00'),
(48, 90, 10, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:31:00'),
(49, 91, 11, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:31:00'),
(50, 92, 13, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-3C369D completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 10:31:00'),
(51, 93, 1, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#3 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:02:53'),
(52, 94, 2, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#3 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:02:53'),
(53, 95, 3, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#3 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:02:53'),
(54, 96, 6, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#3 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:02:53'),
(55, 97, 8, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#3 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:02:53'),
(56, 98, 10, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#3 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:02:53'),
(57, 99, 11, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#3 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:02:53'),
(58, 100, 13, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#3 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:02:53'),
(59, 101, 1, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:03:17'),
(60, 102, 2, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:03:17'),
(61, 103, 3, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:03:17'),
(62, 104, 6, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:03:17'),
(63, 105, 8, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:03:17'),
(64, 106, 10, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:03:17'),
(65, 107, 11, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:03:17'),
(66, 108, 13, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-26921E completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:03:17'),
(67, 109, 1, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#4 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:39'),
(68, 110, 2, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#4 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:39'),
(69, 111, 3, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#4 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:39'),
(70, 112, 6, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#4 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:39'),
(71, 113, 8, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#4 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:39'),
(72, 114, 10, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#4 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:39'),
(73, 115, 11, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#4 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:39'),
(74, 116, 13, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#4 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:39'),
(75, 117, 1, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:46'),
(76, 118, 2, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:46'),
(77, 119, 3, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:46'),
(78, 120, 6, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:46'),
(79, 121, 8, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:46'),
(80, 122, 10, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:46'),
(81, 123, 11, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:46'),
(82, 124, 13, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-32F128 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:08:46'),
(83, 125, 1, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#5 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(84, 126, 2, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#5 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(85, 127, 3, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#5 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(86, 128, 6, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#5 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(87, 129, 8, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#5 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(88, 130, 10, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#5 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(89, 131, 11, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#5 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(90, 132, 13, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#5 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(91, 133, 1, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(92, 134, 2, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(93, 135, 3, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(94, 136, 6, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(95, 137, 8, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(96, 138, 10, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(97, 139, 11, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(98, 140, 13, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN-20260620-790196 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:12:19'),
(99, 141, 1, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#6 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:19:37'),
(100, 142, 2, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#6 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:19:37'),
(101, 143, 3, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#6 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:19:37'),
(102, 144, 6, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#6 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:19:37'),
(103, 145, 8, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#6 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:19:37'),
(104, 146, 10, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#6 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:19:37'),
(105, 147, 11, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#6 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:19:37'),
(106, 148, 13, 'browser', NULL, 'Receiving Completed', 'Goods receipt GRN#6 completed.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-20 11:19:37'),
(107, 173, 1, 'browser', NULL, 'Vente importante', 'Vente importante de 1 317 368 FCFA enregistrée.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-28 20:52:31'),
(108, 174, 2, 'browser', NULL, 'Vente importante', 'Vente importante de 1 317 368 FCFA enregistrée.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-28 20:52:31'),
(109, 175, 3, 'browser', NULL, 'Vente importante', 'Vente importante de 1 317 368 FCFA enregistrée.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-28 20:52:31'),
(110, 176, 6, 'browser', NULL, 'Vente importante', 'Vente importante de 1 317 368 FCFA enregistrée.', NULL, 'pending', 0, 3, NULL, NULL, NULL, '2026-06-28 20:52:31');

-- --------------------------------------------------------

--
-- Table structure for table `notification_templates`
--

CREATE TABLE `notification_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(100) NOT NULL,
  `category_id` smallint(5) UNSIGNED DEFAULT NULL,
  `type_slug` varchar(40) NOT NULL DEFAULT 'info',
  `title_en` varchar(200) NOT NULL,
  `title_fr` varchar(200) NOT NULL,
  `body_en` text NOT NULL,
  `body_fr` text NOT NULL,
  `default_priority` enum('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  `default_channels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`default_channels`)),
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_templates`
--

INSERT INTO `notification_templates` (`id`, `slug`, `category_id`, `type_slug`, `title_en`, `title_fr`, `body_en`, `body_fr`, `default_priority`, `default_channels`, `variables`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'inventory.low_stock', 1, 'warning', 'Low Stock Alert', 'Alerte stock faible', 'Low stock detected for {product} ({qty} remaining).', 'Le stock du produit {product} est faible ({qty} restants).', 'high', '[\"in_app\",\"browser\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(2, 'inventory.out_of_stock', 2, 'critical', 'Out of Stock', 'Rupture de stock', '{product} is out of stock.', '{product} est en rupture de stock.', 'critical', '[\"in_app\",\"email\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(3, 'warehouse.transfer_request', 5, 'approval', 'Transfer Request', 'Demande de transfert', 'Transfer {reference} requires approval.', 'Le transfert {reference} nécessite une approbation.', 'high', '[\"in_app\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(4, 'warehouse.transfer_approved', 5, 'success', 'Transfer Approved', 'Transfert approuvé', 'Transfer {reference} has been approved.', 'Le transfert {reference} a été approuvé.', 'normal', '[\"in_app\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(5, 'warehouse.transfer_rejected', 5, 'warning', 'Transfer Rejected', 'Transfert rejeté', 'Transfer {reference} was rejected.', 'Le transfert {reference} a été rejeté.', 'high', '[\"in_app\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(6, 'warehouse.receiving_completed', 6, 'success', 'Receiving Completed', 'Réception terminée', 'Goods receipt {reference} completed.', 'Réception {reference} terminée.', 'normal', '[\"in_app\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(7, 'warehouse.dispatch_completed', 7, 'success', 'Dispatch Completed', 'Expédition terminée', 'Dispatch {reference} completed.', 'Expédition {reference} terminée.', 'normal', '[\"in_app\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(8, 'pos.sale_completed', 8, 'success', 'Sale Completed', 'Vente terminée', 'Sale {reference} completed for {amount}.', 'Vente {reference} terminée pour {amount}.', 'low', '[\"in_app\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(9, 'pos.large_sale', 8, 'info', 'Large Sale', 'Vente importante', 'Large sale of {amount} recorded.', 'Vente importante de {amount} enregistrée.', 'normal', '[\"in_app\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(10, 'pos.refund_completed', 9, 'warning', 'Refund Completed', 'Remboursement effectué', 'Refund of {amount} processed.', 'Remboursement de {amount} traité.', 'normal', '[\"in_app\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(11, 'accounting.expense_added', 10, 'info', 'Expense Added', 'Dépense ajoutée', 'New expense of {amount} added.', 'Nouvelle dépense de {amount} ajoutée.', 'normal', '[\"in_app\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(12, 'accounting.invoice_overdue', 11, 'warning', 'Invoice Overdue', 'Facture en retard', 'Invoice {reference} is overdue.', 'La facture {reference} est en retard.', 'high', '[\"in_app\",\"email\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(13, 'cash_register.opened', 12, 'info', 'Register Opened', 'Caisse ouverte', 'Register {register} opened with {amount}.', 'Caisse {register} ouverte avec {amount}.', 'normal', '[\"in_app\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(14, 'cash_register.closed', 12, 'info', 'Register Closed', 'Caisse fermée', 'Register {register} closed (variance: {variance}).', 'Caisse {register} fermée (écart: {variance}).', 'normal', '[\"in_app\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(15, 'cash_register.cash_difference', 12, 'warning', 'Cash Difference', 'Écart de caisse', 'Cash difference of {variance} detected.', 'Écart de caisse de {variance} détecté.', 'high', '[\"in_app\",\"browser\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(16, 'cash_register.reconciliation', 12, 'approval', 'Reconciliation Required', 'Rapprochement requis', 'Cash reconciliation required for {register}.', 'Rapprochement de caisse requis pour {register}.', 'high', '[\"in_app\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(17, 'users.role_changed', 13, 'info', 'Role Changed', 'Rôle modifié', 'Your role was changed to {role}.', 'Votre rôle a été modifié en {role}.', 'high', '[\"in_app\",\"email\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(18, 'users.login_failed', 16, 'warning', 'Failed Login', 'Échec de connexion', 'Failed login attempt on your account.', 'Tentative de connexion échouée sur votre compte.', 'high', '[\"in_app\",\"email\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(19, 'users.account_locked', 16, 'critical', 'Account Locked', 'Compte verrouillé', 'Your account has been locked.', 'Votre compte a été verrouillé.', 'critical', '[\"in_app\",\"email\",\"sms\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(20, 'system.backup_completed', 14, 'success', 'Backup Completed', 'Sauvegarde terminée', 'System backup completed successfully.', 'Sauvegarde système terminée avec succès.', 'low', '[\"in_app\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(21, 'system.backup_failed', 14, 'critical', 'Backup Failed', 'Échec de sauvegarde', 'System backup failed.', 'Échec de la sauvegarde système.', 'critical', '[\"in_app\",\"email\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(22, 'system.offline_sync', 14, 'info', 'Offline Sync', 'Synchronisation hors ligne', 'Offline data synchronized.', 'Données hors ligne synchronisées.', 'low', '[\"in_app\"]', NULL, 1, '2026-06-17 02:21:58', NULL),
(23, 'system.security_alert', 16, 'critical', 'Security Alert', 'Alerte de sécurité', '{message}', '{message}', 'critical', '[\"in_app\",\"email\"]', NULL, 1, '2026-06-17 02:21:58', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notification_types`
--

CREATE TABLE `notification_types` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `slug` varchar(40) NOT NULL,
  `name_en` varchar(80) NOT NULL,
  `name_fr` varchar(80) NOT NULL,
  `icon` varchar(40) DEFAULT 'notifications',
  `sort_order` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_types`
--

INSERT INTO `notification_types` (`id`, `slug`, `name_en`, `name_fr`, `icon`, `sort_order`) VALUES
(1, 'info', 'Information', 'Information', 'info', 1),
(2, 'success', 'Success', 'Succès', 'check_circle', 2),
(3, 'warning', 'Warning', 'Avertissement', 'warning_amber', 3),
(4, 'error', 'Error', 'Erreur', 'error_outline', 4),
(5, 'critical', 'Critical', 'Critique', 'report', 5),
(6, 'reminder', 'Reminder', 'Rappel', 'schedule', 6),
(7, 'approval', 'Approval Required', 'Approbation requise', 'pending_actions', 7),
(8, 'announcement', 'Announcement', 'Annonce', 'campaign', 8),
(9, 'system', 'System Alert', 'Alerte système', 'settings', 9);

-- --------------------------------------------------------

--
-- Table structure for table `offline_transactions`
--

CREATE TABLE `offline_transactions` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `local_uuid` varchar(100) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `status` enum('pending','synced','conflict','failed') NOT NULL DEFAULT 'pending',
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `error_message` text DEFAULT NULL,
  `conflict_reason` varchar(255) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `created_at`) VALUES
(1, 9, 'ee9306ab10995fcf8bcd83903dc0d9bc3ad1ba4fe44d6b4d032586ce643d1c27', '2026-06-15 19:56:22', '2026-06-15 16:56:22');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `method` enum('cash','mobile_money','card') DEFAULT 'cash',
  `provider` varchar(50) DEFAULT NULL,
  `transaction_ref` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','success','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `sale_id`, `method`, `provider`, `transaction_ref`, `amount`, `status`, `created_at`) VALUES
(1, 1, 'cash', NULL, NULL, 41300.00, 'success', '2026-05-10 05:31:11'),
(2, 2, 'cash', NULL, NULL, 277300.00, 'success', '2026-05-10 05:32:49'),
(3, 3, 'cash', NULL, NULL, 29500.00, 'success', '2026-05-10 05:45:04'),
(4, 4, 'cash', NULL, NULL, 23600.00, 'success', '2026-05-10 05:45:18'),
(5, 5, 'cash', NULL, NULL, 5900.00, 'success', '2026-05-10 05:45:37'),
(6, 6, 'mobile_money', NULL, NULL, 41300.00, 'success', '2026-05-10 05:57:51'),
(7, 7, 'cash', NULL, NULL, 17700.00, 'success', '2026-05-10 08:20:50'),
(8, 8, 'cash', NULL, NULL, 7080.00, 'success', '2026-05-10 23:24:08'),
(9, 9, 'cash', NULL, NULL, 2360000.00, 'success', '2026-05-13 13:21:38'),
(10, 10, 'cash', NULL, NULL, 17700.00, 'success', '2026-05-13 13:46:55'),
(11, 11, 'cash', NULL, NULL, 2146420.00, 'success', '2026-05-13 13:49:50'),
(12, 12, 'cash', NULL, NULL, 84960.00, 'success', '2026-05-13 13:52:59'),
(13, 13, 'cash', NULL, NULL, 17700.00, 'success', '2026-05-17 15:38:38'),
(14, 14, 'cash', NULL, NULL, 1191800.00, 'success', '2026-05-17 16:29:02'),
(15, 15, 'cash', NULL, NULL, 372880.00, 'success', '2026-05-17 16:44:33'),
(16, 16, 'cash', NULL, NULL, 330400.00, 'success', '2026-05-17 17:02:27'),
(17, 17, 'cash', NULL, NULL, 1180000.00, 'success', '2026-05-17 17:53:49'),
(18, 18, 'cash', NULL, NULL, 118000.00, 'success', '2026-05-18 11:10:11'),
(19, 19, 'cash', NULL, NULL, 1416000.00, 'success', '2026-05-18 11:11:24'),
(20, 20, 'mobile_money', NULL, NULL, 11800.00, 'success', '2026-05-18 12:16:18'),
(21, 21, 'card', NULL, NULL, 11800.00, 'success', '2026-05-18 12:16:55'),
(22, 22, 'card', NULL, NULL, 11800.00, 'success', '2026-05-18 12:17:12'),
(23, 23, 'cash', NULL, NULL, 30916000.00, 'success', '2026-05-18 12:18:08'),
(24, 24, 'cash', NULL, NULL, 965240.00, 'success', '2026-05-18 12:43:42'),
(25, 25, 'cash', NULL, NULL, 330400.00, 'success', '2026-05-18 17:28:17'),
(26, 26, 'cash', NULL, NULL, 17700.00, 'success', '2026-05-22 19:48:38'),
(27, 27, 'cash', NULL, NULL, 1486800.00, 'success', '2026-05-22 19:49:05'),
(28, 28, 'cash', NULL, NULL, 2803680.00, 'success', '2026-05-22 19:51:56'),
(29, 29, 'cash', NULL, NULL, 171100.00, 'success', '2026-05-22 19:53:10'),
(30, 30, 'cash', NULL, NULL, 672600.00, 'success', '2026-05-22 20:24:02'),
(31, 31, 'cash', NULL, NULL, 356360.00, 'success', '2026-05-22 20:25:48'),
(32, 32, 'cash', NULL, NULL, 182900.00, 'success', '2026-05-22 20:37:04'),
(33, 33, 'cash', NULL, NULL, 165200.00, 'success', '2026-05-22 20:38:23'),
(34, 34, 'cash', NULL, NULL, 165200.00, 'success', '2026-05-22 20:57:29'),
(35, 35, 'cash', NULL, NULL, 3304000.00, 'success', '2026-05-22 21:12:20'),
(36, 36, 'cash', NULL, NULL, 5900.00, 'success', '2026-05-22 21:15:19'),
(37, 37, 'mobile_money', 'wave', NULL, 17700.00, 'success', '2026-05-22 21:31:37'),
(38, 38, 'cash', NULL, NULL, 9440.00, 'success', '2026-05-22 21:51:34'),
(39, 39, 'cash', NULL, NULL, 23600.00, 'success', '2026-05-22 21:59:23'),
(40, 40, 'cash', NULL, NULL, 477900.00, 'success', '2026-05-22 22:50:03'),
(41, 41, 'cash', NULL, NULL, 240720.00, 'success', '2026-05-23 00:10:27'),
(42, 42, 'cash', NULL, NULL, 4720.00, 'success', '2026-05-23 00:11:29'),
(43, 43, 'cash', NULL, NULL, 4489900.00, 'success', '2026-05-23 00:51:36'),
(44, 44, 'cash', NULL, NULL, 572400.00, 'success', '2026-05-23 03:00:10'),
(45, 45, 'mobile_money', 'wave', NULL, 167480.00, 'success', '2026-05-23 05:12:38'),
(46, 46, 'cash', NULL, NULL, 167480.00, 'success', '2026-05-23 05:13:00'),
(47, 47, 'cash', NULL, NULL, 167480.00, 'success', '2026-05-23 05:13:39'),
(48, 48, 'cash', NULL, NULL, 10600.00, 'success', '2026-05-23 05:20:00'),
(49, 49, 'cash', NULL, NULL, 5088000.00, 'success', '2026-05-23 05:42:40'),
(50, 50, 'cash', NULL, NULL, 267120.00, 'success', '2026-05-23 15:43:38'),
(51, 51, 'cash', NULL, NULL, 63600.00, 'success', '2026-05-23 15:43:38'),
(52, 52, 'cash', NULL, NULL, 3816000.00, 'success', '2026-05-23 15:43:38'),
(53, 53, 'cash', NULL, NULL, 5300000.00, 'success', '2026-05-23 15:43:38'),
(54, 54, 'cash', NULL, NULL, 1060000.00, 'success', '2026-05-23 15:43:38'),
(55, 55, 'cash', NULL, NULL, 2438000.00, 'success', '2026-05-23 20:19:44'),
(56, 56, 'cash', NULL, NULL, 5300.00, 'success', '2026-05-23 20:22:24'),
(57, 57, 'cash', NULL, NULL, 1156400.00, 'success', '2026-05-24 00:30:34'),
(58, 58, 'cash', NULL, NULL, 23600.00, 'success', '2026-05-24 00:30:34'),
(59, 59, 'cash', NULL, NULL, 4240.00, 'success', '2026-05-24 00:30:34'),
(60, 60, 'cash', NULL, NULL, 150520.00, 'success', '2026-05-24 00:30:34'),
(61, 61, 'cash', NULL, NULL, 854360.00, 'success', '2026-05-24 00:30:34'),
(62, 62, 'mobile_money', 'wave', NULL, 831040.00, 'success', '2026-05-24 00:30:34'),
(63, 63, 'cash', NULL, NULL, 38160.00, 'success', '2026-05-24 01:09:55'),
(64, 64, 'cash', NULL, NULL, 977040.00, 'success', '2026-05-24 03:15:01'),
(65, 65, 'cash', NULL, NULL, 148680.00, 'success', '2026-05-24 03:15:05'),
(66, 66, 'cash', NULL, NULL, 68900.00, 'success', '2026-05-24 12:10:57'),
(67, 67, 'cash', NULL, NULL, 6360000.00, 'success', '2026-05-24 12:13:07'),
(68, 68, 'cash', NULL, NULL, 265000.00, 'success', '2026-05-24 12:13:27'),
(69, 69, 'cash', NULL, NULL, 47200.00, 'success', '2026-05-24 12:23:40'),
(70, 70, 'cash', NULL, NULL, 148400.00, 'success', '2026-05-24 13:04:05'),
(71, 71, 'cash', NULL, NULL, 296800.00, 'success', '2026-05-24 13:04:40'),
(72, 72, 'cash', NULL, NULL, 424000.00, 'success', '2026-05-24 13:05:33'),
(73, 73, 'cash', NULL, NULL, 6360.00, 'success', '2026-05-24 17:26:11'),
(74, 74, 'mobile_money', 'wave', NULL, 188800.00, 'success', '2026-05-24 17:31:52'),
(75, 75, 'mobile_money', 'wave', NULL, 767440.00, 'success', '2026-05-25 22:23:42'),
(76, 76, 'mobile_money', 'orange_money', NULL, 4240.00, 'success', '2026-05-25 22:25:33'),
(77, 77, 'card', 'card', NULL, 4240.00, 'success', '2026-05-25 22:25:59'),
(78, 78, 'cash', NULL, NULL, 148400.00, 'success', '2026-05-25 23:07:47'),
(79, 79, 'cash', NULL, NULL, 1484000.00, 'success', '2026-05-25 23:08:32'),
(80, 80, 'cash', NULL, NULL, 445200.00, 'success', '2026-05-26 01:03:44'),
(81, 81, 'cash', NULL, NULL, 1038800.00, 'success', '2026-05-26 01:14:30'),
(82, 82, 'cash', NULL, NULL, 445200.00, 'success', '2026-05-26 01:18:12'),
(83, 83, 'cash', NULL, NULL, 424000.00, 'success', '2026-05-26 01:18:42'),
(84, 84, 'cash', NULL, NULL, 742000.00, 'success', '2026-05-26 01:19:07'),
(85, 85, 'cash', NULL, NULL, 742000.00, 'success', '2026-05-26 01:19:44'),
(86, 86, 'cash', NULL, NULL, 1038800.00, 'success', '2026-05-26 01:20:05'),
(87, 87, 'cash', NULL, NULL, 424000.00, 'success', '2026-05-26 01:24:49'),
(88, 88, 'cash', NULL, NULL, 1272000.00, 'success', '2026-05-26 01:37:26'),
(89, 89, 'cash', NULL, NULL, 848000.00, 'success', '2026-05-26 01:37:46'),
(90, 90, 'cash', NULL, NULL, 29680.00, 'success', '2026-05-26 01:44:30'),
(91, 91, 'cash', NULL, NULL, 4240000.00, 'success', '2026-05-26 01:46:03'),
(92, 92, 'cash', NULL, NULL, 2120.00, 'success', '2026-05-26 02:17:45'),
(93, 93, 'cash', NULL, NULL, 428240.00, 'success', '2026-05-26 02:21:53'),
(94, 94, 'cash', NULL, NULL, 10600.00, 'success', '2026-05-26 20:42:53'),
(95, 95, 'cash', NULL, NULL, 4240.00, 'success', '2026-05-26 20:42:54'),
(96, 96, 'cash', NULL, NULL, 4240.00, 'success', '2026-05-26 20:43:09'),
(97, 97, 'cash', NULL, NULL, 6360.00, 'success', '2026-05-26 20:58:15'),
(98, 98, 'cash', NULL, NULL, 3816000.00, 'success', '2026-05-26 20:59:26'),
(99, 99, 'cash', NULL, NULL, 15900.00, 'success', '2026-05-26 21:00:01'),
(100, 100, 'cash', NULL, NULL, 26500.00, 'success', '2026-05-29 15:57:35'),
(101, 101, 'cash', NULL, NULL, 2120000.00, 'success', '2026-05-29 16:05:31'),
(102, 102, 'cash', NULL, NULL, 728220.00, 'success', '2026-06-02 02:25:21'),
(103, 103, 'cash', NULL, NULL, 917960.00, 'success', '2026-06-02 02:25:21'),
(104, 104, 'cash', NULL, NULL, 1280480.00, 'success', '2026-06-02 02:27:22'),
(105, 105, 'cash', NULL, NULL, 430360.00, 'success', '2026-06-03 02:07:12'),
(106, 106, 'cash', NULL, NULL, 2120.00, 'success', '2026-06-03 02:07:12'),
(107, 107, 'cash', NULL, NULL, 1288960.00, 'success', '2026-06-03 02:07:12'),
(108, 108, 'mobile_money', 'mtn_momo', NULL, 19080.00, 'success', '2026-06-03 02:09:01'),
(109, 109, 'mobile_money', 'wave', NULL, 574520.00, 'success', '2026-06-03 02:09:13'),
(110, 110, 'mobile_money', 'moov', NULL, 574520.00, 'success', '2026-06-03 02:09:26'),
(111, 111, 'card', 'card', NULL, 574520.00, 'success', '2026-06-03 02:09:50'),
(112, 112, 'cash', NULL, NULL, 9328000.00, 'success', '2026-06-03 02:11:24'),
(113, 113, 'cash', NULL, NULL, 848000.00, 'success', '2026-06-03 04:22:03'),
(114, 114, 'cash', NULL, NULL, 796060.00, 'success', '2026-06-03 04:57:52'),
(115, 115, 'cash', NULL, NULL, 153700.00, 'success', '2026-06-03 05:28:23'),
(116, 116, 'cash', NULL, NULL, 848000.00, 'success', '2026-06-03 05:44:41'),
(117, 117, 'cash', NULL, NULL, 593600.00, 'success', '2026-06-03 05:51:45'),
(118, 118, 'cash', NULL, NULL, 21200.00, 'success', '2026-06-03 05:56:39'),
(119, 119, 'cash', NULL, NULL, 593600.00, 'success', '2026-06-03 06:08:46'),
(120, 120, 'cash', NULL, NULL, 21200.00, 'success', '2026-06-03 06:30:40'),
(121, 121, 'cash', NULL, NULL, 2511140.00, 'success', '2026-06-03 07:23:48'),
(122, 122, 'cash', NULL, NULL, 491840.00, 'success', '2026-06-03 08:06:50'),
(123, 123, 'cash', NULL, NULL, 848000.00, 'success', '2026-06-03 08:07:14'),
(124, 124, 'cash', NULL, NULL, 593600.00, 'success', '2026-06-03 08:08:06'),
(125, 125, 'cash', NULL, NULL, 1590.00, 'success', '2026-06-03 15:37:01'),
(126, 126, 'cash', NULL, NULL, 807190.00, 'success', '2026-06-03 15:39:15'),
(127, 127, 'cash', NULL, NULL, 572400.00, 'success', '2026-06-04 15:22:26'),
(128, 128, 'cash', NULL, NULL, 12720.00, 'success', '2026-06-04 15:22:26'),
(129, 129, 'cash', NULL, NULL, 57240.00, 'success', '2026-06-04 15:22:26'),
(130, 130, 'cash', NULL, NULL, 212000.00, 'success', '2026-06-04 15:54:00'),
(131, 131, 'cash', NULL, NULL, 23600.00, 'success', '2026-06-04 16:04:33'),
(132, 132, 'cash', NULL, NULL, 23600.00, 'success', '2026-06-04 16:08:18'),
(133, 133, 'cash', NULL, NULL, 428000.00, 'success', '2026-06-04 16:20:34'),
(134, 134, 'cash', NULL, NULL, 591480.00, 'success', '2026-06-04 21:40:43'),
(135, 135, 'cash', NULL, NULL, 424000.00, 'success', '2026-06-04 21:47:44'),
(136, 136, 'cash', NULL, NULL, 148400.00, 'success', '2026-06-04 21:48:02'),
(137, 137, 'cash', NULL, NULL, 572400.00, 'success', '2026-06-04 21:49:03'),
(138, 138, 'cash', NULL, NULL, 848000.00, 'success', '2026-06-04 21:49:43'),
(139, 139, 'cash', NULL, NULL, 4240.00, 'success', '2026-06-04 21:52:11'),
(140, 140, 'cash', NULL, NULL, 212400.00, 'success', '2026-06-04 21:59:03'),
(141, 141, 'cash', NULL, NULL, 1685400.00, 'success', '2026-06-05 02:29:02'),
(142, 142, 'cash', NULL, NULL, 636000.00, 'success', '2026-06-05 02:30:39'),
(143, 143, 'cash', NULL, NULL, 23600.00, 'success', '2026-06-05 02:40:42'),
(144, 144, 'cash', NULL, NULL, 2035500.00, 'success', '2026-06-05 02:46:59'),
(145, 145, 'cash', NULL, NULL, 9540000.00, 'success', '2026-06-05 02:51:28'),
(146, 146, 'cash', NULL, NULL, 6921800.00, 'success', '2026-06-05 03:08:25'),
(147, 147, 'cash', NULL, NULL, 593600.00, 'success', '2026-06-06 16:49:49'),
(148, 148, 'cash', NULL, NULL, 619500.00, 'success', '2026-06-06 16:58:26'),
(149, 149, 'mobile_money', 'wave', NULL, 54000.00, 'success', '2026-06-06 17:07:51'),
(150, 150, 'cash', NULL, NULL, 341318.94, 'success', '2026-06-09 01:35:26'),
(151, 151, 'cash', NULL, NULL, 1590.00, 'success', '2026-06-09 01:35:26'),
(152, 152, 'cash', NULL, NULL, 10600.00, 'success', '2026-06-09 01:35:26'),
(153, 153, 'cash', NULL, NULL, 5300.00, 'success', '2026-06-09 01:35:27'),
(154, 154, 'mobile_money', 'mtn_momo', NULL, 248.00, 'success', '2026-06-09 02:48:34'),
(155, 155, 'mobile_money', 'mtn_momo', NULL, 4320.00, 'success', '2026-06-09 02:55:08'),
(156, 156, 'cash', NULL, NULL, 432.00, 'success', '2026-06-09 02:57:33'),
(157, 157, 'cash', NULL, NULL, 18144.00, 'success', '2026-06-09 03:15:57'),
(158, 158, 'cash', NULL, NULL, 1404.00, 'success', '2026-06-09 03:18:35'),
(159, 159, 'cash', NULL, NULL, 5724.00, 'success', '2026-06-09 03:19:43'),
(160, 160, 'mobile_money', 'orange_money', NULL, 1296.00, 'success', '2026-06-09 03:20:50'),
(161, 161, 'mobile_money', 'orange_money', NULL, 1404.00, 'success', '2026-06-09 03:21:44'),
(162, 162, 'card', 'card', NULL, 2160.00, 'success', '2026-06-09 03:21:57'),
(163, 163, 'card', 'card', NULL, 648.00, 'success', '2026-06-09 03:22:10'),
(164, 164, 'card', 'card', NULL, 648.00, 'success', '2026-06-09 03:22:22'),
(165, 165, 'cash', NULL, NULL, 2160.00, 'success', '2026-06-09 04:06:25'),
(166, 166, 'cash', NULL, NULL, 972.00, 'success', '2026-06-09 17:31:05'),
(167, 167, 'mobile_money', 'mtn_momo', NULL, 1000.00, 'success', '2026-06-09 18:52:30'),
(168, 168, 'mobile_money', 'orange_money', NULL, 2160.00, 'success', '2026-06-09 18:54:23'),
(169, 169, 'cash', NULL, NULL, 432.00, 'success', '2026-06-09 18:55:29'),
(170, 170, 'card', 'card', NULL, 432.00, 'success', '2026-06-09 18:59:14'),
(171, 171, 'cash', NULL, NULL, 410.40, 'success', '2026-06-09 19:39:12'),
(172, 172, 'cash', NULL, NULL, 12312.00, 'success', '2026-06-09 19:40:00'),
(173, 173, 'card', 'card', NULL, 1296.00, 'success', '2026-06-09 19:41:06'),
(174, 174, 'cash', NULL, NULL, 1026.00, 'success', '2026-06-09 20:58:10'),
(175, 175, 'mobile_money', 'mtn_momo', NULL, 324.00, 'success', '2026-06-09 20:58:11'),
(176, 176, 'card', 'card', NULL, 82080.00, 'success', '2026-06-09 20:58:11'),
(177, 177, 'mobile_money', 'mtn_momo', NULL, 324.00, 'success', '2026-06-09 21:02:05'),
(178, 178, 'mobile_money', 'wave', NULL, 1080.00, 'success', '2026-06-09 21:02:58'),
(179, 179, 'mobile_money', 'moov', NULL, 648.00, 'success', '2026-06-09 21:03:52'),
(180, 180, 'mobile_money', 'mtn_momo', NULL, 540.00, 'success', '2026-06-09 22:29:28'),
(181, 181, 'mobile_money', 'orange_money', NULL, 1080.00, 'success', '2026-06-09 23:17:56'),
(182, 182, 'mobile_money', 'mtn_momo', NULL, 324.00, 'success', '2026-06-09 23:31:05'),
(183, 183, 'cash', NULL, NULL, 1620.00, 'success', '2026-06-09 23:32:02'),
(184, 184, 'cash', NULL, NULL, 1296.00, 'success', '2026-06-09 23:32:55'),
(185, 185, 'cash', NULL, NULL, 1620.00, 'success', '2026-06-09 23:43:11'),
(186, 186, 'mobile_money', 'orange_money', NULL, 540.00, 'success', '2026-06-09 23:45:15'),
(187, 187, 'mobile_money', 'orange_money', NULL, 540.00, 'success', '2026-06-09 23:45:55'),
(188, 188, 'mobile_money', 'mtn_momo', NULL, 162.00, 'success', '2026-06-09 23:57:38'),
(189, 189, 'cash', NULL, NULL, 216.00, 'success', '2026-06-10 00:21:38'),
(190, 190, 'cash', NULL, NULL, 4887.00, 'success', '2026-06-10 00:26:29'),
(191, 191, 'cash', NULL, NULL, 500.00, 'success', '2026-06-10 00:27:45'),
(192, 192, 'cash', NULL, NULL, 5300.00, 'success', '2026-06-10 23:38:35'),
(193, 193, 'cash', NULL, NULL, 300.00, 'success', '2026-06-11 03:14:25'),
(194, 194, 'cash', NULL, NULL, 405.00, 'success', '2026-06-11 03:15:16'),
(195, 195, 'cash', NULL, NULL, 3375.00, 'success', '2026-06-11 03:18:38'),
(196, 196, 'cash', NULL, NULL, 1080.00, 'success', '2026-06-11 03:20:22'),
(197, 197, 'cash', NULL, NULL, 216.00, 'success', '2026-06-11 03:22:13'),
(198, 198, 'cash', NULL, NULL, 648.00, 'success', '2026-06-11 03:23:58'),
(199, 199, 'cash', NULL, NULL, 432.00, 'success', '2026-06-14 18:39:48'),
(200, 200, 'cash', NULL, NULL, 432.00, 'success', '2026-06-14 18:40:35'),
(201, 201, 'cash', NULL, NULL, 593600.00, 'success', '2026-06-14 19:15:27'),
(202, 202, 'cash', NULL, NULL, 593600.00, 'success', '2026-06-15 17:01:23'),
(203, 203, 'cash', NULL, NULL, 148400.00, 'success', '2026-06-15 17:01:59'),
(204, 204, 'cash', NULL, NULL, 42400.00, 'success', '2026-06-15 17:22:21'),
(205, 205, 'cash', NULL, NULL, 171720.00, 'success', '2026-06-15 17:41:51'),
(206, 206, 'cash', NULL, NULL, 324.00, 'success', '2026-06-15 18:47:39'),
(207, 207, 'cash', NULL, NULL, 21200.00, 'success', '2026-06-15 19:31:26'),
(208, 208, 'cash', NULL, NULL, 21200.00, 'success', '2026-06-15 19:33:20'),
(209, 209, 'cash', NULL, NULL, 212000.00, 'success', '2026-06-15 19:39:35'),
(210, 210, 'cash', NULL, NULL, 1484000.00, 'success', '2026-06-15 19:50:15'),
(211, 211, 'cash', NULL, NULL, 212000.00, 'success', '2026-06-15 19:57:00'),
(212, 212, 'cash', NULL, NULL, 8480.00, 'success', '2026-06-15 20:02:10'),
(213, 213, 'cash', NULL, NULL, 1908000.00, 'success', '2026-06-15 20:07:23'),
(214, 214, 'cash', NULL, NULL, 10600000.00, 'success', '2026-06-15 20:10:29'),
(215, 215, 'cash', NULL, NULL, 2700.00, 'success', '2026-06-15 21:52:47'),
(216, 216, 'cash', NULL, NULL, 216.00, 'success', '2026-06-15 21:52:59'),
(217, 217, 'cash', NULL, NULL, 1259280.00, 'success', '2026-06-15 23:13:17'),
(218, 218, 'cash', NULL, NULL, 66770.46, 'success', '2026-06-15 23:14:45'),
(219, 219, 'cash', NULL, NULL, 133000.00, 'success', '2026-06-16 01:21:43'),
(220, 220, 'cash', NULL, NULL, 848000.00, 'success', '2026-06-16 01:26:36'),
(221, 221, 'cash', NULL, NULL, 162180.00, 'success', '2026-06-16 01:30:48'),
(222, 222, 'cash', NULL, NULL, 5300.00, 'success', '2026-06-16 01:41:23'),
(223, 223, 'cash', NULL, NULL, 864.00, 'success', '2026-06-16 02:08:49'),
(224, 224, 'cash', NULL, NULL, 5300.00, 'success', '2026-06-16 02:38:39'),
(225, 225, 'cash', NULL, NULL, 5300.00, 'success', '2026-06-16 02:38:52'),
(226, 226, 'cash', NULL, NULL, 15900.00, 'success', '2026-06-16 02:39:09'),
(227, 227, 'cash', NULL, NULL, 381600.00, 'success', '2026-06-16 02:40:05'),
(228, 228, 'cash', NULL, NULL, 267120.00, 'success', '2026-06-16 02:53:40'),
(229, 229, 'cash', NULL, NULL, 10600.00, 'success', '2026-06-16 03:09:03'),
(230, 230, 'cash', NULL, NULL, 1590.00, 'success', '2026-06-16 03:45:21'),
(231, 231, 'cash', NULL, NULL, 267120.00, 'success', '2026-06-16 03:49:01'),
(232, 232, 'cash', NULL, NULL, 4770000.00, 'success', '2026-06-16 19:15:59'),
(233, 233, 'cash', NULL, NULL, 36803200.00, 'success', '2026-06-16 19:19:01'),
(234, 234, 'cash', NULL, NULL, 1441600.00, 'success', '2026-06-16 19:27:32'),
(235, 235, 'cash', NULL, NULL, 254400.00, 'success', '2026-06-16 19:51:46'),
(236, 236, 'cash', NULL, NULL, 95400.00, 'success', '2026-06-16 19:52:21'),
(237, 237, 'cash', NULL, NULL, 95400.00, 'success', '2026-06-16 19:52:59'),
(238, 238, 'cash', NULL, NULL, 424000.00, 'success', '2026-06-16 19:53:44'),
(239, 239, 'cash', NULL, NULL, 47700.00, 'success', '2026-06-16 19:55:35'),
(240, 240, 'cash', NULL, NULL, 424000.00, 'success', '2026-06-16 19:57:13'),
(241, 241, 'cash', NULL, NULL, 424000.00, 'success', '2026-06-16 21:13:59'),
(242, 242, 'cash', NULL, NULL, 2120000.00, 'success', '2026-06-17 00:53:07'),
(243, 243, 'cash', NULL, NULL, 424000.00, 'success', '2026-06-17 02:17:39'),
(244, 244, 'cash', NULL, NULL, 424000.00, 'success', '2026-06-17 02:17:53'),
(245, 245, 'cash', NULL, NULL, 8000.00, 'success', '2026-06-17 02:58:24'),
(246, 246, 'cash', NULL, NULL, 216.00, 'success', '2026-06-17 03:27:19'),
(247, 247, 'cash', NULL, NULL, 2120000.00, 'success', '2026-06-17 04:15:54'),
(248, 248, 'cash', NULL, NULL, 731400.00, 'success', '2026-06-17 04:16:38'),
(249, 249, 'cash', NULL, NULL, 16960000.00, 'success', '2026-06-17 04:17:17'),
(250, 250, 'cash', NULL, NULL, 1272000.00, 'success', '2026-06-17 04:27:51'),
(251, 251, 'cash', NULL, NULL, 1590.00, 'success', '2026-06-17 04:28:24'),
(252, 252, 'cash', NULL, NULL, 2120.00, 'success', '2026-06-17 04:29:29'),
(253, 253, 'cash', NULL, NULL, 10.30, 'success', '2026-06-17 09:02:01'),
(254, 254, 'cash', NULL, NULL, 10.30, 'success', '2026-06-17 09:03:58'),
(255, 255, 'mobile_money', 'mtn_momo', NULL, 10.30, 'success', '2026-06-17 09:08:08'),
(256, 256, 'cash', NULL, NULL, 41.20, 'success', '2026-06-17 09:37:16'),
(257, 257, 'cash', NULL, NULL, 226.60, 'success', '2026-06-17 09:46:34'),
(258, 258, 'cash', NULL, NULL, 15.45, 'success', '2026-06-17 09:56:20'),
(259, 259, 'cash', NULL, NULL, 10.30, 'success', '2026-06-17 10:28:39'),
(260, 260, 'mobile_money', 'mtn_momo', NULL, 10.30, 'success', '2026-06-17 10:51:21'),
(261, 261, 'mobile_money', 'mtn_momo', NULL, 108.15, 'success', '2026-06-17 18:59:57'),
(262, 262, 'mobile_money', 'orange_money', NULL, 1328.00, 'success', '2026-06-18 02:29:09'),
(263, 263, 'mobile_money', 'mtn_momo', NULL, 365.65, 'success', '2026-06-18 02:48:26'),
(264, 264, 'cash', NULL, NULL, 1076.35, 'success', '2026-06-18 02:49:38'),
(265, 265, 'cash', NULL, NULL, 46.35, 'success', '2026-06-18 02:57:13'),
(266, 266, 'cash', NULL, NULL, 319.30, 'success', '2026-06-18 03:31:41'),
(267, 267, 'cash', NULL, NULL, 92.70, 'success', '2026-06-18 03:32:38'),
(268, 268, 'cash', NULL, NULL, 15.45, 'success', '2026-06-18 03:57:55'),
(269, 269, 'cash', NULL, NULL, 15.45, 'success', '2026-06-18 03:59:01'),
(270, 270, 'cash', NULL, NULL, 540.75, 'success', '2026-06-18 04:50:19'),
(271, 271, 'cash', NULL, NULL, 231.75, 'success', '2026-06-18 04:54:50'),
(272, 272, 'cash', NULL, NULL, 6895.80, 'success', '2026-06-20 23:11:04'),
(273, 273, 'cash', NULL, NULL, 34560.00, 'success', '2026-06-20 23:12:25'),
(274, 274, 'cash', NULL, NULL, 432.00, 'success', '2026-06-20 23:13:14'),
(275, 275, 'cash', NULL, NULL, 972.00, 'success', '2026-06-21 00:07:53'),
(276, 276, 'mobile_money', 'mtn_momo', NULL, 6372.00, 'success', '2026-06-21 18:28:46'),
(277, 277, 'card', 'card', NULL, 1317368.00, 'success', '2026-06-28 20:52:30');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `description`) VALUES
(1, 'dashboard.view', 'Voir le tableau de bord'),
(2, 'sales.view', 'Voir les ventes'),
(3, 'sales.manage', 'Gérer les ventes'),
(4, 'inventory.view', 'Voir inventaire'),
(5, 'inventory.manage', 'Gérer inventaire'),
(6, 'stores.manage', 'Gérer succursales'),
(7, 'users.manage', 'Gérer utilisateurs'),
(8, 'pos.access', 'Accès caisse'),
(9, 'reports.view', 'Voir rapports'),
(10, 'manage_users', 'Manage users and roles'),
(11, 'manage_products', 'Manage product catalog'),
(12, 'manage_sales', 'Manage sales'),
(13, 'manage_inventory', 'Manage inventory'),
(14, 'manage_warehouse', 'Manage warehouse operations'),
(15, 'manage_accounting', 'Manage accounting'),
(16, 'manage_reports', 'View and export reports'),
(17, 'manage_cash_register', 'Manage cash registers'),
(18, 'manage_settings', 'System settings'),
(19, 'view_dashboard', 'Access dashboards'),
(20, 'approve_transfers', 'Approve stock transfers'),
(21, 'approve_expenses', 'Approve expenses'),
(22, 'warehouse.receive', 'Receive goods'),
(23, 'warehouse.dispatch', 'Dispatch stock'),
(24, 'warehouse.inventory', 'Warehouse inventory');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `store_id` int(11) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `unit` varchar(50) DEFAULT 'unite',
  `expiry_date` date DEFAULT NULL,
  `min_stock_level` int(11) DEFAULT 5,
  `image_url` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `supplier_id`, `store_id`, `sku`, `barcode`, `name`, `description`, `price`, `cost`, `stock_quantity`, `unit`, `expiry_date`, `min_stock_level`, `image_url`, `updated_at`, `deleted_at`) VALUES
(1, NULL, NULL, 1, 'ch001', '115075084638', 'Chargeur iPhone', NULL, 5000.00, 3500.00, -52, '', NULL, 15, NULL, '2026-05-17 15:20:13', '2026-05-17 15:20:13'),
(2, NULL, NULL, 1, '12pro', '1234567890', 'Chargeur iPhone12 Edit', NULL, 7500.00, 5000.00, 490, 'piece', NULL, 5, NULL, '2026-05-10 06:36:04', '2026-05-10 05:37:20'),
(3, NULL, NULL, 1, 'USB', '', 'Clé USB', NULL, 2000.00, 1500.00, 100, 'piece', '0000-00-00', 5, NULL, '2026-05-10 06:12:56', '2026-05-10 06:12:56'),
(8, 1, NULL, 1, 'Cas', '620228254950', 'Casque', NULL, 1000.00, 600.00, 478, 'piece', NULL, 50, NULL, '2026-05-17 15:20:19', '2026-05-17 15:20:19'),
(9, 3, NULL, 1, 'TCL', '996750472055', 'TCL', NULL, 200000.00, 150000.00, 45, 'piece', NULL, 10, 'uploads/products/prod_6a2228e75ae92.jpeg', '2026-06-28 20:52:30', NULL),
(10, 3, NULL, 1, 'ROCH', '706980946618', 'Roch', NULL, 18000.00, 150000.00, 70, 'piece', NULL, 10, 'uploads/products/prod_6a2229cb308fe.jpeg', '2026-06-16 19:52:59', NULL),
(11, 3, NULL, 1, 'Masco', '295506542713', 'Masco', NULL, 140000.00, 100000.00, 20, 'piece', NULL, 10, 'uploads/products/prod_6a22418685ee5.jpeg', '2026-06-15 17:41:51', NULL),
(12, 5, NULL, 1, 'Alimentaire ', '361415102148', 'Pomme de cannelle ', NULL, 5000.00, 3500.00, 960, 'kg', '2026-05-30', 50, '../uploads/products/prod_6a1073159dee8.jpeg', '2026-05-23 02:09:51', '2026-05-23 02:09:51'),
(13, 6, NULL, 1, 'ACK001', '863203042252', 'Anti-choquée', NULL, 2000.00, 1500.00, 50, 'piece', NULL, 50, 'uploads/products/prod_6a22405d102cd.jpeg', '2026-06-28 21:03:52', NULL),
(14, 1, NULL, 1, 'pc', '976494348358', 'Gaming Clavier', NULL, 400000.00, 500.00, 740, 'piece', NULL, 10, 'uploads/products/prod_6a2241c683677.jpeg', '2026-06-17 04:27:51', NULL),
(15, 1, NULL, 1, 'Skl', '752260992895', 'Skelewu', NULL, 5000.00, 1500.00, 0, 'piece', NULL, 5, 'uploads/products/prod_6a125100c6335.png', '2026-05-29 16:02:46', '2026-05-29 16:02:46'),
(16, 1, NULL, 1, 'cq', '641098202873', 'Casque & Clavier', NULL, 4000.00, 500.00, 140, 'piece', NULL, 50, 'uploads/products/prod_6a2240b0bf042.jpeg', '2026-06-15 19:33:20', NULL),
(17, 6, NULL, 1, 'CH', '263899435929', 'Chargeur', NULL, 5000.00, 3000.00, 20, 'piece', NULL, 5, 'uploads/products/prod_6a224074513a7.jpeg', '2026-06-16 02:39:09', NULL),
(24, 7, NULL, 6, 'Air', '296859369272', 'Apsonic 200GY', NULL, 20000.00, 15000.00, 59, 'piece', NULL, 20, 'uploads/products/prod_6a14d92198ca2.jpeg', '2026-06-05 02:40:42', NULL),
(25, 1, NULL, 1, 'H&H', '617916893536', 'Bar code Scanner', NULL, 300.00, 150.00, 40, 'piece', '2026-06-04', 5, 'uploads/products/prod_6a2242127fd2a.jpeg', '2026-06-17 04:28:24', NULL),
(26, 6, NULL, 1, 'Support', '761511577313', 'Support', NULL, 3000.00, 1500.00, 570, 'piece', NULL, 50, 'uploads/products/prod_6a222ad98b2ae.jpeg', '2026-06-17 04:16:38', NULL),
(27, 6, NULL, 1, 'Srt', '681634850630', 'support vélo & Moto', NULL, 4500.00, 2000.00, 1990, 'piece', NULL, 100, 'uploads/products/prod_6a222cc40edf5.jpeg', '2026-06-16 19:55:35', NULL),
(28, 1, NULL, 6, 'POS', '985227665804', 'POS Machine', NULL, 75000.00, 30000.00, 70, 'piece', NULL, 20, 'uploads/products/prod_6a2237f4bde8d.jpeg', '2026-06-09 18:44:00', NULL),
(29, 1, NULL, 1, 'tkp', '349858904752', 'Network tools', NULL, 5000.00, 2000.00, 40, 'piece', '0000-00-00', 5, 'uploads/products/prod_6a224602d6458.jpeg', '2026-06-15 20:03:41', NULL),
(30, 1, NULL, 1, 'hub', '748736309555', 'USB Hub', NULL, 3800.00, 1800.00, 299, 'piece', '0000-00-00', 48, 'uploads/products/prod_6a22edee41819.jpeg', '2026-06-28 20:52:30', NULL),
(31, 1, NULL, 1, 'MS', '570230088567', 'Support Métallique', NULL, 15000.00, 7000.00, 49, 'piece', '0000-00-00', 10, 'uploads/products/prod_6a22ef3dca0fa.jpeg', '2026-06-28 20:52:30', NULL),
(32, 1, NULL, 1, 'GG', '736716370874', 'Souris', NULL, 3000.00, 998.00, 300, 'piece', '0000-00-00', 50, 'uploads/products/prod_6a22f016ecbc1.jpeg', '2026-06-05 15:49:42', NULL),
(35, 1, NULL, 1, 'POS-Full', '341101095671', 'Pos Machine', NULL, 800000.00, 300000.00, 50, 'piece', '0000-00-00', 47, 'uploads/products/prod_6a22f191bee51.jpeg', '2026-06-05 15:56:01', NULL),
(38, 1, NULL, 1, 'mac', '644589997178', 'Mac Book Chargeurs', NULL, 25000.00, 12000.00, 300, 'piece', '0000-00-00', 50, 'uploads/products/prod_6a23309901953.jpeg', '2026-06-05 20:24:57', NULL),
(39, 1, NULL, 1, 'CA_GA', '194629564684', 'Gaming Casque', NULL, 15000.00, 6000.00, 70, 'piece', '0000-00-00', 13, 'uploads/products/prod_6a2331bb85cf4.jpeg', '2026-06-05 20:29:47', NULL),
(40, 3, NULL, 1, 'MTC', '693752893718', 'Multi-Air conditioner', NULL, 1200000.00, 600000.00, 10, 'piece', '0000-00-00', 5, 'uploads/products/prod_6a2332a50e421.jpeg', '2026-06-05 20:33:41', NULL),
(41, 1, NULL, 1, 'MSTC', '449038913001', 'Mac type-C Hub', NULL, 17000.00, 8000.00, 40, 'piece', '0000-00-00', 6, 'uploads/products/prod_6a2334aa73717.jpeg', '2026-06-16 01:30:48', NULL),
(42, 8, NULL, 1, 'Tst001', '716751066523', 'Testeur', NULL, 12000.00, 3998.00, 200, 'piece', '0000-00-00', 50, 'uploads/products/prod_6a23354a5ecf1.jpeg', '2026-06-15 23:13:17', NULL),
(43, 1, NULL, 1, 'CCTV01', '404341954468', 'CCTV Camera', NULL, 27000.00, 12000.00, 300, 'piece', '0000-00-00', 50, 'uploads/products/prod_6a2335c8366e1.jpeg', '2026-06-05 20:47:04', NULL),
(44, 1, NULL, 1, 'CCTV02', '102323874522', 'CCTV Camera', NULL, 28000.00, 11000.00, 142, 'piece', '0000-00-00', 50, 'uploads/products/prod_6a233618e7fed.jpeg', '2026-06-28 20:52:30', NULL),
(46, 1, NULL, 1, 'CCTV03', '859892108665', 'CCTV Camera', NULL, 40000.00, 23000.00, 240, 'piece', '0000-00-00', 50, 'uploads/products/prod_6a2336cb11ebc.jpeg', '2026-06-16 19:51:46', NULL),
(47, 1, NULL, 1, 'barcode01', '618324508680', 'Bar code device', NULL, 18000.00, 8000.00, 100, 'piece', '0000-00-00', 15, 'uploads/products/prod_6a23374073fc2.jpeg', '2026-06-15 20:11:45', NULL),
(48, 1, NULL, 1, 'barcode02', '589123243903', 'Bar code scanners', NULL, 20000.00, 8000.00, 50, 'piece', '0000-00-00', 13, 'uploads/products/prod_6a2337d555b38.jpeg', '2026-06-05 20:55:49', NULL),
(49, 1, NULL, 1, 'POS android', '182187591628', 'Pos Machine', NULL, 75000.00, 35000.00, 200, 'piece', '0000-00-00', 38, 'uploads/products/prod_6a23384758798.jpeg', '2026-06-05 20:57:43', NULL),
(50, 1, NULL, 1, 'printer001', '277714597346', 'Printer Label', NULL, 23000.00, 13000.00, 40, 'piece', '0000-00-00', 15, 'uploads/products/prod_6a2338ca3de7b.jpeg', '2026-06-05 20:59:54', NULL),
(51, 1, NULL, 1, 'printer01', '823264922350', 'Thermal Printer', NULL, 30000.00, 15000.00, 400, 'piece', '0000-00-00', 50, 'uploads/products/prod_6a23397f2c2ca.jpeg', '2026-06-05 21:02:55', NULL),
(52, 1, NULL, 1, 'printer002', '137687117997', 'Printer Label Machine', NULL, 70000.00, 37000.00, 70, 'piece', NULL, 20, 'uploads/products/prod_6a233bd8653e3.jpeg', '2026-06-05 21:13:20', NULL),
(53, 9, NULL, 1, 'ChairG1', '644806397133', 'Gaming Chair', NULL, 6999.00, 45000.00, 20, 'piece', '0000-00-00', 10, 'uploads/products/prod_6a233c803f219.png', '2026-06-15 23:14:45', NULL),
(55, 6, NULL, 6, 'CH-02', '349740254814', 'Chargeur', NULL, 2500.00, 1000.00, 80, 'piece', '0000-00-00', 20, 'uploads/products/prod_6a2452bf1307b.jpeg', '2026-06-06 17:07:51', NULL),
(56, 6, NULL, 7, 'CH-BK1', '563414013961', 'Apple Magnetic Wireless Power Bank', NULL, 400.00, 2500.00, 199, 'piece', NULL, 20, 'uploads/products/prod_6a2842dcf1276.jpeg', '2026-06-21 18:28:46', NULL),
(57, 1, NULL, 7, 'Apple AirTag', '795771512739', 'Apple AirTag', NULL, 500.00, 350.00, 90, 'piece', '0000-00-00', 50, 'uploads/products/prod_6a277c3bb24aa.jpeg', '2026-06-21 18:28:46', NULL),
(58, 6, NULL, 7, 'Apple USB-C to USB Adapter', '752200695230', 'Apple USB-C to USB Adapter', NULL, 200.00, 150.00, 290, 'piece', '0000-00-00', 10, 'uploads/products/prod_6a277ce7a4c46.jpeg', '2026-06-17 02:58:24', NULL),
(59, 6, NULL, 7, 'Universal Lazy Stand', '529976633489', 'Universal Lazy Stand', NULL, 200.00, 150.00, 65, 'piece', '0000-00-00', 15, 'uploads/products/prod_6a277dc44a394.jpeg', '2026-06-21 18:28:46', NULL),
(60, 6, NULL, 7, 'AIR-POD', '870741646038', 'AIR Podg2', NULL, 300.00, 200.00, 294, 'piece', '0000-00-00', 50, 'uploads/products/prod_6a284416350e8.jpeg', '2026-06-21 18:28:46', NULL),
(61, 1, NULL, 7, 'TYPEC-HUB', '823825971572', 'TypeC-Hub', NULL, 380.00, 200.00, 88, 'piece', '0000-00-00', 50, 'uploads/products/prod_6a2845057933a.jpeg', '2026-06-20 23:11:04', NULL),
(62, 1, NULL, 7, 'Metal Support', '787336083381', 'Support Métallique', NULL, 300.00, 180.00, 79, 'piece', '0000-00-00', 30, 'uploads/products/prod_6a28459725894.jpeg', '2026-06-20 23:11:04', NULL),
(63, 6, NULL, 7, 'iphone16 Charger', '984146153888', 'Iphone 16 Chargeur', NULL, 75.00, 40.00, 270, 'piece', '0000-00-00', 50, 'uploads/products/prod_6a284612b377d.jpeg', '2026-06-20 23:11:04', NULL),
(64, 6, NULL, 7, 'Anti-choque', '631854132465', 'Anti-choque', NULL, 20.00, 8.00, 298, 'piece', '0000-00-00', 50, 'uploads/products/prod_6a28467c6aa31.jpeg', '2026-06-11 03:18:38', NULL),
(65, 6, NULL, 7, 'Support de Téléphone', '141005794221', 'Support de Portable', NULL, 150.00, 70.00, 490, 'piece', '0000-00-00', 50, 'uploads/products/prod_6a28470bee8d2.jpeg', '2026-06-11 03:23:58', NULL),
(70, 6, NULL, 6, 'AIR-POD-S6', NULL, 'AIR Podg2', NULL, 300.00, 200.00, 92, 'piece', NULL, 50, 'uploads/products/prod_6a284416350e8.jpeg', '2026-06-15 18:39:42', NULL),
(71, 3, NULL, 1, 'Roof Air', '792151598129', 'Roof Air condition', NULL, 2000000.00, 1600000.00, 50, 'piece', '0000-00-00', 10, 'uploads/products/prod_6a305faf78888.jpeg', '2026-06-15 20:25:19', NULL),
(72, 1, NULL, 6, 'Apple AirTag-S6', NULL, 'Apple AirTag', NULL, 500.00, 350.00, 50, 'piece', NULL, 50, 'uploads/products/prod_6a277c3bb24aa.jpeg', '2026-06-15 23:04:14', NULL),
(73, 10, NULL, 11, 'Beef Kebabs', NULL, 'Flame-Grilled Beef Kebabs', NULL, 15.00, 8.00, 90, 'piece', '2026-06-20', 20, 'uploads/products/prod_6a32445279a8d.jpeg', '2026-06-18 03:32:38', NULL),
(74, 10, NULL, 11, 'BBQ Chicken', NULL, 'BBQ Chicken', NULL, 20.00, 11.00, 88, 'piece', '2026-06-20', 20, 'uploads/products/prod_6a32477d876ec.jpeg', '2026-06-18 02:49:38', NULL),
(75, 13, NULL, 11, 'BEV-001', '3760123456789', 'Mineral Water 1.5L', NULL, 10.00, 7.00, 30, 'piece', NULL, 10, 'uploads/products/prod_6a32552920cce.jpeg', '2026-06-18 03:31:41', NULL),
(76, 14, NULL, 11, 'GRO-002', NULL, 'Rice 5kg', NULL, 140.00, 38.00, 45, 'piece', NULL, 5, NULL, '2026-06-17 08:10:40', '2026-06-17 08:10:40'),
(77, 10, NULL, 11, 'Full chicken', NULL, 'Full chicken', NULL, 160.00, 60.00, 5, 'piece', '2026-06-24', 5, 'uploads/products/prod_6a3254693306e.jpeg', '2026-06-18 02:48:26', NULL),
(78, 12, NULL, 11, 'Malta', '305885457120', 'Malta canette', NULL, 15.00, 9.00, 90, 'piece', '0000-00-00', 20, 'uploads/products/prod_6a3256fcaa9a8.jpeg', '2026-06-18 03:32:38', NULL),
(79, 12, NULL, 11, 'Coco- Cola', '3.76012E+12', 'Coca Cola', NULL, 15.00, 10.00, 115, 'piece', NULL, 50, 'uploads/products/prod_6a32eb69c7328.jpeg', '2026-06-18 03:31:41', NULL),
(80, 12, NULL, 11, 'Fanta-002', NULL, 'Fanta', NULL, 15.00, 10.00, 110, 'piece', NULL, 50, 'uploads/products/prod_6a32ebe1acce8.jpeg', '2026-06-18 04:54:50', NULL),
(81, 12, NULL, 11, 'Sprint-01', NULL, 'Sprite', NULL, 15.00, 10.00, 110, 'piece', NULL, 50, 'uploads/products/prod_6a32edc130283.jpeg', '2026-06-18 04:54:50', NULL),
(82, 12, NULL, 11, 'Cola-1.5L', NULL, 'Coca Cola 1.5L', NULL, 35.00, 28.00, 115, 'piece', NULL, 50, 'uploads/products/prod_6a32eb8bc4f9d.jpeg', '2026-06-18 03:31:41', NULL),
(83, 12, NULL, 11, 'pepsi', NULL, 'Pepsi', NULL, 35.00, 28.00, 110, 'piece', NULL, 50, 'uploads/products/prod_6a32ed6809afa.jpeg', '2026-06-18 04:50:19', NULL),
(84, 12, NULL, 11, 'Red Bull', NULL, 'Red Bull', NULL, 15.00, 10.00, 119, 'piece', NULL, 50, 'uploads/products/prod_6a32eda8bf460.jpeg', '2026-06-18 02:49:38', NULL),
(85, 12, NULL, 11, 'Energy', NULL, 'Monster', NULL, 15.00, 10.00, 95, 'piece', NULL, 50, 'uploads/products/prod_6a32ec74929bc.jpeg', '2026-06-18 02:49:38', NULL),
(86, 12, NULL, 11, 'Sprint-02', NULL, 'Sprite canette', NULL, 15.00, 10.00, 110, 'piece', NULL, 50, 'uploads/products/prod_6a32edd356cf7.jpeg', '2026-06-18 04:50:19', NULL),
(87, 12, NULL, 11, 'Cannett Fanta', NULL, 'Fanta Canette', NULL, 15.00, 10.00, 115, 'piece', NULL, 50, 'uploads/products/prod_6a32ebfa0bb86.jpeg', '2026-06-18 03:59:01', NULL),
(88, 12, NULL, 11, 'Watermelon', NULL, 'Watermelon Jus', NULL, 15.00, 10.00, 115, 'piece', NULL, 50, 'uploads/products/prod_6a32ee0857d91.jpeg', '2026-06-18 04:54:50', NULL),
(89, 12, NULL, 11, 'Peanapall', NULL, 'Peanapall', NULL, 15.00, 10.00, 119, 'piece', NULL, 50, 'uploads/products/prod_6a32ed13eb6f5.jpeg', '2026-06-18 02:57:13', NULL),
(90, 12, NULL, 11, 'Mango', NULL, 'Mango Jus', NULL, 15.00, 10.00, 110, 'piece', NULL, 50, 'uploads/products/prod_6a32ec3a24bf8.jpeg', '2026-06-18 04:50:19', NULL),
(91, 12, NULL, 11, 'Orange', NULL, 'Orange', NULL, 15.00, 10.00, 119, 'piece', NULL, 50, 'uploads/products/prod_6a32ec935cd94.jpeg', '2026-06-18 02:48:26', NULL),
(92, 12, NULL, 11, 'Malt-Cannet', NULL, 'Malta Cannett', NULL, 15.00, 10.00, 120, 'piece', NULL, 50, NULL, '2026-06-17 18:48:53', '2026-06-17 18:48:53'),
(93, 12, NULL, 11, 'Don-Simon', NULL, 'Don Simon', NULL, 45.00, 30.00, 115, 'piece', NULL, 50, 'uploads/products/prod_6a32ebc5552f5.jpeg', '2026-06-18 03:31:41', NULL),
(94, 12, NULL, 11, 'Ceres', NULL, 'Ceres', NULL, 45.00, 30.00, 111, 'piece', NULL, 50, 'uploads/products/prod_6a32eb3e81264.jpeg', '2026-06-18 02:49:38', NULL),
(95, 12, NULL, 11, 'Tampico', NULL, 'Tampico', NULL, 15.00, 10.00, 119, 'piece', NULL, 50, 'uploads/products/prod_6a32edeb450d1.jpeg', '2026-06-18 02:48:26', NULL),
(96, NULL, NULL, 7, 'WH-260620-8170', NULL, 'TCL 2.5hz', NULL, 4999.00, 4999.00, 0, 'piece', NULL, 5, NULL, '2026-06-20 10:30:38', NULL),
(97, NULL, NULL, 7, 'WH-260620-884E', NULL, 'Media 2.5hz', NULL, 7999.00, 7999.00, 0, 'piece', NULL, 5, NULL, '2026-06-20 10:30:39', NULL),
(98, NULL, NULL, 7, 'WH-260620-289D', NULL, 'LG 5.5hz', NULL, 6999.00, 6999.00, 0, 'piece', NULL, 5, NULL, '2026-06-20 11:02:52', NULL),
(99, NULL, NULL, 7, 'WH-260620-EEB4', NULL, 'Apple Magnetic Wireless Power Bank · CH-BK1', NULL, 2500.00, 2500.00, 0, 'piece', NULL, 5, NULL, '2026-06-20 11:02:52', NULL),
(100, NULL, NULL, 7, 'WH-260620-8735', NULL, 'Media 2.5hz · WH-260620-884E', NULL, 2500.00, 2500.00, 0, 'piece', NULL, 5, NULL, '2026-06-20 11:02:52', NULL),
(101, NULL, NULL, 7, 'WH-260620-E037', NULL, 'AIR Podg2 · AIR-POD', NULL, 200.00, 200.00, 1, 'piece', NULL, 5, NULL, '2026-06-20 23:15:36', NULL),
(102, NULL, NULL, 7, 'WH-260620-0EB8', NULL, 'Anti-choque · Anti-choque', NULL, 8.00, 8.00, 0, 'piece', NULL, 5, NULL, '2026-06-20 11:19:37', NULL),
(103, NULL, NULL, 7, 'WH-260620-9850', NULL, 'Apple Magnetic Wireless Power Bank · CH-BK1 · WH-260620-EEB4', NULL, 2500.00, 2500.00, 0, 'piece', NULL, 5, NULL, '2026-06-20 11:19:37', NULL),
(104, 15, NULL, 12, 'Vaseline coco', '827789149881', 'Vaseline', NULL, 120.00, 80.00, 300, 'piece', '0000-00-00', 50, 'uploads/products/prod_6a408e91e240d.jpeg', '2026-06-28 03:01:37', NULL),
(105, 15, NULL, 12, 'Nivea', '0987', 'Nivea', NULL, 120.00, 80.00, 300, 'piece', '0000-00-00', 50, 'uploads/products/prod_6a408fe1befa9.jpeg', '2026-06-28 03:07:13', NULL),
(106, 15, NULL, 12, 'Bleuseil', '0001', 'Vaseline, blueseal', NULL, 80.00, 45.00, 100, 'piece', '0000-00-00', 20, 'uploads/products/prod_6a4090dfd4ece.jpeg', '2026-06-28 03:11:27', NULL),
(107, 6, NULL, 7, 'ACK001-S7', NULL, 'Anti-choquée', NULL, 2000.00, 1500.00, 9, 'piece', NULL, 50, 'uploads/products/prod_6a22405d102cd.jpeg', '2026-06-28 21:03:52', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `status` enum('draft','pending','approved','partial','received','cancelled') NOT NULL DEFAULT 'draft',
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `expected_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_ordered` int(11) NOT NULL DEFAULT 0,
  `quantity_received` int(11) NOT NULL DEFAULT 0,
  `unit_cost` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `line_total` decimal(14,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Super Admin', 'Role for Super Admin'),
(2, 'Admin', 'Role for Admin'),
(3, 'Manager', 'Role for Manager'),
(4, 'Cashier', 'Role for Cashier'),
(5, 'Staff', 'Role for Staff'),
(6, 'Warehouse Manager', 'Warehouse operations lead'),
(7, 'Inventory Officer', 'Inventory and stock control'),
(8, 'Receiving Officer', 'Goods receipt operations'),
(9, 'Dispatch Officer', 'Stock dispatch operations'),
(10, 'Accountant', 'Accounting and finance'),
(11, 'Customer', 'Customer portal (future)'),
(12, 'Warehouse Auditor', 'Read-only warehouse audits and reports'),
(13, 'Storekeeper', 'Warehouse inventory operations');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 20),
(1, 21),
(1, 22),
(1, 23),
(1, 24),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 12),
(2, 13),
(2, 14),
(2, 16),
(2, 17),
(2, 18),
(2, 19),
(2, 20),
(3, 1),
(3, 2),
(3, 4),
(3, 5),
(3, 8),
(3, 9),
(3, 12),
(3, 13),
(3, 16),
(3, 19),
(3, 20),
(3, 21),
(4, 1),
(4, 2),
(4, 8),
(4, 19),
(5, 1),
(5, 4),
(5, 8),
(5, 19),
(6, 1),
(6, 4),
(6, 5),
(6, 9),
(6, 13),
(6, 14),
(6, 16),
(6, 19),
(6, 20),
(6, 22),
(6, 23),
(6, 24),
(7, 1),
(7, 4),
(7, 5),
(7, 9),
(7, 13),
(7, 14),
(7, 19),
(7, 24),
(8, 1),
(8, 4),
(8, 19),
(8, 22),
(8, 24),
(9, 1),
(9, 4),
(9, 19),
(9, 23),
(9, 24),
(10, 1),
(10, 9),
(10, 15),
(10, 16),
(10, 19),
(10, 21),
(11, 1),
(11, 19);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `receipt_no` varchar(100) NOT NULL,
  `store_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `status` enum('completed','pending','cancelled') DEFAULT 'completed',
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `receipt_no`, `store_id`, `user_id`, `customer_id`, `total`, `tax`, `discount`, `status`, `synced_at`, `created_at`, `deleted_at`) VALUES
(1, 'REC-1778391070996', 1, 1, NULL, 41300.00, 6300.00, 0.00, 'completed', NULL, '2026-05-10 05:31:11', NULL),
(2, 'REC-1778391169535', 1, 1, NULL, 277300.00, 42300.00, 0.00, 'completed', NULL, '2026-05-10 05:32:49', NULL),
(3, 'REC-1778391904665', 1, 1, NULL, 29500.00, 4500.00, 0.00, 'completed', NULL, '2026-05-10 05:45:04', NULL),
(4, 'REC-1778391917829', 1, 1, NULL, 23600.00, 3600.00, 0.00, 'completed', NULL, '2026-05-10 05:45:17', NULL),
(5, 'REC-1778391936799', 1, 1, NULL, 5900.00, 900.00, 0.00, 'completed', NULL, '2026-05-10 05:45:36', NULL),
(6, 'REC-1778392670815', 1, 1, NULL, 41300.00, 6300.00, 0.00, 'completed', NULL, '2026-05-10 05:57:50', NULL),
(7, 'REC-1778401249934', 1, 1, NULL, 17700.00, 2700.00, 0.00, 'completed', NULL, '2026-05-10 08:20:50', NULL),
(8, 'REC-1778455448456', 1, 1, NULL, 7080.00, 1080.00, 0.00, 'completed', NULL, '2026-05-10 23:24:08', NULL),
(9, 'REC-1778678497960', 1, 1, NULL, 2360000.00, 360000.00, 0.00, 'completed', NULL, '2026-05-13 13:21:38', NULL),
(10, 'REC-1778680014792', 1, 1, NULL, 17700.00, 2700.00, 0.00, 'completed', NULL, '2026-05-13 13:46:55', NULL),
(11, 'REC-1778680190326', 1, 1, NULL, 2146420.00, 327420.00, 0.00, 'completed', NULL, '2026-05-13 13:49:50', NULL),
(12, 'REC-1778680379645', 1, 1, NULL, 84960.00, 12960.00, 0.00, 'completed', NULL, '2026-05-13 13:52:59', NULL),
(13, 'REC-1779032318371', 1, 1, NULL, 17700.00, 2700.00, 0.00, 'completed', NULL, '2026-05-17 15:38:38', NULL),
(14, 'REC-1779035342380', 1, 1, NULL, 1191800.00, 181800.00, 0.00, 'completed', NULL, '2026-05-17 16:29:02', NULL),
(15, 'REC-1779036273167', 1, 1, NULL, 372880.00, 56880.00, 0.00, 'completed', NULL, '2026-05-17 16:44:33', NULL),
(16, 'REC-1779037347551', 1, 1, NULL, 330400.00, 50400.00, 0.00, 'completed', NULL, '2026-05-17 17:02:27', NULL),
(17, 'REC-1779040428999', 1, 1, NULL, 1180000.00, 180000.00, 0.00, 'completed', NULL, '2026-05-17 17:53:49', NULL),
(18, 'REC-1779102610935', 1, 1, NULL, 118000.00, 18000.00, 0.00, 'completed', NULL, '2026-05-18 11:10:10', NULL),
(19, 'REC-1779102684494', 1, 1, NULL, 1416000.00, 216000.00, 0.00, 'completed', NULL, '2026-05-18 11:11:24', NULL),
(20, 'REC-1779106578542', 1, 1, NULL, 11800.00, 1800.00, 0.00, 'completed', NULL, '2026-05-18 12:16:18', NULL),
(21, 'REC-1779106615438', 1, 1, NULL, 11800.00, 1800.00, 0.00, 'completed', NULL, '2026-05-18 12:16:55', NULL),
(22, 'REC-1779106632730', 1, 1, NULL, 11800.00, 1800.00, 0.00, 'completed', NULL, '2026-05-18 12:17:12', NULL),
(23, 'REC-1779106687763', 1, 1, NULL, 30916000.00, 4716000.00, 0.00, 'completed', NULL, '2026-05-18 12:18:08', NULL),
(24, 'REC-1779108222259', 1, 1, NULL, 965240.00, 147240.00, 0.00, 'completed', NULL, '2026-05-18 12:43:42', NULL),
(25, 'REC-1779125297022', 1, 1, NULL, 330400.00, 50400.00, 0.00, 'completed', NULL, '2026-05-18 17:28:17', NULL),
(26, 'R1-20260522194838-4', 1, 4, NULL, 17700.00, 2700.00, 0.00, 'completed', NULL, '2026-05-22 19:48:38', NULL),
(27, 'R1-20260522194905-4', 1, 4, NULL, 1486800.00, 226800.00, 0.00, 'completed', NULL, '2026-05-22 19:49:05', NULL),
(28, 'R1-20260522195156-4', 1, 4, NULL, 2803680.00, 427680.00, 0.00, 'completed', NULL, '2026-05-22 19:51:56', NULL),
(29, 'R1-20260522195310-4', 1, 4, NULL, 171100.00, 26100.00, 0.00, 'completed', NULL, '2026-05-22 19:53:10', NULL),
(30, 'R1-20260522202402-4', 1, 4, NULL, 672600.00, 102600.00, 0.00, 'completed', NULL, '2026-05-22 20:24:02', NULL),
(31, 'R1-20260522202548-4', 1, 4, NULL, 356360.00, 54360.00, 0.00, 'completed', NULL, '2026-05-22 20:25:48', NULL),
(32, 'R1-20260522203704-4', 1, 4, NULL, 182900.00, 27900.00, 0.00, 'completed', NULL, '2026-05-22 20:37:04', NULL),
(33, 'R1-20260522203823-4', 1, 4, NULL, 165200.00, 25200.00, 0.00, 'completed', NULL, '2026-05-22 20:38:23', NULL),
(34, 'R1-20260522205729-4', 1, 4, NULL, 165200.00, 25200.00, 0.00, 'completed', NULL, '2026-05-22 20:57:29', NULL),
(35, 'R1-20260522211219-4', 1, 4, NULL, 3304000.00, 504000.00, 0.00, 'completed', NULL, '2026-05-22 21:12:20', NULL),
(36, 'R1-20260522211518-4', 1, 4, NULL, 5900.00, 900.00, 0.00, 'completed', NULL, '2026-05-22 21:15:19', NULL),
(37, 'R1-20260522213137-4', 1, 4, NULL, 17700.00, 2700.00, 0.00, 'completed', NULL, '2026-05-22 21:31:37', NULL),
(38, 'R1-20260522215134-4', 1, 4, 1, 9440.00, 1440.00, 0.00, 'completed', NULL, '2026-05-22 21:51:34', NULL),
(39, 'R1-20260522215923-4', 1, 4, NULL, 23600.00, 3600.00, 0.00, 'completed', NULL, '2026-05-22 21:59:23', NULL),
(40, 'R1-20260522225003-2', 1, 2, NULL, 477900.00, 72900.00, 0.00, 'completed', NULL, '2026-05-22 22:50:03', NULL),
(41, 'R1-20260523001027-4', 1, 4, NULL, 240720.00, 36720.00, 0.00, 'completed', NULL, '2026-05-23 00:10:27', NULL),
(42, 'R1-20260523001129-4', 1, 4, NULL, 4720.00, 720.00, 0.00, 'completed', NULL, '2026-05-23 00:11:29', NULL),
(43, 'R1-20260523005136-4', 1, 4, NULL, 4489900.00, 684900.00, 0.00, 'completed', NULL, '2026-05-23 00:51:36', NULL),
(44, 'R1-20260523030010-4', 1, 4, NULL, 572400.00, 32400.00, 0.00, 'completed', NULL, '2026-05-23 03:00:10', NULL),
(45, 'R1-20260523051240-4', 1, 4, NULL, 167480.00, 9480.00, 0.00, 'completed', NULL, '2026-05-23 05:12:38', NULL),
(46, 'R1-20260523051301-4', 1, 4, NULL, 167480.00, 9480.00, 0.00, 'completed', NULL, '2026-05-23 05:13:00', NULL),
(47, 'R1-20260523051341-4', 1, 4, NULL, 167480.00, 9480.00, 0.00, 'completed', NULL, '2026-05-23 05:13:39', NULL),
(48, 'R1-20260523052002-4', 1, 4, NULL, 10600.00, 600.00, 0.00, 'completed', NULL, '2026-05-23 05:20:00', NULL),
(49, 'R1-20260523054241-4', 1, 4, 1, 5088000.00, 288000.00, 0.00, 'completed', NULL, '2026-05-23 05:42:40', NULL),
(50, 'R1-20260523152808-3', 1, 3, NULL, 267120.00, 15120.00, 0.00, 'completed', NULL, '2026-05-23 15:43:38', NULL),
(51, 'R1-20260523152953-3', 1, 3, 1, 63600.00, 3600.00, 0.00, 'completed', NULL, '2026-05-23 15:43:38', NULL),
(52, 'R1-20260523153036-3', 1, 3, NULL, 3816000.00, 216000.00, 0.00, 'completed', NULL, '2026-05-23 15:43:38', NULL),
(53, 'R1-20260523154124-3', 1, 3, 1, 5300000.00, 300000.00, 0.00, 'completed', NULL, '2026-05-23 15:43:38', NULL),
(54, 'R1-20260523154148-3', 1, 3, NULL, 1060000.00, 60000.00, 0.00, 'completed', NULL, '2026-05-23 15:43:38', NULL),
(55, 'R1-20260523201944-4', 1, 4, NULL, 2438000.00, 138000.00, 0.00, 'completed', NULL, '2026-05-23 20:19:44', NULL),
(56, 'R1-20260523202224-4', 1, 4, NULL, 5300.00, 300.00, 0.00, 'completed', NULL, '2026-05-23 20:22:24', NULL),
(57, 'R4-20260523163736-1', 1, 4, NULL, 1156400.00, 176400.00, 0.00, 'completed', NULL, '2026-05-24 00:30:34', NULL),
(58, 'R4-20260523163953-1', 1, 4, NULL, 23600.00, 3600.00, 0.00, 'completed', NULL, '2026-05-24 00:30:34', NULL),
(59, 'R1-20260523225847-4', 1, 4, NULL, 4240.00, 240.00, 0.00, 'completed', NULL, '2026-05-24 00:30:34', NULL),
(60, 'R1-20260523231344-4', 1, 4, NULL, 150520.00, 8520.00, 0.00, 'completed', NULL, '2026-05-24 00:30:34', NULL),
(61, 'R1-20260523231437-4', 1, 4, NULL, 854360.00, 48360.00, 0.00, 'completed', NULL, '2026-05-24 00:30:34', NULL),
(62, 'R1-20260523231844-4', 1, 4, NULL, 831040.00, 47040.00, 0.00, 'completed', NULL, '2026-05-24 00:30:34', NULL),
(63, 'R1-20260524010955-4', 1, 4, NULL, 38160.00, 2160.00, 0.00, 'completed', NULL, '2026-05-24 01:09:55', NULL),
(64, 'R6-20260524031249-7', 6, 7, NULL, 977040.00, 149040.00, 0.00, 'completed', NULL, '2026-05-24 03:15:01', NULL),
(65, 'R6-20260524031318-7', 6, 7, NULL, 148680.00, 22680.00, 0.00, 'completed', NULL, '2026-05-24 03:15:05', NULL),
(66, 'R1-20260524121057-4', 1, 4, NULL, 68900.00, 3900.00, 0.00, 'completed', NULL, '2026-05-24 12:10:57', NULL),
(67, 'R1-20260524121307-1', 1, 1, NULL, 6360000.00, 360000.00, 0.00, 'completed', NULL, '2026-05-24 12:13:07', NULL),
(68, 'R1-20260524121327-1', 1, 1, NULL, 265000.00, 15000.00, 0.00, 'completed', NULL, '2026-05-24 12:13:27', NULL),
(69, 'R6-20260524122340-8', 6, 8, NULL, 47200.00, 7200.00, 0.00, 'completed', NULL, '2026-05-24 12:23:40', NULL),
(70, 'R1-20260524130404-4', 1, 4, NULL, 148400.00, 8400.00, 0.00, 'completed', NULL, '2026-05-24 13:04:05', NULL),
(71, 'R1-20260524130440-4', 1, 4, NULL, 296800.00, 16800.00, 0.00, 'completed', NULL, '2026-05-24 13:04:40', NULL),
(72, 'R1-20260524130532-4', 1, 4, NULL, 424000.00, 24000.00, 0.00, 'completed', NULL, '2026-05-24 13:05:32', NULL),
(73, 'R1-20260524150556-4', 6, 7, NULL, 6360.00, 360.00, 0.00, 'completed', NULL, '2026-05-24 17:26:11', NULL),
(74, 'R6-20260524172918-7', 6, 7, NULL, 188800.00, 28800.00, 0.00, 'completed', NULL, '2026-05-24 17:31:52', NULL),
(75, 'R1-20260525222342-4', 1, 4, NULL, 767440.00, 43440.00, 0.00, 'completed', NULL, '2026-05-25 22:23:42', NULL),
(76, 'R1-20260525222533-4', 1, 4, NULL, 4240.00, 240.00, 0.00, 'completed', NULL, '2026-05-25 22:25:33', NULL),
(77, 'R1-20260525222559-4', 1, 4, NULL, 4240.00, 240.00, 0.00, 'completed', NULL, '2026-05-25 22:25:59', NULL),
(78, 'R1-20260525230747-3', 1, 3, NULL, 148400.00, 8400.00, 0.00, 'completed', NULL, '2026-05-25 23:07:47', NULL),
(79, 'R1-20260525230832-4', 1, 4, NULL, 1484000.00, 84000.00, 0.00, 'completed', NULL, '2026-05-25 23:08:32', NULL),
(80, 'R1-20260526010343-2', 1, 2, NULL, 445200.00, 25200.00, 0.00, 'completed', NULL, '2026-05-26 01:03:44', NULL),
(81, 'R1-20260526011430-4', 1, 4, NULL, 1038800.00, 58800.00, 0.00, 'completed', NULL, '2026-05-26 01:14:30', NULL),
(82, 'R1-20260526011812-4', 1, 4, NULL, 445200.00, 25200.00, 0.00, 'completed', NULL, '2026-05-26 01:18:12', NULL),
(83, 'R1-20260526011842-4', 1, 4, NULL, 424000.00, 24000.00, 0.00, 'completed', NULL, '2026-05-26 01:18:42', NULL),
(84, 'R1-20260526011907-4', 1, 4, NULL, 742000.00, 42000.00, 0.00, 'completed', NULL, '2026-05-26 01:19:07', NULL),
(85, 'R1-20260526011944-4', 1, 4, NULL, 742000.00, 42000.00, 0.00, 'completed', NULL, '2026-05-26 01:19:44', NULL),
(86, 'R1-20260526012005-4', 1, 4, NULL, 1038800.00, 58800.00, 0.00, 'completed', NULL, '2026-05-26 01:20:05', NULL),
(87, 'R1-20260526012449-2', 1, 2, NULL, 424000.00, 24000.00, 0.00, 'completed', NULL, '2026-05-26 01:24:49', NULL),
(88, 'R1-20260526013726-2', 1, 2, NULL, 1272000.00, 72000.00, 0.00, 'completed', NULL, '2026-05-26 01:37:26', NULL),
(89, 'R1-20260526013746-2', 1, 2, NULL, 848000.00, 48000.00, 0.00, 'completed', NULL, '2026-05-26 01:37:46', NULL),
(90, 'R1-20260526014430-2', 1, 2, NULL, 29680.00, 1680.00, 0.00, 'completed', NULL, '2026-05-26 01:44:30', NULL),
(91, 'R1-20260526014603-4', 1, 4, NULL, 4240000.00, 240000.00, 0.00, 'completed', NULL, '2026-05-26 01:46:03', NULL),
(92, 'R1-20260526021745-4', 1, 4, NULL, 2120.00, 120.00, 0.00, 'completed', NULL, '2026-05-26 02:17:45', NULL),
(93, 'R1-20260526022153-4', 1, 4, NULL, 428240.00, 24240.00, 0.00, 'completed', NULL, '2026-05-26 02:21:53', NULL),
(94, 'R1-20260526204046-4', 1, 4, NULL, 10600.00, 600.00, 0.00, 'completed', NULL, '2026-05-26 20:42:53', NULL),
(95, 'R1-20260526204134-4', 1, 4, NULL, 4240.00, 240.00, 0.00, 'completed', NULL, '2026-05-26 20:42:54', NULL),
(96, 'R1-20260526204309-4', 1, 4, NULL, 4240.00, 240.00, 0.00, 'completed', NULL, '2026-05-26 20:43:09', NULL),
(97, 'R1-20260526205815-4', 1, 4, NULL, 6360.00, 360.00, 0.00, 'completed', NULL, '2026-05-26 20:58:15', NULL),
(98, 'R1-20260526205925-4', 1, 4, 1, 3816000.00, 216000.00, 0.00, 'completed', NULL, '2026-05-26 20:59:25', NULL),
(99, 'R1-20260526210001-4', 1, 4, NULL, 15900.00, 900.00, 0.00, 'completed', NULL, '2026-05-26 21:00:01', NULL),
(100, 'R1-20260529155329-4', 1, 4, NULL, 26500.00, 1500.00, 0.00, 'completed', NULL, '2026-05-29 15:57:35', NULL),
(101, 'R1-20260529160531-1', 1, 1, NULL, 2120000.00, 120000.00, 0.00, 'completed', NULL, '2026-05-29 16:05:31', NULL),
(102, 'R1-20260602021939-3', 1, 4, NULL, 728220.00, 41220.00, 0.00, 'completed', NULL, '2026-06-02 02:25:21', NULL),
(103, 'R1-20260602022418-4', 1, 4, NULL, 917960.00, 51960.00, 0.00, 'completed', NULL, '2026-06-02 02:25:21', NULL),
(104, 'R1-20260602022721-3', 1, 3, NULL, 1280480.00, 72480.00, 0.00, 'completed', NULL, '2026-06-02 02:27:22', NULL),
(105, 'R1-20260603020148-4', 1, 4, NULL, 430360.00, 24360.00, 0.00, 'completed', NULL, '2026-06-03 02:07:12', NULL),
(106, 'R1-20260603020219-4', 1, 4, NULL, 2120.00, 120.00, 0.00, 'completed', NULL, '2026-06-03 02:07:12', NULL),
(107, 'R1-20260603020630-4', 1, 4, NULL, 1288960.00, 72960.00, 0.00, 'completed', NULL, '2026-06-03 02:07:12', NULL),
(108, 'R1-20260603020901-4', 1, 4, NULL, 19080.00, 1080.00, 0.00, 'completed', NULL, '2026-06-03 02:09:01', NULL),
(109, 'R1-20260603020913-4', 1, 4, NULL, 574520.00, 32520.00, 0.00, 'completed', NULL, '2026-06-03 02:09:13', NULL),
(110, 'R1-20260603020926-4', 1, 4, NULL, 574520.00, 32520.00, 0.00, 'completed', NULL, '2026-06-03 02:09:26', NULL),
(111, 'R1-20260603020950-4', 1, 4, NULL, 574520.00, 32520.00, 0.00, 'completed', NULL, '2026-06-03 02:09:50', NULL),
(112, 'R1-20260603021124-4', 1, 4, 1, 9328000.00, 528000.00, 0.00, 'completed', NULL, '2026-06-03 02:11:24', NULL),
(113, 'R1-20260603042203-1', 1, 1, NULL, 848000.00, 48000.00, 0.00, 'completed', NULL, '2026-06-03 04:22:03', NULL),
(114, 'R1-20260603045205-4', 1, 4, NULL, 796060.00, 45060.00, 0.00, 'completed', NULL, '2026-06-03 04:57:52', NULL),
(115, 'R1-20260603052823-4', 1, 4, NULL, 153700.00, 8700.00, 0.00, 'completed', NULL, '2026-06-03 05:28:23', NULL),
(116, 'R1-20260603054441-4', 1, 4, NULL, 848000.00, 48000.00, 0.00, 'completed', NULL, '2026-06-03 05:44:41', NULL),
(117, 'R1-20260603055145-4', 1, 4, NULL, 593600.00, 33600.00, 0.00, 'completed', NULL, '2026-06-03 05:51:45', NULL),
(118, 'R1-20260603055639-1', 1, 1, NULL, 21200.00, 1200.00, 0.00, 'completed', NULL, '2026-06-03 05:56:39', NULL),
(119, 'R1-20260603060846-4', 1, 4, NULL, 593600.00, 33600.00, 0.00, 'completed', NULL, '2026-06-03 06:08:46', NULL),
(120, 'R1-20260603063040-1', 1, 1, NULL, 21200.00, 1200.00, 0.00, 'completed', NULL, '2026-06-03 06:30:40', NULL),
(121, 'R1-20260603072348-4', 1, 4, NULL, 2511140.00, 142140.00, 0.00, 'completed', NULL, '2026-06-03 07:23:48', NULL),
(122, 'R1-20260603080650-4', 1, 4, NULL, 491840.00, 27840.00, 0.00, 'completed', NULL, '2026-06-03 08:06:50', NULL),
(123, 'R1-20260603080715-4', 1, 4, NULL, 848000.00, 48000.00, 0.00, 'completed', NULL, '2026-06-03 08:07:14', NULL),
(124, 'R1-20260603080807-4', 1, 4, NULL, 593600.00, 33600.00, 0.00, 'completed', NULL, '2026-06-03 08:08:06', NULL),
(125, 'R1-20260603142505-4', 1, 4, NULL, 1590.00, 90.00, 0.00, 'completed', NULL, '2026-06-03 15:37:01', NULL),
(126, 'R1-20260603153915-4', 1, 4, NULL, 807190.00, 45690.00, 0.00, 'completed', NULL, '2026-06-03 15:39:15', NULL),
(127, 'R1-20260603202241-4', 1, 4, NULL, 572400.00, 32400.00, 0.00, 'completed', NULL, '2026-06-04 15:22:26', NULL),
(128, 'R1-20260603203519-4', 1, 4, NULL, 12720.00, 720.00, 0.00, 'completed', NULL, '2026-06-04 15:22:26', NULL),
(129, 'R1-20260603203602-4', 1, 4, NULL, 57240.00, 3240.00, 0.00, 'completed', NULL, '2026-06-04 15:22:26', NULL),
(130, 'R6-20260604155359-1', 6, 1, NULL, 212000.00, 32400.00, 400.00, 'completed', NULL, '2026-06-04 15:54:00', NULL),
(131, 'R6-20260604160433-1', 6, 1, NULL, 23600.00, 3600.00, 0.00, 'completed', NULL, '2026-06-04 16:04:33', NULL),
(132, 'R6-20260604160818-1', 6, 1, NULL, 23600.00, 3600.00, 0.00, 'cancelled', NULL, '2026-06-04 16:08:18', NULL),
(133, 'R1-20260604162034-1', 1, 1, NULL, 428000.00, 24480.00, 4480.00, 'completed', NULL, '2026-06-04 16:20:34', NULL),
(134, 'R1-20260604214043-4', 1, 4, NULL, 591480.00, 33480.00, 0.00, 'completed', NULL, '2026-06-04 21:40:43', NULL),
(135, 'R1-20260604214743-4', 1, 4, NULL, 424000.00, 24000.00, 0.00, 'completed', NULL, '2026-06-04 21:47:44', NULL),
(136, 'R1-20260604214801-4', 1, 4, NULL, 148400.00, 8400.00, 0.00, 'completed', NULL, '2026-06-04 21:48:02', NULL),
(137, 'R1-20260604214902-4', 1, 4, NULL, 572400.00, 32400.00, 0.00, 'completed', NULL, '2026-06-04 21:49:02', NULL),
(138, 'R1-20260604214942-4', 1, 4, NULL, 848000.00, 48000.00, 0.00, 'completed', NULL, '2026-06-04 21:49:43', NULL),
(139, 'R1-20260604215210-1', 1, 1, NULL, 4240.00, 240.00, 0.00, 'completed', NULL, '2026-06-04 21:52:11', NULL),
(140, 'R6-20260604215903-7', 6, 7, NULL, 212400.00, 32400.00, 0.00, 'completed', NULL, '2026-06-04 21:59:03', NULL),
(141, 'R1-20260605022902-4', 1, 4, NULL, 1685400.00, 95400.00, 0.00, 'completed', NULL, '2026-06-05 02:29:02', NULL),
(142, 'R1-20260605023039-4', 1, 4, NULL, 636000.00, 36000.00, 0.00, 'completed', NULL, '2026-06-05 02:30:39', NULL),
(143, 'R6-20260605024042-1', 6, 1, NULL, 23600.00, 3600.00, 0.00, 'completed', NULL, '2026-06-05 02:40:42', NULL),
(144, 'R6-20260605024659-1', 6, 1, 2, 2035500.00, 310500.00, 0.00, 'completed', NULL, '2026-06-05 02:46:59', NULL),
(145, 'R1-20260605025128-1', 1, 1, NULL, 9540000.00, 540000.00, 0.00, 'completed', NULL, '2026-06-05 02:51:28', NULL),
(146, 'R1-20260605030825-2', 1, 2, NULL, 6921800.00, 391800.00, 0.00, 'completed', NULL, '2026-06-05 03:08:25', NULL),
(147, 'R1-20260606164949-1', 1, 1, NULL, 593600.00, 33600.00, 0.00, 'completed', NULL, '2026-06-06 16:49:49', NULL),
(148, 'R6-20260606165826-1', 6, 1, NULL, 619500.00, 94500.00, 0.00, 'completed', NULL, '2026-06-06 16:58:26', NULL),
(149, 'R6-20260606170751-1', 6, 1, 2, 54000.00, 9000.00, 5000.00, 'completed', NULL, '2026-06-06 17:07:51', NULL),
(150, 'R1-20260606192627-4', 1, 4, NULL, 341318.94, 19319.94, 0.00, 'completed', NULL, '2026-06-09 01:35:26', NULL),
(151, 'R1-20260606192741-4', 1, 4, NULL, 1590.00, 90.00, 0.00, 'completed', NULL, '2026-06-09 01:35:26', NULL),
(152, 'R1-20260609013343-4', 1, 4, NULL, 10600.00, 600.00, 0.00, 'completed', NULL, '2026-06-09 01:35:26', NULL),
(153, 'R1-20260609013434-4', 1, 4, NULL, 5300.00, 300.00, 0.00, 'completed', NULL, '2026-06-09 01:35:27', NULL),
(154, 'R7-20260609024833-1', 7, 1, NULL, 248.00, 48.00, 400.00, 'completed', NULL, '2026-06-09 02:48:34', NULL),
(155, 'R7-20260609025508-1', 7, 1, NULL, 4320.00, 320.00, 0.00, 'completed', NULL, '2026-06-09 02:55:08', NULL),
(156, 'R7-20260609025733-1', 7, 1, NULL, 432.00, 32.00, 0.00, 'completed', NULL, '2026-06-09 02:57:33', NULL),
(157, 'R7-20260609031556-1', 7, 1, 2, 18144.00, 1344.00, 0.00, 'completed', NULL, '2026-06-09 03:15:57', NULL),
(158, 'R7-20260609031835-1', 7, 1, NULL, 1404.00, 104.00, 0.00, 'completed', NULL, '2026-06-09 03:18:35', NULL),
(159, 'R7-20260609031943-1', 7, 1, NULL, 5724.00, 424.00, 0.00, 'completed', NULL, '2026-06-09 03:19:43', NULL),
(160, 'R7-20260609032050-1', 7, 1, NULL, 1296.00, 96.00, 0.00, 'completed', NULL, '2026-06-09 03:20:50', NULL),
(161, 'R7-20260609032144-1', 7, 1, NULL, 1404.00, 104.00, 0.00, 'completed', NULL, '2026-06-09 03:21:44', NULL),
(162, 'R7-20260609032157-1', 7, 1, NULL, 2160.00, 160.00, 0.00, 'completed', NULL, '2026-06-09 03:21:57', NULL),
(163, 'R7-20260609032210-1', 7, 1, NULL, 648.00, 48.00, 0.00, 'completed', NULL, '2026-06-09 03:22:10', NULL),
(164, 'R7-20260609032222-1', 7, 1, NULL, 648.00, 48.00, 0.00, 'completed', NULL, '2026-06-09 03:22:22', NULL),
(165, 'R7-20260609040625-9', 7, 9, NULL, 2160.00, 160.00, 0.00, 'completed', NULL, '2026-06-09 04:06:25', NULL),
(166, 'R7-20260609173105-1', 7, 1, NULL, 972.00, 72.00, 0.00, 'cancelled', NULL, '2026-06-09 17:31:05', NULL),
(167, 'R7-20260609185230-9', 7, 9, NULL, 1000.00, 96.00, 296.00, 'completed', NULL, '2026-06-09 18:52:30', NULL),
(168, 'R7-20260609185423-9', 7, 9, NULL, 2160.00, 160.00, 0.00, 'completed', NULL, '2026-06-09 18:54:23', NULL),
(169, 'R7-20260609185529-9', 7, 9, NULL, 432.00, 32.00, 0.00, 'completed', NULL, '2026-06-09 18:55:29', NULL),
(170, 'R7-20260609185914-9', 7, 9, NULL, 432.00, 32.00, 0.00, 'cancelled', NULL, '2026-06-09 18:59:14', NULL),
(171, 'R7-20260609193912-9', 7, 9, NULL, 410.40, 30.40, 0.00, 'completed', NULL, '2026-06-09 19:39:12', NULL),
(172, 'R7-20260609194000-9', 7, 9, NULL, 12312.00, 912.00, 0.00, 'completed', NULL, '2026-06-09 19:40:00', NULL),
(173, 'R7-20260609194106-9', 7, 9, NULL, 1296.00, 96.00, 0.00, 'completed', NULL, '2026-06-09 19:41:06', NULL),
(174, 'R7-20260609205245-12', 7, 12, NULL, 1026.00, 76.00, 0.00, 'completed', NULL, '2026-06-09 20:58:10', NULL),
(175, 'R7-20260609205353-12', 7, 12, NULL, 324.00, 24.00, 0.00, 'completed', NULL, '2026-06-09 20:58:11', NULL),
(176, 'R7-20260609205634-12', 7, 12, NULL, 82080.00, 6080.00, 0.00, 'completed', NULL, '2026-06-09 20:58:11', NULL),
(177, 'R7-20260609210205-12', 7, 12, NULL, 324.00, 24.00, 0.00, 'completed', NULL, '2026-06-09 21:02:05', NULL),
(178, 'R7-20260609210258-12', 7, 12, NULL, 1080.00, 80.00, 0.00, 'completed', NULL, '2026-06-09 21:02:58', NULL),
(179, 'R7-20260609210352-12', 7, 12, NULL, 648.00, 48.00, 0.00, 'completed', NULL, '2026-06-09 21:03:52', NULL),
(180, 'R7-20260609222927-13', 7, 13, NULL, 540.00, 40.00, 0.00, 'cancelled', NULL, '2026-06-09 22:29:27', NULL),
(181, 'R7-20260609231756-13', 7, 13, NULL, 1080.00, 80.00, 0.00, 'completed', NULL, '2026-06-09 23:17:56', NULL),
(182, 'R7-20260609233105-13', 7, 13, NULL, 324.00, 24.00, 0.00, 'completed', NULL, '2026-06-09 23:31:05', NULL),
(183, 'R7-20260609233202-13', 7, 13, NULL, 1620.00, 120.00, 0.00, 'completed', NULL, '2026-06-09 23:32:02', NULL),
(184, 'R7-20260609233255-13', 7, 13, NULL, 1296.00, 96.00, 0.00, 'completed', NULL, '2026-06-09 23:32:55', NULL),
(185, 'R7-20260609234311-1', 7, 1, NULL, 1620.00, 120.00, 0.00, 'completed', NULL, '2026-06-09 23:43:11', NULL),
(186, 'R7-20260609234515-1', 7, 1, NULL, 540.00, 40.00, 0.00, 'completed', NULL, '2026-06-09 23:45:15', NULL),
(187, 'R7-20260609234555-1', 7, 1, NULL, 540.00, 40.00, 0.00, 'completed', NULL, '2026-06-09 23:45:55', NULL),
(188, 'R7-20260609235738-1', 7, 1, NULL, 162.00, 12.00, 0.00, 'completed', NULL, '2026-06-09 23:57:38', NULL),
(189, 'R7-20260610002138-1', 7, 1, NULL, 216.00, 16.00, 0.00, 'completed', NULL, '2026-06-10 00:21:38', NULL),
(190, 'R7-20260610002629-1', 7, 1, NULL, 4887.00, 362.00, 0.00, 'completed', NULL, '2026-06-10 00:26:29', NULL),
(191, 'R7-20260610002745-1', 7, 1, NULL, 500.00, 40.00, 40.00, 'completed', NULL, '2026-06-10 00:27:45', NULL),
(192, 'R1-20260610233835-1', 1, 1, NULL, 5300.00, 300.00, 0.00, 'completed', NULL, '2026-06-10 23:38:35', NULL),
(193, 'R7-20260611031425-1', 7, 1, NULL, 300.00, 24.00, 24.00, 'completed', NULL, '2026-06-11 03:14:25', NULL),
(194, 'R7-20260611031516-1', 7, 1, NULL, 405.00, 30.00, 0.00, 'completed', NULL, '2026-06-11 03:15:16', NULL),
(195, 'R7-20260611031838-1', 7, 1, NULL, 3375.00, 250.00, 0.00, 'completed', NULL, '2026-06-11 03:18:38', NULL),
(196, 'R7-20260611032022-1', 7, 1, NULL, 1080.00, 80.00, 0.00, 'completed', NULL, '2026-06-11 03:20:22', NULL),
(197, 'R7-20260611032213-1', 7, 1, NULL, 216.00, 16.00, 0.00, 'completed', NULL, '2026-06-11 03:22:13', NULL),
(198, 'R7-20260611032357-1', 7, 1, NULL, 648.00, 48.00, 0.00, 'completed', NULL, '2026-06-11 03:23:57', NULL),
(199, 'R7-20260614183948-1', 7, 1, NULL, 432.00, 32.00, 0.00, 'completed', NULL, '2026-06-14 18:39:48', NULL),
(200, 'R7-20260614184035-1', 7, 1, 2, 432.00, 32.00, 0.00, 'completed', NULL, '2026-06-14 18:40:35', NULL),
(201, 'R1-20260614191527-4', 1, 4, NULL, 593600.00, 33600.00, 0.00, 'completed', NULL, '2026-06-14 19:15:26', NULL),
(202, 'R1-20260615170123-4', 1, 4, NULL, 593600.00, 33600.00, 0.00, 'completed', NULL, '2026-06-15 17:01:23', NULL),
(203, 'R1-20260615170159-4', 1, 4, NULL, 148400.00, 8400.00, 0.00, 'completed', NULL, '2026-06-15 17:01:59', NULL),
(204, 'R1-20260615172221-4', 1, 4, NULL, 42400.00, 2400.00, 0.00, 'completed', NULL, '2026-06-15 17:22:21', NULL),
(205, 'R1-20260615174150-4', 1, 4, NULL, 171720.00, 9720.00, 0.00, 'completed', NULL, '2026-06-15 17:41:51', NULL),
(206, 'R7-20260615184739-1', 7, 1, NULL, 324.00, 24.00, 0.00, 'completed', NULL, '2026-06-15 18:47:39', NULL),
(207, 'R1-20260615193126-4', 1, 4, NULL, 21200.00, 1200.00, 0.00, 'completed', NULL, '2026-06-15 19:31:26', NULL),
(208, 'R1-20260615193319-4', 1, 4, 2, 21200.00, 1200.00, 0.00, 'completed', NULL, '2026-06-15 19:33:20', NULL),
(209, 'R1-20260615193934-4', 1, 4, 2, 212000.00, 12000.00, 0.00, 'completed', NULL, '2026-06-15 19:39:35', NULL),
(210, 'R1-20260615195014-4', 1, 4, NULL, 1484000.00, 84000.00, 0.00, 'completed', NULL, '2026-06-15 19:50:15', NULL),
(211, 'R1-20260615195659-4', 1, 4, NULL, 212000.00, 12000.00, 0.00, 'completed', NULL, '2026-06-15 19:57:00', NULL),
(212, 'R1-20260615200210-4', 1, 4, NULL, 8480.00, 480.00, 0.00, 'cancelled', NULL, '2026-06-15 20:02:10', NULL),
(213, 'R1-20260615200722-4', 1, 4, NULL, 1908000.00, 108000.00, 0.00, 'completed', NULL, '2026-06-15 20:07:23', NULL),
(214, 'R1-20260615201028-4', 1, 4, NULL, 10600000.00, 600000.00, 0.00, 'completed', NULL, '2026-06-15 20:10:29', NULL),
(215, 'R7-20260615215247-13', 7, 13, NULL, 2700.00, 200.00, 0.00, 'completed', NULL, '2026-06-15 21:52:47', NULL),
(216, 'R7-20260615215259-13', 7, 13, NULL, 216.00, 16.00, 0.00, 'completed', NULL, '2026-06-15 21:52:59', NULL),
(217, 'R1-20260615231317-4', 1, 4, NULL, 1259280.00, 71280.00, 0.00, 'completed', NULL, '2026-06-15 23:13:17', NULL),
(218, 'R1-20260615231445-4', 1, 4, NULL, 66770.46, 3779.46, 0.00, 'completed', NULL, '2026-06-15 23:14:45', NULL),
(219, 'R1-20260616012143-1', 1, 1, NULL, 133000.00, 7560.00, 560.00, 'completed', NULL, '2026-06-16 01:21:43', NULL),
(220, 'R1-20260616012636-1', 1, 1, NULL, 848000.00, 48000.00, 0.00, 'completed', NULL, '2026-06-16 01:26:36', NULL),
(221, 'R1-20260616013048-1', 1, 1, NULL, 162180.00, 9180.00, 0.00, 'completed', NULL, '2026-06-16 01:30:48', NULL),
(222, 'R1-20260616014122-4', 1, 4, NULL, 5300.00, 300.00, 0.00, 'completed', NULL, '2026-06-16 01:41:23', NULL),
(223, 'R7-20260616020849-1', 7, 1, NULL, 864.00, 64.00, 0.00, 'completed', NULL, '2026-06-16 02:08:49', NULL),
(224, 'R1-20260616023839-4', 1, 4, NULL, 5300.00, 300.00, 0.00, 'completed', NULL, '2026-06-16 02:38:39', NULL),
(225, 'R1-20260616023851-4', 1, 4, NULL, 5300.00, 300.00, 0.00, 'completed', NULL, '2026-06-16 02:38:52', NULL),
(226, 'R1-20260616023909-4', 1, 4, NULL, 15900.00, 900.00, 0.00, 'completed', NULL, '2026-06-16 02:39:09', NULL),
(227, 'R1-20260616024005-4', 1, 4, NULL, 381600.00, 21600.00, 0.00, 'completed', NULL, '2026-06-16 02:40:05', NULL),
(228, 'R1-20260616025340-4', 1, 4, NULL, 267120.00, 15120.00, 0.00, 'cancelled', NULL, '2026-06-16 02:53:40', NULL),
(229, 'R1-20260616030902-3', 1, 3, NULL, 10600.00, 600.00, 0.00, 'completed', NULL, '2026-06-16 03:09:03', NULL),
(230, 'R1-20260616034520-4', 1, 4, NULL, 1590.00, 90.00, 0.00, 'completed', NULL, '2026-06-16 03:45:21', NULL),
(231, 'R1-20260616034900-4', 1, 4, NULL, 267120.00, 15120.00, 0.00, 'completed', NULL, '2026-06-16 03:49:01', NULL),
(232, 'R1-20260616191600-1', 1, 1, NULL, 4770000.00, 270000.00, 0.00, 'completed', NULL, '2026-06-16 19:15:58', NULL),
(233, 'R1-20260616191902-1', 1, 1, NULL, 36803200.00, 2083200.00, 0.00, 'completed', NULL, '2026-06-16 19:19:01', NULL),
(234, 'R1-20260616192733-1', 1, 1, NULL, 1441600.00, 81600.00, 0.00, 'completed', NULL, '2026-06-16 19:27:32', NULL),
(235, 'R1-20260616195147-3', 1, 3, NULL, 254400.00, 14400.00, 0.00, 'completed', NULL, '2026-06-16 19:51:46', NULL),
(236, 'R1-20260616195223-3', 1, 3, NULL, 95400.00, 5400.00, 0.00, 'completed', NULL, '2026-06-16 19:52:21', NULL),
(237, 'R1-20260616195301-3', 1, 3, NULL, 95400.00, 5400.00, 0.00, 'completed', NULL, '2026-06-16 19:52:59', NULL),
(238, 'R1-20260616195345-3', 1, 3, NULL, 424000.00, 24000.00, 0.00, 'completed', NULL, '2026-06-16 19:53:44', NULL),
(239, 'R1-20260616195536-3', 1, 3, NULL, 47700.00, 2700.00, 0.00, 'completed', NULL, '2026-06-16 19:55:35', NULL),
(240, 'R1-20260616195714-3', 1, 3, NULL, 424000.00, 24000.00, 0.00, 'cancelled', NULL, '2026-06-16 19:57:12', NULL),
(241, 'R1-20260616211401-3', 1, 3, NULL, 424000.00, 24000.00, 0.00, 'completed', NULL, '2026-06-16 21:13:59', NULL),
(242, 'R1-20260617005306-4', 1, 4, NULL, 2120000.00, 120000.00, 0.00, 'completed', NULL, '2026-06-17 00:53:07', NULL),
(243, 'R1-20260617021742-4', 1, 4, NULL, 424000.00, 24000.00, 0.00, 'completed', NULL, '2026-06-17 02:17:39', NULL),
(244, 'R1-20260617021755-4', 1, 4, NULL, 424000.00, 24000.00, 0.00, 'cancelled', NULL, '2026-06-17 02:17:53', NULL),
(245, 'R7-20260617025826-1', 7, 1, NULL, 8000.00, 627.20, 467.20, 'completed', NULL, '2026-06-17 02:58:24', NULL),
(246, 'R7-20260617032721-1', 7, 1, NULL, 216.00, 16.00, 0.00, 'completed', NULL, '2026-06-17 03:27:19', NULL),
(247, 'R1-20260617041554-3', 1, 3, NULL, 2120000.00, 120000.00, 0.00, 'completed', NULL, '2026-06-17 04:15:54', NULL),
(248, 'R1-20260617041637-3', 1, 3, NULL, 731400.00, 41400.00, 0.00, 'completed', NULL, '2026-06-17 04:16:38', NULL),
(249, 'R1-20260617041717-3', 1, 3, NULL, 16960000.00, 960000.00, 0.00, 'completed', NULL, '2026-06-17 04:17:17', NULL),
(250, 'R1-20260617042751-3', 1, 3, NULL, 1272000.00, 72000.00, 0.00, 'completed', NULL, '2026-06-17 04:27:51', NULL),
(251, 'R1-20260617042824-3', 1, 3, NULL, 1590.00, 90.00, 0.00, 'completed', NULL, '2026-06-17 04:28:24', NULL),
(252, 'R1-20260617042929-3', 1, 3, NULL, 2120.00, 120.00, 0.00, 'completed', NULL, '2026-06-17 04:29:29', NULL),
(253, 'R11-20260617090203-1', 11, 1, NULL, 10.30, 0.30, 0.00, 'completed', NULL, '2026-06-17 09:02:01', NULL),
(254, 'R11-20260617090400-1', 11, 1, NULL, 10.30, 0.30, 0.00, 'completed', NULL, '2026-06-17 09:03:58', NULL),
(255, 'R11-20260617090810-1', 11, 1, 3, 10.30, 0.30, 0.00, 'completed', NULL, '2026-06-17 09:08:08', NULL),
(256, 'R11-20260617093716-1', 11, 1, NULL, 41.20, 1.20, 0.00, 'completed', NULL, '2026-06-17 09:37:16', NULL),
(257, 'R11-20260617094634-1', 11, 1, NULL, 226.60, 6.60, 0.00, 'completed', NULL, '2026-06-17 09:46:34', NULL),
(258, 'R11-20260617095622-1', 11, 1, NULL, 15.45, 0.45, 0.00, 'completed', NULL, '2026-06-17 09:56:20', NULL),
(259, 'R11-20260617102839-1', 11, 1, NULL, 10.30, 0.30, 0.00, 'completed', NULL, '2026-06-17 10:28:39', NULL),
(260, 'R11-20260617105123-1', 11, 1, NULL, 10.30, 0.30, 0.00, 'completed', NULL, '2026-06-17 10:51:20', NULL),
(261, 'R11-20260617185957-1', 11, 1, NULL, 108.15, 3.15, 0.00, 'completed', NULL, '2026-06-17 18:59:57', NULL),
(262, 'R11-20260618022909-1', 11, 1, NULL, 1328.00, 48.00, 320.00, 'completed', NULL, '2026-06-18 02:29:09', NULL),
(263, 'R11-20260618024825-1', 11, 1, NULL, 365.65, 10.65, 0.00, 'completed', NULL, '2026-06-18 02:48:26', NULL),
(264, 'R11-20260618024938-1', 11, 1, NULL, 1076.35, 31.35, 0.00, 'completed', NULL, '2026-06-18 02:49:38', NULL),
(265, 'R11-20260618025713-1', 11, 1, NULL, 46.35, 1.35, 0.00, 'completed', NULL, '2026-06-18 02:57:13', NULL),
(266, 'R11-20260618033141-1', 11, 1, NULL, 319.30, 9.30, 0.00, 'completed', NULL, '2026-06-18 03:31:41', NULL),
(267, 'R11-20260618033238-1', 11, 1, NULL, 92.70, 2.70, 0.00, 'completed', NULL, '2026-06-18 03:32:38', NULL),
(268, 'R11-20260618035755-1', 11, 1, NULL, 15.45, 0.45, 0.00, 'completed', NULL, '2026-06-18 03:57:55', NULL),
(269, 'R11-20260618035900-1', 11, 1, NULL, 15.45, 0.45, 0.00, 'completed', NULL, '2026-06-18 03:59:01', NULL),
(270, 'R11-20260618045018-1', 11, 1, NULL, 540.75, 15.75, 0.00, 'completed', NULL, '2026-06-18 04:50:19', NULL),
(271, 'R11-20260618045450-1', 11, 1, NULL, 231.75, 6.75, 0.00, 'completed', NULL, '2026-06-18 04:54:50', NULL),
(272, 'R7-20260620231011-1', 7, 1, NULL, 6895.80, 510.80, 0.00, 'completed', NULL, '2026-06-20 23:11:04', NULL),
(273, 'R7-20260620231225-1', 7, 1, NULL, 34560.00, 2560.00, 0.00, 'completed', NULL, '2026-06-20 23:12:25', NULL),
(274, 'R7-20260620231313-1', 7, 1, NULL, 432.00, 32.00, 0.00, 'completed', NULL, '2026-06-20 23:13:13', NULL),
(275, 'R7-20260621000752-1', 7, 1, NULL, 972.00, 72.00, 0.00, 'completed', NULL, '2026-06-21 00:07:53', NULL),
(276, 'R7-20260621182845-1', 7, 1, NULL, 6372.00, 472.00, 0.00, 'completed', NULL, '2026-06-21 18:28:46', NULL),
(277, 'R1-20260628205229-1', 1, 1, NULL, 1317368.00, 74568.00, 0.00, 'completed', NULL, '2026-06-28 20:52:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `unit_price`, `subtotal`) VALUES
(1, 1, 2, 5, 7000.00, 35000.00),
(2, 2, 1, 40, 5000.00, 200000.00),
(3, 2, 2, 5, 7000.00, 35000.00),
(4, 3, 1, 5, 5000.00, 25000.00),
(5, 4, 1, 4, 5000.00, 20000.00),
(6, 5, 1, 1, 5000.00, 5000.00),
(7, 6, 1, 7, 5000.00, 35000.00),
(8, 7, 1, 1, 5000.00, 5000.00),
(9, 7, 8, 10, 1000.00, 10000.00),
(10, 8, 8, 6, 1000.00, 6000.00),
(11, 9, 9, 10, 200000.00, 2000000.00),
(12, 10, 12, 3, 5000.00, 15000.00),
(13, 11, 1, 94, 5000.00, 470000.00),
(14, 11, 12, 3, 5000.00, 15000.00),
(15, 11, 11, 3, 140000.00, 420000.00),
(16, 11, 10, 6, 18000.00, 108000.00),
(17, 11, 8, 6, 1000.00, 6000.00),
(18, 11, 9, 4, 200000.00, 800000.00),
(19, 12, 10, 4, 18000.00, 72000.00),
(20, 13, 12, 3, 5000.00, 15000.00),
(21, 14, 12, 2, 5000.00, 10000.00),
(22, 14, 9, 5, 200000.00, 1000000.00),
(23, 15, 11, 2, 140000.00, 280000.00),
(24, 15, 10, 2, 18000.00, 36000.00),
(25, 16, 11, 2, 140000.00, 280000.00),
(26, 17, 9, 5, 200000.00, 1000000.00),
(27, 18, 12, 20, 5000.00, 100000.00),
(28, 19, 9, 6, 200000.00, 1200000.00),
(29, 20, 13, 5, 2000.00, 10000.00),
(30, 21, 13, 5, 2000.00, 10000.00),
(31, 22, 13, 5, 2000.00, 10000.00),
(32, 23, 9, 131, 200000.00, 26200000.00),
(33, 24, 14, 2, 400000.00, 800000.00),
(34, 24, 10, 1, 18000.00, 18000.00),
(35, 25, 11, 2, 140000.00, 280000.00),
(36, 26, 12, 1, 5000.00, 5000.00),
(37, 26, 15, 2, 5000.00, 10000.00),
(38, 27, 11, 9, 140000.00, 1260000.00),
(39, 28, 11, 16, 140000.00, 2240000.00),
(40, 28, 10, 7, 18000.00, 126000.00),
(41, 28, 12, 2, 5000.00, 10000.00),
(42, 29, 11, 1, 140000.00, 140000.00),
(43, 29, 12, 1, 5000.00, 5000.00),
(44, 30, 11, 1, 140000.00, 140000.00),
(45, 30, 10, 1, 18000.00, 18000.00),
(46, 30, 12, 1, 5000.00, 5000.00),
(47, 30, 13, 1, 2000.00, 2000.00),
(48, 30, 15, 1, 5000.00, 5000.00),
(49, 30, 14, 1, 400000.00, 400000.00),
(50, 31, 11, 1, 140000.00, 140000.00),
(51, 31, 10, 9, 18000.00, 162000.00),
(52, 32, 11, 1, 140000.00, 140000.00),
(53, 32, 12, 3, 5000.00, 15000.00),
(54, 33, 11, 1, 140000.00, 140000.00),
(55, 34, 11, 1, 140000.00, 140000.00),
(56, 35, 14, 7, 400000.00, 2800000.00),
(57, 36, 12, 1, 5000.00, 5000.00),
(58, 37, 15, 3, 5000.00, 15000.00),
(59, 38, 13, 4, 2000.00, 8000.00),
(60, 39, 15, 4, 5000.00, 20000.00),
(61, 40, 9, 2, 200000.00, 400000.00),
(62, 40, 15, 1, 5000.00, 5000.00),
(63, 41, 16, 1, 4000.00, 4000.00),
(64, 41, 9, 1, 200000.00, 200000.00),
(65, 42, 16, 1, 4000.00, 4000.00),
(66, 43, 14, 9, 400000.00, 3600000.00),
(67, 43, 15, 1, 5000.00, 5000.00),
(68, 43, 9, 1, 200000.00, 200000.00),
(69, 44, 10, 30, 18000.00, 540000.00),
(70, 45, 10, 1, 18000.00, 18000.00),
(71, 45, 11, 1, 140000.00, 140000.00),
(72, 46, 10, 1, 18000.00, 18000.00),
(73, 46, 11, 1, 140000.00, 140000.00),
(74, 47, 11, 1, 140000.00, 140000.00),
(75, 47, 10, 1, 18000.00, 18000.00),
(76, 48, 15, 2, 5000.00, 10000.00),
(77, 49, 9, 6, 200000.00, 1200000.00),
(78, 49, 14, 9, 400000.00, 3600000.00),
(79, 50, 9, 1, 200000.00, 200000.00),
(80, 50, 16, 3, 4000.00, 12000.00),
(81, 50, 15, 8, 5000.00, 40000.00),
(82, 51, 13, 30, 2000.00, 60000.00),
(83, 52, 14, 9, 400000.00, 3600000.00),
(84, 53, 17, 1000, 5000.00, 5000000.00),
(85, 54, 17, 200, 5000.00, 1000000.00),
(86, 55, 14, 1, 400000.00, 400000.00),
(87, 55, 13, 50, 2000.00, 100000.00),
(88, 55, 9, 9, 200000.00, 1800000.00),
(89, 56, 15, 1, 5000.00, 5000.00),
(90, 57, 11, 7, 140000.00, 980000.00),
(91, 58, 16, 5, 4000.00, 20000.00),
(92, 59, 13, 2, 2000.00, 4000.00),
(93, 60, 13, 1, 2000.00, 2000.00),
(94, 60, 11, 1, 140000.00, 140000.00),
(95, 61, 14, 2, 400000.00, 800000.00),
(96, 61, 13, 3, 2000.00, 6000.00),
(97, 62, 9, 1, 200000.00, 200000.00),
(98, 62, 15, 4, 5000.00, 20000.00),
(99, 62, 16, 1, 4000.00, 4000.00),
(100, 62, 14, 1, 400000.00, 400000.00),
(101, 62, 11, 1, 140000.00, 140000.00),
(102, 62, 10, 1, 18000.00, 18000.00),
(103, 62, 13, 1, 2000.00, 2000.00),
(104, 63, 16, 9, 4000.00, 36000.00),
(105, 64, 10, 46, 18000.00, 828000.00),
(106, 65, 10, 7, 18000.00, 126000.00),
(107, 66, 15, 13, 5000.00, 65000.00),
(108, 67, 9, 30, 200000.00, 6000000.00),
(109, 68, 17, 50, 5000.00, 250000.00),
(110, 69, 24, 2, 20000.00, 40000.00),
(111, 70, 11, 1, 140000.00, 140000.00),
(112, 71, 11, 2, 140000.00, 280000.00),
(113, 72, 14, 1, 400000.00, 400000.00),
(114, 73, 13, 3, 2000.00, 6000.00),
(115, 74, 24, 8, 20000.00, 160000.00),
(116, 75, 13, 80, 2000.00, 160000.00),
(117, 75, 11, 4, 140000.00, 560000.00),
(118, 75, 16, 1, 4000.00, 4000.00),
(119, 76, 16, 1, 4000.00, 4000.00),
(120, 77, 16, 1, 4000.00, 4000.00),
(121, 78, 11, 1, 140000.00, 140000.00),
(122, 79, 11, 10, 140000.00, 1400000.00),
(123, 80, 11, 3, 140000.00, 420000.00),
(124, 81, 11, 7, 140000.00, 980000.00),
(125, 82, 11, 3, 140000.00, 420000.00),
(126, 83, 16, 100, 4000.00, 400000.00),
(127, 84, 11, 5, 140000.00, 700000.00),
(128, 85, 11, 5, 140000.00, 700000.00),
(129, 86, 11, 7, 140000.00, 980000.00),
(130, 87, 14, 1, 400000.00, 400000.00),
(131, 88, 14, 3, 400000.00, 1200000.00),
(132, 89, 14, 2, 400000.00, 800000.00),
(133, 90, 16, 7, 4000.00, 28000.00),
(134, 91, 14, 10, 400000.00, 4000000.00),
(135, 92, 13, 1, 2000.00, 2000.00),
(136, 93, 13, 2, 2000.00, 4000.00),
(137, 93, 14, 1, 400000.00, 400000.00),
(138, 94, 17, 2, 5000.00, 10000.00),
(139, 95, 13, 2, 2000.00, 4000.00),
(140, 96, 13, 2, 2000.00, 4000.00),
(141, 97, 13, 3, 2000.00, 6000.00),
(142, 98, 14, 9, 400000.00, 3600000.00),
(143, 99, 17, 3, 5000.00, 15000.00),
(144, 100, 17, 5, 5000.00, 25000.00),
(145, 101, 14, 5, 400000.00, 2000000.00),
(146, 102, 16, 20, 4000.00, 80000.00),
(147, 102, 24, 10, 20000.00, 200000.00),
(148, 102, 17, 1, 5000.00, 5000.00),
(149, 102, 14, 1, 400000.00, 400000.00),
(150, 102, 13, 1, 2000.00, 2000.00),
(151, 103, 13, 2, 2000.00, 4000.00),
(152, 103, 14, 2, 400000.00, 800000.00),
(153, 103, 24, 2, 20000.00, 40000.00),
(154, 103, 17, 2, 5000.00, 10000.00),
(155, 103, 16, 3, 4000.00, 12000.00),
(156, 104, 13, 4, 2000.00, 8000.00),
(157, 104, 14, 3, 400000.00, 1200000.00),
(158, 105, 13, 3, 2000.00, 6000.00),
(159, 105, 14, 1, 400000.00, 400000.00),
(160, 106, 13, 1, 2000.00, 2000.00),
(161, 107, 14, 3, 400000.00, 1200000.00),
(162, 107, 17, 2, 5000.00, 10000.00),
(163, 107, 16, 1, 4000.00, 4000.00),
(164, 107, 13, 1, 2000.00, 2000.00),
(165, 108, 10, 1, 18000.00, 18000.00),
(166, 109, 11, 1, 140000.00, 140000.00),
(167, 109, 13, 1, 2000.00, 2000.00),
(168, 109, 14, 1, 400000.00, 400000.00),
(169, 110, 11, 1, 140000.00, 140000.00),
(170, 110, 13, 1, 2000.00, 2000.00),
(171, 110, 14, 1, 400000.00, 400000.00),
(172, 111, 14, 1, 400000.00, 400000.00),
(173, 111, 13, 1, 2000.00, 2000.00),
(174, 111, 11, 1, 140000.00, 140000.00),
(175, 112, 14, 22, 400000.00, 8800000.00),
(176, 113, 14, 2, 400000.00, 800000.00),
(177, 114, 11, 1, 140000.00, 140000.00),
(178, 114, 13, 1, 2000.00, 2000.00),
(179, 114, 14, 1, 400000.00, 400000.00),
(180, 114, 9, 1, 200000.00, 200000.00),
(181, 114, 17, 1, 5000.00, 5000.00),
(182, 114, 16, 1, 4000.00, 4000.00),
(183, 115, 11, 1, 140000.00, 140000.00),
(184, 115, 17, 1, 5000.00, 5000.00),
(185, 116, 14, 2, 400000.00, 800000.00),
(186, 117, 10, 1, 18000.00, 18000.00),
(187, 117, 11, 1, 140000.00, 140000.00),
(188, 117, 13, 1, 2000.00, 2000.00),
(189, 117, 14, 1, 400000.00, 400000.00),
(190, 118, 24, 1, 20000.00, 20000.00),
(191, 119, 11, 4, 140000.00, 560000.00),
(192, 120, 24, 1, 20000.00, 20000.00),
(193, 121, 10, 1, 18000.00, 18000.00),
(194, 121, 11, 1, 140000.00, 140000.00),
(195, 121, 13, 1, 2000.00, 2000.00),
(196, 121, 14, 1, 400000.00, 400000.00),
(197, 121, 17, 1, 5000.00, 5000.00),
(198, 121, 16, 1, 4000.00, 4000.00),
(199, 121, 9, 9, 200000.00, 1800000.00),
(200, 122, 17, 2, 5000.00, 10000.00),
(201, 122, 14, 1, 400000.00, 400000.00),
(202, 122, 10, 3, 18000.00, 54000.00),
(203, 123, 14, 2, 400000.00, 800000.00),
(204, 124, 11, 4, 140000.00, 560000.00),
(205, 125, 25, 5, 300.00, 1500.00),
(206, 126, 13, 1, 2000.00, 2000.00),
(207, 126, 11, 1, 140000.00, 140000.00),
(208, 126, 10, 1, 18000.00, 18000.00),
(209, 126, 14, 1, 400000.00, 400000.00),
(210, 126, 25, 5, 300.00, 1500.00),
(211, 126, 9, 1, 200000.00, 200000.00),
(212, 127, 11, 1, 140000.00, 140000.00),
(213, 127, 14, 1, 400000.00, 400000.00),
(214, 128, 25, 40, 300.00, 12000.00),
(215, 129, 10, 3, 18000.00, 54000.00),
(216, 130, 24, 9, 20000.00, 180000.00),
(217, 131, 24, 1, 20000.00, 20000.00),
(218, 132, 24, 1, 20000.00, 20000.00),
(219, 133, 13, 4, 2000.00, 8000.00),
(220, 133, 9, 2, 200000.00, 400000.00),
(221, 134, 11, 1, 140000.00, 140000.00),
(222, 134, 14, 1, 400000.00, 400000.00),
(223, 134, 13, 1, 2000.00, 2000.00),
(224, 134, 16, 4, 4000.00, 16000.00),
(225, 135, 14, 1, 400000.00, 400000.00),
(226, 136, 11, 1, 140000.00, 140000.00),
(227, 137, 14, 1, 400000.00, 400000.00),
(228, 137, 11, 1, 140000.00, 140000.00),
(229, 138, 9, 4, 200000.00, 800000.00),
(230, 139, 13, 2, 2000.00, 4000.00),
(231, 140, 24, 9, 20000.00, 180000.00),
(232, 141, 10, 5, 18000.00, 90000.00),
(233, 141, 26, 500, 3000.00, 1500000.00),
(234, 142, 9, 3, 200000.00, 600000.00),
(235, 143, 24, 1, 20000.00, 20000.00),
(236, 144, 28, 23, 75000.00, 1725000.00),
(237, 145, 9, 45, 200000.00, 9000000.00),
(238, 146, 9, 26, 200000.00, 5200000.00),
(239, 146, 11, 5, 140000.00, 700000.00),
(240, 146, 10, 35, 18000.00, 630000.00),
(241, 147, 14, 1, 400000.00, 400000.00),
(242, 147, 13, 10, 2000.00, 20000.00),
(243, 147, 11, 1, 140000.00, 140000.00),
(244, 148, 28, 7, 75000.00, 525000.00),
(245, 149, 55, 20, 2500.00, 50000.00),
(246, 150, 42, 1, 12000.00, 12000.00),
(247, 150, 41, 1, 17000.00, 17000.00),
(248, 150, 47, 1, 18000.00, 18000.00),
(249, 150, 46, 1, 40000.00, 40000.00),
(250, 150, 44, 1, 28000.00, 28000.00),
(251, 150, 53, 1, 6999.00, 6999.00),
(252, 150, 9, 1, 200000.00, 200000.00),
(253, 151, 25, 5, 300.00, 1500.00),
(254, 152, 17, 2, 5000.00, 10000.00),
(255, 153, 17, 1, 5000.00, 5000.00),
(256, 154, 56, 1, 400.00, 400.00),
(257, 154, 58, 1, 200.00, 200.00),
(258, 155, 57, 8, 500.00, 4000.00),
(259, 156, 58, 2, 200.00, 400.00),
(260, 157, 57, 30, 500.00, 15000.00),
(261, 157, 59, 5, 200.00, 1000.00),
(262, 157, 56, 2, 400.00, 800.00),
(263, 158, 57, 1, 500.00, 500.00),
(264, 158, 59, 1, 200.00, 200.00),
(265, 158, 58, 1, 200.00, 200.00),
(266, 158, 56, 1, 400.00, 400.00),
(267, 159, 59, 4, 200.00, 800.00),
(268, 159, 57, 9, 500.00, 4500.00),
(269, 160, 58, 6, 200.00, 1200.00),
(270, 161, 56, 1, 400.00, 400.00),
(271, 161, 58, 1, 200.00, 200.00),
(272, 161, 57, 1, 500.00, 500.00),
(273, 161, 59, 1, 200.00, 200.00),
(274, 162, 57, 4, 500.00, 2000.00),
(275, 163, 58, 3, 200.00, 600.00),
(276, 164, 58, 3, 200.00, 600.00),
(277, 165, 56, 5, 400.00, 2000.00),
(278, 166, 60, 3, 300.00, 900.00),
(279, 167, 60, 1, 300.00, 300.00),
(280, 167, 57, 1, 500.00, 500.00),
(281, 167, 58, 1, 200.00, 200.00),
(282, 167, 59, 1, 200.00, 200.00),
(283, 168, 57, 4, 500.00, 2000.00),
(284, 169, 58, 2, 200.00, 400.00),
(285, 170, 59, 2, 200.00, 400.00),
(286, 171, 61, 1, 380.00, 380.00),
(287, 172, 61, 30, 380.00, 11400.00),
(288, 173, 62, 4, 300.00, 1200.00),
(289, 174, 65, 1, 150.00, 150.00),
(290, 174, 57, 1, 500.00, 500.00),
(291, 174, 60, 1, 300.00, 300.00),
(292, 175, 60, 1, 300.00, 300.00),
(293, 176, 61, 200, 380.00, 76000.00),
(294, 177, 62, 1, 300.00, 300.00),
(295, 178, 57, 2, 500.00, 1000.00),
(296, 179, 60, 2, 300.00, 600.00),
(297, 180, 57, 1, 500.00, 500.00),
(298, 181, 57, 2, 500.00, 1000.00),
(299, 182, 62, 1, 300.00, 300.00),
(300, 183, 63, 20, 75.00, 1500.00),
(301, 184, 62, 4, 300.00, 1200.00),
(302, 185, 57, 3, 500.00, 1500.00),
(303, 186, 57, 1, 500.00, 500.00),
(304, 187, 57, 1, 500.00, 500.00),
(305, 188, 65, 1, 150.00, 150.00),
(306, 189, 59, 1, 200.00, 200.00),
(307, 190, 60, 1, 300.00, 300.00),
(308, 190, 61, 1, 380.00, 380.00),
(309, 190, 62, 1, 300.00, 300.00),
(310, 190, 63, 1, 75.00, 75.00),
(311, 190, 65, 1, 150.00, 150.00),
(312, 190, 64, 1, 20.00, 20.00),
(313, 190, 57, 3, 500.00, 1500.00),
(314, 190, 56, 1, 400.00, 400.00),
(315, 190, 58, 3, 200.00, 600.00),
(316, 190, 59, 4, 200.00, 800.00),
(317, 191, 57, 1, 500.00, 500.00),
(318, 192, 17, 1, 5000.00, 5000.00),
(319, 193, 65, 2, 150.00, 300.00),
(320, 194, 63, 1, 75.00, 75.00),
(321, 194, 62, 1, 300.00, 300.00),
(322, 195, 60, 3, 300.00, 900.00),
(323, 195, 64, 1, 20.00, 20.00),
(324, 195, 65, 1, 150.00, 150.00),
(325, 195, 61, 1, 380.00, 380.00),
(326, 195, 62, 1, 300.00, 300.00),
(327, 195, 63, 1, 75.00, 75.00),
(328, 195, 59, 1, 200.00, 200.00),
(329, 195, 58, 1, 200.00, 200.00),
(330, 195, 57, 1, 500.00, 500.00),
(331, 195, 56, 1, 400.00, 400.00),
(332, 196, 59, 1, 200.00, 200.00),
(333, 196, 62, 1, 300.00, 300.00),
(334, 196, 57, 1, 500.00, 500.00),
(335, 197, 58, 1, 200.00, 200.00),
(336, 198, 65, 4, 150.00, 600.00),
(337, 199, 59, 1, 200.00, 200.00),
(338, 199, 58, 1, 200.00, 200.00),
(339, 200, 59, 1, 200.00, 200.00),
(340, 200, 58, 1, 200.00, 200.00),
(341, 201, 11, 1, 140000.00, 140000.00),
(342, 201, 10, 1, 18000.00, 18000.00),
(343, 201, 13, 1, 2000.00, 2000.00),
(344, 201, 14, 1, 400000.00, 400000.00),
(345, 202, 10, 1, 18000.00, 18000.00),
(346, 202, 11, 1, 140000.00, 140000.00),
(347, 202, 13, 1, 2000.00, 2000.00),
(348, 202, 14, 1, 400000.00, 400000.00),
(349, 203, 11, 1, 140000.00, 140000.00),
(350, 204, 16, 10, 4000.00, 40000.00),
(351, 205, 10, 1, 18000.00, 18000.00),
(352, 205, 11, 1, 140000.00, 140000.00),
(353, 205, 16, 1, 4000.00, 4000.00),
(354, 206, 60, 1, 300.00, 300.00),
(355, 207, 16, 5, 4000.00, 20000.00),
(356, 208, 16, 5, 4000.00, 20000.00),
(357, 209, 9, 1, 200000.00, 200000.00),
(358, 210, 9, 7, 200000.00, 1400000.00),
(359, 211, 9, 1, 200000.00, 200000.00),
(360, 212, 26, 1, 3000.00, 3000.00),
(361, 212, 29, 1, 5000.00, 5000.00),
(362, 213, 9, 9, 200000.00, 1800000.00),
(363, 214, 9, 50, 200000.00, 10000000.00),
(364, 215, 57, 5, 500.00, 2500.00),
(365, 216, 59, 1, 200.00, 200.00),
(366, 217, 42, 99, 12000.00, 1188000.00),
(367, 218, 53, 9, 6999.00, 62991.00),
(368, 219, 10, 7, 18000.00, 126000.00),
(369, 220, 14, 2, 400000.00, 800000.00),
(370, 221, 41, 9, 17000.00, 153000.00),
(371, 222, 17, 1, 5000.00, 5000.00),
(372, 223, 56, 2, 400.00, 800.00),
(373, 224, 17, 1, 5000.00, 5000.00),
(374, 225, 17, 1, 5000.00, 5000.00),
(375, 226, 17, 3, 5000.00, 15000.00),
(376, 227, 46, 9, 40000.00, 360000.00),
(377, 228, 44, 9, 28000.00, 252000.00),
(378, 229, 13, 5, 2000.00, 10000.00),
(379, 230, 25, 5, 300.00, 1500.00),
(380, 231, 44, 9, 28000.00, 252000.00),
(381, 232, 27, 1000, 4500.00, 4500000.00),
(382, 233, 14, 80, 400000.00, 32000000.00),
(383, 233, 46, 40, 40000.00, 1600000.00),
(384, 233, 44, 40, 28000.00, 1120000.00),
(385, 234, 14, 3, 400000.00, 1200000.00),
(386, 234, 46, 4, 40000.00, 160000.00),
(387, 235, 46, 6, 40000.00, 240000.00),
(388, 236, 10, 5, 18000.00, 90000.00),
(389, 237, 10, 5, 18000.00, 90000.00),
(390, 238, 14, 1, 400000.00, 400000.00),
(391, 239, 27, 10, 4500.00, 45000.00),
(392, 240, 14, 1, 400000.00, 400000.00),
(393, 241, 14, 1, 400000.00, 400000.00),
(394, 242, 14, 5, 400000.00, 2000000.00),
(395, 243, 14, 1, 400000.00, 400000.00),
(396, 244, 14, 1, 400000.00, 400000.00),
(397, 245, 61, 8, 380.00, 3040.00),
(398, 245, 62, 6, 300.00, 1800.00),
(399, 245, 56, 6, 400.00, 2400.00),
(400, 245, 58, 3, 200.00, 600.00),
(401, 246, 59, 1, 200.00, 200.00),
(402, 247, 14, 5, 400000.00, 2000000.00),
(403, 248, 26, 230, 3000.00, 690000.00),
(404, 249, 14, 40, 400000.00, 16000000.00),
(405, 250, 14, 3, 400000.00, 1200000.00),
(406, 251, 25, 5, 300.00, 1500.00),
(407, 252, 13, 1, 2000.00, 2000.00),
(408, 253, 75, 1, 10.00, 10.00),
(409, 254, 75, 1, 10.00, 10.00),
(410, 255, 75, 1, 10.00, 10.00),
(411, 256, 74, 2, 20.00, 40.00),
(412, 257, 75, 1, 10.00, 10.00),
(413, 257, 74, 1, 20.00, 20.00),
(414, 257, 73, 1, 15.00, 15.00),
(415, 257, 77, 1, 160.00, 160.00),
(416, 257, 78, 1, 15.00, 15.00),
(417, 258, 78, 1, 15.00, 15.00),
(418, 259, 75, 1, 10.00, 10.00),
(419, 260, 75, 1, 10.00, 10.00),
(420, 261, 73, 4, 15.00, 60.00),
(421, 261, 79, 1, 15.00, 15.00),
(422, 261, 80, 1, 15.00, 15.00),
(423, 261, 90, 1, 15.00, 15.00),
(424, 262, 79, 3, 15.00, 45.00),
(425, 262, 75, 82, 10.00, 820.00),
(426, 262, 74, 8, 20.00, 160.00),
(427, 262, 78, 1, 15.00, 15.00),
(428, 262, 81, 1, 15.00, 15.00),
(429, 262, 82, 1, 35.00, 35.00),
(430, 262, 77, 3, 160.00, 480.00),
(431, 262, 73, 2, 15.00, 30.00),
(432, 263, 95, 1, 15.00, 15.00),
(433, 263, 94, 1, 45.00, 45.00),
(434, 263, 93, 1, 45.00, 45.00),
(435, 263, 86, 1, 15.00, 15.00),
(436, 263, 87, 1, 15.00, 15.00),
(437, 263, 88, 2, 15.00, 30.00),
(438, 263, 90, 1, 15.00, 15.00),
(439, 263, 91, 1, 15.00, 15.00),
(440, 263, 77, 1, 160.00, 160.00),
(441, 264, 75, 1, 10.00, 10.00),
(442, 264, 74, 1, 20.00, 20.00),
(443, 264, 73, 1, 15.00, 15.00),
(444, 264, 81, 1, 15.00, 15.00),
(445, 264, 82, 1, 35.00, 35.00),
(446, 264, 83, 1, 35.00, 35.00),
(447, 264, 84, 1, 15.00, 15.00),
(448, 264, 85, 25, 15.00, 375.00),
(449, 264, 78, 3, 15.00, 45.00),
(450, 264, 87, 3, 15.00, 45.00),
(451, 264, 86, 2, 15.00, 30.00),
(452, 264, 94, 8, 45.00, 360.00),
(453, 264, 93, 1, 45.00, 45.00),
(454, 265, 88, 1, 15.00, 15.00),
(455, 265, 89, 1, 15.00, 15.00),
(456, 265, 90, 1, 15.00, 15.00),
(457, 266, 75, 1, 10.00, 10.00),
(458, 266, 79, 1, 15.00, 15.00),
(459, 266, 81, 3, 15.00, 45.00),
(460, 266, 82, 3, 35.00, 105.00),
(461, 266, 93, 3, 45.00, 135.00),
(462, 267, 73, 2, 15.00, 30.00),
(463, 267, 78, 4, 15.00, 60.00),
(464, 268, 81, 1, 15.00, 15.00),
(465, 269, 87, 1, 15.00, 15.00),
(466, 270, 90, 7, 15.00, 105.00),
(467, 270, 86, 7, 15.00, 105.00),
(468, 270, 83, 9, 35.00, 315.00),
(469, 271, 80, 9, 15.00, 135.00),
(470, 271, 81, 4, 15.00, 60.00),
(471, 271, 88, 2, 15.00, 30.00),
(472, 272, 56, 10, 400.00, 4000.00),
(473, 272, 63, 7, 75.00, 525.00),
(474, 272, 62, 1, 300.00, 300.00),
(475, 272, 61, 2, 380.00, 760.00),
(476, 272, 60, 2, 300.00, 600.00),
(477, 272, 59, 1, 200.00, 200.00),
(478, 273, 56, 80, 400.00, 32000.00),
(479, 274, 59, 2, 200.00, 400.00),
(480, 275, 60, 3, 300.00, 900.00),
(481, 276, 56, 1, 400.00, 400.00),
(482, 276, 57, 10, 500.00, 5000.00),
(483, 276, 59, 1, 200.00, 200.00),
(484, 276, 60, 1, 300.00, 300.00),
(485, 277, 9, 5, 200000.00, 1000000.00),
(486, 277, 31, 1, 15000.00, 15000.00),
(487, 277, 30, 1, 3800.00, 3800.00),
(488, 277, 44, 8, 28000.00, 224000.00);

-- --------------------------------------------------------

--
-- Table structure for table `security_audit_logs`
--

CREATE TABLE `security_audit_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `browser` varchar(120) DEFAULT NULL,
  `os_name` varchar(80) DEFAULT NULL,
  `device_type` varchar(40) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'success',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `security_audit_logs`
--

INSERT INTO `security_audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `user_agent`, `browser`, `os_name`, `device_type`, `status`, `created_at`) VALUES
(1, 1, 'logout', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'Chrome', 'Windows', 'desktop', 'success', '2026-06-17 01:58:13'),
(2, 1, 'login', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'Chrome', 'Windows', 'desktop', 'success', '2026-06-17 01:58:33'),
(3, 1, 'logout', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'Chrome', 'Windows', 'desktop', 'success', '2026-06-17 02:01:18'),
(4, 1, 'login', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'Chrome', 'Windows', 'desktop', 'success', '2026-06-17 02:01:37'),
(5, 1, 'logout', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'Chrome', 'Windows', 'desktop', 'success', '2026-06-17 02:02:50'),
(6, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-17 02:03:32'),
(7, 1, 'logout', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-17 02:03:42'),
(8, 3, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-17 02:03:55'),
(9, 4, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 02:14:37'),
(10, 4, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 02:19:07'),
(11, 4, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 02:19:12'),
(12, 4, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 02:23:54'),
(13, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 02:24:12'),
(14, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 02:25:09'),
(15, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 02:25:14'),
(16, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 02:32:05'),
(17, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 02:32:09'),
(18, 1, 'login', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'Chrome', 'Windows', 'desktop', 'success', '2026-06-17 02:38:29'),
(19, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 02:48:47'),
(20, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 02:48:54'),
(21, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 03:47:35'),
(22, 3, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 03:47:49'),
(23, 3, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 03:52:14'),
(24, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 03:52:25'),
(25, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 03:57:48'),
(26, 4, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 03:58:02'),
(27, 3, 'logout', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-17 03:59:41'),
(28, 3, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-17 03:59:57'),
(29, 4, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 04:08:56'),
(30, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 04:09:09'),
(31, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 04:27:07'),
(32, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 04:27:12'),
(33, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 04:39:08'),
(34, 3, 'logout', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-17 04:48:02'),
(35, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-17 04:48:21'),
(36, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 05:12:46'),
(37, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 05:12:51'),
(38, 1, 'logout', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-17 05:38:28'),
(39, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-17 05:38:43'),
(40, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 05:48:39'),
(41, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 06:35:59'),
(42, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 07:58:41'),
(43, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 08:10:18'),
(44, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 08:27:05'),
(45, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 09:10:06'),
(46, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 09:10:12'),
(47, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 09:24:12'),
(48, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 09:59:09'),
(49, 13, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 09:59:19'),
(50, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 10:35:50'),
(51, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 10:39:13'),
(52, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 10:39:18'),
(53, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 10:55:00'),
(54, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 10:55:09'),
(55, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-17 11:01:42'),
(56, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-17 18:11:01'),
(57, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 18:44:17'),
(58, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-17 18:52:38'),
(59, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-18 01:24:02'),
(60, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-18 02:36:44'),
(61, 1, 'logout', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-18 03:00:15'),
(62, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-18 03:00:31'),
(63, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-18 03:04:08'),
(64, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-18 03:04:22'),
(65, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-18 03:04:30'),
(66, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-18 03:59:18'),
(67, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-18 03:59:23'),
(68, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-18 04:03:40'),
(69, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-18 05:06:53'),
(70, 3, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-18 05:07:03'),
(71, 1, 'login', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'Chrome', 'Windows', 'desktop', 'success', '2026-06-18 06:25:25'),
(72, 3, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-18 06:27:40'),
(73, 3, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-18 06:29:56'),
(74, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-18 06:30:06'),
(75, 1, 'logout', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-18 06:47:17'),
(76, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-18 06:47:38'),
(77, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-18 07:52:13'),
(78, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-18 17:56:51'),
(79, 1, 'logout', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-18 18:27:47'),
(80, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-18 18:28:01'),
(81, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-18 19:57:26'),
(82, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-18 20:46:04'),
(83, 1, 'logout', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-18 21:25:02'),
(84, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-18 21:25:37'),
(85, 1, 'logout', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-18 21:25:50'),
(86, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-18 21:26:01'),
(87, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-18 22:34:30'),
(88, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-18 23:04:10'),
(89, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-19 02:34:58'),
(90, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-19 05:34:21'),
(91, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-19 05:46:25'),
(92, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-19 06:04:17'),
(93, 1, 'logout', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-19 07:55:24'),
(94, 5, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-19 07:56:10'),
(95, 5, 'logout', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-19 07:58:58'),
(96, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-19 07:59:15'),
(97, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-19 08:42:24'),
(98, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-19 08:54:16'),
(99, 1, 'logout', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-19 09:03:04'),
(100, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-19 09:03:15'),
(101, 1, 'login', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'Chrome', 'Windows', 'desktop', 'success', '2026-06-19 09:18:30'),
(102, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-19 09:21:04'),
(103, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-19 09:29:21'),
(104, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-19 09:44:18'),
(105, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-19 17:45:25'),
(106, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-20 07:46:54'),
(107, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-20 08:47:07'),
(108, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-20 09:29:31'),
(109, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-20 09:30:02'),
(110, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-20 09:39:27'),
(111, 4, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-20 09:39:37'),
(112, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-20 09:43:20'),
(113, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-20 17:05:00'),
(114, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-20 23:08:36'),
(115, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-20 23:14:16'),
(116, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-21 00:05:57'),
(117, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-21 00:47:33'),
(118, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-21 03:38:42'),
(119, 1, 'logout', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-21 04:26:14'),
(120, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-21 04:26:23'),
(121, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'Firefox', 'Windows', 'desktop', 'success', '2026-06-21 06:51:59'),
(122, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-21 17:48:41'),
(123, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-21 17:52:49'),
(124, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-21 17:54:23'),
(125, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-21 18:20:33'),
(126, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-21 18:31:40'),
(127, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-21 18:31:49'),
(128, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-21 18:32:38'),
(129, NULL, 'login_failed', 'email', NULL, '{\"email\":\"super_admin@colistrak.com\"}', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'failed', '2026-06-23 17:34:14'),
(130, NULL, 'login_failed', 'email', NULL, '{\"email\":\"super_admin@colistrak.com\"}', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'failed', '2026-06-23 18:55:23'),
(131, NULL, 'login_failed', 'email', NULL, '{\"email\":\"super_admin@colistrak.com\"}', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'failed', '2026-06-23 18:55:29'),
(132, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-23 18:55:43'),
(133, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-23 22:54:54'),
(134, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-24 03:03:29'),
(135, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-24 04:21:46'),
(136, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 02:42:07'),
(137, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 02:45:23'),
(138, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 03:14:26'),
(139, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 03:14:35'),
(140, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 03:14:57'),
(141, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 03:15:27'),
(142, 1, 'login', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 20:19:09'),
(143, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 20:38:15'),
(144, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 20:46:28'),
(145, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 20:48:32'),
(146, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 20:48:39'),
(147, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 20:48:56'),
(148, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 20:55:33'),
(149, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 20:55:49'),
(150, 1, 'logout', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 20:56:03'),
(151, 1, 'login', NULL, NULL, NULL, '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'success', '2026-06-28 20:56:14'),
(152, NULL, 'login_failed', 'email', NULL, '{\"email\":\"super_admin@colistrak.com\"}', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'Safari', 'macOS', 'mobile', 'failed', '2026-06-28 22:15:32');

-- --------------------------------------------------------

--
-- Table structure for table `sms_templates`
--

CREATE TABLE `sms_templates` (
  `id` bigint(20) NOT NULL,
  `slug` varchar(128) NOT NULL,
  `language` varchar(8) NOT NULL,
  `body` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `from_store_id` int(11) NOT NULL,
  `to_store_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `from_store_id`, `to_store_id`, `product_id`, `quantity`, `status`, `created_at`) VALUES
(1, 1, 5, 9, 6, 'pending', '2026-05-23 02:44:10'),
(2, 7, 6, 60, 92, 'accepted', '2026-06-15 18:37:10'),
(3, 7, 6, 57, 50, 'accepted', '2026-06-15 23:03:49'),
(4, 1, 7, 13, 9, 'accepted', '2026-06-28 21:03:30');

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE `stores` (
  `id` int(11) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'FCFA',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stores`
--

INSERT INTO `stores` (`id`, `code`, `name`, `location`, `email`, `phone`, `tax_rate`, `currency`, `created_at`, `deleted_at`, `is_active`, `updated_at`) VALUES
(1, 'Côté d\'Ivoire-B1', 'Boutique ABOBO', 'Abidjan, côté d\'Ivoire', 'boutiqueabobo@pos.com', '+2250748640781', 6.00, 'FCFA', '2026-05-10 04:02:39', NULL, 1, '2026-06-04 22:34:45'),
(3, 'Togo-B3', 'Boutique Zoro Bar', 'Lomé,Togo', NULL, '0599944823', 18.00, 'FCFA', '2026-05-22 23:30:58', NULL, 1, '2026-05-24 02:54:04'),
(4, 'Côté d\'Ivoire-B3', 'Boutique Angré', 'Abidjan,côté d\'Ivoire.', NULL, '0599944826', 23.00, '€ Euro', '2026-05-22 23:31:48', NULL, 1, '2026-06-03 21:27:05'),
(5, 'Côté d\'Ivoire-B2', 'Boutique Adjamé', 'Abidjan,côté d\'Ivoire.', 'Camp@gmail.com', '2250748640726', 18.00, 'FCFA', '2026-05-22 23:34:59', NULL, 1, '2026-06-04 23:02:08'),
(6, 'Niger-B1', 'Boutique Dar salam', 'Niamey,Niger', NULL, '97482474', 18.00, 'XOF', '2026-05-22 23:40:48', NULL, 1, '2026-06-04 23:03:49'),
(7, 'Ghana -B1', 'Appel Store', 'Accra,Ghana', 'applestore227@pos.com', '0599944826', 8.00, 'GHS', '2026-05-22 23:48:55', NULL, 1, '2026-06-09 02:20:55'),
(8, 'Togo-B1', 'Boutique Kodjoviakopé', 'Lomé,Togo', NULL, '0599944826', 18.00, 'FCFA', '2026-05-22 23:54:04', NULL, 1, '2026-05-23 02:39:35'),
(9, 'Niger-B3', 'Boutique Niamey 2000', 'Niamey,Niger', NULL, '0599944826', 18.00, 'FCFA', '2026-05-22 23:57:14', NULL, 1, '2026-05-24 02:57:54'),
(10, 'Ghana-B2', 'Boutique legon', 'Accra,Ghana', NULL, NULL, 18.00, '$ USD', '2026-05-23 02:42:18', NULL, 1, '2026-06-04 22:36:15'),
(11, 'Ghana-R1', 'Restaurant BBQ', 'Alajo,Accra.', 'bbq@pos.com', '0599944833', 3.00, 'GHS', '2026-06-17 05:18:14', NULL, 1, NULL),
(12, 'Ghana B2', 'Chic Cosmétique', 'Lapaz Ecobank', 'superadmin@pos.com', '0599944888', 4.00, 'GHS', '2026-06-28 02:48:48', NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `store_sync_status`
--

CREATE TABLE `store_sync_status` (
  `store_id` int(11) NOT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT 1,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `pending_local_count` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_sync_status`
--

INSERT INTO `store_sync_status` (`store_id`, `is_online`, `last_seen_at`, `last_sync_at`, `pending_local_count`, `updated_at`) VALUES
(1, 1, '2026-06-28 20:55:28', NULL, 0, '2026-06-28 20:55:28'),
(4, 0, '2026-06-16 03:18:22', NULL, 0, '2026-06-16 03:24:03'),
(5, 0, '2026-06-16 03:17:05', NULL, 0, '2026-06-16 03:23:04'),
(6, 0, '2026-06-16 03:19:51', NULL, 0, '2026-06-16 03:25:03'),
(7, 1, '2026-06-21 18:30:11', NULL, 0, '2026-06-21 18:30:11'),
(11, 1, '2026-06-21 18:31:25', NULL, 0, '2026-06-21 18:31:25'),
(12, 1, '2026-06-28 21:47:31', NULL, 0, '2026-06-28 21:47:31');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `synchronization_queue`
--

CREATE TABLE `synchronization_queue` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `status` enum('pending','synced','failed','conflict') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `error_message` text DEFAULT NULL,
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `local_uuid` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `key` varchar(128) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES
(1, 'default_language', 'en', '2026-06-16 07:05:22', '2026-06-16 07:05:22');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `first_name` varchar(120) DEFAULT NULL,
  `last_name` varchar(120) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(255) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `department` varchar(120) DEFAULT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `pin_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `remember_token` varchar(100) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `last_activity` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `language` varchar(8) DEFAULT NULL,
  `timezone` varchar(64) DEFAULT 'UTC',
  `status` enum('active','inactive','locked') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_id`, `name`, `full_name`, `first_name`, `last_name`, `email`, `phone`, `address`, `emergency_contact`, `avatar_path`, `department`, `supervisor_id`, `password_hash`, `pin_hash`, `role_id`, `store_id`, `branch_id`, `warehouse_id`, `is_active`, `remember_token`, `email_verified_at`, `last_login`, `failed_login_attempts`, `locked_until`, `last_activity`, `created_at`, `updated_at`, `deleted_at`, `language`, `timezone`, `status`) VALUES
(1, NULL, 'Faycal Sam', 'Faycal Sam', NULL, NULL, 'superadmin@pos.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$Si3lRYUyeaIpmx6cW464wu83u.BgTvUB/iSECA2XU3UDN8B6jzJwa', '$2y$10$ChaJT9DewDAyIgIlly6Oie1FrfI9BaRUKAO2JDUU/4t6.j6Jgmzw2', 1, NULL, NULL, NULL, 1, NULL, NULL, '2026-06-28 20:56:14', 0, NULL, '2026-06-28 20:56:14', '2026-05-10 04:02:40', '2026-06-28 20:56:14', NULL, NULL, 'UTC', 'active'),
(2, NULL, 'LT SAM', 'LT SAM', NULL, NULL, 'admin@pos.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$Si3lRYUyeaIpmx6cW464wu83u.BgTvUB/iSECA2XU3UDN8B6jzJwa', '$2y$10$ChaJT9DewDAyIgIlly6Oie1FrfI9BaRUKAO2JDUU/4t6.j6Jgmzw2', 2, 1, 1, NULL, 1, NULL, NULL, '2026-06-09 20:38:11', 0, NULL, NULL, '2026-05-10 04:02:40', '2026-06-17 01:52:05', NULL, NULL, 'UTC', 'active'),
(3, NULL, 'Lt Chaoulani Manager', 'Lt Chaoulani Manager', NULL, NULL, 'manager@pos.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$Si3lRYUyeaIpmx6cW464wu83u.BgTvUB/iSECA2XU3UDN8B6jzJwa', '$2y$10$ChaJT9DewDAyIgIlly6Oie1FrfI9BaRUKAO2JDUU/4t6.j6Jgmzw2', 3, 1, 1, NULL, 1, NULL, NULL, '2026-06-18 06:27:40', 0, NULL, '2026-06-18 06:27:40', '2026-05-10 04:02:40', '2026-06-18 06:27:40', NULL, NULL, 'UTC', 'active'),
(4, NULL, 'Moctar Chaoulani', 'Moctar Chaoulani', NULL, NULL, 'cashier@pos.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$GDd7MmQuTptgc9UGkie7lu6T/7PtVS.SmPof8aKM9skA3gTkcS1de', '$2y$10$ChaJT9DewDAyIgIlly6Oie1FrfI9BaRUKAO2JDUU/4t6.j6Jgmzw2', 4, 1, 1, NULL, 1, NULL, NULL, '2026-06-20 09:39:37', 0, NULL, '2026-06-20 09:39:37', '2026-05-10 04:02:40', '2026-06-20 09:39:37', NULL, NULL, 'UTC', 'active'),
(5, NULL, 'Staff Demo', 'Staff Demo', NULL, NULL, 'staff@pos.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$Si3lRYUyeaIpmx6cW464wu83u.BgTvUB/iSECA2XU3UDN8B6jzJwa', '$2y$10$ChaJT9DewDAyIgIlly6Oie1FrfI9BaRUKAO2JDUU/4t6.j6Jgmzw2', 5, 1, 1, NULL, 1, NULL, NULL, '2026-06-19 07:56:10', 0, NULL, '2026-06-19 07:56:10', '2026-05-10 04:02:40', '2026-06-19 07:56:10', NULL, NULL, 'UTC', 'active'),
(6, NULL, 'Mahadi SAM', 'Mahadi SAM', NULL, NULL, 'mahadibusinessglobal227@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 'password123', '', 3, 1, 1, NULL, 1, NULL, NULL, NULL, 0, NULL, NULL, '2026-05-10 05:08:43', '2026-06-17 01:52:05', NULL, NULL, 'UTC', 'active'),
(7, NULL, 'Ibou Sam', 'Ibou Sam', NULL, NULL, 'ibou@pos.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$f1L3mrMSd0i65bGNVD0aKe6xP/47ecKsyq.jvajHDPkViTW5OlYIy', '$2y$10$h.nGlnJdr1psauwQOoEdZuBLFjOieKvaevol.0RL9In4JZV2fWwBS', 4, 6, 6, NULL, 1, NULL, NULL, '2026-06-04 21:58:26', 0, NULL, NULL, '2026-05-24 03:03:43', '2026-06-17 01:52:05', NULL, NULL, 'UTC', 'active'),
(8, NULL, 'Mami Sam', 'Mami Sam', NULL, NULL, 'mami@pos.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$/PUVeFa9TqblnJaAHiY3jesVnQSRZ6tgt6OWoE14NzyhOnectKETO', '$2y$10$bEDQPsbOGt/WILze1jm6zeOI6gCszjkjFbUOQFse8ota/JYMm9AB.', 2, 6, 6, NULL, 1, NULL, NULL, '2026-06-03 08:02:10', 0, NULL, NULL, '2026-05-24 12:18:16', '2026-06-17 01:52:05', NULL, NULL, 'UTC', 'active'),
(9, NULL, 'Desmond', 'Desmond', NULL, NULL, 'desmond@pos.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$oKBhriikQbbFjR6ct83cVeYWNLctcyta2Q.OlQhfHhULHIh0McxtK', '$2y$10$aDIihg9VDJ.a82YNeoEv9OapkWmDXmi4B0nY0MVClHKCwuR8MeXZm', 4, 7, 7, NULL, 1, NULL, NULL, '2026-06-09 19:36:35', 0, NULL, NULL, '2026-06-09 03:45:19', '2026-06-17 01:52:05', NULL, NULL, 'UTC', 'active'),
(10, NULL, 'Souleymane', 'Souleymane', NULL, NULL, 'applestore@pos.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$nnTAB.ZVv6guqMb1iWNe.uB5a2E4i7kmpbsWORpUMdEnAqLV3vXYS', '$2y$10$3vKTkY.WdQykqc2/9Bvo/uO4JECosmz7B8yr2z2Y3/e5oaOmw9tOa', 2, 7, 7, NULL, 1, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-09 03:48:22', '2026-06-17 01:52:05', NULL, NULL, 'UTC', 'active'),
(11, NULL, 'Siatta', 'Siatta', NULL, NULL, 'siatta@pos.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$h31qSRFHQM292lWLpDlGH.GWfRWVXc24EDI30ltMB2B5CRjXNBU4e', '$2y$10$ZWB6YLNhDinJ2YFyKPwUdeTUK6V8tXDs6RBxCUJQ5l57LTWllR.6.', 3, 7, 7, NULL, 1, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-09 03:51:00', '2026-06-17 01:52:05', NULL, NULL, 'UTC', 'active'),
(12, NULL, 'Karim', 'Karim', NULL, NULL, 'karim@pos.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$2Id5f7/yIuSBbev6WoCn.uLeVQlCw9Kd4UjARPxuiKZ98IYGYydwK', '$2y$10$L9hBWHI0hBGI9nwaPaurbev76XrsRIuC/FPxAQD8VZPLDpBGKQCWK', 4, 7, 7, NULL, 1, NULL, NULL, '2026-06-09 21:27:33', 0, NULL, NULL, '2026-06-09 03:52:05', '2026-06-17 01:52:05', NULL, NULL, 'UTC', 'active'),
(13, NULL, 'Ahmed', 'Ahmed', NULL, NULL, 'ahmed@pos.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$6395IwkaQgedARl.peENauHF2R0O/RJd1pgMCbL0BRUR0dw5mgUKO', '$2y$10$EmnH.ICG4ut7ip/EeE82P.uuVcKMcWuoHwxwd0BnW6mn6JBYGH5Z6', 2, 7, 7, NULL, 1, NULL, NULL, '2026-06-17 09:59:19', 0, NULL, '2026-06-17 09:59:19', '2026-06-09 21:14:52', '2026-06-17 09:59:19', NULL, NULL, 'UTC', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `user_activity_logs`
--

CREATE TABLE `user_activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'success',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_activity_logs`
--

INSERT INTO `user_activity_logs` (`id`, `user_id`, `action`, `ip_address`, `user_agent`, `status`, `created_at`) VALUES
(1, 1, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 04:03:40'),
(2, 1, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 04:08:02'),
(3, 1, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 04:11:14'),
(4, 1, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 04:13:02'),
(5, 1, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 04:13:40'),
(6, 2, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 04:17:07'),
(7, 3, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 04:18:37'),
(8, 3, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'failed', '2026-05-10 04:19:01'),
(9, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 04:19:27'),
(10, 5, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 04:20:14'),
(11, 1, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 04:21:17'),
(12, 1, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 04:36:19'),
(13, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 05:13:55'),
(14, 4, 'login_attempt', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-10 05:20:04'),
(15, 3, 'login_attempt', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-10 05:29:33'),
(16, 1, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 05:35:19'),
(17, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 05:44:53'),
(18, 1, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 05:47:32'),
(19, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 05:57:19'),
(20, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-05-10 07:04:13'),
(21, 3, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 07:08:21'),
(22, 5, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 07:09:21'),
(23, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 07:31:17'),
(24, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 07:34:30'),
(25, 4, 'login_attempt', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-10 07:54:57'),
(26, 1, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 23:24:50'),
(27, 2, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-10 23:27:13'),
(28, 2, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-13 12:59:04'),
(29, 3, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-13 13:19:07'),
(30, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-13 13:20:16'),
(31, 2, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-17 15:17:39'),
(32, 3, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-17 15:22:42'),
(33, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-17 15:23:05'),
(34, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-17 16:23:23'),
(35, 1, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-17 17:52:05'),
(36, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-17 17:53:21'),
(37, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-18 11:08:28'),
(38, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-18 11:42:00'),
(39, 2, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-18 17:39:12'),
(40, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-18 23:23:22'),
(41, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-18 23:53:01'),
(42, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-18 23:54:29'),
(43, 3, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-18 23:56:40'),
(44, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-18 23:56:54'),
(45, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-18 23:57:46'),
(46, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-19 00:28:03'),
(47, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-19 21:51:32'),
(48, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-20 09:56:49'),
(49, 2, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-22 15:11:03'),
(50, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-22 15:20:01'),
(51, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-22 17:28:13'),
(52, 2, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-22 18:35:46'),
(53, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-22 18:36:49'),
(54, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-22 19:43:49'),
(55, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-22 21:58:41'),
(56, 2, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-22 22:03:23'),
(57, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-22 22:06:38'),
(58, 2, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-22 22:10:22'),
(59, 2, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-22 22:21:10'),
(60, 2, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-22 22:50:44'),
(61, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 00:09:05'),
(62, 2, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 00:15:53'),
(63, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 00:28:54'),
(64, 2, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 00:39:36'),
(65, 4, 'login_attempt', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-23 00:48:28'),
(66, 1, 'login_attempt', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-23 00:56:03'),
(67, 1, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 01:15:56'),
(68, 1, 'role_permissions_updated_user_4', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 02:11:10'),
(69, 1, 'role_permissions_updated_user_2', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 02:11:21'),
(70, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 02:56:38'),
(71, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 03:03:15'),
(72, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 03:03:43'),
(73, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 04:52:58'),
(74, 6, 'login_failed', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-23 05:11:04'),
(75, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-23 05:11:41'),
(76, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-23 05:40:50'),
(77, 2, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-23 05:45:12'),
(78, 1, 'login_failed', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-23 05:48:31'),
(79, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-23 05:48:40'),
(80, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-23 05:53:26'),
(81, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 15:01:36'),
(82, 1, 'user_updated_user_6', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 15:10:25'),
(83, 1, 'user_updated_user_2', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 15:11:08'),
(84, 1, 'user_suspended_user_4', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 15:11:50'),
(85, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 15:12:42'),
(86, 1, 'user_activated_user_4', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 15:12:54'),
(87, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 15:19:28'),
(88, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 16:15:54'),
(89, 1, 'user_updated_user_3', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 16:18:15'),
(90, 5, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'success', '2026-05-23 16:18:46'),
(91, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-23 16:25:03'),
(92, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-23 19:02:23'),
(93, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-23 20:15:57'),
(94, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-23 20:17:17'),
(95, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-23 22:36:21'),
(96, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-05-23 23:21:41'),
(97, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-05-24 00:29:44'),
(98, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 01:03:17'),
(99, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 01:04:38'),
(100, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 01:05:29'),
(101, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-24 01:08:08'),
(102, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 02:28:39'),
(103, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 02:43:26'),
(104, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-05-24 02:45:17'),
(105, 1, 'user_created_user_7', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 03:03:43'),
(106, 1, 'user_updated_user_7', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 03:04:03'),
(107, 7, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 03:04:15'),
(108, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 03:18:30'),
(109, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 12:07:07'),
(110, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 12:11:54'),
(111, 1, 'user_created_user_8', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 12:18:16'),
(112, 8, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 12:18:48'),
(113, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 12:24:40'),
(114, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 12:28:44'),
(115, 4, 'login_failed', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-24 12:57:34'),
(116, 4, 'login_failed', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-24 12:57:42'),
(117, 4, 'login_failed', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-24 13:01:28'),
(118, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 13:02:42'),
(119, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-24 13:03:25'),
(120, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 15:04:33'),
(121, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 15:11:48'),
(122, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 17:20:41'),
(123, 7, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 17:23:46'),
(124, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-24 17:36:44'),
(125, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 00:24:21'),
(126, 2, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 00:45:50'),
(127, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 00:46:16'),
(128, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 12:23:16'),
(129, 2, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 12:24:19'),
(130, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 22:06:17'),
(131, 4, 'login_failed', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-25 22:19:02'),
(132, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-25 22:19:08'),
(133, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 22:24:52'),
(134, 6, 'login_failed', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-25 23:02:18'),
(135, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-25 23:03:24'),
(136, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 23:05:39'),
(137, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-25 23:09:30'),
(138, 6, 'login_failed', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'failed', '2026-05-25 23:12:34'),
(139, 3, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-25 23:13:25'),
(140, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-25 23:16:10'),
(141, 2, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-26 00:59:49'),
(142, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-05-26 01:07:46'),
(143, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-26 01:47:42'),
(144, 2, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-26 01:48:35'),
(145, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-05-26 02:14:10'),
(146, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-26 20:38:15'),
(147, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-05-26 21:09:31'),
(148, 2, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-26 21:10:21'),
(149, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-26 21:11:26'),
(150, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-27 00:02:02'),
(151, 2, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-27 00:04:49'),
(152, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-27 01:14:40'),
(153, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-29 15:52:10'),
(154, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-29 16:00:03'),
(155, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-29 16:06:34'),
(156, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-29 16:07:02'),
(157, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-29 16:16:32'),
(158, 5, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-29 16:18:35'),
(159, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-05-30 22:28:58'),
(160, 4, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-30 23:51:12'),
(161, 1, 'login_attempt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-30 23:54:04'),
(162, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-05-30 23:55:26'),
(163, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-01 00:30:14'),
(164, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-02 02:15:44'),
(165, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-02 02:23:42'),
(166, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-02 02:26:40'),
(167, 4, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'failed', '2026-06-02 02:35:22'),
(168, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-02 02:35:37'),
(169, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-02 02:52:04'),
(170, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-03 01:59:07'),
(171, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 02:01:02'),
(172, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 02:13:56'),
(173, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 04:02:41'),
(174, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 04:16:58'),
(175, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-03 04:45:10'),
(176, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 05:07:06'),
(177, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 05:20:53'),
(178, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-03 06:07:29'),
(179, 1, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-03 06:26:16'),
(180, 1, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-03 07:16:45'),
(181, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-03 07:21:37'),
(182, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 07:34:37'),
(183, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 07:58:11'),
(184, 8, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 08:02:10'),
(185, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 08:03:40'),
(186, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 08:04:23'),
(187, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-03 08:04:43'),
(188, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 14:19:31'),
(189, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 15:36:01'),
(190, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 15:41:13'),
(191, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 16:14:25'),
(192, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 19:56:03'),
(193, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-03 20:53:30'),
(194, 1, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-03 20:55:37'),
(195, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-03 22:09:52'),
(196, 1, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-03 22:11:14'),
(197, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 15:05:46'),
(198, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 15:23:58'),
(199, 1, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-04 15:24:42'),
(200, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 15:50:00'),
(201, 1, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-04 19:52:45'),
(202, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 19:53:40'),
(203, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 19:57:01'),
(204, 2, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 20:10:41'),
(205, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-04 21:27:14'),
(206, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-04 21:50:46'),
(207, 7, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 21:58:26'),
(208, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-04 22:26:25'),
(209, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-04 23:01:16'),
(210, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-05 01:29:50'),
(211, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-05 02:21:13'),
(212, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-05 02:36:32'),
(213, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-05 02:55:19'),
(214, 2, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-05 02:59:31'),
(215, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-05 03:13:22'),
(216, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-05 03:28:20'),
(217, 1, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-05 15:37:51'),
(218, 1, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '2026-06-05 20:22:10'),
(219, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-05 21:23:48'),
(220, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-06 04:20:41'),
(221, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-06 16:41:34'),
(222, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-06 17:13:43'),
(223, 2, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-06 17:15:26'),
(224, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-06 17:20:26'),
(225, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-06 19:22:06'),
(226, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 01:34:04'),
(227, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 02:18:51'),
(228, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-09 02:45:23'),
(229, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-09 03:31:07'),
(230, 1, 'user_created_user_9', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-09 03:45:19'),
(231, 1, 'user_created_user_10', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-09 03:48:22'),
(232, 1, 'user_updated_user_10', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-09 03:49:10'),
(233, 1, 'user_created_user_11', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-09 03:51:00'),
(234, 1, 'user_created_user_12', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-09 03:52:05'),
(235, 9, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-09 03:53:32'),
(236, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 16:41:06'),
(237, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 18:40:29'),
(238, 9, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-09 18:48:10'),
(239, 9, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-09 19:36:35'),
(240, 2, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 20:38:11'),
(241, 12, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 20:39:43'),
(242, 12, 'login_success', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-09 20:55:06'),
(243, 1, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-09 20:59:09'),
(244, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-09 21:15:44'),
(245, 1, 'user_updated_user_13', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-09 21:16:39'),
(246, 1, 'user_activated_user_13', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-09 21:16:55'),
(247, 1, 'user_updated_user_13', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-09 21:17:17'),
(248, 13, 'login_success', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-09 21:17:45'),
(249, 13, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-09 21:24:20'),
(250, 1, 'user_updated_user_13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-09 21:25:52'),
(251, 12, 'login_success', '172.20.10.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Safari/605.1.15', 'success', '2026-06-09 21:27:33'),
(252, 13, 'login_success', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-09 21:53:20'),
(253, 13, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-09 21:57:12'),
(254, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 23:23:18'),
(255, 13, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 23:24:55'),
(256, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-09 23:37:54'),
(257, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-10 00:38:52'),
(258, 13, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-10 00:41:41'),
(259, 1, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-10 00:55:07'),
(260, 13, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-10 00:57:54'),
(261, 13, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-10 01:02:40'),
(262, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-10 23:34:11'),
(263, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-11 01:16:44'),
(264, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-11 03:07:31'),
(265, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-11 03:25:08'),
(266, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-12 01:56:34'),
(267, 1, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'failed', '2026-06-14 18:35:28'),
(268, 1, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-14 18:35:38'),
(269, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-14 19:08:18'),
(270, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-14 19:20:13'),
(271, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-15 16:54:51'),
(272, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-15 16:58:47'),
(273, 1, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-15 17:50:37'),
(274, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-15 17:57:47'),
(275, 1, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-15 18:47:05'),
(276, 1, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-15 19:02:21'),
(277, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-15 19:26:34'),
(278, 4, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-15 20:42:17'),
(279, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-15 20:42:47'),
(280, 4, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-15 21:08:37'),
(281, 13, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-15 21:09:30'),
(282, 13, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-15 21:54:34'),
(283, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-15 21:55:22'),
(284, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-15 23:11:34'),
(285, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 00:30:36'),
(286, 13, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 00:30:48'),
(287, 13, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 00:39:28'),
(288, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 00:39:37'),
(289, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 01:02:32'),
(290, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 01:04:34'),
(291, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 01:10:11'),
(292, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 01:42:42'),
(293, 1, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 01:56:50');
INSERT INTO `user_activity_logs` (`id`, `user_id`, `action`, `ip_address`, `user_agent`, `status`, `created_at`) VALUES
(294, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 01:57:03'),
(295, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 02:05:49'),
(296, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 02:06:05'),
(297, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 02:11:00'),
(298, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 02:11:14'),
(299, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 02:37:41'),
(300, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 02:37:54'),
(301, 4, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 02:56:55'),
(302, 3, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 02:57:12'),
(303, 3, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 02:59:41'),
(304, 3, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 02:59:48'),
(305, 1, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', 'success', '2026-06-16 03:12:10'),
(306, 3, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 03:13:44'),
(307, 13, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 03:13:56'),
(308, 13, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 03:23:26'),
(309, 4, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 03:23:36'),
(310, 3, 'logout', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 05:43:53'),
(311, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 05:44:07'),
(312, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 06:35:58'),
(313, 13, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-16 08:22:19'),
(314, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 16:58:19'),
(315, 13, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 17:14:02'),
(316, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 18:02:32'),
(317, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 18:17:09'),
(318, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 18:39:57'),
(319, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 18:55:16'),
(320, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 18:55:24'),
(321, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 19:01:12'),
(322, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 19:01:23'),
(323, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 19:03:57'),
(324, 1, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 19:04:07'),
(325, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 19:43:15'),
(326, 3, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 19:43:28'),
(327, 1, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 19:46:59'),
(328, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 19:48:26'),
(329, 3, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 20:06:05'),
(330, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 20:06:21'),
(331, 3, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 21:10:38'),
(332, 3, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 21:14:24'),
(333, 3, 'login_success', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-16 21:14:30'),
(334, 3, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 21:19:55'),
(335, 3, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 21:22:14'),
(336, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-16 21:22:27'),
(337, 4, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 00:34:47'),
(338, 4, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 01:07:26'),
(339, 1, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 01:07:47'),
(340, 1, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-17 01:45:45'),
(341, 1, 'logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-17 01:58:13'),
(342, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-17 01:58:33'),
(343, 1, 'logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-17 02:01:18'),
(344, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-17 02:01:37'),
(345, 1, 'logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-17 02:02:50'),
(346, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 02:03:32'),
(347, 1, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 02:03:42'),
(348, 3, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 02:03:55'),
(349, 4, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:14:37'),
(350, 4, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:19:07'),
(351, 4, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:19:12'),
(352, 4, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:23:54'),
(353, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:24:12'),
(354, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:25:09'),
(355, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:25:14'),
(356, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:32:05'),
(357, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:32:09'),
(358, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-17 02:38:29'),
(359, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:48:47'),
(360, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 02:48:54'),
(361, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 03:47:36'),
(362, 3, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 03:47:49'),
(363, 3, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 03:52:14'),
(364, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 03:52:25'),
(365, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 03:57:48'),
(366, 4, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 03:58:02'),
(367, 3, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 03:59:41'),
(368, 3, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 03:59:57'),
(369, 4, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 04:08:56'),
(370, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 04:09:09'),
(371, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 04:27:07'),
(372, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 04:27:12'),
(373, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 04:39:08'),
(374, 3, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 04:48:02'),
(375, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 04:48:21'),
(376, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 05:12:46'),
(377, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 05:12:51'),
(378, 1, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 05:38:28'),
(379, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 05:38:43'),
(380, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 05:48:39'),
(381, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 06:35:59'),
(382, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 07:58:41'),
(383, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 08:10:18'),
(384, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 08:27:06'),
(385, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 09:10:06'),
(386, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 09:10:12'),
(387, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 09:24:12'),
(388, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 09:59:09'),
(389, 13, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 09:59:19'),
(390, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 10:35:50'),
(391, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 10:39:13'),
(392, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 10:39:18'),
(393, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 10:55:00'),
(394, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 10:55:09'),
(395, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 11:01:42'),
(396, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-17 18:11:01'),
(397, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 18:44:17'),
(398, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-17 18:52:38'),
(399, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 01:24:02'),
(400, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 02:36:44'),
(401, 1, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 03:00:15'),
(402, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 03:00:31'),
(403, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 03:04:08'),
(404, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 03:04:22'),
(405, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 03:04:30'),
(406, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 03:59:18'),
(407, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 03:59:23'),
(408, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 04:03:40'),
(409, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 05:06:53'),
(410, 3, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 05:07:03'),
(411, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-18 06:25:25'),
(412, 3, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 06:27:40'),
(413, 3, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 06:29:56'),
(414, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 06:30:06'),
(415, 1, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 06:47:17'),
(416, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 06:47:38'),
(417, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 07:52:13'),
(418, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 17:56:51'),
(419, 1, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 18:27:47'),
(420, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 18:28:01'),
(421, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 19:57:26'),
(422, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 20:46:04'),
(423, 1, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 21:25:02'),
(424, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 21:25:37'),
(425, 1, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 21:25:50'),
(426, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 21:26:01'),
(427, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-18 22:34:30'),
(428, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-18 23:04:10'),
(429, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 02:34:58'),
(430, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-19 05:34:21'),
(431, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-19 05:46:25'),
(432, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 06:04:17'),
(433, 1, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 07:55:24'),
(434, 5, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 07:56:10'),
(435, 5, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 07:58:58'),
(436, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 07:59:15'),
(437, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-19 08:42:24'),
(438, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 08:54:16'),
(439, 1, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 09:03:04'),
(440, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 09:03:15'),
(441, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'success', '2026-06-19 09:18:30'),
(442, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-19 09:21:04'),
(443, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-19 09:29:21'),
(444, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-19 09:44:18'),
(445, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-19 17:45:25'),
(446, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-20 07:46:54'),
(447, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'success', '2026-06-20 08:47:07'),
(448, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-20 09:29:31'),
(449, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-20 09:30:02'),
(450, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-20 09:39:27'),
(451, 4, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-20 09:39:37'),
(452, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'success', '2026-06-20 09:43:20'),
(453, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'success', '2026-06-20 17:05:00'),
(454, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'success', '2026-06-20 23:08:36'),
(455, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-20 23:14:16'),
(456, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'success', '2026-06-21 00:05:57'),
(457, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 00:47:33'),
(458, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 03:38:42'),
(459, 1, 'logout', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'success', '2026-06-21 04:26:14'),
(460, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'success', '2026-06-21 04:26:23'),
(461, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 'success', '2026-06-21 06:51:59'),
(462, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 17:48:41'),
(463, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 17:52:49'),
(464, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 17:54:23'),
(465, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 18:20:33'),
(466, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 18:31:40'),
(467, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 18:31:49'),
(468, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-21 18:32:38'),
(469, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-23 18:55:43'),
(470, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-23 22:54:54'),
(471, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-24 03:03:29'),
(472, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-24 04:21:46'),
(473, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 02:42:08'),
(474, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 02:45:23'),
(475, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 03:14:26'),
(476, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 03:14:35'),
(477, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 03:14:58'),
(478, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 03:15:27'),
(479, 1, 'login', '127.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:19:10'),
(480, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:38:15'),
(481, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:46:29'),
(482, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:48:32'),
(483, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:48:39'),
(484, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:48:56'),
(485, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:55:33'),
(486, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:55:49'),
(487, 1, 'logout', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:56:03'),
(488, 1, 'login', '172.20.10.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.4 Mobile/15E148 Safari/604.1', 'success', '2026-06-28 20:56:14');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `user_id` int(11) NOT NULL,
  `theme` enum('light','dark','system') NOT NULL DEFAULT 'system',
  `date_format` varchar(20) NOT NULL DEFAULT 'Y-m-d',
  `time_format` enum('12h','24h') NOT NULL DEFAULT '24h',
  `items_per_page` int(11) NOT NULL DEFAULT 50,
  `dashboard_layout` enum('compact','standard','expanded') NOT NULL DEFAULT 'standard',
  `default_warehouse_view` enum('assigned','all') NOT NULL DEFAULT 'assigned',
  `warehouse_notif_dashboard` tinyint(1) NOT NULL DEFAULT 1,
  `warehouse_notif_low_stock` tinyint(1) NOT NULL DEFAULT 1,
  `warehouse_notif_transfer` tinyint(1) NOT NULL DEFAULT 1,
  `warehouse_notif_receiving` tinyint(1) NOT NULL DEFAULT 1,
  `warehouse_notif_dispatch` tinyint(1) NOT NULL DEFAULT 1,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_stores`
--

CREATE TABLE `user_stores` (
  `user_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_stores`
--

INSERT INTO `user_stores` (`user_id`, `store_id`, `created_at`) VALUES
(1, 7, '2026-05-22 23:48:55'),
(1, 10, '2026-05-23 02:42:18'),
(1, 11, '2026-06-17 05:18:14'),
(1, 12, '2026-06-28 02:48:48'),
(7, 6, '2026-05-24 03:03:43'),
(8, 6, '2026-05-24 12:18:16'),
(9, 7, '2026-06-09 03:45:19'),
(10, 7, '2026-06-09 03:48:22'),
(11, 7, '2026-06-09 03:51:00'),
(12, 7, '2026-06-09 03:52:05');

-- --------------------------------------------------------

--
-- Table structure for table `warehouses`
--

CREATE TABLE `warehouses` (
  `id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `warehouse_code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `warehouse_type` enum('central','regional','store','distribution','cold_storage','temporary') NOT NULL DEFAULT 'central',
  `manager_id` int(11) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Senegal',
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `capacity_units` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warehouses`
--

INSERT INTO `warehouses` (`id`, `store_id`, `warehouse_code`, `name`, `warehouse_type`, `manager_id`, `address`, `city`, `country`, `phone`, `email`, `status`, `capacity_units`, `notes`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'Côté d\'Ivoire M1', 'Entrepôt Yopoubon', 'central', NULL, 'Abidjan,Côté d\'Ivoire ', 'Abidjan ', 'Côte d’Ivoire ', '', '', 'active', 0, '', '2026-06-16 07:57:39', '2026-06-18 23:27:38', NULL),
(2, 1, 'Ghana M1', 'Entrepôt Lapaz ', 'regional', NULL, 'Ecobank Lapaz ', 'Accra,Ghana', NULL, NULL, NULL, 'active', 0, NULL, '2026-06-16 19:06:28', '2026-06-16 19:06:56', NULL),
(3, 11, 'Ghana-A1', 'Lapaz Warehouse', 'regional', 13, 'Lapaz Ecobank', 'Accra', 'Ghana', '+2231213141516', 'Warehouse@pos.com', 'active', 10000, 'Skelewu', '2026-06-18 23:16:38', NULL, NULL),
(4, NULL, 'Côte d’Ivoire M1', 'Entrepôt de Kumasi', 'central', 11, 'Champ Commando', 'Abidjan', 'Côte d’Ivoire', '+225599944826', 'Enterpotm1@pos.com', 'active', 0, NULL, '2026-06-18 23:25:02', '2026-06-21 04:10:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_aisles`
--

CREATE TABLE `warehouse_aisles` (
  `id` int(11) NOT NULL,
  `zone_id` int(11) NOT NULL,
  `aisle_code` varchar(50) NOT NULL,
  `name` varchar(120) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_audits`
--

CREATE TABLE `warehouse_audits` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `audit_type` enum('cycle_count','physical_count','spot_check') NOT NULL DEFAULT 'cycle_count',
  `status` enum('draft','in_progress','pending_approval','approved','rejected') NOT NULL DEFAULT 'draft',
  `expected_value` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `counted_value` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `variance_value` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `notes` text DEFAULT NULL,
  `conducted_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_audit_items`
--

CREATE TABLE `warehouse_audit_items` (
  `id` int(11) NOT NULL,
  `audit_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `system_qty` int(11) NOT NULL DEFAULT 0,
  `counted_qty` int(11) NOT NULL DEFAULT 0,
  `variance_qty` int(11) NOT NULL DEFAULT 0,
  `unit_cost` decimal(12,4) NOT NULL DEFAULT 0.0000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_bins`
--

CREATE TABLE `warehouse_bins` (
  `id` int(11) NOT NULL,
  `shelf_id` int(11) NOT NULL,
  `bin_code` varchar(50) NOT NULL,
  `capacity_units` int(11) DEFAULT 0,
  `status` enum('active','inactive','full') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_dispatches`
--

CREATE TABLE `warehouse_dispatches` (
  `id` int(11) NOT NULL,
  `dispatch_number` varchar(50) NOT NULL,
  `from_warehouse_id` int(11) NOT NULL,
  `to_store_id` int(11) DEFAULT NULL,
  `to_warehouse_id` int(11) DEFAULT NULL,
  `status` enum('draft','picking','packed','dispatched','in_transit','delivered','cancelled') NOT NULL DEFAULT 'draft',
  `driver_name` varchar(100) DEFAULT NULL,
  `vehicle_number` varchar(50) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `total_items` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warehouse_dispatches`
--

INSERT INTO `warehouse_dispatches` (`id`, `dispatch_number`, `from_warehouse_id`, `to_store_id`, `to_warehouse_id`, `status`, `driver_name`, `vehicle_number`, `delivery_date`, `received_by`, `received_at`, `total_items`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'DSP-20260621-D8D3C4', 4, 1, NULL, 'delivered', 'Ibou Sam', 'AB-8012', '2026-06-22', 1, '2026-06-21 00:18:20', 2, NULL, 1, '2026-06-20 23:22:45', '2026-06-21 00:18:20');

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_dispatch_items`
--

CREATE TABLE `warehouse_dispatch_items` (
  `id` int(11) NOT NULL,
  `dispatch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `quantity_picked` int(11) NOT NULL DEFAULT 0,
  `unit_cost` decimal(12,4) NOT NULL DEFAULT 0.0000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warehouse_dispatch_items`
--

INSERT INTO `warehouse_dispatch_items` (`id`, `dispatch_id`, `product_id`, `batch_id`, `quantity`, `quantity_picked`, `unit_cost`) VALUES
(1, 1, 98, NULL, 10, 0, 6999.0000),
(2, 1, 97, NULL, 10, 0, 7999.0000);

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_inventory`
--

CREATE TABLE `warehouse_inventory` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `location_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reserved_qty` int(11) NOT NULL DEFAULT 0,
  `damaged_qty` int(11) NOT NULL DEFAULT 0,
  `expired_qty` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 5,
  `unit_cost` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `stock_value` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `last_movement_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warehouse_inventory`
--

INSERT INTO `warehouse_inventory` (`id`, `warehouse_id`, `product_id`, `location_id`, `batch_id`, `quantity`, `reserved_qty`, `damaged_qty`, `expired_qty`, `reorder_level`, `unit_cost`, `stock_value`, `last_movement_at`, `updated_at`) VALUES
(1, 4, 24, NULL, NULL, 200, 0, 0, 0, 5, 20000.0000, 4000000.0000, '2026-06-20 10:23:53', NULL),
(2, 4, 96, NULL, NULL, 1000, 0, 0, 0, 5, 4999.0000, 4999000.0000, '2026-06-20 11:12:19', '2026-06-20 11:12:19'),
(3, 4, 97, NULL, 1, 790, 0, 0, 0, 5, 7999.0000, 6319210.0000, '2026-06-20 23:23:08', '2026-06-20 23:23:08'),
(4, 4, 98, NULL, NULL, 90, 0, 0, 0, 5, 6999.0000, 629910.0000, '2026-06-20 23:23:08', '2026-06-20 23:23:08'),
(5, 4, 99, NULL, NULL, 400, 0, 0, 0, 5, 2500.0000, 1000000.0000, '2026-06-20 11:03:17', NULL),
(6, 4, 100, NULL, NULL, 50, 0, 0, 0, 5, 2500.0000, 125000.0000, '2026-06-20 11:03:17', NULL),
(7, 4, 101, NULL, NULL, 1, 0, 0, 0, 5, 200.0000, 200.0000, '2026-06-20 11:08:46', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_locations`
--

CREATE TABLE `warehouse_locations` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `zone` varchar(50) NOT NULL DEFAULT 'A',
  `aisle` varchar(50) DEFAULT NULL,
  `rack` varchar(50) DEFAULT NULL,
  `shelf` varchar(50) DEFAULT NULL,
  `bin` varchar(50) DEFAULT NULL,
  `location_code` varchar(100) NOT NULL,
  `capacity_units` int(11) DEFAULT 0,
  `status` enum('active','inactive','full') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warehouse_locations`
--

INSERT INTO `warehouse_locations` (`id`, `warehouse_id`, `zone`, `aisle`, `rack`, `shelf`, `bin`, `location_code`, `capacity_units`, `status`, `created_at`) VALUES
(1, 1, 'Electronic', '6', '4', 'A1', 'R1', 'Electronic-6-4-A1-R1', 10000, 'active', '2026-06-16 18:34:36'),
(2, 1, 'Electronic', '5', 'C2', '2', 'A2', 'Electronic-5-C2-2-A2', 100, 'active', '2026-06-16 18:46:02');

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_logs`
--

CREATE TABLE `warehouse_logs` (
  `id` bigint(20) NOT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warehouse_logs`
--

INSERT INTO `warehouse_logs` (`id`, `warehouse_id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 1, 'warehouse_created', 'warehouse', 1, '{\"name\":\"Entrepôt Yopoubon\"}', '127.0.0.1', '2026-06-16 07:57:39'),
(2, 1, 1, 'warehouse_updated', 'warehouse', 1, NULL, '127.0.0.1', '2026-06-16 07:58:06'),
(3, 1, 1, 'location_created', 'warehouse_location', 1, NULL, '172.20.10.1', '2026-06-16 18:34:36'),
(4, 1, 1, 'location_created', 'warehouse_location', 2, NULL, '127.0.0.1', '2026-06-16 18:46:02'),
(5, 2, 1, 'warehouse_created', 'warehouse', 2, '{\"name\":\"Lapaz\"}', '172.20.10.1', '2026-06-16 19:06:28'),
(6, 2, 1, 'warehouse_updated', 'warehouse', 2, NULL, '172.20.10.1', '2026-06-16 19:06:56'),
(7, 3, 1, 'warehouse_created', 'warehouse', 3, '{\"name\":\"Lapaz Warehouse\"}', '127.0.0.1', '2026-06-18 23:16:38'),
(8, 4, 1, 'warehouse_created', 'warehouse', 4, '{\"name\":\"Entrepôt de Kumasi\"}', '172.20.10.1', '2026-06-18 23:25:02'),
(9, 1, 1, 'warehouse_updated', 'warehouse', 1, NULL, '172.20.10.1', '2026-06-18 23:27:38'),
(10, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"overview\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:29:06'),
(11, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"overview\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:33:42'),
(12, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"overview\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:34:51'),
(13, 4, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":\"4\",\"tab\":\"inventory\",\"filters\":{\"warehouse_id\":\"4\",\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:36:27'),
(14, 4, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":\"4\",\"tab\":\"overview\",\"filters\":{\"warehouse_id\":\"4\",\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:36:31'),
(15, 4, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":\"4\",\"tab\":\"inventory\",\"filters\":{\"warehouse_id\":\"4\",\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:36:32'),
(16, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"overview\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:36:43'),
(17, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"inventory\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:36:55'),
(18, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"movements\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:36:57'),
(19, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"low_stock\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:36:58'),
(20, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"out_of_stock\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:37:00'),
(21, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"expiry\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\",\"expiry_days\":\"90\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:37:02'),
(22, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"movements\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:37:04'),
(23, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"performance\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:37:05'),
(24, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"valuation\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\",\"valuation_method\":\"weighted\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:37:07'),
(25, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"damaged\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:37:08'),
(26, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"overview\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:37:10'),
(27, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"overview\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 06:42:08'),
(28, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"overview\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 07:03:49'),
(29, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"movements\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 07:04:07'),
(30, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"damaged\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 07:04:11'),
(31, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"overview\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 07:04:17'),
(32, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"overview\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 07:05:01'),
(33, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"overview\",\"filters\":{\"date_from\":\"2026-05-20\",\"date_to\":\"2026-06-19\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-19 07:47:22'),
(34, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"overview\",\"filters\":{\"date_from\":\"2026-05-21\",\"date_to\":\"2026-06-20\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-20 09:45:17'),
(35, 4, NULL, 'incoming_delivery', 'notification', NULL, '{\"reference\":\"GRN#1\",\"message\":\"Incoming delivery GRN#1 pending inspection\",\"notify\":true}', '127.0.0.1', '2026-06-20 10:23:39'),
(36, 4, NULL, 'purchase_received', 'notification', NULL, '{\"reference\":\"GRN-20260620-3B49A8\",\"message\":\"Goods received GRN-20260620-3B49A8 — 4,000,000 FCFA\",\"notify\":true}', '127.0.0.1', '2026-06-20 10:23:53'),
(37, 4, 1, 'product_quick_created', 'product', 96, NULL, '127.0.0.1', '2026-06-20 10:30:38'),
(38, 4, 1, 'product_quick_created', 'product', 97, NULL, '127.0.0.1', '2026-06-20 10:30:39'),
(39, 4, NULL, 'incoming_delivery', 'notification', NULL, '{\"reference\":\"GRN#2\",\"message\":\"Incoming delivery GRN#2 pending inspection\",\"notify\":true}', '127.0.0.1', '2026-06-20 10:30:39'),
(40, 4, NULL, 'purchase_received', 'notification', NULL, '{\"reference\":\"GRN-20260620-3C369D\",\"message\":\"Goods received GRN-20260620-3C369D — 2,299,600 FCFA\",\"notify\":true}', '127.0.0.1', '2026-06-20 10:31:00'),
(41, 4, 1, 'product_quick_created', 'product', 98, NULL, '127.0.0.1', '2026-06-20 11:02:52'),
(42, 4, 1, 'product_quick_created', 'product', 99, NULL, '127.0.0.1', '2026-06-20 11:02:52'),
(43, 4, 1, 'product_quick_created', 'product', 100, NULL, '127.0.0.1', '2026-06-20 11:02:52'),
(44, 4, NULL, 'incoming_delivery', 'notification', NULL, '{\"reference\":\"GRN#3\",\"message\":\"Incoming delivery GRN#3 pending inspection\",\"notify\":true}', '127.0.0.1', '2026-06-20 11:02:52'),
(45, 4, NULL, 'purchase_received', 'notification', NULL, '{\"reference\":\"GRN-20260620-26921E\",\"message\":\"Goods received GRN-20260620-26921E — 1,824,900 FCFA\",\"notify\":true}', '127.0.0.1', '2026-06-20 11:03:17'),
(46, 4, 1, 'product_quick_created', 'product', 101, NULL, '127.0.0.1', '2026-06-20 11:08:38'),
(47, 4, NULL, 'incoming_delivery', 'notification', NULL, '{\"reference\":\"GRN#4\",\"message\":\"Incoming delivery GRN#4 pending inspection\",\"notify\":true}', '127.0.0.1', '2026-06-20 11:08:39'),
(48, 4, NULL, 'purchase_received', 'notification', NULL, '{\"reference\":\"GRN-20260620-32F128\",\"message\":\"Goods received GRN-20260620-32F128 — 200 FCFA\",\"notify\":true}', '127.0.0.1', '2026-06-20 11:08:46'),
(49, 4, NULL, 'incoming_delivery', 'notification', NULL, '{\"reference\":\"GRN#5\",\"message\":\"Incoming delivery GRN#5 pending inspection\",\"notify\":true}', '127.0.0.1', '2026-06-20 11:12:19'),
(50, 4, NULL, 'purchase_received', 'notification', NULL, '{\"reference\":\"GRN-20260620-790196\",\"message\":\"Goods received GRN-20260620-790196 — 9,098,600 FCFA\",\"notify\":true}', '127.0.0.1', '2026-06-20 11:12:19'),
(51, 4, 1, 'product_quick_created', 'product', 102, NULL, '127.0.0.1', '2026-06-20 11:19:37'),
(52, 4, 1, 'product_quick_created', 'product', 103, NULL, '127.0.0.1', '2026-06-20 11:19:37'),
(53, 4, NULL, 'incoming_delivery', 'notification', NULL, '{\"reference\":\"GRN#6\",\"message\":\"Incoming delivery GRN#6 pending inspection\",\"notify\":true}', '127.0.0.1', '2026-06-20 11:19:37'),
(54, 4, 1, 'dispatch_created', 'warehouse_dispatch', 1, NULL, '172.20.10.1', '2026-06-20 23:22:45'),
(55, 4, 1, 'dispatch_out', 'warehouse_dispatch', 1, NULL, '172.20.10.1', '2026-06-20 23:23:08'),
(56, 4, 1, 'dispatch_delivered', 'warehouse_dispatch', 1, NULL, '127.0.0.1', '2026-06-21 00:18:20'),
(57, NULL, 1, 'inventory_report', 'inventory_report', NULL, '{\"action\":\"audit\",\"warehouse_id\":null,\"tab\":\"overview\",\"filters\":{\"date_from\":\"2026-05-22\",\"date_to\":\"2026-06-21\"},\"export_type\":\"view\"}', '127.0.0.1', '2026-06-21 00:20:57');

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_racks`
--

CREATE TABLE `warehouse_racks` (
  `id` int(11) NOT NULL,
  `aisle_id` int(11) NOT NULL,
  `rack_code` varchar(50) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_requests`
--

CREATE TABLE `warehouse_requests` (
  `id` int(11) NOT NULL,
  `request_number` varchar(50) NOT NULL,
  `store_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `status` enum('pending','manager_approved','warehouse_approved','dispatched','delivered','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `notes` text DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `warehouse_approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_request_items`
--

CREATE TABLE `warehouse_request_items` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_requested` int(11) NOT NULL DEFAULT 0,
  `quantity_approved` int(11) NOT NULL DEFAULT 0,
  `quantity_delivered` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_settings`
--

CREATE TABLE `warehouse_settings` (
  `warehouse_id` int(11) NOT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`settings`)),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouse_settings`
--

INSERT INTO `warehouse_settings` (`warehouse_id`, `settings`, `updated_by`, `updated_at`) VALUES
(4, '{\"general\":{\"working_hours\":\"08:00-18:00\",\"timezone\":\"UTC\",\"language\":\"en\",\"currency\":\"FCFA\"},\"inventory\":{\"default_reorder_level\":10,\"allow_negative_stock\":false,\"require_adjustment_approval\":true,\"automatic_inventory_updates\":true,\"valuation_method\":\"fifo\",\"enable_batch_tracking\":true,\"enable_serial_tracking\":false,\"enable_expiry_tracking\":true,\"automatic_low_stock_alerts\":true},\"transfers\":{\"require_approval\":true,\"auto_approve_internal\":false,\"allow_partial\":true,\"require_notes\":false,\"auto_generate_number\":true,\"transfer_prefix\":\"TRF\",\"default_status\":\"pending\"},\"receiving\":{\"require_purchase_order\":true,\"require_quality_inspection\":false,\"require_barcode_scan\":false,\"auto_generate_grn\":true,\"auto_update_inventory\":true,\"require_manager_approval\":false},\"dispatch\":{\"require_picking\":true,\"require_packing\":true,\"require_final_verification\":true,\"generate_dispatch_note\":true,\"generate_delivery_note\":true,\"require_delivery_signature\":false},\"barcode\":{\"default_type\":\"code128\",\"auto_generate\":true,\"barcode_prefix\":\"WH\",\"print_labels\":true,\"print_qr_codes\":false},\"notifications\":{\"low_stock\":true,\"out_of_stock\":true,\"expired_products\":true,\"damaged_products\":true,\"transfer_requests\":true,\"transfer_approved\":true,\"receiving_completed\":true,\"dispatch_completed\":true,\"inventory_count_due\":true,\"warehouse_full\":true,\"channel_dashboard\":true,\"channel_email\":true,\"channel_sms\":false,\"channel_push\":true,\"channel_whatsapp\":false},\"security\":{\"require_password_critical\":true,\"enable_audit_logs\":true,\"enable_activity_logs\":true,\"max_failed_attempts\":5,\"session_timeout_minutes\":30,\"ip_restrictions\":\"\"},\"offline\":{\"enable_offline_mode\":true,\"automatic_sync\":true,\"conflict_strategy\":\"server_wins\",\"sync_frequency_minutes\":5,\"local_storage_limit_mb\":50},\"reports\":{\"default_format\":\"pdf\",\"default_date_range\":\"30d\",\"automatic_scheduled_reports\":false}}', 1, '2026-06-21 04:10:17');

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_settings_audit`
--

CREATE TABLE `warehouse_settings_audit` (
  `id` bigint(20) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `setting_key` varchar(160) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_shelves`
--

CREATE TABLE `warehouse_shelves` (
  `id` int(11) NOT NULL,
  `rack_id` int(11) NOT NULL,
  `shelf_code` varchar(50) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_stock_movements`
--

CREATE TABLE `warehouse_stock_movements` (
  `id` bigint(20) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `movement_type` enum('purchase','sale','transfer_in','transfer_out','return_in','return_out','adjustment','damaged','expired','lost','manual','dispatch_out','receipt_in') NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `balance_after` int(11) NOT NULL DEFAULT 0,
  `unit_cost` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `stock_value` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sync_status` enum('synced','pending','conflict') NOT NULL DEFAULT 'synced',
  `local_uuid` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warehouse_stock_movements`
--

INSERT INTO `warehouse_stock_movements` (`id`, `warehouse_id`, `product_id`, `batch_id`, `movement_type`, `quantity`, `balance_after`, `unit_cost`, `stock_value`, `reference_type`, `reference_id`, `notes`, `created_by`, `created_at`, `sync_status`, `local_uuid`) VALUES
(1, 4, 24, NULL, 'receipt_in', 200, 200, 20000.0000, 4000000.0000, 'goods_receipt', 1, NULL, 1, '2026-06-20 10:23:53', 'synced', NULL),
(2, 4, 96, NULL, 'receipt_in', 300, 300, 4999.0000, 1499700.0000, 'goods_receipt', 2, NULL, 1, '2026-06-20 10:31:00', 'synced', NULL),
(3, 4, 97, NULL, 'receipt_in', 100, 100, 7999.0000, 799900.0000, 'goods_receipt', 2, NULL, 1, '2026-06-20 10:31:00', 'synced', NULL),
(4, 4, 98, NULL, 'receipt_in', 100, 100, 6999.0000, 699900.0000, 'goods_receipt', 3, NULL, 1, '2026-06-20 11:03:17', 'synced', NULL),
(5, 4, 99, NULL, 'receipt_in', 400, 400, 2500.0000, 1000000.0000, 'goods_receipt', 3, NULL, 1, '2026-06-20 11:03:17', 'synced', NULL),
(6, 4, 100, NULL, 'receipt_in', 50, 50, 2500.0000, 125000.0000, 'goods_receipt', 3, NULL, 1, '2026-06-20 11:03:17', 'synced', NULL),
(7, 4, 101, NULL, 'receipt_in', 1, 1, 200.0000, 200.0000, 'goods_receipt', 4, NULL, 1, '2026-06-20 11:08:46', 'synced', NULL),
(8, 4, 96, NULL, 'receipt_in', 700, 1000, 4999.0000, 4999000.0000, 'goods_receipt', 5, NULL, 1, '2026-06-20 11:12:19', 'synced', NULL),
(9, 4, 97, 1, 'receipt_in', 700, 800, 7999.0000, 6399200.0000, 'goods_receipt', 5, NULL, 1, '2026-06-20 11:12:19', 'synced', NULL),
(10, 4, 98, NULL, 'dispatch_out', -10, 90, 6999.0000, 629910.0000, 'warehouse_dispatch', 1, NULL, 1, '2026-06-20 23:23:08', 'synced', NULL),
(11, 4, 97, NULL, 'dispatch_out', -10, 790, 7999.0000, 6319210.0000, 'warehouse_dispatch', 1, NULL, 1, '2026-06-20 23:23:08', 'synced', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_tasks`
--

CREATE TABLE `warehouse_tasks` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `task_type` enum('receiving','dispatch','transfer','inventory_count','inspection','approval','picking','packing','shipping','other') NOT NULL DEFAULT 'other',
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `reference_type` varchar(60) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_transfers`
--

CREATE TABLE `warehouse_transfers` (
  `id` int(11) NOT NULL,
  `transfer_number` varchar(50) NOT NULL,
  `transfer_type` enum('warehouse_to_warehouse','warehouse_to_store','store_to_warehouse','branch_to_branch') NOT NULL,
  `from_warehouse_id` int(11) DEFAULT NULL,
  `to_warehouse_id` int(11) DEFAULT NULL,
  `from_store_id` int(11) DEFAULT NULL,
  `to_store_id` int(11) DEFAULT NULL,
  `status` enum('requested','approved','picking','in_transit','received','completed','rejected','cancelled') NOT NULL DEFAULT 'requested',
  `reason` text DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sync_status` enum('synced','pending','conflict') NOT NULL DEFAULT 'synced',
  `local_uuid` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_transfer_items`
--

CREATE TABLE `warehouse_transfer_items` (
  `id` int(11) NOT NULL,
  `transfer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity_requested` int(11) NOT NULL DEFAULT 0,
  `quantity_sent` int(11) NOT NULL DEFAULT 0,
  `quantity_received` int(11) NOT NULL DEFAULT 0,
  `unit_cost` decimal(12,4) NOT NULL DEFAULT 0.0000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_zones`
--

CREATE TABLE `warehouse_zones` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `zone_code` varchar(50) NOT NULL,
  `name` varchar(120) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `acc_accounting_logs`
--
ALTER TABLE `acc_accounting_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_acc_log_store` (`store_id`,`created_at`),
  ADD KEY `idx_acc_log_action` (`action`);

--
-- Indexes for table `acc_accounts`
--
ALTER TABLE `acc_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_acc_code_store` (`code`,`store_id`),
  ADD KEY `idx_acc_type` (`account_type`),
  ADD KEY `idx_acc_store` (`store_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `acc_bank_accounts`
--
ALTER TABLE `acc_bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `acc_bank_transactions`
--
ALTER TABLE `acc_bank_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bank_tx_date` (`store_id`,`transaction_date`),
  ADD KEY `bank_account_id` (`bank_account_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `acc_cash_accounts`
--
ALTER TABLE `acc_cash_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `acc_cash_transactions`
--
ALTER TABLE `acc_cash_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cash_tx_date` (`store_id`,`transaction_date`),
  ADD KEY `cash_account_id` (`cash_account_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `journal_entry_id` (`journal_entry_id`);

--
-- Indexes for table `acc_expense_records`
--
ALTER TABLE `acc_expense_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_exp_store_date` (`store_id`,`expense_date`),
  ADD KEY `idx_exp_status` (`status`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `acc_journal_entries`
--
ALTER TABLE `acc_journal_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_entry_no` (`entry_no`),
  ADD KEY `idx_je_store_date` (`store_id`,`entry_date`),
  ADD KEY `idx_je_ref` (`reference_type`,`reference_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `acc_journal_lines`
--
ALTER TABLE `acc_journal_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jl_entry` (`journal_entry_id`),
  ADD KEY `idx_jl_account` (`account_id`);

--
-- Indexes for table `acc_mobile_money_accounts`
--
ALTER TABLE `acc_mobile_money_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `acc_mobile_money_transactions`
--
ALTER TABLE `acc_mobile_money_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mm_tx_date` (`store_id`,`transaction_date`),
  ADD KEY `mobile_account_id` (`mobile_account_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `acc_offline_queue`
--
ALTER TABLE `acc_offline_queue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_offline_uuid` (`local_uuid`),
  ADD KEY `idx_offline_status` (`status`),
  ADD KEY `store_id` (`store_id`);

--
-- Indexes for table `acc_payables`
--
ALTER TABLE `acc_payables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ap_store_status` (`store_id`,`status`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `acc_receivables`
--
ALTER TABLE `acc_receivables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ar_store_status` (`store_id`,`status`),
  ADD KEY `idx_ar_customer` (`customer_id`),
  ADD KEY `sale_id` (`sale_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `barcode_registry`
--
ALTER TABLE `barcode_registry`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `batch_tracking`
--
ALTER TABLE `batch_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_batch_wh_product` (`warehouse_id`,`product_id`),
  ADD KEY `idx_batch_expiry` (`expiry_date`),
  ADD KEY `idx_batch_number` (`batch_number`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `cashier_presence`
--
ALTER TABLE `cashier_presence`
  ADD PRIMARY KEY (`user_id`,`store_id`),
  ADD KEY `idx_presence_store_seen` (`store_id`,`last_seen_at`);

--
-- Indexes for table `cashier_shifts`
--
ALTER TABLE `cashier_shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shifts_store_status` (`store_id`,`status`),
  ADD KEY `idx_shifts_user` (`user_id`);

--
-- Indexes for table `cash_movements`
--
ALTER TABLE `cash_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cm_store_date` (`store_id`,`created_at`),
  ADD KEY `idx_cm_register` (`register_id`),
  ADD KEY `idx_cm_session` (`session_id`),
  ADD KEY `idx_cm_type` (`movement_type`),
  ADD KEY `idx_cm_sync` (`sync_status`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `cash_reconciliation`
--
ALTER TABLE `cash_reconciliation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cr_store_status` (`store_id`,`status`),
  ADD KEY `idx_cr_session` (`session_id`),
  ADD KEY `register_id` (`register_id`),
  ADD KEY `manager_id` (`manager_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `cash_registers`
--
ALTER TABLE `cash_registers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_cash_register_code` (`store_id`,`register_code`),
  ADD KEY `idx_cash_registers_store_status` (`store_id`,`status`),
  ADD KEY `idx_cash_registers_assigned` (`assigned_user_id`);

--
-- Indexes for table `cash_register_logs`
--
ALTER TABLE `cash_register_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_crl_store_created` (`store_id`,`created_at`),
  ADD KEY `idx_crl_register` (`register_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cash_register_sessions`
--
ALTER TABLE `cash_register_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_crs_register_status` (`register_id`,`status`),
  ADD KEY `idx_crs_store_opened` (`store_id`,`opened_at`),
  ADD KEY `idx_crs_user` (`user_id`),
  ADD KEY `opened_by` (`opened_by`),
  ADD KEY `closed_by` (`closed_by`);

--
-- Indexes for table `cash_transfers`
--
ALTER TABLE `cash_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ct_store_status` (`store_id`,`status`),
  ADD KEY `from_register_id` (`from_register_id`),
  ADD KEY `to_register_id` (`to_register_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `idx_categories_store` (`store_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customers_store` (`store_id`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`,`language`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `failed_login_attempts`
--
ALTER TABLE `failed_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_email` (`ip_address`,`email`);

--
-- Indexes for table `goods_receipts`
--
ALTER TABLE `goods_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_grn_number` (`grn_number`),
  ADD KEY `idx_grn_warehouse` (`warehouse_id`),
  ADD KEY `idx_grn_status` (`status`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `received_by` (`received_by`),
  ADD KEY `inspected_by` (`inspected_by`);

--
-- Indexes for table `goods_receipt_items`
--
ALTER TABLE `goods_receipt_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `goods_receipt_id` (`goods_receipt_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `help_articles`
--
ALTER TABLE `help_articles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_ha_category` (`category_id`),
  ADD KEY `idx_ha_module` (`module`);

--
-- Indexes for table `help_categories`
--
ALTER TABLE `help_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `help_faq`
--
ALTER TABLE `help_faq`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `help_support_tickets`
--
ALTER TABLE `help_support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `idx_hst_user` (`user_id`),
  ADD KEY `idx_hst_status` (`status`),
  ADD KEY `warehouse_id` (`warehouse_id`);

--
-- Indexes for table `help_system_updates`
--
ALTER TABLE `help_system_updates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `help_ticket_replies`
--
ALTER TABLE `help_ticket_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `help_tutorial_videos`
--
ALTER TABLE `help_tutorial_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `inventory_counts`
--
ALTER TABLE `inventory_counts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_count_number` (`count_number`),
  ADD KEY `idx_ic_wh_status` (`warehouse_id`,`status`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `inventory_ledger`
--
ALTER TABLE `inventory_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_store` (`product_id`,`store_id`),
  ADD KEY `idx_movement_type` (`movement_type`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_dates` (`movement_date`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `idx_ledger_warehouse` (`warehouse_id`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_inventory_logs_product` (`product_id`);

--
-- Indexes for table `login_activity`
--
ALTER TABLE `login_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `manager_approvals`
--
ALTER TABLE `manager_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mgr_approvals_store_status` (`store_id`,`status`),
  ADD KEY `idx_mgr_approvals_type` (`type`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `manager_audit_log`
--
ALTER TABLE `manager_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_store` (`store_id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_notifications_uuid` (`uuid`),
  ADD KEY `idx_notif_user` (`user_id`,`is_read`,`is_archived`,`deleted_at`),
  ADD KEY `idx_notif_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_notif_category` (`category_slug`),
  ADD KEY `idx_notif_priority` (`priority`,`severity`),
  ADD KEY `idx_notif_store` (`store_id`),
  ADD KEY `idx_notif_warehouse` (`warehouse_id`);

--
-- Indexes for table `notifications_legacy`
--
ALTER TABLE `notifications_legacy`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notification_categories`
--
ALTER TABLE `notification_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `notification_channels`
--
ALTER TABLE `notification_channels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nl_notif` (`notification_id`),
  ADD KEY `idx_nl_user` (`user_id`),
  ADD KEY `idx_nl_action` (`action`);

--
-- Indexes for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `notification_queue`
--
ALTER TABLE `notification_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nq_status` (`status`,`scheduled_at`),
  ADD KEY `idx_nq_user` (`user_id`),
  ADD KEY `notification_id` (`notification_id`);

--
-- Indexes for table `notification_templates`
--
ALTER TABLE `notification_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `notification_types`
--
ALTER TABLE `notification_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `offline_transactions`
--
ALTER TABLE `offline_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `local_uuid` (`local_uuid`),
  ADD KEY `store_id` (`store_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_ref` (`transaction_ref`),
  ADD KEY `sale_id` (`sale_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD UNIQUE KEY `uq_products_store_sku` (`store_id`,`sku`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `idx_products_category` (`category_id`),
  ADD KEY `idx_products_supplier` (`supplier_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_po_number` (`po_number`),
  ADD KEY `idx_po_supplier` (`supplier_id`),
  ADD KEY `idx_po_warehouse` (`warehouse_id`),
  ADD KEY `idx_po_status` (`status`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_no` (`receipt_no`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_sales_store_date` (`store_id`,`created_at`),
  ADD KEY `idx_sales_customer` (`customer_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `security_audit_logs`
--
ALTER TABLE `security_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sal_user` (`user_id`),
  ADD KEY `idx_sal_action` (`action`),
  ADD KEY `idx_sal_created` (`created_at`);

--
-- Indexes for table `sms_templates`
--
ALTER TABLE `sms_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`,`language`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from_store_id` (`from_store_id`),
  ADD KEY `to_store_id` (`to_store_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `store_sync_status`
--
ALTER TABLE `store_sync_status`
  ADD PRIMARY KEY (`store_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `synchronization_queue`
--
ALTER TABLE `synchronization_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `store_id` (`store_id`);

--
-- Indexes for table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`user_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `user_stores`
--
ALTER TABLE `user_stores`
  ADD PRIMARY KEY (`user_id`,`store_id`),
  ADD KEY `store_id` (`store_id`);

--
-- Indexes for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_warehouse_code` (`warehouse_code`),
  ADD KEY `idx_wh_store_status` (`store_id`,`status`),
  ADD KEY `idx_wh_type` (`warehouse_type`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `warehouse_aisles`
--
ALTER TABLE `warehouse_aisles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_zone_aisle` (`zone_id`,`aisle_code`);

--
-- Indexes for table `warehouse_audits`
--
ALTER TABLE `warehouse_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `conducted_by` (`conducted_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `warehouse_audit_items`
--
ALTER TABLE `warehouse_audit_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `audit_id` (`audit_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `warehouse_bins`
--
ALTER TABLE `warehouse_bins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_shelf_bin` (`shelf_id`,`bin_code`);

--
-- Indexes for table `warehouse_dispatches`
--
ALTER TABLE `warehouse_dispatches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_dispatch_number` (`dispatch_number`),
  ADD KEY `idx_wd_from` (`from_warehouse_id`),
  ADD KEY `idx_wd_status` (`status`),
  ADD KEY `to_store_id` (`to_store_id`),
  ADD KEY `to_warehouse_id` (`to_warehouse_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `warehouse_dispatch_items`
--
ALTER TABLE `warehouse_dispatch_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dispatch_id` (`dispatch_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `warehouse_inventory`
--
ALTER TABLE `warehouse_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_wh_product` (`warehouse_id`,`product_id`),
  ADD KEY `idx_wi_warehouse` (`warehouse_id`),
  ADD KEY `idx_wi_product` (`product_id`),
  ADD KEY `idx_wi_low_stock` (`warehouse_id`,`quantity`,`reorder_level`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `warehouse_locations`
--
ALTER TABLE `warehouse_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_wh_location` (`warehouse_id`,`location_code`),
  ADD KEY `idx_wl_warehouse` (`warehouse_id`);

--
-- Indexes for table `warehouse_logs`
--
ALTER TABLE `warehouse_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wlog_wh_created` (`warehouse_id`,`created_at`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `warehouse_racks`
--
ALTER TABLE `warehouse_racks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_aisle_rack` (`aisle_id`,`rack_code`);

--
-- Indexes for table `warehouse_requests`
--
ALTER TABLE `warehouse_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_request_number` (`request_number`),
  ADD KEY `idx_wr_store` (`store_id`),
  ADD KEY `idx_wr_status` (`status`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `manager_id` (`manager_id`),
  ADD KEY `warehouse_approved_by` (`warehouse_approved_by`);

--
-- Indexes for table `warehouse_request_items`
--
ALTER TABLE `warehouse_request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `warehouse_settings`
--
ALTER TABLE `warehouse_settings`
  ADD PRIMARY KEY (`warehouse_id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `warehouse_settings_audit`
--
ALTER TABLE `warehouse_settings_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wsa_wh_created` (`warehouse_id`,`created_at`),
  ADD KEY `idx_wsa_key` (`setting_key`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `warehouse_shelves`
--
ALTER TABLE `warehouse_shelves`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_rack_shelf` (`rack_id`,`shelf_code`);

--
-- Indexes for table `warehouse_stock_movements`
--
ALTER TABLE `warehouse_stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wsm_wh_date` (`warehouse_id`,`created_at`),
  ADD KEY `idx_wsm_product` (`product_id`),
  ADD KEY `idx_wsm_type` (`movement_type`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `warehouse_tasks`
--
ALTER TABLE `warehouse_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wt_warehouse_status` (`warehouse_id`,`status`),
  ADD KEY `idx_wt_assigned` (`assigned_to`,`status`),
  ADD KEY `idx_wt_due` (`due_date`,`status`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `warehouse_transfers`
--
ALTER TABLE `warehouse_transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_transfer_number` (`transfer_number`),
  ADD KEY `idx_wt_status` (`status`),
  ADD KEY `idx_wt_from_wh` (`from_warehouse_id`),
  ADD KEY `to_warehouse_id` (`to_warehouse_id`),
  ADD KEY `from_store_id` (`from_store_id`),
  ADD KEY `to_store_id` (`to_store_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `warehouse_transfer_items`
--
ALTER TABLE `warehouse_transfer_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transfer_id` (`transfer_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `warehouse_zones`
--
ALTER TABLE `warehouse_zones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_wh_zone` (`warehouse_id`,`zone_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `acc_accounting_logs`
--
ALTER TABLE `acc_accounting_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `acc_accounts`
--
ALTER TABLE `acc_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `acc_bank_accounts`
--
ALTER TABLE `acc_bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_bank_transactions`
--
ALTER TABLE `acc_bank_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_cash_accounts`
--
ALTER TABLE `acc_cash_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `acc_cash_transactions`
--
ALTER TABLE `acc_cash_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `acc_expense_records`
--
ALTER TABLE `acc_expense_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `acc_journal_entries`
--
ALTER TABLE `acc_journal_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `acc_journal_lines`
--
ALTER TABLE `acc_journal_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `acc_mobile_money_accounts`
--
ALTER TABLE `acc_mobile_money_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_mobile_money_transactions`
--
ALTER TABLE `acc_mobile_money_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_offline_queue`
--
ALTER TABLE `acc_offline_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_payables`
--
ALTER TABLE `acc_payables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_receivables`
--
ALTER TABLE `acc_receivables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `barcode_registry`
--
ALTER TABLE `barcode_registry`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `batch_tracking`
--
ALTER TABLE `batch_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cashier_shifts`
--
ALTER TABLE `cashier_shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `cash_movements`
--
ALTER TABLE `cash_movements`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cash_reconciliation`
--
ALTER TABLE `cash_reconciliation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cash_registers`
--
ALTER TABLE `cash_registers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `cash_register_logs`
--
ALTER TABLE `cash_register_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `cash_register_sessions`
--
ALTER TABLE `cash_register_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cash_transfers`
--
ALTER TABLE `cash_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_login_attempts`
--
ALTER TABLE `failed_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `goods_receipts`
--
ALTER TABLE `goods_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `goods_receipt_items`
--
ALTER TABLE `goods_receipt_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `help_articles`
--
ALTER TABLE `help_articles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `help_categories`
--
ALTER TABLE `help_categories`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `help_faq`
--
ALTER TABLE `help_faq`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `help_support_tickets`
--
ALTER TABLE `help_support_tickets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `help_system_updates`
--
ALTER TABLE `help_system_updates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `help_ticket_replies`
--
ALTER TABLE `help_ticket_replies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `help_tutorial_videos`
--
ALTER TABLE `help_tutorial_videos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inventory_counts`
--
ALTER TABLE `inventory_counts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_ledger`
--
ALTER TABLE `inventory_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=663;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=613;

--
-- AUTO_INCREMENT for table `login_activity`
--
ALTER TABLE `login_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=333;

--
-- AUTO_INCREMENT for table `manager_approvals`
--
ALTER TABLE `manager_approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `manager_audit_log`
--
ALTER TABLE `manager_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=177;

--
-- AUTO_INCREMENT for table `notifications_legacy`
--
ALTER TABLE `notifications_legacy`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_categories`
--
ALTER TABLE `notification_categories`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36373;

--
-- AUTO_INCREMENT for table `notification_channels`
--
ALTER TABLE `notification_channels`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=256;

--
-- AUTO_INCREMENT for table `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=207;

--
-- AUTO_INCREMENT for table `notification_queue`
--
ALTER TABLE `notification_queue`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `notification_templates`
--
ALTER TABLE `notification_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52284;

--
-- AUTO_INCREMENT for table `notification_types`
--
ALTER TABLE `notification_types`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=256;

--
-- AUTO_INCREMENT for table `offline_transactions`
--
ALTER TABLE `offline_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=278;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11126;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=278;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=489;

--
-- AUTO_INCREMENT for table `security_audit_logs`
--
ALTER TABLE `security_audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153;

--
-- AUTO_INCREMENT for table `sms_templates`
--
ALTER TABLE `sms_templates`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `synchronization_queue`
--
ALTER TABLE `synchronization_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=489;

--
-- AUTO_INCREMENT for table `warehouses`
--
ALTER TABLE `warehouses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `warehouse_aisles`
--
ALTER TABLE `warehouse_aisles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_audits`
--
ALTER TABLE `warehouse_audits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_audit_items`
--
ALTER TABLE `warehouse_audit_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_bins`
--
ALTER TABLE `warehouse_bins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_dispatches`
--
ALTER TABLE `warehouse_dispatches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `warehouse_dispatch_items`
--
ALTER TABLE `warehouse_dispatch_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `warehouse_inventory`
--
ALTER TABLE `warehouse_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `warehouse_locations`
--
ALTER TABLE `warehouse_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `warehouse_logs`
--
ALTER TABLE `warehouse_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `warehouse_racks`
--
ALTER TABLE `warehouse_racks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_requests`
--
ALTER TABLE `warehouse_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_request_items`
--
ALTER TABLE `warehouse_request_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_settings_audit`
--
ALTER TABLE `warehouse_settings_audit`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_shelves`
--
ALTER TABLE `warehouse_shelves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_stock_movements`
--
ALTER TABLE `warehouse_stock_movements`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `warehouse_tasks`
--
ALTER TABLE `warehouse_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_transfers`
--
ALTER TABLE `warehouse_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_transfer_items`
--
ALTER TABLE `warehouse_transfer_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_zones`
--
ALTER TABLE `warehouse_zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `acc_accounts`
--
ALTER TABLE `acc_accounts`
  ADD CONSTRAINT `acc_accounts_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `acc_accounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `acc_accounts_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `acc_bank_accounts`
--
ALTER TABLE `acc_bank_accounts`
  ADD CONSTRAINT `acc_bank_accounts_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `acc_bank_accounts_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `acc_bank_transactions`
--
ALTER TABLE `acc_bank_transactions`
  ADD CONSTRAINT `acc_bank_transactions_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `acc_bank_accounts` (`id`),
  ADD CONSTRAINT `acc_bank_transactions_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `acc_bank_transactions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `acc_cash_accounts`
--
ALTER TABLE `acc_cash_accounts`
  ADD CONSTRAINT `acc_cash_accounts_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `acc_cash_accounts_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `acc_cash_transactions`
--
ALTER TABLE `acc_cash_transactions`
  ADD CONSTRAINT `acc_cash_transactions_ibfk_1` FOREIGN KEY (`cash_account_id`) REFERENCES `acc_cash_accounts` (`id`),
  ADD CONSTRAINT `acc_cash_transactions_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `acc_cash_transactions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `acc_cash_transactions_ibfk_4` FOREIGN KEY (`journal_entry_id`) REFERENCES `acc_journal_entries` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `acc_expense_records`
--
ALTER TABLE `acc_expense_records`
  ADD CONSTRAINT `acc_expense_records_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `acc_expense_records_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `acc_expense_records_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `acc_expense_records_ibfk_4` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `acc_journal_entries`
--
ALTER TABLE `acc_journal_entries`
  ADD CONSTRAINT `acc_journal_entries_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `acc_journal_entries_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `acc_journal_lines`
--
ALTER TABLE `acc_journal_lines`
  ADD CONSTRAINT `acc_journal_lines_ibfk_1` FOREIGN KEY (`journal_entry_id`) REFERENCES `acc_journal_entries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `acc_journal_lines_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`id`);

--
-- Constraints for table `acc_mobile_money_accounts`
--
ALTER TABLE `acc_mobile_money_accounts`
  ADD CONSTRAINT `acc_mobile_money_accounts_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `acc_mobile_money_accounts_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `acc_mobile_money_transactions`
--
ALTER TABLE `acc_mobile_money_transactions`
  ADD CONSTRAINT `acc_mobile_money_transactions_ibfk_1` FOREIGN KEY (`mobile_account_id`) REFERENCES `acc_mobile_money_accounts` (`id`),
  ADD CONSTRAINT `acc_mobile_money_transactions_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `acc_mobile_money_transactions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `acc_offline_queue`
--
ALTER TABLE `acc_offline_queue`
  ADD CONSTRAINT `acc_offline_queue_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`);

--
-- Constraints for table `acc_payables`
--
ALTER TABLE `acc_payables`
  ADD CONSTRAINT `acc_payables_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `acc_payables_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `acc_receivables`
--
ALTER TABLE `acc_receivables`
  ADD CONSTRAINT `acc_receivables_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `acc_receivables_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `acc_receivables_ibfk_3` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `barcode_registry`
--
ALTER TABLE `barcode_registry`
  ADD CONSTRAINT `barcode_registry_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `batch_tracking`
--
ALTER TABLE `batch_tracking`
  ADD CONSTRAINT `batch_tracking_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `batch_tracking_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cashier_presence`
--
ALTER TABLE `cashier_presence`
  ADD CONSTRAINT `cashier_presence_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cashier_presence_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cashier_shifts`
--
ALTER TABLE `cashier_shifts`
  ADD CONSTRAINT `cashier_shifts_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cashier_shifts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cash_movements`
--
ALTER TABLE `cash_movements`
  ADD CONSTRAINT `cash_movements_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cash_movements_ibfk_2` FOREIGN KEY (`register_id`) REFERENCES `cash_registers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cash_movements_ibfk_3` FOREIGN KEY (`session_id`) REFERENCES `cash_register_sessions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cash_movements_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cash_reconciliation`
--
ALTER TABLE `cash_reconciliation`
  ADD CONSTRAINT `cash_reconciliation_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cash_reconciliation_ibfk_2` FOREIGN KEY (`register_id`) REFERENCES `cash_registers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cash_reconciliation_ibfk_3` FOREIGN KEY (`session_id`) REFERENCES `cash_register_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cash_reconciliation_ibfk_4` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cash_reconciliation_ibfk_5` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cash_registers`
--
ALTER TABLE `cash_registers`
  ADD CONSTRAINT `cash_registers_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cash_registers_ibfk_2` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cash_register_logs`
--
ALTER TABLE `cash_register_logs`
  ADD CONSTRAINT `cash_register_logs_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cash_register_logs_ibfk_2` FOREIGN KEY (`register_id`) REFERENCES `cash_registers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cash_register_logs_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cash_register_sessions`
--
ALTER TABLE `cash_register_sessions`
  ADD CONSTRAINT `cash_register_sessions_ibfk_1` FOREIGN KEY (`register_id`) REFERENCES `cash_registers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cash_register_sessions_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cash_register_sessions_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cash_register_sessions_ibfk_4` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cash_register_sessions_ibfk_5` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cash_transfers`
--
ALTER TABLE `cash_transfers`
  ADD CONSTRAINT `cash_transfers_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cash_transfers_ibfk_2` FOREIGN KEY (`from_register_id`) REFERENCES `cash_registers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cash_transfers_ibfk_3` FOREIGN KEY (`to_register_id`) REFERENCES `cash_registers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cash_transfers_ibfk_4` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cash_transfers_ibfk_5` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cash_transfers_ibfk_6` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `goods_receipts`
--
ALTER TABLE `goods_receipts`
  ADD CONSTRAINT `goods_receipts_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `goods_receipts_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `goods_receipts_ibfk_3` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `goods_receipts_ibfk_4` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `goods_receipts_ibfk_5` FOREIGN KEY (`inspected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `goods_receipt_items`
--
ALTER TABLE `goods_receipt_items`
  ADD CONSTRAINT `goods_receipt_items_ibfk_1` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `goods_receipt_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `goods_receipt_items_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `batch_tracking` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `goods_receipt_items_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `warehouse_locations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `help_articles`
--
ALTER TABLE `help_articles`
  ADD CONSTRAINT `help_articles_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `help_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `help_faq`
--
ALTER TABLE `help_faq`
  ADD CONSTRAINT `help_faq_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `help_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `help_support_tickets`
--
ALTER TABLE `help_support_tickets`
  ADD CONSTRAINT `help_support_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `help_support_tickets_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `help_ticket_replies`
--
ALTER TABLE `help_ticket_replies`
  ADD CONSTRAINT `help_ticket_replies_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `help_support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `help_ticket_replies_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `help_tutorial_videos`
--
ALTER TABLE `help_tutorial_videos`
  ADD CONSTRAINT `help_tutorial_videos_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `help_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_counts`
--
ALTER TABLE `inventory_counts`
  ADD CONSTRAINT `inventory_counts_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_counts_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_counts_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_ledger`
--
ALTER TABLE `inventory_ledger`
  ADD CONSTRAINT `inventory_ledger_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_ledger_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_ledger_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `inventory_logs_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `inventory_logs_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `login_activity`
--
ALTER TABLE `login_activity`
  ADD CONSTRAINT `login_activity_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `manager_approvals`
--
ALTER TABLE `manager_approvals`
  ADD CONSTRAINT `manager_approvals_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `manager_approvals_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `manager_approvals_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `manager_audit_log`
--
ALTER TABLE `manager_audit_log`
  ADD CONSTRAINT `manager_audit_log_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `manager_audit_log_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD CONSTRAINT `notification_logs_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notification_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD CONSTRAINT `notification_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_queue`
--
ALTER TABLE `notification_queue`
  ADD CONSTRAINT `notification_queue_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notification_queue_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_templates`
--
ALTER TABLE `notification_templates`
  ADD CONSTRAINT `notification_templates_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `notification_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `offline_transactions`
--
ALTER TABLE `offline_transactions`
  ADD CONSTRAINT `offline_transactions_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_3` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_orders_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_orders_ibfk_5` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `security_audit_logs`
--
ALTER TABLE `security_audit_logs`
  ADD CONSTRAINT `security_audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`from_store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`to_store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `store_sync_status`
--
ALTER TABLE `store_sync_status`
  ADD CONSTRAINT `store_sync_status_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `synchronization_queue`
--
ALTER TABLE `synchronization_queue`
  ADD CONSTRAINT `synchronization_queue_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  ADD CONSTRAINT `user_activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_stores`
--
ALTER TABLE `user_stores`
  ADD CONSTRAINT `user_stores_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_stores_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD CONSTRAINT `warehouses_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouses_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_aisles`
--
ALTER TABLE `warehouse_aisles`
  ADD CONSTRAINT `warehouse_aisles_ibfk_1` FOREIGN KEY (`zone_id`) REFERENCES `warehouse_zones` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warehouse_audits`
--
ALTER TABLE `warehouse_audits`
  ADD CONSTRAINT `warehouse_audits_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_audits_ibfk_2` FOREIGN KEY (`conducted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouse_audits_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_audit_items`
--
ALTER TABLE `warehouse_audit_items`
  ADD CONSTRAINT `warehouse_audit_items_ibfk_1` FOREIGN KEY (`audit_id`) REFERENCES `warehouse_audits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_audit_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `warehouse_bins`
--
ALTER TABLE `warehouse_bins`
  ADD CONSTRAINT `warehouse_bins_ibfk_1` FOREIGN KEY (`shelf_id`) REFERENCES `warehouse_shelves` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warehouse_dispatches`
--
ALTER TABLE `warehouse_dispatches`
  ADD CONSTRAINT `warehouse_dispatches_ibfk_1` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `warehouse_dispatches_ibfk_2` FOREIGN KEY (`to_store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouse_dispatches_ibfk_3` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouse_dispatches_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_dispatches_ibfk_5` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_dispatch_items`
--
ALTER TABLE `warehouse_dispatch_items`
  ADD CONSTRAINT `warehouse_dispatch_items_ibfk_1` FOREIGN KEY (`dispatch_id`) REFERENCES `warehouse_dispatches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_dispatch_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `warehouse_dispatch_items_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `batch_tracking` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_inventory`
--
ALTER TABLE `warehouse_inventory`
  ADD CONSTRAINT `warehouse_inventory_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_inventory_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_inventory_ibfk_3` FOREIGN KEY (`location_id`) REFERENCES `warehouse_locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouse_inventory_ibfk_4` FOREIGN KEY (`batch_id`) REFERENCES `batch_tracking` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_locations`
--
ALTER TABLE `warehouse_locations`
  ADD CONSTRAINT `warehouse_locations_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warehouse_logs`
--
ALTER TABLE `warehouse_logs`
  ADD CONSTRAINT `warehouse_logs_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouse_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_racks`
--
ALTER TABLE `warehouse_racks`
  ADD CONSTRAINT `warehouse_racks_ibfk_1` FOREIGN KEY (`aisle_id`) REFERENCES `warehouse_aisles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warehouse_requests`
--
ALTER TABLE `warehouse_requests`
  ADD CONSTRAINT `warehouse_requests_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_requests_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `warehouse_requests_ibfk_3` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_requests_ibfk_4` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouse_requests_ibfk_5` FOREIGN KEY (`warehouse_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_request_items`
--
ALTER TABLE `warehouse_request_items`
  ADD CONSTRAINT `warehouse_request_items_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `warehouse_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_request_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `warehouse_settings`
--
ALTER TABLE `warehouse_settings`
  ADD CONSTRAINT `warehouse_settings_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_settings_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_settings_audit`
--
ALTER TABLE `warehouse_settings_audit`
  ADD CONSTRAINT `warehouse_settings_audit_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_settings_audit_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_shelves`
--
ALTER TABLE `warehouse_shelves`
  ADD CONSTRAINT `warehouse_shelves_ibfk_1` FOREIGN KEY (`rack_id`) REFERENCES `warehouse_racks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warehouse_stock_movements`
--
ALTER TABLE `warehouse_stock_movements`
  ADD CONSTRAINT `warehouse_stock_movements_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_stock_movements_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_stock_movements_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `batch_tracking` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouse_stock_movements_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_tasks`
--
ALTER TABLE `warehouse_tasks`
  ADD CONSTRAINT `warehouse_tasks_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_tasks_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouse_tasks_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_transfers`
--
ALTER TABLE `warehouse_transfers`
  ADD CONSTRAINT `warehouse_transfers_ibfk_1` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouse_transfers_ibfk_2` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouse_transfers_ibfk_3` FOREIGN KEY (`from_store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouse_transfers_ibfk_4` FOREIGN KEY (`to_store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouse_transfers_ibfk_5` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_transfers_ibfk_6` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouse_transfers_ibfk_7` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_transfer_items`
--
ALTER TABLE `warehouse_transfer_items`
  ADD CONSTRAINT `warehouse_transfer_items_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `warehouse_transfers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_transfer_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `warehouse_transfer_items_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `batch_tracking` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_zones`
--
ALTER TABLE `warehouse_zones`
  ADD CONSTRAINT `warehouse_zones_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
