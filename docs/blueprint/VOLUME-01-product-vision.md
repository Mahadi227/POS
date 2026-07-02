# Volume 1 — Product Vision & Business Requirements

**Blueprint:** RetailPOS Enterprise v1.0  
**Statut:** Draft  
**Dernière mise à jour:** Juin 2026

---

## 1. Executive Summary

RetailPOS Cloud vise à devenir la **plateforme ERP retail de référence pour l'Afrique francophone et anglophone** : POS temps réel, inventaire multi-magasin, entrepôt (WMS), caisses, comptabilité, et extensions RH/CRM — livrés en **SaaS multi-tenant** avec mode offline, mobile money, et conformité fiscale locale.

**Positionnement :** Odoo Retail × Square × locale (FCFA, XOF/XAF, WhatsApp, faible connectivité).

---

## 2. Problem Statement

### 2.1 Marché cible

| Segment | Besoin | Douleur actuelle |
|---------|--------|------------------|
| Chaînes retail (5–500 magasins) | Centralisation stocks, ventes, compta | Excel + logiciels isolés |
| Distributeurs / grossistes | WMS + transferts inter-magasins | ERP lourds, coûteux |
| Franchises | Multi-site + reporting consolidé | Pas de visibilité temps réel |
| PME retail | POS simple + croissance | Solutions non scalables |

### 2.2 Problèmes résolus par RetailPOS Cloud

1. **Fragmentation** — Un seul système pour POS, stock, entrepôt, caisses, finance.
2. **Connectivité** — Mode offline avec sync (existant : `SyncController`, `synchronization_queue`).
3. **Coût d'entrée** — SaaS par abonnement vs licence + serveur.
4. **Conformité locale** — Multi-devise (FCFA, EUR, USD), reçus thermiques, TVA configurable.
5. **Évolutivité** — Du magasin unique à la chaîne nationale sans migration.

---

## 3. Vision produit

> *« Every store connected. Every sale accounted. Every warehouse visible — from Dakar to Nairobi. »*

### 3.1 Principes produit

| # | Principe | Implication |
|---|----------|-------------|
| P1 | **Offline-first POS** | La caisse ne s'arrête jamais |
| P2 | **Tenant isolation** | Zéro fuite de données entre clients |
| P3 | **Modularité** | Activer uniquement les modules souscrits |
| P4 | **Mobile money native** | Orange Money, MTN MoMo, Wave en first-class |
| P5 | **Simplicité terrain** | UX caissier < 3 clics pour une vente |
| P6 | **Auditabilité** | Chaque action sensible tracée |

---

## 4. Personas

| Persona | Rôle système | Objectifs | Portail |
|---------|--------------|-----------|---------|
| **Awa — Caissière** | `cashier` | Vendre vite, scanner, encaisser | `public/cashier/` |
| **Moussa — Manager magasin** | `manager` | Superviser caisses, approuver retours | `public/manager/` |
| **Fatou — Responsable entrepôt** | `warehouse_manager` | Réception, dispatch, transferts | `public/warehouse/` |
| **Ibrahim — Comptable** | `accountant` | GL, trésorerie, rapports | `public/accounting/` |
| **Admin IT — Tenant Admin** | `admin` | Utilisateurs, magasins, config | `public/admin/` |
| **Sam — Platform Ops** | `platform_admin` *(nouveau)* | Tenants, billing, support | `public/platform/` *(à créer)* |

---

## 5. Business Model (SaaS)

### 5.1 Tiers d'abonnement (proposition v1)

| Plan | Cible | Magasins | Utilisateurs | Modules | Prix indicatif |
|------|-------|----------|--------------|---------|----------------|
| **Starter** | TPE | 1 | 5 | POS + Inventaire | ~29 €/mois |
| **Business** | PME | 5 | 25 | + Caisses + Manager | ~99 €/mois |
| **Enterprise** | Chaîne | Illimité | Illimité | + WMS + Compta + API | Sur devis |
| **Platform** | Franchise / intégrateur | Multi-tenant | — | White-label + SLA | Partenariat |

### 5.2 Revenus additionnels

- Transactions payment gateway (commission %)
- SMS / WhatsApp notifications (usage-based)
- Formation & onboarding premium
- Intégrations custom (ERP tiers, e-commerce)
- Support 24/7 SLA Enterprise

### 5.3 Métriques business (North Star)

| KPI | Cible Y1 | Mesure |
|-----|----------|--------|
| MRR | Croissance 15 %/mois | Billing system |
| Churn mensuel | < 3 % | Cancellations / actifs |
| ARPU | > 75 € | MRR / tenants |
| Time-to-value | < 24 h | Signup → première vente |
| Uptime SLA | 99.9 % | Monitoring |
| NPS | > 40 | Enquêtes trimestrielles |

---

## 6. Exigences fonctionnelles (par domaine)

### 6.1 Matrice module × statut

| Module | Statut actuel | Priorité SaaS | Volume réf. |
|--------|---------------|---------------|-------------|
| POS / Caisse | ✅ Production | P0 | Vol. 5 |
| Inventaire | ✅ Production | P0 | Vol. 5 |
| Multi-magasin | ✅ Production | P0 | Vol. 3 |
| Caisses (registers) | ✅ Production | P0 | Vol. 5 |
| Manager / Supervision | ✅ Production | P1 | Vol. 5 |
| Warehouse (WMS) | ✅ Production | P1 | Vol. 5 |
| Comptabilité | ✅ Beta | P1 | Vol. 5 |
| Notifications | ✅ Production | P1 | Vol. 7 |
| RH / Paie | ❌ À concevoir | P2 | Vol. 5 |
| CRM / Fidélité | ⚠️ Partiel (customers) | P2 | Vol. 5 |
| Achats / Fournisseurs | ⚠️ Partiel (PO WMS) | P2 | Vol. 5 |
| E-commerce sync | ❌ À concevoir | P3 | Vol. 7 |
| Customer portal | ⚠️ Stub | P3 | Vol. 5 |

### 6.2 Exigences transverses (MUST)

| ID | Exigence | Critère d'acceptation |
|----|----------|----------------------|
| FR-001 | Isolation tenant | Aucune requête sans filtre `tenant_id` |
| FR-002 | Multi-magasin | Un tenant gère N stores avec scope utilisateur |
| FR-003 | Offline POS | Vente possible 72 h sans réseau ; sync auto |
| FR-004 | RBAC granulaire | Permission par action, pas seulement par rôle |
| FR-005 | Audit trail | Login, ventes annulées, ajustements stock tracés |
| FR-006 | Multi-devise | Affichage et rapports par devise magasin |
| FR-007 | i18n | FR + EN minimum ; extensible par tenant |
| FR-008 | Export données | CSV/PDF pour tous les rapports majeurs |
| FR-009 | Self-service signup | Création tenant sans intervention manuelle |
| FR-010 | Plan entitlements | Module désactivé si non souscrit |

---

## 7. Exigences non-fonctionnelles

| ID | Catégorie | Exigence | Cible |
|----|-----------|----------|-------|
| NFR-001 | Performance | Temps réponse API P95 | < 300 ms |
| NFR-002 | Performance | Chargement POS | < 2 s (3G) |
| NFR-003 | Disponibilité | Uptime production | 99.9 % |
| NFR-004 | Scalabilité | Tenants simultanés | 1 000+ (Y2) |
| NFR-005 | Scalabilité | Ventes/jour plateforme | 1 M+ |
| NFR-006 | Sécurité | Chiffrement transit | TLS 1.3 |
| NFR-007 | Sécurité | Chiffrement repos | AES-256 (PII) |
| NFR-008 | Conformité | RGPD / lois locales | Droit à l'effacement |
| NFR-009 | Résilience | RPO / RTO | 1 h / 4 h |
| NFR-010 | Accessibilité | WCAG | Niveau AA (progressif) |
| NFR-011 | Maintenabilité | Couverture tests critiques | > 70 % domaine |
| NFR-012 | Portabilité | Export tenant complet | JSON + SQL |

---

## 8. Conformité & contexte Afrique

### 8.1 Fiscalité

- TVA / taxes configurables par magasin et catégorie produit
- Numérotation factures séquentielle par tenant
- Export pour logiciels fiscaux locaux (FEC, etc.) — roadmap

### 8.2 Paiements

| Méthode | Statut | Intégration |
|---------|--------|-------------|
| Espèces | ✅ | POS natif |
| Carte | ⚠️ | Terminal tiers |
| Mobile Money | ⚠️ Partiel | Vol. 7 — API partenaires |
| Crédit client | ✅ | `customers` + AR |

### 8.3 Connectivité

- PWA offline (Vol. 8)
- Sync différentiel (`SyncController` push/pull)
- Compression payloads sync
- SMS/WhatsApp pour alertes stock (migration 013)

---

## 9. User Stories prioritaires (SaaS MVP)

### Epic E1 — Onboarding tenant
```
EN TANT QUE nouveau client
JE VEUX créer mon compte et mon premier magasin en < 15 minutes
AFIN DE commencer à vendre le jour même
```
**Acceptance:** signup → email verify → wizard magasin → 1 caissier → POS opérationnel.

### Epic E2 — Isolation données
```
EN TANT QUE Tenant Admin
JE VEUX être certain que mes données ne sont jamais visibles par un autre client
AFIN DE respecter la confidentialité commerciale
```
**Acceptance:** tests pénétration cross-tenant ; audit code `TenantScope`.

### Epic E3 — Abonnement & modules
```
EN TANT QUE Tenant Admin
JE VEUX souscrire au plan Business et activer le module WMS
AFIN D'étendre mes opérations sans changer d'outil
```
**Acceptance:** upgrade plan → entitlement WMS → menu warehouse visible sous 1 min.

### Epic E4 — Consolidation multi-magasin
```
EN TANT QUE Admin chaîne
JE VEUX un tableau de bord consolidé de tous mes magasins
AFIN DE piloter performance réseau
```
**Acceptance:** dashboard admin avec filtre magasin + vue globale ; KPIs agrégés.

---

## 10. Roadmap produit (12 mois)

| Trimestre | Livrables clés |
|-----------|----------------|
| **Q3 2026** | Tenant model, TenantScope, Platform admin MVP, JWT API |
| **Q4 2026** | Signup self-service, plans Starter/Business, billing Stripe |
| **Q1 2027** | White-label, mobile money intégrations, CRM basique |
| **Q2 2027** | HR module MVP, marketplace API, SLA Enterprise |

---

## 11. Risques produit

| Risque | Impact | Mitigation |
|--------|--------|------------|
| Complexité migration clients on-prem | Élevé | Outil import tenant + période parallèle |
| Fuite données cross-tenant | Critique | TenantScope obligatoire + tests auto |
| Adoption offline insuffisante | Moyen | UX sync visible, indicateurs connexion |
| Concurrence ERP établis | Moyen | Focus Afrique + prix + offline |
| Dette technique monolithe | Moyen | Modular monolith, boundaries claires |

---

## 12. Critères de succès v1.0 SaaS

- [ ] 10 tenants pilotes en production
- [ ] Zéro incident cross-tenant
- [ ] Billing automatisé opérationnel
- [ ] POS offline validé sur 3 pays
- [ ] Documentation blueprint v1.0 adoptée par l'équipe

---

*Volume 1 — RetailPOS Enterprise Blueprint v1.0*
