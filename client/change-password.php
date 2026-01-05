<?php
/**
 * Client - Change Password
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/UI/Layouts/AdminLayout.php';

startSecureSession();

// Require login
if (!isLoggedIn()) {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$userId = getCurrentUserId();
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = getCurrentUserEmail();
$userRole = $_SESSION['user_role'] ?? 'client';

// Initialize variables
$conn = getDBConnection();
$errorMsg = '';
$successMsg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($currentPassword)) {
        $errorMsg = 'Please enter your current password.';
    } elseif (empty($newPassword)) {
        $errorMsg = 'Please enter a new password.';
    } elseif (strlen($newPassword) < 8) {
        $errorMsg = 'New password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMsg = 'New passwords do not match.';
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!password_verify($currentPassword, $user['password'])) {
            $errorMsg = 'Current password is incorrect.';
        } else {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $updateStmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $updateStmt->bind_param("si", $hashedPassword, $userId);
            
            if ($updateStmt->execute()) {
                $successMsg = 'Password changed successfully!';
                
                // Log activity
                $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, 'password_change', 'User changed their password', NOW())");
                $logStmt->bind_param("i", $userId);
                $logStmt->execute();
                $logStmt->close();
            } else {
                $errorMsg = 'Failed to update password. Please try again.';
            }
            $updateStmt->close();
        }
    }
}

// Get user initials
$nameParts = explode(' ', $userName);
$userInitials = strtoupper(
    substr($nameParts[0], 0, 1) . 
    (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : substr($nameParts[0], 1, 1))
);

// Use AdminLayout for admin/staff, otherwise use regular HTML
$isAdminUser = in_array($userRole, ['admin', 'staff']);

if ($isAdminUser) {
    // Admin layout content
    $content = '';
    
    if ($errorMsg) {
        $content .= '<div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-triangle-fill"></i> ' . htmlspecialchars($errorMsg) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    if ($successMsg) {
        $content .= '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="bi bi-check-circle-fill"></i> ' . htmlspecialchars($successMsg) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    
    $content .= <<<HTML
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <i class="bi bi-shield-lock" style="font-size: 48px; color: #3498db;"></i>
                    <h5 class="mt-3">Update Your Password</h5>
                    <p class="text-muted">Choose a strong password to keep your account secure</p>
                </div>

                <form method="POST" id="changePasswordForm">
                    <!-- Current Password -->
                    <div class="mb-4">
                        <label for="current_password" class="form-label">
                            <i class="bi bi-lock"></i> Current Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                <i class="bi bi-eye" id="current_password_icon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- New Password -->
                    <div class="mb-3">
                        <label for="new_password" class="form-label">
                            <i class="bi bi-key"></i> New Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                <i class="bi bi-eye" id="new_password_icon"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <ul class="password-requirements" id="requirements">
                            <li id="req-length">
                                <i class="bi bi-circle"></i> At least 8 characters
                            </li>
                            <li id="req-uppercase">
                                <i class="bi bi-circle"></i> Contains uppercase letter
                            </li>
                            <li id="req-lowercase">
                                <i class="bi bi-circle"></i> Contains lowercase letter
                            </li>
                            <li id="req-number">
                                <i class="bi bi-circle"></i> Contains number
                            </li>
                        </ul>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">
                            <i class="bi bi-check-circle"></i> Confirm New Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                <i class="bi bi-eye" id="confirm_password_icon"></i>
                            </button>
                        </div>
                        <small class="text-muted" id="match-message"></small>
                    </div>

                    <!-- Buttons -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-shield-check"></i> Change Password
                        </button>
                        <a href="/documentSystem/admin/dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security Tips -->
        <div class="card mt-4 bg-light">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-lightbulb"></i> Password Security Tips</h6>
                <ul class="mb-0" style="font-size: 14px;">
                    <li>Use a unique password for this account</li>
                    <li>Avoid using personal information</li>
                    <li>Mix uppercase, lowercase, numbers, and symbols</li>
                    <li>Change your password regularly</li>
                    <li>Never share your password with anyone</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
    .password-strength {
        height: 5px;
        margin-top: 5px;
        border-radius: 3px;
        background: #e0e0e0;
        overflow: hidden;
    }
    .password-strength-bar {
        height: 100%;
        width: 0%;
        transition: all 0.3s;
    }
    .strength-weak { background: #dc3545; }
    .strength-medium { background: #ffc107; }
    .strength-strong { background: #28a745; }
    .password-requirements {
        font-size: 12px;
        color: #666;
        margin-top: 10px;
        padding-left: 20px;
    }
    .password-requirements li {
        margin: 5px 0;
        list-style: none;
    }
    .password-requirements .met {
        color: #28a745;
    }
    .password-requirements .met i {
        color: #28a745;
    }
</style>

<script>
    // Toggle password visibility
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = document.getElementById(fieldId + '_icon');
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }

    // Password strength checker
    const newPasswordField = document.getElementById('new_password');
    const strengthBar = document.getElementById('strengthBar');
    const confirmPasswordField = document.getElementById('confirm_password');
    const matchMessage = document.getElementById('match-message');

    newPasswordField.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        // Check requirements
        const hasLength = password.length >= 8;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        
        // Update requirement indicators
        updateRequirement('req-length', hasLength);
        updateRequirement('req-uppercase', hasUpper);
        updateRequirement('req-lowercase', hasLower);
        updateRequirement('req-number', hasNumber);
        
        // Calculate strength
        if (hasLength) strength += 25;
        if (hasUpper) strength += 25;
        if (hasLower) strength += 25;
        if (hasNumber) strength += 25;
        
        // Update strength bar
        strengthBar.style.width = strength + '%';
        strengthBar.className = 'password-strength-bar';
        
        if (strength <= 25) {
            strengthBar.classList.add('strength-weak');
        } else if (strength <= 75) {
            strengthBar.classList.add('strength-medium');
        } else {
            strengthBar.classList.add('strength-strong');
        }
        
        // Check password match
        checkPasswordMatch();
    });

    confirmPasswordField.addEventListener('input', checkPasswordMatch);

    function updateRequirement(reqId, met) {
        const req = document.getElementById(reqId);
        const icon = req.querySelector('i');
        
        if (met) {
            req.classList.add('met');
            icon.classList.remove('bi-circle');
            icon.classList.add('bi-check-circle-fill');
        } else {
            req.classList.remove('met');
            icon.classList.remove('bi-check-circle-fill');
            icon.classList.add('bi-circle');
        }
    }

    function checkPasswordMatch() {
        const newPass = newPasswordField.value;
        const confirmPass = confirmPasswordField.value;
        
        if (confirmPass.length === 0) {
            matchMessage.textContent = '';
            matchMessage.className = 'text-muted';
            return;
        }
        
        if (newPass === confirmPass) {
            matchMessage.textContent = '✓ Passwords match';
            matchMessage.className = 'text-success';
        } else {
            matchMessage.textContent = '✗ Passwords do not match';
            matchMessage.className = 'text-danger';
        }
    }

    // Form validation
    document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
        const newPass = newPasswordField.value;
        const confirmPass = confirmPasswordField.value;
        
        if (newPass !== confirmPass) {
            e.preventDefault();
            alert('Passwords do not match!');
            return false;
        }
        
        if (newPass.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters long!');
            return false;
        }
    });
</script>
HTML;

    $layout = new AdminLayout('Change Password', 'Change Password', 'key-fill');
    $layout->setContent($content);
    echo $layout->render();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Change Password - Parish Church System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="/documentSystem/assets/css/common.css" rel="stylesheet">
  <link href="/documentSystem/assets/css/forms.css" rel="stylesheet">
  <style>
    .password-strength {
      height: 5px;
      margin-top: 5px;
      border-radius: 3px;
      background: #e0e0e0;
      overflow: hidden;
    }
    .password-strength-bar {
      height: 100%;
      width: 0%;
      transition: all 0.3s;
    }
    .strength-weak { background: #dc3545; }
    .strength-medium { background: #ffc107; }
    .strength-strong { background: #28a745; }
    .password-requirements {
      font-size: 12px;
      color: #666;
      margin-top: 10px;
    }
    .password-requirements li {
      margin: 5px 0;
    }
    .password-requirements .met {
      color: #28a745;
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="sidebar-header">
      <div class="logo-circles">
        <div class="logo-circle">PC</div>
      </div>
      <div class="system-title">
        Parish Ease: An Interactive<br>
        Document Request and<br>
        Appointment System
      </div>
    </div>

    <div class="user-profile-card">
      <div class="user-avatar"><?php echo $userInitials; ?></div>
      <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
      <div class="user-email"><?php echo htmlspecialchars($userEmail); ?></div>
    </div>

    <ul class="nav-menu">
      <li class="nav-item">
        <a href="/documentSystem/client/dashboard.php" class="nav-link">
          <i class="bi bi-house-door-fill"></i>
          <span>Home</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/client/view-documents.php" class="nav-link">
          <i class="bi bi-file-earmark-check"></i>
          <span>View Documents</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/client/view-appointments.php" class="nav-link">
          <i class="bi bi-calendar-check"></i>
          <span>View Appointments</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/client/new-appointment.php" class="nav-link">
          <i class="bi bi-calendar-plus"></i>
          <span>New Appointment</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/client/request-documents.php" class="nav-link">
          <i class="bi bi-file-earmark-text"></i>
          <span>Request Document</span>
        </a>
      </li>
      
      <div class="nav-separator"></div>
      
      <li class="nav-item">
        <a href="/documentSystem/client/change-password.php" class="nav-link active">
          <i class="bi bi-key-fill"></i>
          <span>Change Password</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/api/logout.php" class="nav-link">
          <i class="bi bi-box-arrow-right"></i>
          <span>Log Out</span>
        </a>
      </li>
    </ul>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="content-area">
      <h1 class="page-title">CHANGE PASSWORD</h1>
      <div class="title-underline"></div>

      <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($successMsg); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($errorMsg); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="row">
        <div class="col-lg-6 mx-auto">
          <div class="card shadow-sm">
            <div class="card-body p-4">
              <div class="text-center mb-4">
                <i class="bi bi-shield-lock" style="font-size: 48px; color: #3498db;"></i>
                <h5 class="mt-3">Update Your Password</h5>
                <p class="text-muted">Choose a strong password to keep your account secure</p>
              </div>

              <form method="POST" id="changePasswordForm">
                <!-- Current Password -->
                <div class="mb-4">
                  <label for="current_password" class="form-label">
                    <i class="bi bi-lock"></i> Current Password <span class="text-danger">*</span>
                  </label>
                  <div class="input-group">
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                      <i class="bi bi-eye" id="current_password_icon"></i>
                    </button>
                  </div>
                </div>

                <!-- New Password -->
                <div class="mb-3">
                  <label for="new_password" class="form-label">
                    <i class="bi bi-key"></i> New Password <span class="text-danger">*</span>
                  </label>
                  <div class="input-group">
                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                      <i class="bi bi-eye" id="new_password_icon"></i>
                    </button>
                  </div>
                  <div class="password-strength">
                    <div class="password-strength-bar" id="strengthBar"></div>
                  </div>
                  <ul class="password-requirements" id="requirements">
                    <li id="req-length">
                      <i class="bi bi-circle"></i> At least 8 characters
                    </li>
                    <li id="req-uppercase">
                      <i class="bi bi-circle"></i> Contains uppercase letter
                    </li>
                    <li id="req-lowercase">
                      <i class="bi bi-circle"></i> Contains lowercase letter
                    </li>
                    <li id="req-number">
                      <i class="bi bi-circle"></i> Contains number
                    </li>
                  </ul>
                </div>

                <!-- Confirm Password -->
                <div class="mb-4">
                  <label for="confirm_password" class="form-label">
                    <i class="bi bi-check-circle"></i> Confirm New Password <span class="text-danger">*</span>
                  </label>
                  <div class="input-group">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                      <i class="bi bi-eye" id="confirm_password_icon"></i>
                    </button>
                  </div>
                  <small class="text-muted" id="match-message"></small>
                </div>

                <!-- Buttons -->
                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-shield-check"></i> Change Password
                  </button>
                  <a href="/documentSystem/client/dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                  </a>
                </div>
              </form>
            </div>
          </div>

          <!-- Security Tips -->
          <div class="card mt-4 bg-light">
            <div class="card-body">
              <h6 class="card-title"><i class="bi bi-lightbulb"></i> Password Security Tips</h6>
              <ul class="mb-0" style="font-size: 14px;">
                <li>Use a unique password for this account</li>
                <li>Avoid using personal information</li>
                <li>Mix uppercase, lowercase, numbers, and symbols</li>
                <li>Change your password regularly</li>
                <li>Never share your password with anyone</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
      <div class="footer-content">
        <div class="footer-contact">
          <strong>FOR INQUIRIES:</strong><br>
          HOTLINE: 0999 MAYNAY<br>
          EMAIL: maequinas@gmail.com
        </div>
      </div>
      <div class="footer-bottom">
        Parish Church © <?php echo date('Y'); ?>
      </div>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Toggle password visibility
    function togglePassword(fieldId) {
      const field = document.getElementById(fieldId);
      const icon = document.getElementById(fieldId + '_icon');
      
      if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
      } else {
        field.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
      }
    }

    // Password strength checker
    const newPasswordField = document.getElementById('new_password');
    const strengthBar = document.getElementById('strengthBar');
    const confirmPasswordField = document.getElementById('confirm_password');
    const matchMessage = document.getElementById('match-message');

    newPasswordField.addEventListener('input', function() {
      const password = this.value;
      let strength = 0;
      
      // Check requirements
      const hasLength = password.length >= 8;
      const hasUpper = /[A-Z]/.test(password);
      const hasLower = /[a-z]/.test(password);
      const hasNumber = /[0-9]/.test(password);
      
      // Update requirement indicators
      updateRequirement('req-length', hasLength);
      updateRequirement('req-uppercase', hasUpper);
      updateRequirement('req-lowercase', hasLower);
      updateRequirement('req-number', hasNumber);
      
      // Calculate strength
      if (hasLength) strength += 25;
      if (hasUpper) strength += 25;
      if (hasLower) strength += 25;
      if (hasNumber) strength += 25;
      
      // Update strength bar
      strengthBar.style.width = strength + '%';
      strengthBar.className = 'password-strength-bar';
      
      if (strength <= 25) {
        strengthBar.classList.add('strength-weak');
      } else if (strength <= 75) {
        strengthBar.classList.add('strength-medium');
      } else {
        strengthBar.classList.add('strength-strong');
      }
      
      // Check password match
      checkPasswordMatch();
    });

    confirmPasswordField.addEventListener('input', checkPasswordMatch);

    function updateRequirement(reqId, met) {
      const req = document.getElementById(reqId);
      const icon = req.querySelector('i');
      
      if (met) {
        req.classList.add('met');
        icon.classList.remove('bi-circle');
        icon.classList.add('bi-check-circle-fill');
      } else {
        req.classList.remove('met');
        icon.classList.remove('bi-check-circle-fill');
        icon.classList.add('bi-circle');
      }
    }

    function checkPasswordMatch() {
      const newPass = newPasswordField.value;
      const confirmPass = confirmPasswordField.value;
      
      if (confirmPass.length === 0) {
        matchMessage.textContent = '';
        matchMessage.className = 'text-muted';
        return;
      }
      
      if (newPass === confirmPass) {
        matchMessage.textContent = '✓ Passwords match';
        matchMessage.className = 'text-success';
      } else {
        matchMessage.textContent = '✗ Passwords do not match';
        matchMessage.className = 'text-danger';
      }
    }

    // Form validation
    document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
      const newPass = newPasswordField.value;
      const confirmPass = confirmPasswordField.value;
      
      if (newPass !== confirmPass) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
      }
      
      if (newPass.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long!');
        return false;
      }
    });
  </script>
</body>
</html>
<?php
closeDBConnection($conn);
?>
