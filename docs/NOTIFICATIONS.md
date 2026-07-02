# Enterprise Notification System — Guide / Guide des notifications

## Overview / Vue d'ensemble

The **Enterprise Notification System** centralizes in-app (and optional browser/email) alerts across POS, cash registers, warehouse (WMS), and security modules. Notifications are stored per user in the database and surfaced via the **admin bell**, the **notification center**, and the REST API.

Le **système de notifications entreprise** centralise les alertes in-app (et optionnellement navigateur/e-mail) pour le POS, les caisses, l'entrepôt (WMS) et la sécurité. Les notifications sont enregistrées par utilisateur en base et affichées via la **cloche admin**, le **centre de notifications** et l'API REST.

| Layer | Path | Responsibility |
|-------|------|----------------|
| **Presentation** | `public/notifications/` | Notification center, preferences, analytics, logs |
| **Admin UI** | `public/admin/includes/notification-bell.php` | Bell + bottom sheet on admin dashboard |
| **Application** | `includes/Notifications/NotificationManager.php` | Central dispatcher |
| **Integration** | `CashRegisterNotifier`, `WmsNotifier`, `NotificationEvents` | Module-specific triggers |
| **API** | `api/v1/index.php?request=notifications/...` | List, read, archive, preferences, admin send |
| **Assets** | `assets/js/notifications/`, `assets/css/notifications.css` | Front-end clients |
| **Persistence** | `includes/Database/migrations/010_notifications.sql` | Schema + templates |
| **i18n** | `languages/en/notifications.php`, `languages/fr/notifications.php` | UI strings |

---

## Where notifications appear / Où les voir

| Location | Path | Notes |
|----------|------|-------|
| **Admin bell** | Admin dashboard header | Polls API every ~20 s (`notification-bell.js`) |
| **Notification center** | `/public/notifications/notification_center.php` | Full inbox: tabs, search, filters |
| **Preferences** | `/public/notifications/preferences.php` | Enable/disable channels per category |
| **Analytics** (admin) | `/public/notifications/analytics.php` | Delivery stats |
| **Browser popup** | — | Only if channel `browser` is enabled and permission granted |

**English:** You must be logged in as a user whose **role** and **store_id / warehouse_id** match the event scope.

**Français :** Il faut être connecté avec un **rôle** et un **store_id / warehouse_id** compatibles avec l'événement.

---

## Prerequisites / Prérequis

| Check | Why / Pourquoi |
|-------|----------------|
| Migration `010_notifications.sql` applied | Tables `notifications`, `notification_templates`, etc. must exist |
| Run `tools/fix_notification_schema.php` once if tables are missing | Bootstraps schema via `NotificationSchemaMigrator` |
| User role matches event targets | e.g. cash register → `admin`, `manager`, `super_admin` |
| User `store_id` / `warehouse_id` matches event | Cross-branch users may be filtered out |
| Channel **in_app** enabled in preferences | `/public/notifications/preferences.php` |

---

## Dispatch flow / Flux d'envoi

```
Business action (caisse, entrepôt, auth…)
        │
        ▼
CashRegisterNotifier / WmsNotifier / AuthController / NotificationEvents
        │
        ▼
NotificationManager::dispatch() or ::notifyUser()
        │
        ├── Resolve recipients (roles + store_id + warehouse_id)
        ├── Render template (EN/FR from notification_templates)
        ├── INSERT into notifications table
        └── NotificationDeliveryService (in_app, browser, email queue)
        │
        ▼
Bell polls API (~20 s)  →  User sees notification
```

**Key files:**

- `includes/Notifications/NotificationManager.php` — central dispatcher
- `includes/Notifications/NotificationEvents.php` — generic helpers (POS, inventory, system)
- `includes/CashRegister/CashRegisterNotifier.php` — cash register events
- `includes/Wms/WmsNotifier.php` — warehouse events
- `includes/Controllers/AuthController.php` — failed login / account lock

---

## Currently wired triggers / Déclencheurs actuellement branchés

These operations **send notifications today** (code calls `NotificationManager`).

Ces opérations **envoient des notifications aujourd'hui** (le code appelle `NotificationManager`).

### Cash registers / Caisses enregistreuses

**Source:** `CashRegisterService` → `CashRegisterNotifier`  
**Recipients / Destinataires:** `admin`, `manager`, `super_admin` (scoped to `store_id`)

| Action (EN) | Action (FR) | Condition |
|-------------|-------------|-----------|
| Open register session | Ouvrir une session de caisse | Session opened with opening balance |
| Close register session | Fermer une session de caisse | Session closed |
| Cash difference detected | Écart de caisse détecté | Variance ≥ **500 FCFA** at closing |
| Large cash transfer | Gros transfert de fonds | Transfer amount ≥ **100 000 FCFA** |

**How to test / Comment tester:**

1. Log in as **admin** on the same store as the register.
2. Go to **Admin → Cash registers** → open a session.
3. Wait ~20 s or open **Notification center**.
4. Expected: *Cash register opened* notification.

### Warehouse (WMS) / Entrepôt

**Source:** `WmsService` → `WmsNotifier`  
**Recipients / Destinataires:** `admin`, `manager`, `warehouse_manager`, `inventory_officer`, `super_admin` (scoped to `warehouse_id`)

| Action (EN) | Action (FR) | When / Quand |
|-------------|-------------|--------------|
| Transfer approved | Transfert approuvé | `approveTransfer()` or `completeTransfer()` |
| Transfer rejected | Transfert rejeté | `rejectTransfer()` |
| Incoming delivery | Livraison entrante | `createReceipt()` — GRN created |
| Purchase received | Réception finalisée | `completeReceipt()` — goods received |

**How to test / Comment tester:**

1. Log in as **admin** or **warehouse_manager** linked to the warehouse.
2. **Admin → Warehouse** → approve a transfer or complete a goods receipt.
3. Check bell or notification center.

### Security / Auth / Sécurité

**Source:** `AuthController` (failed login handler)  
**Recipient / Destinataire:** the **affected user only** (`notifyUser`)

| Action (EN) | Action (FR) | Condition |
|-------------|-------------|-----------|
| Failed login | Échec de connexion | Each failed attempt for a known user |
| Account locked | Compte verrouillé | After **5** failed attempts (15 min lock) |

### POS / Sales / Ventes

**Source:** `SalesController::processCheckout()`, `SyncController::processOfflineSale()` → `NotificationEvents::posCheckout()`

| Action (EN) | Action (FR) | Recipients | Condition |
|-------------|-------------|------------|-----------|
| Sale completed | Vente terminée | manager, admin, super_admin | Every checkout (online or offline sync) |
| Large sale | Vente importante | manager, admin, super_admin | Total ≥ **100 000 FCFA** |

### Inventory / Stock / Inventaire

**Source:** `StockAlertNotifier` (via `InventoryLedgerHelper`, `InventoryController::adjustStock()`, `WmsLedgerHelper`)

| Action (EN) | Action (FR) | Recipients | Condition |
|-------------|-------------|------------|-----------|
| Low stock (store) | Stock faible (magasin) | admin, manager, inventory_officer, warehouse_manager, super_admin | `stock_quantity` crosses into ≤ `min_stock_level` (default 5) |
| Out of stock (store) | Rupture (magasin) | same | Stock reaches **0** |
| Low stock (warehouse) | Stock faible (entrepôt) | same | `warehouse_inventory.quantity` crosses into ≤ `reorder_level` |
| Out of stock (warehouse) | Rupture (entrepôt) | same | Warehouse qty reaches **0** |
| Stock adjustment | Ajustement manuel | same | Admin inventory adjustment crosses thresholds |

Alerts fire only when stock **crosses** a threshold (not on every sale while already low).

### Refunds / Remboursements

**Source:** `ReturnApprovalService::processReturnTransaction()` → `CashRegisterNotifier::largeRefund()`

| Action (EN) | Action (FR) | Recipients | Condition |
|-------------|-------------|------------|-----------|
| Large refund | Gros remboursement | admin, manager, super_admin | Approved return ≥ **50 000 FCFA** |

### Offline sync / Sync hors ligne

**Source:** `SyncController::pushData()`, `CashRegisterService::syncOfflineMovements()`, `WmsService::syncOffline()`

| Action (EN) | Action (FR) | Recipient |
|-------------|-------------|-----------|
| Offline sync complete | Sync hors ligne terminée | User who triggered the sync |

### Register deactivated / Caisse désactivée

**Source:** `CashRegisterService::deleteRegister()` → `CashRegisterNotifier::registerInactive()`

| Action (EN) | Action (FR) | Recipients |
|-------------|-------------|------------|
| Register deactivated | Caisse désactivée | admin, manager, super_admin |

---

## Integration map / Carte d'intégration

| File | Trigger |
|------|---------|
| `includes/Controllers/SalesController.php` | POS checkout + stock via ledger |
| `includes/Controllers/SyncController.php` | Offline sales push + sync notification |
| `includes/Controllers/InventoryController.php` | Manual stock adjustments |
| `includes/Helpers/InventoryLedgerHelper.php` | Stock alerts after ledger movements |
| `includes/Helpers/WmsLedgerHelper.php` | Warehouse stock alerts |
| `includes/Manager/Services/ReturnApprovalService.php` | Large refund after approved return |
| `includes/Notifications/StockAlertNotifier.php` | Threshold-crossing stock logic |
| `includes/Notifications/NotificationEvents.php` | POS, inventory, offline helpers |

---

## Recipient resolution / Résolution des destinataires

`NotificationManager::resolveRecipients()`:

1. If `user_ids` is set → notify those users only.
2. Else → query `users` joined with `roles` where role slug is in `roles[]`.
3. Filter by `store_id` (user `store_id`, `branch_id`, or NULL).
4. Filter by `warehouse_id` (user `warehouse_id` or NULL).

Users must be **active** (`status = 'active'` or `is_active = 1`) and not deleted.

---

## API reference (summary) / API (résumé)

Base: `api/v1/index.php?request=notifications/{action}`

| Action | Method | Description |
|--------|--------|-------------|
| `list` | GET/POST | Paginated notifications for current user |
| `unread-count` | GET | Badge count |
| `mark-read` | POST | Mark one or all as read |
| `archive` | POST | Archive notifications |
| `pin` | POST | Pin / unpin |
| `delete` | POST | Soft delete |
| `preferences` | GET/POST | User channel preferences |
| `send` | POST | **Admin only** — manual dispatch / test |
| `process-queue` | POST | **Admin only** — process email queue |

### Manual test (admin) / Test manuel (admin)

Admins can send a test notification:

```http
POST /api/v1/index.php?request=notifications/send
Content-Type: application/json

{
  "template": "cash_register.opened",
  "roles": ["admin"],
  "store_id": 1,
  "params": {
    "register": "Caisse 1",
    "amount": "50 000 FCFA"
  },
  "channels": ["in_app", "browser"]
}
```

---

## UI & theme / Interface et thème

| File | Purpose |
|------|---------|
| `public/notifications/notification_center.php` | Main inbox (light/dark via `app-theme.js`) |
| `assets/js/app-theme.js` | Shared theme (`app-theme` localStorage key) |
| `assets/css/notifications.css` | Notification page styles + dark mode |
| `assets/js/notifications/notification-bell.js` | Admin bell + mobile bottom sheet |

Theme preference syncs with the admin dashboard (`app-theme`, `admin-theme` legacy keys).

---

## Database / Base de données

- **Migration:** `includes/Database/migrations/010_notifications.sql`
- **Migrator:** `includes/Notifications/NotificationSchemaMigrator.php` (auto-runs on first dispatch)
- **Repair tool:** `php tools/fix_notification_schema.php` (CLI)

Main tables: `notifications`, `notification_templates`, `notification_categories`, `notification_types`, `notification_preferences`, `notification_queue`, `notification_logs`.

---

## Related documentation / Documentation associée

- Cash registers: [docs/cash-registers/README.md](./cash-registers/README.md) — module overview; [registers page](./cash-registers/registers.md) — how `registers.php` operates (`includes/CashRegister/`, migration `007_cash_registers.sql`)
- Warehouse WMS: `includes/Wms/` + migration `008_wms.sql`
- RBAC roles: `includes/Database/migrations/009_rbac_enterprise.sql`
- i18n: `docs/I18N_README.md`

---

## Future integration checklist / Extensions possibles

| Feature | Suggested hook |
|---------|----------------|
| Transfer request pending approval | `WmsService::createTransfer()` |
| Accounting expense / invoice overdue | Accounting module controllers |
| User role changed | User management controller |
| Security alert (custom) | `NotificationEvents::securityAlert()` |

Example:

```php
NotificationEvents::securityAlert($userId, 'Suspicious activity detected', 'Activité suspecte détectée');
```
