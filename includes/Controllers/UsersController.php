<?php
/**
 * Gestion utilisateurs — réservé Super Admin.
 */
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';

class UsersController
{
    private PDO $db;

    /** Rôles qu'un Super Admin peut créer / assigner */
    private const ASSIGNABLE_ROLES = ['admin', 'manager', 'cashier', 'staff'];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function handleRequest(string $method, array $path): void
    {
        $this->requireSuperAdmin();

        $parsed = $this->parsePath($path);
        $action = $parsed['action'];
        $id = $parsed['id'];
        $sub = $parsed['sub'];

        if ($method === 'GET' && $action === null && $id === null) {
            $this->listUsers();
            return;
        }

        if ($method === 'GET' && $action === 'activity') {
            $this->listActivity();
            return;
        }

        if ($method === 'GET' && $action === 'roles') {
            $this->listRoles();
            return;
        }

        if ($method === 'GET' && $action === 'permissions') {
            $this->listPermissions();
            return;
        }

        if ($method === 'GET' && $action === 'role-permissions') {
            $this->getRolePermissions();
            return;
        }

        if ($method === 'PUT' && $action === 'role-permissions') {
            $this->updateRolePermissions();
            return;
        }

        if ($method === 'POST' && count($path) === 1) {
            $this->createUser();
            return;
        }

        if ($id > 0 && $sub === 'reset-password' && $method === 'POST') {
            $this->resetPassword($id);
            return;
        }

        if ($id > 0 && $sub === 'suspend' && $method === 'PUT') {
            $this->setUserActive($id, false);
            return;
        }

        if ($id > 0 && $sub === 'activate' && $method === 'PUT') {
            $this->setUserActive($id, true);
            return;
        }

        if ($method === 'GET' && $id > 0) {
            $this->getUser($id);
            return;
        }

        if ($method === 'PUT' && $id > 0 && $sub === null) {
            $this->updateUser($id);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
    }

    private function parsePath(array $path): array
    {
        $s1 = $path[1] ?? null;
        $s2 = $path[2] ?? null;

        if ($s1 !== null && $s1 !== '' && is_numeric($s1)) {
            return ['id' => (int) $s1, 'action' => null, 'sub' => $s2];
        }

        return [
            'id'     => ($s2 !== null && is_numeric($s2)) ? (int) $s2 : null,
            'action' => ($s1 !== null && $s1 !== '') ? $s1 : null,
            'sub'    => null,
        ];
    }

    private function requireSuperAdmin(): void
    {
        if (!StoreScope::isSuperAdmin()) {
            http_response_code(403);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Accès réservé au Super Admin',
            ]);
            exit;
        }
    }

    private function listUsers(): void
    {
        $roleFilter = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';

        $sql = "SELECT u.id, u.name, u.email, u.role_id, u.store_id, u.is_active, u.last_login, u.created_at,
                       r.name AS role_name, s.name AS store_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                LEFT JOIN stores s ON u.store_id = s.id
                WHERE u.deleted_at IS NULL";
        $params = [];

        if ($roleFilter !== '') {
            $sql .= ' AND LOWER(REPLACE(r.name, \' \', \'_\')) = ?';
            $params[] = strtolower(str_replace(' ', '_', $roleFilter));
        }

        if ($status === 'active') {
            $sql .= ' AND u.is_active = 1';
        } elseif ($status === 'suspended') {
            $sql .= ' AND u.is_active = 0';
        }

        $storeId = isset($_GET['store_id']) ? (int) $_GET['store_id'] : 0;
        if ($storeId > 0) {
            $sql .= ' AND u.store_id = ?';
            $params[] = $storeId;
        }

        $sql .= ' ORDER BY u.created_at DESC LIMIT 500';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data'   => array_map([$this, 'formatUser'], $rows),
        ]);
    }

    private function getUser(int $id): void
    {
        $user = $this->fetchUserRow($id);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Utilisateur introuvable']);
            return;
        }

        $permStmt = $this->db->prepare(
            'SELECT p.id, p.name, p.description
             FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ?'
        );
        $permStmt->execute([$user['role_id']]);
        $user['permissions'] = $permStmt->fetchAll(PDO::FETCH_ASSOC);

        $storeIds = [];
        if (StoreScope::tableExists($this->db, 'user_stores')) {
            $st = $this->db->prepare('SELECT store_id FROM user_stores WHERE user_id = ?');
            $st->execute([$id]);
            $storeIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        }
        $user['store_ids'] = $storeIds;

        echo json_encode(['status' => 'success', 'data' => $this->formatUser($user, true)]);
    }

    private function createUser(): void
    {
        $data = $this->jsonInput();
        $name = trim($data['name'] ?? '');
        $email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $data['password'] ?? '';
        $roleId = (int) ($data['role_id'] ?? 0);

        if ($name === '' || $email === '' || strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Nom, email et mot de passe (8+ car.) requis']);
            return;
        }

        if (!$this->isAssignableRoleId($roleId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Rôle non autorisé (Admin, Manager, Cashier, Staff uniquement)']);
            return;
        }

        $chk = $this->db->prepare('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL');
        $chk->execute([$email]);
        if ($chk->fetch()) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Email déjà utilisé']);
            return;
        }

        $storeId = isset($data['store_id']) && $data['store_id'] !== '' ? (int) $data['store_id'] : null;
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pin = password_hash($data['pin'] ?? '1234', PASSWORD_BCRYPT);

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO users (name, email, password_hash, pin_hash, role_id, store_id, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)'
            );
            $stmt->execute([$name, $email, $hash, $pin, $roleId, $storeId]);
            $newId = (int) $this->db->lastInsertId();

            $this->syncUserStores($newId, $data['store_ids'] ?? ($storeId ? [$storeId] : []));
            $this->logAction('user_created', $newId, 'success');

            echo json_encode([
                'status'  => 'success',
                'message' => 'Utilisateur créé',
                'id'      => $newId,
            ]);
        } catch (PDOException $e) {
            $this->respondDbError($e);
        }
    }

    private function updateUser(int $id): void
    {
        if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Modifiez votre profil via les paramètres compte']);
            return;
        }

        $existing = $this->fetchUserRow($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Utilisateur introuvable']);
            return;
        }

        if ($this->roleSlug((string) $existing['role_name']) === 'super_admin') {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Impossible de modifier un Super Admin']);
            return;
        }

        $data = $this->jsonInput();
        $name = trim($data['name'] ?? $existing['name']);
        $email = filter_var(trim($data['email'] ?? $existing['email']), FILTER_SANITIZE_EMAIL);
        $roleId = isset($data['role_id']) ? (int) $data['role_id'] : (int) $existing['role_id'];

        if (!$this->isAssignableRoleId($roleId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Rôle non autorisé']);
            return;
        }

        $storeId = array_key_exists('store_id', $data)
            ? ($data['store_id'] !== '' && $data['store_id'] !== null ? (int) $data['store_id'] : null)
            : $existing['store_id'];

        try {
            $sql = 'UPDATE users SET name = ?, email = ?, role_id = ?, store_id = ?';
            $params = [$name, $email, $roleId, $storeId];

            if (!empty($data['password']) && strlen($data['password']) >= 8) {
                $sql .= ', password_hash = ?';
                $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
            }

            $sql .= ' WHERE id = ? AND deleted_at IS NULL';
            $params[] = $id;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            if (isset($data['store_ids'])) {
                $this->syncUserStores($id, $data['store_ids']);
            }

            $this->logAction('user_updated', $id, 'success');
            echo json_encode(['status' => 'success', 'message' => 'Utilisateur mis à jour']);
        } catch (PDOException $e) {
            $this->respondDbError($e);
        }
    }

    private function setUserActive(int $id, bool $active): void
    {
        if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Vous ne pouvez pas suspendre votre propre compte']);
            return;
        }

        $existing = $this->fetchUserRow($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Utilisateur introuvable']);
            return;
        }

        if ($this->roleSlug((string) $existing['role_name']) === 'super_admin') {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Impossible de suspendre un Super Admin']);
            return;
        }

        $stmt = $this->db->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);

        $this->logAction($active ? 'user_activated' : 'user_suspended', $id, 'success');
        echo json_encode([
            'status'  => 'success',
            'message' => $active ? 'Compte réactivé' : 'Compte suspendu',
        ]);
    }

    private function resetPassword(int $id): void
    {
        $existing = $this->fetchUserRow($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Utilisateur introuvable']);
            return;
        }

        $data = $this->jsonInput();
        $password = $data['password'] ?? '';
        if (strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Mot de passe : 8 caractères minimum']);
            return;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $id]);

        $this->logAction('password_reset', $id, 'success');
        echo json_encode(['status' => 'success', 'message' => 'Mot de passe réinitialisé']);
    }

    private function listActivity(): void
    {
        $limit = min(300, max(20, (int) ($_GET['limit'] ?? 150)));
        $userId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int) $_GET['user_id'] : null;
        $type = $_GET['type'] ?? 'all';

        $stats = [
            'logins_today'      => 0,
            'logins_failed'     => 0,
            'admin_actions'     => 0,
            'unique_users_today'=> 0,
        ];

        try {
            $stats['logins_today'] = (int) $this->db->query(
                "SELECT COUNT(*) FROM user_activity_logs
                 WHERE DATE(created_at) = CURDATE()
                 AND (action IN ('login_success','login_attempt') AND status = 'success'
                      OR action = 'login_success')"
            )->fetchColumn();

            $stats['logins_failed'] = (int) $this->db->query(
                "SELECT COUNT(*) FROM user_activity_logs
                 WHERE DATE(created_at) = CURDATE()
                 AND (action IN ('login_failed','login_attempt') AND status = 'failed')"
            )->fetchColumn();

            $stats['admin_actions'] = (int) $this->db->query(
                "SELECT COUNT(*) FROM user_activity_logs
                 WHERE DATE(created_at) = CURDATE()
                 AND action NOT IN ('login_success','login_failed','login_attempt','logout')"
            )->fetchColumn();

            $stats['unique_users_today'] = (int) $this->db->query(
                "SELECT COUNT(DISTINCT user_id) FROM user_activity_logs
                 WHERE DATE(created_at) = CURDATE() AND user_id IS NOT NULL"
            )->fetchColumn();
        } catch (PDOException $e) {
            error_log('listActivity stats: ' . $e->getMessage());
        }

        $sql = "SELECT l.id, l.user_id, u.name AS user_name, u.email, l.action, l.ip_address,
                       l.user_agent, l.status, l.created_at
                FROM user_activity_logs l
                LEFT JOIN users u ON u.id = l.user_id
                WHERE 1=1";
        $params = [];

        if ($userId) {
            $sql .= ' AND l.user_id = ?';
            $params[] = $userId;
        }

        if ($type === 'login') {
            $sql .= " AND (l.action IN ('login_success','login_failed','login_attempt','logout')
                      OR l.action LIKE 'login_%')";
        } elseif ($type === 'admin') {
            $sql .= " AND l.action NOT IN ('login_success','login_failed','login_attempt','logout')
                      AND l.action NOT LIKE 'login_%'";
        }

        $sql .= ' ORDER BY l.created_at DESC LIMIT ' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted = array_map([$this, 'formatActivityEntry'], $rows);

        echo json_encode([
            'status' => 'success',
            'data'   => $formatted,
            'stats'  => $stats,
        ]);
    }

    private function formatActivityEntry(array $row): array
    {
        $action = (string) ($row['action'] ?? '');
        $status = (string) ($row['status'] ?? '');

        $category = 'admin';
        if (in_array($action, ['login_success', 'login_failed', 'login_attempt', 'logout'], true)
            || strpos($action, 'login_') === 0) {
            $category = 'login';
        }

        $labels = [
            'login_success'  => 'Connexion réussie',
            'login_failed'   => 'Échec de connexion',
            'login_attempt'  => $status === 'success' ? 'Connexion réussie' : 'Échec de connexion',
            'logout'         => 'Déconnexion',
            'user_created'   => 'Utilisateur créé',
            'user_updated'   => 'Utilisateur modifié',
            'user_suspended' => 'Compte suspendu',
            'user_activated' => 'Compte réactivé',
            'password_reset' => 'Mot de passe réinitialisé',
            'role_permissions_updated' => 'Permissions mises à jour',
        ];

        $label = $labels[$action] ?? null;
        if (!$label && preg_match('/^(user_created|user_updated|user_suspended|user_activated|password_reset)_user_(\d+)$/', $action, $m)) {
            $map = [
                'user_created'   => 'Création utilisateur #%s',
                'user_updated'   => 'Modification utilisateur #%s',
                'user_suspended' => 'Suspension utilisateur #%s',
                'user_activated' => 'Réactivation utilisateur #%s',
                'password_reset' => 'Reset mot de passe #%s',
            ];
            $label = sprintf($map[$m[1]] ?? $action, $m[2]);
        }
        if (!$label && preg_match('/^role_permissions_updated_user_(\d+)$/', $action, $m)) {
            $label = 'Permissions mises à jour (rôle #' . $m[1] . ')';
        }
        if (!$label) {
            $label = str_replace('_', ' ', $action);
        }

        return [
            'id'            => (int) ($row['id'] ?? 0),
            'user_id'       => $row['user_id'] ? (int) $row['user_id'] : null,
            'user_name'     => $row['user_name'] ?? null,
            'email'         => $row['email'] ?? null,
            'action'        => $action,
            'action_label'  => $label,
            'category'      => $category,
            'ip_address'    => $row['ip_address'] ?? '',
            'user_agent'    => $row['user_agent'] ?? '',
            'status'        => $status,
            'created_at'    => $row['created_at'] ?? null,
        ];
    }

    private function listRoles(): void
    {
        $stmt = $this->db->query('SELECT id, name, description FROM roles ORDER BY id ASC');
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($roles as &$r) {
            $r['slug'] = $this->roleSlug($r['name']);
            $r['can_assign'] = in_array($r['slug'], self::ASSIGNABLE_ROLES, true);
        }
        echo json_encode(['status' => 'success', 'data' => $roles]);
    }

    private function listPermissions(): void
    {
        $stmt = $this->db->query('SELECT id, name, description FROM permissions ORDER BY name ASC');
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function getRolePermissions(): void
    {
        $roleId = (int) ($_GET['role_id'] ?? 0);
        if ($roleId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'role_id requis']);
            return;
        }

        $all = $this->db->query('SELECT id, name, description FROM permissions ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $assigned = $this->db->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?');
        $assigned->execute([$roleId]);
        $ids = array_map('intval', $assigned->fetchAll(PDO::FETCH_COLUMN));

        foreach ($all as &$p) {
            $p['assigned'] = in_array((int) $p['id'], $ids, true);
        }

        echo json_encode(['status' => 'success', 'data' => ['role_id' => $roleId, 'permissions' => $all]]);
    }

    private function updateRolePermissions(): void
    {
        $data = $this->jsonInput();
        $roleId = (int) ($data['role_id'] ?? 0);
        $permissionIds = $data['permission_ids'] ?? [];

        if ($roleId <= 0 || !is_array($permissionIds)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Données invalides']);
            return;
        }

        $roleStmt = $this->db->prepare('SELECT name FROM roles WHERE id = ?');
        $roleStmt->execute([$roleId]);
        $roleName = $roleStmt->fetchColumn();
        if (!$roleName || $this->roleSlug((string) $roleName) === 'super_admin') {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Permissions du Super Admin non modifiables']);
            return;
        }

        try {
            $this->db->beginTransaction();
            $this->db->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$roleId]);
            $ins = $this->db->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach ($permissionIds as $pid) {
                $ins->execute([$roleId, (int) $pid]);
            }
            $this->db->commit();
            $this->logAction('role_permissions_updated', $roleId, 'success');
            echo json_encode(['status' => 'success', 'message' => 'Permissions mises à jour']);
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->respondDbError($e);
        }
    }

    private function fetchUserRow(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT u.*, r.name AS role_name, s.name AS store_name
             FROM users u
             JOIN roles r ON u.role_id = r.id
             LEFT JOIN stores s ON u.store_id = s.id
             WHERE u.id = ? AND u.deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function isAssignableRoleId(int $roleId): bool
    {
        $stmt = $this->db->prepare('SELECT name FROM roles WHERE id = ?');
        $stmt->execute([$roleId]);
        $name = $stmt->fetchColumn();
        return $name && in_array($this->roleSlug((string) $name), self::ASSIGNABLE_ROLES, true);
    }

    private function syncUserStores(int $userId, $storeIds): void
    {
        if (!StoreScope::tableExists($this->db, 'user_stores')) {
            return;
        }
        if (!is_array($storeIds)) {
            $storeIds = [];
        }
        $this->db->prepare('DELETE FROM user_stores WHERE user_id = ?')->execute([$userId]);
        $ins = $this->db->prepare('INSERT IGNORE INTO user_stores (user_id, store_id) VALUES (?, ?)');
        foreach ($storeIds as $sid) {
            $sid = (int) $sid;
            if ($sid > 0) {
                $ins->execute([$userId, $sid]);
            }
        }
    }

    private function formatUser(array $row, bool $extended = false): array
    {
        $out = [
            'id'         => (int) $row['id'],
            'name'       => $row['name'],
            'email'      => $row['email'],
            'role_id'    => (int) $row['role_id'],
            'role_name'  => $row['role_name'] ?? '',
            'role_slug'  => $this->roleSlug($row['role_name'] ?? ''),
            'store_id'   => isset($row['store_id']) ? ($row['store_id'] ? (int) $row['store_id'] : null) : null,
            'store_name' => $row['store_name'] ?? null,
            'is_active'  => (bool) ($row['is_active'] ?? true),
            'last_login' => $row['last_login'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
        if ($extended) {
            $out['permissions'] = $row['permissions'] ?? [];
            $out['store_ids'] = $row['store_ids'] ?? [];
        }
        return $out;
    }

    private function roleSlug(string $role): string
    {
        return strtolower(str_replace(' ', '_', trim($role)));
    }

    private function jsonInput(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }

    private function logAction(string $action, int $targetId, string $status): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO user_activity_logs (user_id, action, ip_address, user_agent, status)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action . '_user_' . $targetId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            $status,
        ]);
    }

    private function respondDbError(PDOException $e): void
    {
        error_log('UsersController: ' . $e->getMessage());
        http_response_code(500);
        $msg = 'Erreur base de données';
        if (defined('APP_DEBUG') && APP_DEBUG) {
            $msg .= ': ' . $e->getMessage();
        }
        echo json_encode(['status' => 'error', 'message' => $msg]);
    }
}
