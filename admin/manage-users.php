<?php
/**
 * Admin: Manage Users
 * Interface for admin to view and manage user accounts
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../src/Services/UserService.php';
require_once __DIR__ . '/../src/UI/Layouts/AdminLayout.php';
require_once __DIR__ . '/../src/UI/Helpers/UIHelpers.php';

startSecureSession();

// Check if user is logged in and is admin
$userRole = $_SESSION['user_role'] ?? 'guest';
if (!isset($_SESSION['user_id']) || $userRole !== 'admin') {
    header('Location: /documentSystem/client/login.php');
    exit;
}

use Services\UserService;

$userService = new UserService();
$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Handle action requests (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetUserId = intval($_POST['user_id'] ?? 0);
    
    if ($targetUserId > 0 && $targetUserId !== $userId) { // Prevent self-modification
        switch ($action) {
            case 'activate':
                $userService->activateUser($targetUserId);
                header('Location: /documentSystem/admin/manage-users.php?status=success&message=User+activated');
                exit;
                
            case 'deactivate':
                $userService->deactivateUser($targetUserId);
                header('Location: /documentSystem/admin/manage-users.php?status=success&message=User+deactivated');
                exit;
                
            case 'make_staff':
                $userService->updateUserRole($targetUserId, 'staff');
                header('Location: /documentSystem/admin/manage-users.php?status=success&message=User+promoted+to+staff');
                exit;
                
            case 'make_client':
                $userService->updateUserRole($targetUserId, 'client');
                header('Location: /documentSystem/admin/manage-users.php?status=success&message=User+role+changed+to+client');
                exit;
        }
    }
}

// Get filter parameters
$roleFilter = $_GET['role'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$pageNum = intval($_GET['page'] ?? 1);
$limit = 20;
$offset = ($pageNum - 1) * $limit;

// Fetch users
$filters = [
    'role' => $roleFilter,
    'status' => $statusFilter,
    'limit' => $limit,
    'offset' => $offset
];
$users = $userService->getUsers($filters);

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as count FROM users WHERE 1=1";
$params = [];
$types = '';

if ($roleFilter && $roleFilter !== 'all') {
    $countQuery .= " AND role = ?";
    $params[] = $roleFilter;
    $types .= 's';
}

if ($statusFilter && $statusFilter !== 'all') {
    $countQuery .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($params) {
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalCount = $stmt->get_result()->fetch_assoc()['count'];
} else {
    $totalCount = $conn->query($countQuery)->fetch_assoc()['count'];
}

$totalPages = ceil($totalCount / $limit);

// Get user counts
$statsQuery = "SELECT role, status, COUNT(*) as count FROM users GROUP BY role, status";
$result = $conn->query($statsQuery);
$userStats = [];
while ($row = $result->fetch_assoc()) {
    $userStats[$row['role'] . '_' . $row['status']] = $row['count'];
}

$statusMessage = $_GET['message'] ?? '';
$statusType = $_GET['status'] ?? '';

closeDBConnection($conn);

// Build content
$content = '';

// Alert messages
if ($statusMessage) {
    $content .= alert(htmlspecialchars($statusMessage), 
                     ($statusType === 'success') ? 'success' : 'danger');
}

// Statistics cards
$totalClients = ($userStats['client_active'] ?? 0) + ($userStats['client_pending'] ?? 0) + ($userStats['client_inactive'] ?? 0);
$totalStaff = ($userStats['staff_active'] ?? 0) + ($userStats['staff_inactive'] ?? 0);
$activeUsers = ($userStats['client_active'] ?? 0) + ($userStats['staff_active'] ?? 0) + ($userStats['admin_active'] ?? 0);
$pendingUsers = $userStats['client_pending'] ?? 0;

$content .= '<div class="row mb-3">';
$content .= statCard('Total Clients', $totalClients, 'people', 'primary');
$content .= statCard('Staff Members', $totalStaff, 'person-badge', 'info');
$content .= statCard('Active Users', $activeUsers, 'person-check', 'success');
$content .= statCard('Pending Verification', $pendingUsers, 'clock-history', 'warning');
$content .= '</div>';

// Filters
$content .= '<div class="card mb-4"><div class="card-body">';
$content .= '<form method="GET" class="row g-3">';
$content .= '<div class="col-md-4">';
$content .= '<label class="form-label">Role</label>';
$content .= '<select name="role" class="form-select" onchange="this.form.submit()">';
$content .= '<option value="all" ' . ($roleFilter === 'all' ? 'selected' : '') . '>All Roles</option>';
$content .= '<option value="client" ' . ($roleFilter === 'client' ? 'selected' : '') . '>Clients</option>';
$content .= '<option value="staff" ' . ($roleFilter === 'staff' ? 'selected' : '') . '>Staff</option>';
$content .= '<option value="admin" ' . ($roleFilter === 'admin' ? 'selected' : '') . '>Admin</option>';
$content .= '</select></div>';
$content .= '<div class="col-md-4">';
$content .= '<label class="form-label">Status</label>';
$content .= '<select name="status" class="form-select" onchange="this.form.submit()">';
$content .= '<option value="all" ' . ($statusFilter === 'all' ? 'selected' : '') . '>All Status</option>';
$content .= '<option value="active" ' . ($statusFilter === 'active' ? 'selected' : '') . '>Active</option>';
$content .= '<option value="pending" ' . ($statusFilter === 'pending' ? 'selected' : '') . '>Pending</option>';
$content .= '<option value="inactive" ' . ($statusFilter === 'inactive' ? 'selected' : '') . '>Inactive</option>';
$content .= '</select></div>';
$content .= '</form></div></div>';

// Users table
$usersTableHtml = '<div class="table-responsive"><table class="table table-hover">';
$usersTableHtml .= '<thead><tr>';
$usersTableHtml .= '<th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Registered</th><th>Actions</th>';
$usersTableHtml .= '</tr></thead><tbody>';

if (empty($users)) {
    $usersTableHtml .= '<tr><td colspan="6" class="text-center">No users found</td></tr>';
} else {
    foreach ($users as $user) {
        $fullName = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $email = htmlspecialchars($user['email']);
        $roleBadge = badge(ucfirst($user['role']), $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'staff' ? 'info' : 'secondary'));
        $statusBadge = badge(ucfirst($user['status']), $user['status'] === 'active' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'secondary'));
        $registeredDate = date('M d, Y', strtotime($user['created_at']));
        
        $actions = '';
        if ($user['id'] != $userId) { // Don't allow actions on self
            if ($user['status'] === 'active') {
                $actions .= '<form method="POST" style="display: inline;">';
                $actions .= '<input type="hidden" name="action" value="deactivate">';
                $actions .= '<input type="hidden" name="user_id" value="' . $user['id'] . '">';
                $actions .= '<button type="submit" class="btn btn-sm btn-warning" title="Deactivate"><i class="bi bi-pause-circle"></i></button>';
                $actions .= '</form> ';
            } else {
                $actions .= '<form method="POST" style="display: inline;">';
                $actions .= '<input type="hidden" name="action" value="activate">';
                $actions .= '<input type="hidden" name="user_id" value="' . $user['id'] . '">';
                $actions .= '<button type="submit" class="btn btn-sm btn-success" title="Activate"><i class="bi bi-play-circle"></i></button>';
                $actions .= '</form> ';
            }
            
            if ($user['role'] === 'client') {
                $actions .= '<form method="POST" style="display: inline;">';
                $actions .= '<input type="hidden" name="action" value="make_staff">';
                $actions .= '<input type="hidden" name="user_id" value="' . $user['id'] . '">';
                $actions .= '<button type="submit" class="btn btn-sm btn-info" title="Promote to Staff"><i class="bi bi-arrow-up-circle"></i></button>';
                $actions .= '</form> ';
            } elseif ($user['role'] === 'staff') {
                $actions .= '<form method="POST" style="display: inline;">';
                $actions .= '<input type="hidden" name="action" value="make_client">';
                $actions .= '<input type="hidden" name="user_id" value="' . $user['id'] . '">';
                $actions .= '<button type="submit" class="btn btn-sm btn-secondary" title="Demote to Client"><i class="bi bi-arrow-down-circle"></i></button>';
                $actions .= '</form> ';
            }
            
            $actions .= '<button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewModal' . $user['id'] . '" title="View Details"><i class="bi bi-eye"></i></button>';
        } else {
            $actions = '<small class="text-muted">You</small>';
        }
        
        $usersTableHtml .= "<tr>";
        $usersTableHtml .= "<td>$fullName</td>";
        $usersTableHtml .= "<td>$email</td>";
        $usersTableHtml .= "<td>$roleBadge</td>";
        $usersTableHtml .= "<td>$statusBadge</td>";
        $usersTableHtml .= "<td>$registeredDate</td>";
        $usersTableHtml .= "<td>$actions</td>";
        $usersTableHtml .= "</tr>";
        
        // User details modal
        $stats = $userService->getUserStatistics($user['id']);
        $usersTableHtml .= '<div class="modal fade" id="viewModal' . $user['id'] . '" tabindex="-1">';
        $usersTableHtml .= '<div class="modal-dialog"><div class="modal-content">';
        $usersTableHtml .= '<div class="modal-header"><h5 class="modal-title">User Details</h5>';
        $usersTableHtml .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
        $usersTableHtml .= '<div class="modal-body">';
        $usersTableHtml .= '<p><strong>Name:</strong> ' . $fullName . '</p>';
        $usersTableHtml .= '<p><strong>Email:</strong> ' . $email . '</p>';
        $usersTableHtml .= '<p><strong>Phone:</strong> ' . ($user['phone'] ?: 'N/A') . '</p>';
        $usersTableHtml .= '<p><strong>Role:</strong> ' . $roleBadge . '</p>';
        $usersTableHtml .= '<p><strong>Status:</strong> ' . $statusBadge . '</p>';
        $usersTableHtml .= '<hr><h6>Activity Summary</h6>';
        $usersTableHtml .= '<p>Documents Requested: <strong>' . $stats['documents'] . '</strong></p>';
        $usersTableHtml .= '<p>Appointments Booked: <strong>' . $stats['bookings'] . '</strong></p>';
        $usersTableHtml .= '<p>Payments Made: <strong>' . $stats['payments'] . '</strong></p>';
        $usersTableHtml .= '<p>Total Paid: <strong>₱' . number_format($stats['total_paid'], 2) . '</strong></p>';
        $usersTableHtml .= '</div></div></div></div>';
    }
}

$usersTableHtml .= '</tbody></table></div>';

$content .= card('Users (' . count($users) . ' of ' . $totalCount . ')', $usersTableHtml);

// Pagination
if ($totalPages > 1) {
    $baseUrl = '?role=' . urlencode($roleFilter) . '&status=' . urlencode($statusFilter);
    $pagination = new Pagination($pageNum, $totalPages, $baseUrl);
    $content .= $pagination->render();
}

// Create layout and render
$layout = new AdminLayout('Manage Users', 'Manage Users', 'people');
$layout->setContent($content);

echo $layout->render();
?>
