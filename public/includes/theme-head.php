<?php
/** Early theme bootstrap — prevents flash; reads localStorage before paint. */
$themeAccent = $themeAccent ?? '#2563eb';
$themePortal = $themePortal ?? 'admin';
?>
<script>
(function () {
    try {
        var accent = <?php echo json_encode($themeAccent, JSON_UNESCAPED_UNICODE); ?>;
        var darkMeta = '#111827';
        var mode = localStorage.getItem('app-theme')
            || localStorage.getItem('admin-theme')
            || localStorage.getItem('theme')
            || 'light';
        if (mode !== 'light' && mode !== 'dark' && mode !== 'system') mode = 'light';
        var effective = mode === 'dark'
            ? 'dark'
            : (mode === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        var html = document.documentElement;
        html.setAttribute('data-theme', effective);
        html.setAttribute('data-theme-mode', mode);
        html.setAttribute('data-portal', <?php echo json_encode($themePortal, JSON_UNESCAPED_UNICODE); ?>);
        html.setAttribute('data-theme-accent', accent);
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.setAttribute('content', effective === 'dark' ? darkMeta : accent);
    } catch (e) { /* ignore */ }
})();
</script>
