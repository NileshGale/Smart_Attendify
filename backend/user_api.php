<?php
/**
 * User Management API
 * Handles: User creation, search, subject allocation, admin operations
 */

require_once 'db_config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ============================================================================
// ADD NEW USER (Admin only)
// ============================================================================
if ($action === 'addUser') {
    requireRole('admin');
    
    $username = sanitize($_POST['username'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $fullName = sanitize($_POST['full_name'] ?? '');
    $role = sanitize($_POST['role'] ?? 'student');
    $department = sanitize($_POST['department'] ?? '');
    $branch = sanitize($_POST['branch'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $subjects = json_decode($_POST['subjects'] ?? '[]', true); // Subject IDs
    
    if (!$username || !$email || !$fullName) {
        echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Generate registration ID
        $regId = generateRegId($role, $pdo);
        
        // Generate QR code data for students
        $qrData = ($role === 'student') ? generateQRData($regId) : null;
        
        // Default password (user should change on first login)
        $defaultPassword = password_hash('changeme123', PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, reg_id, qr_code_data, role, department, branch, phone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$username, $email, $defaultPassword, $fullName, $regId, $qrData, $role, $department, $branch, $phone]);
        
        $userId = $pdo->lastInsertId();
        
        // Allocate subjects
        if (!empty($subjects)) {
            $table = ($role === 'student') ? 'student_subjects' : 'teacher_subjects';
            $idColumn = ($role === 'student') ? 'student_id' : 'teacher_id';
            
            $stmt = $pdo->prepare("INSERT INTO $table ($idColumn, subject_id) VALUES (?, ?)");
            
            foreach ($subjects as $subjectId) {
                $stmt->execute([$userId, intval($subjectId)]);
            }
        }
        
        // Generate QR code for students
        if ($role === 'student' && $qrData) {
            // You can trigger QR generation here or do it separately
            // For now, just store the data
        }
        
        $pdo->commit();
        
        // Send registration email
        require_once 'send_otp.php';
        sendRegistrationEmail($email, $fullName, $regId, $role);
        
        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'reg_id' => $regId,
            'user_id' => $userId,
            'default_password' => 'changeme123'
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
    exit;
}

// ============================================================================
// SEARCH USERS (Teacher/Student by name or username)
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
            SELECT id, reg_id, username, full_name, email, role, department, branch
            FROM users
            WHERE is_active = 1
              AND (full_name LIKE ? OR username LIKE ? OR reg_id LIKE ?)
        ";
        
        $params = ["%$query%", "%$query%", "%$query%"];
        
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
// GET USER DETAILS
// ============================================================================
if ($action === 'getUserDetails') {
    requireLogin();
    
    $userId = intval($_GET['user_id'] ?? $_SESSION['user_id']);
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, reg_id, username, email, full_name, role, department, branch, phone, qr_code_path, qr_code_data, created_at
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
// ALLOCATE SUBJECTS TO USER
// ============================================================================
if ($action === 'allocateSubjects') {
    requireRole('admin');
    
    $userId = intval($_POST['user_id'] ?? 0);
    $subjects = json_decode($_POST['subjects'] ?? '[]', true);
    
    if (!$userId || empty($subjects)) {
        echo json_encode(['success' => false, 'message' => 'User ID and subjects required']);
        exit;
    }
    
    try {
        // Get user role
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        $table = ($user['role'] === 'student') ? 'student_subjects' : 'teacher_subjects';
        $idColumn = ($user['role'] === 'student') ? 'student_id' : 'teacher_id';
        
        // Delete existing allocations
        $stmt = $pdo->prepare("DELETE FROM $table WHERE $idColumn = ?");
        $stmt->execute([$userId]);
        
        // Add new allocations
        $stmt = $pdo->prepare("INSERT INTO $table ($idColumn, subject_id) VALUES (?, ?)");
        
        foreach ($subjects as $subjectId) {
            $stmt->execute([$userId, intval($subjectId)]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Subjects allocated successfully',
            'count' => count($subjects)
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
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
                phone, created_at, is_active
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
        $userStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
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
        $overallPercentage = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_students' => $userStats['student'] ?? 0,
                'total_teachers' => $userStats['teacher'] ?? 0,
                'total_subjects' => $totalSubjects,
                'today_attendance' => $todayAttendance,
                'overall_percentage' => $overallPercentage
            ]
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Invalid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
