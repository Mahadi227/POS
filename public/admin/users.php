<?php
require_once '../../includes/Config/session.php';
requireLogin();

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if (!in_array($roleSlug, ['admin', 'manager', 'super_admin'], true)) {
    header('Location: ../login.php');
    exit;
}
$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
$activePage = 'users';
$isSuperAdmin = ($roleSlug === 'super_admin');
$storeId = isset($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : 0;
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Utilisateurs — RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/admin-users.css?v=2">
</head>

<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span class="material-icons-round">storefront</span>
                    <h2>RetailPOS<span class="dot">.</span></h2>
                </div>
            </div>
            <ul class="nav-menu">
                <li class="nav-section">Principal</li>
                <li><a href="index.php" class="nav-link"><span class="material-icons-round">dashboard</span><span>Tableau de bord</span></a></li>
                <li><a href="sales.php" class="nav-link"><span class="material-icons-round">point_of_sale</span><span>Ventes</span></a></li>
                <li><a href="inventory.php" class="nav-link"><span class="material-icons-round">inventory_2</span><span>Inventaire</span></a></li>
                <?php include __DIR__ . '/includes/sidebar-extra.php'; ?>
                <li class="nav-section">Système</li>
                <li><a href="../cashier/pos.php" class="nav-link"><span class="material-icons-round">shopping_cart</span><span>Terminal caisse</span></a></li>
                <li><a href="../logout.php" class="nav-link" style="color:var(--danger);margin-top:12px;"><span class="material-icons-round">logout</span><span>Déconnexion</span></a></li>
            </ul>
            <div class="user-profile-widget">
                <span class="avatar-initial"><?php echo htmlspecialchars($initial); ?></span>
                <div class="user-info">
                    <p class="name"><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></p>
                    <p class="role"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $roleSlug))); ?></p>
                </div>
            </div>
        </aside>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <main class="main-content">
            <header class="top-header">
                <div class="header-left" style="display:flex;align-items:center;gap:16px;">
                    <button type="button" class="icon-btn mobile-menu-btn" id="mobileMenuBtn"><span class="material-icons-round">menu</span></button>
                    <div>
                        <h1>Gestion des utilisateurs</h1>
                        <p class="date-display">Super Admin — équipes & permissions</p>
                    </div>
                </div>
                <div class="header-right">
                    <button type="button" class="ad-refresh-btn" id="addUserBtn">
                        <span class="material-icons-round">person_add</span>
                        Nouvel utilisateur
                    </button>
                    <button type="button" class="icon-btn theme-toggle" id="theme-toggle"><span class="material-icons-round">dark_mode</span></button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="um-tabs">
                    <button type="button" class="um-tab active" data-panel="users">Utilisateurs</button>
                    <button type="button" class="um-tab" data-panel="permissions">Permissions par rôle</button>
                    <button type="button" class="um-tab" data-panel="activity">Activité</button>
                </div>

                <div id="panel-users" class="um-panel">
                    <div class="um-toolbar">
                        <div class="um-search">
                            <span class="material-icons-round">search</span>
                            <input type="search" id="userSearch" placeholder="Nom ou email…">
                        </div>
                        <select id="roleFilter" class="um-select">
                            <option value="">Tous les rôles</option>
                            <option value="admin">Administrateur</option>
                            <option value="manager">Manager</option>
                            <option value="cashier">Caissier</option>
                            <option value="staff">Staff</option>
                        </select>
                        <select id="storeFilter" class="um-select">
                            <option value="">Toutes les succursales</option>
                        </select>
                        <select id="statusFilter" class="um-select">
                            <option value="">Tous statuts</option>
                            <option value="active">Actifs</option>
                            <option value="suspended">Suspendus</option>
                        </select>
                        <button type="button" class="ad-refresh-btn" id="refreshUsers"><span class="material-icons-round">refresh</span></button>
                    </div>
                    <div class="card table-widget">
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Rôle</th>
                                        <th>Succursale</th>
                                        <th>Statut</th>
                                        <th>Dernière connexion</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="usersTableBody">
                                    <tr>
                                        <td colspan="6" class="ad-empty-row">Chargement…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="panel-permissions" class="um-panel hidden">
                    <div class="card" style="padding:20px;">
                        <div class="um-form-row" style="max-width:400px;margin-bottom:16px;">
                            <div class="um-form-group">
                                <label>Rôle à configurer</label>
                                <select id="permRoleSelect"></select>
                            </div>
                            <button type="button" class="ad-refresh-btn" id="savePermissionsBtn">
                                <span class="material-icons-round">save</span>
                                Enregistrer permissions
                            </button>
                        </div>
                        <div class="um-perm-grid" id="permissionsGrid">Chargement…</div>
                    </div>
                </div>

                <div id="panel-activity" class="um-panel hidden">
                    <div class="stat-cards um-activity-stats">
                        <div class="card stat-card um-act-stat">
                            <div class="card-icon success">
                                <span class="material-icons-round">login</span>
                            </div>
                            <div class="card-info">
                                <h3>Connexions aujourd'hui</h3>
                                <h2 id="actStatLogins">—</h2>
                            </div>
                        </div>
                        <div class="card stat-card um-act-stat">
                            <div class="card-icon warning">
                                <span class="material-icons-round">gpp_bad</span>
                            </div>
                            <div class="card-info">
                                <h3>Échecs connexion</h3>
                                <h2 id="actStatFailed">—</h2>
                            </div>
                        </div>
                        <div class="card stat-card um-act-stat">
                            <div class="card-icon primary">
                                <span class="material-icons-round">admin_panel_settings</span>
                            </div>
                            <div class="card-info">
                                <h3>Actions admin (jour)</h3>
                                <h2 id="actStatAdmin">—</h2>
                            </div>
                        </div>
                        <div class="card stat-card um-act-stat">
                            <div class="card-icon info">
                                <span class="material-icons-round">groups</span>
                            </div>
                            <div class="card-info">
                                <h3>Utilisateurs actifs</h3>
                                <h2 id="actStatUsers">—</h2>
                                <p class="trend ad-trend--neutral">Aujourd'hui</p>
                            </div>
                        </div>
                    </div>

                    <div class="um-toolbar um-activity-toolbar">
                        <select id="activityTypeFilter" class="um-select" title="Type d'événement">
                            <option value="all">Tous les événements</option>
                            <option value="login">Connexions & déconnexions</option>
                            <option value="admin">Actions administration</option>
                        </select>
                        <select id="activityUserFilter" class="um-select" title="Filtrer par utilisateur">
                            <option value="">Tous les utilisateurs</option>
                        </select>
                        <button type="button" class="as-btn" id="refreshActivity" title="Actualiser">
                            <span class="material-icons-round">refresh</span>
                            Actualiser
                        </button>
                    </div>

                    <div class="card table-widget">
                        <div class="table-responsive">
                            <table class="modern-table um-activity-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Utilisateur</th>
                                        <th>Type</th>
                                        <th>Action</th>
                                        <th>IP</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody id="activityTableBody">
                                    <tr>
                                        <td colspan="6" class="ad-empty-row">Chargement…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- User modal -->
    <div class="um-modal-overlay" id="userModal">
        <div class="um-modal">
            <h2 id="userModalTitle">Nouvel utilisateur</h2>
            <div class="um-form-error" id="userFormError"></div>
            <form id="userForm">
                <input type="hidden" id="userId">
                <div class="um-form-group">
                    <label>Nom complet *</label>
                    <input type="text" id="ufName" required>
                </div>
                <div class="um-form-group">
                    <label>Email *</label>
                    <input type="email" id="ufEmail" required>
                </div>
                <div class="um-form-row">
                    <div class="um-form-group">
                        <label>Rôle *</label>
                        <select id="ufRole" required></select>
                    </div>
                    <div class="um-form-group">
                        <label>Succursale principale</label>
                        <select id="ufStore">
                            <option value="">— Aucune —</option>
                        </select>
                    </div>
                </div>
                <div class="um-form-group" id="passwordGroup">
                    <label id="passwordLabel">Mot de passe * (8+ caractères)</label>
                    <input type="password" id="ufPassword" minlength="8" autocomplete="new-password">
                </div>
                <div class="um-modal-actions">
                    <button type="button" class="as-btn" id="closeUserModal">Annuler</button>
                    <button type="submit" class="as-btn as-btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset password modal -->
    <div class="um-modal-overlay" id="resetModal">
        <div class="um-modal" style="max-width:400px;">
            <h2>Réinitialiser le mot de passe</h2>
            <div class="um-form-error" id="resetFormError"></div>
            <form id="resetForm">
                <input type="hidden" id="resetUserId">
                <p id="resetUserLabel" style="margin-bottom:12px;color:var(--text-secondary);"></p>
                <div class="um-form-group">
                    <label>Nouveau mot de passe *</label>
                    <input type="password" id="resetPassword" minlength="8" required>
                </div>
                <div class="um-modal-actions">
                    <button type="button" class="as-btn" id="closeResetModal">Annuler</button>
                    <button type="submit" class="as-btn as-btn-primary">Réinitialiser</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.ADMIN_PAGE = window.ADMIN_PAGE || {};
        window.ADMIN_PAGE.role = <?php echo json_encode($roleSlug); ?>;
        window.ADMIN_PAGE.storeId = <?php echo json_encode($storeId); ?>;
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=6"></script>
    <script src="../../assets/js/admin/users.js?v=2"></script>
    <script>
        document.getElementById('mobileMenuBtn')?.addEventListener('click', () => {
            document.querySelector('.sidebar')?.classList.toggle('open');
            document.getElementById('sidebarOverlay')?.classList.toggle('active');
        });
        document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
            document.querySelector('.sidebar')?.classList.remove('open');
            document.getElementById('sidebarOverlay')?.classList.remove('active');
        });
    </script>
</body>

</html>