<?php
/**
 * Attendance Management API
 * Handles: Manual marking, QR code, Unique code attendance
 */

require_once 'db_config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ============================================================================
// AUTO-CREATE attendance_codes TABLE IF NOT EXISTS
// ============================================================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(10) NOT NULL,
            teacher_id INT NOT NULL,
            subject_name VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            teacher_lat DECIMAL(10,7) DEFAULT NULL,
            teacher_lng DECIMAL(10,7) DEFAULT NULL,
            teacher_accuracy DECIMAL(8,2) DEFAULT NULL,
            max_distance_meters INT DEFAULT NULL,
            INDEX idx_code (code),
            INDEX idx_teacher (teacher_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Add geo columns if table already exists without them
    try {
        $pdo->exec("ALTER TABLE attendance_codes ADD COLUMN teacher_lat DECIMAL(10,7) DEFAULT NULL");
        $pdo->exec("ALTER TABLE attendance_codes ADD COLUMN teacher_lng DECIMAL(10,7) DEFAULT NULL");
        $pdo->exec("ALTER TABLE attendance_codes ADD COLUMN teacher_accuracy DECIMAL(8,2) DEFAULT NULL");
        $pdo->exec("ALTER TABLE attendance_codes ADD COLUMN max_distance_meters INT DEFAULT NULL");
    } catch (PDOException $ignore) {
        // Columns already exist or error, individual column checks are better but this is common in Attendify
    }
} catch (PDOException $e) {
    // Table likely already exists, continue
}

// ============================================================================
// HAVERSINE DISTANCE HELPER (returns distance in meters)
// ============================================================================
function haversineDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371000; // meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

// ============================================================================
// GENERATE UNIQUE CODE FOR ATTENDANCE
// ============================================================================
if ($action === 'generateUniqueCode') {
    requireRole('teacher');
    
    $teacherId = $_SESSION['user_id'];
    $subjectName = sanitize($_POST['subject_name'] ?? '');
    $validitySeconds = intval($_POST['validity_seconds'] ?? 15);
    
    // Geolocation params (optional)
    $teacherLat = isset($_POST['teacher_lat']) && $_POST['teacher_lat'] !== '' ? floatval($_POST['teacher_lat']) : null;
    $teacherLng = isset($_POST['teacher_lng']) && $_POST['teacher_lng'] !== '' ? floatval($_POST['teacher_lng']) : null;
    $teacherAccuracy = isset($_POST['teacher_accuracy']) && $_POST['teacher_accuracy'] !== '' ? floatval($_POST['teacher_accuracy']) : null;
    $maxDistance = isset($_POST['max_distance_meters']) && $_POST['max_distance_meters'] !== '' ? intval($_POST['max_distance_meters']) : null;
    
    if (!$subjectName) {
        echo json_encode(['success' => false, 'message' => 'Subject name required']);
        exit;
    }
    try {
        // Check daily limit (max 3 codes per subject per day)
        $limitStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM attendance_codes 
            WHERE teacher_id = ? 
              AND subject_name = ? 
              AND DATE(created_at) = CURDATE()
        ");
        $limitStmt->execute([$teacherId, $subjectName]);
        $countToday = (int)$limitStmt->fetchColumn();

        if ($countToday >= 3) {
            echo json_encode([
                'success' => false, 
                'message' => "Daily limit reached (3/3) for \"$subjectName\". Please try again in the next lecture."
            ]);
            exit;
        }

        // Generate random 7-char alphanumeric code (no I,O,0,1 to avoid confusion)
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 7; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        // Calculate expiry entirely in DB to avoid PHP/MySQL timezone mismatches
        $stmt = $pdo->prepare("
            INSERT INTO attendance_codes (code, teacher_id, subject_name, expires_at, teacher_lat, teacher_lng, teacher_accuracy, max_distance_meters)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?, ?, ?, ?)
        ");
        $stmt->execute([$code, $teacherId, $subjectName, $validitySeconds, $teacherLat, $teacherLng, $teacherAccuracy, $maxDistance]);
        
        // Fetch the exact DB-assigned expiration time back
        $stmt = $pdo->prepare("SELECT expires_at FROM attendance_codes WHERE code = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$code]);
        $expiresAt = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'unique_code' => $code,
            'expires_at' => $expiresAt,
            'validity_seconds' => $validitySeconds,
            'codes_generated_today' => $countToday + 1,
            'geo_locked' => ($teacherLat !== null && $maxDistance !== null),
            'max_distance_meters' => $maxDistance
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
    requireLogin();
    
    // Only students can mark attendance via unique code
    if (($_SESSION['role'] ?? '') !== 'student') {
        echo json_encode([
            'success' => false, 
            'message' => 'Only students can mark attendance via unique code. You are logged in as ' . ($_SESSION['role'] ?? 'unknown') . ' (' . ($_SESSION['full_name'] ?? '') . '). Please login as a student in a different browser.'
        ]);
        exit;
    }
    
    $studentId = $_SESSION['user_id'];
    // Completely remove any spaces or non-alphanumeric chars that students might paste
    $uniqueCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['unique_code'] ?? ''));
    
    if (!$uniqueCode) {
        echo json_encode(['success' => false, 'message' => 'Please enter the code']);
        exit;
    }
    
    // Student geolocation (optional)
    $studentLat = isset($_POST['student_lat']) && $_POST['student_lat'] !== '' ? floatval($_POST['student_lat']) : null;
    $studentLng = isset($_POST['student_lng']) && $_POST['student_lng'] !== '' ? floatval($_POST['student_lng']) : null;
    $studentAccuracy = isset($_POST['student_accuracy']) && $_POST['student_accuracy'] !== '' ? floatval($_POST['student_accuracy']) : 0;
    
    try {
        // Find the code and check if it's still valid
        $stmt = $pdo->prepare("
            SELECT id, code, teacher_id, subject_name, expires_at, teacher_lat, teacher_lng, teacher_accuracy, max_distance_meters
            FROM attendance_codes
            WHERE code = ? AND expires_at > NOW()
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$uniqueCode]);
        $codeRecord = $stmt->fetch();
        
        if (!$codeRecord) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired code']);
            exit;
        }
        
        // Geolocation proximity check
        if ($codeRecord['teacher_lat'] !== null && $codeRecord['max_distance_meters'] !== null) {
            if ($studentLat === null || $studentLng === null) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'This code requires location access. Please enable GPS/location in your browser and try again.',
                    'geo_required' => true
                ]);
                exit;
            }
            
            $distance = haversineDistance(
                $codeRecord['teacher_lat'], $codeRecord['teacher_lng'],
                $studentLat, $studentLng
            );
            $maxDist = intval($codeRecord['max_distance_meters']);
            
            // Calculate Accuracy Buffer
            $teacherAcc = floatval($codeRecord['teacher_accuracy'] ?? 10); // default 10m buffer if missing
            $buffer = $teacherAcc + $studentAccuracy;
            $allowedDistance = $maxDist + $buffer;
            
            if ($distance > $allowedDistance) {
                $distRounded = round($distance);
                echo json_encode([
                    'success' => false, 
                    'message' => "Location match failed. Reported distance: {$distRounded}m. Maximum allowed with device accuracy buffer: " . round($allowedDistance) . "m. Please move closer to the teacher and try again.",
                    'geo_rejected' => true,
                    'distance' => $distRounded,
                    'max_distance' => $maxDist,
                    'accuracy_buffer' => round($buffer)
                ]);
                exit;
            }
        }
        
        // Check if student already marked attendance for this subject today (any method)
        $stmt = $pdo->prepare("
            SELECT id FROM attendance
            WHERE student_id = ? AND attendance_date = CURDATE()
              AND subject_id = (SELECT id FROM subjects WHERE subject_name = ? LIMIT 1)
        ");
        $stmt->execute([$studentId, $codeRecord['subject_name']]);
        
        if ($stmt->fetch()) {
            // Get student name for informative message
            $nameStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $nameStmt->execute([$studentId]);
            $nameRow = $nameStmt->fetch();
            $sName = $nameRow['full_name'] ?? 'Unknown';
            echo json_encode(['success' => false, 'message' => "Attendance already marked for {$sName} in {$codeRecord['subject_name']} today"]);
            exit;
        }
        
        // Try to find subject_id from subjects table, or auto-create it
        $stmt = $pdo->prepare("SELECT id FROM subjects WHERE subject_name = ? LIMIT 1");
        $stmt->execute([$codeRecord['subject_name']]);
        $subjectRow = $stmt->fetch();
        if ($subjectRow) {
            $subjectId = $subjectRow['id'];
        } else {
            // Auto-create the subject so FK constraint is satisfied
            $subjectCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $codeRecord['subject_name']), 0, 4)) . rand(100, 999);
            $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, subject_code, department) VALUES (?, ?, 'General')");
            $stmt->execute([$codeRecord['subject_name'], $subjectCode]);
            $subjectId = $pdo->lastInsertId();
        }
        
        // Mark attendance (ON DUPLICATE KEY UPDATE as safety net for unique constraint)
        $stmt = $pdo->prepare("
            INSERT INTO attendance (student_id, subject_id, teacher_id, attendance_date, marking_method, status, marked_at)
            VALUES (?, ?, ?, CURDATE(), 'unique_code', 'present', NOW())
            ON DUPLICATE KEY UPDATE
                status = 'present',
                marking_method = 'unique_code',
                teacher_id = VALUES(teacher_id),
                marked_at = NOW()
        ");
        $stmt->execute([$studentId, $subjectId, $codeRecord['teacher_id']]);
        
        // Get student name for response
        $stmt = $pdo->prepare("SELECT full_name, reg_id FROM users WHERE id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance marked successfully for ' . $codeRecord['subject_name'],
            'student_name' => $student['full_name'] ?? '',
            'reg_id' => $student['reg_id'] ?? '',
            'subject' => $codeRecord['subject_name']
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
    requireLogin();
    
    $teacherId = $_SESSION['user_id'];
    $qrData = trim($_POST['qr_data'] ?? '');
    $subjectName = sanitize($_POST['subject_name'] ?? '');
    $subjectId = intval($_POST['subject_id'] ?? 0);
    $attendanceDate = $_POST['attendance_date'] ?? date('Y-m-d');
    
    if (!$qrData) {
        echo json_encode(['success' => false, 'message' => 'QR data required']);
        exit;
    }
    
    // Clean invisible characters and whitespace from scanned QR data
    $qrData = preg_replace('/[\x00-\x1F\x7F]/', '', trim($qrData));
    
    try {
        // Find student by reg_id first (student QR encodes reg_id), then by qr_code_data
        $stmt = $pdo->prepare("
            SELECT id, full_name, reg_id FROM users 
            WHERE (reg_id = ? OR qr_code_data = ?) AND role = 'student'
            LIMIT 1
        ");
        $stmt->execute([$qrData, $qrData]);
        $student = $stmt->fetch();
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found for this QR code: ' . $qrData]);
            exit;
        }
        
        // Resolve subject_id from subject_name if not provided
        if (!$subjectId && $subjectName) {
            $stmt = $pdo->prepare("SELECT id FROM subjects WHERE subject_name = ? LIMIT 1");
            $stmt->execute([$subjectName]);
            $subjectRow = $stmt->fetch();
            if ($subjectRow) {
                $subjectId = $subjectRow['id'];
            } else {
                // Auto-create the subject so FK constraint is satisfied
                $subjectCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $subjectName), 0, 4)) . rand(100, 999);
                $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, subject_code, department) VALUES (?, ?, 'General')");
                $stmt->execute([$subjectName, $subjectCode]);
                $subjectId = $pdo->lastInsertId();
            }
        }
        
        // Check if already marked today for this subject
        $stmt = $pdo->prepare("
            SELECT id FROM attendance
            WHERE student_id = ? AND subject_id = ? AND attendance_date = ? AND marking_method = 'qr'
        ");
        $stmt->execute([$student['id'], $subjectId, $attendanceDate]);
        if ($stmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => $student['full_name'] . ' already marked present today',
                'student_name' => $student['full_name'],
                'reg_id' => $student['reg_id'],
                'already_marked' => true
            ]);
            exit;
        }
        
        // Mark attendance
        try {
            $stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, subject_id, teacher_id, attendance_date, marking_method, status, marked_at)
                VALUES (?, ?, ?, ?, 'qr', 'present', NOW())
            ");
            $stmt->execute([$student['id'], $subjectId, $teacherId, $attendanceDate]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Successfully attendance marked ' . $student['full_name'],
                'student_name' => $student['full_name'],
                'reg_id' => $student['reg_id'],
                'marked_at' => date('Y-m-d H:i:s')
            ]);
        } catch (PDOException $e) {
            // Handle duplicate entry error (Integrity constraint violation: 1062)
            if ($e->getCode() == 23000) {
                echo json_encode([
                    'success' => false,
                    'message' => $student['full_name'] . ' already marked present today',
                    'student_name' => $student['full_name'],
                    'reg_id' => $student['reg_id'],
                    'already_marked' => true
                ]);
            } else {
                throw $e; // Re-throw other database errors
            }
        }
        
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
    requireLogin();
    
    $code = strtoupper(trim($_GET['code'] ?? ''));
    
    if (!$code) {
        echo json_encode(['success' => false, 'message' => 'Code required']);
        exit;
    }
    
    try {
        // Find the code record (lookup by code only, not session user_id, due to shared sessions)
        $stmt = $pdo->prepare("
            SELECT id, teacher_id, subject_name, created_at, expires_at
            FROM attendance_codes
            WHERE code = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$code]);
        $codeRecord = $stmt->fetch();
        
        if (!$codeRecord) {
            echo json_encode(['success' => true, 'students' => [], 'count' => 0]);
            exit;
        }
        
        // Get students who submitted this code (use teacher_id from code record, not session)
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.reg_id,
                u.full_name,
                a.marked_at,
                a.status
            FROM attendance a
            JOIN users u ON a.student_id = u.id
            WHERE a.teacher_id = ?
              AND a.attendance_date = CURDATE()
              AND a.marking_method = 'unique_code'
              AND a.marked_at >= ?
              AND u.role = 'student'
            ORDER BY a.marked_at DESC
        ");
        $stmt->execute([$codeRecord['teacher_id'], $codeRecord['created_at']]);
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
// GET STUDENT'S OWN ATTENDANCE HISTORY
// ============================================================================
if ($action === 'getMyAttendanceHistory') {
    requireLogin();
    
    $studentId = $_SESSION['user_id'];
    $subjectFilter = sanitize($_GET['subject'] ?? 'all');
    $statusFilter = sanitize($_GET['status'] ?? 'all');
    
    try {
        if ($subjectFilter === 'College Event') {
            // Only event attendance
            $sql = "
                SELECT 
                    e.event_date AS attendance_date,
                    e.event_name AS subject_name,
                    'present' AS status,
                    ea.marking_method,
                    ea.scanned_at AS marked_at
                FROM event_attendance ea
                JOIN events e ON ea.event_id = e.id
                WHERE ea.student_id = ?
                ORDER BY e.event_date DESC, ea.scanned_at DESC
                LIMIT 100
            ";
            $params = [$studentId];
        } else {
            // Regular subject attendance including implicit absences
            $sql = "
                SELECT 
                    dt.attendance_date,
                    COALESCE(s.subject_name, 'Unknown') AS subject_name,
                    COALESCE(a.status, 'absent') AS status,
                    a.marking_method,
                    a.marked_at
                FROM (
                    SELECT DISTINCT attendance_date, subject_id
                    FROM attendance
                ) dt
                JOIN subjects s ON dt.subject_id = s.id
                LEFT JOIN attendance a ON a.subject_id = dt.subject_id 
                    AND a.attendance_date = dt.attendance_date 
                    AND a.student_id = ?
                WHERE (s.id IN (SELECT subject_id FROM student_subjects WHERE student_id = ?)
                    OR NOT EXISTS (SELECT 1 FROM student_subjects WHERE student_id = ?))
            ";
            $params = [$studentId, $studentId, $studentId];
            
            if ($subjectFilter !== 'all') {
                $sql .= " AND s.subject_name = ?";
                $params[] = $subjectFilter;
            }
            if ($statusFilter !== 'all') {
                $sql .= " AND COALESCE(a.status, 'absent') = ?";
                $params[] = $statusFilter;
            }
            
            // Also include event attendance when showing all subjects and status allows it
            if ($subjectFilter === 'all' && ($statusFilter === 'all' || $statusFilter === 'present')) {
                $sql .= "
                    UNION ALL
                    SELECT 
                        e.event_date AS attendance_date,
                        e.event_name AS subject_name,
                        'present' AS status,
                        ea.marking_method,
                        ea.scanned_at AS marked_at
                    FROM event_attendance ea
                    JOIN events e ON ea.event_id = e.id
                    WHERE ea.student_id = ?
                ";
                $params[] = $studentId;
            }
            
            $sql .= " ORDER BY attendance_date DESC, marked_at DESC LIMIT 100";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'records' => $records,
            'count' => count($records)
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// STUDENT ANALYTICS — daily attendance + subject-wise stats from DB
// ============================================================================
if ($action === 'getStudentAnalytics') {
    requireLogin();
    
    $studentId = $_SESSION['user_id'];
    $range = sanitize($_GET['range'] ?? 'week');      // week, 2week, month
    $subject = sanitize($_GET['subject'] ?? 'all');
    
    // Compute date range
    if ($range === 'all') {
        $startDate = '2000-01-01'; // Fetch all records
    } else {
        $days = $range === 'month' ? 30 : ($range === '2week' ? 14 : 7);
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
    }
    
    try {
        // 1. Daily attendance records in the date range
        $sql = "
            SELECT 
                dt.attendance_date,
                COALESCE(a.status, 'absent') AS status,
                COALESCE(s.subject_name, 'Unknown') AS subject_name
            FROM (
                SELECT DISTINCT attendance_date, subject_id
                FROM attendance
                WHERE attendance_date >= ?
            ) dt
            JOIN subjects s ON dt.subject_id = s.id
            LEFT JOIN attendance a ON a.subject_id = dt.subject_id 
                AND a.attendance_date = dt.attendance_date 
                AND a.student_id = ?
            WHERE (s.id IN (SELECT subject_id FROM student_subjects WHERE student_id = ?)
                OR NOT EXISTS (SELECT 1 FROM student_subjects WHERE student_id = ?))
        ";
        $params = [$startDate, $studentId, $studentId, $studentId];
        
        if ($subject !== 'all') {
            $sql .= " AND s.subject_name = ?";
            $params[] = $subject;
        }
        $sql .= " ORDER BY dt.attendance_date ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dailyRecords = $stmt->fetchAll();
        
        // 2. Subject-wise aggregation (total classes, present, absent)
        $sql2 = "
            SELECT 
                COALESCE(s.subject_name, 'Unknown') AS subject_name,
                (SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE subject_id = s.id AND attendance_date >= ?) AS total_classes,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                (SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE subject_id = s.id AND attendance_date >= ?) - SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS absent_count
            FROM subjects s
            LEFT JOIN attendance a ON a.subject_id = s.id AND a.student_id = ? AND a.attendance_date >= ?
            WHERE (s.id IN (SELECT subject_id FROM student_subjects WHERE student_id = ?)
                OR NOT EXISTS (SELECT 1 FROM student_subjects WHERE student_id = ?))
        ";
        $params2 = [$startDate, $startDate, $studentId, $startDate, $studentId, $studentId];
        
        if ($subject !== 'all') {
            $sql2 .= " AND s.subject_name = ?";
            $params2[] = $subject;
        }
        $sql2 .= " GROUP BY s.id, s.subject_name ORDER BY s.subject_name";
        
        $stmt = $pdo->prepare($sql2);
        $stmt->execute($params2);
        $subjectStats = $stmt->fetchAll();
        
        // Compute totals
        $totalPresent = array_sum(array_column($subjectStats, 'present_count'));
        $totalAbsent = array_sum(array_column($subjectStats, 'absent_count'));
        $totalClasses = $totalPresent + $totalAbsent;
        
        echo json_encode([
            'success' => true,
            'daily' => $dailyRecords,
            'subjects' => $subjectStats,
            'totals' => [
                'present' => (int)$totalPresent,
                'absent' => (int)$totalAbsent,
                'total' => (int)$totalClasses,
                'percentage' => $totalClasses > 0 ? round($totalPresent / $totalClasses * 100, 1) : 0
            ]
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// TEACHER ANALYTICS — attendance stats for teacher's classes
// ============================================================================
if ($action === 'getTeacherAnalytics') {
    requireLogin();
    
    $teacherId = $_SESSION['user_id'];
    $range = sanitize($_GET['range'] ?? 'week');
    $subject = sanitize($_GET['subject'] ?? 'all');
    $regId = sanitize($_GET['reg_id'] ?? '');
    
    if ($range === 'all') {
        $startDate = '2000-01-01'; // Fetch all records
    } else {
        $days = $range === 'month' ? 30 : ($range === '2week' ? 14 : 7);
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
    }
    
    try {
        // 1. Daily attendance counts (present vs absent per day)
        if ($regId !== '') {
            $sql = "
                SELECT 
                    dt.attendance_date,
                    COALESCE(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END), 0) AS present_count,
                    COUNT(dt.attendance_date) - COALESCE(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END), 0) AS absent_count,
                    COUNT(dt.attendance_date) AS total_count
                FROM (
                    SELECT DISTINCT attendance_date, subject_id 
                    FROM attendance 
                    WHERE teacher_id = ? AND attendance_date >= ?
            ";
            $params = [$teacherId, $startDate];
            
            if ($subject !== 'all') {
                $sql .= " AND subject_id = (SELECT id FROM subjects WHERE subject_name = ? LIMIT 1)";
                $params[] = $subject;
            }
            
            $sql .= "
                ) dt
                LEFT JOIN attendance a ON dt.attendance_date = a.attendance_date 
                    AND dt.subject_id = a.subject_id
                    AND a.teacher_id = ? 
                    AND a.student_id = (SELECT id FROM users WHERE (reg_id = ? OR full_name LIKE ?) AND role='student' LIMIT 1)
                GROUP BY dt.attendance_date 
                ORDER BY dt.attendance_date ASC
            ";
            $params[] = $teacherId;
            $params[] = $regId;
            $params[] = "%$regId%";
        } else {
            // Calculate true attendance based on enrollment per subject session
            $sql = "
                SELECT 
                    a.attendance_date,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                    SUM(q.enrolled_count) AS total_count,
                    SUM(q.enrolled_count) - SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS absent_count
                FROM attendance a
                JOIN (
                    SELECT subject_id, COUNT(*) as enrolled_count 
                    FROM student_subjects 
                    GROUP BY subject_id
                ) q ON a.subject_id = q.subject_id
                LEFT JOIN subjects s ON a.subject_id = s.id
                WHERE a.teacher_id = ? AND a.attendance_date >= ?
            ";
            $params = [$teacherId, $startDate];
            
            if ($subject !== 'all') {
                $sql .= " AND s.subject_name = ?";
                $params[] = $subject;
            }
            $sql .= " GROUP BY a.attendance_date ORDER BY a.attendance_date ASC";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dailyStats = $stmt->fetchAll();
        
        // 2. Subject-wise summary
        if ($regId !== '') {
            $sql2 = "
                SELECT 
                    COALESCE(s.subject_name, 'Unknown') AS subject_name,
                    COUNT(dt.attendance_date) AS total_records,
                    COALESCE(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END), 0) AS present_count,
                    COUNT(dt.attendance_date) - COALESCE(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END), 0) AS absent_count
                FROM (
                    SELECT DISTINCT attendance_date, subject_id
                    FROM attendance
                    WHERE teacher_id = ? AND attendance_date >= ?
                ) dt
                JOIN subjects s ON dt.subject_id = s.id
                LEFT JOIN attendance a ON a.subject_id = dt.subject_id 
                    AND a.attendance_date = dt.attendance_date 
                    AND a.teacher_id = ?
                    AND a.student_id = (SELECT id FROM users WHERE (reg_id = ? OR full_name LIKE ?) AND role='student' LIMIT 1)
            ";
            $params2 = [$teacherId, $startDate, $teacherId, $regId, "%$regId%"];
            
            if ($subject !== 'all') {
                $sql2 .= " WHERE s.subject_name = ?";
                $params2[] = $subject;
            }
            $sql2 .= " GROUP BY dt.subject_id, s.subject_name ORDER BY s.subject_name";
        } else {
            $sql2 = "
                SELECT 
                    COALESCE(s.subject_name, 'Unknown') AS subject_name,
                    COUNT(DISTINCT a.attendance_date) * q.enrolled_count AS total_records,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                    (COUNT(DISTINCT a.attendance_date) * q.enrolled_count) - SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS absent_count
                FROM attendance a
                JOIN subjects s ON a.subject_id = s.id
                JOIN (
                    SELECT subject_id, COUNT(*) as enrolled_count 
                    FROM student_subjects 
                    GROUP BY subject_id
                ) q ON a.subject_id = q.subject_id
                WHERE a.teacher_id = ? AND a.attendance_date >= ?
            ";
            $params2 = [$teacherId, $startDate];
            
            if ($subject !== 'all') {
                $sql2 .= " AND s.subject_name = ?";
                $params2[] = $subject;
            }
            $sql2 .= " GROUP BY s.id, s.subject_name ORDER BY s.subject_name";
        }
        
        $stmt = $pdo->prepare($sql2);
        $stmt->execute($params2);
        $subjectStats = $stmt->fetchAll();
        
        $totalPresent = array_sum(array_column($subjectStats, 'present_count'));
        $totalAbsent = array_sum(array_column($subjectStats, 'absent_count'));
        $totalRecords = array_sum(array_column($subjectStats, 'total_records'));
        
        echo json_encode([
            'success' => true,
            'daily' => $dailyStats,
            'subjects' => $subjectStats,
            'totals' => [
                'present' => (int)$totalPresent,
                'absent' => (int)$totalAbsent,
                'total' => (int)$totalRecords,
                'percentage' => $totalRecords > 0 ? round($totalPresent / $totalRecords * 100, 1) : 0
            ]
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// GET STUDENT REPORT — search by reg_id or name, return subject-wise attendance
// ============================================================================
if ($action === 'getStudentReport') {
    requireLogin();
    
    $query = sanitize($_GET['reg_id'] ?? $_GET['query'] ?? '');
    $range = sanitize($_GET['range'] ?? 'all');
    $fromTeacher = isset($_GET['from_teacher']) && $_GET['from_teacher'] == '1';
    
    if (!$query) {
        if ($fromTeacher) {
            // Fetch all students associated with this teacher's classes with aggregate stats
            $teacherId = $_SESSION['user_id'];
            if ($range === 'all') {
                $startDate = '2000-01-01';
            } else {
                $days = $range === 'month' ? 30 : ($range === '2week' ? 14 : 7);
                $startDate = date('Y-m-d', strtotime("-{$days} days"));
            }

            // Get all students who have attendance marked by this teacher
            $stmt = $pdo->prepare("
                SELECT 
                    u.id, u.full_name, u.reg_id, u.photo_path, u.department, u.branch,
                    COUNT(a.id) as present_count,
                    (SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE teacher_id = ? AND attendance_date >= ?) as total_days
                FROM users u
                JOIN attendance a ON u.id = a.student_id
                WHERE a.teacher_id = ? AND a.attendance_date >= ? AND a.status = 'present'
                GROUP BY u.id
                ORDER BY u.full_name
            ");
            $stmt->execute([$teacherId, $startDate, $teacherId, $startDate]);
            $students = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'students' => $students,
                'count' => count($students)
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Please enter a search term']);
            exit;
        }
    }
    
    try {
        // Find student by exact reg_id first, then by partial name match
        $stmt = $pdo->prepare("
            SELECT id, full_name, email, reg_id, department, branch, photo_path
            FROM users 
            WHERE role = 'student' AND (reg_id = ? OR full_name LIKE ?)
            LIMIT 1
        ");
        $stmt->execute([$query, '%' . $query . '%']);
        $student = $stmt->fetch();
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }
        
        if ($range === 'all') {
            $startDate = '2000-01-01';
        } else {
            $days = $range === 'month' ? 30 : ($range === '2week' ? 14 : 7);
            $startDate = date('Y-m-d', strtotime("-{$days} days"));
        }

        // Get subject-wise attendance stats
        if ($fromTeacher) {
            $teacherId = $_SESSION['user_id'];
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(s.subject_name, 'Unknown') AS subject_name,
                    COALESCE(s.subject_code, 'N/A') AS subject_code,
                    (SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE subject_id = s.id AND teacher_id = ? AND attendance_date >= ?) AS total_classes,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                    (SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE subject_id = s.id AND teacher_id = ? AND attendance_date >= ?) - SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS absent_count
                FROM (
                    SELECT DISTINCT subject_id 
                    FROM attendance 
                    WHERE teacher_id = ? AND attendance_date >= ?
                ) taught
                JOIN subjects s ON taught.subject_id = s.id
                LEFT JOIN attendance a ON a.subject_id = s.id AND a.student_id = ? AND a.teacher_id = ? AND a.attendance_date >= ?
                GROUP BY s.id, s.subject_name, s.subject_code
                ORDER BY s.subject_name
            ");
            $stmt->execute([
                $teacherId, $startDate, 
                $teacherId, $startDate, 
                $teacherId, $startDate, 
                $student['id'], $teacherId, $startDate
            ]);
        } else {
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(s.subject_name, 'Unknown') AS subject_name,
                    COALESCE(s.subject_code, 'N/A') AS subject_code,
                    (SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE subject_id = s.id AND attendance_date >= ?) AS total_classes,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                    (SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE subject_id = s.id AND attendance_date >= ?) - SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS absent_count
                FROM attendance a
                LEFT JOIN subjects s ON a.subject_id = s.id
                WHERE a.student_id = ? AND a.attendance_date >= ?
                GROUP BY s.id, s.subject_name, s.subject_code
                ORDER BY s.subject_name
            ");
            $stmt->execute([$startDate, $startDate, $student['id'], $startDate]);
        }
        
        $attendance = $stmt->fetchAll();
        
        // Format for frontend compatibility
        $report = array_map(function($row) {
            $total = (int)$row['total_classes'];
            $present = (int)$row['present_count'];
            $absent = (int)$row['absent_count'];
            return [
                'subject_name' => $row['subject_name'],
                'subject_code' => $row['subject_code'],
                'subject' => $row['subject_name'],
                'total' => $total,
                'total_classes' => $total,
                'present' => $present,
                'present_count' => $present,
                'absent' => $absent,
                'absent_count' => $absent,
                'percentage' => $total > 0 ? round($present / $total * 100, 1) : 0
            ];
        }, $attendance);
        
        echo json_encode([
            'success' => true,
            'student' => [
                'name' => $student['full_name'],
                'full_name' => $student['full_name'],
                'email' => $student['email'],
                'reg_id' => $student['reg_id'],
                'regId' => $student['reg_id'],
                'department' => $student['department'] ?? '',
                'branch' => $student['branch'] ?? $student['department'] ?? '',
                'class' => $student['branch'] ?? '',
                'photo_path' => $student['photo_path'] ?? ''
            ],
            'attendance' => $report,
            'report' => $report
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// UPDATE ATTENDANCE — teacher updates a student's attendance for a specific date
// ============================================================================
if ($action === 'updateAttendance') {
    requireRole('teacher');
    
    $teacherId = $_SESSION['user_id'];
    $regId = sanitize($_POST['regId'] ?? '');
    $subjectName = sanitize($_POST['subject'] ?? '');
    $date = sanitize($_POST['date'] ?? '');
    $status = sanitize($_POST['status'] ?? 'present');
    
    if (!$regId || !$subjectName || !$date) {
        echo json_encode(['success' => false, 'message' => 'Registration ID, subject, and date are required']);
        exit;
    }
    
    // Validate status
    $status = strtolower($status);
    if (!in_array($status, ['present', 'absent'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status. Use present or absent']);
        exit;
    }

    // 1. Validate Date Range
    $inputDate = new DateTime($date);
    $today = new DateTime();
    $threeMonthsAgo = new DateTime();
    $threeMonthsAgo->modify('-3 months');

    if ($inputDate > $today) {
        echo json_encode(['success' => false, 'message' => 'Cannot set a future date.']);
        exit;
    }
    if ($inputDate < $threeMonthsAgo) {
        echo json_encode(['success' => false, 'message' => 'Cannot set a date older than 3 months.']);
        exit;
    }

    try {
        // Find subject by name to check lecture existence
        $stmt = $pdo->prepare("SELECT id FROM subjects WHERE subject_name = ? LIMIT 1");
        $stmt->execute([$subjectName]);
        $subjectRow = $stmt->fetch();
        
        if (!$subjectRow) {
            echo json_encode(['success' => false, 'message' => "Subject '$subjectName' not found in system."]);
            exit;
        }
        $subjectId = $subjectRow['id'];

        // 2. Check if a lecture of this subject happened on that particular date (any record exists)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM attendance 
            WHERE subject_id = ? AND attendance_date = ?
        ");
        $stmt->execute([$subjectId, $date]);
        $lectureExists = (int)$stmt->fetchColumn() > 0;

        if (!$lectureExists) {
            echo json_encode(['success' => false, 'message' => "No lecture record found for '$subjectName' on $date. You cannot update attendance for a date when no lecture happened."]);
            exit;
        }
        // Find student by reg_id
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE reg_id = ? AND role = 'student' LIMIT 1");
        $stmt->execute([$regId]);
        $student = $stmt->fetch();
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }
        

        
        // Check if attendance record already exists for this student/subject/date
        $stmt = $pdo->prepare("
            SELECT id FROM attendance 
            WHERE student_id = ? AND subject_id = ? AND attendance_date = ?
            LIMIT 1
        ");
        $stmt->execute([$student['id'], $subjectId, $date]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET status = ?, teacher_id = ?, marked_at = NOW(), marking_method = 'manual'
                WHERE id = ?
            ");
            $stmt->execute([$status, $teacherId, $existing['id']]);
            $action_taken = 'updated';
        } else {
            // Insert new record
            $stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, subject_id, teacher_id, attendance_date, status, marking_method, marked_at)
                VALUES (?, ?, ?, ?, ?, 'manual', NOW())
            ");
            $stmt->execute([$student['id'], $subjectId, $teacherId, $date, $status]);
            $action_taken = 'added';
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Attendance {$action_taken} for {$student['full_name']} on {$date} — {$status}",
            'action' => $action_taken
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
        // Get current stats
        $statsStmt = $pdo->prepare("
            SELECT COUNT(*) as total_classes,
                   SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count
            FROM attendance
            WHERE student_id = ? AND subject_id = ?
        ");
        $statsStmt->execute([$studentId, $subjectId]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

        $totalClasses = (int)($stats['total_classes'] ?? 0);
        $presentCount = (int)($stats['present_count'] ?? 0);

        $currentPct = $totalClasses > 0 ? ($presentCount / $totalClasses) * 100 : 0;
        $targetPct = min($currentPct + $percentageIncrease, 100);
        $classesToAdd = 0;

        if ($targetPct == 100 && $totalClasses > $presentCount) {
             // Cannot mathematically reach 100% if there is any absence
             $targetPct = 99.5;
        }

        if ($targetPct < 100 && $targetPct > $currentPct) {
             $x = ($targetPct * $totalClasses - 100 * $presentCount) / (100 - $targetPct);
             if ($x > 0) {
                 $classesToAdd = (int)ceil($x);
             }
        }

        if ($classesToAdd > 0) {
            $dateStmt = $pdo->prepare("SELECT MAX(attendance_date) as max_date FROM attendance WHERE student_id = ? AND subject_id = ?");
            $dateStmt->execute([$studentId, $subjectId]);
            $dateRow = $dateStmt->fetch(PDO::FETCH_ASSOC);
            $startDateStr = !empty($dateRow['max_date']) ? $dateRow['max_date'] : date('Y-m-d');
            
            $startDate = new DateTime($startDateStr);
            $insertStmt = $pdo->prepare("
                INSERT IGNORE INTO attendance (student_id, subject_id, attendance_date, status, marking_method)
                VALUES (?, ?, ?, 'present', 'manual')
            ");

            for ($i = 0; $i < $classesToAdd; $i++) {
                $startDate->modify('+1 day');
                $insertStmt->execute([$studentId, $subjectId, $startDate->format('Y-m-d')]);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Added ' . $classesToAdd . ' classes to increase attendance',
            'classes_added' => $classesToAdd
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
                    (SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE subject_id = s.id) AS total_classes,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                    (SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE subject_id = s.id) - SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS absent_count,
                    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / NULLIF((SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE subject_id = s.id), 0)) * 100, 2) AS percentage
                FROM attendance a
                JOIN subjects s ON a.subject_id = s.id
                WHERE a.student_id = ? AND a.subject_id = ?
                GROUP BY s.id, s.subject_name, s.subject_code
            ");
            $stmt->execute([$studentId, $subjectId]);
        } else {
            // All subjects report
            $stmt = $pdo->prepare("
                SELECT 
                    s.subject_name,
                    s.subject_code,
                    (SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE subject_id = s.id) AS total_classes,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                    (SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE subject_id = s.id) - SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS absent_count,
                    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / NULLIF((SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE subject_id = s.id), 0)) * 100, 2) AS percentage
                FROM attendance a
                JOIN subjects s ON a.subject_id = s.id
                WHERE a.student_id = ?
                GROUP BY s.id, s.subject_name, s.subject_code
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
