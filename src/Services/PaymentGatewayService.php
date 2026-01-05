<?php
/**
 * Payment Gateway Service
 * Handles integration with GCash, PayMaya, and other payment gateways
 */

namespace Services;

require_once __DIR__ . '/../../config/database.php';

class PaymentGatewayService {
    private $conn;
    private $gcashConfig;
    private $paymayaConfig;
    
    public function __construct() {
        $this->conn = getDBConnection();
        
        // Load gateway configurations
        $this->gcashConfig = [
            'environment' => $_ENV['GCASH_ENV'] ?? 'production',
            'api_key' => $_ENV['GCASH_API_KEY'] ?? '',
            'api_secret' => $_ENV['GCASH_API_SECRET'] ?? '',
            'redirect_url' => $_ENV['APP_URL'] . '/documentSystem/client/payment-callback.php?gateway=gcash'
        ];
        
        $this->paymayaConfig = [
            'environment' => $_ENV['PAYMAYA_ENV'] ?? 'production',
            'public_key' => $_ENV['PAYMAYA_PUBLIC_KEY'] ?? '',
            'secret_key' => $_ENV['PAYMAYA_SECRET_KEY'] ?? '',
            'redirect_url' => $_ENV['APP_URL'] . '/documentSystem/client/payment-callback.php?gateway=paymaya'
        ];
    }
    
    /**
     * Initiate GCash payment
     */
    public function initiateGCashPayment($paymentId, $amount, $description, $customerEmail, $customerName) {
        try {
            // Validate amount
            if ($amount <= 0) {
                return ['success' => false, 'error' => 'Invalid payment amount'];
            }
            
            // Prepare GCash API request
            $payload = [
                'reference_number' => 'PAY-' . $paymentId . '-' . time(),
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => 'PHP',
                'description' => $description,
                'customer' => [
                    'email' => $customerEmail,
                    'name' => $customerName
                ],
                'redirect_url' => $this->gcashConfig['redirect_url'] . '&payment_id=' . $paymentId
            ];
            
            // Log transaction initiation
            $this->logPaymentGatewayTransaction($paymentId, 'gcash', 'initiated', json_encode($payload));
            
            // Call GCash API (endpoint depends on their current API)
            $response = $this->callGCashAPI('/v1/checkout', 'POST', $payload);
            
            if ($response['success']) {
                // Update payment with gateway transaction ID
                $this->updatePaymentGatewayData($paymentId, 'gcash', 'pending', $response['checkout_url'] ?? '');
                
                return [
                    'success' => true,
                    'checkout_url' => $response['checkout_url'],
                    'transaction_id' => $response['transaction_id'] ?? null
                ];
            } else {
                $this->logPaymentGatewayTransaction($paymentId, 'gcash', 'failed', $response['error'] ?? 'Unknown error');
                return ['success' => false, 'error' => $response['error'] ?? 'GCash API error'];
            }
        } catch (\Exception $e) {
            error_log('GCash Payment Initiation Error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Payment initiation failed'];
        }
    }
    
    /**
     * Initiate PayMaya payment
     */
    public function initiatePayMayaPayment($paymentId, $amount, $description, $customerEmail, $customerName) {
        try {
            if ($amount <= 0) {
                return ['success' => false, 'error' => 'Invalid payment amount'];
            }
            
            // Prepare PayMaya API request
            $payload = [
                'totalAmount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency' => 'PHP'
                ],
                'buyer' => [
                    'firstName' => explode(' ', $customerName)[0],
                    'lastName' => implode(' ', array_slice(explode(' ', $customerName), 1)),
                    'email' => $customerEmail,
                    'phone' => '' // Optional: add if available
                ],
                'items' => [
                    [
                        'name' => $description,
                        'quantity' => 1,
                        'amount' => [
                            'value' => number_format($amount, 2, '.', ''),
                            'currency' => 'PHP'
                        ]
                    ]
                ],
                'redirectUrl' => [
                    'success' => $this->paymayaConfig['redirect_url'] . '&payment_id=' . $paymentId . '&status=success',
                    'failure' => $this->paymayaConfig['redirect_url'] . '&payment_id=' . $paymentId . '&status=failure',
                    'cancel' => $this->paymayaConfig['redirect_url'] . '&payment_id=' . $paymentId . '&status=cancel'
                ],
                'requestReferenceNumber' => 'PAY-' . $paymentId . '-' . time(),
                'metadata' => [
                    'payment_id' => $paymentId,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
            
            $this->logPaymentGatewayTransaction($paymentId, 'paymaya', 'initiated', json_encode($payload));
            
            // Call PayMaya API
            $response = $this->callPayMayaAPI('/v1/checkout/create', 'POST', $payload);
            
            if ($response['success'] && isset($response['checkout_url'])) {
                $this->updatePaymentGatewayData($paymentId, 'paymaya', 'pending', $response['checkout_url']);
                
                return [
                    'success' => true,
                    'checkout_url' => $response['checkout_url'],
                    'transaction_id' => $response['transaction_id'] ?? null
                ];
            } else {
                $this->logPaymentGatewayTransaction($paymentId, 'paymaya', 'failed', $response['error'] ?? 'Unknown error');
                return ['success' => false, 'error' => $response['error'] ?? 'PayMaya API error'];
            }
        } catch (\Exception $e) {
            error_log('PayMaya Payment Initiation Error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Payment initiation failed'];
        }
    }
    
    /**
     * Handle GCash webhook/callback
     */
    public function handleGCashCallback($paymentId, $data) {
        try {
            $status = strtolower($data['status'] ?? '');
            $transactionId = $data['transaction_id'] ?? null;
            
            // Map GCash status to system status
            $systemStatus = match($status) {
                'completed', 'success' => 'verified',
                'pending' => 'pending',
                'failed' => 'rejected',
                'cancelled' => 'rejected',
                default => 'pending'
            };
            
            // Verify the signature/authenticity
            if (!$this->verifyGCashSignature($data)) {
                $this->logPaymentGatewayTransaction($paymentId, 'gcash', 'failed', 'Invalid signature');
                return ['success' => false, 'error' => 'Invalid signature'];
            }
            
            // Update payment status
            $this->updatePaymentStatus($paymentId, $systemStatus, $transactionId);
            $this->logPaymentGatewayTransaction($paymentId, 'gcash', $systemStatus, json_encode($data));
            
            return ['success' => true, 'message' => 'Payment status updated'];
        } catch (\Exception $e) {
            error_log('GCash Callback Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle PayMaya webhook/callback
     */
    public function handlePayMayaCallback($paymentId, $data) {
        try {
            $status = strtolower($data['status'] ?? '');
            $transactionId = $data['transactionId'] ?? null;
            
            // Map PayMaya status to system status
            $systemStatus = match($status) {
                'success' => 'verified',
                'pending' => 'pending',
                'failed', 'rejected' => 'rejected',
                default => 'pending'
            };
            
            // Verify the signature/authenticity
            if (!$this->verifyPayMayaSignature($data)) {
                $this->logPaymentGatewayTransaction($paymentId, 'paymaya', 'failed', 'Invalid signature');
                return ['success' => false, 'error' => 'Invalid signature'];
            }
            
            // Update payment status
            $this->updatePaymentStatus($paymentId, $systemStatus, $transactionId);
            $this->logPaymentGatewayTransaction($paymentId, 'paymaya', $systemStatus, json_encode($data));
            
            return ['success' => true, 'message' => 'Payment status updated'];
        } catch (\Exception $e) {
            error_log('PayMaya Callback Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Update payment status and trigger notifications
     */
    private function updatePaymentStatus($paymentId, $newStatus, $transactionId = null) {
        $query = "UPDATE payments SET status = ? WHERE id = ?";
        
        if ($transactionId) {
            $query = "UPDATE payments SET status = ?, transaction_number = ? WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('ssi', $newStatus, $transactionId, $paymentId);
        } else {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('si', $newStatus, $paymentId);
        }
        
        if ($stmt->execute()) {
            // Trigger appropriate notification/action based on status
            if ($newStatus === 'verified') {
                $this->handlePaymentVerified($paymentId);
            } elseif ($newStatus === 'rejected') {
                $this->handlePaymentRejected($paymentId);
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle verified payment
     */
    private function handlePaymentVerified($paymentId) {
        // Get payment details
        $query = "SELECT reference_type, reference_id, user_id FROM payments WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $paymentId);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        
        if (!$payment) return false;
        
        // Auto-approve the referenced item if configured
        if ($payment['reference_type'] === 'document_request') {
            // Document payment verified - update document payment status
            $updateQuery = "UPDATE document_requests SET payment_status = 'paid' WHERE id = ?";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bind_param('i', $payment['reference_id']);
            $updateStmt->execute();
        } elseif ($payment['reference_type'] === 'booking') {
            // Booking payment verified - update booking payment status
            $updateQuery = "UPDATE bookings SET payment_status = 'paid' WHERE id = ?";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bind_param('i', $payment['reference_id']);
            $updateStmt->execute();
        }
        
        return true;
    }
    
    /**
     * Handle rejected payment
     */
    private function handlePaymentRejected($paymentId) {
        // Log rejection and potentially notify user
        $query = "INSERT INTO activity_logs (user_id, action, description) 
                  SELECT user_id, 'PAYMENT_REJECTED', CONCAT('Payment ID: ', ?) FROM payments WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $paymentId, $paymentId);
        return $stmt->execute();
    }
    
    /**
     * Update payment gateway data
     */
    private function updatePaymentGatewayData($paymentId, $gateway, $status, $gatewayUrl) {
        // This could extend to store gateway-specific data
        // For now, we use the transaction_number field
        $query = "UPDATE payments SET transaction_number = CONCAT(?, ':', ?, ':', ?) WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $timestamp = date('Y-m-d H:i:s');
        $stmt->bind_param('sssi', $gateway, $status, $timestamp, $paymentId);
        return $stmt->execute();
    }
    
    /**
     * Log payment gateway transaction
     */
    private function logPaymentGatewayTransaction($paymentId, $gateway, $status, $details = '') {
        // Create a log entry in a payment_gateway_logs table (if it exists)
        // Or store in activity_logs
        $query = "INSERT INTO activity_logs (action, description) 
                  VALUES (?, ?)";
        $stmt = $this->conn->prepare($query);
        $action = strtoupper($gateway) . '_' . strtoupper($status);
        $description = "Payment ID: $paymentId | Details: $details";
        $stmt->bind_param('ss', $action, $description);
        return $stmt->execute();
    }
    
    /**
     * Call GCash API (stub - requires actual API endpoint)
     */
    private function callGCashAPI($endpoint, $method, $payload) {
        // This is a stub implementation
        // In production, this would make actual HTTP requests to GCash API
        
        // Placeholder response
        return [
            'success' => false,
            'error' => 'GCash API integration not configured',
            'checkout_url' => null
        ];
        
        /* Production implementation example:
        $url = 'https://api.gcash.com' . $endpoint;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->gcashConfig['api_key']
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        return [
            'success' => $httpCode === 200,
            'data' => $data,
            'error' => $data['error'] ?? null
        ];
        */
    }
    
    /**
     * Call PayMaya API (stub - requires actual API endpoint)
     */
    private function callPayMayaAPI($endpoint, $method, $payload) {
        // This is a stub implementation
        // In production, this would make actual HTTP requests to PayMaya API
        
        // Placeholder response
        return [
            'success' => false,
            'error' => 'PayMaya API integration not configured',
            'checkout_url' => null
        ];
        
        /* Production implementation example:
        $url = 'https://api.paymaya.com' . $endpoint;
        $auth = base64_encode($this->paymayaConfig['secret_key'] . ':');
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . $auth
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        return [
            'success' => $httpCode === 200,
            'data' => $data,
            'error' => $data['error'] ?? null
        ];
        */
    }
    
    /**
     * Verify GCash signature
     */
    private function verifyGCashSignature($data) {
        // Stub implementation
        // In production, verify the HMAC signature from GCash
        return true;
    }
    
    /**
     * Verify PayMaya signature
     */
    private function verifyPayMayaSignature($data) {
        // Stub implementation
        // In production, verify the signature from PayMaya
        return true;
    }
    
    /**
     * Get payment gateway configuration
     */
    public function getGatewayConfig($gateway) {
        return match($gateway) {
            'gcash' => $this->gcashConfig,
            'paymaya' => $this->paymayaConfig,
            default => null
        };
    }
}
