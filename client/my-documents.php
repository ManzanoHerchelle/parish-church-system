<?php
session_start();

use Services\DocumentService;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user role is client
if ($_SESSION['role'] !== 'client') {
    header('Location: ../admin/dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Services/DocumentService.php';
require_once __DIR__ . '/../src/UI/Layouts/ClientLayout.php';
require_once __DIR__ . '/../src/UI/Components/ClientComponents.php';

$userId = $_SESSION['user_id'];
$conn = getDBConnection();
$documentService = new DocumentService($conn);

// Handle cancellation (only for pending documents)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $docId = $_POST['document_id'] ?? null;
    if ($docId) {
        // Verify ownership
        $doc = $documentService->getDocumentById($docId);
        if ($doc && $doc['user_id'] == $userId && $doc['status'] === 'pending') {
            $stmt = $conn->prepare("UPDATE document_requests SET status = 'cancelled' WHERE id = ?");
            $stmt->bind_param("i", $docId);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Document request cancelled successfully.';
            }
        }
    }
    header('Location: my-documents.php');
    exit;
}

// Get filter
$statusFilter = $_GET['status'] ?? 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build filter options
$filterOptions = ['user_id' => $userId, 'limit' => $perPage, 'offset' => $offset];
if ($statusFilter !== 'all') {
    $filterOptions['status'] = $statusFilter;
}

// Get documents
$documents = $documentService->getDocuments($filterOptions);

// Get total count for pagination
if ($statusFilter !== 'all') {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM document_requests WHERE user_id = ? AND status = ?");
    $stmt->bind_param("is", $userId, $statusFilter);
    $stmt->execute();
    $totalRecords = $stmt->get_result()->fetch_assoc()['total'];
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM document_requests WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $totalRecords = $stmt->get_result()->fetch_assoc()['total'];
}
$totalPages = ceil($totalRecords / $perPage);

// Build content
ob_start();
?>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> <?= $_SESSION['success_message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success_message']); endif; ?>

<!-- Status Filter Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === 'all' ? 'active' : '' ?>" href="?status=all">
            All Documents
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="?status=pending">
            <i class="bi bi-clock"></i> Pending
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === 'processing' ? 'active' : '' ?>" href="?status=processing">
            <i class="bi bi-hourglass-split"></i> Processing
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === 'ready' ? 'active' : '' ?>" href="?status=ready">
            <i class="bi bi-check-circle"></i> Ready
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === 'completed' ? 'active' : '' ?>" href="?status=completed">
            <i class="bi bi-check-all"></i> Completed
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === 'rejected' ? 'active' : '' ?>" href="?status=rejected">
            <i class="bi bi-x-circle"></i> Rejected
        </a>
    </li>
</ul>

<!-- Document List -->
<?php if (count($documents) > 0): ?>
    <?php foreach ($documents as $doc): ?>
        <?php 
        $card = new ClientDocumentCard($doc);
        echo $card->render();
        ?>
    <?php endforeach; ?>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?status=<?= $statusFilter ?>&page=<?= $page - 1 ?>">Previous</a>
            </li>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?status=<?= $statusFilter ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="?status=<?= $statusFilter ?>&page=<?= $page + 1 ?>">Next</a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
<?php else: ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox display-1 text-muted"></i>
            <h5 class="mt-3">No Documents Found</h5>
            <p class="text-muted">You haven't requested any documents yet.</p>
            <a href="request-document.php" class="btn btn-primary">
                <i class="bi bi-file-earmark-plus"></i> Request Document
            </a>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();

// Render layout
$layout = new ClientLayout('My Documents', 'My Document Requests', 'documents');
$layout->setContent($content);
echo $layout->render();

closeDBConnection($conn);
