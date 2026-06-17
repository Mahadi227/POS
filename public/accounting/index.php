<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Accounting Dashboard';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> — RetailPOS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: Inter, sans-serif; margin: 0; background: #f1f5f9; color: #0f172a; }
        .wrap { max-width: 960px; margin: 48px auto; padding: 0 24px; }
        .card { background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { margin: 0 0 8px; font-size: 1.5rem; }
        p { color: #64748b; margin: 0 0 24px; }
        a { color: #2563eb; text-decoration: none; }
        .actions a { display: inline-block; margin-right: 16px; font-weight: 500; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Accounting Workspace</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['name'] ?? 'User'); ?>.</p>
            <div class="actions">
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </div>
</body>
</html>
