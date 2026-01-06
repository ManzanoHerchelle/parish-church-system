<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ParishEase - Registration</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background-color: #e8e8e8; margin: 0; min-height: 100vh; display: flex; flex-direction: column; }
    .top-header { background: #3e5361; padding: 15px 0; }
    .logo-circle { width: 70px; height: 70px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #3e5361; }
    .header-text { color: #fff; text-align: center; font-weight: 700; text-transform: uppercase; line-height: 1.4; font-size: 13px; }
    .main-content { flex: 1; padding: 30px 0; }
    .panel-title { background: #3e5361; color: #fff; padding: 14px 20px; font-weight: 700; text-transform: uppercase; font-size: 15px; text-align: center; }
    .panel-body { background: #d3d3d3; padding: 30px; border: 2px solid #3e5361; }
    .section-label { font-weight: 700; text-transform: uppercase; color: #000; margin-bottom: 15px; font-size: 14px; padding-bottom: 8px; border-bottom: 2px solid #3e5361; }
    .form-row { display: flex; align-items: center; margin-bottom: 15px; }
    .form-row label { font-weight: 700; text-transform: uppercase; width: 180px; margin: 0; font-size: 13px; }
    .form-row input { flex: 1; }
    .btn-register { background: #3e5361; border: none; font-weight: 700; color: #fff; padding: 10px 50px; border-radius: 25px; }
    .btn-register:hover { background: #2d3e4a; }
    .footer { background: #3e5361; color: #fff; padding: 25px 0; margin-top: auto; }
    .footer a { color: #fff; text-decoration: none; margin: 0 10px; font-size: 13px; }
    .footer a:hover { text-decoration: underline; }
    .social-icon { color: #fff; font-size: 24px; margin: 0 8px; }
    .link-underline { text-decoration: underline; font-weight: 600; color: #000; }
    .link-underline:hover { color: #3e5361; }
  </style>
</head>
<body>
<?php 
require_once __DIR__ . '/../includes/session.php';
startSecureSession();

// Check if already logged in
if (isLoggedIn()) {
    header('Location: /client/dashboard.php');
    exit;
}

// Get messages and preserve form data
$error = $_SESSION['registration_error'] ?? '';
$oldData = $_SESSION['registration_data'] ?? [];
unset($_SESSION['registration_error'], $_SESSION['registration_data']);
?>
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
    <div class="container" style="max-width: 700px;">
      <div class="panel-title">Registration Form</div>
      <div class="panel-body">
        <?php if ($error): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
        <form method="POST" action="/documentSystem/api/register.php" id="registrationForm">
          <div class="section-label">Log In Credentials</div>
          <div class="form-row">
            <label>Email:</label>
            <div class="input-group flex-grow-1">
              <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($oldData['email'] ?? ''); ?>" required>
              <span class="input-group-text" style="background: transparent; border: none; width: 48px;"></span>
            </div>
          </div>
          <div class="form-row">
            <label>Password:</label>
            <div class="input-group flex-grow-1">
              <input type="password" name="password" id="regPassword" class="form-control" required minlength="8">
              <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('regPassword', this)">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>
          <div class="password-strength px-0" id="passwordStrength" style="font-size: 11px; display: none; margin-left: 180px; margin-top: -10px; margin-bottom: 10px;"></div>
          <div class="form-row">
            <label>Retype Password:</label>
            <div class="input-group flex-grow-1">
              <input type="password" name="confirm_password" id="confirmPassword" class="form-control" required>
              <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmPassword', this)">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>

          <div class="section-label mt-4">Client Information</div>
          <div class="form-row">
            <label>Name:</label>
            <div class="input-group flex-grow-1">
              <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($oldData['first_name'] ?? ''); ?>" required>
              <span class="input-group-text" style="background: transparent; border: none; width: 48px;"></span>
            </div>
          </div>
          <div class="form-row">
            <label>Surname:</label>
            <div class="input-group flex-grow-1">
              <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($oldData['last_name'] ?? ''); ?>" required>
              <span class="input-group-text" style="background: transparent; border: none; width: 48px;"></span>
            </div>
          </div>
          <div class="form-row">
            <label>Contact Number:</label>
            <div class="input-group flex-grow-1">
              <input type="tel" name="phone" class="form-control" pattern="[0-9]{11}" placeholder="09xxxxxxxxx" value="<?php echo htmlspecialchars($oldData['phone'] ?? ''); ?>" required>
              <span class="input-group-text" style="background: transparent; border: none; width: 48px;"></span>
            </div>
          </div>
          <div class="form-row">
            <label>Address:</label>
            <div class="input-group flex-grow-1">
              <textarea name="address" class="form-control" rows="2" required><?php echo htmlspecialchars($oldData['address'] ?? ''); ?></textarea>
              <span class="input-group-text" style="background: transparent; border: none; width: 48px;"></span>
            </div>
          </div>
          <div class="form-row">
            <label>Date of Birth:</label>
            <div class="input-group flex-grow-1">
              <input type="date" name="date_of_birth" class="form-control" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($oldData['date_of_birth'] ?? ''); ?>" required>
              <span class="input-group-text" style="background: transparent; border: none; width: 48px;"></span>
            </div>
          </div>
          <div class="form-row">
            <label>Gender:</label>
            <div class="input-group flex-grow-1">
              <select name="gender" class="form-select" required>
                <option value="">Select gender</option>
                <option value="male" <?php echo ($oldData['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                <option value="female" <?php echo ($oldData['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
              </select>
              <span class="input-group-text" style="background: transparent; border: none; width: 48px;"></span>
            </div>
          </div>
          <div class="form-row">
            <label>Civil Status:</label>
            <div class="input-group flex-grow-1">
              <select name="civil_status" class="form-select" required>
                <option value="">Select civil status</option>
                <option value="single" <?php echo ($oldData['civil_status'] ?? '') === 'single' ? 'selected' : ''; ?>>Single</option>
                <option value="married" <?php echo ($oldData['civil_status'] ?? '') === 'married' ? 'selected' : ''; ?>>Married</option>
                <option value="widowed" <?php echo ($oldData['civil_status'] ?? '') === 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                <option value="separated" <?php echo ($oldData['civil_status'] ?? '') === 'separated' ? 'selected' : ''; ?>>Separated/Annulled</option>
              </select>
              <span class="input-group-text" style="background: transparent; border: none; width: 48px;"></span>
            </div>
          </div>
          <div class="form-row">
            <label>Membership:</label>
            <div class="input-group flex-grow-1">
              <select name="parish_membership" class="form-select" required>
                <option value="">Select membership status</option>
                <option value="member" <?php echo ($oldData['parish_membership'] ?? '') === 'member' ? 'selected' : ''; ?>>Member of this parish</option>
                <option value="other_parish" <?php echo ($oldData['parish_membership'] ?? '') === 'other_parish' ? 'selected' : ''; ?>>Member of another parish</option>
                <option value="visitor" <?php echo ($oldData['parish_membership'] ?? '') === 'visitor' ? 'selected' : ''; ?>>Non-parishioner/Visitor</option>
              </select>
              <span class="input-group-text" style="background: transparent; border: none; width: 48px;"></span>
            </div>
          </div>
          
          <div class="form-check mb-4 mt-3">
            <input class="form-check-input" type="checkbox" value="1" id="consentCheck" name="consent" required>
            <label class="form-check-label" for="consentCheck" style="font-size: 12px;">
              By clicking this check box, you confirm that the details provided are true and you agree to our 
              <a href="#" class="link-underline">Terms of Service</a> and 
              <a href="#" class="link-underline">Privacy Policy</a>. You also consent to receive email notifications.
            </label>
          </div>
          
          <div class="text-center mb-3">
            <button type="submit" class="btn btn-register">REGISTER</button>
          </div>
          <div class="text-center">
            <a href="login.php" class="link-underline" style="font-size: 13px;">RETURN TO LOGIN PAGE</a>
          </div>
        </form>
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

    // Password strength checker
    document.getElementById('regPassword').addEventListener('input', function() {
      const password = this.value;
      const strengthDiv = document.getElementById('passwordStrength');
      
      if (password.length === 0) {
        strengthDiv.style.display = 'none';
        return;
      }
      
      strengthDiv.style.display = 'block';
      let strength = 0;
      let feedback = [];
      
      if (password.length >= 8) strength++;
      else feedback.push('at least 8 characters');
      
      if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
      else feedback.push('uppercase and lowercase');
      
      if (/[0-9]/.test(password)) strength++;
      else feedback.push('numbers');
      
      if (/[^A-Za-z0-9]/.test(password)) strength++;
      else feedback.push('special characters');
      
      if (strength <= 1) {
        strengthDiv.innerHTML = '<span style="color: #dc3545;">Weak password. Add: ' + feedback.join(', ') + '</span>';
      } else if (strength === 2) {
        strengthDiv.innerHTML = '<span style="color: #ffc107;">Fair password. Consider adding: ' + feedback.join(', ') + '</span>';
      } else if (strength === 3) {
        strengthDiv.innerHTML = '<span style="color: #28a745;">Good password!</span>';
      } else {
        strengthDiv.innerHTML = '<span style="color: #28a745;"><strong>Strong password!</strong></span>';
      }
    });

    // Password match checker
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
      const password = document.getElementById('regPassword').value;
      const confirm = document.getElementById('confirmPassword').value;
      
      if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
      }
    });
  </script>
</body>
</html>
