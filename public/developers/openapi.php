<?php
$specUrl = '../../docs/api/openapi-v2.yaml';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RetailPOS API v2 — OpenAPI</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body { margin: 0; background: #0f172a; }
        .swagger-back { padding: 12px 20px; background: #1e293b; }
        .swagger-back a { color: #93c5fd; text-decoration: none; }
    </style>
</head>
<body>
<div class="swagger-back"><a href="index.php">← Developer portal</a></div>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script>
SwaggerUIBundle({
    url: <?php echo json_encode($specUrl); ?>,
    dom_id: '#swagger-ui',
    deepLinking: true,
    presets: [SwaggerUIBundle.presets.apis],
});
</script>
</body>
</html>
