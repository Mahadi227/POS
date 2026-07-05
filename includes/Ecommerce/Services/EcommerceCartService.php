<?php
declare(strict_types=1);

/**
 * Session-based shopping cart for storefront.
 */
final class EcommerceCartService
{
    private const SESSION_KEY = 'ecommerce_cart';

    public function __construct(private PDO $db, private int $storeId)
    {
    }

    /** @return array<int, array{product_id:int, quantity:int, unit_price:float, name:string, image_url:?string}> */
    public function items(): array
    {
        $raw = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $productId => $qty) {
            $productId = (int) $productId;
            $qty = (int) $qty;
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            $stmt = $this->db->prepare(
                'SELECT id, name, price, image_url, stock_quantity FROM products WHERE id = ? AND store_id = ? AND deleted_at IS NULL LIMIT 1'
            );
            $stmt->execute([$productId, $this->storeId]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p) {
                continue;
            }
            $out[$productId] = [
                'product_id' => $productId,
                'quantity' => min($qty, max(0, (int) $p['stock_quantity'])),
                'unit_price' => (float) $p['price'],
                'name' => (string) $p['name'],
                'image_url' => $p['image_url'] ?? null,
                'stock_quantity' => max(0, (int) $p['stock_quantity']),
                'line_total' => round(min($qty, max(0, (int) $p['stock_quantity'])) * (float) $p['price'], 2),
            ];
        }
        return $out;
    }

    public function count(): int
    {
        $n = 0;
        foreach ($this->items() as $item) {
            $n += $item['quantity'];
        }
        return $n;
    }

    public function subtotal(): float
    {
        $sum = 0.0;
        foreach ($this->items() as $item) {
            $sum += $item['quantity'] * $item['unit_price'];
        }
        return round($sum, 2);
    }

    public function add(int $productId, int $quantity = 1): void
    {
        if ($productId <= 0 || $quantity <= 0) {
            return;
        }
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        $current = (int) ($_SESSION[self::SESSION_KEY][$productId] ?? 0);
        $_SESSION[self::SESSION_KEY][$productId] = $current + $quantity;
    }

    public function update(int $productId, int $quantity): void
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        if ($quantity <= 0) {
            unset($_SESSION[self::SESSION_KEY][$productId]);
            return;
        }
        $_SESSION[self::SESSION_KEY][$productId] = $quantity;
    }

    public function remove(int $productId): void
    {
        if (isset($_SESSION[self::SESSION_KEY][$productId])) {
            unset($_SESSION[self::SESSION_KEY][$productId]);
        }
    }

    public function clear(): void
    {
        $_SESSION[self::SESSION_KEY] = [];
    }
}
