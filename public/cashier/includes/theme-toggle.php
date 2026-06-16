<?php
if (!function_exists('__t')) {
    require_once __DIR__ . '/../../../languages/LanguageMiddleware.php';
    require_once __DIR__ . '/../../../languages/helpers.php';
}
$themeToggleClass = $themeToggleClass ?? '';
$themeLabel = $themeLabel ?? __t('theme', 'cashier');
?>
<button type="button" class="icon-btn theme-toggle <?php echo htmlspecialchars($themeToggleClass, ENT_QUOTES, 'UTF-8'); ?>" id="theme-toggle" aria-label="<?php echo htmlspecialchars($themeLabel, ENT_QUOTES, 'UTF-8'); ?>">
    <span class="material-icons-round">dark_mode</span>
</button>
