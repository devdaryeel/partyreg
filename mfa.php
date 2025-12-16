<?php
session_start();

// Check if user came from login page
if (!isset($_SESSION['mfa_user_id'])) {
    header("Location: login.php");
    exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection for Hostinger
$host = '127.0.0.1';
$port = '3306';
$dbname = 'u512201512_partyregsys';
$db_username = 'u512201512_dev_partyregsy';
$db_password = '8L+Puyhvz*o';

// Initialize variables
$error = "";
$success = "";
$otp_sent = false;
$otp_code = "";
$email = "";
$user_name = "";

// Database connection
$conn = null;
try {
    $conn = new mysqli($host, $db_username, $db_password, $dbname, $port);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    $error = "System temporarily unavailable. Please try again later.";
    error_log("Database connection error: " . $e->getMessage());
}

// Get user information
if ($conn && isset($_SESSION['mfa_user_id'])) {
    $user_id = $_SESSION['mfa_user_id'];
    
    $stmt = $conn->prepare("SELECT u.user_id, u.username, u.full_name, u.email FROM users u WHERE u.user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                $email = $user['email'];
                $user_name = $user['full_name'];
                
                // Mask email for display
                $email_parts = explode('@', $email);
                if (count($email_parts) == 2) {
                    $username_part = $email_parts[0];
                    $domain_part = $email_parts[1];
                    
                    // Show first 2 characters and last 2 characters of username part
                    if (strlen($username_part) > 4) {
                        $masked_username = substr($username_part, 0, 2) . str_repeat('*', strlen($username_part) - 4) . substr($username_part, -2);
                    } else {
                        $masked_username = substr($username_part, 0, 1) . str_repeat('*', strlen($username_part) - 1);
                    }
                    
                    $masked_email = $masked_username . '@' . $domain_part;
                } else {
                    $masked_email = $email;
                }
            }
        }
        $stmt->close();
    }
}

// Generate and send OTP
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_otp'])) {
    if ($conn && isset($_SESSION['mfa_user_id']) && !empty($email)) {
        // Generate 6-digit OTP
        $otp_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Delete any existing OTPs for this user
        $delete_stmt = $conn->prepare("DELETE FROM otp_authentications WHERE user_id = ? AND otp_type = 'login' AND is_used = 0");
        if ($delete_stmt) {
            $delete_stmt->bind_param("i", $_SESSION['mfa_user_id']);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
        
        // Insert new OTP
        $insert_stmt = $conn->prepare("INSERT INTO otp_authentications (user_id, otp_code, email, otp_type, expires_at) VALUES (?, ?, ?, 'login', ?)");
        if ($insert_stmt) {
            $insert_stmt->bind_param("isss", $_SESSION['mfa_user_id'], $otp_code, $email, $expires_at);
            if ($insert_stmt->execute()) {
                // Send OTP via email
                $subject = "Your OTP for Party Registration System Login";
                $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #2a82f5, #049133); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                        .otp-code { font-size: 32px; font-weight: bold; color: #2a82f5; text-align: center; letter-spacing: 5px; margin: 20px 0; padding: 15px; background: white; border-radius: 5px; border: 2px dashed #2a82f5; }
                        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
                        .warning { color: #e74c3c; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Party Registration System</h2>
                            <p>Multi-Factor Authentication</p>
                        </div>
                        <div class='content'>
                            <h3>Hello $user_name,</h3>
                            <p>You have requested to login to the Party Registration System. Please use the following One-Time Password (OTP) to complete your login:</p>
                            
                            <div class='otp-code'>$otp_code</div>
                            
                            <p>This OTP is valid for <strong>10 minutes</strong> only.</p>
                            
                            <p class='warning'>⚠️ Do not share this OTP with anyone. If you did not request this, please ignore this email or contact system administrator immediately.</p>
                            
                            <p>Party Members Registration System<br>Developed by Daryeel ICT Solutions</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message, please do not reply to this email.</p>
                            <p>&copy; " . date('Y') . " Daryeel Software Solutions. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: Party Registration System <noreply@daryeelict.com>" . "\r\n";
                $headers .= "Reply-To: support@daryeelict.com" . "\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();
                
                if (mail($email, $subject, $message, $headers)) {
                    $otp_sent = true;
                    $success = "OTP has been sent to your email address. Please check your inbox (and spam folder).";
                } else {
                    $error = "Failed to send OTP email. Please try again.";
                }
            } else {
                $error = "Failed to generate OTP. Please try again.";
            }
            $insert_stmt->close();
        } else {
            $error = "System error. Please try again.";
        }
    } else {
        $error = "Unable to process OTP request. Please try logging in again.";
    }
}

// Verify OTP
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_otp'])) {
    if ($conn && isset($_SESSION['mfa_user_id']) && isset($_POST['otp_code'])) {
        $entered_otp = trim($_POST['otp_code']);
        
        if (empty($entered_otp)) {
            $error = "Please enter the OTP code.";
        } elseif (strlen($entered_otp) != 6 || !is_numeric($entered_otp)) {
            $error = "Invalid OTP format. Please enter a 6-digit number.";
        } else {
            // Verify OTP
            $stmt = $conn->prepare("SELECT otp_id, expires_at FROM otp_authentications WHERE user_id = ? AND otp_code = ? AND otp_type = 'login' AND is_used = 0 ORDER BY created_at DESC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("is", $_SESSION['mfa_user_id'], $entered_otp);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    if ($result->num_rows == 1) {
                        $otp_data = $result->fetch_assoc();
                        
                        // Check if OTP is expired
                        if (strtotime($otp_data['expires_at']) < time()) {
                            $error = "OTP has expired. Please request a new one.";
                        } else {
                            // Mark OTP as used
                            $update_stmt = $conn->prepare("UPDATE otp_authentications SET is_used = 1 WHERE otp_id = ?");
                            if ($update_stmt) {
                                $update_stmt->bind_param("i", $otp_data['otp_id']);
                                $update_stmt->execute();
                                $update_stmt->close();
                            }
                            
                            // Transfer session data and redirect to dashboard
                            $_SESSION['user_id'] = $_SESSION['mfa_user_id'];
                            $_SESSION['username'] = $_SESSION['mfa_username'];
                            $_SESSION['full_name'] = $_SESSION['mfa_full_name'];
                            $_SESSION['role_id'] = $_SESSION['mfa_role_id'];
                            $_SESSION['role_name'] = $_SESSION['mfa_role_name'];
                            $_SESSION['loggedin'] = true;
                            $_SESSION['LAST_ACTIVITY'] = time();
                            
                            // Clear MFA session data
                            unset($_SESSION['mfa_user_id']);
                            unset($_SESSION['mfa_username']);
                            unset($_SESSION['mfa_full_name']);
                            unset($_SESSION['mfa_role_id']);
                            unset($_SESSION['mfa_role_name']);
                            
                            // Redirect to dashboard
                            header("Location: dashboard.php");
                            exit;
                        }
                    } else {
                        $error = "Invalid OTP code. Please try again.";
                    }
                } else {
                    $error = "System error. Please try again.";
                }
                $stmt->close();
            } else {
                $error = "System error. Please try again.";
            }
        }
    } else {
        $error = "Invalid request. Please try logging in again.";
    }
}

// Resend OTP
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['resend_otp'])) {
    // Same logic as send_otp
    if ($conn && isset($_SESSION['mfa_user_id']) && !empty($email)) {
        $otp_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $delete_stmt = $conn->prepare("DELETE FROM otp_authentications WHERE user_id = ? AND otp_type = 'login' AND is_used = 0");
        if ($delete_stmt) {
            $delete_stmt->bind_param("i", $_SESSION['mfa_user_id']);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
        
        $insert_stmt = $conn->prepare("INSERT INTO otp_authentications (user_id, otp_code, email, otp_type, expires_at) VALUES (?, ?, ?, 'login', ?)");
        if ($insert_stmt) {
            $insert_stmt->bind_param("isss", $_SESSION['mfa_user_id'], $otp_code, $email, $expires_at);
            if ($insert_stmt->execute()) {
                // Send email (same as above)
                $subject = "Your New OTP for Party Registration System Login";
                $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #2a82f5, #049133); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                        .otp-code { font-size: 32px; font-weight: bold; color: #2a82f5; text-align: center; letter-spacing: 5px; margin: 20px 0; padding: 15px; background: white; border-radius: 5px; border: 2px dashed #2a82f5; }
                        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
                        .warning { color: #e74c3c; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Party Registration System</h2>
                            <p>Multi-Factor Authentication - New OTP</p>
                        </div>
                        <div class='content'>
                            <h3>Hello $user_name,</h3>
                            <p>You have requested a new One-Time Password (OTP) for login to the Party Registration System:</p>
                            
                            <div class='otp-code'>$otp_code</div>
                            
                            <p>This OTP is valid for <strong>10 minutes</strong> only.</p>
                            
                            <p class='warning'>⚠️ Do not share this OTP with anyone. If you did not request this, please ignore this email or contact system administrator immediately.</p>
                            
                            <p>Best regards,<br>Party Registration System Team<br>Daryeel ICT Solutions</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message, please do not reply to this email.</p>
                            <p>&copy; " . date('Y') . " Party Registration System. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: Party Registration System <noreply@daryeelict.com>" . "\r\n";
                $headers .= "Reply-To: support@daryeelict.com" . "\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();
                
                if (mail($email, $subject, $message, $headers)) {
                    $otp_sent = true;
                    $success = "New OTP has been sent to your email address.";
                } else {
                    $error = "Failed to send OTP email. Please try again.";
                }
            } else {
                $error = "Failed to generate OTP. Please try again.";
            }
            $insert_stmt->close();
        } else {
            $error = "System error. Please try again.";
        }
    } else {
        $error = "Unable to process OTP request. Please try logging in again.";
    }
}

// Close connection if it exists
if ($conn !== null) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Factor Authentication - Party Registration System</title>
    <link rel="icon" href="icontest.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2a82f5;
            --secondary-color: #049133;
            --white-color: #ffffff;
            --dark-color: #333333;
            --light-gray: #f5f5f5;
            --error-color: #e74c3c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .mfa-container {
            background: var(--white-color);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
            position: relative;
        }
        
        .mfa-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            color: var(--primary-color);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .logo span {
            color: var(--secondary-color);
        }
        
        .mfa-title {
            color: var(--dark-color);
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .mfa-subtitle {
            color: var(--dark-color);
            font-size: 14px;
            font-weight: 500;
            line-height: 1.4;
            margin-bottom: 20px;
        }
        
        .user-info {
            background: var(--light-gray);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .user-info p {
            margin: 5px 0;
            font-size: 14px;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .user-email {
            color: var(--dark-color);
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 14px;
        }
        
        .otp-input-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            transition: all 0.3s ease;
            background: var(--white-color);
        }
        
        .otp-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(42, 130, 245, 0.1);
        }
        
        .otp-input.filled {
            border-color: var(--success-color);
            background-color: #e8f6ef;
        }
        
        .otp-timer {
            text-align: center;
            font-size: 14px;
            color: var(--warning-color);
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .verify-button {
            width: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--white-color);
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .verify-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .send-otp-button {
            width: 100%;
            background: linear-gradient(135deg, var(--warning-color), #e67e22);
            color: var(--white-color);
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .send-otp-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .resend-otp-button {
            width: 100%;
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .resend-otp-button:hover {
            background: var(--primary-color);
            color: var(--white-color);
        }
        
        .cancel-button {
            width: 100%;
            background: transparent;
            color: var(--error-color);
            border: 2px solid var(--error-color);
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .cancel-button:hover {
            background: var(--error-color);
            color: var(--white-color);
        }
        
        .error-message {
            background-color: #ffeaea;
            color: var(--error-color);
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            border-left: 4px solid var(--error-color);
            font-size: 14px;
        }
        
        .success-message {
            background-color: #e8f6ef;
            color: var(--success-color);
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            border-left: 4px solid var(--success-color);
            font-size: 14px;
        }
        
        .info-box {
            background-color: #e8f4ff;
            color: var(--primary-color);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
            font-size: 14px;
        }
        
        .info-box i {
            margin-right: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #e1e5e9;
            color: #888;
            font-size: 11px;
        }
        
        .footer p {
            margin: 3px 0;
        }
        
        @media (max-width: 768px) {
            .mfa-container {
                padding: 30px 25px;
            }
            
            .logo {
                font-size: 24px;
            }
            
            .mfa-title {
                font-size: 20px;
            }
            
            .otp-input {
                width: 45px;
                height: 55px;
                font-size: 22px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .mfa-container {
                padding: 25px 20px;
            }
            
            .otp-input {
                width: 40px;
                height: 50px;
                font-size: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="mfa-container">
        <div class="mfa-header">
            <div class="logo">Party <span>Members</span> Registration</div>
            <div class="mfa-title">Multi-Factor Authentication</div>
            <div class="mfa-subtitle">Enter the OTP sent to your registered email address</div>
        </div>
        
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="user-info">
            <p class="user-name">Hello, <?php echo htmlspecialchars($user_name); ?></p>
            <p class="user-email">OTP will be sent to: <?php echo isset($masked_email) ? htmlspecialchars($masked_email) : htmlspecialchars($email); ?></p>
        </div>
        
        <?php if (!$otp_sent): ?>
        <div class="info-box">
            <i class="fas fa-info-circle"></i> 
            Click the button below to receive a One-Time Password (OTP) via email.
        </div>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <button type="submit" name="send_otp" class="send-otp-button">
                <i class="fas fa-paper-plane"></i> Send OTP to Email
            </button>
        </form>
        <?php else: ?>
        <div class="info-box">
            <i class="fas fa-envelope"></i> 
            A 6-digit OTP has been sent to your email. Please check your inbox (and spam folder).
        </div>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="otpForm">
            <div class="form-group">
                <label for="otp_code">Enter 6-Digit OTP</label>
                <div class="otp-input-container">
                    <input type="text" class="otp-input" maxlength="1" data-index="1" autocomplete="off">
                    <input type="text" class="otp-input" maxlength="1" data-index="2" autocomplete="off">
                    <input type="text" class="otp-input" maxlength="1" data-index="3" autocomplete="off">
                    <input type="text" class="otp-input" maxlength="1" data-index="4" autocomplete="off">
                    <input type="text" class="otp-input" maxlength="1" data-index="5" autocomplete="off">
                    <input type="text" class="otp-input" maxlength="1" data-index="6" autocomplete="off">
                </div>
                <input type="hidden" name="otp_code" id="otp_code_hidden">
            </div>
            
            <div class="otp-timer" id="otpTimer">
                OTP expires in: <span id="timer">10:00</span>
            </div>
            
            <button type="submit" name="verify_otp" class="verify-button">
                <i class="fas fa-check-circle"></i> Verify OTP
            </button>
        </form>
        
        <div class="action-buttons">
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" style="flex: 1;">
                <button type="submit" name="resend_otp" class="resend-otp-button">
                    <i class="fas fa-redo"></i> Resend OTP
                </button>
            </form>
            <a href="logout.php?cancel_mfa=true" class="cancel-button" style="text-decoration: none; text-align: center; flex: 1;">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>&copy; 2025 Party Members Registration System. All rights reserved.</p>
            <p>Developed by Daryeel ICT Solutions</p>
            <p>Version 1.0</p>
        </div>
    </div>

    <script>
        // OTP input handling
        const otpInputs = document.querySelectorAll('.otp-input');
        const otpHidden = document.getElementById('otp_code_hidden');
        
        // Auto-focus first OTP input
        if (otpInputs.length > 0) {
            otpInputs[0].focus();
        }
        
        // Handle OTP input
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value;
                
                // Only allow numbers
                if (!/^\d*$/.test(value)) {
                    e.target.value = '';
                    return;
                }
                
                // If a digit is entered, move to next input
                if (value.length === 1 && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
                
                // Update hidden field
                updateHiddenOTP();
                
                // Add filled class
                if (value.length === 1) {
                    e.target.classList.add('filled');
                } else {
                    e.target.classList.remove('filled');
                }
            });
            
            // Handle backspace
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    otpInputs[index - 1].focus();
                    otpInputs[index - 1].value = '';
                    otpInputs[index - 1].classList.remove('filled');
                    updateHiddenOTP();
                }
            });
            
            // Handle paste
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').trim();
                
                // Only allow numbers
                if (!/^\d+$/.test(pastedData)) return;
                
                // Fill OTP inputs with pasted data
                const digits = pastedData.split('');
                for (let i = 0; i < Math.min(digits.length, otpInputs.length); i++) {
                    otpInputs[i].value = digits[i];
                    otpInputs[i].classList.add('filled');
                }
                
                // Focus next empty input or last input
                const nextEmptyIndex = digits.length < otpInputs.length ? digits.length : otpInputs.length - 1;
                otpInputs[nextEmptyIndex].focus();
                
                updateHiddenOTP();
            });
        });
        
        function updateHiddenOTP() {
            let otp = '';
            otpInputs.forEach(input => {
                otp += input.value;
            });
            otpHidden.value = otp;
        }
        
        // OTP Timer
        <?php if ($otp_sent): ?>
        let timerInterval;
        let timeLeft = 600; // 10 minutes in seconds
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            document.getElementById('timer').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                document.getElementById('otpTimer').innerHTML = 
                    '<span style="color: var(--error-color);">OTP expired! Please request a new one.</span>';
            } else {
                timeLeft--;
            }
        }
        
        // Start timer
        timerInterval = setInterval(updateTimer, 1000);
        updateTimer(); // Initial call
        <?php endif; ?>
        
        // Form validation
        document.getElementById('otpForm')?.addEventListener('submit', (e) => {
            const otp = otpHidden.value;
            if (otp.length !== 6) {
                e.preventDefault();
                alert('Please enter a complete 6-digit OTP code.');
                otpInputs[0].focus();
            }
        });
        
        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>