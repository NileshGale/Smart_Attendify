<?php
/**
 * Enhanced User Management API
 * Handles: User profile, search, subject allocation, admin operations
 */

require_once 'db_config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ============================================================================
// GET USER PROFILE (Current logged-in user)
// ============================================================================
if ($action === 'getMyProfile') {
    requireLogin();
    
    $userId = $_SESSION['user_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, reg_id, username, email, full_name, role, department, branch, 
                   phone, dob, photo_path, qr_code_path, qr_code_data, created_at
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Get enrolled subjects
        if ($user['role'] === 'student') {
            $stmt = $pdo->prepare("
                SELECT s.id, s.subject_name, s.subject_code, s.credits, s.department
                FROM student_subjects ss
                JOIN subjects s ON ss.subject_id = s.id
                WHERE ss.student_id = ?
                ORDER BY s.subject_name
            ");
            $stmt->execute([$userId]);
            $user['subjects'] = $stmt->fetchAll();
            
        } else if ($user['role'] === 'teacher') {
            $stmt = $pdo->prepare("
                SELECT s.id, s.subject_name, s.subject_code, s.department
                FROM teacher_subjects ts
                JOIN subjects s ON ts.subject_id = s.id
                WHERE ts.teacher_id = ?
                ORDER BY s.subject_name
            ");
            $stmt->execute([$userId]);
            $user['subjects'] = $stmt->fetchAll();
        }
        
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// UPDATE MY PROFILE
// ============================================================================
if ($action === 'updateMyProfile') {
    requireLogin();
    
    $userId = $_SESSION['user_id'];
    $fullName = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $dob = sanitize($_POST['dob'] ?? '');
    $department = sanitize($_POST['department'] ?? '');
    $branch = sanitize($_POST['branch'] ?? '');
    
    if (!$fullName) {
        echo json_encode(['success' => false, 'message' => 'Full name is required']);
        exit;
    }
    
    // Validate DOB if provided
    if ($dob && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }
    
    // Validate phone if provided
    if ($phone && !preg_match('/^[0-9]{10}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Phone must be 10 digits']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, phone = ?, dob = ?, department = ?, branch = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $fullName, 
            $phone ?: null, 
            $dob ?: null, 
            $department ?: null, 
            $branch ?: null, 
            $userId
        ]);
        
        $_SESSION['full_name'] = $fullName;
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// UPLOAD PROFILE PHOTO
// ============================================================================
if ($action === 'uploadProfilePhoto') {
    requireLogin();
    
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No photo uploaded']);
        exit;
    }
    
    $file = $_FILES['photo'];
    $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
    $maxSize = 2 * 1024 * 1024; // 2 MB
    
    if (!in_array($file['type'], $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG and PNG allowed']);
        exit;
    }
    
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'Photo must be less than 2MB']);
        exit;
    }
    
    $uploadDir = __DIR__ . '/../uploads/photos/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
    $destPath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save photo']);
        exit;
    }
    
    $photoPath = 'uploads/photos/' . $filename;
    
    try {
        // Delete old photo if exists
        $stmt = $pdo->prepare("SELECT photo_path FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $oldPhoto = $stmt->fetchColumn();
        
        if ($oldPhoto && file_exists(__DIR__ . '/../' . $oldPhoto)) {
            unlink(__DIR__ . '/../' . $oldPhoto);
        }
        
        // Update database
        $stmt = $pdo->prepare("UPDATE users SET photo_path = ? WHERE id = ?");
        $stmt->execute([$photoPath, $_SESSION['user_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Photo uploaded successfully',
            'photo_path' => $photoPath
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// SEARCH USERS (Enhanced with email support)
// ============================================================================
if ($action === 'searchUsers') {
    requireLogin();
    
    $query = sanitize($_GET['query'] ?? '');
    $role = sanitize($_GET['role'] ?? '');
    $limit = intval($_GET['limit'] ?? 20);
    
    if (strlen($query) < 2) {
        echo json_encode(['success' => false, 'message' => 'Search query too short']);
        exit;
    }
    
    try {
        $sql = "
            SELECT id, reg_id, username, full_name, email, role, department, branch, phone, photo_path
            FROM users
            WHERE is_active = 1
              AND (full_name LIKE ? OR username LIKE ? OR reg_id LIKE ? OR email LIKE ?)
        ";
        
        $params = ["%$query%", "%$query%", "%$query%", "%$query%"];
        
        if ($role) {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        
        $sql .= " ORDER BY full_name LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'count' => count($users)
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// GET USER DETAILS (with full profile)
// ============================================================================
if ($action === 'getUserDetails') {
    requireLogin();
    
    $userId = intval($_GET['user_id'] ?? 0);
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, reg_id, username, email, full_name, role, department, branch, 
                   phone, dob, photo_path, qr_code_path, qr_code_data, created_at
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Get enrolled subjects
        if ($user['role'] === 'student') {
            $stmt = $pdo->prepare("
                SELECT s.id, s.subject_name, s.subject_code, s.credits
                FROM student_subjects ss
                JOIN subjects s ON ss.subject_id = s.id
                WHERE ss.student_id = ?
                ORDER BY s.subject_name
            ");
            $stmt->execute([$userId]);
            $user['subjects'] = $stmt->fetchAll();
            
        } else if ($user['role'] === 'teacher') {
            $stmt = $pdo->prepare("
                SELECT s.id, s.subject_name, s.subject_code
                FROM teacher_subjects ts
                JOIN subjects s ON ts.subject_id = s.id
                WHERE ts.teacher_id = ?
                ORDER BY s.subject_name
            ");
            $stmt->execute([$userId]);
            $user['subjects'] = $stmt->fetchAll();
        }
        
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// GET ALL SUBJECTS
// ============================================================================
if ($action === 'getAllSubjects') {
    requireLogin();
    
    try {
        $stmt = $pdo->query("
            SELECT id, subject_name, subject_code, description, credits, department
            FROM subjects
            ORDER BY subject_name
        ");
        $subjects = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'subjects' => $subjects
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// GET STUDENTS BY SUBJECT (for teacher)
// ============================================================================
if ($action === 'getStudentsBySubject') {
    requireRole('teacher');
    
    $subjectId = intval($_GET['subject_id'] ?? 0);
    
    if (!$subjectId) {
        echo json_encode(['success' => false, 'message' => 'Subject ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.reg_id,
                u.full_name,
                u.email,
                u.photo_path,
                u.qr_code_data
            FROM student_subjects ss
            JOIN users u ON ss.student_id = u.id
            WHERE ss.subject_id = ? AND u.is_active = 1
            ORDER BY u.full_name
        ");
        $stmt->execute([$subjectId]);
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
// GET ALL USERS (Admin dashboard)
// ============================================================================
if ($action === 'getAllUsers') {
    requireRole('admin');
    
    $role = sanitize($_GET['role'] ?? '');
    $search = sanitize($_GET['search'] ?? '');
    
    try {
        $sql = "
            SELECT 
                id, reg_id, username, full_name, email, role, department, branch, 
                phone, dob, photo_path, created_at, is_active
            FROM users
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($role) {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        
        if ($search) {
            $sql .= " AND (full_name LIKE ? OR username LIKE ? OR reg_id LIKE ? OR email LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'count' => count($users)
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// TOGGLE USER STATUS (Activate/Deactivate)
// ============================================================================
if ($action === 'toggleUserStatus') {
    requireRole('admin');
    
    $userId = intval($_POST['user_id'] ?? 0);
    $status = intval($_POST['status'] ?? 1);
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->execute([$status, $userId]);
        
        echo json_encode([
            'success' => true,
            'message' => $status ? 'User activated' : 'User deactivated'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// DELETE USER (Admin only)
// ============================================================================
if ($action === 'deleteUser') {
    requireRole('admin');
    
    $userId = intval($_POST['user_id'] ?? 0);
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit;
    }
    
    // Prevent deleting yourself
    if ($userId === $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// GET DASHBOARD STATISTICS (Admin)
// ============================================================================
if ($action === 'getDashboardStats') {
    requireRole('admin');
    
    try {
        // Total users by role
        $stmt = $pdo->query("
            SELECT 
                role,
                COUNT(*) AS count,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_count
            FROM users
            GROUP BY role
        ");
        $userStats = [];
        while ($row = $stmt->fetch()) {
            $userStats[$row['role']] = $row['count'];
        }
        
        // Total subjects
        $stmt = $pdo->query("SELECT COUNT(*) FROM subjects");
        $totalSubjects = $stmt->fetchColumn();
        
        // Today's attendance
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM attendance 
            WHERE attendance_date = CURDATE()
        ");
        $todayAttendance = $stmt->fetchColumn();
        
        // Overall attendance percentage
        $stmt = $pdo->query("
            SELECT ROUND(
                (SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 
                2
            ) AS percentage
            FROM attendance
        ");
        $overallPercentage = $stmt->fetchColumn() ?: 0;
        
        // Count of students below 50%
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM (
                SELECT 
                    student_id,
                    SUM(present_count) as total_present,
                    SUM(total_classes) as grand_total_classes
                FROM (
                    SELECT 
                        a.student_id,
                        a.subject_id,
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                        (SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE subject_id = a.subject_id) AS total_classes
                    FROM attendance a
                    GROUP BY a.student_id, a.subject_id
                ) student_subjects
                GROUP BY student_id
                HAVING (SUM(present_count) / SUM(total_classes)) * 100 < 50
            ) AS sub
        ");
        $below50Count = $stmt->fetchColumn() ?: 0;
        
        // Recent registrations
        $stmt = $pdo->query("
            SELECT full_name, reg_id, role, department, created_at 
            FROM users 
            ORDER BY created_at DESC 
        ");
        $recentRegistrations = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_students' => $userStats['student'] ?? 0,
                'total_teachers' => $userStats['teacher'] ?? 0,
                'total_subjects' => $totalSubjects,
                'today_attendance' => $todayAttendance,
                'overall_percentage' => $overallPercentage,
                'below_50_count' => $below50Count,
                'recent_registrations' => $recentRegistrations
            ]
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// GET RECENT REGISTRATIONS (Admin)
// ============================================================================
if ($action === 'getRecentRegistrations') {
    requireRole('admin');
    
    $role = sanitize($_GET['role'] ?? 'all');
    $dept = sanitize($_GET['dept'] ?? 'all');
    
    try {
        $sql = "SELECT full_name, reg_id, role, department, created_at FROM users WHERE 1=1";
        $params = [];
        
        if ($role !== 'all') {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        if ($dept !== 'all') {
            $sql .= " AND department = ?";
            $params[] = $dept;
        }
        
        $sql .= " ORDER BY created_at DESC";
        $limit = $_GET['limit'] ?? 'all';
        if ($limit !== 'all' && is_numeric($limit)) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $recentRegistrations = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'recent_registrations' => $recentRegistrations
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Invalid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>