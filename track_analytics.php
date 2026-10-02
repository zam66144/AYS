<?php
session_start();
require_once 'config.php';

$product_id = (int)($_POST['product_id'] ?? 0);
$event = $_POST['event'] ?? '';
$client_id = $_SESSION['client_id'] ?? null;

if ($product_id <= 0 || !in_array($event, ['view', 'add_to_cart', 'purchase', 'wishlist'])) {
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO product_analytics (product_id, event_type, client_id) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$product_id, $event, $client_id]);
    
    // Update view count on product
    if ($event === 'view') {
        $pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?")->execute([$product_id]);
    }
} catch (Exception $e) {}