<?php
session_start();
include __DIR__ . '/../dbConfig.php';

if (empty($_SESSION['admin_id'])) {
    header("Location: ../adminLogin.php");
    exit();
}

$error = '';
$success = '';

// === AUTO DETECT COLUMNS (WORKS EVEN IF NAMES DIFFER) ===
$col_name = 'Category_Name';
$col_desc = 'C_Description';
$col_img  = 'Cat_Image';
$col_admin = 'A_ID_FK';

$result = $conn->query("SHOW COLUMNS FROM category");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $field = $row['Field'];
        if (stripos($field, 'name') !== false && $field !== 'Category_ID') $col_name = $field;
        elseif (stripos($field, 'desc') !== false) $col_desc = $field;
        elseif (stripos($field, 'image') !== false) $col_img = $field;
        elseif (stripos($field, 'fk') !== false) $col_admin = $field;
    }
}

// === PROCESS FORM ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image = '';

    if (empty($name)) {
        $error = "Category name is required.";
    } else {
        // === IMAGE UPLOAD (30MB) ===
        if (!empty($_FILES['image']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $size = $_FILES['image']['size'];

            if (!in_array($ext, $allowed)) {
                $error = "Only JPG, PNG, GIF, WEBP allowed.";
            } elseif ($size > 30 * 1024 * 1024) {
                $error = "Image too large (max 30MB).";
            } else {
                $image = time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_path = "../uploads/" . $image;

                if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $error = "Failed to upload image.";
                    $image = '';
                }
            }
        }

        // === SAVE TO DB ===
        if (!$error) {
            $sql = "INSERT INTO category ($col_name, $col_desc, $col_img, $col_admin, Date_Created) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                $error = "Database error: " . $conn->error;
            } else {
                $admin_id = $_SESSION['admin_id'];
                $stmt->bind_param("sssi", $name, $description, $image, $admin_id);
                if ($stmt->execute()) {
                    $success = "Category added successfully!";
                    $name = $description = '';
                    $image = '';
                } else {
                    $error = "Failed to save.";
                    if ($image) @unlink("../uploads/" . $image);
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Category | Velvet Vogue</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #040404ff, #362b3f5a); color: white; min-height: 100vh; }
        .sidebar .nav-link { color: white; padding: 12px 20px; margin: 5px 0; border-radius: 8px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); transform: translateX(5px); }
        .sidebar .nav-link.active { background: rgba(255,255,255,0.2); font-weight: 600; }
        .main-content { padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .preview-img { max-width: 200px; max-height: 200px; object-fit: cover; border-radius: 10px; margin-top: 10px; display: none; }
        .form-control { border-radius: 10px; }
        .btn-primary { border-radius: 10px; padding: 10px 20px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <div class="col-md-2 sidebar p-0">
                <div class="p-4">
                    <h4 class="text-white mb-4">
                        <i class="m-2"></i>Admin Panel
                    </h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'admindashboard.php' ? 'active' : '' ?>" 
                               href="admindashboard.php">
                                <i class="fa fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>" 
                               href="products.php">
                                <i class="fa fa-box"></i> Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : '' ?>" 
                               href="orders.php">
                                <i class="fa fa-shopping-bag"></i> Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>" 
                               href="users.php">
                                <i class="fa fa-users"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['categories.php', 'add_category.php', 'edit_category.php']) ? 'active' : '' ?>" 
                               href="categories.php">
                                <i class="fa fa-tags"></i> Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="offers.php">
                                <i class="fa fa-percentage"></i> Offers & Promotions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="chat.php">
                                <i class="fa fa-comments"></i> Customer Support
                            </a>
                        </li>                        
                        <li class="nav-item mt-4">
                            <a class="nav-link text-warning" href="../adminLogin.php?logout=1">
                                <i class="fa fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h3 mb-1">Add New Category</h2>
                    <a href="categories.php" class="btn btn-outline-secondary">Back</a>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= $success ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= $error ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row justify-content-center">
                    <div class="col-lg-8 col-xl-6">
                        <div class="card">
                            <div class="card-header"><h5>Add Category with Image</h5></div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Description</label>
                                        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($description ?? '') ?></textarea>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">Category Image (Max 30MB)</label>
                                        <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this)">
                                        <small class="text-muted">JPG, PNG, GIF, WEBP</small>
                                        <img id="imagePreview" class="preview-img" src="" alt="Preview">
                                    </div>
                                    <button type="submit" class="btn btn-primary">Save Category</button>
                                    <a href="categories.php" class="btn btn-secondary">Cancel</a>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>