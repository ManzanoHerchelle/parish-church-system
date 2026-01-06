<?php
/**
 * Registration API Handler
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../handlers/email_handler.php';
require_once __DIR__ . '/../includes/session.php';

startSecureSession();

// Helper function to preserve form data and redirect with error
function redirectWithError($message, $formData = []) {
    $_SESSION['registration_error'] = $message;
    if (!empty($formData)) {
        $_SESSION['registration_data'] = $formData;
        unset($_SESSION['registration_data']['password'], $_SESSION['registration_data']['confirm_password']);
    }
    header('Location: /documentSystem/client/register.php');
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /documentSystem/client/register.php');
    exit;
}

// Initialize response
$response = ['success' => false, 'message' => '', 'errors' => []];

try {
    // Get and sanitize input
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $dateOfBirth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $civilStatus = $_POST['civil_status'] ?? '';
    $parishMembership = $_POST['parish_membership'] ?? '';
    $consent = isset($_POST['consent']) ? 1 : 0;
    
    // Validate required fields
    if (empty($email) || empty($password) || empty($firstName) || empty($lastName) || 
        empty($phone) || empty($address) || empty($dateOfBirth) || empty($gender) || 
        empty($civilStatus) || empty($parishMembership)) {
        redirectWithError('All required fields must be filled.', $_POST);
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectWithError('Invalid email format.', $_POST);
    }
    
    // Validate password
    if (strlen($password) < 8) {
        redirectWithError('Password must be at least 8 characters long.', $_POST);
    }
    
    if ($password !== $confirmPassword) {
        redirectWithError('Passwords do not match.', $_POST);
    }
    
    // Validate phone (Philippine format)
    if (!preg_match('/^[0-9]{11}$/', $phone)) {
        redirectWithError('Phone number must be 11 digits.', $_POST);
    }
    
    // Validate date of birth
    $dob = DateTime::createFromFormat('Y-m-d', $dateOfBirth);
    if (!$dob || $dob->format('Y-m-d') !== $dateOfBirth) {
        redirectWithError('Invalid date of birth.', $_POST);
    }
    
    // Check if user is at least 13 years old
    $today = new DateTime();
    $age = $today->diff($dob)->y;
    if ($age < 13) {
        redirectWithError('You must be at least 13 years old to register.', $_POST);
    }
    
    // Validate consent
    if (!$consent) {
        redirectWithError('You must agree to the Terms of Service and Privacy Policy.', $_POST);
    }
    
    // Validate enum fields
    $validGenders = ['male', 'female'];
    $validCivilStatuses = ['single', 'married', 'widowed', 'separated'];
    $validMemberships = ['member', 'other_parish', 'visitor'];
    
    if (!in_array($gender, $validGenders)) {
        redirectWithError('Invalid gender selection.', $_POST);
    }
    
    if (!in_array($civilStatus, $validCivilStatuses)) {
        redirectWithError('Invalid civil status selection.', $_POST);
    }
    
    if (!in_array($parishMembership, $validMemberships)) {
        redirectWithError('Invalid parish membership selection.', $_POST);
    }
    
    // Get database connection
    $conn = getDBConnection();
    
    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $checkStmt->close();
        closeDBConnection($conn);
        redirectWithError('An account with this email already exists.', $_POST);
    }
    $checkStmt->close();
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Generate verification token
    $verificationToken = bin2hex(random_bytes(32));
    
    // Insert user into database
    $stmt = $conn->prepare("
        INSERT INTO users (
            first_name, last_name, email, password, phone, address, 
            role, status, email_verified, verification_token
        ) VALUES (?, ?, ?, ?, ?, ?, 'client', 'pending', 0, ?)
    ");
    
    $stmt->bind_param(
        "sssssss",
        $firstName, $lastName, $email, $hashedPassword, $phone, $address, $verificationToken
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Registration failed. Please try again.');
    }
    
    $userId = $stmt->insert_id;
    $stmt->close();
    
    // Send verification email
    $emailHandler = new EmailHandler();
    $emailResult = $emailHandler->sendVerificationEmail($userId, $email, $firstName, $verificationToken);
    
    // Log activity
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, 'register', 'New user registered', ?, ?)");
    $logStmt->bind_param("iss", $userId, $ipAddress, $userAgent);
    $logStmt->execute();
    $logStmt->close();
    
    closeDBConnection($conn);
    
    $response['success'] = true;
    $response['message'] = 'Registration successful! Please check your email to verify your account.';
    
    // Store success message and redirect
    $_SESSION['registration_success'] = $response['message'];
    header('Location: /documentSystem/client/login.php?registered=1');
    exit;
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    
    // Store error in session and redirect back
    $_SESSION['registration_error'] = $response['message'];
    $_SESSION['registration_data'] = $_POST; // Preserve form data
    unset($_SESSION['registration_data']['password'], $_SESSION['registration_data']['confirm_password']); // Don't preserve passwords
    header('Location: /documentSystem/client/register.php');
    exit;
}
?>
