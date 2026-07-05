(function () {
    'use strict';

    var input = document.querySelector('[data-ecom-brands-search]');
    var clearBtn = document.querySelector('[data-ecom-brands-clear]');
    var grid = document.querySelector('[data-ecom-brand-grid]');
    var status = document.querySelector('[data-ecom-brands-status]');
    var noMatch = document.querySelector('[data-ecom-brands-no-match]');
    if (!input || !grid) return;

    var cards = Array.prototype.slice.call(grid.querySelectorAll('[data-ecom-brand-card]'));
    var total = cards.length;
    var statusTemplate = (window.ECOM && ECOM.i18n && ECOM.i18n.brands_filter_status) || ':visible of :total brands';

    function updateStatus(visible) {
        if (!status) return;
        status.textContent = statusTemplate
            .replace(':visible', String(visible))
            .replace(':total', String(total));
    }

    function filterBrands() {
        var query = input.value.trim().toLowerCase();
        var visible = 0;

        cards.forEach(function (card) {
            var name = (card.getAttribute('data-brand-name') || card.textContent || '').toLowerCase();
            var match = query === '' || name.indexOf(query) !== -1;
            card.hidden = !match;
            if (match) visible += 1;
        });

        if (clearBtn) clearBtn.hidden = query === '';
        if (noMatch) noMatch.hidden = visible > 0 || query === '';
        updateStatus(visible);
    }

    input.addEventListener('input', filterBrands);

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            input.focus();
            filterBrands();
        });
    }

    updateStatus(total);
})();
