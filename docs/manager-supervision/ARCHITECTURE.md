# Manager Supervision — Enterprise Architecture

## Overview / Vue d'ensemble

The **Manager Supervision Module** is a dedicated operational layer between **Admin** (configuration) and **Cashier** (transactions). Managers supervise live registers, approve exceptions, monitor shifts, and review store performance without full system administration.

| Layer | Path | Responsibility |
|-------|------|----------------|
| **Presentation** | `public/manager/` | Manager UI pages & workflows |
| **Application** | `includes/Manager/Services/` | Business rules, orchestration |
| **Domain** | `includes/Manager/Repositories/` | Data access |
| **API** | `api/v1/index.php?request=manager/...` | JSON endpoints for SPA/JS |
| **Assets** | `assets/css/manager/`, `assets/js/manager/` | Front-end clients |
| **Persistence** | `includes/Database/migrations/005_manager_supervision.sql` | Approvals, shifts, audit |

---

## Workflow Domains

```
┌─────────────────────────────────────────────────────────────────┐
[object Object]                    MANAGER SUPERVISION WORKFLOW                      │
├──────────────┬──────────────┬──────────────┬─────────────────────┤
│  SUPERVISION │  APPROVALS   │  OPERATIONS  │  REPORTS            │
│  Live POS    │  Returns     │  Stock alerts│  Daily summary      │
│  Shifts      │  Discounts   │  Cash recon  │  Audit trail        │
│  Team KPIs   │  Voids       │  Sales review│                     │
└──────┬───────┴──────┬───────┴──────┬───────┴──────────┬──────────┘
       │              │              │                  │
       └──────────────┴──────────────┴──────────────────┘
                              │
                    StoreScope (store_id filter)
                    ManagerAuth (role + permissions)
```

### 1. Supervision (`public/manager/supervision/`)
- **Live registers** — heartbeat from cashiers, online/offline status
- **Shifts** — open/close shift, float, handover
- **Team performance** — sales by cashier, avg ticket, returns rate

### 2. Approvals (`public/manager/approvals/`)
- **Queue hub** — pending items requiring manager action
- **Returns** — refund above threshold
- **Discounts** — override beyond cashier limit
- **Voids** — cancelled sale after payment

### 3. Operations (`public/manager/operations/`)
- **Inventory alerts** — low stock, expiry (read + escalate)
- **Cash reconciliation** — expected vs counted
- **Sales review** — flagged transactions

### 4. Reports (`public/manager/reports/`)
- **Daily summary** — store day-end rollup
- **Audit trail** — manager actions log

---

## Role & Access Matrix

| Capability | Cashier | Manager | Admin | Super Admin |
|------------|---------|---------|-------|-------------|
| POS sales | ✓ | ✓ | ✓ | ✓ |
| Manager dashboard | — | ✓ | ✓ | ✓ |
| Approve returns/voids | — | ✓ | ✓ | ✓ |
| Live supervision | — | ✓ | ✓ | ✓ |
| User management | — | — | partial | ✓ |
| Multi-store switch | — | own store(s) | ✓ | all |

**Entry point:** `public/manager/index.php`  
**Login redirect:** Manager role → `public/manager/index.php` (not admin)

---

## API Convention

Base: `GET/POST ../../api/v1/index.php?request=manager/{resource}/{action}`

| Request | Method | Description |
|---------|--------|-------------|
| `manager/dashboard` | GET | KPIs, pending approvals count |
| `manager/supervision/live` | GET | Active cashiers / registers |
| `manager/supervision/shifts` | GET/POST | List / open / close shifts |
| `manager/approvals` | GET | Pending approval queue |
| `manager/approvals/{id}` | POST | Approve or reject |
| `manager/reports/daily` | GET | Daily summary |
| `manager/audit` | GET | Audit log |

Client: `assets/js/manager/manager-api.js` → `ManagerAPI`

---

## Integration Points

| Existing module | Integration |
|-----------------|-------------|
| `CashierController` | Sync heartbeat → supervision live view |
| `SalesController` | Void/return triggers approval record |
| `InventoryController` | Low stock feeds operations alerts |
| `StoreScope` | All manager queries scoped by store |
| `AuthMiddleware` | API routes: `manager`, `admin`, `super_admin` |

---

## Deployment Checklist

1. Run migration: `005_manager_supervision.sql`
2. Verify manager login redirects to `public/manager/`
3. Configure approval thresholds in store settings (future)
4. Enable cashier heartbeat (already in `pos-app.js` sync)

---

## Related Documents

- [FOLDER-STRUCTURE.md](./FOLDER-STRUCTURE.md) — complete directory tree
- [../MULTI_STORE.md](../../MULTI_STORE.md) — store scoping
