<?php
/**
 * Shared theme toggle button — bound by app-theme.js
 * @var string $themeToggleClass extra CSS classes
 * @var string $themeLabel aria-label
 */
if (!function_exists('__t')) {
    require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
    require_once __DIR__ . '/../../languages/helpers.php';
}
$themeToggleClass = trim('icon-btn theme-toggle ad-header-icon ' . ($themeToggleClass ?? ''));
$themeLabel = $themeLabel ?? __t('theme', 'dashboard');
?>
<button type="button" class="<?php echo htmlspecialchars($themeToggleClass, ENT_QUOTES, 'UTF-8'); ?>" id="theme-toggle" data-theme-toggle aria-label="<?php echo htmlspecialchars($themeLabel, ENT_QUOTES, 'UTF-8'); ?>" aria-pressed="false">
    <span class="material-icons-round">dark_mode</span>
</button>
