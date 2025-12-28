<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables
$error = "";
$username = "";
$success_message = "";
$remember_me = false;

// Check for logout or timeout messages
if (isset($_GET['message']) && $_GET['message'] == 'logout') {
    $success_message = "You have been successfully logged out.";
} elseif (isset($_GET['message']) && $_GET['message'] == 'timeout') {
    $error = "Your session has expired. Please login again.";
}

// Handle remember me cookie
if (isset($_COOKIE['remembered_username'])) {
    $username = $_COOKIE['remembered_username'];
    $remember_me = true;
}

// Database connection for Hostinger
$host = '127.0.0.1';
$port = '3306';
$dbname = 'partgrey';
$db_username = 'root';
$db_password = '';

// Database connection
$conn = null;
try {
    $conn = new mysqli($host, $db_username, $db_password, $dbname, $port);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    // Set charset to utf8
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    $error = "System temporarily unavailable. Please try again later.";
    error_log("Database connection error: " . $e->getMessage());
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $remember_me = isset($_POST['remember_me']);
    
    // Set remember me cookie if checked
    if ($remember_me) {
        setcookie('remembered_username', $username, time() + (30 * 24 * 60 * 60), "/"); // 30 days
    } else {
        // Remove cookie if not checked
        setcookie('remembered_username', '', time() - 3600, "/");
    }
    
    // Validate inputs
    if (empty($username) && empty($password)) {
        $error = "Please fill username and password or use Phone number to register as New Member";
    } elseif (empty($username)) {
        $error = "Please fill username with phone number as member or username";
    } elseif (empty($password)) {
        $error = "Please fill password";
    } elseif (strlen($username) > 13 && preg_match('/^[0-9]+$/', $username)) {
        $error = "Phone number incorrect. Use real phone number starting with 25261,61,061,062,068,063,066,090,070,0771,25262,25263 and maximum 13 digits";
    } else {
        // Check phone number format if it looks like a phone number
        $is_phone_like = preg_match('/^[0-9]+$/', $username);
        
        if ($is_phone_like) {
            // Check if phone number is valid
            $phone_patterns = [
                '/^25261\d{7}$/',    // 25261xxxxxxxx
                '/^61\d{7}$/',        // 61xxxxxxxx
                '/^061\d{7}$/',       // 061xxxxxxxx
                '/^062\d{7}$/',       // 062xxxxxxxx
                '/^068\d{7}$/',       // 068xxxxxxxx
                '/^063\d{7}$/',       // 063xxxxxxxx
                '/^066\d{7}$/',       // 066xxxxxxxx
                '/^090\d{7}$/',       // 090xxxxxxxx
                '/^070\d{7}$/',       // 070xxxxxxxx
                '/^0771\d{6}$/',      // 0771xxxxxx
                '/^25262\d{7}$/',     // 25262xxxxxxxx
                '/^25263\d{7}$/'      // 25263xxxxxxxx
            ];
            
            $is_valid_phone = false;
            foreach ($phone_patterns as $pattern) {
                if (preg_match($pattern, $username)) {
                    $is_valid_phone = true;
                    break;
                }
            }
            
            if (!$is_valid_phone && strlen($username) > 13) {
                $error = "Phone number incorrect. Use real phone number starting with 25261,61,061,062,068,063,066,090,070,0771,25262,25263 and maximum 13 digits";
            }
        }
        
        // If no error yet, proceed with login/registration
        if (empty($error)) {
            // Check if it's a phone number registration attempt (9-digit starting with 61/68/62/90/67/63/77)
            $is_phone_number = preg_match('/^(61|68|62|90|67|63|77)\d{7}$/', $username);
            
            if ($is_phone_number && $username === $password) {
                // Show confirmation modal for new member registration
                echo "<script>
                    if(confirm('You are registering as a new member with phone number: $username\\n\\nClick OK to continue registration or Cancel to go back.')) {
                        window.location.href = 'memberregistration.php?phone=' + encodeURIComponent('$username');
                    }
                </script>";
                exit;
            } else {
                // Regular user login
                if ($conn === null) {
                    $error = "System temporarily unavailable. Please try again later.";
                } else {
                    // Check user status and role status
                    $sql = "SELECT u.user_id, u.username, u.password, u.full_name, u.role_id, u.otp_enabled, u.isactive as user_active, r.role_name, r.isactive as role_active 
                           FROM users u 
                           JOIN sys_roles r ON u.role_id = r.role_id 
                           WHERE u.username = ?";
                    
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("s", $username);
                        
                        if ($stmt->execute()) {
                            $result = $stmt->get_result();

                            
                            if ($result->num_rows == 1) {
                                $user = $result->fetch_assoc();
                                
                                // Check if user is locked
                                if ($user['user_active'] == 0) {
                                    $error = "User Locked, Contact Administrator";
                                } 
                                // Check if role is inactive
                                elseif ($user['role_active'] == 0) {
                                    $error = "Your Role is Locked. Contact Administrator";
                                }
                                // Verify password if user and role are active
                                elseif ($password === $user['password']) {
                                    // Password is correct, check OTP status
                                    if ($user['otp_enabled'] == 1) {
                                        // Store user data in session for MFA page
                                        $_SESSION['mfa_user_id'] = $user['user_id'];
                                        $_SESSION['mfa_username'] = $user['username'];
                                        $_SESSION['mfa_full_name'] = $user['full_name'];
                                        $_SESSION['mfa_role_id'] = $user['role_id'];
                                        $_SESSION['mfa_role_name'] = $user['role_name'];
                                        
                                        // Redirect to MFA page
                                        header("Location: mfa.php");
                                        exit;
                                    } else {
                                        // No OTP required, start a new session
                                        $_SESSION['user_id'] = $user['user_id'];
                                        $_SESSION['username'] = $user['username'];
                                        $_SESSION['full_name'] = $user['full_name'];
                                        $_SESSION['role_id'] = $user['role_id'];
                                        $_SESSION['role_name'] = $user['role_name'];
                                        $_SESSION['loggedin'] = true;
                                        $_SESSION['LAST_ACTIVITY'] = time();
                                        
                                        // Redirect to dashboard
                                        header("Location: dashboard.php");
                                        exit;
                                    }
                                } else {
                                    $error = "Invalid username or password.";
                                }
                            } else {
                                // User not found - check if it's a phone number for new member registration
                                if (preg_match('/^[0-9]+$/', $username) && $username === $password) {
                                    // Check phone number patterns for new registration
                                    $new_member_patterns = [
                                        '/^25261\d{7}$/',    // 25261xxxxxxxx
                                        '/^61\d{7}$/',        // 61xxxxxxxx
                                        '/^061\d{7}$/',       // 061xxxxxxxx
                                        '/^062\d{7}$/',       // 062xxxxxxxx
                                        '/^068\d{7}$/',       // 068xxxxxxxx
                                        '/^063\d{7}$/',       // 063xxxxxxxx
                                        '/^066\d{7}$/',       // 066xxxxxxxx
                                        '/^090\d{7}$/',       // 090xxxxxxxx
                                        '/^070\d{7}$/',       // 070xxxxxxxx
                                        '/^0771\d{6}$/',      // 0771xxxxxx
                                        '/^25262\d{7}$/',     // 25262xxxxxxxx
                                        '/^25263\d{7}$/',     // 25263xxxxxxxx
                                        '/^61\d{7}$/',        // 61xxxxxxxx (Somalia mobile)
                                        '/^68\d{7}$/',        // 68xxxxxxxx (Somalia mobile)
                                        '/^62\d{7}$/',        // 62xxxxxxxx (Somalia mobile)
                                        '/^90\d{7}$/',        // 90xxxxxxxx (Somalia mobile)
                                        '/^67\d{7}$/',        // 67xxxxxxxx (Somalia mobile)
                                        '/^63\d{7}$/',        // 63xxxxxxxx (Somalia mobile)
                                        '/^77\d{7}$/'         // 77xxxxxxxx (Somalia mobile)
                                    ];
                                    
                                    $is_valid_new_member_phone = false;
                                    foreach ($new_member_patterns as $pattern) {
                                        if (preg_match($pattern, $username)) {
                                            $is_valid_new_member_phone = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($is_valid_new_member_phone) {
                                        // Redirect to new member registration
                                        header("Location: register_member.php?phone=" . urlencode($username));
                                        exit;
                                    } else {
                                        $error = "Invalid phone number format for registration. Please use a valid Somali phone number.";
                                    }
                                } else {
                                    $error = "Invalid username or password.";
                                }
                            }
                            $stmt->close();
                        } else {
                            $error = "System error. Please try again.";
                            error_log("Login execute error: " . $stmt->error);
                        }
                    } else {
                        $error = "System error. Please try again.";
                        error_log("Login prepare error: " . $conn->error);
                    }
                }
            }
        }
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
    <title>Login - Party Member Registration System</title>
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
        
        .login-container {
            background: var(--white-color);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            position: relative;
        }
        
        .login-header {
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
        
        .subtitle {
            color: var(--dark-color);
            font-size: 14px;
            font-weight: 500;
            line-height: 1.4;
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
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: var(--white-color);
        }
        
        .input-with-icon input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(42, 130, 245, 0.1);
        }
        
        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            cursor: pointer;
            font-size: 16px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            margin: 15px 0;
        }
        
        .remember-me input[type="checkbox"] {
            margin-right: 8px;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .remember-me label {
            cursor: pointer;
            font-size: 14px;
            color: var(--dark-color);
        }
        
        .login-button {
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
        
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
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
        
        .help-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }
        
        .forgot-password {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .forgot-password:hover {
            color: var(--secondary-color);
            text-decoration: underline;
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
            .login-container {
                padding: 30px 25px;
            }
            
            .logo {
                font-size: 24px;
            }
            
            .subtitle {
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .login-container {
                padding: 25px 20px;
            }
            
            .input-with-icon input {
                padding: 10px 40px 10px 12px;
                font-size: 14px;
            }
            
            .login-button {
                padding: 12px;
                font-size: 14px;
            }
        }
        
        @media (max-height: 700px) {
            .login-container {
                padding: 30px;
            }
            
            .login-header {
                margin-bottom: 20px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">Party <span>Members</span> Registration</div>
            <div class="subtitle">Secure Party Members Management Access Portal</div>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="loginForm">
            <div class="form-group">
                <label for="username">Username / Phone Number</label>
                <div class="input-with-icon">
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" 
                           placeholder="Enter your username or phone number">
                    <i class="input-icon fas fa-user"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-with-icon">
                    <input type="password" id="password" name="password" 
                           placeholder="Enter your password" maxlength="20">
                    <i class="input-icon fas fa-eye" id="togglePassword"></i>
                </div>
            </div>
            
            <div class="remember-me">
                <input type="checkbox" id="remember_me" name="remember_me" <?php echo $remember_me ? 'checked' : ''; ?>>
                <label for="remember_me">Remember Me</label>
            </div>
            
            <button type="submit" class="login-button">
                <i class="fas fa-sign-in-alt"></i> Login / Register
            </button>
            
            <div class="help-links">
                <a href="forgot-password.php" class="forgot-password">
                    <i class="fas fa-key"></i> Forgot Password?
                </a>
            </div>
        </form>
        
        <div class="footer">
            <p>&copy; 2025 Party Members Registration System. All rights reserved.</p>
            <p>Developed by Daryeel ICT Solutions</p>
            <p>Version 1.0</p>
        </div>
    </div>

    <script>
        // Auto-focus on username field
        document.getElementById('username').focus();
        
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
        
        // Add input validation for phone numbers - only apply limit to numeric inputs
        document.getElementById('username').addEventListener('input', function(e) {
            const value = e.target.value;
            
            // Check if input contains only digits (phone number)
            const isPhoneNumber = /^\d+$/.test(value);
            
            if (isPhoneNumber) {
                // For phone numbers, limit to 13 characters
                if (value.length > 13) {
                    this.value = value.slice(0, 13);
                }
                
                // If it looks like a Somali phone number, suggest using it as password
                if (/^(61|68|62|90|67|63|77)\d{0,7}$/.test(value)) {
                    document.getElementById('password').placeholder = "Use same phone number to register";
                } else {
                    document.getElementById('password').placeholder = "Enter your password";
                }
            } else {
                // For usernames (containing letters/symbols), no character limit
                document.getElementById('password').placeholder = "Enter your password";
            }
        });
        
        // Prevent typing more than 13 characters only for phone numbers
        document.getElementById('username').addEventListener('keydown', function(e) {
            const value = this.value;
            const isPhoneNumber = /^\d+$/.test(value);
            
            if (!isPhoneNumber) {
                // Allow any input for non-phone usernames
                return;
            }
            
            // Allow special keys: backspace, delete, tab, escape, enter, etc.
            if ([8, 9, 13, 16, 17, 18, 20, 27, 33, 34, 35, 36, 37, 38, 39, 40, 45, 46, 91, 92, 93].indexOf(e.keyCode) !== -1) {
                return;
            }
            
            // If already at max length and not deleting, prevent input (only for phone numbers)
            if (isPhoneNumber && value.length >= 13 && !(e.keyCode === 8 || e.keyCode === 46)) {
                e.preventDefault();
            }
        });
        
        // Also prevent paste that would exceed 13 characters only for phone numbers
        document.getElementById('username').addEventListener('paste', function(e) {
            const pastedText = (e.clipboardData || window.clipboardData).getData('text');
            const value = this.value;
            const isPhoneNumber = /^\d+$/.test(value) && /^\d+$/.test(pastedText);
            
            if (isPhoneNumber && value.length + pastedText.length > 13) {
                e.preventDefault();
            }
        });
        
        // Prevent form resubmission warning
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Clear form on page load to prevent browser from remembering POST data
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>
</body>
</html>