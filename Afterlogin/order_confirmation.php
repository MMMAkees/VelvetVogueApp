<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// Redirect if not logged in
if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$username = 'Customer';

// Fetch user details
$stmt = $conn->prepare("SELECT Username FROM user WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $username = $row['Username'];
}
$stmt->close();

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Fetch order details
$order = null;
$order_items = [];

if ($order_id > 0) {
    // Get order information
    $order_stmt = $conn->prepare("
        SELECT o.*, u.Username, u.Email, u.Phone 
        FROM orders o 
        JOIN user u ON o.User_ID_FK = u.User_ID 
        WHERE o.Order_ID = ? AND o.User_ID_FK = ?
    ");
    $order_stmt->bind_param("ii", $order_id, $user_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    $order = $order_result->fetch_assoc();
    $order_stmt->close();

    if ($order) {
        // Get order items
        $items_stmt = $conn->prepare("
            SELECT oi.*, p.P_Name, p.Image_URL 
            FROM order_item oi 
            JOIN product p ON oi.Product_ID_FK = p.Product_ID 
            WHERE oi.Order_ID_FK = ?
        ");
        $items_stmt->bind_param("i", $order_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        while ($item = $items_result->fetch_assoc()) {
            $order_items[] = $item;
        }
        $items_stmt->close();
    }
}

// If no order found, check session for success message
$success_message = '';
if (isset($_SESSION['order_success'])) {
    $success_message = $_SESSION['order_success'];
    unset($_SESSION['order_success']);
}

// Redirect if no order found and no success message
if (!$order && empty($success_message)) {
    header("Location: my_orders.php");
    exit();
}

// Determine current status for timeline highlighting
$current_status = strtolower($order['Status'] ?? 'pending');
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Order Confirmation - Velvet Vogue</title>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .navbar-brand { font-family: 'Dancing Script', cursive; color: #0a0a0aff !important; }
    .btn-primary { background: #000000ff; border: none; }
    .btn-primary:hover { background: #000000ff; }
    .confirmation-section { 
        background: white; 
        border-radius: 20px; 
        padding: 3rem; 
        margin-bottom: 2rem; 
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        border: 1px solid #f0f0f0;
    }
    .success-icon { 
        font-size: 6rem; 
        color: #28a745; 
        margin-bottom: 2rem;
        background: linear-gradient(135deg, #28a745, #20c997);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .product-img { 
        width: 80px; 
        height: 80px; 
        object-fit: cover; 
        border-radius: 12px; 
        border: 3px solid #f8f9fa;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    /* Professional Order Summary */
    .order-summary-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        padding: 2.5rem;
        color: white;
        position: relative;
        overflow: hidden;
    }
    .order-summary-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 200%;
        background: rgba(255,255,255,0.1);
        transform: rotate(45deg);
    }
    .summary-item {
        text-align: center;
        padding: 1rem;
        position: relative;
        z-index: 2;
    }
    .summary-label {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    .summary-value {
        font-size: 1.4rem;
        font-weight: 700;
        margin-bottom: 0;
    }
    .order-number {
        font-size: 2rem !important;
        font-weight: 800;
    }
    
    /* FIXED Timeline Alignment */
    .timeline-highlight {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 20px;
        padding: 2.5rem;
        margin: 3rem 0;
        border: 2px solid #8a2be2;
        box-shadow: 0 10px 30px rgba(138, 43, 226, 0.2);
    }
    .timeline-container {
        position: relative;
        padding: 2rem 0;
    }
    .timeline-progress {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        position: relative;
        max-width: 900px;
        margin: 0 auto;
    }
    .timeline-progress::before {
        content: '';
        position: absolute;
        top: 35px;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(to right, #8a2be2, #6f42c1);
        z-index: 1;
    }
    .timeline-step {
        position: relative;
        z-index: 2;
        text-align: center;
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .step-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        font-size: 1.5rem;
        border: 4px solid white;
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
        position: relative;
        z-index: 3;
    }
    .step-completed .step-icon {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        transform: scale(1.1);
    }
    .step-pending .step-icon {
        background: #e9ecef;
        color: #6c757d;
    }
    .step-active .step-icon {
        background: linear-gradient(135deg, #8a2be2, #6f42c1);
        color: white;
        transform: scale(1.15);
        box-shadow: 0 8px 25px rgba(138, 43, 226, 0.4);
        animation: pulse 2s infinite;
    }
    .step-content {
        text-align: center;
        max-width: 200px;
        margin-top: 0.5rem;
    }
    .step-title {
        font-weight: 700;
        margin-bottom: 0.5rem;
        font-size: 1rem;
        line-height: 1.2;
    }
    .step-completed .step-title {
        color: #28a745;
    }
    .step-pending .step-title {
        color: #6c757d;
    }
    .step-active .step-title {
        color: #8a2be2;
        font-weight: 800;
    }
    .step-description {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 0.5rem;
        line-height: 1.3;
    }
    .step-date {
        font-size: 0.8rem;
        font-weight: 600;
    }
    .step-completed .step-date {
        color: #28a745;
    }
    .step-active .step-date {
        color: #8a2be2;
    }
    
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(138, 43, 226, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(138, 43, 226, 0); }
        100% { box-shadow: 0 0 0 0 rgba(138, 43, 226, 0); }
    }
    
    /* Professional Order Items */
    .order-items-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1.5rem;
        margin: 2rem 0;
    }
    .order-item-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        border: 1px solid #f0f0f0;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .order-item-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    
    /* Status Badges */
    .status-badge {
        padding: 0.6rem 1.2rem;
        border-radius: 25px;
        font-weight: 700;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-pending { 
        background: linear-gradient(135deg, #fff3cd, #ffeaa7); 
        color: #856404; 
        border: 2px solid #ffd43b;
    }
    .status-processing { 
        background: linear-gradient(135deg, #cce7ff, #b3d7ff); 
        color: #004085; 
        border: 2px solid #4dabf7;
    }
    .status-shipped { 
        background: linear-gradient(135deg, #d1ecf1, #bee5eb); 
        color: #0c5460; 
        border: 2px solid #3bc9db;
    }
    .status-delivered { 
        background: linear-gradient(135deg, #d4edda, #c3e6cb); 
        color: #155724; 
        border: 2px solid #51cf66;
    }
    
    /* Centered Content */
    .centered-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
    
    /* Enhanced Cards */
    .info-card {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        border: 1px solid #f0f0f0;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .timeline-progress {
            flex-direction: column;
            gap: 2rem;
            align-items: center;
        }
        .timeline-progress::before {
            display: none;
        }
        .timeline-step {
            width: 100%;
            flex: none;
        }
        .confirmation-section {
            padding: 2rem 1.5rem;
        }
        .order-summary-card {
            padding: 2rem 1.5rem;
        }
        .order-items-grid {
            grid-template-columns: 1fr;
        }
        .step-content {
            max-width: 100%;
        }
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
        <a href="alcart.php" class="btn btn-outline-primary">
          <i class="fa fa-shopping-cart"></i> Cart
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- Confirmation Header -->
<section class="bg-light py-4 border-bottom">
  <div class="container">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb justify-content-center">
        <li class="breadcrumb-item"><a href="alhome.php">Home</a></li>
        <li class="breadcrumb-item"><a href="my_orders.php">My Orders</a></li>
        <li class="breadcrumb-item active">Order Confirmation</li>
      </ol>
    </nav>
  </div>
</section>

<div class="container my-5">
  <?php if ($order || $success_message): ?>
  
  <!-- Success Message -->
  <?php if ($success_message): ?>
  <div class="alert alert-success alert-dismissible fade show mb-4 text-center" role="alert" style="max-width: 800px; margin: 0 auto; border-radius: 15px; padding: 1.5rem;">
    <i class="fa fa-check-circle me-2 fa-lg"></i>
    <strong><?= htmlspecialchars($success_message) ?></strong>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <div class="centered-content">
    <!-- Main Confirmation -->
    <div class="confirmation-section text-center" style="max-width: 900px;">
      <div class="success-icon">
        <i class="fa fa-check-circle"></i>
      </div>
      <h1 class="mb-3 fw-bold display-5">Order Confirmed!</h1>
      <p class="lead mb-4 text-muted fs-5">Thank you for your purchase. Your order is being processed and you'll receive updates shortly.</p>
      
      <?php if ($order): ?>
      <!-- Professional Order Summary -->
      <div class="order-summary-card mb-5">
        <div class="row align-items-center">
          <div class="col-md-3 summary-item">
            <div class="summary-label">ORDER NUMBER</div>
            <div class="summary-value order-number">#<?= $order['Order_ID'] ?></div>
          </div>
          <div class="col-md-3 summary-item">
            <div class="summary-label">ORDER DATE</div>
            <div class="summary-value"><?= date('M j, Y', strtotime($order['Order_Date'])) ?></div>
          </div>
          <div class="col-md-3 summary-item">
            <div class="summary-label">TOTAL AMOUNT</div>
            <div class="summary-value">$<?= number_format($order['Total_Amount'], 2) ?></div>
          </div>
          <div class="col-md-3 summary-item">
            <div class="summary-label">CURRENT STATUS</div>
            <div>
              <?php
              $status_class = 'status-pending';
              switch(strtolower($order['Status'])) {
                  case 'processing': $status_class = 'status-processing'; break;
                  case 'shipped': $status_class = 'status-shipped'; break;
                  case 'delivered': $status_class = 'status-delivered'; break;
              }
              ?>
              <span class="status-badge <?= $status_class ?>">
                <?= htmlspecialchars($order['Status']) ?>
              </span>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="d-flex justify-content-center gap-3 flex-wrap">
        <a href="my_orders.php" class="btn btn-primary btn-lg px-4">
          <i class="fa fa-list me-2"></i>View All Orders
        </a>
        <a href="alshop.php" class="btn btn-outline-primary btn-lg px-4">
          <i class="fa fa-shopping-bag me-2"></i>Continue Shopping
        </a>
        <?php if ($order): ?>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-lg px-4">
          <i class="fa fa-print me-2"></i>Print Receipt
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- FIXED ORDER JOURNEY ALIGNMENT -->
    <?php if ($order): ?>
    <div class="confirmation-section timeline-highlight" style="max-width: 1000px;">
      <h2 class="text-center mb-4 fw-bold">
        <i class="fa fa-map-marked-alt me-2"></i>Order Journey
      </h2>
      <p class="text-center text-muted mb-5">Track your order progress in real-time</p>
      
      <div class="timeline-container">
        <div class="timeline-progress">
          <?php
          $steps = [
              ['key' => 'confirmed', 'icon' => 'fa-check-circle', 'title' => 'Order Confirmed', 'desc' => 'Order received and confirmed'],
              ['key' => 'processing', 'icon' => 'fa-cog', 'title' => 'Processing', 'desc' => 'Preparing your items'],
              ['key' => 'shipped', 'icon' => 'fa-shipping-fast', 'title' => 'Shipped', 'desc' => 'On the way to you'],
              ['key' => 'delivered', 'icon' => 'fa-home', 'title' => 'Delivered', 'desc' => 'Arrived at destination']
          ];
          
          foreach ($steps as $index => $step):
              $isCompleted = false;
              $isActive = false;
              
              // Determine step status based on current order status
              switch($current_status) {
                  case 'pending':
                      $isCompleted = in_array($step['key'], ['confirmed']);
                      $isActive = $step['key'] === 'confirmed';
                      break;
                  case 'processing':
                      $isCompleted = in_array($step['key'], ['confirmed']);
                      $isActive = $step['key'] === 'processing';
                      break;
                  case 'shipped':
                      $isCompleted = in_array($step['key'], ['confirmed', 'processing']);
                      $isActive = $step['key'] === 'shipped';
                      break;
                  case 'delivered':
                      $isCompleted = in_array($step['key'], ['confirmed', 'processing', 'shipped']);
                      $isActive = $step['key'] === 'delivered';
                      break;
              }
              
              $stepClass = $isActive ? 'step-active' : ($isCompleted ? 'step-completed' : 'step-pending');
          ?>
          <div class="timeline-step <?= $stepClass ?>">
            <div class="step-icon">
              <i class="fa <?= $step['icon'] ?>"></i>
            </div>
            <div class="step-content">
              <div class="step-title"><?= $step['title'] ?></div>
              <div class="step-description"><?= $step['desc'] ?></div>
              <div class="step-date">
                <?php if ($isCompleted): ?>
                  <i class="fa fa-check me-1"></i>Completed
                <?php elseif ($isActive): ?>
                  <i class="fa fa-spinner fa-spin me-1"></i>In Progress
                <?php else: ?>
                  <i class="fa fa-clock me-1"></i>Pending
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      
      <div class="text-center mt-4">
        <p class="text-muted">
          <i class="fa fa-info-circle me-2"></i>
          Current Status: <strong class="text-primary"><?= ucfirst($current_status) ?></strong>
        </p>
      </div>
    </div>
    <?php endif; ?>

    <!-- Order Items -->
    <?php if ($order && count($order_items) > 0): ?>
    <div class="confirmation-section" style="max-width: 1000px;">
      <h3 class="text-center mb-4 fw-bold">
        <i class="fa fa-shopping-bag me-2"></i>Order Items
      </h3>
      
      <div class="order-items-grid">
        <?php foreach ($order_items as $item): ?>
        <div class="order-item-card">
          <div class="d-flex align-items-center">
            <div class="flex-shrink-0">
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
              <img src="<?= $product_image_path ?>" class="product-img" alt="<?= htmlspecialchars($item['P_Name']) ?>">
            </div>
            <div class="flex-grow-1 ms-4">
              <h6 class="mb-2 fw-bold text-dark"><?= htmlspecialchars($item['P_Name']) ?></h6>
              <div class="row text-muted">
                <div class="col-6">
                  <small><strong>Qty:</strong> <?= $item['Quantity'] ?></small>
                </div>
                <div class="col-6">
                  <small><strong>Price:</strong> $<?= number_format($item['Unit_Price'], 2) ?></small>
                </div>
                <div class="col-12 mt-2">
                  <small><strong>Subtotal:</strong> <span class="text-success fw-bold">$<?= number_format($item['Subtotal'], 2) ?></span></small>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Shipping & Support Information -->
    <?php if ($order): ?>
    <div class="confirmation-section" style="max-width: 1000px;">
      <div class="row">
        <div class="col-md-6">
          <div class="info-card h-100">
            <h5 class="mb-3 fw-bold text-primary">
              <i class="fa fa-truck me-2"></i>Shipping Information
            </h5>
            <div class="text-muted">
              <p class="mb-3">
                <strong class="text-dark">Delivery Address:</strong><br>
                <span class="fs-6"><?= nl2br(htmlspecialchars($order['Shipping_Address'])) ?></span>
              </p>
              <p class="mb-0">
                <strong class="text-dark">Payment Method:</strong><br>
                <span class="fs-6"><?= htmlspecialchars($order['Payment_Method']) ?></span>
              </p>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-card h-100">
            <h5 class="mb-3 fw-bold text-primary">
              <i class="fa fa-headset me-2"></i>Customer Support
            </h5>
            <div class="text-muted">
              <p class="mb-3">
                <i class="fa fa-phone me-2 text-primary"></i>
                <strong class="text-dark">Support Line:</strong><br>
                <span class="fs-6">+1 (555) 123-4567</span>
              </p>
              <p class="mb-3">
                <i class="fa fa-envelope me-2 text-primary"></i>
                <strong class="text-dark">Email Support:</strong><br>
                <span class="fs-6">support@velvetvogue.com</span>
              </p>
              <p class="mb-0">
                <i class="fa fa-clock me-2 text-primary"></i>
                <strong class="text-dark">Business Hours:</strong><br>
                <span class="fs-6">Mon-Fri: 9AM-6PM EST</span>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
  
  <?php else: ?>
  <!-- No Order Found -->
  <div class="text-center py-5 centered-content">
    <i class="fas fa-exclamation-circle fa-4x text-muted mb-4"></i>
    <h3>Order Not Found</h3>
    <p class="text-muted mb-4">We couldn't find the order you're looking for.</p>
    <a href="my_orders.php" class="btn btn-primary me-2">View My Orders</a>
    <a href="alshop.php" class="btn btn-outline-primary">Continue Shopping</a>
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
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // Print functionality
    const printButton = document.querySelector('button[onclick="window.print()"]');
    if (printButton) {
        printButton.addEventListener('click', function() {
            window.print();
        });
    }
});

// Print styles
const printStyle = `
@media print {
    .navbar, .breadcrumb, .btn, footer {
        display: none !important;
    }
    body {
        background: white !important;
    }
    .confirmation-section {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        margin: 1rem 0 !important;
    }
    .centered-content {
        align-items: flex-start !important;
    }
    .timeline-highlight {
        border: 2px solid #333 !important;
    }
}
`;
const styleSheet = document.createElement("style");
styleSheet.innerText = printStyle;
document.head.appendChild(styleSheet);
</script>
</body>
</html>