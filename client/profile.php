<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/client_nav_helper.php';

startSecureSession();
if ($_SESSION['role'] !== 'client') {
    header('Location: ../admin/dashboard.php');
    exit;
}

require_once '../config/database.php';
require_once '../src/UI/Layouts/ClientLayout.php';

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        $errors = [];
        
        if (empty($firstName)) $errors[] = 'First name is required.';
        if (empty($lastName)) $errors[] = 'Last name is required.';
        
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $firstName, $lastName, $phone, $address, $userId);
            
            if ($stmt->execute()) {
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;
                $_SESSION['success_message'] = 'Profile updated successfully!';
                header('Location: profile.php');
                exit;
            } else {
                $updateError = 'Failed to update profile.';
            }
        }
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        $errors = [];
        
        if (empty($currentPassword)) $errors[] = 'Current password is required.';
        if (empty($newPassword)) $errors[] = 'New password is required.';
        if (strlen($newPassword) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($newPassword !== $confirmPassword) $errors[] = 'Passwords do not match.';
        
        if (empty($errors)) {
            // Get current password hash
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            // Verify current password (support both bcrypt and SHA-256)
            $isValid = false;
            if (password_verify($currentPassword, $result['password'])) {
                $isValid = true;
            } elseif (hash('sha256', $currentPassword) === $result['password']) {
                $isValid = true;
            }
            
            if ($isValid) {
                // Hash new password with bcrypt
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $newHash, $userId);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Password changed successfully!';
                    header('Location: profile.php');
                    exit;
                } else {
                    $passwordError = 'Failed to change password.';
                }
            } else {
                $passwordError = 'Current password is incorrect.';
            }
        }
    }
}

// Get user details
$stmt = $conn->prepare("
    SELECT first_name, last_name, email, phone, address, created_at,
           (SELECT COUNT(*) FROM document_requests WHERE user_id = ?) as total_documents,
           (SELECT COUNT(*) FROM bookings WHERE user_id = ?) as total_bookings,
           (SELECT COUNT(*) FROM payments WHERE user_id = ? AND status = 'verified') as total_payments,
           (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE user_id = ? AND status = 'verified') as total_paid
    FROM users 
    WHERE id = ?
");
$stmt->bind_param("iiiii", $userId, $userId, $userId, $userId, $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get recent activity
$recentActivity = [];
$activityQuery = "
    (SELECT 'document' as type, reference_number, status, created_at, document_type as name 
     FROM document_requests dr 
     JOIN document_types dt ON dr.document_type_id = dt.id 
     WHERE dr.user_id = ?)
    UNION ALL
    (SELECT 'booking' as type, reference_number, status, created_at, booking_type as name 
     FROM bookings b 
     JOIN booking_types bt ON b.booking_type_id = bt.id 
     WHERE b.user_id = ?)
    ORDER BY created_at DESC 
    LIMIT 10
";
$stmt = $conn->prepare($activityQuery);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recentActivity[] = $row;
}

// Build content
ob_start();
?>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> <?= $_SESSION['success_message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success_message']); endif; ?>

<?php if (isset($updateError)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle"></i> <?= $updateError ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($passwordError)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle"></i> <?= $passwordError ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Profile Summary -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="display-1 text-primary mb-3">
                    <i class="bi bi-person-circle"></i>
                </div>
                <h4><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h4>
                <p class="text-muted"><?= htmlspecialchars($user['email']) ?></p>
                <hr>
                <div class="text-start">
                    <p class="mb-2">
                        <i class="bi bi-telephone"></i> 
                        <strong>Phone:</strong> <?= htmlspecialchars($user['phone'] ?: 'Not provided') ?>
                    </p>
                    <p class="mb-2">
                        <i class="bi bi-geo-alt"></i> 
                        <strong>Address:</strong> <?= htmlspecialchars($user['address'] ?: 'Not provided') ?>
                    </p>
                    <p class="mb-0">
                        <i class="bi bi-calendar"></i> 
                        <strong>Member since:</strong> <?= date('M Y', strtotime($user['created_at'])) ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-graph-up"></i> Account Statistics</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Documents</span>
                        <strong><?= $user['total_documents'] ?></strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Appointments</span>
                        <strong><?= $user['total_bookings'] ?></strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Payments</span>
                        <strong><?= $user['total_payments'] ?></strong>
                    </div>
                </div>
                <div class="pt-2 border-top">
                    <div class="d-flex justify-content-between">
                        <span>Total Paid</span>
                        <strong class="text-success">₱<?= number_format($user['total_paid'], 2) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profile Forms -->
    <div class="col-lg-8">
        <!-- Edit Profile -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-gear"></i> Edit Profile</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?= htmlspecialchars($user['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?= htmlspecialchars($user['last_name']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" 
                               value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        <div class="form-text">Email cannot be changed. Contact admin if needed.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-key"></i> Change Password</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" 
                               required minlength="8">
                        <div class="form-text">Must be at least 8 characters</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php if (count($recentActivity) > 0): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentActivity as $activity): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <i class="bi bi-<?= $activity['type'] === 'document' ? 'file-earmark-text' : 'calendar-check' ?>"></i>
                                <strong><?= htmlspecialchars($activity['name']) ?></strong>
                                <br>
                                <small class="text-muted">
                                    Ref: <?= htmlspecialchars($activity['reference_number']) ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <?php
                                $statusClass = ['pending' => 'warning', 'approved' => 'success', 'processing' => 'info', 
                                               'ready' => 'success', 'completed' => 'secondary', 'rejected' => 'danger', 
                                               'cancelled' => 'dark'];
                                $class = $statusClass[$activity['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $class ?>"><?= htmlspecialchars($activity['status']) ?></span>
                                <br>
                                <small class="text-muted"><?= date('M d, Y', strtotime($activity['created_at'])) ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No recent activity</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Render layout
$layout = new ClientLayout('My Profile', 'My Profile', 'profile');
$layout->setContent($content);
echo $layout->render();

closeDBConnection($conn);
