<?php
/**
 * Admin Account Setup Script
 * Run this once to create the first admin account
 * Delete this file after use for security
 */

require_once __DIR__ . '/config/database.php';

$message = '';
$success = false;

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
        $message = 'All fields are required.';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $message = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } else {
        // Check if admin already exists
        $conn = getDBConnection();
        
        $checkQuery = "SELECT COUNT(*) as count FROM users WHERE role = 'admin'";
        $adminCount = $conn->query($checkQuery)->fetch_assoc()['count'];
        
        if ($adminCount > 0) {
            $message = 'Admin account already exists! Delete this file for security.';
        } else {
            // Check if email exists
            $emailCheckQuery = "SELECT COUNT(*) as count FROM users WHERE email = ?";
            $stmt = $conn->prepare($emailCheckQuery);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $emailExists = $stmt->get_result()->fetch_assoc()['count'] > 0;
            $stmt->close();
            
            if ($emailExists) {
                $message = 'Email already in use. Please choose a different email.';
            } else {
                // Hash password using SHA2-256
                $hashedPassword = hash('sha256', $password);
                
                // Insert admin user
                $insertQuery = "INSERT INTO users (name, email, password, role, email_verified, phone, created_at) 
                               VALUES (?, ?, ?, 'admin', 1, '', NOW())";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("sss", $name, $email, $hashedPassword);
                
                if ($stmt->execute()) {
                    $success = true;
                    $message = "Admin account created successfully!<br><strong>Email:</strong> $email<br><strong>Password:</strong> $password<br><br>You can now log in. <strong>Please delete this setup file after logging in.</strong>";
                } else {
                    $message = 'Error creating admin account: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
        
        closeDBConnection($conn);
    }
}

// Check if any admin already exists
$conn = getDBConnection();
$adminCount = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];
closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Setup - Parish Church System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .setup-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 500px;
        }
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .setup-header h1 {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        .setup-header p {
            color: #666;
            margin: 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        .alert {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid;
        }
        .alert-success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .alert-warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        .success-actions {
            margin-top: 20px;
            text-align: center;
        }
        .success-actions a {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px;
            font-weight: bold;
        }
        .success-actions a:hover {
            background: #764ba2;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .warning-box strong {
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>🛠️ Admin Setup</h1>
            <p>Create your admin account</p>
        </div>

        <?php if ($adminCount > 0): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Admin Already Exists</strong><br>
                An admin account has already been created. This setup file is no longer needed.<br>
                <strong>Please delete this file (setup.php) from your server for security.</strong>
            </div>
            <div style="text-align: center;">
                <a href="/documentSystem/client/login.php" style="color: #667eea; text-decoration: none; font-weight: bold;">
                    ← Go to Login
                </a>
            </div>
        <?php elseif ($success): ?>
            <div class="alert alert-success">
                <strong>✓ Success!</strong><br>
                <?php echo $message; ?>
            </div>
            <div class="success-actions">
                <a href="/documentSystem/client/login.php">Go to Login →</a>
            </div>
        <?php else: ?>
            <?php if (!empty($message)): ?>
                <div class="alert alert-danger">
                    <strong>✗ Error</strong><br>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="warning-box">
                <strong>⚠️ Security Note:</strong> Delete this setup.php file after creating the admin account!
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="name">Admin Name</label>
                    <input type="text" id="name" name="name" placeholder="e.g., John Doe" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="e.g., admin@parishchurch.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="At least 6 characters" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
                </div>

                <button type="submit" class="btn-submit">Create Admin Account</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
