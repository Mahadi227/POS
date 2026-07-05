<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Config/config.php';

/** Executes database backup jobs for the platform console. */
final class PlatformBackupService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array{ok: bool, path: ?string, size: int, error: ?string} */
    public function run(int $backupId, string $scope, ?int $tenantId, string $destPath): array
    {
        $this->markRunning($backupId);

        $dir = dirname($destPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return $this->fail($backupId, 'Could not create backup directory');
        }

        if ($scope === 'tenant' && $tenantId > 0) {
            $result = $this->exportTenantSnapshot($tenantId, $destPath);
        } elseif ($scope === 'schema') {
            $result = $this->runMysqldump($destPath, true);
            if (!$result['ok']) {
                $result = $this->exportSchemaPhp($destPath);
            }
        } else {
            $result = $this->runMysqldump($destPath, false);
            if (!$result['ok']) {
                $result = $this->exportFullPhp($destPath);
            }
        }

        if (!$result['ok']) {
            return $this->fail($backupId, $result['error'] ?? 'Backup failed');
        }

        $size = is_file($destPath) ? (int) filesize($destPath) : 0;
        $this->markCompleted($backupId, $destPath, $size);

        return ['ok' => true, 'path' => $destPath, 'size' => $size, 'error' => null];
    }

    /** @return array{ok: bool, error: ?string} */
    private function runMysqldump(string $destPath, bool $schemaOnly): array
    {
        $bin = $this->mysqldumpBinary();
        if ($bin === null) {
            return ['ok' => false, 'error' => 'mysqldump not available'];
        }

        $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $user = defined('DB_USER') ? DB_USER : 'root';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $name = defined('DB_NAME') ? DB_NAME : '';

        $args = [
            escapeshellarg($bin),
            '--host=' . escapeshellarg($host),
            '--user=' . escapeshellarg($user),
            $pass !== '' ? '--password=' . escapeshellarg($pass) : '',
            '--default-character-set=utf8mb4',
            '--single-transaction',
            '--routines',
            '--triggers',
        ];
        if ($schemaOnly) {
            $args[] = '--no-data';
        }
        $args[] = escapeshellarg($name);

        $cmd = implode(' ', array_filter($args)) . ' > ' . escapeshellarg($destPath) . ' 2>&1';
        $code = 1;
        if (function_exists('exec')) {
            exec($cmd, $output, $code);
        }

        if ($code !== 0 || !is_file($destPath) || filesize($destPath) === 0) {
            @unlink($destPath);
            $msg = isset($output[0]) ? implode("\n", $output) : 'mysqldump failed';
            return ['ok' => false, 'error' => $msg];
        }

        return ['ok' => true, 'error' => null];
    }

    /** @return array{ok: bool, error: ?string} */
    private function exportFullPhp(string $destPath): array
    {
        try {
            $fp = fopen($destPath, 'wb');
            if (!$fp) {
                return ['ok' => false, 'error' => 'Cannot write backup file'];
            }

            fwrite($fp, "-- RetailPOS platform backup\n-- Generated: " . date('c') . "\n\n");
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            $tables = $this->db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];
            foreach ($tables as $table) {
                $table = (string) $table;
                $create = $this->db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                if ($create) {
                    fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
                    fwrite($fp, ($create['Create Table'] ?? '') . ";\n\n");
                }

                $rows = $this->db->query("SELECT * FROM `{$table}`");
                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    $cols = array_map(static fn ($c) => "`{$c}`", array_keys($row));
                    $vals = array_map([$this, 'sqlValue'], array_values($row));
                    fwrite($fp, 'INSERT INTO `' . $table . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ");\n");
                }
                fwrite($fp, "\n");
            }

            fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fp);

            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            @unlink($destPath);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, error: ?string} */
    private function exportSchemaPhp(string $destPath): array
    {
        try {
            $fp = fopen($destPath, 'wb');
            if (!$fp) {
                return ['ok' => false, 'error' => 'Cannot write backup file'];
            }

            fwrite($fp, "-- RetailPOS schema backup\n-- Generated: " . date('c') . "\n\n");
            $tables = $this->db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];
            foreach ($tables as $table) {
                $table = (string) $table;
                $create = $this->db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                if ($create) {
                    fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
                    fwrite($fp, ($create['Create Table'] ?? '') . ";\n\n");
                }
            }
            fclose($fp);

            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            @unlink($destPath);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, error: ?string} */
    private function exportTenantSnapshot(int $tenantId, string $destPath): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT id, uuid, slug, name, status, created_at FROM tenants WHERE id = ? AND deleted_at IS NULL LIMIT 1'
            );
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tenant) {
                return ['ok' => false, 'error' => 'Tenant not found'];
            }

            $payload = [
                'exported_at' => date('c'),
                'tenant' => $tenant,
                'counts' => [],
            ];

            foreach (['stores', 'users', 'subscriptions'] as $tbl) {
                if (!$this->tableExists($tbl)) {
                    continue;
                }
                $col = $this->hasColumn($tbl, 'tenant_id') ? 'tenant_id' : null;
                if ($col) {
                    $c = $this->db->prepare("SELECT COUNT(*) FROM `{$tbl}` WHERE tenant_id = ?");
                    $c->execute([$tenantId]);
                    $payload['counts'][$tbl] = (int) $c->fetchColumn();
                }
            }

            file_put_contents($destPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            @unlink($destPath);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function sqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return $this->db->quote((string) $value);
    }

    private function mysqldumpBinary(): ?string
    {
        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'mysqldump',
        ];

        foreach ($candidates as $bin) {
            if ($bin === 'mysqldump') {
                if (function_exists('exec')) {
                    exec('where mysqldump 2>nul', $out, $code);
                    if ($code === 0 && !empty($out[0]) && is_file($out[0])) {
                        return $out[0];
                    }
                }
                continue;
            }
            if (is_file($bin)) {
                return $bin;
            }
        }

        return null;
    }

    private function markRunning(int $backupId): void
    {
        $this->db->prepare(
            'UPDATE platform_backups SET status = ?, started_at = NOW() WHERE id = ?'
        )->execute(['running', $backupId]);
    }

    /** @return array{ok: bool, path: ?string, size: int, error: ?string} */
    private function fail(int $backupId, string $error): array
    {
        $this->db->prepare(
            'UPDATE platform_backups SET status = ?, error_message = ?, completed_at = NOW() WHERE id = ?'
        )->execute(['failed', $error, $backupId]);

        return ['ok' => false, 'path' => null, 'size' => 0, 'error' => $error];
    }

    private function markCompleted(int $backupId, string $path, int $size): void
    {
        $this->db->prepare(
            'UPDATE platform_backups SET status = ?, file_path = ?, size_bytes = ?, completed_at = NOW(), error_message = NULL WHERE id = ?'
        )->execute(['completed', $path, $size, $backupId]);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }

    public static function storageDir(): string
    {
        $root = dirname(__DIR__, 3);
        return $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'platform-backups';
    }
}
