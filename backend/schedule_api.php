<?php
/**
 * Schedule Management API
 * Handles: Get/Add/Update/Delete teacher schedules
 * Place in: backend/
 */

require_once 'db_config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ============================================================================
// GET TEACHER SCHEDULE
// ============================================================================
if ($action === 'getTeacherSchedule') {
    $teacherId = intval($_GET['teacher_id'] ?? 0);

    if (!$teacherId) {
        echo json_encode(['success' => false, 'message' => 'Teacher ID required']);
        exit;
    }

    try {
        // Try to get from teacher_schedules table
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
        $dayOrder  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        foreach ($dayOrder as $d) $schedule[$d] = [];

        foreach ($rows as $r) {
            $schedule[$r['day_of_week']][] = [
                'id'      => $r['id'],
                'time'    => date('h:i A', strtotime($r['start_time'])) . ' - ' . date('h:i A', strtotime($r['end_time'])),
                'subject' => $r['subject_name'],
                'code'    => $r['subject_code'] ?? '',
                'cls'     => $r['class_section'] ?? '',
            ];
        }

        echo json_encode(['success' => true, 'schedule' => $schedule, 'count' => count($rows)]);

    } catch (PDOException $e) {
        // Table may not exist yet — return empty schedule with a note
        echo json_encode([
            'success'  => false,
            'message'  => 'Schedule table not yet created. Run the SQL migration. Error: ' . $e->getMessage()
        ]);
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
        echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        exit;
    }

    // Validate day
    $validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    if (!in_array($day, $validDays)) {
        echo json_encode(['success' => false, 'message' => 'Invalid day']);
        exit;
    }

    try {
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
        echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE teacher_schedules
            SET teacher_id = ?, day_of_week = ?, start_time = ?, end_time = ?,
                subject_name = ?, subject_code = ?, class_section = ?
            WHERE id = ?
        ");
        $stmt->execute([$teacherId, $day, $startTime, $endTime, $subject, $code, $cls, $schedId]);

        echo json_encode(['success' => true, 'message' => 'Schedule updated', 'id' => $schedId]);

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

        echo json_encode(['success' => true, 'message' => 'Schedule deleted']);

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

    try {
        $stmt = $pdo->query("
            SELECT
                ts.id,
                u.full_name AS teacher_name,
                u.reg_id    AS teacher_reg_id,
                ts.day_of_week,
                ts.start_time,
                ts.end_time,
                ts.subject_name,
                ts.subject_code,
                ts.class_section
            FROM teacher_schedules ts
            JOIN users u ON ts.teacher_id = u.id
            ORDER BY u.full_name, FIELD(ts.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), ts.start_time
        ");
        $schedules = $stmt->fetchAll();

        echo json_encode(['success' => true, 'schedules' => $schedules, 'count' => count($schedules)]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Invalid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>