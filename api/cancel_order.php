<?php
session_start();
include __DIR__ . '/../dbConfig.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to cancel orders']);
    exit();
}

$user_id = intval($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Verify the order belongs to the user and is cancellable
        $verify_stmt = $conn->prepare("
            SELECT Order_ID, Status 
            FROM orders 
            WHERE Order_ID = ? AND User_ID_FK = ? AND Status IN ('Pending', 'Processing')
        ");
        $verify_stmt->bind_param("ii", $order_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            $verify_stmt->close();
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Order not found or cannot be cancelled']);
            exit();
        }
        
        $order_data = $verify_result->fetch_assoc();
        $verify_stmt->close();
        
        // Update order status to Cancelled
        $update_stmt = $conn->prepare("UPDATE orders SET Status = 'Cancelled' WHERE Order_ID = ?");
        $update_stmt->bind_param("i", $order_id);
        
        if ($update_stmt->execute()) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to cancel order']);
        }
        
        $update_stmt->close();
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>