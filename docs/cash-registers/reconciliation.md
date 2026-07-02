# Réconciliation caisse — How It Works / Fonctionnement

**Path:** `public/cash-registers/reconciliation.php`  
**Purpose:** Review cash variances detected when register sessions are closed. Admins and managers approve or reject close-outs where physical cash differs from expected cash.

**Chemin :** `public/cash-registers/reconciliation.php`  
**Rôle :** Examiner les écarts de caisse détectés à la clôture de session. Les admins et managers approuvent ou rejettent les clôtures où l'espèces comptée diffère du montant attendu.

---

## Business context / Contexte métier

When a cash register session is closed, the system compares:

| Field | Source | Description |
|-------|--------|-------------|
| **Expected cash** (`expected_cash`) | System | Opening balance + cash sales during the session |
| **Physical cash** (`physical_cash`) | Cashier / admin | Amount counted in the drawer (`counted_cash` at close) |
| **Difference** (`difference`) | Computed | `physical_cash − expected_cash` (variance) |

**Tolerance:** `CashRegisterService::VARIANCE_TOLERANCE = 500` (FCFA by default).

- If `|difference| < 500` → reconciliation is **auto-approved** on session close.
- If `|difference| ≥ 500` → status **`pending`** — requires manual review on this page.

A notification is sent via `CashRegisterNotifier::cashDifferenceDetected()` when variance exceeds tolerance.

---

## Architecture flow / Flux d'architecture

```
Session close (registers.php modal, close_register.php, or API)
    └── CashRegisterService::closeSession()
            └── Creates row in cash_reconciliation
            └── status: approved (within tolerance) | pending (variance)

reconciliation.php
    └── includes/bootstrap.php
    └── cash-registers-reconciliation.js
            └── AdminAPI.getCashReconciliations()
                    └── GET cash-registers/reconciliation
                            └── CashRegisterService::listReconciliations()
            └── Card grid + filters (client-side)
            └── Approve / Reject modal
                    └── POST cash-registers/reconciliation/approve/{id}
                    └── POST cash-registers/reconciliation/reject/{id}
                            └── CashRegisterService::reviewReconciliation()
```

---

## 1. Server side (PHP) / Côté serveur

### Page shell

| UI block | Element IDs | Role |
|----------|-------------|------|
| Hero title | `#crReconHeroTitle` | Subtitle from `cr_recon_subtitle` i18n |
| Stats | `#crReconStatPending`, `#crReconStatApproved`, `#crReconStatRejected`, `#crReconStatVariance` | KPI cards |
| Search | `#crReconSearch` | Filter by register, cashier, branch, notes |
| Status chips | `#crReconFilters` | `all`, `pending`, `approved`, `rejected` |
| List | `#crReconRoot` | Reconciliation cards (JS-rendered) |
| Review modal | `#crReconModal` | Approve/reject with optional/required note |

**Page title (FR):** `cr_recon_title` → **Réconciliation caisse**

### Access / Accès

Same bootstrap as other cash register pages:

- **View:** admin, manager, super_admin
- **Approve / reject:** admin, manager, super_admin (via API; reviewer role stored as `admin_id` or `manager_id`)

---

## 2. Client side (JavaScript) / Côté client

**Main file:** `assets/js/admin/cash-registers-reconciliation.js`

### State

```javascript
state = {
    items: [],      // all reconciliations from API
    filter: 'all',  // status chip
    search: '',     // search box
}
```

### On load

1. `AdminAPI.getCashReconciliations()` → `GET cash-registers/reconciliation`
2. `renderList()` — card grid sorted with **pending first**, then by date desc
3. `updateStats()` — counts and total pending variance

### Client-side filtering

| Filter | Logic |
|--------|--------|
| Status chip | `r.status === filter` |
| Search | Matches `register_name`, `cashier_name`, `store_name`, `status`, `notes` |

### Variance styling (UI thresholds)

| Condition | CSS class | Meaning |
|-----------|-----------|---------|
| `|difference| ≥ 500` | `is-danger` | High variance |
| `|difference| > 0` | `is-warn` | Minor variance |
| Zero | `is-ok` | Balanced |

### Review workflow

1. Pending card → **Approve** or **Reject** button
2. Modal shows register, branch, cashier, expected / physical / difference
3. Submit:
   - **Approve:** note optional → `approveCashReconciliation(id, note)`
   - **Reject:** note **required** → `rejectCashReconciliation(id, note)`
4. Reload list on success; errors in `#crError` banner

---

## 3. API & backend / API et backend

### List reconciliations

```
GET /api/v1/index.php?request=cash-registers/reconciliation
GET ...?request=cash-registers/reconciliation&status=pending
```

Returns rows from `cash_reconciliation` joined with register, store, session cashier, and reviewer names.

### Approve / reject

```
POST cash-registers/reconciliation/approve/{id}
POST cash-registers/reconciliation/reject/{id}
Body: { "note": "..." }
```

**`CashRegisterService::reviewReconciliation()`:**

1. Validates decision (`approved` | `rejected`)
2. Updates row only if current status is **`pending`**
3. Sets `status`, `admin_id` or `manager_id`, note column, `reviewed_at`
4. Audit log: `reconciliation_approved` or `reconciliation_rejected`

### Creation (not on this page)

Reconciliations are **created automatically** in `closeSession()`:

```php
$reconId = $this->reconciliations->create([
    'expected_cash' => $expected,
    'physical_cash' => $counted,
    'difference'    => $variance,
    'status'        => abs($variance) < 500 ? 'approved' : 'pending',
]);
```

---

## 4. Database / Base de données

**Table:** `cash_reconciliation`

Key columns: `store_id`, `register_id`, `session_id`, `expected_cash`, `physical_cash`, `difference`, `status`, `notes`, `manager_id`, `admin_id`, `manager_note`, `admin_note`, `reviewed_at`, `created_at`.

**Status values:** `pending`, `approved`, `rejected`

---

## Key files / Fichiers clés

| Layer | File |
|-------|------|
| Page | `public/cash-registers/reconciliation.php` |
| Frontend | `assets/js/admin/cash-registers-reconciliation.js` |
| API client | `assets/js/admin/admin-api.js` |
| Controller | `includes/Controllers/CashRegisterController.php` (`reconciliation` case) |
| Service | `includes/CashRegister/Services/CashRegisterService.php` |
| Repository | `includes/CashRegister/Repositories/CashReconciliationRepository.php` |
| Notifier | `includes/CashRegister/CashRegisterNotifier.php` |
| Styles | `assets/css/admin-cash-registers.css` (`cr-recon-*` section) |
| i18n FR | `languages/fr/admin.php` — `cr_recon_*` keys |

---

## Related pages / Pages liées

| Page | Relationship |
|------|--------------|
| `close_register.php` | Full-page session close → creates reconciliation |
| `registers.php` | Inline close modal → same `closeSession()` flow |
| `dashboard.php` | Shows `pending_reconciliation` KPI |
| `logs.php` | Audit entries `reconciliation_approved`, `reconciliation_rejected` |
| Manager `operations/cash-reconciliation.php` | Manager-side reconciliation view |
