# Warehouse Portal

Single entry point for all warehouse operations — same pattern as [Accounting](../accounting/README.md).

## URL

| Role | Entry |
|------|--------|
| Warehouse staff | `/public/warehouse/` |
| Admin / Manager | `/public/warehouse/` (from admin sidebar → **Entrepôts**) |

Login remains **`/public/login.php`** — no separate warehouse login.

## Structure

```
public/warehouse/
├── dashboard.php          # KPI dashboard
├── profile.php, notifications.php, calendar.php, settings.php, help.php
├── inventory/             # Stock, scanner, ledger, count
├── receiving/               # GRN, PO, inspection
├── dispatch/                # Pick, pack, ship
├── transfers/               # Inter-warehouse / branch
├── batch/                   # Lots, serials, FIFO/FEFO, expiry
├── reports/                 # Export / print reports
├── management/              # Admin-only: warehouses, locations, logs, sync
└── includes/
    ├── bootstrap.php        # RBAC workspace guard + nav
    ├── layout-start.php
    ├── layout-end.php
    └── page-shell.php       # Generic module pages (simple tables)
```

## Legacy path (redirects only)

**Do not add features under `public/admin/warehouse/`.**

That folder keeps **HTTP redirects** to `public/warehouse/*` for old bookmarks.

## API

- `GET api/v1/index.php?request=warehouse/dashboard` — portal dashboard
- `GET api/v1/index.php?request=warehouse/search&q=` — global search
- `GET api/v1/index.php?request=wms/...` — shared WMS backend (inventory, receipts, dispatch, …)

## Roles

| Role | Access |
|------|--------|
| Warehouse Manager | Full portal + management |
| Inventory Officer | Inventory + locations |
| Receiving Officer | Receiving |
| Dispatch Officer | Dispatch |
| Warehouse Auditor | Read-only |
| Storekeeper | Inventory operations |
| Admin / Manager | Full portal (via sidebar link) |

## i18n

- Portal chrome: `languages/{en,fr}/warehouse.php`
- WMS field labels: `languages/{en,fr}/wms.php`

## Migrations

- `008_wms.sql` — core WMS tables
- `015_warehouse_portal.sql` — tasks, location hierarchy, extra roles
