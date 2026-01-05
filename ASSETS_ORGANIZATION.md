# Asset Organization Guide

## CSS Files (in `/assets/css/`)

### common.css (770+ lines)
**Used by:** All pages
**Contents:**
- Sidebar styling (user profile, navigation, icons)
- Main content layout
- Form sections and input styling
- Button styles (primary, secondary, small)
- Alert styling (success, danger, info)
- Footer styling
- Utility classes and animations
- Responsive breakpoints

**Link to add to HTML:**
```html
<link href="/documentSystem/assets/css/common.css" rel="stylesheet">
```

---

### dashboard.css (170+ lines)
**Used by:** dashboard.php
**Contents:**
- Stats grid layout for KPI cards
- Quick action buttons grid
- Notification alert variations
- Email verification banner
- List item styling for appointments/documents
- Status-specific badge colors
- Two-column grid layout
- Account info grid

**Link to add to HTML:**
```html
<link href="/documentSystem/assets/css/dashboard.css" rel="stylesheet">
```

---

### forms.css (140+ lines)
**Used by:** request-documents.php, new-appointment.php
**Contents:**
- Document/booking type card selection styling
- File upload with drag-and-drop visual feedback
- Success message styling
- Calendar and time slot grid layouts
- Time slot selection feedback
- Requirements list styling
- Form card layouts

**Link to add to HTML:**
```html
<link href="/documentSystem/assets/css/forms.css" rel="stylesheet">
```

---

### appointments.css (200+ lines)
**Used by:** view-appointments.php, view-documents.php
**Contents:**
- Booking/document card styling
- Card status variations (cancelled, ready)
- Details grid layout
- Tab interface styling
- Modal styling
- Actions button group
- Responsive layout for mobile

**Link to add to HTML:**
```html
<link href="/documentSystem/assets/css/appointments.css" rel="stylesheet">
```

---

## JavaScript Files (in `/assets/js/`)

### common.js (70+ lines)
**Used by:** All pages
**Functions:**
- `showAlert(message, type)` - Display alert messages
- `formatDate(dateString)` - Format dates to readable format
- `generateReference(prefix)` - Generate reference numbers (DOC-, APT-)
- `isValidEmail(email)` - Email validation
- `isValidFileSize(file, maxSizeMB)` - File size validation
- `toggleModal(modalId)` - Toggle modal visibility
- `closeModal(modalId)` - Close specific modal
- `openModal(modalId)` - Open specific modal
- `formatDateForInput(date)` - Format date for input fields
- `getMinDateForInput()` - Get tomorrow's date for minimum date

**Link to add to HTML:**
```html
<script src="/documentSystem/assets/js/common.js"></script>
```

---

### forms.js (250+ lines)
**Used by:** request-documents.php, new-appointment.php
**Functions:**
- `setupFileUpload()` - Initialize file upload with drag-and-drop
- `updateFileDisplay(fileInput)` - Update file list display
- `removeFile(btn, index)` - Remove file from selection
- `validateDocumentRequestForm()` - Validate document request
- `validateAppointmentForm()` - Validate appointment booking
- `setupBookingTypeSelection()` - Handle booking type card selection
- `setupDocumentTypeSelection()` - Handle document type card selection
- `generateTimeSlots(date)` - Generate time slot buttons
- Auto-initializes on DOMContentLoaded

**Link to add to HTML:**
```html
<script src="/documentSystem/assets/js/forms.js"></script>
```

---

### appointments.js (150+ lines)
**Used by:** view-appointments.php, view-documents.php
**Functions:**
- `setupTabs()` - Initialize tab switching
- `cancelAppointment(appointmentId)` - Open cancel modal
- `submitCancellation()` - Submit appointment cancellation
- `closeCancelModal()` - Close cancellation modal
- `rescheduleAppointment(appointmentId)` - Placeholder for rescheduling
- `viewAppointmentDetails(appointmentId)` - Placeholder for details modal
- `makePayment(documentId)` - Placeholder for payment
- `downloadDocument(documentId)` - Placeholder for document download
- `viewDocumentDetails(documentId)` - Placeholder for document details
- Close modal on outside click or Escape key
- Auto-initializes tabs on DOMContentLoaded

**Link to add to HTML:**
```html
<script src="/documentSystem/assets/js/appointments.js"></script>
```

---

### dashboard.js (200+ lines)
**Used by:** dashboard.php
**Functions:**
- `updateStatistics()` - Fetch and update KPI statistics
- `loadUpcomingAppointments()` - Load upcoming appointments from API
- `loadPendingDocuments()` - Load pending documents from API
- `loadReadyDocuments()` - Load ready documents from API
- `loadNotifications()` - Load recent notifications from API
- `goToNewAppointment()` - Navigate to appointment booking
- `goToRequestDocument()` - Navigate to document request
- `goToViewAppointments()` - Navigate to view appointments
- `goToViewDocuments()` - Navigate to view documents
- `loadPaymentHistory()` - Load payment history table
- Auto-refresh statistics every 30 seconds
- Auto-initializes on DOMContentLoaded

**Link to add to HTML:**
```html
<script src="/documentSystem/assets/js/dashboard.js"></script>
```

---

## How to Update HTML Files

### For ALL pages, add this in the `<head>` section:
```html
<link href="/documentSystem/assets/css/common.css" rel="stylesheet">
```

### For dashboard.php, also add:
```html
<link href="/documentSystem/assets/css/dashboard.css" rel="stylesheet">
<script src="/documentSystem/assets/js/dashboard.js"></script>
```

### For request-documents.php and new-appointment.php, also add:
```html
<link href="/documentSystem/assets/css/forms.css" rel="stylesheet">
<script src="/documentSystem/assets/js/forms.js"></script>
<script src="/documentSystem/assets/js/common.js"></script>
```

### For view-appointments.php and view-documents.php, also add:
```html
<link href="/documentSystem/assets/css/appointments.css" rel="stylesheet">
<script src="/documentSystem/assets/js/appointments.js"></script>
<script src="/documentSystem/assets/js/common.js"></script>
```

---

## Image Organization Structure

Create these subdirectories in `/assets/images/`:
- `/assets/images/icons/` - UI icons (upload, delete, edit, etc.)
- `/assets/images/logos/` - Church logo and branding
- `/assets/images/user-avatars/` - Default user avatar placeholder
- `/assets/images/document-types/` - Document type icons/thumbnails
- `/assets/images/status-icons/` - Status indicators (pending, approved, ready, etc.)

---

## Next Steps

1. **Update all HTML pages** to link the CSS and JavaScript files from assets folder
2. **Remove inline `<style>` and `<script>` tags** from HTML pages once external files are linked
3. **Test each page** to ensure functionality works with external files
4. **Add image assets** to the appropriate subdirectories in `/assets/images/`
5. **Update image references** in CSS/HTML to use `/documentSystem/assets/images/...` paths

