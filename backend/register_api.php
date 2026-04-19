<?php
/**
 * register_api.php
 * Handles new user self-registration from register.html
 * Place this file in: backend/
 */

require_once 'db_config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

// ============================================================================
// REGISTER NEW USER
// ============================================================================
if ($action === 'register') {
    // ── Stealth Registration Key Check ───────────────────────────────────────
    $regKey = $_POST['reg_key'] ?? '';
    if ($regKey !== REG_ACCESS_KEY) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: Invalid or missing registration key.']);
        exit;
    }


    // ── Collect & validate inputs ────────────────────────────────────────────
    $fullName   = sanitize($_POST['full_name']   ?? '');
    $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $mobile     = sanitize($_POST['mobile']      ?? '');
    $role       = sanitize($_POST['role']        ?? '');
    $department = sanitize($_POST['department']  ?? '');
    $branch     = sanitize($_POST['branch']      ?? '');
    $dob        = sanitize($_POST['dob']         ?? '');
    $password   = $_POST['password']             ?? '';
    $confirm    = $_POST['confirm_password']     ?? '';

    // Required field checks
    if (!$fullName || !$email || !$mobile || !$role || !$password) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }

    if (!in_array($role, ['student', 'teacher', 'admin'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
        exit;
    }

    if (!preg_match('/^[0-9]{10}$/', $mobile)) {
        echo json_encode(['success' => false, 'message' => 'Mobile number must be 10 digits']);
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }

    if ($password !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }

    if ($role === 'student' && (!$department || !$branch)) {
        echo json_encode(['success' => false, 'message' => 'Department and branch are required for students']);
        exit;
    }

    if ($role === 'teacher' && !$department) {
        echo json_encode(['success' => false, 'message' => 'Department is required for teachers']);
        exit;
    }

    // ── Photo upload ─────────────────────────────────────────────────────────
    $photoPath = null;

    if (isset($_FILES['user_photo']) && $_FILES['user_photo']['error'] === UPLOAD_ERR_OK) {
        $file     = $_FILES['user_photo'];
        $allowed  = ['image/jpeg', 'image/jpg', 'image/png'];
        $maxSize  = 2 * 1024 * 1024; // 2 MB

        if (!in_array($file['type'], $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, and PNG photos are allowed']);
            exit;
        }

        if ($file['size'] > $maxSize) {
            echo json_encode(['success' => false, 'message' => 'Photo must be less than 2MB']);
            exit;
        }

        // Save to ../uploads/photos/ relative to backend/
        $uploadDir = __DIR__ . '/../uploads/photos/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'photo_' . uniqid() . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save photo. Check folder permissions.']);
            exit;
        }

        $photoPath = 'uploads/photos/' . $filename;
    }

    // ── Database insert ───────────────────────────────────────────────────────
    try {
        // Check email not already taken
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
        $maxAttempts = 10; // Increase attempts for better coverage
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
            $total = 0;
            try { $total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); } catch(Exception $e){}
            echo json_encode([
                'success' => false, 
                'message' => "Could not find a unique ID after $maxAttempts tries. System sees $total total users. Please contact support."
            ]);
            exit;
        }

        $hashedPw = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO users 
                (username, email, password, full_name, reg_id, qr_code_data,
                 role, department, branch, phone, dob, photo_path)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $username, $email, $hashedPw, $fullName,
            $regId, $qrData, $role, $department,
            $branch, $mobile,
            ($dob ?: null),
            $photoPath
        ]);

        $userId = $pdo->lastInsertId();
        $pdo->commit();

        // Send welcome email (non-blocking — failure doesn't stop registration)
        try {
            require_once 'send_otp.php';
            sendRegistrationEmail($email, $fullName, $regId, $role, $password);
        } catch (Exception $e) {
            error_log('Welcome email failed: ' . $e->getMessage());
        }

        echo json_encode([
            'success'  => true,
            'message'  => 'Registration successful! Please login with your Registration ID.',
            'reg_id'   => $regId,
            'username' => $username
        ]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errorMsg = $e->getMessage();
        if (strpos($errorMsg, 'Duplicate entry') !== false) {
            if (strpos($errorMsg, 'email') !== false) {
                echo json_encode(['success' => false, 'message' => 'This email is already registered.']);
            } elseif (strpos($errorMsg, 'username') !== false) {
                echo json_encode(['success' => false, 'message' => 'This username is already taken. Please try again.']);
            } elseif (strpos($errorMsg, 'reg_id') !== false) {
                echo json_encode(['success' => false, 'message' => 'Registration ID collision. Please try again.']);
            } elseif (strpos($errorMsg, 'qr_code_data') !== false) {
                echo json_encode(['success' => false, 'message' => 'QR Code collision detected. Please contact Admin.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Data collision detected: ' . $errorMsg]);
            }
        } elseif (strpos($errorMsg, "Unknown column 'dob'") !== false) {
            // If dob or photo_path columns don't exist yet, retry without them
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    INSERT INTO users 
                        (username, email, password, full_name, reg_id, qr_code_data,
                         role, department, branch, phone)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $username, $email, $hashedPw, $fullName,
                    $regId, $qrData, $role, $department, $branch, $mobile
                ]);
                $pdo->commit();

                echo json_encode([
                    'success'  => true,
                    'message'  => 'Registration successful!',
                    'reg_id'   => $regId,
                    'username' => $username
                ]);
            } catch (PDOException $e2) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e2->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
        }
    }
    exit;
}

// Invalid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>