<?php
/**
 * Base Page Layout
 * Main layout wrapper for all admin pages
 */

class AdminLayout {
    private $title = '';
    private $pageTitle = '';
    private $pageIcon = '';
    private $breadcrumbs = [];
    private $content = '';
    private $userRole = 'admin';
    private $userName = '';
    private $notificationCount = 0;
    
    public function __construct($title, $pageTitle, $pageIcon = 'house') {
        $this->title = $title;
        $this->pageTitle = $pageTitle;
        $this->pageIcon = $pageIcon;
        $this->userName = $_SESSION['user_name'] ?? ($_SESSION['first_name'] ?? 'User');
        $this->userRole = $_SESSION['user_role'] ?? 'staff';
    }
    
    public function setContent($content) {
        $this->content = $content;
        return $this;
    }
    
    public function addBreadcrumb($label, $url = null) {
        $this->breadcrumbs[] = ['label' => $label, 'url' => $url];
        return $this;
    }
    
    public function setNotificationCount($count) {
        $this->notificationCount = $count;
        return $this;
    }
    
    public function render() {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($this->title); ?> - Parish Church System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/documentSystem/assets/css/common.css" rel="stylesheet">
    <link href="/documentSystem/assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <?php echo $this->renderSidebar(); ?>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="content-area">
            <h1 class="page-title"><?php echo htmlspecialchars(strtoupper($this->pageTitle)); ?></h1>
            <div class="title-underline"></div>

            <!-- Content -->
            <?php echo $this->content; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/documentSystem/assets/js/common.js"></script>
</body>
</html>
        <?php
        return ob_get_clean();
    }
    
    private function renderHeader() {
        $breadcrumbHtml = '';
        if (!empty($this->breadcrumbs)) {
            $breadcrumbHtml = '<nav aria-label="breadcrumb" class="mt-3"><ol class="breadcrumb">';
            foreach ($this->breadcrumbs as $crumb) {
                if ($crumb['url']) {
                    $breadcrumbHtml .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($crumb['url']) . '">' . htmlspecialchars($crumb['label']) . '</a></li>';
                } else {
                    $breadcrumbHtml .= '<li class="breadcrumb-item active">' . htmlspecialchars($crumb['label']) . '</li>';
                }
            }
            $breadcrumbHtml .= '</ol></nav>';
        }
        
        return <<<HTML
<header class="top-header">
    <div class="header-left">
        <h2><i class="bi bi-$this->pageIcon"></i> {$this->pageTitle}</h2>
        {$breadcrumbHtml}
    </div>
    <div class="header-right">
        <span class="user-greeting">Welcome, {$this->userName}</span>
    </div>
</header>
HTML;
    }
    
    private function renderSidebar() {
        // Generate user initials
        $nameParts = explode(' ', $this->userName);
        $userInitials = strtoupper(
            substr($nameParts[0], 0, 1) . 
            (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : '')
        );
        $userEmail = $_SESSION['user_email'] ?? '';
        $isAdmin = ($this->userRole === 'admin');
        
        // Get active logo
        $activeLogo = null;
        try {
            $conn = getDBConnection();
            $tableCheck = $conn->query("SELECT 1 FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA='parish_church_system' AND TABLE_NAME='system_logos'");
            
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $logoResult = $conn->query("SELECT file_path, alt_text, name FROM system_logos WHERE is_active = 1 AND is_archived = 0 LIMIT 1");
                if ($logoResult && $logoResult->num_rows > 0) {
                    $activeLogo = $logoResult->fetch_assoc();
                }
            }
            closeDBConnection($conn);
        } catch (Exception $e) {
            // Logo fetch failed, continue without it
        }
        
        // Prepare logo HTML
        $logoHTML = '';
        if ($activeLogo) {
            $logoHTML = '<img src="/documentSystem/' . htmlspecialchars($activeLogo['file_path']) . '" 
                        alt="' . htmlspecialchars($activeLogo['alt_text'] ?: $activeLogo['name']) . '" 
                        style="max-width: 120px; max-height: 120px; object-fit: contain; border-radius: 50%;">';
        } else {
            $logoHTML = '<div class="logo-circle">PC</div>';
        }
        
        return <<<HTML
<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo-circles">
            {$logoHTML}
        </div>
        <div class="system-title">
            Parish Ease: Admin Panel
        </div>
    </div>

    <!-- User Profile Card -->
    <div class="user-profile-card">
        <div class="user-avatar">{$userInitials}</div>
        <div class="user-name">{$this->userName}</div>
        <div class="user-email">{$userEmail}</div>
        <span style="font-size: 10px; color: #ffc107; text-transform: uppercase; font-weight: bold;">
            {$this->userRole}
        </span>
    </div>

    <ul class="nav-menu">
        <li class="nav-item">
            <a href="/documentSystem/admin/dashboard.php" class="nav-link">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="/documentSystem/admin/manage-documents.php" class="nav-link" id="nav-documents">
                <i class="bi bi-file-earmark-check"></i>
                <span>Manage Documents</span>
                <span class="nav-badge" id="badge-documents" style="display:none;"></span>
            </a>
        </li>

        <li class="nav-item">
            <a href="/documentSystem/admin/manage-appointments.php" class="nav-link" id="nav-appointments">
                <i class="bi bi-calendar-check"></i>
                <span>Manage Appointments</span>
                <span class="nav-badge" id="badge-appointments" style="display:none;"></span>
            </a>
        </li>

        <li class="nav-item">
            <a href="/documentSystem/admin/manage-payments.php" class="nav-link" id="nav-payments">
                <i class="bi bi-credit-card"></i>
                <span>Manage Payments</span>
                <span class="nav-badge" id="badge-payments" style="display:none;"></span>
            </a>
        </li>

        <li class="nav-item">
            <a href="/documentSystem/admin/manage-users.php" class="nav-link">
                <i class="bi bi-people"></i>
                <span>Manage Users</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="/documentSystem/admin/manage-logos.php" class="nav-link">
                <i class="bi bi-image"></i>
                <span>Manage Logos</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="/documentSystem/admin/system-settings.php" class="nav-link">
                <i class="bi bi-gear"></i>
                <span>System Settings</span>
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

<script>
// Load and update notification counts
function loadNotificationCounts() {
    fetch('/documentSystem/api/get-admin-notification-counts.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update document badge
                const docBadge = document.getElementById('badge-documents');
                if (data.pending_documents > 0) {
                    docBadge.textContent = data.pending_documents;
                    docBadge.style.display = 'inline';
                } else {
                    docBadge.style.display = 'none';
                }
                
                // Update appointment badge
                const aptBadge = document.getElementById('badge-appointments');
                if (data.pending_bookings > 0) {
                    aptBadge.textContent = data.pending_bookings;
                    aptBadge.style.display = 'inline';
                } else {
                    aptBadge.style.display = 'none';
                }
                
                // Update payment badge
                const payBadge = document.getElementById('badge-payments');
                if (data.pending_payments > 0) {
                    payBadge.textContent = data.pending_payments;
                    payBadge.style.display = 'inline';
                } else {
                    payBadge.style.display = 'none';
                }
            }
        })
        .catch(error => console.error('Error loading notification counts:', error));
}

// Load counts on page load
document.addEventListener('DOMContentLoaded', function() {
    loadNotificationCounts();
    // Refresh every 30 seconds
    setInterval(loadNotificationCounts, 30000);
});
</script>
HTML;
    }
}
?>
