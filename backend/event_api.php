<?php
require_once 'db_config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'createEvent') {
    requireRole('teacher');
    $eventName = sanitize($_POST['event_name'] ?? '');
    $eventDate = sanitize($_POST['event_date'] ?? '');
    $eventTime = sanitize($_POST['event_time'] ?? '');
    $generateCode = isset($_POST['generate_code']) && $_POST['generate_code'] === 'true';
    $teacherId = $_SESSION['user_id'];

    if (!$eventName || !$eventDate || !$eventTime) {
        echo json_encode(['success' => false, 'message' => 'Missing event details']);
        exit;
    }

    $eventTimestamp = strtotime("$eventDate $eventTime");
    $currentTimestamp = time();
    $fiveDaysAgo = strtotime('-5 days');

    if ($eventTimestamp > $currentTimestamp) {
        echo json_encode(['success' => false, 'message' => 'Event date and time cannot be in the future.']);
        exit;
    }

    if ($eventTimestamp < $fiveDaysAgo) {
        echo json_encode(['success' => false, 'message' => 'Event date and time cannot be older than 5 days.']);
        exit;
    }

    try {
        $uniqueCode = null;
        $expiresAt = null;

        if ($generateCode) {
            $uniqueCode = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+3 minutes'));
        }

        $stmt = $pdo->prepare("INSERT INTO events (event_name, event_date, event_time, teacher_id, unique_code, code_expires_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$eventName, $eventDate, $eventTime, $teacherId, $uniqueCode, $expiresAt]);
        $eventId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'event_id' => $eventId,
            'unique_code' => $uniqueCode,
            'expires_at' => $expiresAt
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'markEventAttendance') {
    // Can be called by teacher (scanning student QR) or student (entering code)
    requireLogin();
    
    $method = sanitize($_POST['method'] ?? 'qr'); // 'qr' or 'unique_code'
    
    try {
        if ($method === 'qr') {
            requireRole('teacher');
            $eventId = intval($_POST['event_id']);
            $studentRegId = sanitize($_POST['student_reg_id']);
            
            // Find student ID and details
            $stmt = $pdo->prepare("SELECT id, full_name, reg_id FROM users WHERE reg_id = ? AND role = 'student'");
            $stmt->execute([$studentRegId]);
            $student = $stmt->fetch();
            
            if (!$student) {
                echo json_encode(['success' => false, 'message' => 'Student not found']);
                exit;
            }
            $studentId = $student['id'];
            $studentName = $student['full_name'];
            $studentReg = $student['reg_id'];
        } else {
            // Student entering code
            requireRole('student');
            $uniqueCode = strtoupper(sanitize($_POST['unique_code'] ?? ''));
            $studentId = $_SESSION['user_id'];
            
            $stmt = $pdo->prepare("SELECT id, event_name FROM events WHERE unique_code = ? AND code_expires_at > NOW()");
            $stmt->execute([$uniqueCode]);
            $event = $stmt->fetch();
            
            if (!$event) {
                echo json_encode(['success' => false, 'message' => 'Invalid or expired code']);
                exit;
            }
            $eventId = $event['id'];
            
            // Get student info for return
            $stStmt = $pdo->prepare("SELECT full_name, reg_id FROM users WHERE id = ?");
            $stStmt->execute([$studentId]);
            $st = $stStmt->fetch();
            $studentName = $st['full_name'] ?? 'Student';
            $studentReg = $st['reg_id'] ?? '';
        }

        // Check for duplicate attendance
        $checkStmt = $pdo->prepare("SELECT id FROM event_attendance WHERE event_id = ? AND student_id = ?");
        $checkStmt->execute([$eventId, $studentId]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Student already scanned / marked for this event']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO event_attendance (event_id, student_id, marking_method) VALUES (?, ?, ?)");
        $stmt->execute([$eventId, $studentId, $method]);
        
        // Fetch event name if not already fetched
        if (!isset($event['event_name'])) {
            $stmt = $pdo->prepare("SELECT event_name FROM events WHERE id = ?");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch();
        }
        $eventName = $event['event_name'] ?? 'Event';

        $successMessage = ($method === 'qr') 
            ? "Attendance marked successfully for $studentName" 
            : "Attendance marked successfully for $eventName";

        echo json_encode([
            'success' => true, 
            'message' => $successMessage,
            'event_name' => $eventName,
            'student_name' => $studentName,
            'student_reg_id' => $studentReg
        ]);
    } catch (PDOException $e) {
        // Handle race conditions where a duplicate might still somehow violate a unique constraint if one exists
        if ($e->getCode() == 23000) {
            echo json_encode(['success' => false, 'message' => 'Student already scanned / marked for this event']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
    exit;
}

if ($action === 'verifyEventStudent') {
    requireRole('teacher');
    $eventId = intval($_POST['event_id']);
    $studentRegId = sanitize($_POST['student_reg_id']);
    
    try {
        // Find student ID and details
        $stmt = $pdo->prepare("SELECT id, full_name, reg_id FROM users WHERE reg_id = ? AND role = 'student'");
        $stmt->execute([$studentRegId]);
        $student = $stmt->fetch();
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }
        
        // Check for duplicate attendance in DB
        $checkStmt = $pdo->prepare("SELECT id FROM event_attendance WHERE event_id = ? AND student_id = ?");
        $checkStmt->execute([$eventId, $student['id']]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Student already scanned / marked for this event']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Successfully attendance marked ' . $student['full_name'],
            'student_name' => $student['full_name'],
            'student_reg_id' => $student['reg_id']
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'saveEventAttendanceBatch') {
    requireRole('teacher');
    $eventId = intval($_POST['event_id']);
    $regIds = isset($_POST['reg_ids']) ? json_decode($_POST['reg_ids'], true) : [];
    
    if (empty($regIds)) {
        echo json_encode(['success' => true, 'message' => 'No attendance to save.']);
        exit;
    }
    
    try {
        $savedCount = 0;
        $stmtFind = $pdo->prepare("SELECT id FROM users WHERE reg_id = ? AND role = 'student'");
        $stmtCheck = $pdo->prepare("SELECT id FROM event_attendance WHERE event_id = ? AND student_id = ?");
        $stmtInsert = $pdo->prepare("INSERT INTO event_attendance (event_id, student_id, marking_method) VALUES (?, ?, 'qr')");
        
        foreach ($regIds as $regId) {
            // Find student
            $stmtFind->execute([sanitize($regId)]);
            $student = $stmtFind->fetch();
            if ($student) {
                // Check duplicate
                $stmtCheck->execute([$eventId, $student['id']]);
                if (!$stmtCheck->fetch()) {
                    $stmtInsert->execute([$eventId, $student['id']]);
                    $savedCount++;
                }
            }
        }
        
        echo json_encode(['success' => true, 'message' => "$savedCount attendance records saved successfully."]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error saving attendance: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'getEventStats') {
    requireRole('teacher');
    $eventId = intval($_GET['event_id']);
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.full_name, u.reg_id, ea.scanned_at 
            FROM event_attendance ea
            JOIN users u ON ea.student_id = u.id
            WHERE ea.event_id = ?
            ORDER BY ea.scanned_at DESC
        ");
        $stmt->execute([$eventId]);
        $students = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'students' => $students]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
