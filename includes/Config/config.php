<?php
// includes/Config/config.php

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pos_system_db');

// Security
define('JWT_SECRET', 'super_secret_pos_key_2026_change_in_prod');
define('JWT_EXPIRATION', 86400); // 24 hours

// Application settings
define('APP_URL', 'http://localhost:6060/dashboard/workstation/Pos system');
define('APP_NAME', 'Modern POS');

/** true = messages SQL détaillés dans les réponses API (développement local) */
define('APP_DEBUG', true);

/** Stripe (optional — demo mode if empty) */
define('STRIPE_SECRET_KEY', getenv('STRIPE_SECRET_KEY') ?: '');
define('STRIPE_WEBHOOK_SECRET', getenv('STRIPE_WEBHOOK_SECRET') ?: '');
define('STRIPE_PUBLISHABLE_KEY', getenv('STRIPE_PUBLISHABLE_KEY') ?: '');

/** SaaS multi-tenant routing (Phase 4) — e.g. retailpos.local for dev */
define('SAAS_BASE_DOMAIN', getenv('SAAS_BASE_DOMAIN') ?: '');
define('SAAS_TENANT_PARAM', 'tenant');

/** Paystack (Africa) — optional */
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: '');
define('PAYSTACK_PUBLIC_KEY', getenv('PAYSTACK_PUBLIC_KEY') ?: '');

require_once __DIR__ . '/../Helpers/UrlHelper.php';
