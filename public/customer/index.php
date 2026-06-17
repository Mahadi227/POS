<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal — RetailPOS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: Inter, sans-serif; margin: 0; background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1); text-align: center; max-width: 420px; }
        h1 { font-size: 1.25rem; margin: 0 0 12px; }
        p { color: #64748b; }
        a { color: #2563eb; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Customer Portal</h1>
        <p>Welcome. This workspace is ready for future customer features.</p>
        <p><a href="../logout.php">Logout</a></p>
    </div>
</body>
</html>
