<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// Redirect if not logged in
if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php?next=Afterlogin/profile.php");
    exit();
}

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$username = $email = $phone = $profile_pic = 'default.jpg';
$update_msg = $pass_msg = '';

// === CART COUNT (THIS WAS MISSING) ===
$cart_count = 0;
if (!empty($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT SUM(Quantity) as total FROM cart WHERE User_ID_FK = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $cart_count = $row['total'] ?? 0;
        }
        $stmt->close();
    }
}

// === FETCH USER DATA (USING Phone) ===
$stmt = $conn->prepare("SELECT Username, Email, Phone, Profile_Pic FROM user WHERE User_ID = ?");
if (!$stmt) die("DB Error: " . $conn->error);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $username = $row['Username'];
    $email = $row['Email'];
    $phone = $row['Phone'] ?? '';
    $profile_pic = $row['Profile_Pic'] ?? 'default.jpg';
}
$stmt->close();

// === UPLOAD PROFILE PHOTO ===
if (isset($_POST['upload_photo'])) {
    $upload_dir = '../img/profiles/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $file = $_FILES['profile_pic'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($ext, $allowed) && $file['size'] <= 2 * 1024 * 1024) {
        $new_name = $user_id . '.' . $ext;
        $path = $upload_dir . $new_name;

        if (move_uploaded_file($file['tmp_name'], $path)) {
            $stmt = $conn->prepare("UPDATE user SET Profile_Pic = ? WHERE User_ID = ?");
            $stmt->bind_param("si", $new_name, $user_id);
            $stmt->execute();
            $stmt->close();
            $profile_pic = $new_name;
            $update_msg = '<div class="alert alert-success">Photo updated!</div>';
        } else {
            $update_msg = '<div class="alert alert-danger">Upload failed.</div>';
        }
    } else {
        $update_msg = '<div class="alert alert-danger">Invalid file (JPG/PNG, max 2MB).</div>';
    }
}

// === UPDATE PROFILE (USING Phone) ===
if (isset($_POST['update_profile'])) {
    $new_name = trim($_POST['username'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');

    if ($new_name === '') {
        $update_msg = '<div class="alert alert-danger">Name is required.</div>';
    } else {
        $stmt = $conn->prepare("UPDATE user SET Username = ?, Phone = ? WHERE User_ID = ?");
        if (!$stmt) {
            $update_msg = '<div class="alert alert-danger">DB Error: ' . $conn->error . '</div>';
        } else {
            $stmt->bind_param("ssi", $new_name, $new_phone, $user_id);
            if ($stmt->execute()) {
                $update_msg = '<div class="alert alert-success">Profile updated!</div>';
                $username = $new_name;
                $phone = $new_phone;
            } else {
                $update_msg = '<div class="alert alert-danger">Update failed: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
    }
}

// === CHANGE PASSWORD ===
if (isset($_POST['change_password'])) {
    $old_pass = $_POST['old_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if ($new_pass !== $confirm_pass) {
        $pass_msg = '<div class="alert alert-danger">Passwords do not match.</div>';
    } elseif (strlen($new_pass) < 6) {
        $pass_msg = '<div class="alert alert-danger">Password too short (min 6 chars).</div>';
    } else {
        $stmt = $conn->prepare("SELECT Password FROM user WHERE User_ID = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (password_verify($old_pass, $row['Password'])) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE user SET Password = ? WHERE User_ID = ?");
            $stmt->bind_param("si", $hashed, $user_id);
            if ($stmt->execute()) {
                $pass_msg = '<div class="alert alert-success">Password changed!</div>';
            } else {
                $pass_msg = '<div class="alert alert-danger">Failed: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        } else {
            $pass_msg = '<div class="alert alert-danger">Old password incorrect.</div>';
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profile - <?= htmlspecialchars($username) ?> | Velvet Vogue</title>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .navbar-brand { font-family: 'Dancing Script', cursive; color: #000000ff !important; }
    .profile-card { border-radius: 16px; overflow: hidden; box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
    .avatar { width: 140px; height: 140px; border: 5px solid #747474ff; object-fit: cover; }
    .upload-btn { position: absolute; bottom: 10px; right: 10px; }
    .form-control:focus { border-color: #817a88ff; box-shadow: 0 0 0 0.2rem rgba(138, 43, 226, 0.25); }
    .btn-primary { background: #5e5a63ff; border: none; }
    .btn-primary:hover { background: #55505aff; }
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
          <?php if ($cart_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
              <?= $cart_count ?>
            </span>
          <?php endif; ?>
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- PROFILE CONTENT -->
<div class="container my-5">
  <div class="row g-5">
    <!-- PROFILE PHOTO -->
    <div class="col-lg-5">
      <div class="card profile-card text-center p-4 position-relative">
        <div class="position-relative d-inline-block">
          <img src="../img/profiles/<?= htmlspecialchars($profile_pic) ?>" 
               alt="Profile" class="rounded-circle avatar mx-auto mb-3">
          <form method="post" enctype="multipart/form-data" class="upload-btn">
            <input type="file" name="profile_pic" id="fileInput" class="d-none" accept="image/*" onchange="this.form.submit()">
            <label for="fileInput" class="btn btn-sm btn-primary">
              <i class="fa fa-camera"></i>
            </label>
            <input type="hidden" name="upload_photo" value="1">
          </form>
        </div>
        <h4><?= htmlspecialchars($username) ?></h4>
        <p class="text-muted"><?= htmlspecialchars($email) ?></p>
        <hr>
        <p><i class="fa fa-phone"></i> <?= htmlspecialchars($phone ?: 'Not set') ?></p>
      </div>
    </div>

    <!-- FORMS -->
    <div class="col-lg-7">
      <!-- UPDATE PROFILE -->
      <div class="card profile-card mb-4">
        <div class="card-body">
          <h5>Update Profile</h5>
          <?= $update_msg ?>
          <form method="post">
            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($username) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>" placeholder="9876543210">
            </div>
            <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
          </form>
        </div>
      </div>

      <!-- CHANGE PASSWORD -->
      <div class="card profile-card">
        <div class="card-body">
          <h5>Change Password</h5>
          <?= $pass_msg ?>
          <form method="post">
            <div class="mb-3">
              <label class="form-label">Current Password</label>
              <input type="password" name="old_password" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">New Password</label>
              <input type="password" name="new_password" class="form-control" minlength="6" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-control" minlength="6" required>
            </div>
            <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- FOOTER -->
<footer class="bg-light text-dark pt-5 pb-4 mt-5 border-top">
  <div class="container">
    <p class="text-center small mb-0">© 2025 Velvet Vogue. All Rights Reserved.</p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>