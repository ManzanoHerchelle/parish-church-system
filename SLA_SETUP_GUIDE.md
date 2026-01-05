# SLA Dashboard & Alerts - Setup Guide

## Overview
The SLA (Service Level Agreement) Dashboard monitors request processing times and sends alerts when items approach or exceed their deadlines. This ensures your parish never misses a deadline and maintains high service quality.

---

## Features Implemented

### 1. **Real-Time SLA Dashboard** 
- Live monitoring of all pending items (documents, bookings, payments)
- Color-coded status indicators:
  - 🟢 **OK** - Within safe limits
  - 🟡 **Warning** - Approaching deadline (75% of SLA)
  - 🔴 **Critical** - Exceeded SLA deadline
- Auto-refresh every 2 minutes
- Background checks every 30 seconds
- Today's processing activity stats

### 2. **Automated Email Alerts**
- Sends email notifications to all admin/staff when:
  - Items reach warning threshold (75% of SLA)
  - Items exceed critical threshold (100% of SLA)
- Beautiful HTML email templates
- Only sends once per item to avoid spam

### 3. **Real-Time Toast Notifications**
- Browser notifications when new critical items detected
- Sound alerts for critical breaches
- Persistent notification history

### 4. **Configurable SLA Thresholds**
- Admin can customize SLA targets via Settings page
- Default values:
  - Documents: 48 hours (warning at 36h)
  - Bookings: 24 hours (warning at 18h)
  - Payments: 24 hours (warning at 18h)

### 5. **Automated Monitoring**
- Background task checks for SLA breaches
- Can run via Windows Task Scheduler or Linux cron
- Configurable check frequency (5-60 minutes)

---

## Installation Steps

### Step 1: Database Setup

Run the SLA alerts database migration:

```bash
# In MySQL/phpMyAdmin, run:
mysql -u root -p parish_church_system < database_sla_alerts.sql
```

Or manually execute:
```sql
-- Add SLA alert tracking columns
ALTER TABLE document_requests ADD COLUMN sla_alert_sent TINYINT(1) DEFAULT 0;
ALTER TABLE document_requests ADD COLUMN sla_alert_sent_at DATETIME;
ALTER TABLE document_requests ADD INDEX idx_sla_alert (sla_alert_sent);

ALTER TABLE bookings ADD COLUMN sla_alert_sent TINYINT(1) DEFAULT 0;
ALTER TABLE bookings ADD COLUMN sla_alert_sent_at DATETIME;
ALTER TABLE bookings ADD INDEX idx_sla_alert (sla_alert_sent);

ALTER TABLE payments ADD COLUMN sla_alert_sent TINYINT(1) DEFAULT 0;
ALTER TABLE payments ADD COLUMN sla_alert_sent_at DATETIME;
ALTER TABLE payments ADD INDEX idx_sla_alert (sla_alert_sent);
```

### Step 2: Configure Email (if not already done)

Ensure `config/email_config.php` is properly configured with Gmail SMTP settings.

### Step 3: Test the Dashboard

1. Navigate to: `http://localhost/documentSystem/staff/sla-dashboard.php`
2. You should see:
   - Statistics cards showing pending counts
   - Lists of overdue items (if any)
   - Today's activity
   - SLA standards reference

### Step 4: Set Up Automated Alerts (Windows)

**Using Windows Task Scheduler:**

1. Open **Task Scheduler** (search in Start menu)
2. Click **Create Task**
3. **General Tab:**
   - Name: `Parish SLA Alert Checker`
   - Description: `Checks for SLA breaches and sends email alerts`
   - Run whether user is logged on or not
4. **Triggers Tab:**
   - New trigger: **On a schedule**
   - Repeat task every: **15 minutes**
   - Duration: **Indefinitely**
5. **Actions Tab:**
   - Action: **Start a program**
   - Program: `C:\xampp\htdocs\documentSystem\scripts\run-sla-check.bat`
6. Click **OK** to save

**Verify it's working:**
```cmd
# Run manually to test
C:\xampp\htdocs\documentSystem\scripts\run-sla-check.bat

# Check log file
type C:\xampp\htdocs\documentSystem\logs\sla-checks.log
```

### Step 5: Set Up Automated Alerts (Linux/Mac)

**Using Cron:**

1. Make script executable:
```bash
chmod +x /var/www/html/documentSystem/scripts/run-sla-check.sh
```

2. Edit crontab:
```bash
crontab -e
```

3. Add this line (runs every 15 minutes):
```bash
*/15 * * * * /var/www/html/documentSystem/scripts/run-sla-check.sh
```

4. Verify:
```bash
# Check log
tail -f /var/www/html/documentSystem/logs/sla-checks.log
```

### Step 6: Configure SLA Settings (Optional)

1. Log in as **Admin**
2. Go to **Admin Dashboard** → **Settings** → **SLA Configuration**
3. Customize:
   - SLA target hours for each type
   - Warning thresholds
   - Enable/disable email alerts
   - Alert check frequency
4. Click **Save SLA Settings**

---

## How to Use

### For Staff

**Dashboard Access:**
```
http://localhost/documentSystem/staff/sla-dashboard.php
```

**What You'll See:**
- 📊 **Statistics**: Counts of pending items by type
- 🔴 **Critical Items**: Items that exceeded SLA (red)
- 🟡 **Warning Items**: Items approaching SLA (yellow)
- ✅ **Today's Activity**: What was processed today

**Dashboard Features:**
- ⏱️ **Auto-refresh**: Page reloads every 2 minutes
- 🔔 **Toast Notifications**: Pop-up alerts for new critical items
- 🔄 **Manual Refresh**: Click "Refresh Now" button anytime
- ⏸️ **Pause on Inactive**: Auto-refresh pauses if you're away

**What to Do:**
1. Check dashboard at start of shift
2. Address critical items first (red)
3. Then handle warning items (yellow)
4. Monitor throughout the day

### For Admin

**Additional Features:**
- View **all staff** activity (not just your own)
- Configure SLA thresholds via Settings
- Receive email alerts for all breaches
- Access via: `Admin Dashboard` → `SLA Dashboard` link

---

## Email Alert Examples

### Warning Alert (Approaching SLA)
```
Subject: [WARNING] SLA Alert: document

⚠️ SLA warning Alert

Hello Admin,

A document request has approached the SLA threshold and requires immediate attention.

Document Type: Baptismal Certificate
Reference: DOC-2026-001
Client: John Santos
Time Pending: 38 hours

Action Required: Please review and process this request as soon as possible.

[View SLA Dashboard]
```

### Critical Alert (Exceeded SLA)
```
Subject: [CRITICAL] SLA Alert: booking

🔴 SLA critical Alert

Hello Staff,

A booking request has exceeded the SLA threshold and requires immediate attention.

Booking Type: Wedding Ceremony
Reference: BOOK-2026-015
Client: Maria Garcia
Time Pending: 26 hours

Action Required: Please review and process this request as soon as possible.

[View SLA Dashboard]
```

---

## API Endpoints

### Get SLA Status (for dashboard refresh)
```
GET /documentSystem/api/sla-status.php
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2026-01-06 14:30:00",
  "summary": {
    "critical": 3,
    "warning": 5,
    "total": 8
  },
  "documents": {
    "critical": 1,
    "warning": 2
  },
  "bookings": {
    "critical": 1,
    "warning": 2
  },
  "payments": {
    "critical": 1,
    "warning": 1
  },
  "criticalItems": [
    {
      "type": "document",
      "reference_number": "DOC-2026-001",
      "item_name": "Baptismal Certificate",
      "client_name": "John Santos",
      "hours_pending": 50
    }
  ]
}
```

---

## Troubleshooting

### Dashboard Not Loading
- Check if user is logged in as admin/staff
- Verify database connection in `config/database.php`
- Check browser console for JavaScript errors

### No Email Alerts Received
- Verify email configuration: `config/email_config.php`
- Check if alert script is running: `logs/sla-checks.log`
- Ensure SLA alerts are enabled in Settings
- Verify items actually exceed thresholds

### Alerts Sent Multiple Times
- Check `sla_alert_sent` column in database
- Should be `1` after first alert sent
- If `0`, alert will be sent again

### Task Scheduler Not Running
**Windows:**
```cmd
# Check if task exists
schtasks /query /tn "Parish SLA Alert Checker"

# Run manually
schtasks /run /tn "Parish SLA Alert Checker"
```

**Linux:**
```bash
# Check cron is running
service cron status

# View cron logs
grep CRON /var/log/syslog
```

### Auto-Refresh Not Working
- Check browser console for errors
- Ensure `/api/sla-status.php` is accessible
- Try clearing browser cache
- Disable browser extensions that block scripts

---

## Performance Considerations

### For Large Databases

If you have thousands of pending items:

1. **Add indexes** (already included):
```sql
CREATE INDEX idx_status_created ON document_requests(status, created_at);
CREATE INDEX idx_status_created ON bookings(status, created_at);
CREATE INDEX idx_status_created ON payments(status, created_at);
```

2. **Limit dashboard queries**:
Edit `sla-dashboard.php` and add `LIMIT 50` to queries

3. **Adjust auto-refresh**:
Change `REFRESH_INTERVAL` from 120 to 300 seconds (5 minutes)

4. **Reduce alert frequency**:
Set check frequency to 30 or 60 minutes in Settings

---

## Customization

### Change SLA Thresholds
1. Admin → Settings → SLA Configuration
2. Modify hours for each type
3. Save changes

### Change Dashboard Colors
Edit `staff/sla-dashboard.php` CSS:
```css
.status-critical { background: #ef4444; }  /* Red */
.status-warning { background: #f59e0b; }   /* Orange */
.status-ok { background: #10b981; }        /* Green */
```

### Change Alert Email Template
Edit `handlers/sla_alert_handler.php`:
- Look for `generateAlertEmail()` method
- Modify HTML template

### Add Custom Notifications
Edit `staff/sla-dashboard.php`:
- Look for `notyf.error()` and `notyf.success()` calls
- Add custom messages

---

## Files Created/Modified

### New Files
- ✅ `api/sla-status.php` - API endpoint for real-time status
- ✅ `handlers/sla_alert_handler.php` - Email alert logic
- ✅ `admin/settings/sla-config.php` - Settings configuration page
- ✅ `scripts/run-sla-check.bat` - Windows scheduler script
- ✅ `scripts/run-sla-check.sh` - Linux/Mac cron script
- ✅ `database_sla_alerts.sql` - Database migration for alert tracking

### Modified Files
- ✅ `staff/sla-dashboard.php` - Enhanced with auto-refresh and notifications
- ✅ `database_schema.sql` - Already has SLA columns from previous session

---

## Best Practices

### For Staff
1. Check dashboard first thing each morning
2. Process critical items before warning items
3. Keep dashboard open in background tab
4. Don't disable browser notifications

### For Admin
1. Review SLA compliance weekly
2. Adjust thresholds if too many alerts
3. Monitor staff response times
4. Ensure automated checker is running

### For System Administrators
1. Monitor log file size: `logs/sla-checks.log`
2. Rotate logs monthly
3. Test email delivery periodically
4. Keep PHP and dependencies updated

---

## Future Enhancements

Potential improvements:
- 📊 Charts/graphs for SLA compliance trends
- 📱 SMS alerts via Twilio/Semaphore
- 🔔 Browser push notifications (PWA)
- 📈 Performance analytics dashboard
- 🤖 Auto-reassignment when staff absent
- 📧 Digest emails (daily summary instead of per-item)

---

## Support

If you encounter issues:
1. Check this guide's Troubleshooting section
2. Review log files: `logs/sla-checks.log`
3. Test components individually:
   - Dashboard: `staff/sla-dashboard.php`
   - API: `api/sla-status.php`
   - Alert handler: Run `php handlers/sla_alert_handler.php` manually

---

## Summary

The SLA Dashboard & Alerts system ensures:
- ✅ No request falls through the cracks
- ✅ Staff knows what needs immediate attention
- ✅ Admin has visibility into compliance
- ✅ Clients get timely responses
- ✅ Parish maintains professional service levels

**Dashboard URL:** `http://localhost/documentSystem/staff/sla-dashboard.php`
**Settings URL:** `http://localhost/documentSystem/admin/settings/sla-config.php`
