<?php
/**
 * QR Code Generator for Student Attendance
 * Requires: phpqrcode library (download from: https://sourceforge.net/projects/phpqrcode/)
 * Place phpqrcode folder in the same directory as this file
 */

require_once 'db_config.php';

// Include QR Code library
// Download from: https://sourceforge.net/projects/phpqrcode/
// Or use composer: composer require endroid/qr-code
require_once 'phpqrcode/qrlib.php';

/**
 * Generate QR Code for a student
 */
function generateStudentQRCode($studentId, $regId, $qrData) {
    $qrDir = '../qr_codes/';
    
    // Create directory if it doesn't exist
    if (!file_exists($qrDir)) {
        mkdir($qrDir, 0755, true);
    }
    
    $filename = $qrDir . 'QR_' . $regId . '.png';
    
    // Generate QR code
    // Parameters: data, filename, error correction level (L,M,Q,H), size, margin
    QRcode::png($qrData, $filename, QR_ECLEVEL_H, 10, 2);
    
    return $filename;
}

/**
 * Alternative: Generate QR using Google Charts API (no library needed)
 */
function generateQRCodeGoogle($qrData, $regId, $size = 300) {
    $qrDir = '../qr_codes/';
    
    if (!file_exists($qrDir)) {
        mkdir($qrDir, 0755, true);
    }
    
    $url = "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl=" . urlencode($qrData) . "&choe=UTF-8";
    
    $imageData = file_get_contents($url);
    
    if ($imageData) {
        $filename = $qrDir . 'QR_' . $regId . '.png';
        file_put_contents($filename, $imageData);
        return $filename;
    }
    
    return false;
}

/**
 * API endpoint to generate QR code
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generateQR') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['student_id'])) {
        echo json_encode(['success' => false, 'message' => 'Student ID required']);
        exit;
    }
    
    $studentId = intval($_POST['student_id']);
    
    try {
        // Get student details
        $stmt = $pdo->prepare("SELECT reg_id, qr_code_data, full_name FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }
        
        // Generate QR code (using Google API method - no external library needed)
        $qrPath = generateQRCodeGoogle($student['qr_code_data'], $student['reg_id'], 400);
        
        if ($qrPath) {
            // Update database with QR path
            $stmt = $pdo->prepare("UPDATE users SET qr_code_path = ? WHERE id = ?");
            $stmt->execute([$qrPath, $studentId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'QR code generated successfully',
                'qr_path' => $qrPath,
                'student_name' => $student['full_name'],
                'reg_id' => $student['reg_id']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to generate QR code']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * API endpoint to download QR code
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'downloadQR') {
    if (!isset($_GET['student_id'])) {
        die('Student ID required');
    }
    
    $studentId = intval($_GET['student_id']);
    
    try {
        $stmt = $pdo->prepare("SELECT reg_id, qr_code_path, full_name FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        
        if (!$student || !file_exists($student['qr_code_path'])) {
            die('QR code not found');
        }
        
        // Set headers for download
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="QR_' . $student['reg_id'] . '_' . $student['full_name'] . '.png"');
        header('Content-Length: ' . filesize($student['qr_code_path']));
        readfile($student['qr_code_path']);
        exit;
        
    } catch (PDOException $e) {
        die('Error: ' . $e->getMessage());
    }
}

/**
 * Batch generate QR codes for all students
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generateAllQR') {
    header('Content-Type: application/json');
    
    try {
        $stmt = $pdo->query("SELECT id, reg_id, qr_code_data, full_name FROM users WHERE role = 'student' AND is_active = 1");
        $students = $stmt->fetchAll();
        
        $generated = 0;
        $failed = 0;
        
        foreach ($students as $student) {
            $qrPath = generateQRCodeGoogle($student['qr_code_data'], $student['reg_id'], 400);
            
            if ($qrPath) {
                $updateStmt = $pdo->prepare("UPDATE users SET qr_code_path = ? WHERE id = ?");
                $updateStmt->execute([$qrPath, $student['id']]);
                $generated++;
            } else {
                $failed++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Generated $generated QR codes, $failed failed",
            'generated' => $generated,
            'failed' => $failed
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}
?>
