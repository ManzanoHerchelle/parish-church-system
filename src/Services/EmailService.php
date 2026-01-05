<?php
/**
 * Email Service
 * Handles all email sending operations using PHPMailer
 */

namespace Services;

require_once __DIR__ . '/../../config/email_config.php';
require_once __DIR__ . '/../../includes/PHPMailer-6.9.1/src/Exception.php';
require_once __DIR__ . '/../../includes/PHPMailer-6.9.1/src/PHPMailer.php';
require_once __DIR__ . '/../../includes/PHPMailer-6.9.1/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        try {
            // SMTP Configuration
            $this->mail->isSMTP();
            $this->mail->Host = SMTP_HOST;
            $this->mail->SMTPAuth = SMTP_AUTH;
            $this->mail->Username = SMTP_USERNAME;
            $this->mail->Password = SMTP_PASSWORD;
            $this->mail->SMTPSecure = SMTP_SECURE;
            $this->mail->Port = SMTP_PORT;
            
            // Sender
            $this->mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $this->mail->addReplyTo(SMTP_REPLY_TO);
            
            // Debug
            $this->mail->SMTPDebug = SMTP_DEBUG;
            $this->mail->Timeout = SMTP_TIMEOUT;
        } catch (Exception $e) {
            error_log('EmailService Constructor Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Send document approval notification
     */
    public function sendDocumentApprovalEmail($clientEmail, $clientName, $documentType, $referenceNumber) {
        try {
            $this->mail->addAddress($clientEmail, $clientName);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Document Request Approved - ' . $referenceNumber;
            
            $body = $this->getDocumentApprovalTemplate($clientName, $documentType, $referenceNumber);
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags($body);
            
            $result = $this->mail->send();
            $this->resetRecipients();
            return $result;
        } catch (Exception $e) {
            error_log('Email Error (Approval): ' . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send document ready for pickup notification
     */
    public function sendDocumentReadyEmail($clientEmail, $clientName, $documentType, $referenceNumber) {
        try {
            $this->mail->addAddress($clientEmail, $clientName);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Your Document is Ready - ' . $referenceNumber;
            
            $body = $this->getDocumentReadyTemplate($clientName, $documentType, $referenceNumber);
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags($body);
            
            $result = $this->mail->send();
            $this->resetRecipients();
            return $result;
        } catch (Exception $e) {
            error_log('Email Error (Ready): ' . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send document rejection notification
     */
    public function sendDocumentRejectionEmail($clientEmail, $clientName, $documentType, $referenceNumber, $reason) {
        try {
            $this->mail->addAddress($clientEmail, $clientName);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Document Request Rejected - ' . $referenceNumber;
            
            $body = $this->getDocumentRejectionTemplate($clientName, $documentType, $referenceNumber, $reason);
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags($body);
            
            $result = $this->mail->send();
            $this->resetRecipients();
            return $result;
        } catch (Exception $e) {
            error_log('Email Error (Rejection): ' . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send booking approval notification
     */
    public function sendBookingApprovalEmail($clientEmail, $clientName, $bookingType, $bookingDate, $bookingTime, $referenceNumber) {
        try {
            $this->mail->addAddress($clientEmail, $clientName);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Appointment Approved - ' . $referenceNumber;
            
            $body = $this->getBookingApprovalTemplate($clientName, $bookingType, $bookingDate, $bookingTime, $referenceNumber);
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags($body);
            
            $result = $this->mail->send();
            $this->resetRecipients();
            return $result;
        } catch (Exception $e) {
            error_log('Email Error (Booking Approval): ' . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send booking rejection notification
     */
    public function sendBookingRejectionEmail($clientEmail, $clientName, $bookingType, $referenceNumber, $reason) {
        try {
            $this->mail->addAddress($clientEmail, $clientName);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Appointment Rejected - ' . $referenceNumber;
            
            $body = $this->getBookingRejectionTemplate($clientName, $bookingType, $referenceNumber, $reason);
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags($body);
            
            $result = $this->mail->send();
            $this->resetRecipients();
            return $result;
        } catch (Exception $e) {
            error_log('Email Error (Booking Rejection): ' . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send payment verification notification
     */
    public function sendPaymentVerifiedEmail($clientEmail, $clientName, $amount, $referenceNumber) {
        try {
            $this->mail->addAddress($clientEmail, $clientName);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Payment Verified - ' . $referenceNumber;
            
            $body = $this->getPaymentVerifiedTemplate($clientName, $amount, $referenceNumber);
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags($body);
            
            $result = $this->mail->send();
            $this->resetRecipients();
            return $result;
        } catch (Exception $e) {
            error_log('Email Error (Payment Verified): ' . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    // Email Templates
    private function getDocumentApprovalTemplate($clientName, $documentType, $referenceNumber) {
        return "
        <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto;'>
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 5px 5px 0 0;'>
                        <h2 style='margin: 0;'><i>✓</i> Document Request Approved</h2>
                    </div>
                    <div style='padding: 20px; background: #f9f9f9; border: 1px solid #ddd;'>
                        <p>Hello <strong>$clientName</strong>,</p>
                        <p>Great news! Your document request has been <strong>approved</strong> by our office.</p>
                        
                        <div style='background: white; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0;'>
                            <p><strong>Document Details:</strong></p>
                            <ul style='margin: 10px 0;'>
                                <li><strong>Type:</strong> $documentType</li>
                                <li><strong>Reference #:</strong> $referenceNumber</li>
                                <li><strong>Status:</strong> <span style='color: #28a745; font-weight: bold;'>Approved</span></li>
                            </ul>
                        </div>
                        
                        <p>Your document is now being processed. You will receive another notification when it's ready for pickup.</p>
                        
                        <p style='margin-top: 30px;'>Best regards,<br><strong>Parish Church System</strong></p>
                    </div>
                    <div style='padding: 15px; background: #f0f0f0; border-radius: 0 0 5px 5px; font-size: 12px; color: #666;'>
                        <p style='margin: 0;'>This is an automated email. Please do not reply directly to this message.</p>
                    </div>
                </div>
            </body>
        </html>";
    }
    
    private function getDocumentReadyTemplate($clientName, $documentType, $referenceNumber) {
        $downloadLink = BASE_URL . '/client/download-document.php?id=' . urlencode($referenceNumber);
        return "
        <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto;'>
                    <div style='background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%); color: white; padding: 20px; border-radius: 5px 5px 0 0;'>
                        <h2 style='margin: 0;'><i>📄</i> Your Document is Ready!</h2>
                    </div>
                    <div style='padding: 20px; background: #f9f9f9; border: 1px solid #ddd;'>
                        <p>Hello <strong>$clientName</strong>,</p>
                        <p>Excellent! Your <strong>$documentType</strong> is now ready for download.</p>
                        
                        <div style='background: white; padding: 15px; border-left: 4px solid #56ab2f; margin: 20px 0;'>
                            <p><strong>Document Details:</strong></p>
                            <ul style='margin: 10px 0;'>
                                <li><strong>Type:</strong> $documentType</li>
                                <li><strong>Reference #:</strong> $referenceNumber</li>
                                <li><strong>Status:</strong> <span style='color: #28a745; font-weight: bold;'>Ready for Download</span></li>
                            </ul>
                        </div>
                        
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='" . BASE_URL . "/client/my-documents.php' style='display: inline-block; background: #56ab2f; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Download Document</a>
                        </p>
                        
                        <p>You can log in to your account to download your document anytime.</p>
                        
                        <p style='margin-top: 30px;'>Best regards,<br><strong>Parish Church System</strong></p>
                    </div>
                    <div style='padding: 15px; background: #f0f0f0; border-radius: 0 0 5px 5px; font-size: 12px; color: #666;'>
                        <p style='margin: 0;'>This is an automated email. Please do not reply directly to this message.</p>
                    </div>
                </div>
            </body>
        </html>";
    }
    
    private function getDocumentRejectionTemplate($clientName, $documentType, $referenceNumber, $reason) {
        return "
        <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto;'>
                    <div style='background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%); color: white; padding: 20px; border-radius: 5px 5px 0 0;'>
                        <h2 style='margin: 0;'><i>⚠</i> Document Request Rejected</h2>
                    </div>
                    <div style='padding: 20px; background: #f9f9f9; border: 1px solid #ddd;'>
                        <p>Hello <strong>$clientName</strong>,</p>
                        <p>Unfortunately, your document request has been <strong>rejected</strong>.</p>
                        
                        <div style='background: white; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;'>
                            <p><strong>Document Details:</strong></p>
                            <ul style='margin: 10px 0;'>
                                <li><strong>Type:</strong> $documentType</li>
                                <li><strong>Reference #:</strong> $referenceNumber</li>
                            </ul>
                            <p><strong>Reason:</strong></p>
                            <p style='color: #555; background: #f5f5f5; padding: 10px; border-radius: 3px;'>$reason</p>
                        </div>
                        
                        <p>Please contact our office if you have questions or would like to resubmit your request with corrections.</p>
                        
                        <p style='margin-top: 30px;'>Best regards,<br><strong>Parish Church System</strong></p>
                    </div>
                    <div style='padding: 15px; background: #f0f0f0; border-radius: 0 0 5px 5px; font-size: 12px; color: #666;'>
                        <p style='margin: 0;'>This is an automated email. Please do not reply directly to this message.</p>
                    </div>
                </div>
            </body>
        </html>";
    }
    
    private function getBookingApprovalTemplate($clientName, $bookingType, $bookingDate, $bookingTime, $referenceNumber) {
        $dateFormatted = date('F d, Y', strtotime($bookingDate));
        $timeFormatted = date('g:i A', strtotime($bookingTime));
        return "
        <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto;'>
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 5px 5px 0 0;'>
                        <h2 style='margin: 0;'><i>✓</i> Appointment Approved</h2>
                    </div>
                    <div style='padding: 20px; background: #f9f9f9; border: 1px solid #ddd;'>
                        <p>Hello <strong>$clientName</strong>,</p>
                        <p>Great news! Your appointment has been <strong>approved</strong>.</p>
                        
                        <div style='background: white; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0;'>
                            <p><strong>Appointment Details:</strong></p>
                            <ul style='margin: 10px 0;'>
                                <li><strong>Type:</strong> $bookingType</li>
                                <li><strong>Date:</strong> $dateFormatted</li>
                                <li><strong>Time:</strong> $timeFormatted</li>
                                <li><strong>Reference #:</strong> $referenceNumber</li>
                            </ul>
                        </div>
                        
                        <p><strong>Please remember to:</strong></p>
                        <ul>
                            <li>Arrive 10 minutes before your scheduled time</li>
                            <li>Bring any required documents</li>
                            <li>Contact us if you need to reschedule</li>
                        </ul>
                        
                        <p style='margin-top: 30px;'>Best regards,<br><strong>Parish Church System</strong></p>
                    </div>
                    <div style='padding: 15px; background: #f0f0f0; border-radius: 0 0 5px 5px; font-size: 12px; color: #666;'>
                        <p style='margin: 0;'>This is an automated email. Please do not reply directly to this message.</p>
                    </div>
                </div>
            </body>
        </html>";
    }
    
    private function getBookingRejectionTemplate($clientName, $bookingType, $referenceNumber, $reason) {
        return "
        <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto;'>
                    <div style='background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%); color: white; padding: 20px; border-radius: 5px 5px 0 0;'>
                        <h2 style='margin: 0;'><i>⚠</i> Appointment Rejected</h2>
                    </div>
                    <div style='padding: 20px; background: #f9f9f9; border: 1px solid #ddd;'>
                        <p>Hello <strong>$clientName</strong>,</p>
                        <p>Unfortunately, your appointment request has been <strong>rejected</strong>.</p>
                        
                        <div style='background: white; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;'>
                            <p><strong>Appointment Details:</strong></p>
                            <ul style='margin: 10px 0;'>
                                <li><strong>Type:</strong> $bookingType</li>
                                <li><strong>Reference #:</strong> $referenceNumber</li>
                            </ul>
                            <p><strong>Reason:</strong></p>
                            <p style='color: #555; background: #f5f5f5; padding: 10px; border-radius: 3px;'>$reason</p>
                        </div>
                        
                        <p>Please try booking another date or contact our office for more information.</p>
                        
                        <p style='margin-top: 30px;'>Best regards,<br><strong>Parish Church System</strong></p>
                    </div>
                    <div style='padding: 15px; background: #f0f0f0; border-radius: 0 0 5px 5px; font-size: 12px; color: #666;'>
                        <p style='margin: 0;'>This is an automated email. Please do not reply directly to this message.</p>
                    </div>
                </div>
            </body>
        </html>";
    }
    
    private function getPaymentVerifiedTemplate($clientName, $amount, $referenceNumber) {
        $amountFormatted = '₱' . number_format($amount, 2);
        return "
        <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto;'>
                    <div style='background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%); color: white; padding: 20px; border-radius: 5px 5px 0 0;'>
                        <h2 style='margin: 0;'><i>💳</i> Payment Verified</h2>
                    </div>
                    <div style='padding: 20px; background: #f9f9f9; border: 1px solid #ddd;'>
                        <p>Hello <strong>$clientName</strong>,</p>
                        <p>Thank you! Your payment has been <strong>verified</strong> and processed.</p>
                        
                        <div style='background: white; padding: 15px; border-left: 4px solid #56ab2f; margin: 20px 0;'>
                            <p><strong>Payment Details:</strong></p>
                            <ul style='margin: 10px 0;'>
                                <li><strong>Amount:</strong> $amountFormatted</li>
                                <li><strong>Reference #:</strong> $referenceNumber</li>
                                <li><strong>Status:</strong> <span style='color: #28a745; font-weight: bold;'>Verified</span></li>
                            </ul>
                        </div>
                        
                        <p>Your transaction is complete. You can now proceed with your request or appointment.</p>
                        
                        <p style='margin-top: 30px;'>Best regards,<br><strong>Parish Church System</strong></p>
                    </div>
                    <div style='padding: 15px; background: #f0f0f0; border-radius: 0 0 5px 5px; font-size: 12px; color: #666;'>
                        <p style='margin: 0;'>This is an automated email. Please do not reply directly to this message.</p>
                    </div>
                </div>
            </body>
        </html>";
    }
    
    private function resetRecipients() {
        $this->mail->clearAllRecipients();
    }
}
