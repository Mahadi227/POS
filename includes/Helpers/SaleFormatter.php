<?php
/**
 * Normalise les enregistrements vente pour le frontend (noms de champs cohérents).
 */
class SaleFormatter
{
    public static function formatListRow(array $row): array
    {
        $total = (float) ($row['total'] ?? $row['total_amount'] ?? 0);
        $createdAt = $row['created_at'] ?? $row['sale_date'] ?? null;

        $receipt = $row['receipt_no'] ?? $row['receipt_number'] ?? '';

        return [
            'id'              => (int) ($row['id'] ?? 0),
            'receipt_number'  => $receipt,
            'receipt_no'      => $receipt,
            'sale_date'       => $createdAt,
            'created_at'      => $createdAt,
            'total_amount'    => $total,
            'total'           => $total,
            'tax_amount'      => (float) ($row['tax'] ?? $row['tax_amount'] ?? 0),
            'tax'             => (float) ($row['tax'] ?? $row['tax_amount'] ?? 0),
            'discount_amount' => (float) ($row['discount'] ?? $row['discount_amount'] ?? 0),
            'discount'        => (float) ($row['discount'] ?? $row['discount_amount'] ?? 0),
            'payment_method'   => $row['payment_method'] ?? null,
            'payment_provider' => $row['payment_provider'] ?? null,
            'payment_ref'      => $row['payment_ref'] ?? null,
            'store_name'       => $row['store_name'] ?? null,
            'cashier_name'     => $row['cashier_name'] ?? null,
            'customer_name'    => $row['customer_name'] ?? null,
            'store_id'        => isset($row['store_id']) ? (int) $row['store_id'] : null,
            'user_id'         => isset($row['user_id']) ? (int) $row['user_id'] : null,
            'status'          => $row['status'] ?? 'completed',
        ];
    }

    public static function formatDetail(array $info, array $items): array
    {
        $formatted = self::formatListRow($info);
        $subtotal = 0.0;

        $formatted['items'] = array_map(static function (array $item) use (&$subtotal) {
            $qty = (int) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
            $lineTotal = (float) ($item['subtotal'] ?? ($qty * $unitPrice));
            $subtotal += $lineTotal;

            return [
                'product_id'   => (int) ($item['product_id'] ?? 0),
                'product_name' => $item['product_name'] ?? '',
                'sku'          => $item['sku'] ?? '',
                'quantity'     => $qty,
                'unit_price'   => $unitPrice,
                'subtotal'     => $lineTotal,
            ];
        }, $items);

        $formatted['subtotal'] = $subtotal;
        $formatted['cashier_name'] = $info['cashier_name'] ?? $formatted['cashier_name'];

        return $formatted;
    }
}
