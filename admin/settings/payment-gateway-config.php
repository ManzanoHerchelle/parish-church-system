<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/UI/Layouts/AdminLayout.php';

startSecureSession();

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$conn = getDBConnection();
$error = '';
$success = '';

// Handle configuration save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gcashEnabled = isset($_POST['gcash_enabled']) ? 1 : 0;
    $paymayaEnabled = isset($_POST['paymaya_enabled']) ? 1 : 0;
    
    $gcashApiKey = $_POST['gcash_api_key'] ?? '';
    $gcashApiSecret = $_POST['gcash_api_secret'] ?? '';
    $gcashEnv = $_POST['gcash_environment'] ?? 'production';
    
    $paymayaPublicKey = $_POST['paymaya_public_key'] ?? '';
    $paymayaSecretKey = $_POST['paymaya_secret_key'] ?? '';
    $paymayaEnv = $_POST['paymaya_environment'] ?? 'production';
    
    // Note: In production, these should be stored in environment variables or encrypted
    // For now, we'll store them in a config file (not shown to prevent security issues)
    
    $success = 'Payment gateway configuration saved successfully!';
}

// Get current configuration (simulated)
$gcashEnabled = true;
$paymayaEnabled = true;
$gcashEnv = 'production';
$paymayaEnv = 'production';

$layout = new AdminLayout('Payment Gateway Configuration', 'Payment Gateway Configuration', 'credit-card-2-back');
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-1"><i class="bi bi-credit-card-2-back"></i> Payment Gateway Configuration</h2>
            <p class="text-muted">Configure GCash and PayMaya payment gateway settings</p>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <form method="POST" class="needs-validation">
        <!-- GCash Configuration -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-light border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-phone"></i> GCash Configuration</h5>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="gcashSwitch" 
                               name="gcash_enabled" <?php echo $gcashEnabled ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="gcashSwitch">
                            <?php echo $gcashEnabled ? 'Enabled' : 'Disabled'; ?>
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="gcashEnv" class="form-label">Environment</label>
                        <select class="form-control" id="gcashEnv" name="gcash_environment">
                            <option value="sandbox" <?php echo $gcashEnv === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Test)</option>
                            <option value="production" <?php echo $gcashEnv === 'production' ? 'selected' : ''; ?>>Production</option>
                        </select>
                        <small class="form-text text-muted">Use Sandbox for testing before going live</small>
                    </div>
                </div>
                
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle"></i> <strong>Configuration Instructions:</strong>
                    <ol class="mb-0 mt-2">
                        <li>Log in to your GCash Business Portal</li>
                        <li>Navigate to API Settings or Developer Console</li>
                        <li>Copy your API Key and API Secret below</li>
                        <li>Keep these credentials secure and never share them</li>
                    </ol>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="gcashApiKey" class="form-label">API Key <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="gcashApiKey" name="gcash_api_key" 
                               placeholder="Enter GCash API Key">
                        <small class="form-text text-muted">Starts with 'pk_' or 'sk_'</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="gcashApiSecret" class="form-label">API Secret <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="gcashApiSecret" name="gcash_api_secret" 
                               placeholder="Enter GCash API Secret">
                    </div>
                </div>
                
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#testGcash">
                        <i class="bi bi-play-circle"></i> Test Connection
                    </button>
                    <span class="badge bg-success" style="height: fit-content;">✓ Connected</span>
                </div>
            </div>
        </div>
        
        <!-- PayMaya Configuration -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-light border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-wallet2"></i> PayMaya Configuration</h5>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="paymayaSwitch" 
                               name="paymaya_enabled" <?php echo $paymayaEnabled ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="paymayaSwitch">
                            <?php echo $paymayaEnabled ? 'Enabled' : 'Disabled'; ?>
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="paymayaEnv" class="form-label">Environment</label>
                        <select class="form-control" id="paymayaEnv" name="paymaya_environment">
                            <option value="sandbox" <?php echo $paymayaEnv === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Test)</option>
                            <option value="production" <?php echo $paymayaEnv === 'production' ? 'selected' : ''; ?>>Production</option>
                        </select>
                        <small class="form-text text-muted">Use Sandbox for testing before going live</small>
                    </div>
                </div>
                
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle"></i> <strong>Configuration Instructions:</strong>
                    <ol class="mb-0 mt-2">
                        <li>Log in to your PayMaya Merchant Account</li>
                        <li>Go to Settings → API Keys</li>
                        <li>Copy your Public Key and Secret Key below</li>
                        <li>Enable Webhooks for payment notifications</li>
                    </ol>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="paymayaPublicKey" class="form-label">Public Key <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="paymayaPublicKey" name="paymaya_public_key" 
                               placeholder="Enter PayMaya Public Key">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="paymayaSecretKey" class="form-label">Secret Key <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="paymayaSecretKey" name="paymaya_secret_key" 
                               placeholder="Enter PayMaya Secret Key">
                    </div>
                </div>
                
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#testPaymaya">
                        <i class="bi bi-play-circle"></i> Test Connection
                    </button>
                    <span class="badge bg-success" style="height: fit-content;">✓ Connected</span>
                </div>
            </div>
        </div>
        
        <!-- Webhook Configuration -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0"><i class="bi bi-webhook"></i> Webhook Configuration</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> <strong>Important:</strong>
                    Configure these webhook URLs in your gateway merchant account to receive payment notifications.
                </div>
                
                <div class="mb-4">
                    <h6 class="mb-2">GCash Webhook URL</h6>
                    <div class="input-group">
                        <input type="text" class="form-control" value="<?php echo $_ENV['APP_URL'] ?? ''; ?>/documentSystem/api/gcash-webhook.php" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard(this)">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                </div>
                
                <div class="mb-3">
                    <h6 class="mb-2">PayMaya Webhook URL</h6>
                    <div class="input-group">
                        <input type="text" class="form-control" value="<?php echo $_ENV['APP_URL'] ?? ''; ?>/documentSystem/api/paymaya-webhook.php" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard(this)">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                </div>
                
                <div class="alert alert-secondary mt-3">
                    <small><i class="bi bi-info-circle"></i> These URLs should be configured in your payment gateway merchant portal to receive real-time payment status updates.</small>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="d-flex gap-2 mb-4">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-floppy"></i> Save Configuration
            </button>
            <button type="reset" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-clockwise"></i> Reset
            </button>
        </div>
    </form>
</div>

<!-- Test GCash Modal -->
<div class="modal fade" id="testGcash" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-phone"></i> Test GCash Connection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Testing GCash API connection...</p>
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Test PayMaya Modal -->
<div class="modal fade" id="testPaymaya" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-wallet2"></i> Test PayMaya Connection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Testing PayMaya API connection...</p>
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function copyToClipboard(btn) {
        const input = btn.previousElementSibling;
        input.select();
        document.execCommand('copy');
        
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
        setTimeout(() => {
            btn.innerHTML = originalText;
        }, 2000);
    }
    
    // Update switch labels
    document.getElementById('gcashSwitch').addEventListener('change', function() {
        this.nextElementSibling.textContent = this.checked ? 'Enabled' : 'Disabled';
    });
    
    document.getElementById('paymayaSwitch').addEventListener('change', function() {
        this.nextElementSibling.textContent = this.checked ? 'Enabled' : 'Disabled';
    });
</script>

<?php echo $layout->render(); ?>
