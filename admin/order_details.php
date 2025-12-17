<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// === CHECK ADMIN LOGIN ===
if (empty($_SESSION['admin_id'])) {
    header("Location: ../adminLogin.php");
    exit();
}

// === GET ORDER ID ===
$order_id = intval($_GET['id'] ?? 0);
if ($order_id <= 0) {
    header("Location: orders.php");
    exit();
}

// === UPDATE STATUS ===
if (isset($_POST['update_status'])) {
    $new_status = $_POST['new_status'];
    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $conn->prepare("UPDATE orders SET Status = ? WHERE Order_ID = ?");
        $stmt->bind_param("si", $new_status, $order_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: order_details.php?id=$order_id");
    exit();
}

// === DELETE CANCELLED ORDER ===
if (isset($_GET['delete_order']) && is_numeric($_GET['delete_order'])) {
    $delete_order_id = intval($_GET['delete_order']);
    
    // Verify the order is cancelled before deletion
    $verify_stmt = $conn->prepare("SELECT Status FROM orders WHERE Order_ID = ?");
    $verify_stmt->bind_param("i", $delete_order_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_row = $verify_result->fetch_assoc()) {
        if (strtolower($verify_row['Status']) === 'cancelled') {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Delete order items first (due to foreign key constraints)
                $delete_items_stmt = $conn->prepare("DELETE FROM order_item WHERE Order_ID_FK = ?");
                $delete_items_stmt->bind_param("i", $delete_order_id);
                $delete_items_stmt->execute();
                $delete_items_stmt->close();
                
                // Delete the order
                $delete_order_stmt = $conn->prepare("DELETE FROM orders WHERE Order_ID = ?");
                $delete_order_stmt->bind_param("i", $delete_order_id);
                $delete_order_stmt->execute();
                $delete_order_stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['admin_message'] = "Order #$delete_order_id has been deleted successfully.";
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $_SESSION['admin_message'] = "Error deleting order: " . $e->getMessage();
            }
        } else {
            $_SESSION['admin_message'] = "Only cancelled orders can be deleted.";
        }
    } else {
        $_SESSION['admin_message'] = "Order not found.";
    }
    
    $verify_stmt->close();
    header("Location: orders.php");
    exit();
}

// === FETCH ORDER DETAILS ===
$stmt = $conn->prepare("
    SELECT o.*, u.Username, u.Email, u.Phone, u.Address 
    FROM orders o 
    JOIN user u ON o.User_ID_FK = u.User_ID 
    WHERE o.Order_ID = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: orders.php");
    exit();
}

// === FETCH ORDER ITEMS ===
$stmt = $conn->prepare("
    SELECT oi.*, p.P_Name, p.Image_URL 
    FROM order_item oi 
    JOIN product p ON oi.Product_ID_FK = p.Product_ID 
    WHERE oi.Order_ID_FK = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result();

// Check if order is cancelled
$is_cancelled = (strtolower($order['Status']) === 'cancelled');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= $order_id ?> | Velvet Vogue Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f8f9fa; }
        .sidebar {
            background: linear-gradient(135deg, #040404ff, #362b3f5a);
            color: white;
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: white;
            padding: 14px 20px;
            margin: 4px 0;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.15);
            transform: translateX(6px);
        }
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.25);
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .sidebar .nav-link i {
            width: 22px;
            margin-right: 12px;
            font-size: 1.1rem;
        }
        .sidebar .text-warning {
            color: #ffc107 !important;
        }
        .sidebar .text-warning:hover {
            background: rgba(255,193,7,0.2);
        }
        .main-content { padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .order-header {
            background: linear-gradient(135deg, #8a2be2, #4b0082);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px;
        }
        .product-img {
            width: 60px; height: 60px; object-fit: cover; border-radius: 10px;
        }
        .badge-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .total-row {
            font-size: 1.1rem;
            font-weight: 700;
        }
        .status-form {
            display: inline-block;
        }
        .status-select {
            min-width: 140px;
            font-size: 0.9rem;
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
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR - EXACT SAME AS DASHBOARD -->
            <div class="col-md-2 sidebar p-0">
                <div class="p-4">
                    <h4 class="text-white mb-4">
                        Admin Panel
                    </h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'admindashboard.php' ? 'active' : '' ?>" 
                               href="admindashboard.php">
                                <i class="fa fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['products.php', 'add_product.php', 'edit_product.php']) ? 'active' : '' ?>" 
                               href="products.php">
                                <i class="fa fa-box"></i> Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'orders.php' || basename($_SERVER['PHP_SELF']) == 'order_details.php' ? 'active' : '' ?>" 
                               href="orders.php">
                                <i class="fa fa-shopping-bag"></i> Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>" 
                               href="users.php">
                                <i class="fa fa-users"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : '' ?>" 
                               href="categories.php">
                                <i class="fa fa-tags"></i> Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="offers.php">
                                <i class="fa fa-percentage"></i>Offers & Promotions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="chat.php">
                                <i class="fa fa-comments"></i> Customer Support
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-warning" href="../adminLogin.php?logout=1">
                                <i class="fa fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="h3 mb-1">Order Details #<?= $order_id ?></h2>
                        <p class="text-muted mb-0">Placed on <?= date('F j, Y \a\t g:i A', strtotime($order['Order_Date'])) ?></p>
                    </div>
                    <div class="btn-group">
                        <a href="orders.php" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-left me-2"></i>Back to Orders
                        </a>
                        <?php if ($is_cancelled): ?>
                        <a href="order_details.php?delete_order=<?= $order_id ?>" 
                           class="btn btn-delete" 
                           onclick="return confirm('Are you sure you want to permanently delete Order #<?= $order_id ?>? This action cannot be undone.')">
                            <i class="fa fa-trash me-2"></i>Delete Order
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Header -->
                <div class="card mb-4 <?= $is_cancelled ? 'cancelled-order' : '' ?>">
                    <div class="order-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1">Order Status</h4>
                                <div class="d-flex align-items-center">
                                    <span class="badge-status me-3
                                        <?= $order['Status'] == 'delivered' ? 'bg-success' : 
                                           ($order['Status'] == 'pending' ? 'bg-warning' : 
                                           ($order['Status'] == 'processing' ? 'bg-info' : 
                                           ($order['Status'] == 'shipped' ? 'bg-primary' : 'bg-danger'))) ?>">
                                        <?= ucfirst($order['Status']) ?>
                                    </span>
                                    <form method="POST" class="status-form">
                                        <select name="new_status" class="form-select status-select" onchange="this.form.submit()">
                                            <option value="pending" <?= $order['Status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="processing" <?= $order['Status'] == 'processing' ? 'selected' : '' ?>>Processing</option>
                                            <option value="shipped" <?= $order['Status'] == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                            <option value="delivered" <?= $order['Status'] == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                            <option value="cancelled" <?= $order['Status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <h4 class="mb-0">$<?= number_format($order['Total_Amount'], 2) ?></h4>
                                <small>Total Amount</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Customer Info -->
                    <div class="col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fa fa-user me-2"></i>Customer Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong><?= htmlspecialchars($order['Username']) ?></strong>
                                </div>
                                <div class="text-muted mb-2">
                                    <i class="fa fa-envelope me-2"></i><?= htmlspecialchars($order['Email']) ?>
                                </div>
                                <div class="text-muted mb-2">
                                    <i class="fa fa-phone me-2"></i><?= htmlspecialchars($order['Phone'] ?? 'Not provided') ?>
                                </div>
                                <div class="text-muted">
                                    <i class="fa fa-map-marker-alt me-2"></i><?= nl2br(htmlspecialchars($order['Address'] ?? 'Not provided')) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="col-lg-8 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fa fa-box me-2"></i>Order Items</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Price</th>
                                                <th>Qty</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $items_total = 0;
                                            while ($item = $items->fetch_assoc()): 
                                                $unit_price = $item['Unit_Price'] ?? 0;
                                                $quantity = $item['Quantity'] ?? 0;
                                                $subtotal = $unit_price * $quantity;
                                                $items_total += $subtotal;
                                            ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if ($item['Image_URL'] && file_exists("../uploads/" . $item['Image_URL'])): ?>
                                                                <img src="../uploads/<?= htmlspecialchars($item['Image_URL']) ?>" class="product-img me-3" alt="">
                                                            <?php else: ?>
                                                                <div class="bg-light rounded d-flex align-items-center justify-content-center me-3" style="width:60px;height:60px;">
                                                                    <i class="fa fa-image text-muted"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <div class="fw-semibold"><?= htmlspecialchars($item['P_Name']) ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>$<?= number_format($unit_price, 2) ?></td>
                                                    <td><?= $quantity ?></td>
                                                    <td><strong>$<?= number_format($subtotal, 2) ?></strong></td>
                                                </tr>
                                            <?php endwhile; ?>
                                            <tr class="total-row">
                                                <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                                <td><strong class="text-primary">$<?= number_format($order['Total_Amount'], 2) ?></strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>