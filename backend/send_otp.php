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
        $mail->AltBody = "Welcome $toName!\n\nYour Registration ID: $regId\nRole: $role\nEmail: $toEmail\nPassword: $password\n\nPlease keep these credentials safe.\n\nThank you for using Attendify";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Registration Email Error: " . $e->getMessage());
        return false;
    }
}

// ── SEND PROFILE UPDATE NOTIFICATION ─────────────────────────────────────────
function sendProfileUpdateNotification(string $toEmail, string $toName): bool {
    try {
        $mail = buildMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Profile Updated - Attendify';
        $mail->Body    = generateProfileUpdateEmailHTML($toName);
        $mail->AltBody = "Hello $toName,\n\nSome changes have been made to your Attendify account. Please verify the updated details in your profile.\n\nAttendify Team";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Profile Update Email Error: " . $e->getMessage());
        return false;
    }
}

// ── SEND ADMIN PASSWORD UPDATE EMAIL ─────────────────────────────────────────
function sendAdminPasswordUpdateEmail(string $toEmail, string $toName, string $regId, string $newPassword): bool {
    try {
        $mail = buildMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Your Account Password Has Been Reset - Attendify';
        $mail->Body    = generateAdminPasswordUpdateEmailHTML($toName, $regId, $newPassword);
        $mail->AltBody = "Hello $toName,\n\nYour password has been changed by the administration.\n\nRegistration ID: $regId\nNew Password: $newPassword\n\nPlease log in and change your password for security.\n\nAttendify Team";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Admin Password Update Email Error: " . $e->getMessage());
        return false;
    }
}

// ── SEND EMAIL CHANGE ALERT (OLD) ────────────────────────────────────────────
function sendEmailChangeAlert_Old(string $oldEmail, string $toName, string $newEmail): bool {
    try {
        $mail = buildMailer();
        $mail->addAddress($oldEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Email Address Changed - Attendify';
        $mail->Body    = generateEmailChangeOldTemplate($toName, $newEmail);
        $mail->AltBody = "Hello $toName,\n\nYour email address has been changed to $newEmail. From now on, all notifications will be sent to the new address.\n\nAttendify Team";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Old Email Change Alert Error: " . $e->getMessage());
        return false;
    }
}

// ── SEND EMAIL CHANGE ALERT (NEW) ────────────────────────────────────────────
function sendEmailChangeAlert_New(string $newEmail, string $toName, string $oldEmail): bool {
    try {
        $mail = buildMailer();
        $mail->addAddress($newEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Email Verified Successfully - Attendify';
        $mail->Body    = generateEmailChangeNewTemplate($toName, $oldEmail);
        $mail->AltBody = "Hello $toName,\n\nYour email address has been successfully changed to this one. The previous address ($oldEmail) has been removed from your account.\n\nAttendify Team";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("New Email Change Alert Error: " . $e->getMessage());
        return false;
    }
}

// ── SEND DELETION NOTIFICATION ───────────────────────────────────────────────
function sendDeletionNotification(string $toEmail, string $toName): bool {
    try {
        $mail = buildMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Account Deleted - Attendify';
        $mail->Body    = generateDeletionEmailHTML($toName);
        $mail->AltBody = "Hello $toName,\n\nYour account has been deleted from Attendify. All your data and photos have been removed from our system.\n\nAttendify Team";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Deletion Email Error: " . $e->getMessage());
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
        <p>We have received a request to send a OTP to your email address. Please use the OTP provided below to proceed:</p>
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
    $passwordRow = $password ? "<p>Password: <strong style=\"color:#4f46e5\">{$password}</strong></p>" : '';
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>
    body{font-family: Arial, sans-serif; line-height: 1.6; color: #333; padding: 20px;}
    .container{max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; padding: 20px;}
    .header{font-size: 20px; font-weight: bold; margin-bottom: 20px; color: #4f46e5;}
    .box{background: #f4f4f4; padding: 15px; border-radius: 5px; margin: 20px 0;}
    .reg-id{font-size: 19px; font-weight: bold; color: #333;}
    .footer{font-size: 12px; color: #666; margin-top: 30px; border-top: 1px solid #eee; padding-top: 10px;}
</style></head>
<body>
<div class="container">
    <div class="header">Registration Successful - Attendify</div>
    <p>Dear <strong>{$name}</strong>,</p>
    <p>Your account has been created. Here are your credentials:</p>
    <div class="box">
        <strong>Registration ID:</strong> <span class="reg-id">{$regId}</span><br>
        <strong>Role:</strong> {$roleLabel}<br>
        <strong>Email:</strong> {$email}<br>
        {$passwordRow}
        <strong>Click here to visit website ➡️<a href="https://attendify.gt.tc">Attendify</a></strong>
    </div>
    <p><strong>Important:</strong> Please keep these credentials safe.</p>
    <div class="footer">
        Thank you for using <strong>Attendify</strong><br>
        &copy; Attendance Management System
    </div>
</div>
</body></html>
HTML;
}

function generateProfileUpdateEmailHTML(string $name): string {
    return '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>
    body{font-family: Arial, sans-serif; line-height: 1.6; color: #333; padding: 20px;}
    .container{max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; padding: 20px;}
    .header{font-size: 20px; font-weight: bold; margin-bottom: 20px; color: #4f46e5; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;}
    .footer{font-size: 12px; color: #666; margin-top: 30px; border-top: 1px solid #eee; padding-top: 10px;}
</style></head>
<body>
<div class="container">
    <div class="header">Account Details Updated - Attendify</div>
    <p>Hello <strong>' . $name . '</strong>,</p>
    <p>This is to inform you that some changes have been made to your Attendify account profile by the administrator.</p>
    <p>The updated details include one or more of the following: <strong>Name, Phone Number, Date of Birth, Role, Department, or Branch</strong>.</p>
    <p>Please log in to your account to verify your updated information. If you did not expect these changes, please contact the administration immediately.</p>
    <div class="footer">
        Team Attendify<br>
        &copy; Attendance Management System
    </div>
</div>
</body></html>';
}

function generateAdminPasswordUpdateEmailHTML(string $name, string $regId, string $newPassword): string {
    return '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>
    body{font-family: Arial, sans-serif; line-height: 1.6; color: #333; padding: 20px;}
    .container{max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; padding: 200/px 20px 30px 20px;}
    .header{font-size: 20px; font-weight: bold; margin-bottom: 20px; color: #ef4444; border-bottom: 2px solid #fef2f2; padding-bottom: 10px;}
    .box{background: #f8fafc; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; margin: 20px 0;}
    .footer{font-size: 12px; color: #666; margin-top: 30px; border-top: 1px solid #eee; padding-top: 10px;}
</style></head>
<body>
<div class="container">
    <div class="header">Your Password Has Been Changed</div>
    <p>Hello <strong>' . $name . '</strong>,</p>
    <p>Your Attendify account password has been reset by the administration. You can now log in using the credentials below:</p>
    <div class="box">
        <p style="margin: 5px 0;"><strong>Registration ID:</strong> ' . $regId . '</p>
        <p style="margin: 5px 0;"><strong>New Password:</strong> <span style="color: #4f46e5; font-weight: bold;">' . $newPassword . '</span></p>
    </div>
    <p style="color: #64748b; font-size: 14px;"><em>For your security, we recommend changing this password immediately after logging in.</em></p>
    <div class="footer">
        Attendify Security Team<br>
        &copy; Attendance Management System
    </div>
</div>
</body></html>';
}

function generateEmailChangeOldTemplate(string $name, string $newEmail): string {
    return '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>
    body{font-family: Arial, sans-serif; line-height: 1.6; color: #333; padding: 20px;}
    .container{max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; padding: 20px;}
    .header{font-size: 20px; font-weight: bold; margin-bottom: 20px; color: #f59e0b; border-bottom: 2px solid #fffbeb; padding-bottom: 10px;}
    .footer{font-size: 12px; color: #666; margin-top: 30px; border-top: 1px solid #eee; padding-top: 10px;}
</style></head>
<body>
<div class="container">
    <div class="header">Your Email Address Has Been Changed</div>
    <p>Hello <strong>' . $name . '</strong>,</p>
    <p>This is a security notification to inform you that your registered email address on Attendify has been successfully changed to: <strong>' . $newEmail . '</strong></p>
    <p>From now on, all notifications, attendance reports, and account alerts will be sent exclusively to the new email address.</p>
    <p>If you did not authorize this change, please report it to the principal or administrator immediately.</p>
    <div class="footer">
        Attendify Security<br>
        &copy; Attendance Management System
    </div>
</div>
</body></html>';
}

function generateEmailChangeNewTemplate(string $name, string $oldEmail): string {
    return '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>
    body{font-family: Arial, sans-serif; line-height: 1.6; color: #333; padding: 20px;}
    .container{max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; padding: 20px;}
    .header{font-size: 20px; font-weight: bold; margin-bottom: 20px; color: #10b981; border-bottom: 2px solid #f0fdf4; padding-bottom: 10px;}
    .footer{font-size: 12px; color: #666; margin-top: 30px; border-top: 1px solid #eee; padding-top: 10px;}
</style></head>
<body>
<div class="container">
    <div class="header">Email Successfully Updated</div>
    <p>Hello <strong>' . $name . '</strong>,</p>
    <p>Your email address for Attendify has been successfully updated to this account.</p>
    <p>We have removed your previous email (<strong>' . $oldEmail . '</strong>) from our records. You will now receive all account-related updates and attendance info here.</p>
    <div class="footer">
        Welcome to your new primary email!<br>
        Attendify Team
    </div>
</div>
</body></html>';
}

function generateDeletionEmailHTML(string $name): string {
    return '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>
    body{font-family: Arial, sans-serif; line-height: 1.6; color: #333; padding: 20px;}
    .container{max-width: 600px; margin: 0 auto; border: 1px solid #ef4444; border-radius: 8px; padding: 20px; background: #fffafb;}
    .header{font-size: 20px; font-weight: bold; margin-bottom: 20px; color: #ef4444;}
    .footer{font-size: 12px; color: #666; margin-top: 30px; border-top: 1px solid #fee2e2; padding-top: 10px;}
</style></head>
<body>
<div class="container">
    <div class="header">Your Attendify Account Has Been Deleted</div>
    <p>Dear <strong>' . $name . '</strong>,</p>
    <p>This email is to inform you that your Attendify account has been officially deleted from our system by the administrator.</p>
    <p><strong>What does this mean?</strong></p>
    <ul>
        <li>You can no longer log in to the Attendify portal.</li>
        <li>Your profile photo and identification QR code have been permanently removed.</li>
        <li>All your current session attendance logs have been erased.</li>
    </ul>
    <p>If you believe this was an error, please contact your department head or the administration office.</p>
    <div class="footer">
        Attendify Team<br>
        &copy; Attendance Management System
    </div>
</div>
</body></html>';
}
?>