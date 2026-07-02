        </div>

    </main>

</div>



<div id="mgrToast" class="inv-toast mgr-toast" role="status" aria-live="polite"></div>



<script>

    window.MANAGER_CONFIG = <?php echo json_encode($managerConfig, JSON_UNESCAPED_UNICODE); ?>;

    <?php if (!empty($pageI18n)): ?>

    window.MANAGER_I18N = <?php echo json_encode($pageI18n, JSON_UNESCAPED_UNICODE); ?>;

    <?php endif; ?>

</script>

<script src="<?php echo $mgrPrefix; ?>../../assets/js/manager/manager-api.js?v=4"></script>

<script src="<?php echo $mgrPrefix; ?>../../assets/js/app-theme.js?v=2"></script>

<?php foreach ($pageScripts ?? [] as $js): ?>

<script src="<?php echo $mgrPrefix; ?>../../assets/js/manager/<?php echo htmlspecialchars($js); ?>?v=7"></script>

<?php endforeach; ?>

<script src="<?php echo $mgrPrefix; ?>../../assets/js/admin/admin-sidebar.js?v=2"></script>

<script>

(function () {

    const locale = window.MANAGER_CONFIG?.locale || 'en-US';

    const headerDate = document.getElementById('mgrHeaderDate');

    if (headerDate) {

        headerDate.textContent = new Date().toLocaleDateString(locale, {

            weekday: 'long',

            year: 'numeric',

            month: 'long',

            day: 'numeric',

        });

    }



    document.getElementById('mgrRefreshBtn')?.addEventListener('click', () => {

        const btn = document.getElementById('mgrRefreshBtn');

        btn?.classList.add('spinning');

        document.dispatchEvent(new CustomEvent('mgr:refresh'));

        setTimeout(() => btn?.classList.remove('spinning'), 700);

    });

})();

</script>

</body>

</html>

