<?php
/**
 * Admin: Manage System Logos
 * Upload, edit, delete, and archive system logos
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../src/UI/Layouts/AdminLayout.php';

startSecureSession();

// Check if user is logged in and is admin
$userRole = $_SESSION['user_role'] ?? 'guest';
if (!isset($_SESSION['user_id']) || $userRole !== 'admin') {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$conn = getDBConnection();
$statusMessage = '';
$statusType = 'success';
$userId = $_SESSION['user_id'];

// Create table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS system_logos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255),
    description TEXT,
    is_active TINYINT(1) DEFAULT 0,
    is_archived TINYINT(1) DEFAULT 0,
    display_order INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_is_active (is_active),
    INDEX idx_is_archived (is_archived),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB";

if (!$conn->query($createTableSQL)) {
    $statusMessage = 'Error creating system_logos table: ' . $conn->error;
    $statusType = 'error';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'upload_logo':
            if (!isset($_FILES['logo_image']) || $_FILES['logo_image']['error'] !== UPLOAD_ERR_OK) {
                $statusMessage = 'Please select a valid image file';
                $statusType = 'error';
                break;
            }
            
            $name = trim($_POST['logo_name']);
            $alt_text = trim($_POST['alt_text']);
            $description = trim($_POST['logo_description']);
            
            if (empty($name)) {
                $statusMessage = 'Logo name is required';
                $statusType = 'error';
                break;
            }
            
            // Validate file
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $maxSize = 10 * 1024 * 1024; // 10MB
            
            $fileType = $_FILES['logo_image']['type'];
            $fileSize = $_FILES['logo_image']['size'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $statusMessage = 'Invalid file type. Only JPG, PNG, GIF, WebP, and SVG are allowed';
                $statusType = 'error';
                break;
            }
            
            if ($fileSize > $maxSize) {
                $statusMessage = 'File too large. Maximum size is 10MB';
                $statusType = 'error';
                break;
            }
            
            // Create upload directory
            $uploadDir = __DIR__ . '/../uploads/logos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($_FILES['logo_image']['name'], PATHINFO_EXTENSION);
            $fileName = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $targetPath = $uploadDir . $fileName;
            
            if (!move_uploaded_file($_FILES['logo_image']['tmp_name'], $targetPath)) {
                $statusMessage = 'Failed to upload file. Please try again';
                $statusType = 'error';
                break;
            }
            
            $relativeFilePath = 'uploads/logos/' . $fileName;
            
            // Insert into database
            $stmt = $conn->prepare("INSERT INTO system_logos (name, file_path, alt_text, description, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssi', $name, $relativeFilePath, $alt_text, $description, $userId);
            
            if ($stmt->execute()) {
                $statusMessage = 'Logo uploaded successfully';
                $statusType = 'success';
            } else {
                $statusMessage = 'Failed to save logo to database: ' . $stmt->error;
                $statusType = 'error';
            }
            $stmt->close();
            break;
            
        case 'edit_logo':
            $logoId = intval($_POST['logo_id']);
            $name = trim($_POST['logo_name']);
            $alt_text = trim($_POST['alt_text']);
            $description = trim($_POST['logo_description']);
            
            if (empty($name)) {
                $statusMessage = 'Logo name is required';
                $statusType = 'error';
                break;
            }
            
            $stmt = $conn->prepare("UPDATE system_logos SET name = ?, alt_text = ?, description = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('sssi', $name, $alt_text, $description, $logoId);
            
            if ($stmt->execute()) {
                $statusMessage = 'Logo updated successfully';
                $statusType = 'success';
            } else {
                $statusMessage = 'Failed to update logo: ' . $stmt->error;
                $statusType = 'error';
            }
            $stmt->close();
            break;
            
        case 'set_active_logo':
            $logoId = intval($_POST['logo_id']);
            
            // Deactivate all other logos first
            $conn->query("UPDATE system_logos SET is_active = 0 WHERE is_archived = 0");
            
            // Activate this logo
            $stmt = $conn->prepare("UPDATE system_logos SET is_active = 1, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $logoId);
            
            if ($stmt->execute()) {
                $statusMessage = 'Logo set as active';
                $statusType = 'success';
            } else {
                $statusMessage = 'Failed to set active logo: ' . $stmt->error;
                $statusType = 'error';
            }
            $stmt->close();
            break;
            
        case 'archive_logo':
            $logoId = intval($_POST['logo_id']);
            
            $stmt = $conn->prepare("UPDATE system_logos SET is_archived = 1, is_active = 0, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $logoId);
            
            if ($stmt->execute()) {
                $statusMessage = 'Logo archived successfully';
                $statusType = 'success';
            } else {
                $statusMessage = 'Failed to archive logo: ' . $stmt->error;
                $statusType = 'error';
            }
            $stmt->close();
            break;
            
        case 'restore_logo':
            $logoId = intval($_POST['logo_id']);
            
            $stmt = $conn->prepare("UPDATE system_logos SET is_archived = 0, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $logoId);
            
            if ($stmt->execute()) {
                $statusMessage = 'Logo restored successfully';
                $statusType = 'success';
            } else {
                $statusMessage = 'Failed to restore logo: ' . $stmt->error;
                $statusType = 'error';
            }
            $stmt->close();
            break;
            
        case 'delete_logo':
            $logoId = intval($_POST['logo_id']);
            
            // Get file path
            $result = $conn->query("SELECT file_path FROM system_logos WHERE id = $logoId");
            $logo = $result->fetch_assoc();
            
            if ($logo && file_exists(__DIR__ . '/../' . $logo['file_path'])) {
                unlink(__DIR__ . '/../' . $logo['file_path']);
            }
            
            // Delete from database
            $stmt = $conn->prepare("DELETE FROM system_logos WHERE id = ?");
            $stmt->bind_param('i', $logoId);
            
            if ($stmt->execute()) {
                $statusMessage = 'Logo deleted successfully';
                $statusType = 'success';
            } else {
                $statusMessage = 'Failed to delete logo: ' . $stmt->error;
                $statusType = 'error';
            }
            $stmt->close();
            break;
    }
}

// Get all logos
$logosResult = $conn->query("SELECT sl.*, u.first_name, u.last_name FROM system_logos sl JOIN users u ON sl.created_by = u.id ORDER BY sl.is_archived ASC, sl.is_active DESC, sl.display_order ASC");
$logos = $logosResult->fetch_all(MYSQLI_ASSOC);

// Build the content for the layout
$content = '';

// Status message
if (!empty($statusMessage)) {
    $alertType = $statusType === 'success' ? 'success' : 'danger';
    $content .= '<div class="alert alert-' . $alertType . ' alert-dismissible fade show" role="alert">
        ' . htmlspecialchars($statusMessage) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

// Upload form and preview
$content .= '
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Upload New Logo</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_logo">
                    
                    <div class="mb-3">
                        <label for="logo_name" class="form-label">Logo Name *</label>
                        <input type="text" class="form-control" id="logo_name" name="logo_name" required>
                        <small class="form-text text-muted">e.g., Main Logo, Footer Logo, Header Logo</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="alt_text" class="form-label">Alt Text</label>
                        <input type="text" class="form-control" id="alt_text" name="alt_text" placeholder="For accessibility">
                    </div>
                    
                    <div class="mb-3">
                        <label for="logo_description" class="form-label">Description</label>
                        <textarea class="form-control" id="logo_description" name="logo_description" rows="3" placeholder="Optional description"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="logo_image" class="form-label">Image File *</label>
                        <input type="file" class="form-control" id="logo_image" name="logo_image" accept="image/*" required>
                        <small class="form-text text-muted">Supported formats: JPG, PNG, GIF, WebP, SVG. Max 10MB</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload Logo
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Preview</h5>
            </div>
            <div class="card-body text-center" id="previewContainer" style="min-height: 300px; display: flex; align-items: center; justify-content: center;">
                <p class="text-muted">Image preview will appear here</p>
            </div>
        </div>
    </div>
</div>
';

// Logos table
$content .= '
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">All Logos</h5>
            </div>
            <div class="card-body p-0">';

if (empty($logos)) {
    $content .= '<p class="text-muted p-4 mb-0">No logos uploaded yet</p>';
} else {
    $content .= '<div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Preview</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($logos as $logo) {
        $statusBadge = '';
        if ($logo['is_archived']) {
            $statusBadge = '<span class="badge bg-secondary">Archived</span>';
        } elseif ($logo['is_active']) {
            $statusBadge = '<span class="badge bg-success">Active</span>';
        } else {
            $statusBadge = '<span class="badge bg-warning">Inactive</span>';
        }
        
        $content .= '<tr>
            <td>
                <img src="/documentSystem/' . htmlspecialchars($logo['file_path']) . '" 
                     alt="' . htmlspecialchars($logo['alt_text']) . '" 
                     style="max-width: 60px; max-height: 60px; object-fit: contain;">
            </td>
            <td>
                <strong>' . htmlspecialchars($logo['name']) . '</strong>
            </td>
            <td>
                ' . htmlspecialchars(substr($logo['description'], 0, 50)) . (strlen($logo['description']) > 50 ? '...' : '') . '
            </td>
            <td>
                ' . $statusBadge . '
            </td>
            <td>
                ' . htmlspecialchars($logo['first_name'] . ' ' . $logo['last_name']) . '
            </td>
            <td>
                ' . date('M d, Y', strtotime($logo['created_at'])) . '
            </td>
            <td>
                <div class="btn-group btn-group-sm" role="group">';
        
        // Edit button
        $content .= '<button type="button" class="btn btn-info" data-bs-toggle="modal" 
                        data-bs-target="#editModal" 
                        onclick="loadEditForm(' . $logo['id'] . ', \'' . htmlspecialchars($logo['name'], ENT_QUOTES) . '\', \'' . htmlspecialchars($logo['alt_text'], ENT_QUOTES) . '\', \'' . htmlspecialchars($logo['description'], ENT_QUOTES) . '\')">
                    <i class="fas fa-edit"></i>
                </button>';
        
        if (!$logo['is_archived']) {
            if (!$logo['is_active']) {
                $content .= '<form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="set_active_logo">
                    <input type="hidden" name="logo_id" value="' . $logo['id'] . '">
                    <button type="submit" class="btn btn-success" title="Set as Active">
                        <i class="fas fa-check"></i>
                    </button>
                </form>';
            }
            
            $content .= '<form method="POST" style="display: inline;" onsubmit="return confirm(\'Archive this logo?\');">
                <input type="hidden" name="action" value="archive_logo">
                <input type="hidden" name="logo_id" value="' . $logo['id'] . '">
                <button type="submit" class="btn btn-warning" title="Archive">
                    <i class="fas fa-archive"></i>
                </button>
            </form>';
        } else {
            $content .= '<form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="restore_logo">
                <input type="hidden" name="logo_id" value="' . $logo['id'] . '">
                <button type="submit" class="btn btn-info" title="Restore">
                    <i class="fas fa-undo"></i>
                </button>
            </form>';
        }
        
        $content .= '<form method="POST" style="display: inline;" onsubmit="return confirm(\'Delete this logo permanently?\');">
            <input type="hidden" name="action" value="delete_logo">
            <input type="hidden" name="logo_id" value="' . $logo['id'] . '">
            <button type="submit" class="btn btn-danger" title="Delete">
                <i class="fas fa-trash"></i>
            </button>
        </form>
                </div>
            </td>
        </tr>';
    }
    
    $content .= '</tbody>
        </table>
    </div>';
}

$content .= '</div>
        </div>
    </div>
</div>

<!-- Edit Logo Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Logo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_logo">
                    <input type="hidden" name="logo_id" id="edit_logo_id">
                    
                    <div class="mb-3">
                        <label for="edit_logo_name" class="form-label">Logo Name *</label>
                        <input type="text" class="form-control" id="edit_logo_name" name="logo_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_alt_text" class="form-label">Alt Text</label>
                        <input type="text" class="form-control" id="edit_alt_text" name="alt_text">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_logo_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_logo_description" name="logo_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Image preview
document.getElementById("logo_image")?.addEventListener("change", function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewContainer = document.getElementById("previewContainer");
            previewContainer.innerHTML = `<img src="${e.target.result}" style="max-width: 100%; max-height: 300px; object-fit: contain;">`;
        };
        reader.readAsDataURL(file);
    }
});

// Load edit form
function loadEditForm(logoId, name, altText, description) {
    document.getElementById("edit_logo_id").value = logoId;
    document.getElementById("edit_logo_name").value = name;
    document.getElementById("edit_alt_text").value = altText;
    document.getElementById("edit_logo_description").value = description;
}
</script>
';

// Create layout and render
$layout = new AdminLayout('Manage Logos', 'Manage Logos', 'image');
$layout->setContent($content);
echo $layout->render();
