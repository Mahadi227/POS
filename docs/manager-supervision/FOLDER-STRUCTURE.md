# Manager Supervision — Enterprise Folder Structure

```
Pos system/
│
├── docs/manager-supervision/
│   ├── ARCHITECTURE.md              # Workflow & integration design
│   └── FOLDER-STRUCTURE.md          # This file
│
├── public/manager/                  # ★ Manager UI (presentation layer)
│   ├── index.php                    # Supervision dashboard (home)
│   │
│   ├── includes/                    # Shared PHP partials
│   │   ├── auth-guard.php           # Session + role gate
│   │   ├── manager-config.php       # Page config (store, user, API base)
│   │   ├── sidebar.php              # Navigation (supervision workflows)
│   │   ├── layout-start.php         # HTML head + sidebar + main open
│   │   └── layout-end.php           # Scripts + close tags
│   │
│   ├── supervision/                 # Live oversight
│   │   ├── live-registers.php       # Active terminals / cashiers
│   │   ├── shifts.php               # Shift open/close & handover
│   │   └── team-performance.php     # Cashier KPIs
│   │
│   ├── approvals/                   # Exception handling queue
│   │   ├── index.php                  # Approval queue hub
│   │   ├── returns.php              # Return/refund approvals
│   │   ├── discounts.php            # Discount override queue
│   │   └── voids.php                # Void / cancel approvals
│   │
│   ├── operations/                  # Day-to-day ops
│   │   ├── inventory-alerts.php     # Stock & expiry alerts
│   │   ├── cash-reconciliation.php  # Drawer reconciliation
│   │   └── sales-review.php         # Flagged transactions
│   │
│   └── reports/                     # Manager reporting
│       ├── daily-summary.php        # End-of-day rollup
│       └── audit-trail.php          # Manager action log
│
├── includes/Manager/                # ★ Application layer (PHP)
│   ├── ManagerAuth.php              # Role + permission helpers
│   │
│   ├── Services/                    # Business logic
│   │   ├── ApprovalService.php      # Approve/reject workflow
│   │   ├── ShiftService.php         # Shift lifecycle
│   │   ├── SupervisionService.php   # Live register aggregation
│   │   └── AuditService.php         # Manager audit logging
│   │
│   ├── Repositories/                # Data access
│   │   ├── ApprovalRepository.php
│   │   └── ShiftRepository.php
│   │
│   └── DTOs/                        # Data transfer objects (future)
│       └── .gitkeep
│
├── includes/Controllers/
│   └── ManagerController.php        # ★ API: manager/* routes
│
├── includes/Database/migrations/
│   └── 005_manager_supervision.sql  # approvals, shifts, audit tables
│
├── assets/
│   ├── css/manager/
│   │   ├── manager-layout.css       # Layout + sidebar (extends admin)
│   │   ├── manager-dashboard.css    # Dashboard widgets
│   │   ├── supervision.css          # Live registers, shifts
│   │   └── approvals.css            # Approval queue UI
│   │
│   └── js/manager/
│       ├── manager-api.js           # API client (ManagerAPI)
│       ├── manager-dashboard.js     # Dashboard page logic
│       ├── supervision-live.js      # Live registers polling
│       ├── approvals-queue.js       # Approval actions
│       └── shifts.js                # Shift management
│
└── api/v1/
    └── index.php                    # Route: case 'manager' → ManagerController
```

## Naming Conventions

| Type | Pattern | Example |
|------|---------|---------|
| Page | `{workflow}/{feature}.php` | `approvals/returns.php` |
| Service | `{Domain}Service.php` | `ApprovalService.php` |
| API action | `manager/{domain}/{action}` | `manager/approvals/list` |
| CSS | `manager-{area}.css` | `manager-dashboard.css` |
| JS | `{area}-{feature}.js` | `supervision-live.js` |

## Page Bootstrap Pattern

Every manager page follows:

```php
<?php
$pageTitle   = 'Titre';
$activePage  = 'approvals';      // sidebar highlight
$pageScripts = ['approvals-queue.js'];
require __DIR__ . '/../includes/auth-guard.php';
require __DIR__ . '/../includes/layout-start.php';
?>
<!-- page content -->
<?php require __DIR__ . '/../includes/layout-end.php'; ?>
```

## Role Redirect Map

| Role | Default URL after login |
|------|-------------------------|
| manager | `public/manager/index.php` |
| admin | `public/admin/index.php` |
| super_admin | `public/admin/index.php` |
| cashier | `public/cashier/dashboard.php` |
