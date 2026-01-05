<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Services/PaymentService.php';
require_once __DIR__ . '/../src/Services/PaymentGatewayService.php';

use Services\PaymentService;
use Services\PaymentGatewayService;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$conn = getDBConnection();
$paymentService = new PaymentService();
$gatewayService = new PaymentGatewayService();
$error = '';
$success = '';

// Get payment details
$paymentId = $_GET['payment_id'] ?? null;
if (!$paymentId) {
    header('Location: /documentSystem/client/my-documents.php');
    exit;
}

// Fetch payment details
$query = "SELECT p.*, 
          CASE 
            WHEN p.reference_type = 'document_request' THEN dr.document_title
            WHEN p.reference_type = 'booking' THEN b.type
          END as item_name
          FROM payments p
          LEFT JOIN document_requests dr ON p.reference_type = 'document_request' AND p.reference_id = dr.id
          LEFT JOIN bookings b ON p.reference_type = 'booking' AND p.reference_id = b.id
          WHERE p.id = ? AND p.user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $paymentId, $_SESSION['user_id']);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    header('Location: /documentSystem/client/my-documents.php');
    exit;
}

// Handle payment method selection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'] ?? '';
    
    if (!in_array($paymentMethod, ['gcash', 'paymaya'])) {
        $error = 'Invalid payment method selected.';
    } else {
        // Initiate payment with gateway
        if ($paymentMethod === 'gcash') {
            $result = $gatewayService->initiateGCashPayment(
                $paymentId,
                $payment['amount'],
                $payment['item_name'] ?? 'Document Request Payment',
                $_SESSION['email'],
                $_SESSION['full_name']
            );
        } else {
            $result = $gatewayService->initiatePayMayaPayment(
                $paymentId,
                $payment['amount'],
                $payment['item_name'] ?? 'Document Request Payment',
                $_SESSION['email'],
                $_SESSION['full_name']
            );
        }
        
        if ($result['success'] && isset($result['checkout_url'])) {
            // Redirect to payment gateway
            header('Location: ' . $result['checkout_url']);
            exit;
        } else {
            $error = $result['error'] ?? 'Payment initiation failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout - Document Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        
        .checkout-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
        }
        
        .checkout-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        
        .checkout-header h1 {
            font-size: 28px;
            margin: 0;
            font-weight: 600;
        }
        
        .checkout-body {
            padding: 40px;
        }
        
        .order-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .summary-row.total {
            font-weight: 600;
            font-size: 18px;
            color: #667eea;
        }
        
        .payment-method-selector {
            margin: 30px 0;
        }
        
        .payment-option {
            display: none;
        }
        
        .payment-option.active {
            display: block;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .payment-method-btn {
            position: relative;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        
        .payment-method-btn:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .payment-method-btn.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .payment-method-btn input[type="radio"] {
            display: none;
        }
        
        .payment-method-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .payment-method-label {
            font-weight: 600;
            font-size: 16px;
            color: #333;
            display: block;
            margin-bottom: 5px;
        }
        
        .payment-method-desc {
            font-size: 12px;
            color: #999;
        }
        
        .btn-checkout {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-checkout:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-checkout:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #666;
            font-size: 14px;
        }
        
        .alert {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="checkout-header">
            <h1><i class="bi bi-shield-check"></i> Secure Checkout</h1>
        </div>
        
        <div class="checkout-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Order Summary -->
            <div class="order-summary">
                <h5 class="mb-3"><i class="bi bi-receipt"></i> Order Summary</h5>
                
                <div class="summary-row">
                    <span>Item:</span>
                    <span><?php echo htmlspecialchars($payment['item_name'] ?? 'Service'); ?></span>
                </div>
                
                <div class="summary-row">
                    <span>Reference Number:</span>
                    <span class="text-muted"><?php echo htmlspecialchars($payment['reference_number']); ?></span>
                </div>
                
                <div class="summary-row">
                    <span>Amount:</span>
                    <span class="text-success">₱<?php echo number_format($payment['amount'], 2); ?></span>
                </div>
                
                <div class="summary-row total">
                    <span>Total Amount Due:</span>
                    <span>₱<?php echo number_format($payment['amount'], 2); ?></span>
                </div>
            </div>
            
            <!-- Payment Method Selection -->
            <form method="POST">
                <div class="payment-method-selector">
                    <h5 class="mb-3"><i class="bi bi-credit-card"></i> Select Payment Method</h5>
                    
                    <div class="payment-methods">
                        <!-- GCash -->
                        <label class="payment-method-btn" id="gcash-btn">
                            <input type="radio" name="payment_method" value="gcash" required>
                            <div class="payment-method-icon">
                                <i class="bi bi-phone"></i>
                            </div>
                            <span class="payment-method-label">GCash</span>
                            <span class="payment-method-desc">Fast & Secure</span>
                        </label>
                        
                        <!-- PayMaya -->
                        <label class="payment-method-btn" id="paymaya-btn">
                            <input type="radio" name="payment_method" value="paymaya" required>
                            <div class="payment-method-icon">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <span class="payment-method-label">PayMaya</span>
                            <span class="payment-method-desc">Card & Wallet</span>
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn-checkout" id="checkout-btn" disabled>
                    <i class="bi bi-lock"></i> Proceed to Payment
                </button>
            </form>
            
            <!-- Security Badge -->
            <div class="security-badge">
                <i class="bi bi-shield-check text-success"></i>
                <span>Your payment is secure and encrypted</span>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle payment method selection
        const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
        const paymentBtns = document.querySelectorAll('.payment-method-btn');
        const checkoutBtn = document.getElementById('checkout-btn');
        
        paymentMethods.forEach((method, index) => {
            method.addEventListener('change', function() {
                // Remove selected class from all buttons
                paymentBtns.forEach(btn => btn.classList.remove('selected'));
                
                // Add selected class to clicked button
                paymentBtns[index].classList.add('selected');
                
                // Enable checkout button
                checkoutBtn.disabled = false;
            });
        });
    </script>
</body>
</html>
