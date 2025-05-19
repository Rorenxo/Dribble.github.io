<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Dribble</title>
    <link rel="icon" href="asset/imoticon.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Privacy Policy Page Styles */
        :root {
            --primary: #68181f;
            --primary-light: #8c2a33;
            --primary-dark: #4d1218;
            --secondary: #6c757d;
            --dark: #343a40;
            --light: #f8f9fa;
            --border-color: #dee2e6;
            --body-bg: #f5f5f5;
            --card-bg: #ffffff;
            --radius: 5px;
            --radius-sm: 3px;
            --radius-md: 8px;
            --radius-lg: 15px;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--body-bg);
            color: var(--dark);
            line-height: 1.6;
        }

        /* Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            background-color: var(--dark);
            box-shadow: var(--shadow-md);
        }

        .logo img {
            height: 40px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-links a {
            color: var(--light);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--primary-light);
        }

        .login-btn, .register-btn {
            padding: 8px 16px;
            border-radius: var(--radius);
            font-weight: 500;
        }

        .login-btn {
            border: 1px solid var(--light);
        }

        .register-btn {
            background-color: var(--primary-light);
            color: white;
        }

        .menu-toggle {
            display: none;
            color: var(--light);
            font-size: 24px;
            cursor: pointer;
        }

        /* Content */
        .content-wrapper {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background-color: var(--card-bg);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
        }

        .content-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .content-header h1 {
            color: var(--primary);
            font-size: 28px;
            margin-bottom: 10px;
        }

        .content-header p {
            color: var(--secondary);
            font-size: 16px;
        }

        .content-section {
            margin-bottom: 30px;
        }

        .content-section h2 {
            color: var(--primary);
            font-size: 22px;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        .content-section p {
            margin-bottom: 15px;
        }

        .content-section ul {
            margin-left: 20px;
            margin-bottom: 15px;
        }

        .content-section li {
            margin-bottom: 8px;
        }

        /* Footer */
        .footer {
            background-color: var(--dark);
            color: var(--light);
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }

        .footer-links {
            margin-bottom: 10px;
        }

        .footer-links a {
            color: var(--light);
            text-decoration: none;
            margin: 0 10px;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary-light);
            text-decoration: underline;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }

            .nav-links {
                position: fixed;
                top: 70px;
                left: 0;
                right: 0;
                background-color: var(--dark);
                flex-direction: column;
                padding: 20px;
                clip-path: circle(0px at top right);
                transition: clip-path 0.5s ease;
                z-index: 100;
            }

            .nav-links.active {
                clip-path: circle(1000px at top right);
            }

            .content-wrapper {
                margin: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo">
            <a href="index.php"><img src="asset/drib.png" alt="Dribble"></a>
        </div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="#features">Features</a>
            <a href="#how-it-works">How It Works</a>
            <a href="login.php" class="login-btn">Sign In</a>
            <a href="registration.php" class="register-btn">Join Now</a>
        </div>
        <div class="menu-toggle">
            <i class="fas fa-bars"></i>
        </div>
    </nav>
    
    <div class="content-wrapper">
        <div class="content-header">
            <h1>Privacy Policy</h1>
            <p>Last updated: <?php echo date('F d, Y'); ?></p>
        </div>
        
        <div class="content-section">
            <h2>1. Introduction</h2>
            <p>Welcome to Dribble ("we," "our," or "us"). We are committed to protecting your privacy and personal information. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our court booking service.</p>
            <p>Please read this Privacy Policy carefully. By accessing or using our service, you acknowledge that you have read, understood, and agree to be bound by all the terms of this Privacy Policy.</p>
        </div>
        
        <div class="content-section">
            <h2>2. Information We Collect</h2>
            <p>We may collect the following types of information:</p>
            <ul>
                <li><strong>Personal Information:</strong> Name, email address, phone number, physical address, and other information you provide during registration.</li>
                <li><strong>Account Information:</strong> Login credentials, account settings, and preferences.</li>
                <li><strong>Court Booking Information:</strong> Details about court bookings, including dates, times, and locations.</li>
                <li><strong>Payment Information:</strong> Payment method details, transaction history, and billing information.</li>
                <li><strong>Usage Information:</strong> Information about how you use our service, including log data, device information, and IP address.</li>
            </ul>
        </div>
        
        <div class="content-section">
            <h2>3. How We Use Your Information</h2>
            <p>We may use the information we collect for various purposes, including:</p>
            <ul>
                <li>Providing and maintaining our service</li>
                <li>Processing court bookings and payments</li>
                <li>Sending notifications about bookings, updates, and promotions</li>
                <li>Improving our service and user experience</li>
                <li>Responding to your inquiries and providing customer support</li>
                <li>Preventing fraud and ensuring security</li>
                <li>Complying with legal obligations</li>
            </ul>
        </div>
        
        <div class="content-section">
            <h2>4. Data Storage and Security</h2>
            <p>We use Firebase, a platform developed by Google, to store and process your data. Firebase provides secure infrastructure for storing user authentication information and other data related to our service.</p>
            <p>We implement appropriate security measures to protect your personal information from unauthorized access, alteration, disclosure, or destruction. However, no method of transmission over the Internet or electronic storage is 100% secure, and we cannot guarantee absolute security.</p>
        </div>
        
        <div class="content-section">
            <h2>5. Sharing Your Information</h2>
            <p>We may share your information in the following circumstances:</p>
            <ul>
                <li>With service providers who help us operate our business</li>
                <li>With court owners or managers for booking purposes</li>
                <li>To comply with legal obligations</li>
                <li>In connection with a business transfer or merger</li>
                <li>With your consent or at your direction</li>
            </ul>
            <p>We do not sell your personal information to third parties.</p>
        </div>
        
        <div class="content-section">
            <h2>6. Your Rights</h2>
            <p>Depending on your location, you may have certain rights regarding your personal information, including:</p>
            <ul>
                <li>Accessing, correcting, or deleting your personal information</li>
                <li>Withdrawing consent for processing your information</li>
                <li>Requesting restriction of processing of your information</li>
                <li>Data portability</li>
                <li>Objecting to processing of your information</li>
            </ul>
            <p>To exercise these rights, please contact us using the information provided in the "Contact Us" section.</p>
        </div>
        
        <div class="content-section">
            <h2>7. Changes to This Privacy Policy</h2>
            <p>We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last updated" date at the top of this Privacy Policy.</p>
            <p>You are advised to review this Privacy Policy periodically for any changes. Changes to this Privacy Policy are effective when they are posted on this page.</p>
        </div>
        
        <div class="content-section">
            <h2>8. Contact Us</h2>
            <p>If you have any questions or concerns about this Privacy Policy, please contact us at:</p>
            <p>Email: support@dribble.com</p>
            <p>Phone: (123) 456-7890</p>
            <p>Address: 123 Basketball Court, Sports City, SC 12345</p>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-links">
            <a href="privacy-policy.php">Privacy Policy</a>
            <a href="terms-of-service.php">Terms of Service</a>
            <a href="contact.php">Contact Us</a>
        </div>
        <p>&copy; <?php echo date('Y'); ?> Dribble. All rights reserved.</p>
    </footer>

    <script>
        // Mobile menu toggle
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.nav-links').classList.toggle('active');
        });
    </script>
</body>
</html>