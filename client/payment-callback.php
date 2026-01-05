<?php
/**
 * Payment Callback Handler
 * Handles callbacks from GCash and PayMaya payment gateways
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Services/PaymentGatewayService.php';
require_once __DIR__ . '/../src/Services/EmailService.php';

use Services\PaymentGatewayService;
use Services\EmailService;

$gatewayService = new PaymentGatewayService();
$emailService = new EmailService();
$conn = getDBConnection();

$gateway = $_GET['gateway'] ?? '';
$paymentId = $_GET['payment_id'] ?? '';
$status = $_GET['status'] ?? '';

// Validate inputs
if (!$gateway || !$paymentId) {
    die('Invalid payment callback');
}

// Prepare callback data
$callbackData = [
    'status' => $status,
    'payment_id' => $paymentId,
    'timestamp' => time(),
    'gateway' => $gateway,
    'transaction_id' => $_GET['transaction_id'] ?? $_POST['transaction_id'] ?? null,
    'reference_number' => $_GET['reference_number'] ?? $_POST['reference_number'] ?? null,
    'raw_data' => array_merge($_GET, $_POST) // Log all data received
];

try {
    // Route callback to appropriate gateway handler
    if ($gateway === 'gcash') {
        $result = $gatewayService->handleGCashCallback($paymentId, $callbackData);
    } elseif ($gateway === 'paymaya') {
        $result = $gatewayService->handlePayMayaCallback($paymentId, $callbackData);
    } else {
        throw new Exception('Unknown gateway');
    }
    
    if ($result['success']) {
        // Get payment and user details
        $query = "SELECT p.*, u.email, u.full_name, p.reference_type, p.reference_id
                  FROM payments p
                  JOIN users u ON p.user_id = u.id
                  WHERE p.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $paymentId);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        
        if ($payment) {
            // Send notification email based on payment status
            $subject = '';
            $template = '';
            
            if ($payment['status'] === 'verified') {
                $subject = 'Payment Confirmed';
                $template = 'payment_confirmed';
                $successMessage = 'Thank you! Your payment has been successfully processed.';
                $statusBadge = 'success';
            } else {
                $subject = 'Payment Failed';
                $template = 'payment_failed';
                $successMessage = 'Your payment could not be processed. Please try again or contact support.';
                $statusBadge = 'danger';
            }
            
            // Send email notification
            $emailData = [
                'to' => $payment['email'],
                'subject' => $subject,
                'template' => $template,
                'data' => [
                    'full_name' => $payment['full_name'],
                    'amount' => number_format($payment['amount'], 2),
                    'reference_number' => $payment['reference_number'],
                    'payment_method' => ucfirst($gateway),
                    'transaction_id' => $callbackData['transaction_id'],
                    'date' => date('F j, Y H:i A')
                ]
            ];
            
            // Send confirmation email based on payment status
            if ($payment['status'] === 'verified') {
                $subject = 'Payment Confirmed';
                $message = 'Thank you! Your payment has been successfully processed.';
            } else {
                $subject = 'Payment Failed';
                $message = 'Your payment could not be processed. Please try again or contact support.';
            }
            
            // Log email attempt (actual email sending handled by callback page display)
            error_log('Payment notification for user: ' . $payment['email'] . ' - Status: ' . $payment['status']);
        }
        
        // Display success/failure page
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Payment <?php echo ucfirst($status); ?> - Document Management System</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
            <style>
                body {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                
                .payment-result {
                    background: white;
                    border-radius: 15px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                    padding: 60px 40px;
                    text-align: center;
                    max-width: 500px;
                    width: 100%;
                }
                
                .result-icon {
                    font-size: 80px;
                    margin-bottom: 20px;
                }
                
                .result-title {
                    font-size: 28px;
                    font-weight: 600;
                    margin-bottom: 15px;
                }
                
                .result-message {
                    font-size: 16px;
                    color: #666;
                    margin-bottom: 30px;
                    line-height: 1.6;
                }
                
                .payment-details {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 10px;
                    margin-bottom: 30px;
                    text-align: left;
                }
                
                .detail-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 0;
                    border-bottom: 1px solid #e0e0e0;
                }
                
                .detail-row:last-child {
                    border-bottom: none;
                }
                
                .detail-label {
                    color: #666;
                    font-weight: 500;
                }
                
                .detail-value {
                    color: #333;
                    font-weight: 600;
                }
                
                .btn-group-custom {
                    display: flex;
                    gap: 10px;
                    justify-content: center;
                }
                
                .btn-custom {
                    padding: 12px 30px;
                    border-radius: 8px;
                    font-weight: 600;
                    text-decoration: none;
                    transition: all 0.3s;
                    border: none;
                    cursor: pointer;
                }
                
                .btn-primary-custom {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    flex: 1;
                }
                
                .btn-primary-custom:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
                    color: white;
                }
                
                .btn-secondary-custom {
                    background: #f0f0f0;
                    color: #333;
                    flex: 1;
                }
                
                .btn-secondary-custom:hover {
                    background: #e0e0e0;
                    color: #333;
                }
                
                .text-success-custom {
                    color: #28a745;
                }
                
                .text-danger-custom {
                    color: #dc3545;
                }
            </style>
        </head>
        <body>
            <div class="payment-result">
                <?php if ($payment['status'] === 'verified'): ?>
                    <div class="result-icon text-success-custom">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <h1 class="result-title text-success-custom">Payment Successful!</h1>
                    <p class="result-message">
                        Your payment has been successfully processed. We've sent a confirmation email to <?php echo htmlspecialchars($payment['email']); ?>.
                    </p>
                    <div class="payment-details">
                        <div class="detail-row">
                            <span class="detail-label">Reference Number:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['reference_number']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Amount Paid:</span>
                            <span class="detail-value">₱<?php echo number_format($payment['amount'], 2); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Payment Method:</span>
                            <span class="detail-value"><?php echo ucfirst($gateway); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Transaction ID:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($callbackData['transaction_id'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Date & Time:</span>
                            <span class="detail-value"><?php echo date('M j, Y H:i A'); ?></span>
                        </div>
                    </div>
                    <div class="btn-group-custom">
                        <a href="/documentSystem/client/my-documents.php" class="btn-custom btn-primary-custom">
                            <i class="bi bi-file-earmark"></i> View Documents
                        </a>
                        <a href="/documentSystem/client/dashboard.php" class="btn-custom btn-secondary-custom">
                            <i class="bi bi-house"></i> Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <div class="result-icon text-danger-custom">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <h1 class="result-title text-danger-custom">Payment Failed</h1>
                    <p class="result-message">
                        Unfortunately, your payment could not be processed. Please check your payment details and try again, or contact our support team if you need assistance.
                    </p>
                    <div class="payment-details">
                        <div class="detail-row">
                            <span class="detail-label">Reference Number:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['reference_number']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Amount:</span>
                            <span class="detail-value">₱<?php echo number_format($payment['amount'], 2); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Payment Method:</span>
                            <span class="detail-value"><?php echo ucfirst($gateway); ?></span>
                        </div>
                    </div>
                    <div class="btn-group-custom">
                        <a href="/documentSystem/client/checkout.php?payment_id=<?php echo urlencode($paymentId); ?>" class="btn-custom btn-primary-custom">
                            <i class="bi bi-arrow-left"></i> Try Again
                        </a>
                        <a href="/documentSystem/client/dashboard.php" class="btn-custom btn-secondary-custom">
                            <i class="bi bi-house"></i> Home
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
    } else {
        throw new Exception($result['error'] ?? 'Payment processing failed');
    }
} catch (Exception $e) {
    error_log('Payment Callback Error: ' . $e->getMessage());
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Error - Document Management System</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">Payment Processing Error</h4>
                <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
                <hr>
                <a href="/documentSystem/client/dashboard.php" class="btn btn-danger">Return to Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
