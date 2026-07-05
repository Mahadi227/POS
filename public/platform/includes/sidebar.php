<?php
declare(strict_types=1);

require_once __DIR__ . '/navigation.php';

$activePlatPage = $activePlatPage ?? '';
?>
<ul class="nav-menu plat-nav-menu">
<?php foreach (plat_nav_sections() as $section): ?>
    <li class="nav-section"><?php echo __t($section['section'], 'platform'); ?></li>
    <?php foreach ($section['items'] as $item):
        $href = !empty($item['external']) ? plat_public_href((string) $item['external']) : plat_href((string) $item['path']);
        $isActive = $activePlatPage === $item['id'];
        $isSoon = empty($item['ready']);
    ?>
    <li>
        <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
           class="nav-link<?php echo $isActive ? ' active' : ''; ?><?php echo $isSoon ? ' plat-nav-link--soon' : ''; ?>"
           <?php echo !empty($item['external']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
            <span class="material-icons-round"><?php echo htmlspecialchars((string) $item['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
            <span><?php echo __t((string) $item['label'], 'platform'); ?></span>
            <?php if ($isSoon): ?>
            <span class="plat-nav-soon" title="<?php echo htmlspecialchars(__t('plat_module_coming_soon', 'platform'), ENT_QUOTES, 'UTF-8'); ?>">β</span>
            <?php endif; ?>
        </a>
    </li>
    <?php endforeach; ?>
<?php endforeach; ?>
    <li class="nav-section"><?php echo __t('plat_section_system', 'platform'); ?></li>
    <li>
        <a href="<?php echo htmlspecialchars('../admin/index.php', ENT_QUOTES, 'UTF-8'); ?>" class="nav-link">
            <span class="material-icons-round">storefront</span>
            <span><?php echo __t('plat_open_tenant_app', 'platform'); ?></span>
        </a>
    </li>
    <li>
        <a href="<?php echo htmlspecialchars(plat_href('logout.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-link plat-nav-logout">
            <span class="material-icons-round">logout</span>
            <span><?php echo __t('plat_logout', 'platform'); ?></span>
        </a>
    </li>
</ul>
