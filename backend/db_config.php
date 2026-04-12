<?php
/**
 * Database Configuration for Attendify
 * Update credentials based on your environment
 */

// Database credentials
define('DB_HOST', 'sql308.infinityfree.com');
define('DB_NAME', 'if0_41396868_attendify_db');
define('DB_USER', 'if0_41396868');
define('DB_PASS', 'hope1916dhanno');

// Registration Access Key for Stealth Registration
define('REG_ACCESS_KEY', 'Attendify1916DNhope');

// Create PDO connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]));
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Attempt to sync MySQL session timezone with PHP (important for InfinityFree/shared hosting)
try {
    $pdo->exec("SET time_zone = '+05:30'");
} catch (PDOException $e) {
    // Some shared hosting providers block SET time_zone; we handle this by using PHP date() in queries later.
}

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,  // 24 hours
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true
    ]);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    global $pdo;
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    
    // Single-device login check
    if (isset($_SESSION['session_token']) && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT session_token FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $dbToken = $stmt->fetchColumn();
            
            if ($dbToken && $dbToken !== $_SESSION['session_token']) {
                // Token mismatch means user logged in from another device
                session_destroy();
                return false;
            }
        } catch (PDOException $e) {
            // DB error, allow current session if valid otherwise
        }
    }
    
    return true;
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    return isLoggedIn() && strtolower($_SESSION['role'] ?? '') === strtolower($role);
}

/**
 * Require login for protected pages
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // Return JSON for API/AJAX calls, redirect for page loads
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
            strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/') !== false ||
            strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false ||
            $_SERVER['REQUEST_METHOD'] === 'POST') {
            die(json_encode(['success' => false, 'message' => 'Session expired. Please login again.']));
        }
        header('Location: index.html');
        exit();
    }
}

/**
 * Require specific role
 */
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        die(json_encode([
            'success' => false,
            'message' => 'Unauthorized access'
        ]));
    }
}

/**
 * Get current user data
 */
function getCurrentUser($pdo) {
    if (!isLoggedIn()) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate unique registration ID
 */
function generateRegId($role, $pdo) {
    $prefix = '';
    switch ($role) {
        case 'student':
            $prefix = 'SEE';
            break;
        case 'teacher':
            $prefix = 'TEA';
            break;
        case 'admin':
            $prefix = 'ADMIN';
            break;
    }
    
    // Get last ID
    $stmt = $pdo->prepare("SELECT reg_id FROM users WHERE reg_id LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetch();
    
    if ($last) {
        $num = intval(substr($last['reg_id'], strlen($prefix))) + 1;
    } else {
        $num = $role === 'student' ? 2004001 : 2024001;
    }
    
    return $prefix . $num;
}

/**
 * Generate QR code data
 */
function generateQRData($regId) {
    return 'QR' . substr($regId, -7);
}
?>
