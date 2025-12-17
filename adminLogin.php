<?php
session_start();
include 'dbConfig.php';

$msg = '';

// === HANDLE LOGOUT ===
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: adminLogin.php");
    exit();
}

// === HANDLE LOGIN ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $conn->prepare("SELECT A_ID, Username, Password, Role FROM admin WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            if (password_verify($password, $admin['Password'])) {
                $_SESSION['admin_id'] = $admin['A_ID'];
                $_SESSION['admin_username'] = $admin['Username'];
                $_SESSION['admin_role'] = $admin['Role'];
                header("Location: admin/admindashboard.php");
                exit();
            } else {
                $msg = '<div class="alert alert-danger">Invalid password.</div>';
            }
        } else {
            $msg = '<div class="alert alert-danger">Admin not found. Check email.</div>';
        }
        $stmt->close();
    } else {
        $msg = '<div class="alert alert-danger">Email and password required.</div>';
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login | Velvet Vogue</title>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #625b5bff, #a490a9ff);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Segoe UI', sans-serif;
    }
    .login-card {
      background: white;
      border-radius: 20px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.2);
      padding: 40px;
      max-width: 420px;
      width: 100%;
    }
    .brand {
      font-family: 'Dancing Script', cursive;
      color: #010101ff;
      font-size: 2.8rem;
      text-align: center;
      margin-bottom: 8px;
    }
    .subtitle {
      text-align: center;
      color: #6c757d;
      font-size: 1.1rem;
      margin-bottom: 25px;
    }
    .btn-login {
      background: #202021ff;
      border: none;
      padding: 14px;
      font-weight: bold;
      border-radius: 50px;
      font-size: 1.1rem;
      transition: 0.3s;
    }
    .btn-login:hover {
      background: #7a1fd1;
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(138,43,226,0.3);
    }
    .admin-icon {
      font-size: 3.8rem;
      color: #101011ff;
      margin-bottom: 15px;
      animation: pulse 2s infinite;
    }
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }
    .form-control {
      border-radius: 50px;
      padding: 14px 20px;
      border: 1px solid #ddd;
      transition: 0.3s;
    }
    .form-control:focus {
      border-color: #7a638fff;
      box-shadow: 0 0 0 0.2rem rgba(138,43,226,0.25);
    }
    .input-group-text {
      border-radius: 50px 0 0 50px;
      background: #f8f9fa;
    }
  </style>
</head>
<body>

<div class="container">
  <div class="row justify-content-center">
    <div class="col-12">
      <div class="login-card mx-auto">
        <div class="text-center">
          <i class="fas fa-user-shield admin-icon"></i>
          <h1 class="brand">Velvet Vogue</h1>
          <p class="subtitle">Admin Panel Access</p>
        </div>

        <?= $msg ?>

        <form method="POST" class="mt-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Email Address</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-envelope"></i></span>
              <input type="email" name="email" class="form-control" 
                     placeholder="Enter Email"  required>
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold">Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-lock"></i></span>
              <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>
          </div>
          <button type="submit" class="btn btn-login text-white w-100 btn-lg">
            Login as Admin
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>