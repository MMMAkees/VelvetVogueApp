<?php
/**
 * Page Name: Admin Dashboard
 * Description: Handles admin overview and navigation
 * Author: Developer
 */
session_start();
include __DIR__ . '/../dbConfig.php';

// === CHECK ADMIN LOGIN ===
if (empty($_SESSION['admin_id'])) {
    header("Location: ../adminLogin.php");
    exit();
}

$admin_id = intval($_SESSION['admin_id']);
$error = '';
$success = '';

// === GET ADMIN DATA ===
$stmt = $conn->prepare("SELECT A_ID, Username, Email, Phone_Number, Role, Date_Created FROM admin WHERE A_ID = ?");
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

// === UPDATE PROFILE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $phone_number = trim($_POST['phone_number']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Basic validation
    if (empty($email) || empty($username)) {
        $error = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Check if username or email already exists (excluding current admin)
        $check_stmt = $conn->prepare("SELECT A_ID FROM admin WHERE (Username = ? OR Email = ?) AND A_ID != ?");
        $check_stmt->bind_param("ssi", $username, $email, $admin_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Username or email already exists";
        } else {
            // If password change is requested
            if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
                if (empty($current_password)) {
                    $error = "Please enter your current password to change password";
                } elseif (empty($new_password)) {
                    $error = "Please enter a new password";
                } elseif (strlen($new_password) < 6) {
                    $error = "New password must be at least 6 characters long";
                } elseif ($new_password !== $confirm_password) {
                    $error = "New passwords do not match";
                } else {
                    // Verify current password
                    $verify_stmt = $conn->prepare("SELECT Password FROM admin WHERE A_ID = ?");
                    $verify_stmt->bind_param("i", $admin_id);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->get_result();
                    $admin_data = $verify_result->fetch_assoc();
                    
                    if (!password_verify($current_password, $admin_data['Password'])) {
                        $error = "Current password is incorrect";
                    } else {
                        // Update with new password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_stmt = $conn->prepare("UPDATE admin SET Email = ?, Username = ?, Phone_Number = ?, Password = ? WHERE A_ID = ?");
                        $update_stmt->bind_param("ssssi", $email, $username, $phone_number, $hashed_password, $admin_id);
                    }
                }
            } else {
                // Update without changing password
                $update_stmt = $conn->prepare("UPDATE admin SET Email = ?, Username = ?, Phone_Number = ? WHERE A_ID = ?");
                $update_stmt->bind_param("sssi", $email, $username, $phone_number, $admin_id);
            }
            
            // Execute update if no errors
            if (empty($error) && isset($update_stmt)) {
                if ($update_stmt->execute()) {
                    $success = "Profile updated successfully";
                    // Refresh admin data
                    $stmt = $conn->prepare("SELECT A_ID, Username, Email, Phone_Number, Role, Date_Created FROM admin WHERE A_ID = ?");
                    $stmt->bind_param("i", $admin_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $admin = $result->fetch_assoc();
                } else {
                    $error = "Failed to update profile: " . $conn->error;
                }
                $update_stmt->close();
            }
        }
        $check_stmt->close();
    }
}

// === LOGOUT HANDLER ===
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../adminLogin.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Velvet Vogue</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
   
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
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: none;
        }
        .profile-header {
            background: linear-gradient(135deg, #8a2be2, #4b0082);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 30px;
            text-align: center;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2.5rem;
        }
        .form-control:focus {
            border-color: #8a2be2;
            box-shadow: 0 0 0 0.2rem rgba(138, 43, 226, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #8a2be2, #4b0082);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #7a1be2, #3b0072);
            transform: translateY(-1px);
        }
        .role-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: inline-block;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="p-4">
                    <h4 class="text-white mb-4">
                        <i class="me-2"></i>Admin Panel
                    </h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="admindashboard.php">
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
                                <i class="fa fa-percentage"></i>Offers & Promotions
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
                        <h2 class="h3 mb-1">Admin Profile</h2>
                        <p class="text-muted mb-0">Manage your account settings</p>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="text-muted me-3"><?= date('F j, Y') ?></span>
                        <div class="dropdown">
                            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fa fa-user me-2"></i>Profile
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php"><i class="fa fa-user me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item" href="system_settings.php"><i class="fa fa-cog me-2"></i>System Settings</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Profile Card -->
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="profile-card">
                            <!-- Profile Header -->
                            <div class="profile-header">
                                <div class="profile-avatar">
                                    <i class="fa fa-user-cog"></i>
                                </div>
                                <h4><?= htmlspecialchars($admin['Username']) ?></h4>
                                <div class="role-badge">
                                    <i class="fa fa-shield-alt me-1"></i>
                                    <?= htmlspecialchars($admin['Role'] ?? 'Administrator') ?>
                                </div>
                            </div>

                            <!-- Profile Form -->
                            <div class="p-4">
                                <?php if ($error): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?= htmlspecialchars($error) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if ($success): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?= htmlspecialchars($success) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Username *</label>
                                            <input type="text" class="form-control" name="username" 
                                                   value="<?= htmlspecialchars($admin['Username']) ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Phone Number</label>
                                            <input type="text" class="form-control" name="phone_number" 
                                                   value="<?= htmlspecialchars($admin['Phone_Number'] ?? '') ?>" 
                                                   placeholder="Optional">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?= htmlspecialchars($admin['Email'] ?? '') ?>" required>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Admin ID</label>
                                            <input type="text" class="form-control" value="<?= $admin['A_ID'] ?>" readonly>
                                            <small class="text-muted">Admin ID cannot be changed</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Role</label>
                                            <input type="text" class="form-control" 
                                                   value="<?= htmlspecialchars($admin['Role'] ?? 'Administrator') ?>" readonly>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Member Since</label>
                                        <input type="text" class="form-control" 
                                               value="<?= date('F j, Y', strtotime($admin['Date_Created'])) ?>" readonly>
                                    </div>

                                    <hr class="my-4">

                                    <h5 class="mb-3">Change Password</h5>
                                    <p class="text-muted">Leave blank if you don't want to change password</p>

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Current Password</label>
                                            <input type="password" class="form-control" name="current_password">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">New Password</label>
                                            <input type="password" class="form-control" name="new_password">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" name="confirm_password">
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="admindashboard.php" class="btn btn-outline-secondary me-md-2">
                                            <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
                                        </a>
                                        <button type="submit" name="update_profile" class="btn btn-primary">
                                            <i class="fa fa-save me-2"></i>Update Profile
                                        </button>
                                    </div>
                                </form>
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