<?php
/**
 * Staff Page Layout
 * Layout wrapper for all staff pages with limited functionality
 */

class StaffLayout {
    private $title = '';
    private $pageTitle = '';
    private $pageIcon = '';
    private $breadcrumbs = [];
    private $content = '';
    
    public function __construct($title, $pageTitle, $pageIcon = 'house') {
        $this->title = $title;
        $this->pageTitle = $pageTitle;
        $this->pageIcon = $pageIcon;
    }
    
    public static function header($title = '', $pageTitle = '', $pageIcon = 'house') {
        $layout = new self($title, $pageTitle, $pageIcon);
        $layout->renderHeader();
    }
    
    public function renderHeader() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo htmlspecialchars($this->title); ?> - Document Management System</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
            <link rel="stylesheet" href="/documentSystem/assets/css/style.css">
            <style>
                :root {
                    --primary-color: #6366f1;
                    --primary-dark: #4f46e5;
                    --primary-light: #818cf8;
                    --sidebar-bg: #1e293b;
                    --sidebar-text: #cbd5e1;
                }
                
                body {
                    background: #f8fafc;
                    color: #0f172a;
                }
                
                .sidebar {
                    background: var(--sidebar-bg);
                    color: var(--sidebar-text);
                    min-height: 100vh;
                    padding: 0;
                    position: fixed;
                    width: 260px;
                    left: 0;
                    top: 0;
                    z-index: 1000;
                }
                
                .sidebar-header {
                    padding: 20px;
                    border-bottom: 1px solid rgba(255,255,255,0.1);
                    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
                    color: white;
                }
                
                .sidebar-header h5 {
                    margin: 0;
                    font-weight: 600;
                    font-size: 16px;
                }
                
                .sidebar-header small {
                    color: rgba(255,255,255,0.7);
                    display: block;
                    margin-top: 5px;
                    font-size: 12px;
                }
                
                .sidebar-nav {
                    padding: 20px 0;
                }
                
                .sidebar-nav a {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 12px 20px;
                    color: var(--sidebar-text);
                    text-decoration: none;
                    transition: all 0.2s;
                    border-left: 3px solid transparent;
                }
                
                .sidebar-nav a:hover {
                    background: rgba(255,255,255,0.05);
                    color: white;
                    border-left-color: var(--primary-light);
                }
                
                .sidebar-nav a.active {
                    background: rgba(99, 102, 241, 0.1);
                    color: white;
                    border-left-color: var(--primary-light);
                    font-weight: 500;
                }
                
                .sidebar-nav a i {
                    width: 20px;
                    text-align: center;
                }
                
                .sidebar-nav .divider {
                    margin: 15px 0;
                    padding: 0;
                    border-top: 1px solid rgba(255,255,255,0.1);
                }
                
                .sidebar-nav .nav-label {
                    padding: 10px 20px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    color: rgba(255,255,255,0.5);
                    margin-top: 10px;
                }
                
                .main-content {
                    margin-left: 260px;
                    min-height: 100vh;
                    display: flex;
                    flex-direction: column;
                }
                
                .topbar {
                    background: white;
                    border-bottom: 1px solid #e2e8f0;
                    padding: 15px 30px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                }
                
                .topbar-left {
                    display: flex;
                    align-items: center;
                    gap: 20px;
                }
                
                .page-breadcrumb {
                    font-size: 12px;
                    color: #64748b;
                }
                
                .page-breadcrumb a {
                    color: var(--primary-color);
                    text-decoration: none;
                }
                
                .topbar-right {
                    display: flex;
                    align-items: center;
                    gap: 20px;
                }
                
                .user-info {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .user-avatar {
                    width: 36px;
                    height: 36px;
                    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-weight: 600;
                    font-size: 14px;
                }
                
                .page-content {
                    flex: 1;
                    padding: 30px;
                }
                
                .page-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 30px;
                }
                
                .page-title {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    margin: 0;
                }
                
                .page-title i {
                    font-size: 32px;
                    color: var(--primary-color);
                }
                
                .page-title h1 {
                    font-size: 28px;
                    font-weight: 600;
                    margin: 0;
                    color: #0f172a;
                }
                
                .card {
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                }
                
                .card-header {
                    background: #f8fafc;
                    border-bottom: 1px solid #e2e8f0;
                    padding: 15px 20px;
                    font-weight: 600;
                    color: #334155;
                }
                
                .badge-staff {
                    background: var(--primary-color);
                    color: white;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: 600;
                }
                
                @media (max-width: 768px) {
                    .sidebar {
                        width: 0;
                        overflow: hidden;
                    }
                    
                    .main-content {
                        margin-left: 0;
                    }
                }
            </style>
        </head>
        <body>
            <!-- Sidebar -->
            <nav class="sidebar">
                <div class="sidebar-header">
                    <h5><i class="bi bi-briefcase"></i> Staff Portal</h5>
                    <small><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?></small>
                </div>
                
                <div class="sidebar-nav">
                    <a href="/documentSystem/staff/dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                    
                    <div class="nav-label">Workflow</div>
                    <a href="/documentSystem/staff/process-documents.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'process-documents.php') ? 'active' : ''; ?>">
                        <i class="bi bi-file-earmark-check"></i>
                        <span>Process Documents</span>
                    </a>
                    
                    <a href="/documentSystem/staff/process-bookings.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'process-bookings.php') ? 'active' : ''; ?>">
                        <i class="bi bi-calendar-check"></i>
                        <span>Process Bookings</span>
                    </a>
                    
                    <a href="/documentSystem/staff/verify-payments.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'verify-payments.php') ? 'active' : ''; ?>">
                        <i class="bi bi-wallet-check"></i>
                        <span>Verify Payments</span>
                    </a>
                    
                    <div class="nav-label">Management</div>
                    <a href="/documentSystem/staff/activity-log.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'activity-log.php') ? 'active' : ''; ?>">
                        <i class="bi bi-clock-history"></i>
                        <span>Activity Log</span>
                    </a>
                    
                    <div class="divider"></div>
                    
                    <a href="/documentSystem/client/profile.php">
                        <i class="bi bi-person-circle"></i>
                        <span>My Profile</span>
                    </a>
                    
                    <a href="/documentSystem/api/logout.php" style="color: #ef4444;">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
            
            <!-- Main Content -->
            <div class="main-content">
                <!-- Topbar -->
                <div class="topbar">
                    <div class="topbar-left">
                        <div>
                            <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: #0f172a;">
                                <i class="bi bi-<?php echo htmlspecialchars($this->pageIcon); ?>"></i>
                                <?php echo htmlspecialchars($this->pageTitle); ?>
                            </h3>
                            <small class="page-breadcrumb" style="display: block; margin-top: 5px;">
                                <a href="/documentSystem/staff/dashboard.php">Staff Portal</a> / <?php echo htmlspecialchars($this->pageTitle); ?>
                            </small>
                        </div>
                    </div>
                    
                    <div class="topbar-right">
                        <div class="user-info">
                            <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'S', 0, 1)); ?></div>
                            <div>
                                <div style="font-size: 13px; font-weight: 600; color: #334155;">
                                    <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?>
                                </div>
                                <div style="font-size: 11px; color: #64748b;">Staff Account</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Page Content -->
                <div class="page-content">
        <?php
    }
    
    public static function footer() {
        ?>
                </div>
            </div>
            
            <!-- Scripts -->
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
            <script>
                // Auto-hide alerts after 5 seconds
                document.querySelectorAll('.alert').forEach(alert => {
                    setTimeout(() => {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }, 5000);
                });
            </script>
        </body>
        </html>
        <?php
    }
}
