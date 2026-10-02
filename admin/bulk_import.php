<?php
session_start();
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once '../config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$products = $input['products'] ?? [];

if (empty($products)) {
    echo json_encode(['success' => false, 'message' => 'No products to import']);
    exit;
}

$imported = 0;
$errors = [];
$allowed_cats = ['perfume', 'tester', 'watch', 'glass'];

try {
    $pdo->beginTransaction();
    
    foreach ($products as $index => $p) {
        $name = trim($p['name'] ?? '');
        $category = strtolower(trim($p['category'] ?? 'perfume'));
        $price = (float)($p['price'] ?? 0);
        $stock = (int)($p['stock'] ?? 0);
        $description = trim($p['description'] ?? '');
        $image_url = trim($p['image_url'] ?? '');
        
        if (empty($name)) {
            $errors[] = "Row " . ($index + 2) . ": Missing name";
            continue;
        }
        
        if (!in_array($category, $allowed_cats)) {
            $category = 'perfume';
        }
        
        if ($price <= 0) {
            $errors[] = "Row " . ($index + 2) . ": Invalid price";
            continue;
        }
        
        if (empty($image_url)) {
            $image_url = 'https://via.placeholder.com/300';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO products (name, category, price, stock, image_url, description) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$name, $category, $price, $stock, $image_url, $description])) {
            $imported++;
        }
    }
    
    $logStmt = $pdo->prepare("INSERT INTO bulk_operations (operation_type, affected_count, details) VALUES ('import', ?, ?)");
    $logStmt->execute([$imported, json_encode(['total_rows' => count($products), 'errors' => $errors])]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'errors' => $errors,
        'message' => "Imported $imported products"
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()]);
}