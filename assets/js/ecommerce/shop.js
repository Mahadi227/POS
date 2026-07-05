(function () {
    'use strict';

    var select = document.querySelector('[data-ecom-store-filter]');
    if (!select) return;

    select.addEventListener('change', function () {
        var params = new URLSearchParams(window.location.search);
        params.set('store_id', select.value);
        params.delete('page');
        window.location.search = params.toString();
    });
})();
