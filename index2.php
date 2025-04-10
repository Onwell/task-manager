<?php
// Task Manager Landing Page
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager - Organize Your Work Efficiently</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-orange: #E5A624;
            --dark-orange: #D99000;
            --light-orange: #FFF0D3;
            --primary-blue: #2E5C8A;
            --dark-blue: #1A3A5F;
            --light-blue: #D1E0F0;
            --dark-text: #333333;
            --light-text: #666666;
            --white: #FFFFFF;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--light-orange);
            color: var(--dark-text);
            line-height: 1.6;
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Styles */
        header {
            background-color: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: 100%;
            z-index: 1000;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-orange);
            text-decoration: none;
        }
        
        .logo span {
            color: var(--primary-blue);
        }
        
        .nav-links {
            display: flex;
            list-style: none;
        }
        
        .nav-links li {
            margin-left: 30px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--dark-text);
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: var(--primary-orange);
        }
        
        .btn {
            display: inline-block;
            background-color: var(--primary-orange);
            color: var(--white);
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background-color: var(--dark-orange);
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary-orange);
            color: var(--primary-orange);
        }
        
        .btn-outline:hover {
            background-color: var(--primary-orange);
            color: var(--white);
        }
        
        /* Hero Section */
        .hero-section {
            padding-top: 120px;
            padding-bottom: 80px;
            background-color: var(--white);
            overflow: hidden;
        }
        
        .hero-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .hero-content {
            flex: 1;
            padding-right: 40px;
            opacity: 0;
            transform: translateX(-30px);
            animation: fadeInLeft 1s forwards 0.5s;
        }
        
        .hero-image {
            flex: 1;
            text-align: center;
            opacity: 0;
            transform: translateX(30px);
            animation: fadeInRight 1s forwards 0.5s;
        }
        
        .hero-image img {
            max-width: 100%;
            height: auto;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
            100% {
                transform: translateY(0px);
            }
        }
        
        @keyframes fadeInLeft {
            0% {
                opacity: 0;
                transform: translateX(-30px);
            }
            100% {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes fadeInRight {
            0% {
                opacity: 0;
                transform: translateX(30px);
            }
            100% {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .hero-content h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--primary-orange);
        }
        
        .hero-content h1 span {
            color: var(--dark-text);
            font-weight: 400;
            display: block;
            font-size: 24px;
        }
        
        .hero-content p {
            margin-bottom: 30px;
            color: var(--light-text);
            font-size: 18px;
        }
        
        /* Features Section */
        .features {
            padding: 80px 0;
            background-color: var(--light-orange);
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .section-title h2 {
            font-size: 36px;
            color: var(--primary-blue);
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }
        
        .section-title h2:after {
            content: '';
            display: block;
            width: 70px;
            height: 3px;
            background-color: var(--primary-orange);
            margin: 15px auto 0;
        }
        
        .section-title p {
            color: var(--light-text);
            max-width: 700px;
            margin: 0 auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .feature-card {
            background-color: var(--white);
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            opacity: 0;
            transform: translateY(20px);
        }
        
        .feature-card.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            font-size: 40px;
            color: var(--primary-orange);
            margin-bottom: 20px;
        }
        
        .feature-card h3 {
            font-size: 22px;
            margin-bottom: 15px;
            color: var(--primary-blue);
        }
        
        /* CTA Section */
        .cta {
            padding: 80px 0;
            background-color: var(--primary-blue);
            color: var(--white);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .cta:before {
            content: '';
            position: absolute;
            top: -50px;
            left: -50px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .cta:after {
            content: '';
            position: absolute;
            bottom: -80px;
            right: -80px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .cta h2 {
            font-size: 36px;
            margin-bottom: 20px;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.8s ease;
        }
        
        .cta.visible h2 {
            opacity: 1;
            transform: translateY(0);
        }
        
        .cta p {
            max-width: 700px;
            margin: 0 auto 30px;
            opacity: 0.9;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.8s ease 0.2s;
        }
        
        .cta.visible p {
            opacity: 0.9;
            transform: translateY(0);
        }
        
        .cta .btn {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.8s ease 0.4s;
        }
        
        .cta.visible .btn {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Footer */
        footer {
            background-color: var(--white);
            padding: 40px 0;
        }
        
        .footer-container {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .footer-column {
            flex: 1;
            min-width: 200px;
            margin-bottom: 30px;
        }
        
        .footer-column h3 {
            font-size: 18px;
            color: var(--primary-blue);
            margin-bottom: 20px;
            position: relative;
        }
        
        .footer-column h3:after {
            content: '';
            display: block;
            width: 30px;
            height: 2px;
            background-color: var(--primary-orange);
            margin-top: 10px;
        }
        
        .footer-column ul {
            list-style: none;
        }
        
        .footer-column li {
            margin-bottom: 10px;
        }
        
        .footer-column a {
            text-decoration: none;
            color: var(--light-text);
            transition: color 0.3s;
        }
        
        .footer-column a:hover {
            color: var(--primary-orange);
        }
        
        .social-links {
            display: flex;
            gap: 15px;
        }
        
        .social-links a {
            display: inline-block;
            width: 40px;
            height: 40px;
            background-color: var(--light-orange);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .social-links a:hover {
            background-color: var(--primary-orange);
            color: var(--white);
            transform: translateY(-3px);
        }
        
        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eee;
            margin-top: 20px;
        }
        
        /* Responsive design */
        @media (max-width: 992px) {
            .hero-container {
                flex-direction: column;
            }
            
            .hero-content {
                padding-right: 0;
                margin-bottom: 40px;
                text-align: center;
            }
            
            .nav-links {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-content h1 {
                font-size: 36px;
            }
            
            .hero-content h1 span {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="#" class="logo">TASK <span>MANAGER</span></a>
                <ul class="nav-links">
                    <li><a href="#">Home</a></li>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Services</a></li>
                    <li><a href="#">Contact Us</a></li>
                </ul>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="btn">My Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn">Get Started</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Hero Section (Replacing Slider) -->
    <section class="hero-section">
        <div class="hero-container">
            <div class="hero-content">
                <h1>TASK <span>MANAGER</span></h1>
                <p>Organize your work, boost your productivity, and achieve your goals with our powerful task management system. Whether you're working solo or collaborating with a team, our intuitive platform helps you stay on track and meet deadlines with ease.</p>
                <a href="<?php echo isset($_SESSION['user_id']) ? 'dashboard.php' : 'register.php'; ?>" class="btn">Get Started</a>
            </div>
            <div class="hero-image">
                <img src="images/4262.jpg" alt="Task Manager Illustration" onerror="this.src='https://via.placeholder.com/500x400/FFF0D3/E5A624?text=Task+Manager'">
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <div class="section-title">
                <h2>Why Choose Our Task Manager?</h2>
                <p>Our task management system helps you organize your work, collaborate with your team, and boost productivity.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3>Task Organization</h3>
                    <p>Create, categorize, and prioritize tasks to manage your workload efficiently.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h3>Reminders & Deadlines</h3>
                    <p>Set due dates and receive notifications for upcoming and overdue tasks.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Team Collaboration</h3>
                    <p>Share tasks, assign responsibilities, and work together seamlessly.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Progress Tracking</h3>
                    <p>Monitor task completion and measure productivity with visual analytics.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Mobile Friendly</h3>
                    <p>Access your tasks from anywhere using our responsive mobile interface.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h3>Secure & Private</h3>
                    <p>Keep your task data secure with our robust privacy and security features.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h2>Start Managing Your Tasks Today!</h2>
            <p>Join thousands of individuals and teams who use our task manager to boost their productivity and streamline their workflow.</p>
            <a href="register.php" class="btn">Sign Up Free</a>
            <a href="features.php" class="btn btn-outline">Learn More</a>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-column">
                    <h3>Task Manager</h3>
                    <p>A simple and powerful tool to organize your work and boost productivity.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#">Home</a></li>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Features</a></li>
                        <li><a href="#">Pricing</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Resources</h3>
                    <ul>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">FAQs</a></li>
                        <li><a href="#">Documentation</a></li>
                        <li><a href="#">Community</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contact Us</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> 123 Task Street, Productivity City</li>
                        <li><i class="fas fa-phone"></i> +1 (555) 123-4567</li>
                        <li><i class="fas fa-envelope"></i> info@taskmanager.com</li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> Task Manager. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Intersection Observer for animation
            const observerOptions = {
                threshold: 0.2
            };
            
            // Feature cards animation
            const featureCards = document.querySelectorAll('.feature-card');
            const featureObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        // Add delay based on index for cascade effect
                        setTimeout(() => {
                            entry.target.classList.add('visible');
                        }, index * 100);
                    }
                });
            }, observerOptions);
            
            featureCards.forEach(card => {
                featureObserver.observe(card);
            });
            
            // CTA section animation
            const ctaSection = document.querySelector('.cta');
            const ctaObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, observerOptions);
            
            if (ctaSection) {
                ctaObserver.observe(ctaSection);
            }
        });
    </script>
</body>
</html>