<?php
// forgotpassword.php - Secure Password Reset Request (Email + Token)
require_once 'dbConfig.php';
session_start();

// Generate CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$email_sent = false;

// For demo: Store reset token in DB (in real app, send email)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF Check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $email = trim($_POST['email']);

        if (empty($email)) {
            $error = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Check if user exists
            $stmt = $conn->prepare("SELECT User_ID, Username FROM user WHERE Email = ? AND Status = 'Active' LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user) {
                // Generate secure reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Remove old tokens
                $conn->query("DELETE FROM password_resets WHERE User_ID_FK = {$user['User_ID']}");

                // Insert new reset token
                $insert_stmt = $conn->prepare("
                    INSERT INTO password_resets (User_ID_FK, Token, Expires_At) 
                    VALUES (?, ?, ?)
                ");
                $insert_stmt->bind_param("iss", $user['User_ID'], $token, $expires);

                if ($insert_stmt->execute()) {
                    // In real app: send email
                    // For assignment: show token on screen (DEMO ONLY)
                    $reset_link = "http://localhost/velvet/resetpassword.php?token=" . urlencode($token);
                    $success = "Password reset link generated!<br><br>";
                    $success .= "<strong>Reset Link (for testing):</strong><br>";
                    $success .= "<a href='$reset_link' target='_blank'>$reset_link</a><br><br>";
                    $success .= "<small>Note: In production, this link would be emailed.</small>";
                    $email_sent = true;
                } else {
                    $error = "Failed to generate reset link. Try again.";
                }
                $insert_stmt->close();
            } else {
                // Security: Don't reveal if email exists
                $success = "If your email is registered, a reset link has been sent.";
                $email_sent = true;
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
    <title>Forgot Password - Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="layout.css">
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&display=swap" rel="stylesheet">
    <style>
        .brand-name { 
            font-family: 'Dancing Script', cursive; 
            color: #8a2be2; 
            font-size: 2.5rem; 
        }
        .reset-card { 
            max-width: 480px; 
            margin: 60px auto; 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
        }
        .btn-primary { 
            background: linear-gradient(45deg, #8a2be2, #ba55d3); 
            border: none; 
        }
        .btn-primary:hover { 
            background: linear-gradient(45deg, #7a1fd1, #a945c7); 
        }
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            font-size: 0.95rem;
        }
    </style>
</head>
<body class="bg-light">

<div class="container">
    <div class="reset-card card border-0">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <h1 class="brand-name">Velvet Vogue</h1>
                <p class="text-muted">Reset your password</p>
            </div>

            <!-- Success Message -->
            <?php if ($success && $email_sent): ?>
                <div class="success-box mb-4">
                    <i class="fas fa-check-circle text-success"></i> 
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Forgot Password Form -->
            <?php if (!$email_sent): ?>
            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="mb-4">
                    <label class="form-label">Enter your email address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" class="form-control" 
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                               placeholder="you@example.com" required>
                    </div>
                    <div class="form-text">
                        We'll send a password reset link to your email.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>

                <div class="text-center">
                    <p class="mb-2">
                        <a href="login.php" class="text-decoration-none">Back to Login</a>
                    </p>
                    <p class="small text-muted">
                        Don't have an account? <a href="register.php">Register</a>
                    </p>
                </div>
            </form>
            <?php else: ?>
                <div class="text-center">
                    <p><a href="login.php" class="btn btn-outline-primary">Return to Login</a></p>
                </div>
            <?php endif; ?>
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