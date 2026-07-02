<?php
// Legacy admin WMS URL — redirects to Warehouse Portal
$id = isset($_GET['id']) ? '?id=' . urlencode((string) $_GET['id']) : '';
header('Location: ../../warehouse/management/edit_warehouse.php' . $id);
exit;
