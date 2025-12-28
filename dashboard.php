<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Check session timeout (5 minutes)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 300)) {
    session_unset();
    session_destroy();
    header("Location: login.php?message=timeout");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Database connection
$host = '127.0.0.1';
$port = '3306';
$dbname = 'partgrey';
$db_username = 'root';
$db_password = '';

// Initialize variables
$role_data = ['role_name' => 'Unknown', 'description' => 'Unable to fetch role details'];
$stats = [
    'total_members' => 0,
    'today_registered' => 0,
    'yesterday_registered' => 0,
    'week_registered' => 0,
    'active_members' => 0,
    'inactive_members' => 0,
    'total_campaigners' => 0,
    'states_represented' => 0
];

try {
    $conn = new mysqli($host, $db_username, $db_password, $dbname, $port);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Get user role details
    $stmt = $conn->prepare("
        SELECT r.role_name, r.description 
        FROM sys_roles r 
        INNER JOIN users u ON r.role_id = u.role_id 
        WHERE u.user_id = ? 
        AND u.isactive = 1 
        AND r.isactive = 1
    ");
    
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $role_data = $result->fetch_assoc();
        }
        $stmt->close();
    }

    // Get statistics
    // Total Members (assuming you have a 'members' table)
    $result = $conn->query("SELECT COUNT(*) as total FROM members WHERE isactive = 1");
    if ($result) $stats['total_members'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Today's Registered Members
    $result = $conn->query("SELECT COUNT(*) as total FROM members WHERE DATE(datecreated) = CURDATE()");
    if ($result) $stats['today_registered'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Yesterday's Registered Members
    $result = $conn->query("SELECT COUNT(*) as total FROM members WHERE DATE(datecreated) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
    if ($result) $stats['yesterday_registered'] = $result->fetch_assoc()['total'] ?? 0;
    
    // This Week's Registration
    $result = $conn->query("SELECT COUNT(*) as total FROM members WHERE YEARWEEK(datecreated) = YEARWEEK(CURDATE())");
    if ($result) $stats['week_registered'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Active Members
    $result = $conn->query("SELECT COUNT(*) as total FROM members WHERE isactive = 1");
    if ($result) $stats['active_members'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Inactive Members
    $result = $conn->query("SELECT COUNT(*) as total FROM members WHERE isactive = 0");
    if ($result) $stats['inactive_members'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Total Campaigners (assuming you have a 'campaigners' table)
    $result = $conn->query("SELECT COUNT(*) as total FROM campaigners WHERE isactive = 1");
    if ($result) $stats['total_campaigners'] = $result->fetch_assoc()['total'] ?? 0;
    
    // States Represented
    $result = $conn->query("SELECT COUNT(DISTINCT state_id) as total FROM members WHERE state_id IS NOT NULL");
    if ($result) $stats['states_represented'] = $result->fetch_assoc()['total'] ?? 0;
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Get current time and date
$current_time = date('g:i A');
$current_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Party Members Registration System</title>
    <link rel="icon" href="icontest.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-blue: #2a82f5;
            --primary-green: #049133;
            --blue-light: #3b82f6;
            --blue-dark: #1d4ed8;
            --green-light: #10b981;
            --green-dark: #059669;
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --sidebar-bg: linear-gradient(180deg, var(--gray-900) 0%, var(--gray-800) 100%);
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --hover-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, var(--gray-50) 0%, #e6f7ff 100%);
            display: flex;
            min-height: 100vh;
            color: var(--gray-900);
            overflow-x: hidden;
        }
        
        /* Sidebar Styles - Collapsible */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: var(--white);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 5px 0 25px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar.collapsed .logo-text,
        .sidebar.collapsed .system-name,
        .sidebar.collapsed .menu-item span,
        .sidebar.collapsed .submenu-item {
            display: none;
        }
        
        .logo-section {
            padding: 25px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            position: relative;
        }
        
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 22px;
            font-weight: 700;
            color: var(--white);
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-green));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .logo-text {
            font-size: 18px;
        }
        
        .logo span {
            color: var(--green-light);
        }
        
        .system-name {
            font-size: 12px;
            color: #94a3b8;
            font-weight: 500;
            margin-top: 5px;
            letter-spacing: 0.5px;
        }
        
        .menu {
            padding: 20px 0;
        }
        
        .menu-item {
            padding: 14px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #cbd5e1;
            font-weight: 500;
            font-size: 14px;
        }
        
        .menu-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--white);
            border-left-color: var(--primary-blue);
        }
        
        .menu-item.active {
            background: rgba(42, 130, 245, 0.1);
            color: var(--white);
            border-left-color: var(--primary-green);
        }
        
        .menu-item i {
            width: 20px;
            font-size: 16px;
            text-align: center;
        }
        
        /* No arrow for Members - removed chevron styles */
        
        .submenu {
            background: rgba(0, 0, 0, 0.2);
            display: none;
            border-left: 2px solid var(--primary-blue);
            margin-left: 10px;
        }
        
        .submenu.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .submenu-item {
            padding: 10px 15px 10px 45px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 13px;
            color: #94a3b8;
        }
        
        .submenu-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--green-light);
            padding-left: 50px;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
        }
        
        .main-content.expanded {
            margin-left: 70px;
        }
        
        /* Header Styles */
        .header {
            background: linear-gradient(135deg, var(--white) 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 25px 35px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-blue), var(--primary-green));
        }
        
        .party-name {
            text-align: left;
        }
        
        .party-name h1 {
            background: linear-gradient(90deg, var(--primary-blue), var(--primary-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 30px;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .party-name h2 {
            color: var(--gray-700);
            font-size: 16px;
            font-weight: 600;
            font-style: italic;
        }
        
        .header-info {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }
        
        .date-time {
            background: linear-gradient(135deg, var(--primary-blue), var(--blue-dark));
            padding: 15px 20px;
            border-radius: 12px;
            color: white;
            text-align: center;
            min-width: 180px;
            box-shadow: 0 5px 15px rgba(42, 130, 245, 0.3);
        }
        
        .date-time .date {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .date-time .time {
            font-size: 24px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        
        .user-welcome {
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--white);
            padding: 12px 20px;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            min-width: 250px;
            transition: all 0.3s ease;
        }
        
        .user-welcome:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-green));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 700;
            font-size: 20px;
            box-shadow: 0 5px 15px rgba(42, 130, 245, 0.3);
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-role {
            font-size: 12px;
            color: var(--gray-700);
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .user-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: var(--white);
            border: none;
            padding: 14px 25px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.4);
        }
        
        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--white) 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--hover-shadow);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-blue), var(--primary-green));
        }
        
        .stat-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 48px;
            opacity: 0.1;
            color: var(--primary-blue);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1);
            opacity: 0.15;
        }
        
        .stat-number {
            font-size: 42px;
            font-weight: 800;
            background: linear-gradient(90deg, var(--primary-blue), var(--primary-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            line-height: 1;
        }
        
        .stat-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
        }
        
        .stat-subtitle {
            font-size: 14px;
            color: var(--gray-700);
            font-weight: 500;
            line-height: 1.4;
        }
        
        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .chart-card {
            background: linear-gradient(135deg, var(--white) 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }
        
        .chart-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .chart-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .chart-title i {
            color: var(--primary-blue);
            font-size: 22px;
        }
        
        .chart-container {
            height: 320px;
            position: relative;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 25px;
            background: linear-gradient(135deg, var(--white) 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--gray-200);
        }
        
        .footer p {
            margin: 8px 0;
            color: var(--gray-700);
            font-size: 14px;
        }
        
        .footer a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .footer a:hover {
            color: var(--primary-green);
            text-decoration: underline;
        }
        
        /* Toggle Button */
        .sidebar-toggle {
            position: fixed;
            top: 25px;
            left: 20px;
            z-index: 1002;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-green));
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(42, 130, 245, 0.4);
            transition: all 0.3s ease;
        }
        
        .sidebar-toggle:hover {
            transform: rotate(180deg);
            box-shadow: 0 8px 20px rgba(42, 130, 245, 0.6);
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
                padding: 20px;
            }
            
            .party-name {
                text-align: center;
            }
            
            .header-info {
                justify-content: center;
                gap: 15px;
            }
        }
        
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
            }
            
            .sidebar-toggle {
                display: flex;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header-info {
                flex-direction: column;
                align-items: stretch;
            }
            
            .date-time, .user-welcome, .logout-btn {
                width: 100%;
            }
            
            .main-content {
                padding: 15px;
            }
            
            .party-name h1 {
                font-size: 24px;
            }
            
            .party-name h2 {
                font-size: 14px;
            }
            
            .chart-card {
                padding: 20px;
            }
            
            .stat-number {
                font-size: 36px;
            }
        }
        
        @media (max-width: 480px) {
            .stat-card {
                padding: 20px;
            }
            
            .stat-number {
                font-size: 32px;
            }
            
            .footer {
                padding: 20px;
            }
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            opacity: 0;
        }
        
        .fade-in-up:nth-child(1) { animation-delay: 0.1s; }
        .fade-in-up:nth-child(2) { animation-delay: 0.2s; }
        .fade-in-up:nth-child(3) { animation-delay: 0.3s; }
        .fade-in-up:nth-child(4) { animation-delay: 0.4s; }
        .fade-in-up:nth-child(5) { animation-delay: 0.5s; }
        .fade-in-up:nth-child(6) { animation-delay: 0.6s; }
        .fade-in-up:nth-child(7) { animation-delay: 0.7s; }
        .fade-in-up:nth-child(8) { animation-delay: 0.8s; }
    </style>
</head>
<body>
    <!-- Sidebar Toggle Button -->
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-section">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="logo-text">Party <span>Registration</span></div>
            </div>
            <div class="system-name">Management System</div>
        </div>
        
        <div class="menu">
            <!-- Dashboard -->
            <div class="menu-item active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </div>

            <!-- Members Menu -->
            <div class="menu-item" onclick="toggleSubmenu('members')">
                <i class="fas fa-users"></i>
                <span>Members</span>
            </div>
            <div class="submenu" id="members">
                <div class="submenu-item">Register New Member</div>
                <div class="submenu-item">Member List</div>
                <div class="submenu-item">Manage Members</div>
                <div class="submenu-item">Certificates</div>
            </div>

            <!-- Campaigner Menu -->
            <div class="menu-item" onclick="toggleSubmenu('campaigner')">
                <i class="fas fa-megaphone"></i>
                <span>Campaigner</span>
            </div>
            <div class="submenu" id="campaigner">
                <div class="submenu-item">Register New Campaigner</div>
                <div class="submenu-item">Campaigner List</div>
                <div class="submenu-item">Manage Campaigners</div>
                <div class="submenu-item">Campaigner Report</div>
            </div>

            <!-- Communication Menu -->
            <div class="menu-item" onclick="toggleSubmenu('communication')">
                <i class="fas fa-comments"></i>
                <span>Communication</span>
            </div>
            <div class="submenu" id="communication">
                <div class="submenu-item">Send Bulk SMS</div>
                <div class="submenu-item">Send Bulk Email</div>
                <div class="submenu-item">Send Voice Ads</div>
            </div>

            <!-- Roles Management -->
            <div class="menu-item" onclick="toggleSubmenu('roles')">
                <i class="fas fa-user-shield"></i>
                <span>Roles Management</span>
            </div>
            <div class="submenu" id="roles">
                <div class="submenu-item">Roles List</div>
                <div class="submenu-item">Manage Roles</div>
            </div>

            <!-- User Management -->
            <div class="menu-item" onclick="toggleSubmenu('users')">
                <i class="fas fa-user-cog"></i>
                <span>User Management</span>
            </div>
            <div class="submenu" id="users">
                <div class="submenu-item">Register New User</div>
                <div class="submenu-item">Users Lists</div>
                <div class="submenu-item">Manage Users</div>
                <div class="submenu-item">Activity Logs</div>
            </div>

            <!-- Reports -->
            <div class="menu-item" onclick="toggleSubmenu('reports')">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </div>
            <div class="submenu" id="reports">
                <div class="submenu-item">Global Report</div>
                <div class="submenu-item">Statistical Report</div>
                <div class="submenu-item">Regional Reports</div>
            </div>

            <!-- Settings -->
            <div class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </div>

            <!-- Logout -->
            <div class="menu-item" onclick="logout()">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <div class="header">
            <div class="party-name">
                <h1>PARTY REGISTRATION SYSTEM</h1>
                <h2>Nidaamka Diiwaangelinta Xisbiyadda</h2>
            </div>
            <div class="header-info">
                <div class="date-time">
                    <div class="date"><?php echo $current_date; ?></div>
                    <div class="time" id="currentTime"><?php echo $current_time; ?></div>
                </div>
                <div class="user-welcome">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-role"><?php echo htmlspecialchars($role_data['role_name']); ?></div>
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    </div>
                </div>
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Logout System
                </button>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card fade-in-up">
                <i class="fas fa-users stat-icon"></i>
                <div class="stat-number"><?php echo number_format($stats['total_members']); ?></div>
                <div class="stat-title">Total Members</div>
                <div class="stat-subtitle">Tirada Xubnaha</div>
            </div>
            
            <div class="stat-card fade-in-up">
                <i class="fas fa-user-plus stat-icon"></i>
                <div class="stat-number"><?php echo number_format($stats['today_registered']); ?></div>
                <div class="stat-title">Today's Registered</div>
                <div class="stat-subtitle">Xubnaha la diiwaan geliyay Maanta</div>
            </div>
            
            <div class="stat-card fade-in-up">
                <i class="fas fa-calendar-day stat-icon"></i>
                <div class="stat-number"><?php echo number_format($stats['yesterday_registered']); ?></div>
                <div class="stat-title">Yesterday's Registered</div>
                <div class="stat-subtitle">Xubnaha la diiwaan geliyay Shalay</div>
            </div>
            
            <div class="stat-card fade-in-up">
                <i class="fas fa-calendar-week stat-icon"></i>
                <div class="stat-number"><?php echo number_format($stats['week_registered']); ?></div>
                <div class="stat-title">This Week's Registration</div>
                <div class="stat-subtitle">Xubnaha Asbuucaan la diiwaan geliyay</div>
            </div>
            
            <div class="stat-card fade-in-up">
                <i class="fas fa-user-check stat-icon"></i>
                <div class="stat-number"><?php echo number_format($stats['active_members']); ?></div>
                <div class="stat-title">Active Members</div>
                <div class="stat-subtitle">Xubnaha Active ka ah</div>
            </div>
            
            <div class="stat-card fade-in-up">
                <i class="fas fa-user-times stat-icon"></i>
                <div class="stat-number"><?php echo number_format($stats['inactive_members']); ?></div>
                <div class="stat-title">InActive Members</div>
                <div class="stat-subtitle">Xubnaha Ka Baxay</div>
            </div>
            
            <div class="stat-card fade-in-up">
                <i class="fas fa-megaphone stat-icon"></i>
                <div class="stat-number"><?php echo number_format($stats['total_campaigners']); ?></div>
                <div class="stat-title">Total Campaigners</div>
                <div class="stat-subtitle">Tirada Guud ee Ololoyaasha</div>
            </div>
            
            <div class="stat-card fade-in-up">
                <i class="fas fa-map-marked-alt stat-icon"></i>
                <div class="stat-number"><?php echo number_format($stats['states_represented']); ?></div>
                <div class="stat-title">States Represented</div>
                <div class="stat-subtitle">Gobolada iyo Degmooyinka</div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-chart-line"></i> Registration Trends
                </div>
                <div class="chart-container">
                    <canvas id="registrationChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-chart-pie"></i> Member Distribution
                </div>
                <div class="chart-container">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; 2025 Party Members Registration System. All rights reserved.</p>
            <p>Developed by <a href="https://www.daryeelict.com/" target="_blank">Daryeel ICT Solutions</a></p>
            <p>Version 2.0 | Secure Party Management Platform</p>
        </div>
    </div>

    <script>
        // Sidebar toggle functionality
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');
        
        // Toggle sidebar collapse/expand
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Change icon based on state
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.className = 'fas fa-chevron-right';
            } else {
                icon.className = 'fas fa-bars';
            }
        });

        // Mobile sidebar toggle
        function toggleMobileSidebar() {
            sidebar.classList.toggle('active');
        }

        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Submenu toggle - removed arrow functionality
        function toggleSubmenu(menuId) {
            const submenu = document.getElementById(menuId);
            submenu.classList.toggle('show');
        }

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout from the system?')) {
                window.location.href = 'logout.php';
            }
        }

        // Update time every second
        function updateTime() {
            const now = new Date();
            const time = now.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                second: '2-digit',
                hour12: true 
            });
            document.getElementById('currentTime').textContent = time;
        }
        setInterval(updateTime, 1000);

        // Charts with enhanced styling
        document.addEventListener('DOMContentLoaded', function() {
            // Registration Trends Chart
            const regCtx = document.getElementById('registrationChart').getContext('2d');
            new Chart(regCtx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'New Registrations',
                        data: [12, 19, 8, 15, 12, 10, 7],
                        borderColor: '#2a82f5',
                        backgroundColor: 'rgba(42, 130, 245, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3,
                        pointBackgroundColor: '#049133',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            padding: 12,
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(226, 232, 240, 0.5)'
                            },
                            ticks: {
                                color: '#64748b'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(226, 232, 240, 0.5)'
                            },
                            ticks: {
                                color: '#64748b'
                            }
                        }
                    }
                }
            });

            // Distribution Chart
            const distCtx = document.getElementById('distributionChart').getContext('2d');
            new Chart(distCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Active Members', 'Inactive Members', 'New Today', 'This Week'],
                    datasets: [{
                        data: [
                            <?php echo $stats['active_members']; ?>,
                            <?php echo $stats['inactive_members']; ?>,
                            <?php echo $stats['today_registered']; ?>,
                            <?php echo $stats['week_registered']; ?>
                        ],
                        backgroundColor: ['#049133', '#ef4444', '#2a82f5', '#8b5cf6'],
                        borderColor: '#ffffff',
                        borderWidth: 3,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: '#334155',
                                padding: 20,
                                font: {
                                    size: 13
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            padding: 12,
                            cornerRadius: 8
                        }
                    },
                    cutout: '65%'
                }
            });
        });

        // Add scroll animation to stat cards
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, {
            threshold: 0.1
        });

        document.querySelectorAll('.fade-in-up').forEach(card => {
            observer.observe(card);
        });

        // Handle window resize for mobile sidebar
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>