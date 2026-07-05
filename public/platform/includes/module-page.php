<?php
declare(strict_types=1);

/**
 * Render a platform module page (stub for modules not yet implemented).
 *
 * @param array<string, mixed> $options
 */
function plat_module_page(string $moduleId, array $options = []): void
{
    require_once __DIR__ . '/navigation.php';

    $def = plat_nav_find($moduleId);
    if (!$def) {
        http_response_code(404);
        echo 'Module not found';
        exit;
    }

    if (!empty($def['ready']) && !empty($options['force_stub'])) {
        // allow override
    } elseif (!empty($def['ready']) && empty($options['render_stub'])) {
        return;
    }

    if (!empty($def['external'])) {
        header('Location: ' . plat_public_href((string) $def['external']));
        exit;
    }

    global $activePlatPage, $pageTitle, $extraStyles, $extraScripts, $pageI18n;

    $activePlatPage = $moduleId;
    $pageTitle = __t((string) $def['label'], 'platform');
    $extraStyles = array_merge(['platform-module.css'], $options['extraStyles'] ?? []);
    $extraScripts = $options['extraScripts'] ?? ['platform-common.js'];
    $pageI18n = plat_i18n(array_merge([
        'plat_module_coming_soon', 'plat_module_phase', 'plat_module_desc',
        'plat_module_related', 'plat_open_module',
    ], $options['i18n'] ?? []));

    $phase = (int) ($def['phase'] ?? 2);
    $descKey = 'plat_module_desc_' . $moduleId;
    $moduleDesc = __t($descKey, 'platform');
    if ($moduleDesc === $descKey) {
        $moduleDesc = __t('plat_module_desc', 'platform');
    }

    $related = plat_nav_related($moduleId);

    require __DIR__ . '/layout-start.php';
    ?>
    <div class="plat-module">
        <section class="plat-module-hero">
            <div class="plat-module-hero__intro">
                <div class="plat-module-badge">
                    <span class="material-icons-round" aria-hidden="true"><?php echo htmlspecialchars((string) $def['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php echo __t('plat_module_coming_soon', 'platform'); ?>
                </div>
                <h2 class="plat-module-hero__title"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="plat-module-hero__desc"><?php echo htmlspecialchars($moduleDesc, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="plat-module-phase">
                <span class="material-icons-round" aria-hidden="true">rocket_launch</span>
                <?php echo sprintf(__t('plat_module_phase', 'platform'), $phase); ?>
            </div>
        </section>

        <section class="plat-panel plat-module-panel">
            <div class="plat-module-placeholder">
                <span class="material-icons-round" aria-hidden="true">construction</span>
                <h3><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo htmlspecialchars($moduleDesc, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </section>

        <?php if ($related): ?>
        <section class="plat-panel plat-module-related">
            <h2><?php echo __t('plat_module_related', 'platform'); ?></h2>
            <div class="plat-module-related-grid">
                <?php foreach ($related as $item): ?>
                <a class="plat-module-related-card" href="<?php echo htmlspecialchars(!empty($item['external']) ? plat_public_href((string) $item['external']) : plat_href((string) $item['path']), ENT_QUOTES, 'UTF-8'); ?>"
                   <?php echo !empty($item['external']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                    <span class="material-icons-round" aria-hidden="true"><?php echo htmlspecialchars((string) $item['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><?php echo __t((string) $item['label'], 'platform'); ?></span>
                    <span class="material-icons-round plat-module-related-arrow" aria-hidden="true">arrow_forward</span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
    <?php
    require __DIR__ . '/layout-end.php';
    exit;
}
