<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// Redirect if not logged in
if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php?next=Afterlogin/my_orders.php");
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

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Handle order deletion
if (isset($_GET['delete_order']) && is_numeric($_GET['delete_order'])) {
    $order_id = intval($_GET['delete_order']);
    
    // Verify the order belongs to the user and is cancelled
    $verify_stmt = $conn->prepare("SELECT Status FROM orders WHERE Order_ID = ? AND User_ID_FK = ?");
    $verify_stmt->bind_param("ii", $order_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_row = $verify_result->fetch_assoc()) {
        if (strtolower($verify_row['Status']) === 'cancelled') {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Delete order items first (due to foreign key constraints)
                $delete_items_stmt = $conn->prepare("DELETE FROM order_item WHERE Order_ID_FK = ?");
                $delete_items_stmt->bind_param("i", $order_id);
                $delete_items_stmt->execute();
                $delete_items_stmt->close();
                
                // Delete the order
                $delete_order_stmt = $conn->prepare("DELETE FROM orders WHERE Order_ID = ?");
                $delete_order_stmt->bind_param("i", $order_id);
                $delete_order_stmt->execute();
                $delete_order_stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['order_message'] = "Order #$order_id has been deleted successfully.";
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $_SESSION['order_message'] = "Error deleting order: " . $e->getMessage();
            }
        } else {
            $_SESSION['order_message'] = "Only cancelled orders can be deleted.";
        }
    } else {
        $_SESSION['order_message'] = "Order not found or you don't have permission to delete it.";
    }
    
    $verify_stmt->close();
    header("Location: my_orders.php");
    exit();
}

// Handle order cancellation
if (isset($_POST['cancel_order']) && is_numeric($_POST['cancel_order'])) {
    $order_id = intval($_POST['cancel_order']);
    
    // Verify the order belongs to the user and can be cancelled
    $verify_stmt = $conn->prepare("SELECT Status FROM orders WHERE Order_ID = ? AND User_ID_FK = ?");
    $verify_stmt->bind_param("ii", $order_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_row = $verify_result->fetch_assoc()) {
        if (in_array(strtolower($verify_row['Status']), ['pending', 'processing'])) {
            // Update order status to cancelled
            $cancel_stmt = $conn->prepare("UPDATE orders SET Status = 'Cancelled' WHERE Order_ID = ?");
            $cancel_stmt->bind_param("i", $order_id);
            
            if ($cancel_stmt->execute()) {
                $_SESSION['order_message'] = "Order #$order_id has been cancelled successfully.";
            } else {
                $_SESSION['order_message'] = "Error cancelling order.";
            }
            $cancel_stmt->close();
        } else {
            $_SESSION['order_message'] = "This order cannot be cancelled.";
        }
    } else {
        $_SESSION['order_message'] = "Order not found.";
    }
    
    $verify_stmt->close();
    header("Location: my_orders.php");
    exit();
}

// Fetch user's orders - Using alternative approach to handle missing order items
$orders = [];

// First, get all orders for the user
$orders_stmt = $conn->prepare("
    SELECT 
        Order_ID,
        Order_Date,
        Total_Amount,
        Status,
        Payment_Method,
        Shipping_Address
    FROM orders 
    WHERE User_ID_FK = ?
    ORDER BY Order_Date DESC
");
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

while ($order = $orders_result->fetch_assoc()) {
    // For each order, fetch order items separately
    $items_stmt = $conn->prepare("
        SELECT 
            oi.Quantity,
            p.P_Name,
            p.Product_ID,
            oi.Unit_Price,
            (oi.Quantity * oi.Unit_Price) as Item_Total
        FROM order_item oi
        LEFT JOIN product p ON oi.Product_ID_FK = p.Product_ID
        WHERE oi.Order_ID_FK = ?
    ");
    $items_stmt->bind_param("i", $order['Order_ID']);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    $order_items = [];
    $total_quantity = 0;
    $item_count = 0;
    $product_details = [];
    
    while ($item = $items_result->fetch_assoc()) {
        $order_items[] = $item;
        $total_quantity += $item['Quantity'];
        $item_count++;
        $product_details[] = $item['Quantity'] . ' × ' . $item['P_Name'];
    }
    $items_stmt->close();
    
    // Add calculated fields to order
    $order['item_count'] = $item_count;
    $order['total_quantity'] = $total_quantity;
    $order['product_details'] = implode('; ', $product_details);
    $order['order_items'] = $order_items;
    
    $orders[] = $order;
}
$orders_stmt->close();

// Display success message if exists
$success_message = '';
if (isset($_SESSION['order_success'])) {
    $success_message = $_SESSION['order_success'];
    unset($_SESSION['order_success']);
}

// Display order message if exists
$order_message = '';
if (isset($_SESSION['order_message'])) {
    $order_message = $_SESSION['order_message'];
    unset($_SESSION['order_message']);
}

// Count orders by status for filter buttons
$status_counts = [
    'all' => count($orders),
    'pending' => 0,
    'processing' => 0,
    'shipped' => 0,
    'delivered' => 0,
    'cancelled' => 0
];

foreach ($orders as $order) {
    $status = strtolower($order['Status']);
    if (isset($status_counts[$status])) {
        $status_counts[$status]++;
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Orders - Velvet Vogue</title>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .navbar-brand { font-family: 'Dancing Script', cursive; color: #373638ff !important; }
    .btn-primary { background: #1f1f1fff; border: none; }
    .btn-primary:hover { background: #232323ff; }
    .orders-section { 
        background: white; 
        border-radius: 15px; 
        padding: 2rem; 
        margin-bottom: 2rem; 
        box-shadow: 0 5px 25px rgba(0,0,0,0.08);
        border: 1px solid #f0f0f0;
    }
    .order-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        border: 1px solid #f0f0f0;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .order-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    .order-header {
        border-bottom: 2px solid #f8f9fa;
        padding-bottom: 1rem;
        margin-bottom: 1rem;
    }
    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-pending { 
        background: linear-gradient(135deg, #fff3cd, #ffeaa7); 
        color: #856404; 
        border: 1px solid #ffd43b;
    }
    .status-processing { 
        background: linear-gradient(135deg, #cce7ff, #b3d7ff); 
        color: #004085; 
        border: 1px solid #4dabf7;
    }
    .status-shipped { 
        background: linear-gradient(135deg, #d1ecf1, #bee5eb); 
        color: #0c5460; 
        border: 1px solid #3bc9db;
    }
    .status-delivered { 
        background: linear-gradient(135deg, #d4edda, #c3e6cb); 
        color: #155724; 
        border: 1px solid #51cf66;
    }
    .status-cancelled { 
        background: linear-gradient(135deg, #f8d7da, #f5c6cb); 
        color: #721c24; 
        border: 1px solid #f1aeb5;
    }
    .order-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .info-item {
        text-align: center;
        padding: 0.5rem;
    }
    .info-label {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 0.25rem;
        font-weight: 500;
    }
    .info-value {
        font-size: 1rem;
        font-weight: 600;
        color: #333;
    }
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: #6c757d;
    }
    .empty-icon {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        opacity: 0.5;
    }
    .order-actions {
        border-top: 1px solid #f8f9fa;
        padding-top: 1rem;
        margin-top: 1rem;
    }
    .order-number {
        background: linear-gradient(135deg, #8a2be2, #6f42c1);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-weight: 700;
    }
    .filter-btn.active {
        background: #8a2be2 !important;
        color: white !important;
        border-color: #8a2be2 !important;
    }
    .custom-notification {
        position: fixed;
        top: 100px;
        right: 20px;
        z-index: 1050;
        min-width: 300px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    .product-list {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    .product-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid #e9ecef;
    }
    .product-item:last-child {
        border-bottom: none;
    }
    .product-name {
        font-weight: 500;
        color: #333;
        flex: 1;
    }
    .product-quantity {
        color: #6c757d;
        font-size: 0.9rem;
        margin-left: 1rem;
    }
    .no-items {
        color: #6c757d;
        font-style: italic;
        text-align: center;
        padding: 1rem;
    }
    .product-price {
        color: #8a2be2;
        font-weight: 600;
        margin-left: 1rem;
    }
    .btn-delete {
        background: #dc3545;
        border: none;
        color: white;
    }
    .btn-delete:hover {
        background: #c82333;
        color: white;
    }
    .cancelled-order {
        opacity: 0.8;
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    }
    .cancelled-order:hover {
        opacity: 0.9;
        transform: translateY(-2px);
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
            <li><a class="dropdown-item active" href="my_orders.php">Orders</a></li>
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

<!-- Orders Header -->
<section class="bg-light py-4 border-bottom">
  <div class="container">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="alhome.php">Home</a></li>
        <li class="breadcrumb-item active">My Orders</li>
      </ol>
    </nav>
    <div class="row align-items-center">
      <div class="col-md-8">
        <h2 class="mb-2">My Orders</h2>
        <p class="text-muted mb-0">View and track your order history</p>
      </div>
      <div class="col-md-4 text-md-end">
        <a href="alshop.php" class="btn btn-primary">
          <i class="fa fa-shopping-bag me-2"></i>Continue Shopping
        </a>
      </div>
    </div>
  </div>
</section>

<div class="container my-5">
  <!-- Success Message -->
  <?php if ($success_message): ?>
  <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="fa fa-check-circle me-2"></i>
    <?= htmlspecialchars($success_message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- Order Message -->
  <?php if ($order_message): ?>
  <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
    <i class="fa fa-info-circle me-2"></i>
    <?= htmlspecialchars($order_message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <div class="orders-section">
    <?php if (count($orders) > 0): ?>
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Order History</h4>
        <span class="text-muted" id="orderCount"><?= count($orders) ?> order(s) found</span>
      </div>

      <!-- Order Filter -->
      <div class="mb-4">
        <h5 class="mb-3">Filter Orders</h5>
        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-outline-primary btn-sm filter-btn active" onclick="filterOrders('all')">
            All Orders (<?= $status_counts['all'] ?>)
          </button>
          <button class="btn btn-outline-secondary btn-sm filter-btn" onclick="filterOrders('pending')">
            Pending (<?= $status_counts['pending'] ?>)
          </button>
          <button class="btn btn-outline-info btn-sm filter-btn" onclick="filterOrders('processing')">
            Processing (<?= $status_counts['processing'] ?>)
          </button>
          <button class="btn btn-outline-warning btn-sm filter-btn" onclick="filterOrders('shipped')">
            Shipped (<?= $status_counts['shipped'] ?>)
          </button>
          <button class="btn btn-outline-success btn-sm filter-btn" onclick="filterOrders('delivered')">
            Delivered (<?= $status_counts['delivered'] ?>)
          </button>
          <button class="btn btn-outline-danger btn-sm filter-btn" onclick="filterOrders('cancelled')">
            Cancelled (<?= $status_counts['cancelled'] ?>)
          </button>
        </div>
      </div>

      <?php foreach ($orders as $order): 
        // Determine status class
        $status_class = 'status-pending';
        switch(strtolower($order['Status'])) {
            case 'processing': $status_class = 'status-processing'; break;
            case 'shipped': $status_class = 'status-shipped'; break;
            case 'delivered': $status_class = 'status-delivered'; break;
            case 'cancelled': $status_class = 'status-cancelled'; break;
        }
        
        // Check if order is cancelled for special styling
        $is_cancelled = (strtolower($order['Status']) === 'cancelled');
      ?>
      <div class="order-card <?= $is_cancelled ? 'cancelled-order' : '' ?>" data-status="<?= strtolower($order['Status']) ?>">
        <div class="order-header">
          <div class="row align-items-center">
            <div class="col-md-6">
              <h5 class="mb-2">
                <span class="order-number">Order #<?= $order['Order_ID'] ?></span>
              </h5>
              <p class="text-muted mb-0">
                <i class="fa fa-calendar me-1"></i>
                Placed on <?= date('F j, Y', strtotime($order['Order_Date'])) ?>
              </p>
            </div>
            <div class="col-md-6 text-md-end">
              <span class="status-badge <?= $status_class ?>">
                <?= htmlspecialchars($order['Status']) ?>
              </span>
            </div>
          </div>
        </div>

        <!-- Product List -->
        <?php if (count($order['order_items']) > 0): ?>
        <div class="product-list">
          <h6 class="mb-3"><i class="fa fa-shopping-bag me-2"></i>Order Items</h6>
          <?php foreach ($order['order_items'] as $item): ?>
          <div class="product-item">
            <span class="product-name"><?= htmlspecialchars($item['P_Name']) ?></span>
            <span class="product-quantity">Qty: <?= $item['Quantity'] ?></span>
            <span class="product-price">$<?= number_format($item['Item_Total'], 2) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="product-list">
          <h6 class="mb-3"><i class="fa fa-shopping-bag me-2"></i>Order Items</h6>
          <div class="no-items">
            <i class="fa fa-info-circle me-2"></i>
            No items found for this order
          </div>
        </div>
        <?php endif; ?>

        <div class="order-info-grid">
          <div class="info-item">
            <div class="info-label">Total Amount</div>
            <div class="info-value text-success">$<?= number_format($order['Total_Amount'], 2) ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">Total Items</div>
            <div class="info-value"><?= $order['item_count'] ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">Total Quantity</div>
            <div class="info-value"><?= $order['total_quantity'] ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">Payment Method</div>
            <div class="info-value"><?= htmlspecialchars($order['Payment_Method'] ?? 'N/A') ?></div>
          </div>
        </div>

        <?php if (!empty($order['Shipping_Address'])): ?>
        <div class="mb-3">
          <small class="text-muted">
            <strong>Shipping Address:</strong> 
            <?= htmlspecialchars($order['Shipping_Address']) ?>
          </small>
        </div>
        <?php endif; ?>

        <div class="order-actions">
          <div class="row align-items-center">
            <div class="col-md-8">
              <small class="text-muted">
                <i class="fa fa-info-circle me-1"></i>
                <?php if (strtolower($order['Status']) === 'delivered'): ?>
                  Order was delivered successfully
                <?php elseif (strtolower($order['Status']) === 'shipped'): ?>
                  Your order is on the way
                <?php elseif (strtolower($order['Status']) === 'processing'): ?>
                  We're preparing your order
                <?php elseif (strtolower($order['Status']) === 'cancelled'): ?>
                  This order has been cancelled
                <?php else: ?>
                  Your order is being processed
                <?php endif; ?>
              </small>
            </div>
            <div class="col-md-4 text-md-end">
              <a href="order_details.php?order_id=<?= $order['Order_ID'] ?>" class="btn btn-outline-primary btn-sm">
                <i class="fa fa-eye me-1"></i>View Details
              </a>
              
              <?php if (in_array(strtolower($order['Status']), ['pending', 'processing'])): ?>
              <form method="post" action="my_orders.php" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to cancel this order?')">
                <input type="hidden" name="cancel_order" value="<?= $order['Order_ID'] ?>">
                <button type="submit" class="btn btn-outline-warning btn-sm ms-1">
                  <i class="fa fa-times me-1"></i>Cancel
                </button>
              </form>
              <?php endif; ?>
              
              <?php if ($is_cancelled): ?>
              <a href="my_orders.php?delete_order=<?= $order['Order_ID'] ?>" 
                 class="btn btn-delete btn-sm ms-1" 
                 onclick="return confirm('Are you sure you want to permanently delete this cancelled order? This action cannot be undone.')">
                <i class="fa fa-trash me-1"></i>Delete
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

    <?php else: ?>
      <!-- Empty State -->
      <div class="empty-state">
        <div class="empty-icon">
          <i class="fas fa-shopping-bag"></i>
        </div>
        <h4 class="mb-3">No Orders Yet</h4>
        <p class="text-muted mb-4">You haven't placed any orders yet. Start shopping to see your orders here.</p>
        <a href="alshop.php" class="btn btn-primary btn-lg">
          <i class="fa fa-shopping-bag me-2"></i>Start Shopping
        </a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Order Statistics -->
  <?php if (count($orders) > 0): ?>
  <div class="orders-section">
    <h4 class="mb-4">Order Statistics</h4>
    <div class="row">
      <div class="col-md-3 col-6 mb-3">
        <div class="text-center">
          <div class="h3 text-primary mb-1"><?= count($orders) ?></div>
          <div class="text-muted small">Total Orders</div>
        </div>
      </div>
      <div class="col-md-3 col-6 mb-3">
        <div class="text-center">
          <div class="h3 text-success mb-1">
            <?= array_sum(array_column($orders, 'total_quantity')) ?>
          </div>
          <div class="text-muted small">Items Purchased</div>
        </div>
      </div>
      <div class="col-md-3 col-6 mb-3">
        <div class="text-center">
          <div class="h3 text-info mb-1">
            $<?= number_format(array_sum(array_column($orders, 'Total_Amount')), 2) ?>
          </div>
          <div class="text-muted small">Total Spent</div>
        </div>
      </div>
      <div class="col-md-3 col-6 mb-3">
        <div class="text-center">
          <div class="h3 text-warning mb-1">
            <?= count(array_filter($orders, function($order) { 
                return strtolower($order['Status']) === 'delivered'; 
            })) ?>
          </div>
          <div class="text-muted small">Delivered</div>
        </div>
      </div>
    </div>
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
    
    // Initialize filter buttons
    initializeFilterButtons();
});

function initializeFilterButtons() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
        });
    });
}

function filterOrders(status) {
    const orderCards = document.querySelectorAll('.order-card');
    let visibleCount = 0;
    
    orderCards.forEach(card => {
        const cardStatus = card.getAttribute('data-status');
        if (status === 'all' || cardStatus === status) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Update order count display
    const orderCountElement = document.getElementById('orderCount');
    if (orderCountElement) {
        orderCountElement.textContent = `${visibleCount} order(s) found`;
    }
}

// Enhanced delete confirmation
function confirmDelete(orderId) {
    return confirm('Are you sure you want to permanently delete Order #' + orderId + '? This action cannot be undone and all order data will be lost.');
}

// Enhanced cancel confirmation  
function confirmCancel(orderId) {
    return confirm('Are you sure you want to cancel Order #' + orderId + '? This action cannot be undone.');
}
</script>
</body>
</html>