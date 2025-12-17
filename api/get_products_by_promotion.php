<?php
include __DIR__ . '/../dbConfig.php';

header('Content-Type: application/json');

$promotion_id = $_GET['promotion_id'] ?? 0;

// For demo purposes, return some sample products
// In real implementation, you would query products associated with this promotion
$sql = "SELECT Product_ID, P_Name, P_Price, Image_URL, Stock_Quantity 
        FROM product 
        WHERE Stock_Quantity > 0 
        ORDER BY P_Date_Added DESC 
        LIMIT 6";

$result = $conn->query($sql);
$products = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

echo json_encode($products);
?>