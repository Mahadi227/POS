-- E-commerce Paystack settings (tenant-scoped keys)
ALTER TABLE ecommerce_settings
    ADD COLUMN paystack_public_key VARCHAR(120) NULL AFTER tax_rate,
    ADD COLUMN paystack_secret_key VARCHAR(120) NULL AFTER paystack_public_key,
    ADD COLUMN paystack_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER paystack_secret_key,
    ADD COLUMN paystack_currency VARCHAR(8) NULL AFTER paystack_enabled;
