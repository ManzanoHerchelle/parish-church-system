<?php
/**
 * Certificate Service
 * Handles PDF certificate generation for completed documents
 */

namespace Services;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/TCPDF-main/tcpdf.php';

class CertificateService {
    private $conn;
    private $uploadDir;
    
    public function __construct() {
        $this->conn = getDBConnection();
        $this->uploadDir = __DIR__ . '/../../uploads/certificates';
        
        // Create certificates directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Generate certificate for a document request
     * Returns the file path if successful, false otherwise
     */
    public function generateCertificate($documentRequestId, $documentName, $userName, $issuedDate = null) {
        try {
            $issuedDate = $issuedDate ?? date('Y-m-d');
            $referenceNumber = $this->getDocumentReferenceNumber($documentRequestId);
            
            if (!$referenceNumber) {
                return false;
            }
            
            // Generate unique filename
            $filename = 'CERT_' . str_replace(' ', '_', $referenceNumber) . '_' . time() . '.pdf';
            $filePath = $this->uploadDir . '/' . $filename;
            
            // Generate PDF using TCPDF
            $this->generatePDFWithTCPDF($filePath, $documentName, $userName, $issuedDate, $referenceNumber);
            
            if (file_exists($filePath)) {
                // Store certificate record in database
                $this->saveCertificateRecord($documentRequestId, $filename, $filePath);
                return $filename;
            }
            
            return false;
        } catch (\Exception $e) {
            error_log('Certificate Generation Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate PDF certificate using TCPDF
     */
    private function generatePDFWithTCPDF($filePath, $documentName, $userName, $issuedDate, $referenceNumber) {
        // Create new PDF document
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->setCreator(PDF_CREATOR);
        $pdf->setAuthor('Parish Church System');
        $pdf->setTitle('Certificate of Completion');
        
        // Set margins
        $pdf->setMargins(10, 10, 10);
        
        // Add a page
        $pdf->addPage();
        
        // Set fonts
        $pdf->setFont('helvetica', '', 12);
        
        // Add decorative border
        $pdf->setLineWidth(1);
        $pdf->setDrawColor(41, 128, 185);
        $pdf->rect(8, 8, 194, 279);
        $pdf->setLineWidth(0.5);
        $pdf->rect(10, 10, 190, 275);
        
        // Add gradient-like header background
        $pdf->setFillColor(41, 128, 185);
        $pdf->rect(10, 10, 190, 45, 'F');
        
        // Add header text
        $pdf->setTextColor(255, 255, 255);
        $pdf->setFont('helvetica', 'B', 36);
        $pdf->setXY(10, 18);
        $pdf->cell(190, 20, 'CERTIFICATE', 0, 1, 'C');
        
        // Reset colors
        $pdf->setTextColor(0, 0, 0);
        $pdf->setFont('helvetica', '', 12);
        $y = $pdf->getY() + 10;
        $pdf->setXY(10, $y);
        
        // Certificate body
        $pdf->cell(190, 8, 'OF', 0, 1, 'C');
        $pdf->cell(190, 8, 'COMPLETION', 0, 1, 'C');
        
        $pdf->ln(10);
        $pdf->setFont('helvetica', '', 11);
        $pdf->cell(190, 8, 'This is to certify that', 0, 1, 'C');
        
        $pdf->ln(5);
        
        // Recipient name
        $pdf->setFont('helvetica', 'B', 18);
        $pdf->setTextColor(41, 128, 185);
        $pdf->cell(190, 12, $userName, 0, 1, 'C');
        
        // Reset to black
        $pdf->setTextColor(0, 0, 0);
        $pdf->setFont('helvetica', '', 11);
        $pdf->ln(8);
        
        $pdf->cell(190, 8, 'has successfully completed the document request for', 0, 1, 'C');
        
        $pdf->ln(5);
        
        // Document name with color
        $pdf->setFont('helvetica', 'B', 13);
        $pdf->setTextColor(41, 128, 185);
        $pdf->multiCell(190, 6, $documentName, 0, 'C');
        
        $pdf->setTextColor(0, 0, 0);
        $pdf->setFont('helvetica', '', 11);
        
        // Details section with border
        $pdf->ln(12);
        $y = $pdf->getY();
        $pdf->setDrawColor(200, 200, 200);
        $pdf->rect(15, $y, 180, 38);
        
        $pdf->setXY(20, $y + 3);
        $pdf->setFont('helvetica', 'B', 10);
        $pdf->cell(40, 6, 'Issued Date:', 0, 0);
        $pdf->setFont('helvetica', '', 10);
        $pdf->cell(130, 6, date('F d, Y', strtotime($issuedDate)), 0, 1);
        
        $pdf->setXY(20, $y + 11);
        $pdf->setFont('helvetica', 'B', 10);
        $pdf->cell(40, 6, 'Reference #:', 0, 0);
        $pdf->setFont('helvetica', '', 10);
        $pdf->cell(130, 6, $referenceNumber, 0, 1);
        
        $pdf->setXY(20, $y + 19);
        $pdf->setFont('helvetica', 'B', 10);
        $pdf->cell(40, 6, 'Certificate ID:', 0, 0);
        $pdf->setFont('helvetica', '', 10);
        $certificateId = 'CERT-' . strtoupper(substr(md5($referenceNumber . time()), 0, 8));
        $pdf->cell(130, 6, $certificateId, 0, 1);
        
        $pdf->setXY(20, $y + 27);
        $pdf->setFont('helvetica', 'B', 10);
        $pdf->cell(40, 6, 'Issued By:', 0, 0);
        $pdf->setFont('helvetica', '', 10);
        $pdf->cell(130, 6, 'Parish Church Document System', 0, 1);
        
        // Footer text
        $pdf->ln(20);
        $pdf->setFont('helvetica', 'I', 9);
        $pdf->setTextColor(100, 100, 100);
        $pdf->multiCell(190, 5, 'This certificate is issued as proof of completion of the requested document. It is valid for official use and records maintenance.', 0, 'C');
        
        // Validity note
        $pdf->setFont('helvetica', '', 8);
        $pdf->setTextColor(150, 150, 150);
        $pdf->ln(5);
        $pdf->cell(190, 4, 'Valid from: ' . date('M d, Y'), 0, 1, 'C');
        
        // Save the PDF to filesystem
        $pdf->output($filePath, 'F');
    }
    
    /**
     * Get document reference number
     */
    private function getDocumentReferenceNumber($documentRequestId) {
        $query = "SELECT reference_number FROM document_requests WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $documentRequestId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result ? $result['reference_number'] : null;
    }
    
    /**
     * Save certificate record to database
     */
    private function saveCertificateRecord($documentRequestId, $filename, $filePath) {
        $query = "
            INSERT INTO certificates (document_request_id, certificate_file, file_path, issued_date)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                certificate_file = VALUES(certificate_file),
                file_path = VALUES(file_path),
                issued_date = VALUES(issued_date)
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iss", $documentRequestId, $filename, $filePath);
        return $stmt->execute();
    }
    
    /**
     * Get certificate for a document
     */
    public function getCertificate($documentRequestId) {
        $query = "SELECT * FROM certificates WHERE document_request_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $documentRequestId);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Delete certificate
     */
    public function deleteCertificate($documentRequestId) {
        $certificate = $this->getCertificate($documentRequestId);
        
        if ($certificate && file_exists($certificate['file_path'])) {
            unlink($certificate['file_path']);
        }
        
        $query = "DELETE FROM certificates WHERE document_request_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $documentRequestId);
        
        return $stmt->execute();
    }
}

