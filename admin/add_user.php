<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// Check if admin is logged in
if (empty($_SESSION['admin_id'])) {
    header("Location: ../adminLogin.php");
    exit();
}

$admin_id = intval($_SESSION['admin_id']);

/* FETCH LOGGED-IN ADMIN ROLE */
$stmt = $conn->prepare("SELECT Role FROM admin WHERE A_ID = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_result = $stmt->get_result();
$admin = $admin_result->fetch_assoc();
$stmt->close();

$admin_role = $admin['Role'] ?? 'staff';

$success_msg = '';
$error_msg = '';

if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $password = trim($_POST['password']);
    $role = $_POST['role'] ?? 'customer';
    $status = $_POST['status'] ?? 'active';

    // Basic validation
    if (empty($username) || empty($email) || empty($password)) {
        $error_msg = "Username, Email, and Password are required.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT User_ID FROM user WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error_msg = "Email already exists!";
            $stmt->close();
        } else {
            $stmt->close(); // Close previous select statement

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert into database
            $stmt = $conn->prepare("INSERT INTO user (Username, Email, Phone, Address, Password, Account_Type, Status, Date_Created) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssssss", $username, $email, $phone, $address, $hashed_password, $role, $status);
            if ($stmt->execute()) {
                $success_msg = "User added successfully!";
                $_POST = []; // clear form fields
            } else {
                $error_msg = "Failed to add user. Please try again.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add User | Velvet Vogue Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { font-family: 'Poppins', sans-serif; background: #f8f9fa; }
    .sidebar { background: linear-gradient(135deg, #040404ff, #362b3f5a); color: white; min-height: 100vh; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
    .sidebar .nav-link { color: white; padding: 14px 20px; margin: 4px 0; border-radius: 12px; transition: all 0.3s ease; font-weight: 500; display: flex; align-items: center; }
    .sidebar .nav-link i { margin-right: 12px; /* Space between icon and text */ }
    .sidebar .nav-link:hover { background: rgba(255,255,255,0.15); transform: translateX(6px); }
    .sidebar .nav-link.active { background: rgba(255,255,255,0.25); font-weight: 600; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
    .main-content { padding: 20px; }
    .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .btn { border-radius: 30px; padding: 8px 20px; font-weight: 500; }
</style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- SIDEBAR -->
        <div class="col-md-2 sidebar p-0">
            <div class="p-4">
                <h4 class="text-white mb-4">
                    <i class="me-4"></i>Admin Panel
                </h4>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'admindashboard.php' ? 'active' : '' ?>" href="admindashboard.php">
                            <i class="fa fa-tachometer-alt"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['products.php', 'add_product.php', 'edit_product.php']) ? 'active' : '' ?>" href="products.php">
                            <i class="fa fa-box"></i>Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['orders.php', 'order_details.php']) ? 'active' : '' ?>" href="orders.php">
                            <i class="fa fa-shopping-bag"></i>Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' || basename($_SERVER['PHP_SELF']) == 'add_user.php' ? 'active' : '' ?>" href="users.php">
                            <i class="fa fa-users"></i>Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : '' ?>" href="categories.php">
                            <i class="fa fa-tags"></i>Categories
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
                            <i class="fa fa-sign-out-alt"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="col-md-10 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 mb-0">Add New User</h2>
                <a href="users.php" class="btn btn-outline-secondary">Back to Users</a>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?= $success_msg ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-danger"><?= $error_msg ?></div>
            <?php endif; ?>

            <div class="card p-4">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="customer" <?= (($_POST['role'] ?? '') === 'customer') ? 'selected' : '' ?>>Customer</option>
                                <option value="super_admin" <?= (($_POST['role'] ?? '') === 'super_admin') ? 'selected' : '' ?>>Super Admin</option>
                                <option value="staff_admin" <?= (($_POST['role'] ?? '') === 'staff_admin') ? 'selected' : '' ?>>Staff Admin</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= (($_POST['status'] ?? '') === 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= (($_POST['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-12 mt-3">
                            <button type="submit" name="add_user" class="btn btn-primary"><i class="fa fa-plus me-2"></i>Add User</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
