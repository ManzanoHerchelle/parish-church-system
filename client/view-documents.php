<?php
/**
 * Client - View Documents (Draft)
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();

if (!isLoggedIn()) {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$userId = getCurrentUserId();
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = getCurrentUserEmail();

$conn = getDBConnection();

// Get all document requests for user
$documentsQuery = "
    SELECT dr.id, dr.reference_number, dr.status, dr.payment_status, dr.created_at, dr.updated_at, dt.name, dt.fee
    FROM document_requests dr
    JOIN document_types dt ON dr.document_type_id = dt.id
    WHERE dr.user_id = ?
    ORDER BY dr.created_at DESC
";

$stmt = $conn->prepare($documentsQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$documentsResult = $stmt->get_result();
$documents = $documentsResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get active payment methods
$paymentMethods = $conn->query("SELECT id, code, display_name FROM payment_methods WHERE is_active = 1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

// Get payment accounts organized by method
$paymentAccountsResult = $conn->query("SELECT pa.*, pm.code as method_code FROM payment_accounts pa JOIN payment_methods pm ON pa.payment_method_id = pm.id WHERE pa.is_active = 1 ORDER BY pm.sort_order, pa.sort_order");
$paymentAccounts = [];
while ($row = $paymentAccountsResult->fetch_assoc()) {
    $methodCode = $row['method_code'];
    if (!isset($paymentAccounts[$methodCode])) {
        $paymentAccounts[$methodCode] = [];
    }
    $paymentAccounts[$methodCode][] = $row;
}

// Get user initials
$nameParts = explode(' ', $userName);
$userInitials = strtoupper(
    substr($nameParts[0], 0, 1) . 
    (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : substr($nameParts[0], 1, 1))
);

function getStatusBadge($status) {
    $colors = [
        'pending' => ['bg' => '#fff3cd', 'text' => '#856404', 'icon' => 'hourglass-split'],
        'processing' => ['bg' => '#d1ecf1', 'text' => '#0c5460', 'icon' => 'arrow-repeat'],
        'ready' => ['bg' => '#d4edda', 'text' => '#155724', 'icon' => 'check-circle'],
        'completed' => ['bg' => '#d4edda', 'text' => '#155724', 'icon' => 'check-circle-fill'],
        'rejected' => ['bg' => '#f8d7da', 'text' => '#721c24', 'icon' => 'x-circle']
    ];
    
    $color = $colors[$status] ?? ['bg' => '#d1ecf1', 'text' => '#0c5460', 'icon' => 'info-circle'];
    return '<span style="background: ' . $color['bg'] . '; color: ' . $color['text'] . '; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; white-space: nowrap;"><i class="bi bi-' . $color['icon'] . '" style="margin-right: 4px;"></i>' . ucfirst($status) . '</span>';
}

function getPaymentStatusBadge($status) {
    $colors = [
        'unpaid' => ['bg' => '#f8d7da', 'text' => '#721c24'],
        'pending' => ['bg' => '#fff3cd', 'text' => '#856404'],
        'paid' => ['bg' => '#d4edda', 'text' => '#155724']
    ];
    
    $color = $colors[$status] ?? ['bg' => '#d1ecf1', 'text' => '#0c5460'];
    return '<span style="background: ' . $color['bg'] . '; color: ' . $color['text'] . '; padding: 3px 10px; border-radius: 15px; font-size: 10px; font-weight: bold;">' . ucfirst($status) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Documents - Parish Church System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="/documentSystem/assets/css/common.css" rel="stylesheet">
  <link href="/documentSystem/assets/css/appointments.css" rel="stylesheet">
  <style>
    /* Fix modal z-index */
    .modal {
      z-index: 1060 !important;
    }
    .modal-backdrop {
      z-index: 1050 !important;
    }
    .modal-dialog {
      z-index: 1061 !important;
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
      <li class="nav-item"><a href="/documentSystem/client/dashboard.php" class="nav-link"><i class="bi bi-house-door-fill"></i><span>Home</span></a></li>
      <li class="nav-item"><a href="/documentSystem/client/view-documents.php" class="nav-link active"><i class="bi bi-file-earmark-check"></i><span>View Documents</span></a></li>
      <li class="nav-item"><a href="/documentSystem/client/view-appointments.php" class="nav-link"><i class="bi bi-calendar-check"></i><span>View Appointments</span></a></li>
      <li class="nav-item"><a href="/documentSystem/client/new-appointment.php" class="nav-link"><i class="bi bi-calendar-plus"></i><span>New Appointment</span></a></li>
      <li class="nav-item"><a href="/documentSystem/client/request-documents.php" class="nav-link"><i class="bi bi-file-earmark-text"></i><span>Request Document</span></a></li>
      <div class="nav-separator"></div>
      <li class="nav-item"><a href="/documentSystem/client/change-password.php" class="nav-link"><i class="bi bi-key-fill"></i><span>Change Password</span></a></li>
      <li class="nav-item"><a href="/documentSystem/api/logout.php" class="nav-link"><i class="bi bi-box-arrow-right"></i><span>Log Out</span></a></li>
    </ul>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="content-area">
      <h1 class="page-title">DOCUMENT REQUESTS</h1>
      <div class="title-underline"></div>

      <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($_SESSION['success_message']); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
      <?php endif; ?>

      <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($_SESSION['error_message']); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
      <?php endif; ?>

      <div class="info-banner">
        <strong><i class="bi bi-info-circle"></i> Track your document requests here.</strong> Once ready, you'll be notified and can pick up from our office during business hours.
      </div>

      <!-- Tabs -->
      <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('all')">All Requests</button>
        <button class="tab-btn" onclick="switchTab('pending')">Pending</button>
        <button class="tab-btn" onclick="switchTab('ready')">Ready for Pickup</button>
        <button class="tab-btn" onclick="switchTab('completed')">Completed</button>
      </div>

      <!-- All Requests Tab -->
      <div id="all" class="tab-content active">
        <?php if (count($documents) > 0): ?>
          <?php foreach ($documents as $doc): ?>
            <div class="doc-card <?php echo $doc['status'] === 'ready' ? 'ready' : ''; ?>">
              <div class="doc-header">
                <div>
                  <div class="doc-title"><?php echo htmlspecialchars($doc['name']); ?></div>
                  <div class="doc-ref">Reference: <?php echo htmlspecialchars($doc['reference_number']); ?></div>
                </div>
                <div>
                  <div style="text-align: right;">
                    <?php echo getStatusBadge($doc['status']); ?><br>
                    <small style="color: #666; display: block; margin-top: 5px;">Requested: <?php echo date('M d, Y', strtotime($doc['created_at'])); ?></small>
                  </div>
                </div>
              </div>

              <div class="doc-details">
                <div class="detail-item">
                  <div class="detail-label"><i class="bi bi-tag"></i> Fee</div>
                  <div class="detail-value">₱<?php echo number_format($doc['fee'], 2); ?></div>
                </div>
                <div class="detail-item">
                  <div class="detail-label"><i class="bi bi-credit-card"></i> Payment Status</div>
                  <div class="detail-value"><?php echo getPaymentStatusBadge($doc['payment_status']); ?></div>
                </div>
                <div class="detail-item">
                  <div class="detail-label"><i class="bi bi-clock"></i> Status</div>
                  <div class="detail-value" style="text-transform: capitalize;"><?php echo htmlspecialchars($doc['status']); ?></div>
                </div>
              </div>

              <?php if ($doc['status'] === 'ready'): ?>
                <div style="background: #d4edda; border-left: 3px solid #28a745; padding: 10px 15px; margin-bottom: 15px; border-radius: 3px;">
                  <strong style="color: #155724;">Your document is ready for pickup!</strong><br>
                  <small style="color: #155724;">Visit our office during business hours with a valid ID</small>
                </div>
              <?php endif; ?>

              <div class="doc-actions">
                <?php if ($doc['status'] === 'ready'): ?>
                  <button class="btn-small btn-download" onclick="alert('Download feature coming soon')">
                    <i class="bi bi-download"></i> Download
                  </button>
                <?php endif; ?>

                <?php if ($doc['payment_status'] === 'unpaid'): ?>
                  <button class="btn-small btn-pay" onclick="openPaymentModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['reference_number']); ?>', <?php echo $doc['fee']; ?>)">
                    <i class="bi bi-credit-card"></i> Make Payment
                  </button>
                <?php endif; ?>

                <button class="btn-small btn-view" onclick="viewDocumentDetails(<?php echo htmlspecialchars(json_encode($doc)); ?>)">
                  <i class="bi bi-eye"></i> View Details
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state">
            <i class="bi bi-file-earmark-x"></i>
            <p>No document requests yet</p>
            <a href="/documentSystem/client/request-documents.php" style="color: #3d4f5c; font-weight: bold; text-decoration: none;">Request a document →</a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Pending Tab -->
      <div id="pending" class="tab-content">
        <?php $pending = array_filter($documents, fn($d) => in_array($d['status'], ['pending', 'processing'])); ?>
        <?php if (count($pending) > 0): ?>
          <?php foreach ($pending as $doc): ?>
            <div class="doc-card">
              <div class="doc-header">
                <div>
                  <div class="doc-title"><?php echo htmlspecialchars($doc['name']); ?></div>
                  <div class="doc-ref">Reference: <?php echo htmlspecialchars($doc['reference_number']); ?></div>
                </div>
                <div><?php echo getStatusBadge($doc['status']); ?></div>
              </div>
              <div class="doc-details">
                <div class="detail-item"><div class="detail-label">Fee</div><div class="detail-value">₱<?php echo number_format($doc['fee'], 2); ?></div></div>
                <div class="detail-item"><div class="detail-label">Payment</div><div class="detail-value"><?php echo getPaymentStatusBadge($doc['payment_status']); ?></div></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state"><i class="bi bi-inbox"></i><p>No pending requests</p></div>
        <?php endif; ?>
      </div>

      <!-- Ready Tab -->
      <div id="ready" class="tab-content">
        <?php $ready = array_filter($documents, fn($d) => $d['status'] === 'ready'); ?>
        <?php if (count($ready) > 0): ?>
          <?php foreach ($ready as $doc): ?>
            <div class="doc-card ready">
              <div class="doc-header">
                <div>
                  <div class="doc-title"><i class="bi bi-check-circle" style="color: #28a745; margin-right: 8px;"></i><?php echo htmlspecialchars($doc['name']); ?></div>
                  <div class="doc-ref">Reference: <?php echo htmlspecialchars($doc['reference_number']); ?></div>
                </div>
              </div>
              <div style="background: #e8f5e9; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                <strong style="color: #2e7d32;">Ready for Pickup!</strong><br>
                <small style="color: #2e7d32;">Visit our office: Mon-Sat, 9 AM - 5 PM. Bring a valid ID.</small>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state"><i class="bi bi-folder-check"></i><p>No documents ready yet</p></div>
        <?php endif; ?>
      </div>

      <!-- Completed Tab -->
      <div id="completed" class="tab-content">
        <?php $completed = array_filter($documents, fn($d) => $d['status'] === 'completed'); ?>
        <?php if (count($completed) > 0): ?>
          <?php foreach ($completed as $doc): ?>
            <div class="doc-card">
              <div class="doc-header">
                <div>
                  <div class="doc-title"><?php echo htmlspecialchars($doc['name']); ?></div>
                  <div class="doc-ref">Reference: <?php echo htmlspecialchars($doc['reference_number']); ?></div>
                </div>
                <div><?php echo getStatusBadge($doc['status']); ?></div>
              </div>
              <small style="color: #666;">Completed: <?php echo date('F d, Y', strtotime($doc['updated_at'])); ?></small>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state"><i class="bi bi-check-all"></i><p>No completed documents</p></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
      <div class="footer-content">
        <strong>FOR INQUIRIES:</strong><br>
        HOTLINE: 0999 MAYNAY<br>
        EMAIL: maequinas@gmail.com
      </div>
      <div class="footer-bottom">
        Parish Church © <?php echo date('Y'); ?>
      </div>
    </footer>
  </div>

  <!-- Payment Upload Modal -->
  <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="paymentModalLabel">
            <i class="bi bi-credit-card"></i> Upload Payment Proof
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="paymentForm" method="POST" action="/documentSystem/client/upload-payment-proof.php" enctype="multipart/form-data">
          <div class="modal-body">
            <input type="hidden" name="document_request_id" id="document_request_id">
            
            <div class="alert alert-info">
              <strong><i class="bi bi-info-circle"></i> Payment Details</strong><br>
              <div class="mt-2">
                <strong>Reference:</strong> <span id="modal_reference"></span><br>
                <strong>Amount Due:</strong> ₱<span id="modal_amount"></span>
              </div>
            </div>

            <div class="alert alert-warning" id="bankDetails" style="display: none;">
              <strong><i class="bi bi-bank"></i> Bank Transfer Details:</strong><br>
              <div class="mt-2" style="font-size: 14px;" id="bankDetailsContent">
                <!-- Populated by JavaScript -->
              </div>
            </div>

            <div class="alert alert-success" id="gcashDetails" style="display: none;">
              <strong><i class="bi bi-wallet2"></i> GCash Details:</strong><br>
              <div class="mt-2" style="font-size: 14px;" id="gcashDetailsContent">
                <!-- Populated by JavaScript -->
              </div>
            </div>

            <div class="alert alert-info" id="paymayaDetails" style="display: none;">
              <strong><i class="bi bi-credit-card-2-front"></i> PayMaya Details:</strong><br>
              <div class="mt-2" style="font-size: 14px;" id="paymayaDetailsContent">
                <!-- Populated by JavaScript -->
              </div>
            </div>

            <div class="alert alert-secondary" id="counterDetails" style="display: none;">
              <strong><i class="bi bi-shop"></i> Over the Counter Payment:</strong><br>
              <div class="mt-2" style="font-size: 14px;">
                Visit the parish office during business hours:<br>
                <strong>Monday - Friday:</strong> 8:00 AM - 5:00 PM<br>
                <strong>Saturday:</strong> 8:00 AM - 12:00 NN<br>
                Request for official receipt after payment.
              </div>
            </div>

            <div class="mb-3">
              <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
              <select class="form-select" id="payment_method" name="payment_method" required onchange="showPaymentDetails()">
                <option value="">-- Select Method --</option>
                <?php foreach ($paymentMethods as $pm): ?>
                  <option value="<?php echo htmlspecialchars($pm['code']); ?>"><?php echo htmlspecialchars($pm['display_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label for="transaction_reference" class="form-label">Transaction/Reference Number</label>
              <input type="text" class="form-control" id="transaction_reference" name="transaction_reference" placeholder="Enter transaction reference number">
            </div>

            <div class="mb-3">
              <label for="payment_proof" class="form-label">Upload Receipt/Proof <span class="text-danger">*</span></label>
              <input type="file" class="form-control" id="payment_proof" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf">
              <small class="text-muted">Accepted formats: JPG, PNG, PDF (Max 5MB)</small>
            </div>

            <div class="mb-3">
              <label for="payment_notes" class="form-label">Notes (Optional)</label>
              <textarea class="form-control" id="payment_notes" name="payment_notes" rows="2" placeholder="Any additional notes..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="bi bi-x-circle"></i> Cancel
            </button>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-upload"></i> Submit Payment
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Document Details Modal -->
  <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title" id="detailsModalLabel">
            <i class="bi bi-info-circle"></i> Document Request Details
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="text-muted small">REFERENCE NUMBER</label>
              <div class="fw-bold" id="detail_reference"></div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="text-muted small">DOCUMENT TYPE</label>
              <div class="fw-bold" id="detail_document_type"></div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="text-muted small">STATUS</label>
              <div id="detail_status"></div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="text-muted small">PAYMENT STATUS</label>
              <div id="detail_payment_status"></div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="text-muted small">AMOUNT</label>
              <div class="fw-bold text-success" id="detail_amount"></div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="text-muted small">REQUESTED ON</label>
              <div id="detail_created_at"></div>
            </div>
          </div>

          <div class="row">
            <div class="col-12 mb-3">
              <label class="text-muted small">LAST UPDATED</label>
              <div id="detail_updated_at"></div>
            </div>
          </div>

          <hr>

          <div class="alert alert-info">
            <h6 class="alert-heading"><i class="bi bi-info-circle"></i> What's Next?</h6>
            <div id="detail_next_steps"></div>
          </div>

          <div id="detail_pickup_info" class="alert alert-success" style="display: none;">
            <h6 class="alert-heading"><i class="bi bi-geo-alt"></i> Pickup Information</h6>
            <p class="mb-1"><strong>Office Hours:</strong> Monday - Friday, 8:00 AM - 5:00 PM</p>
            <p class="mb-1"><strong>Location:</strong> Parish Office</p>
            <p class="mb-0"><strong>Bring:</strong> Valid ID and this reference number</p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle"></i> Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/documentSystem/assets/js/common.js"></script>
  
  <script>
    // Payment form submission validation
    document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
      const method = document.getElementById('payment_method').value;
      const proofFile = document.getElementById('payment_proof');
      
      // Check if proof is required for this method
      const requiresProof = ['bank_transfer', 'gcash', 'paymaya'].includes(method);
      
      if (requiresProof && !proofFile.value) {
        e.preventDefault();
        alert('Please upload a payment proof file for the selected payment method.');
        return false;
      }
    });

    // Show payment details based on selected method
    function showPaymentDetails() {
      const method = document.getElementById('payment_method').value;
      const transactionRef = document.getElementById('transaction_reference');
      const proofFile = document.getElementById('payment_proof');
      const transactionRefContainer = transactionRef.closest('.mb-3');
      const paymentProofContainer = proofFile.closest('.mb-3');
      
      // Hide all payment details
      document.getElementById('bankDetails').style.display = 'none';
      document.getElementById('gcashDetails').style.display = 'none';
      document.getElementById('paymayaDetails').style.display = 'none';
      document.getElementById('counterDetails').style.display = 'none';
      
      if (!method) return;
      
      // Show the appropriate section and populate with account details
      if (method === 'bank_transfer' && paymentAccountsData['bank_transfer']) {
        const accounts = paymentAccountsData['bank_transfer'];
        let html = '';
        accounts.forEach(acc => {
          html += `<strong>${acc.account_name}:</strong><br>
                   <strong>Account Number:</strong> ${acc.account_number}<br>
                   <strong>Account Holder:</strong> ${acc.account_holder}`;
          if (acc.branch_name) html += `<br><strong>Branch:</strong> ${acc.branch_name}`;
          if (acc.instructions) html += `<br><strong>Instructions:</strong> ${acc.instructions}`;
          html += '<br><br>';
        });
        document.getElementById('bankDetailsContent').innerHTML = html;
        document.getElementById('bankDetails').style.display = 'block';
        transactionRefContainer.style.display = 'block';
        transactionRef.disabled = false;
        transactionRef.style.backgroundColor = '';
        transactionRef.value = '';
        paymentProofContainer.style.display = 'block';
        proofFile.disabled = false;
        proofFile.style.backgroundColor = '';
        proofFile.value = '';
      } else if (method === 'gcash' && paymentAccountsData['gcash']) {
        const accounts = paymentAccountsData['gcash'];
        let html = '';
        accounts.forEach(acc => {
          html += `<strong>Account Name:</strong> ${acc.account_number}<br>`;
          if (acc.instructions) html += `<strong>Note:</strong> ${acc.instructions}<br>`;
        });
        document.getElementById('gcashDetailsContent').innerHTML = html;
        document.getElementById('gcashDetails').style.display = 'block';
        transactionRefContainer.style.display = 'block';
        transactionRef.disabled = false;
        transactionRef.style.backgroundColor = '';
        transactionRef.value = '';
        paymentProofContainer.style.display = 'block';
        proofFile.disabled = false;
        proofFile.style.backgroundColor = '';
        proofFile.value = '';
      } else if (method === 'paymaya' && paymentAccountsData['paymaya']) {
        const accounts = paymentAccountsData['paymaya'];
        let html = '';
        accounts.forEach(acc => {
          html += `<strong>Account Name:</strong> ${acc.account_number}<br>`;
          if (acc.instructions) html += `<strong>Note:</strong> ${acc.instructions}<br>`;
        });
        document.getElementById('paymayaDetailsContent').innerHTML = html;
        document.getElementById('paymayaDetails').style.display = 'block';
        transactionRefContainer.style.display = 'block';
        transactionRef.disabled = false;
        transactionRef.style.backgroundColor = '';
        transactionRef.value = '';
        paymentProofContainer.style.display = 'block';
        proofFile.disabled = false;
        proofFile.style.backgroundColor = '';
        proofFile.value = '';
      } else if (method === 'cash' || method === 'over_counter') {
        document.getElementById('counterDetails').style.display = 'block';
        transactionRefContainer.style.display = 'block';
        transactionRef.disabled = true;
        transactionRef.style.backgroundColor = '#f0f0f0';
        transactionRef.value = '';
        paymentProofContainer.style.display = 'block';
        proofFile.disabled = true;
        proofFile.style.backgroundColor = '#f0f0f0';
        proofFile.value = '';
      }
    }

    // Open payment modal
    function openPaymentModal(documentId, referenceNumber, amount) {
      document.getElementById('document_request_id').value = documentId;
      document.getElementById('modal_reference').textContent = referenceNumber;
      document.getElementById('modal_amount').textContent = parseFloat(amount).toFixed(2);

      // Reset payment form fields and apply defaults
      const methodSelect = document.getElementById('payment_method');
      const proofFile = document.getElementById('payment_proof');
      const transactionRef = document.getElementById('transaction_reference');

      if (methodSelect) methodSelect.value = '';
      if (proofFile) {
        proofFile.value = '';
        proofFile.required = false;
        proofFile.disabled = false;
        proofFile.style.backgroundColor = '';
      }
      if (transactionRef) {
        transactionRef.value = '';
        transactionRef.required = false;
        transactionRef.disabled = false;
        transactionRef.style.backgroundColor = '';
      }

      // Re-apply visibility/requirements for currently selected method (or default)
      showPaymentDetails();
      
      const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
      modal.show();
    }

    // File size validation
    document.getElementById('payment_proof')?.addEventListener('change', function() {
      if (this.files[0] && this.files[0].size > 5 * 1024 * 1024) {
        alert('File size must be less than 5MB');
        this.value = '';
      }
    });

    // Tab switching function
    function switchTab(tabName) {
      // Hide all tab contents
      const tabContents = document.querySelectorAll('.tab-content');
      tabContents.forEach(content => {
        content.classList.remove('active');
        content.style.display = 'none';
      });
      
      // Remove active class from all buttons
      const tabButtons = document.querySelectorAll('.tab-btn');
      tabButtons.forEach(btn => {
        btn.classList.remove('active');
      });
      
      // Show selected tab content
      const selectedTab = document.getElementById(tabName);
      if (selectedTab) {
        selectedTab.classList.add('active');
        selectedTab.style.display = 'block';
      }
      
      // Add active class to clicked button
      event.target.classList.add('active');
    }

    // View document details
    function viewDocumentDetails(doc) {
      // Populate modal fields
      document.getElementById('detail_reference').textContent = doc.reference_number;
      document.getElementById('detail_document_type').textContent = doc.name;
      
      // Format and display status
      const statusBadges = {
        'pending': '<span class="badge bg-warning">Pending</span>',
        'approved': '<span class="badge bg-info">Approved</span>',
        'ready': '<span class="badge bg-primary">Ready for Pickup</span>',
        'completed': '<span class="badge bg-success">Completed</span>',
        'rejected': '<span class="badge bg-danger">Rejected</span>'
      };
      document.getElementById('detail_status').innerHTML = statusBadges[doc.status] || doc.status;
      
      // Format and display payment status
      const paymentBadges = {
        'unpaid': '<span class="badge bg-danger">Unpaid</span>',
        'pending': '<span class="badge bg-warning">Payment Pending Verification</span>',
        'paid': '<span class="badge bg-success">Paid</span>'
      };
      document.getElementById('detail_payment_status').innerHTML = paymentBadges[doc.payment_status] || doc.payment_status;
      
      // Display amount
      document.getElementById('detail_amount').textContent = '₱' + parseFloat(doc.fee).toFixed(2);
      
      // Format and display dates
      document.getElementById('detail_created_at').textContent = new Date(doc.created_at).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
      
      document.getElementById('detail_updated_at').textContent = new Date(doc.updated_at).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
      
      // Set next steps based on status and payment
      let nextSteps = '';
      const pickupInfo = document.getElementById('detail_pickup_info');
      pickupInfo.style.display = 'none';
      
      if (doc.status === 'pending') {
        nextSteps = 'Your request is being reviewed by our staff. You will be notified once it has been approved.';
      } else if (doc.status === 'approved' && doc.payment_status === 'unpaid') {
        nextSteps = 'Your request has been approved! Please proceed with payment to continue processing your document.';
      } else if (doc.payment_status === 'pending') {
        nextSteps = 'Your payment is being verified by our staff. Once confirmed, your document will be processed.';
      } else if (doc.status === 'approved' && doc.payment_status === 'paid') {
        nextSteps = 'Payment received! Your document is being processed and will be ready for pickup soon.';
      } else if (doc.status === 'ready') {
        nextSteps = 'Your document is ready for pickup! Please visit our office during business hours.';
        pickupInfo.style.display = 'block';
      } else if (doc.status === 'completed') {
        nextSteps = 'This document request has been completed. Thank you for using our service!';
      } else if (doc.status === 'rejected') {
        nextSteps = 'Unfortunately, your request has been rejected. Please contact the office for more information.';
      }
      
      document.getElementById('detail_next_steps').textContent = nextSteps;
      
      // Show the modal
      const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
      modal.show();
    }

    // Payment account details data
    const paymentAccountsData = <?php echo json_encode($paymentAccounts); ?>;

    // Function to populate payment account details
    function showPaymentDetails() {
      const method = document.getElementById('payment_method').value;
      const transactionRefField = document.getElementById('transaction_reference');
      const paymentProofField = document.getElementById('payment_proof');
      const transactionRefContainer = transactionRefField.closest('.mb-3');
      const paymentProofContainer = paymentProofField.closest('.mb-3');
      
      // Hide all detail sections
      document.getElementById('bankDetails').style.display = 'none';
      document.getElementById('gcashDetails').style.display = 'none';
      document.getElementById('paymayaDetails').style.display = 'none';
      document.getElementById('counterDetails').style.display = 'none';
      
      if (!method) {
        // When no method selected, keep fields visible (not hidden)
        transactionRefField.required = false;
        transactionRefContainer.style.display = 'block';
        paymentProofField.required = true;
        paymentProofContainer.style.display = 'block';
        return;
      }
      
      // For cash/over_counter payments, don't require reference number or proof
      if (method === 'over_counter' || method === 'cash') {
        document.getElementById('counterDetails').style.display = 'block';
        transactionRefField.required = false;
        transactionRefContainer.style.display = 'none';
        paymentProofField.required = false;
        paymentProofContainer.style.display = 'none';
        return;
      }
      
      // For online payments, require reference and proof
      transactionRefField.required = true;
      transactionRefContainer.style.display = 'block';
      paymentProofField.required = true;
      paymentProofContainer.style.display = 'block';
      
      // Show the appropriate section and populate with account details
      if (method === 'bank_transfer' && paymentAccountsData['bank_transfer']) {
        const accounts = paymentAccountsData['bank_transfer'];
        let html = '';
        accounts.forEach(acc => {
          html += `<strong>${acc.account_name}:</strong><br>
                   <strong>Account Number:</strong> ${acc.account_number}<br>
                   <strong>Account Holder:</strong> ${acc.account_holder}`;
          if (acc.branch_name) html += `<br><strong>Branch:</strong> ${acc.branch_name}`;
          if (acc.instructions) html += `<br><strong>Instructions:</strong> ${acc.instructions}`;
          html += '<br><br>';
        });
        document.getElementById('bankDetailsContent').innerHTML = html;
        document.getElementById('bankDetails').style.display = 'block';
      } else if (method === 'gcash' && paymentAccountsData['gcash']) {
        const accounts = paymentAccountsData['gcash'];
        let html = '';
        accounts.forEach(acc => {
          html += `<strong>Account Name:</strong> ${acc.account_number}<br>`;
          if (acc.instructions) html += `<strong>Note:</strong> ${acc.instructions}<br>`;
        });
        document.getElementById('gcashDetailsContent').innerHTML = html;
        document.getElementById('gcashDetails').style.display = 'block';
      } else if (method === 'paymaya' && paymentAccountsData['paymaya']) {
        const accounts = paymentAccountsData['paymaya'];
        let html = '';
        accounts.forEach(acc => {
          html += `<strong>Account Name:</strong> ${acc.account_number}<br>`;
          if (acc.instructions) html += `<strong>Note:</strong> ${acc.instructions}<br>`;
        });
        document.getElementById('paymayaDetailsContent').innerHTML = html;
        document.getElementById('paymayaDetails').style.display = 'block';
      }
    }
  </script>
</body>
</html>
<?php
closeDBConnection($conn);
?>
