<?php
// login.php - Secure Login using MySQLi + Prepared Statements
require_once 'dbConfig.php';
session_start();

// Generate CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF Check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $error = "Both fields are required.";
        } else {
            // Prepared statement to prevent SQL injection
            $stmt = $conn->prepare("SELECT User_ID, Username, Password, Account_Type, Status FROM user WHERE Email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user) {
                if ($user['Status'] !== 'Active') {
                    $error = "Account is inactive.";
                } elseif (password_verify($password, $user['Password'])) {
                    // Secure session
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['User_ID'];
                    $_SESSION['username'] = $user['Username'];
                    $_SESSION['account_type'] = $user['Account_Type'];

                    // Redirect
                    if ($user['Account_Type'] === 'Admin') {
                        header("Location: Admindashboard/admindashboard.php");
                    } else {
                        header("Location: Afterlogin/alhome.php");
                    }
                    exit();
                } else {
                    $error = "Incorrect password.";
                }
            } else {
                $error = "No account found with this email.";
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
    <title>Login - Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="layout.css">
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&display=swap" rel="stylesheet">
    <style>
        .brand-name { font-family: 'Dancing Script', cursive; color: #000000ff; font-size: 2.5rem; }
        .login-card { max-width: 420px; margin: 60px auto; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .btn-primary { background: linear-gradient(45deg, #000000ff, #000000ff); border: none; }
        .btn-primary:hover { background: linear-gradient(45deg, #000000ff, #000000ff); }
    </style>
</head>
<body class="bg-light">

<div class="container">
    <div class="login-card card border-0">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <h1 class="brand-name">Velvet Vogue</h1>
                <p class="text-muted">Sign in to your account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" class="form-control" 
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                               placeholder="Enter your Email" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">Sign In</button>

                <p class="text-center mb-2">
                    New here? <a href="register.php" class="fw-bold text-decoration-none">Create Account</a>
                </p>
                <p class="text-center text-muted small">
                    <a href="forgotpassword.php" class="text-muted">Forgot password?</a>
                </p>
            </form>
        </div>
    </div>

    <div class="text-center mt-4 text-muted small">
        © 2025 Velvet Vogue. All rights reserved.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/js/all.min.js"></script>
</body>
</html>