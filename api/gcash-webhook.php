<?php
/**
 * GCash Webhook Handler
 * Receives payment notifications from GCash
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Services/PaymentGatewayService.php';

use Services\PaymentGatewayService;

$gatewayService = new PaymentGatewayService();
$conn = getDBConnection();

// Get raw request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log the webhook
$logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'gateway' => 'gcash',
    'data' => $data,
    'ip' => $_SERVER['REMOTE_ADDR']
];
error_log('GCash Webhook: ' . json_encode($logEntry));

try {
    // Extract payment ID from reference number
    // Format expected: PAY-{PAYMENT_ID}-{TIMESTAMP}
    if (isset($data['reference_number'])) {
        preg_match('/PAY-(\d+)-/', $data['reference_number'], $matches);
        $paymentId = $matches[1] ?? null;
        
        if ($paymentId) {
            // Verify payment status with GCash
            $paymentStatus = strtolower($data['status'] ?? '');
            
            // Handle the callback
            $result = $gatewayService->handleGCashCallback($paymentId, [
                'status' => $paymentStatus,
                'transaction_id' => $data['transaction_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'amount' => $data['amount'] ?? null,
                'currency' => $data['currency'] ?? 'PHP'
            ]);
            
            if ($result['success']) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Webhook processed']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $result['error']]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid reference number format']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing reference number']);
    }
} catch (Exception $e) {
    error_log('GCash Webhook Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Webhook processing failed']);
}
