<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/WarehouseHelpRepository.php';
require_once __DIR__ . '/../WarehouseHelpSchema.php';
require_once __DIR__ . '/../../Wms/WmsSchema.php';
require_once __DIR__ . '/../../Helpers/WarehousePortalAuth.php';
require_once __DIR__ . '/../../Database/Database.php';

class WarehouseHelpService
{
    private WarehouseHelpRepository $repo;

    /** @var list<string> */
    private array $tipsEn = [
        'Use barcode scanning for faster receiving.',
        'Perform inventory counts regularly.',
        'Always verify transfers before approval.',
        'Check expiry dates during receiving.',
        'Synchronize after working offline.',
        'Review low-stock alerts on the dashboard daily.',
        'Use batch tracking for FEFO expiry management.',
    ];

    /** @var list<string> */
    private array $tipsFr = [
        'Utilisez le scan code-barres pour accélérer la réception.',
        'Effectuez des comptages inventaire régulièrement.',
        'Vérifiez toujours les transferts avant approbation.',
        'Contrôlez les dates de péremption à la réception.',
        'Synchronisez après travail hors ligne.',
        'Consultez les alertes stock bas sur le tableau de bord.',
        'Utilisez le suivi lots pour la gestion FEFO.',
    ];

    public function __construct(?PDO $db = null)
    {
        $this->repo = new WarehouseHelpRepository($db);
    }

    public function moduleReady(): bool
    {
        return WarehouseHelpSchema::ready(Database::getInstance()->getConnection());
    }

    public function lang(): string
    {
        $lang = $_SESSION['lang'] ?? 'en';
        return in_array($lang, ['en', 'fr'], true) ? $lang : 'en';
    }

    public function hub(int $userId, ?int $warehouseId): array
    {
        $lang = $this->lang();
        $role = WarehousePortalAuth::roleSlug();
        $categories = array_map(function (array $c) use ($lang) {
            return [
                'slug' => $c['slug'],
                'icon' => $c['icon'],
                'name' => $lang === 'fr' ? $c['name_fr'] : $c['name_en'],
            ];
        }, $this->repo->listCategories($role));

        return [
            'categories' => $categories,
            'guides' => $this->repo->listArticles(null, 'guide', $lang, $role),
            'manuals' => $this->repo->listArticles(null, 'manual', $lang, $role),
            'faq' => $this->repo->listFaq(null, $lang, $role),
            'videos' => $this->repo->listVideos(null, $lang, $role),
            'updates' => $this->repo->listUpdates($lang),
            'tips' => $lang === 'fr' ? $this->tipsFr : $this->tipsEn,
            'system_status' => $this->systemStatus($warehouseId),
            'tickets' => $this->repo->listUserTickets($userId),
            'user' => [
                'name' => $_SESSION['name'] ?? '',
                'email' => $_SESSION['email'] ?? '',
                'role' => $role,
                'warehouse_id' => $warehouseId,
            ],
        ];
    }

    public function search(string $query): array
    {
        return [
            'results' => $this->repo->search($query, $this->lang(), WarehousePortalAuth::roleSlug()),
        ];
    }

    public function article(string $slug): ?array
    {
        return $this->repo->getArticle($slug, $this->lang());
    }

    public function createTicket(int $userId, array $input, ?array $file = null): array
    {
        $name = trim((string) ($input['name'] ?? $_SESSION['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? $_SESSION['email'] ?? ''));
        $subject = trim((string) ($input['subject'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $category = trim((string) ($input['category'] ?? 'general'));
        $priority = in_array($input['priority'] ?? 'normal', ['low', 'normal', 'high', 'critical'], true)
            ? $input['priority'] : 'normal';
        $ticketType = ($input['ticket_type'] ?? 'support') === 'problem' ? 'problem' : 'support';
        $problemType = trim((string) ($input['problem_type'] ?? ''));

        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Valid name and email are required');
        }
        if ($subject === '' || strlen($subject) < 3) {
            throw new InvalidArgumentException('Subject is required');
        }
        if ($description === '' || strlen($description) < 10) {
            throw new InvalidArgumentException('Description must be at least 10 characters');
        }

        $attachmentPath = null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $attachmentPath = $this->storeAttachment($userId, $file);
        }

        $ticketNumber = $this->repo->nextTicketNumber();
        $id = $this->repo->createTicket([
            'ticket_number' => $ticketNumber,
            'user_id' => $userId,
            'warehouse_id' => !empty($input['warehouse_id']) ? (int) $input['warehouse_id'] : ((int) ($_SESSION['warehouse_id'] ?? 0) ?: null),
            'name' => $name,
            'email' => $email,
            'role_slug' => WarehousePortalAuth::roleSlug(),
            'subject' => $subject,
            'category' => $category,
            'priority' => $priority,
            'description' => $description,
            'attachment_path' => $attachmentPath,
            'ticket_type' => $ticketType,
            'problem_type' => $problemType ?: null,
        ]);

        return ['id' => $id, 'ticket_number' => $ticketNumber, 'status' => 'open'];
    }

    public function manualHtml(string $slug): ?array
    {
        $article = $this->repo->getArticle($slug, $this->lang());
        if (!$article || ($article['article_type'] ?? '') !== 'manual') {
            return null;
        }
        return [
            'title' => $article['title'],
            'html' => $article['body'],
            'slug' => $slug,
        ];
    }

    private function systemStatus(?int $warehouseId): array
    {
        $db = Database::getInstance()->getConnection();
        $wmsReady = WmsSchema::ready();
        $helpReady = $this->moduleReady();

        $syncPending = 0;
        $lastSync = null;
        if ($this->tableExists($db, 'synchronization_queue')) {
            try {
                $syncPending = (int) $db->query("SELECT COUNT(*) FROM synchronization_queue WHERE status = 'pending'")->fetchColumn();
                $lastSync = $db->query('SELECT MAX(updated_at) FROM synchronization_queue WHERE status = \'synced\'')->fetchColumn() ?: null;
            } catch (Throwable) {
            }
        }

        $notifReady = $this->tableExists($db, 'notifications');

        return [
            'database' => $wmsReady && $helpReady ? 'online' : 'degraded',
            'wms_module' => $wmsReady,
            'help_module' => $helpReady,
            'api' => 'online',
            'notifications' => $notifReady ? 'online' : 'unavailable',
            'offline_mode' => true,
            'sync_pending' => $syncPending,
            'last_sync' => $lastSync,
            'storage_note' => 'Local cache via IndexedDB / localStorage',
        ];
    }

    private function storeAttachment(int $userId, array $file): string
    {
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Attachment must be under 5 MB');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'text/plain'];
        if (!in_array($mime, $allowed, true)) {
            throw new InvalidArgumentException('Invalid attachment type');
        }
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'txt',
        };
        $dir = dirname(__DIR__, 3) . '/public/uploads/support';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create upload directory');
        }
        $filename = 'ticket_' . $userId . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            throw new RuntimeException('Failed to save attachment');
        }
        return 'uploads/support/' . $filename;
    }

    private function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}
