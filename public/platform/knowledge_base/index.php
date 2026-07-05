<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'knowledge_base';
$pageTitle = __t('plat_nav_knowledge_base', 'platform');
$extraStyles = ['platform-knowledge.css'];
$extraScripts = ['platform-common.js', 'platform-knowledge.js'];
$pageI18n = plat_i18n([
    'plat_nav_knowledge_base', 'plat_nav_support', 'plat_no_data', 'plat_search', 'plat_clear_filters',
    'loading', 'load_error', 'action_success', 'action_error',
    'plat_kb_subtitle', 'plat_kb_badge', 'plat_kb_count', 'plat_kb_load_error',
    'plat_kb_empty', 'plat_kb_empty_hint', 'plat_kb_kpi_categories', 'plat_kb_kpi_articles',
    'plat_kb_kpi_published', 'plat_kb_kpi_drafts', 'plat_kb_view_support', 'plat_kb_add',
    'plat_kb_add_title', 'plat_kb_edit_title', 'plat_kb_save', 'plat_kb_cancel',
    'plat_kb_filter_all_categories', 'plat_kb_filter_all_audience', 'plat_kb_filter_all_status',
    'plat_kb_filter_published', 'plat_kb_filter_draft', 'plat_kb_col_title', 'plat_kb_col_category',
    'plat_kb_col_type', 'plat_kb_col_audience', 'plat_kb_col_status', 'plat_kb_col_updated',
    'plat_kb_status_published', 'plat_kb_status_draft', 'plat_kb_publish', 'plat_kb_unpublish',
    'plat_kb_open', 'plat_kb_detail_close', 'plat_kb_field_slug', 'plat_kb_field_category',
    'plat_kb_field_type', 'plat_kb_field_audience', 'plat_kb_field_title_en', 'plat_kb_field_title_fr',
    'plat_kb_field_summary_en', 'plat_kb_field_summary_fr', 'plat_kb_field_body_en', 'plat_kb_field_body_fr',
    'plat_kb_type_article', 'plat_kb_type_guide', 'plat_kb_type_faq',
    'plat_kb_audience_tenant', 'plat_kb_audience_support', 'plat_kb_audience_public',
    'plat_kb_all_categories',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-kb">
    <div class="plat-kb-error" id="platKbError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platKbErrorText"></span>
    </div>
    <div class="plat-kb-alert" id="platKbAlert" hidden role="status"></div>

    <section class="plat-kb-hero" aria-labelledby="platKbHeroTitle">
        <div class="plat-kb-hero__intro">
            <div class="plat-kb-badge">
                <span class="material-icons-round" aria-hidden="true">menu_book</span>
                <?php echo __t('plat_kb_badge', 'platform'); ?>
            </div>
            <h2 class="plat-kb-hero__title" id="platKbHeroTitle"><?php echo __t('plat_nav_knowledge_base', 'platform'); ?></h2>
            <p class="plat-kb-hero__desc"><?php echo __t('plat_kb_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-kb-hero__actions">
            <p class="plat-kb-count" id="platKbCount" aria-live="polite"></p>
            <div class="plat-kb-hero__btns">
                <a href="<?php echo htmlspecialchars(plat_href('support/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-kb-link-btn">
                    <span class="material-icons-round" aria-hidden="true">support_agent</span>
                    <?php echo __t('plat_kb_view_support', 'platform'); ?>
                </a>
                <button type="button" class="plat-kb-add-btn" id="platKbAddOpen">
                    <span class="material-icons-round" aria-hidden="true">add</span>
                    <?php echo __t('plat_kb_add', 'platform'); ?>
                </button>
            </div>
        </div>
    </section>

    <section class="plat-kpi-grid plat-kb-kpi-grid" id="platKbKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">category</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_kb_kpi_categories', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platKbKpiCats">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">article</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_kb_kpi_articles', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platKbKpiArticles">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">visibility</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_kb_kpi_published', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platKbKpiPublished">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">edit_note</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_kb_kpi_drafts', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platKbKpiDrafts">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-kb-cats-panel" id="platKbCatsPanel" hidden>
        <h3 class="plat-kb-cats-title"><?php echo __t('plat_kb_all_categories', 'platform'); ?></h3>
        <div class="plat-kb-cats" id="platKbCats" aria-live="polite"></div>
    </section>

    <section class="plat-panel plat-kb-panel">
        <div class="plat-kb-toolbar">
            <div class="plat-kb-search-wrap">
                <span class="material-icons-round plat-kb-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platKbSearch" class="plat-search plat-kb-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platKbCategoryFilter" class="plat-select">
                <option value=""><?php echo __t('plat_kb_filter_all_categories', 'platform'); ?></option>
            </select>
            <select id="platKbAudienceFilter" class="plat-select">
                <option value=""><?php echo __t('plat_kb_filter_all_audience', 'platform'); ?></option>
                <option value="tenant"><?php echo __t('plat_kb_audience_tenant', 'platform'); ?></option>
                <option value="support"><?php echo __t('plat_kb_audience_support', 'platform'); ?></option>
                <option value="public"><?php echo __t('plat_kb_audience_public', 'platform'); ?></option>
            </select>
            <select id="platKbPublishedFilter" class="plat-select">
                <option value=""><?php echo __t('plat_kb_filter_all_status', 'platform'); ?></option>
                <option value="yes"><?php echo __t('plat_kb_filter_published', 'platform'); ?></option>
                <option value="no"><?php echo __t('plat_kb_filter_draft', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-kb-clear-btn" id="platKbClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-kb-grid" id="platKbGrid" aria-live="polite">
            <div class="plat-kb-loading">
                <span class="plat-kb-spinner" aria-hidden="true"></span>
                <?php echo __t('loading', 'platform'); ?>…
            </div>
        </div>

        <div class="plat-kb-empty" id="platKbEmpty" hidden>
            <span class="material-icons-round" aria-hidden="true">menu_book</span>
            <h3><?php echo __t('plat_kb_empty', 'platform'); ?></h3>
            <p><?php echo __t('plat_kb_empty_hint', 'platform'); ?></p>
        </div>
    </section>
</div>

<div class="plat-kb-drawer" id="platKbDrawer" hidden role="dialog" aria-modal="true">
    <div class="plat-kb-drawer__backdrop" data-close-drawer></div>
    <aside class="plat-kb-drawer__panel">
        <header class="plat-kb-drawer__head">
            <div>
                <p class="plat-kb-drawer__meta" id="platKbDrawerMeta"></p>
                <h3 id="platKbDrawerTitle">—</h3>
            </div>
            <button type="button" class="plat-kb-drawer__close" data-close-drawer aria-label="<?php echo __t('plat_kb_detail_close', 'platform'); ?>">
                <span class="material-icons-round" aria-hidden="true">close</span>
            </button>
        </header>
        <div class="plat-kb-drawer__body" id="platKbDrawerBody"></div>
        <footer class="plat-kb-drawer__foot">
            <button type="button" class="plat-kb-btn" id="platKbDrawerEdit"><?php echo __t('plat_kb_edit_title', 'platform'); ?></button>
            <button type="button" class="plat-kb-btn plat-kb-btn--primary" id="platKbDrawerPublish"></button>
        </footer>
    </aside>
</div>

<div class="plat-kb-modal" id="platKbModal" hidden role="dialog" aria-modal="true">
    <div class="plat-kb-modal__backdrop" data-close-modal></div>
    <form class="plat-kb-modal__panel" id="platKbForm">
        <header class="plat-kb-modal__head">
            <h3 id="platKbModalTitle"><?php echo __t('plat_kb_add_title', 'platform'); ?></h3>
            <button type="button" class="plat-kb-drawer__close" data-close-modal aria-label="<?php echo __t('plat_kb_cancel', 'platform'); ?>">
                <span class="material-icons-round" aria-hidden="true">close</span>
            </button>
        </header>
        <div class="plat-kb-modal__body">
            <input type="hidden" name="id" id="platKbFormId">
            <div class="plat-kb-field-row">
                <label class="plat-kb-field">
                    <span><?php echo __t('plat_kb_field_category', 'platform'); ?></span>
                    <select name="category_id" id="platKbFormCategory" required></select>
                </label>
                <label class="plat-kb-field">
                    <span><?php echo __t('plat_kb_field_type', 'platform'); ?></span>
                    <select name="article_type" id="platKbFormType">
                        <option value="article"><?php echo __t('plat_kb_type_article', 'platform'); ?></option>
                        <option value="guide"><?php echo __t('plat_kb_type_guide', 'platform'); ?></option>
                        <option value="faq"><?php echo __t('plat_kb_type_faq', 'platform'); ?></option>
                    </select>
                </label>
            </div>
            <div class="plat-kb-field-row">
                <label class="plat-kb-field">
                    <span><?php echo __t('plat_kb_field_audience', 'platform'); ?></span>
                    <select name="audience" id="platKbFormAudience">
                        <option value="tenant"><?php echo __t('plat_kb_audience_tenant', 'platform'); ?></option>
                        <option value="support"><?php echo __t('plat_kb_audience_support', 'platform'); ?></option>
                        <option value="public"><?php echo __t('plat_kb_audience_public', 'platform'); ?></option>
                    </select>
                </label>
                <label class="plat-kb-field">
                    <span><?php echo __t('plat_kb_field_slug', 'platform'); ?></span>
                    <input type="text" name="slug" id="platKbFormSlug" placeholder="auto-generated">
                </label>
            </div>
            <label class="plat-kb-field">
                <span><?php echo __t('plat_kb_field_title_en', 'platform'); ?></span>
                <input type="text" name="title_en" id="platKbFormTitleEn" required maxlength="200">
            </label>
            <label class="plat-kb-field">
                <span><?php echo __t('plat_kb_field_title_fr', 'platform'); ?></span>
                <input type="text" name="title_fr" id="platKbFormTitleFr" maxlength="200">
            </label>
            <label class="plat-kb-field">
                <span><?php echo __t('plat_kb_field_summary_en', 'platform'); ?></span>
                <textarea name="summary_en" id="platKbFormSummaryEn" rows="2"></textarea>
            </label>
            <label class="plat-kb-field">
                <span><?php echo __t('plat_kb_field_summary_fr', 'platform'); ?></span>
                <textarea name="summary_fr" id="platKbFormSummaryFr" rows="2"></textarea>
            </label>
            <label class="plat-kb-field">
                <span><?php echo __t('plat_kb_field_body_en', 'platform'); ?></span>
                <textarea name="body_en" id="platKbFormBodyEn" rows="5" required></textarea>
            </label>
            <label class="plat-kb-field">
                <span><?php echo __t('plat_kb_field_body_fr', 'platform'); ?></span>
                <textarea name="body_fr" id="platKbFormBodyFr" rows="5"></textarea>
            </label>
            <label class="plat-kb-check">
                <input type="checkbox" name="is_published" id="platKbFormPublished">
                <?php echo __t('plat_kb_status_published', 'platform'); ?>
            </label>
        </div>
        <footer class="plat-kb-modal__foot">
            <button type="button" class="plat-kb-btn" data-close-modal><?php echo __t('plat_kb_cancel', 'platform'); ?></button>
            <button type="submit" class="plat-kb-btn plat-kb-btn--primary"><?php echo __t('plat_kb_save', 'platform'); ?></button>
        </footer>
    </form>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>
