<?php
// includes/Config/config.php

define('DB_HOST', 'localhost');
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
