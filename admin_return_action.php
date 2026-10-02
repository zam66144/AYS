<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Only admin can access
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$action = $_POST['action'] ?? '';
$order_id = (int)($_POST['order_id'] ?? 0);
$note = trim($_POST['note'] ?? '');

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID.']);
    exit;
}

try {
    // Get order details
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    // ============ APPROVE RETURN ============
    if ($action === 'approve') {
        if ($order['return_status'] !== 'requested') {
            echo json_encode(['success' => false, 'message' => 'Return is not in requested state.']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE orders 
            SET return_status = 'approved',
                admin_notes = ?
            WHERE id = ?
        ");
        $stmt->execute([$note, $order_id]);

        echo json_encode(['success' => true, 'message' => 'Return approved!']);
        exit;
    }

    // ============ REJECT RETURN ============
    if ($action === 'reject') {
        if ($order['return_status'] !== 'requested') {
            echo json_encode(['success' => false, 'message' => 'Return is not in requested state.']);
            exit;
        }

        if (empty($note)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a rejection reason.']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE orders 
            SET return_status = 'rejected',
                admin_notes = ?
            WHERE id = ?
        ");
        $stmt->execute([$note, $order_id]);

        echo json_encode(['success' => true, 'message' => 'Return rejected.']);
        exit;
    }

    // ============ MARK AS RETURNED + REFUND + RESTOCK ============
    if ($action === 'mark_returned') {
        if ($order['return_status'] !== 'approved') {
            echo json_encode(['success' => false, 'message' => 'Return must be approved first.']);
            exit;
        }

        $amount = (float)($_POST['amount'] ?? 0);
        $method = trim($_POST['method'] ?? 'card');

        // Get product_id before changing anything
        $product_id = (int)($order['product_id'] ?? 0);
        $quantity = (int)($order['quantity'] ?? 0);

        // Start transaction
        $pdo->beginTransaction();

        try {
            // 1. Update order as returned
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET return_status = 'returned',
                    status = 'returned',
                    refund_amount = ?,
                    refund_method = ?,
                    return_processed_at = NOW(),
                    admin_notes = CONCAT(IFNULL(admin_notes, ''), ' | Refunded: ', ?, ' via ', ?)
                WHERE id = ?
            ");
            $stmt->execute([$amount, $method, $amount, $method, $order_id]);

            // 2. Add stock back to product
            if ($product_id > 0 && $quantity > 0) {
                $stmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                $stmt->execute([$quantity, $product_id]);
            }

            $pdo->commit();

            echo json_encode([
                'success' => true, 
                'message' => "Return completed! Refund: \${$amount} via {$method}. Stock updated."
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}