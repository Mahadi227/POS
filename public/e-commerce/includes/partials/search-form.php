<?php
/** @var string $searchFormClass @var string $searchAction @var string $searchValue @var string $searchPanelId @var bool $searchShowBtn */
$searchFormClass = trim($searchFormClass ?? '');
$searchAction = $searchAction ?? ecom_href('search/');
$searchValue = $searchValue ?? '';
$searchPanelId = $searchPanelId ?? 'ecom-search-panel';
$searchShowBtn = !empty($searchShowBtn);
$searchWrapClass = 'ecom-search-wrap' . ($searchFormClass !== '' ? ' ecom-search-wrap--' . preg_replace('/[^a-z0-9_-]/i', '', $searchFormClass) : '');
?>
<div class="<?php echo htmlspecialchars($searchWrapClass, ENT_QUOTES, 'UTF-8'); ?>" data-ecom-search-wrap>
    <form class="ecom-search<?php echo $searchFormClass !== '' ? ' ecom-search--' . htmlspecialchars($searchFormClass, ENT_QUOTES, 'UTF-8') : ''; ?>" action="<?php echo htmlspecialchars($searchAction, ENT_QUOTES, 'UTF-8'); ?>" method="get" role="search">
        <span class="material-icons-round ecom-search__icon" aria-hidden="true">search</span>
        <input
            type="search"
            name="q"
            class="ecom-search__input"
            data-ecom-search-input
            value="<?php echo htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8'); ?>"
            placeholder="<?php echo __t('ecom_search_placeholder', 'ecommerce'); ?>"
            autocomplete="off"
            autocapitalize="off"
            spellcheck="false"
            aria-autocomplete="list"
            aria-controls="<?php echo htmlspecialchars($searchPanelId, ENT_QUOTES, 'UTF-8'); ?>"
            aria-expanded="false"
        >
        <button type="button" class="ecom-search__clear" data-ecom-search-clear hidden aria-label="<?php echo __t('ecom_search_clear', 'ecommerce'); ?>">
            <span class="material-icons-round" aria-hidden="true">close</span>
        </button>
        <?php if ($searchShowBtn): ?>
        <button type="submit" class="ecom-btn ecom-btn--primary ecom-search__submit"><?php echo __t('ecom_search_btn', 'ecommerce'); ?></button>
        <?php endif; ?>
    </form>
    <div
        class="ecom-search-panel"
        id="<?php echo htmlspecialchars($searchPanelId, ENT_QUOTES, 'UTF-8'); ?>"
        data-ecom-search-panel
        hidden
        role="listbox"
        aria-label="<?php echo __t('ecom_search_suggestions', 'ecommerce'); ?>"
    ></div>
</div>
