# Cash Registers Module — Documentation / Module caisses — Documentation

## Overview / Vue d'ensemble

The **Cash Registers module** lets admins and managers configure physical POS terminals, open/close cash sessions, track movements, reconcile cash, and audit activity per store.

Le **module caisses** permet aux admins et managers de configurer les terminaux POS, ouvrir/fermer les sessions de caisse, suivre les mouvements, réconcilier l'espèces et auditer l'activité par magasin.

| Layer | Path | Responsibility |
|-------|------|----------------|
| **Presentation** | `public/cash-registers/` | Caisses portal UI (standalone module) |
| **Legacy redirects** | `public/admin/cash_registers/` | HTTP redirects only (old bookmarks) |
| **Application** | `includes/CashRegister/Services/` | Business rules, orchestration |
| **Domain** | `includes/CashRegister/Repositories/` | Data access |
| **API** | `api/v1/index.php?request=cash-registers/...` | JSON endpoints for JS clients |
| **Assets** | `assets/css/admin-cash-registers.css`, `assets/js/admin/cash-registers-*.js` | Front-end clients |
| **Persistence** | `includes/Database/migrations/007_cash_registers.sql` | Schema |
| **i18n** | `languages/en/admin.php`, `languages/fr/admin.php` (`cr_*` keys) | UI strings |

---

## Admin pages / Pages admin

| Page | Path | Documentation |
|------|------|---------------|
| **Registers list** | `public/cash-registers/registers.php` | [registers.md](./registers.md) |
| Dashboard | `dashboard.php` | — |
| Create / edit register | `create_register.php`, `edit_register.php` | — |
| Shifts | `shift_management.php` | — |
| Open / close (full page) | `open_register.php`, `close_register.php` | — |
| Register details | `register_details.php` | — |
| Movements | `cash_movements.php` | — |
| Transfers | `cash_transfers.php` | — |
| **Reconciliation** | `reconciliation.php` | [reconciliation.md](./reconciliation.md) |
| **Reports** | `reports.php` | — |
| **Analytics** | `analytics.php` | [analytics.md](./analytics.md) |
| Audit logs | `logs.php` | — |

---

## Access / Accès

| Role | View registers | Create / edit / open / close |
|------|----------------|------------------------------|
| **Manager** | ✓ | — (read-only management actions) |
| **Admin** | ✓ | ✓ |
| **Super Admin** | ✓ | ✓ |

Bootstrap: `public/cash-registers/includes/bootstrap.php`  
Active store: `$_SESSION['active_store_id']` or `$_SESSION['store_id']` via `StoreScope::activeStoreId()`.

---

## API base / Base API

```
GET/POST  api/v1/index.php?request=cash-registers/{resource}/{action}
```

Controller: `includes/Controllers/CashRegisterController.php`  
Main service: `includes/CashRegister/Services/CashRegisterService.php`

| Request | Method | Description |
|---------|--------|-------------|
| `cash-registers/registers` | GET | List registers for active store |
| `cash-registers/registers/{id}` | GET | Single register + sessions + movements |
| `cash-registers/registers` | POST | Create register |
| `cash-registers/registers/open/{id}` | POST | Open session |
| `cash-registers/sessions/close/{id}` | POST | Close session |
| `cash-registers/reconciliation` | GET | List cash reconciliations |
| `cash-registers/reconciliation/approve/{id}` | POST | Approve pending reconciliation |
| `cash-registers/reconciliation/reject/{id}` | POST | Reject pending reconciliation |

---

## Prerequisites / Prérequis

| Check | Why / Pourquoi |
|-------|----------------|
| Migration `007_cash_registers.sql` applied | Tables `cash_registers`, `cash_register_sessions`, etc. |
| `CashRegisterSchema::ready()` returns true | UI shows migration hint if schema is missing |
| User logged in with allowed role | Redirect to login otherwise |

---

## Related docs / Docs associées

- [Registers page — how it works](./registers.md)
- [Réconciliation caisse — how it works](./reconciliation.md)
- [Notifications (cash register events)](../NOTIFICATIONS.md)
- [Manager supervision](../manager-supervision/ARCHITECTURE.md)
