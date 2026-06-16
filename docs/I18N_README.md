# i18n / l10n integration

Overview:

- Languages stored under `/languages/<lang>/*.php` as associative arrays.
- `LanguageManager.php` resolves and applies language preference (session, cookie, DB, Accept-Language).
- `TranslationService.php` loads and caches translation files.
- Use helper `__t($key, $section)` in PHP templates.
- Frontend loader at `/public/js/i18n.js`, offline cache in `/public/js/i18n-indexeddb.js`.

Quick usage:

- Include `languages/LanguageMiddleware.php` early in bootstrap to set `ACTIVE_LANG`.
- In PHP templates: `<?php echo __t('dashboard','dashboard'); ?>`
- Language switcher: include `public/includes/language_switcher.php` in navigation.
- API: GET `/api/v1/i18n?lang=fr&section=dashboard` returns JSON translations.

Extending:

- Add language folder `languages/pt/` and files.
- Admin UI can write into `email_templates` and `sms_templates` table for multilingual templates.
