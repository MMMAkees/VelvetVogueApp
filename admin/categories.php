<?php
session_start();
include __DIR__ . '/../dbConfig.php';

if (empty($_SESSION['admin_id'])) {
    header("Location: ../adminLogin.php");
    exit();
}

// === DELETE CATEGORY ===
if (isset($_GET['delete'])) {
    $cat_id = intval($_GET['delete']);
    
    // Delete category image
    $stmt = $conn->prepare("SELECT Cat_Image FROM category WHERE Category_ID = ?");
    $stmt->bind_param("i", $cat_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cat = $result->fetch_assoc();
    $stmt->close();

    if ($cat && !empty($cat['Cat_Image'])) {
        @unlink("../uploads/" . $cat['Cat_Image']);
    }

    // Delete category
    $stmt = $conn->prepare("DELETE FROM category WHERE Category_ID = ?");
    $stmt->bind_param("i", $cat_id);
    $stmt->execute();
    $stmt->close();

    header("Location: categories.php");
    exit();
}

// === FETCH ALL CATEGORIES ===
$categories = [];
$sql = "SELECT * FROM category ORDER BY Date_Created DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Get product count for this category
        $stmt = $conn->prepare("SELECT COUNT(*) as product_count FROM product WHERE Category_ID_FK = ?");
        $stmt->bind_param("i", $row['Category_ID']);
        $stmt->execute();
        $count_result = $stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $row['product_count'] = $count_row['product_count'];
        $stmt->close();

        $categories[] = $row;
    }
    $result->free();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Categories | Velvet Vogue Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    font-family: 'Poppins', sans-serif;
    background: #f1f3f6;
    margin: 0;
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
    transition: all 0.2s ease;
    font-weight: 500;
    display: flex;
    align-items: center;
}

.sidebar .nav-link:hover {
    background: rgba(255,255,255,0.1);
}

.sidebar .nav-link.active {
    background: rgba(255,255,255,0.25);
    font-weight: 600;
}

.sidebar .nav-link i {
    width: 20px;
    margin-right: 12px;
    text-align: center;
}

.main-content {
    padding: 20px;
}

.card {
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: box-shadow 0.2s ease;
}

.card:hover {
    box-shadow: 0 6px 18px rgba(0,0,0,0.12);
}

.cat-img {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 12px;
    border: 2px solid #eee;
}

.badge-count {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.card-footer {
    background: white;
    border-top: 1px solid #e9ecef;
    border-radius: 0 0 15px 15px;
}

.btn-sm {
    border-radius: 25px;
}

.category-date {
    font-size: 0.75rem;
    color: #888;
}
</style>
</head>
<body>
<div class="container-fluid">
<div class="row">
    <!-- Sidebar -->
    <div class="col-md-2 sidebar p-0">
        <div class="p-4">
            <h4 class="text-white mb-4"><i class="me-4"></i>Admin Panel</h4>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="admindashboard.php"><i class="fa fa-tachometer-alt"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="products.php"><i class="fa fa-box"></i>Products</a></li>
                <li class="nav-item"><a class="nav-link" href="orders.php"><i class="fa fa-shopping-bag"></i>Orders</a></li>
                <li class="nav-item"><a class="nav-link" href="users.php"><i class="fa fa-users"></i>Users</a></li>
                <li class="nav-item"><a class="nav-link active" href="categories.php"><i class="fa fa-tags"></i>Categories</a></li>
                <li class="nav-item"><a class="nav-link" href="offers.php"><i class="fa fa-percentage"></i>Offers & Promotions</a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="chat.php">
                            <i class="fa fa-comments"></i> Customer Support
                        </a>
                    </li>                
                <li class="nav-item mt-4"><a class="nav-link text-warning" href="../adminLogin.php?logout=1"><i class="fa fa-sign-out-alt"></i>Logout</a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="col-md-10 main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h3 mb-1">Manage Categories</h2>
                <p class="text-muted mb-0">Add, edit, or remove product categories</p>
            </div>
            <a href="add_category.php" class="btn btn-primary">Add New Category</a>
        </div>

        <div class="row">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body d-flex">
                                <div class="me-3">
                                    <?php if (!empty($cat['Cat_Image'])): ?>
                                        <img src="../uploads/<?= htmlspecialchars($cat['Cat_Image']) ?>" class="cat-img" alt="<?= htmlspecialchars($cat['Category_Name']) ?>">
                                    <?php else: ?>
                                        <div class="bg-light cat-img d-flex align-items-center justify-content-center rounded">
                                            <i class="fa fa-tags text-muted fa-2x"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1"><?= htmlspecialchars($cat['Category_Name']) ?></h5>
                                    <p class="text-muted small mb-1"><?= htmlspecialchars(substr($cat['C_Description'], 0, 80)) ?><?= strlen($cat['C_Description']) > 80 ? '...' : '' ?></p>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge-count bg-success"><?= $cat['product_count'] ?> Products</span>
                                    </div>
                                    <div class="category-date">Created: <?= date('d M Y', strtotime($cat['Date_Created'])) ?></div>
                                </div>
                            </div>
                            <div class="card-footer d-flex justify-content-between">
                                <a href="edit_category.php?id=<?= $cat['Category_ID'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa fa-edit me-1"></i>Edit</a>
                                <a href="categories.php?delete=<?= $cat['Category_ID'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete <?= htmlspecialchars($cat['Category_Name']) ?>? This will NOT delete products.')"><i class="fa fa-trash me-1"></i>Delete</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fa fa-tags fa-4x text-muted mb-3"></i>
                        <h4>No categories found</h4>
                        <p class="text-muted">Start by adding your first category!</p>
                        <a href="add_category.php" class="btn btn-primary">Add Category</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
