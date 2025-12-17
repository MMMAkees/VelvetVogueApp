<?php
include __DIR__ . '/../dbConfig.php';

header('Content-Type: application/json');

$sql = "SELECT Product_ID, P_Name, P_Price, Image_URL, Stock_Quantity 
        FROM product 
        WHERE Stock_Quantity > 0 
        ORDER BY P_Date_Added DESC 
        LIMIT 12";

$result = $conn->query($sql);
$products = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

echo json_encode($products);
?>