<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// === CHECK ADMIN LOGIN ===
if (empty($_SESSION['admin_id'])) {
    header("Location: ../adminLogin.php");
    exit();
}

$admin_id = intval($_SESSION['admin_id']);
$stmt = $conn->prepare("SELECT Username FROM admin WHERE A_ID = ?");
if (!$stmt) die("DB Error: " . $conn->error);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if (!$admin) {
    session_destroy();
    header("Location: ../adminLogin.php");
    exit();
}

$username = $admin['Username'];

// === LOGOUT HANDLER ===
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../adminLogin.php");
    exit();
}

// === SAFE QUERY FUNCTION ===
function safeQuery($conn, $sql, $types = "", $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error . " | SQL: " . $sql);
        $result = $conn->query($sql);
        if (!$result) return ['total' => 0];
        $row = $result->fetch_assoc();
        $result->free();
        return $row ?? ['total' => 0];
    }
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ?? ['total' => 0];
}

// === DASHBOARD STATS ===
$stats = [];

// Total Products
$stats['total_products'] = safeQuery($conn, "SELECT COUNT(*) as total FROM product")['total'];

// Total Users (Customers)
$stats['total_users'] = safeQuery($conn, "SELECT COUNT(*) as total FROM user WHERE Account_Type = 'customer'")['total'];

// Total Orders
$stats['total_orders'] = safeQuery($conn, "SELECT COUNT(*) as total FROM orders")['total'];

// Total Revenue (Delivered)
$stats['total_revenue'] = safeQuery($conn, "SELECT COALESCE(SUM(Total_Amount), 0) as total FROM orders WHERE Status = 'delivered'")['total'];

// Pending Orders
$stats['pending_orders'] = safeQuery($conn, "SELECT COUNT(*) as total FROM orders WHERE Status = 'pending'")['total'];

// Low Stock Products
$stats['low_stock'] = safeQuery($conn, "SELECT COUNT(*) as total FROM product WHERE Stock_Quantity < 10")['total'];

// === RECENT ORDERS ===
$recent_orders = [];
$sql = "
    SELECT o.Order_ID, o.Total_Amount, o.Status, o.Order_Date, u.Username 
    FROM orders o 
    JOIN user u ON o.User_ID_FK = u.User_ID 
    ORDER BY o.Order_Date DESC 
    LIMIT 5
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
    $result->free();
}

// === POPULAR PRODUCTS ===
$popular_products = [];
$sql = "
    SELECT p.Product_ID, p.P_Name, p.P_Price, p.Stock_Quantity, p.Image_URL, 
           COALESCE(COUNT(oi.Order_Item_ID), 0) as sales_count
    FROM product p
    LEFT JOIN order_item oi ON p.Product_ID = oi.Product_ID_FK
    GROUP BY p.Product_ID
    ORDER BY sales_count DESC
    LIMIT 5
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $popular_products[] = $row;
    }
    $result->free();
}

// === MONTHLY REVENUE CHART ===
$monthly_revenue = [];
$sql = "
    SELECT DATE_FORMAT(Order_Date, '%Y-%m') as month, 
           COALESCE(SUM(Total_Amount), 0) as revenue
    FROM orders 
    WHERE Status = 'delivered'
    GROUP BY DATE_FORMAT(Order_Date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $monthly_revenue[] = $row;
    }
    $result->free();
}
$monthly_revenue = array_reverse($monthly_revenue);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Velvet Vogue</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
        }
        .navbar-brand {
            font-family: 'Dancing Script', cursive;
            font-size: 2rem;
            color: #8a2be2 !important;
        }
        .sidebar {
            background: linear-gradient(135deg, #040404ff, #362b3f5a);
            color: white;
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: white;
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            font-weight: 600;
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        .main-content {
            padding: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: none;
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
            background: #f8f9fa;
        }
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-1 ">
                <div class="p-4">
                    <h4 class="text-white mb-4">
                        <i class="me-4"></i>Admin Panel
                    </h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="admindashboard.php">
                                <i class="fa fa-tachometer-alt"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">
                                <i class="fa fa-box"></i>Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fa fa-shopping-bag"></i>Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fa fa-users"></i>Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="categories.php">
                                <i class="fa fa-tags"></i>Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="offers.php">
                                <i class="fa fa-percentage"></i> Offers & Promotions
                            </a>
                        </li>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="chat.php">
                                <i class="fa fa-comments"></i> Customer Support
                            </a>
                        </li>                        
                        <li class="nav-item mt-4">
                            <a class="nav-link text-warning" href="?logout=1">
                                <i class="fa fa-sign-out-alt"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="h3 mb-1">Dashboard Overview</h2>
                        <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($username) ?>!</p>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="text-muted me-3"><?= date('F j, Y') ?></span>
                        <div class="dropdown">
                            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fa fa-cog me-2"></i>Settings
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php"><i class="fa fa-user me-2"></i>Profile</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #8a2be2, #4b0082); color: white;">
                                <i class="fa fa-box"></i>
                            </div>
                            <div class="stat-number text-primary"><?= $stats['total_products'] ?></div>
                            <div class="stat-label">Total Products</div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                                <i class="fa fa-users"></i>
                            </div>
                            <div class="stat-number text-success"><?= $stats['total_users'] ?></div>
                            <div class="stat-label">Total Customers</div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #fd7e14); color: white;">
                                <i class="fa fa-shopping-bag"></i>
                            </div>
                            <div class="stat-number text-warning"><?= $stats['total_orders'] ?></div>
                            <div class="stat-label">Total Orders</div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #6f42c1); color: white;">
                                <i class="fa fa-dollar-sign"></i>
                            </div>
                            <div class="stat-number text-info">$<?= number_format($stats['total_revenue'], 2) ?></div>
                            <div class="stat-label">Total Revenue</div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545, #e83e8c); color: white;">
                                <i class="fa fa-clock"></i>
                            </div>
                            <div class="stat-number text-danger"><?= $stats['pending_orders'] ?></div>
                            <div class="stat-label">Pending Orders</div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #6c757d, #495057); color: white;">
                                <i class="fa fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-number text-secondary"><?= $stats['low_stock'] ?></div>
                            <div class="stat-label">Low Stock Items</div>
                        </div>
                    </div>
                </div>

                <!-- Charts and Tables Row -->
                <div class="row">
                    <!-- Revenue Chart -->
                    <div class="col-lg-8 mb-4">
                        <div class="chart-container">
                            <h5 class="mb-3">Revenue Overview</h5>
                            <canvas id="revenueChart" height="250"></canvas>
                        </div>
                    </div>
                    <!-- Quick Actions -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="add_product.php" class="btn btn-primary">
                                        <i class="fa fa-plus me-2"></i>Add New Product
                                    </a>
                                    <a href="orders.php" class="btn btn-outline-primary">
                                        <i class="fa fa-eye me-2"></i>View All Orders
                                    </a>
                                    <a href="users.php" class="btn btn-outline-success">
                                        <i class="fa fa-user-plus me-2"></i>Manage Users
                                    </a>
                                    <a href="categories.php" class="btn btn-outline-info">
                                        <i class="fa fa-tags me-2"></i>Manage Categories
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders & Popular Products -->
                <div class="row">
                    <!-- Recent Orders -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Orders</h5>
                                <a href="manage_orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Customer</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($recent_orders) > 0): ?>
                                                <?php foreach ($recent_orders as $order): ?>
                                                    <tr>
                                                        <td>#<?= $order['Order_ID'] ?></td>
                                                        <td><?= htmlspecialchars($order['Username']) ?></td>
                                                        <td>$<?= number_format($order['Total_Amount'], 2) ?></td>
                                                        <td>
                                                            <span class="badge-status badge
                                                                <?= $order['Status'] == 'delivered' ? 'bg-success' : 
                                                                   ($order['Status'] == 'pending' ? 'bg-warning' : 'bg-secondary') ?>">
                                                                <?= ucfirst($order['Status']) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="4" class="text-center text-muted py-3">No recent orders</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Popular Products -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Popular Products</h5>
                                <a href="products.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Price</th>
                                                <th>Stock</th>
                                                <th>Sales</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($popular_products) > 0): ?>
                                                <?php foreach ($popular_products as $product): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <?php if (!empty($product['Image_URL'])): ?>
                                                                    <img src="../uploads/<?= htmlspecialchars($product['Image_URL']) ?>" class="rounded me-2" width="30" height="30" alt="">
                                                                <?php else: ?>
                                                                    <div class="bg-light rounded d-flex align-items-center justify-content-center me-2" style="width:30px;height:30px;">
                                                                        <i class="fa fa-box text-muted"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <span class="text-truncate" style="max-width:150px;">
                                                                    <?= htmlspecialchars($product['P_Name']) ?>
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td>$<?= number_format($product['P_Price'], 2) ?></td>
                                                        <td>
                                                            <span class="badge <?= $product['Stock_Quantity'] > 10 ? 'bg-success' : ($product['Stock_Quantity'] > 0 ? 'bg-warning' : 'bg-danger') ?>">
                                                                <?= $product['Stock_Quantity'] ?>
                                                            </span>
                                                        </td>
                                                        <td><?= $product['sales_count'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="4" class="text-center text-muted py-3">No products found</td></tr>
                                            <?php endif; ?>
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
    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: [<?php 
                    echo implode(',', array_map(function($m) { 
                        return "'" . date('M Y', strtotime($m['month'] . '-01')) . "'"; 
                    }, $monthly_revenue));
                ?>],
                datasets: [{
                    label: 'Monthly Revenue ($)',
                    data: [<?php echo implode(',', array_map('floatval', array_column($monthly_revenue, 'revenue'))); ?>],
                    borderColor: '#8a2be2',
                    backgroundColor: 'rgba(138, 43, 226, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: true } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: value => '$' + value.toLocaleString() }
                    }
                }
            }
        });
    </script>
</body>
</html>