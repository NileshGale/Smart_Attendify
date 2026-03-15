<?php


require_once 'db_config.php';

// ── PHPMailer includes ──────────────────────────────────────────────────────
// We try multiple common patterns to be robust on live/manual uploads
$possiblePaths = [
    __DIR__ . '/PHPMailer/src/',
    __DIR__ . '/phpmailer/src/',
    __DIR__ . '/PHPMailer/',
    __DIR__ . '/phpmailer/'
];

$phpmailerBase = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path . 'PHPMailer.php')) {
        $phpmailerBase = $path;
        break;
    }
}

if (!$phpmailerBase) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'PHPMailer not found on server. Searched paths: ' . implode(', ', $possiblePaths) . '. Please ensure the PHPMailer folder is uploaded correctly to the backend directory.'
    ]);
    exit();
}

require_once $phpmailerBase . 'Exception.php';
require_once $phpmailerBase . 'PHPMailer.php';
require_once $phpmailerBase . 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');


define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_USERNAME',   'nileshgale520@gmail.com');   // ← your Gmail address
define('SMTP_PASSWORD',   'namc fhdg vbke dvps');       // ← 16-char App Password (spaces are fine)
define('SMTP_PORT',       587);
define('SMTP_FROM_EMAIL', 'nileshgale520@gmail.com');   // Must match SMTP_USERNAME for Gmail
define('SMTP_FROM_NAME',  'Attendify');
define('SMTP_SECURE',     PHPMailer::ENCRYPTION_STARTTLS);

// ── HELPER: Build PHPMailer instance ────────────────────────────────────────
function buildMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = str_replace(' ', '', SMTP_PASSWORD); // strip spaces just in case
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    return $mail;
}

// ── SEND OTP EMAIL ───────────────────────────────────────────────────────────
function sendOTPEmail(string $toEmail, string $toName, string $otp): array {
    try {
        $mail = buildMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP - Attendify';
        $mail->Body    = generateOTPEmailHTML($toName, $otp);
        $mail->AltBody = "Hello $toName,\n\nYour OTP is: $otp\n\nExpires in 2 minutes.\n\nAttendify Team";
        $mail->send();
        return ['sent' => true];
    } catch (Exception $e) {
        error_log("OTP Email Error: " . $e->getMessage());
        return ['sent' => false, 'error' => $e->getMessage()];
    }
}

// ── SEND REGISTRATION EMAIL ──────────────────────────────────────────────────
function sendRegistrationEmail(string $toEmail, string $toName, string $regId, string $role, string $password = ''): bool {
    try {
        $mail = buildMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to Attendify - Registration Successful';
        $mail->Body    = generateRegistrationEmailHTML($toName, $regId, $role, $toEmail, $password);
        $mail->AltBody = "Welcome $toName!\n\nYour Registration ID: $regId\nRole: $role\nEmail: $toEmail\nPassword: $password\n\nPlease keep these credentials safe.\nLogin at your Attendify portal.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Registration Email Error: " . $e->getMessage());
        return false;
    }
}

// ── REQUEST OTP ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'requestOtp') {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

        if (!$email) {
            echo json_encode(['success' => false, 'message' => 'A valid email address is required']);
            exit();
        }

        try {
            // Check if email already exists in users table
            $stmt = $pdo->prepare("SELECT id, full_name, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                // Generic message for security — don't reveal unregistered emails
                echo json_encode(['success' => true, 'message' => 'If this email is registered, an OTP will be sent']);
                exit();
            }

            $userName = !empty($user['full_name']) ? $user['full_name'] : $user['username'];

            // Generate 6-digit OTP
            $otp = sprintf('%06d', random_int(100000, 999999));

            // Remove old tokens for this email
            $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
            $stmt->execute([$email]);

            // Store hashed OTP with 2-minute expiry
            $stmt = $pdo->prepare("
                INSERT INTO password_reset_tokens (email, token, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))
            ");
            $stmt->execute([$email, password_hash($otp, PASSWORD_DEFAULT)]);

            // Send email
            $result = sendOTPEmail($email, $userName, $otp);

            if ($result['sent']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'OTP sent to your email address'
                ]);
            } else {
                // <i class="fa-solid fa-triangle-exclamation"></i>  DEV FALLBACK: returns OTP in response when email fails
                // REMOVE the 'otp' key before going to production!
                echo json_encode([
                    'success'       => true,
                    'message'       => 'OTP generated (email failed — check server error log)',
                    
                ]);
            }

        } catch (PDOException $e) {
            error_log('OTP DB error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
        }
        exit();
    }

    // ── VERIFY OTP ───────────────────────────────────────────────────────────
    if ($action === 'verifyOtp') {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $otp   = trim($_POST['otp'] ?? '');

        if (!$email || empty($otp)) {
            echo json_encode(['success' => false, 'message' => 'Email and OTP are required']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("
                SELECT token FROM password_reset_tokens
                WHERE email = ? AND expires_at > NOW() AND used = 0
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$email]);
            $row = $stmt->fetch();

            if ($row && password_verify($otp, $row['token'])) {
                echo json_encode(['success' => true, 'message' => 'OTP verified successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP']);
            }

        } catch (PDOException $e) {
            error_log('OTP verify error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Verification failed. Please try again.']);
        }
        exit();
    }

    // ── SEND REGISTRATION OTP (for new users during signup) ──────────────────
    if ($action === 'requestRegOtp') {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

        if (!$email) {
            echo json_encode(['success' => false, 'message' => 'A valid email address is required']);
            exit();
        }

        try {
            // Check email is not already taken
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'This email is already registered']);
                exit();
            }

            $otp = sprintf('%06d', random_int(100000, 999999));

            $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
            $stmt->execute([$email]);

            $stmt = $pdo->prepare("
                INSERT INTO password_reset_tokens (email, token, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))
            ");
            $stmt->execute([$email, password_hash($otp, PASSWORD_DEFAULT)]);

            $result = sendOTPEmail($email, 'New User', $otp);

            if ($result['sent']) {
                echo json_encode(['success' => true, 'message' => 'Verification code sent to your email']);
            } else {
                echo json_encode([
                    'success'       => true,
                    'message'       => 'OTP generated (email failed)',
                    'otp'           => $otp,            // DEV only — REMOVE IN PRODUCTION
                    'email_error'   => $result['error'] // DEV only — REMOVE IN PRODUCTION
                ]);
            }

        } catch (PDOException $e) {
            error_log('RegOTP DB error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
        }
        exit();
    }
}

// ── EMAIL TEMPLATES ──────────────────────────────────────────────────────────

function generateOTPEmailHTML(string $name, string $otp): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>
    body{font-family:'Segoe UI',sans-serif;background:#f1f5f9;margin:0;padding:20px}
    .wrap{max-width:540px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1)}
    .header{background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:32px;text-align:center;color:#fff}
    .header h1{margin:0;font-size:24px}
    .body{padding:32px}
    .otp-box{background:#f8f7ff;border:2px dashed #4f46e5;border-radius:12px;text-align:center;padding:24px;margin:24px 0}
    .otp{font-size:42px;font-weight:900;letter-spacing:.2em;color:#4f46e5;font-family:'Courier New',monospace}
    .note{color:#ef4444;font-weight:600;font-size:14px;margin-top:8px}
    .footer{background:#f8f9fa;padding:20px;text-align:center;color:#64748b;font-size:13px;border-top:1px solid #e2e8f0}
</style></head>
<body>
<div class="wrap">
    <div class="header"><h1>Password Security</h1><p style="opacity:.85;margin:8px 0 0">Attendify Security</p></div>
    <div class="body">
        <p>Hello <strong>{$name}</strong>,</p>
        <p>We received a request to OTP to your email. Use the OTP below:</p>
        <div class="otp-box">
            <div class="otp">{$otp}</div>
            <div class="note">Expires in 2 minutes</div>
        </div>
        <p>If you did not request this, please ignore this email.</p>
    </div>
    <div class="footer"><p>&copy; Attendify &mdash; Attendance Management System</p></div>
</div>
</body></html>
HTML;
}

function generateRegistrationEmailHTML(string $name, string $regId, string $role, string $email, string $password = ''): string {
    $roleLabel = ucfirst($role);
    $passwordRow = $password ? "<p>Password: <strong style=\"font-family:'Courier New',monospace;font-size:15px;color:#4f46e5\">{$password}</strong></p>" : '';
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>
    body{font-family:'Segoe UI',sans-serif;background:#f1f5f9;margin:0;padding:20px}
    .wrap{max-width:540px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1)}
    .header{background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:32px;text-align:center;color:#fff}
    .body{padding:32px}
    .id-box{background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:12px;text-align:center;padding:20px;margin:20px 0;color:#fff}
    .reg-id{font-size:28px;font-weight:900;letter-spacing:.2em;font-family:'Courier New',monospace}
    .details{background:#f8f9fa;border-radius:10px;padding:16px;margin:16px 0}
    .details p{margin:6px 0;font-size:14px;color:#374151}
    .footer{background:#f8f9fa;padding:20px;text-align:center;color:#64748b;font-size:13px;border-top:1px solid #e2e8f0}
</style></head>
<body>
<div class="wrap">
    <div class="header"><h1>Welcome to Attendify!</h1></div>
    <div class="body">
        <p>Dear <strong>{$name}</strong>,</p>
        <p>Your registration was successful. Here are your credentials:</p>
        <div class="id-box">
            <div style="font-size:13px;opacity:.8;margin-bottom:4px">Your Registration ID</div>
            <div class="reg-id">{$regId}</div>
        </div>
        <div class="details">
            <p>Role: <strong>{$roleLabel}</strong></p>
            <p>Email: <strong>{$email}</strong></p>
            <p>Registration ID: <strong>{$regId}</strong></p>
            {$passwordRow}
        </div>
        <p><strong style="color:#ef4444">Important:</strong> Keep your credentials safe &mdash; you'll need your Registration ID and password to log in.</p>
        <p style="font-size:13px;color:#64748b">For security, we recommend changing your password after your first login.</p>
    </div>
    <div class="footer"><p>&copy; Attendify &mdash; Attendance Management System</p></div>
</div>
</body></html>
HTML;
}
?>