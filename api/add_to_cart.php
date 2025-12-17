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
$quantity = intval($_POST['quantity'] ?? 1);
$size = $_POST['size'] ?? '';
$color = $_POST['color'] ?? '';

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit();
}

if ($quantity <= 0) {
    $quantity = 1;
}

try {
    // Check if product exists and has stock
    $check_product = $conn->prepare("SELECT Product_ID, P_Name, P_Price, Stock_Quantity FROM product WHERE Product_ID = ?");
    $check_product->bind_param("i", $product_id);
    $check_product->execute();
    $product_result = $check_product->get_result();
    
    if ($product_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }
    
    $product = $product_result->fetch_assoc();
    
    // Check stock availability
    if ($product['Stock_Quantity'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
        exit();
    }
    
    // Get or create shopping cart for user
    $cart_stmt = $conn->prepare("SELECT Cart_ID FROM shopping_cart WHERE User_ID_FK = ?");
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    
    if ($cart_result->num_rows > 0) {
        $cart = $cart_result->fetch_assoc();
        $cart_id = $cart['Cart_ID'];
    } else {
        // Create new cart
        $create_cart = $conn->prepare("INSERT INTO shopping_cart (User_ID_FK) VALUES (?)");
        $create_cart->bind_param("i", $user_id);
        $create_cart->execute();
        $cart_id = $conn->insert_id;
        $create_cart->close();
    }
    $cart_stmt->close();
    
    // Check if product already in cart
    $check_cart_item = $conn->prepare("SELECT Cart_Item_ID, Quantity FROM cart_item WHERE Cart_ID_FK = ? AND Product_ID_FK = ?");
    $check_cart_item->bind_param("ii", $cart_id, $product_id);
    $check_cart_item->execute();
    $cart_item_result = $check_cart_item->get_result();
    
    if ($cart_item_result->num_rows > 0) {
        // Update quantity if item exists
        $cart_item = $cart_item_result->fetch_assoc();
        $new_quantity = $cart_item['Quantity'] + $quantity;
        
        // Check if new quantity exceeds stock
        if ($new_quantity > $product['Stock_Quantity']) {
            echo json_encode(['success' => false, 'message' => 'Cannot add more than available stock']);
            exit();
        }
        
        $update_stmt = $conn->prepare("UPDATE cart_item SET Quantity = ? WHERE Cart_Item_ID = ?");
        $update_stmt->bind_param("ii", $new_quantity, $cart_item['Cart_Item_ID']);
        
        if ($update_stmt->execute()) {
            // Get updated cart count
            $count_stmt = $conn->prepare("SELECT SUM(Quantity) as total FROM cart_item WHERE Cart_ID_FK = ?");
            $count_stmt->bind_param("i", $cart_id);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $cart_count = $count_result->fetch_assoc()['total'] ?? 0;
            $count_stmt->close();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Quantity updated in cart!', 
                'cart_count' => $cart_count
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
        }
        $update_stmt->close();
    } else {
        // Add new item to cart
        // First check if we have the Selected_Size and Selected_Color columns
        $check_columns = $conn->query("SHOW COLUMNS FROM cart_item LIKE 'Selected_Size'");
        $has_size_column = $check_columns->num_rows > 0;
        $check_columns = $conn->query("SHOW COLUMNS FROM cart_item LIKE 'Selected_Color'");
        $has_color_column = $check_columns->num_rows > 0;
        
        if ($has_size_column && $has_color_column) {
            $insert_stmt = $conn->prepare("INSERT INTO cart_item (Cart_ID_FK, Product_ID_FK, Quantity, Selected_Size, Selected_Color) VALUES (?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("iiiss", $cart_id, $product_id, $quantity, $size, $color);
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO cart_item (Cart_ID_FK, Product_ID_FK, Quantity) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("iii", $cart_id, $product_id, $quantity);
        }
        
        if ($insert_stmt->execute()) {
            // Get updated cart count
            $count_stmt = $conn->prepare("SELECT SUM(Quantity) as total FROM cart_item WHERE Cart_ID_FK = ?");
            $count_stmt->bind_param("i", $cart_id);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $cart_count = $count_result->fetch_assoc()['total'] ?? 0;
            $count_stmt->close();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Product added to cart!', 
                'cart_count' => $cart_count
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add to cart']);
        }
        $insert_stmt->close();
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>