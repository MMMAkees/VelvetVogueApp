<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// === CHECK ADMIN LOGIN ===
if (empty($_SESSION['admin_id'])) {
    header("Location: ../adminLogin.php");
    exit();
}

// === HANDLE FORM SUBMISSION ===
$msg = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $size = trim($_POST['size'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $category_id = intval($_POST['category'] ?? 0);
    $admin_id = $_SESSION['admin_id'];

    // Validation
    if (empty($name) || $price <= 0 || $stock < 0 || $category_id <= 0) {
        $msg = "All fields are required and must be valid.";
        $alert_type = "danger";
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $msg = "Image upload is required.";
        $alert_type = "danger";
    } else {
        // === IMAGE UPLOAD ===
        $upload_dir = "../uploads/";
        $image_name = uniqid('prod_') . "_" . basename($_FILES['image']['name']);
        $image_path = $upload_dir . $image_name;
        $image_file_type = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));

        // Validate image
        $valid_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($image_file_type, $valid_types)) {
            $msg = "Only JPG, JPEG, PNG, GIF, WEBP allowed.";
            $alert_type = "danger";
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) { // 5MB
            $msg = "Image must be less than 5MB.";
            $alert_type = "danger";
        } else {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                // === INSERT INTO DB ===
                $stmt = $conn->prepare("
                    INSERT INTO product 
                    (P_Name, P_Description, P_Price, Stock_Quantity, P_Size, P_Color, Image_URL, A_ID_FK, Category_ID_FK) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt) {
                    $stmt->bind_param("ssdissiii", $name, $description, $price, $stock, $size, $color, $image_name, $admin_id, $category_id);
                    
                    if ($stmt->execute()) {
                        $msg = "Product added successfully for $$price!";
                        $alert_type = "success";
                        // Reset form
                        $_POST = [];
                    } else {
                        $msg = "Database error: " . $stmt->error;
                        $alert_type = "danger";
                        unlink($image_path); // Delete uploaded image
                    }
                    $stmt->close();
                } else {
                    $msg = "Database preparation error: " . $conn->error;
                    $alert_type = "danger";
                    unlink($image_path); // Delete uploaded image
                }
            } else {
                $msg = "Failed to upload image.";
                $alert_type = "danger";
            }
        }
    }
}

// === GET CATEGORIES ===
$categories = $conn->query("SELECT Category_ID, Category_Name FROM category ORDER BY Category_Name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product | Velvet Vogue Admin</title>
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
            padding: 14px 20px;
            margin: 4px 0;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.15);
            transform: translateX(6px);
        }
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.25);
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .sidebar .nav-link i {
            width: 22px;
            margin-right: 12px;
            font-size: 1.1rem;
        }
        .sidebar .text-warning {
            color: #ffc107 !important;
        }
        .sidebar .text-warning:hover {
            background: rgba(255,193,7,0.2);
        }
        .main-content { padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-control, .form-select { border-radius: 12px; padding: 12px 16px; }
        .form-label { font-weight: 600; color: #495057; }
        .btn-primary { background: #0b0b0bff; border: none; border-radius: 12px; padding: 12px 30px; font-weight: 600; }
        .btn-primary:hover { background: #7a1fd1; }
        .preview-img { max-width: 150px; max-height: 150px; object-fit: cover; border-radius: 12px; margin-top: 10px; }
        .upload-area {
            border: 2px dashed #050505ff;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            background: #f8f4ff;
            transition: 0.3s;
            cursor: pointer;
        }
        .upload-area:hover { background: #f0e6ff; border-color: #7a1fd1; }
        .upload-area.dragover { background: #e6d9ff; border-color: #8a2be2; }
        .input-group-text {
            background: #f8f9fa;
            border-radius: 12px 0 0 12px;
            font-weight: 600;
        }
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
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'admindashboard.php' ? 'active' : '' ?>" 
                               href="admindashboard.php">
                                <i class="fa fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['products.php', 'add_product.php', 'edit_product.php']) ? 'active' : '' ?>" 
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
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : '' ?>" 
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
                        <h2 class="h3 mb-1">Add New Product</h2>
                        <p class="text-muted mb-0">Fill in the details to add a new product</p>
                    </div>
                    <a href="products.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-2"></i>Back to Products</a>
                </div>

                <!-- Alert -->
                <?php if ($msg): ?>
                    <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Add Product Form -->
                <div class="card">
                    <div class="card-body p-4">
                        <form method="POST" enctype="multipart/form-data" id="addProductForm">
                            <div class="row g-4">
                                <!-- Product Name -->
                                <div class="col-md-6">
                                    <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" placeholder="Enter product name" 
                                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                                </div>

                                <!-- Price -->
                                <div class="col-md-6">
                                    <label class="form-label">Price ($) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="price" step="0.01" class="form-control" placeholder="Enter price" 
                                               value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" min="0" required>
                                    </div>
                                </div>

                                <!-- Category -->
                                <div class="col-md-6">
                                    <label class="form-label">Category <span class="text-danger">*</span></label>
                                    <select name="category" class="form-select" required>
                                        <option value="">-- Select Category --</option>
                                        <?php while ($cat = $categories->fetch_assoc()): ?>
                                            <option value="<?= $cat['Category_ID'] ?>" 
                                                <?= (isset($_POST['category']) && $_POST['category'] == $cat['Category_ID']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['Category_Name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <!-- Stock -->
                                <div class="col-md-6">
                                    <label class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                                    <input type="number" name="stock" class="form-control" placeholder="Enter stock quantity" 
                                           value="<?= htmlspecialchars($_POST['stock'] ?? '') ?>" min="0" required>
                                </div>

                                <!-- Size -->
                                <div class="col-md-6">
                                    <label class="form-label">Size</label>
                                    <input type="text" name="size" class="form-control" placeholder="Enter size" 
                                           value="<?= htmlspecialchars($_POST['size'] ?? '') ?>">
                                </div>

                                <!-- Color -->
                                <div class="col-md-6">
                                    <label class="form-label">Color</label>
                                    <input type="text" name="color" class="form-control" placeholder="Enter color" 
                                           value="<?= htmlspecialchars($_POST['color'] ?? '') ?>">
                                </div>

                                <!-- Description -->
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="4" placeholder="Describe the product..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                </div>

                                <!-- Image Upload -->
                                <div class="col-12">
                                    <label class="form-label">Product Image <span class="text-danger">*</span></label>
                                    <div class="upload-area" id="uploadArea">
                                        <i class="fa fa-cloud-upload-alt fa-3x text-purple mb-3"></i>
                                        <p class="mb-2">Drag & drop image here or <span class="text-primary">browse</span></p>
                                        <small class="text-muted">JPG, PNG, GIF, WEBP up to 5MB</small>
                                        <input type="file" name="image" id="imageInput" accept="image/*" required hidden>
                                    </div>
                                    <div id="previewContainer" class="mt-3"></div>
                                </div>

                                <!-- Submit -->
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fa fa-save me-2"></i>Add Product
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image Upload Preview & Drag-Drop
        const uploadArea = document.getElementById('uploadArea');
        const imageInput = document.getElementById('imageInput');
        const previewContainer = document.getElementById('previewContainer');

        uploadArea.addEventListener('click', () => imageInput.click());

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => uploadArea.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => uploadArea.classList.remove('dragover'), false);
        });

        uploadArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files);
        }

        imageInput.addEventListener('change', () => {
            handleFiles(imageInput.files);
        });

        function handleFiles(files) {
            if (files.length > 0) {
                const file = files[0];
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        previewContainer.innerHTML = `
                            <img src="${e.target.result}" class="preview-img" alt="Preview">
                            <p class="mt-2 text-success"><i class="fa fa-check"></i> ${file.name}</p>
                        `;
                    };
                    reader.readAsDataURL(file);
                }
            }
        }
    </script>
</body>
</html>