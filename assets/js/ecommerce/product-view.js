(function () {
    'use strict';

    var wrap = document.querySelector('[data-ecom-product-actions]');
    if (!wrap) return;

    var input = wrap.querySelector('[data-ecom-qty]');
    var minus = wrap.querySelector('[data-ecom-qty-minus]');
    var plus = wrap.querySelector('[data-ecom-qty-plus]');

    function clampQty() {
        if (!input) return 1;
        var min = parseInt(input.min || '1', 10) || 1;
        var max = parseInt(input.max || '9999', 10) || 9999;
        var val = parseInt(input.value, 10);
        if (Number.isNaN(val) || val < min) val = min;
        if (val > max) val = max;
        input.value = String(val);
        return val;
    }

    if (minus) {
        minus.addEventListener('click', function () {
            if (!input) return;
            input.value = String(Math.max(parseInt(input.min || '1', 10) || 1, clampQty() - 1));
        });
    }

    if (plus) {
        plus.addEventListener('click', function () {
            if (!input) return;
            var max = parseInt(input.max || '9999', 10) || 9999;
            input.value = String(Math.min(max, clampQty() + 1));
        });
    }

    if (input) {
        input.addEventListener('change', clampQty);
        input.addEventListener('blur', clampQty);
    }
})();
