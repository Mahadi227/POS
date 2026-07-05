<?php
$id = (int) ($_GET['id'] ?? 0);
$target = $id > 0 ? 'companies/view.php?id=' . $id : 'companies/index.php';
header('Location: ' . $target, true, 301);
exit;
