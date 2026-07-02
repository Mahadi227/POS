<?php
// Legacy admin path — redirects to Cash Registers portal
$id = isset($_GET['id']) ? '?id=' . urlencode((string) $_GET['id']) : '';
header('Location: ../../cash-registers/edit_register.php' . $id);
exit;
