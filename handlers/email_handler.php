<?php
require_once __DIR__ . '/../includes/PHPMailer-6.9.1/src/PHPMailer.php';
require_once __DIR__ . '/../includes/PHPMailer-6.9.1/src/SMTP.php';
require_once __DIR__ . '/../includes/PHPMailer-6.9.1/src/Exception.php';
require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailHandler {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Send email using Gmail SMTP
     */
    public function sendEmail($to, $subject, $body, $userId = null) {
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = SMTP_AUTH;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = SMTP_PORT;
            $mail->SMTPDebug = SMTP_DEBUG;
            $mail->Timeout = SMTP_TIMEOUT;
            
            // Recipients
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->addReplyTo(SMTP_REPLY_TO, SMTP_FROM_NAME);
            
            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body); // Plain text version
            
            // Send email
            $result = $mail->send();
            
            // Log email
            $this->logEmail($userId, $to, $subject, $body, 'sent', null);
            
            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
            
        } catch (Exception $e) {
            // Log failed email
            $this->logEmail($userId, $to, $subject, $body, 'failed', $mail->ErrorInfo);
            
            return [
                'success' => false,
                'message' => 'Email failed to send',
                'error' => $mail->ErrorInfo
            ];
        }
    }
    
    /**
     * Send verification email
     */
    public function sendVerificationEmail($userId, $email, $firstName, $token) {
        $verifyLink = VERIFY_URL . '?token=' . urlencode($token);
        
        $subject = 'Verify Your Email Address';
        $body = $this->getVerificationEmailTemplate($firstName, $verifyLink);
        
        return $this->sendEmail($email, $subject, $body, $userId);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($userId, $email, $firstName, $token) {
        $resetLink = RESET_URL . '?token=' . urlencode($token);
        
        $subject = 'Reset Your Password';
        $body = $this->getPasswordResetTemplate($firstName, $resetLink);
        
        return $this->sendEmail($email, $subject, $body, $userId);
    }
    
    /**
     * Send document request confirmation
     */
    public function sendDocumentRequestConfirmation($userId, $email, $firstName, $refNumber, $docType) {
        $subject = 'Document Request Received - ' . $refNumber;
        $body = $this->getDocumentRequestTemplate($firstName, $refNumber, $docType);
        
        return $this->sendEmail($email, $subject, $body, $userId);
    }
    
    /**
     * Send document ready notification
     */
    public function sendDocumentReadyNotification($userId, $email, $firstName, $refNumber, $docType) {
        $subject = 'Your Document is Ready - ' . $refNumber;
        $body = $this->getDocumentReadyTemplate($firstName, $refNumber, $docType);
        
        return $this->sendEmail($email, $subject, $body, $userId);
    }
    
    /**
     * Send booking confirmation
     */
    public function sendBookingConfirmation($userId, $email, $firstName, $refNumber, $bookingType, $date, $time) {
        $subject = 'Booking Confirmation - ' . $refNumber;
        $body = $this->getBookingConfirmationTemplate($firstName, $refNumber, $bookingType, $date, $time);
        
        return $this->sendEmail($email, $subject, $body, $userId);
    }
    
    /**
     * Log email to database
     */
    private function logEmail($userId, $email, $subject, $message, $status, $errorMessage) {
        $stmt = $this->conn->prepare("INSERT INTO email_logs (user_id, email_address, subject, message, status, error_message) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $userId, $email, $subject, $message, $status, $errorMessage);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Email Templates
     */
    private function getVerificationEmailTemplate($firstName, $verifyLink) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .button { display: inline-block; padding: 12px 30px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to Parish Church System</h1>
                </div>
                <div class='content'>
                    <h2>Hello, {$firstName}!</h2>
                    <p>Thank you for registering with our Parish Church Document Request and Booking System.</p>
                    <p>Please verify your email address by clicking the button below:</p>
                    <p style='text-align: center;'>
                        <a href='{$verifyLink}' class='button'>Verify Email Address</a>
                    </p>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; color: #666;'>{$verifyLink}</p>
                    <p>This link will expire in 24 hours.</p>
                    <p>If you didn't create this account, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2026 Parish Church. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getPasswordResetTemplate($firstName, $resetLink) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2196F3; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .button { display: inline-block; padding: 12px 30px; background: #2196F3; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset Request</h1>
                </div>
                <div class='content'>
                    <h2>Hello, {$firstName}!</h2>
                    <p>You requested to reset your password.</p>
                    <p>Click the button below to create a new password:</p>
                    <p style='text-align: center;'>
                        <a href='{$resetLink}' class='button'>Reset Password</a>
                    </p>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; color: #666;'>{$resetLink}</p>
                    <p>This link will expire in 1 hour.</p>
                    <p><strong>If you didn't request this, please ignore this email.</strong> Your password will remain unchanged.</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2026 Parish Church. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getDocumentRequestTemplate($firstName, $refNumber, $docType) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #FF9800; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .info-box { background: white; padding: 15px; border-left: 4px solid #FF9800; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Document Request Received</h1>
                </div>
                <div class='content'>
                    <h2>Hello, {$firstName}!</h2>
                    <p>We have received your document request.</p>
                    <div class='info-box'>
                        <strong>Reference Number:</strong> {$refNumber}<br>
                        <strong>Document Type:</strong> {$docType}<br>
                        <strong>Status:</strong> Pending
                    </div>
                    <p>You can track your request status by logging into your account.</p>
                    <p>You will receive another email once your document is ready for pickup/download.</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2026 Parish Church. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getDocumentReadyTemplate($firstName, $refNumber, $docType) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .info-box { background: white; padding: 15px; border-left: 4px solid #4CAF50; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Your Document is Ready!</h1>
                </div>
                <div class='content'>
                    <h2>Hello, {$firstName}!</h2>
                    <p>Good news! Your requested document is now ready.</p>
                    <div class='info-box'>
                        <strong>Reference Number:</strong> {$refNumber}<br>
                        <strong>Document Type:</strong> {$docType}<br>
                        <strong>Status:</strong> Ready for Pickup/Download
                    </div>
                    <p>Please log in to your account to download your document or visit the church office during office hours to claim it.</p>
                    <p>Please bring a valid ID and your reference number.</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2026 Parish Church. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getBookingConfirmationTemplate($firstName, $refNumber, $bookingType, $date, $time) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #9C27B0; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .info-box { background: white; padding: 15px; border-left: 4px solid #9C27B0; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Booking Confirmation</h1>
                </div>
                <div class='content'>
                    <h2>Hello, {$firstName}!</h2>
                    <p>Your booking request has been received and is pending approval.</p>
                    <div class='info-box'>
                        <strong>Reference Number:</strong> {$refNumber}<br>
                        <strong>Booking Type:</strong> {$bookingType}<br>
                        <strong>Date:</strong> {$date}<br>
                        <strong>Time:</strong> {$time}<br>
                        <strong>Status:</strong> Pending Approval
                    </div>
                    <p>You will receive a confirmation email once your booking is approved by the church admin.</p>
                    <p>Please keep your reference number for future correspondence.</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2026 Parish Church. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    public function __destruct() {
        closeDBConnection($this->conn);
    }
}
?>
