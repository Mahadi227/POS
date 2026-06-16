<?php
// language_switcher.php: include in top nav
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$lang = defined('ACTIVE_LANG') ? ACTIVE_LANG : ($_SESSION['lang'] ?? ($_COOKIE['lang'] ?? 'en'));
// compute path to public/change_language.php relative to this script's URL
if (!isset($changeUrl)) {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $publicRoot = dirname($scriptDir); // one level up from includes
    $changeUrl = $publicRoot . '/change_language.php';
}
?>
<div class="lang-switcher">
    <select name="lang" id="langSelect">
        <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>>🇬🇧 English</option>
        <option value="fr" <?= $lang === 'fr' ? 'selected' : '' ?>>🇫🇷 Français</option>
    </select>
</div>
<style>
    .lang-switcher select {
        border: none;
        background: transparent;
        padding: 6px;
        font-size: 14px
    }

    @media(max-width:600px) {
        .lang-switcher select {
            font-size: 13px
        }
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sel = document.getElementById('langSelect');
        if (!sel) return;
        sel.addEventListener('change', async function() {
            const lang = this.value;
            try {
                // call I18N setter if available, fallback to redirect
                if (window.I18N && window.I18N.setLanguage) {
                    await window.I18N.setLanguage(lang);
                    // update select state if needed
                    sel.value = lang;
                } else {
                    // fallback: post to change_language.php
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '<?php echo $changeUrl; ?>';
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'lang';
                    inp.value = lang;
                    form.appendChild(inp);
                    document.body.appendChild(form);
                    form.submit();
                }
            } catch (err) {
                console.error('Language change failed', err);
                // fallback to full-page change
                window.location = '<?php echo $changeUrl; ?>?lang=' + encodeURIComponent(lang);
            }
        });
    });
</script>