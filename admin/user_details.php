<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// Check if admin is logged in
if (empty($_SESSION['admin_id'])) {
    header("Location: ../adminLogin.php");
    exit();
}

$admin_id = intval($_SESSION['admin_id']);
$user_id = intval($_GET['id'] ?? 0);

if ($user_id <= 0) {
    header("Location: users.php");
    exit();
}

/* FETCH ADMIN ROLE */
$stmt = $conn->prepare("SELECT Account_Type FROM user WHERE User_ID = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_result = $stmt->get_result();
$admin = $admin_result->fetch_assoc();
$stmt->close();

$admin_role = $admin['Account_Type'] ?? 'staff';

/* DELETE USER LOGIC */
if (isset($_POST['delete_user'])) {
    // Prevent deleting Super Admin
    $stmt = $conn->prepare("SELECT Account_Type FROM user WHERE User_ID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $target_user = $res->fetch_assoc();
    $stmt->close();

    if ($target_user && $target_user['Account_Type'] !== 'superadmin') {
        $stmt = $conn->prepare("DELETE FROM user WHERE User_ID = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: users.php?deleted=1");
        exit();
    } else {
        $error_msg = "Cannot delete a Super Admin!";
    }
}

/* FETCH USER */
$stmt = $conn->prepare("
    SELECT User_ID, Username, Email, Phone, Address, Account_Type AS Role, Status, Date_Created AS Registration_Date 
    FROM user 
    WHERE User_ID = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: users.php");
    exit();
}

/* FETCH USER ORDERS */
$stmt = $conn->prepare("
    SELECT Order_ID, Total_Amount, Status, Order_Date 
    FROM orders 
    WHERE User_ID_FK = ? 
    ORDER BY Order_Date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User: <?= htmlspecialchars($user['Username']) ?> | Velvet Vogue Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { font-family: 'Poppins', sans-serif; background: #f8f9fa; }
    .sidebar { background: linear-gradient(135deg, #040404ff, #362b3f5a); color: white; min-height: 100vh; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
    .sidebar .nav-link { 
        color: white; 
        padding: 14px 20px; 
        margin: 4px 0; 
        border-radius: 12px; 
        transition: all 0.3s ease; 
        font-weight: 500; 
        display: flex; 
        align-items: center; 
    }
    .sidebar .nav-link i { 
        margin-right: 12px; /* Space between icon and text */ 
    }
    .sidebar .nav-link:hover { background: rgba(255,255,255,0.15); transform: translateX(6px); }
    .sidebar .nav-link.active { background: rgba(255,255,255,0.25); font-weight: 600; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
    .main-content { padding: 20px; }
    .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .badge-role { padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; }
    .btn { border-radius: 30px; padding: 8px 20px; font-weight: 500; }
    .delete-btn { background: #dc3545; border: none; color: #fff; transition: 0.3s; }
    .delete-btn:hover { background: #b02a37; }
</style>

</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- SIDEBAR -->
        <div class="col-md-2 sidebar p-0">
            <div class="p-4">
                <h4 class="text-white mb-4">Admin Panel</h4>
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'admindashboard.php' ? 'active' : '' ?>" href="admindashboard.php"><i class="fa fa-tachometer-alt"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['products.php', 'add_product.php', 'edit_product.php']) ? 'active' : '' ?>" href="products.php"><i class="fa fa-box"></i>Products</a></li>
                    <li class="nav-item"><a class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['orders.php', 'order_details.php']) ? 'active' : '' ?>" href="orders.php"><i class="fa fa-shopping-bag"></i>Orders</a></li>
                    <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' || basename($_SERVER['PHP_SELF']) == 'user_details.php' ? 'active' : '' ?>" href="users.php"><i class="fa fa-users"></i>Users</a></li>
                    <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : '' ?>" href="categories.php"><i class="fa fa-tags"></i>Categories</a></li>
                    <li class="nav-item"><a class="nav-link" href="offers.php"><i class="fa fa-percentage"></i>Offers & Promotions</a></li>
                        <li class="nav-item">
                                <a class="nav-link" href="chat.php">
                                    <i class="fa fa-comments"></i> Customer Support
                                </a>
                            </li>
                    <li class="nav-item mt-4"><a class="nav-link text-warning" href="../adminLogin.php?logout=1"><i class="fa fa-sign-out-alt"></i>Logout</a></li>
                </ul>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="col-md-10 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-1">User Profile</h2>
                    <p class="text-muted mb-0">ID: #<?= $user_id ?> | Registered: <?= date('M j, Y', strtotime($user['Registration_Date'])) ?></p>
                </div>
                <a href="users.php" class="btn btn-outline-secondary">Back to Users</a>
            </div>

            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card text-center h-100">
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width:120px;height:120px;">
                                    <i class="fa fa-user fa-4x text-muted"></i>
                                </div>
                            </div>
                            <h4 class="mb-1"><?= htmlspecialchars($user['Username']) ?></h4>
                            <div class="mb-3">
                                <span class="badge-role <?= $user['Role'] === 'superadmin' ? 'bg-danger' : ($user['Role'] === 'admin' ? 'bg-warning' : 'bg-success') ?>">
                                    <?= ucfirst($user['Role']) ?>
                                </span>
                            </div>

                            <?php if ($user['Role'] !== 'superadmin'): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                <input type="hidden" name="delete_user" value="1">
                                <button type="submit" class="btn delete-btn">
                                    <i class="fa fa-trash me-1"></i> Delete User
                                </button>
                            </form>
                            <?php elseif(isset($error_msg)): ?>
                                <div class="text-danger mt-2"><?= $error_msg ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Contact Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <i class="fa fa-envelope text-primary me-3"></i>
                                        <div>
                                            <small class="text-muted">Email</small><br>
                                            <strong><?= htmlspecialchars($user['Email'] ?? 'Not provided') ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <i class="fa fa-phone text-success me-3"></i>
                                        <div>
                                            <small class="text-muted">Phone</small><br>
                                            <strong><?= htmlspecialchars($user['Phone'] ?? 'Not provided') ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex align-items-start">
                                        <i class="fa fa-map-marker-alt text-danger me-3 mt-1"></i>
                                        <div>
                                            <small class="text-muted">Address</small><br>
                                            <strong><?= nl2br(htmlspecialchars($user['Address'] ?? 'Not provided')) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Order History -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Order History</h5>
                    <span class="text-muted"><?= $orders->num_rows ?> order<?= $orders->num_rows !== 1 ? 's' : '' ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if ($orders->num_rows > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while ($order = $orders->fetch_assoc()): ?>
                                <a href="order_details.php?id=<?= $order['Order_ID'] ?>" class="list-group-item list-group-item-action p-3">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">#<?= $order['Order_ID'] ?></h6>
                                            <small class="text-muted"><?= date('M j, Y \a\t g:i A', strtotime($order['Order_Date'])) ?></small>
                                        </div>
                                        <div class="text-end">
                                            <strong class="d-block">$<?= number_format($order['Total_Amount'], 2) ?></strong>
                                            <span class="badge bg-secondary"><?= ucfirst($order['Status']) ?></span>
                                        </div>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">No orders yet</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
