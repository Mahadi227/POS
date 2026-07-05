        </div>
    </main>
</div>
<script>
window.PLATFORM_CONFIG = {
    apiBase: <?php echo json_encode($apiBase, JSON_THROW_ON_ERROR); ?>,
    apiV2Base: <?php echo json_encode($apiV2Base, JSON_THROW_ON_ERROR); ?>,
    locale: <?php echo json_encode($activeLang === 'fr' ? 'fr-FR' : 'en-US', JSON_THROW_ON_ERROR); ?>,
    i18n: <?php echo json_encode($platI18n, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="<?php echo $assetsBase; ?>/js/app-theme.js?v=3"></script>
<?php foreach ($extraScripts as $js): ?>
<script src="<?php echo $assetsBase; ?>/js/platform/<?php echo htmlspecialchars($js, ENT_QUOTES, 'UTF-8'); ?>?v=2"></script>
<?php endforeach; ?>
</body>
</html>
