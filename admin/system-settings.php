<?php
/**
 * Admin: System Settings
 * Configure document types, booking types, blocked dates, and system settings
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../src/UI/Layouts/AdminLayout.php';
require_once __DIR__ . '/../src/UI/Helpers/UIHelpers.php';

startSecureSession();

// Check if user is logged in and is admin
$userRole = $_SESSION['user_role'] ?? 'guest';
if (!isset($_SESSION['user_id']) || $userRole !== 'admin') {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$conn = getDBConnection();
$statusMessage = '';
$statusType = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_document_type':
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $fee = floatval($_POST['fee']);
            $processing_days = intval($_POST['processing_days']);
            $requirements = trim($_POST['requirements']);
            
            $query = "INSERT INTO document_types (name, description, fee, processing_days, requirements) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssdis', $name, $description, $fee, $processing_days, $requirements);
            if ($stmt->execute()) {
                $statusMessage = 'Document type added successfully';
            }
            break;
            
        case 'edit_document_type':
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $fee = floatval($_POST['fee']);
            $processing_days = intval($_POST['processing_days']);
            $requirements = trim($_POST['requirements']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $query = "UPDATE document_types SET name = ?, description = ?, fee = ?, processing_days = ?, requirements = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssdisii', $name, $description, $fee, $processing_days, $requirements, $is_active, $id);
            if ($stmt->execute()) {
                $statusMessage = 'Document type updated successfully';
            }
            break;
            
        case 'add_booking_type':
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $fee = floatval($_POST['fee']);
            $duration = intval($_POST['duration']);
            $max_per_day = intval($_POST['max_per_day']);
            
            $query = "INSERT INTO booking_types (name, description, fee, duration_minutes, max_bookings_per_day) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssdii', $name, $description, $fee, $duration, $max_per_day);
            if ($stmt->execute()) {
                $statusMessage = 'Booking type added successfully';
            }
            break;
            
        case 'edit_booking_type':
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $fee = floatval($_POST['fee']);
            $duration = intval($_POST['duration']);
            $max_per_day = intval($_POST['max_per_day']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $query = "UPDATE booking_types SET name = ?, description = ?, fee = ?, duration_minutes = ?, max_bookings_per_day = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssdiiii', $name, $description, $fee, $duration, $max_per_day, $is_active, $id);
            if ($stmt->execute()) {
                $statusMessage = 'Booking type updated successfully';
            }
            break;
            
        case 'add_blocked_date':
            $date = $_POST['date'];
            $reason = trim($_POST['reason']);
            $userId = $_SESSION['user_id'];
            
            $query = "INSERT INTO blocked_dates (date, reason, created_by) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssi', $date, $reason, $userId);
            if ($stmt->execute()) {
                $statusMessage = 'Blocked date added successfully';
            }
            break;
            
        case 'delete_blocked_date':
            $id = intval($_POST['id']);
            $query = "DELETE FROM blocked_dates WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $statusMessage = 'Blocked date removed successfully';
            }
            break;

        // Payment Methods
        case 'add_payment_method':
            $name = trim($_POST['name']);
            $code = trim($_POST['code']);
            $display_name = trim($_POST['display_name']);
            $description = trim($_POST['description']);
            $requires_account = isset($_POST['requires_account']) ? 1 : 0;
            
            $query = "INSERT INTO payment_methods (name, code, display_name, description, requires_account_info) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssssi', $name, $code, $display_name, $description, $requires_account);
            if ($stmt->execute()) {
                $statusMessage = 'Payment method added successfully';
            }
            break;

        case 'edit_payment_method':
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $display_name = trim($_POST['display_name']);
            $description = trim($_POST['description']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $query = "UPDATE payment_methods SET name = ?, display_name = ?, description = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('sssii', $name, $display_name, $description, $is_active, $id);
            if ($stmt->execute()) {
                $statusMessage = 'Payment method updated successfully';
            }
            break;

        case 'delete_payment_method':
            $id = intval($_POST['id']);
            $query = "DELETE FROM payment_methods WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $statusMessage = 'Payment method deleted successfully';
            }
            break;

        // Payment Accounts
        case 'add_payment_account':
            $payment_method_id = intval($_POST['payment_method_id']);
            $account_name = trim($_POST['account_name']);
            $account_number = trim($_POST['account_number']);
            $account_holder = trim($_POST['account_holder']);
            $branch_name = trim($_POST['branch_name']);
            $instructions = trim($_POST['instructions']);
            
            $query = "INSERT INTO payment_accounts (payment_method_id, account_name, account_number, account_holder, branch_name, instructions) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('isssss', $payment_method_id, $account_name, $account_number, $account_holder, $branch_name, $instructions);
            if ($stmt->execute()) {
                $statusMessage = 'Payment account added successfully';
            }
            break;

        case 'edit_payment_account':
            $id = intval($_POST['id']);
            $account_name = trim($_POST['account_name']);
            $account_number = trim($_POST['account_number']);
            $account_holder = trim($_POST['account_holder']);
            $branch_name = trim($_POST['branch_name']);
            $instructions = trim($_POST['instructions']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $query = "UPDATE payment_accounts SET account_name = ?, account_number = ?, account_holder = ?, branch_name = ?, instructions = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssssii', $account_name, $account_number, $account_holder, $branch_name, $instructions, $is_active, $id);
            if ($stmt->execute()) {
                $statusMessage = 'Payment account updated successfully';
            }
            break;

        case 'delete_payment_account':
            $id = intval($_POST['id']);
            $query = "DELETE FROM payment_accounts WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $statusMessage = 'Payment account deleted successfully';
            }
            break;

        // Announcements
        case 'add_announcement':
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $userId = $_SESSION['user_id'];
            
            $query = "INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssi', $title, $content, $userId);
            if ($stmt->execute()) {
                $statusMessage = 'Announcement added successfully';
            }
            break;

        case 'edit_announcement':
            $id = intval($_POST['id']);
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $display_order = intval($_POST['display_order'] ?? 0);
            
            $query = "UPDATE announcements SET title = ?, content = ?, is_active = ?, display_order = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('sssii', $title, $content, $is_active, $display_order, $id);
            if ($stmt->execute()) {
                $statusMessage = 'Announcement updated successfully';
            }
            break;

        case 'delete_announcement':
            $id = intval($_POST['id']);
            $query = "DELETE FROM announcements WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $statusMessage = 'Announcement deleted successfully';
            }
            break;
    }
}

// Fetch document types
$documentTypes = $conn->query("SELECT * FROM document_types ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Fetch booking types
$bookingTypes = $conn->query("SELECT * FROM booking_types ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Fetch blocked dates
$blockedDates = $conn->query("SELECT bd.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name 
                               FROM blocked_dates bd 
                               LEFT JOIN users u ON bd.created_by = u.id 
                               ORDER BY bd.date DESC")->fetch_all(MYSQLI_ASSOC);

// Fetch payment methods
$paymentMethods = $conn->query("SELECT * FROM payment_methods ORDER BY sort_order, name")->fetch_all(MYSQLI_ASSOC);

// Fetch payment accounts
$paymentAccounts = $conn->query("SELECT pa.*, pm.name as method_name 
                                 FROM payment_accounts pa 
                                 JOIN payment_methods pm ON pa.payment_method_id = pm.id 
                                 ORDER BY pm.sort_order, pa.sort_order")->fetch_all(MYSQLI_ASSOC);

// Fetch announcements
$announcements = $conn->query("SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name 
                               FROM announcements a 
                               LEFT JOIN users u ON a.created_by = u.id 
                               ORDER BY a.display_order, a.created_at DESC")->fetch_all(MYSQLI_ASSOC);

closeDBConnection($conn);

// Build content
$content = '';

if ($statusMessage) {
    $content .= alert($statusMessage, $statusType);
}

// Add SLA Settings Quick Link
$content .= '<div class="alert alert-info d-flex justify-content-between align-items-center" role="alert">';
$content .= '<div><i class="bi bi-clock-history"></i> <strong>SLA Monitoring:</strong> Configure SLA thresholds and alert settings</div>';
$content .= '<a href="/documentSystem/admin/settings/sla-config.php" class="btn btn-sm btn-primary"><i class="bi bi-gear"></i> SLA Settings</a>';
$content .= '</div>';

// Document Types Section
$docTypesTable = '<div class="table-responsive"><table class="table table-hover">';
$docTypesTable .= '<thead><tr><th>Name</th><th>Fee</th><th>Processing Days</th><th>Status</th><th>Actions</th></tr></thead><tbody>';

foreach ($documentTypes as $dt) {
    $statusBadge = $dt['is_active'] ? badge('Active', 'success') : badge('Inactive', 'secondary');
    $docTypesTable .= '<tr>';
    $docTypesTable .= '<td><strong>' . htmlspecialchars($dt['name']) . '</strong><br><small class="text-muted">' . htmlspecialchars($dt['description']) . '</small></td>';
    $docTypesTable .= '<td>₱' . number_format($dt['fee'], 2) . '</td>';
    $docTypesTable .= '<td>' . $dt['processing_days'] . ' days</td>';
    $docTypesTable .= '<td>' . $statusBadge . '</td>';
    $docTypesTable .= '<td><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editDocModal' . $dt['id'] . '"><i class="bi bi-pencil"></i> Edit</button></td>';
    $docTypesTable .= '</tr>';
    
    // Edit modal
    $docTypesTable .= '<div class="modal fade" id="editDocModal' . $dt['id'] . '" tabindex="-1">';
    $docTypesTable .= '<div class="modal-dialog"><div class="modal-content"><form method="POST">';
    $docTypesTable .= '<div class="modal-header"><h5 class="modal-title">Edit Document Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
    $docTypesTable .= '<div class="modal-body">';
    $docTypesTable .= '<input type="hidden" name="action" value="edit_document_type">';
    $docTypesTable .= '<input type="hidden" name="id" value="' . $dt['id'] . '">';
    $docTypesTable .= formGroup('name', 'Name', 'text', $dt['name'], true);
    $docTypesTable .= formGroup('description', 'Description', 'textarea', $dt['description'], false);
    $docTypesTable .= formGroup('fee', 'Fee (₱)', 'number', $dt['fee'], true, ['placeholder' => '0.00']);
    $docTypesTable .= formGroup('processing_days', 'Processing Days', 'number', $dt['processing_days'], true);
    $docTypesTable .= formGroup('requirements', 'Requirements', 'textarea', $dt['requirements'], false);
    $docTypesTable .= '<div class="form-check"><input type="checkbox" class="form-check-input" name="is_active" id="is_active' . $dt['id'] . '" ' . ($dt['is_active'] ? 'checked' : '') . '>';
    $docTypesTable .= '<label class="form-check-label" for="is_active' . $dt['id'] . '">Active</label></div>';
    $docTypesTable .= '</div>';
    $docTypesTable .= '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
    $docTypesTable .= '<button type="submit" class="btn btn-primary">Save Changes</button></div>';
    $docTypesTable .= '</form></div></div></div>';
}

$docTypesTable .= '</tbody></table></div>';
$docTypesTable .= '<button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addDocModal"><i class="bi bi-plus"></i> Add Document Type</button>';

// Add Document Type Modal
$docTypesTable .= '<div class="modal fade" id="addDocModal" tabindex="-1">';
$docTypesTable .= '<div class="modal-dialog"><div class="modal-content"><form method="POST">';
$docTypesTable .= '<div class="modal-header"><h5 class="modal-title">Add Document Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
$docTypesTable .= '<div class="modal-body">';
$docTypesTable .= '<input type="hidden" name="action" value="add_document_type">';
$docTypesTable .= formGroup('name', 'Name', 'text', '', true);
$docTypesTable .= formGroup('description', 'Description', 'textarea', '', false);
$docTypesTable .= formGroup('fee', 'Fee (₱)', 'number', '', true, ['placeholder' => '0.00']);
$docTypesTable .= formGroup('processing_days', 'Processing Days', 'number', '3', true);
$docTypesTable .= formGroup('requirements', 'Requirements', 'textarea', '', false);
$docTypesTable .= '</div>';
$docTypesTable .= '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
$docTypesTable .= '<button type="submit" class="btn btn-success">Add Document Type</button></div>';
$docTypesTable .= '</form></div></div></div>';

$content .= card('Document Types', $docTypesTable);

// Booking Types Section
$bookingTypesTable = '<div class="table-responsive"><table class="table table-hover">';
$bookingTypesTable .= '<thead><tr><th>Name</th><th>Fee</th><th>Duration</th><th>Max/Day</th><th>Status</th><th>Actions</th></tr></thead><tbody>';

foreach ($bookingTypes as $bt) {
    $statusBadge = $bt['is_active'] ? badge('Active', 'success') : badge('Inactive', 'secondary');
    $bookingTypesTable .= '<tr>';
    $bookingTypesTable .= '<td><strong>' . htmlspecialchars($bt['name']) . '</strong><br><small class="text-muted">' . htmlspecialchars($bt['description']) . '</small></td>';
    $bookingTypesTable .= '<td>₱' . number_format($bt['fee'], 2) . '</td>';
    $bookingTypesTable .= '<td>' . $bt['duration_minutes'] . ' min</td>';
    $bookingTypesTable .= '<td>' . $bt['max_bookings_per_day'] . '</td>';
    $bookingTypesTable .= '<td>' . $statusBadge . '</td>';
    $bookingTypesTable .= '<td><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editBookingModal' . $bt['id'] . '"><i class="bi bi-pencil"></i> Edit</button></td>';
    $bookingTypesTable .= '</tr>';
    
    // Edit modal
    $bookingTypesTable .= '<div class="modal fade" id="editBookingModal' . $bt['id'] . '" tabindex="-1">';
    $bookingTypesTable .= '<div class="modal-dialog"><div class="modal-content"><form method="POST">';
    $bookingTypesTable .= '<div class="modal-header"><h5 class="modal-title">Edit Booking Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
    $bookingTypesTable .= '<div class="modal-body">';
    $bookingTypesTable .= '<input type="hidden" name="action" value="edit_booking_type">';
    $bookingTypesTable .= '<input type="hidden" name="id" value="' . $bt['id'] . '">';
    $bookingTypesTable .= formGroup('name', 'Name', 'text', $bt['name'], true);
    $bookingTypesTable .= formGroup('description', 'Description', 'textarea', $bt['description'], false);
    $bookingTypesTable .= formGroup('fee', 'Fee (₱)', 'number', $bt['fee'], true);
    $bookingTypesTable .= formGroup('duration', 'Duration (minutes)', 'number', $bt['duration_minutes'], true);
    $bookingTypesTable .= formGroup('max_per_day', 'Max Bookings Per Day', 'number', $bt['max_bookings_per_day'], true);
    $bookingTypesTable .= '<div class="form-check"><input type="checkbox" class="form-check-input" name="is_active" id="is_active_bt' . $bt['id'] . '" ' . ($bt['is_active'] ? 'checked' : '') . '>';
    $bookingTypesTable .= '<label class="form-check-label" for="is_active_bt' . $bt['id'] . '">Active</label></div>';
    $bookingTypesTable .= '</div>';
    $bookingTypesTable .= '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
    $bookingTypesTable .= '<button type="submit" class="btn btn-primary">Save Changes</button></div>';
    $bookingTypesTable .= '</form></div></div></div>';
}

$bookingTypesTable .= '</tbody></table></div>';
$bookingTypesTable .= '<button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBookingModal"><i class="bi bi-plus"></i> Add Booking Type</button>';

// Add Booking Type Modal
$bookingTypesTable .= '<div class="modal fade" id="addBookingModal" tabindex="-1">';
$bookingTypesTable .= '<div class="modal-dialog"><div class="modal-content"><form method="POST">';
$bookingTypesTable .= '<div class="modal-header"><h5 class="modal-title">Add Booking Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
$bookingTypesTable .= '<div class="modal-body">';
$bookingTypesTable .= '<input type="hidden" name="action" value="add_booking_type">';
$bookingTypesTable .= formGroup('name', 'Name', 'text', '', true);
$bookingTypesTable .= formGroup('description', 'Description', 'textarea', '', false);
$bookingTypesTable .= formGroup('fee', 'Fee (₱)', 'number', '', true);
$bookingTypesTable .= formGroup('duration', 'Duration (minutes)', 'number', '60', true);
$bookingTypesTable .= formGroup('max_per_day', 'Max Bookings Per Day', 'number', '10', true);
$bookingTypesTable .= '</div>';
$bookingTypesTable .= '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
$bookingTypesTable .= '<button type="submit" class="btn btn-success">Add Booking Type</button></div>';
$bookingTypesTable .= '</form></div></div></div>';

$content .= card('Booking Types', $bookingTypesTable);

// Blocked Dates Section
$blockedDatesTable = '<div class="table-responsive"><table class="table table-hover">';
$blockedDatesTable .= '<thead><tr><th>Date</th><th>Reason</th><th>Created By</th><th>Actions</th></tr></thead><tbody>';

if (empty($blockedDates)) {
    $blockedDatesTable .= '<tr><td colspan="4" class="text-center">No blocked dates</td></tr>';
} else {
    foreach ($blockedDates as $bd) {
        $blockedDatesTable .= '<tr>';
        $blockedDatesTable .= '<td>' . date('M d, Y', strtotime($bd['date'])) . '</td>';
        $blockedDatesTable .= '<td>' . htmlspecialchars($bd['reason']) . '</td>';
        $blockedDatesTable .= '<td>' . htmlspecialchars($bd['created_by_name']) . '</td>';
        $blockedDatesTable .= '<td><form method="POST" style="display: inline;"><input type="hidden" name="action" value="delete_blocked_date"><input type="hidden" name="id" value="' . $bd['id'] . '"><button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(\'Remove this blocked date?\')"><i class="bi bi-trash"></i></button></form></td>';
        $blockedDatesTable .= '</tr>';
    }
}

$blockedDatesTable .= '</tbody></table></div>';
$blockedDatesTable .= '<button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBlockedDateModal"><i class="bi bi-plus"></i> Add Blocked Date</button>';

// Add Blocked Date Modal
$blockedDatesTable .= '<div class="modal fade" id="addBlockedDateModal" tabindex="-1">';
$blockedDatesTable .= '<div class="modal-dialog"><div class="modal-content"><form method="POST">';
$blockedDatesTable .= '<div class="modal-header"><h5 class="modal-title">Add Blocked Date</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
$blockedDatesTable .= '<div class="modal-body">';
$blockedDatesTable .= '<input type="hidden" name="action" value="add_blocked_date">';
$blockedDatesTable .= formGroup('date', 'Date', 'date', '', true);
$blockedDatesTable .= formGroup('reason', 'Reason', 'text', '', true, ['placeholder' => 'e.g., Holiday, Special Event']);
$blockedDatesTable .= '</div>';
$blockedDatesTable .= '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
$blockedDatesTable .= '<button type="submit" class="btn btn-success">Add Blocked Date</button></div>';
$blockedDatesTable .= '</form></div></div></div>';

$content .= card('Blocked Dates (Holidays & Special Events)', $blockedDatesTable);

// Payment Methods Section
$paymentMethodsTable = '<div class="table-responsive"><table class="table table-hover">';
$paymentMethodsTable .= '<thead><tr><th>Name</th><th>Code</th><th>Requires Account</th><th>Status</th><th>Actions</th></tr></thead><tbody>';

if (empty($paymentMethods)) {
    $paymentMethodsTable .= '<tr><td colspan="5" class="text-center">No payment methods configured</td></tr>';
} else {
    foreach ($paymentMethods as $pm) {
        $statusBadge = $pm['is_active'] ? badge('Active', 'success') : badge('Inactive', 'secondary');
        $requiresBadge = $pm['requires_account_info'] ? badge('Yes', 'info') : badge('No', 'secondary');
        $paymentMethodsTable .= '<tr>';
        $paymentMethodsTable .= '<td><strong>' . htmlspecialchars($pm['display_name']) . '</strong><br><small class="text-muted">' . htmlspecialchars($pm['description']) . '</small></td>';
        $paymentMethodsTable .= '<td><code>' . htmlspecialchars($pm['code']) . '</code></td>';
        $paymentMethodsTable .= '<td>' . $requiresBadge . '</td>';
        $paymentMethodsTable .= '<td>' . $statusBadge . '</td>';
        $paymentMethodsTable .= '<td><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editPayMethodModal' . $pm['id'] . '"><i class="bi bi-pencil"></i> Edit</button> ';
        $paymentMethodsTable .= '<form method="POST" style="display: inline;"><input type="hidden" name="action" value="delete_payment_method"><input type="hidden" name="id" value="' . $pm['id'] . '"><button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(\'Delete this payment method?\')"><i class="bi bi-trash"></i></button></form></td>';
        $paymentMethodsTable .= '</tr>';
        
        // Edit modal
        $paymentMethodsTable .= '<div class="modal fade" id="editPayMethodModal' . $pm['id'] . '" tabindex="-1">';
        $paymentMethodsTable .= '<div class="modal-dialog"><div class="modal-content"><form method="POST">';
        $paymentMethodsTable .= '<div class="modal-header"><h5 class="modal-title">Edit Payment Method</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
        $paymentMethodsTable .= '<div class="modal-body">';
        $paymentMethodsTable .= '<input type="hidden" name="action" value="edit_payment_method">';
        $paymentMethodsTable .= '<input type="hidden" name="id" value="' . $pm['id'] . '">';
        $paymentMethodsTable .= formGroup('name', 'Name', 'text', $pm['name'], true);
        $paymentMethodsTable .= formGroup('display_name', 'Display Name', 'text', $pm['display_name'], true);
        $paymentMethodsTable .= formGroup('description', 'Description', 'textarea', $pm['description'], false);
        $paymentMethodsTable .= '<div class="form-check"><input type="checkbox" class="form-check-input" name="is_active" id="is_active_pm' . $pm['id'] . '" ' . ($pm['is_active'] ? 'checked' : '') . '>';
        $paymentMethodsTable .= '<label class="form-check-label" for="is_active_pm' . $pm['id'] . '">Active</label></div>';
        $paymentMethodsTable .= '</div>';
        $paymentMethodsTable .= '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
        $paymentMethodsTable .= '<button type="submit" class="btn btn-primary">Save Changes</button></div>';
        $paymentMethodsTable .= '</form></div></div></div>';
    }
}

$paymentMethodsTable .= '</tbody></table></div>';
$paymentMethodsTable .= '<button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPayMethodModal"><i class="bi bi-plus"></i> Add Payment Method</button>';

// Add Payment Method Modal
$paymentMethodsTable .= '<div class="modal fade" id="addPayMethodModal" tabindex="-1">';
$paymentMethodsTable .= '<div class="modal-dialog"><div class="modal-content"><form method="POST">';
$paymentMethodsTable .= '<div class="modal-header"><h5 class="modal-title">Add Payment Method</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
$paymentMethodsTable .= '<div class="modal-body">';
$paymentMethodsTable .= '<input type="hidden" name="action" value="add_payment_method">';
$paymentMethodsTable .= formGroup('name', 'Name', 'text', '', true);
$paymentMethodsTable .= formGroup('code', 'Code', 'text', '', true, ['placeholder' => 'e.g., bdo, paymaya']);
$paymentMethodsTable .= formGroup('display_name', 'Display Name', 'text', '', true, ['placeholder' => 'How it appears to users']);
$paymentMethodsTable .= formGroup('description', 'Description', 'textarea', '', false);
$paymentMethodsTable .= '<div class="form-check"><input type="checkbox" class="form-check-input" name="requires_account" id="requires_account"><label class="form-check-label" for="requires_account">Requires Account Information</label></div>';
$paymentMethodsTable .= '</div>';
$paymentMethodsTable .= '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
$paymentMethodsTable .= '<button type="submit" class="btn btn-success">Add Payment Method</button></div>';
$paymentMethodsTable .= '</form></div></div></div>';

$content .= card('Payment Methods', $paymentMethodsTable);

// Payment Accounts Section
$paymentAccountsTable = '<div class="table-responsive"><table class="table table-hover">';
$paymentAccountsTable .= '<thead><tr><th>Method</th><th>Account Name</th><th>Account Number</th><th>Account Holder</th><th>Status</th><th>Actions</th></tr></thead><tbody>';

if (empty($paymentAccounts)) {
    $paymentAccountsTable .= '<tr><td colspan="6" class="text-center">No payment accounts configured</td></tr>';
} else {
    foreach ($paymentAccounts as $pa) {
        $statusBadge = $pa['is_active'] ? badge('Active', 'success') : badge('Inactive', 'secondary');
        $paymentAccountsTable .= '<tr>';
        $paymentAccountsTable .= '<td><strong>' . htmlspecialchars($pa['method_name']) . '</strong></td>';
        $paymentAccountsTable .= '<td>' . htmlspecialchars($pa['account_name']) . '</td>';
        $paymentAccountsTable .= '<td><code>' . htmlspecialchars($pa['account_number']) . '</code></td>';
        $paymentAccountsTable .= '<td>' . htmlspecialchars($pa['account_holder']) . '</td>';
        $paymentAccountsTable .= '<td>' . $statusBadge . '</td>';
        $paymentAccountsTable .= '<td><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editPayAccModal' . $pa['id'] . '"><i class="bi bi-pencil"></i> Edit</button> ';
        $paymentAccountsTable .= '<form method="POST" style="display: inline;"><input type="hidden" name="action" value="delete_payment_account"><input type="hidden" name="id" value="' . $pa['id'] . '"><button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(\'Delete this payment account?\')"><i class="bi bi-trash"></i></button></form></td>';
        $paymentAccountsTable .= '</tr>';
        
        // Edit modal
        $paymentAccountsTable .= '<div class="modal fade" id="editPayAccModal' . $pa['id'] . '" tabindex="-1">';
        $paymentAccountsTable .= '<div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST">';
        $paymentAccountsTable .= '<div class="modal-header"><h5 class="modal-title">Edit Payment Account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
        $paymentAccountsTable .= '<div class="modal-body">';
        $paymentAccountsTable .= '<input type="hidden" name="action" value="edit_payment_account">';
        $paymentAccountsTable .= '<input type="hidden" name="id" value="' . $pa['id'] . '">';
        $paymentAccountsTable .= formGroup('account_name', 'Account Name', 'text', $pa['account_name'], true);
        $paymentAccountsTable .= formGroup('account_number', 'Account Number', 'text', $pa['account_number'], true);
        $paymentAccountsTable .= formGroup('account_holder', 'Account Holder Name', 'text', $pa['account_holder'], true);
        $paymentAccountsTable .= formGroup('branch_name', 'Branch Name', 'text', $pa['branch_name'], false);
        $paymentAccountsTable .= formGroup('instructions', 'Instructions', 'textarea', $pa['instructions'], false, ['rows' => '3']);
        $paymentAccountsTable .= '<div class="form-check"><input type="checkbox" class="form-check-input" name="is_active" id="is_active_pa' . $pa['id'] . '" ' . ($pa['is_active'] ? 'checked' : '') . '>';
        $paymentAccountsTable .= '<label class="form-check-label" for="is_active_pa' . $pa['id'] . '">Active</label></div>';
        $paymentAccountsTable .= '</div>';
        $paymentAccountsTable .= '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
        $paymentAccountsTable .= '<button type="submit" class="btn btn-primary">Save Changes</button></div>';
        $paymentAccountsTable .= '</form></div></div></div>';
    }
}

$paymentAccountsTable .= '</tbody></table></div>';
$paymentAccountsTable .= '<button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPayAccModal"><i class="bi bi-plus"></i> Add Payment Account</button>';

// Add Payment Account Modal
$paymentAccountsTable .= '<div class="modal fade" id="addPayAccModal" tabindex="-1">';
$paymentAccountsTable .= '<div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST">';
$paymentAccountsTable .= '<div class="modal-header"><h5 class="modal-title">Add Payment Account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
$paymentAccountsTable .= '<div class="modal-body">';
$paymentAccountsTable .= '<input type="hidden" name="action" value="add_payment_account">';

// Payment method select
$paymentAccountsTable .= '<div class="mb-3"><label for="payment_method_id" class="form-label">Payment Method <span class="text-danger">*</span></label>';
$paymentAccountsTable .= '<select class="form-select" name="payment_method_id" id="payment_method_id" required>';
$paymentAccountsTable .= '<option value="">-- Select Payment Method --</option>';
foreach ($paymentMethods as $pm) {
    $paymentAccountsTable .= '<option value="' . $pm['id'] . '">' . htmlspecialchars($pm['display_name']) . '</option>';
}
$paymentAccountsTable .= '</select></div>';

$paymentAccountsTable .= formGroup('account_name', 'Account Name', 'text', '', true);
$paymentAccountsTable .= formGroup('account_number', 'Account Number', 'text', '', true);
$paymentAccountsTable .= formGroup('account_holder', 'Account Holder Name', 'text', '', true);
$paymentAccountsTable .= formGroup('branch_name', 'Branch Name', 'text', '', false);
$paymentAccountsTable .= formGroup('instructions', 'Instructions', 'textarea', '', false, ['rows' => '3', 'placeholder' => 'Payment instructions for customers']);
$paymentAccountsTable .= '</div>';
$paymentAccountsTable .= '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
$paymentAccountsTable .= '<button type="submit" class="btn btn-success">Add Payment Account</button></div>';
$paymentAccountsTable .= '</form></div></div></div>';

$content .= card('Payment Accounts (Bank, GCash, PayMaya)', $paymentAccountsTable);

// Announcements Section
$announcementsTable = '<div class="table-responsive"><table class="table table-hover">';
$announcementsTable .= '<thead><tr><th>Title</th><th>Order</th><th>Status</th><th>Created By</th><th>Date</th><th>Actions</th></tr></thead><tbody>';

if (empty($announcements)) {
    $announcementsTable .= '<tr><td colspan="6" class="text-center">No announcements yet</td></tr>';
} else {
    foreach ($announcements as $ann) {
        $statusBadge = $ann['is_active'] ? badge('Active', 'success') : badge('Inactive', 'secondary');
        $announcementsTable .= '<tr>';
        $announcementsTable .= '<td><strong>' . htmlspecialchars($ann['title']) . '</strong><br><small class="text-muted">' . substr(htmlspecialchars($ann['content']), 0, 100) . '...</small></td>';
        $announcementsTable .= '<td><span class="badge bg-secondary">' . $ann['display_order'] . '</span></td>';
        $announcementsTable .= '<td>' . $statusBadge . '</td>';
        $announcementsTable .= '<td>' . htmlspecialchars($ann['created_by_name']) . '</td>';
        $announcementsTable .= '<td>' . date('M d, Y', strtotime($ann['created_at'])) . '</td>';
        $announcementsTable .= '<td><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editAnnModal' . $ann['id'] . '"><i class="bi bi-pencil"></i> Edit</button> ';
        $announcementsTable .= '<form method="POST" style="display: inline;"><input type="hidden" name="action" value="delete_announcement"><input type="hidden" name="id" value="' . $ann['id'] . '"><button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(\'Delete this announcement?\')"><i class="bi bi-trash"></i></button></form></td>';
        $announcementsTable .= '</tr>';
        
        // Edit modal
        $announcementsTable .= '<div class="modal fade" id="editAnnModal' . $ann['id'] . '" tabindex="-1">';
        $announcementsTable .= '<div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST">';
        $announcementsTable .= '<div class="modal-header"><h5 class="modal-title">Edit Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
        $announcementsTable .= '<div class="modal-body">';
        $announcementsTable .= '<input type="hidden" name="action" value="edit_announcement">';
        $announcementsTable .= '<input type="hidden" name="id" value="' . $ann['id'] . '">';
        $announcementsTable .= formGroup('title', 'Title', 'text', $ann['title'], true);
        $announcementsTable .= formGroup('content', 'Content', 'textarea', $ann['content'], true, ['rows' => '6']);
        $announcementsTable .= formGroup('display_order', 'Display Order', 'number', $ann['display_order'], false, ['placeholder' => '0']);
        $announcementsTable .= '<div class="form-check"><input type="checkbox" class="form-check-input" name="is_active" id="is_active_ann' . $ann['id'] . '" ' . ($ann['is_active'] ? 'checked' : '') . '>';
        $announcementsTable .= '<label class="form-check-label" for="is_active_ann' . $ann['id'] . '">Active</label></div>';
        $announcementsTable .= '</div>';
        $announcementsTable .= '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
        $announcementsTable .= '<button type="submit" class="btn btn-primary">Save Changes</button></div>';
        $announcementsTable .= '</form></div></div></div>';
    }
}

$announcementsTable .= '</tbody></table></div>';
$announcementsTable .= '<button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addAnnModal"><i class="bi bi-plus"></i> Add Announcement</button>';

// Add Announcement Modal
$announcementsTable .= '<div class="modal fade" id="addAnnModal" tabindex="-1">';
$announcementsTable .= '<div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST">';
$announcementsTable .= '<div class="modal-header"><h5 class="modal-title">Add Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
$announcementsTable .= '<div class="modal-body">';
$announcementsTable .= '<input type="hidden" name="action" value="add_announcement">';
$announcementsTable .= formGroup('title', 'Title', 'text', '', true, ['placeholder' => 'Announcement title']);
$announcementsTable .= formGroup('content', 'Content', 'textarea', '', true, ['rows' => '6', 'placeholder' => 'Enter announcement content']);
$announcementsTable .= '</div>';
$announcementsTable .= '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
$announcementsTable .= '<button type="submit" class="btn btn-success">Add Announcement</button></div>';
$announcementsTable .= '</form></div></div></div>';

$content .= card('Announcements (Login Page)', $announcementsTable);

// Create layout and render
$layout = new AdminLayout('System Settings', 'System Settings', 'gear');
$layout->setContent($content);

echo $layout->render();
?>
