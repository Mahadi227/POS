# Volume 10 — Documentation, Coding Standards & Maintenance

**Blueprint:** RetailPOS Enterprise v1.0  
**Statut:** Draft

---

## 1. Objectif

Établir les standards de documentation, conventions de code, processus de maintenance, et gouvernance technique pour RetailPOS Cloud — assurant la cohérence à mesure que l'équipe et le codebase grandissent.

---

## 2. Hiérarchie documentaire

```
docs/
├── blueprint/              # ← Ce blueprint (v1.0)
│   ├── README.md
│   └── VOLUME-01..10.md
├── api/
│   └── openapi-v2.yaml     # À créer
├── accounting/README.md    # ✅ Existe
├── warehouse/README.md     # ✅ Existe
├── cash-registers/         # ✅ Existe
├── manager-supervision/    # ✅ Existe
├── NOTIFICATIONS.md        # ✅ Existe
├── I18N_README.md          # ✅ Existe
├── adr/                    # Architecture Decision Records
│   ├── 001-modular-monolith.md
│   ├── 002-shared-db-tenant-id.md
│   └── ...
├── runbooks/               # Opérations
│   ├── incident-response.md
│   ├── tenant-export.md
│   └── database-restore.md
└── modules/                # Template par module
    └── TEMPLATE.md
```

---

## 3. Architecture Decision Records (ADR)

### 3.1 Format ADR

```markdown
# ADR-{NNN}: {Title}

**Statut:** Proposed | Accepted | Deprecated
**Date:** YYYY-MM-DD
**Décideurs:** @names

## Contexte
{Pourquoi cette décision est nécessaire}

## Décision
{Ce qui a été décidé}

## Conséquences
{Positif, négatif, neutre}

## Alternatives considérées
{Liste}
```

### 3.2 ADRs initiaux (Blueprint v1.0)

| ID | Titre | Statut |
|----|-------|--------|
| ADR-001 | Modular monolith | Accepted |
| ADR-002 | Shared DB + tenant_id | Accepted |
| ADR-003 | Redis sessions + cache | Proposed |
| ADR-004 | S3 object storage | Proposed |
| ADR-005 | API v2 REST + OpenAPI | Proposed |
| ADR-006 | PHP 8.2+ minimum | Proposed |
| ADR-007 | Pas de migration Laravel | Accepted |
| ADR-008 | PWA-first mobile | Accepted |

---

## 4. Standards PHP

### 4.1 Conventions générales

| Règle | Standard |
|-------|----------|
| PHP version | 8.2+ |
| Style | PSR-12 |
| Types | `declare(strict_types=1)` obligatoire fichiers neufs |
| Naming classes | PascalCase |
| Naming methods | camelCase |
| Naming tables SQL | snake_case pluriel |
| Fichiers | Un class par fichier, namespace = path |

### 4.2 Structure classe

```php
<?php
declare(strict_types=1);

namespace RetailPOS\Sales\Services;

final class SalesService
{
    public function __construct(
        private readonly SaleRepository $sales,
        private readonly InventoryService $inventory,
    ) {}

    public function createSale(CreateSaleDTO $dto): Sale
    {
        // 1. Validate tenant scope
        // 2. Business logic
        // 3. Persist
        // 4. Dispatch events
    }
}
```

### 4.3 Règles obligatoires (MUST)

| # | Règle |
|---|-------|
| 1 | Jamais de SQL dans `public/` (vues) |
| 2 | Toujours PDO prepared statements |
| 3 | `TenantScope::sqlFilter()` sur requêtes domaine |
| 4 | `htmlspecialchars()` sur sorties HTML |
| 5 | Pas de `$_GET`/`$_POST` direct dans Services |
| 6 | Controllers minces — logique dans Services |
| 7 | Exceptions métier typées (`SaleNotFoundException`) |

### 4.4 Organisation namespaces (cible)

```
RetailPOS\
├── Platform\
├── Sales\
├── Inventory\
├── Accounting\
├── CashRegister\
├── Wms\
├── Manager\
├── Notifications\
└── Shared\
```

**Migration progressive :** Composer PSR-4 autoload depuis `includes/`.

---

## 5. Standards JavaScript

### 5.1 Conventions

| Règle | Standard |
|-------|----------|
| Modules | ES modules (`import`/`export`) ou IIFE legacy |
| Style | ESLint recommended |
| Naming | camelCase variables, PascalCase classes |
| API calls | Via `AdminAPI` / `WarehouseAPI` wrappers |
| i18n | `t('key')` — jamais de strings hardcodées UI |
| Async | `async/await` préféré |

### 5.2 Structure fichier page

```javascript
// assets/js/warehouse/warehouse-example.js
(function () {
    'use strict';

    const t = (key) => window.WH_I18N?.[key] ?? key;

    async function init() {
        bindEvents();
        await loadData();
    }

    function bindEvents() { ... }
    async function loadData() { ... }

    document.addEventListener('DOMContentLoaded', init);
})();
```

### 5.3 Règles MUST

| # | Règle |
|---|-------|
| 1 | Pas de `innerHTML` avec données user sans escape |
| 2 | API errors → toast/banner utilisateur |
| 3 | Loading states sur actions async |
| 4 | `hidden` attribute pour éléments vides (alertes) |
| 5 | Version cache-bust CSS/JS (`?v=N`) |

---

## 6. Standards CSS

| Règle | Standard |
|-------|----------|
| Variables | CSS custom properties (`--primary`, `--bg-surface`) |
| Thème | `data-theme="light|dark"`, `data-portal="warehouse"` |
| Composants partagés | `admin.css` (`.ad-alert-strip`, layout) |
| Portail | `{portal}-portal.css` ou `admin-{module}.css` |
| Mobile | Mobile-first `@media (max-width: 640px)` |
| Naming | BEM-like : `.wh-dash-hero__title` |

---

## 7. Standards SQL

| Règle | Standard |
|-------|----------|
| Tables | snake_case, pluriel (`sales`, `journal_entries`) |
| PK | `id BIGINT UNSIGNED AUTO_INCREMENT` |
| FK | `{table_singular}_id` |
| Timestamps | `created_at`, `updated_at`, `deleted_at` (soft delete) |
| Tenant | `tenant_id BIGINT UNSIGNED NOT NULL` + index |
| Migrations | `019_description.sql` numéroté séquentiel |

---

## 8. Git workflow

### 8.1 Branches

| Branch | Usage |
|--------|-------|
| `main` | Production-ready |
| `develop` | Intégration (optionnel) |
| `feature/{ticket}-{desc}` | Features |
| `fix/{ticket}-{desc}` | Bug fixes |
| `release/v{x.y.z}` | Release prep |

### 8.2 Commit messages

Format : [Conventional Commits](https://www.conventionalcommits.org/)

```
feat(warehouse): add retour admin sidebar link
fix(pos): hide empty alert strips
docs(blueprint): add volume 3 multi-tenant design
refactor(api): extract route table from index.php
```

### 8.3 Pull Request checklist

- [ ] Tests passent
- [ ] Pas de régression cross-tenant (si applicable)
- [ ] i18n EN + FR pour nouvelles strings
- [ ] Migration SQL si schema change
- [ ] Documentation module mise à jour
- [ ] Screenshots si changement UI

---

## 9. Template documentation module

```markdown
# Module {Name}

## Overview
{Description 2-3 phrases}

## Access
| Rôle | Portail | Permission |
|------|---------|------------|

## Architecture
- Controller: `includes/Controllers/{X}Controller.php`
- Services: `includes/{Module}/Services/`
- Repositories: `includes/{Module}/Repositories/`
- Pages: `public/{portal}/`
- JS: `assets/js/{portal}/`
- CSS: `assets/css/{portal}-*.css`
- API: `api/v1?request={resource}/...`

## Database Tables
| Table | Description |
|-------|-------------|

## Key Workflows
{Diagramme ou étapes}

## Configuration
{Settings tenant/store}

## API Endpoints
| Method | Path | Description |
|--------|------|-------------|

## i18n Keys
`languages/{en,fr}/{module}.php`

## Testing
{Tests existants / à écrire}
```

---

## 10. Maintenance & support

### 10.1 Versioning produit

**Semantic Versioning :** `MAJOR.MINOR.PATCH`

| Type | Exemple | Quand |
|------|---------|-------|
| MAJOR | 2.0.0 | Breaking API, migration majeure |
| MINOR | 1.1.0 | Nouveau module, feature |
| PATCH | 1.0.1 | Bug fix |

### 10.2 Cycle de release

| Fréquence | Contenu |
|-----------|---------|
| Patch | Hebdomadaire (hotfix as needed) |
| Minor | Mensuel |
| Major | Annuel ou milestone SaaS |

### 10.3 Changelog

**Fichier :** `CHANGELOG.md` — format [Keep a Changelog](https://keepachangelog.com/)

### 10.4 Deprecation policy

- API v1 : 6 mois notice avant sunset
- Features : banner « Deprecated » 2 versions avant retrait
- Documenter dans CHANGELOG + developer portal

---

## 11. Runbooks ops

### 11.1 Runbooks requis

| Runbook | Contenu |
|---------|---------|
| `incident-response.md` | Sévérités, escalation, communication |
| `tenant-export.md` | Export données client RGPD |
| `tenant-suspend.md` | Suspendre compte impayé |
| `database-restore.md` | Restauration backup |
| `migration-failure.md` | Rollback migration |
| `high-traffic.md` | Scale up procédure |

### 11.2 Incident severity

| Niveau | Exemple | Response |
|--------|---------|----------|
| SEV1 | Plateforme down, fuite données | 15 min, all hands |
| SEV2 | POS offline global | 30 min |
| SEV3 | Module dégradé | 4 h |
| SEV4 | Bug mineur | Next sprint |

---

## 12. Onboarding développeur

### 12.1 Jour 1

1. Clone repo + Docker setup
2. Lire `docs/blueprint/README.md`
3. Run `seed.php` → explorer portails
4. Lire `MULTI_STORE.md` + `StoreScope.php`

### 12.2 Semaine 1

1. Lire Volumes 2, 3, 4 du blueprint
2. Premier PR : fix documentation ou test
3. Pair programming sur un module

### 12.3 Ressources

| Ressource | Path |
|-----------|------|
| Blueprint | `docs/blueprint/` |
| Multi-store | `MULTI_STORE.md` |
| API | `api/v1/index.php` (v2 spec à venir) |
| Migrations | `includes/Database/migrations/` |
| Seed data | `seed.php` |

---

## 13. Qualité code — métriques

| Métrique | Cible | Outil |
|----------|-------|-------|
| PHPStan level | 5+ | PHPStan |
| Test coverage (critical) | 70 %+ | PHPUnit |
| ESLint errors | 0 | ESLint |
| Security vulnerabilities | 0 critical | Composer audit |
| Open TODO/FIXME | < 50 | grep tracking |
| Documentation coverage | 100 % modules | Manual audit |

---

## 14. Technical debt register

| ID | Description | Impact | Effort | Priorité |
|----|-------------|--------|--------|----------|
| TD-001 | `api/v1/index.php` monolithic switch | High | Medium | P1 |
| TD-002 | JWT configured unused | Medium | Low | P1 |
| TD-003 | Legacy admin/warehouse redirects | Low | Low | P2 |
| TD-004 | Runtime SchemaMigrators vs versioned | Medium | Medium | P1 |
| TD-005 | No Composer PSR-4 autoload | Medium | High | P2 |
| TD-006 | Duplicate service workers | Low | Low | P2 |
| TD-007 | Global email unique constraint | High | Medium | P0 |
| TD-008 | Local file uploads | Medium | Medium | P1 |

**Review :** Mensuel en tech lead meeting.

---

## 15. Checklist documentation

- [ ] Blueprint v1.0 publié (`docs/blueprint/`)
- [ ] ADR directory créé
- [ ] Module template appliqué à tous modules
- [ ] OpenAPI v2 draft
- [ ] CHANGELOG.md initialisé
- [ ] `.env.example` documenté
- [ ] Runbooks incident + restore
- [ ] CONTRIBUTING.md
- [ ] PHPStan + ESLint config
- [ ] Tech debt register maintenu

---

*Volume 10 — RetailPOS Enterprise Blueprint v1.0*
