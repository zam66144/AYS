<?php
session_start();
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once '../config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$updates = $input['updates'] ?? [];

if (empty($updates)) {
    echo json_encode(['success' => false, 'message' => 'No updates provided']);
    exit;
}

try {
    $pdo->beginTransaction();
    $count = 0;
    
    $stmt = $pdo->prepare("UPDATE products SET price = ? WHERE id = ?");
    foreach ($updates as $u) {
        if ($stmt->execute([$u['newPrice'], $u['id']])) {
            $count++;
        }
    }
    
    $logStmt = $pdo->prepare("INSERT INTO bulk_operations (operation_type, affected_count, details) VALUES ('price_update', ?, ?)");
    $logStmt->execute([$count, json_encode(['updates' => $updates])]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'updated' => $count]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}