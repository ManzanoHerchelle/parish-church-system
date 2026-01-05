# Email Notifications, Staff Absence & SLA Tracking Implementation Guide

## Overview
This document outlines the three major features added to improve real-world church operations:

1. **Email Notifications** - Automatic client updates on request status changes
2. **Staff Absence Management** - Track staff availability and reassign work
3. **SLA Tracking** - Monitor processing times and compliance

---

## 1. Email Notifications ✅

### What Was Added

**Enhanced EmailHandler (`handlers/email_handler.php`)**
- `sendDocumentApprovedNotification()` - Notify client when document is approved
- `sendDocumentRejectedNotification()` - Notify client when document is rejected
- `sendBookingConfirmedNotification()` - Notify client when booking is confirmed
- `sendBookingCancelledNotification()` - Notify client when booking is cancelled
- `sendPaymentVerifiedNotification()` - Notify client when payment is verified
- `sendPaymentRejectedNotification()` - Notify client when payment is rejected

**Updated Staff Pages**
- `staff/process-documents.php` - Sends email when document approved/rejected
- `staff/process-bookings.php` - Sends email when booking confirmed/cancelled
- `staff/verify-payments.php` - Sends email when payment verified/rejected

### How It Works

```
Staff approves document
     ↓
System updates status & sends email
     ↓
Client receives notification with details
     ↓
Client tracks status in dashboard
```

### Benefits
- ✅ Clients know immediately when action taken
- ✅ No repeated inquiries to check status
- ✅ Professional communication
- ✅ Reduces walk-ins to office
- ✅ Better customer satisfaction

### Email Templates Included
- ✉️ Document Approved (green, professional)
- ✉️ Document Rejected (with reason)
- ✉️ Booking Confirmed (with date/time)
- ✉️ Booking Cancelled (with reason)
- ✉️ Payment Verified (with amount)
- ✉️ Payment Rejected (with reason)

---

## 2. Staff Absence Management ✅

### New Feature: Staff Management Page

**Location:** `/admin/manage-staff.php`

**Key Features:**
1. **Staff Workload Dashboard**
   - See who's available vs absent
   - View pending work per staff member
   - Monitor overall workload

2. **Add Staff Absence**
   - Set start/end dates
   - Specify reason (sick, vacation, training)
   - Optionally reassign work to backup staff

3. **Active Absence Tracking**
   - Shows currently absent staff
   - Displays their pending work
   - Shows reassignment status

4. **Absence History**
   - View all past absences
   - Track absence patterns
   - Audit trail

### Database Changes

**New Table: `staff_absences`**
```sql
CREATE TABLE staff_absences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,              -- Staff member on leave
    start_date DATE NOT NULL,           -- When absence starts
    end_date DATE NOT NULL,             -- When absence ends
    reason VARCHAR(255),                 -- Why absent (sick, vacation, etc)
    is_approved TINYINT(1),             -- Admin approval
    reassign_to INT,                    -- Backup staff member
    notes TEXT,                         -- Additional notes
    created_at TIMESTAMP,
    ...
);
```

### How to Use

**As an Admin:**
1. Go to Admin Dashboard → Manage Staff
2. Click "Add Staff Absence"
3. Select staff member and dates
4. Optionally select backup staff
5. Click "Add Absence Record"

**What Happens Automatically:**
- Staff member marked as unavailable
- Dashboard shows current absences
- Other staff see the workload
- Email notifications sent (if configured)

### Real-World Scenarios

**Scenario 1: Friday absence**
```
Thursday: Admin marks John as absent Friday
         Reassigns his 3 pending documents to Maria
Friday:  Clients' documents still processed on time
         Maria handles John's workload
Monday:  John returns, catches up on any new requests
```

**Scenario 2: One week vacation**
```
Manager: Sets June 1-7 as vacation
         Reassigns all work to staff team
Client:  Their document submitted June 2
         Gets processed by someone available
         Gets updated by email within 24 hours
```

---

## 3. SLA Tracking & Alerts ✅

### New Feature: SLA Dashboard

**Location:** `/staff/sla-dashboard.php`

**SLA Standards Set:**
- 📄 **Documents:** 48 hours
  - ⚠️ Warning: After 36 hours
  - 🔴 Critical: After 48 hours

- 📅 **Bookings:** 24 hours
  - ⚠️ Warning: After 18 hours
  - 🔴 Critical: After 24 hours

- 💳 **Payments:** 24 hours
  - ⚠️ Warning: After 18 hours
  - 🔴 Critical: After 24 hours

### Dashboard Features

1. **Critical Alert Banner**
   - Shows if any items exceed SLA
   - Immediate attention indicator
   - "Fix immediately" message

2. **Real-Time Statistics**
   - Total pending documents
   - Total pending bookings
   - Total pending payments
   - Critical items count

3. **Overdue Items List**
   - Shows each pending request
   - How long it's been pending
   - Color-coded status (critical/warning/ok)
   - Reference numbers for quick lookup

4. **Today's Activity**
   - What each staff processed today
   - Documents approved/rejected
   - Bookings confirmed
   - Payments verified

5. **SLA Standards Display**
   - Clear explanation of SLA times
   - Warning/critical thresholds
   - Helps staff understand expectations

### Database Changes

**Updated Tables:**
```sql
-- document_requests
ALTER TABLE document_requests ADD COLUMN target_completion_date DATETIME;
ALTER TABLE document_requests ADD COLUMN is_overdue TINYINT(1) DEFAULT 0;
ALTER TABLE document_requests ADD COLUMN client_notified TINYINT(1) DEFAULT 0;
ALTER TABLE document_requests ADD COLUMN client_notification_sent_at DATETIME;

-- bookings
ALTER TABLE bookings ADD COLUMN target_completion_date DATETIME;
ALTER TABLE bookings ADD COLUMN is_overdue TINYINT(1) DEFAULT 0;
ALTER TABLE bookings ADD COLUMN client_notified TINYINT(1) DEFAULT 0;
ALTER TABLE bookings ADD COLUMN client_notification_sent_at DATETIME;

-- payments
ALTER TABLE payments ADD COLUMN target_verification_date DATETIME;
ALTER TABLE payments ADD COLUMN is_overdue TINYINT(1) DEFAULT 0;
ALTER TABLE payments ADD COLUMN client_notified TINYINT(1) DEFAULT 0;
ALTER TABLE payments ADD COLUMN client_notification_sent_at DATETIME;
```

### Real-World Example

**Scenario: Monday at 10 AM**

```
SLA Dashboard shows:
─────────────────────
📄 Documents:
   - Ref#2024-001 (Baptismal Certificate) 
     Pending: 47 hours ⚠️ WARNING
     Client: John Santos
     
   - Ref#2024-002 (Marriage Certificate)
     Pending: 50 hours 🔴 CRITICAL
     Client: Maria Garcia
     
📅 Bookings: All OK ✓

💳 Payments:
   - TXN#PAY-001
     Pending: 25 hours 🔴 CRITICAL
     Amount: ₱5,000

Staff Action Required:
→ Process Document 2024-002 (overdue!)
→ Verify Payment PAY-001 (overdue!)
```

### Benefits

**For Staff:**
- 📍 Know what needs immediate attention
- 📊 Track their productivity
- 🎯 Meet SLA targets
- 📈 Performance visibility

**For Admin:**
- 👁️ Monitor compliance
- 🚨 Identify bottlenecks
- 📋 Performance metrics
- 🎯 Operational health

**For Clients:**
- ✅ Know when to expect response
- 🔔 Get updates when status changes
- 🕐 Can estimate wait time
- 😊 Better experience

---

## Integration Summary

### How These Three Features Work Together

```
Client submits request
     ↓
System records creation time
Sets target completion (SLA deadline)
     ↓
Staff reviews in SLA Dashboard
Gets assignment (or reassignment if absent)
     ↓
Staff processes request
     ↓
System sends email to client
Marks client_notified = true
Records notification time
     ↓
Dashboard updated in real-time
Shows completion status
     ↓
Client happy with:
- Prompt response ✅
- Email updates ✅
- Professional service ✅
```

---

## Setup Instructions

### Step 1: Update Database

Run the SQL changes from `database_schema.sql`:
```sql
-- Add columns to document_requests, bookings, payments
-- Create staff_absences table
-- Add email_logs table if not exists
```

### Step 2: Test Email Configuration

Check `config/email_config.php`:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

### Step 3: Access New Features

**Admin Panel:**
- Go to Admin Dashboard
- Click "Manage Staff"
- Add sample absence to test

**Staff Pages:**
- Process Documents → Approves document → Client gets email
- Process Bookings → Confirms booking → Client gets email
- Verify Payments → Verifies payment → Client gets email

**SLA Dashboard:**
- Staff → SLA Dashboard
- See all pending items with wait times
- Monitor critical items

---

## Testing Checklist

- [ ] Create test staff absence record
- [ ] Verify staff shows as "absent" on dashboard
- [ ] Approve/reject document and confirm email sent
- [ ] Confirm/cancel booking and confirm email sent
- [ ] Verify/reject payment and confirm email sent
- [ ] Check SLA dashboard shows correct wait times
- [ ] Test email notifications reach Gmail/email client

---

## Real-World Usage Scenarios

### Scenario 1: Managing a Sick Staff Member
```
Tuesday 8 AM: John calls in sick
Admin goes to Manage Staff
Marks John absent for Tuesday-Friday
Reassigns his pending work to Maria
Maria sees 2 additional documents to process
All clients' documents processed on time
John returns Monday refreshed
```

### Scenario 2: Processing Workflow with Notifications
```
Monday 2 PM: Maria submits baptism certificate request
System sets deadline: Wednesday 2 PM (48 hours)

Tuesday 10 AM: Admin approves the document
Email sent to Maria: "Your document has been approved"
Status shows: "approved" in her dashboard

Wednesday 1 PM: Maria picks up her certificate
Happy client, no follow-up calls needed
```

### Scenario 3: SLA Breach Prevention
```
Wednesday 2 PM: Staff reviews SLA Dashboard
Shows 2 documents approaching deadline
Staff member gets 1 critical item

Staff immediately processes them
Sends approvals
Emails sent to clients
Both meet SLA targets
Office reputation maintained
```

---

## Maintenance

### Monthly Tasks
1. Review staff absence patterns
2. Check SLA compliance metrics
3. Adjust SLA times if needed
4. Archive old absence records

### Quarterly Tasks
1. Analyze processing time trends
2. Identify bottlenecks
3. Adjust staff assignments
4. Review email templates

---

## Future Enhancements

These could be added later:

1. **Auto-Reassignment**
   - Automatically assign work when staff absent
   - Based on workload balance

2. **SLA Notifications**
   - Alert staff 1 hour before deadline
   - Alert admin when items critical

3. **Performance Reports**
   - Monthly staff performance
   - SLA compliance by staff
   - Turnaround time analytics

4. **SMS Notifications**
   - Text alerts for urgent items
   - Notifications for critical SLA breaches

5. **Calendar Integration**
   - Google Calendar sync for absences
   - iCalendar support

6. **Escalation Rules**
   - Auto-escalate to admin if overdue
   - Automatic reminders to staff

---

## Support & Troubleshooting

### Emails not sending?
- Check email config: `config/email_config.php`
- Verify SMTP credentials
- Check Gmail app-specific password is set
- Review error logs

### SLA times showing incorrectly?
- Verify server timezone is correct
- Check database timestamps are accurate
- Reload page to refresh data

### Staff absence not showing?
- Confirm dates are correct
- Check date format (YYYY-MM-DD)
- Ensure staff member is set to "staff" role

---

## Files Changed/Added

### Files Modified:
1. `database_schema.sql` - Added SLA columns & staff_absences table
2. `handlers/email_handler.php` - Added 6 new notification methods
3. `staff/process-documents.php` - Added email notification on status change
4. `staff/process-bookings.php` - Added email notification on status change
5. `staff/verify-payments.php` - Added email notification on status change

### Files Created:
1. `admin/manage-staff.php` - Staff absence management interface
2. `staff/sla-dashboard.php` - SLA monitoring & alerts dashboard

---

## Summary

**Email Notifications:**
- ✅ 6 new notification types
- ✅ Professional email templates
- ✅ Automatic on status changes
- ✅ Reduces client inquiries

**Staff Absence Management:**
- ✅ Track staff availability
- ✅ Reassign pending work
- ✅ Workload visibility
- ✅ Professional administration

**SLA Tracking:**
- ✅ Monitor processing times
- ✅ Alert on critical breaches
- ✅ Performance metrics
- ✅ Real-time dashboard

**Total Impact:**
- 🎯 Better client experience
- 📊 Better operations management
- 👥 Better staff coordination
- 📈 Better service quality
