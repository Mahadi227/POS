# Registers Page — How It Works / Page Caisses — Fonctionnement

**Path:** `public/cash-registers/registers.php`  
**Purpose:** Central hub to list all cash registers for the active store, view KPIs, filter/search, switch grid/table view, and open or close sessions inline.

**Chemin :** `public/cash-registers/registers.php`  
**Rôle :** Hub central pour lister les caisses du magasin actif, afficher les KPIs, filtrer/rechercher, basculer grille/tableau, et ouvrir/fermer les sessions sur place.

---

## Architecture flow / Flux d'architecture

```
registers.php
    └── includes/bootstrap.php          (auth, store, i18n)
    └── includes/layout-start.php       (shell, sidebar, ADMIN_I18N)
    └── HTML shell (hero, toolbar, modals, #crRegistersRoot)
    └── includes/layout-end.php         (ADMIN_PAGE, AdminAPI scripts)
            └── cash-registers-registers.js
                    └── AdminAPI.getCashRegisters()
                            └── GET cash-registers/registers
                                    └── CashRegisterController
                                            └── CashRegisterService::listRegisters()
                                                    └── CashRegisterRepository::list()
                                                    └── enrichRegister()
                    └── render grid / table (client-side filters)
                    └── modals → open/close session APIs
```

---

## 1. Server side (PHP) / Côté serveur

### Bootstrap & access / Bootstrap et accès

`registers.php` loads `includes/bootstrap.php`, which:

- Requires login and role **admin**, **manager**, or **super_admin**
- Resolves the **active store** from session (`active_store_id` / `store_id`)
- Sets `$canManageRegisters = true` only for **admin** and **super_admin** (managers can view but not create, edit, open, or close)

### Page shell / Structure de la page

The PHP file outputs static HTML only; data is loaded by JavaScript:

| UI block | Element IDs | Role |
|----------|-------------|------|
| Hero stats | `#crRegStatTotal`, `#crRegStatOpen`, `#crRegStatClosed`, `#crRegStatBalance` | KPI placeholders filled by JS |
| Search | `#crRegSearch` | Client-side text filter |
| Filter chips | `#crRegFilters` | Session/status filters |
| View toggle | `.cr-reg-view-btn` | Grid vs table (`localStorage`: `cr-reg-view`) |
| List root | `#crRegistersRoot` | Render target |
| Open modal | `#crOpenModal`, `#crOpenModalForm` | Opening balance + shift type |
| Close modal | `#crCloseModal`, `#crCloseModalForm` | Counted cash vs expected |

Translations are merged into `window.ADMIN_I18N` via `layout-end.php` (`cr_i18n()` + common keys).

Admin-only toolbar links:

- `create_register.php` — new register
- `open_register.php` / `close_register.php` — dedicated full-page flows

---

## 2. Client side (JavaScript) / Côté client

**Main file:** `assets/js/admin/cash-registers-registers.js`  
**Shared helpers:** `assets/js/admin/cash-registers-common.js` (`CashRegistersUI`: i18n, money, errors)

### State / État

```javascript
state = {
    items: [],           // all registers from API
    filter: 'all',       // chip filter
    search: '',          // search box
    view: 'grid' | 'table'  // persisted in localStorage
}
```

### On load / Au chargement

1. `AdminAPI.getCashRegisters()` → `GET cash-registers/registers`
2. Result stored in `state.items`
3. `renderList()` builds grid or table
4. `updateHeroStats()` computes KPIs from full list
5. `CashRegisterOffline.sync()` — offline cache (shared module)
6. Header refresh fires `cr:refresh` → `load(true)` (silent reload)

### Client-side filtering / Filtres côté client

Filters run in the browser — **no extra API call** when searching or changing chips:

| Filter | Logic |
|--------|--------|
| **Search** | Matches `register_code`, `name`, `store_name`, `assigned_cashier` |
| **Session open** | `session_status === 'open'` |
| **Session closed** | `session_status !== 'open'` |
| **Active** | `status === 'active'` |
| **Inactive** | `status !== 'active'` |

Hero stats use **all** registers; the count label (`#crRegCount`) reflects **filtered** count.

### Hero stats (computed client-side) / KPIs

| Stat | Calculation |
|------|-------------|
| Total | `items.length` |
| Open sessions | `session_status === 'open'` |
| Closed | total − open |
| Cash balance | sum of `current_balance` |

---

## 3. API & backend / API et backend

### List registers / Lister les caisses

```
GET /api/v1/index.php?request=cash-registers/registers
```

Optional query: `?status=active|inactive|maintenance`

**Chain:** `CashRegisterController` → `CashRegisterService::listRegisters($storeId)` → `CashRegisterRepository::list()`

SQL joins `stores`, `users`, and subqueries for open session:

```sql
SELECT r.*, s.name AS store_name, u.name AS assigned_cashier,
       (SELECT COUNT(*) FROM cash_register_sessions crs
        WHERE crs.register_id = r.id AND crs.status = 'open') AS is_session_open,
       (SELECT crs.id FROM cash_register_sessions crs
        WHERE crs.register_id = r.id AND crs.status = 'open'
        ORDER BY crs.opened_at DESC LIMIT 1) AS open_session_id
FROM cash_registers r
INNER JOIN stores s ON s.id = r.store_id
LEFT JOIN users u ON u.id = r.assigned_user_id
WHERE r.deleted_at IS NULL
  AND r.store_id = ?   -- when store scope is set
```

`enrichRegister()` adds:

- `session_status`: `'open'` | `'closed'`
- `open_session_id`: ID of current open session (or null)
- Rounded `current_balance`, `opening_balance`

### Open session / Ouvrir une session

**Trigger:** Admin clicks **Open register** on an active register with no open session.

```
POST cash-registers/registers/open/{registerId}
Body: { "opening_balance": 0, "shift_type": "morning" }
```

**`CashRegisterService::openSession()` steps:**

1. Validate register exists, is **active**, has **no open session**
2. Insert row in `cash_register_sessions`
3. Update register `current_balance`
4. Record cash movement (`movement_type: opening_cash`)
5. Audit log (`session_opened`) via `CashRegisterLogRepository`

Shift types: `morning`, `afternoon`, `evening`, `night`.

### Close session / Fermer une session

**Trigger:** Admin clicks **Close register** when `open_session_id` is set.

```
POST cash-registers/sessions/close/{sessionId}
Body: { "counted_cash": 12345.00 }
```

**`CashRegisterService::closeSession()` steps:**

1. Load open session; verify store access
2. **Expected cash** = `opening_balance + cash_sales`
3. **Variance** = `counted_cash − expected`
4. Close session row (totals, variance, closed_by)
5. Update register `current_balance` to counted amount
6. Record closing movement; audit log (`session_closed`)
7. If variance exceeds tolerance (`VARIANCE_TOLERANCE = 500`), reconciliation/notification may apply

The close modal pre-fills `counted_cash` with `current_balance` and shows expected amount.

---

## 4. UI actions per register / Actions par caisse

Each card (grid) or row (table) displays:

- Register code, name, store (branch)
- Assigned cashier
- Register status: `active` | `inactive` | `maintenance`
- Session status: open / closed
- Current balance

| Action | Who | Condition | Target |
|--------|-----|-----------|--------|
| **View details** | All roles | Always | `register_details.php?id={id}` |
| **Edit** | Admin, super_admin | Always | `edit_register.php?id={id}` |
| **Open** | Admin, super_admin | Active + session closed | Inline modal → API |
| **Close** | Admin, super_admin | Session open | Inline modal → API |

---

## 5. Errors & migration hint / Erreurs et migration

| Signal | Source |
|--------|--------|
| `#crError` banner | `CashRegistersUI.showError()` on API failure |
| `#crMigrationHint` | Shown when `module_ready === false` (schema not migrated) |
| Empty state | `#crRegistersRoot` → link to `create_register.php` (admins only) |
| Filter empty | Message to clear search / change filters |

---

## Key files / Fichiers clés

| Layer | File |
|-------|------|
| Page | `public/cash-registers/registers.php` |
| Bootstrap | `public/cash-registers/includes/bootstrap.php` |
| Layout | `layout-start.php`, `layout-end.php` |
| Frontend | `assets/js/admin/cash-registers-registers.js` |
| Shared UI | `assets/js/admin/cash-registers-common.js` |
| API client | `assets/js/admin/admin-api.js` (`getCashRegisters`, `openCashRegisterSession`, `closeCashSession`) |
| Controller | `includes/Controllers/CashRegisterController.php` |
| Service | `includes/CashRegister/Services/CashRegisterService.php` |
| Repository | `includes/CashRegister/Repositories/CashRegisterRepository.php` |
| Sessions | `includes/CashRegister/Repositories/CashRegisterSessionRepository.php` |
| Styles | `assets/css/admin-cash-registers.css` |

---

## Related pages / Pages liées

| Page | Relationship |
|------|--------------|
| `create_register.php` | POST `cash-registers/registers` — new register metadata |
| `edit_register.php` | PUT `cash-registers/registers/{id}` |
| `open_register.php` / `close_register.php` | Full-page alternatives to inline modals |
| `register_details.php` | Single register: sessions, movements, history |
| `logs.php` | Audit trail (`cash_register_logs`) |

---

## POS integration (downstream) / Intégration POS

When a cashier completes a sale on `public/cashier/pos.php` with an open register session:

- `CashRegisterService::recordSaleToSession()` increments session totals (`cash_sales`, `total_sales`, etc.)
- Register `current_balance` is updated through the sales/checkout flow

See cashier POS documentation when available.
