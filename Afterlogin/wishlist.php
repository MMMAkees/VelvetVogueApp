<?php
session_start();
include __DIR__ . '/../dbConfig.php';

if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php?next=Afterlogin/wishlist.php");
    exit();
}

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$username = 'Customer';
$cart_count = 0;
$wishlist = [];
$msg = '';

// === CART COUNT ===
$stmt = $conn->prepare("
    SELECT SUM(ci.Quantity) as total 
    FROM cart_item ci 
    JOIN shopping_cart sc ON ci.Cart_ID_FK = sc.Cart_ID 
    WHERE sc.User_ID_FK = ?
");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) $cart_count = $row['total'] ?? 0;
    $stmt->close();
} else {
    die("Cart count SQL error: " . $conn->error);
}

// === FETCH USERNAME ===
$stmt = $conn->prepare("SELECT Username FROM user WHERE User_ID = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) $username = $row['Username'];
    $stmt->close();
} else {
    die("Username SQL error: " . $conn->error);
}

// === FETCH WISHLIST ===
// First, let's use a simpler query to avoid column name issues
$stmt = $conn->prepare("
    SELECT w.Wishlist_ID, p.Product_ID, p.P_Name, p.P_Price, p.Image_URL
    FROM wishlist w
    JOIN product p ON w.Product_ID_FK = p.Product_ID
    WHERE w.User_ID_FK = ?
    ORDER BY w.Wishlist_ID DESC
");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $wishlist[] = $row;
    }
    $stmt->close();
} else {
    die("Wishlist SQL error: " . $conn->error);
}

// === REMOVE FROM WISHLIST ===
if (isset($_GET['remove'])) {
    $wishlist_id = intval($_GET['remove']);
    $stmt = $conn->prepare("DELETE FROM wishlist WHERE Wishlist_ID = ? AND User_ID_FK = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $wishlist_id, $user_id);
        if ($stmt->execute()) {
            header("Location: wishlist.php?msg=removed");
            exit();
        }
        $stmt->close();
    }
}

// === ADD TO CART ===
if (isset($_GET['add_to_cart'])) {
    $product_id = intval($_GET['add_to_cart']);
    
    // First, get or create shopping cart for user
    $stmt = $conn->prepare("SELECT Cart_ID FROM shopping_cart WHERE User_ID_FK = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $cart = $result->fetch_assoc();
        $cart_id = $cart['Cart_ID'];
    } else {
        // Create new cart
        $stmt = $conn->prepare("INSERT INTO shopping_cart (User_ID_FK) VALUES (?)");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $cart_id = $conn->insert_id;
    }
    $stmt->close();
    
    // Check if product already in cart
    $stmt = $conn->prepare("SELECT Cart_Item_ID, Quantity FROM cart_item WHERE Cart_ID_FK = ? AND Product_ID_FK = ?");
    $stmt->bind_param("ii", $cart_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update quantity
        $item = $result->fetch_assoc();
        $new_qty = $item['Quantity'] + 1;
        $stmt = $conn->prepare("UPDATE cart_item SET Quantity = ? WHERE Cart_Item_ID = ?");
        $stmt->bind_param("ii", $new_qty, $item['Cart_Item_ID']);
        if ($stmt->execute()) {
            $msg = '<div class="alert alert-success">Quantity updated in cart!</div>';
            $cart_count++;
        }
    } else {
        // Add new item to cart
        $stmt = $conn->prepare("INSERT INTO cart_item (Cart_ID_FK, Product_ID_FK, Quantity) VALUES (?, ?, 1)");
        $stmt->bind_param("ii", $cart_id, $product_id);
        if ($stmt->execute()) {
            $msg = '<div class="alert alert-success">Added to cart!</div>';
            $cart_count++;
        }
    }
    $stmt->close();
}

// Show message from URL parameter
if (isset($_GET['msg']) && $_GET['msg'] === 'removed') {
    $msg = '<div class="alert alert-info">Item removed from wishlist</div>';
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Wishlist - <?= htmlspecialchars($username) ?> | Velvet Vogue</title>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .navbar-brand { font-family: 'Dancing Script', cursive; color: #000000ff !important; }
    .wishlist-item { border-radius: 16px; overflow: hidden; box-shadow: 0 6px 20px rgba(0,0,0,0.08); transition: 0.3s; }
    .wishlist-item:hover { transform: translateY(-5px); }
    .product-img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; }
    .price { font-size: 1.2rem; color: #dc3545; font-weight: bold; }
    .btn-primary { background: #8a2be2; border: none; }
    .btn-primary:hover { background: #7a1fd1; }
    .price { 
    font-size: 1.2rem; 
    color: #dc3545; 
    font-weight: bold; 
}
  </style>
</head>
<body class="bg-light">

<!-- SAME NAVBAR -->
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
        <div class="dropdown">
          <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fa fa-user"></i> <?= htmlspecialchars($username) ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
            <li><a class="dropdown-item" href="my_orders.php">Orders</a></li>
            <li><a class="dropdown-item active" href="wishlist.php">Wishlist</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="?logout=1">Logout</a></li>
          </ul>
        </div>
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

<!-- WISHLIST HEADER -->
<section class="bg-light py-4 border-bottom">
  <div class="container">
    <h2>My Wishlist</h2>
    <p class="text-muted"><?= count($wishlist) ?> item(s)</p>
  </div>
</section>

<!-- WISHLIST CONTENT -->
<div class="container my-5">
  <?= $msg ?>
  
  <?php if (count($wishlist) > 0): ?>
    <div class="row g-4">
      <?php foreach ($wishlist as $item): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card wishlist-item h-100">
          <div class="card-body d-flex align-items-center">
            <?php
            $image_path = "";
            $image_found = false;
            
            // Check different possible image locations
            $possible_paths = [
                "../uploads/" . $item['Image_URL'],
                "../img/products/" . $item['Image_URL'],
                "../img/NEW ARRIVALS/" . $item['Image_URL']
            ];
            
            foreach ($possible_paths as $path) {
                if (file_exists($path) && !empty($item['Image_URL'])) {
                    $image_path = $path;
                    $image_found = true;
                    break;
                }
            }
            ?>
            
            <?php if ($image_found): ?>
                <img src="<?= $image_path ?>" class="product-img me-3" alt="<?= htmlspecialchars($item['P_Name']) ?>">
            <?php else: ?>
                <div class="product-img me-3 bg-light d-flex align-items-center justify-content-center">
                    <i class="fa fa-image text-muted"></i>
                </div>
            <?php endif; ?>
            
            <div class="flex-grow-1">
              <h6 class="mb-1"><?= htmlspecialchars($item['P_Name']) ?></h6>
              <p class="price mb-2">$<?= number_format($item['P_Price'], 2) ?></p>
              <div class="d-flex gap-2">
                <a href="?add_to_cart=<?= $item['Product_ID'] ?>" 
                   class="btn btn-primary btn-sm"><i class="fa fa-cart-plus"></i> Add to Cart</a>
                <a href="?remove=<?= $item['Wishlist_ID'] ?>" 
                   class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove from wishlist?')">
                  <i class="fa fa-trash"></i>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="text-center py-5">
      <i class="fas fa-heart fa-4x text-muted mb-4"></i>
      <h4>Your wishlist is empty</h4>
      <p class="text-muted">Save your favorite items here.</p>
      <a href="alshop.php" class="btn btn-primary btn-lg">Continue Shopping</a>
    </div>
  <?php endif; ?>
</div>

<!-- FOOTER -->
<footer class="bg-light text-dark pt-4 mb-5 border-top">
  <div class="container">
    <p class="text-center small mb-100">© 2025 Velvet Vogue. All Rights Reserved.</p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>