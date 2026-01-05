# Payment Gateway Integration Guide

## Overview
This guide covers the integration of GCash and PayMaya payment gateways with the Document Management System. The system provides a complete payment processing workflow from initiation through verification.

## System Architecture

### Components

#### 1. PaymentGatewayService (`src/Services/PaymentGatewayService.php`)
Core service for payment gateway integration with methods for:
- **GCash Integration**
  - `initiateGCashPayment()` - Start a GCash payment transaction
  - `handleGCashCallback()` - Process GCash payment callbacks
  - `verifyGCashSignature()` - Verify webhook authenticity

- **PayMaya Integration**
  - `initiatePayMayaPayment()` - Start a PayMaya payment transaction
  - `handlePayMayaCallback()` - Process PayMaya payment callbacks
  - `verifyPayMayaSignature()` - Verify webhook authenticity

#### 2. Frontend Components
- **Checkout Page** (`client/checkout.php`)
  - Payment method selection (GCash/PayMaya)
  - Order summary display
  - Secure payment initiation

- **Payment Callback** (`client/payment-callback.php`)
  - Handles post-payment redirect
  - Displays payment status to user
  - Sends confirmation emails

#### 3. Webhook Handlers
- **GCash Webhook** (`api/gcash-webhook.php`)
  - Receives GCash payment notifications
  - Updates payment status in real-time
  - Logs transaction details

- **PayMaya Webhook** (`api/paymaya-webhook.php`)
  - Receives PayMaya payment notifications
  - Updates payment status in real-time
  - Handles various payment states

#### 4. Admin Configuration
- **Payment Gateway Config** (`admin/settings/payment-gateway-config.php`)
  - Configure API credentials
  - Set sandbox/production environment
  - Test gateway connections
  - View webhook URLs

## Integration Flow

### Payment Initiation Flow
```
1. User selects payment method (GCash/PayMaya)
2. System creates payment record in database
3. PaymentGatewayService initiates gateway transaction
4. User redirected to gateway payment page
5. User completes payment on gateway platform
```

### Webhook Flow
```
1. Payment gateway sends webhook notification
2. Webhook handler (gcash-webhook.php / paymaya-webhook.php) receives data
3. Signature verification
4. Payment status updated in database
5. Email notification sent to user
6. Associated document/booking status updated
```

### Callback Flow
```
1. User redirected from gateway to payment-callback.php
2. Display payment status to user
3. Send confirmation email
4. Provide links to documents/dashboard
```

## Configuration

### GCash Setup

#### 1. Get API Credentials
- Log in to GCash Business Portal
- Navigate to API Settings or Developer Console
- Generate/Copy API Key (starts with `pk_` or `sk_`)
- Copy API Secret

#### 2. Configure in System
```
1. Go to Admin → Settings → Payment Gateway Configuration
2. Enable GCash
3. Select Environment (Sandbox for testing, Production for live)
4. Paste API Key and API Secret
5. Click "Save Configuration"
```

#### 3. Set Webhook URL
In GCash Merchant Account:
```
Webhook URL: https://yourdomain.com/documentSystem/api/gcash-webhook.php
Events to Subscribe: payment.completed, payment.failed, payment.cancelled
```

#### 4. Test Connection
```
Click "Test Connection" button in admin panel
```

### PayMaya Setup

#### 1. Get API Credentials
- Log in to PayMaya Merchant Account
- Go to Settings → API Keys
- Copy Public Key
- Copy Secret Key

#### 2. Configure in System
```
1. Go to Admin → Settings → Payment Gateway Configuration
2. Enable PayMaya
3. Select Environment (Sandbox for testing, Production for live)
4. Paste Public Key and Secret Key
5. Click "Save Configuration"
```

#### 3. Set Webhook URL
In PayMaya Merchant Settings:
```
Webhook URL: https://yourdomain.com/documentSystem/api/paymaya-webhook.php
Enable: Payment notifications
```

#### 4. Test Connection
```
Click "Test Connection" button in admin panel
```

## Usage

### For Clients

#### Making a Payment
1. Request document or book appointment (creates payment record)
2. Click "Pay Now" or checkout button
3. Select payment method (GCash or PayMaya)
4. Review order summary
5. Click "Proceed to Payment"
6. Complete payment on payment gateway
7. Redirected back to confirmation page
8. Receive confirmation email

### For Admins

#### Viewing Payment Gateway Configuration
```
1. Go to Admin Dashboard
2. Click Settings (sidebar)
3. Click "Payment Gateway Configuration"
4. View current configuration and credentials
```

#### Testing Gateway Connection
```
1. In Payment Gateway Configuration page
2. Click "Test Connection" button for respective gateway
3. System attempts connection and displays result
```

#### Monitoring Payments
```
1. Go to Admin → Manage Payments
2. Filter by Payment Method (GCash, PayMaya, etc.)
3. View payment status: Pending, Verified, Rejected
4. See transaction IDs and amounts
```

## Database Fields

### Payments Table Extensions
```sql
-- Existing fields
- payment_method: cash | online | bank_transfer | gcash | paymaya
- status: pending | verified | rejected
- amount: decimal amount
- reference_number: unique reference

-- New fields for gateway
- transaction_number: stores transaction ID from gateway
- payment_gateway: which gateway processed (gcash/paymaya)
- gateway_response: raw response from gateway (JSON)
```

## Error Handling

### Common Issues & Solutions

#### 1. "GCash API integration not configured"
- **Cause**: API credentials not entered or incorrect
- **Solution**: Add valid API Key and Secret in admin settings

#### 2. "Invalid signature" on webhook
- **Cause**: Webhook signature verification failed
- **Solution**: 
  - Verify webhook URL is correct
  - Check API credentials match
  - Ensure request came from actual gateway

#### 3. Payment stuck in "Pending"
- **Cause**: Webhook not received or webhook handler failed
- **Solution**:
  - Check webhook URL in gateway settings
  - Check server logs for errors
  - Manually verify payment in gateway dashboard
  - Update payment status manually if needed

#### 4. Email notifications not sent
- **Cause**: EmailService not configured
- **Solution**: Check email configuration in `config/email_config.php`

## Security Considerations

### Sensitive Data
- **Never commit** API keys or secrets to version control
- Store credentials in **environment variables** or **secure config files**
- Use **HTTPS only** for all payment endpoints
- Verify **webhook signatures** before processing

### Best Practices
1. Use **Sandbox environment** for testing
2. Implement **rate limiting** on webhook handlers
3. **Log all transactions** for audit trail
4. **Encrypt** sensitive payment data in database
5. Follow **PCI DSS compliance** requirements
6. Never store full credit card details

## API Reference

### PaymentGatewayService Methods

#### initiateGCashPayment()
```php
public function initiateGCashPayment(
    $paymentId,      // Payment record ID
    $amount,         // Amount in PHP
    $description,    // Item description
    $customerEmail,  // Customer email
    $customerName    // Customer full name
)
```

**Returns:**
```php
[
    'success' => true|false,
    'checkout_url' => 'https://gateway.com/checkout/...',
    'transaction_id' => 'txn_xxx',
    'error' => 'Error message if failed'
]
```

#### initiatePayMayaPayment()
```php
public function initiatePayMayaPayment(
    $paymentId,      // Payment record ID
    $amount,         // Amount in PHP
    $description,    // Item description
    $customerEmail,  // Customer email
    $customerName    // Customer full name
)
```

**Returns:** Same as GCash

#### handleGCashCallback()
```php
public function handleGCashCallback(
    $paymentId,  // Payment record ID
    $data        // Callback data array
)
```

**Returns:**
```php
[
    'success' => true|false,
    'message' => 'Status message',
    'error' => 'Error if failed'
]
```

#### handlePayMayaCallback()
Same as GCash callback handler.

## Webhook Data Format

### GCash Webhook
```json
{
    "reference_number": "PAY-123-1234567890",
    "status": "completed",
    "transaction_id": "GCH123456",
    "amount": "1000.00",
    "currency": "PHP"
}
```

### PayMaya Webhook
```json
{
    "id": "PAYMAYA123456",
    "status": "success",
    "totalAmount": {
        "value": "1000.00",
        "currency": "PHP"
    },
    "requestReferenceNumber": "PAY-123-1234567890",
    "metadata": {
        "payment_id": "123",
        "timestamp": "2024-01-01 12:00:00"
    }
}
```

## Testing

### Sandbox Testing Flow

#### GCash Sandbox
1. Use sandbox API credentials from GCash
2. Set environment to "Sandbox" in admin config
3. Create test payments
4. Use test payment credentials provided by GCash
5. Verify webhooks received in logs

#### PayMaya Sandbox
1. Use sandbox credentials from PayMaya
2. Set environment to "Sandbox" in admin config
3. Create test payments
4. Use test card details (4111 1111 1111 1111)
5. Verify payment processing

### Test Scenarios
1. **Successful Payment**
   - Complete payment flow
   - Verify status changes to "verified"
   - Confirm email sent
   - Check document/booking status updated

2. **Failed Payment**
   - Use invalid card/credentials
   - Verify status remains "pending" or changes to "rejected"
   - Confirm error email sent

3. **Webhook Verification**
   - Check server logs for webhook receipt
   - Verify payment status updated from webhook
   - Test signature verification

## Troubleshooting

### Enable Debug Logging
Add to your `error_reporting`:
```php
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/payment_gateway.log');
```

### Check Webhook Logs
```bash
tail -f /var/log/apache2/error.log | grep "Webhook"
```

### Manual Payment Status Update
In rare cases where webhook fails:
```php
// In admin panel, click "Verify Payment" to manually mark as verified
```

## Future Enhancements

1. **Installment Payments**
   - Support payment plans for large amounts
   - Track partial payments

2. **Refund Processing**
   - Implement refund workflows
   - Handle refund notifications

3. **Payment Analytics**
   - Track conversion rates
   - Analyze payment method usage
   - Revenue forecasting

4. **Additional Gateways**
   - Stripe integration
   - 2Checkout support
   - International payment methods

## Support

For issues or questions:
1. Check this documentation first
2. Review error logs in `/logs` directory
3. Contact payment gateway support
4. Check gateway webhook delivery status

## Changelog

### v1.0 (Current)
- Initial implementation
- GCash integration
- PayMaya integration
- Webhook support
- Admin configuration panel
- Email notifications
