<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// === CHECK ADMIN LOGIN ===
if (empty($_SESSION['admin_id'])) {
    header("Location: ../adminLogin.php");
    exit();
}

$admin_id = intval($_SESSION['admin_id']);
$stmt = $conn->prepare("SELECT Username FROM admin WHERE A_ID = ?");
if (!$stmt) die("DB Error: " . $conn->error);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if (!$admin) {
    session_destroy();
    header("Location: ../adminLogin.php");
    exit();
}

$username = $admin['Username'];

// === LOGOUT HANDLER ===
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../adminLogin.php");
    exit();
}

// === HANDLE FORM SUBMISSIONS ===
$message = '';
$message_type = '';

// Add Offer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_offer'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $discount_percentage = intval($_POST['discount_percentage']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $offer_type = $_POST['offer_type']; // 'offer' or 'promotion'

    // Validate dates
    if ($end_date <= $start_date) {
        $message = "End date must be after start date";
        $message_type = 'error';
    } elseif ($discount_percentage < 1 || $discount_percentage > 100) {
        $message = "Discount percentage must be between 1 and 100";
        $message_type = 'error';
    } else {
        // Handle image upload
        $image_url = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "../img/offers/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $file_name;
            
            // Check if file is an image
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array(strtolower($file_extension), $allowed_types)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $image_url = $file_name;
                }
            }
        }

        if ($offer_type === 'offer') {
            // Insert into offers table
            $stmt = $conn->prepare("
                INSERT INTO offers (Title, Description, Image_URL, Discount_Percentage, Start_Date, End_Date, Is_Active) 
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->bind_param("sssiss", $title, $description, $image_url, $discount_percentage, $start_date, $end_date);
        } else {
            // Insert into promotion table
            $stmt = $conn->prepare("
                INSERT INTO promotion (P_Title, Description, Discount_Percentage, Start_Date, End_Date, A_ID_FK) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssissi", $title, $description, $discount_percentage, $start_date, $end_date, $admin_id);
        }

        if ($stmt->execute()) {
            $message = ucfirst($offer_type) . " added successfully!";
            $message_type = 'success';
        } else {
            $message = "Error adding " . $offer_type . ": " . $stmt->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Delete Offer
if (isset($_GET['delete_offer'])) {
    $offer_id = intval($_GET['delete_offer']);
    $offer_type = $_GET['type'] ?? 'offer';
    
    if ($offer_type === 'offer') {
        $stmt = $conn->prepare("DELETE FROM offers WHERE Offer_ID = ?");
    } else {
        $stmt = $conn->prepare("DELETE FROM promotion WHERE Promotion_ID = ?");
    }
    
    $stmt->bind_param("i", $offer_id);
    if ($stmt->execute()) {
        $message = ucfirst($offer_type) . " deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting " . $offer_type;
        $message_type = 'error';
    }
    $stmt->close();
}

// Toggle Offer Status
if (isset($_GET['toggle_offer'])) {
    $offer_id = intval($_GET['toggle_offer']);
    $stmt = $conn->prepare("UPDATE offers SET Is_Active = NOT Is_Active WHERE Offer_ID = ?");
    $stmt->bind_param("i", $offer_id);
    if ($stmt->execute()) {
        $message = "Offer status updated!";
        $message_type = 'success';
    }
    $stmt->close();
}

// === FETCH DATA ===
// Fetch offers
$offers = [];
$offer_result = $conn->query("
    SELECT * FROM offers 
    ORDER BY Created_At DESC
");
if ($offer_result) {
    while ($row = $offer_result->fetch_assoc()) {
        $offers[] = $row;
    }
}

// Fetch promotions
$promotions = [];
$promo_result = $conn->query("
    SELECT p.*, a.Username as Admin_Name 
    FROM promotion p 
    LEFT JOIN admin a ON p.A_ID_FK = a.A_ID 
    ORDER BY p.Start_Date DESC
");
if ($promo_result) {
    while ($row = $promo_result->fetch_assoc()) {
        $promotions[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Offers & Promotions - Velvet Vogue</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
   
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
        }
        .navbar-brand {
            font-family: 'Dancing Script', cursive;
            font-size: 2rem;
            color: #8a2be2 !important;
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
        .main-content {
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        .offer-card {
            transition: transform 0.3s ease;
        }
        .offer-card:hover {
            transform: translateY(-5px);
        }
        .badge-active {
            background: #28a745;
        }
        .badge-inactive {
            background: #6c757d;
        }
        .badge-expired {
            background: #dc3545;
        }
        .offer-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }
        .nav-tabs .nav-link.active {
            background: #8a2be2;
            color: white;
            border: none;
        }
        .nav-tabs .nav-link {
            color: #8a2be2;
            border: 1px solid #8a2be2;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="p-4">
                    <h4 class="text-white mb-4">
                        <i class="me-2"></i>Admin Panel
                    </h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="admindashboard.php">
                                <i class="fa fa-tachometer-alt"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">
                                <i class="fa fa-box"></i>Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fa fa-shopping-bag"></i>Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fa fa-users"></i>Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="categories.php">
                                <i class="fa fa-tags"></i>Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="offers.php">
                                <i class="fa fa-percentage"></i>Offers & Promotions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="chat.php">
                                <i class="fa fa-comments"></i> Customer Support
                            </a>
                        </li>                        
                        <li class="nav-item mt-4">
                            <a class="nav-link text-warning" href="?logout=1">
                                <i class="fa fa-sign-out-alt"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="h3 mb-1">Manage Offers & Promotions</h2>
                        <p class="text-muted mb-0">Create and manage special deals for your customers</p>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="text-muted me-3"><?= date('F j, Y') ?></span>
                    </div>
                </div>

                <!-- Message Alert -->
                <?php if ($message): ?>
                <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                    <i class="fa <?= $message_type === 'success' ? 'fa-check' : 'fa-exclamation-triangle' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Add Offer/Promotion Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Add New Offer/Promotion</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="offers.php" enctype="multipart/form-data" id="offerForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Type</label>
                                    <select class="form-select" name="offer_type" required>
                                        <option value="offer">Limited Time Offer</option>
                                        <option value="promotion">Special Promotion</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Title *</label>
                                    <input type="text" class="form-control" name="title" required placeholder="Enter offer title">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3" placeholder="Enter offer description"></textarea>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Discount Percentage *</label>
                                    <input type="number" class="form-control" name="discount_percentage" min="1" max="100" required placeholder="%">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" name="start_date" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">End Date *</label>
                                    <input type="date" class="form-control" name="end_date" required>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Image (Optional)</label>
                                    <input type="file" class="form-control" name="image" accept="image/*">
                                    <small class="text-muted">Recommended size: 400x300px. Formats: JPG, PNG, GIF, WebP</small>
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="add_offer" class="btn btn-primary">
                                        <i class="fa fa-plus me-2"></i>Add Offer/Promotion
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabs for Offers and Promotions -->
                <ul class="nav nav-tabs mb-4" id="offersTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="offers-tab" data-bs-toggle="tab" data-bs-target="#offers" type="button" role="tab">
                            <i class="fa fa-tag me-2"></i>Limited Time Offers (<?= count($offers) ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="promotions-tab" data-bs-toggle="tab" data-bs-target="#promotions" type="button" role="tab">
                            <i class="fa fa-percentage me-2"></i>Special Promotions (<?= count($promotions) ?>)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="offersTabContent">
                    <!-- Offers Tab -->
                    <div class="tab-pane fade show active" id="offers" role="tabpanel">
                        <div class="row">
                            <?php if (count($offers) > 0): ?>
                                <?php foreach ($offers as $offer): 
                                    $is_active = $offer['Is_Active'] == 1;
                                    $is_expired = strtotime($offer['End_Date']) < time();
                                    $status_class = $is_expired ? 'badge-expired' : ($is_active ? 'badge-active' : 'badge-inactive');
                                    $status_text = $is_expired ? 'Expired' : ($is_active ? 'Active' : 'Inactive');
                                ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card offer-card h-100">
                                        <?php if (!empty($offer['Image_URL'])): ?>
                                        <img src="../img/offers/<?= htmlspecialchars($offer['Image_URL']) ?>" class="offer-image" alt="<?= htmlspecialchars($offer['Title']) ?>">
                                        <?php else: ?>
                                        <div class="offer-image bg-light d-flex align-items-center justify-content-center">
                                            <i class="fa fa-tag fa-3x text-muted"></i>
                                        </div>
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0"><?= htmlspecialchars($offer['Title']) ?></h6>
                                                <span class="badge <?= $status_class ?>"><?= $status_text ?></span>
                                            </div>
                                            <p class="card-text small text-muted"><?= htmlspecialchars($offer['Description']) ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge bg-primary"><?= $offer['Discount_Percentage'] ?>% OFF</span>
                                                <small class="text-muted">Ends: <?= date('M d, Y', strtotime($offer['End_Date'])) ?></small>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <div class="btn-group w-100">
                                                <?php if (!$is_expired): ?>
                                                <a href="?toggle_offer=<?= $offer['Offer_ID'] ?>" class="btn btn-sm btn-<?= $is_active ? 'warning' : 'success' ?>">
                                                    <i class="fa fa-<?= $is_active ? 'pause' : 'play' ?>"></i>
                                                </a>
                                                <?php endif; ?>
                                                <a href="?delete_offer=<?= $offer['Offer_ID'] ?>&type=offer" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this offer?')">
                                                    <i class="fa fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12 text-center py-5">
                                    <i class="fa fa-tag fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">No offers created yet</h5>
                                    <p class="text-muted">Create your first limited time offer using the form above.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Promotions Tab -->
                    <div class="tab-pane fade" id="promotions" role="tabpanel">
                        <div class="row">
                            <?php if (count($promotions) > 0): ?>
                                <?php foreach ($promotions as $promo): 
                                    $is_active = strtotime($promo['Start_Date']) <= time() && strtotime($promo['End_Date']) >= time();
                                    $is_expired = strtotime($promo['End_Date']) < time();
                                    $status_class = $is_expired ? 'badge-expired' : ($is_active ? 'badge-active' : 'badge-inactive');
                                    $status_text = $is_expired ? 'Expired' : ($is_active ? 'Active' : 'Upcoming');
                                ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card offer-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0"><?= htmlspecialchars($promo['P_Title']) ?></h6>
                                                <span class="badge <?= $status_class ?>"><?= $status_text ?></span>
                                            </div>
                                            <p class="card-text small text-muted"><?= htmlspecialchars($promo['Description']) ?></p>
                                            <div class="mb-2">
                                                <?php if (!empty($promo['Discount_Percentage'])): ?>
                                                <span class="badge bg-primary"><?= $promo['Discount_Percentage'] ?>% OFF</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small text-muted">
                                                <div>Starts: <?= date('M d, Y', strtotime($promo['Start_Date'])) ?></div>
                                                <div>Ends: <?= date('M d, Y', strtotime($promo['End_Date'])) ?></div>
                                                <?php if (!empty($promo['Admin_Name'])): ?>
                                                <div>Created by: <?= htmlspecialchars($promo['Admin_Name']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <a href="?delete_offer=<?= $promo['Promotion_ID'] ?>&type=promotion" class="btn btn-sm btn-danger w-100" onclick="return confirm('Are you sure you want to delete this promotion?')">
                                                <i class="fa fa-trash me-2"></i>Delete
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12 text-center py-5">
                                    <i class="fa fa-percentage fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">No promotions created yet</h5>
                                    <p class="text-muted">Create your first special promotion using the form above.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set today's date as default for start date
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="start_date"]').value = today;
            
            // Set end date to 7 days from today by default
            const nextWeek = new Date();
            nextWeek.setDate(nextWeek.getDate() + 7);
            document.querySelector('input[name="end_date"]').value = nextWeek.toISOString().split('T')[0];
            
            // Form validation
            const form = document.getElementById('offerForm');
            form.addEventListener('submit', function(e) {
                const startDate = new Date(document.querySelector('input[name="start_date"]').value);
                const endDate = new Date(document.querySelector('input[name="end_date"]').value);
                
                if (endDate <= startDate) {
                    e.preventDefault();
                    alert('End date must be after start date');
                    return false;
                }
            });
        });
    </script>
</body>
</html>