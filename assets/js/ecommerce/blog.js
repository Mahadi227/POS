(function () {
    'use strict';

    var input = document.querySelector('[data-ecom-blog-search]');
    var clearBtn = document.querySelector('[data-ecom-blog-clear]');
    var grid = document.querySelector('[data-ecom-blog-grid]');
    var featured = document.querySelector('[data-ecom-blog-featured]');
    var status = document.querySelector('[data-ecom-blog-status]');
    var noMatch = document.querySelector('[data-ecom-blog-no-match]');
    if (!input) return;

    var cards = [];
    if (featured) {
        var featuredCard = featured.querySelector('[data-ecom-blog-card]');
        if (featuredCard) cards.push(featuredCard);
    }
    if (grid) {
        cards = cards.concat(Array.prototype.slice.call(grid.querySelectorAll('[data-ecom-blog-card]')));
    }
    var total = cards.length;
    var statusTemplate = (window.ECOM && ECOM.i18n && ECOM.i18n.blog_filter_status) || ':visible of :total articles';

    function updateStatus(visible) {
        if (!status) return;
        status.textContent = statusTemplate
            .replace(':visible', String(visible))
            .replace(':total', String(total));
    }

    function filterPosts() {
        var query = input.value.trim().toLowerCase();
        var visible = 0;

        cards.forEach(function (card) {
            var title = (card.getAttribute('data-post-title') || card.textContent || '').toLowerCase();
            var match = query === '' || title.indexOf(query) !== -1;
            card.hidden = !match;
            if (match) visible += 1;
        });

        if (clearBtn) clearBtn.hidden = query === '';
        if (noMatch) noMatch.hidden = visible > 0 || query === '';
        updateStatus(visible);
    }

    input.addEventListener('input', filterPosts);

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            input.focus();
            filterPosts();
        });
    }

    if (total > 0) updateStatus(total);
})();
