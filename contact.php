<?php
session_start();
// Include database configuration
include 'dbConfig.php';

// // 1. AUTO-REDIRECT: If user is already logged in, send them to the logged-in contact page
// if (!empty($_SESSION['user_id'])) {
//     header("Location: Afterlogin/alcontact.php");
//     exit();
// }

// Handle form submission (Optional: You can add actual email sending logic here later)
$message_sent = false;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Logic to save message to database or send email would go here
    $message_sent = true;
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Contact Us | Velvet Vogue</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Playfair+Display:wght@400;700&family=Jost:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <style>
    :root {
        --primary-black: #1a1a1a;
        --secondary-gold: #c5a059;
        --text-grey: #666;
        --light-bg: #f8f9fa;
    }
    
    body { font-family: 'Jost', sans-serif; color: var(--primary-black); }
    h1, h2, h3, h4, h5 { font-family: 'Playfair Display', serif; }
    
    /* Navbar Styling (Consistent with Home/Shop) */
    .navbar { padding: 15px 0; background: white; }
    .navbar-brand { 
        font-family: 'Dancing Script', cursive; 
        font-size: 2rem; 
        color: var(--primary-black) !important; 
        font-weight: 700;
    }
    .nav-link { 
        color: var(--primary-black) !important; 
        font-weight: 500; 
        margin: 0 10px;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 1px;
    }
    
    /* Hero Header */
    .page-header {
        background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1441986300917-64674bd600d8?q=80&w=2070&auto=format&fit=crop') center/cover;
        color: white;
        padding: 100px 0;
        text-align: center;
        margin-bottom: 50px;
    }
    
    /* Contact Cards */
    .contact-info-card {
        padding: 30px;
        background: white;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        height: 100%;
        text-align: center;
        transition: transform 0.3s ease;
        border: 1px solid #eee;
    }
    .contact-info-card:hover { transform: translateY(-5px); border-color: var(--secondary-gold); }
    .icon-box {
        width: 60px;
        height: 60px;
        background: var(--primary-black);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 1.5rem;
    }
    
    /* Form Styling */
    .contact-form-wrapper {
        background: white;
        padding: 40px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        border-radius: 8px;
    }
    .form-control {
        border-radius: 0;
        padding: 12px 15px;
        border: 1px solid #ddd;
        background-color: var(--light-bg);
    }
    .form-control:focus {
        box-shadow: none;
        border-color: var(--primary-black);
        background-color: white;
    }
    .btn-submit {
        background: var(--primary-black);
        color: white;
        border: none;
        padding: 15px 30px;
        text-transform: uppercase;
        letter-spacing: 2px;
        font-weight: 600;
        transition: 0.3s;
        width: 100%;
    }
    .btn-submit:hover {
        background: var(--secondary-gold);
        color: white;
    }
    
    /* Map */
    .map-container {
        height: 100%;
        min-height: 400px;
        background: #eee;
        border-radius: 8px;
        overflow: hidden;
    }
    
    /* Navbar Buttons */
    .btn-login { border: 1px solid var(--primary-black); color: var(--primary-black); border-radius: 0; padding: 8px 25px; transition: 0.3s; text-decoration: none; }
    .btn-login:hover { background: var(--primary-black); color: white; }
    .btn-register { background: var(--primary-black); color: white; border-radius: 0; padding: 8px 25px; border: 1px solid var(--primary-black); transition: 0.3s; text-decoration: none; }
    .btn-register:hover { background: transparent; color: var(--primary-black); }

    /* Footer */
    footer { background-color: #111; color: white; }
    .footer-link { color: #999; text-decoration: none; transition: 0.3s; }
    .footer-link:hover { color: white; }
  </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="home.php">Velvet Vogue</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav m-auto">
        <li class="nav-item"><a class="nav-link" href="home.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
        <li class="nav-item"><a class="nav-link " href="contact.php">Contact</a></li>
        <li class="nav-item"><a class="nav-link Active" href="aboutus.php">About us</a></li>
      </ul>
      <div class="d-flex align-items-center gap-2">
        <a href="login.php" class="btn-login">Login</a>
        <a href="register.php" class="btn-register">Register</a>
      </div>
    </div>
  </div>
</nav>

<header class="page-header">
    <div class="container">
        <h1 class="display-4 fw-bold">Get in Touch</h1>
        <p class="lead">We'd love to hear from you. Here is how you can reach us.</p>
    </div>
</header>

<div class="container mb-5">
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="contact-info-card">
                <div class="icon-box"><i class="fas fa-map-marker-alt"></i></div>
                <h4>Visit Us</h4>
                <p class="text-muted">123 Fashion Avenue,<br>Colombo 07, Sri Lanka</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="contact-info-card">
                <div class="icon-box"><i class="fas fa-phone-alt"></i></div>
                <h4>Call Us</h4>
                <p class="text-muted">Mon-Fri from 8am to 5pm.</p>
                <p class="fw-bold">+94 77 123 4567</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="contact-info-card">
                <div class="icon-box"><i class="fas fa-envelope"></i></div>
                <h4>Email Us</h4>
                <p class="text-muted">Send us your query anytime!</p>
                <p class="fw-bold">support@velvetvogue.lk</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="contact-form-wrapper">
                <h3 class="mb-4">Send a Message</h3>
                
                <?php if($message_sent): ?>
                    <div class="alert alert-success">
                        Thank you! Your message has been sent successfully.
                    </div>
                <?php endif; ?>

                <form action="contact.php" method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="name" placeholder="Your Name" required>
                        </div>
                        <div class="col-md-6">
                            <input type="email" class="form-control" name="email" placeholder="Your Email" required>
                        </div>
                        <div class="col-12">
                            <input type="text" class="form-control" name="subject" placeholder="Subject" required>
                        </div>
                        <div class="col-12">
                            <textarea class="form-control" name="message" rows="6" placeholder="How can we help you?" required></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn-submit">Send Message</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="map-container shadow-sm h-100">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d31686.41738734676!2d79.8559091!3d6.9147575!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae25963120b1509%3A0x2db2c18a68712c63!2sCinnamon%20Gardens%2C%20Colombo%2007!5e0!3m2!1sen!2slk!4v1715000000000!5m2!1sen!2slk" 
                    width="100%" 
                    height="100%" 
                    style="border:0; min-height: 450px;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>
    </div>
</div>

<footer class="pt-5 pb-3 mt-5">
  <div class="container">
    <div class="row">
      <div class="col-md-4 mb-4">
        <h4 class="mb-4" style="font-family: 'Playfair Display', serif;">Velvet Vogue</h4>
        <p class="text-muted">Experience the pinnacle of fashion. We bring you the latest trends with unmatched quality and style.</p>
      </div>
      <div class="col-md-2 mb-4">
        <h5 class="text-white mb-3">Shop</h5>
        <ul class="list-unstyled">
          <li><a href="shop.php" class="footer-link">New Arrivals</a></li>
          <li><a href="shop.php" class="footer-link">Best Sellers</a></li>
          <li><a href="shop.php" class="footer-link">Sale</a></li>
        </ul>
      </div>
      <div class="col-md-2 mb-4">
        <h5 class="text-white mb-3">Company</h5>
        <ul class="list-unstyled">
          <li><a href="#" class="footer-link">About Us</a></li>
          <li><a href="contact.php" class="footer-link">Contact</a></li>
          <li><a href="#" class="footer-link">Privacy Policy</a></li>
        </ul>
      </div>
      <div class="col-md-4 mb-4">
        <h5 class="text-white mb-3">Newsletter</h5>
        <p class="text-muted small">Subscribe to get special offers and updates.</p>
        <form class="d-flex gap-2">
            <input type="email" class="form-control rounded-0 border-0" placeholder="Your Email">
            <button class="btn btn-light rounded-0">JOIN</button>
        </form>
      </div>
    </div>
    <hr class="border-secondary mt-4">
    <div class="text-center text-muted small">
        &copy; 2025 Velvet Vogue. All Rights Reserved.
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>