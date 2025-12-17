<?php
session_start();
include 'dbConfig.php';

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>About Us | Velvet Vogue</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Playfair+Display:wght@400;700&family=Jost:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <style>
    :root {
        --primary-black: #1a1a1a;
        --secondary-gold: #c5a059;
        --text-grey: #666;
        --light-bg: #f9f9f9;
    }
    
    body { font-family: 'Jost', sans-serif; color: var(--primary-black); overflow-x: hidden; }
    h1, h2, h3, h4 { font-family: 'Playfair Display', serif; }
    
    /* Navbar */
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
    
    /* Hero Section */
    .about-hero {
        height: 60vh;
        background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), 
                    url('https://images.unsplash.com/photo-1441984904996-e0b6ba687e04?q=80&w=2070&auto=format&fit=crop') center/cover fixed;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: white;
    }
    .hero-title { font-size: 4rem; margin-bottom: 20px; }
    
    /* Story Section */
    .story-img {
        width: 100%;
        height: 500px;
        object-fit: cover;
        border-radius: 8px;
        box-shadow: 20px 20px 0px rgba(197, 160, 89, 0.2); /* Gold offset shadow */
    }
    
    /* Values Section */
    .values-section { background-color: var(--light-bg); }
    .value-card {
        background: white;
        padding: 40px 30px;
        text-align: center;
        transition: transform 0.3s ease;
        height: 100%;
        border: 1px solid #eee;
    }
    .value-card:hover { transform: translateY(-10px); border-color: var(--secondary-gold); }
    .value-icon {
        font-size: 2.5rem;
        color: var(--secondary-gold);
        margin-bottom: 20px;
    }
    
    /* Stats Strip */
    .stats-strip { background: var(--primary-black); color: white; padding: 60px 0; }
    .stat-number { font-size: 3rem; font-weight: 700; color: var(--secondary-gold); font-family: 'Playfair Display', serif; }
    
    /* Buttons */
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
<body>

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
        <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
        <li class="nav-item"><a class="nav-link Active" href="aboutus.php">About us</a></li>
        
      </ul>
      <div class="d-flex align-items-center gap-2">
        <a href="login.php" class="btn-login">Login</a>
        <a href="register.php" class="btn-register">Register</a>
      </div>
    </div>
  </div>
</nav>

<header class="about-hero">
    <div class="container">
        <h1 class="hero-title">Our Story</h1>
        <p class="lead text-uppercase" style="letter-spacing: 3px;">Crafting Elegance Since 2025</p>
    </div>
</header>

<section class="container my-5 py-5">
    <div class="row align-items-center">
        <div class="col-lg-6 mb-4 mb-lg-0">
            <img src="https://images.unsplash.com/photo-1558769132-cb1aea458c5e?q=80&w=1974&auto=format&fit=crop" alt="Fashion Studio" class="story-img">
        </div>
        <div class="col-lg-6 ps-lg-5">
            <span class="text-muted text-uppercase small" style="letter-spacing: 2px;">Who We Are</span>
            <h2 class="display-5 mb-4 mt-2">Redefining Modern Luxury</h2>
            <p class="text-muted lead">Velvet Vogue wasn't just born out of a passion for fashion; it was created to bridge the gap between timeless elegance and modern street style.</p>
            <p class="text-muted">We believe that clothing is more than just fabric—it is a form of self-expression. Our team of dedicated designers works tirelessly to source the finest materials, ensuring that every stitch embodies perfection. From the bustling streets of Colombo to the global stage, Velvet Vogue is here to make you feel confident, empowered, and undeniably stylish.</p>
            <div class="mt-4">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e4/Signature_sample.svg/1200px-Signature_sample.svg.png" alt="Signature" style="height: 50px; opacity: 0.6;">
                <p class="mt-2 fw-bold">Founder, Velvet Vogue</p>
            </div>
        </div>
    </div>
</section>

<section class="values-section py-5">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="mb-3">Why Choose Us</h2>
            <div style="width: 60px; height: 3px; background: var(--secondary-gold); margin: 0 auto;"></div>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="value-card">
                    <i class="fas fa-gem value-icon"></i>
                    <h4>Premium Quality</h4>
                    <p class="text-muted">We allow no compromises. Only the finest fabrics and materials make it into our collections.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="value-card">
                    <i class="fas fa-leaf value-icon"></i>
                    <h4>Sustainability</h4>
                    <p class="text-muted">Fashion shouldn't cost the earth. We are committed to ethical sourcing and eco-friendly packaging.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="value-card">
                    <i class="fas fa-shipping-fast value-icon"></i>
                    <h4>Global Delivery</h4>
                    <p class="text-muted">Style knows no borders. We ship our exclusive collections to fashion lovers worldwide.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="stats-strip">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3 mb-4 mb-md-0">
                <div class="stat-number">5k+</div>
                <div class="text-uppercase small" style="letter-spacing: 1px;">Happy Customers</div>
            </div>
            <div class="col-md-3 mb-4 mb-md-0">
                <div class="stat-number">120+</div>
                <div class="text-uppercase small" style="letter-spacing: 1px;">Exclusive Designs</div>
            </div>
            <div class="col-md-3 mb-4 mb-md-0">
                <div class="stat-number">15</div>
                <div class="text-uppercase small" style="letter-spacing: 1px;">Awards Won</div>
            </div>
            <div class="col-md-3">
                <div class="stat-number">24/7</div>
                <div class="text-uppercase small" style="letter-spacing: 1px;">Support</div>
            </div>
        </div>
    </div>
</section>

<footer class="pt-5 pb-3">
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
          <li><a href="aboutus.php" class="footer-link">About Us</a></li>
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
        © 2025 Velvet Vogue. All Rights Reserved.
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>