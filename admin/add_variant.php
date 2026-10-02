<?php
session_start();
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once '../config.php';
header('Content-Type: application/json');

$product_id = (int)($_POST['product_id'] ?? 0);
$type = trim($_POST['variant_type'] ?? '');
$value = trim($_POST['variant_value'] ?? '');
$price_adj = (float)($_POST['price_adjustment'] ?? 0);
$stock = (int)($_POST['stock'] ?? 0);

if ($product_id <= 0 || empty($type) || empty($value)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$allowed_types = ['size', 'color', 'volume'];
if (!in_array($type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid variant type']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO product_variants (product_id, variant_type, variant_value, price_adjustment, stock) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$product_id, $type, $value, $price_adj, $stock]);
    
    echo json_encode(['success' => true, 'message' => 'Variant added']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}