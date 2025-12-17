<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// Redirect if not logged in
if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php?next=Afterlogin/alcart.php");
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

// === GET CART & CART ID ===
$cart_id = null;
$cart_stmt = $conn->prepare("SELECT Cart_ID FROM shopping_cart WHERE User_ID_FK = ?");
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
if ($cart_row = $cart_result->fetch_assoc()) {
    $cart_id = $cart_row['Cart_ID'];
}
$cart_stmt->close();

$cart_items = [];
$subtotal = 0;

if ($cart_id) {
    $items_stmt = $conn->prepare("
        SELECT ci.Cart_Item_ID, ci.Quantity, p.Product_ID, p.P_Name, p.P_Price, p.Image_URL
        FROM cart_item ci
        JOIN product p ON ci.Product_ID_FK = p.Product_ID
        WHERE ci.Cart_ID_FK = ?
    ");
    $items_stmt->bind_param("i", $cart_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();

    while ($item = $items_result->fetch_assoc()) {
        $item_total = $item['P_Price'] * $item['Quantity'];
        $subtotal += $item_total;
        $cart_items[] = $item;
    }
    $items_stmt->close();
}

// Handle remove item
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $remove_id = intval($_GET['remove']);
    $delete_stmt = $conn->prepare("DELETE FROM cart_item WHERE Cart_Item_ID = ? AND Cart_ID_FK = ?");
    $delete_stmt->bind_param("ii", $remove_id, $cart_id);
    if ($delete_stmt->execute()) {
        $_SESSION['cart_message'] = "Item removed from cart";
    } else {
        $_SESSION['cart_message'] = "Error removing item";
    }
    $delete_stmt->close();
    header("Location: alcart.php");
    exit();
}

// Handle update quantity - Auto update when quantity changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qty'])) {
    if (isset($_POST['qty']) && is_array($_POST['qty'])) {
        $updated = false;
        foreach ($_POST['qty'] as $item_id => $qty) {
            $item_id = intval($item_id);
            $qty = max(1, intval($qty));
            
            // Update the quantity
            $update_stmt = $conn->prepare("UPDATE cart_item SET Quantity = ? WHERE Cart_Item_ID = ? AND Cart_ID_FK = ?");
            $update_stmt->bind_param("iii", $qty, $item_id, $cart_id);
            
            if ($update_stmt->execute()) {
                $updated = true;
            }
            $update_stmt->close();
        }
        
        if ($updated) {
            $_SESSION['cart_message'] = "Cart updated successfully";
        }
    }
    header("Location: alcart.php");
    exit();
}

// Display cart message if exists
$cart_message = '';
if (isset($_SESSION['cart_message'])) {
    $cart_message = $_SESSION['cart_message'];
    unset($_SESSION['cart_message']);
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cart - <?= htmlspecialchars($username) ?> | Velvet Vogue</title>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .navbar-brand { font-family: 'Dancing Script', cursive; color: #000000ff !important; }
    .cart-item { border-bottom: 1px solid #eee; padding: 1rem 0; }
    .cart-item:last-child { border-bottom: none; }
    .btn-primary { background: #8a2be2; border: none; }
    .btn-primary:hover { background: #7a1fd1; }
    .price-tag { color: #dc3545; font-weight: bold; }
    .total-price { font-size: 1.5rem; color: #8a2be2; }
    .empty-cart { text-align: center; padding: 3rem; }
    .cart-message {
        position: fixed;
        top: 100px;
        right: 20px;
        z-index: 1050;
        min-width: 300px;
    }
    .quantity-input {
        width: 80px;
        text-align: center;
    }
    .quantity-input:focus {
        border-color: #8a2be2;
        box-shadow: 0 0 0 0.2rem rgba(138, 43, 226, 0.25);
    }
  </style>
</head>
<body class="bg-light">

<!-- Cart Message Notification -->
<?php if ($cart_message): ?>
<div class="cart-message">
  <div class="alert alert-info alert-dismissible fade show shadow" role="alert">
    <i class="fa fa-info-circle me-2"></i>
    <?= htmlspecialchars($cart_message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
</div>
<?php endif; ?>

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
        <a href="alcart.php" class="btn btn-primary position-relative">
          <i class="fa fa-shopping-cart"></i> Cart
          <?php if (count($cart_items) > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
              <?= array_sum(array_column($cart_items, 'Quantity')) ?>
            </span>
          <?php endif; ?>
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- CART HEADER -->
<section class="bg-light py-4 border-bottom">
  <div class="container">
    <h2>Your Shopping Cart</h2>
    <p class="text-muted">
        <?= count($cart_items) ?> item(s) - 
        Total quantity: <?= array_sum(array_column($cart_items, 'Quantity')) ?>
    </p>
  </div>
</section>

<!-- CART CONTENT -->
<div class="container my-5">
  <?php if (count($cart_items) > 0): ?>
  <form method="post" action="alcart.php" id="cartForm">
    <div class="row">
      <!-- CART ITEMS -->
      <div class="col-lg-8">
        <?php foreach ($cart_items as $item): 
          $item_total = $item['P_Price'] * $item['Quantity'];
          
          // Fix product image path for cart
          $product_image_path = "";
          if (!empty($item['Image_URL'])) {
              if (file_exists("../uploads/" . $item['Image_URL'])) {
                  $product_image_path = "../uploads/" . $item['Image_URL'];
              } elseif (file_exists("../img/NEW ARRIVALS/" . $item['Image_URL'])) {
                  $product_image_path = "../img/NEW ARRIVALS/" . $item['Image_URL'];
              } elseif (file_exists("../img/products/" . $item['Image_URL'])) {
                  $product_image_path = "../img/products/" . $item['Image_URL'];
              } else {
                  $product_image_path = "../img/NEW ARRIVALS/default.jpg";
              }
          } else {
              $product_image_path = "../img/NEW ARRIVALS/default.jpg";
          }
        ?>
        <div class="cart-item d-flex align-items-center">
          <div class="flex-shrink-0">
            <img src="<?= $product_image_path ?>" 
                 class="rounded" style="width:80px; height:80px; object-fit:cover;" 
                 alt="<?= htmlspecialchars($item['P_Name']) ?>">
          </div>
          <div class="flex-grow-1 ms-3">
            <h6 class="mb-1">
              <a href="alproduct_details.php?id=<?= $item['Product_ID'] ?>" class="text-dark text-decoration-none">
                <?= htmlspecialchars($item['P_Name']) ?>
              </a>
            </h6>
            <p class="mb-1 text-muted">$<?= number_format($item['P_Price'], 2) ?> each</p>
          </div>
          <div class="d-flex align-items-center gap-3">
            <input type="number" 
                   name="qty[<?= $item['Cart_Item_ID'] ?>]" 
                   value="<?= $item['Quantity'] ?>" 
                   min="1" 
                   class="form-control quantity-input" 
                   data-item-id="<?= $item['Cart_Item_ID'] ?>"
                   onchange="this.form.submit()">
          </div>
          <div class="text-end ms-3" style="width:100px;">
            <p class="price-tag mb-0">$<?= number_format($item_total, 2) ?></p>
          </div>
          <div class="ms-3">
            <a href="alcart.php?remove=<?= $item['Cart_Item_ID'] ?>" 
               class="btn btn-sm btn-danger" onclick="return confirm('Remove this item from cart?')">
              <i class="fa fa-trash"></i>
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- ORDER SUMMARY -->
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h5 class="card-title">Order Summary</h5>
            <div class="d-flex justify-content-between mb-2">
              <span>Subtotal</span>
              <span class="price-tag">$<?= number_format($subtotal, 2) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-3">
              <span>Shipping</span>
              <span class="text-success">FREE</span>
            </div>
            <hr>
            <div class="d-flex justify-content-between mb-4">
              <strong>Total</strong>
              <strong class="total-price">$<?= number_format($subtotal, 2) ?></strong>
            </div>
            <button type="button" class="btn btn-primary w-100 mb-2" onclick="window.location='checkout.php'">
              Proceed to Checkout
            </button>
            <a href="alshop.php" class="btn btn-outline-secondary w-100">
              Continue Shopping
            </a>
          </div>
        </div>
      </div>
    </div>
  </form>
  <?php else: ?>
  <div class="empty-cart">
    <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
    <h4>Your cart is empty</h4>
    <p class="text-muted">Looks like you haven't added anything yet.</p>
    <a href="alshop.php" class="btn btn-primary btn-lg">Start Shopping</a>
  </div>
  <?php endif; ?>
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
document.addEventListener('DOMContentLoaded', function() {
    // Add loading state when quantity changes
    const quantityInputs = document.querySelectorAll('.quantity-input');
    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Show loading state on the input
            this.style.background = '#f8f9fa';
            this.disabled = true;
            
            // The form will auto-submit due to onchange attribute
        });
    });

    // Auto-dismiss messages after 3 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 3000);
    });
});
</script>
</body>
</html>