<?php
/**
 * Admin: SLA Settings Configuration
 * Customize SLA thresholds and notification settings
 */

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/database.php';

startSecureSession();

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$conn = getDBConnection();
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docSLA = intval($_POST['doc_sla_hours']);
    $docWarning = intval($_POST['doc_warning_hours']);
    $bookingSLA = intval($_POST['booking_sla_hours']);
    $bookingWarning = intval($_POST['booking_warning_hours']);
    $paymentSLA = intval($_POST['payment_sla_hours']);
    $paymentWarning = intval($_POST['payment_warning_hours']);
    $alertEnabled = isset($_POST['alert_enabled']) ? 1 : 0;
    $alertFrequency = intval($_POST['alert_frequency']);
    
    // Validate inputs
    if ($docSLA <= 0 || $bookingSLA <= 0 || $paymentSLA <= 0) {
        $error = 'SLA hours must be greater than 0';
    } elseif ($docWarning >= $docSLA || $bookingWarning >= $bookingSLA || $paymentWarning >= $paymentSLA) {
        $error = 'Warning threshold must be less than SLA target';
    } else {
        // Save settings
        $settings = [
            'sla_doc_hours' => $docSLA,
            'sla_doc_warning_hours' => $docWarning,
            'sla_booking_hours' => $bookingSLA,
            'sla_booking_warning_hours' => $bookingWarning,
            'sla_payment_hours' => $paymentSLA,
            'sla_payment_warning_hours' => $paymentWarning,
            'sla_alert_enabled' => $alertEnabled,
            'sla_alert_frequency' => $alertFrequency
        ];
        
        foreach ($settings as $key => $value) {
            $query = "INSERT INTO system_settings (setting_key, setting_value, setting_type, description) 
                      VALUES (?, ?, 'integer', 'SLA Configuration') 
                      ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('sss', $key, $value, $value);
            $stmt->execute();
        }
        
        $success = 'SLA settings saved successfully!';
    }
}

// Load current settings
function getSetting($conn, $key, $default) {
    $query = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

$docSLA = getSetting($conn, 'sla_doc_hours', 48);
$docWarning = getSetting($conn, 'sla_doc_warning_hours', 36);
$bookingSLA = getSetting($conn, 'sla_booking_hours', 24);
$bookingWarning = getSetting($conn, 'sla_booking_warning_hours', 18);
$paymentSLA = getSetting($conn, 'sla_payment_hours', 24);
$paymentWarning = getSetting($conn, 'sla_payment_warning_hours', 18);
$alertEnabled = getSetting($conn, 'sla_alert_enabled', 1);
$alertFrequency = getSetting($conn, 'sla_alert_frequency', 15);

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLA Settings - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; }
        .settings-card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .setting-group {
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #f9fafb;
        }
        .info-box {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="mb-4">
            <a href="/documentSystem/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <h1 class="mb-4"><i class="bi bi-gear"></i> SLA Settings</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <!-- Document SLA Settings -->
            <div class="settings-card">
                <h4 class="mb-3"><i class="bi bi-file-earmark"></i> Document Requests SLA</h4>
                <div class="setting-group">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SLA Target (hours)</label>
                            <input type="number" name="doc_sla_hours" class="form-control" value="<?php echo $docSLA; ?>" min="1" required>
                            <small class="text-muted">Maximum time to process a document request</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Warning Threshold (hours)</label>
                            <input type="number" name="doc_warning_hours" class="form-control" value="<?php echo $docWarning; ?>" min="1" required>
                            <small class="text-muted">Alert when approaching SLA deadline</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Booking SLA Settings -->
            <div class="settings-card">
                <h4 class="mb-3"><i class="bi bi-calendar"></i> Bookings SLA</h4>
                <div class="setting-group">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SLA Target (hours)</label>
                            <input type="number" name="booking_sla_hours" class="form-control" value="<?php echo $bookingSLA; ?>" min="1" required>
                            <small class="text-muted">Maximum time to confirm/process a booking</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Warning Threshold (hours)</label>
                            <input type="number" name="booking_warning_hours" class="form-control" value="<?php echo $bookingWarning; ?>" min="1" required>
                            <small class="text-muted">Alert when approaching SLA deadline</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment SLA Settings -->
            <div class="settings-card">
                <h4 class="mb-3"><i class="bi bi-wallet2"></i> Payments SLA</h4>
                <div class="setting-group">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SLA Target (hours)</label>
                            <input type="number" name="payment_sla_hours" class="form-control" value="<?php echo $paymentSLA; ?>" min="1" required>
                            <small class="text-muted">Maximum time to verify a payment</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Warning Threshold (hours)</label>
                            <input type="number" name="payment_warning_hours" class="form-control" value="<?php echo $paymentWarning; ?>" min="1" required>
                            <small class="text-muted">Alert when approaching SLA deadline</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Alert Settings -->
            <div class="settings-card">
                <h4 class="mb-3"><i class="bi bi-bell"></i> Alert Configuration</h4>
                <div class="setting-group">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="alert_enabled" id="alertEnabled" 
                               <?php echo $alertEnabled ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="alertEnabled">
                            Enable Email Alerts
                        </label>
                        <div><small class="text-muted">Send email notifications to staff when items exceed thresholds</small></div>
                    </div>
                    
                    <div class="mt-3">
                        <label class="form-label fw-bold">Alert Check Frequency (minutes)</label>
                        <select name="alert_frequency" class="form-select">
                            <option value="5" <?php echo $alertFrequency == 5 ? 'selected' : ''; ?>>Every 5 minutes</option>
                            <option value="10" <?php echo $alertFrequency == 10 ? 'selected' : ''; ?>>Every 10 minutes</option>
                            <option value="15" <?php echo $alertFrequency == 15 ? 'selected' : ''; ?>>Every 15 minutes</option>
                            <option value="30" <?php echo $alertFrequency == 30 ? 'selected' : ''; ?>>Every 30 minutes</option>
                            <option value="60" <?php echo $alertFrequency == 60 ? 'selected' : ''; ?>>Every hour</option>
                        </select>
                        <small class="text-muted">How often to check for SLA breaches and send alerts</small>
                    </div>
                </div>
            </div>
            
            <div class="info-box">
                <h6><i class="bi bi-info-circle"></i> How SLA Alerts Work</h6>
                <ul class="mb-0">
                    <li><strong>Warning Threshold:</strong> Staff receive an alert when an item reaches this threshold</li>
                    <li><strong>Critical/SLA Target:</strong> Item is marked as critical when it exceeds this threshold</li>
                    <li><strong>Alerts:</strong> Sent via email to all admin and staff members</li>
                    <li><strong>Dashboard:</strong> Real-time updates every 30 seconds, auto-refresh every 2 minutes</li>
                </ul>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> Save SLA Settings
                </button>
                <a href="/documentSystem/staff/sla-dashboard.php" class="btn btn-outline-primary btn-lg">
                    <i class="bi bi-clock-history"></i> View SLA Dashboard
                </a>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
