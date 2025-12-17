<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// === CHECK ADMIN LOGIN ===
if (empty($_SESSION['admin_id'])) {
    header("Location: ../adminLogin.php");
    exit();
}

// === DELETE PRODUCT ===
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("SELECT Image_URL FROM product WHERE Product_ID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $image = $row['Image_URL'];
        if ($image && file_exists("../uploads/" . $image)) {
            unlink("../uploads/" . $image);
        }
    }
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM product WHERE Product_ID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: products.php");
    exit();
}

// === SEARCH & FILTER ===
$search = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');
$where = [];
$params = [];
$types = "";

if ($search) {
    $where[] = "(P_Name LIKE ? OR P_Description LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}
if ($category_filter) {
    $where[] = "Category_ID_FK = ?";
    $params[] = $category_filter;
    $types .= "i";
}

$sql = "SELECT p.*, c.Category_Name 
        FROM product p 
        LEFT JOIN category c ON p.Category_ID_FK = c.Category_ID";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY p.Product_ID DESC";

$stmt = $conn->prepare($sql);
if (!empty($types) && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// === GET CATEGORIES FOR FILTER ===
$categories = $conn->query("SELECT Category_ID, Category_Name FROM category ORDER BY Category_Name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products | Velvet Vogue Admin</title>
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
        .table img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; }
        .badge-stock { font-size: 0.8rem; }
        .btn-action { font-size: 0.9rem; padding: 5px 10px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR - EXACT SAME AS DASHBOARD -->
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
                        <h2 class="h3 mb-1">Manage Products</h2>
                        <p class="text-muted mb-0">View, add, edit, or delete products</p>
                    </div>
                    <a href="add_product.php" class="btn btn-primary"><i class="fa fa-plus me-2"></i>Add New Product</a>
                </div>

                <!-- Search & Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" name="search" class="form-control" placeholder="Search by name or description" value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-4">
                                <select name="category" class="form-select">
                                    <option value="">All Categories</option>
                                    <?php while ($cat = $categories->fetch_assoc()): ?>
                                        <option value="<?= $cat['Category_ID'] ?>" <?= $category_filter == $cat['Category_ID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['Category_Name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary w-100"><i class="fa fa-search"></i> Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">All Products</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Size</th>
                                        <th>Color</th>
                                        <th>Stock</th>
                                        <th>Date Added</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($products->num_rows > 0): ?>
                                        <?php while ($p = $products->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    $image_path = "../uploads/" . $p['Image_URL'];
                                                    if (!empty($p['Image_URL']) && file_exists($image_path)): 
                                                    ?>
                                                        <img src="<?= $image_path ?>" alt="<?= htmlspecialchars($p['P_Name']) ?>" style="width:50px;height:50px;object-fit:cover;border-radius:8px;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:50px;height:50px;">
                                                            <i class="fa fa-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($p['P_Name']) ?></div>
                                                    <small class="text-muted">
                                                        <?= 
                                                            !empty($p['P_Description']) 
                                                            ? (strlen($p['P_Description']) > 50 
                                                                ? substr(htmlspecialchars($p['P_Description']), 0, 50) . '...' 
                                                                : htmlspecialchars($p['P_Description']))
                                                            : 'No description'
                                                        ?>
                                                    </small>
                                                </td>
                                                <td><?= htmlspecialchars($p['Category_Name'] ?? 'Uncategorized') ?></td>
                                                <td>$<?= number_format($p['P_Price'], 2) ?></td>
                                                <td><?= !empty($p['P_Size']) ? htmlspecialchars($p['P_Size']) : '-' ?></td>
                                                <td><?= !empty($p['P_Color']) ? htmlspecialchars($p['P_Color']) : '-' ?></td>
                                                <td>
                                                    <span class="badge <?= $p['Stock_Quantity'] > 10 ? 'bg-success' : ($p['Stock_Quantity'] > 0 ? 'bg-warning' : 'bg-danger') ?> badge-stock">
                                                        <?= $p['Stock_Quantity'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= !empty($p['P_Date_Added']) ? date('M j, Y', strtotime($p['P_Date_Added'])) : '-' ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <a href="edit_product.php?id=<?= $p['Product_ID'] ?>" class="btn btn-sm btn-outline-primary btn-action">
                                                        <i class="fa fa-edit"></i>
                                                    </a>
                                                    <a href="products.php?delete=<?= $p['Product_ID'] ?>" 
                                                       class="btn btn-sm btn-outline-danger btn-action" 
                                                       onclick="return confirm('Delete this product?')">
                                                        <i class="fa fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="fa fa-box-open fa-3x mb-3"></i><br>
                                                No products found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>