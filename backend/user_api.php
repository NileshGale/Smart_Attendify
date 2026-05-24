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
    
    $targetUserId = $_SESSION['user_id'];
    
    // Admin override for editing other users
    if (isset($_POST['user_id_override']) && hasRole('admin')) {
        $targetUserId = intval($_POST['user_id_override']);
    }

    $uploadDir = __DIR__ . '/../uploads/photos/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    // Use target user ID in filename
    $filename = 'profile_' . $targetUserId . '_' . time() . '.' . $ext;
    $destPath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save photo']);
        exit;
    }
    
    $photoPath = 'uploads/photos/' . $filename;
    
    try {
        // Delete old photo if exists
        $stmt = $pdo->prepare("SELECT photo_path FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        $oldPhoto = $stmt->fetchColumn();
        
        if ($oldPhoto && file_exists(__DIR__ . '/../' . $oldPhoto)) {
            unlink(__DIR__ . '/../' . $oldPhoto);
        }
        
        // Update database
        $stmt = $pdo->prepare("UPDATE users SET photo_path = ? WHERE id = ?");
        $stmt->execute([$photoPath, $targetUserId]);
        
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
// ============================================================================
// ADMIN: GET USER DETAILS FOR EDITING
// ============================================================================
if ($action === 'adminGetUserDetails') {
    requireRole('admin');
    
    $userId = intval($_GET['user_id'] ?? 0);
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, reg_id, username, email, full_name, role, department, branch, 
                   phone, dob, photo_path, created_at, is_active
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
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
// ADMIN: UPDATE USER PROFILE (General info)
// ============================================================================
if ($action === 'adminUpdateUserProfile') {
    requireRole('admin');
    
    $userId     = intval($_POST['user_id'] ?? 0);
    $fullName   = sanitize($_POST['full_name'] ?? '');
    $phone      = sanitize($_POST['phone'] ?? '');
    $dob        = sanitize($_POST['dob'] ?? '');
    $role       = sanitize($_POST['role'] ?? '');
    $department = sanitize($_POST['department'] ?? '');
    $branch     = sanitize($_POST['branch'] ?? '');
    $newRegId   = sanitize($_POST['reg_id'] ?? '');
    
    if (!$userId || !$fullName || !$newRegId) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    try {
        // 1. Fetch current info for comparisons and preservation
        $stmt = $pdo->prepare("SELECT reg_id, role, department, branch FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch();
        
        if (!$currentUser) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        // 2. Handle reg_id changes
        if ($currentUser['reg_id'] !== $newRegId) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE reg_id = ? AND id != ?");
            $stmt->execute([$newRegId, $userId]);
            if ($stmt->fetch()) {
                $suggestedId = generateRegId($role, $pdo);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Registration ID already exists.',
                    'suggested_id' => $suggestedId
                ]);
                exit;
            }
        }

        // 3. Determine final values for department and branch
        // We preserve existing values if they are not relevant to the new role 
        // OR if they weren't provided in the request (e.g. for Admins/Teachers)
        $finalDept = $currentUser['department'];
        $finalBranch = $currentUser['branch'];

        if ($role === 'student') {
            $finalDept = $_POST['department'] ?? $finalDept;
            $finalBranch = $_POST['branch'] ?? $finalBranch;
        } else if ($role === 'teacher') {
            $finalDept = $_POST['department'] ?? $finalDept;
            // Branch is not relevant for teachers, but we keep the existing one if any
            // or we could null it if you prefer. User said "dont auto update".
        } else if ($role === 'admin') {
            // Admin doesn't have dept/branch fields in UI
        }

        // 4. Perform Update
        $stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, phone = ?, dob = ?, role = ?, department = ?, branch = ?, reg_id = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $fullName, $phone ?: null, $dob ?: null, $role, 
            $finalDept, $finalBranch, $newRegId, $userId
        ]);

        // 3. Notify user
        require_once 'send_otp.php';
        $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $uInfo = $stmt->fetch();

        // Check if reg_id was changed to send the correct alert
        if ($currentUser['reg_id'] !== $newRegId) {
            sendRegIdUpdateNotification($uInfo['email'], $uInfo['full_name'], $currentUser['reg_id'], $newRegId);
        } else {
            sendProfileUpdateNotification($uInfo['email'], $uInfo['full_name']);
        }

        echo json_encode(['success' => true, 'message' => 'User profile updated and notification sent']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// ADMIN: INITIATE EMAIL CHANGE (Send OTP to NEW email)
// ============================================================================
if ($action === 'adminSendEmailChangeOTP') {
    requireRole('admin');
    
    $userId   = intval($_POST['user_id'] ?? 0);
    $newEmail = filter_var(trim($_POST['new_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    
    if (!$userId || !$newEmail) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or user ID']);
        exit;
    }
    
    try {
        // Check if email already taken
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$newEmail, $userId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'This email is already linked to another account']);
            exit;
        }

        // Generate OTP
        $otp = sprintf('%06d', random_int(100000, 999999));
        
        require_once 'send_otp.php';
        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
        $stmt->execute([$newEmail]);

        $stmt = $pdo->prepare("
            INSERT INTO password_reset_tokens (email, token, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))
        ");
        $stmt->execute([$newEmail, password_hash($otp, PASSWORD_DEFAULT)]);

        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userName = $stmt->fetchColumn() ?: 'User';

        $result = sendOTPEmail($newEmail, $userName, $otp);
        if ($result['sent']) {
            echo json_encode(['success' => true, 'message' => 'Verification code sent to the new email address']);
        } else {
             echo json_encode(['success' => true, 'message' => 'OTP generated (Email delivery failed)']);
        }

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// ADMIN: VERIFY EMAIL CHANGE & NOTIFY BOTH
// ============================================================================
if ($action === 'adminVerifyEmailChange') {
    requireRole('admin');
    
    $userId   = intval($_POST['user_id'] ?? 0);
    $newEmail = filter_var(trim($_POST['new_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $otp      = trim($_POST['otp'] ?? '');
    
    if (!$userId || !$newEmail || !$otp) {
        echo json_encode(['success' => false, 'message' => 'Missing data for verification']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT token FROM password_reset_tokens
            WHERE email = ? AND expires_at > NOW() AND used = 0
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$newEmail]);
        $row = $stmt->fetch();

        if ($row && password_verify($otp, $row['token'])) {
            $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();
            $oldEmail = $userData['email'];
            $fullName = $userData['full_name'];

            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$newEmail, $userId]);

            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE email = ?");
            $stmt->execute([$newEmail]);

            require_once 'send_otp.php';
            sendEmailChangeAlert_Old($oldEmail, $fullName, $newEmail);
            sendEmailChangeAlert_New($newEmail, $fullName, $oldEmail);

            echo json_encode(['success' => true, 'message' => 'Email address successfully updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP code']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// ADMIN: DIRECT PASSWORD RESET
// ============================================================================
if ($action === 'adminUpdatePassword') {
    requireRole('admin');
    
    $userId      = intval($_POST['user_id'] ?? 0);
    $newPassword = $_POST['new_password'] ?? '';
    
    if (!$userId || strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT email, full_name, reg_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $userId]);

        require_once 'send_otp.php';
        sendAdminPasswordUpdateEmail($user['email'], $user['full_name'], $user['reg_id'], $newPassword);

        echo json_encode(['success' => true, 'message' => 'Password reset successfully and email sent to user']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

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
// DELETE USER (Admin only - Enhanced Cleanup)
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
        // 1. Fetch details for notification and file cleanup
        $stmt = $pdo->prepare("SELECT email, full_name, photo_path, qr_code_path FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        // 2. 물리적 파일 삭제 (Physical file cleanup)
        $baseDir = __DIR__ . '/../';
        if ($user['photo_path'] && file_exists($baseDir . $user['photo_path'])) {
            unlink($baseDir . $user['photo_path']);
        }
        if ($user['qr_code_path'] && file_exists($baseDir . $user['qr_code_path'])) {
            unlink($baseDir . $user['qr_code_path']);
        }

        // 3. Send Deletion Email
        require_once 'send_otp.php';
        sendDeletionNotification($user['email'], $user['full_name']);

        // 4. Delete from Database (Cascades will handle attendance records if fk defined)
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'User and all associated data deleted successfully'
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
        
        // Determine total students for calculation
        $totalStudents = (int)($userStats['student'] ?? 0);
        if ($totalStudents === 0) $totalStudents = 1; // Prevent division by zero

        // Overall attendance percentage
        $stmt = $pdo->query("
            SELECT 
                SUM(present_count) as total_present,
                SUM(subject_count * $totalStudents) as total_expected
            FROM (
                SELECT 
                    attendance_date,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                    COUNT(DISTINCT subject_id) as subject_count
                FROM attendance
                GROUP BY attendance_date
            ) daily_stats
        ");
        $overallStats = $stmt->fetch();
        $totalExpected = $overallStats['total_expected'] ?: 1;
        $overallPercentage = round(($overallStats['total_present'] / $totalExpected) * 100, 2);
        
        // Count of students below 50% attendance across all their enrolled subjects
        // Logic: 
        // 1. Get all students
        // 2. For each student, get all subjects they are enrolled in
        // 3. For each subject, get total classes held (distinct dates in attendance table)
        // 4. Calculate total present across all subjects vs grand total possible classes
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM (
                SELECT 
                    u.id as student_id,
                    COALESCE(SUM(att.present_count), 0) as total_present,
                    COALESCE(SUM(att.total_classes), 0) as grand_total_classes
                FROM users u
                -- Get all subjects for all students
                LEFT JOIN student_subjects ss ON u.id = ss.student_id
                -- Get attendance stats per student per subject
                LEFT JOIN (
                    SELECT 
                        a.student_id,
                        a.subject_id,
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                        (SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE subject_id = a.subject_id) AS total_classes
                    FROM attendance a
                    GROUP BY a.student_id, a.subject_id
                ) att ON u.id = att.student_id AND ss.subject_id = att.subject_id
                WHERE u.role = 'student'
                GROUP BY u.id
                HAVING grand_total_classes > 0 AND (total_present / grand_total_classes) * 100 < 50
                   OR grand_total_classes = 0 -- Optionally count students with no classes? Usually 50% of 0 is undefined, but for this metric, we might want to exclude or include them.
                                             -- Let's stick to students who have HAD classes and are below 50%.
            ) AS sub
        ");
        $below50Count = $stmt->fetchColumn() ?: 0;
        
        // Recent registrations (all users including admin, teacher, student)
        $stmt = $pdo->query("
            SELECT id, full_name, reg_id, role, department, branch, phone, dob, email, photo_path, created_at 
            FROM users 
            ORDER BY created_at DESC
        ");
        $recentRegistrations = $stmt->fetchAll();

        // Chart Data: Last 30 days attendance
        $stmt = $pdo->query("
            SELECT 
                attendance_date,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                (COUNT(DISTINCT subject_id) * $totalStudents) - SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as absent
            FROM attendance
            WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY attendance_date
            ORDER BY attendance_date ASC
        ");
        $dailyAttendance = $stmt->fetchAll();

        // Chart Data: Department-wise attendance
        $stmt = $pdo->query("
            SELECT 
                u.department,
                COUNT(a.id) as present_count,
                (SELECT COUNT(DISTINCT attendance_date, subject_id) FROM attendance) * 
                (SELECT COUNT(*) FROM users u2 WHERE u2.department = u.department AND u2.role = 'student') as expected_count
            FROM users u
            LEFT JOIN attendance a ON u.id = a.student_id AND a.status = 'present'
            WHERE u.department IS NOT NULL AND u.role = 'student'
            GROUP BY u.department
        ");
        $deptAttendanceRaw = $stmt->fetchAll();
        $deptAttendance = [];
        foreach ($deptAttendanceRaw as $row) {
            $expected = $row['expected_count'] > 0 ? $row['expected_count'] : 1;
            $pct = round(($row['present_count'] / $expected) * 100, 1);
            $deptAttendance[] = [
                'department' => $row['department'],
                'percentage' => $pct
            ];
        }

        // Role Distribution (Doughnut Chart)
        $roleDistribution = [];
        foreach ($userStats as $role => $count) {
            $roleDistribution[] = ['label' => ucfirst($role), 'value' => (int)$count];
        }
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_students' => (int)($userStats['student'] ?? 0),
                'total_teachers' => (int)($userStats['teacher'] ?? 0),
                'total_subjects' => (int)$totalSubjects,
                'today_attendance' => (int)$todayAttendance,
                'overall_percentage' => (float)$overallPercentage,
                'below_50_count' => (int)$below50Count,
                'recent_registrations' => $recentRegistrations,
                'chart_data' => [
                    'daily_attendance' => $dailyAttendance,
                    'dept_attendance' => $deptAttendance,
                    'role_distribution' => $roleDistribution
                ]
            ]
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// ADMIN: CREATE NEW USER (Admin-to-Admin model)
// ============================================================================
if ($action === 'adminCreateUser') {
    requireRole('admin');
    
    $fullName   = sanitize($_POST['full_name']   ?? '');
    $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $mobile     = sanitize($_POST['phone']       ?? '');
    $role       = sanitize($_POST['role']        ?? '');
    $department = sanitize($_POST['department']  ?? '');
    $branch     = sanitize($_POST['branch']      ?? '');
    $password   = $_POST['password']             ?? '';

    if (!$fullName || !$email || !$role || !$password) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }

    if (!in_array($role, ['student', 'teacher', 'admin'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid role selected']);
        exit;
    }

    try {
        // Check email uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'This email is already registered']);
            exit;
        }

        // Generate base ID and prepare smart incrementing
        $prefix = ($role === 'student') ? 'SEE' : (($role === 'teacher') ? 'TEA' : 'ADMIN');
        $baseRegId = generateRegId($role, $pdo);
        $baseNum = intval(substr($baseRegId, strlen($prefix)));

        $regId = '';
        $username = '';
        $qrData = '';
        $attempts = 0;
        $maxAttempts = 10;
        $isUnique = false;

        while (!$isUnique && $attempts < $maxAttempts) {
            // Increment ID by the attempt number to automatically "jump" over collisions
            $currentNum = $baseNum + $attempts;
            $regId = $prefix . $currentNum;
            $username = strtolower(str_replace(' ', '.', $fullName)) . rand(1000, 9999);
            $qrData = ($role === 'student') ? generateQRData($regId) : null;
            
            // Check uniqueness for registration ID, username, and QR data
            $regUnique = isFieldUnique($pdo, 'users', 'reg_id', $regId);
            $userUnique = isFieldUnique($pdo, 'users', 'username', $username);
            $qrUnique = ($qrData === null) || isFieldUnique($pdo, 'users', 'qr_code_data', $qrData);

            if ($regUnique && $userUnique && $qrUnique) {
                $isUnique = true;
            } else {
                $attempts++;
            }
        }

        if (!$isUnique) {
            echo json_encode(['success' => false, 'message' => "Failed to find a unique ID after $maxAttempts attempts. Please try again or contact support."]);
            exit;
        }

        $hashedPw = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users 
                (username, email, password, full_name, reg_id, qr_code_data,
                 role, department, branch, phone, created_at)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $username, $email, $hashedPw, $fullName,
            $regId, $qrData, $role, $department,
            $branch, $mobile
        ]);

        // Send notification email
        try {
            require_once 'send_otp.php';
            if (function_exists('sendRegistrationEmail')) {
                sendRegistrationEmail($email, $fullName, $regId, $role, $password);
            }
        } catch (Exception $e) {
            error_log("Failed to send registration email: " . $e->getMessage());
        }

        echo json_encode([
            'success' => true, 
            'message' => 'User account created successfully! Credentials sent to email.',
            'reg_id' => $regId,
            'username' => $username
        ]);
    } catch (PDOException $e) {
        $errorMsg = $e->getMessage();
        if (strpos($errorMsg, 'Duplicate entry') !== false) {
            if (strpos($errorMsg, 'email') !== false) {
                echo json_encode(['success' => false, 'message' => 'This email is already registered.']);
            } elseif (strpos($errorMsg, 'username') !== false) {
                echo json_encode(['success' => false, 'message' => 'The generated username is already in use. Please try again.']);
            } elseif (strpos($errorMsg, 'reg_id') !== false) {
                echo json_encode(['success' => false, 'message' => 'Registration ID collision. Please try again.']);
            } elseif (strpos($errorMsg, 'qr_code_data') !== false) {
                echo json_encode(['success' => false, 'message' => 'QR Code collision detected. Please try again.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Data collision detected: ' . $errorMsg]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $errorMsg]);
        }
    }
    exit;
}

// ============================================================================
// GET DISTINCT DEPARTMENTS AND BRANCHES (For dropdowns)
// ============================================================================
if ($action === 'getFilterOptions') {
    requireRole('admin');
    
    try {
        // Fetch departments from both users and subjects to be comprehensive
        $stmt = $pdo->query("
            SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != ''
            UNION
            SELECT DISTINCT department FROM subjects WHERE department IS NOT NULL AND department != ''
        ");
        $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Fetch branches from users
        $stmt = $pdo->query("SELECT DISTINCT branch FROM users WHERE branch IS NOT NULL AND branch != ''");
        $branches = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Fallback for empty database (Ensuring user's specific request values are prioritized)
        if (empty($departments)) $departments = ['Commerce and Management'];
        if (empty($branches)) $branches = ['BCCA'];

        echo json_encode([
            'success' => true,
            'departments' => array_values(array_unique($departments)),
            'branches' => array_values(array_unique($branches))
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
    $search = sanitize($_GET['search'] ?? '');
    $sort = sanitize($_GET['sort'] ?? 'default');
    
    try {
        $sql = "SELECT id, full_name, reg_id, role, department, branch, phone, dob, email, photo_path, created_at FROM users WHERE 1=1";
        $params = [];
        
        if ($role !== 'all') {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        if ($dept !== 'all') {
            $sql .= " AND department = ?";
            $params[] = $dept;
        }
        if ($search !== '') {
            $sql .= " AND (full_name LIKE ? OR reg_id LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        // Handle Sorting
        if ($sort === 'asc') {
            $sql .= " ORDER BY full_name ASC";
        } elseif ($sort === 'desc') {
            $sql .= " ORDER BY full_name DESC";
        } elseif ($sort === 'reg_asc') {
            $sql .= " ORDER BY reg_id ASC";
        } elseif ($sort === 'reg_desc') {
            $sql .= " ORDER BY reg_id DESC";
        } else {
            $sql .= " ORDER BY created_at DESC";
        }

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