<?php
/**
 * Enhanced Schedule Management API
 * Features: Search by teacher, Full CRUD operations, Real data management
 */

require_once 'db_config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ============================================================================
// GET TEACHER SCHEDULE (By Teacher ID)
// ============================================================================
if ($action === 'getTeacherSchedule') {
    requireLogin();
    
    $teacherId = intval($_GET['teacher_id'] ?? 0);

    if (!$teacherId) {
        echo json_encode(['success' => false, 'message' => 'Teacher ID required']);
        exit;
    }

    try {
        // Get teacher info first
        $stmt = $pdo->prepare("
            SELECT id, reg_id, full_name, email, department, branch, phone, dob
            FROM users
            WHERE id = ? AND role = 'teacher' AND is_active = 1
        ");
        $stmt->execute([$teacherId]);
        $teacher = $stmt->fetch();
        
        if (!$teacher) {
            echo json_encode(['success' => false, 'message' => 'Teacher not found']);
            exit;
        }
        
        // Get schedule
        $stmt = $pdo->prepare("
            SELECT id, day_of_week, start_time, end_time, subject_name, subject_code, class_section
            FROM teacher_schedules
            WHERE teacher_id = ?
            ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), start_time
        ");
        $stmt->execute([$teacherId]);
        $rows = $stmt->fetchAll();

        // Group by day
        $schedule = [];
        $dayOrder = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        foreach ($dayOrder as $d) $schedule[$d] = [];

        foreach ($rows as $r) {
            $schedule[$r['day_of_week']][] = [
                'id'      => $r['id'],
                'start_time' => $r['start_time'],
                'end_time' => $r['end_time'],
                'time'    => date('h:i A', strtotime($r['start_time'])) . ' - ' . date('h:i A', strtotime($r['end_time'])),
                'subject' => $r['subject_name'],
                'code'    => $r['subject_code'] ?? '',
                'class'   => $r['class_section'] ?? '',
            ];
        }

        echo json_encode([
            'success' => true,
            'teacher' => $teacher,
            'schedule' => $schedule,
            'count' => count($rows)
        ]);

    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ============================================================================
// SEARCH TEACHER SCHEDULES (By name, reg_id, email)
// ============================================================================
if ($action === 'searchTeacherSchedule') {
    requireRole('admin');
    
    $query = sanitize($_GET['query'] ?? '');
    
    if (strlen($query) < 2) {
        echo json_encode(['success' => false, 'message' => 'Search query too short']);
        exit;
    }
    
    try {
        // Find teacher by name, reg_id, or email
        $stmt = $pdo->prepare("
            SELECT id, reg_id, full_name, email, department, branch, phone, dob
            FROM users
            WHERE role = 'teacher' 
              AND is_active = 1
              AND (full_name LIKE ? OR reg_id LIKE ? OR email LIKE ?)
            LIMIT 1
        ");
        $stmt->execute(["%$query%", "%$query%", "%$query%"]);
        $teacher = $stmt->fetch();
        
        if (!$teacher) {
            echo json_encode(['success' => false, 'message' => 'Teacher not found']);
            exit;
        }
        
        // Get their schedule
        $stmt = $pdo->prepare("
            SELECT id, day_of_week, start_time, end_time, subject_name, subject_code, class_section
            FROM teacher_schedules
            WHERE teacher_id = ?
            ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), start_time
        ");
        $stmt->execute([$teacher['id']]);
        $rows = $stmt->fetchAll();

        // Group by day
        $schedule = [];
        $dayOrder = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        foreach ($dayOrder as $d) $schedule[$d] = [];

        foreach ($rows as $r) {
            $schedule[$r['day_of_week']][] = [
                'id'      => $r['id'],
                'start_time' => $r['start_time'],
                'end_time' => $r['end_time'],
                'time'    => date('h:i A', strtotime($r['start_time'])) . ' - ' . date('h:i A', strtotime($r['end_time'])),
                'subject' => $r['subject_name'],
                'code'    => $r['subject_code'] ?? '',
                'class'   => $r['class_section'] ?? '',
            ];
        }

        echo json_encode([
            'success' => true,
            'teacher' => $teacher,
            'schedule' => $schedule,
            'count' => count($rows)
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// ADD SCHEDULE
// ============================================================================
if ($action === 'addSchedule') {
    requireRole('admin');

    $teacherId   = intval($_POST['teacher_id']    ?? 0);
    $day         = sanitize($_POST['day']         ?? '');
    $subject     = sanitize($_POST['subject']     ?? '');
    $code        = sanitize($_POST['subject_code']?? '');
    $cls         = sanitize($_POST['class_section']?? '');
    $startTime   = $_POST['start_time']  ?? '';
    $endTime     = $_POST['end_time']    ?? '';

    if (!$teacherId || !$day || !$subject || !$startTime || !$endTime) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    // Validate day
    $validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    if (!in_array($day, $validDays)) {
        echo json_encode(['success' => false, 'message' => 'Invalid day']);
        exit;
    }
    
    // Validate time format
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $endTime)) {
        echo json_encode(['success' => false, 'message' => 'Invalid time format']);
        exit;
    }

    try {
        // Check for conflicts
        $stmt = $pdo->prepare("
            SELECT id FROM teacher_schedules
            WHERE teacher_id = ? 
              AND day_of_week = ?
              AND (
                  (start_time <= ? AND end_time > ?)
                  OR (start_time < ? AND end_time >= ?)
                  OR (start_time >= ? AND end_time <= ?)
              )
        ");
        $stmt->execute([$teacherId, $day, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Schedule conflicts with existing entry']);
            exit;
        }
        
        // Insert schedule
        $stmt = $pdo->prepare("
            INSERT INTO teacher_schedules
                (teacher_id, day_of_week, start_time, end_time, subject_name, subject_code, class_section)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$teacherId, $day, $startTime, $endTime, $subject, $code, $cls]);
        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Schedule added successfully',
            'id'      => $newId
        ]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// UPDATE SCHEDULE
// ============================================================================
if ($action === 'updateSchedule') {
    requireRole('admin');

    $schedId   = intval($_POST['id']            ?? 0);
    $teacherId = intval($_POST['teacher_id']    ?? 0);
    $day       = sanitize($_POST['day']         ?? '');
    $subject   = sanitize($_POST['subject']     ?? '');
    $code      = sanitize($_POST['subject_code']?? '');
    $cls       = sanitize($_POST['class_section']?? '');
    $startTime = $_POST['start_time']  ?? '';
    $endTime   = $_POST['end_time']    ?? '';

    if (!$schedId || !$day || !$subject || !$startTime || !$endTime) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    // Validate day
    $validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    if (!in_array($day, $validDays)) {
        echo json_encode(['success' => false, 'message' => 'Invalid day']);
        exit;
    }
    
    // Validate time format
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $endTime)) {
        echo json_encode(['success' => false, 'message' => 'Invalid time format']);
        exit;
    }

    try {
        // Check for conflicts (excluding current schedule)
        $stmt = $pdo->prepare("
            SELECT id FROM teacher_schedules
            WHERE teacher_id = ? 
              AND day_of_week = ?
              AND id != ?
              AND (
                  (start_time <= ? AND end_time > ?)
                  OR (start_time < ? AND end_time >= ?)
                  OR (start_time >= ? AND end_time <= ?)
              )
        ");
        $stmt->execute([$teacherId, $day, $schedId, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Schedule conflicts with existing entry']);
            exit;
        }
        
        // Update schedule
        $stmt = $pdo->prepare("
            UPDATE teacher_schedules
            SET day_of_week = ?, start_time = ?, end_time = ?,
                subject_name = ?, subject_code = ?, class_section = ?
            WHERE id = ?
        ");
        $stmt->execute([$day, $startTime, $endTime, $subject, $code, $cls, $schedId]);

        echo json_encode([
            'success' => true,
            'message' => 'Schedule updated successfully',
            'id' => $schedId
        ]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// DELETE SCHEDULE
// ============================================================================
if ($action === 'deleteSchedule') {
    requireRole('admin');

    $schedId = intval($_POST['id'] ?? 0);

    if (!$schedId) {
        echo json_encode(['success' => false, 'message' => 'Schedule ID required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM teacher_schedules WHERE id = ?");
        $stmt->execute([$schedId]);

        echo json_encode([
            'success' => true,
            'message' => 'Schedule deleted successfully'
        ]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// GET ALL SCHEDULES (for overview)
// ============================================================================
if ($action === 'getAllSchedules') {
    requireRole('admin');
    
    $search = sanitize($_GET['search'] ?? '');

    try {
        $sql = "
            SELECT
                ts.id,
                ts.teacher_id,
                u.full_name AS teacher_name,
                u.reg_id    AS teacher_reg_id,
                u.email     AS teacher_email,
                ts.day_of_week,
                ts.start_time,
                ts.end_time,
                ts.subject_name,
                ts.subject_code,
                ts.class_section
            FROM teacher_schedules ts
            JOIN users u ON ts.teacher_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($search) {
            $sql .= " AND (u.full_name LIKE ? OR u.reg_id LIKE ? OR u.email LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $sql .= " ORDER BY u.full_name, FIELD(ts.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), ts.start_time";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $schedules = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'schedules' => $schedules,
            'count' => count($schedules)
        ]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// GET SCHEDULE BY ID (for editing)
// ============================================================================
if ($action === 'getScheduleById') {
    requireRole('admin');
    
    $schedId = intval($_GET['id'] ?? 0);
    
    if (!$schedId) {
        echo json_encode(['success' => false, 'message' => 'Schedule ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ts.*,
                u.full_name AS teacher_name,
                u.reg_id AS teacher_reg_id
            FROM teacher_schedules ts
            JOIN users u ON ts.teacher_id = u.id
            WHERE ts.id = ?
        ");
        $stmt->execute([$schedId]);
        $schedule = $stmt->fetch();
        
        if (!$schedule) {
            echo json_encode(['success' => false, 'message' => 'Schedule not found']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'schedule' => $schedule
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Invalid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>