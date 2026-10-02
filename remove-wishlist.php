<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit();
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$client_id = $_SESSION['client_id'];

if ($product_id > 0) {
    $stmt = $pdo->prepare("DELETE FROM wishlist WHERE product_id = ? AND client_id = ?");
    if ($stmt->execute([$product_id, $client_id])) {
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Removed from wishlist.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found in your wishlist.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error. Failed to remove.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
}
?>