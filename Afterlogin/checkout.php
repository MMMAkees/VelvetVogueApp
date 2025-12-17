<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// Redirect if not logged in
if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php?next=Afterlogin/checkout.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$username = 'Customer';

// Fetch user details
$stmt = $conn->prepare("SELECT Username, Email, Phone, Address FROM user WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
if ($user_data) {
    $username = $user_data['Username'];
    $email = $user_data['Email'];
    $phone = $user_data['Phone'] ?? '';
    $address = $user_data['Address'] ?? '';
}
$stmt->close();

// === HANDLE BUY NOW ===
$buy_now_product_id = $_GET['product_id'] ?? 0;
$buy_now_quantity = $_GET['quantity'] ?? 1;
$buy_now_size = $_GET['size'] ?? '';
$buy_now_color = $_GET['color'] ?? '';

if ($buy_now_product_id > 0) {
    // Clear existing cart and add the buy now product
    $cart_id = null;
    $cart_stmt = $conn->prepare("SELECT Cart_ID FROM shopping_cart WHERE User_ID_FK = ?");
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    
    if ($cart_result->num_rows > 0) {
        $cart = $cart_result->fetch_assoc();
        $cart_id = $cart['Cart_ID'];
        
        // Clear existing cart items
        $clear_stmt = $conn->prepare("DELETE FROM cart_item WHERE Cart_ID_FK = ?");
        $clear_stmt->bind_param("i", $cart_id);
        $clear_stmt->execute();
        $clear_stmt->close();
    } else {
        // Create cart if it doesn't exist
        $create_cart_stmt = $conn->prepare("INSERT INTO shopping_cart (User_ID_FK) VALUES (?)");
        $create_cart_stmt->bind_param("i", $user_id);
        $create_cart_stmt->execute();
        $cart_id = $conn->insert_id;
        $create_cart_stmt->close();
    }
    $cart_stmt->close();
    
    // Add the buy now product to cart
    // Check if we have the size/color columns
    $check_columns = $conn->query("SHOW COLUMNS FROM cart_item LIKE 'Selected_Size'");
    $has_size_column = $check_columns->num_rows > 0;
    $check_columns = $conn->query("SHOW COLUMNS FROM cart_item LIKE 'Selected_Color'");
    $has_color_column = $check_columns->num_rows > 0;
    
    if ($has_size_column && $has_color_column) {
        $add_stmt = $conn->prepare("INSERT INTO cart_item (Cart_ID_FK, Product_ID_FK, Quantity, Selected_Size, Selected_Color) VALUES (?, ?, ?, ?, ?)");
        $add_stmt->bind_param("iiiss", $cart_id, $buy_now_product_id, $buy_now_quantity, $buy_now_size, $buy_now_color);
    } else {
        $add_stmt = $conn->prepare("INSERT INTO cart_item (Cart_ID_FK, Product_ID_FK, Quantity) VALUES (?, ?, ?)");
        $add_stmt->bind_param("iii", $cart_id, $buy_now_product_id, $buy_now_quantity);
    }
    
    $add_stmt->execute();
    $add_stmt->close();
    
    // Store product attributes in session for order processing
    $_SESSION['buy_now_attributes'] = [
        'size' => $buy_now_size,
        'color' => $buy_now_color
    ];
    
    // Refresh to get the updated cart
    header("Location: checkout.php");
    exit();
}

// === GET CART ITEMS ===
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
$shipping = 0;
$tax = 0;
$total = 0;

if ($cart_id) {
    // Check if we have size/color columns
    $check_columns = $conn->query("SHOW COLUMNS FROM cart_item LIKE 'Selected_Size'");
    $has_size_column = $check_columns->num_rows > 0;
    
    if ($has_size_column) {
        $items_stmt = $conn->prepare("
            SELECT ci.Cart_Item_ID, ci.Quantity, ci.Selected_Size, ci.Selected_Color, 
                   p.Product_ID, p.P_Name, p.P_Price, p.Image_URL
            FROM cart_item ci
            JOIN product p ON ci.Product_ID_FK = p.Product_ID
            WHERE ci.Cart_ID_FK = ?
        ");
    } else {
        $items_stmt = $conn->prepare("
            SELECT ci.Cart_Item_ID, ci.Quantity, p.Product_ID, p.P_Name, p.P_Price, p.Image_URL
            FROM cart_item ci
            JOIN product p ON ci.Product_ID_FK = p.Product_ID
            WHERE ci.Cart_ID_FK = ?
        ");
    }
    
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

// Calculate totals
$shipping = $subtotal > 0 ? 5.00 : 0;
$tax = $subtotal * 0.08;
$total = $subtotal + $shipping + $tax;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Validate required fields
    $required_fields = ['full_name', 'email', 'phone', 'address', 'city', 'zip_code', 'payment_method'];
    $valid = true;
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $valid = false;
            break;
        }
    }
    
    if ($valid && count($cart_items) > 0) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // 1. Create order
            $order_stmt = $conn->prepare("
                INSERT INTO orders (User_ID_FK, Total_Amount, Shipping_Address, Billing_Address, Payment_Method, Status)
                VALUES (?, ?, ?, ?, ?, 'Pending')
            ");
            
            if ($order_stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $shipping_address = $_POST['address'] . ', ' . $_POST['city'] . ', ' . $_POST['zip_code'];
            $billing_address = isset($_POST['same_as_shipping']) && $_POST['same_as_shipping'] ? $shipping_address : 
                              ($_POST['billing_address'] ?? $shipping_address);
            
            $order_stmt->bind_param("idsss", 
                $user_id, 
                $total, 
                $shipping_address, 
                $billing_address, 
                $_POST['payment_method']
            );
            
            $order_stmt->execute();
            $order_id = $conn->insert_id;
            $order_stmt->close();
            
            // 2. Add order items to order_item table
            // Check if we have size/color columns in order_item
            $check_columns = $conn->query("SHOW COLUMNS FROM order_item LIKE 'Selected_Size'");
            $has_size_column = $check_columns->num_rows > 0;
            
            if ($has_size_column) {
                $order_item_stmt = $conn->prepare("
                    INSERT INTO order_item (Order_ID_FK, Product_ID_FK, Quantity, Unit_Price, Subtotal, Selected_Size, Selected_Color)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
            } else {
                $order_item_stmt = $conn->prepare("
                    INSERT INTO order_item (Order_ID_FK, Product_ID_FK, Quantity, Unit_Price, Subtotal)
                    VALUES (?, ?, ?, ?, ?)
                ");
            }
            
            if ($order_item_stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            foreach ($cart_items as $item) {
                $item_subtotal = $item['P_Price'] * $item['Quantity'];
                
                // Get size and color from cart item or session
                $item_size = $item['Selected_Size'] ?? '';
                $item_color = $item['Selected_Color'] ?? '';
                
                // If this was a buy now order, use session attributes
                if (isset($_SESSION['buy_now_attributes']) && count($cart_items) === 1) {
                    $item_size = $_SESSION['buy_now_attributes']['size'];
                    $item_color = $_SESSION['buy_now_attributes']['color'];
                }
                
                if ($has_size_column) {
                    $order_item_stmt->bind_param("iiiddss", 
                        $order_id, 
                        $item['Product_ID'], 
                        $item['Quantity'], 
                        $item['P_Price'],
                        $item_subtotal,
                        $item_size,
                        $item_color
                    );
                } else {
                    $order_item_stmt->bind_param("iiidd", 
                        $order_id, 
                        $item['Product_ID'], 
                        $item['Quantity'], 
                        $item['P_Price'],
                        $item_subtotal
                    );
                }
                $order_item_stmt->execute();
            }
            $order_item_stmt->close();
            
            // 3. Update product stock quantities
            $update_stock_stmt = $conn->prepare("UPDATE product SET Stock_Quantity = Stock_Quantity - ? WHERE Product_ID = ?");
            if ($update_stock_stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            foreach ($cart_items as $item) {
                $update_stock_stmt->bind_param("ii", $item['Quantity'], $item['Product_ID']);
                $update_stock_stmt->execute();
            }
            $update_stock_stmt->close();
            
            // 4. Clear cart
            $clear_cart_stmt = $conn->prepare("DELETE FROM cart_item WHERE Cart_ID_FK = ?");
            if ($clear_cart_stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $clear_cart_stmt->bind_param("i", $cart_id);
            $clear_cart_stmt->execute();
            $clear_cart_stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            // Clear buy now session data
            if (isset($_SESSION['buy_now_attributes'])) {
                unset($_SESSION['buy_now_attributes']);
            }
            
            // Redirect to order confirmation
            $_SESSION['order_success'] = "Order placed successfully! Order ID: #" . $order_id;
            header("Location: order_confirmation.php?order_id=" . $order_id);
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error placing order: " . $e->getMessage();
        }
    } else {
        $error_message = "Please fill all required fields and ensure your cart is not empty.";
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout - Velvet Vogue</title>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .navbar-brand { font-family: 'Dancing Script', cursive; color: #000000ff !important; }
    .btn-primary { background: #8a2be2; border: none; }
    .btn-primary:hover { background: #7a1fd1; }
    .checkout-section { background: white; border-radius: 10px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .order-summary { position: sticky; top: 20px; }
    .product-img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
    .form-control:focus { border-color: #8a2be2; box-shadow: 0 0 0 0.2rem rgba(138, 43, 226, 0.25); }
    .is-invalid { border-color: #dc3545; }
    .error-message { color: #dc3545; font-size: 0.875rem; margin-top: 0.25rem; }
    .attribute-badge { 
        background: #e9ecef; 
        color: #495057; 
        padding: 2px 8px; 
        border-radius: 4px; 
        font-size: 0.75rem; 
        margin-left: 5px;
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

<!-- Checkout Header -->
<section class="bg-light py-4 border-bottom">
  <div class="container">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="alhome.php">Home</a></li>
        <li class="breadcrumb-item"><a href="alcart.php">Cart</a></li>
        <li class="breadcrumb-item active">Checkout</li>
      </ol>
    </nav>
    <h2>Checkout</h2>
    <p class="text-muted">Complete your purchase</p>
  </div>
</section>

<div class="container my-5">
  <?php if (count($cart_items) > 0): ?>
  
  <!-- Error Message -->
  <?php if (isset($error_message)): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fa fa-exclamation-triangle me-2"></i>
    <?= htmlspecialchars($error_message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <form method="post" action="checkout.php" id="checkoutForm">
    <div class="row">
      <!-- Checkout Form -->
      <div class="col-lg-8">
        <!-- Contact Information -->
        <div class="checkout-section">
          <h4 class="mb-4"><i class="fa fa-user me-2"></i>Contact Information</h4>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="full_name" class="form-label">Full Name *</label>
              <input type="text" class="form-control" id="full_name" name="full_name" 
                     value="<?= htmlspecialchars($_POST['full_name'] ?? $username) ?>" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="email" class="form-label">Email *</label>
              <input type="email" class="form-control" id="email" name="email" 
                     value="<?= htmlspecialchars($_POST['email'] ?? $email) ?>" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="phone" class="form-label">Phone Number *</label>
              <input type="tel" class="form-control" id="phone" name="phone" 
                     value="<?= htmlspecialchars($_POST['phone'] ?? $phone) ?>" required>
            </div>
          </div>
        </div>

        <!-- Shipping Address -->
        <div class="checkout-section">
          <h4 class="mb-4"><i class="fa fa-truck me-2"></i>Shipping Address</h4>
          <div class="row">
            <div class="col-12 mb-3">
              <label for="address" class="form-label">Street Address *</label>
              <input type="text" class="form-control" id="address" name="address" 
                     value="<?= htmlspecialchars($_POST['address'] ?? $address) ?>" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="city" class="form-label">City *</label>
              <input type="text" class="form-control" id="city" name="city" 
                     value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="zip_code" class="form-label">ZIP Code *</label>
              <input type="text" class="form-control" id="zip_code" name="zip_code" 
                     value="<?= htmlspecialchars($_POST['zip_code'] ?? '') ?>" required>
            </div>
          </div>
          
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="same_as_shipping" name="same_as_shipping" value="1" checked>
            <label class="form-check-label" for="same_as_shipping">
              Billing address same as shipping address
            </label>
          </div>

          <div id="billing_address" style="display: none;">
            <h5 class="mb-3">Billing Address</h5>
            <div class="row">
              <div class="col-12 mb-3">
                <label for="billing_address_text" class="form-label">Billing Address</label>
                <textarea class="form-control" id="billing_address_text" name="billing_address" rows="3"><?= htmlspecialchars($_POST['billing_address'] ?? '') ?></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Method -->
        <div class="checkout-section">
          <h4 class="mb-4"><i class="fa fa-credit-card me-2"></i>Payment Method</h4>
          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="payment_method" id="credit_card" value="Credit Card" checked required>
              <label class="form-check-label" for="credit_card">
                <i class="fa fa-credit-card me-2"></i>Credit Card
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="payment_method" id="paypal" value="PayPal" required>
              <label class="form-check-label" for="paypal">
                <i class="fa fa-paypal me-2"></i>PayPal
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="payment_method" id="cod" value="Cash on Delivery" required>
              <label class="form-check-label" for="cod">
                <i class="fa fa-money-bill me-2"></i>Cash on Delivery
              </label>
            </div>
          </div>

          <!-- Credit Card Details (shown when credit card is selected) -->
          <div id="credit_card_details">
            <div class="row">
              <div class="col-12 mb-3">
                <label for="card_number" class="form-label">Card Number</label>
                <input type="text" class="form-control" id="card_number" name="card_number" placeholder="1234 5678 9012 3456">
              </div>
              <div class="col-md-6 mb-3">
                <label for="expiry_date" class="form-label">Expiry Date</label>
                <input type="text" class="form-control" id="expiry_date" name="expiry_date" placeholder="MM/YY">
              </div>
              <div class="col-md-6 mb-3">
                <label for="cvv" class="form-label">CVV</label>
                <input type="text" class="form-control" id="cvv" name="cvv" placeholder="123">
              </div>
              <div class="col-12 mb-3">
                <label for="card_holder" class="form-label">Card Holder Name</label>
                <input type="text" class="form-control" id="card_holder" name="card_holder" placeholder="John Doe">
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Order Summary -->
      <div class="col-lg-4">
        <div class="checkout-section order-summary">
          <h4 class="mb-4">Order Summary</h4>
          
          <!-- Cart Items -->
          <div class="mb-4">
            <?php foreach ($cart_items as $item): ?>
            <div class="d-flex align-items-center mb-3">
              <?php
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
              <img src="<?= $product_image_path ?>" class="product-img me-3" alt="<?= htmlspecialchars($item['P_Name']) ?>">
              <div class="flex-grow-1">
                <h6 class="mb-1"><?= htmlspecialchars($item['P_Name']) ?></h6>
                <p class="text-muted mb-0">Qty: <?= $item['Quantity'] ?></p>
                <?php if (!empty($item['Selected_Size'])): ?>
                  <small class="attribute-badge">Size: <?= htmlspecialchars($item['Selected_Size']) ?></small>
                <?php endif; ?>
                <?php if (!empty($item['Selected_Color'])): ?>
                  <small class="attribute-badge">Color: <?= htmlspecialchars($item['Selected_Color']) ?></small>
                <?php endif; ?>
              </div>
              <div class="text-end">
                <strong>$<?= number_format($item['P_Price'] * $item['Quantity'], 2) ?></strong>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Price Breakdown -->
          <div class="border-top pt-3">
            <div class="d-flex justify-content-between mb-2">
              <span>Subtotal</span>
              <span>$<?= number_format($subtotal, 2) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span>Shipping</span>
              <span>$<?= number_format($shipping, 2) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span>Tax</span>
              <span>$<?= number_format($tax, 2) ?></span>
            </div>
            <hr>
            <div class="d-flex justify-content-between mb-3">
              <strong>Total</strong>
              <strong class="text-primary">$<?= number_format($total, 2) ?></strong>
            </div>
          </div>

          <!-- Place Order Button -->
          <button type="submit" name="place_order" value="1" class="btn btn-primary btn-lg w-100">
            <i class="fa fa-lock me-2"></i>Place Order
          </button>
          
          <div class="text-center mt-3">
            <small class="text-muted">
              <i class="fa fa-shield-alt me-1"></i>
              Your payment information is secure and encrypted
            </small>
          </div>
        </div>
      </div>
    </div>
  </form>
  
  <?php else: ?>
  <!-- Empty Cart Message -->
  <div class="text-center py-5">
    <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
    <h3>Your cart is empty</h3>
    <p class="text-muted mb-4">Add some items to your cart before proceeding to checkout.</p>
    <a href="alshop.php" class="btn btn-primary btn-lg">Continue Shopping</a>
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
    // Toggle billing address
    const sameAsShipping = document.getElementById('same_as_shipping');
    const billingAddress = document.getElementById('billing_address');
    
    sameAsShipping.addEventListener('change', function() {
        billingAddress.style.display = this.checked ? 'none' : 'block';
    });

    // Toggle credit card details
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const creditCardDetails = document.getElementById('credit_card_details');
    
    function toggleCreditCardDetails() {
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
        if (selectedMethod && selectedMethod.value === 'Credit Card') {
            creditCardDetails.style.display = 'block';
        } else {
            creditCardDetails.style.display = 'none';
        }
    }
    
    paymentMethods.forEach(method => {
        method.addEventListener('change', toggleCreditCardDetails);
    });
    
    // Initialize on page load
    toggleCreditCardDetails();

    // Form validation
    const form = document.getElementById('checkoutForm');
    form.addEventListener('submit', function(e) {
        let valid = true;
        let firstInvalidField = null;
        
        // Check required fields
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                valid = false;
                field.classList.add('is-invalid');
                if (!firstInvalidField) {
                    firstInvalidField = field;
                }
            } else {
                field.classList.remove('is-invalid');
            }
        });

        if (!valid) {
            e.preventDefault();
            alert('Please fill all required fields.');
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
        }
    });

    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>
</body>
</html>