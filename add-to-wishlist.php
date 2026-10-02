<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit();
}

$client_id = $_SESSION['client_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if ($product_id > 0) {
    // Check if product already in wishlist
    $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE client_id = ? AND product_id = ?");
    $stmt->execute([$client_id, $product_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Product already in wishlist.']);
        exit();
    }
    
    // Add to wishlist
    $stmt = $pdo->prepare("INSERT INTO wishlist (client_id, product_id) VALUES (?, ?)");
    if ($stmt->execute([$client_id, $product_id])) {
        echo json_encode(['success' => true, 'message' => 'Added to wishlist!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add to wishlist.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
}
?>