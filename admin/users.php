<?php
session_start();
include __DIR__ . '/../dbConfig.php';

if (empty($_SESSION['admin_id'])) {
    header("Location: ../adminLogin.php");
    exit();
}

// === FILTER SYSTEM ===
$role_filter = $_GET['role'] ?? '';
$valid_filters = ['', 'customer', 'superadmin', 'staffadmin'];
$role_filter = in_array(strtolower($role_filter), $valid_filters) ? strtolower($role_filter) : '';

$users = [];

// === BUILD QUERY BASED ON FILTER ===
if ($role_filter === 'customer') {
    $sql = "
        SELECT 
            User_ID AS id, Username, Email, Phone, 
            Account_Type AS role, Status AS status, 
            Date_Created AS reg_date, 'customer' AS user_type 
        FROM user
        WHERE Account_Type = 'customer'
        ORDER BY reg_date DESC
    ";
} elseif ($role_filter === 'superadmin') {
    $sql = "
        SELECT 
            A_ID AS id, Username, Email, Phone_Number AS Phone, 
            Role AS role, 'active' AS status, 
            Date_Created AS reg_date, 'admin' AS user_type 
        FROM admin
        WHERE Role = 'SuperAdmin'
        ORDER BY reg_date DESC
    ";
} elseif ($role_filter === 'staffadmin') {
    $sql = "
        SELECT 
            A_ID AS id, Username, Email, Phone_Number AS Phone, 
            Role AS role, 'active' AS status, 
            Date_Created AS reg_date, 'admin' AS user_type 
        FROM admin
        WHERE Role = 'StaffAdmin'
        ORDER BY reg_date DESC
    ";
} else {
    $sql = "
        SELECT 
            User_ID AS id, Username, Email, Phone, 
            Account_Type AS role, Status AS status, 
            Date_Created AS reg_date, 'customer' AS user_type 
        FROM user

        UNION ALL

        SELECT 
            A_ID AS id, Username, Email, Phone_Number AS Phone, 
            Role AS role, 'active' AS status, 
            Date_Created AS reg_date, 'admin' AS user_type 
        FROM admin

        ORDER BY reg_date DESC
    ";
}

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users | Velvet Vogue Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { font-family: 'Poppins', sans-serif; background: #f8f9fa; }
    .sidebar { background: linear-gradient(135deg, #040404ff, #362b3f5a); color: white; min-height: 100vh; }
    .sidebar .nav-link { color: white; padding: 14px 20px; margin: 4px 0; border-radius: 12px; transition: all 0.3s ease; font-weight: 500; }
    .sidebar .nav-link:hover { background: rgba(255,255,255,0.15); transform: translateX(6px); }
    .sidebar .nav-link.active { background: rgba(255,255,255,0.25); font-weight: 600; }
    .sidebar .nav-link i { width: 22px; margin-right: 12px; }
    .main-content { padding: 20px; }
    .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .badge-role { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
</style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar p-0">
            <div class="p-4">
                <h4 class="text-white mb-4"><i class="me-2"></i>Admin Panel</h4>
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'admindashboard.php' ? 'active' : '' ?>" href="admindashboard.php"><i class="fa fa-tachometer-alt"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['products.php', 'add_product.php', 'edit_product.php']) ? 'active' : '' ?>" href="products.php"><i class="fa fa-box"></i>Products</a></li>
                    <li class="nav-item"><a class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['orders.php', 'order_details.php']) ? 'active' : '' ?>" href="orders.php"><i class="fa fa-shopping-bag"></i>Orders</a></li>
                    <li class="nav-item"><a class="nav-link active" href="users.php"><i class="fa fa-users"></i>Users</a></li>
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

        <!-- Main Content -->
        <div class="col-md-10 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 mb-1">Manage Users</h2>
                <a href="add_user.php" class="btn btn-primary">Add New User</a>
            </div>

            <!-- FILTER -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-5">
                            <select name="role" class="form-select" onchange="this.form.submit()">
                                <option value="">All Users</option>
                                <option value="customer" <?= $role_filter === 'customer' ? 'selected' : '' ?>>Customers Only</option>
                                <option value="superadmin" <?= $role_filter === 'superadmin' ? 'selected' : '' ?>>SuperAdmin Only</option>
                                <option value="staffadmin" <?= $role_filter === 'staffadmin' ? 'selected' : '' ?>>StaffAdmin Only</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <h5>All Users (<?= count($users) ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width:40px;height:40px;">
                                                        <i class="fa <?= $user['user_type'] === 'admin' ? 'fa-crown text-warning' : 'fa-user text-muted' ?>"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($user['Username']) ?></strong><br>
                                                        <small class="text-muted"><?= $user['user_type'] === 'admin' ? $user['role'] : 'Customer' ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($user['Email']) ?></td>
                                            <td><?= htmlspecialchars($user['Phone'] ?? '—') ?></td>
                                            <td>
                                                <span class="badge-role 
                                                    <?= $user['role'] === 'SuperAdmin' ? 'bg-danger' : 
                                                       ($user['role'] === 'StaffAdmin' ? 'bg-warning text-dark' : 'bg-success') ?>">
                                                    <?= $user['role'] === 'customer' ? 'Customer' : $user['role'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="<?= $user['status'] === 'active' ? 'text-success' : 'text-danger' ?>">
                                                    <?= ucfirst($user['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($user['reg_date'])) ?></td>
                                            <td>
                                                <a href="user_details.php?id=<?= $user['id'] ?>&type=<?= $user['user_type'] ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">No users found</td>
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
</body>
</html>
