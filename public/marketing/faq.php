<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$activePage = 'faq';
$pageTitle = __t('mkt_faq_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
require __DIR__ . '/includes/partials/faq.php';
require __DIR__ . '/includes/partials/cta.php';
require __DIR__ . '/includes/layout-end.php';
