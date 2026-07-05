(function () {
    'use strict';

    /* Mobile navigation */
    const toggle = document.getElementById('mkt-menu-toggle');
    const nav = document.getElementById('mkt-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', (e) => {
            if (!nav.contains(e.target) && !toggle.contains(e.target)) {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /* Sticky header shadow */
    const header = document.getElementById('mkt-header');
    if (header) {
        window.addEventListener('scroll', () => {
            header.classList.toggle('is-scrolled', window.scrollY > 10);
        }, { passive: true });
    }

    /* FAQ accordion */
    document.querySelectorAll('.mkt-faq__question').forEach((btn) => {
        btn.addEventListener('click', () => {
            const item = btn.closest('.mkt-faq__item');
            const wasOpen = item.classList.contains('is-open');
            document.querySelectorAll('.mkt-faq__item').forEach((i) => i.classList.remove('is-open'));
            if (!wasOpen) item.classList.add('is-open');
        });
    });

    /* Lazy load images */
    if ('loading' in HTMLImageElement.prototype) {
        document.querySelectorAll('img[loading="lazy"]').forEach((img) => {
            if (img.complete) img.classList.add('is-loaded');
            else img.addEventListener('load', () => img.classList.add('is-loaded'));
        });
    } else {
        const lazyImages = document.querySelectorAll('img[loading="lazy"]');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) img.src = img.dataset.src;
                    img.classList.add('is-loaded');
                    observer.unobserve(img);
                }
            });
        });
        lazyImages.forEach((img) => observer.observe(img));
    }

    /* Contact / demo form basic validation */
    document.querySelectorAll('.mkt-form[data-validate]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            const email = form.querySelector('[type="email"]');
            if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                e.preventDefault();
                email.focus();
                return;
            }
        });
    });

    /* Video placeholder */
    document.querySelectorAll('.mkt-video__play').forEach((btn) => {
        btn.addEventListener('click', () => {
            const wrap = btn.closest('.mkt-video');
            if (!wrap) return;
            wrap.innerHTML = '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1" title="RetailPOS Demo" allow="autoplay; encrypted-media" allowfullscreen style="width:100%;height:100%;border:0;"></iframe>';
        });
    });

    /* Service worker registration (PWA) */
    if ('serviceWorker' in navigator) {
        const swPath = document.querySelector('link[rel="manifest"]');
        if (swPath) {
            const base = swPath.getAttribute('href').replace('manifest.json', 'sw.js');
            navigator.serviceWorker.register(base).catch(() => {});
        }
    }
})();
