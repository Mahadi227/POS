# Accounting & Finance Module

Enterprise accounting integrated with POS, inventory, warehouse, and multi-store operations.

**Path:** `public/accounting/`  
**API:** `GET/POST api/v1/index.php?request=accounting/{action}`  
**Migration:** `includes/Database/migrations/014_accounting.sql`

---

## Setup

1. Run migration **014** in phpMyAdmin or MySQL CLI.
2. Or visit any accounting page — `AccountingSchema::ensure()` attempts auto-install on first load.
3. Log in as **Admin**, **Super Admin**, **Accountant**, or **Manager**.

---

## Architecture

```
public/accounting/          UI pages + bootstrap/layout
includes/Accounting/        Domain (Schema, Repositories, Services)
includes/Controllers/       AccountingController.php
assets/js/admin/            accounting-*.js, admin-api.js
assets/css/                 admin-accounting.css
languages/{en,fr}/          accounting.php
```

### Double-entry GL

| Table | Purpose |
|-------|---------|
| `acc_accounts` | Chart of accounts (seeded on migration) |
| `acc_journal_entries` | Journal headers |
| `acc_journal_lines` | Debit/credit lines |

### Treasury

| Table | Purpose |
|-------|---------|
| `acc_cash_accounts` / `acc_cash_transactions` | Cash registers |
| `acc_bank_accounts` / `acc_bank_transactions` | Banking |
| `acc_mobile_money_accounts` / `acc_mobile_money_transactions` | MTN, Orange, Moov, Airtel, Vodafone |

### AR / AP / Expenses

| Table | Purpose |
|-------|---------|
| `acc_receivables` | Customer credit |
| `acc_payables` | Supplier invoices |
| `acc_expense_records` | Expense workflow (pending → approved) |

### Audit & Offline

| Table | Purpose |
|-------|---------|
| `acc_accounting_logs` | Financial audit trail |
| `acc_offline_queue` | Server-side sync queue |

---

## Automatic posting

**POS checkout** (`SalesController::processCheckout`):

- Dr Cash / Bank / Mobile Money (by payment method)
- Cr Product Sales (4010)
- Optional Dr COGS / Cr Inventory when product costs exist

**Expense approval** (`ExpenseAccountingService::approve`):

- Dr Expense account (by category)
- Cr Cash/Bank/Mobile

---

## API endpoints

| Request | Method | Description |
|---------|--------|-------------|
| `accounting/dashboard` | GET | KPIs + charts |
| `accounting/accounts` | GET | Chart of accounts |
| `accounting/journal` | GET/POST | Journal entries |
| `accounting/expenses` | GET/POST | Expense CRUD + approve/reject |
| `accounting/cash` | GET/POST | Cash accounts & transactions |
| `accounting/banks` | GET/POST | Bank accounts |
| `accounting/mobile-money` | GET/POST | Mobile money wallets |
| `accounting/receivables` | GET | AR list |
| `accounting/payables` | GET | AP list |
| `accounting/inventory` | GET | Inventory valuation |
| `accounting/reports/profit-loss` | GET | P&L |
| `accounting/reports/balance-sheet` | GET | Balance sheet |
| `accounting/reports/cashflow` | GET | Cash flow |
| `accounting/analytics` | GET | Chart data |
| `accounting/audit` | GET | Audit logs |
| `accounting/sync` | POST | Offline queue sync |

---

## Roles & permissions

| Role | Access |
|------|--------|
| Super Admin | Full |
| Admin | Full |
| Accountant | GL, expenses approval, reports |
| Manager | Read-only dashboard & reports |

RBAC workspace: `accounting` via `RbacGuard::workspace('accounting')`.

---

## Pages

| File | Feature |
|------|---------|
| `dashboard.php` | KPIs, trends, branch comparison |
| `chart_of_accounts.php` | Account hierarchy |
| `journal_entries.php` | Manual journals |
| `revenues.php` | Revenue analytics |
| `expenses.php` | Expense management |
| `cash_management.php` | Cash deposits/withdrawals |
| `bank_accounts.php` | Banking |
| `mobile_money.php` | Mobile money |
| `accounts_receivable.php` | AR |
| `accounts_payable.php` | AP |
| `inventory_accounting.php` | Stock valuation |
| `profit_loss.php` | P&L report |
| `balance_sheet.php` | Balance sheet |
| `cashflow.php` | Cash flow |
| `reports.php` | Report hub |
| `analytics.php` | Chart.js analytics |
| `audit_logs.php` | Audit trail |

---

## Offline (PWA)

- `accounting-offline.js` — IndexedDB queue (`retailpos_accounting`)
- Auto-sync on `online` event via `accounting/sync` API

---

## Chart of accounts (seed)

| Code | Account |
|------|---------|
| 1010 | Cash |
| 1020 | Bank Accounts |
| 1030 | Mobile Money |
| 1040 | Inventory |
| 1050 | Accounts Receivable |
| 2010 | Accounts Payable |
| 4010 | Product Sales |
| 5010–5090 | Expense categories |

See `014_accounting.sql` for full hierarchy.
