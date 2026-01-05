<?php
/**
 * Client Dashboard
 */

require_once __DIR__ . '/../../includes/session.php';

startSecureSession();

// Require login
if (!isLoggedIn()) {
    header('Location: /documentSystem/client/login.php');
    exit;
}

// Get user info
$userId = getCurrentUserId();
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = getCurrentUserEmail();
$userRole = getCurrentUserRole();

// Redirect admin/staff to admin dashboard
if ($userRole === 'admin' || $userRole === 'staff') {
    header('Location: /documentSystem/admin/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard - Parish Church System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body {
      background: #f5f5f5;
    }

    .navbar {
      background: #3e5361;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .navbar-brand {
      font-weight: bold;
      font-size: 20px;
    }

    .nav-link {
      color: rgba(255,255,255,0.8) !important;
      transition: color 0.3s ease;
    }

    .nav-link:hover {
      color: white !important;
    }

    .nav-link.active {
      color: white !important;
      border-bottom: 2px solid white;
    }

    .sidebar {
      background: white;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .sidebar-item {
      padding: 15px 0;
      border-bottom: 1px solid #eee;
    }

    .sidebar-item:last-child {
      border-bottom: none;
    }

    .sidebar-label {
      font-size: 12px;
      text-transform: uppercase;
      color: #999;
      font-weight: bold;
    }

    .sidebar-value {
      font-size: 16px;
      color: #333;
      margin-top: 5px;
    }

    .dashboard-card {
      background: white;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .dashboard-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .card-icon {
      font-size: 32px;
      margin-bottom: 10px;
    }

    .card-title {
      font-size: 18px;
      font-weight: bold;
      color: #333;
      margin-bottom: 10px;
    }

    .card-description {
      font-size: 14px;
      color: #666;
      margin-bottom: 15px;
    }

    .btn-card {
      display: inline-block;
      padding: 8px 16px;
      background: #3e5361;
      color: white;
      border-radius: 4px;
      text-decoration: none;
      font-size: 14px;
      transition: background 0.3s ease;
    }

    .btn-card:hover {
      background: #2c3f4f;
      color: white;
    }

    .welcome-section {
      background: linear-gradient(135deg, #3e5361 0%, #2c3f4f 100%);
      color: white;
      padding: 30px;
      border-radius: 8px;
      margin-bottom: 30px;
    }

    .welcome-title {
      font-size: 28px;
      font-weight: bold;
      margin-bottom: 10px;
    }

    .welcome-subtitle {
      font-size: 16px;
      opacity: 0.9;
    }

    .footer {
      background: #333;
      color: white;
      padding: 20px 0;
      text-align: center;
      margin-top: 40px;
    }

    .status-badge {
      display: inline-block;
      padding: 4px 12px;
      background: #28a745;
      color: white;
      border-radius: 20px;
      font-size: 12px;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
      <a class="navbar-brand" href="/documentSystem/client/dashboard.php">
        <i class="bi bi-church"></i> ParishEase
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link active" href="/documentSystem/client/dashboard.php">Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#documents">Document Requests</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#bookings">Bookings</a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars(explode(' ', $userName)[0]); ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="#profile">My Profile</a></li>
              <li><a class="dropdown-item" href="#settings">Settings</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="/documentSystem/api/logout.php">Logout</a></li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="container-fluid py-4">
    <div class="row">
      <!-- Sidebar -->
      <div class="col-md-3">
        <div class="sidebar">
          <div class="sidebar-item">
            <div class="sidebar-label">Account Status</div>
            <div class="sidebar-value">
              <span class="status-badge">Active</span>
            </div>
          </div>
          <div class="sidebar-item">
            <div class="sidebar-label">Full Name</div>
            <div class="sidebar-value"><?php echo htmlspecialchars($userName); ?></div>
          </div>
          <div class="sidebar-item">
            <div class="sidebar-label">Email Address</div>
            <div class="sidebar-value" style="font-size: 14px;"><?php echo htmlspecialchars($userEmail); ?></div>
          </div>
          <div class="sidebar-item">
            <div class="sidebar-label">Member Since</div>
            <div class="sidebar-value"><?php echo date('M d, Y'); ?></div>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="col-md-9">
        <!-- Welcome Section -->
        <div class="welcome-section">
          <div class="welcome-title">Welcome, <?php echo htmlspecialchars(explode(' ', $userName)[0]); ?>!</div>
          <div class="welcome-subtitle">To the Parish Church Document Request and Booking System</div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
          <div class="col-md-6">
            <div class="dashboard-card">
              <div class="card-icon" style="color: #3e5361;">
                <i class="bi bi-file-earmark-text"></i>
              </div>
              <div class="card-title">Request a Document</div>
              <div class="card-description">
                Request official church documents such as baptism certificates, marriage certificates, and more.
              </div>
              <a href="#documents" class="btn-card">Request Now</a>
            </div>
          </div>
          <div class="col-md-6">
            <div class="dashboard-card">
              <div class="card-icon" style="color: #28a745;">
                <i class="bi bi-calendar-event"></i>
              </div>
              <div class="card-title">Book an Appointment</div>
              <div class="card-description">
                Schedule an appointment with the church for sacraments, counseling, or other services.
              </div>
              <a href="#bookings" class="btn-card">Book Now</a>
            </div>
          </div>
        </div>

        <!-- Recent Activities -->
        <div class="dashboard-card">
          <h5 class="mb-3"><i class="bi bi-clock-history"></i> Recent Activity</h5>
          <p class="text-muted mb-0">No recent activities yet. Start by requesting a document or booking an appointment.</p>
        </div>

        <!-- Upcoming Bookings -->
        <div class="dashboard-card">
          <h5 class="mb-3"><i class="bi bi-calendar-check"></i> Upcoming Bookings</h5>
          <p class="text-muted mb-0">You don't have any upcoming bookings yet.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <p>&copy; 2026 Parish Church System. All rights reserved.</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
