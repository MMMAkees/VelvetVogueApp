<?php
// Public home page design
session_start();
include 'dbConfig.php';

// === 2. NEW ARRIVALS ===
$new_arrivals = [];
$na_query = "SELECT Product_ID, P_Name, P_Price, Image_URL FROM product ORDER BY P_Date_Added DESC LIMIT 4";
$na_result = $conn->query($na_query);
if ($na_result && $na_result->num_rows > 0) {
    while ($p = $na_result->fetch_assoc()) {
        $new_arrivals[] = $p;
    }
}

// === 3. CATEGORIES ===
$categories = [];
$cat_query = "SELECT Category_ID, Category_Name, C_Description, Cat_Image FROM category ORDER BY Category_Name LIMIT 3";
$cat_result = $conn->query($cat_query);
if ($cat_result && $cat_result->num_rows > 0) {
    while ($cat = $cat_result->fetch_assoc()) {
        $categories[] = $cat;
    }
}

// === 4. OFFERS ===
$offers = [];
$offer_query = "SELECT Offer_ID, Title, Description, Image_URL, Discount_Percentage, End_Date FROM offers WHERE Is_Active = 1 AND End_Date >= CURDATE() ORDER BY Created_At DESC LIMIT 1";
$offer_result = $conn->query($offer_query);
if ($offer_result && $offer_result->num_rows > 0) {
    while ($o = $offer_result->fetch_assoc()) {
        $offers[] = $o;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Velvet Vogue — Redefining Fashion</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Playfair+Display:wght@400;700&family=Jost:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <style>
    :root {
        --primary-black: #1a1a1a;
        --secondary-gold: #c5a059;
        --text-grey: #666;
        --off-white: #f8f9fa;
    }
    
    body { font-family: 'Jost', sans-serif; color: var(--primary-black); background-color: #fff; }
    h1, h2, h3, h4 { font-family: 'Playfair Display', serif; }
    
    /* Navbar Styling */
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
    .btn-login { border: 1px solid var(--primary-black); color: var(--primary-black); border-radius: 0; padding: 6px 20px; transition: 0.3s; text-decoration: none; font-size: 0.9rem; }
    .btn-login:hover { background: var(--primary-black); color: white; }
    .btn-register { background: var(--primary-black); color: white; border-radius: 0; padding: 6px 20px; border: 1px solid var(--primary-black); transition: 0.3s; text-decoration: none; font-size: 0.9rem; }
    .btn-register:hover { background: transparent; color: var(--primary-black); }

    /* Hero Section */
    .hero-banner { 
      background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), 
                  url('https://images.unsplash.com/photo-1490481651871-ab68de25d43d?q=80&w=2070&auto=format&fit=crop') center/cover no-repeat;
      height: 85vh;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: white; 
      position: relative;
    }
    .hero-content h1 { font-size: 4.5rem; margin-bottom: 20px; animation: fadeInUp 1s; }
    .hero-content p { font-size: 1.2rem; margin-bottom: 30px; letter-spacing: 1px; font-weight: 300; }
    .btn-hero { background: white; color: black; padding: 15px 40px; border-radius: 0; text-transform: uppercase; letter-spacing: 2px; border: none; font-weight: 600; transition: 0.3s; text-decoration: none; }
    .btn-hero:hover { background: var(--secondary-gold); color: white; }

    /* === CATEGORY CARD DESIGN === */
    .category-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        height: 100%;
        border: 1px solid rgba(0,0,0,0.05);
        cursor: pointer;
    }
    .category-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    .cat-img-wrapper {
        height: 250px;
        width: 100%;
        overflow: hidden;
        background: #f8f9fa;
        position: relative;
    }
    .category-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s;
    }
    .category-card:hover .category-img { transform: scale(1.05); }
    
    .cat-content { padding: 25px 20px; text-align: center; }
    .cat-title { font-family: 'Jost', sans-serif; font-weight: 600; font-size: 1.25rem; margin-bottom: 5px; color: var(--primary-black); }
    .cat-desc { color: #888; font-size: 0.9rem; margin-bottom: 20px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    
    .btn-view-products {
        border: 1px solid #1a1a1a;
        color: #1a1a1a;
        background: transparent;
        padding: 8px 20px;
        border-radius: 4px;
        text-transform: capitalize;
        font-size: 0.9rem;
        transition: 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    .btn-view-products:hover { background: #1a1a1a; color: white; }

    /* === PRODUCT CARD DESIGN === */
    .product-card { 
      border: none; 
      transition: all 0.3s ease;
      background: transparent;
      cursor: pointer;
    }
    .product-card:hover { transform: translateY(-5px); }
    .product-img-wrapper { 
        position: relative; 
        overflow: hidden; 
        background: #f4f4f4; 
        height: 400px;
        width: 100%;
        margin-bottom: 15px;
    }
    /* Fixed Image Handling */
    .product-img { 
        width: 100%; 
        height: 100%; 
        object-fit: cover; /* Ensures image fills box */
        transition: transform 0.6s ease; 
    }
    .product-image-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #eee;
        color: #aaa;
    }
    .product-card:hover .product-img { transform: scale(1.08); }
    
    .add-to-cart-overlay {
        position: absolute;
        bottom: -50px;
        left: 0;
        width: 100%;
        background: rgba(255, 255, 255, 0.95);
        padding: 15px;
        text-align: center;
        transition: bottom 0.3s;
    }
    .product-card:hover .add-to-cart-overlay { bottom: 0; }

    .section-title { font-size: 2.5rem; margin-bottom: 10px; }
    .section-subtitle { color: var(--secondary-gold); text-transform: uppercase; letter-spacing: 2px; font-size: 0.9rem; font-weight: 600; }

    /* Footer */
    footer { background-color: #111; color: white; }
    .footer-link { color: #999; text-decoration: none; transition: 0.3s; }
    .footer-link:hover { color: white; }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="index.php">Velvet Vogue</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav m-auto">
        <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li> 
        <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
        <li class="nav-item"><a class="nav-link" href="aboutus.php">About us</a></li>
      </ul>
      <div class="d-flex align-items-center gap-2">
        <a href="login.php" class="btn-login">Login</a>
        <a href="register.php" class="btn-register">Register</a>
      </div>
    </div>
  </div>
</nav>

<header class="hero-banner">
  <div class="hero-content">
    <h2>Welcome to Velvet Online Store</h2>
    <h1>Elegance is an Attitude</h1>
    <p>Discover the latest collection of premium fashion designed for you.</p>
    <a href="shop.php" class="btn btn-hero">Shop Collection</a>
  </div>
</header>

<section class="container my-5 pt-5">
  <div class="text-center mb-5">
    <span class="section-subtitle">Fresh Looks</span>
    <h2 class="section-title">New Arrivals</h2>
  </div>
  
  <div class="row g-4">
    <?php if (count($new_arrivals) > 0): ?>
      <?php foreach ($new_arrivals as $p): ?>
      <div class="col-md-3 col-sm-6">
        <div class="card product-card h-100" onclick="window.location.href='login.php'">
          <div class="product-img-wrapper">
             <?php
              // === SMART IMAGE FINDER (FIXED) ===
              $image_path = "";
              $image_found = false;
              $db_image = trim($p['Image_URL']); // Clean whitespace
              
              if (!empty($db_image)) {
                  // 1. Check if DB has full path already (e.g., uploads/image.jpg)
                  if (file_exists($db_image)) {
                      $image_path = $db_image;
                      $image_found = true;
                  }
                  // 2. Check standard uploads folder
                  elseif (file_exists("uploads/" . $db_image)) {
                      $image_path = "uploads/" . $db_image;
                      $image_found = true;
                  }
                  // 3. Check other folders
                  elseif (file_exists("img/products/" . $db_image)) {
                      $image_path = "img/products/" . $db_image;
                      $image_found = true;
                  }
                  elseif (file_exists("img/NEW ARRIVALS/" . $db_image)) {
                      $image_path = "img/NEW ARRIVALS/" . $db_image;
                      $image_found = true;
                  }
                  
                  // === IMPORTANT: Fix Spaces for Browser ===
                  // If path is "uploads/White T Shirt.jpg", browser needs "uploads/White%20T%20Shirt.jpg"
                  if ($image_found) {
                      $image_path = str_replace(" ", "%20", $image_path);
                  }
              }
             ?>
             
             <?php if ($image_found): ?>
                 <img src="<?= $image_path ?>" class="product-img" alt="<?= htmlspecialchars($p['P_Name']) ?>">
             <?php else: ?>
                 <div class="product-image-placeholder">
                     <i class="fa fa-image fa-3x"></i>
                 </div>
             <?php endif; ?>
             
             <div class="add-to-cart-overlay">
                <span class="btn btn-dark w-100 rounded-0">Login to Buy</span>
             </div>
          </div>
          <div class="card-body text-center pt-2">
            <h5 class="card-title" style="font-size: 1rem;"><?= htmlspecialchars($p['P_Name']) ?></h5>
            <p class="fw-bold text-muted">$<?= number_format($p['P_Price'], 2) ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<section class="container my-5 pb-5">
  <div class="text-center mb-5">
    <span class="section-subtitle">The Collections</span>
    <h2 class="section-title">Shop by Category</h2>
  </div>

  <div class="row g-4">
    <?php if (count($categories) > 0): ?>
      <?php foreach ($categories as $cat): ?>
      <div class="col-md-4">
        <div class="category-card h-100 d-flex flex-column" onclick="window.location.href='shop.php?cat=<?= $cat['Category_ID'] ?>'">
            <?php
              $cat_img_path = "";
              $cat_found = false;
              $db_cat_img = trim($cat['Cat_Image']);

              if (!empty($db_cat_img)) {
                  if (file_exists($db_cat_img)) {
                      $cat_img_path = $db_cat_img;
                      $cat_found = true;
                  }
                  elseif (file_exists("uploads/" . $db_cat_img)) {
                      $cat_img_path = "uploads/" . $db_cat_img;
                      $cat_found = true;
                  }
                  elseif (file_exists("img/categories/" . $db_cat_img)) {
                      $cat_img_path = "img/categories/" . $db_cat_img;
                      $cat_found = true;
                  }
                  
                  if ($cat_found) {
                      $cat_img_path = str_replace(" ", "%20", $cat_img_path);
                  }
              }
              
              if (!$cat_found) $cat_img_path = "img/cat-placeholder.jpg"; 
              $desc = !empty($cat['C_Description']) ? $cat['C_Description'] : "Discover our latest " . $cat['Category_Name'] . " collection.";
            ?>
            
            <div class="cat-img-wrapper">
                <?php if ($cat_found): ?>
                    <img src="<?= $cat_img_path ?>" class="category-img" alt="<?= htmlspecialchars($cat['Category_Name']) ?>">
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center h-100 bg-light text-muted">
                        <i class="fa fa-tags fa-3x"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="cat-content flex-grow-1 d-flex flex-column align-items-center justify-content-center">
                <h3 class="cat-title"><?= htmlspecialchars($cat['Category_Name']) ?></h3>
                <p class="cat-desc"><?= htmlspecialchars($desc) ?></p>
                <span class="btn-view-products">View All Products</span>
            </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<section class="container my-5 pb-5">
    <?php if (count($offers) > 0): $o = $offers[0]; ?>
    <div class="row align-items-center bg-light p-5 rounded-0">
        <div class="col-md-6 order-md-2">
            <?php 
                $off_img = "";
                $db_off_img = trim($o['Image_URL']);
                
                if (!empty($db_off_img) && file_exists("uploads/" . $db_off_img)) {
                    $off_img = "uploads/" . $db_off_img;
                    $off_img = str_replace(" ", "%20", $off_img);
                } else {
                    $off_img = "https://images.unsplash.com/photo-1483985988355-763728e1935b?q=80&w=2070&auto=format&fit=crop"; 
                }
            ?>
            <img src="<?= $off_img ?>" class="img-fluid shadow-lg" alt="Exclusive Offer">
        </div>
        <div class="col-md-6 order-md-1 text-center text-md-start p-4">
            <span class="text-danger fw-bold text-uppercase ls-2">Limited Time Offer</span>
            <h2 class="display-4 my-3 font-serif"><?= htmlspecialchars($o['Title']) ?></h2>
            <p class="lead text-muted"><?= htmlspecialchars($o['Description']) ?></p>
            <div class="mt-4">
                <h3 class="mb-4">Get <span class="text-danger"><?= $o['Discount_Percentage'] ?>% OFF</span></h3>
                <a href="register.php" class="btn btn-dark btn-lg rounded-0 px-5">Sign Up to Claim</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</section>

<footer class="pt-5 pb-3">
  <div class="container">
    <div class="row">
      <div class="col-md-4 mb-4">
        <h4 class="mb-4 font-serif">Velvet Vogue</h4>
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