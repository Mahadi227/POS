<?php
declare(strict_types=1);

/**
 * Warehouse workspace entry — redirects to WMS dashboard.
 */
require __DIR__ . '/includes/bootstrap.php';

header('Location: ' . $wmsBase . 'dashboard.php');
exit;
