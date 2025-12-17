<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// === CHECK ADMIN LOGIN ===
if (empty($_SESSION['admin_id'])) {
    header("Location: ../adminLogin.php");
    exit();
}

// === UPDATE ORDER STATUS ===
if (isset($_POST['update_status']) && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['new_status'];

    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $conn->prepare("UPDATE orders SET Status = ? WHERE Order_ID = ?");
        $stmt->bind_param("si", $new_status, $order_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: orders.php");
    exit();
}

// === DELETE CANCELLED ORDER ===
if (isset($_GET['delete_order']) && is_numeric($_GET['delete_order'])) {
    $order_id = intval($_GET['delete_order']);
    
    // Verify the order is cancelled before deletion
    $verify_stmt = $conn->prepare("SELECT Status FROM orders WHERE Order_ID = ?");
    $verify_stmt->bind_param("i", $order_id);
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
                
                $_SESSION['admin_message'] = "Order #$order_id has been deleted successfully.";
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

// === FILTERS ===
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build WHERE clause
$where = [];
$params = [];
$types = "";

if ($status_filter && in_array($status_filter, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
    $where[] = "o.Status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($date_filter === 'today') {
    $where[] = "DATE(o.Order_Date) = CURDATE()";
} elseif ($date_filter === 'week') {
    $where[] = "o.Order_Date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($date_filter === 'month') {
    $where[] = "o.Order_Date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

$sql = "
    SELECT o.Order_ID, o.Total_Amount, o.Status, o.Order_Date, u.Username, u.Email 
    FROM orders o 
    JOIN user u ON o.User_ID_FK = u.User_ID
";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY o.Order_Date DESC";

$stmt = $conn->prepare($sql);
if (!empty($types) && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result();

// Display message if exists
$admin_message = '';
if (isset($_SESSION['admin_message'])) {
    $admin_message = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders | Velvet Vogue Admin</title>
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
        .table img { width: 40px; height: 40px; object-fit: cover; border-radius: 8px; }
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .btn-sm { font-size: 0.8rem; }
        .form-select-sm { padding: 6px 12px; font-size: 0.875rem; }
        .btn-delete {
            background: #dc3545;
            border: none;
            color: white;
        }
        .btn-delete:hover {
            background: #c82333;
            color: white;
        }
        .cancelled-row {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            opacity: 0.9;
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
                        <i class="me-2"></i>Admin Panel
                    </h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'admindashboard.php' ? 'active' : '' ?>" 
                               href="admindashboard.php">
                                <i class="fa fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'products.php' || basename($_SERVER['PHP_SELF']) == 'add_product.php' || basename($_SERVER['PHP_SELF']) == 'edit_product.php' ? 'active' : '' ?>" 
                               href="products.php">
                                <i class="fa fa-box"></i> Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="orders.php">
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
                        <h2 class="h3 mb-1">Manage Orders</h2>
                        <p class="text-muted mb-0">View and update order status</p>
                    </div>
                </div>

                <!-- Admin Message -->
                <?php if ($admin_message): ?>
                <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                    <i class="fa fa-info-circle me-2"></i>
                    <?= htmlspecialchars($admin_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All Status</option>
                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="processing" <?= $status_filter === 'processing' ? 'selected' : '' ?>>Processing</option>
                                    <option value="shipped" <?= $status_filter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                    <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date Range</label>
                                <select name="date" class="form-select form-select-sm">
                                    <option value="">All Time</option>
                                    <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
                                    <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                                    <option value="month" <?= $date_filter === 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="fa fa-filter me-2"></i>Apply Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Orders Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">All Orders</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($orders->num_rows > 0): ?>
                                        <?php while ($order = $orders->fetch_assoc()): 
                                            $is_cancelled = (strtolower($order['Status']) === 'cancelled');
                                        ?>
                                            <tr class="<?= $is_cancelled ? 'cancelled-row' : '' ?>">
                                                <td><strong>#<?= $order['Order_ID'] ?></strong></td>
                                                <td>
                                                    <div>
                                                        <div class="fw-semibold"><?= htmlspecialchars($order['Username']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($order['Email']) ?></small>
                                                    </div>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($order['Order_Date'])) ?><br>
                                                    <small class="text-muted"><?= date('g:i A', strtotime($order['Order_Date'])) ?></small>
                                                </td>
                                                <td><strong>$<?= number_format($order['Total_Amount'], 2) ?></strong></td>
                                                <td>
                                                    <span class="badge-status 
                                                        <?= $order['Status'] == 'delivered' ? 'bg-success' : 
                                                           ($order['Status'] == 'pending' ? 'bg-warning' : 
                                                           ($order['Status'] == 'processing' ? 'bg-info' : 
                                                           ($order['Status'] == 'shipped' ? 'bg-primary' : 'bg-danger'))) ?>">
                                                        <?= ucfirst($order['Status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#statusModal<?= $order['Order_ID'] ?>">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <a href="order_details.php?id=<?= $order['Order_ID'] ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fa fa-eye"></i>
                                                        </a>
                                                        <?php if ($is_cancelled): ?>
                                                        <a href="orders.php?delete_order=<?= $order['Order_ID'] ?>" 
                                                           class="btn btn-sm btn-delete" 
                                                           onclick="return confirm('Are you sure you want to permanently delete Order #<?= $order['Order_ID'] ?>? This action cannot be undone.')">
                                                            <i class="fa fa-trash"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Status Update Modal -->
                                            <div class="modal fade" id="statusModal<?= $order['Order_ID'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-sm">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Update Status</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="order_id" value="<?= $order['Order_ID'] ?>">
                                                                <select name="new_status" class="form-select" required>
                                                                    <option value="pending" <?= $order['Status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                                                    <option value="processing" <?= $order['Status'] == 'processing' ? 'selected' : '' ?>>Processing</option>
                                                                    <option value="shipped" <?= $order['Status'] == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                                                    <option value="delivered" <?= $order['Status'] == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                                                    <option value="cancelled" <?= $order['Status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                                </select>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                <i class="fa fa-shopping-bag fa-3x mb-3"></i><br>
                                                No orders found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
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