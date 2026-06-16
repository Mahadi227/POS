<script>
(function () {
    try {
        var t = localStorage.getItem('app-theme')
            || localStorage.getItem('admin-theme')
            || localStorage.getItem('theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    } catch (e) { /* ignore */ }
})();
</script>
