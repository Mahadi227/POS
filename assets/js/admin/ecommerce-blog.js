document.addEventListener('DOMContentLoaded', () => {
    const { t, esc, formatDate, updateLastUpdated, toast, bindModalClose } = EcommerceUI;
    const modal = document.getElementById('ecomBlogModal');
    const form = document.getElementById('ecomBlogForm');
    let postsCache = [];

    async function load() {
        const data = await AdminAPI.getEcommerceBlog();
        const tbody = document.querySelector('#ecomBlogTable tbody');
        if (!tbody) return;
        postsCache = data.items || [];
        tbody.innerHTML = postsCache.length
            ? postsCache.map((p) => `<tr>
                <td>${esc(p.title)}</td>
                <td><code>${esc(p.slug)}</code></td>
                <td>${Number(p.is_published) ? '✓' : '—'}</td>
                <td>${formatDate(p.published_at || p.created_at)}</td>
                <td>
                    <button type="button" class="ecom-btn ecom-btn--ghost" data-edit-id="${p.id}">Edit</button>
                    <button type="button" class="ecom-btn ecom-btn--ghost ecom-btn--danger" data-delete="${p.id}">${esc(t('delete'))}</button>
                </td>
            </tr>`).join('')
            : `<tr><td colspan="5">${esc(t('ecom_no_posts'))}</td></tr>`;

        tbody.querySelectorAll('[data-edit-id]').forEach((btn) => {
            btn.addEventListener('click', () => openModal(postsCache.find((x) => String(x.id) === String(btn.dataset.editId))));
        });
        tbody.querySelectorAll('[data-delete]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (!confirm(t('delete_confirm'))) return;
                await AdminAPI.deleteEcommerceBlogPost(btn.dataset.delete);
                toast(t('ecom_saved'));
                load();
            });
        });
        updateLastUpdated();
    }

    function openModal(post = null) {
        document.getElementById('ecomPostId').value = post?.id || '';
        document.getElementById('ecomPostTitle').value = post?.title || '';
        document.getElementById('ecomPostSlug').value = post?.slug || '';
        document.getElementById('ecomPostExcerpt').value = post?.excerpt || '';
        document.getElementById('ecomPostBody').value = post?.body || '';
        document.getElementById('ecomPostPublished').checked = !!Number(post?.is_published);
        document.getElementById('ecomBlogModalTitle').textContent = post ? 'Edit' : t('ecom_add_post');
        modal?.showModal();
    }

    document.getElementById('ecomAddPostBtn')?.addEventListener('click', () => openModal());
    bindModalClose(modal);
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            title: document.getElementById('ecomPostTitle').value,
            slug: document.getElementById('ecomPostSlug').value,
            excerpt: document.getElementById('ecomPostExcerpt').value,
            body: document.getElementById('ecomPostBody').value,
            is_published: document.getElementById('ecomPostPublished').checked ? 1 : 0,
        };
        const id = document.getElementById('ecomPostId').value ? Number(document.getElementById('ecomPostId').value) : null;
        await AdminAPI.saveEcommerceBlogPost(payload, id);
        modal?.close();
        toast(t('ecom_saved'));
        load();
    });

    load();
    document.addEventListener('ecom:refresh', load);
});
