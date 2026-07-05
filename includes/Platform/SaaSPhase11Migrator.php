<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantSchemaMigrator.php';

/**
 * SaaS Phase 11 — platform knowledge base categories and articles.
 */
final class SaaSPhase11Migrator
{
    private static bool $done = false;

    public const VERSION = '029_saas_phase11';

    public static function ensure(PDO $db): bool
    {
        if (self::$done) {
            return self::isApplied($db);
        }
        self::$done = true;

        if (!TenantSchemaMigrator::isReady($db)) {
            TenantSchemaMigrator::ensure($db);
        }

        if (self::isApplied($db)) {
            self::seed($db);
            return true;
        }

        self::createTables($db);
        self::seed($db);
        self::markApplied($db);

        return true;
    }

    public static function isApplied(PDO $db): bool
    {
        if (!self::tableExists($db, 'schema_migrations')) {
            return false;
        }
        $stmt = $db->prepare('SELECT 1 FROM schema_migrations WHERE version = ? LIMIT 1');
        $stmt->execute([self::VERSION]);
        return (bool) $stmt->fetchColumn();
    }

    private static function createTables(PDO $db): void
    {
        $path = dirname(__DIR__) . '/Database/migrations/029_platform_knowledge_base.sql';
        if (!is_file($path)) {
            return;
        }

        $sql = file_get_contents($path) ?: '';
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement === '') {
                continue;
            }
            try {
                $db->exec($statement);
            } catch (PDOException $e) {
                error_log('SaaSPhase11Migrator: ' . $e->getMessage());
            }
        }
    }

    private static function seed(PDO $db): void
    {
        if (!self::tableExists($db, 'platform_kb_categories')) {
            return;
        }

        $count = (int) $db->query('SELECT COUNT(*) FROM platform_kb_categories')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $categories = [
            ['getting-started', 'rocket_launch', 10, 'Getting started', 'Premiers pas'],
            ['billing', 'payments', 20, 'Billing & plans', 'Facturation et plans'],
            ['subscriptions', 'autorenew', 30, 'Subscriptions', 'Abonnements'],
            ['api', 'api', 40, 'API & integrations', 'API et intégrations'],
            ['security', 'shield', 50, 'Security & access', 'Sécurité et accès'],
            ['troubleshooting', 'build', 60, 'Troubleshooting', 'Dépannage'],
        ];

        $stmt = $db->prepare(
            'INSERT INTO platform_kb_categories (slug, icon, sort_order, name_en, name_fr) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($categories as [$slug, $icon, $order, $en, $fr]) {
            $stmt->execute([$slug, $icon, $order, $en, $fr]);
        }

        $catIds = [];
        foreach ($db->query('SELECT id, slug FROM platform_kb_categories')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $catIds[$row['slug']] = (int) $row['id'];
        }

        if (!self::tableExists($db, 'platform_kb_articles')) {
            return;
        }

        $articles = [
            [
                'getting-started', 'welcome-to-retailpos-cloud', 'guide',
                'Welcome to RetailPOS Cloud', 'Bienvenue sur RetailPOS Cloud',
                'Overview of the multi-tenant SaaS platform.', 'Vue d\'ensemble de la plateforme SaaS multi-locataires.',
                '<p>RetailPOS Cloud lets you operate organizations (tenants) with isolated data, subscription plans, and module entitlements.</p><p>Start from the Organizations list, open a tenant detail page, and use support impersonation when troubleshooting.</p>',
                '<p>RetailPOS Cloud permet d\'exploiter des organisations (locataires) avec des données isolées, des plans d\'abonnement et des droits par module.</p><p>Commencez par la liste des organisations, ouvrez une fiche locataire et utilisez l\'impersonation support pour le dépannage.</p>',
                'tenant', 10, 1,
            ],
            [
                'billing', 'understanding-mrr', 'article',
                'Understanding MRR and billing events', 'Comprendre le MRR et les événements de facturation',
                'How platform billing metrics are calculated.', 'Comment les métriques de facturation plateforme sont calculées.',
                '<p>Monthly recurring revenue (MRR) sums active subscription plan prices. Billing events log payments, refunds, and failed charges across all tenants.</p>',
                '<p>Le revenu récurrent mensuel (MRR) additionne les tarifs des abonnements actifs. Les événements de facturation enregistrent paiements, remboursements et échecs pour tous les locataires.</p>',
                'support', 10, 1,
            ],
            [
                'subscriptions', 'trial-to-active', 'guide',
                'Trial to active subscription', 'Passage de l\'essai à l\'abonnement actif',
                'Lifecycle of tenant subscriptions.', 'Cycle de vie des abonnements locataires.',
                '<p>Tenants may start on trial. When a plan is assigned and payment succeeds, status moves to active. Past-due subscriptions appear in the support attention queue.</p>',
                '<p>Les locataires peuvent démarrer en essai. Lorsqu\'un plan est assigné et le paiement réussi, le statut passe à actif. Les abonnements en retard apparaissent dans la file d\'attention support.</p>',
                'support', 10, 1,
            ],
            [
                'api', 'api-keys-overview', 'article',
                'API keys and developer portal', 'Clés API et portail développeur',
                'Tenant API access for integrations.', 'Accès API locataire pour les intégrations.',
                '<p>Tenants with the API module can issue keys from the developer portal. Rate limits apply per tenant. Use the platform API monitor for usage metrics.</p>',
                '<p>Les locataires avec le module API peuvent émettre des clés depuis le portail développeur. Des limites de débit s\'appliquent par locataire. Utilisez le moniteur API plateforme pour les métriques d\'usage.</p>',
                'tenant', 10, 1,
            ],
            [
                'security', 'impersonation-policy', 'article',
                'Support impersonation policy', 'Politique d\'impersonation support',
                'Rules for logging in as a tenant admin.', 'Règles pour se connecter en tant qu\'admin locataire.',
                '<p>Platform support may impersonate tenant admins for troubleshooting. All impersonation sessions are audited. Never share credentials; use the platform console action instead.</p>',
                '<p>Le support plateforme peut impersonner les admins locataires pour le dépannage. Toutes les sessions sont auditées. Ne partagez jamais les identifiants ; utilisez l\'action de la console plateforme.</p>',
                'support', 10, 1,
            ],
            [
                'troubleshooting', 'tenant-suspended', 'faq',
                'Why is a tenant suspended?', 'Pourquoi un locataire est-il suspendu ?',
                'Common causes and resolution steps.', 'Causes fréquentes et étapes de résolution.',
                '<p>Suspension may be manual (support action) or automated (billing). Restore from the organization detail page after resolving the underlying issue.</p>',
                '<p>La suspension peut être manuelle (action support) ou automatique (facturation). Rétablissez depuis la fiche organisation après résolution du problème.</p>',
                'support', 10, 1,
            ],
        ];

        $ins = $db->prepare(
            'INSERT INTO platform_kb_articles
             (category_id, slug, article_type, title_en, title_fr, summary_en, summary_fr, body_en, body_fr, audience, sort_order, is_published)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($articles as $row) {
            [$catSlug, $slug, $type, $titleEn, $titleFr, $sumEn, $sumFr, $bodyEn, $bodyFr, $audience, $sort, $pub] = $row;
            $catId = $catIds[$catSlug] ?? null;
            if (!$catId) {
                continue;
            }
            $ins->execute([$catId, $slug, $type, $titleEn, $titleFr, $sumEn, $sumFr, $bodyEn, $bodyFr, $audience, $sort, $pub]);
        }
    }

    private static function markApplied(PDO $db): void
    {
        if (!self::tableExists($db, 'schema_migrations')) {
            return;
        }
        $stmt = $db->prepare('INSERT IGNORE INTO schema_migrations (version) VALUES (?)');
        $stmt->execute([self::VERSION]);
    }

    private static function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}
