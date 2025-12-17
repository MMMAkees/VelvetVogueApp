<?php
session_start();
include __DIR__ . '/../dbConfig.php';

if (empty($_SESSION['admin_id'])) {
    header("Location: ../adminLogin.php");
    exit();
}

$cat_id = intval($_GET['id'] ?? 0);
if ($cat_id <= 0) {
    header("Location: categories.php");
    exit();
}

// === FETCH CURRENT CATEGORY ===
$stmt = $conn->prepare("SELECT * FROM category WHERE Category_ID = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $cat_id);
$stmt->execute();
$result = $stmt->get_result();
$cat = $result->fetch_assoc();
$stmt->close();

if (!$cat) {
    header("Location: categories.php");
    exit();
}

$error = '';
$success = '';

// === PROCESS UPDATE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $new_image = $cat['Cat_Image']; // Keep old image by default

    // Validation
    if (empty($name)) {
        $error = "Category name is required.";
    } elseif (strlen($name) > 100) {
        $error = "Category name too long (max 100 characters).";
    } else {
        // Handle new image upload
        if (!empty($_FILES['image']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $size = $_FILES['image']['size'];

            if (!in_array($ext, $allowed)) {
                $error = "Only JPG, PNG, GIF, WEBP allowed.";
            } elseif ($size > 5 * 1024 * 1024) {
                $error = "Image too large (max 5MB).";
            } else {
                $new_image = time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_path = "../uploads/" . $new_image;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    // Delete old image if exists
                    if ($cat['Cat_Image'] && file_exists("../uploads/" . $cat['Cat_Image'])) {
                        @unlink("../uploads/" . $cat['Cat_Image']);
                    }
                } else {
                    $error = "Failed to upload image.";
                    $new_image = $cat['Cat_Image']; // Revert
                }
            }
        }

        // Update database
        if (!$error) {
            $stmt = $conn->prepare("UPDATE category SET Category_Name = ?, C_Description = ?, Cat_Image = ? WHERE Category_ID = ?");
            if (!$stmt) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssi", $name, $description, $new_image, $cat_id);

            if ($stmt->execute()) {
                $success = "Category updated successfully!";
                $cat['Category_Name'] = $name;
                $cat['C_Description'] = $description;
                $cat['Cat_Image'] = $new_image;
            } else {
                $error = "Database error. Try again.";
                // Revert image if failed
                if ($new_image !== $cat['Cat_Image']) {
                    @unlink("../uploads/" . $new_image);
                }
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
    <title>Edit Category | Velvet Vogue Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f8f9fa; }
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
        .main-content { padding: 20px; }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            border-radius: 15px 15px 0 0 !important;
        }
        .current-img, .preview-img {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-top: 10px;
        }
        .preview-img { display: none; }
        .form-control, .form-select { border-radius: 10px; }
        .btn-primary, .btn-danger { border-radius: 10px; padding: 10px 20px; }
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

        <!-- Main Content -->
        <div class="col-md-10 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-1">Edit Category</h2>
                    <p class="text-muted mb-0">Update category details</p>
                </div>
                <a href="categories.php" class="btn btn-outline-secondary">Back to Categories</a>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Edit Form -->
            <div class="row justify-content-center">
                <div class="col-lg-8 col-xl-6">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Category Information</h5></div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" 
                                           value="<?= htmlspecialchars($cat['Category_Name']) ?>" required maxlength="100">
                                    <small class="text-muted">Max 100 characters</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Description</label>
                                    <textarea name="description" class="form-control" rows="4" placeholder="Optional description..."><?= htmlspecialchars($cat['C_Description']) ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Current Image</label><br>
                                    <?php if ($cat['Cat_Image']): ?>
                                        <img src="../uploads/<?= htmlspecialchars($cat['Cat_Image']) ?>" class="current-img" alt="Current">
                                        <p class="text-muted small mt-2">Leave blank to keep current image</p>
                                    <?php else: ?>
                                        <p class="text-muted">No image set</p>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Replace Image</label>
                                    <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this)">
                                    <small class="text-muted">JPG, PNG, GIF, WEBP (Max 5MB)</small>
                                    <img id="imagePreview" class="preview-img" src="" alt="New Preview">
                                </div>

                                <div class="d-flex gap-3">
                                    <button type="submit" class="btn btn-primary">Update Category</button>
                                    <a href="categories.php" class="btn btn-secondary">Cancel</a>
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
<script>
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.src = '';
        preview.style.display = 'none';
    }
}
</script>
</body>
</html>
