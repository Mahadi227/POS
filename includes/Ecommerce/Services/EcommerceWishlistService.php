<?php
declare(strict_types=1);

/**
 * Wishlist — session for guests, DB for logged-in accounts.
 */
final class EcommerceWishlistService
{
    private const SESSION_KEY = 'ecommerce_wishlist';

    public function __construct(private PDO $db, private int $tenantId, private int $storeId)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function items(?int $accountId = null): array
    {
        $ids = $this->productIds($accountId);
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = $ids;
        $params[] = $this->storeId;
        $stmt = $this->db->prepare(
            "SELECT * FROM products WHERE id IN ($placeholders) AND store_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function toggle(int $productId, ?int $accountId = null): bool
    {
        $ids = $this->productIds($accountId);
        $has = in_array($productId, $ids, true);
        if ($has) {
            $this->remove($productId, $accountId);
            return false;
        }
        $this->add($productId, $accountId);
        return true;
    }

    public function add(int $productId, ?int $accountId = null): void
    {
        if ($accountId) {
            $this->db->prepare(
                'INSERT IGNORE INTO ecommerce_wishlist_items (tenant_id, account_id, product_id) VALUES (?, ?, ?)'
            )->execute([$this->tenantId, $accountId, $productId]);
            return;
        }
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        $_SESSION[self::SESSION_KEY][$productId] = true;
    }

    public function remove(int $productId, ?int $accountId = null): void
    {
        if ($accountId) {
            $this->db->prepare(
                'DELETE FROM ecommerce_wishlist_items WHERE tenant_id = ? AND account_id = ? AND product_id = ?'
            )->execute([$this->tenantId, $accountId, $productId]);
            return;
        }
        unset($_SESSION[self::SESSION_KEY][$productId]);
    }

    public function count(?int $accountId = null): int
    {
        return count($this->productIds($accountId));
    }

    /** @return array<int, int> */
    private function productIds(?int $accountId): array
    {
        if ($accountId) {
            $stmt = $this->db->prepare(
                'SELECT product_id FROM ecommerce_wishlist_items WHERE tenant_id = ? AND account_id = ? ORDER BY id DESC'
            );
            $stmt->execute([$this->tenantId, $accountId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }
        $raw = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        return array_map('intval', array_keys($raw));
    }
}
