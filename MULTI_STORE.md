# Système multi-succursales (RetailPOS)

## Architecture

```
Super Admin ──► Vue globale (toutes succursales) OU filtre par succursale active
Admin/Manager ──► Succursale(s) assignée(s) via users.store_id + user_stores
Cashier ──► Une succursale (users.store_id)
```

### Tables clés

| Table | Rôle |
|-------|------|
| `stores` | Succursales (code, contact, TVA, devise) |
| `user_stores` | Accès multi-succursales par utilisateur |
| `products.store_id` | Stock par magasin |
| `sales.store_id` | Ventes par magasin |
| `stock_movements` | Transferts inter-succursales |

### Session

- `$_SESSION['store_id']` — succursale principale de l'utilisateur
- `$_SESSION['active_store_id']` — contexte UI/API (`null` = toutes, super admin)

### API `stores`

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `stores` | Liste accessible |
| GET | `stores/context` | Contexte actif |
| POST | `stores/switch` | Changer succursale `{ store_id: 1 \| null }` |
| POST | `stores` | Créer succursale |
| PUT | `stores/{id}` | Modifier |
| GET | `stores/transfers` | Transferts |
| POST | `stores/transfers` | Créer transfert |
| PUT | `stores/transfers/{id}` | `{ action: accept \| reject }` |

## Installation

1. Exécuter dans phpMyAdmin : `includes/Database/migrations/002_multi_store.sql`
2. (Optionnel) Re-seed : `php seed.php`
3. Admin → **Succursales** (`public/admin/stores.php`)
4. Super admin : sélecteur de succursale dans l'en-tête

## Transfert de stock

1. Produit doit exister dans la succursale **source** avec stock suffisant
2. Création → statut `pending`
3. Acceptation à la succursale **destination** :
   - Déduction source
   - Ajout destination (crée le produit par SKU si absent)

## Fichiers principaux

- `includes/Helpers/StoreScope.php`
- `includes/Controllers/StoresController.php`
- `assets/js/admin/store-switcher.js`
- `public/admin/stores.php`
