<?php
/**
 * Authentication API
 * Handles: Login, Logout, Password Reset
 */

require_once 'db_config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ============================================================================
// LOGIN
// ============================================================================
if ($action === 'login') {
    $regId = sanitize($_POST['reg_id'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!$regId || !$password) {
        echo json_encode(['success' => false, 'message' => 'Registration ID and password required']);
        exit;
    }
    
    try {
        // Fetch user plus rate limit columns
        $stmt = $pdo->prepare("
            SELECT id, reg_id, username, email, full_name, role, password, is_active, 
                   failed_attempts, lockout_until
            FROM users
            WHERE reg_id = ?
        ");
        $stmt->execute([$regId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            exit;
        }

        // Check for lockout
        if ($user['lockout_until'] && strtotime($user['lockout_until']) > time()) {
            $remaining = ceil((strtotime($user['lockout_until']) - time()) / 60);
            echo json_encode(['success' => false, 'message' => "Too many failed attempts. Try again in $remaining minutes."]);
            exit;
        }
        
        if (!$user['is_active']) {
            echo json_encode(['success' => false, 'message' => 'Your account has been deactivated']);
            exit;
        }
        
        if (!password_verify($password, $user['password'])) {
            // Increment failed attempts
            $attempts = ($user['failed_attempts'] ?? 0) + 1;
            $newLockout = null;
            
            if ($attempts >= 5) {
                // Lockout for 15 minutes
                $newLockout = date('Y-m-d H:i:s', time() + (15 * 60));
                $msg = "Invalid credentials. Account locked for 15 minutes due to 5 failures.";
            } else {
                $rem = 5 - $attempts;
                $msg = "Invalid credentials. $rem attempts remaining before lockout.";
            }
            
            $update = $pdo->prepare("UPDATE users SET failed_attempts = ?, lockout_until = ? WHERE id = ?");
            $update->execute([$attempts, $newLockout, $user['id']]);
            
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
        
        // Successful login: Reset failed attempts and lockout
        if (($user['failed_attempts'] ?? 0) > 0 || $user['lockout_until']) {
            $reset = $pdo->prepare("UPDATE users SET failed_attempts = 0, lockout_until = NULL WHERE id = ?");
            $reset->execute([$user['id']]);
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['reg_id'] = $user['reg_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        
        // Determine redirect URL
        $redirectUrl = '';
        switch ($user['role']) {
            case 'admin':
                $redirectUrl = 'admin_dashboard.html';
                break;
            case 'teacher':
                $redirectUrl = 'teacher_dashboard.html';
                break;
            case 'student':
                $redirectUrl = 'student_dashboard.html';
                break;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'role' => $user['role'],
            'redirect' => $redirectUrl,
            'user' => [
                'reg_id' => $user['reg_id'],
                'full_name' => $user['full_name'],
                'username' => $user['username']
            ]
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Login error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// LOGOUT
// ============================================================================
if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
    exit;
}

// ============================================================================
// CHECK SESSION
// ============================================================================
if ($action === 'checkSession') {
    if (isLoggedIn()) {
        echo json_encode([
            'success' => true,
            'logged_in' => true,
            'user' => [
                'reg_id' => $_SESSION['reg_id'],
                'username' => $_SESSION['username'],
                'full_name' => $_SESSION['full_name'],
                'role' => $_SESSION['role']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'logged_in' => false
        ]);
    }
    exit;
}

// ============================================================================
// RESET PASSWORD (After OTP verification)
// ============================================================================
if ($action === 'resetPassword') {
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $otp = trim($_POST['otp'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    
    if (!$email || !$otp || !$newPassword) {
        echo json_encode(['success' => false, 'message' => 'All fields required']);
        exit;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }
    
    try {
        // Verify OTP
        $stmt = $pdo->prepare("
            SELECT id FROM password_reset_tokens
            WHERE email = ? AND expires_at > NOW() AND used = 0
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$email]);
        $token = $stmt->fetch();
        
        if (!$token) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP']);
            exit;
        }
        
        // Verify OTP hash
        $stmt = $pdo->prepare("SELECT token FROM password_reset_tokens WHERE id = ?");
        $stmt->execute([$token['id']]);
        $storedToken = $stmt->fetchColumn();
        
        if (!password_verify($otp, $storedToken)) {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP']);
            exit;
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashedPassword, $email]);
        
        // Mark token as used
        $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?");
        $stmt->execute([$token['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully. Please login with your new password.'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// CHANGE PASSWORD (Logged in users)
// ============================================================================
if ($action === 'changePassword') {
    requireLogin();
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (!$currentPassword || !$newPassword || !$confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'All fields required']);
        exit;
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }
    
    try {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentHash = $stmt->fetchColumn();
        
        if (!password_verify($currentPassword, $currentHash)) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }
        
        // Update password
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$newHash, $_SESSION['user_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// UPDATE PROFILE
// ============================================================================
if ($action === 'updateProfile') {
    requireLogin();
    
    $fullName = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $department = sanitize($_POST['department'] ?? '');
    $branch = sanitize($_POST['branch'] ?? '');
    
    if (!$fullName) {
        echo json_encode(['success' => false, 'message' => 'Full name is required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, phone = ?, department = ?, branch = ?
            WHERE id = ?
        ");
        $stmt->execute([$fullName, $phone, $department, $branch, $_SESSION['user_id']]);
        
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

// Invalid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
