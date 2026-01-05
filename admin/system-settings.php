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
            $stmt->bind_param('ssdiii', $name, $description, $fee, $duration, $max_per_day, $is_active, $id);
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

// Create layout and render
$layout = new AdminLayout('System Settings', 'System Settings', 'gear');
$layout->setContent($content);

echo $layout->render();
?>
