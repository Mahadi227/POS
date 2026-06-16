<?php
$themeToggleClass = $themeToggleClass ?? 'mgr-header-icon ad-header-icon';
$themeLabel = $themeLabel ?? __t('theme', 'manager');
?>
<button type="button" class="icon-btn theme-toggle <?php echo htmlspecialchars($themeToggleClass, ENT_QUOTES, 'UTF-8'); ?>" id="theme-toggle" aria-label="<?php echo htmlspecialchars($themeLabel, ENT_QUOTES, 'UTF-8'); ?>">
    <span class="material-icons-round">dark_mode</span>
</button>
