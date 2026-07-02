# Cash analytics — How It Works / Analytique caisse

**Path:** `public/cash-registers/analytics.php`  
**Purpose:** Visualize cash register trends over time — daily collection, payment mix, register/branch performance, refund trends, and cashier rankings.

**Chemin :** `public/cash-registers/analytics.php`  
**Rôle :** Visualiser les tendances caisse — encaissement journalier, mix paiements, performance caisses/succursales, remboursements et classement caissiers.

---

## Architecture flow / Flux d'architecture

```
analytics.php
    └── includes/bootstrap.php (auth, store scope, i18n)
    └── layout-start.php (Chart.js, admin-cash-registers.css v14)
    └── cash-registers-analytics.js (v2)
            └── AdminAPI.getCashRegisterAnalytics(period)
                    └── GET /api/admin/cash-registers/analytics?period=week|month|year
                            └── CashRegisterController → CashRegisterDashboardService::analytics()
```

---

## Page sections / Sections de la page

| Section | Element IDs | Description |
|---------|-------------|-------------|
| **Hero KPIs** | `#crAnalyticsStats` | Total revenue, sessions, refunds, avg revenue per session |
| **Period toolbar** | `#crAnalyticsPeriod` | Chips: 7 days / 30 days / 12 months |
| **Daily collection** | `#crDailyChart` | Line chart — completed sales payments by day |
| **Payment mix** | `#crPaymentChart`, `#crPaymentLegend` | Doughnut + legend (cash, card, mobile, split) |
| **Register / branch bar** | `#crRegisterChart` | Horizontal bar — per-register revenue; falls back to branch balances when no register data |
| **Refund trends** | `#crRefundChart` | Bar chart — session refunds aggregated by day |
| **Cashier ranking** | `#crCashierPerf` | Table (desktop) + card list (mobile), CSV export |

---

## API response / Réponse API

**Endpoint:** `GET cash-registers/analytics?period=week|month|year`

```json
{
  "status": "success",
  "module_ready": true,
  "data": {
    "period": "month",
    "days": 30,
    "daily_collection": [{ "day": "2026-06-01", "amount": 125000 }],
    "branch_comparison": [{ "name": "Main", "balance": 50000, "registers": 2 }],
    "register_comparison": [{ "name": "Caisse 1", "code": "REG-01", "revenue": 80000, "sessions": 12 }],
    "cashier_performance": [{ "name": "Jean", "revenue": 45000, "sessions": 8 }],
    "refund_trends": [{ "day": "2026-06-01", "amount": 2000 }],
    "payment_breakdown": { "cash": 60000, "card": 30000, "mobile_money": 15000, "split": 0 }
  }
}
```

**Store scope:** When an admin selects a store in the header switcher, all queries filter by `store_id`. Global admins see all stores; branch comparison is scoped to the selected store when applicable.

---

## Backend queries / Requêtes backend

| Method | Source tables | Notes |
|--------|---------------|-------|
| `dailyCollection()` | `sales`, `payments` | Completed sales only, grouped by `DATE(created_at)` |
| `paymentBreakdown()` | `sales`, `payments` | Sum by `p.method` |
| `registerComparison()` | `cash_registers`, `cash_register_sessions` | Revenue & sessions since period start |
| `branchComparison()` | `stores`, `cash_registers` | Current balance per branch |
| `cashierPerformance()` | `cash_register_sessions`, `users` | Ranked by session revenue |
| `refundTrends()` | `cash_register_sessions` | Uses `DATE(opened_at)` and `SUM(refunds)` |

**Bug fix (2026):** `refundTrends()` previously used `DATE(created_at)` on a table without that column, causing HTTP 500. Fixed to `DATE(opened_at)`.

---

## Frontend behaviour / Comportement frontend

- **Default period:** 30 days (`month`)
- **Theme:** Charts re-render on dark/light toggle (`theme-toggle` event)
- **Refresh:** Header refresh button dispatches `cr:refresh`; page reloads data silently
- **Export:** CSV of cashier performance only (`#crAnalyticsExportBtn`)
- **Empty states:** Each chart shows “No data” when all values are zero
- **Responsive:** At ≤992px, cashier table becomes a card list; chart grid becomes single column

---

## i18n keys / Clés de traduction

Namespace: `admin` — prefix `cr_analytics_*`

See `languages/en/admin.php` and `languages/fr/admin.php` for full list.

---

## Related files / Fichiers liés

| File | Role |
|------|------|
| `assets/css/admin-cash-registers.css` | `.cr-analytics-*` styles |
| `assets/js/admin/cash-registers-analytics.js` | Page logic (v2) |
| `assets/js/admin/admin-api.js` | `getCashRegisterAnalytics()` |
| `includes/CashRegister/Services/CashRegisterDashboardService.php` | Data aggregation |

---

## See also / Voir aussi

- [Registers](./registers.md) — open/close sessions
- [Reconciliation](./reconciliation.md) — variance review
- [README](./README.md) — module index
