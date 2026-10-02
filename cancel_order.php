<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$client_id = $_SESSION['client_id'];

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID.']);
    exit;
}

// Verify order belongs to this client and is pending
$stmt = $pdo->prepare("SELECT id, status, product_id, quantity FROM orders WHERE id = ? AND client_id = ?");
$stmt->execute([$order_id, $client_id]);
$order = $stmt->fetch();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found.']);
    exit;
}

if ($order['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Only pending orders can be cancelled.']);
    exit;
}

try {
    // Update status to cancelled
    $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$order_id]);
    
    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to cancel order: ' . $e->getMessage()]);
}