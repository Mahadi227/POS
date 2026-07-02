# Volume 7 — API & Integrations

**Blueprint:** RetailPOS Enterprise v1.0  
**Statut:** Draft

---

## 1. Objectif

Définir la stratégie API REST, le versioning, les webhooks, et les intégrations tierces pour RetailPOS Cloud — en évoluant depuis `api/v1/index.php` monolithique.

---

## 2. État actuel API v1

### 2.1 Router

**Fichier :** `api/v1/index.php`  
**Pattern :** `?request={resource}/{action}/{id}`  
**Auth :** Session cookie PHP  
**Format :** JSON

### 2.2 Ressources existantes

| Resource | Controller | Rôles API |
|----------|------------|-----------|
| `auth` | `AuthController` | Public |
| `sales` | `SalesController` | Session |
| `cashier` | `CashierController` | Session |
| `dashboard` | `DashboardController` | admin, manager |
| `reports` | `ReportsController` | Session |
| `inventory` | `InventoryController` | Session |
| `stores` | `StoresController` | admin, manager, cashier |
| `users` | `UsersController` | Session |
| `sync` | `SyncController` | Session |
| `manager` | `ManagerController` | manager, admin |
| `cash-registers` | `CashRegisterController` | admin, manager |
| `wms` | `WmsController` | WMS roles |
| `warehouse` | `WarehousePortalController` | WMS roles |
| `notifications` | `NotificationController` | Session |
| `accounting` | `AccountingController` | accountant, admin |

### 2.3 Limites v1

- Pas de versioning URL propre
- Pas de OpenAPI spec
- Pas de pagination standard
- Pas de rate limiting
- Auth session only (pas JWT)
- Pas de webhooks sortants
- CORS basique

---

## 3. API v2 — Design cible

### 3.1 Conventions REST

| Aspect | Standard |
|--------|----------|
| Base URL | `https://api.retailpos.cloud/v2/` |
| Auth | `Authorization: Bearer {jwt}` ou `X-API-Key` |
| Tenant | `X-Tenant-ID: {uuid}` ou subdomain |
| Content-Type | `application/json` |
| Pagination | `?page=1&per_page=50` + header `X-Total-Count` |
| Sorting | `?sort=-created_at` |
| Filtering | `?filter[status]=active&filter[store_id]=1` |
| Errors | RFC 7807 Problem Details |
| Idempotency | `Idempotency-Key` header (POST payments) |

### 3.2 Structure réponse

```json
{
  "data": { ... },
  "meta": {
    "page": 1,
    "per_page": 50,
    "total": 234
  }
}
```

### 3.3 Structure erreur

```json
{
  "type": "https://api.retailpos.cloud/errors/validation",
  "title": "Validation Error",
  "status": 422,
  "detail": "SKU already exists",
  "errors": {
    "sku": ["must be unique per store"]
  }
}
```

---

## 4. Endpoints v2 (prioritaires)

### 4.1 Core

| Method | Path | Description |
|--------|------|-------------|
| POST | `/auth/token` | Exchange credentials → JWT |
| POST | `/auth/refresh` | Refresh token |
| GET | `/me` | Current user + tenant + permissions |
| GET | `/stores` | List stores |
| POST | `/stores/switch` | Change active store context |

### 4.2 Sales / POS

| Method | Path | Description |
|--------|------|-------------|
| GET | `/sales` | List sales |
| POST | `/sales` | Create sale |
| GET | `/sales/{id}` | Sale detail |
| POST | `/sales/{id}/void` | Void sale |
| POST | `/returns` | Process return |
| GET | `/products` | Product catalog (scoped) |
| GET | `/products/barcode/{code}` | Lookup by barcode |

### 4.3 Inventory & WMS

| Method | Path | Description |
|--------|------|-------------|
| GET | `/inventory/levels` | Stock levels |
| POST | `/inventory/adjustments` | Stock adjustment |
| GET | `/transfers` | List transfers |
| POST | `/transfers` | Request transfer |
| POST | `/transfers/{id}/approve` | Approve |
| GET | `/warehouses` | List warehouses |
| POST | `/goods-receipts` | Receive stock |

### 4.4 Accounting

| Method | Path | Description |
|--------|------|-------------|
| GET | `/accounting/accounts` | Chart of accounts |
| GET | `/journal-entries` | Journal entries |
| POST | `/journal-entries` | Manual entry |
| GET | `/reports/profit-loss` | P&L report |
| GET | `/reports/balance-sheet` | Balance sheet |

### 4.5 Platform (API keys only)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/tenant` | Tenant info |
| GET | `/usage` | Current usage metrics |
| GET | `/subscription` | Plan details |

---

## 5. OpenAPI Specification

### 5.1 Livrable

**Fichier :** `docs/api/openapi-v2.yaml`  
**UI :** Swagger UI à `https://developers.retailpos.cloud`

### 5.2 Génération

- Maintenir spec à la main (Phase 1)
- Validation CI : `spectral lint openapi-v2.yaml`
- SDK clients : openapi-generator (PHP, JS, Python) — Phase 2

---

## 6. Webhooks sortants

### 6.1 Configuration tenant

```sql
CREATE TABLE webhook_endpoints (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    url VARCHAR(512) NOT NULL,
    secret VARCHAR(64) NOT NULL,
    events JSON NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE webhook_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    endpoint_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    payload_json JSON NOT NULL,
    response_status SMALLINT NULL,
    attempts TINYINT DEFAULT 0,
    next_retry_at DATETIME NULL,
    delivered_at DATETIME NULL
);
```

### 6.2 Events catalogue

| Event | Payload |
|-------|---------|
| `sale.completed` | Sale object |
| `sale.voided` | Sale + reason |
| `inventory.low_stock` | Product + qty |
| `transfer.approved` | Transfer object |
| `payment.received` | Payment object |
| `customer.created` | Customer object |

### 6.3 Signature

```
X-RetailPOS-Signature: sha256={hmac_hex}
X-RetailPOS-Event: sale.completed
X-RetailPOS-Delivery: {uuid}
```

HMAC-SHA256 du body avec `webhook_secret`.

### 6.4 Retry policy

| Attempt | Délai |
|---------|-------|
| 1 | Immédiat |
| 2 | 5 min |
| 3 | 30 min |
| 4 | 2 h |
| 5 | 24 h → dead letter |

---

## 7. Intégrations tierces

### 7.1 Paiements

| Intégration | Type | Module |
|-------------|------|--------|
| Stripe | Billing SaaS + terminal | Platform, POS |
| Paystack | Billing Afrique | Platform |
| Orange Money API | Mobile money | POS, Accounting |
| MTN MoMo | Mobile money | POS, Accounting |
| Wave | Mobile money | POS |

### 7.2 Communications

| Intégration | Usage |
|-------------|-------|
| Twilio / Africa's Talking | SMS |
| WhatsApp Business API | Notifications (existe 013) |
| SendGrid / Mailgun | Email transactionnel |

### 7.3 E-commerce

| Plateforme | Direction | Méthode |
|------------|-----------|---------|
| WooCommerce | Bi-directionnel | REST plugin |
| Shopify | Bi-directionnel | Webhooks + API |
| PrestaShop | Import catalogue | CSV / API |

### 7.4 Comptabilité externe

| Logiciel | Export |
|----------|--------|
| Sage | CSV / API |
| QuickBooks | API OAuth |
| Ciel | FEC export |

### 7.5 Hardware POS

| Device | Protocole |
|--------|-----------|
| Imprimante thermique | ESC/POS (browser print / native bridge) |
| Scanner USB | Keyboard wedge (existant) |
| Tiroir caisse | Via imprimante |
| Balance | Serial / API (roadmap) |
| Terminal carte | Stripe Terminal, Ingenico SDK |

---

## 8. Sync API (offline)

### 8.1 Existant

`SyncController` — push/pull offline transactions

### 8.2 Évolution v2

| Endpoint | Rôle |
|----------|------|
| `POST /sync/push` | Upload batch offline ops |
| `GET /sync/pull?since={cursor}` | Delta changes |
| `GET /sync/status` | Queue health |
| `POST /sync/resolve-conflict` | Manual merge |

**Conflict resolution :** Last-write-wins pour catalog ; manual pour ventes conflicting.

---

## 9. Rate limiting

| Plan | API calls/mois | Burst/min |
|------|----------------|-----------|
| Starter | 0 (no API) | — |
| Business | 50 000 | 100 |
| Enterprise | 500 000 | 1000 |
| Platform partners | Custom | Custom |

Header réponse : `X-RateLimit-Remaining`, `Retry-After`

---

## 10. Migration v1 → v2

| Phase | Action |
|-------|--------|
| 1 | v1 maintenu, v2 en parallèle |
| 2 | Nouveaux clients v2 only |
| 3 | Deprecation notice v1 (6 mois) |
| 4 | v1 sunset |

Adapter `admin-api.js` → support JWT progressivement.

---

## 11. Developer Portal

**URL :** `developers.retailpos.cloud`

| Section | Contenu |
|---------|---------|
| Quickstart | Auth, first API call |
| API Reference | OpenAPI UI |
| Webhooks guide | Setup, verify signatures |
| SDKs | JS, PHP clients |
| Changelog | Version history |
| Status | Uptime integration |

---

## 12. Checklist API

- [ ] OpenAPI v2 spec draft
- [ ] JWT auth endpoints
- [ ] API key management UI
- [ ] Pagination standard all list endpoints
- [ ] Webhook engine + retry worker
- [ ] Rate limiter (Redis)
- [ ] Developer portal static site
- [ ] Stripe + Paystack webhooks
- [ ] Mobile money sandbox integrations

---

*Volume 7 — RetailPOS Enterprise Blueprint v1.0*
