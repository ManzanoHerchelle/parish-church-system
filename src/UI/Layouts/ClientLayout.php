<?php
class ClientLayout {
    private $pageTitle;
    private $pageHeading;
    private $content = '';
    private $activeNav = '';
    
    public function __construct($pageTitle = 'Client Portal', $pageHeading = '', $activeNav = '') {
        $this->pageTitle = $pageTitle;
        $this->pageHeading = $pageHeading ?: $pageTitle;
        $this->activeNav = $activeNav;
    }
    
    public function setContent($content) {
        $this->content = $content;
    }
    
    private function getNavbar() {
        $firstName = $_SESSION['first_name'] ?? 'User';
        
        return '
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container-fluid">
                <a class="navbar-brand" href="dashboard.php">
                    <i class="bi bi-church me-2"></i>Parish Church
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link ' . ($this->activeNav === 'dashboard' ? 'active' : '') . '" href="dashboard.php">
                                <i class="bi bi-house-door"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link ' . ($this->activeNav === 'documents' ? 'active' : '') . '" href="request-document.php">
                                <i class="bi bi-file-earmark-text"></i> Request Document
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link ' . ($this->activeNav === 'appointments' ? 'active' : '') . '" href="book-appointment.php">
                                <i class="bi bi-calendar-check"></i> Book Appointment
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link ' . ($this->activeNav === 'profile' ? 'active' : '') . '" href="profile.php">
                                <i class="bi bi-person"></i> Profile
                            </a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-bell"></i>
                                <span class="badge bg-danger" id="notificationCount">0</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" style="width: 300px; max-height: 400px; overflow-y: auto;">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <li><hr class="dropdown-divider"></li>
                                <div id="notificationList">
                                    <li><span class="dropdown-item-text text-muted">No new notifications</span></li>
                                </div>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> ' . htmlspecialchars($firstName) . '
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../api/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>';
    }
    
    private function getNotificationScript() {
        return "
        <script>
        function loadNotifications() {
            fetch('../api/get-notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const count = data.unread_count || 0;
                        const badge = document.getElementById('notificationCount');
                        if (badge) {
                            badge.textContent = count;
                            badge.style.display = count > 0 ? 'inline' : 'none';
                        }
                        
                        const list = document.getElementById('notificationList');
                        if (list && data.notifications.length > 0) {
                            list.innerHTML = data.notifications.map(notif => `
                                <li>
                                    <a class=\"dropdown-item \${notif.is_read == 0 ? 'bg-light' : ''}\" href=\"#\" onclick=\"markAsRead(\${notif.id})\">
                                        <div class=\"d-flex justify-content-between align-items-start\">
                                            <div class=\"flex-grow-1\">
                                                <small class=\"text-muted\">\${notif.created_at}</small>
                                                <p class=\"mb-0\">\${notif.message}</p>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            `).join('');
                        }
                    }
                })
                .catch(error => console.error('Error loading notifications:', error));
        }
        
        function markAsRead(notifId) {
            fetch('../api/mark-notification-read.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({notification_id: notifId})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                }
            });
        }
        
        // Load notifications on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications();
            // Refresh every 30 seconds
            setInterval(loadNotifications, 30000);
        });
        </script>";
    }
    
    public function render() {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($this->pageTitle) . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .navbar { box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .navbar-brand { font-weight: 600; font-size: 1.25rem; }
        .nav-link { padding: 0.5rem 1rem !important; }
        .nav-link.active { background-color: rgba(255,255,255,0.1); border-radius: 0.25rem; }
        #notificationCount { font-size: 0.75rem; padding: 0.2rem 0.4rem; margin-left: 0.25rem; }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .quick-action-card { 
            transition: transform 0.2s, box-shadow 0.2s; 
            cursor: pointer;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .quick-action-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stat-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    ' . $this->getNavbar() . '
    
    <div class="page-header">
        <div class="container">
            <h1 class="mb-0">' . htmlspecialchars($this->pageHeading) . '</h1>
        </div>
    </div>
    
    <div class="container mb-5">
        ' . $this->content . '
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    ' . $this->getNotificationScript() . '
</body>
</html>';
    }
}
