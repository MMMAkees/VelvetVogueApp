<?php
session_start();
include __DIR__ . '/../dbConfig.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user_id = intval($_SESSION['user_id']);
$product_id = intval($_POST['product_id']);

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit();
}

try {
    // Check if product exists
    $check_product = $conn->prepare("SELECT Product_ID FROM product WHERE Product_ID = ?");
    $check_product->bind_param("i", $product_id);
    $check_product->execute();
    $product_result = $check_product->get_result();
    
    if ($product_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }
    
    // Check if already in wishlist
    $check_wishlist = $conn->prepare("SELECT Wishlist_ID FROM wishlist WHERE User_ID_FK = ? AND Product_ID_FK = ?");
    $check_wishlist->bind_param("ii", $user_id, $product_id);
    $check_wishlist->execute();
    $wishlist_result = $check_wishlist->get_result();
    
    if ($wishlist_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Already in wishlist']);
        exit();
    }
    
    // Add to wishlist
    $insert_stmt = $conn->prepare("INSERT INTO wishlist (User_ID_FK, Product_ID_FK) VALUES (?, ?)");
    $insert_stmt->bind_param("ii", $user_id, $product_id);
    
    if ($insert_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Added to wishlist!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add to wishlist']);
    }
    
    $insert_stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>