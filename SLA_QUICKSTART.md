# SLA Dashboard & Alerts - Quick Start

## ⚡ 5-Minute Setup

### 1. Run Database Migration
```bash
# Open phpMyAdmin or MySQL command line
# Run this SQL:
```

```sql
-- Add SLA alert tracking columns
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS sla_alert_sent TINYINT(1) DEFAULT 0;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS sla_alert_sent_at DATETIME;
ALTER TABLE document_requests ADD INDEX IF NOT EXISTS idx_sla_alert (sla_alert_sent);

ALTER TABLE bookings ADD COLUMN IF NOT EXISTS sla_alert_sent TINYINT(1) DEFAULT 0;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS sla_alert_sent_at DATETIME;
ALTER TABLE bookings ADD INDEX IF NOT EXISTS idx_sla_alert (sla_alert_sent);

ALTER TABLE payments ADD COLUMN IF NOT EXISTS sla_alert_sent TINYINT(1) DEFAULT 0;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS sla_alert_sent_at DATETIME;
ALTER TABLE payments ADD INDEX IF NOT EXISTS idx_sla_alert (sla_alert_sent);
```

### 2. Access the Dashboard

**For Admin/Staff:**
```
http://localhost/documentSystem/staff/sla-dashboard.php
```

**From Admin Dashboard:**
- Click **"SLA Dashboard"** in the left menu

### 3. Configure Settings (Optional)

```
Admin Dashboard → System Settings → SLA Configuration
```

Or directly:
```
http://localhost/documentSystem/admin/settings/sla-config.php
```

### 4. Set Up Automated Alerts (Windows)

**Quick Setup:**
1. Open **Task Scheduler**
2. Import task: `scripts/sla-task-scheduler.xml` (if provided)
   
**Or Manual Setup:**
1. Open **Task Scheduler** → Create Task
2. Name: `Parish SLA Checker`
3. Trigger: Repeat every **15 minutes**
4. Action: `C:\xampp\htdocs\documentSystem\scripts\run-sla-check.bat`

**Test it works:**
```cmd
C:\xampp\htdocs\documentSystem\scripts\run-sla-check.bat
```

---

## 🎯 What You Get

### Dashboard Features
- ✅ Real-time SLA monitoring
- ✅ Color-coded alerts (🟢 OK, 🟡 Warning, 🔴 Critical)
- ✅ Auto-refresh every 2 minutes
- ✅ Browser notifications for new critical items
- ✅ Today's activity stats

### Email Alerts
- ✅ Automatic alerts when items exceed thresholds
- ✅ Beautiful HTML email templates
- ✅ Sent to all admin/staff
- ✅ One alert per item (no spam)

### Settings
- ✅ Customize SLA thresholds
- ✅ Enable/disable alerts
- ✅ Adjust check frequency

---

## 📊 Default SLA Targets

| Type | SLA Target | Warning Threshold |
|------|------------|-------------------|
| Documents | 48 hours | 36 hours |
| Bookings | 24 hours | 18 hours |
| Payments | 24 hours | 18 hours |

---

## 🔔 How Alerts Work

```
Item submitted
     ↓
Time passes...
     ↓
Reaches 75% of SLA (Warning) → 🟡 Email sent to staff
     ↓
Time passes...
     ↓
Exceeds 100% of SLA (Critical) → 🔴 Dashboard shows red, sound alert
     ↓
Staff processes item → ✅ Removed from dashboard
```

---

## 🧪 Test the System

### Create Test Data
1. Go to client dashboard
2. Submit a document request
3. In database, manually set `created_at` to 50 hours ago:
```sql
UPDATE document_requests 
SET created_at = DATE_SUB(NOW(), INTERVAL 50 HOUR) 
WHERE id = YOUR_TEST_ID;
```
4. Open SLA dashboard - should show as critical (red)

### Test Email Alerts
```cmd
# Run alert checker manually
cd C:\xampp\htdocs\documentSystem
C:\xampp\php\php.exe handlers\sla_alert_handler.php
```

Check your inbox - you should receive an email alert!

---

## 🚨 Troubleshooting

### Dashboard shows "Access Denied"
- Make sure you're logged in as admin or staff
- Check session is active

### No items showing
- Good news - no overdue items!
- Or check if database has pending items:
```sql
SELECT COUNT(*) FROM document_requests WHERE status = 'pending';
```

### Auto-refresh not working
- Check browser console (F12) for errors
- Ensure `/api/sla-status.php` exists and is accessible

### Email alerts not received
- Check `config/email_config.php` is configured
- Verify SMTP credentials
- Check spam folder

---

## 📖 Full Documentation

For complete details, see: [SLA_SETUP_GUIDE.md](SLA_SETUP_GUIDE.md)

---

## ✨ Pro Tips

1. **Keep dashboard open** in a background tab - you'll hear sound alerts
2. **Check first thing in the morning** - see what accumulated overnight
3. **Process critical items first** - red before yellow before green
4. **Adjust thresholds** if getting too many false alarms
5. **Monitor weekly** - track trends and adjust staffing

---

## 🎉 You're All Set!

The SLA system is now:
- ✅ Monitoring all pending items 24/7
- ✅ Alerting you before deadlines
- ✅ Helping you maintain service quality
- ✅ Ensuring client satisfaction

**Questions?** Check [SLA_SETUP_GUIDE.md](SLA_SETUP_GUIDE.md) for detailed documentation.
