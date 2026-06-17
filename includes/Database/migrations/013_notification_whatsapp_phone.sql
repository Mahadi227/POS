-- WhatsApp delivery phone on notification preferences (per user)
ALTER TABLE notification_preferences
    ADD COLUMN whatsapp_phone VARCHAR(20) NULL AFTER whatsapp_enabled;
