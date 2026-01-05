<?php
/**
 * Admin: Manage Documents
 * Interface for admin to view, approve, reject, and manage document requests
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../src/Services/DocumentService.php';
require_once __DIR__ . '/../src/Services/SearchService.php';
require_once __DIR__ . '/../src/UI/Layouts/AdminLayout.php';
require_once __DIR__ . '/../src/UI/Helpers/UIHelpers.php';

startSecureSession();

// Check if user is logged in and is admin/staff
$userRole = $_SESSION['user_role'] ?? 'guest';
if (!isset($_SESSION['user_id']) || !in_array($userRole, ['admin', 'staff'])) {
    header('Location: /documentSystem/client/login.php');
    exit;
}

use Services\DocumentService;
use Services\SearchService;

$documentService = new DocumentService();
$searchService = new SearchService();
$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Handle action requests (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $documentId = intval($_POST['document_id'] ?? 0);
    
    if ($documentId > 0) {
        switch ($action) {
            case 'approve':
                $doc = $documentService->getDocumentById($documentId);
                if (!$doc) {
                    header('Location: /documentSystem/admin/manage-documents.php?alert=error&message=Document+not+found');
                    exit;
                }
                if (($doc['payment_status'] ?? '') === 'unpaid') {
                    header('Location: /documentSystem/admin/manage-documents.php?alert=error&message=Cannot+approve+an+unpaid+document');
                    exit;
                }
                $documentService->approveDocument($documentId, $userId);
                header('Location: /documentSystem/admin/manage-documents.php?alert=success&message=Document+approved');
                exit;
                
            case 'reject':
                $reason = trim($_POST['reason'] ?? '');
                if (!$reason) {
                    header('Location: /documentSystem/admin/manage-documents.php?alert=error&message=Rejection+reason+required');
                    exit;
                }
                $documentService->rejectDocument($documentId, $userId, $reason);
                header('Location: /documentSystem/admin/manage-documents.php?alert=success&message=Document+rejected');
                exit;
                
            case 'mark_ready':
                $documentService->markAsReady($documentId, $userId);
                header('Location: /documentSystem/admin/manage-documents.php?alert=success&message=Document+marked+as+ready');
                exit;
                
            case 'mark_completed':
                $documentService->markAsCompleted($documentId, $userId);
                header('Location: /documentSystem/admin/manage-documents.php?alert=success&message=Document+marked+as+completed');
                exit;
        }
    }
}

// Get filter parameters
$keyword = $_GET['keyword'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$documentType = $_GET['document_type'] ?? '';
$paymentStatus = $_GET['payment_status'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$pageNum = intval($_GET['page'] ?? 1);
$limit = 10;
$offset = ($pageNum - 1) * $limit;

// Build search filters
$searchFilters = [
    'keyword' => $keyword,
    'status' => $statusFilter,
    'document_type' => $documentType,
    'payment_status' => $paymentStatus,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'limit' => $limit,
    'offset' => $offset
];

// Fetch documents using search service
$documents = $searchService->searchDocuments($searchFilters);
$totalCount = $searchService->countDocuments($searchFilters);
$totalPages = ceil($totalCount / $limit);

// Get document types for filter dropdown
$docTypesQuery = "SELECT id, name FROM document_types WHERE is_active = 1 ORDER BY name";
$docTypesResult = $conn->query($docTypesQuery);
$documentTypes = $docTypesResult->fetch_all(MYSQLI_ASSOC);

// Get status counts
$statusCounts = [
    'pending' => 0,
    'processing' => 0,
    'ready' => 0,
    'completed' => 0,
    'rejected' => 0
];
$statusQuery = "
    SELECT status, COUNT(*) as count 
    FROM document_requests 
    GROUP BY status
";
$result = $conn->query($statusQuery);
while ($row = $result->fetch_assoc()) {
    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']] = $row['count'];
    }
}

$statusMessage = $_GET['message'] ?? '';
$statusType = $_GET['alert'] ?? '';

closeDBConnection($conn);

// Build content
$content = '';

// Alert messages
if ($statusMessage) {
    $content .= alert(htmlspecialchars($statusMessage), 
                     ($statusType === 'success') ? 'success' : 'danger');
}

// Search and Advanced Filters Section
$content .= '
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bi bi-search"></i> Search & Filters</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label for="keyword" class="form-label">Search (Reference, Client Name, Email)</label>
                <input type="text" class="form-control" id="keyword" name="keyword" placeholder="Search..." value="' . htmlspecialchars($keyword) . '">
            </div>
            
            <div class="col-md-6">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">-- All Statuses --</option>
                    <option value="pending" ' . ($statusFilter === 'pending' ? 'selected' : '') . '>Pending</option>
                    <option value="processing" ' . ($statusFilter === 'processing' ? 'selected' : '') . '>Processing</option>
                    <option value="ready" ' . ($statusFilter === 'ready' ? 'selected' : '') . '>Ready</option>
                    <option value="completed" ' . ($statusFilter === 'completed' ? 'selected' : '') . '>Completed</option>
                    <option value="rejected" ' . ($statusFilter === 'rejected' ? 'selected' : '') . '>Rejected</option>
                </select>
            </div>
            
            <div class="col-md-6">
                <label for="document_type" class="form-label">Document Type</label>
                <select class="form-select" id="document_type" name="document_type">
                    <option value="">-- All Types --</option>';
                    
foreach ($documentTypes as $docType) {
    $selected = $documentType == $docType['id'] ? 'selected' : '';
    $content .= '<option value="' . $docType['id'] . '" ' . $selected . '>' . htmlspecialchars($docType['name']) . '</option>';
}

$content .= '
                </select>
            </div>
            
            <div class="col-md-6">
                <label for="payment_status" class="form-label">Payment Status</label>
                <select class="form-select" id="payment_status" name="payment_status">
                    <option value="">-- All Payment Status --</option>
                    <option value="unpaid" ' . ($paymentStatus === 'unpaid' ? 'selected' : '') . '>Unpaid</option>
                    <option value="pending" ' . ($paymentStatus === 'pending' ? 'selected' : '') . '>Pending</option>
                    <option value="paid" ' . ($paymentStatus === 'paid' ? 'selected' : '') . '>Paid</option>
                </select>
            </div>
            
            <div class="col-md-6">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="' . htmlspecialchars($startDate) . '">
            </div>
            
            <div class="col-md-6">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="' . htmlspecialchars($endDate) . '">
            </div>
            
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Search
                </button>
                <a href="manage-documents.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-clockwise"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>';

// Status filter tabs - Build dynamic filters array with counts
$allCount = array_sum($statusCounts);

$filtersArray = [
    'all' => 'All',
    'all_count' => $allCount,
    'pending' => 'Pending',
    'pending_count' => $statusCounts['pending'] ?? 0,
    'processing' => 'Processing',
    'processing_count' => $statusCounts['processing'] ?? 0,
    'ready' => 'Ready',
    'ready_count' => $statusCounts['ready'] ?? 0,
    'completed' => 'Completed',
    'completed_count' => $statusCounts['completed'] ?? 0,
    'rejected' => 'Rejected',
    'rejected_count' => $statusCounts['rejected'] ?? 0,
];

$filterComponent = new StatusFilterTabs($statusFilter, $filtersArray);
$content .= $filterComponent->render();

// Documents list
$documentsHtml = '';
if (empty($documents)) {
    $documentsHtml .= alert('No documents found', 'info', false);
} else {
    foreach ($documents as $doc) {
        $docCard = new DocumentCard($doc);
        $documentsHtml .= $docCard->render();
        
        // Add modals for this document
        $rejectModal = new RejectModal($doc['id']);
        $documentsHtml .= $rejectModal->render();
        
        $detailsModal = new DocumentDetailsModal($doc);
        $documentsHtml .= $detailsModal->render();
    }
}

$content .= card('Documents (' . count($documents) . ' of ' . $totalCount . ')', $documentsHtml);

// Pagination - build URL with all filters
if ($totalPages > 1) {
    $baseUrl = '?keyword=' . urlencode($keyword) . '&status=' . urlencode($statusFilter) . 
               '&document_type=' . urlencode($documentType) . '&payment_status=' . urlencode($paymentStatus) .
               '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate);
    $pagination = new Pagination($pageNum, $totalPages, $baseUrl);
    $content .= $pagination->render();
}

// Create layout and render
$layout = new AdminLayout('Manage Documents', 'Manage Documents', 'file-earmark');
$layout->setContent($content);

echo $layout->render();
?>
