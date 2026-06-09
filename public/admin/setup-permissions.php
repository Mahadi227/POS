<?php
/**
 * Initialise les permissions (une fois) — Super Admin uniquement.
 */
require_once '../../includes/Config/session.php';
requireLogin();

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if ($roleSlug !== 'super_admin') {
    die('Accès refusé');
}

require_once '../../includes/Database/Database.php';
$db = Database::getInstance()->getConnection();

$sql = file_get_contents(__DIR__ . '/../../includes/Database/migrations/003_user_management.sql');
$statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));

$done = 0;
$errors = [];
foreach ($statements as $stmt) {
    if ($stmt === '' || strpos($stmt, '--') === 0) {
        continue;
    }
    try {
        $db->exec($stmt);
        $done++;
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:24px;">';
echo '<h1>Permissions — ' . ($errors ? 'partiel' : 'OK') . '</h1>';
echo '<p>' . $done . ' requête(s) exécutée(s).</p>';
if ($errors) {
    echo '<ul>';
    foreach ($errors as $e) {
        echo '<li>' . htmlspecialchars($e) . '</li>';
    }
    echo '</ul>';
}
echo '<p><a href="users.php">Gestion utilisateurs</a></p></body></html>';
