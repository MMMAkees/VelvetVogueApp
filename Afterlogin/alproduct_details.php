<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// Redirect if not logged in
if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php?next=Afterlogin/alproduct_details.php");
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
    SELECT SUM(ci.Quantity) FROM cart_item ci 
    JOIN shopping_cart sc ON ci.Cart_ID_FK = sc.Cart_ID 
    WHERE sc.User_ID_FK = ?
");
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
if ($cart_row = $cart_result->fetch_assoc()) {
    $cart_count = $cart_row['SUM(ci.Quantity)'] ?? 0;
}
$cart_stmt->close();

// === GET PRODUCT ID ===
$product_id = intval($_GET['id'] ?? 0);
if ($product_id <= 0) {
    header("Location: alshop.php");
    exit();
}

// === FETCH PRODUCT DETAILS ===
$product = null;
$stmt = $conn->prepare("
    SELECT p.Product_ID, p.P_Name, p.P_Price, p.P_Description, p.Image_URL, 
           c.Category_Name, p.Stock_Quantity, p.P_Size, p.P_Color
    FROM product p 
    LEFT JOIN category c ON p.Category_ID_FK = c.Category_ID 
    WHERE p.Product_ID = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $product = $row;
} else {
    header("Location: alshop.php");
    exit();
}
$stmt->close();

// Parse sizes and colors
$sizes = [];
if (!empty($product['P_Size'])) {
    $sizes = array_map('trim', explode(',', $product['P_Size']));
}

$colors = [];
if (!empty($product['P_Color'])) {
    $colors = array_map('trim', explode(',', $product['P_Color']));
}

// Default selections
$selected_size = $sizes[0] ?? '';
$selected_color = $colors[0] ?? '';
$quantity = 1;
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($product['P_Name']) ?> | Velvet Vogue</title>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .navbar-brand { font-family: 'Dancing Script', cursive; color: #0c0c0cff !important; }
    .product-gallery {
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        background: #f8f9fa;
    }
    .product-image {
        width: 100%;
        height: 500px;
        object-fit: contain;
        padding: 20px;
        background: white;
    }
    .product-image-placeholder {
        height: 500px;
        background: linear-gradient(135deg, #8a2be2, #4b0082);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }
    .btn-primary { 
        background: #8a2be2; 
        border: none; 
        padding: 12px 30px;
        border-radius: 25px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .btn-primary:hover { 
        background: #7a1fd1; 
        transform: translateY(-2px);
    }
    .price-tag { 
        font-size: 2.5rem; 
        color: #dc3545; 
        font-weight: 700;
    }
    .stock-info { font-size: 0.9rem; }
    .breadcrumb a { color: #8a2be2; text-decoration: none; }
    .breadcrumb a:hover { text-decoration: underline; }
    .option-section {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .size-option {
        display: inline-block;
        padding: 8px 16px;
        margin: 4px;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    .size-option:hover, .size-option.active {
        border-color: #8a2be2;
        background: #8a2be2;
        color: white;
    }
    .color-option {
        display: inline-block;
        width: 40px;
        height: 40px;
        margin: 4px;
        border: 3px solid #fff;
        border-radius: 50%;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .color-option:hover, .color-option.active {
        border-color: #8a2be2;
        transform: scale(1.1);
    }
    .quantity-selector {
        display: flex;
        align-items: center;
        gap: 10px;
        background: white;
        padding: 10px;
        border-radius: 10px;
        border: 2px solid #e9ecef;
        max-width: 150px;
    }
    .quantity-btn {
        width: 35px;
        height: 35px;
        border: none;
        background: #8a2be2;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .quantity-btn:hover {
        background: #7a1fd1;
        transform: scale(1.1);
    }
    .quantity-input {
        border: none;
        text-align: center;
        font-weight: 600;
        font-size: 1.1rem;
        width: 50px;
        background: transparent;
    }
    .feature-list {
        list-style: none;
        padding: 0;
    }
    .feature-list li {
        padding: 8px 0;
        border-bottom: 1px solid #f1f3f4;
    }
    .feature-list li:last-child {
        border-bottom: none;
    }
    .feature-list i {
        color: #8a2be2;
        margin-right: 10px;
    }
    .action-buttons {
        display: flex;
        gap: 15px;
        margin-top: 20px;
    }
    .btn-buy-now {
        background: #dc3545;
        border: none;
        color: white;
        padding: 12px 30px;
        border-radius: 25px;
        font-weight: 600;
        transition: all 0.3s ease;
        flex: 1;
    }
    .btn-buy-now:hover {
        background: #c82333;
        transform: translateY(-2px);
    }
    .btn-add-cart {
        background: #28a745;
        border: none;
        color: white;
        padding: 12px 30px;
        border-radius: 25px;
        font-weight: 600;
        transition: all 0.3s ease;
        flex: 1;
    }
    .btn-add-cart:hover {
        background: #218838;
        transform: translateY(-2px);
    }
    .wishlist-btn {
        background: #6c757d;
        border: none;
        color: white;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    .wishlist-btn:hover {
        background: #ff6b6b;
        transform: scale(1.1);
    }
    .btn-loading {
        position: relative;
        color: transparent !important;
    }
    .btn-loading::after {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        top: 50%;
        left: 50%;
        margin-left: -10px;
        margin-top: -10px;
        border: 2px solid #ffffff;
        border-radius: 50%;
        border-right-color: transparent;
        animation: spin 0.75s linear infinite;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
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
        <li class="nav-item"><a class="nav-link" href="alshop.php">Shop</a></li>
        <li class="nav-item"><a class="nav-link" href="alcontact.php">Contact</a></li>
      </ul>
      <div class="d-flex align-items-center gap-3">
        <form action="alshop.php" class="d-flex">
          <input name="q" class="form-control me-2" placeholder="Search..." style="max-width:220px;">
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

<!-- BREADCRUMB -->
<div class="container mt-3">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="alhome.php">Home</a></li>
      <li class="breadcrumb-item"><a href="alshop.php">Shop</a></li>
      <li class="breadcrumb-item"><a href="alshop.php?cat=<?= $product['Category_ID_FK'] ?? '' ?>"><?= htmlspecialchars($product['Category_Name'] ?? 'Products') ?></a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($product['P_Name']) ?></li>
    </ol>
  </nav>
</div>

<!-- PRODUCT DETAILS -->
<section class="container my-5">
  <div class="row g-5">
    <!-- IMAGE GALLERY -->
    <div class="col-lg-6">
      <div class="product-gallery">
        <?php
        $image_path = "";
        $image_found = false;
        
        // Check different possible image locations
        $possible_paths = [
            "../uploads/" . $product['Image_URL'],
            "../img/products/" . $product['Image_URL'],
            "../img/NEW ARRIVALS/" . $product['Image_URL']
        ];
        
        foreach ($possible_paths as $path) {
            if (file_exists($path) && !empty($product['Image_URL'])) {
                $image_path = $path;
                $image_found = true;
                break;
            }
        }
        ?>
        
        <?php if ($image_found): ?>
            <img src="<?= $image_path ?>" class="product-image" alt="<?= htmlspecialchars($product['P_Name']) ?>">
        <?php else: ?>
            <div class="product-image-placeholder">
                <i class="fa fa-image fa-5x"></i>
            </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- PRODUCT INFO -->
    <div class="col-lg-6">
      <div class="ps-lg-4">
        <!-- Category & Stock -->
        <div class="d-flex justify-content-between align-items-start mb-3">
          <span class="badge bg-primary"><?= htmlspecialchars($product['Category_Name'] ?? 'Uncategorized') ?></span>
          <?php if ($product['Stock_Quantity'] > 0): ?>
            <span class="badge bg-success stock-info">
              <i class="fa fa-check me-1"></i>In Stock (<?= $product['Stock_Quantity'] ?> available)
            </span>
          <?php else: ?>
            <span class="badge bg-danger stock-info">
              <i class="fa fa-times me-1"></i>Out of Stock
            </span>
          <?php endif; ?>
        </div>

        <!-- Product Title -->
        <h1 class="display-5 fw-bold mb-3"><?= htmlspecialchars($product['P_Name']) ?></h1>
        
        <!-- Price -->
        <div class="mb-4">
          <p class="price-tag mb-2">$<?= number_format($product['P_Price'], 2) ?></p>
          <small class="text-muted">Inclusive of all taxes</small>
        </div>

        <hr>

        <!-- Size Selection -->
        <?php if (!empty($sizes)): ?>
        <div class="option-section">
          <h5 class="fw-bold mb-3">Select Size</h5>
          <div class="size-options">
            <?php foreach ($sizes as $size): ?>
              <span class="size-option <?= $size === $selected_size ? 'active' : '' ?>" 
                    data-size="<?= htmlspecialchars($size) ?>">
                <?= htmlspecialchars($size) ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Color Selection -->
        <?php if (!empty($colors)): ?>
        <div class="option-section">
          <h5 class="fw-bold mb-3">Select Color</h5>
          <div class="color-options">
            <?php foreach ($colors as $color): ?>
              <span class="color-option <?= $color === $selected_color ? 'active' : '' ?>" 
                    style="background-color: <?= $color ?>;"
                    data-color="<?= htmlspecialchars($color) ?>"
                    title="<?= htmlspecialchars($color) ?>"></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Quantity Selection -->
        <div class="option-section">
          <h5 class="fw-bold mb-3">Quantity</h5>
          <div class="quantity-selector">
            <button class="quantity-btn" onclick="updateQuantity(-1)">
              <i class="fa fa-minus"></i>
            </button>
            <input type="number" class="quantity-input" id="quantity" value="1" min="1" max="<?= $product['Stock_Quantity'] ?>" readonly>
            <button class="quantity-btn" onclick="updateQuantity(1)">
              <i class="fa fa-plus"></i>
            </button>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
          <?php if ($product['Stock_Quantity'] > 0): ?>
            <button class="btn-add-cart" id="addToCartBtn" onclick="addToCart()">
              <i class="fa fa-shopping-cart me-2"></i> Add to Cart
            </button>
            <button class="btn-buy-now" id="buyNowBtn" onclick="buyNow()">
              <i class="fa fa-bolt me-2"></i> Buy Now
            </button>
            <button class="wishlist-btn" id="wishlistBtn" onclick="addToWishlist()">
              <i class="fa fa-heart"></i>
            </button>
          <?php else: ?>
            <button class="btn btn-secondary btn-lg px-5" disabled>Out of Stock</button>
          <?php endif; ?>
        </div>

        <!-- Product Features -->
        <div class="mt-5">
          <h5 class="fw-bold mb-3">Product Features</h5>
          <ul class="feature-list">
            <li><i class="fa fa-check"></i> Premium quality materials</li>
            <li><i class="fa fa-check"></i> Free shipping on orders over $50</li>
            <li><i class="fa fa-check"></i> 30-day return policy</li>
            <li><i class="fa fa-check"></i> Secure payment options</li>
            <li><i class="fa fa-check"></i> Customer support 24/7</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- PRODUCT DESCRIPTION -->
  <div class="row mt-5">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <h4 class="card-title fw-bold mb-4">Product Description</h4>
          <div class="product-description">
            <?php if (!empty($product['P_Description'])): ?>
              <p class="lead"><?= nl2br(htmlspecialchars($product['P_Description'])) ?></p>
            <?php else: ?>
              <p class="text-muted">No detailed description available for this product.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

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
// Quantity management
function updateQuantity(change) {
    const quantityInput = document.getElementById('quantity');
    let quantity = parseInt(quantityInput.value);
    const maxQuantity = <?= $product['Stock_Quantity'] ?>;
    
    quantity += change;
    if (quantity < 1) quantity = 1;
    if (quantity > maxQuantity) quantity = maxQuantity;
    
    quantityInput.value = quantity;
}

// Size selection
document.querySelectorAll('.size-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.size-option').forEach(opt => opt.classList.remove('active'));
        this.classList.add('active');
        selected_size = this.getAttribute('data-size');
    });
});

// Color selection
document.querySelectorAll('.color-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('active'));
        this.classList.add('active');
        selected_color = this.getAttribute('data-color');
    });
});

// Add to cart function
function addToCart() {
    const quantity = document.getElementById('quantity').value;
    const size = selected_size || '';
    const color = selected_color || '';
    const button = document.getElementById('addToCartBtn');
    
    // Show loading state
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i> Adding...';
    button.disabled = true;
    button.classList.add('btn-loading');
    
    const formData = new FormData();
    formData.append('product_id', <?= $product['Product_ID'] ?>);
    formData.append('quantity', quantity);
    formData.append('size', size);
    formData.append('color', color);
    
    fetch('../api/add_to_cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            // Update cart count
            updateCartCount(data.cart_count);
        } else {
            showNotification(data.message, 'error');
        }
        button.innerHTML = originalText;
        button.disabled = false;
        button.classList.remove('btn-loading');
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error adding to cart', 'error');
        button.innerHTML = originalText;
        button.disabled = false;
        button.classList.remove('btn-loading');
    });
}

// Buy now function
function buyNow() {
    const quantity = document.getElementById('quantity').value;
    const size = selected_size || '';
    const color = selected_color || '';
    const button = document.getElementById('buyNowBtn');
    
    // Show loading state
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i> Processing...';
    button.disabled = true;
    button.classList.add('btn-loading');
    
    const formData = new FormData();
    formData.append('product_id', <?= $product['Product_ID'] ?>);
    formData.append('quantity', quantity);
    formData.append('size', size);
    formData.append('color', color);
    
    fetch('../api/add_to_cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirect to checkout page with product details as URL parameters
            const params = new URLSearchParams({
                product_id: <?= $product['Product_ID'] ?>,
                quantity: quantity,
                size: size,
                color: color,
                buy_now: '1'
            });
            window.location.href = 'checkout.php?' + params.toString();
        } else {
            showNotification(data.message, 'error');
            button.innerHTML = originalText;
            button.disabled = false;
            button.classList.remove('btn-loading');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error processing request', 'error');
        button.innerHTML = originalText;
        button.disabled = false;
        button.classList.remove('btn-loading');
    });
}

// Add to wishlist function
function addToWishlist() {
    const button = document.getElementById('wishlistBtn');
    
    // Show loading state
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    const formData = new FormData();
    formData.append('product_id', <?= $product['Product_ID'] ?>);
    
    fetch('../api/add_to_wishlist.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            // Change heart color to indicate it's in wishlist
            button.style.background = '#ff6b6b';
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

// Update cart count in navbar
function updateCartCount(count) {
    const cartCountElement = document.getElementById('cartCount');
    if (cartCountElement) {
        cartCountElement.textContent = count;
    } else {
        // Create cart count badge if it doesn't exist
        const cartLink = document.querySelector('a[href="alcart.php"]');
        const badge = document.createElement('span');
        badge.id = 'cartCount';
        badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
        badge.textContent = count;
        cartLink.appendChild(badge);
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

// Initialize selections
let selected_size = '<?= $selected_size ?>';
let selected_color = '<?= $selected_color ?>';
</script>
</body>
</html>