<?php
/**
 * Mise à jour base succursales (une fois) — ouvrir dans le navigateur puis supprimer ce fichier en production.
 */
require_once '../../includes/Config/session.php';
requireLogin();

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if (!in_array($roleSlug, ['admin', 'manager', 'super_admin'], true)) {
    die('Accès refusé');
}

require_once '../../includes/Database/Database.php';
require_once '../../includes/Database/StoreSchemaMigrator.php';

$db = Database::getInstance()->getConnection();
StoreSchemaMigrator::ensure($db);

$cols = $db->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' ORDER BY ORDINAL_POSITION"
)->fetchAll(PDO::FETCH_COLUMN);

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Setup succursales</title></head><body style="font-family:sans-serif;padding:24px;">';
echo '<h1>Setup succursales — OK</h1>';
echo '<p>Colonnes <code>stores</code> :</p><ul>';
foreach ($cols as $c) {
    echo '<li>' . htmlspecialchars($c) . '</li>';
}
echo '</ul><p><a href="stores.php">Retour aux succursales</a></p></body></html>';
