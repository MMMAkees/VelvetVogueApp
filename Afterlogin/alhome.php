<?php
// User home page after login
// Home page layout
session_start();
include __DIR__ . '/../dbConfig.php';

// Redirect if not logged in
if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php?next=Afterlogin/alhome.php");
    exit();
}

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Handle cart success message
$cart_success = '';
if (isset($_SESSION['cart_success'])) {
    $cart_success = $_SESSION['cart_success'];
    unset($_SESSION['cart_success']);
}

$user_id = intval($_SESSION['user_id']);
$username = 'Customer';

// === 1. FETCH USER ===
$stmt = $conn->prepare("SELECT Username FROM user WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $username = $row['Username'];
}
$stmt->close();

// === 2. CART COUNT ===
$cart_count = 0;
$cart_stmt = $conn->prepare("
    SELECT SUM(ci.Quantity) AS total 
    FROM cart_item ci 
    JOIN shopping_cart sc ON ci.Cart_ID_FK = sc.Cart_ID 
    WHERE sc.User_ID_FK = ?
");
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
if ($cart_row = $cart_result->fetch_assoc()) {
    $cart_count = $cart_row['total'] ?? 0;
}
$cart_stmt->close();

// === 3. NEW ARRIVALS ===
$new_arrivals = [];
$na_query = "SELECT Product_ID, P_Name, P_Price, Image_URL FROM product ORDER BY P_Date_Added DESC LIMIT 4";
$na_result = $conn->query($na_query);

if ($na_result && $na_result->num_rows > 0) {
    while ($p = $na_result->fetch_assoc()) {
        $new_arrivals[] = $p;
    }
}

// === 4. CATEGORIES - FETCH FROM DATABASE ===
$categories = [];
$cat_query = "SELECT Category_ID, Category_Name, C_Description, Cat_Image FROM category ORDER BY Category_Name LIMIT 6";
$cat_result = $conn->query($cat_query);

if ($cat_result && $cat_result->num_rows > 0) {
    while ($cat = $cat_result->fetch_assoc()) {
        $categories[] = $cat;
    }
}

// === 5. OFFERS ===
$offers = [];
$offer_query = "
    SELECT Offer_ID, Title, Description, Image_URL, Discount_Percentage, Start_Date, End_Date 
    FROM offers 
    WHERE Is_Active = 1 AND End_Date >= CURDATE()
    ORDER BY Created_At DESC 
    LIMIT 3
";
$offer_result = $conn->query($offer_query);

if ($offer_result && $offer_result->num_rows > 0) {
    while ($o = $offer_result->fetch_assoc()) {
        $offers[] = $o;
    }
}

// === 6. PROMOTIONS ===
$promotions = [];
$promo_query = "
    SELECT Promotion_ID, P_Title, Description, Discount_Percentage, Start_Date, End_Date 
    FROM promotion 
    WHERE Start_Date <= CURDATE() AND End_Date >= CURDATE()
    ORDER BY Start_Date DESC 
    LIMIT 3
";
$promo_result = $conn->query($promo_query);

if ($promo_result && $promo_result->num_rows > 0) {
    while ($promo = $promo_result->fetch_assoc()) {
        $promotions[] = $promo;
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Velvet Vogue — Welcome <?= htmlspecialchars($username) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .hero-banner { 
      background: linear-gradient(rgba(85, 77, 92, 0.5), rgba(63, 49, 66, 0.24)), 
                  url('../img/hero-welcome.jpg') center/cover no-repeat;
      color: white; border-radius: 20px; padding: 80px 20px; text-align: center;
    }
    .hero-banner h1 { font-family: 'Dancing Script', cursive; font-size: 3.5rem; }
    .poster-card, .category-card, .offer-card, .promotion-card { 
      border-radius: 16px; overflow: hidden; box-shadow: 0 6px 20px rgba(0,0,0,0.08); transition: 0.3s; 
      cursor: pointer;
    }
    .poster-card:hover, .category-card:hover, .offer-card:hover, .promotion-card:hover { 
      transform: translateY(-8px); 
      box-shadow: 0 12px 30px rgba(0,0,0,0.15);
    }
    .btn-primary { background: #131313ff; border: none; }
    .btn-primary:hover { background: #ffffffff; }
    .navbar-brand { font-family: 'Dancing Script', cursive; color: #000000ff !important; }
    .search-form input { max-width: 220px; }
    .product-img { 
        height: 250px; 
        object-fit: contain; 
        width: 100%;
        background: #f8f9fa;
        padding: 15px;
    }
    .category-img { 
        height: 200px; 
        object-fit: cover; 
        width: 100%;
    }
    .offer-img { 
        height: 180px; 
        object-fit: cover; 
        width: 100%;
    }
    .footer-link:hover { color: #8a2be2 !important; }
    .category-description {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      font-size: 0.9rem;
      color: #6c757d;
    }
    .cart-notification {
      position: fixed;
      top: 100px;
      right: 20px;
      z-index: 1050;
      min-width: 300px;
    }
    /* Button Style */
    .btn-add-to-cart {
      position: relative;
      overflow: hidden;
    }
    .btn-add-to-cart .spinner-border {
      width: 1rem;
      height: 1rem;
      display: none;
    }
    .promotion-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        background: #dc3545;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        z-index: 2;
    }
    .offer-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: #28a745;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        z-index: 2;
    }
    .promotion-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        min-height: 280px;
    }
    .promotion-content {
        padding: 2rem;
        text-align: center;
        display: flex;
        flex-direction: column;
        height: 100%;
        justify-content: center;
    }
    .promotion-timer {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-top: 10px;
    }
    .image-placeholder {
        height: 250px;
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6c757d;
    }
    .offer-image-placeholder {
        height: 180px;
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6c757d;
    }
    .modal-offer-image {
        width: 100%;
        height: 300px;
        object-fit: cover;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    .modal-promotion-content {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 10px;
        text-align: center;
    }
    .days-left {
        background: #ffc107;
        color: #000;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        margin-left: 8px;
    }
    .offer-days-left {
        background: #dc3545;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        margin-left: 8px;
    }
    .product-selection-card {
      border: 2px solid transparent;
      transition: all 0.3s ease;
    }
    .product-selection-card:hover {
      border-color: #8a2be2;
      box-shadow: 0 4px 15px rgba(138, 43, 226, 0.2);
    }
    .form-check-input:checked + .form-check-label {
      color: #8a2be2;
      font-weight: bold;
    }
    .form-check-input:checked ~ .card-body {
      background-color: rgba(138, 43, 226, 0.05);
    }
  </style>
</head>
<body class="bg-light">

<!-- Cart Success Notification -->
<?php if ($cart_success): ?>
<div class="cart-notification">
  <div class="alert alert-success alert-dismissible fade show shadow" role="alert">
    <i class="fa fa-check-circle me-2"></i>
    <?= htmlspecialchars($cart_success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
</div>
<?php endif; ?>

<!-- Header Section -->
<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg bg-white shadow-sm border-bottom sticky-top">
  <div class="container">
    <a class="navbar-brand fs-3" href="alhome.php">Velvet Vogue</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav m-auto">
        <li class="nav-item"><a class="nav-link active" href="alhome.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="alshop.php">Shop</a></li>
        <li class="nav-item"><a class="nav-link" href="alcontact.php">Contact</a></li>
      </ul>
      <div class="d-flex align-items-center gap-3">
        <!-- WORKING SEARCH BAR -->
        <form action="../search_results.php" method="GET" class="d-flex search-form">
          <input type="text" name="q" class="form-control me-2" placeholder="Search products..." required>
          <button type="submit" class="btn btn-danger">
            <i class="fa fa-search"></i>
          </button>
        </form>
        <!-- USER MENU -->
        <div class="dropdown">
          <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fa fa-user"></i> <?= htmlspecialchars($username) ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
            <li><a class="dropdown-item" href="my_orders.php">Orders</a></li>
            <li><a class="dropdown-item" href="wishlist.php">Wishlist</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="?logout=1">Logout</a></li>
          </ul>
        </div>
        <!-- CART -->
        <a href="alcart.php" class="btn btn-outline-primary position-relative">
          <i class="fa fa-shopping-cart"></i> Cart
          <?php if ($cart_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
              <?= $cart_count ?>
            </span>
          <?php endif; ?>
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- 1. WELCOME BANNER -->
<section class="container my-5">
  <div class="hero-banner">
    <h5 class="fw-light">Welcome back to your style hub</h5>
    <h1>Hi, <?= htmlspecialchars($username) ?>!</h1>
    <p class="lead">Save up to 70% with exclusive deals just for you</p>
    <a href="alshop.php" class="btn btn-light btn-lg">Start Shopping</a>
  </div>
</section>

<!-- NEW ARRIVALS -->
<section class="container my-5">
  <div class="text-center mb-5">
    <h2 class="fw-bold text-uppercase" style="letter-spacing:1px;">NEW ARRIVALS</h2>
  </div>
  <div class="row g-4">
    <?php if (count($new_arrivals) > 0): ?>
      <?php foreach ($new_arrivals as $p): ?>
      <div class="col-md-3">
        <div class="card poster-card h-100">
          <?php

          $product_image_path = "";
          $image_found = false;
          
          if (!empty($p['Image_URL'])) {

              $possible_paths = [
                  "../uploads/" . $p['Image_URL'],
                  "../img/products/" . $p['Image_URL'],
                  "../img/NEW ARRIVALS/" . $p['Image_URL']
              ];
              
              foreach ($possible_paths as $path) {
                  if (file_exists($path) && !empty($p['Image_URL'])) {
                      $product_image_path = $path;
                      $image_found = true;
                      break;
                  }
              }
          }
          ?>
          
          <?php if ($image_found): ?>
              <img src="<?= $product_image_path ?>" 
                   class="card-img-top product-img" alt="<?= htmlspecialchars($p['P_Name']) ?>">
          <?php else: ?>
              <div class="image-placeholder">
                  <i class="fa fa-image fa-3x"></i>
              </div>
          <?php endif; ?>
          
          <div class="card-body d-flex flex-column">
            <h5 class="card-title" style="font-size: 1rem; min-height: 48px; display: -webkit-box; -webkit-line-clamp: 2; 
            -webkit-box-orient: vertical; overflow: hidden;">
                <?= htmlspecialchars($p['P_Name']) ?>
            </h5>
            <p class="text-danger fw-bold mt-auto">$<?= number_format($p['P_Price'], 2) ?></p>
            <button class="btn btn-primary mt-2 btn-add-to-cart" 
                    data-product-id="<?= $p['Product_ID'] ?>" 
                    data-product-name="<?= htmlspecialchars($p['P_Name']) ?>">
              <span class="btn-text">Add to Cart</span>
              <span class="spinner-border spinner-border-sm" role="status"></span>
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="col-12 text-center">
        <p class="text-muted">No new arrivals yet.</p>
        <a href="alshop.php" class="btn btn-primary">Browse All Products</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- CATEGORIES -->
<section class="container my-5">
  <div class="text-center mb-5">
    <h2 class="fw-bold text-uppercase" style="letter-spacing:1px;">BROWSE BY CATEGORY</h2>
  </div>
  <div class="row g-4 justify-content-center">
    <?php if (count($categories) > 0): ?>
      <?php foreach ($categories as $cat): ?>
      <div class="col-md-4">
        <div class="card category-card text-center h-100" onclick="window.location.href='alshop.php?cat=<?= $cat['Category_ID'] ?>'">
          <?php
          $category_image_path = "";
          $category_image_found = false;
          
          if (!empty($cat['Cat_Image'])) {
              $possible_paths = [
                  "../uploads/" . $cat['Cat_Image'],
                  "../img/categories/" . $cat['Cat_Image'],
                  "../img/NEW ARRIVALS/" . $cat['Cat_Image']
              ];
              
              foreach ($possible_paths as $path) {
                  if (file_exists($path) && !empty($cat['Cat_Image'])) {
                      $category_image_path = $path;
                      $category_image_found = true;
                      break;
                  }
              }
          }
          ?>
          
          <?php if ($category_image_found): ?>
              <img src="<?= $category_image_path ?>" 
                   class="card-img-top category-img" alt="<?= htmlspecialchars($cat['Category_Name']) ?>">
          <?php else: ?>
              <div class="image-placeholder" style="height: 200px;">
                  <i class="fa fa-tags fa-3x"></i>
              </div>
          <?php endif; ?>
          
          <div class="card-body">
            <h5 class="card-title"><?= htmlspecialchars($cat['Category_Name']) ?></h5>
            <?php if (!empty($cat['C_Description'])): ?>
              <p class="category-description"><?= htmlspecialchars($cat['C_Description']) ?></p>
            <?php endif; ?>
            <span class="btn btn-outline-success">View All Products</span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="col-12 text-center">
        <p class="text-muted">No categories available yet.</p>
        <a href="alshop.php" class="btn btn-primary">Browse All Products</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- OFFERS -->
<section class="container my-5">
  <div class="text-center mb-5">
    <h2 class="fw-bold text-uppercase" style="letter-spacing:1px;">LIMITED TIME OFFERS</h2>
  </div>
  <?php 
  ?>
  
  <?php if (count($offers) > 0): ?>
  <div class="row g-4 justify-content-center">
    <?php foreach ($offers as $o): 
        $days_left = floor((strtotime($o['End_Date']) - time()) / (60 * 60 * 24));
    ?>
    <div class="col-md-4">
      <div class="card offer-card text-center h-100 position-relative" onclick="showOfferDetails(<?= htmlspecialchars(json_encode($o)) ?>)">
        <div class="offer-badge">
          <?= $o['Discount_Percentage'] ?>% OFF
        </div>
        <?php
        // Fix offer image path
        $offer_image_path = "";
        $offer_image_found = false;
        
        if (!empty($o['Image_URL'])) {
            // Check different possible image locations
            $possible_paths = [
                "../uploads/" . $o['Image_URL'],
                "../img/offers/" . $o['Image_URL'],
                "../img/NEW ARRIVALS/" . $o['Image_URL'],
                "../img/" . $o['Image_URL']
            ];
            
            foreach ($possible_paths as $path) {
                if (file_exists($path) && !empty($o['Image_URL'])) {
                    $offer_image_path = $path;
                    $offer_image_found = true;
                    break;
                }
            }
            
            if (!$offer_image_found && !empty($o['Image_URL'])) {
                $offer_image_path = "../uploads/" . $o['Image_URL'];
                $offer_image_found = true; 
            }
        }
        ?>
        
        <?php if ($offer_image_found): ?>
            <img src="<?= $offer_image_path ?>" 
                 class="card-img-top offer-img" alt="<?= htmlspecialchars($o['Title']) ?>"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div class="offer-image-placeholder" style="display: none;">
                <i class="fa fa-tag fa-3x"></i>
            </div>
        <?php else: ?>
            <div class="offer-image-placeholder">
                <i class="fa fa-tag fa-3x"></i>
            </div>
        <?php endif; ?>
        
        <div class="card-body">
          <h5 class="card-title"><?= htmlspecialchars($o['Title']) ?></h5>
          <p class="card-text small text-muted" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
            <?= htmlspecialchars($o['Description']) ?>
          </p>
          <div class="d-flex justify-content-between align-items-center mt-3">
            <small class="text-muted">
              <i class="fa fa-clock me-1"></i>
              Ends: <?= date('M d, Y', strtotime($o['End_Date'])) ?>
              <?php if ($days_left > 0): ?>
                <span class="offer-days-left"><?= $days_left ?> days left</span>
              <?php endif; ?>
            </small>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="text-center">
    <p class="text-muted">No active offers right now. Check back soon for exciting deals!</p>
    <a href="alshop.php" class="btn btn-outline-primary">Browse Products</a>
  </div>
  <?php endif; ?>
</section>

<!-- PROMOTIONS -->
<?php if (count($promotions) > 0): ?>
<section class="container my-5" id="promotions-section">
  <div class="text-center mb-5">
    <h2 class="fw-bold text-uppercase" style="letter-spacing:1px;">SPECIAL PROMOTIONS</h2>
  </div>
  <div class="row g-4 justify-content-center">
    <?php foreach ($promotions as $promo): 
        $days_left = floor((strtotime($promo['End_Date']) - time()) / (60 * 60 * 24));
    ?>
    <div class="col-md-4">
      <div class="card promotion-card h-100 position-relative" onclick="showPromotionDetails(<?= htmlspecialchars(json_encode($promo)) ?>)">
        <div class="promotion-badge">
          PROMOTION
        </div>
        <div class="promotion-content">
          <h5 class="card-title"><?= htmlspecialchars($promo['P_Title']) ?></h5>
          <?php if (!empty($promo['Description'])): ?>
            <p class="card-text" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
              <?= htmlspecialchars($promo['Description']) ?>
            </p>
          <?php endif; ?>
          <?php if (!empty($promo['Discount_Percentage'])): ?>
            <div class="mb-3">
              <span class="badge bg-warning text-dark fs-6"><?= $promo['Discount_Percentage'] ?>% OFF</span>
            </div>
          <?php endif; ?>
          <div class="promotion-timer">
            <i class="fa fa-clock me-1"></i>
            Valid until: <?= date('M d, Y', strtotime($promo['End_Date'])) ?>
            <?php if ($days_left > 0): ?>
              <span class="days-left"><?= $days_left ?> days left</span>
            <?php endif; ?>
          </div>
          <span class="btn btn-light mt-3">
            <i class="fa fa-info-circle me-2"></i>View Details
          </span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- Offer Details  -->
<div class="modal fade" id="offerModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="offerModalTitle">Offer Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="offerModalBody">
>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success" id="offerAddToCartBtn" style="display: none;">
          <i class="fa fa-shopping-cart me-2"></i>Add to Cart
        </button>
        <a href="alshop.php" class="btn btn-primary">Shop All Products</a>
      </div>
    </div>
  </div>
</div>

<!-- Promotion Details -->
<div class="modal fade" id="promotionModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="promotionModalTitle">Promotion Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="promotionModalBody">

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success" id="promotionAddToCartBtn" style="display: none;">
          <i class="fa fa-shopping-cart me-2"></i>Add to Cart
        </button>
        <a href="alshop.php" class="btn btn-primary">Shop All Products</a>
      </div>
    </div>
  </div>
</div>

<!-- Product Selection  -->
<div class="modal fade" id="productSelectionModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="productSelectionModalTitle">Select Products</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="productSelectionModalBody">
        <div class="row" id="productSelectionGrid">

        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmSelectionBtn" disabled>
          <i class="fa fa-check me-2"></i>Add Selected to Cart
        </button>
      </div>
    </div>
  </div>
</div>

 <!-- FOOTER  -->
<footer class="bg-dark text-light pt-5 pb-4 mt-5 border-top">
  <div class="container">
    <div class="row">
      <div class="col-md-4">
        <h5 class="fw-bold">About Velvet Vogue</h5>
        <p>Premium fashion with timeless elegance and unmatched quality.</p>
      </div>
      <div class="col-md-4">
        <h5 class="fw-bold">Quick Links</h5>
        <ul class="list-unstyled">
          <li><a href="alhome.php" class="text-light text-decoration-none footer-link">Home</a></li>
          <li><a href="alshop.php" class="text-light text-decoration-none footer-link">Shop</a></li>
          <?php if (count($promotions) > 0): ?>
          <li><a href="#promotions-section" class="text-light text-decoration-none footer-link">Promotions</a></li>
          <?php endif; ?>
          <li><a href="alcontact.php" class="text-light text-decoration-none footer-link">Contact</a></li>
        </ul>
      </div>
      <div class="col-md-4">
        <h5 class="fw-bold">Stay Connected</h5>
        <div class="mb-3">
          <a href="#" class="me-3 text-light footer-link"><i class="fab fa-instagram fa-lg"></i></a>
          <a href="#" class="me-3 text-light footer-link"><i class="fab fa-facebook fa-lg"></i></a>
          <a href="#" class="me-3 text-light footer-link"><i class="fab fa-twitter fa-lg"></i></a>
        </div>
        <p class="small text-muted">Follow us for latest updates and fashion tips</p>
      </div>
    </div>
    <hr>
    <p class="text-center small mb-0">© 2025 Velvet Vogue. All Rights Reserved.</p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

let currentOffer = null;
let currentPromotion = null;
let selectedProducts = [];

// Add to cart functionality with AJAX
document.addEventListener('DOMContentLoaded', function() {
    const addToCartButtons = document.querySelectorAll('.btn-add-to-cart');
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            addProductToCart(this);
        });
    });

    // Offer Add to Cart button
    document.getElementById('offerAddToCartBtn').addEventListener('click', function() {
        if (currentOffer) {
            showProductSelection(currentOffer, 'offer');
        }
    });

    // Promotion Add to Cart button
    document.getElementById('promotionAddToCartBtn').addEventListener('click', function() {
        if (currentPromotion) {
            showProductSelection(currentPromotion, 'promotion');
        }
    });

    // Confirm selection button
    document.getElementById('confirmSelectionBtn').addEventListener('click', function() {
        addSelectedProductsToCart();
    });
});

function addProductToCart(button) {
    const productId = button.getAttribute('data-product-id');
    const productName = button.getAttribute('data-product-name');
    const btnText = button.querySelector('.btn-text');
    const spinner = button.querySelector('.spinner-border');
    
    // Show loading state
    btnText.textContent = 'Adding...';
    spinner.style.display = 'inline-block';
    button.disabled = true;
    

    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', 1);
    
    fetch('../api/add_to_cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
 
        btnText.textContent = 'Add to Cart';
        spinner.style.display = 'none';
        button.disabled = false;
        
        if (data.success) {
            showCartNotification('✓ ' + productName + ' added to cart successfully!');
            updateCartCount(data.cart_count);
        } else {
            showCartNotification('❌ ' + (data.message || 'Failed to add item to cart'), 'danger');
        }
    })
    .catch(error => {

        btnText.textContent = 'Add to Cart';
        spinner.style.display = 'none';
        button.disabled = false;
        showCartNotification('❌ Network error. Please try again.', 'danger');
        console.error('Error:', error);
    });
}

// Show offer details in 
function showOfferDetails(offer) {
    currentOffer = offer;
    const daysLeft = Math.floor((new Date(offer.End_Date) - new Date()) / (1000 * 60 * 60 * 24));
    
    // Fix image path for 
    let offerImagePath = '';
    const possiblePaths = [
        `../uploads/${offer.Image_URL}`,
        `../img/offers/${offer.Image_URL}`,
        `../img/NEW ARRIVALS/${offer.Image_URL}`
    ];
    
    for (const path of possiblePaths) {
        if (offer.Image_URL) {
            offerImagePath = path;
            break;
        }
    }
    
    const modalContent = `
        ${offerImagePath ? `<img src="${offerImagePath}" class="modal-offer-image" alt="${offer.Title}">` : ''}
        <div class="mb-3">
            <span class="badge bg-success fs-6">${offer.Discount_Percentage}% OFF</span>
            ${daysLeft > 0 ? `<span class="badge bg-danger ms-2">${daysLeft} days left</span>` : ''}
        </div>
        <h4 class="mb-3">${offer.Title}</h4>
        <p class="lead">${offer.Description}</p>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <strong><i class="fa fa-percentage me-2"></i>Discount:</strong>
                <span class="text-success">${offer.Discount_Percentage}% OFF</span>
            </div>
            <div class="col-md-6">
                <strong><i class="fa fa-clock me-2"></i>Valid Until:</strong>
                <span>${new Date(offer.End_Date).toLocaleDateString()}</span>
            </div>
        </div>
        
        <div class="purchasable-section mt-4 p-3 bg-light rounded">
            <h5><i class="fa fa-shopping-cart me-2"></i>Available Products</h5>
            <p class="mb-3">This offer applies to selected products. Choose products to add to your cart:</p>
            <div class="text-center">
                <button type="button" class="btn btn-success btn-lg" onclick="showProductSelection(${JSON.stringify(offer).replace(/"/g, '&quot;')}, 'offer')">
                    <i class="fa fa-shopping-cart me-2"></i>Select Products
                </button>
            </div>
        </div>
        
        <div class="alert alert-info mt-3">
            <i class="fa fa-info-circle me-2"></i>
            The ${offer.Discount_Percentage}% discount will be automatically applied to selected products at checkout.
        </div>
    `;
    
    document.getElementById('offerModalTitle').textContent = offer.Title;
    document.getElementById('offerModalBody').innerHTML = modalContent;
    
    const modal = new bootstrap.Modal(document.getElementById('offerModal'));
    modal.show();
}

// Show promotion details in 
function showPromotionDetails(promotion) {
    currentPromotion = promotion;
    const daysLeft = Math.floor((new Date(promotion.End_Date) - new Date()) / (1000 * 60 * 60 * 24));
    
    const modalContent = `
        <div class="modal-promotion-content mb-4">
            <h4 class="mb-3">${promotion.P_Title}</h4>
            ${promotion.Description ? `<p class="mb-3">${promotion.Description}</p>` : ''}
            ${promotion.Discount_Percentage ? `
                <div class="mb-3">
                    <span class="badge bg-warning text-dark fs-6">${promotion.Discount_Percentage}% OFF</span>
                </div>
            ` : ''}
            <div class="promotion-timer">
                <i class="fa fa-clock me-2"></i>
                Valid until: ${new Date(promotion.End_Date).toLocaleDateString()}
                ${daysLeft > 0 ? `<span class="days-left">${daysLeft} days left</span>` : ''}
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-6">
                <strong><i class="fa fa-calendar me-2"></i>Start Date:</strong>
                <span>${new Date(promotion.Start_Date).toLocaleDateString()}</span>
            </div>
            <div class="col-md-6">
                <strong><i class="fa fa-calendar me-2"></i>End Date:</strong>
                <span>${new Date(promotion.End_Date).toLocaleDateString()}</span>
            </div>
        </div>
        
        <div class="purchasable-section mt-4 p-3 bg-light rounded">
            <h5><i class="fa fa-gift me-2"></i>Included Products</h5>
            <p class="mb-3">This promotion includes special seasonal products. Select products to add to your cart:</p>
            <div class="text-center">
                <button type="button" class="btn btn-success btn-lg" onclick="showProductSelection(${JSON.stringify(promotion).replace(/"/g, '&quot;')}, 'promotion')">
                    <i class="fa fa-shopping-cart me-2"></i>Browse Products
                </button>
            </div>
        </div>
        
        <div class="alert alert-success mt-3">
            <i class="fa fa-gift me-2"></i>
            ${promotion.Discount_Percentage ? `This promotion includes ${promotion.Discount_Percentage}% discount on selected products!` : 'Special seasonal promotion with exclusive deals!'}
        </div>
    `;
    
    document.getElementById('promotionModalTitle').textContent = promotion.P_Title;
    document.getElementById('promotionModalBody').innerHTML = modalContent;
    
    const modal = new bootstrap.Modal(document.getElementById('promotionModal'));
    modal.show();
}

// Show product selection 
function showProductSelection(item, type) {
    selectedProducts = [];
    
    // Close the current 
    if (type === 'offer') {
        bootstrap.Modal.getInstance(document.getElementById('offerModal')).hide();
    } else {
        bootstrap.Modal.getInstance(document.getElementById('promotionModal')).hide();
    }
    
    document.getElementById('productSelectionModalTitle').textContent = `Select Products - ${type === 'offer' ? item.Title : item.P_Title}`;
    
    // Show loading state
    document.getElementById('productSelectionGrid').innerHTML = `
        <div class="col-12 text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading available products...</p>
        </div>
    `;
    
    // Fetch products for this offer/promotion
    fetch(`../api/get_products_by_${type}.php?${type}_id=${type === 'offer' ? item.Offer_ID : item.Promotion_ID}`)
        .then(response => response.json())
        .then(products => {
            displayProducts(products, item, type);
        })
        .catch(error => {
            console.error('Error:', error);
            fetch('../api/get_all_products.php')
                .then(response => response.json())
                .then(products => {
                    displayProducts(products, item, type);
                })
                .catch(fallbackError => {
                    document.getElementById('productSelectionGrid').innerHTML = `
                        <div class="col-12 text-center py-4">
                            <i class="fa fa-exclamation-triangle fa-2x text-warning mb-3"></i>
                            <p>Unable to load products. Please try again later.</p>
                        </div>
                    `;
                });
        });
    
    const modal = new bootstrap.Modal(document.getElementById('productSelectionModal'));
    modal.show();
}

// Display products in selection modal
function displayProducts(products, item, type) {
    if (!products || products.length === 0) {
        document.getElementById('productSelectionGrid').innerHTML = `
            <div class="col-12 text-center py-4">
                <i class="fa fa-box-open fa-2x text-muted mb-3"></i>
                <p>No products available for this ${type}.</p>
                <a href="alshop.php" class="btn btn-primary">Browse All Products</a>
            </div>
        `;
        document.getElementById('confirmSelectionBtn').style.display = 'none';
        return;
    }
    
    let productsHTML = '';
    
    products.forEach(product => {
        const discountPrice = type === 'offer' ? 
            (product.P_Price * (1 - item.Discount_Percentage / 100)).toFixed(2) :
            (item.Discount_Percentage ? (product.P_Price * (1 - item.Discount_Percentage / 100)).toFixed(2) : product.P_Price);
        
        productsHTML += `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 product-selection-card">
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input product-checkbox" type="checkbox" 
                                   value="${product.Product_ID}" id="product_${product.Product_ID}"
                                   onchange="toggleProductSelection(${product.Product_ID}, this.checked)">
                            <label class="form-check-label" for="product_${product.Product_ID}">
                                <strong>Select</strong>
                            </label>
                        </div>
                        
                        ${product.Image_URL ? `
                            <img src="../uploads/${product.Image_URL}" class="card-img-top mb-3" 
                                 alt="${product.P_Name}" style="height: 120px; object-fit: contain;">
                        ` : `
                            <div class="bg-light rounded d-flex align-items-center justify-content-center mb-3" 
                                 style="height: 120px;">
                                <i class="fa fa-image fa-2x text-muted"></i>
                            </div>
                        `}
                        
                        <h6 class="card-title">${product.P_Name}</h6>
                        
                        <div class="price-section">
                            ${type === 'offer' || item.Discount_Percentage ? `
                                <div class="d-flex align-items-center">
                                    <span class="text-danger fw-bold fs-5">$${discountPrice}</span>
                                    <span class="text-muted text-decoration-line-through ms-2">$${product.P_Price}</span>
                                    <span class="badge bg-success ms-2">Save ${type === 'offer' ? item.Discount_Percentage : item.Discount_Percentage}%</span>
                                </div>
                            ` : `
                                <span class="text-primary fw-bold fs-5">$${product.P_Price}</span>
                            `}
                        </div>
                        
                        ${product.Stock_Quantity > 0 ? `
                            <small class="text-success">
                                <i class="fa fa-check-circle me-1"></i>In Stock (${product.Stock_Quantity} available)
                            </small>
                        ` : `
                            <small class="text-danger">
                                <i class="fa fa-times-circle me-1"></i>Out of Stock
                            </small>
                        `}
                    </div>
                </div>
            </div>
        `;
    });
    
    document.getElementById('productSelectionGrid').innerHTML = productsHTML;
    document.getElementById('confirmSelectionBtn').style.display = 'block';
    updateConfirmButton();
}

// Toggle product selection
function toggleProductSelection(productId, isSelected) {
    if (isSelected) {
        if (!selectedProducts.includes(productId)) {
            selectedProducts.push(productId);
        }
    } else {
        selectedProducts = selectedProducts.filter(id => id !== productId);
    }
    updateConfirmButton();
}

// Update confirm button state
function updateConfirmButton() {
    const confirmBtn = document.getElementById('confirmSelectionBtn');
    if (selectedProducts.length > 0) {
        confirmBtn.disabled = false;
        confirmBtn.textContent = `Add ${selectedProducts.length} Product${selectedProducts.length > 1 ? 's' : ''} to Cart`;
    } else {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Add Selected to Cart';
    }
}

// Add selected products to cart
function addSelectedProductsToCart() {
    if (selectedProducts.length === 0) return;
    
    const confirmBtn = document.getElementById('confirmSelectionBtn');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>Adding...';
    
    let addedCount = 0;
    let errorCount = 0;
    
    // Add each selected product to cart
    const addPromises = selectedProducts.map(productId => {
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('quantity', 1);
        
        return fetch('../api/add_to_cart.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                addedCount++;
                updateCartCount(data.cart_count);
            } else {
                errorCount++;
            }
            return data;
        });
    });
    
    // Wait for all requests to complete
    Promise.all(addPromises).then(results => {
        // Close the modal
        bootstrap.Modal.getInstance(document.getElementById('productSelectionModal')).hide();
        
        // Show result notification
        if (addedCount > 0) {
            showCartNotification(`✓ ${addedCount} product${addedCount > 1 ? 's' : ''} added to cart successfully!`);
        }
        if (errorCount > 0) {
            showCartNotification(`❌ ${errorCount} product${errorCount > 1 ? 's' : ''} failed to add. Please try again.`, 'warning');
        }
        
        // Reset button
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = originalText;
        
        // Clear selection
        selectedProducts = [];
    });
}

function showCartNotification(message, type = 'success') {
    // Remove existing notifications
    const existingNotification = document.querySelector('.cart-notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Create new notification
    const notification = document.createElement('div');
    notification.className = 'cart-notification';
    notification.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show shadow" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 3000);
}

function updateCartCount(count) {
    const cartBadge = document.querySelector('.navbar .badge');
    if (cartBadge) {
        cartBadge.textContent = count;
    } else {
        const cartLink = document.querySelector('a[href="alcart.php"]');
        if (cartLink && count > 0) {
            const badge = document.createElement('span');
            badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
            badge.textContent = count;
            cartLink.appendChild(badge);
        }
    }
}

// Auto-dismiss existing notifications after 3 seconds
const existingAlerts = document.querySelectorAll('.alert');
existingAlerts.forEach(alert => {
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    }, 3000);
});
</script>
</body>
</html>