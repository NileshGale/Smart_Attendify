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
        
        $content = "
            <p style='margin-bottom: 24px;'>We received a request to reset your password. Use the security code below to proceed with the reset. It will expire in 2 minutes.</p>
            <div style='background: #f8f7ff; border: 2px dashed #1a1a7a; border-radius: 12px; text-align: center; padding: 24px; margin: 24px 0;'>
                <div style=\"font-size: 42px; font-weight: 900; letter-spacing: .2em; color: #1a1a7a; font-family: 'Courier New', monospace;\">{$otp}</div>
                <div style='color: #ef4444; font-weight: 600; font-size: 14px; margin-top: 8px;'>Expires in 2 minutes</div>
            </div>
            <p style='color: #64748b; font-size: 14px;'>If you did not request this, you can safely ignore this email.</p>
        ";
        
        $mail->Body = generatePremiumTemplate(
            "Account Security",
            "Confirm Your Identity",
            "hi {$toName},",
            $content
        );
        
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
        
        $roleLabel = ucfirst($role);
        $passwordInfo = $password ? "Your password is: <strong style='color:#1a1a7a'>{$password}</strong>" : "Use your existing password to log in.";
        
        $content = "
            <p>Welcome to Attendify! Your account has been successfully created. You can now access your dashboard using the credentials below:</p>
            <div style='background: #f8fafc; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; margin: 24px 0;'>
                <p style='margin: 5px 0;'><strong>Registration ID:</strong> {$regId}</p>
                <p style='margin: 5px 0;'><strong>Role:</strong> {$roleLabel}</p>
                <p style='margin: 5px 0;'><strong>Email:</strong> {$toEmail}</p>
                <p style='margin: 5px 0;'>{$passwordInfo}</p>
            </div>
            <p>We're excited to have you on board. Start tracking your attendance today! and for security reasons, we strongly recommend changing your password after your first login</p>
        ";
        
        $mail->Body = generatePremiumTemplate(
            "Welcome to Attendify",
            "Thanks for signing up",
            "hi {$toName},",
            $content,
            "Go to Attendify",
            "https://attendify.gt.tc"
        );
        
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
        
        $content = "
            <p>This is a security notification to inform you that your profile information has been updated by the administrator.</p>
            <p>The following fields may have been modified: <strong>Name, Phone, Academic Details, or Security Settings</strong>.</p>
            <p style='margin-top: 20px;'>If you authorized these changes, no further action is required. If not, please contact the Attendify Administrator immediately.</p>
        ";
        
        $mail->Body = generatePremiumTemplate(
            "Profile Update",
            "Your Account was Modified",
            "hi {$toName},",
            $content,
            "Go to Attendify",
            "https://attendify.gt.tc"
        );
        
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
        
        $content = "
            <p>Your password has been reset by the system administrator. You can now log in using the temporary credentials provided below:</p>
            <div style='background: #fff5f5; padding: 20px; border: 1px solid #feb2b2; border-radius: 12px; margin: 24px 0;'>
                <p style='margin: 5px 0;'><strong>Registration ID:</strong> {$regId}</p>
                <p style='margin: 5px 0;'><strong>New Password:</strong> <strong style='color:#ef4444'>{$newPassword}</strong></p>
            </div>
            <p style='color: #64748b; font-size: 14px;'><em>Note: We strongly recommend changing this password immediately after logging in. & if you didn't requested to change password then report it to the Attendify Administrator immediately</em></p>
        ";
        
        $mail->Body = generatePremiumTemplate(
            "Security Alert",
            "Password Reset by Admin",
            "hi {$toName},",
            $content,
            "Log In Now",
            "https://attendify.gt.tc"
        );
        
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
        
        $content = "
            <p>Your registered email address on Attendify has been changed. From now on, you will no longer receive updates at this address.</p>
            <p style='margin: 20px 0;'><strong>New Email:</strong> {$newEmail}</p>
            <p style='color: #ef4444; font-weight: 600;'>If you did not authorize this change, please report it to the Attendify Administrator immediately.</p>
        ";
        
        $mail->Body = generatePremiumTemplate(
            "Security Update",
            "Email Address Transfer",
            "hi {$toName},",
            $content,
            "Contact Support",
            "mailto:nileshgale520@gmail.com"
        );
        
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
        
        $content = "
            <p>Your email address has been successfully updated to this account. Your previous address (<strong>{$oldEmail}</strong>) has been unlinked.</p>
            <p>You will now receive all attendance reports, OTPs, and system alerts at this address.</p>
        ";
        
        $mail->Body = generatePremiumTemplate(
            "Verification",
            "New Email Linked",
            "hi {$toName},",
            $content,
            "Open Attendify",
            "https://attendify.gt.tc"
        );
        
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
        
        $content = "
            <p>This is a final confirmation that your Attendify account and all associated data (profile photo, QR code, attendance logs) have been permanently removed from our system.</p>
            <p style='margin-top: 20px;'>If you believe this account should not have been deleted, please contact the administration office.</p>
        ";
        
        $mail->Body = generatePremiumTemplate(
            "Account Closed",
            "Your Data was Removed",
            "hi {$toName},",
            $content,
            "Contact Office",
            "mailto:nileshgale520@gmail.com"
        );
        
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

// ── UNIVERSAL PREMIUM TEMPLATE (Recess Inspired) ──────────────────────────────

function generatePremiumTemplate(string $title, string $subtitle, string $greeting, string $content, string $actionText = '', string $actionUrl = ''): string {
    $baseUrl = 'https://attendify.gt.tc';
    $headerImg = $baseUrl . '/frontend/img/email_header_3d.jpg'; // Using optimized JPG for speed

    $buttonHtml = '';
    if (!empty($actionText)) {
        $buttonHtml = "
            <div class='btn-container'>
                <a href='{$actionUrl}' class='button'>{$actionText}</a>
            </div>";
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { margin: 0; padding: 0; background-color: #fdf1ec; font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #fdf1ec; padding-bottom: 40px; padding-top: 40px; }
        .main-table { width: 100%; max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 12px solid #1a1a7a; border-radius: 4px; border-collapse: separate; }
        .header-img-cell { padding: 40px 20px 20px; text-align: center; -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
        .header-img { width: 100%; max-width: 320px; height: auto; display: inline-block; pointer-events: none; -webkit-user-drag: none; pointer-events: none; }
        .content-cell { padding: 0 50px 40px; }
        .branding-text { color: #1a1a7a; font-family: Georgia, serif; font-size: 24px; font-weight: bold; margin-bottom: 40px; text-align: center; letter-spacing: -0.02em; }
        .headline { color: #1a1a7a; font-family: Georgia, serif; font-size: 32px; font-weight: bold; line-height: 1.2; margin-bottom: 12px; text-align: center; }
        .subtitle { color: #1a1a7a; font-size: 16px; font-weight: 600; margin-bottom: 40px; text-align: center; opacity: 0.8; }
        .greeting { color: #1a1a7a; font-size: 16px; margin-bottom: 20px; font-weight: 500; }
        .body-text { color: #1a1a7a; font-size: 15px; line-height: 1.6; margin-bottom: 30px; }
        .btn-container { text-align: center; padding-top: 10px; padding-bottom: 40px; }
        .button { background-color: #0000ff; color: #ffffff !important; padding: 16px 48px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 4px 12px rgba(0,0,255,0.2); }
        .footer-cell { padding: 30px 50px; border-top: 1px solid #fdf1ec; }
        .footer-text { color: #1a1a7a; font-size: 13px; line-height: 1.6; opacity: 0.7; }
        .footer-link { color: #1a1a7a; text-decoration: underline; }
        @media screen and (max-width: 600px) {
            .content-cell { padding: 0 25px 30px; }
            .headline { font-size: 26px; }
            .main-table { border-width: 8px; }
        }
    </style>
</head>
<body>
    <center class="wrapper">
        <table class="main-table" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td class="header-img-cell">
                    <img src="{$headerImg}" alt="Attendify" class="header-img">
                </td>
            </tr>
            <tr>
                <td style="padding: 0 50px;">
                    <div class="branding-text">Attendify</div>
                </td>
            </tr>
            <tr>
                <td class="content-cell">
                    <div class="headline">{$title}</div>
                    <div class="subtitle">{$subtitle}</div>
                    
                    <div class="greeting">{$greeting}</div>
                    <div class="body-text">
                        {$content}
                    </div>
                    
                    {$buttonHtml}
                </td>
            </tr>
            <tr>
                <td class="footer-cell">
                    <div class="footer-text">
                        If you didn't request this email or you're not sure why you received it, you can safely ignore it. Your account will not be affected.<br><br>
                        If you have any questions, you can reply to this email or contact Attendify Support at <a href="mailto:nileshgale520@gmail.com" class="footer-link">nileshgale520@gmail.com</a>.
                    </div>
                </td>
            </tr>
        </table>
    </center>
</body>
</html>
HTML;
}
?>