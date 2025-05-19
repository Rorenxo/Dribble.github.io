
<?php
session_start();
include("connection.php");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dribble - Landing page</title>
    <link rel="icon" href="asset/imoticon.png" type="image/png">
    <link rel="stylesheet" href="asset/landing.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <!-- Hero Section -->
    <section class="hero">
        <div class="navbar">
            <div class="logo">
                <img src="asset/drib.png" alt="KORTambayan Logo">
            </div>
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
                <a href="login.php" class="login-btn">Sign In</a>
                <a href="registration.php" class="register-btn">Register</a>
            </div>
            <div class="menu-toggle">
                <i class="fas fa-bars"></i>
            </div>
        </div>
        
        <div class="hero-content">
            <div class="hero-text">
                <h1>Manage Your Basketball Courts with Ease</h1>
                <p>Dribble is the ultimate solution for basketball court owners to manage bookings, track revenue, and grow their business.</p>
                <div class="hero-buttons">
                    <a href="registration.php" class="primary-btn">Get Started</a>
                    <a href="#features" class="secondary-btn">Learn More</a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section id="features" class="features">
        <div class="section-header">
            <h2>Features</h2>
            <p>Everything you need to manage your basketball courts efficiently</p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3>Easy Scheduling Management</h3>
                <p>Manage all your court bookings in one place with an intuitive calendar interface.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h3>Mobile Friendly</h3>
                <p>Access your dashboard from any device - desktop, tablet, or smartphone.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-piggy-bank"></i>
                </div>
                <h3>Savings Tracker</h3>
                <p>Use the built-in piggy bank feature to save for court improvements or expenses.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Customer Management</h3>
                <p>Keep track of your regular customers and their booking history.</p>
            </div>
            
        </div>
    </section>

        <!-- CTA Section -->
    <section class="cta">
        <div class="cta-content">
            <h2>Ready to Transform Your Court Management?</h2>
            <p>Join thousands of court owners who have simplified their booking process and increased their revenue with Dribble.</p>
            <a href="registration.php" class="primary-btn">Get Started Now</a>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="how-it-works">
        <div class="section-header">
            <h2>How It Works</h2>
            <p>Get started with Dribble in just a few simple steps</p>
        </div>
        
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <h3>Create an Account</h3>
                <p>Sign up for a free account and set up your profile.</p>
            </div>
            
            <div class="step">
                <div class="step-number">2</div>
                <h3>Add Your Courts</h3>
                <p>Add details about your basketball courts including photos and availability.</p>
            </div>
            
            <div class="step">
                <div class="step-number">3</div>
                <h3>Manage Bookings</h3>
                <p>Start accepting and managing bookings through your personalized dashboard.</p>
            </div>
            
            <div class="step">
                <div class="step-number">4</div>
                <h3>Track Revenue</h3>
                <p>Monitor your earnings and analyze booking patterns to grow your business.</p>
            </div>
        </div>
    </section>


    
    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-logo">
                <img src="asset/drib.png" alt="KORTambayan Logo">
                <p>The ultimate basketball court management solution with AI automation</p>
            </div>
            
            <div class="footer-links">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="registration.php">Registration</a></li>
                </ul>
            </div>
            
            <div class="footer-links">
                <h3>Legal</h3>
                <ul>
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Cookie Policy</a></li>
                </ul>
            </div>
            
            <div class="footer-social">
                <h3>Connect With Us</h3>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; 2025 Dribble. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        const menuToggle = document.querySelector('.menu-toggle');
        const navLinks = document.querySelector('.nav-links');
        
        menuToggle.addEventListener('click', () => {
            navLinks.classList.toggle('active');
        });
        
        // Testimonial slider
        const dots = document.querySelectorAll('.dot');
        const testimonials = document.querySelectorAll('.testimonial');
        let currentSlide = 0;
        
        function showSlide(index) {
            testimonials.forEach(testimonial => testimonial.style.display = 'none');
            dots.forEach(dot => dot.classList.remove('active'));
            
            testimonials[index].style.display = 'block';
            dots[index].classList.add('active');
            currentSlide = index;
        }
        
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                showSlide(index);
            });
        });
        
        // Auto slide
        setInterval(() => {
            currentSlide = (currentSlide + 1) % testimonials.length;
            showSlide(currentSlide);
        }, 5000);
        
        // Initialize
        showSlide(0);
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    window.scrollTo({
                        top: target.offsetTop - 80,
                        behavior: 'smooth'
                    });
                    
                    // Close mobile menu if open
                    navLinks.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
