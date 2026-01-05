<?php
/**
 * PayMaya Webhook Handler
 * Receives payment notifications from PayMaya
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
    'gateway' => 'paymaya',
    'data' => $data,
    'ip' => $_SERVER['REMOTE_ADDR']
];
error_log('PayMaya Webhook: ' . json_encode($logEntry));

try {
    // Extract payment ID from metadata
    if (isset($data['metadata']['payment_id'])) {
        $paymentId = $data['metadata']['payment_id'];
        
        // Map PayMaya payment status to our system status
        $paymentStatus = 'pending';
        if (isset($data['status'])) {
            $paymentStatus = strtolower($data['status']);
        }
        
        // Handle the callback
        $result = $gatewayService->handlePayMayaCallback($paymentId, [
            'status' => $paymentStatus,
            'transactionId' => $data['id'] ?? null,
            'reference_number' => $data['requestReferenceNumber'] ?? null,
            'amount' => $data['totalAmount']['value'] ?? null,
            'currency' => $data['totalAmount']['currency'] ?? 'PHP',
            'metadata' => $data['metadata'] ?? []
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
        echo json_encode(['success' => false, 'error' => 'Missing payment ID in metadata']);
    }
} catch (Exception $e) {
    error_log('PayMaya Webhook Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Webhook processing failed']);
}
