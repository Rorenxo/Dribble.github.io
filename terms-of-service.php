<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - Dribble</title>
    <link rel="icon" href="asset/imoticon.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Terms of Service Page Styles */
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
            <h1>Terms of Service</h1>
            <p>Last updated: <?php echo date('F d, Y'); ?></p>
        </div>
        
        <div class="content-section">
            <h2>1. Acceptance of Terms</h2>
            <p>Welcome to Dribble. By accessing or using our court booking service, you agree to be bound by these Terms of Service ("Terms"). If you do not agree to these Terms, please do not use our service.</p>
            <p>These Terms constitute a legally binding agreement between you and Dribble regarding your use of our service. Please read them carefully.</p>
        </div>
        
        <div class="content-section">
            <h2>2. Description of Service</h2>
            <p>Dribble provides a platform for booking basketball courts and managing court reservations. Our service allows users to:</p>
            <ul>
                <li>Create and manage user accounts</li>
                <li>List and manage basketball courts</li>
                <li>Make and manage court reservations</li>
                <li>Process payments for court bookings</li>
                <li>Communicate with court owners and other users</li>
            </ul>
        </div>
        
        <div class="content-section">
            <h2>3. User Accounts</h2>
            <p><strong>Registration:</strong> To use certain features of our service, you must register for an account. You agree to provide accurate, current, and complete information during the registration process and to update such information to keep it accurate, current, and complete.</p>
            <p><strong>Account Security:</strong> You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You agree to notify us immediately of any unauthorized use of your account.</p>
            <p><strong>Account Termination:</strong> We reserve the right to suspend or terminate your account at our sole discretion, without notice, for conduct that we determine violates these Terms or is harmful to other users, us, or third parties, or for any other reason.</p>
        </div>
        
        <div class="content-section">
            <h2>4. Court Bookings</h2>
            <p><strong>Booking Process:</strong> Users may book courts through our platform based on availability. All bookings are subject to confirmation and acceptance by the court owner.</p>
            <p><strong>Cancellation Policy:</strong> Cancellation policies may vary by court. Users are responsible for reviewing the specific cancellation policy for each court before making a booking.</p>
            <p><strong>Fees and Payments:</strong> Users agree to pay all fees and charges associated with their bookings. All payments are processed securely through our platform.</p>
        </div>
        
        <div class="content-section">
            <h2>5. User Conduct</h2>
            <p>You agree not to use our service to:</p>
            <ul>
                <li>Violate any applicable laws or regulations</li>
                <li>Infringe on the rights of others</li>
                <li>Post or transmit unauthorized commercial communications</li>
                <li>Upload viruses or other malicious code</li>
                <li>Attempt to access accounts or systems without authorization</li>
                <li>Interfere with or disrupt the service or servers</li>
                <li>Engage in fraudulent or deceptive practices</li>
            </ul>
        </div>
        
        <div class="content-section">
            <h2>6. Intellectual Property</h2>
            <p>Our service and its original content, features, and functionality are owned by Dribble and are protected by international copyright, trademark, patent, trade secret, and other intellectual property laws.</p>
            <p>You may not copy, modify, create derivative works from, publicly display, publicly perform, republish, download, store, or transmit any of the material on our service without our prior written consent.</p>
        </div>
        
        <div class="content-section">
            <h2>7. Limitation of Liability</h2>
            <p>To the maximum extent permitted by law, Dribble shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including but not limited to, loss of profits, data, use, goodwill, or other intangible losses, resulting from:</p>
            <ul>
                <li>Your access to or use of or inability to access or use the service</li>
                <li>Any conduct or content of any third party on the service</li>
                <li>Unauthorized access, use, or alteration of your transmissions or content</li>
                <li>Any other matter relating to the service</li>
            </ul>
        </div>
        
        <div class="content-section">
            <h2>8. Changes to Terms</h2>
            <p>We reserve the right to modify or replace these Terms at any time. If a revision is material, we will provide at least 30 days' notice prior to any new terms taking effect. What constitutes a material change will be determined at our sole discretion.</p>
            <p>By continuing to access or use our service after any revisions become effective, you agree to be bound by the revised terms. If you do not agree to the new terms, you are no longer authorized to use the service.</p>
        </div>
        
        <div class="content-section">
            <h2>9. Governing Law</h2>
            <p>These Terms shall be governed and construed in accordance with the laws of the Philippines, without regard to its conflict of law provisions.</p>
            <p>Our failure to enforce any right or provision of these Terms will not be considered a waiver of those rights. If any provision of these Terms is held to be invalid or unenforceable by a court, the remaining provisions of these Terms will remain in effect.</p>
        </div>
        
        <div class="content-section">
            <h2>10. Contact Us</h2>
            <p>If you have any questions about these Terms, please contact us at:</p>
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