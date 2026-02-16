<?php
/**
 * Attendance Management API
 * Handles: Manual marking, QR code, Unique code attendance
 */

require_once 'db_config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ============================================================================
// GENERATE UNIQUE CODE FOR ATTENDANCE
// ============================================================================
if ($action === 'generateUniqueCode') {
    requireRole('teacher');
    
    $teacherId = $_SESSION['user_id'];
    $subjectId = intval($_POST['subject_id'] ?? 0);
    $validityMinutes = intval($_POST['validity_minutes'] ?? 15);
    $sessionDate = $_POST['session_date'] ?? date('Y-m-d');
    
    if (!$subjectId) {
        echo json_encode(['success' => false, 'message' => 'Subject ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("CALL sp_generate_attendance_code(?, ?, ?, ?)");
        $stmt->execute([$teacherId, $subjectId, $sessionDate, $validityMinutes]);
        $result = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'unique_code' => $result['unique_code'],
            'expires_at' => $result['expires_at'],
            'validity_minutes' => $validityMinutes
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// MARK ATTENDANCE VIA UNIQUE CODE (Student side)
// ============================================================================
if ($action === 'markByUniqueCode') {
    requireRole('student');
    
    $studentId = $_SESSION['user_id'];
    $uniqueCode = strtoupper(trim($_POST['unique_code'] ?? ''));
    
    if (!$uniqueCode) {
        echo json_encode(['success' => false, 'message' => 'Please enter the code']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("CALL sp_mark_attendance_by_code(?, ?)");
        $stmt->execute([$studentId, $uniqueCode]);
        $result = $stmt->fetch();
        
        echo json_encode([
            'success' => $result['status'] === 'success',
            'message' => $result['message']
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// MARK ATTENDANCE VIA QR CODE (Teacher scans student QR)
// ============================================================================
if ($action === 'markByQR') {
    requireRole('teacher');
    
    $teacherId = $_SESSION['user_id'];
    $qrData = trim($_POST['qr_data'] ?? '');
    $subjectId = intval($_POST['subject_id'] ?? 0);
    $attendanceDate = $_POST['attendance_date'] ?? date('Y-m-d');
    
    if (!$qrData || !$subjectId) {
        echo json_encode(['success' => false, 'message' => 'QR data and subject required']);
        exit;
    }
    
    try {
        // Find student by QR data
        $stmt = $pdo->prepare("SELECT id, full_name, reg_id FROM users WHERE qr_code_data = ? AND role = 'student'");
        $stmt->execute([$qrData]);
        $student = $stmt->fetch();
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Invalid QR code']);
            exit;
        }
        
        // Check if student is enrolled in this subject
        $stmt = $pdo->prepare("SELECT 1 FROM student_subjects WHERE student_id = ? AND subject_id = ?");
        $stmt->execute([$student['id'], $subjectId]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => $student['full_name'] . ' is not enrolled in this subject']);
            exit;
        }
        
        // Mark attendance
        $stmt = $pdo->prepare("
            INSERT INTO attendance (student_id, subject_id, teacher_id, attendance_date, marking_method, status)
            VALUES (?, ?, ?, ?, 'qr', 'present')
            ON DUPLICATE KEY UPDATE 
                status = 'present',
                marking_method = 'qr',
                teacher_id = ?,
                marked_at = NOW()
        ");
        $stmt->execute([$student['id'], $subjectId, $teacherId, $attendanceDate, $teacherId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance marked for ' . $student['full_name'],
            'student_name' => $student['full_name'],
            'reg_id' => $student['reg_id']
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// MANUAL ATTENDANCE MARKING
// ============================================================================
if ($action === 'markManual') {
    requireRole('teacher');
    
    $teacherId = $_SESSION['user_id'];
    $students = json_decode($_POST['students'] ?? '[]', true);
    $subjectId = intval($_POST['subject_id'] ?? 0);
    $attendanceDate = $_POST['attendance_date'] ?? date('Y-m-d');
    
    if (!$subjectId || empty($students)) {
        echo json_encode(['success' => false, 'message' => 'Subject and students required']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $marked = 0;
        $stmt = $pdo->prepare("
            INSERT INTO attendance (student_id, subject_id, teacher_id, attendance_date, marking_method, status)
            VALUES (?, ?, ?, ?, 'manual', ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                marking_method = 'manual',
                teacher_id = VALUES(teacher_id),
                marked_at = NOW()
        ");
        
        foreach ($students as $student) {
            $stmt->execute([
                $student['id'],
                $subjectId,
                $teacherId,
                $attendanceDate,
                $student['status']
            ]);
            $marked++;
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Attendance marked for $marked students",
            'marked_count' => $marked
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// GET STUDENTS WHO MARKED ATTENDANCE VIA UNIQUE CODE
// ============================================================================
if ($action === 'getCodeAttendance') {
    requireRole('teacher');
    
    $teacherId = $_SESSION['user_id'];
    $subjectId = intval($_GET['subject_id'] ?? 0);
    $sessionDate = $_GET['session_date'] ?? date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.reg_id,
                u.full_name,
                a.marked_at,
                a.status
            FROM attendance a
            JOIN users u ON a.student_id = u.id
            JOIN attendance_sessions s ON a.attendance_session_id = s.id
            WHERE a.teacher_id = ?
              AND a.subject_id = ?
              AND a.attendance_date = ?
              AND a.marking_method = 'unique_code'
            ORDER BY a.marked_at DESC
        ");
        $stmt->execute([$teacherId, $subjectId, $sessionDate]);
        $students = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'students' => $students,
            'count' => count($students)
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// DOWNLOAD ATTENDANCE SHEET (CSV)
// ============================================================================
if ($action === 'downloadAttendanceCSV') {
    requireRole('teacher');
    
    $teacherId = $_SESSION['user_id'];
    $subjectId = intval($_GET['subject_id'] ?? 0);
    $attendanceDate = $_GET['attendance_date'] ?? date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.reg_id AS 'Registration ID',
                u.full_name AS 'Student Name',
                u.email AS 'Email',
                s.subject_name AS 'Subject',
                a.attendance_date AS 'Date',
                a.status AS 'Status',
                a.marking_method AS 'Marking Method',
                a.marked_at AS 'Marked At'
            FROM attendance a
            JOIN users u ON a.student_id = u.id
            JOIN subjects s ON a.subject_id = s.id
            WHERE a.teacher_id = ?
              AND a.subject_id = ?
              AND a.attendance_date = ?
            ORDER BY u.full_name
        ");
        $stmt->execute([$teacherId, $subjectId, $attendanceDate]);
        $records = $stmt->fetchAll();
        
        if (empty($records)) {
            die('No attendance records found');
        }
        
        // Set CSV headers
        $filename = 'Attendance_' . $attendanceDate . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers
        fputcsv($output, array_keys($records[0]));
        
        // Write data
        foreach ($records as $record) {
            fputcsv($output, $record);
        }
        
        fclose($output);
        exit;
        
    } catch (PDOException $e) {
        die('Error: ' . $e->getMessage());
    }
}

// ============================================================================
// UPDATE ATTENDANCE (Increase percentage)
// ============================================================================
if ($action === 'increaseAttendance') {
    requireRole('teacher');
    
    $studentId = intval($_POST['student_id'] ?? 0);
    $subjectId = intval($_POST['subject_id'] ?? 0);
    $percentageIncrease = floatval($_POST['percentage_increase'] ?? 0);
    
    if (!$studentId || !$subjectId || $percentageIncrease <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("CALL sp_increase_attendance(?, ?, ?)");
        $stmt->execute([$studentId, $subjectId, $percentageIncrease]);
        $result = $stmt->fetch();
        
        echo json_encode([
            'success' => $result['status'] === 'success',
            'message' => $result['message'],
            'classes_added' => $result['classes_added'] ?? 0
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// GET STUDENT ATTENDANCE REPORT
// ============================================================================
if ($action === 'getStudentReport') {
    $studentId = intval($_GET['student_id'] ?? $_SESSION['user_id'] ?? 0);
    $subjectId = intval($_GET['subject_id'] ?? 0);
    
    if (!$studentId) {
        echo json_encode(['success' => false, 'message' => 'Student ID required']);
        exit;
    }
    
    try {
        if ($subjectId) {
            // Single subject report
            $stmt = $pdo->prepare("
                SELECT 
                    s.subject_name,
                    s.subject_code,
                    COUNT(a.id) AS total_classes,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
                    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) AS percentage
                FROM attendance a
                JOIN subjects s ON a.subject_id = s.id
                WHERE a.student_id = ? AND a.subject_id = ?
                GROUP BY s.subject_name, s.subject_code
            ");
            $stmt->execute([$studentId, $subjectId]);
        } else {
            // All subjects report
            $stmt = $pdo->prepare("
                SELECT 
                    s.subject_name,
                    s.subject_code,
                    COUNT(a.id) AS total_classes,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
                    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) AS percentage
                FROM attendance a
                JOIN subjects s ON a.subject_id = s.id
                WHERE a.student_id = ?
                GROUP BY s.subject_name, s.subject_code
                ORDER BY s.subject_name
            ");
            $stmt->execute([$studentId]);
        }
        
        $report = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'report' => $report
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// GET ATTENDANCE ANALYTICS FOR TEACHER
// ============================================================================
if ($action === 'getTeacherAnalytics') {
    requireRole('teacher');
    
    $teacherId = $_SESSION['user_id'];
    $subjectId = intval($_GET['subject_id'] ?? 0);
    $studentId = intval($_GET['student_id'] ?? 0);  // Optional: specific student stats
    
    try {
        if ($studentId && $subjectId) {
            // Specific student analytics
            $stmt = $pdo->prepare("
                SELECT 
                    u.reg_id,
                    u.full_name,
                    s.subject_name,
                    COUNT(a.id) AS total_classes,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) AS percentage
                FROM attendance a
                JOIN users u ON a.student_id = u.id
                JOIN subjects s ON a.subject_id = s.id
                WHERE a.student_id = ? AND a.subject_id = ? AND a.teacher_id = ?
                GROUP BY u.reg_id, u.full_name, s.subject_name
            ");
            $stmt->execute([$studentId, $subjectId, $teacherId]);
            $analytics = $stmt->fetch();
            
        } else if ($subjectId) {
            // Subject-wise analytics
            $stmt = $pdo->prepare("
                SELECT 
                    u.id,
                    u.reg_id,
                    u.full_name,
                    COUNT(a.id) AS total_classes,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) AS percentage
                FROM student_subjects ss
                JOIN users u ON ss.student_id = u.id
                LEFT JOIN attendance a ON u.id = a.student_id AND a.subject_id = ss.subject_id
                WHERE ss.subject_id = ? AND u.is_active = 1
                GROUP BY u.id, u.reg_id, u.full_name
                ORDER BY percentage DESC
            ");
            $stmt->execute([$subjectId]);
            $analytics = $stmt->fetchAll();
            
        } else {
            // Overall analytics for teacher
            $stmt = $pdo->prepare("
                SELECT 
                    s.subject_name,
                    COUNT(DISTINCT a.student_id) AS total_students,
                    COUNT(a.id) AS total_classes,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS total_present,
                    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) AS avg_percentage
                FROM teacher_subjects ts
                JOIN subjects s ON ts.subject_id = s.id
                LEFT JOIN attendance a ON s.id = a.subject_id AND a.teacher_id = ts.teacher_id
                WHERE ts.teacher_id = ?
                GROUP BY s.subject_name
                ORDER BY s.subject_name
            ");
            $stmt->execute([$teacherId]);
            $analytics = $stmt->fetchAll();
        }
        
        echo json_encode([
            'success' => true,
            'analytics' => $analytics
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Invalid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
