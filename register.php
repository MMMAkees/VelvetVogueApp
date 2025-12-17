<?php
// register.php - Secure User Registration with MySQLi + Password Hashing + CSRF
require_once 'dbConfig.php';
session_start();

// Generate CSRF Token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Sanitize inputs
        $username = trim($_POST['username']);
        $email    = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm  = $_POST['confirm_password'];
        $phone    = trim($_POST['phone'] ?? '');
        $address  = trim($_POST['address'] ?? '');

        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
            $error = "All required fields must be filled.";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Check if email already exists
            $check_stmt = $conn->prepare("SELECT User_ID FROM user WHERE Email = ? LIMIT 1");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $error = "Email already registered. Please login.";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user - CORRECTED SQL
                $insert_stmt = $conn->prepare("
                    INSERT INTO user 
                    (Username, Password, Email, Phone, Address, Account_Type, Status, Date_Created) 
                    VALUES (?, ?, ?, ?, ?, 'Customer', 'Active', NOW())
                ");
                
                // Debug: Check if prepare failed
                if (!$insert_stmt) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $insert_stmt->bind_param("sssss", $username, $hashed_password, $email, $phone, $address);

                    if ($insert_stmt->execute()) {
                        $success = true;
                        // Auto-login after registration (optional)
                        $user_id = $conn->insert_id;
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $username;
                        $_SESSION['account_type'] = 'Customer';
                    } else {
                        $error = "Registration failed: " . $insert_stmt->error;
                    }
                    $insert_stmt->close();
                }
            }
            $check_stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="layout.css">
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .brand-name { 
            font-family: 'Dancing Script', cursive; 
            color: #5f4e6eff; 
            font-size: 2.5rem; 
        }
        .register-card { 
            max-width: 500px; 
            margin: 40px auto; 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
        }
        .btn-primary { 
            background: linear-gradient(45deg, #614d73, #8a2be2); 
            border: none; 
        }
        .btn-primary:hover { 
            background: linear-gradient(45deg, #40334dff, #69596dff); 
        }
        .form-control:focus {
            border-color: #544e5aff;
            box-shadow: 0 0 0 0.2rem rgba(138, 43, 226, 0.25);
        }
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
    </style>
</head>
<body class="bg-light">

<div class="container">
    <div class="register-card card border-0">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <h1 class="brand-name">Velvet Vogue</h1>
                <p class="text-muted">Create your account</p>
            </div>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="POST" novalidate id="registrationForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" name="username" class="form-control" 
                                   value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" 
                                   placeholder="Enter Name" required>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                                   placeholder="Enter Email" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" class="form-control" 
                                   placeholder="Enter Password" required minlength="6">
                            <button type="button" class="btn btn-outline-secondary toggle-password" 
                                    data-target="password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="confirm_password" class="form-control" 
                                   placeholder="Repeat password" required>
                            <button type="button" class="btn btn-outline-secondary toggle-password" 
                                    data-target="confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Phone (Optional)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                        <input type="tel" name="phone" class="form-control" 
                               value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" 
                               placeholder="Enter Phone Number">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Address (Optional)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-home"></i></span>
                        <textarea name="address" class="form-control" rows="2" 
                                  placeholder="Enter Address"><?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?></textarea>
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="#" class="text-decoration-none">Terms & Conditions</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3 py-2">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>

                <p class="text-center text-muted">
                    Already have an account? 
                    <a href="login.php" class="fw-bold text-decoration-none">Sign In</a>
                </p>
            </form>
        </div>
    </div>

    <div class="text-center mt-4 text-muted small">
        © 2025 Velvet Vogue. All rights reserved.
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/js/all.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Toggle password visibility
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const target = this.getAttribute('data-target');
        const input = document.querySelector(`[name="${target}"]`);
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
});

// Form validation
document.getElementById('registrationForm').addEventListener('submit', function(e) {
    const password = document.querySelector('[name="password"]').value;
    const confirm = document.querySelector('[name="confirm_password"]').value;
    
    if (password !== confirm) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Password Mismatch',
            text: 'Passwords do not match!',
            confirmButtonColor: '#614d73'
        });
        return false;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Password Too Short',
            text: 'Password must be at least 6 characters long!',
            confirmButtonColor: '#614d73'
        });
        return false;
    }
});

// Show SweetAlert2 on successful registration (NO REDIRECT)
<?php if ($success): ?>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: '🎉 Registration Successful!',
        html: `
            <div class="text-center">
                <div style="font-size: 60px; color: #28a745;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h5 class="mt-3">Welcome to Velvet Vogue!</h5>
                <p class="mb-0">Hello <strong><?= htmlspecialchars($username) ?></strong>, your account has been created successfully.</p>
                <p class="mt-2"><i class="fas fa-user-check"></i> You are now logged in.</p>
            </div>
        `,
        icon: 'success',
        showConfirmButton: true,
        confirmButtonText: 'Continue Shopping',
        confirmButtonColor: '#614d73',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            // Optional: You can redirect to homepage if needed, or stay on page
            // window.location.href = 'index.php';
            // For now, just close the alert and stay on page
        }
    });
    
    // Clear the form after successful registration
    document.getElementById('registrationForm').reset();
});
<?php endif; ?>
</script>
</body>
</html>