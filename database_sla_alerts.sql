-- SLA Alert Tracking Columns
-- Add these columns to track when SLA alerts have been sent

-- For document_requests table
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS sla_alert_sent TINYINT(1) DEFAULT 0;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS sla_alert_sent_at DATETIME;
ALTER TABLE document_requests ADD INDEX IF NOT EXISTS idx_sla_alert (sla_alert_sent);

-- For bookings table
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS sla_alert_sent TINYINT(1) DEFAULT 0;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS sla_alert_sent_at DATETIME;
ALTER TABLE bookings ADD INDEX IF NOT EXISTS idx_sla_alert (sla_alert_sent);

-- For payments table
ALTER TABLE payments ADD COLUMN IF NOT EXISTS sla_alert_sent TINYINT(1) DEFAULT 0;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS sla_alert_sent_at DATETIME;
ALTER TABLE payments ADD INDEX IF NOT EXISTS idx_sla_alert (sla_alert_sent);
