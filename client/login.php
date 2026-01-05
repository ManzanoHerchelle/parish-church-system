<?php 
// Login page
require_once __DIR__ . '/../includes/session.php';
startSecureSession();

// Check if already logged in
if (isLoggedIn()) {
    $role = getCurrentUserRole();
    $redirectUrl = ($role === 'admin' || $role === 'staff') ? '/admin/dashboard.php' : '/client/dashboard.php';
    header('Location: ' . $redirectUrl);
    exit;
}

// Get messages
$error = $_SESSION['login_error'] ?? '';
$success = isset($_GET['registered']) ? 'Registration successful! Please check your email to verify your account.' : '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ParishEase - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background-color: #e8e8e8; margin: 0; min-height: 100vh; display: flex; flex-direction: column; }
    .top-header { background: #3e5361; padding: 15px 0; }
    .logo-circle { width: 70px; height: 70px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #3e5361; }
    .header-text { color: #fff; text-align: center; font-weight: 700; text-transform: uppercase; line-height: 1.4; font-size: 13px; }
    .main-content { flex: 1; padding: 30px 0; }
    .panel-title { background: #3e5361; color: #fff; padding: 14px 20px; font-weight: 700; text-transform: uppercase; font-size: 15px; }
    .panel-body { background: #d3d3d3; padding: 25px; border: 2px solid #3e5361; }
    .paalala-box { background: #f8f8f8; padding: 15px; border: 1px solid #999; margin-bottom: 20px; font-size: 13px; line-height: 1.5; }
    .form-row { display: flex; align-items: center; margin-bottom: 18px; }
    .form-row label { font-weight: 700; text-transform: uppercase; width: 140px; margin: 0; font-size: 14px; }
    .form-row input { flex: 1; }
    .btn-login { background: #3e5361; border: none; font-weight: 700; color: #fff; padding: 10px 40px; border-radius: 25px; }
    .btn-login:hover { background: #2d3e4a; }
    .footer { background: #3e5361; color: #fff; padding: 25px 0; margin-top: auto; }
    .footer a { color: #fff; text-decoration: none; margin: 0 10px; font-size: 13px; }
    .footer a:hover { text-decoration: underline; }
    .social-icon { color: #fff; font-size: 24px; margin: 0 8px; }
    .link-underline { text-decoration: underline; font-weight: 600; color: #000; }
    .link-underline:hover { color: #3e5361; }
  </style>
</head>
<body>
  <div class="top-header">
    <div class="container">
      <div class="row align-items-center justify-content-center g-3">
        <div class="col-auto">
          <div class="logo-circle">LOGO</div>
        </div>
        <div class="col-auto">
          <div class="header-text">
            <div>Parish Name Lorem Ipsum</div>
            <div>Parish Address Lorem Ipsum Yadda Yadda</div>
            <div style="font-size: 11px; margin-top: 3px;">Parish Ease: An Interactive Document Request and Appointment System</div>
          </div>
        </div>
        <div class="col-auto">
          <div class="logo-circle">LOGO</div>
        </div>
      </div>
    </div>
  </div>

  <div class="main-content">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-7">
          <div class="panel-title">Announcements</div>
          <div class="panel-body" style="min-height: 400px; background: #f5f5f5;">
            <!-- Announcements will be displayed here -->
          </div>
        </div>

        <div class="col-lg-5">
          <div class="panel-title text-center">Log In Form</div>
          <div class="panel-body">
            <?php if ($error): ?>
              <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>
            <?php endif; ?>
            <?php if ($success): ?>
              <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>
            <?php endif; ?>
            <div class="paalala-box">
              <strong>PAALALA:</strong> Ang buscopan Venus ay hindi gamot at hindi dapat gamiting pang-gamot sa anumang uri ng sakit.
            </div>
            <form method="POST" action="/documentSystem/api/login.php">
              <div class="form-row">
                <label>Email:</label>
                <div class="input-group flex-grow-1">
                  <input type="email" name="email" class="form-control" required>
                  <span class="input-group-text" style="background: transparent; border: none; width: 48px;"></span>
                </div>
              </div>
              <div class="form-row">
                <label>Password:</label>
                <div class="input-group flex-grow-1">
                  <input type="password" name="password" id="loginPassword" class="form-control" required>
                  <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('loginPassword', this)">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </div>
              <div class="text-end mb-3">
                <a href="#" class="link-underline" style="font-size: 13px;">FORGOT PASSWORD?</a>
              </div>
              <div class="text-center mb-3">
                <button type="submit" class="btn btn-login">LOG IN</button>
              </div>
              <div class="text-center">
                <span style="font-size: 13px;">NEW USER? <a href="register.php" class="link-underline">REGISTER HERE.</a></span>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <footer class="footer">
    <div class="container">
      <div class="row">
        <div class="col-md-4 mb-3 mb-md-0">
          <div style="font-size: 12px; line-height: 1.6;">
            <strong>FOR INQUIRIES AND CONCERN:</strong><br>
            <strong>HOTLINE:</strong> 87000 JOLLIBEE DELIVERY<br>
            <strong>EMAIL:</strong> mommyoni@mailidot.com
          </div>
        </div>
        <div class="col-md-4 mb-3 mb-md-0 text-center">
          <a href="#">HOME</a>
          <a href="#">ABOUT US</a>
          <a href="#">TERMS OF SERVICE</a>
          <a href="#">PRIVACY POLICY</a>
          <div class="mt-2" style="font-size: 12px;">Parish Name Copyright © 2025</div>
        </div>
        <div class="col-md-4 text-end">
          <div style="font-size: 13px; margin-bottom: 8px;"><strong>FOLLOW US ON:</strong></div>
          <a href="#" class="social-icon"><i class="bi bi-facebook"></i></a>
          <a href="#" class="social-icon"><i class="bi bi-youtube"></i></a>
        </div>
      </div>
    </div>
  </footer>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function togglePassword(fieldId, button) {
      const field = document.getElementById(fieldId);
      const icon = button.querySelector('i');
      if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
      } else {
        field.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
      }
    }
  </script>
</body>
</html>
