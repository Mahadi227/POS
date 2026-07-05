<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (!$canManageEcom) {
    header('Location: dashboard.php');
    exit;
}

$activeEcomPage = 'blog';
$pageTitle = __t('ecom_blog_title', 'admin');
$extraScripts = ['ecommerce-common.js', 'ecommerce-blog.js'];
$pageI18n = ecom_i18n([
    'ecom_blog_subtitle', 'ecom_add_post', 'ecom_post_title', 'ecom_post_slug', 'ecom_post_excerpt',
    'ecom_post_body', 'ecom_post_published', 'ecom_no_posts', 'ecom_saved', 'delete_confirm',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<section class="ecom-page-head ecom-page-head--split">
    <p class="ecom-page-head__desc"><?php echo __t('ecom_blog_subtitle', 'admin'); ?></p>
    <button type="button" class="ecom-btn ecom-btn--primary" id="ecomAddPostBtn">
        <span class="material-icons-round">add</span>
        <?php echo __t('ecom_add_post', 'admin'); ?>
    </button>
</section>

<div class="ecom-table-wrap">
    <table class="ecom-table" id="ecomBlogTable">
        <thead>
            <tr>
                <th><?php echo __t('ecom_post_title', 'admin'); ?></th>
                <th><?php echo __t('ecom_post_slug', 'admin'); ?></th>
                <th><?php echo __t('ecom_post_published', 'admin'); ?></th>
                <th><?php echo __t('col_date', 'admin'); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="5"><?php echo __t('loading', 'admin'); ?></td></tr></tbody>
    </table>
</div>

<dialog class="ecom-modal ecom-modal--wide" id="ecomBlogModal">
    <form id="ecomBlogForm" class="ecom-form">
        <div class="ecom-modal__head">
            <h2 id="ecomBlogModalTitle"><?php echo __t('ecom_add_post', 'admin'); ?></h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="<?php echo __t('close', 'admin'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="ecom-modal__body">
            <input type="hidden" name="id" id="ecomPostId">
            <label class="ecom-field">
                <span><?php echo __t('ecom_post_title', 'admin'); ?></span>
                <input type="text" name="title" id="ecomPostTitle" required>
            </label>
            <label class="ecom-field">
                <span><?php echo __t('ecom_post_slug', 'admin'); ?></span>
                <input type="text" name="slug" id="ecomPostSlug" placeholder="auto">
            </label>
            <label class="ecom-field">
                <span><?php echo __t('ecom_post_excerpt', 'admin'); ?></span>
                <textarea name="excerpt" id="ecomPostExcerpt" rows="2"></textarea>
            </label>
            <label class="ecom-field">
                <span><?php echo __t('ecom_post_body', 'admin'); ?></span>
                <textarea name="body" id="ecomPostBody" rows="8"></textarea>
            </label>
            <label class="ecom-check">
                <input type="checkbox" name="is_published" id="ecomPostPublished" value="1">
                <span><?php echo __t('ecom_post_published', 'admin'); ?></span>
            </label>
        </div>
        <div class="ecom-modal__foot">
            <button type="button" class="ecom-btn ecom-btn--ghost" data-close-modal><?php echo __t('cancel', 'admin'); ?></button>
            <button type="submit" class="ecom-btn ecom-btn--primary"><?php echo __t('save', 'admin'); ?></button>
        </div>
    </form>
</dialog>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
