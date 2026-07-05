<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/LicenseRepository.php';
require_once __DIR__ . '/../SaaSPhase8Migrator.php';

final class PlatformLicenseService
{
    /** @var string[] */
    public const TYPES = ['cloud', 'on_prem', 'partner', 'trial'];

    private PDO $db;
    private LicenseRepository $licenses;

    public function __construct(PDO $db, LicenseRepository $licenses)
    {
        $this->db = $db;
        $this->licenses = $licenses;
    }

    /** @return array{raw_key: string, id: int, prefix: string} */
    public function issue(
        ?int $tenantId,
        string $licenseType,
        ?string $planCode,
        ?int $maxSeats,
        ?string $notes,
        ?int $issuedBy,
        ?string $expiresAt
    ): array {
        SaaSPhase8Migrator::ensure($this->db);

        $licenseType = strtolower(trim($licenseType));
        if (!in_array($licenseType, self::TYPES, true)) {
            throw new InvalidArgumentException('Invalid license type');
        }

        if ($tenantId !== null && $tenantId <= 0) {
            $tenantId = null;
        }

        $raw = 'rpl_' . bin2hex(random_bytes(16));
        $hash = hash('sha256', $raw);
        $prefix = substr($raw, 0, 12);

        $id = $this->licenses->create(
            $tenantId,
            $prefix,
            $hash,
            $licenseType,
            $planCode,
            $maxSeats,
            $notes,
            $issuedBy,
            $expiresAt
        );

        return [
            'id' => $id,
            'raw_key' => $raw,
            'prefix' => $prefix,
        ];
    }

    public function revoke(int $licenseId): bool
    {
        SaaSPhase8Migrator::ensure($this->db);
        return $this->licenses->revoke($licenseId);
    }
}
