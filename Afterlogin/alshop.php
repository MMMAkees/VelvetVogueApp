<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// Redirect if not logged in
if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php?next=Afterlogin/alshop.php");
    exit();
}

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$username = 'Customer';

// Fetch user
$stmt = $conn->prepare("SELECT Username FROM user WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $username = $row['Username'];
}
$stmt->close();

// Cart count
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

// Check wishlist items for this user to show active hearts
$wishlist_items = [];
$wishlist_stmt = $conn->prepare("
    SELECT Product_ID_FK 
    FROM wishlist 
    WHERE User_ID_FK = ?
");
$wishlist_stmt->bind_param("i", $user_id);
$wishlist_stmt->execute();
$wishlist_result = $wishlist_stmt->get_result();
while ($wishlist_row = $wishlist_result->fetch_assoc()) {
    $wishlist_items[] = $wishlist_row['Product_ID_FK'];
}
$wishlist_stmt->close();

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
  <title>Shop - <?= htmlspecialchars($username) ?> | Velvet Vogue</title>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .navbar-brand { font-family: 'Dancing Script', cursive; color: #000000ff !important; }
    .product-card { 
        border: none;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 6px 20px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        height: 100%;
        position: relative;
        cursor: pointer;
    }
    .product-card:hover { 
        transform: translateY(-8px); 
        box-shadow: 0 12px 30px rgba(0,0,0,0.15);
    }
    .product-image {
        height: 250px;
        object-fit: contain;
        width: 100%;
        background: #f8f9fa;
        padding: 15px;
    }
    .product-image-placeholder {
        height: 250px;
        background: linear-gradient(135deg, #8a2be2, #4b0082);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }
    .btn-primary { 
        background: #8a2be2; 
        border: none; 
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 600;
        transition: all 0.3s ease;
        font-size: 0.85rem;
    }
    .btn-primary:hover { 
        background: #7a1fd1; 
        transform: translateY(-2px);
    }
    .filter-sidebar { 
        background: white; 
        border-radius: 16px; 
        padding: 1.5rem; 
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        position: sticky;
        top: 20px;
    }
    .price-tag { 
        font-size: 1.2rem; 
        color: #dc3545; 
        font-weight: 700;
    }
    .category-badge {
        background: #8a2be2;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    .color-dots {
        display: flex;
        gap: 6px;
        margin-top: 8px;
    }
    .color-dot {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .stock-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        z-index: 2;
    }
    .product-card-body {
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .product-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .wishlist-btn {
        position: absolute;
        top: 12px;
        left: 12px;
        background: white;
        border: none;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        z-index: 2;
        cursor: pointer;
    }
    .wishlist-btn:hover {
        background: #ff6b6b;
        color: white;
        transform: scale(1.1);
    }
    .wishlist-btn.active {
        background: #ff6b6b;
        color: white;
    }
    .product-features {
        margin: 12px 0;
        font-size: 0.85rem;
    }
    .feature-item {
        display: flex;
        align-items: center;
        margin-bottom: 6px;
        color: #6c757d;
    }
    .feature-item i {
        width: 16px;
        margin-right: 8px;
        color: #8a2be2;
    }
    .quick-actions {
        display: flex;
        gap: 8px;
        margin-top: 12px;
    }
    .quick-action-btn {
        flex: 1;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        color: #6c757d;
        padding: 8px;
        border-radius: 8px;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }
    .quick-action-btn:hover {
        background: #e9ecef;
        color: #495057;
        transform: translateY(-1px);
    }
    .rating {
        color: #ffc107;
        font-size: 0.9rem;
        margin: 8px 0;
    }
    .product-meta {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 8px;
    }
    .product-footer {
        margin-top: auto;
        padding-top: 15px;
        border-top: 1px solid #f0f0f0;
    }
  </style>
</head>
<body class="bg-light">

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg bg-white shadow-sm border-bottom sticky-top">
  <div class="container">
    <a class="navbar-brand fs-3" href="alhome.php">Velvet Vogue</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav m-auto">
        <li class="nav-item"><a class="nav-link" href="alhome.php">Home</a></li>
        <li class="nav-item"><a class="nav-link active" href="alshop.php">Shop</a></li>
        <li class="nav-item"><a class="nav-link" href="alcontact.php">Contact</a></li>
      </ul>
      <div class="d-flex align-items-center gap-3">
        <form action="alshop.php" class="d-flex">
          <input name="q" class="form-control me-2" placeholder="Search products..." value="<?= htmlspecialchars($search_query) ?>" style="max-width:220px;">
          <button type="submit" class="btn btn-danger">
            <i class="fa fa-search"></i>
          </button>
        </form>
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
        <a href="alcart.php" class="btn btn-outline-primary position-relative">
          <i class="fa fa-shopping-cart"></i> Cart
          <?php if ($cart_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cartCount">
              <?= $cart_count ?>
            </span>
          <?php endif; ?>
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- SHOP HEADER -->
<section class="bg-light py-4 border-bottom">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-md-6">
        <h2 class="mb-0 fw-bold">Shop Collection</h2>
        <p class="text-muted mb-0">Discover our premium fashion selection</p>
      </div>
      <div class="col-md-6 text-md-end">
        <span class="text-muted"><?= count($products) ?> products found</span>
        <a href="alshop.php" class="btn btn-outline-secondary btn-sm ms-2">Clear Filters</a>
      </div>
    </div>
  </div>
</section>

<!-- MAIN SHOP -->
<div class="container my-5">
  <div class="row">
    <!-- FILTER SIDEBAR -->
    <div class="col-lg-3 mb-4">
      <div class="filter-sidebar">
        <h5 class="fw-bold mb-3"><i class="fa fa-filter me-2"></i>Filters</h5>

        <!-- Category Filter -->
        <div class="mb-4">
          <h6 class="fw-bold mb-3">Category</h6>
          <div class="list-group list-group-flush">
            <a href="alshop.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $category_filter === '' ? 'active' : '' ?>">
              All Products
              <span class="badge bg-primary rounded-pill"><?= count($products) ?></span>
            </a>
            <?php foreach ($categories as $c): 
                // Count products in this category
                $count_stmt = $conn->prepare("SELECT COUNT(*) FROM product WHERE Category_ID_FK = ?");
                $count_stmt->bind_param("i", $c['Category_ID']);
                $count_stmt->execute();
                $count_result = $count_stmt->get_result();
                $product_count = $count_result->fetch_array()[0];
                $count_stmt->close();
            ?>
            <a href="alshop.php?cat=<?= $c['Category_ID'] ?>" 
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $category_filter == $c['Category_ID'] ? 'active' : '' ?>">
              <?= htmlspecialchars($c['Category_Name']) ?>
              <span class="badge bg-primary rounded-pill"><?= $product_count ?></span>
            </a>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="mt-4">
          <h6 class="fw-bold mb-3">Shop Info</h6>
          <div class="bg-light p-3 rounded">
            <div class="feature-item">
              <i class="fas fa-shipping-fast"></i>
              <span>Free Shipping Over $100</span>
            </div>
            <div class="feature-item">
              <i class="fas fa-undo-alt"></i>
              <span>30-Day Returns</span>
            </div>
            <div class="feature-item">
              <i class="fas fa-shield-alt"></i>
              <span>Secure Payment</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- PRODUCT GRID -->
    <div class="col-lg-9">
      <?php if (count($products) > 0): ?>
      <div class="row g-4">
        <?php foreach ($products as $p): 
            // Parse colors
            $colors = [];
            if (!empty($p['P_Color'])) {
                $color_array = explode(',', $p['P_Color']);
                $colors = array_slice($color_array, 0, 3); // Show max 3 colors
            }
            
            // Check if product is in wishlist
            $is_in_wishlist = in_array($p['Product_ID'], $wishlist_items);
        ?>
        <div class="col-md-4 col-sm-6">
          <div class="card product-card h-100" onclick="viewProduct(<?= $p['Product_ID'] ?>)">
            <!-- Product Image -->
            <div class="position-relative">
                <?php
                $image_path = "";
                $image_found = false;
                
                // Check different possible image locations
                $possible_paths = [
                    "../uploads/" . $p['Image_URL'],
                    "../img/products/" . $p['Image_URL'],
                    "../img/NEW ARRIVALS/" . $p['Image_URL']
                ];
                
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
                    <div class="product-image-placeholder">
                        <i class="fa fa-image fa-3x"></i>
                    </div>
                <?php endif; ?>
                
                <!-- Wishlist Button -->
                <button class="wishlist-btn <?= $is_in_wishlist ? 'active' : '' ?>" 
                        onclick="addToWishlist(<?= $p['Product_ID'] ?>, this)">
                    <i class="fa fa-heart"></i>
                </button>
                
                <!-- Stock Badge -->
                <?php if ($p['Stock_Quantity'] > 0): ?>
                    <span class="badge bg-success stock-badge">In Stock</span>
                <?php else: ?>
                    <span class="badge bg-danger stock-badge">Out of Stock</span>
                <?php endif; ?>
            </div>

            <!-- Product Info -->
            <div class="product-card-body">
                <!-- Category -->
                <div class="mb-2">
                    <span class="category-badge"><?= htmlspecialchars($p['Category_Name'] ?? 'Uncategorized') ?></span>
                </div>
                
                <!-- Title -->
                <h5 class="product-title"><?= htmlspecialchars($p['P_Name']) ?></h5>
                
                <!-- Rating -->
                <div class="rating">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star-half-alt"></i>
                    <small class="text-muted ms-1">(4.5)</small>
                </div>
                
                <!-- Product Meta -->
                <div class="product-meta">
                    <div class="feature-item">
                        <i class="fas fa-palette"></i>
                        <span>Multiple Colors</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-box"></i>
                        <span><?= $p['Stock_Quantity'] ?> in stock</span>
                    </div>
                </div>
                
                <!-- Colors -->
                <?php if (!empty($colors)): ?>
                <div class="color-dots mb-3">
                    <?php foreach ($colors as $color): ?>
                        <div class="color-dot" style="background-color: <?= trim($color) ?>;" title="<?= htmlspecialchars(trim($color)) ?>"></div>
                    <?php endforeach; ?>
                    <?php if (count($color_array) > 3): ?>
                        <small class="text-muted">+<?= count($color_array) - 3 ?> more</small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Price & Actions -->
                <div class="product-footer">
                    <p class="price-tag mb-3">$<?= number_format($p['P_Price'], 2) ?></p>
                    
                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <button class="quick-action-btn" onclick="quickView(<?= $p['Product_ID'] ?>)">
                            <i class="fas fa-eye"></i> Quick Look
                        </button>
                        <button class="quick-action-btn" onclick="shareProduct(<?= $p['Product_ID'] ?>)">
                            <i class="fas fa-share-alt"></i> Share
                        </button>
                    </div>
                </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="text-center py-5">
        <i class="fas fa-box-open fa-4x text-muted mb-4"></i>
        <h4>No products found</h4>
        <p class="text-muted mb-4">Try adjusting your filters or search term.</p>
        <a href="alshop.php" class="btn btn-primary">Clear Filters</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- FOOTER -->
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
          <li><a href="alhome.php" class="text-light text-decoration-none">Home</a></li>
          <li><a href="alshop.php" class="text-light text-decoration-none">Shop</a></li>
          <li><a href="#promotions-section" class="text-light text-decoration-none footer-link">Promotions</a></li>
          <li><a href="alcontact.php" class="text-light text-decoration-none">Contact</a></li>
        </ul>
      </div>
      <div class="col-md-4">
        <h5 class="fw-bold">Stay Connected</h5>
        <div class="mb-3">
          <a href="#" class="me-3 text-light"><i class="fab fa-instagram"></i></a>
          <a href="#" class="me-3 text-light"><i class="fab fa-facebook"></i></a>
          <a href="#" class="me-3 text-light footer-link"><i class="fab fa-twitter fa-lg"></i></a>
        </div>
      </div>
    </div>
    <hr>
    <p class="text-center small mb-0">© 2025 Velvet Vogue. All Rights Reserved.</p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// View product details when card is clicked
function viewProduct(productId) {
    window.location.href = `alproduct_details.php?id=${productId}`;
}

// Quick View function (same as viewProduct for now)
function quickView(productId) {
    event.stopPropagation();
    viewProduct(productId);
}

// Add to wishlist function
function addToWishlist(productId, button) {
    event.preventDefault();
    event.stopPropagation();
    
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    const formData = new FormData();
    formData.append('product_id', productId);
    
    fetch('../api/add_to_wishlist.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            // Toggle heart color
            button.classList.toggle('active');
        } else {
            showNotification(data.message, 'error');
        }
        button.innerHTML = '<i class="fa fa-heart"></i>';
        button.disabled = false;
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error adding to wishlist', 'error');
        button.innerHTML = '<i class="fa fa-heart"></i>';
        button.disabled = false;
    });
}

// Share Product function
function shareProduct(productId) {
    event.preventDefault();
    event.stopPropagation();
    
    const productUrl = `${window.location.origin}${window.location.pathname.replace('alshop.php', '')}alproduct_details.php?id=${productId}`;
    
    if (navigator.share) {
        // Use Web Share API if available
        navigator.share({
            title: 'Check out this product from Velvet Vogue',
            url: productUrl
        })
        .then(() => showNotification('Product shared successfully!', 'success'))
        .catch(() => showNotification('Share cancelled', 'info'));
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(productUrl)
            .then(() => showNotification('Product link copied to clipboard!', 'success'))
            .catch(() => {
                // Final fallback: show URL in alert
                prompt('Copy this link to share:', productUrl);
            });
    }
}

// Show notification
function showNotification(message, type) {
    // Remove existing notifications
    const existingNotification = document.querySelector('.custom-notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Create new notification
    const notification = document.createElement('div');
    notification.className = `custom-notification alert alert-${type} alert-dismissible fade show`;
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        z-index: 1050;
        min-width: 300px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    `;
    
    notification.innerHTML = `
        <i class="fa ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Prevent card link when clicking buttons
document.querySelectorAll('.wishlist-btn, .quick-action-btn').forEach(button => {
    button.addEventListener('click', function(e) {
        e.stopPropagation();
    });
});

// Add hover effect to product cards
document.querySelectorAll('.product-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-8px)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});
</script>
</body>
</html>