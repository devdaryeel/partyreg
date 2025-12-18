<?php
session_start();
// Set session timeout to 5 minutes
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 300)) {
    // Last request was more than 5 minutes ago
    session_unset();     // Unset $_SESSION variable
    session_destroy();   // Destroy session data
}
$_SESSION['LAST_ACTIVITY'] = time(); // Update last activity time
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Party Members Registration System - Daryeel ICT Solutions</title>
    <link rel="icon" href="icontest.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Styles */
        :root {
            --primary-color: #2a82f5;
            --secondary-color: #049133;
            --white-color: #ffffff;
            --dark-color: #333333;
            --light-gray: #f5f5f5;
            --medium-gray: #e0e0e0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--white-color);
            color: var(--dark-color);
            line-height: 1.6;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Styles */
        header {
            background-color: var(--white-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo h1 {
            color: var(--primary-color);
            font-size: 24px;
            font-weight: 700;
            cursor: pointer;
        }
        
        .logo h1 a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .logo h1 a:hover {
            color: var(--primary-color);
        }
        
        .logo span {
            color: var(--secondary-color);
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 25px;
        }
        
        nav ul li a {
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 600;
            font-size: 16px;
            transition: color 0.3s;
        }
        
        nav ul li a:hover {
            color: var(--primary-color);
        }
        
        .login-btn {
            background-color: var(--primary-color);
            color: var(--white-color);
            padding: 8px 20px;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .login-btn:hover {
            background-color: #1a6fd5;
            color: var(--white-color);
        }
        
        .mobile-menu {
            display: none;
            font-size: 24px;
            cursor: pointer;
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--white-color);
            padding: 80px 0;
            text-align: center;
        }
        
        .hero h2 {
            font-size: 36px;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .hero p {
            font-size: 18px;
            max-width: 800px;
            margin: 0 auto 30px;
        }
        
        .cta-button {
            display: inline-block;
            background-color: var(--white-color);
            color: var(--primary-color);
            padding: 12px 30px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .cta-button:hover {
            background-color: var(--light-gray);
            transform: translateY(-3px);
        }
        
        /* Features Section */
        .features {
            padding: 80px 0;
            background-color: var(--light-gray);
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .section-title h2 {
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .section-title p {
            color: var(--dark-color);
            max-width: 700px;
            margin: 0 auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .feature-card {
            background-color: var(--white-color);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
        }
        
        .feature-image {
            height: 200px;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-color);
            font-size: 60px;
            padding: 20px;
        }
        
        .feature-content {
            padding: 20px;
        }
        
        .feature-content h3 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        /* System Overview */
        .overview {
            padding: 60px 0;
        }
        
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
        }
        
        .overview-item {
            text-align: center;
            padding: 30px 20px;
            border-radius: 8px;
            background-color: var(--white-color);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        
        .overview-item:hover {
            background-color: var(--primary-color);
            color: var(--white-color);
        }
        
        .overview-item:hover h3 {
            color: var(--white-color);
        }
        
        .overview-item h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        /* Footer */
        footer {
            background-color: var(--dark-color);
            color: var(--white-color);
            padding: 50px 0 20px;
            margin-top: 60px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .footer-section h3 {
            color: var(--white-color);
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 18px;
        }
        
        .footer-section p, .footer-section a {
            color: var(--medium-gray);
            margin-bottom: 10px;
            display: block;
            text-decoration: none;
        }
        
        .footer-section a:hover {
            color: var(--white-color);
        }
        
        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--medium-gray);
            font-size: 14px;
        }
        
        .copyright a {
            color: var(--white-color);
            text-decoration: none;
        }
        
        .copyright a:hover {
            text-decoration: underline;
        }
        
        /* Feature Icons */
        .feature-icon {
            font-size: 60px;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .feature-card:hover .feature-icon {
            color: var(--secondary-color);
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                text-align: center;
            }
            
            .logo {
                margin-bottom: 15px;
            }
            
            nav ul {
                flex-direction: column;
                width: 100%;
            }
            
            nav ul li {
                margin: 10px 0;
            }
            
            .mobile-menu {
                display: block;
                position: absolute;
                top: 20px;
                right: 20px;
            }
            
            .hero h2 {
                font-size: 28px;
            }
            
            .hero p {
                font-size: 16px;
            }
            
            .feature-image {
                font-size: 48px;
                height: 160px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-container">
                <div class="logo">
                    <h1><a href="https://partyregsys.daryeelict.com" title="Go to Party Members Registration System">Party <span>Members</span> Registration System</a></h1>
                </div>
                <div class="mobile-menu">☰</div>
                <nav>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="about.php">About</a></li>
                        <li><a href="features.php">Features</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="login.php" class="login-btn">Login</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h2>Advanced Party Membership Management System</h2>
            <p>Streamline member registration, management, and communication with an advanced Party Members Registration System.  Developed by Daryeel ICT Solutions to empower Somali Political organizations with modern digital software tools.</p>
            <a href="https://www.youtube.com/@daryeelict" class="cta-button">Watch Promo Video </a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <div class="section-title">
                <h2>System Features</h2>
                <p>Our comprehensive platform offers all the tools needed for efficient party membership management</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-image">
                        <i class="fas fa-users feature-icon"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Members Management</h3>
                        <p>Complete management of party members with active/inactive status tracking, profile management, and membership lifecycle control.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-image">
                        <i class="fas fa-user-plus feature-icon"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Registration System</h3>
                        <p>Streamlined registration process with direct member registration using phone number, ID verification, and automated onboarding.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-image">
                        <i class="fas fa-file-upload feature-icon"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Bulk Members Upload</h3>
                        <p>Import large numbers of members quickly using Excel or CSV files. Batch processing with validation and error reporting.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-image">
                        <i class="fas fa-chart-bar feature-icon"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Data Visualization & Analysis</h3>
                        <p>Comprehensive analytics and visualization of membership data including state-wise, district-wise breakdowns and demographic analysis.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-image">
                        <i class="fas fa-bullhorn feature-icon"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Campaigner Management</h3>
                        <p>Specialized tools for managing campaign teams, volunteer coordination, and field operations tracking.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-image">
                        <i class="fas fa-map-marked-alt feature-icon"></i>
                    </div>
                    <div class="feature-content">
                        <h3>States & Regions Management</h3>
                        <p>Hierarchical organization structure supporting federal states, regions, and districts with localized administration.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-image">
                        <i class="fas fa-award feature-icon"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Special Memberships</h3>
                        <p>Support for different membership categories including lifetime members, honorary members, and special interest groups.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-image">
                        <i class="fas fa-certificate feature-icon"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Membership Certifications</h3>
                        <p>Automated generation and management of membership certificates with verification capabilities.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-image">
                        <i class="fas fa-comments feature-icon"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Communication System</h3>
                        <p>Integrated communication tools for sending emails, SMS, and voice calls to party members with targeted messaging.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-image">
                        <i class="fas fa-file-alt feature-icon"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Advanced Reporting</h3>
                        <p>Comprehensive reporting system with customizable reports, export capabilities, and real-time dashboard.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-image">
                        <i class="fas fa-user-shield feature-icon"></i>
                    </div>
                    <div class="feature-content">
                        <h3>User Management & Security</h3>
                        <p>Role-based access control, multi-level user permissions, and advanced security features to protect sensitive data.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-image">
                        <i class="fas fa-cloud feature-icon"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Cloud Based System</h3>
                        <p>Access your data securely from anywhere with our cloud-hosted platform. No installation required, automatic updates, and scalable infrastructure.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-image">
                        <i class="fas fa-headset feature-icon"></i>
                    </div>
                    <div class="feature-content">
                        <h3>24/7 Chat Support</h3>
                        <p>Round-the-clock technical support via live chat. Get instant assistance from our support team whenever you need help.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-image">
                        <i class="fas fa-graduation-cap feature-icon"></i>
                    </div>
                    <div class="feature-content">
                        <h3>End User Training</h3>
                        <p>Comprehensive training programs for system administrators and end-users including video tutorials, documentation, and live training sessions.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-image">
                        <i class="fas fa-database feature-icon"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Data Backup & Recovery</h3>
                        <p>Automated daily backups and easy data recovery options to ensure your important membership data is always safe and secure.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- System Overview -->
    <section class="overview">
        <div class="container">
            <div class="section-title">
                <h2>System Capabilities</h2>
                <p>Our platform provides comprehensive tools for modern political organization management</p>
            </div>
            <div class="overview-grid">
                <div class="overview-item">
                    <h3>Direct Member Registration</h3>
                    <p>Simple registration using only phone number with automated verification</p>
                </div>
                <div class="overview-item">
                    <h3>Active/Inactive Tracking</h3>
                    <p>Monitor member engagement and participation status</p>
                </div>
                <div class="overview-item">
                    <h3>Geographic Analysis</h3>
                    <p>State-wise and district-wise membership visualization</p>
                </div>
                <div class="overview-item">
                    <h3>Multi-channel Communication</h3>
                    <p>Email, SMS, and voice call integration for member outreach</p>
                </div>
                <!--<div class="overview-item">
                    <h3>Bulk Data Import</h3>
                    <p>Upload thousands of members quickly using Excel or CSV files</p>
                </div>
                <div class="overview-item">
                    <h3>Automated Backups</h3>
                    <p>Daily automated backups to ensure data safety and recovery</p>
                </div>-->
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Daryeel ICT Solutions</h3>
                    <p>Providing innovative software solutions for modern organizational needs.</p>
                </div>
                <div class="footer-section">
                    <h3>Contact Us on</h3>
                    <p>Email: sales@daryeelict.com</p>
                    <p>Phone: +252 61 228 1968</p>
                    <p>Website: www.daryeelict.com</p>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <a href="index.php">Home</a>
                    <a href="features.php">Features</a>
                    <a href="about.php">About</a>
                    <a href="contact.php">Contact</a>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2025 Daryeel ICT Solutions. All rights reserved. | Party Members Registration System v1.0 | <a href="https://www.daryeelict.com" target="_blank">www.daryeelict.com</a></p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu').addEventListener('click', function() {
            document.querySelector('nav ul').classList.toggle('active');
        });
    </script>
</body>
</html>