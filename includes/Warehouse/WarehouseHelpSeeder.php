<?php
declare(strict_types=1);

/** Seeds default help content (idempotent). */
class WarehouseHelpSeeder
{
    public static function seed(PDO $db): void
    {
        $stmt = $db->query('SELECT COUNT(*) FROM help_categories');
        if ($stmt && (int) $stmt->fetchColumn() > 0) {
            return;
        }

        $categories = [
            ['inventory', 'inventory_2', 10, 'Inventory', 'Inventaire', null],
            ['receiving', 'move_to_inbox', 20, 'Receiving Goods', 'Réception', '["receiving_officer","warehouse_manager","storekeeper"]'],
            ['dispatch', 'local_shipping', 30, 'Dispatch', 'Expédition', '["dispatch_officer","warehouse_manager"]'],
            ['transfers', 'swap_horiz', 40, 'Warehouse Transfers', 'Transferts', null],
            ['barcode', 'qr_code_scanner', 50, 'Barcode Scanner', 'Scanner code-barres', null],
            ['qr', 'qr_code_2', 55, 'QR Code Scanner', 'Scanner QR', null],
            ['batch', 'layers', 60, 'Batch Tracking', 'Suivi lots', null],
            ['serial', 'tag', 65, 'Serial Numbers', 'Numéros de série', null],
            ['stock_count', 'fact_check', 70, 'Stock Count', 'Comptage stock', null],
            ['adjustment', 'tune', 75, 'Inventory Adjustment', 'Ajustements', null],
            ['locations', 'place', 80, 'Warehouse Locations', 'Emplacements', null],
            ['notifications', 'notifications', 90, 'Notifications', 'Notifications', null],
            ['reports', 'assessment', 100, 'Reports', 'Rapports', null],
            ['offline', 'cloud_off', 110, 'Offline Mode', 'Mode hors ligne', null],
            ['profile', 'account_circle', 120, 'User Profile', 'Profil utilisateur', null],
            ['settings', 'settings', 130, 'Settings', 'Paramètres', '["warehouse_manager","admin","super_admin"]'],
            ['security', 'shield', 140, 'Security', 'Sécurité', null],
        ];

        $catIns = $db->prepare(
            'INSERT INTO help_categories (slug, icon, sort_order, name_en, name_fr, roles) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $catIds = [];
        foreach ($categories as [$slug, $icon, $sort, $en, $fr, $roles]) {
            $catIns->execute([$slug, $icon, $sort, $en, $fr, $roles]);
            $catIds[$slug] = (int) $db->lastInsertId();
        }

        $artIns = $db->prepare(
            'INSERT INTO help_articles (category_id, slug, article_type, title_en, title_fr, summary_en, summary_fr, body_en, body_fr, module, roles, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $guides = [
            ['receiving', 'guide-receiving', 'Receiving Products', 'Réception produits',
                'Step-by-step goods receipt workflow.', 'Flux de réception pas à pas.',
                "<ol><li>Open <strong>Receiving → Receive Stock</strong>.</li><li>Select supplier delivery or PO.</li><li>Scan barcodes or search products.</li><li>Enter quantities and batch/expiry if required.</li><li>Complete quality inspection when enabled.</li><li>Confirm receipt — inventory updates automatically.</li></ol>",
                "<ol><li>Ouvrez <strong>Réception → Réception stock</strong>.</li><li>Sélectionnez la livraison ou le bon de commande.</li><li>Scannez ou recherchez les produits.</li><li>Saisissez quantités et lot/péremption.</li><li>Effectuez le contrôle qualité si activé.</li><li>Confirmez — le stock est mis à jour.</li></ol>",
                'receiving', null, 1],
            ['dispatch', 'guide-dispatch', 'Dispatching Products', 'Expédition produits',
                'From pick list to delivery confirmation.', 'Du prélèvement à la confirmation livraison.',
                "<ol><li>Open dispatch orders and generate pick list.</li><li>Pick items and mark packed.</li><li>Run final verification scan.</li><li>Create shipment with driver/vehicle details.</li><li>Confirm delivery when complete.</li></ol>",
                "<ol><li>Ouvrez les ordres d'expédition et générez la liste de prélèvement.</li><li>Prélevez et marquez emballé.</li><li>Effectuez la vérification finale.</li><li>Créez l'expédition avec chauffeur/véhicule.</li><li>Confirmez la livraison.</li></ol>",
                'dispatch', '["dispatch_officer","warehouse_manager"]', 2],
            ['transfers', 'guide-transfers', 'Creating Transfers', 'Créer des transferts',
                'Move stock between warehouses or branches.', 'Déplacer le stock entre entrepôts ou succursales.',
                "<ol><li>Go to <strong>Transfers → Warehouse Transfer</strong>.</li><li>Select source and destination.</li><li>Add products and quantities.</li><li>Submit for approval if required.</li><li>Track status in incoming/outgoing lists.</li></ol>",
                "<ol><li>Allez à <strong>Transferts → Transfert entrepôt</strong>.</li><li>Sélectionnez source et destination.</li><li>Ajoutez produits et quantités.</li><li>Soumettez pour approbation si requis.</li><li>Suivez le statut dans les listes.</li></ol>",
                'transfers', null, 3],
            ['stock_count', 'guide-stock-count', 'Performing Inventory Count', 'Effectuer un comptage',
                'Cycle count and physical inventory.', 'Comptage cyclique et inventaire physique.',
                "<ol><li>Open <strong>Inventory → Stock Count</strong>.</li><li>Create or open an count session.</li><li>Scan or enter counted quantities.</li><li>Review variances.</li><li>Submit for approval.</li></ol>",
                "<ol><li>Ouvrez <strong>Inventaire → Comptage stock</strong>.</li><li>Créez ou ouvrez une session.</li><li>Scannez ou saisissez les quantités.</li><li>Examinez les écarts.</li><li>Soumettez pour approbation.</li></ol>",
                'inventory', null, 4],
            ['inventory', 'guide-search', 'Searching Products', 'Rechercher des produits',
                'Global search and scanner lookup.', 'Recherche globale et scanner.',
                "<p>Use the top search bar or <strong>Inventory → Barcode Scanner</strong>. Enter SKU, barcode, batch, or product name. Results show warehouse, quantity, and location.</p>",
                "<p>Utilisez la barre de recherche ou <strong>Inventaire → Scanner</strong>. Saisissez SKU, code-barres, lot ou nom. Les résultats affichent entrepôt, quantité et emplacement.</p>",
                'inventory', null, 5],
            ['barcode', 'guide-labels', 'Printing Barcode Labels', 'Imprimer des étiquettes',
                'Generate and print product labels.', 'Générer et imprimer des étiquettes.',
                "<p>From product detail or batch tracking, choose <strong>Print Label</strong>. Configure prefix and format in Settings → Barcode. Use standard label printer or PDF export.</p>",
                "<p>Depuis le détail produit ou lot, choisissez <strong>Imprimer étiquette</strong>. Configurez préfixe et format dans Paramètres → Code-barres.</p>",
                'barcode', null, 6],
            ['qr', 'guide-qr', 'Scanning QR Codes', 'Scanner les codes QR',
                'QR scanning for batches and locations.', 'Scan QR pour lots et emplacements.',
                "<p>Open the scanner page, select QR mode, and scan. QR codes may encode batch ID, location bin, or product URL.</p>",
                "<p>Ouvrez le scanner, mode QR, et scannez. Les QR peuvent encoder lot, emplacement ou URL produit.</p>",
                'qr', null, 7],
            ['reports', 'guide-reports', 'Viewing Reports', 'Consulter les rapports',
                'Access warehouse analytics and exports.', 'Accéder aux analyses et exports.',
                "<p>Navigate to <strong>Reports</strong> for inventory, movements, receiving, dispatch, transfers, performance, valuation, damage, and expiry reports. Filter by warehouse and period; export CSV, Excel, or PDF.</p>",
                "<p>Allez à <strong>Rapports</strong> pour inventaire, mouvements, réception, expédition, transferts, performance, valorisation, dommages et péremption. Filtrez et exportez.</p>",
                'reports', null, 8],
            ['notifications', 'guide-notifications', 'Managing Notifications', 'Gérer les notifications',
                'Alerts and notification preferences.', 'Alertes et préférences.',
                "<p>Visit <strong>Notifications</strong> for inbox. Configure channels in Profile or Settings — email, SMS, push, WhatsApp, and warehouse-specific alerts.</p>",
                "<p>Consultez <strong>Notifications</strong>. Configurez les canaux dans Profil ou Paramètres.</p>",
                'notifications', null, 9],
            ['offline', 'guide-offline', 'Working Offline', 'Travailler hors ligne',
                'PWA offline queue and sync.', 'File d\'attente PWA et synchronisation.',
                "<p>When offline, operations queue locally. A badge shows cached data. Reconnect to sync automatically. Check Settings → Offline for conflict strategy and sync frequency.</p>",
                "<p>Hors ligne, les opérations sont mises en file. Reconnectez-vous pour synchroniser. Voir Paramètres → Sync hors ligne.</p>",
                'offline', null, 10],
        ];

        foreach ($guides as $g) {
            [$catSlug, $slug, $ten, $tfr, $sen, $sfr, $ben, $bfr, $mod, $roles, $sort] = $g;
            $artIns->execute([
                $catIds[$catSlug], $slug, 'guide', $ten, $tfr, $sen, $sfr, $ben, $bfr, $mod, $roles, $sort,
            ]);
        }

        $manuals = [
            ['inventory', 'manual-inventory', 'Warehouse User Guide', 'Guide utilisateur entrepôt', 'inventory'],
            ['inventory', 'manual-inventory-detail', 'Inventory Guide', 'Guide inventaire', 'inventory'],
            ['receiving', 'manual-receiving', 'Receiving Guide', 'Guide réception', 'receiving'],
            ['dispatch', 'manual-dispatch', 'Dispatch Guide', 'Guide expédition', 'dispatch'],
            ['transfers', 'manual-transfers', 'Transfer Guide', 'Guide transferts', 'transfers'],
            ['barcode', 'manual-barcode', 'Barcode Guide', 'Guide code-barres', 'barcode'],
            ['offline', 'manual-offline', 'Offline Guide', 'Guide hors ligne', 'offline'],
        ];
        foreach ($manuals as [$catSlug, $slug, $ten, $tfr, $mod]) {
            $body = "<h2>{$ten}</h2><p>Download this guide for offline reference. Content mirrors in-app help articles for the {$mod} module.</p>";
            $bodyFr = "<h2>{$tfr}</h2><p>Téléchargez ce guide pour référence hors ligne. Contenu aligné sur l'aide in-app du module {$mod}.</p>";
            $artIns->execute([
                $catIds[$catSlug], $slug, 'manual', $ten, $tfr,
                "PDF-ready manual for {$mod}.", "Manuel PDF pour {$mod}.",
                $body, $bodyFr, $mod, null, 90,
            ]);
        }

        $faqIns = $db->prepare(
            'INSERT INTO help_faq (category_id, question_en, question_fr, answer_en, answer_fr, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $faqs = [
            [$catIds['receiving'], 'How do I receive products?', 'Comment réceptionner des produits?',
                'Go to Receiving → Receive Stock, select the delivery, scan or add items, complete inspection, and confirm the goods receipt note (GRN).',
                'Allez à Réception → Réception stock, sélectionnez la livraison, scannez ou ajoutez les articles, contrôlez et confirmez le GRN.', 1],
            [$catIds['transfers'], 'How do I transfer stock?', 'Comment transférer du stock?',
                'Use Transfers → Warehouse Transfer. Choose source/destination, add lines, submit, and track approval status.',
                'Utilisez Transferts → Transfert entrepôt. Choisissez source/destination, ajoutez les lignes et suivez l\'approbation.', 2],
            [$catIds['stock_count'], 'How do I perform stock counting?', 'Comment effectuer un comptage?',
                'Open Inventory → Stock Count, start a session, enter counted quantities, review variances, and submit.',
                'Ouvrez Inventaire → Comptage, démarrez une session, saisissez les quantités et soumettez.', 3],
            [$catIds['barcode'], 'How do I scan barcodes?', 'Comment scanner des code-barres?',
                'Open Inventory → Barcode Scanner. Allow camera access or use a USB scanner in the search field.',
                'Ouvrez Inventaire → Scanner. Autorisez la caméra ou utilisez un scanner USB.', 4],
            [$catIds['barcode'], 'How do I print labels?', 'Comment imprimer des étiquettes?',
                'From product or batch screens, click Print Label. Configure barcode type and prefix under Settings → Barcode.',
                'Depuis produit ou lot, cliquez Imprimer étiquette. Configurez le type dans Paramètres → Code-barres.', 5],
            [$catIds['offline'], 'How do I work offline?', 'Comment travailler hors ligne?',
                'The portal caches data locally. Continue receiving, counting, or viewing reports offline; changes sync when online.',
                'Le portail met en cache les données. Continuez hors ligne ; les changements se synchronisent en ligne.', 6],
            [$catIds['offline'], 'How do I recover synchronized data?', 'Comment récupérer les données synchronisées?',
                'Reconnect to the network and click Refresh. Pending items appear in Sync Monitor. Conflicts follow the strategy in Settings → Offline.',
                'Reconnectez le réseau et actualisez. Les éléments en attente apparaissent dans Sync Monitor.', 7],
            [$catIds['adjustment'], 'How do I report damaged products?', 'Comment signaler des produits endommagés?',
                'Use Inventory → Stock Adjustments, select damage reason, enter quantity, and submit for approval if required.',
                'Utilisez Inventaire → Ajustements, motif dommage, quantité, et soumettez si approbation requise.', 8],
            [$catIds['locations'], 'How do I locate products inside the warehouse?', 'Comment localiser des produits?',
                'Search by SKU/barcode or open Warehouse Locations to browse zones, aisles, racks, and bins.',
                'Recherchez par SKU/code-barres ou parcourez Emplacements entrepôt (zones, allées, racks, bacs).', 9],
        ];
        foreach ($faqs as $f) {
            $faqIns->execute($f);
        }

        $vidIns = $db->prepare(
            'INSERT INTO help_tutorial_videos (category_id, title_en, title_fr, description_en, description_fr, video_type, video_url, duration_seconds, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $videos = [
            [$catIds['receiving'], 'Receiving Workflow Overview', 'Aperçu réception',
                'Introduction to supplier deliveries and GRN.', 'Introduction aux livraisons et GRN.',
                'youtube', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 420, 1],
            [$catIds['dispatch'], 'Dispatch & Shipping', 'Expédition et livraison',
                'Pick, pack, ship process.', 'Processus prélèvement, emballage, expédition.',
                'youtube', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 360, 2],
            [$catIds['barcode'], 'Barcode Scanning Tips', 'Conseils scan code-barres',
                'Fast scanning techniques.', 'Techniques de scan rapide.',
                'youtube', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 180, 3],
        ];
        foreach ($videos as $v) {
            $vidIns->execute($v);
        }

        $updIns = $db->prepare(
            'INSERT INTO help_system_updates (version, title_en, title_fr, body_en, body_fr, update_type, published_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $updates = [
            ['2.4.0', 'Warehouse Help Center', 'Centre d\'aide entrepôt',
                'New searchable help center with guides, FAQ, tickets, and offline docs.',
                'Nouveau centre d\'aide avec recherche, guides, FAQ, tickets et docs hors ligne.', 'feature'],
            ['2.3.0', 'Enterprise Reports Suite', 'Suite de rapports entreprise',
                'Inventory, movement, receiving, dispatch, transfer, performance, valuation, damage, and expiry reports.',
                'Rapports inventaire, mouvements, réception, expédition, transferts, performance, valorisation, dommages et péremption.', 'feature'],
            ['2.2.1', 'Offline sync improvements', 'Améliorations sync hors ligne',
                'Faster conflict resolution and clearer pending queue status.',
                'Résolution de conflits plus rapide et file d\'attente plus claire.', 'improvement'],
        ];
        foreach ($updates as [$ver, $ten, $tfr, $ben, $bfr, $type]) {
            $updIns->execute([$ver, $ten, $tfr, $ben, $bfr, $type]);
        }
    }
}
