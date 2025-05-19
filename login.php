<?php
session_start();
require_once 'connection.php';
require_once 'firebase.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"] ?? "";
    $password = $_POST["password"] ?? "";
    
    if (empty($email) || empty($password)) {
        $error = "Email and password are required";
    } else {
        try {
            // Sign in with email and password
            $signInResult = $auth->signInWithEmailAndPassword($email, $password);
            
            // Get the user ID
            $userId = $signInResult->data()['localId'];
            
            // Store user ID in session
            $_SESSION['user_id'] = $userId;
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit();
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "Invalid email or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dribble - Sign In</title>
    <link rel="icon" href="asset/imoticon.png" type="image/png">
    <link rel="stylesheet" href="asset/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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
    
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">SIGN IN</div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <i class="fa-solid fa-envelope input-icon"></i>
                    <input type="email" name="email" placeholder="Email Address" required>
                </div>
                
                <div class="form-group">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <i class="fa-solid fa-eye password-toggle" id="togglePassword"></i>
                </div>
                
                <button type="submit" class="signin-button">SIGN IN</button>
                
                <div class="reg">
                    <a href="registration.php">Join us. Grow your court!</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = this;
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });

        // Mobile menu toggle
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.nav-links').classList.toggle('active');
        });
    </script>
</body>
</html>
