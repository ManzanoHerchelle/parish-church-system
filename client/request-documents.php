<?php
/**
 * Client - Request Church Documents
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../handlers/email_handler.php';

startSecureSession();

require_once __DIR__ . '/../includes/client_nav_helper.php';

// Require login
if (!isLoggedIn()) {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$userId = getCurrentUserId();
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = getCurrentUserEmail();

// Initialize variables
$conn = getDBConnection();
$errorMsg = '';
$successMsg = '';
$formSubmitted = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documentTypeId = $_POST['document_type'] ?? null;
    $purpose = $_POST['purpose'] ?? '';
    $additionalNotes = $_POST['additional_notes'] ?? '';
    
    // Validate inputs
    if (!$documentTypeId) {
        $errorMsg = 'Please select a document type.';
    } elseif (empty($purpose)) {
        $errorMsg = 'Please specify the purpose of the request.';
    } else {
        // Generate reference number
        $refNumber = 'DOC-' . strtoupper(substr(md5(uniqid()), 0, 8)) . '-' . time();
        
        // Insert document request
        $insertQuery = "INSERT INTO document_requests 
            (user_id, document_type_id, reference_number, purpose, additional_notes, status, payment_status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', 'unpaid', NOW())";
        
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("iisss", $userId, $documentTypeId, $refNumber, $purpose, $additionalNotes);
        
        if ($stmt->execute()) {
            $requestId = $conn->insert_id;
            
            // Handle file uploads
            $uploadDir = __DIR__ . '/../uploads/attachments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['tmp_name'])) {
                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {
                    if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                        $fileName = $_FILES['attachments']['name'][$key];
                        $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
                        $safeFileName = $requestId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
                        $filePath = $uploadDir . $safeFileName;
                        
                        if (move_uploaded_file($tmpName, $filePath)) {
                            $attachQuery = "INSERT INTO document_attachments 
                                (document_request_id, file_name, file_path, file_type)
                                VALUES (?, ?, ?, ?)";
                            $attachStmt = $conn->prepare($attachQuery);
                            $attachStmt->bind_param("isss", $requestId, $fileName, $safeFileName, $fileExt);
                            $attachStmt->execute();
                            $attachStmt->close();
                        }
                    }
                }
            }
            
            // Get document type details
            $docQuery = "SELECT name, fee FROM document_types WHERE id = ?";
            $docStmt = $conn->prepare($docQuery);
            $docStmt->bind_param("i", $documentTypeId);
            $docStmt->execute();
            $docTypeData = $docStmt->get_result()->fetch_assoc();
            $docType = $docTypeData['name'];
            $docFee = $docTypeData['fee'];
            $docStmt->close();
            
            // Create payment record if fee > 0
            if ($docFee > 0) {
                // Generate unique transaction number
                $transactionNumber = 'PAY-' . strtoupper(substr(md5(uniqid()), 0, 8)) . '-' . time();
                
                $paymentQuery = "INSERT INTO payments 
                    (user_id, reference_type, reference_id, amount, payment_method, transaction_number, status, created_at)
                    VALUES (?, 'document', ?, ?, 'bank_transfer', ?, 'pending', NOW())";
                $paymentStmt = $conn->prepare($paymentQuery);
                $paymentStmt->bind_param("iids", $userId, $requestId, $docFee, $transactionNumber);
                $paymentStmt->execute();
                $paymentStmt->close();
            }
            
            // Send confirmation email
            $emailHandler = new EmailHandler();
            $nameParts = explode(' ', $userName);
            $firstName = $nameParts[0];
            $emailHandler->sendDocumentRequestConfirmation($userId, $userEmail, $firstName, $refNumber, $docType);
            
            // Create in-app notification
            $notifQuery = "INSERT INTO notifications (user_id, title, message, type, created_at)
                VALUES (?, ?, ?, ?, NOW())";
            $notifStmt = $conn->prepare($notifQuery);
            $notifTitle = "Document Request Received";
            $notifMsg = "Your request for $docType has been received. Reference: $refNumber";
            $notifType = "info";
            $notifStmt->bind_param("isss", $userId, $notifTitle, $notifMsg, $notifType);
            $notifStmt->execute();
            $notifStmt->close();
            
            $successMsg = "Document request submitted successfully! Reference Number: <strong>$refNumber</strong>";
            $formSubmitted = true;
            
            $stmt->close();
        } else {
            $errorMsg = 'Error submitting request. Please try again.';
        }
    }
}

// Get all document types
$docTypesQuery = "SELECT id, name, description, fee, processing_days, requirements FROM document_types WHERE is_active = 1 ORDER BY name";
$docTypesResult = $conn->query($docTypesQuery);
$docTypes = $docTypesResult->fetch_all(MYSQLI_ASSOC);

// Get user initials
$nameParts = explode(' ', $userName);
$userInitials = strtoupper(
    substr($nameParts[0], 0, 1) . 
    (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : substr($nameParts[0], 1, 1))
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Request Church Documents - Parish Church System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="/documentSystem/assets/css/common.css" rel="stylesheet">
  <link href="/documentSystem/assets/css/forms.css" rel="stylesheet">
  <style>
    .doc-type-card {
      cursor: pointer;
      transition: all 0.3s ease;
      border: 2px solid #ddd;
      margin-bottom: 15px;
      border-radius: 8px;
      padding: 15px;
    }
    .doc-type-card:hover {
      border-color: #3498db;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .doc-type-card.selected {
      border-color: #28a745 !important;
      background-color: #f0fff4 !important;
    }
    .file-list {
      list-style: none;
      padding: 0;
      margin-top: 15px;
    }
    .file-list li {
      padding: 8px;
      background: #f8f9fa;
      margin-bottom: 5px;
      border-radius: 4px;
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
      <li class="nav-item">
        <a href="/documentSystem/client/dashboard.php" class="nav-link">
          <i class="bi bi-house-door-fill"></i>
          <span>Home</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/client/view-documents.php" class="nav-link">
          <i class="bi bi-file-earmark-check"></i>
          <span>View Documents</span>
          <?php if (isset($stats) && $stats['pending_documents'] > 0): ?>
            <span class="nav-badge"><?php echo $stats['pending_documents']; ?></span>
          <?php endif; ?>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/client/view-appointments.php" class="nav-link">
          <i class="bi bi-calendar-check"></i>
          <span>View Appointments</span>
          <?php if (isset($stats) && $stats['pending_appointments'] > 0): ?>
            <span class="nav-badge"><?php echo $stats['pending_appointments']; ?></span>
          <?php endif; ?>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/client/new-appointment.php" class="nav-link">
          <i class="bi bi-calendar-plus"></i>
          <span>New Appointment</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/client/request-documents.php" class="nav-link active">
          <i class="bi bi-file-earmark-text"></i>
          <span>Request Document</span>
        </a>
      </li>
      
      <div class="nav-separator"></div>
      
      <li class="nav-item">
        <a href="/documentSystem/client/change-password.php" class="nav-link">
          <i class="bi bi-key-fill"></i>
          <span>Change Password</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/api/logout.php" class="nav-link">
          <i class="bi bi-box-arrow-right"></i>
          <span>Log Out</span>
        </a>
      </li>
    </ul>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="content-area">
      <h1 class="page-title">REQUEST CHURCH DOCUMENTS</h1>
      <div class="title-underline"></div>

      <?php if ($successMsg): ?>
        <div class="success-message">
          <div class="icon"><i class="bi bi-check-circle"></i></div>
          <h3>Request Submitted Successfully!</h3>
          <p>Your document request has been received and is being processed.</p>
          <div class="ref-number"><?php echo $successMsg; ?></div>
          <p style="margin-top: 15px; color: #666; font-size: 14px;">
            You will receive an email confirmation shortly. Check your email for updates on the status of your request.
          </p>
          <a href="/documentSystem/client/dashboard.php" class="btn-primary" style="display: inline-block; margin-top: 15px;">← Back to Dashboard</a>
        </div>
      <?php else: ?>
        <?php if ($errorMsg): ?>
          <div class="alert alert-danger">
            <strong>Error:</strong> <?php echo htmlspecialchars($errorMsg); ?>
          </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
          <!-- Select Document Type -->
          <div class="form-section">
            <div class="form-header">STEP 1: SELECT DOCUMENT TYPE</div>
            <div class="form-content">
              <div style="max-height: 600px; overflow-y: auto;">
                <?php foreach ($docTypes as $doc): ?>
                  <div class="doc-type-card" onclick="selectDocument(<?php echo $doc['id']; ?>)">
                    <input type="radio" name="document_type" value="<?php echo $doc['id']; ?>" style="display: none;" id="doc_<?php echo $doc['id']; ?>">
                    
                    <div class="doc-type-name">
                      <i class="bi bi-check-circle" style="margin-right: 8px; display: none; color: #28a745;" class="selection-icon"></i>
                      <?php echo htmlspecialchars($doc['name']); ?>
                    </div>
                    
                    <div class="doc-type-details">
                      <?php echo htmlspecialchars($doc['description']); ?>
                    </div>
                    
                    <div class="doc-type-fee">
                      Fee: ₱<?php echo number_format($doc['fee'], 2); ?> | Processing Time: <?php echo $doc['processing_days']; ?> days
                    </div>

                    <?php if (!empty($doc['requirements'])): ?>
                      <div class="requirements-list">
                        <strong><i class="bi bi-info-circle"></i> Requirements:</strong>
                        <ul>
                          <?php 
                            $reqs = explode(',', $doc['requirements']);
                            foreach ($reqs as $req): 
                          ?>
                            <li><?php echo trim(htmlspecialchars($req)); ?></li>
                          <?php endforeach; ?>
                        </ul>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Document Details -->
          <div class="form-section">
            <div class="form-header">STEP 2: PROVIDE DETAILS</div>
            <div class="form-content">
              <div class="form-group">
                <label class="form-label" for="purpose">Purpose of Request <span style="color: #dc3545;">*</span></label>
                <input type="text" class="form-control" id="purpose" name="purpose" placeholder="e.g., For employment, For travel, For school application" required>
                <small style="color: #666;">Please specify why you need this document</small>
              </div>

              <div class="form-group">
                <label class="form-label" for="additional_notes">Additional Notes</label>
                <textarea class="form-control" id="additional_notes" name="additional_notes" rows="4" placeholder="Any additional information you'd like to include..."></textarea>
              </div>
            </div>
          </div>

          <!-- Upload Supporting Documents -->
          <div class="form-section">
            <div class="form-header">STEP 3: UPLOAD SUPPORTING DOCUMENTS (OPTIONAL)</div>
            <div class="form-content">
              <p style="color: #666; margin-bottom: 20px;">
                Upload any documents needed to support your request (e.g., ID, birth certificate, etc.)
              </p>
              
              <div class="file-input-wrapper">
                <input type="file" id="attachments" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <label for="attachments" class="file-input-label">
                  <div style="text-align: center;">
                    <i class="bi bi-cloud-upload" style="font-size: 32px; color: #3d4f5c; margin-bottom: 10px;"></i>
                    <div style="font-weight: bold; color: #333;">Click to upload or drag and drop</div>
                    <small style="color: #666;">PDF, JPG, PNG, DOC, DOCX (Max 5MB per file)</small>
                  </div>
                </label>
              </div>

              <ul class="file-list" id="fileList"></ul>
            </div>
          </div>

          <!-- Submit -->
          <div style="margin-bottom: 30px;">
            <button type="submit" class="btn-primary">
              <i class="bi bi-check-lg"></i> SUBMIT REQUEST
            </button>
            <a href="/documentSystem/client/dashboard.php" class="btn-secondary" style="margin-left: 10px;">Cancel</a>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
      <div class="footer-content">
        <div class="footer-contact">
          <strong>FOR INQUIRIES:</strong><br>
          HOTLINE: 0999 MAYNAY<br>
          EMAIL: maequinas@gmail.com
        </div>
      </div>
      <div class="footer-bottom">
        Parish Church © <?php echo date('Y'); ?>
      </div>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/documentSystem/assets/js/common.js"></script>
  <script src="/documentSystem/assets/js/forms.js"></script>
  
  <script>
    // Document type selection
    function selectDocument(docId) {
      // Uncheck all radio buttons and remove selection styling
      document.querySelectorAll('.doc-type-card').forEach(card => {
        card.classList.remove('selected');
        card.style.borderColor = '#ddd';
        card.style.backgroundColor = '#fff';
        const icon = card.querySelector('.bi-check-circle');
        if (icon) icon.style.display = 'none';
      });
      
      // Check the selected radio button
      const radio = document.getElementById('doc_' + docId);
      if (radio) {
        radio.checked = true;
        
        // Add selection styling to the card
        const card = radio.closest('.doc-type-card');
        if (card) {
          card.classList.add('selected');
          card.style.borderColor = '#28a745';
          card.style.backgroundColor = '#f0fff4';
          const icon = card.querySelector('.bi-check-circle');
          if (icon) icon.style.display = 'inline';
        }
      }
    }
    
    // File upload preview
    document.getElementById('attachments')?.addEventListener('change', function(e) {
      const fileList = document.getElementById('fileList');
      fileList.innerHTML = '';
      
      if (this.files.length > 0) {
        Array.from(this.files).forEach((file, index) => {
          const li = document.createElement('li');
          li.innerHTML = `
            <i class="bi bi-file-earmark"></i> ${file.name} 
            <small>(${(file.size / 1024).toFixed(2)} KB)</small>
          `;
          fileList.appendChild(li);
        });
      }
    });
  </script>
</body>
</html>
<?php
closeDBConnection($conn);
?>

