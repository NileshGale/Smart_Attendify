<?php
require_once 'db_config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Auto-migration for InfinityFree
try {
    $pdo->exec("ALTER TABLE event_attendance ADD COLUMN distance_meters INT DEFAULT NULL");
    $pdo->exec("ALTER TABLE event_attendance ADD COLUMN accuracy_meters INT DEFAULT NULL");
} catch (Exception $e) { }

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

    // Geolocation parameters
    $teacherLat = isset($_POST['teacher_lat']) && $_POST['teacher_lat'] !== '' ? floatval($_POST['teacher_lat']) : null;
    $teacherLng = isset($_POST['teacher_lng']) && $_POST['teacher_lng'] !== '' ? floatval($_POST['teacher_lng']) : null;
    $maxDist = isset($_POST['max_distance_meters']) ? intval($_POST['max_distance_meters']) : 50;
    $accuracy = isset($_POST['teacher_accuracy']) ? floatval($_POST['teacher_accuracy']) : null;

    try {
        $uniqueCode = null;
        $expiresAt = null;

        if ($generateCode) {
            $uniqueCode = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+3 minutes'));
        }

        $stmt = $pdo->prepare("INSERT INTO events (event_name, event_date, event_time, teacher_id, unique_code, code_expires_at, teacher_lat, teacher_lng, max_distance_meters, teacher_accuracy) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$eventName, $eventDate, $eventTime, $teacherId, $uniqueCode, $expiresAt, $teacherLat, $teacherLng, $maxDist, $accuracy]);
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
            
            // Get student coordinates if provided
            $studentLat = isset($_POST['student_lat']) && $_POST['student_lat'] !== '' ? floatval($_POST['student_lat']) : null;
            $studentLng = isset($_POST['student_lng']) && $_POST['student_lng'] !== '' ? floatval($_POST['student_lng']) : null;

            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("SELECT id, event_name, teacher_lat, teacher_lng, max_distance_meters FROM events WHERE unique_code = ? AND code_expires_at > ?");
            $stmt->execute([$uniqueCode, $now]);
            $event = $stmt->fetch();
            
            if (!$event) {
                echo json_encode(['success' => false, 'message' => 'Invalid or expired code']);
                exit;
            }

            // Geolocation proximity check and distance calculation
            $distance = null;
            if ($event['teacher_lat'] !== null && $studentLat !== null && $studentLng !== null) {
                $distance = haversineDistance(
                    $event['teacher_lat'], $event['teacher_lng'],
                    $studentLat, $studentLng
                );

                if ($event['max_distance_meters'] !== null) {
                    $allowedDistance = intval($event['max_distance_meters']) + 30; // 30m accuracy buffer

                    if ($distance > $allowedDistance) {
                        $distRounded = round($distance);
                        echo json_encode([
                            'success' => false, 
                            'message' => "Location match failed. You are {$distRounded}m away from the event location (Max allowed: {$allowedDistance}m).",
                            'geo_rejected' => true,
                            'distance' => $distRounded,
                            'max_distance' => $event['max_distance_meters']
                        ]);
                        exit;
                    }
                }
            } elseif ($event['teacher_lat'] !== null && $event['max_distance_meters'] !== null) {
                // Geo-lock is required but GPS was not provided
                echo json_encode([
                    'success' => false, 
                    'message' => 'This event requires location access. Please enable GPS and try again.',
                    'geo_required' => true
                ]);
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

        $storedDistance = (isset($distance) && $distance !== null) ? round($distance) : null;
        $storedAccuracy = ($studentAccuracy > 0) ? round($studentAccuracy) : null;
        $stmt = $pdo->prepare("INSERT INTO event_attendance (event_id, student_id, marking_method, distance_meters, accuracy_meters) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$eventId, $studentId, $method, $storedDistance, $storedAccuracy]);
        
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
            SELECT u.full_name, u.reg_id, ea.scanned_at, ea.distance_meters, ea.accuracy_meters 
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
