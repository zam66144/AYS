<?php
session_start();
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once '../config.php';
header('Content-Type: application/json');

$product_id = (int)($_GET['product_id'] ?? 0);
if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id DESC");
    $stmt->execute([$product_id]);
    $variants = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'variants' => $variants]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}