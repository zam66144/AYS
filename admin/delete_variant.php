<?php
session_start();
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once '../config.php';
header('Content-Type: application/json');

$variant_id = (int)($_POST['variant_id'] ?? 0);
if ($variant_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid variant ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM product_variants WHERE id = ?");
    $stmt->execute([$variant_id]);
    echo json_encode(['success' => true, 'message' => 'Variant deleted']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}