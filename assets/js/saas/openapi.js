(function () {
    'use strict';

    const cfg = window.OPENAPI_CONFIG || {};

    document.getElementById('openapiThemeToggle')?.addEventListener('click', () => {
        if (window.AppTheme?.toggle) {
            window.AppTheme.toggle();
        }
    });

    if (typeof SwaggerUIBundle === 'undefined' || !cfg.specUrl) {
        return;
    }

    SwaggerUIBundle({
        url: cfg.specUrl,
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [SwaggerUIBundle.presets.apis],
        docExpansion: 'list',
        filter: true,
        tryItOutEnabled: true,
        displayRequestDuration: true,
        persistAuthorization: true,
    });
})();
