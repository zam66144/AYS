<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$client_id = $_SESSION['client_id'];

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID.']);
    exit;
}

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a return reason.']);
    exit;
}

try {
    // Verify order belongs to this client
    $stmt = $pdo->prepare("SELECT id, status, return_status FROM orders WHERE id = ? AND client_id = ?");
    $stmt->execute([$order_id, $client_id]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    if (!in_array($order['status'], ['processed', 'delivered'])) {
        echo json_encode(['success' => false, 'message' => 'Only delivered orders can be returned.']);
        exit;
    }

    if (!empty($order['return_status'])) {
        echo json_encode(['success' => false, 'message' => 'Return already requested for this order.']);
        exit;
    }

    // Update order with return request
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET return_status = 'requested',
            return_reason = ?,
            return_requested_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$reason, $order_id]);

    echo json_encode(['success' => true, 'message' => 'Return request submitted! Admin will review it soon.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed: ' . $e->getMessage()]);
}