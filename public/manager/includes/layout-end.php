        </div>
    </main>
</div>

<div id="mgrToast" class="inv-toast mgr-toast" role="status" aria-live="polite"></div>

<script>
    window.MANAGER_CONFIG = <?php echo json_encode($managerConfig, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo $mgrPrefix; ?>../../assets/js/manager/manager-api.js?v=1"></script>
<?php foreach ($pageScripts ?? [] as $js): ?>
<script src="<?php echo $mgrPrefix; ?>../../assets/js/manager/<?php echo htmlspecialchars($js); ?>?v=1"></script>
<?php endforeach; ?>
<script>
(function () {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    function toggleSidebar() {
        sidebar?.classList.toggle('open');
        overlay?.classList.toggle('active');
    }
    mobileMenuBtn?.addEventListener('click', toggleSidebar);
    overlay?.addEventListener('click', toggleSidebar);

    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        const html = document.documentElement;
        const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        document.querySelector('#theme-toggle .material-icons-round').textContent =
            next === 'dark' ? 'light_mode' : 'dark_mode';
    });
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        const icon = document.querySelector('#theme-toggle .material-icons-round');
        if (icon) icon.textContent = 'light_mode';
    }

    document.getElementById('mgrRefreshBtn')?.addEventListener('click', () => {
        document.dispatchEvent(new CustomEvent('mgr:refresh'));
    });
})();
</script>
</body>
</html>
