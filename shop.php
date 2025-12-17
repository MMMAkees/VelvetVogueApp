<?php
session_start();
include 'dbConfig.php'; 

// === FILTERS ===
$category_filter = $_GET['cat'] ?? '';
$search_query = trim($_GET['q'] ?? '');

// Build SQL
$sql = "SELECT p.Product_ID, p.P_Name, p.P_Price, p.Image_URL, p.P_Color, p.Stock_Quantity, c.Category_Name 
        FROM product p 
        LEFT JOIN category c ON p.Category_ID_FK = c.Category_ID 
        WHERE 1=1";
$params = [];
$types = '';

if ($category_filter !== '' && is_numeric($category_filter)) {
    $sql .= " AND p.Category_ID_FK = ?";
    $params[] = $category_filter;
    $types .= 'i';
}
if ($search_query !== '') {
    $sql .= " AND p.P_Name LIKE ?";
    $params[] = "%$search_query%";
    $types .= 's';
}

$sql .= " ORDER BY p.P_Date_Added DESC";

// === PREPARE SAFELY ===
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("SQL Error: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products_result = $stmt->get_result();

$products = [];
while ($row = $products_result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

// === CATEGORIES FOR FILTER ===
$categories = [];
$cat_stmt = $conn->query("SELECT Category_ID, Category_Name FROM category ORDER BY Category_Name");
if ($cat_stmt && $cat_stmt->num_rows > 0) {
    while ($c = $cat_stmt->fetch_assoc()) {
        $categories[] = $c;
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shop Collection | Velvet Vogue</title>
  
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
    
    body { font-family: 'Jost', sans-serif; color: var(--primary-black); background-color: var(--light-bg); }
    h1, h2, h3, h4, h5, h6 { font-family: 'Playfair Display', serif; }
    
    /* === NAVBAR STYLING (MATCHING OTHER PAGES) === */
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

    /* Search Bar in Nav */
    .nav-search-input { border: 1px solid #ddd; border-radius: 0; padding: 5px 10px; font-size: 0.9rem; }
    .nav-search-btn { background: var(--primary-black); color: white; border: none; border-radius: 0; padding: 5px 15px; }
    .nav-search-btn:hover { background: var(--secondary-gold); }

    /* === SHOP CARD STYLING === */
    .product-card { 
        border: none;
        background: white;
        border-radius: 0; /* Minimalist sharp corners */
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        height: 100%;
        position: relative;
        cursor: pointer;
    }
    .product-card:hover { 
        transform: translateY(-5px); 
        box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    }
    .product-image {
        height: 300px; /* Taller editorial look */
        object-fit: cover;
        width: 100%;
        background: #f0f0f0;
    }
    .product-image-placeholder {
        height: 300px;
        background: #eee;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #aaa;
    }
    
    /* Filter Sidebar */
    .filter-sidebar { 
        background: white; 
        border-radius: 0; 
        padding: 1.5rem; 
        border: 1px solid #eee;
        position: sticky;
        top: 100px;
    }
    
    .list-group-item { border: none; padding: 10px 0; border-bottom: 1px solid #f9f9f9; color: var(--text-grey); font-family: 'Jost', sans-serif; }
    .list-group-item.active { background: transparent; color: var(--primary-black); font-weight: 700; border-color: var(--primary-black); }
    .badge-custom { background: #eee; color: var(--primary-black); font-weight: 500; }

    /* Product Elements */
    .category-badge {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--secondary-gold);
        font-weight: 600;
    }
    .product-title {
        font-size: 1.1rem;
        font-family: 'Playfair Display', serif;
        margin-top: 5px;
        margin-bottom: 5px;
        color: var(--primary-black);
    }
    .price-tag { 
        font-size: 1rem; 
        color: var(--primary-black); 
        font-weight: 600;
    }
    
    /* Actions */
    .wishlist-btn {
        position: absolute;
        top: 15px;
        right: 15px; /* Moved to right for cleaner look */
        background: white;
        border: none;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        z-index: 2;
        cursor: pointer;
        color: var(--primary-black);
    }
    .wishlist-btn:hover { background: var(--primary-black); color: white; }
    
    .stock-badge {
        position: absolute;
        top: 15px;
        left: 15px;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 5px 10px;
        border-radius: 0;
    }
    
    /* Quick Actions Overlay */
    .quick-actions-overlay {
        position: absolute;
        bottom: -50px;
        left: 0;
        width: 100%;
        background: white;
        padding: 10px;
        display: flex;
        transition: bottom 0.3s ease;
        border-top: 1px solid #eee;
    }
    .product-card:hover .quick-actions-overlay { bottom: 0; }
    
    .btn-action {
        flex: 1;
        border: none;
        background: transparent;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
        padding: 5px;
    }
    .btn-action:hover { color: var(--secondary-gold); }

    /* Footer */
    footer { background-color: #111; color: white; margin-top: 80px; }
    .footer-link { color: #999; text-decoration: none; transition: 0.3s; }
    .footer-link:hover { color: white; }
    
    /* Clean up Filter Buttons */
    .btn-clear { color: var(--text-grey); font-size: 0.85rem; text-decoration: underline; }
    .btn-clear:hover { color: var(--primary-black); }
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
        <li class="nav-item"><a class="nav-link active" href="shop.php">Shop</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
        <li class="nav-item"><a class="nav-link" href="aboutus.php">About us</a></li>
      </ul>
      
      <div class="d-flex align-items-center gap-3">
        <form action="shop.php" class="d-flex">
          <input name="q" class="nav-search-input" placeholder="Search..." value="<?= htmlspecialchars($search_query) ?>">
          <button class="nav-search-btn"><i class="fa fa-search"></i></button>
        </form>
        
        <div class="d-none d-lg-flex gap-2">
            <a href="login.php" class="btn-login">Login</a>
            <a href="register.php" class="btn-register">Register</a>
        </div>
      </div>
    </div>
  </div>
</nav>

<section class="bg-white py-5 border-bottom">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-md-6">
        <span class="text-uppercase text-muted small" style="letter-spacing: 2px;">The Collection</span>
        <h2 class="mb-0 mt-2 fw-bold">All Products</h2>
      </div>
      <div class="col-md-6 text-md-end">
        <span class="text-muted small me-3"><?= count($products) ?> items</span>
        <a href="shop.php" class="btn-clear">Clear Filters</a>
      </div>
    </div>
  </div>
</section>

<div class="container my-5">
  <div class="row">
    <div class="col-lg-3 mb-4">
      <div class="filter-sidebar">
        <h5 class="fw-bold mb-4">Categories</h5>
        <div class="list-group list-group-flush">
            <a href="shop.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $category_filter === '' ? 'active' : '' ?>">
              All Products
              <span class="badge badge-custom rounded-0"><?= count($products) ?></span>
            </a>
            <?php foreach ($categories as $c): 
                $count_stmt = $conn->prepare("SELECT COUNT(*) FROM product WHERE Category_ID_FK = ?");
                $count_stmt->bind_param("i", $c['Category_ID']);
                $count_stmt->execute();
                $product_count = $count_stmt->get_result()->fetch_array()[0];
                $count_stmt->close();
            ?>
            <a href="shop.php?cat=<?= $c['Category_ID'] ?>" 
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $category_filter == $c['Category_ID'] ? 'active' : '' ?>">
              <?= htmlspecialchars($c['Category_Name']) ?>
              <span class="badge badge-custom rounded-0"><?= $product_count ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="mt-5 p-3 bg-light text-center border">
            <i class="fas fa-truck fa-2x mb-3 text-muted"></i>
            <h6>Free Shipping</h6>
            <p class="small text-muted mb-0">On all orders over $150</p>
        </div>
      </div>
    </div>

    <div class="col-lg-9">
      <?php if (count($products) > 0): ?>
      <div class="row g-4">
        <?php foreach ($products as $p): ?>
        <div class="col-md-4 col-sm-6">
          <div class="card product-card" onclick="viewProduct(<?= $p['Product_ID'] ?>)">
            
            <div class="position-relative">
                <?php
                $image_path = "";
                $image_found = false;
                $possible_paths = ["uploads/" . $p['Image_URL'], "img/products/" . $p['Image_URL'], "img/NEW ARRIVALS/" . $p['Image_URL']];
                
                foreach ($possible_paths as $path) {
                    if (file_exists($path) && !empty($p['Image_URL'])) {
                        $image_path = $path;
                        $image_found = true;
                        break;
                    }
                }
                ?>
                
                <?php if ($image_found): ?>
                    <img src="<?= $image_path ?>" class="product-image" alt="<?= htmlspecialchars($p['P_Name']) ?>">
                <?php else: ?>
                    <div class="product-image-placeholder"><i class="fa fa-image fa-2x"></i></div>
                <?php endif; ?>
                
                <button class="wishlist-btn" onclick="redirectToLogin(event)"><i class="fa fa-heart"></i></button>
                
                <?php if ($p['Stock_Quantity'] > 0): ?>
                    <span class="stock-badge bg-white text-success border">In Stock</span>
                <?php else: ?>
                    <span class="stock-badge bg-black text-white">Out of Stock</span>
                <?php endif; ?>

                <div class="quick-actions-overlay">
                    <button class="btn-action border-end" onclick="viewProduct(<?= $p['Product_ID'] ?>)">View Details</button>
                    <button class="btn-action" onclick="shareProduct(<?= $p['Product_ID'] ?>)">Share</button>
                </div>
            </div>

            <div class="card-body text-center pt-3 pb-4">
                <span class="category-badge"><?= htmlspecialchars($p['Category_Name'] ?? '') ?></span>
                <h5 class="product-title"><?= htmlspecialchars($p['P_Name']) ?></h5>
                <p class="price-tag mb-0">$<?= number_format($p['P_Price'], 2) ?></p>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="text-center py-5">
        <i class="fas fa-search fa-3x text-muted mb-3"></i>
        <h4>No products found</h4>
        <p class="text-muted">Try removing some filters.</p>
        <a href="shop.php" class="btn btn-login px-4 mt-2">View All</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

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
<script>
// Redirect to login page
function viewProduct(productId) {
    window.location.href = `login.php`; 
}

function redirectToLogin(event) {
    event.stopPropagation();
    window.location.href = 'login.php';
}

function shareProduct(productId) {
    event.preventDefault();
    event.stopPropagation();
    const productUrl = `${window.location.origin}/login.php`;
    if (navigator.share) {
        navigator.share({ title: 'Velvet Vogue', url: productUrl }).catch(() => {});
    } else {
        navigator.clipboard.writeText(productUrl).then(() => alert('Link copied!')).catch(() => {});
    }
}
</script>
</body>
</html>