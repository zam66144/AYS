<?php
session_start();
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once '../config.php';
header('Content-Type: application/json');

$range = $_GET['range'] ?? '30';
$sort = $_GET['sort'] ?? 'views';

// Build date filter
$dateCondition = '';
if ($range !== 'all') {
    $days = (int)$range;
    $dateCondition = "AND pa.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
}

try {
    // Get aggregated stats
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(CASE WHEN event_type = 'view' THEN 1 END) as total_views,
            COUNT(CASE WHEN event_type = 'add_to_cart' THEN 1 END) as total_cart_adds,
            COUNT(CASE WHEN event_type = 'purchase' THEN 1 END) as total_purchases
        FROM product_analytics pa
        WHERE 1=1 $dateCondition
    ");
    $stats = $statsStmt->fetch();
    
    $conversionRate = $stats['total_views'] > 0 
        ? round(($stats['total_purchases'] / $stats['total_views']) * 100, 1) 
        : 0;
    
    // Get per-product data
    $orderBy = 'views DESC';
    if ($sort === 'cart') $orderBy = 'cart_adds DESC';
    elseif ($sort === 'purchases') $orderBy = 'purchases DESC';
    elseif ($sort === 'conversion') $orderBy = 'conversion_rate DESC';
    
    $productsStmt = $pdo->query("
        SELECT 
            p.id,
            p.name,
            p.image_url,
            p.category,
            COUNT(CASE WHEN pa.event_type = 'view' THEN 1 END) as views,
            COUNT(CASE WHEN pa.event_type = 'add_to_cart' THEN 1 END) as cart_adds,
            COUNT(CASE WHEN pa.event_type = 'purchase' THEN 1 END) as purchases,
            CASE 
                WHEN COUNT(CASE WHEN pa.event_type = 'view' THEN 1 END) > 0 
                THEN ROUND((COUNT(CASE WHEN pa.event_type = 'purchase' THEN 1 END) * 100.0 / COUNT(CASE WHEN pa.event_type = 'view' THEN 1 END)), 2)
                ELSE 0 
            END as conversion_rate
        FROM products p
        LEFT JOIN product_analytics pa ON p.id = pa.product_id $dateCondition
        GROUP BY p.id, p.name, p.image_url, p.category
        ORDER BY $orderBy
        LIMIT 50
    ");
    $products = $productsStmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'totalViews' => (int)$stats['total_views'],
            'totalCartAdds' => (int)$stats['total_cart_adds'],
            'totalPurchases' => (int)$stats['total_purchases'],
            'conversionRate' => $conversionRate
        ],
        'products' => $products
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}