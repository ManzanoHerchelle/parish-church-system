# Payment Gateway Integration - Client Pages

This guide explains how to integrate the payment gateway checkout into client pages for document requests and bookings.

## Integration Points

### 1. Document Request Payment

In `client/my-documents.php`, add a "Pay Now" button for pending documents:

```php
<?php
// In the document listing loop
if ($document['status'] === 'pending' && $document['payment_status'] === 'unpaid') {
    // Create or get payment record
    $paymentQuery = "SELECT id FROM payments WHERE reference_type = 'document_request' 
                    AND reference_id = ? AND user_id = ?";
    $stmt = $conn->prepare($paymentQuery);
    $stmt->bind_param('ii', $document['id'], $userId);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    
    if ($payment) {
        echo '<a href="/documentSystem/client/checkout.php?payment_id=' . $payment['id'] . '" 
              class="btn btn-primary btn-sm">
              <i class="bi bi-credit-card"></i> Pay Now
          </a>';
    }
}
?>
```

### 2. Booking Payment

In `client/my-appointments.php`, add payment option for pending bookings:

```php
<?php
// In the booking listing loop
if ($booking['status'] === 'pending' && $booking['payment_status'] === 'unpaid') {
    // Get associated payment
    $paymentQuery = "SELECT id FROM payments WHERE reference_type = 'booking' 
                    AND reference_id = ? AND user_id = ?";
    $stmt = $conn->prepare($paymentQuery);
    $stmt->bind_param('ii', $booking['id'], $userId);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    
    if ($payment) {
        echo '<a href="/documentSystem/client/checkout.php?payment_id=' . $payment['id'] . '" 
              class="btn btn-success btn-sm">
              <i class="bi bi-credit-card"></i> Complete Payment
          </a>';
    }
}
?>
```

### 3. Creating Payments on Request Submission

In your document/booking request handlers, create payment records:

```php
<?php
use Services\PaymentService;

$paymentService = new PaymentService();

// After creating document request
if ($documentCreated) {
    // Determine if payment is required
    $paymentRequired = true; // Or based on document type
    
    if ($paymentRequired) {
        $paymentData = [
            'user_id' => $userId,
            'amount' => 150.00, // Document request fee
            'reference_type' => 'document_request',
            'reference_id' => $documentId,
            'payment_method' => 'pending', // Will be selected at checkout
            'status' => 'pending',
            'notes' => 'Payment for document request'
        ];
        
        $paymentId = $paymentService->createPayment($paymentData);
        
        // Optionally redirect to checkout
        // header('Location: /documentSystem/client/checkout.php?payment_id=' . $paymentId);
    }
}
?>
```

## Payment Status Tracking

### Client View
Display payment status in document/booking listings:

```html
<div class="payment-status">
    <?php
        if ($item['payment_status'] === 'unpaid') {
            echo '<span class="badge bg-warning">
                    <i class="bi bi-clock"></i> Awaiting Payment
                  </span>';
        } elseif ($item['payment_status'] === 'paid') {
            echo '<span class="badge bg-success">
                    <i class="bi bi-check-circle"></i> Payment Confirmed
                  </span>';
        } elseif ($item['payment_status'] === 'rejected') {
            echo '<span class="badge bg-danger">
                    <i class="bi bi-x-circle"></i> Payment Failed
                  </span>';
        }
    ?>
</div>
```

### Database Queries

Check payment status:
```sql
SELECT p.status, p.payment_method, p.amount, p.created_at
FROM payments p
WHERE p.reference_type = 'document_request'
AND p.reference_id = ?
AND p.user_id = ?
ORDER BY p.created_at DESC
LIMIT 1;
```

Update payment status automatically when document is approved:
```sql
UPDATE payments
SET status = 'verified'
WHERE reference_type = 'document_request'
AND reference_id = ?;
```

## Email Notifications

After payment is verified, send confirmation emails:

```php
<?php
use Services\EmailService;

$emailService = new EmailService();

// In PaymentGatewayService::handlePaymentVerified()
if ($payment['reference_type'] === 'document_request') {
    // Get document details
    $docQuery = "SELECT dr.*, u.email, u.full_name 
                 FROM document_requests dr
                 JOIN users u ON dr.user_id = u.id
                 WHERE dr.id = ?";
    $stmt = $conn->prepare($docQuery);
    $stmt->bind_param('i', $payment['reference_id']);
    $stmt->execute();
    $document = $stmt->get_result()->fetch_assoc();
    
    // Send payment confirmed email
    $emailService->sendEmail([
        'to' => $document['email'],
        'subject' => 'Payment Confirmed - Document Request',
        'template' => 'payment_confirmed',
        'data' => [
            'full_name' => $document['full_name'],
            'document_type' => $document['document_title'],
            'amount' => $payment['amount'],
            'reference_number' => $payment['reference_number'],
            'date' => date('F j, Y')
        ]
    ]);
}
?>
```

## Admin Monitoring

### Payment Analytics Dashboard

Access via Admin → Reports to see:
- Total revenue from payments
- Payment method breakdown (GCash, PayMaya, Cash, etc.)
- Payment status distribution
- Revenue trends over time

### Manage Payments Page

Features:
- Search by transaction number or client name
- Filter by:
  - Payment status (Pending, Verified, Rejected)
  - Payment method (Cash, Online, Bank Transfer, GCash, PayMaya)
  - Amount range
  - Date range
- Manually verify payments if needed
- View payment details and transaction IDs

### Transaction Logging

All payment transactions are logged:
- Gateway requests and responses
- Webhook receipts
- Status updates
- Email notifications

Access logs in:
- PHP error logs
- Database activity_logs table
- Payment gateway webhooks section

## Testing Checklist

- [ ] Create document request
- [ ] Payment record created automatically
- [ ] Click "Pay Now" button
- [ ] Select payment method (GCash/PayMaya)
- [ ] Click "Proceed to Payment"
- [ ] Redirected to payment gateway
- [ ] Complete test payment
- [ ] Redirected back to confirmation page
- [ ] Confirmation email received
- [ ] Payment status updated to "verified" in database
- [ ] Document status remains in proper state
- [ ] Admin can see payment in "Manage Payments"
- [ ] Payment visible in Reports dashboard

## Troubleshooting

### Payment created but not visible in checkout
- Check: Payment record exists in database with correct status
- Check: Payment ID passed correctly to checkout.php
- Solution: Manually create payment via database INSERT

### Payment verified but status not updating
- Check: Webhook handler is receiving requests
- Check: Server error logs for webhook processing errors
- Solution: Manually update payment status in admin panel

### Gateway not redirecting after payment
- Check: Redirect URLs configured correctly in PaymentGatewayService
- Check: APP_URL environment variable set correctly
- Solution: Update gateway settings, retry payment

### Email not sending after payment
- Check: Email configuration in config/email_config.php
- Check: Gmail App Password is correct
- Solution: Review email service logs, resend manually

## Implementation Timeline

1. **Phase 1: Basic Setup** (Current)
   - PaymentGatewayService created
   - Checkout page created
   - Webhook handlers created
   - Admin configuration page created

2. **Phase 2: Integration** (Recommended next steps)
   - Update request submission pages to create payments
   - Add "Pay Now" buttons to document/booking listings
   - Integrate payment status display
   - Test end-to-end flow

3. **Phase 3: Production**
   - Configure live API credentials
   - Update webhook URLs in gateway accounts
   - Test with real payments
   - Monitor transaction logs
   - Set up email alerts for failed payments

## API Integration Notes

### GCash API
- Base URL: `https://api.gcash.com` (production)
- Authentication: Bearer token or API Key in headers
- Key endpoints:
  - `POST /v1/checkout` - Initiate payment
  - `GET /v1/checkout/{id}` - Check status
  - Webhooks: Payment completed/failed notifications

### PayMaya API
- Base URL: `https://api.paymaya.com` (production)
- Authentication: Basic auth with secret key
- Key endpoints:
  - `POST /v1/checkout/create` - Initiate checkout
  - `GET /v1/checkout/{id}` - Check status
  - Webhooks: Payment status updates

## Security Reminders

1. **Never** log or display full API keys/secrets
2. **Always** verify webhook signatures
3. **Use HTTPS** for all payment endpoints
4. **Validate** all payment data before processing
5. **Sanitize** user inputs in checkout form
6. **Encrypt** sensitive payment information
7. **Monitor** for suspicious payment activity
8. **Test** thoroughly in sandbox before going live

## Production Deployment

Before deploying to production:

1. Get live API credentials from GCash and PayMaya
2. Update environment variables with live keys
3. Set environment to "production" in admin config
4. Configure webhook URLs in gateway merchant portals
5. Test with real transactions (use test credit cards initially)
6. Monitor logs for errors
7. Set up backup payment methods (e.g., bank transfers)
8. Train staff on payment verification process
9. Create documentation for client support
10. Plan for payment failures and refunds
