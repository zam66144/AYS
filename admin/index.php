<?php
session_start();
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}
require_once '../config.php';

// ==========================
// IMAGE UPLOAD HANDLER
// ==========================
function uploadImage($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload failed.'];
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed)) {
        return ['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are allowed.'];
    }
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . rand(1000, 9999) . '.' . $ext;
    $targetPath = $uploadDir . $filename;
    $maxWidth = 1600; $maxHeight = 900; $quality = 85;
    list($width, $height) = getimagesize($file['tmp_name']);
    $source = null;
    switch ($file['type']) {
        case 'image/jpeg': $source = imagecreatefromjpeg($file['tmp_name']); break;
        case 'image/png': $source = imagecreatefrompng($file['tmp_name']); break;
        case 'image/webp': $source = imagecreatefromwebp($file['tmp_name']); break;
    }
    if (!$source) return ['success' => false, 'message' => 'Unable to process image.'];
    $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
    $newWidth = (int)($width * $ratio);
    $newHeight = (int)($height * $ratio);
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($newImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    switch ($file['type']) {
        case 'image/jpeg': imagejpeg($newImage, $targetPath, $quality); break;
        case 'image/png': imagepng($newImage, $targetPath); break;
        case 'image/webp': imagewebp($newImage, $targetPath, $quality); break;
    }
    imagedestroy($source);
    imagedestroy($newImage);
    return ['success' => true, 'path' => 'uploads/' . $filename];
}

// ==========================
// HANDLE AJAX REQUESTS
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // ---- Update Single Stock ----
    if ($_POST['action'] === 'update_stock') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $newStock = (int)($_POST['stock'] ?? 0);
        
        if ($productId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $oldStock = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $success = $stmt->execute([$newStock, $productId]);
        
        if ($success) {
            try {
                $logStmt = $pdo->prepare("INSERT INTO stock_logs (product_id, change_type, quantity, old_stock, new_stock, note) VALUES (?, 'adjust', ?, ?, ?, ?)");
                $logStmt->execute([$productId, abs($newStock - $oldStock), $oldStock, $newStock, 'Manual adjustment by admin']);
            } catch (Exception $e) {}
        }
        
        echo json_encode(['success' => $success, 'message' => $success ? 'Stock updated!' : 'Failed to update']);
        exit;
    }
    
    // ---- Add Stock ----
    if ($_POST['action'] === 'add_stock') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        
        if ($productId <= 0 || $qty <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $oldStock = (int)$stmt->fetchColumn();
        $newStock = $oldStock + $qty;
        
        $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $success = $stmt->execute([$newStock, $productId]);
        
        if ($success) {
            try {
                $logStmt = $pdo->prepare("INSERT INTO stock_logs (product_id, change_type, quantity, old_stock, new_stock, note) VALUES (?, 'add', ?, ?, ?, ?)");
                $logStmt->execute([$productId, $qty, $oldStock, $newStock, 'Stock added by admin']);
            } catch (Exception $e) {}
        }
        
        echo json_encode(['success' => $success, 'message' => $success ? "Added $qty to stock. New total: $newStock" : 'Failed']);
        exit;
    }
    
    // ---- UPDATE PRODUCT DISCOUNT ----
    if ($_POST['action'] === 'update_discount') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $discountPercent = (float)($_POST['discount_percent'] ?? 0);
        
        if ($productId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product']);
            exit;
        }
        
        if ($discountPercent < 0 || $discountPercent > 100) {
            echo json_encode(['success' => false, 'message' => 'Discount must be between 0 and 100']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $originalPrice = (float)$stmt->fetchColumn();
        
        $salePrice = $originalPrice - ($originalPrice * $discountPercent / 100);
        $isOnSale = $discountPercent > 0 ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE products SET discount_percent = ?, sale_price = ?, is_on_sale = ? WHERE id = ?");
        $success = $stmt->execute([$discountPercent, $salePrice, $isOnSale, $productId]);
        
        echo json_encode([
            'success' => $success, 
            'message' => $success ? "Discount {$discountPercent}% applied! Sale price: PKR " . number_format($salePrice, 2) : 'Failed',
            'sale_price' => $salePrice,
            'discount_percent' => $discountPercent
        ]);
        exit;
    }
    
    // ---- DELETE PRODUCT IMAGE ----
    if ($_POST['action'] === 'delete_product_image') {
        $imageId = (int)($_POST['image_id'] ?? 0);
        
        if ($imageId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid image ID']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE id = ?");
            $stmt->execute([$imageId]);
            $img = $stmt->fetch();
            
            if ($img) {
                $filePath = '../' . $img['image_url'];
                if (file_exists($filePath) && strpos($img['image_url'], 'uploads/') === 0) {
                    @unlink($filePath);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
            $stmt->execute([$imageId]);
            
            echo json_encode(['success' => true, 'message' => 'Image deleted']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // ---- UPDATE TRACKING STATUS ----
    if ($_POST['action'] === 'update_tracking') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $tracking_status = $_POST['tracking_status'] ?? 'order_placed';
        $tracking_note = trim($_POST['tracking_note'] ?? '');
        
        $allowed_statuses = ['order_placed', 'confirmed', 'packed', 'dispatched', 'in_transit', 'out_for_delivery', 'delivered', 'cancelled'];
        
        if ($order_id <= 0 || !in_array($tracking_status, $allowed_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE orders SET tracking_status = ?, tracking_note = ? WHERE id = ?");
            $stmt->execute([$tracking_status, $tracking_note, $order_id]);
            
            try {
                $logStmt = $pdo->prepare("INSERT INTO order_tracking_logs (order_id, status, note) VALUES (?, ?, ?)");
                $logStmt->execute([$order_id, $tracking_status, $tracking_note]);
            } catch (Exception $e) {}
            
            if (in_array($tracking_status, ['confirmed', 'packed', 'dispatched', 'in_transit', 'out_for_delivery', 'delivered'])) {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'processed' WHERE id = ?");
                $stmt->execute([$order_id]);
            } elseif ($tracking_status === 'cancelled') {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$order_id]);
            } elseif ($tracking_status === 'order_placed') {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'pending' WHERE id = ?");
                $stmt->execute([$order_id]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Tracking updated successfully!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // ---- CONFIRM ORDER ----
    if ($_POST['action'] === 'confirm_order') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        
        if ($order_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT product_id, quantity, status FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            
            if (!$order) {
                echo json_encode(['success' => false, 'message' => 'Order not found']);
                exit;
            }
            
            if ($order['status'] === 'processed') {
                echo json_encode(['success' => false, 'message' => 'Order already processed']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE orders SET status = 'processed', tracking_status = 'confirmed' WHERE id = ?");
            $stmt->execute([$order_id]);
            
            if ($order['product_id'] > 0) {
                $stmt = $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?");
                $stmt->execute([$order['quantity'], $order['product_id']]);
            }
            
            try {
                $logStmt = $pdo->prepare("INSERT INTO order_tracking_logs (order_id, status, note) VALUES (?, 'confirmed', 'Order confirmed by admin')");
                $logStmt->execute([$order_id]);
            } catch (Exception $e) {}
            
            echo json_encode(['success' => true, 'message' => 'Order confirmed & stock updated!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // ============================================================
    // RETURN MANAGEMENT ACTIONS
    // ============================================================
    
    if ($_POST['action'] === 'approve_return') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        
        if ($order_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT return_status FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $current = $stmt->fetchColumn();
            
            if ($current !== 'requested') {
                echo json_encode(['success' => false, 'message' => 'Return is not in requested state.']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE orders SET return_status = 'approved', admin_notes = ? WHERE id = ?");
            $stmt->execute([$note, $order_id]);
            
            echo json_encode(['success' => true, 'message' => 'Return approved successfully!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'reject_return') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        
        if ($order_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
            exit;
        }
        
        if (empty($note)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a rejection reason.']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT return_status FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $current = $stmt->fetchColumn();
            
            if ($current !== 'requested') {
                echo json_encode(['success' => false, 'message' => 'Return is not in requested state.']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE orders SET return_status = 'rejected', admin_notes = ? WHERE id = ?");
            $stmt->execute([$note, $order_id]);
            
            echo json_encode(['success' => true, 'message' => 'Return rejected.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'complete_return') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $method = trim($_POST['method'] ?? 'card');
        $note = trim($_POST['note'] ?? '');
        
        if ($order_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT return_status, product_id, quantity FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            
            if (!$order) {
                echo json_encode(['success' => false, 'message' => 'Order not found']);
                exit;
            }
            
            if ($order['return_status'] !== 'approved') {
                echo json_encode(['success' => false, 'message' => 'Return must be approved first.']);
                exit;
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET return_status = 'returned',
                    status = 'returned',
                    refund_amount = ?,
                    refund_method = ?,
                    return_processed_at = NOW(),
                    admin_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$amount, $method, $note, $order_id]);
            
            if ($order['product_id'] > 0 && $order['quantity'] > 0) {
                $stmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                $stmt->execute([$order['quantity'], $order['product_id']]);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => "Return completed! Refund: PKR {$amount} via {$method}. Stock restored."
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
}

// ==========================
// FETCH STATS
// ==========================
$stats = [];
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
while ($row = $stmt->fetch()) {
    $stats[$row['status']] = $row['count'];
}
$total = array_sum($stats);

// ==========================
// FETCH RETURN STATS
// ==========================
$returnStats = ['requested' => 0, 'approved' => 0, 'returned' => 0, 'rejected' => 0];
try {
    $stmt = $pdo->query("SELECT return_status, COUNT(*) as count FROM orders WHERE return_status IS NOT NULL GROUP BY return_status");
    while ($row = $stmt->fetch()) {
        $returnStats[$row['return_status']] = (int)$row['count'];
    }
} catch (Exception $e) {}
$totalReturns = array_sum($returnStats);

// ==========================
// FETCH HERO BANNERS
// ==========================
$heroBannerStmt = $pdo->query("SELECT * FROM banners WHERE is_active = 1 AND banner_type = 'hero' ORDER BY id DESC");
$heroBanners = $heroBannerStmt->fetchAll();

// ==========================
// FETCH FLASH BANNERS
// ==========================
$flashBannerStmt = $pdo->query("SELECT * FROM banners WHERE banner_type = 'flash' ORDER BY id DESC");
$flashBanners = $flashBannerStmt->fetchAll();

// ==========================
// FETCH ALL PRODUCTS
// ==========================
$productStmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
$allProducts = $productStmt->fetchAll();

// Fetch all product images
$productImagesMap = [];
try {
    $imgStmt = $pdo->query("SELECT * FROM product_images ORDER BY product_id ASC, display_order ASC, id ASC");
    while ($img = $imgStmt->fetch()) {
        $productImagesMap[$img['product_id']][] = $img;
    }
} catch (Exception $e) {}

foreach ($allProducts as &$p) {
    $p['images'] = $productImagesMap[$p['id']] ?? [];
}
unset($p);

// ==========================
// FETCH ALL ORDERS
// ==========================
$orderStmt = $pdo->query("
    SELECT 
        o.*, 
        c.name AS client_name, 
        c.email AS client_email,
        c.phone AS client_phone,
        p.image_url AS product_image,
        p.description AS product_description
    FROM orders o 
    LEFT JOIN clients c ON o.client_id = c.id 
    LEFT JOIN products p ON o.product_id = p.id
    ORDER BY o.id DESC
");
$allOrders = $orderStmt->fetchAll();

// ==========================
// FETCH RETURN REQUESTS
// ==========================
$returnStmt = $pdo->query("
    SELECT 
        o.*, 
        c.name AS client_name, 
        c.email AS client_email,
        c.phone AS client_phone,
        p.image_url AS product_image,
        p.price AS product_price
    FROM orders o 
    LEFT JOIN clients c ON o.client_id = c.id 
    LEFT JOIN products p ON o.product_id = p.id
    WHERE o.return_status IS NOT NULL
    ORDER BY 
        CASE o.return_status 
            WHEN 'requested' THEN 1 
            WHEN 'approved' THEN 2 
            WHEN 'returned' THEN 3 
            WHEN 'rejected' THEN 4 
        END,
        o.return_requested_at DESC
");
$returnRequests = $returnStmt->fetchAll();

// ==========================
// STOCK STATISTICS
// ==========================
$stockStats = [
    'perfume' => ['total_stock' => 0, 'sold' => 0, 'products' => 0, 'value' => 0],
    'tester' => ['total_stock' => 0, 'sold' => 0, 'products' => 0, 'value' => 0],
    'watch' => ['total_stock' => 0, 'sold' => 0, 'products' => 0, 'value' => 0],
    'glass' => ['total_stock' => 0, 'sold' => 0, 'products' => 0, 'value' => 0],
];

function getCategoryFromName($name) {
    $n = strtolower($name);
    if (strpos($n, 'tester') !== false) return 'tester';
    if (strpos($n, 'watch') !== false) return 'watch';
    if (strpos($n, 'glass') !== false || strpos($n, 'eyewear') !== false || strpos($n, 'aviator') !== false || strpos($n, 'wayfarer') !== false) return 'glass';
    return 'perfume';
}

foreach ($allProducts as $p) {
    $cat = getCategoryFromName($p['name']);
    $stockStats[$cat]['total_stock'] += (int)$p['stock'];
    $stockStats[$cat]['products'] += 1;
    $stockStats[$cat]['value'] += ((int)$p['stock'] * (float)$p['price']);
}

$soldStmt = $pdo->query("SELECT product_name, SUM(quantity) as total_sold, SUM(total) as revenue FROM orders WHERE status IN ('pending','processed') GROUP BY product_name");
$soldData = $soldStmt->fetchAll();
$soldStats = [
    'perfume' => ['sold' => 0, 'revenue' => 0],
    'tester' => ['sold' => 0, 'revenue' => 0],
    'watch' => ['sold' => 0, 'revenue' => 0],
    'glass' => ['sold' => 0, 'revenue' => 0],
];
foreach ($soldData as $s) {
    $cat = getCategoryFromName($s['product_name']);
    $soldStats[$cat]['sold'] += (int)$s['total_sold'];
    $soldStats[$cat]['revenue'] += (float)$s['revenue'];
}

$lowStockCount = 0;
foreach ($allProducts as $p) {
    if ((int)$p['stock'] < 10) $lowStockCount++;
}

$totalSold = array_sum(array_column($soldStats, 'sold'));

// ==========================
// SOLD ITEMS DETAIL
// ==========================
$soldItemsDetail = [];
try {
    $detailStmt = $pdo->query("
        SELECT 
            o.id AS order_id,
            o.product_name,
            o.product_id,
            o.quantity,
            o.total,
            o.created_at,
            o.status,
            c.name AS client_name,
            p.image_url AS product_image,
            p.price AS original_price,
            p.discount_percent,
            p.sale_price
        FROM orders o
        LEFT JOIN clients c ON o.client_id = c.id
        LEFT JOIN products p ON o.product_id = p.id
        WHERE o.status IN ('pending', 'processed')
        ORDER BY o.created_at DESC
        LIMIT 500
    ");
    $soldItemsDetail = $detailStmt->fetchAll();
} catch (Exception $e) {}

// ==========================
// NEW: MONTHLY SALES REPORT DATA
// ==========================
$currentYear = isset($_GET['report_year']) ? (int)$_GET['report_year'] : (int)date('Y');
$currentMonth = isset($_GET['report_month']) ? (int)$_GET['report_month'] : (int)date('n');

// Get available years from orders
$availableYears = [];
try {
    $yearStmt = $pdo->query("SELECT DISTINCT YEAR(created_at) as yr FROM orders WHERE created_at IS NOT NULL ORDER BY yr DESC");
    while ($row = $yearStmt->fetch()) {
        $availableYears[] = (int)$row['yr'];
    }
} catch (Exception $e) {}

if (empty($availableYears)) {
    $availableYears = [(int)date('Y')];
}

// Monthly summary for selected year (all 12 months)
$monthlyData = [];
$monthlyLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$monthlyFullNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

for ($m = 1; $m <= 12; $m++) {
    $monthlyData[$m] = [
        'month' => $m,
        'name' => $monthlyFullNames[$m - 1],
        'short' => $monthlyLabels[$m - 1],
        'orders_count' => 0,
        'items_sold' => 0,
        'revenue' => 0,
        'refunds' => 0,
        'net_revenue' => 0,
        'perfume' => 0, 'tester' => 0, 'watch' => 0, 'glass' => 0,
        'perfume_revenue' => 0, 'tester_revenue' => 0, 'watch_revenue' => 0, 'glass_revenue' => 0,
    ];
}

try {
    // Monthly orders + revenue (excluding cancelled)
    $monthlyStmt = $pdo->prepare("
        SELECT 
            MONTH(created_at) as m,
            COUNT(*) as orders_count,
            SUM(quantity) as items_sold,
            SUM(total) as revenue
        FROM orders
        WHERE YEAR(created_at) = ?
        AND status IN ('pending', 'processed', 'returned')
        GROUP BY MONTH(created_at)
        ORDER BY m
    ");
    $monthlyStmt->execute([$currentYear]);
    while ($row = $monthlyStmt->fetch()) {
        $m = (int)$row['m'];
        if (isset($monthlyData[$m])) {
            $monthlyData[$m]['orders_count'] = (int)$row['orders_count'];
            $monthlyData[$m]['items_sold'] = (int)$row['items_sold'];
            $monthlyData[$m]['revenue'] = (float)$row['revenue'];
        }
    }
    
    // Category-wise monthly breakdown
    $catStmt = $pdo->prepare("
        SELECT 
            MONTH(o.created_at) as m,
            o.product_name,
            SUM(o.quantity) as qty,
            SUM(o.total) as rev
        FROM orders o
        WHERE YEAR(o.created_at) = ?
        AND o.status IN ('pending', 'processed', 'returned')
        GROUP BY MONTH(o.created_at), o.product_name
    ");
    $catStmt->execute([$currentYear]);
    while ($row = $catStmt->fetch()) {
        $m = (int)$row['m'];
        if (!isset($monthlyData[$m])) continue;
        
        $cat = getCategoryFromName($row['product_name']);
        if (isset($monthlyData[$m][$cat])) {
            $monthlyData[$m][$cat] += (int)$row['qty'];
            $monthlyData[$m][$cat . '_revenue'] += (float)$row['rev'];
        }
    }
    
    // Refunds per month
    $refundStmt = $pdo->prepare("
        SELECT 
            MONTH(return_processed_at) as m,
            SUM(refund_amount) as refunds
        FROM orders
        WHERE YEAR(return_processed_at) = ?
        AND return_status = 'returned'
        GROUP BY MONTH(return_processed_at)
    ");
    $refundStmt->execute([$currentYear]);
    while ($row = $refundStmt->fetch()) {
        $m = (int)$row['m'];
        if (isset($monthlyData[$m])) {
            $monthlyData[$m]['refunds'] = (float)$row['refunds'];
        }
    }
} catch (Exception $e) {}

// Calculate net revenue
foreach ($monthlyData as $m => &$data) {
    $data['net_revenue'] = $data['revenue'] - $data['refunds'];
}
unset($data);

// Year totals
$yearTotal = [
    'orders_count' => 0, 'items_sold' => 0, 'revenue' => 0, 'refunds' => 0, 'net_revenue' => 0,
    'perfume' => 0, 'tester' => 0, 'watch' => 0, 'glass' => 0,
];
foreach ($monthlyData as $data) {
    $yearTotal['orders_count'] += $data['orders_count'];
    $yearTotal['items_sold'] += $data['items_sold'];
    $yearTotal['revenue'] += $data['revenue'];
    $yearTotal['refunds'] += $data['refunds'];
    $yearTotal['net_revenue'] += $data['net_revenue'];
    $yearTotal['perfume'] += $data['perfume'];
    $yearTotal['tester'] += $data['tester'];
    $yearTotal['watch'] += $data['watch'];
    $yearTotal['glass'] += $data['glass'];
}

// Top selling products of selected month
$topProductsMonth = [];
try {
    $topStmt = $pdo->prepare("
        SELECT 
            product_name,
            SUM(quantity) as total_sold,
            SUM(total) as revenue,
            COUNT(*) as order_count
        FROM orders
        WHERE YEAR(created_at) = ?
        AND MONTH(created_at) = ?
        AND status IN ('pending', 'processed')
        GROUP BY product_name
        ORDER BY total_sold DESC
        LIMIT 10
    ");
    $topStmt->execute([$currentYear, $currentMonth]);
    $topProductsMonth = $topStmt->fetchAll();
} catch (Exception $e) {}

// Top selling products of whole year
$topProductsYear = [];
try {
    $topYearStmt = $pdo->prepare("
        SELECT 
            product_name,
            SUM(quantity) as total_sold,
            SUM(total) as revenue,
            COUNT(*) as order_count
        FROM orders
        WHERE YEAR(created_at) = ?
        AND status IN ('pending', 'processed')
        GROUP BY product_name
        ORDER BY total_sold DESC
        LIMIT 10
    ");
    $topYearStmt->execute([$currentYear]);
    $topProductsYear = $topYearStmt->fetchAll();
} catch (Exception $e) {}

// Selected month data
$selectedMonthData = $monthlyData[$currentMonth];
$prevMonthData = ($currentMonth > 1) ? $monthlyData[$currentMonth - 1] : null;
$growthPercent = 0;
if ($prevMonthData && $prevMonthData['revenue'] > 0) {
    $growthPercent = (($selectedMonthData['revenue'] - $prevMonthData['revenue']) / $prevMonthData['revenue']) * 100;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Mr.AYS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/admin/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .banner-card, .product-card-admin { 
            background: var(--bg-card); border-radius: 12px; padding: 15px; 
            box-shadow: var(--shadow-sm); margin-bottom: 15px; 
            border: 1px solid var(--border-color); transition: all 0.3s ease; 
        }
        .banner-card:hover, .product-card-admin:hover { 
            transform: translateY(-3px); box-shadow: var(--shadow-lg); 
            border-color: var(--gold); 
        }
        .banner-card img, .product-card-admin img { 
            width: 100%; height: 140px; object-fit: cover; 
            border-radius: 8px; margin-bottom: 10px; 
        }
        .banner-title, .product-name { font-weight: 700; font-size: 0.95rem; margin-bottom: 5px; }
        .banner-subtitle { color: var(--text-muted); font-size: 0.85rem; margin-bottom: 10px; }
        .badge-text { 
            display: inline-block; padding: 4px 12px; border-radius: 20px; 
            font-size: 0.75rem; font-weight: 600; margin-bottom: 10px; 
        }
        .banner-actions, .product-actions { display: flex; gap: 8px; margin-top: 10px; }
        .btn-sm-action { 
            padding: 6px 14px; font-size: 0.8rem; border-radius: 8px; 
            border: none; cursor: pointer; transition: all 0.3s ease; 
        }
        .btn-edit { background: var(--gold); color: var(--primary-dark); }
        .btn-delete { background: #f8d7da; color: #721c24; }
        .btn-edit:hover { background: var(--gold-hover); }
        .btn-delete:hover { background: #dc3545; color: white; }
        .modal { 
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; 
            align-items: center; backdrop-filter: blur(8px); 
        }
        .modal.active { display: flex; }
        .modal-content { 
            background: var(--bg-card); border-radius: 16px; max-width: 600px; 
            width: 90%; padding: 30px; box-shadow: var(--shadow-lg); 
            max-height: 90vh; overflow-y: auto;
        }
        .modal-content h4 { margin-bottom: 20px; }
        .modal-content .form-control { 
            border-radius: 10px; padding: 10px 15px; 
            border: 2px solid var(--border-color); margin-bottom: 15px; 
        }
        .modal-content .form-control:focus { 
            border-color: var(--gold); box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1); 
        }
        .modal-content .btn { 
            padding: 10px 24px; border-radius: 10px; font-weight: 600; margin-right: 10px; 
        }
        .modal-content .btn-primary { background: var(--gold); border: none; color: var(--primary-dark); }
        .modal-content .btn-secondary { background: var(--bg-secondary); border: none; color: var(--text-primary); }
        .form-label { font-weight: 600; font-size: 0.9rem; }
        .image-preview { 
            width: 100%; height: 150px; object-fit: cover; border-radius: 8px; 
            margin-top: 10px; border: 2px dashed var(--border-color); 
        }
        
        /* ORDER CARDS */
        .order-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 20px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            margin-bottom: 16px;
            height: 100%;
        }
        .order-card:hover {
            box-shadow: var(--shadow-lg);
            border-color: var(--gold);
            transform: translateY(-2px);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 10px;
        }
        .order-id { font-weight: 800; color: var(--gold); font-size: 1.05rem; }
        .order-date { color: var(--text-muted); font-size: 0.8rem; }
        .order-status {
            padding: 5px 14px; border-radius: 20px; font-size: 0.75rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processed { background: #d4edda; color: #155724; }
        .status-returned { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #e2e3e5; color: #383d41; }
        
        .order-customer {
            display: flex; flex-direction: column; gap: 6px; margin-bottom: 15px; font-size: 0.85rem;
        }
        .order-customer span { 
            color: var(--text-secondary); display: flex; align-items: flex-start; gap: 6px;
        }
        .order-customer strong { color: var(--text-primary); }
        
        .order-product-display {
            background: var(--bg-secondary); border-radius: 10px; padding: 12px;
            margin-bottom: 15px; display: flex; gap: 12px; align-items: center;
            border: 1px solid var(--border-color);
        }
        .order-product-display img {
            width: 60px; height: 60px; object-fit: cover; border-radius: 10px;
            border: 1px solid var(--border-color); flex-shrink: 0;
        }
        .order-product-display .product-info { flex: 1; min-width: 0; }
        .order-product-display .product-info .pname {
            font-weight: 700; font-size: 0.9rem; margin-bottom: 4px; color: var(--text-primary);
        }
        .order-product-display .product-info .pmeta { color: var(--text-muted); font-size: 0.78rem; }
        
        .order-footer {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 10px; padding-top: 12px; border-top: 1px solid var(--border-color);
        }
        .order-total { font-size: 1.15rem; font-weight: 800; color: var(--text-primary); }
        .order-total small {
            color: var(--text-muted); font-size: 0.72rem; font-weight: 500;
            display: block; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        
        .btn-confirm {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white; border: none; padding: 9px 20px; border-radius: 10px;
            font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.3s ease;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-confirm:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4); }
        
        .screenshot-btn {
            background: var(--bg-secondary); border: 1px solid var(--border-color);
            color: var(--text-primary); padding: 6px 14px; border-radius: 8px;
            font-size: 0.8rem; cursor: pointer; transition: all 0.3s ease;
            display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
        }
        .screenshot-btn:hover { background: var(--gold); color: var(--primary-dark); border-color: var(--gold); }
        
        .receipt-btn {
            background: linear-gradient(135deg, var(--gold), #b8941f);
            color: var(--primary-dark); border: none; padding: 8px 16px;
            border-radius: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer;
            transition: all 0.3s ease; display: inline-flex; align-items: center;
            gap: 6px; text-decoration: none;
        }
        .receipt-btn:hover {
            transform: translateY(-2px); box-shadow: 0 8px 20px rgba(212, 175, 55, 0.4);
            color: var(--primary-dark);
        }
        
        /* TRACKING SECTION */
        .tracking-control {
            margin-top: 14px; padding: 14px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(99, 102, 241, 0.02));
            border-radius: 12px; border: 1px solid rgba(99, 102, 241, 0.2);
        }
        .tracking-control-label {
            font-size: 0.72rem; color: #6366f1; text-transform: uppercase;
            letter-spacing: 1px; font-weight: 700; margin-bottom: 10px;
            display: flex; align-items: center; gap: 6px;
        }
        .tracking-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .tracking-select {
            flex: 1; min-width: 160px; padding: 9px 14px; border-radius: 10px;
            border: 2px solid var(--border-color); background: var(--bg-card);
            color: var(--text-primary); font-size: 0.85rem; font-weight: 600;
            outline: none; cursor: pointer; transition: all 0.3s ease;
        }
        .tracking-select:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
        .tracking-note-input {
            flex: 2; min-width: 180px; padding: 9px 14px; border-radius: 10px;
            border: 2px solid var(--border-color); background: var(--bg-card);
            color: var(--text-primary); font-size: 0.82rem; outline: none; transition: all 0.3s ease;
        }
        .tracking-note-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
        .btn-save-tracking {
            background: linear-gradient(135deg, #6366f1, #4338ca);
            color: white; border: none; padding: 9px 18px; border-radius: 10px;
            font-weight: 700; font-size: 0.82rem; cursor: pointer;
            transition: all 0.3s ease; display: inline-flex; align-items: center;
            gap: 6px; white-space: nowrap;
        }
        .btn-save-tracking:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4); }
        .btn-save-tracking:disabled { opacity: 0.6; cursor: wait; transform: none; }
        
        .tracking-badge-current {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 20px; font-size: 0.72rem;
            font-weight: 700; margin-bottom: 10px; text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .empty-orders { text-align: center; padding: 80px 20px; color: var(--text-muted); }
        .empty-orders i { font-size: 4rem; margin-bottom: 20px; color: var(--gold); opacity: 0.5; display: block; }
        
        .screenshot-modal {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9);
            z-index: 99999; justify-content: center; align-items: center;
            backdrop-filter: blur(10px); cursor: pointer;
        }
        .screenshot-modal.active { display: flex; }
        .screenshot-modal img {
            max-width: 90%; max-height: 90%; border-radius: 12px; border: 2px solid var(--gold);
        }
        .screenshot-modal .modal-close {
            position: absolute; top: 20px; right: 30px; background: var(--gold);
            color: var(--primary-dark); border: none; width: 45px; height: 45px;
            border-radius: 50%; font-size: 1.3rem; cursor: pointer; font-weight: 700;
        }
        
        /* STOCK STYLES */
        .stock-card {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-secondary));
            border-radius: 16px; padding: 22px; border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm); transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
            position: relative; overflow: hidden; height: 100%;
        }
        .stock-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 5px;
            height: 100%; background: var(--gold); transition: width 0.3s ease;
        }
        .stock-card:hover::before { width: 8px; }
        .stock-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); border-color: var(--gold); }
        .stock-card.tester::before { background: #6366f1; }
        .stock-card.watch::before { background: #06b6d4; }
        .stock-card.glass::before { background: #a855f7; }
        .stock-card.perfume::before { background: #d4af37; }
        
        .stock-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .stock-icon {
            width: 50px; height: 50px; border-radius: 12px; display: flex;
            align-items: center; justify-content: center; font-size: 1.5rem;
            color: white; background: linear-gradient(135deg, var(--gold), #b8941f);
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.3);
        }
        .stock-card.tester .stock-icon { background: linear-gradient(135deg, #6366f1, #4338ca); box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3); }
        .stock-card.watch .stock-icon { background: linear-gradient(135deg, #06b6d4, #0891b2); box-shadow: 0 8px 20px rgba(6, 182, 212, 0.3); }
        .stock-card.glass .stock-icon { background: linear-gradient(135deg, #a855f7, #7e22ce); box-shadow: 0 8px 20px rgba(168, 85, 247, 0.3); }
        
        .stock-label {
            font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;
            color: var(--text-muted); font-weight: 600; margin-bottom: 4px;
        }
        .stock-value { font-size: 2rem; font-weight: 800; color: var(--text-primary); line-height: 1.1; }
        .stock-sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 6px; }
        .stock-sub strong { color: var(--text-primary); }
        
        .stock-bar {
            height: 6px; background: var(--bg-primary); border-radius: 10px;
            overflow: hidden; margin-top: 12px; position: relative;
        }
        .stock-bar-fill {
            height: 100%; border-radius: 10px;
            background: linear-gradient(90deg, var(--gold), #f7d26b);
            transition: width 0.6s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .stock-bar-fill.low { background: linear-gradient(90deg, #ef4444, #f87171); }
        .stock-bar-fill.medium { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .stock-bar-fill.good { background: linear-gradient(90deg, #10b981, #34d399); }
        
        .stock-table-container {
            background: var(--bg-card); border-radius: 16px;
            border: 1px solid var(--border-color); overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .stock-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .stock-table thead {
            background: linear-gradient(135deg, var(--primary-dark), #1a2c4a); color: white;
        }
        .stock-table thead th {
            padding: 16px 14px; font-size: 0.75rem; text-transform: uppercase;
            letter-spacing: 1px; font-weight: 700; text-align: left; white-space: nowrap;
        }
        .stock-table tbody tr {
            transition: all 0.2s ease; border-bottom: 1px solid var(--border-color);
        }
        .stock-table tbody tr:hover { background: var(--bg-secondary); }
        .stock-table tbody tr:last-child { border-bottom: none; }
        .stock-table tbody td {
            padding: 14px; font-size: 0.88rem; color: var(--text-primary); vertical-align: middle;
        }
        .stock-table .product-cell {
            display: flex; align-items: center; gap: 12px; min-width: 220px;
        }
        .stock-table .product-cell img {
            width: 45px; height: 45px; object-fit: cover;
            border-radius: 8px; border: 1px solid var(--border-color);
        }
        .stock-table .product-cell .pname { font-weight: 600; font-size: 0.88rem; line-height: 1.2; }
        .stock-table .product-cell .pid { font-size: 0.7rem; color: var(--text-muted); }
        
        .cat-badge {
            display: inline-block; padding: 4px 12px; border-radius: 20px;
            font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.5px; color: white;
        }
        .cat-badge.perfume { background: linear-gradient(135deg, #d4af37, #b8941f); }
        .cat-badge.tester { background: linear-gradient(135deg, #6366f1, #4338ca); }
        .cat-badge.watch { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .cat-badge.glass { background: linear-gradient(135deg, #a855f7, #7e22ce); }
        
        .stock-input-group { display: inline-flex; align-items: center; gap: 6px; }
        .stock-input {
            width: 70px; padding: 6px 10px; border-radius: 8px;
            border: 2px solid var(--border-color); background: var(--bg-secondary);
            color: var(--text-primary); font-size: 0.85rem; font-weight: 600;
            text-align: center; transition: all 0.3s ease;
        }
        .stock-input:focus {
            border-color: var(--gold); outline: none;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
        }
        .btn-stock-save {
            background: var(--gold); color: var(--primary-dark); border: none;
            width: 34px; height: 34px; border-radius: 8px; cursor: pointer;
            font-size: 0.9rem; transition: all 0.3s ease; display: inline-flex;
            align-items: center; justify-content: center;
        }
        .btn-stock-save:hover { background: var(--gold-hover); transform: scale(1.05); }
        .btn-stock-save:disabled { opacity: 0.5; cursor: wait; }
        
        .stock-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700;
        }
        .stock-pill.high { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .stock-pill.medium { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .stock-pill.low { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
        .stock-pill.out { background: rgba(107, 114, 128, 0.2); color: #4b5563; }
        
        .sold-pill {
            background: rgba(99, 102, 241, 0.15); color: #4f46e5;
            padding: 5px 12px; border-radius: 20px; font-size: 0.78rem;
            font-weight: 700; display: inline-flex; align-items: center; gap: 5px;
        }
        
        .search-stock {
            background: var(--bg-secondary); border: 2px solid var(--border-color);
            border-radius: 12px; padding: 10px 18px; color: var(--text-primary);
            font-size: 0.9rem; width: 100%; max-width: 320px; transition: all 0.3s ease;
        }
        .search-stock:focus { border-color: var(--gold); outline: none; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15); }
        
        .filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
        .filter-tab {
            background: var(--bg-card); border: 2px solid var(--border-color);
            color: var(--text-secondary); padding: 8px 18px; border-radius: 30px;
            font-size: 0.82rem; font-weight: 600; cursor: pointer;
            transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 6px;
        }
        .filter-tab:hover { border-color: var(--gold); color: var(--gold); }
        .filter-tab.active { background: var(--gold); color: var(--primary-dark); border-color: var(--gold); }
        
        .low-stock-alert {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-left: 4px solid #f59e0b; border-radius: 12px;
            padding: 16px 20px; margin-bottom: 20px; display: flex;
            align-items: center; gap: 12px; animation: pulseAlert 2s ease-in-out infinite;
        }
        .low-stock-alert i { font-size: 1.5rem; color: #d97706; }
        .low-stock-alert strong { color: #92400e; }
        @keyframes pulseAlert {
            0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
        }
        
        .section-title {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 18px; padding-bottom: 10px; border-bottom: 2px solid var(--border-color);
        }
        .section-title h5 { margin: 0; font-weight: 800; font-size: 1.05rem; color: var(--text-primary); }
        .section-title i { color: var(--gold); font-size: 1.2rem; }
        
        .receipt-number-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 12px;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.15), rgba(212, 175, 55, 0.05));
            border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 20px;
            font-size: 0.68rem; font-weight: 700; color: var(--gold);
            letter-spacing: 0.5px; margin-top: 5px;
        }

        /* RETURNS STYLES */
        .return-card {
            background: var(--bg-card); border-radius: 14px; padding: 20px;
            border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);
            transition: all 0.3s ease; margin-bottom: 16px;
            position: relative; overflow: hidden;
        }
        .return-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 5px; height: 100%;
        }
        .return-card[data-return-status="requested"]::before { background: #f59e0b; }
        .return-card[data-return-status="approved"]::before { background: #3b82f6; }
        .return-card[data-return-status="returned"]::before { background: #10b981; }
        .return-card[data-return-status="rejected"]::before { background: #ef4444; }
        .return-card:hover { box-shadow: var(--shadow-lg); border-color: var(--gold); transform: translateY(-2px); }
        
        .return-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            gap: 15px; flex-wrap: wrap; padding-bottom: 15px;
            border-bottom: 1px dashed var(--border-color); margin-bottom: 15px;
        }
        
        .return-status-badge {
            padding: 6px 16px; border-radius: 50px; font-size: 0.72rem;
            font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .return-badge-requested { background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); }
        .return-badge-approved { background: rgba(59, 130, 246, 0.15); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.3); }
        .return-badge-returned { background: rgba(16, 185, 129, 0.15); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); }
        .return-badge-rejected { background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); }
        
        .return-info-box {
            background: var(--bg-secondary); padding: 12px 16px;
            border-radius: 10px; border-left: 3px solid var(--gold); margin-bottom: 12px;
        }
        .return-info-box .label {
            font-size: 0.68rem; text-transform: uppercase; letter-spacing: 1px;
            color: var(--text-muted); font-weight: 700; margin-bottom: 4px;
        }
        .return-info-box .value { font-size: 0.88rem; color: var(--text-primary); font-weight: 600; }
        
        .return-actions {
            display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
            margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-color);
        }
        .return-actions .note-input {
            flex: 1; min-width: 200px; padding: 9px 14px; border-radius: 10px;
            border: 2px solid var(--border-color); background: var(--bg-card);
            color: var(--text-primary); font-size: 0.82rem; outline: none; transition: all 0.3s ease;
        }
        .return-actions .note-input:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15); }
        
        .btn-approve-return {
            background: linear-gradient(135deg, #10b981, #059669); color: white;
            border: none; padding: 10px 20px; border-radius: 10px; font-weight: 700;
            font-size: 0.82rem; cursor: pointer; transition: all 0.3s ease;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-approve-return:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4); }
        
        .btn-reject-return {
            background: linear-gradient(135deg, #ef4444, #dc2626); color: white;
            border: none; padding: 10px 20px; border-radius: 10px; font-weight: 700;
            font-size: 0.82rem; cursor: pointer; transition: all 0.3s ease;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-reject-return:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4); }
        
        .btn-complete-return {
            background: linear-gradient(135deg, #3b82f6, #2563eb); color: white;
            border: none; padding: 10px 20px; border-radius: 10px; font-weight: 700;
            font-size: 0.82rem; cursor: pointer; transition: all 0.3s ease;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-complete-return:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4); }
        
        .return-stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px; margin-bottom: 25px;
        }
        .return-stat-card {
            padding: 20px; border-radius: 14px; display: flex;
            align-items: center; gap: 14px; border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        .return-stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
        .return-stat-icon {
            width: 50px; height: 50px; border-radius: 12px; display: flex;
            align-items: center; justify-content: center; font-size: 1.4rem;
            color: white; flex-shrink: 0;
        }
        .return-stat-value { font-size: 1.6rem; font-weight: 800; line-height: 1; margin-bottom: 4px; }
        .return-stat-label {
            font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;
            color: var(--text-muted); font-weight: 700;
        }

        /* DISCOUNT BADGE */
        .product-card-admin { position: relative; }
        .discount-badge {
            position: absolute; top: 20px; right: 20px; z-index: 5;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white; padding: 5px 12px; border-radius: 20px;
            font-size: 0.72rem; font-weight: 800; letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
            animation: pulseDiscount 2s ease-in-out infinite;
        }
        @keyframes pulseDiscount {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .price-original-strike {
            text-decoration: line-through;
            color: var(--text-muted);
            font-size: 0.8rem;
            margin-left: 8px;
        }
        .price-sale-highlight {
            color: #ef4444;
            font-weight: 800;
        }
        .btn-discount {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-discount:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(245, 158, 11, 0.4); }

        /* SOLD ITEMS SECTION */
        .sold-summary-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px; margin-bottom: 25px;
        }
        .sold-summary-card {
            padding: 20px; border-radius: 14px; display: flex;
            align-items: center; gap: 14px; border: 1px solid var(--border-color);
            background: var(--bg-card); transition: all 0.3s ease;
            position: relative; overflow: hidden;
        }
        .sold-summary-card::before {
            content: ''; position: absolute; top: 0; left: 0;
            width: 5px; height: 100%; transition: width 0.3s ease;
        }
        .sold-summary-card:hover::before { width: 8px; }
        .sold-summary-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--gold);
        }
        .sold-summary-card.perfume::before { background: #d4af37; }
        .sold-summary-card.tester::before { background: #6366f1; }
        .sold-summary-card.watch::before { background: #06b6d4; }
        .sold-summary-card.glass::before { background: #a855f7; }
        .sold-summary-card.total::before { background: linear-gradient(135deg, #10b981, #059669); }
        
        .sold-summary-icon {
            width: 55px; height: 55px; border-radius: 14px; display: flex;
            align-items: center; justify-content: center; font-size: 1.5rem;
            color: white; flex-shrink: 0;
        }
        .sold-summary-card.perfume .sold-summary-icon { background: linear-gradient(135deg, #d4af37, #b8941f); box-shadow: 0 8px 20px rgba(212, 175, 55, 0.3); }
        .sold-summary-card.tester .sold-summary-icon { background: linear-gradient(135deg, #6366f1, #4338ca); box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3); }
        .sold-summary-card.watch .sold-summary-icon { background: linear-gradient(135deg, #06b6d4, #0891b2); box-shadow: 0 8px 20px rgba(6, 182, 212, 0.3); }
        .sold-summary-card.glass .sold-summary-icon { background: linear-gradient(135deg, #a855f7, #7e22ce); box-shadow: 0 8px 20px rgba(168, 85, 247, 0.3); }
        .sold-summary-card.total .sold-summary-icon { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3); }
        
        .sold-summary-value { font-size: 1.8rem; font-weight: 800; line-height: 1; margin-bottom: 4px; color: var(--text-primary); }
        .sold-summary-label {
            font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px;
            color: var(--text-muted); font-weight: 700;
        }
        .sold-summary-sub {
            font-size: 0.72rem; color: var(--text-muted); margin-top: 4px;
            font-weight: 600;
        }
        .sold-summary-sub strong { color: var(--text-primary); }

        .sold-table-container {
            background: var(--bg-card); border-radius: 16px;
            border: 1px solid var(--border-color); overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .sold-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .sold-table thead {
            background: linear-gradient(135deg, var(--primary-dark), #1a2c4a); color: white;
        }
        .sold-table thead th {
            padding: 14px 12px; font-size: 0.72rem; text-transform: uppercase;
            letter-spacing: 1px; font-weight: 700; text-align: left; white-space: nowrap;
        }
        .sold-table tbody tr {
            transition: all 0.2s ease; border-bottom: 1px solid var(--border-color);
        }
        .sold-table tbody tr:hover { background: var(--bg-secondary); }
        .sold-table tbody tr:last-child { border-bottom: none; }
        .sold-table tbody td {
            padding: 12px; font-size: 0.85rem; color: var(--text-primary); vertical-align: middle;
        }
        .sold-table .product-cell {
            display: flex; align-items: center; gap: 10px; min-width: 200px;
        }
        .sold-table .product-cell img {
            width: 40px; height: 40px; object-fit: cover;
            border-radius: 8px; border: 1px solid var(--border-color);
        }
        .sold-table .product-cell .pname { font-weight: 600; font-size: 0.85rem; line-height: 1.2; }
        .sold-table .product-cell .porder { font-size: 0.68rem; color: var(--text-muted); }
        
        .sold-total-badge {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(16, 185, 129, 0.05));
            color: #059669;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        /* MULTIPLE IMAGE UPLOAD */
        .multi-image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .image-item {
            position: relative;
            aspect-ratio: 1;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        .image-item:hover {
            border-color: var(--gold);
            transform: scale(1.03);
        }
        .image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-item .img-delete {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
            cursor: pointer;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            opacity: 0;
        }
        .image-item:hover .img-delete { opacity: 1; }
        .image-item .img-delete:hover { background: #dc2626; transform: scale(1.1); }
        .image-item .img-primary-badge {
            position: absolute;
            bottom: 4px;
            left: 4px;
            background: linear-gradient(135deg, var(--gold), #b8941f);
            color: var(--primary-dark);
            font-size: 0.6rem;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 10px;
            letter-spacing: 0.3px;
        }
        
        .upload-images-area {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--bg-secondary);
        }
        .upload-images-area:hover {
            border-color: var(--gold);
            background: rgba(212, 175, 55, 0.05);
        }
        .upload-images-area i {
            font-size: 2.5rem;
            color: var(--gold);
            margin-bottom: 10px;
            display: block;
        }
        .upload-images-area .upload-text {
            font-size: 0.85rem;
            color: var(--text-primary);
            font-weight: 600;
        }
        .upload-images-area .upload-hint {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Discount Modal */
        .discount-preview {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
            border: 2px solid rgba(239, 68, 68, 0.3);
            border-radius: 12px;
            padding: 16px;
            margin-top: 15px;
        }
        .discount-preview .row-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 0.9rem;
        }
        .discount-preview .row-line .label { color: var(--text-muted); }
        .discount-preview .row-line .value { font-weight: 700; color: var(--text-primary); }
        .discount-preview .row-line .value.old { text-decoration: line-through; color: var(--text-muted); }
        .discount-preview .row-line .value.new { color: #ef4444; font-size: 1.1rem; }

        /* ============================================
           MONTHLY SALES REPORT STYLES
           ============================================ */
        .monthly-report-header {
            background: linear-gradient(135deg, var(--primary-dark), #1a2c4a);
            color: white;
            padding: 25px 30px;
            border-radius: 18px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 10px 30px rgba(10, 25, 47, 0.3);
            border: 1px solid rgba(212, 175, 55, 0.3);
            position: relative;
            overflow: hidden;
        }
        .monthly-report-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15), transparent 70%);
            border-radius: 50%;
        }
        .monthly-report-header h2 {
            font-weight: 800;
            font-size: 1.6rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 2;
        }
        .monthly-report-header h2 i {
            color: var(--gold);
            font-size: 2rem;
        }
        .monthly-report-header p {
            margin: 5px 0 0;
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
        }

        .report-controls {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }
        .report-select {
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(212, 175, 55, 0.4);
            color: white;
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            outline: none;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        .report-select:hover, .report-select:focus {
            background: rgba(255,255,255,0.15);
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        }
        .report-select option {
            background: var(--primary-dark);
            color: white;
            padding: 10px;
        }

        .report-btn {
            background: linear-gradient(135deg, var(--gold), #b8941f);
            color: var(--primary-dark);
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.3);
        }
        .report-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(212, 175, 55, 0.4);
            background: linear-gradient(135deg, #f7d26b, var(--gold));
        }

        /* KPI CARDS */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }
        .kpi-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 22px;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 5px;
            height: 100%;
            transition: width 0.3s ease;
        }
        .kpi-card:hover::before { width: 8px; }
        .kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--gold);
        }
        .kpi-card.revenue::before { background: linear-gradient(135deg, #10b981, #059669); }
        .kpi-card.items::before { background: linear-gradient(135deg, #6366f1, #4338ca); }
        .kpi-card.orders::before { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .kpi-card.refunds::before { background: linear-gradient(135deg, #ef4444, #dc2626); }

        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
        }
        .kpi-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }
        .kpi-card.revenue .kpi-icon { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3); }
        .kpi-card.items .kpi-icon { background: linear-gradient(135deg, #6366f1, #4338ca); box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3); }
        .kpi-card.orders .kpi-icon { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3); }
        .kpi-card.refunds .kpi-icon { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3); }

        .kpi-value {
            font-size: 1.9rem;
            font-weight: 800;
            line-height: 1.1;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .kpi-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            font-weight: 700;
        }
        .kpi-trend {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            margin-top: 8px;
        }
        .kpi-trend.up { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .kpi-trend.down { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
        .kpi-trend.neutral { background: rgba(107, 114, 128, 0.15); color: #4b5563; }

        /* CHART CONTAINERS */
        .chart-container {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 22px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            margin-bottom: 25px;
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
            flex-wrap: wrap;
            gap: 10px;
        }
        .chart-header h5 {
            margin: 0;
            font-weight: 800;
            font-size: 1.05rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .chart-header h5 i { color: var(--gold); font-size: 1.2rem; }
        .chart-wrapper {
            position: relative;
            height: 320px;
        }

        /* MONTH TABLE */
        .month-table-container {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 25px;
        }
        .month-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .month-table thead {
            background: linear-gradient(135deg, var(--primary-dark), #1a2c4a);
            color: white;
        }
        .month-table thead th {
            padding: 16px 14px;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            text-align: left;
            white-space: nowrap;
        }
        .month-table tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
        }
        .month-table tbody tr:hover { background: var(--bg-secondary); }
        .month-table tbody tr.active {
            background: linear-gradient(90deg, rgba(212, 175, 55, 0.1), transparent);
            border-left: 4px solid var(--gold);
        }
        .month-table tbody tr:last-child { border-bottom: none; }
        .month-table tbody td {
            padding: 14px;
            font-size: 0.88rem;
            color: var(--text-primary);
            vertical-align: middle;
        }
        .month-table .month-name {
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .month-table .month-name.active-badge {
            color: var(--gold);
        }
        .month-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .month-badge.current {
            background: linear-gradient(135deg, var(--gold), #b8941f);
            color: var(--primary-dark);
        }
        .revenue-cell {
            font-weight: 800;
            color: #059669;
        }
        .items-cell {
            font-weight: 700;
            color: #4f46e5;
        }
        .orders-cell {
            font-weight: 700;
            color: #d97706;
        }
        .refund-cell {
            font-weight: 700;
            color: #dc2626;
        }
        .net-cell {
            font-weight: 800;
            color: var(--gold);
        }

        /* TOP PRODUCTS */
        .top-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .top-product-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .top-product-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        .top-product-header i {
            color: var(--gold);
            font-size: 1.3rem;
        }
        .top-product-header h6 {
            margin: 0;
            font-weight: 800;
            font-size: 0.95rem;
            color: var(--text-primary);
        }
        .top-product-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px dashed var(--border-color);
        }
        .top-product-item:last-child { border-bottom: none; }
        .top-product-rank {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.85rem;
            color: white;
            flex-shrink: 0;
        }
        .top-product-rank.gold { background: linear-gradient(135deg, #fbbf24, #d97706); }
        .top-product-rank.silver { background: linear-gradient(135deg, #d1d5db, #9ca3af); }
        .top-product-rank.bronze { background: linear-gradient(135deg, #d97706, #92400e); }
        .top-product-rank.normal { background: var(--bg-secondary); color: var(--text-muted); }
        .top-product-info { flex: 1; min-width: 0; }
        .top-product-info .pname {
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 3px;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .top-product-info .pmeta {
            font-size: 0.72rem;
            color: var(--text-muted);
        }
        .top-product-value {
            text-align: right;
            flex-shrink: 0;
        }
        .top-product-value .qty {
            font-weight: 800;
            font-size: 1rem;
            color: #4f46e5;
        }
        .top-product-value .rev {
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        /* CATEGORY BREAKDOWN */
        .category-breakdown {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .cat-breakdown-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .cat-breakdown-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%;
            height: 4px;
        }
        .cat-breakdown-card.perfume::before { background: linear-gradient(90deg, #d4af37, #b8941f); }
        .cat-breakdown-card.tester::before { background: linear-gradient(90deg, #6366f1, #4338ca); }
        .cat-breakdown-card.watch::before { background: linear-gradient(90deg, #06b6d4, #0891b2); }
        .cat-breakdown-card.glass::before { background: linear-gradient(90deg, #a855f7, #7e22ce); }
        .cat-breakdown-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        .cat-breakdown-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
            margin: 0 auto 12px;
        }
        .cat-breakdown-card.perfume .cat-breakdown-icon { background: linear-gradient(135deg, #d4af37, #b8941f); }
        .cat-breakdown-card.tester .cat-breakdown-icon { background: linear-gradient(135deg, #6366f1, #4338ca); }
        .cat-breakdown-card.watch .cat-breakdown-icon { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .cat-breakdown-card.glass .cat-breakdown-icon { background: linear-gradient(135deg, #a855f7, #7e22ce); }
        .cat-breakdown-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .cat-breakdown-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 700;
            margin-bottom: 8px;
        }
        .cat-breakdown-rev {
            font-size: 0.78rem;
            color: #059669;
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .monthly-report-header {
                padding: 20px;
            }
            .monthly-report-header h2 {
                font-size: 1.3rem;
            }
            .report-controls {
                width: 100%;
            }
            .report-select {
                flex: 1;
                min-width: 120px;
            }
            .kpi-value { font-size: 1.5rem; }
            .chart-wrapper { height: 250px; }
        }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0">
        <div class="col-md-3 col-lg-2 admin-sidebar">
            <div class="d-flex align-items-center gap-2 mb-4">
                <span class="text-white fw-bold">Mr.<span class="gold-text">AYS</span></span>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link active" onclick="loadOrders('all', this)">
                    <i class="bi bi-inbox me-2"></i>All Orders
                    <span class="badge-count"><?php echo $total; ?></span>
                </a>
                <a class="nav-link" onclick="loadOrders('pending', this)">
                    <i class="bi bi-clock-history me-2"></i>Pending
                    <span class="badge-count"><?php echo $stats['pending'] ?? 0; ?></span>
                </a>
                <a class="nav-link" onclick="loadOrders('processed', this)">
                    <i class="bi bi-check2-circle me-2"></i>Processed
                    <span class="badge-count"><?php echo $stats['processed'] ?? 0; ?></span>
                </a>
                
                <a class="nav-link" onclick="showReturnsSection(this)">
                    <i class="bi bi-arrow-return-left me-2"></i>Returns
                    <?php if (($returnStats['requested'] ?? 0) > 0): ?>
                        <span class="badge-count" style="background:#f59e0b;"><?php echo $returnStats['requested']; ?></span>
                    <?php else: ?>
                        <span class="badge-count"><?php echo $totalReturns; ?></span>
                    <?php endif; ?>
                </a>
                
                <hr class="border-secondary opacity-25 my-2">
                
                <a class="nav-link" onclick="showStockSection(this)">
                    <i class="bi bi-box-seam-fill me-2"></i>Stock Management
                    <?php if($lowStockCount > 0): ?>
                        <span class="badge-count" style="background:#ef4444;"><?php echo $lowStockCount; ?></span>
                    <?php endif; ?>
                </a>

                <a class="nav-link" onclick="showSoldItemsSection(this)">
                    <i class="bi bi-graph-up-arrow me-2"></i>Sold Items
                    <span class="badge-count" style="background:#10b981;"><?php echo $totalSold; ?></span>
                </a>

                <!-- NEW: MONTHLY SALES REPORT -->
                <a class="nav-link" onclick="showMonthlyReportSection(this)">
                    <i class="bi bi-calendar-check-fill me-2"></i>Monthly Report
                    <span class="badge-count" style="background:linear-gradient(135deg,#d4af37,#b8941f);color:#0a192f;">
                        <i class="bi bi-star-fill" style="font-size:0.6rem;"></i>
                    </span>
                </a>
                
                <a class="nav-link" onclick="showTrackingSection(this)">
                    <i class="bi bi-geo-alt-fill me-2"></i>Track Orders
                </a>
                
                <a class="nav-link" onclick="showHeroBannerSection(this)">
                    <i class="bi bi-images me-2"></i>Hero Banners
                    <span class="badge-count"><?php echo count($heroBanners); ?></span>
                </a>

                <a class="nav-link" onclick="showFlashBannerSection(this)">
                    <i class="bi bi-lightning-charge-fill me-2"></i>Flash Banners
                    <span class="badge-count"><?php echo count($flashBanners); ?></span>
                </a>

                <a class="nav-link" onclick="showProductSection(this)">
                    <i class="bi bi-grid me-2"></i>Products
                </a>
                
                <hr class="border-secondary">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                </a>
            </nav>
        </div>
        <div class="col-md-9 col-lg-10 p-4" style="background: var(--bg-primary); min-height:100vh;">
            
            <!-- ORDER MANAGEMENT -->
            <div id="orderManagementSection">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="gold-text"><i class="bi bi-clipboard-data me-2"></i>Order Management</h4>
                        <p style="color: var(--text-secondary); font-size:0.9rem; margin:0;">Manage and verify customer orders</p>
                    </div>
                    <div class="d-flex gap-3 align-items-center">
                        <button class="theme-toggle" onclick="toggleTheme()">
                            <i class="bi bi-moon-fill"></i>
                            <span>Theme</span>
                        </button>
                        <span style="color: var(--text-muted); font-size:0.8rem;">
                            <i class="bi bi-database me-1"></i>MySQL
                        </span>
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-3"><div class="stat-card"><div class="stat-number"><?php echo $total; ?></div><div class="stat-label">Total Orders</div></div></div>
                    <div class="col-md-3"><div class="stat-card"><div class="stat-number text-warning"><?php echo $stats['pending'] ?? 0; ?></div><div class="stat-label">Pending</div></div></div>
                    <div class="col-md-3"><div class="stat-card"><div class="stat-number text-success"><?php echo $stats['processed'] ?? 0; ?></div><div class="stat-label">Processed</div></div></div>
                    <div class="col-md-3"><div class="stat-card"><div class="stat-number text-danger"><?php echo $stats['returned'] ?? 0; ?></div><div class="stat-label">Returns</div></div></div>
                </div>
                <div id="adminOrderList" class="row g-3"></div>
            </div>

            <!-- ============================================
                 NEW: MONTHLY SALES REPORT SECTION
                 ============================================ -->
            <div id="monthlyReportSection" style="display:none;">
                
                <!-- Report Header with Controls -->
                <div class="monthly-report-header">
                    <div>
                        <h2><i class="bi bi-calendar-check-fill"></i>Monthly Sales Report</h2>
                        <p>Track your sales, revenue and growth month by month</p>
                    </div>
                    <div class="report-controls">
                        <select class="report-select" id="reportYear" onchange="changeReportFilter()">
                            <?php foreach ($availableYears as $yr): ?>
                                <option value="<?php echo $yr; ?>" <?php echo $yr == $currentYear ? 'selected' : ''; ?>>
                                    📅 <?php echo $yr; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select class="report-select" id="reportMonth" onchange="changeReportFilter()">
                            <?php foreach ($monthlyFullNames as $idx => $mn): ?>
                                <option value="<?php echo $idx + 1; ?>" <?php echo ($idx + 1) == $currentMonth ? 'selected' : ''; ?>>
                                    <?php echo $mn; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="report-btn" onclick="printReport()">
                            <i class="bi bi-printer-fill"></i> Print
                        </button>
                    </div>
                </div>

                <!-- KPI CARDS - Selected Month -->
                <div class="section-title">
                    <i class="bi bi-trophy-fill"></i>
                    <h5><?php echo $monthlyFullNames[$currentMonth - 1]; ?> <?php echo $currentYear; ?> - Overview</h5>
                </div>

                <div class="kpi-grid">
                    <div class="kpi-card revenue">
                        <div class="kpi-header">
                            <div class="kpi-icon"><i class="bi bi-currency-dollar"></i></div>
                        </div>
                        <div class="kpi-value">PKR <?php echo number_format($selectedMonthData['revenue'], 0); ?></div>
                        <div class="kpi-label">Monthly Revenue</div>
                        <?php if ($prevMonthData && $prevMonthData['revenue'] > 0): ?>
                            <div class="kpi-trend <?php echo $growthPercent >= 0 ? 'up' : 'down'; ?>">
                                <i class="bi bi-arrow-<?php echo $growthPercent >= 0 ? 'up' : 'down'; ?>-right"></i>
                                <?php echo number_format(abs($growthPercent), 1); ?>% vs last month
                            </div>
                        <?php elseif ($prevMonthData): ?>
                            <div class="kpi-trend neutral">
                                <i class="bi bi-dash-circle"></i> No previous data
                            </div>
                        <?php else: ?>
                            <div class="kpi-trend neutral">
                                <i class="bi bi-info-circle"></i> First month
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="kpi-card items">
                        <div class="kpi-header">
                            <div class="kpi-icon"><i class="bi bi-box-seam-fill"></i></div>
                        </div>
                        <div class="kpi-value"><?php echo number_format($selectedMonthData['items_sold']); ?></div>
                        <div class="kpi-label">Items Sold</div>
                        <div class="kpi-trend neutral">
                            <i class="bi bi-cart-check"></i> Total units
                        </div>
                    </div>

                    <div class="kpi-card orders">
                        <div class="kpi-header">
                            <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
                        </div>
                        <div class="kpi-value"><?php echo number_format($selectedMonthData['orders_count']); ?></div>
                        <div class="kpi-label">Total Orders</div>
                        <?php if ($selectedMonthData['orders_count'] > 0): ?>
                            <div class="kpi-trend neutral">
                                <i class="bi bi-graph-up"></i> 
                                Avg: PKR <?php echo number_format($selectedMonthData['revenue'] / $selectedMonthData['orders_count'], 0); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="kpi-card refunds">
                        <div class="kpi-header">
                            <div class="kpi-icon"><i class="bi bi-arrow-return-left"></i></div>
                        </div>
                        <div class="kpi-value">PKR <?php echo number_format($selectedMonthData['refunds'], 0); ?></div>
                        <div class="kpi-label">Refunds</div>
                        <div class="kpi-trend neutral">
                            <i class="bi bi-wallet2"></i> 
                            Net: PKR <?php echo number_format($selectedMonthData['net_revenue'], 0); ?>
                        </div>
                    </div>
                </div>

                <!-- Category Breakdown for selected month -->
                <div class="section-title">
                    <i class="bi bi-pie-chart-fill"></i>
                    <h5>Category Breakdown - <?php echo $monthlyFullNames[$currentMonth - 1]; ?></h5>
                </div>

                <div class="category-breakdown">
                    <div class="cat-breakdown-card perfume">
                        <div class="cat-breakdown-icon"><i class="bi bi-flower1"></i></div>
                        <div class="cat-breakdown-value"><?php echo $selectedMonthData['perfume']; ?></div>
                        <div class="cat-breakdown-label">Perfumes</div>
                        <div class="cat-breakdown-rev">PKR <?php echo number_format($selectedMonthData['perfume_revenue'], 0); ?></div>
                    </div>
                    <div class="cat-breakdown-card tester">
                        <div class="cat-breakdown-icon"><i class="bi bi-flask"></i></div>
                        <div class="cat-breakdown-value"><?php echo $selectedMonthData['tester']; ?></div>
                        <div class="cat-breakdown-label">Testers</div>
                        <div class="cat-breakdown-rev">PKR <?php echo number_format($selectedMonthData['tester_revenue'], 0); ?></div>
                    </div>
                    <div class="cat-breakdown-card watch">
                        <div class="cat-breakdown-icon"><i class="bi bi-clock-fill"></i></div>
                        <div class="cat-breakdown-value"><?php echo $selectedMonthData['watch']; ?></div>
                        <div class="cat-breakdown-label">Watches</div>
                        <div class="cat-breakdown-rev">PKR <?php echo number_format($selectedMonthData['watch_revenue'], 0); ?></div>
                    </div>
                    <div class="cat-breakdown-card glass">
                        <div class="cat-breakdown-icon"><i class="bi bi-eyeglasses"></i></div>
                        <div class="cat-breakdown-value"><?php echo $selectedMonthData['glass']; ?></div>
                        <div class="cat-breakdown-label">Glasses</div>
                        <div class="cat-breakdown-rev">PKR <?php echo number_format($selectedMonthData['glass_revenue'], 0); ?></div>
                    </div>
                </div>

                <!-- Monthly Revenue Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h5><i class="bi bi-bar-chart-line-fill"></i>Monthly Revenue - <?php echo $currentYear; ?></h5>
                        <div style="font-size:0.8rem;color:var(--text-muted);">
                            Total: <strong style="color:#059669;">PKR <?php echo number_format($yearTotal['revenue'], 0); ?></strong>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="monthlyRevenueChart"></canvas>
                    </div>
                </div>

                <!-- Monthly Items Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h5><i class="bi bi-graph-up-arrow"></i>Monthly Items Sold - <?php echo $currentYear; ?></h5>
                        <div style="font-size:0.8rem;color:var(--text-muted);">
                            Total: <strong style="color:#4f46e5;"><?php echo number_format($yearTotal['items_sold']); ?> items</strong>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="monthlyItemsChart"></canvas>
                    </div>
                </div>

                <!-- Full Month Table -->
                <div class="section-title">
                    <i class="bi bi-table"></i>
                    <h5>All Months Breakdown - <?php echo $currentYear; ?></h5>
                </div>

                <div class="month-table-container">
                    <table class="month-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Orders</th>
                                <th>Items Sold</th>
                                <th>Revenue</th>
                                <th>Refunds</th>
                                <th>Net Revenue</th>
                                <th>Perfumes</th>
                                <th>Testers</th>
                                <th>Watches</th>
                                <th>Glasses</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyData as $m => $data): ?>
                                <tr class="<?php echo $m == $currentMonth ? 'active' : ''; ?>" onclick="selectMonth(<?php echo $m; ?>)">
                                    <td>
                                        <div class="month-name <?php echo $m == $currentMonth ? 'active-badge' : ''; ?>">
                                            <?php echo $data['name']; ?>
                                            <?php if ($m == (int)date('n') && $currentYear == (int)date('Y')): ?>
                                                <span class="month-badge current">Current</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><span class="orders-cell"><?php echo $data['orders_count']; ?></span></td>
                                    <td><span class="items-cell"><?php echo $data['items_sold']; ?></span></td>
                                    <td><span class="revenue-cell">PKR <?php echo number_format($data['revenue'], 0); ?></span></td>
                                    <td><span class="refund-cell">PKR <?php echo number_format($data['refunds'], 0); ?></span></td>
                                    <td><span class="net-cell">PKR <?php echo number_format($data['net_revenue'], 0); ?></span></td>
                                    <td><?php echo $data['perfume']; ?></td>
                                    <td><?php echo $data['tester']; ?></td>
                                    <td><?php echo $data['watch']; ?></td>
                                    <td><?php echo $data['glass']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot style="background:linear-gradient(135deg,rgba(212,175,55,0.1),rgba(212,175,55,0.05));font-weight:800;">
                            <tr>
                                <td style="padding:16px 14px;color:var(--gold);">YEAR TOTAL</td>
                                <td style="padding:16px 14px;color:#d97706;"><?php echo $yearTotal['orders_count']; ?></td>
                                <td style="padding:16px 14px;color:#4f46e5;"><?php echo $yearTotal['items_sold']; ?></td>
                                <td style="padding:16px 14px;color:#059669;">PKR <?php echo number_format($yearTotal['revenue'], 0); ?></td>
                                <td style="padding:16px 14px;color:#dc2626;">PKR <?php echo number_format($yearTotal['refunds'], 0); ?></td>
                                <td style="padding:16px 14px;color:var(--gold);">PKR <?php echo number_format($yearTotal['net_revenue'], 0); ?></td>
                                <td style="padding:16px 14px;"><?php echo $yearTotal['perfume']; ?></td>
                                <td style="padding:16px 14px;"><?php echo $yearTotal['tester']; ?></td>
                                <td style="padding:16px 14px;"><?php echo $yearTotal['watch']; ?></td>
                                <td style="padding:16px 14px;"><?php echo $yearTotal['glass']; ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Top Products -->
                <div class="top-products-grid">
                    <div class="top-product-card">
                        <div class="top-product-header">
                            <i class="bi bi-trophy-fill"></i>
                            <h6>Top Products - <?php echo $monthlyFullNames[$currentMonth - 1]; ?></h6>
                        </div>
                        <?php if (empty($topProductsMonth)): ?>
                            <div style="text-align:center;padding:30px;color:var(--text-muted);">
                                <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                                No sales this month
                            </div>
                        <?php else: ?>
                            <?php foreach ($topProductsMonth as $idx => $prod): ?>
                                <div class="top-product-item">
                                    <div class="top-product-rank <?php echo $idx === 0 ? 'gold' : ($idx === 1 ? 'silver' : ($idx === 2 ? 'bronze' : 'normal')); ?>">
                                        <?php echo $idx + 1; ?>
                                    </div>
                                    <div class="top-product-info">
                                        <div class="pname"><?php echo htmlspecialchars($prod['product_name']); ?></div>
                                        <div class="pmeta"><?php echo $prod['order_count']; ?> orders</div>
                                    </div>
                                    <div class="top-product-value">
                                        <div class="qty"><?php echo $prod['total_sold']; ?></div>
                                        <div class="rev">PKR <?php echo number_format($prod['revenue'], 0); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="top-product-card">
                        <div class="top-product-header">
                            <i class="bi bi-award-fill"></i>
                            <h6>Top Products - <?php echo $currentYear; ?> (Year)</h6>
                        </div>
                        <?php if (empty($topProductsYear)): ?>
                            <div style="text-align:center;padding:30px;color:var(--text-muted);">
                                <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                                No sales this year
                            </div>
                        <?php else: ?>
                            <?php foreach ($topProductsYear as $idx => $prod): ?>
                                <div class="top-product-item">
                                    <div class="top-product-rank <?php echo $idx === 0 ? 'gold' : ($idx === 1 ? 'silver' : ($idx === 2 ? 'bronze' : 'normal')); ?>">
                                        <?php echo $idx + 1; ?>
                                    </div>
                                    <div class="top-product-info">
                                        <div class="pname"><?php echo htmlspecialchars($prod['product_name']); ?></div>
                                        <div class="pmeta"><?php echo $prod['order_count']; ?> orders</div>
                                    </div>
                                    <div class="top-product-value">
                                        <div class="qty"><?php echo $prod['total_sold']; ?></div>
                                        <div class="rev">PKR <?php echo number_format($prod['revenue'], 0); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- SOLD ITEMS SECTION -->
            <div id="soldItemsSection" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="gold-text"><i class="bi bi-graph-up-arrow me-2"></i>Sold Items Analytics</h4>
                        <p style="color: var(--text-secondary); font-size:0.9rem; margin:0;">Detailed view of all sold products by category</p>
                    </div>
                    <button class="theme-toggle" onclick="toggleTheme()">
                        <i class="bi bi-moon-fill"></i>
                        <span>Theme</span>
                    </button>
                </div>

                <div class="sold-summary-grid">
                    <div class="sold-summary-card perfume">
                        <div class="sold-summary-icon"><i class="bi bi-flower1"></i></div>
                        <div>
                            <div class="sold-summary-value"><?php echo $soldStats['perfume']['sold']; ?></div>
                            <div class="sold-summary-label">Perfumes Sold</div>
                            <div class="sold-summary-sub">Revenue: <strong>PKR <?php echo number_format($soldStats['perfume']['revenue'], 2); ?></strong></div>
                        </div>
                    </div>
                    <div class="sold-summary-card tester">
                        <div class="sold-summary-icon"><i class="bi bi-flask"></i></div>
                        <div>
                            <div class="sold-summary-value"><?php echo $soldStats['tester']['sold']; ?></div>
                            <div class="sold-summary-label">Testers Sold</div>
                            <div class="sold-summary-sub">Revenue: <strong>PKR <?php echo number_format($soldStats['tester']['revenue'], 2); ?></strong></div>
                        </div>
                    </div>
                    <div class="sold-summary-card watch">
                        <div class="sold-summary-icon"><i class="bi bi-clock-fill"></i></div>
                        <div>
                            <div class="sold-summary-value"><?php echo $soldStats['watch']['sold']; ?></div>
                            <div class="sold-summary-label">Watches Sold</div>
                            <div class="sold-summary-sub">Revenue: <strong>PKR <?php echo number_format($soldStats['watch']['revenue'], 2); ?></strong></div>
                        </div>
                    </div>
                    <div class="sold-summary-card glass">
                        <div class="sold-summary-icon"><i class="bi bi-eyeglasses"></i></div>
                        <div>
                            <div class="sold-summary-value"><?php echo $soldStats['glass']['sold']; ?></div>
                            <div class="sold-summary-label">Glasses Sold</div>
                            <div class="sold-summary-sub">Revenue: <strong>PKR <?php echo number_format($soldStats['glass']['revenue'], 2); ?></strong></div>
                        </div>
                    </div>
                    <div class="sold-summary-card total">
                        <div class="sold-summary-icon"><i class="bi bi-trophy-fill"></i></div>
                        <div>
                            <div class="sold-summary-value"><?php echo $totalSold; ?></div>
                            <div class="sold-summary-label">Total Items Sold</div>
                            <div class="sold-summary-sub">Revenue: <strong>PKR <?php echo number_format(array_sum(array_column($soldStats, 'revenue')), 2); ?></strong></div>
                        </div>
                    </div>
                </div>

                <div class="filter-tabs">
                    <div class="filter-tab active" onclick="filterSoldItems('all', this)">
                        <i class="bi bi-grid-fill"></i>All Items
                    </div>
                    <div class="filter-tab" onclick="filterSoldItems('perfume', this)">
                        <i class="bi bi-flower1"></i>Perfumes (<?php echo $soldStats['perfume']['sold']; ?>)
                    </div>
                    <div class="filter-tab" onclick="filterSoldItems('tester', this)">
                        <i class="bi bi-flask"></i>Testers (<?php echo $soldStats['tester']['sold']; ?>)
                    </div>
                    <div class="filter-tab" onclick="filterSoldItems('watch', this)">
                        <i class="bi bi-clock-fill"></i>Watches (<?php echo $soldStats['watch']['sold']; ?>)
                    </div>
                    <div class="filter-tab" onclick="filterSoldItems('glass', this)">
                        <i class="bi bi-eyeglasses"></i>Glasses (<?php echo $soldStats['glass']['sold']; ?>)
                    </div>
                </div>

                <div class="sold-table-container">
                    <table class="sold-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Order</th>
                                <th>Category</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Customer</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="soldItemsTableBody">
                            <?php if (empty($soldItemsDetail)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center;padding:40px;">
                                        <i class="bi bi-inbox" style="font-size:3rem;color:var(--text-muted);display:block;margin-bottom:10px;"></i>
                                        <div style="color:var(--text-muted);">No sold items yet</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($soldItemsDetail as $item): 
                                    $cat = getCategoryFromName($item['product_name']);
                                    $unitPrice = $item['quantity'] > 0 ? ($item['total'] / $item['quantity']) : 0;
                                ?>
                                <tr data-cat="<?php echo $cat; ?>">
                                    <td>
                                        <div class="product-cell">
                                            <img src="<?php echo htmlspecialchars($item['product_image'] ?? 'https://via.placeholder.com/40'); ?>" alt="" onerror="this.src='https://via.placeholder.com/40'">
                                            <div>
                                                <div class="pname"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                                <div class="porder">ID: #<?php echo $item['product_id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight:700;color:var(--gold);">#<?php echo $item['order_id']; ?></span>
                                    </td>
                                    <td><span class="cat-badge <?php echo $cat; ?>"><?php echo ucfirst($cat); ?></span></td>
                                    <td><strong><?php echo $item['quantity']; ?></strong></td>
                                    <td>PKR <?php echo number_format($unitPrice, 2); ?></td>
                                    <td><span class="sold-total-badge"><i class="bi bi-currency-exchange"></i>PKR <?php echo number_format($item['total'], 2); ?></span></td>
                                    <td><?php echo htmlspecialchars($item['client_name'] ?? 'Guest'); ?></td>
                                    <td style="font-size:0.78rem;color:var(--text-muted);">
                                        <?php echo $item['created_at'] ? date('M d, Y', strtotime($item['created_at'])) : 'N/A'; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- RETURNS MANAGEMENT -->
            <div id="returnsManagementSection" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="gold-text"><i class="bi bi-arrow-return-left me-2"></i>Returns Management</h4>
                        <p style="color: var(--text-secondary); font-size:0.9rem; margin:0;">Review, approve and process customer return requests</p>
                    </div>
                    <button class="theme-toggle" onclick="toggleTheme()">
                        <i class="bi bi-moon-fill"></i>
                        <span>Theme</span>
                    </button>
                </div>
                
                <div class="return-stats-grid">
                    <div class="return-stat-card" style="background: linear-gradient(135deg, rgba(245,158,11,0.15), rgba(245,158,11,0.05));">
                        <div class="return-stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div>
                            <div class="return-stat-value" style="color: #d97706;"><?php echo $returnStats['requested'] ?? 0; ?></div>
                            <div class="return-stat-label">Pending Review</div>
                        </div>
                    </div>
                    <div class="return-stat-card" style="background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(59,130,246,0.05));">
                        <div class="return-stat-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div>
                            <div class="return-stat-value" style="color: #2563eb;"><?php echo $returnStats['approved'] ?? 0; ?></div>
                            <div class="return-stat-label">Approved</div>
                        </div>
                    </div>
                    <div class="return-stat-card" style="background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(16,185,129,0.05));">
                        <div class="return-stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <i class="bi bi-check2-all"></i>
                        </div>
                        <div>
                            <div class="return-stat-value" style="color: #059669;"><?php echo $returnStats['returned'] ?? 0; ?></div>
                            <div class="return-stat-label">Completed</div>
                        </div>
                    </div>
                    <div class="return-stat-card" style="background: linear-gradient(135deg, rgba(239,68,68,0.15), rgba(239,68,68,0.05));">
                        <div class="return-stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                            <i class="bi bi-x-circle-fill"></i>
                        </div>
                        <div>
                            <div class="return-stat-value" style="color: #dc2626;"><?php echo $returnStats['rejected'] ?? 0; ?></div>
                            <div class="return-stat-label">Rejected</div>
                        </div>
                    </div>
                </div>
                
                <div class="filter-tabs">
                    <div class="filter-tab active" onclick="filterReturns('all', this)">
                        <i class="bi bi-grid-fill"></i>All Returns
                    </div>
                    <div class="filter-tab" onclick="filterReturns('requested', this)">
                        <i class="bi bi-hourglass-split"></i>Pending (<?php echo $returnStats['requested'] ?? 0; ?>)
                    </div>
                    <div class="filter-tab" onclick="filterReturns('approved', this)">
                        <i class="bi bi-check-circle"></i>Approved (<?php echo $returnStats['approved'] ?? 0; ?>)
                    </div>
                    <div class="filter-tab" onclick="filterReturns('returned', this)">
                        <i class="bi bi-check2-all"></i>Completed (<?php echo $returnStats['returned'] ?? 0; ?>)
                    </div>
                    <div class="filter-tab" onclick="filterReturns('rejected', this)">
                        <i class="bi bi-x-circle"></i>Rejected (<?php echo $returnStats['rejected'] ?? 0; ?>)
                    </div>
                </div>
                
                <div id="returnsList">
                    <?php if (empty($returnRequests)): ?>
                        <div class="empty-orders">
                            <i class="bi bi-arrow-return-left"></i>
                            <h5 style="color:var(--text-primary);font-weight:700;">No return requests</h5>
                            <p>No customers have requested returns yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($returnRequests as $ret): ?>
                            <div class="return-card" data-return-status="<?php echo $ret['return_status']; ?>">
                                <div class="return-header">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?php echo htmlspecialchars($ret['product_image'] ?? 'https://via.placeholder.com/60'); ?>" 
                                             alt=""
                                             style="width: 65px; height: 65px; border-radius: 10px; object-fit: cover; border: 1px solid var(--border-color);">
                                        <div>
                                            <h6 class="fw-bold mb-1" style="color: var(--text-primary);">
                                                <?php echo htmlspecialchars($ret['product_name'] ?? 'Product'); ?>
                                            </h6>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);">
                                                Order <strong style="color: var(--gold);">#<?php echo $ret['id']; ?></strong> · 
                                                Qty: <?php echo $ret['quantity']; ?> · 
                                                PKR <?php echo number_format(($ret['product_price'] ?? 0) * $ret['quantity'], 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <?php if ($ret['return_status'] === 'requested'): ?>
                                            <span class="return-status-badge return-badge-requested">
                                                <i class="bi bi-hourglass-split"></i>Pending Review
                                            </span>
                                        <?php elseif ($ret['return_status'] === 'approved'): ?>
                                            <span class="return-status-badge return-badge-approved">
                                                <i class="bi bi-check-circle"></i>Approved
                                            </span>
                                        <?php elseif ($ret['return_status'] === 'returned'): ?>
                                            <span class="return-status-badge return-badge-returned">
                                                <i class="bi bi-check2-all"></i>Completed
                                            </span>
                                        <?php elseif ($ret['return_status'] === 'rejected'): ?>
                                            <span class="return-status-badge return-badge-rejected">
                                                <i class="bi bi-x-circle"></i>Rejected
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="return-info-box">
                                            <div class="label">👤 Customer</div>
                                            <div class="value"><?php echo htmlspecialchars($ret['client_name'] ?? 'Guest'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="return-info-box">
                                            <div class="label">📝 Return Reason</div>
                                            <div class="value"><?php echo htmlspecialchars($ret['return_reason'] ?? 'No reason provided'); ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($ret['return_status'] === 'requested'): ?>
                                    <div class="return-actions">
                                        <input type="text" id="adminNote-<?php echo $ret['id']; ?>" class="note-input" placeholder="Optional note (required for rejection)..." value="">
                                        <button class="btn-approve-return" onclick="approveReturn(<?php echo $ret['id']; ?>)">
                                            <i class="bi bi-check-circle"></i>Approve
                                        </button>
                                        <button class="btn-reject-return" onclick="rejectReturn(<?php echo $ret['id']; ?>)">
                                            <i class="bi bi-x-circle"></i>Reject
                                        </button>
                                    </div>
                                <?php elseif ($ret['return_status'] === 'approved'): ?>
                                    <div class="alert alert-info mb-0 mt-3 py-2 px-3" style="border-radius: 10px; font-size: 0.85rem;">
                                        <i class="bi bi-info-circle me-1"></i>
                                        <strong>Return approved.</strong> Waiting for product to arrive.
                                    </div>
                                    <div class="return-actions">
                                        <input type="text" id="adminNote-<?php echo $ret['id']; ?>" class="note-input" placeholder="Note (optional)..." value="<?php echo htmlspecialchars($ret['admin_notes'] ?? ''); ?>">
                                        <button class="btn-complete-return" onclick="openCompleteReturnModal(<?php echo $ret['id']; ?>, <?php echo ($ret['product_price'] ?? 0) * $ret['quantity']; ?>)">
                                            <i class="bi bi-cash-coin"></i>Process Refund & Restock
                                        </button>
                                    </div>
                                <?php elseif ($ret['return_status'] === 'returned'): ?>
                                    <div class="alert alert-success mt-3 mb-0 py-2 px-3" style="border-radius: 10px; font-size: 0.85rem;">
                                        <i class="bi bi-check-circle-fill me-1"></i>
                                        <strong>Return completed.</strong>
                                        Refund of <strong>PKR <?php echo number_format($ret['refund_amount'] ?? 0, 2); ?></strong> 
                                        via <strong><?php echo htmlspecialchars($ret['refund_method'] ?? 'N/A'); ?></strong>
                                    </div>
                                <?php elseif ($ret['return_status'] === 'rejected'): ?>
                                    <div class="alert alert-danger mt-3 mb-0 py-2 px-3" style="border-radius: 10px; font-size: 0.85rem;">
                                        <i class="bi bi-x-circle-fill me-1"></i>
                                        <strong>Return rejected.</strong>
                                        <?php if (!empty($ret['admin_notes'])): ?>
                                            Reason: <?php echo htmlspecialchars($ret['admin_notes']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TRACKING MANAGEMENT -->
            <div id="trackingManagementSection" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="gold-text"><i class="bi bi-geo-alt-fill me-2"></i>Track Orders Management</h4>
                        <p style="color: var(--text-secondary); font-size:0.9rem; margin:0;">Update live tracking status for customer orders</p>
                    </div>
                    <button class="theme-toggle" onclick="toggleTheme()">
                        <i class="bi bi-moon-fill"></i>
                        <span>Theme</span>
                    </button>
                </div>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-2 col-6">
                        <div class="stock-card" style="padding:16px;background:linear-gradient(135deg,rgba(99,102,241,0.15),rgba(99,102,241,0.05));">
                            <div style="font-size:0.7rem;text-transform:uppercase;color:#6366f1;font-weight:700;letter-spacing:1px;margin-bottom:5px;">
                                <i class="bi bi-bag-check-fill me-1"></i>Placed
                            </div>
                            <div style="font-size:1.5rem;font-weight:800;color:#4f46e5;" id="stat-placed">0</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="stock-card" style="padding:16px;background:linear-gradient(135deg,rgba(59,130,246,0.15),rgba(59,130,246,0.05));">
                            <div style="font-size:0.7rem;text-transform:uppercase;color:#3b82f6;font-weight:700;letter-spacing:1px;margin-bottom:5px;">
                                <i class="bi bi-check-circle-fill me-1"></i>Confirmed
                            </div>
                            <div style="font-size:1.5rem;font-weight:800;color:#2563eb;" id="stat-confirmed">0</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="stock-card" style="padding:16px;background:linear-gradient(135deg,rgba(139,92,246,0.15),rgba(139,92,246,0.05));">
                            <div style="font-size:0.7rem;text-transform:uppercase;color:#8b5cf6;font-weight:700;letter-spacing:1px;margin-bottom:5px;">
                                <i class="bi bi-box-seam-fill me-1"></i>Packed
                            </div>
                            <div style="font-size:1.5rem;font-weight:800;color:#7c3aed;" id="stat-packed">0</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="stock-card" style="padding:16px;background:linear-gradient(135deg,rgba(245,158,11,0.15),rgba(245,158,11,0.05));">
                            <div style="font-size:0.7rem;text-transform:uppercase;color:#f59e0b;font-weight:700;letter-spacing:1px;margin-bottom:5px;">
                                <i class="bi bi-truck me-1"></i>Shipped
                            </div>
                            <div style="font-size:1.5rem;font-weight:800;color:#d97706;" id="stat-shipped">0</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="stock-card" style="padding:16px;background:linear-gradient(135deg,rgba(16,185,129,0.15),rgba(16,185,129,0.05));">
                            <div style="font-size:0.7rem;text-transform:uppercase;color:#10b981;font-weight:700;letter-spacing:1px;margin-bottom:5px;">
                                <i class="bi bi-check2-all me-1"></i>Delivered
                            </div>
                            <div style="font-size:1.5rem;font-weight:800;color:#059669;" id="stat-delivered">0</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="stock-card" style="padding:16px;background:linear-gradient(135deg,rgba(239,68,68,0.15),rgba(239,68,68,0.05));">
                            <div style="font-size:0.7rem;text-transform:uppercase;color:#ef4444;font-weight:700;letter-spacing:1px;margin-bottom:5px;">
                                <i class="bi bi-x-circle-fill me-1"></i>Cancelled
                            </div>
                            <div style="font-size:1.5rem;font-weight:800;color:#dc2626;" id="stat-cancelled">0</div>
                        </div>
                    </div>
                </div>
                
                <div class="filter-tabs">
                    <div class="filter-tab active" data-track="all" onclick="filterTracking('all', this)">
                        <i class="bi bi-grid-fill"></i>All
                    </div>
                    <div class="filter-tab" data-track="order_placed" onclick="filterTracking('order_placed', this)">
                        <i class="bi bi-bag-check"></i>Order Placed
                    </div>
                    <div class="filter-tab" data-track="dispatched" onclick="filterTracking('dispatched', this)">
                        <i class="bi bi-truck"></i>Dispatched
                    </div>
                    <div class="filter-tab" data-track="in_transit" onclick="filterTracking('in_transit', this)">
                        <i class="bi bi-geo-alt"></i>In Transit
                    </div>
                    <div class="filter-tab" data-track="delivered" onclick="filterTracking('delivered', this)">
                        <i class="bi bi-check2-all"></i>Delivered
                    </div>
                </div>
                
                <div id="trackingOrdersList" class="row g-3"></div>
            </div>

            <!-- STOCK MANAGEMENT -->
            <div id="stockManagementSection" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="gold-text"><i class="bi bi-box-seam-fill me-2"></i>Stock Management</h4>
                        <p style="color: var(--text-secondary); font-size:0.9rem; margin:0;">Track inventory, sales & remaining stock</p>
                    </div>
                    <button class="theme-toggle" onclick="toggleTheme()">
                        <i class="bi bi-moon-fill"></i>
                        <span>Theme</span>
                    </button>
                </div>
                
                <?php if($lowStockCount > 0): ?>
                <div class="low-stock-alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        <strong><?php echo $lowStockCount; ?> products are running low on stock!</strong>
                        <div style="font-size:0.82rem; color:#92400e;">Consider restocking soon to avoid running out.</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="section-title">
                    <i class="bi bi-pie-chart-fill"></i>
                    <h5>Inventory Overview</h5>
                </div>
                
                <div class="row g-3 mb-4">
                    <?php 
                    $categories = [
                        'perfume' => ['label' => 'Perfumes', 'icon' => 'bi-flower1', 'total' => $stockStats['perfume']['total_stock'], 'sold' => $soldStats['perfume']['sold'], 'products' => $stockStats['perfume']['products'], 'revenue' => $soldStats['perfume']['revenue']],
                        'tester' => ['label' => 'Testers', 'icon' => 'bi-flask', 'total' => $stockStats['tester']['total_stock'], 'sold' => $soldStats['tester']['sold'], 'products' => $stockStats['tester']['products'], 'revenue' => $soldStats['tester']['revenue']],
                        'watch' => ['label' => 'Watches', 'icon' => 'bi-clock-fill', 'total' => $stockStats['watch']['total_stock'], 'sold' => $soldStats['watch']['sold'], 'products' => $stockStats['watch']['products'], 'revenue' => $soldStats['watch']['revenue']],
                        'glass' => ['label' => 'Glasses', 'icon' => 'bi-eyeglasses', 'total' => $stockStats['glass']['total_stock'], 'sold' => $soldStats['glass']['sold'], 'products' => $stockStats['glass']['products'], 'revenue' => $soldStats['glass']['revenue']],
                    ];
                    foreach($categories as $key => $cat): 
                        $totalItems = $cat['total'] + $cat['sold'];
                        $percent = $totalItems > 0 ? round(($cat['total'] / $totalItems) * 100) : 100;
                        $barClass = $percent > 60 ? 'good' : ($percent > 30 ? 'medium' : 'low');
                    ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="stock-card <?php echo $key; ?>">
                            <div class="stock-header">
                                <div class="stock-icon">
                                    <i class="bi <?php echo $cat['icon']; ?>"></i>
                                </div>
                                <span class="cat-badge <?php echo $key; ?>"><?php echo $cat['label']; ?></span>
                            </div>
                            <div class="stock-label">Remaining Stock</div>
                            <div class="stock-value"><?php echo $cat['total']; ?></div>
                            <div class="stock-sub">
                                <strong><?php echo $cat['sold']; ?></strong> sold · <strong><?php echo $cat['products']; ?></strong> products
                            </div>
                            <div class="stock-bar">
                                <div class="stock-bar-fill <?php echo $barClass; ?>" style="width:<?php echo $percent; ?>%"></div>
                            </div>
                            <div style="font-size:0.72rem; color:var(--text-muted); margin-top:8px;">
                                <i class="bi bi-currency-exchange me-1"></i>Revenue: PKR <?php echo number_format($cat['revenue'], 2); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="section-title">
                    <i class="bi bi-list-check"></i>
                    <h5>Detailed Stock by Product</h5>
                </div>
                
                <div class="filter-tabs">
                    <div class="filter-tab active" data-cat="all" onclick="filterStock('all', this)">
                        <i class="bi bi-grid-fill"></i>All Products
                    </div>
                    <div class="filter-tab" data-cat="perfume" onclick="filterStock('perfume', this)">
                        <i class="bi bi-flower1"></i>Perfumes
                    </div>
                    <div class="filter-tab" data-cat="tester" onclick="filterStock('tester', this)">
                        <i class="bi bi-flask"></i>Testers
                    </div>
                    <div class="filter-tab" data-cat="watch" onclick="filterStock('watch', this)">
                        <i class="bi bi-clock-fill"></i>Watches
                    </div>
                    <div class="filter-tab" data-cat="glass" onclick="filterStock('glass', this)">
                        <i class="bi bi-eyeglasses"></i>Glasses
                    </div>
                    <div style="margin-left:auto;">
                        <input type="text" class="search-stock" id="stockSearch" placeholder="🔍 Search products..." oninput="searchStock(this.value)">
                    </div>
                </div>
                
                <div class="stock-table-container">
                    <table class="stock-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Sale</th>
                                <th>Sold</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="stockTableBody">
                            <?php 
                            $soldPerProduct = [];
                            foreach ($soldData as $s) {
                                $soldPerProduct[$s['product_name']] = (int)$s['total_sold'];
                            }
                            
                            foreach($allProducts as $product): 
                                $cat = getCategoryFromName($product['name']);
                                $stock = (int)$product['stock'];
                                $sold = $soldPerProduct[$product['name']] ?? 0;
                                $discountPercent = (float)($product['discount_percent'] ?? 0);
                                $salePrice = $product['sale_price'] ?? null;
                                
                                if ($stock == 0) {
                                    $statusPill = '<span class="stock-pill out"><i class="bi bi-x-circle-fill"></i>Out of Stock</span>';
                                } elseif ($stock < 10) {
                                    $statusPill = '<span class="stock-pill low"><i class="bi bi-exclamation-triangle-fill"></i>Low Stock</span>';
                                } elseif ($stock < 30) {
                                    $statusPill = '<span class="stock-pill medium"><i class="bi bi-dash-circle-fill"></i>Medium</span>';
                                } else {
                                    $statusPill = '<span class="stock-pill high"><i class="bi bi-check-circle-fill"></i>In Stock</span>';
                                }
                            ?>
                            <tr data-cat="<?php echo $cat; ?>" data-name="<?php echo strtolower(htmlspecialchars($product['name'])); ?>">
                                <td>
                                    <div class="product-cell">
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="" onerror="this.src='https://via.placeholder.com/45'">
                                        <div>
                                            <div class="pname"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <div class="pid">ID: #<?php echo $product['id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="cat-badge <?php echo $cat; ?>"><?php echo ucfirst($cat); ?></span></td>
                                <td><strong>PKR <?php echo number_format($product['price'], 2); ?></strong></td>
                                <td>
                                    <?php if ($discountPercent > 0): ?>
                                        <span style="background:linear-gradient(135deg,#ef4444,#dc2626);color:white;padding:4px 10px;border-radius:20px;font-size:0.72rem;font-weight:800;display:inline-block;">
                                            -<?php echo $discountPercent; ?>%
                                        </span>
                                        <div style="font-size:0.72rem;color:#ef4444;font-weight:700;margin-top:2px;">
                                            PKR <?php echo number_format($salePrice ?? 0, 2); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);font-size:0.78rem;">No Sale</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="sold-pill"><i class="bi bi-bag-check-fill"></i><?php echo $sold; ?> sold</span></td>
                                <td>
                                    <div class="stock-input-group">
                                        <input type="number" class="stock-input" value="<?php echo $stock; ?>" min="0" id="stock-<?php echo $product['id']; ?>" onchange="markChanged(<?php echo $product['id']; ?>)">
                                        <button class="btn-stock-save" id="save-<?php echo $product['id']; ?>" onclick="saveStock(<?php echo $product['id']; ?>)" title="Save Stock" disabled>
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </div>
                                </td>
                                <td><?php echo $statusPill; ?></td>
                                <td>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                        <button class="btn-discount" onclick="openDiscountModal(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>, <?php echo $discountPercent; ?>)" title="Manage Discount" style="padding:6px 10px;">
                                            <i class="bi bi-percent"></i>
                                        </button>
                                        <button class="btn-sm-action btn-edit" onclick="quickAddStock(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>')" title="Add Stock" style="padding:6px 10px;">
                                            <i class="bi bi-plus-circle"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- HERO BANNERS SECTION -->
            <div id="heroBannerSection" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="gold-text"><i class="bi bi-images me-2"></i>Hero Banners</h4>
                        <p style="color: var(--text-secondary); font-size:0.9rem; margin:0;">
                            Ye banners <strong>hero section ke background par 2 second ke delay se rotate</strong> karte hain
                        </p>
                    </div>
                    <button class="btn btn-primary" onclick="openHeroBannerModal()">
                        <i class="bi bi-plus-circle me-2"></i>Add Hero Banner
                    </button>
                </div>
                
                <div class="row g-3">
                    <?php if (empty($heroBanners)): ?>
                        <div class="col-12">
                            <div class="empty-orders">
                                <i class="bi bi-images"></i>
                                <h5 style="color:var(--text-primary);font-weight:700;">No hero banners yet</h5>
                                <p>Add your first hero banner to show on the homepage.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach($heroBanners as $banner): ?>
                        <div class="col-md-4 col-lg-3">
                            <div class="banner-card">
                                <img src="<?php echo htmlspecialchars($banner['image_url']); ?>" alt="">
                                <div class="banner-title"><?php echo htmlspecialchars($banner['title']); ?></div>
                                <div class="banner-subtitle"><?php echo htmlspecialchars($banner['subtitle']); ?></div>
                                <?php if (!empty($banner['badge_text'])): ?>
                                    <span class="badge-text" style="background: <?php echo str_replace(['bg-danger', 'bg-success', 'bg-primary', 'bg-warning'], ['#ff4757', '#28a745', '#007bff', '#ffc107'], $banner['badge_color']); ?>; color:white;">
                                        <?php echo htmlspecialchars($banner['badge_text']); ?>
                                    </span>
                                <?php endif; ?>
                                <div class="banner-actions">
                                    <button class="btn-sm-action btn-edit" onclick='editHeroBanner(<?php echo json_encode($banner); ?>)'>
                                        <i class="bi bi-pencil-square me-1"></i>Edit
                                    </button>
                                    <button class="btn-sm-action btn-delete" onclick="deleteBanner(<?php echo $banner['id']; ?>)">
                                        <i class="bi bi-trash3 me-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- FLASH BANNERS SECTION -->
            <div id="flashBannerSection" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="gold-text"><i class="bi bi-lightning-charge-fill me-2"></i>Flash Sale Banners</h4>
                        <p style="color: var(--text-secondary); font-size:0.9rem; margin:0;">
                            Ye chhote banners <strong>flash sale section</strong> mein show hote hain
                        </p>
                    </div>
                    <button class="btn btn-primary" onclick="openFlashBannerModal()">
                        <i class="bi bi-plus-circle me-2"></i>Add Flash Banner
                    </button>
                </div>
                
                <div class="row g-3">
                    <?php if (empty($flashBanners)): ?>
                        <div class="col-12">
                            <div class="empty-orders">
                                <i class="bi bi-lightning-charge"></i>
                                <h5 style="color:var(--text-primary);font-weight:700;">No flash banners yet</h5>
                                <p>Add your first flash sale banner.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach($flashBanners as $banner): ?>
                        <div class="col-md-4 col-lg-3">
                            <div class="banner-card">
                                <?php if (!empty($banner['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($banner['image_url']); ?>" alt="">
                                <?php else: ?>
                                    <div style="height: 140px; background: linear-gradient(135deg, #0a192f, #1a2c4a); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
                                        <i class="bi bi-lightning-charge-fill" style="font-size: 3rem; color: var(--gold);"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="banner-title"><?php echo htmlspecialchars($banner['title']); ?></div>
                                <div class="banner-subtitle"><?php echo htmlspecialchars($banner['subtitle']); ?></div>
                                <span class="badge-text" style="background: <?php echo str_replace(['bg-danger', 'bg-success', 'bg-primary', 'bg-warning'], ['#ff4757', '#28a745', '#007bff', '#ffc107'], $banner['badge_color']); ?>; color:white;">
                                    <?php echo htmlspecialchars($banner['badge_text']); ?>
                                </span>
                                <div class="banner-actions">
                                    <button class="btn-sm-action btn-edit" onclick='editFlashBanner(<?php echo json_encode($banner); ?>)'>
                                        <i class="bi bi-pencil-square me-1"></i>Edit
                                    </button>
                                    <button class="btn-sm-action btn-delete" onclick="deleteBanner(<?php echo $banner['id']; ?>)">
                                        <i class="bi bi-trash3 me-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PRODUCT MANAGEMENT -->
            <div id="productManagementSection" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="gold-text"><i class="bi bi-grid me-2"></i>Product Management</h4>
                        <p style="color: var(--text-secondary); font-size:0.9rem; margin:0;">Manage products, multiple images, and prices</p>
                    </div>
                    <button class="btn btn-primary" onclick="openProductModal()">
                        <i class="bi bi-plus-circle me-2"></i>Add New Product
                    </button>
                </div>
                
                <div class="row g-3">
                    <?php foreach($allProducts as $product): 
                        $discountPercent = (float)($product['discount_percent'] ?? 0);
                        $salePrice = $product['sale_price'] ?? null;
                        $imageCount = count($product['images']);
                    ?>
                    <div class="col-md-4 col-lg-3">
                        <div class="product-card-admin">
                            <?php if ($discountPercent > 0): ?>
                                <div class="discount-badge">
                                    <i class="bi bi-percent"></i> -<?php echo $discountPercent; ?>% OFF
                                </div>
                            <?php endif; ?>
                            
                            <div style="position:relative;">
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="" onerror="this.src='https://via.placeholder.com/140'">
                                <?php if ($imageCount > 0): ?>
                                    <span style="position:absolute;bottom:14px;right:14px;background:rgba(10,25,47,0.9);color:white;padding:4px 10px;border-radius:20px;font-size:0.7rem;font-weight:700;">
                                        <i class="bi bi-images me-1"></i><?php echo $imageCount + 1; ?> images
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                            
                            <?php if ($discountPercent > 0): ?>
                                <div style="margin:6px 0;">
                                    <span class="price-sale-highlight">PKR <?php echo number_format($salePrice, 2); ?></span>
                                    <span class="price-original-strike">PKR <?php echo number_format($product['price'], 2); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="product-price">PKR <?php echo number_format($product['price'], 2); ?></div>
                            <?php endif; ?>
                            
                            <div style="font-size:0.78rem; color:var(--text-muted); margin:6px 0;">
                                <i class="bi bi-box-seam me-1"></i>Stock: <strong style="color:var(--gold);"><?php echo (int)$product['stock']; ?></strong>
                            </div>
                            <div class="product-actions">
                                <button class="btn-sm-action btn-edit" onclick='editProduct(<?php echo json_encode($product); ?>)' style="flex:1;">
                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                </button>
                                <button class="btn-sm-action btn-delete" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- DISCOUNT MODAL -->
<div class="modal" id="discountModal">
    <div class="modal-content" style="max-width:500px;">
        <h4 class="gold-text"><i class="bi bi-percent me-2"></i>Apply Sale / Discount</h4>
        <p style="color:var(--text-secondary);font-size:0.9rem;margin-bottom:20px;">
            Product: <strong id="discountProductName" style="color:var(--gold);"></strong>
        </p>
        
        <input type="hidden" id="discountProductId">
        <input type="hidden" id="discountOriginalPrice">
        
        <div class="mb-3">
            <label class="form-label">Discount Percentage (%)</label>
            <input type="number" class="form-control" id="discountPercent" min="0" max="100" step="0.5" value="0" placeholder="e.g., 10" oninput="updateDiscountPreview()">
            <small style="color:var(--text-muted);font-size:0.75rem;">
                <i class="bi bi-info-circle"></i> 0 = No sale. Max 100%.
            </small>
        </div>
        
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
            <button type="button" class="btn btn-secondary" style="padding:6px 14px;font-size:0.8rem;" onclick="setDiscount(0)">0%</button>
            <button type="button" class="btn btn-secondary" style="padding:6px 14px;font-size:0.8rem;" onclick="setDiscount(5)">5%</button>
            <button type="button" class="btn btn-secondary" style="padding:6px 14px;font-size:0.8rem;" onclick="setDiscount(10)">10%</button>
            <button type="button" class="btn btn-secondary" style="padding:6px 14px;font-size:0.8rem;" onclick="setDiscount(15)">15%</button>
            <button type="button" class="btn btn-secondary" style="padding:6px 14px;font-size:0.8rem;" onclick="setDiscount(20)">20%</button>
            <button type="button" class="btn btn-secondary" style="padding:6px 14px;font-size:0.8rem;" onclick="setDiscount(25)">25%</button>
            <button type="button" class="btn btn-secondary" style="padding:6px 14px;font-size:0.8rem;" onclick="setDiscount(30)">30%</button>
            <button type="button" class="btn btn-secondary" style="padding:6px 14px;font-size:0.8rem;" onclick="setDiscount(50)">50%</button>
        </div>
        
        <div class="discount-preview" id="discountPreview">
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);font-weight:700;margin-bottom:8px;">
                <i class="bi bi-eye-fill me-1"></i>Live Preview
            </div>
            <div class="row-line">
                <span class="label">Original Price:</span>
                <span class="value old" id="previewOriginal">PKR 0.00</span>
            </div>
            <div class="row-line">
                <span class="label">Discount:</span>
                <span class="value" id="previewDiscount" style="color:#ef4444;">-0%</span>
            </div>
            <div class="row-line" style="border-top:1px dashed rgba(239,68,68,0.3);padding-top:10px;margin-top:6px;">
                <span class="label" style="font-weight:700;">Sale Price:</span>
                <span class="value new" id="previewSale">PKR 0.00</span>
            </div>
        </div>
        
        <div style="margin-top:20px;">
            <button type="button" class="btn btn-primary" onclick="submitDiscount()">
                <i class="bi bi-check-circle me-2"></i>Apply Discount
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeDiscountModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- Hero Banner Modal -->
<div class="modal" id="heroBannerModal">
    <div class="modal-content">
        <h4 class="gold-text"><i class="bi bi-image me-2"></i><span id="heroModalTitle">Add Hero Banner</span></h4>
        <form id="heroBannerForm" onsubmit="saveHeroBanner(event)" enctype="multipart/form-data">
            <input type="hidden" id="heroBannerId" value="">
            
            <div class="mb-3">
                <label class="form-label">Title <span style="color:#ef4444;">*</span></label>
                <input type="text" class="form-control" id="heroBannerTitle" placeholder="e.g., Azadi Sale - 50% OFF" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Subtitle (Optional)</label>
                <input type="text" class="form-control" id="heroBannerSubtitle" placeholder="e.g., Limited time offer">
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Badge Text</label>
                    <input type="text" class="form-control" id="heroBannerBadgeText" placeholder="e.g., AZADI OFFER">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Badge Color</label>
                    <select class="form-control" id="heroBannerBadgeColor">
                        <option value="bg-danger">Red</option>
                        <option value="bg-success">Green</option>
                        <option value="bg-primary">Blue</option>
                        <option value="bg-warning">Yellow</option>
                        <option value="bg-info">Cyan</option>
                        <option value="bg-dark">Dark</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Hero Background Image <span style="color:#ef4444;">*</span></label>
                <input type="file" class="form-control" id="heroBannerImageFile" accept="image/*" onchange="previewHeroImage(this)" required>
                <img id="heroImagePreview" class="image-preview mt-2" style="display:none;" alt="Preview">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Link URL (Optional)</label>
                <input type="text" class="form-control" id="heroBannerLinkUrl" placeholder="e.g., #perfumeGrid" value="#">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-2"></i>Save Hero Banner
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeHeroBannerModal()">Cancel</button>
        </form>
    </div>
</div>

<!-- Flash Banner Modal -->
<div class="modal" id="flashBannerModal">
    <div class="modal-content">
        <h4 class="gold-text"><i class="bi bi-lightning-charge-fill me-2"></i><span id="flashModalTitle">Add Flash Banner</span></h4>
        <form id="flashBannerForm" onsubmit="saveFlashBanner(event)" enctype="multipart/form-data">
            <input type="hidden" id="flashBannerId" value="">
            
            <div class="mb-3">
                <label class="form-label">Title <span style="color:#ef4444;">*</span></label>
                <input type="text" class="form-control" id="flashBannerTitle" placeholder="e.g., Flash Sale 30% OFF" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Subtitle</label>
                <input type="text" class="form-control" id="flashBannerSubtitle" placeholder="e.g., Limited time offer">
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Badge Text <span style="color:#ef4444;">*</span></label>
                    <input type="text" class="form-control" id="flashBannerBadgeText" placeholder="e.g., 50% OFF" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Badge Color</label>
                    <select class="form-control" id="flashBannerBadgeColor">
                        <option value="bg-danger">Red</option>
                        <option value="bg-success">Green</option>
                        <option value="bg-primary">Blue</option>
                        <option value="bg-warning">Yellow</option>
                        <option value="bg-info">Cyan</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Icon Image (Optional)</label>
                <input type="file" class="form-control" id="flashBannerImageFile" accept="image/*" onchange="previewFlashImage(this)">
                <img id="flashImagePreview" class="image-preview mt-2" style="display:none;" alt="Preview">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Link URL</label>
                <input type="text" class="form-control" id="flashBannerLinkUrl" placeholder="e.g., #perfumeGrid" value="#">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-2"></i>Save Flash Banner
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeFlashBannerModal()">Cancel</button>
        </form>
    </div>
</div>

<!-- PRODUCT MODAL WITH MULTIPLE IMAGES -->
<div class="modal" id="productModal">
    <div class="modal-content" style="max-width:700px;">
        <h4 class="gold-text"><i class="bi bi-box-seam me-2"></i><span id="productModalTitle">Add Product</span></h4>
        <form id="productForm" onsubmit="saveProduct(event)" enctype="multipart/form-data">
            <input type="hidden" id="productId" value="">
            
            <div class="mb-3">
                <label class="form-label">Product Name</label>
                <input type="text" class="form-control" id="productName" placeholder="e.g., Oud Noir" required>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Price (PKR)</label>
                    <input type="number" step="0.01" class="form-control" id="productPrice" placeholder="e.g., 8900" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Initial Stock</label>
                    <input type="number" class="form-control" id="productStock" placeholder="e.g., 50" value="50" min="0" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Discount % (Optional)</label>
                    <input type="number" step="0.5" class="form-control" id="productDiscount" placeholder="e.g., 10" value="0" min="0" max="100">
                    <small style="color:var(--text-muted);font-size:0.72rem;">0 = no sale</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Sale Price Preview</label>
                    <input type="text" class="form-control" id="productSalePreview" readonly value="PKR 0.00" style="background:var(--bg-secondary);font-weight:700;color:#ef4444;">
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">
                    <i class="bi bi-images me-1"></i>Product Images (2-3 recommended)
                </label>
                
                <div id="existingImagesContainer" style="display:none;">
                    <div style="font-size:0.75rem;color:var(--text-muted);font-weight:700;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;">
                        Current Images:
                    </div>
                    <div class="multi-image-grid" id="existingImagesGrid"></div>
                </div>
                
                <div class="upload-images-area" onclick="document.getElementById('productImageFiles').click()" style="margin-top:12px;">
                    <i class="bi bi-cloud-arrow-up-fill"></i>
                    <div class="upload-text">Click to upload multiple images</div>
                    <div class="upload-hint">JPG, PNG, WEBP · Max 3 images at once</div>
                </div>
                <input type="file" id="productImageFiles" accept="image/*" multiple style="display:none;" onchange="previewMultipleImages(this)">
                
                <div id="newImagesPreview" style="display:none;margin-top:12px;">
                    <div style="font-size:0.75rem;color:var(--text-muted);font-weight:700;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;">
                        New Images to Upload:
                    </div>
                    <div class="multi-image-grid" id="newImagesGrid"></div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" id="productDescription" rows="3" placeholder="Product description..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-2"></i>Save Product
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeProductModal()">Cancel</button>
        </form>
    </div>
</div>

<!-- Add Stock Modal -->
<div class="modal" id="addStockModal">
    <div class="modal-content" style="max-width:450px;">
        <h4 class="gold-text"><i class="bi bi-plus-circle me-2"></i>Add Stock</h4>
        <p style="color: var(--text-secondary); font-size:0.9rem; margin-bottom:20px;">
            Product: <strong id="addStockProductName" style="color:var(--gold);"></strong>
        </p>
        <input type="hidden" id="addStockProductId">
        <div class="mb-3">
            <label class="form-label">Quantity to Add</label>
            <input type="number" class="form-control" id="addStockQty" placeholder="e.g., 50" min="1" value="10" required>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px;">
            <button type="button" class="btn btn-secondary" style="padding:6px 14px; font-size:0.8rem;" onclick="document.getElementById('addStockQty').value=10">+10</button>
            <button type="button" class="btn btn-secondary" style="padding:6px 14px; font-size:0.8rem;" onclick="document.getElementById('addStockQty').value=25">+25</button>
            <button type="button" class="btn btn-secondary" style="padding:6px 14px; font-size:0.8rem;" onclick="document.getElementById('addStockQty').value=50">+50</button>
            <button type="button" class="btn btn-secondary" style="padding:6px 14px; font-size:0.8rem;" onclick="document.getElementById('addStockQty').value=100">+100</button>
        </div>
        <button type="button" class="btn btn-primary" onclick="submitAddStock()">
            <i class="bi bi-check-circle me-2"></i>Add to Stock
        </button>
        <button type="button" class="btn btn-secondary" onclick="closeAddStockModal()">Cancel</button>
    </div>
</div>

<!-- Complete Return Modal -->
<div class="modal" id="completeReturnModal">
    <div class="modal-content" style="max-width:500px;">
        <h4 class="gold-text"><i class="bi bi-cash-coin me-2"></i>Process Refund & Restock</h4>
        <p style="color: var(--text-secondary); font-size:0.9rem; margin-bottom:20px;">
            Complete this return by issuing a refund. The product stock will be automatically restored.
        </p>
        
        <input type="hidden" id="completeReturnOrderId">
        
        <div class="mb-3">
            <label class="form-label">Refund Amount (PKR)</label>
            <input type="number" step="0.01" class="form-control" id="refundAmount" placeholder="0.00" required>
            <small style="color: var(--text-muted); font-size: 0.75rem;">
                Suggested amount: <span id="suggestedRefund" style="color: var(--gold); font-weight: 700;">PKR 0.00</span>
            </small>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Refund Method</label>
            <select class="form-control" id="refundMethod">
                <option value="card">💳 Credit/Debit Card</option>
                <option value="bank">🏦 Bank Transfer</option>
                <option value="cash">💵 Cash</option>
                <option value="easypaisa">📱 EasyPaisa</option>
                <option value="jazzcash">📱 JazzCash</option>
            </select>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Note (Optional)</label>
            <textarea class="form-control" id="completeReturnNote" rows="2" placeholder="e.g., Product received in good condition"></textarea>
        </div>
        
        <button type="button" class="btn btn-primary" onclick="submitCompleteReturn()">
            <i class="bi bi-check-circle me-2"></i>Confirm Refund
        </button>
        <button type="button" class="btn btn-secondary" onclick="closeCompleteReturnModal()">Cancel</button>
    </div>
</div>

<!-- Screenshot Modal -->
<div id="screenshotModal" class="screenshot-modal" onclick="closeScreenshotModal()">
    <button class="modal-close" onclick="event.stopPropagation(); closeScreenshotModal();">✕</button>
    <img id="modalImage" src="" alt="Screenshot">
</div>

<script>
// ==========================
// ALL DATA
// ==========================
const allOrdersData = <?php echo json_encode($allOrders); ?>;
const allProductsData = <?php echo json_encode($allProducts); ?>;

const changedInputs = new Set();

// ==========================
// TRACKING STATUS
// ==========================
const trackingStatuses = {
    'order_placed': { label: 'Order Placed', icon: 'bi-bag-check-fill', color: '#6366f1' },
    'confirmed': { label: 'Confirmed', icon: 'bi-check-circle-fill', color: '#3b82f6' },
    'packed': { label: 'Packed', icon: 'bi-box-seam-fill', color: '#8b5cf6' },
    'dispatched': { label: 'Dispatched', icon: 'bi-truck', color: '#f59e0b' },
    'in_transit': { label: 'In Transit', icon: 'bi-geo-alt-fill', color: '#0ea5e9' },
    'out_for_delivery': { label: 'Out for Delivery', icon: 'bi-bicycle', color: '#10b981' },
    'delivered': { label: 'Delivered', icon: 'bi-check2-all', color: '#059669' },
    'cancelled': { label: 'Cancelled', icon: 'bi-x-circle-fill', color: '#ef4444' }
};

// ==========================
// MONTHLY REPORT SECTION - NEW
// ==========================
function showMonthlyReportSection(element) {
    if (element) {
        document.querySelectorAll('.admin-sidebar .nav-link').forEach(l => l.classList.remove('active'));
        element.classList.add('active');
    }
    document.getElementById('orderManagementSection').style.display = 'none';
    document.getElementById('heroBannerSection').style.display = 'none';
    document.getElementById('flashBannerSection').style.display = 'none';
    document.getElementById('productManagementSection').style.display = 'none';
    document.getElementById('stockManagementSection').style.display = 'none';
    document.getElementById('trackingManagementSection').style.display = 'none';
    document.getElementById('returnsManagementSection').style.display = 'none';
    document.getElementById('soldItemsSection').style.display = 'none';
    document.getElementById('monthlyReportSection').style.display = 'block';
    
    // Init charts after showing section
    setTimeout(() => {
        initMonthlyCharts();
    }, 100);
}

function changeReportFilter() {
    const year = document.getElementById('reportYear').value;
    const month = document.getElementById('reportMonth').value;
    window.location.href = `?report_year=${year}&report_month=${month}#monthly`;
}

function selectMonth(monthNum) {
    const year = document.getElementById('reportYear').value;
    window.location.href = `?report_year=${year}&report_month=${monthNum}`;
}

function printReport() {
    window.print();
}

// Monthly Charts
let revenueChartInstance = null;
let itemsChartInstance = null;

function initMonthlyCharts() {
    const monthlyLabels = <?php echo json_encode($monthlyLabels); ?>;
    const monthlyRevenue = <?php echo json_encode(array_values(array_map(function($d) { return round($d['revenue'], 2); }, $monthlyData))); ?>;
    const monthlyItems = <?php echo json_encode(array_values(array_map(function($d) { return $d['items_sold']; }, $monthlyData))); ?>;
    const currentMonthNum = <?php echo $currentMonth; ?>;
    const currentYearNum = <?php echo $currentYear; ?>;
    const actualCurrentMonth = <?php echo (int)date('n'); ?>;
    const actualCurrentYear = <?php echo (int)date('Y'); ?>;
    
    // Revenue Chart
    const revCtx = document.getElementById('monthlyRevenueChart');
    if (revCtx) {
        if (revenueChartInstance) revenueChartInstance.destroy();
        
        const revColors = monthlyLabels.map((_, i) => {
            const mNum = i + 1;
            if (mNum === actualCurrentMonth && currentYearNum === actualCurrentYear) return '#d4af37';
            if (mNum === currentMonthNum) return '#10b981';
            return 'rgba(212, 175, 55, 0.5)';
        });
        
        revenueChartInstance = new Chart(revCtx, {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Revenue (PKR)',
                    data: monthlyRevenue,
                    backgroundColor: revColors,
                    borderColor: revColors.map(c => c.replace('0.5', '1')),
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0a192f',
                        titleColor: '#d4af37',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'PKR ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000) return (value/1000) + 'K';
                                return value;
                            },
                            color: '#6b7a8f',
                            font: { size: 11, weight: '600' }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        ticks: {
                            color: '#6b7a8f',
                            font: { size: 11, weight: '600' }
                        },
                        grid: { display: false }
                    }
                }
            }
        });
    }
    
    // Items Chart
    const itemsCtx = document.getElementById('monthlyItemsChart');
    if (itemsCtx) {
        if (itemsChartInstance) itemsChartInstance.destroy();
        
        itemsChartInstance = new Chart(itemsCtx, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Items Sold',
                    data: monthlyItems,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.15)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#6366f1',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0a192f',
                        titleColor: '#d4af37',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' items';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#6b7a8f',
                            font: { size: 11, weight: '600' }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        ticks: {
                            color: '#6b7a8f',
                            font: { size: 11, weight: '600' }
                        },
                        grid: { display: false }
                    }
                }
            }
        });
    }
}

// ==========================
// ORDER MANAGEMENT
// ==========================
function loadOrders(filter, element) {
    if (element) {
        document.querySelectorAll('.admin-sidebar .nav-link').forEach(l => l.classList.remove('active'));
        element.classList.add('active');
    }
    
    document.getElementById('orderManagementSection').style.display = 'block';
    document.getElementById('stockManagementSection').style.display = 'none';
    document.getElementById('trackingManagementSection').style.display = 'none';
    document.getElementById('heroBannerSection').style.display = 'none';
    document.getElementById('flashBannerSection').style.display = 'none';
    document.getElementById('productManagementSection').style.display = 'none';
    document.getElementById('returnsManagementSection').style.display = 'none';
    document.getElementById('soldItemsSection').style.display = 'none';
    document.getElementById('monthlyReportSection').style.display = 'none';
    
    const container = document.getElementById('adminOrderList');
    let filtered = allOrdersData;
    if (filter && filter !== 'all') {
        filtered = allOrdersData.filter(o => o.status === filter);
    }
    
    if (!filtered || filtered.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="empty-orders">
                    <i class="bi bi-inbox"></i>
                    <h5 style="color:var(--text-primary);font-weight:700;">No orders found</h5>
                    <p>There are no ${filter === 'all' ? '' : filter + ' '}orders to display.</p>
                </div>
            </div>
        `;
        return;
    }
    
    let html = '';
    filtered.forEach(order => {
        const statusClass = 'status-' + (order.status || 'pending');
        const orderDate = order.created_at ? new Date(order.created_at).toLocaleString() : 'N/A';
        const totalAmount = order.total || 0;
        const productName = order.product_name || 'Unknown Product';
        const productImage = order.product_image || 'https://via.placeholder.com/60';
        const quantity = order.quantity || 1;
        const unitPrice = (totalAmount / quantity).toFixed(2);
        
        const confirmBtn = order.status === 'pending' 
            ? `<button class="btn-confirm" onclick="confirmOrder(${order.id})">
                   <i class="bi bi-check-circle"></i>Confirm
               </button>` 
            : '';
        
        const screenshotBtn = order.screenshot 
            ? `<button class="screenshot-btn" onclick="viewScreenshot('${order.screenshot}')">
                   <i class="bi bi-image"></i>Payment
               </button>` 
            : '';
        
        const receiptBtn = `<a href="receipt.php?order_id=${order.id}" target="_blank" class="receipt-btn">
                <i class="bi bi-receipt"></i>Receipt
            </a>`;
        
        const currentTracking = order.tracking_status || 'order_placed';
        const trackInfo = trackingStatuses[currentTracking] || trackingStatuses['order_placed'];
        
        let trackingOptions = '';
        for (const [key, val] of Object.entries(trackingStatuses)) {
            trackingOptions += `<option value="${key}" ${key === currentTracking ? 'selected' : ''}>${val.label}</option>`;
        }
        
        html += `
            <div class="col-md-6 col-lg-4">
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <div class="order-id">Order #${order.id}</div>
                            <div class="order-date"><i class="bi bi-clock me-1"></i>${orderDate}</div>
                        </div>
                        <span class="order-status ${statusClass}">${order.status || 'pending'}</span>
                    </div>
                    <div class="order-customer">
                        <span><i class="bi bi-person me-1"></i><strong>${order.client_name || 'Guest'}</strong></span>
                        <span><i class="bi bi-envelope me-1"></i>${order.client_email || 'N/A'}</span>
                        <span><i class="bi bi-geo-alt me-1"></i>${order.address || 'N/A'}</span>
                    </div>
                    <div class="order-product-display">
                        <img src="${productImage}" alt="${productName}" onerror="this.src='https://via.placeholder.com/60'">
                        <div class="product-info">
                            <div class="pname">${productName}</div>
                            <div class="pmeta">Qty: ${quantity} × PKR ${unitPrice}</div>
                        </div>
                    </div>
                    <div class="order-footer">
                        <div class="order-total">
                            <small>Total Amount</small>
                            PKR ${parseFloat(totalAmount).toFixed(2)}
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            ${receiptBtn}
                            ${screenshotBtn}
                            ${confirmBtn}
                        </div>
                    </div>
                    
                    <div class="tracking-control">
                        <div class="tracking-control-label">
                            <i class="bi bi-geo-alt-fill"></i>Live Tracking Status
                        </div>
                        <div style="margin-bottom:10px;">
                            <span class="tracking-badge-current" style="background:${trackInfo.color}20;color:${trackInfo.color};">
                                <i class="bi ${trackInfo.icon}"></i>${trackInfo.label}
                            </span>
                        </div>
                        <div class="tracking-row">
                            <select id="track-select-${order.id}" class="tracking-select">
                                ${trackingOptions}
                            </select>
                            <input type="text" id="track-note-${order.id}" class="tracking-note-input" placeholder="Note (optional)" value="${order.tracking_note || ''}">
                            <button class="btn-save-tracking" onclick="updateTracking(${order.id})">
                                <i class="bi bi-check-lg"></i>Save
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// ==========================
// SOLD ITEMS SECTION
// ==========================
function showSoldItemsSection(element) {
    if (element) {
        document.querySelectorAll('.admin-sidebar .nav-link').forEach(l => l.classList.remove('active'));
        element.classList.add('active');
    }
    document.getElementById('orderManagementSection').style.display = 'none';
    document.getElementById('heroBannerSection').style.display = 'none';
    document.getElementById('flashBannerSection').style.display = 'none';
    document.getElementById('productManagementSection').style.display = 'none';
    document.getElementById('stockManagementSection').style.display = 'none';
    document.getElementById('trackingManagementSection').style.display = 'none';
    document.getElementById('returnsManagementSection').style.display = 'none';
    document.getElementById('monthlyReportSection').style.display = 'none';
    document.getElementById('soldItemsSection').style.display = 'block';
}

function filterSoldItems(cat, element) {
    document.querySelectorAll('#soldItemsSection .filter-tab').forEach(t => t.classList.remove('active'));
    if (element) element.classList.add('active');
    
    const rows = document.querySelectorAll('#soldItemsTableBody tr');
    rows.forEach(row => {
        if (row.getAttribute('colspan')) return;
        const rowCat = row.getAttribute('data-cat');
        row.style.display = (cat === 'all' || rowCat === cat) ? '' : 'none';
    });
}

// ==========================
// DISCOUNT MANAGEMENT
// ==========================
function openDiscountModal(productId, productName, originalPrice, currentDiscount) {
    document.getElementById('discountProductId').value = productId;
    document.getElementById('discountProductName').textContent = productName;
    document.getElementById('discountOriginalPrice').value = originalPrice;
    document.getElementById('discountPercent').value = currentDiscount || 0;
    updateDiscountPreview();
    document.getElementById('discountModal').classList.add('active');
}

function closeDiscountModal() {
    document.getElementById('discountModal').classList.remove('active');
}

function setDiscount(percent) {
    document.getElementById('discountPercent').value = percent;
    updateDiscountPreview();
}

function updateDiscountPreview() {
    const original = parseFloat(document.getElementById('discountOriginalPrice').value) || 0;
    const percent = parseFloat(document.getElementById('discountPercent').value) || 0;
    const salePrice = original - (original * percent / 100);
    
    document.getElementById('previewOriginal').textContent = 'PKR ' + original.toFixed(2);
    document.getElementById('previewDiscount').textContent = '-' + percent + '%';
    document.getElementById('previewSale').textContent = 'PKR ' + salePrice.toFixed(2);
}

function submitDiscount() {
    const productId = document.getElementById('discountProductId').value;
    const percent = parseFloat(document.getElementById('discountPercent').value) || 0;
    
    if (percent < 0 || percent > 100) {
        alert('Discount must be between 0 and 100');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_discount');
    formData.append('product_id', productId);
    formData.append('discount_percent', percent);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeDiscountModal();
            showAdminToast('✅ ' + data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAdminToast('❌ ' + (data.message || 'Failed'), 'error');
        }
    })
    .catch(() => showAdminToast('❌ Connection error', 'error'));
}

// ==========================
// UPDATE TRACKING
// ==========================
function updateTracking(orderId) {
    const select = document.getElementById('track-select-' + orderId);
    const noteInput = document.getElementById('track-note-' + orderId);
    const trackingStatus = select.value;
    const trackingNote = noteInput.value;
    
    const btn = event.target.closest('.btn-save-tracking');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i>Saving...';
    }
    
    const formData = new FormData();
    formData.append('action', 'update_tracking');
    formData.append('order_id', orderId);
    formData.append('tracking_status', trackingStatus);
    formData.append('tracking_note', trackingNote);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAdminToast('✓ Tracking updated: ' + trackingStatuses[trackingStatus].label, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showAdminToast(data.message || 'Failed to update', 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg"></i>Save';
            }
        }
    })
    .catch(err => {
        console.error(err);
        showAdminToast('Connection error', 'error');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i>Save';
        }
    });
}

// ==========================
// RETURNS MANAGEMENT
// ==========================
function showReturnsSection(element) {
    if (element) {
        document.querySelectorAll('.admin-sidebar .nav-link').forEach(l => l.classList.remove('active'));
        element.classList.add('active');
    }
    document.getElementById('orderManagementSection').style.display = 'none';
    document.getElementById('heroBannerSection').style.display = 'none';
    document.getElementById('flashBannerSection').style.display = 'none';
    document.getElementById('productManagementSection').style.display = 'none';
    document.getElementById('stockManagementSection').style.display = 'none';
    document.getElementById('trackingManagementSection').style.display = 'none';
    document.getElementById('soldItemsSection').style.display = 'none';
    document.getElementById('monthlyReportSection').style.display = 'none';
    document.getElementById('returnsManagementSection').style.display = 'block';
}

function filterReturns(status, element) {
    document.querySelectorAll('#returnsManagementSection .filter-tab').forEach(t => t.classList.remove('active'));
    if (element) element.classList.add('active');
    
    document.querySelectorAll('#returnsList .return-card').forEach(card => {
        if (status === 'all' || card.dataset.returnStatus === status) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function approveReturn(orderId) {
    const noteInput = document.getElementById('adminNote-' + orderId);
    const note = noteInput ? noteInput.value.trim() : '';
    
    if (!confirm('Approve this return request?')) return;
    
    const formData = new FormData();
    formData.append('action', 'approve_return');
    formData.append('order_id', orderId);
    formData.append('note', note);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAdminToast('✅ Return approved!', 'success');
            setTimeout(() => location.reload(), 900);
        } else {
            showAdminToast('❌ ' + (data.message || 'Failed'), 'error');
        }
    })
    .catch(() => showAdminToast('❌ Connection error', 'error'));
}

function rejectReturn(orderId) {
    const noteInput = document.getElementById('adminNote-' + orderId);
    const note = noteInput ? noteInput.value.trim() : '';
    
    if (!note) {
        showAdminToast('❌ Please enter a reason for rejection', 'error');
        noteInput.focus();
        return;
    }
    
    if (!confirm('Reject this return request?')) return;
    
    const formData = new FormData();
    formData.append('action', 'reject_return');
    formData.append('order_id', orderId);
    formData.append('note', note);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAdminToast('✅ Return rejected', 'success');
            setTimeout(() => location.reload(), 900);
        } else {
            showAdminToast('❌ ' + (data.message || 'Failed'), 'error');
        }
    })
    .catch(() => showAdminToast('❌ Connection error', 'error'));
}

function openCompleteReturnModal(orderId, suggestedAmount) {
    document.getElementById('completeReturnOrderId').value = orderId;
    document.getElementById('refundAmount').value = parseFloat(suggestedAmount).toFixed(2);
    document.getElementById('suggestedRefund').textContent = 'PKR ' + parseFloat(suggestedAmount).toFixed(2);
    document.getElementById('refundMethod').value = 'card';
    document.getElementById('completeReturnNote').value = '';
    document.getElementById('completeReturnModal').classList.add('active');
}

function closeCompleteReturnModal() {
    document.getElementById('completeReturnModal').classList.remove('active');
}

function submitCompleteReturn() {
    const orderId = document.getElementById('completeReturnOrderId').value;
    const amount = parseFloat(document.getElementById('refundAmount').value) || 0;
    const method = document.getElementById('refundMethod').value;
    const note = document.getElementById('completeReturnNote').value.trim();
    
    if (amount <= 0) {
        showAdminToast('❌ Please enter a valid refund amount', 'error');
        return;
    }
    
    if (!confirm(`Confirm refund of PKR ${amount.toFixed(2)} via ${method}?\n\nStock will be restored automatically.`)) return;
    
    const formData = new FormData();
    formData.append('action', 'complete_return');
    formData.append('order_id', orderId);
    formData.append('amount', amount);
    formData.append('method', method);
    formData.append('note', note);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeCompleteReturnModal();
            showAdminToast('✅ ' + data.message, 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            showAdminToast('❌ ' + (data.message || 'Failed'), 'error');
        }
    })
    .catch(() => showAdminToast('❌ Connection error', 'error'));
}

// ==========================
// TRACKING MANAGEMENT SECTION
// ==========================
let currentTrackingFilter = 'all';

function showTrackingSection(element) {
    if (element) {
        document.querySelectorAll('.admin-sidebar .nav-link').forEach(l => l.classList.remove('active'));
        element.classList.add('active');
    }
    document.getElementById('orderManagementSection').style.display = 'none';
    document.getElementById('heroBannerSection').style.display = 'none';
    document.getElementById('flashBannerSection').style.display = 'none';
    document.getElementById('productManagementSection').style.display = 'none';
    document.getElementById('stockManagementSection').style.display = 'none';
    document.getElementById('returnsManagementSection').style.display = 'none';
    document.getElementById('soldItemsSection').style.display = 'none';
    document.getElementById('monthlyReportSection').style.display = 'none';
    document.getElementById('trackingManagementSection').style.display = 'block';
    
    loadTrackingStats();
    loadTrackingOrders('all');
}

function loadTrackingStats() {
    const stats = { placed: 0, confirmed: 0, packed: 0, shipped: 0, delivered: 0, cancelled: 0 };
    
    allOrdersData.forEach(o => {
        const status = o.tracking_status || 'order_placed';
        if (status === 'order_placed') stats.placed++;
        else if (status === 'confirmed') stats.confirmed++;
        else if (status === 'packed') stats.packed++;
        else if (['dispatched', 'in_transit', 'out_for_delivery'].includes(status)) stats.shipped++;
        else if (status === 'delivered') stats.delivered++;
        else if (status === 'cancelled') stats.cancelled++;
    });
    
    document.getElementById('stat-placed').textContent = stats.placed;
    document.getElementById('stat-confirmed').textContent = stats.confirmed;
    document.getElementById('stat-packed').textContent = stats.packed;
    document.getElementById('stat-shipped').textContent = stats.shipped;
    document.getElementById('stat-delivered').textContent = stats.delivered;
    document.getElementById('stat-cancelled').textContent = stats.cancelled;
}

function filterTracking(filter, element) {
    currentTrackingFilter = filter;
    document.querySelectorAll('#trackingManagementSection .filter-tab').forEach(t => t.classList.remove('active'));
    if (element) element.classList.add('active');
    loadTrackingOrders(filter);
}

function loadTrackingOrders(filter) {
    const container = document.getElementById('trackingOrdersList');
    let filtered = allOrdersData;
    
    if (filter && filter !== 'all') {
        if (filter === 'dispatched') {
            filtered = allOrdersData.filter(o => ['dispatched', 'in_transit', 'out_for_delivery'].includes(o.tracking_status || 'order_placed'));
        } else {
            filtered = allOrdersData.filter(o => (o.tracking_status || 'order_placed') === filter);
        }
    }
    
    if (!filtered || filtered.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="empty-orders">
                    <i class="bi bi-geo-alt"></i>
                    <h5 style="color:var(--text-primary);font-weight:700;">No orders found</h5>
                    <p>No orders match this tracking filter.</p>
                </div>
            </div>
        `;
        return;
    }
    
    let html = '';
    filtered.forEach(order => {
        const orderDate = order.created_at ? new Date(order.created_at).toLocaleString() : 'N/A';
        const productName = order.product_name || 'Unknown';
        const productImage = order.product_image || 'https://via.placeholder.com/60';
        const quantity = order.quantity || 1;
        const currentTracking = order.tracking_status || 'order_placed';
        const trackInfo = trackingStatuses[currentTracking];
        
        let trackingOptions = '';
        for (const [key, val] of Object.entries(trackingStatuses)) {
            trackingOptions += `<option value="${key}" ${key === currentTracking ? 'selected' : ''}>${val.label}</option>`;
        }
        
        html += `
            <div class="col-md-6 col-lg-4">
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <div class="order-id">Order #${order.id}</div>
                            <div class="order-date"><i class="bi bi-person me-1"></i>${order.client_name || 'Guest'}</div>
                        </div>
                        <span class="tracking-badge-current" style="background:${trackInfo.color}20;color:${trackInfo.color};">
                            <i class="bi ${trackInfo.icon}"></i>${trackInfo.label}
                        </span>
                    </div>
                    
                    <div class="order-product-display">
                        <img src="${productImage}" alt="${productName}" onerror="this.src='https://via.placeholder.com/60'">
                        <div class="product-info">
                            <div class="pname">${productName}</div>
                            <div class="pmeta">Qty: ${quantity} · ${orderDate}</div>
                        </div>
                    </div>
                    
                    <div class="tracking-control" style="margin-top:14px;">
                        <div class="tracking-control-label">
                            <i class="bi bi-geo-alt-fill"></i>Update Status
                        </div>
                        <div class="tracking-row">
                            <select id="track2-select-${order.id}" class="tracking-select">
                                ${trackingOptions}
                            </select>
                            <input type="text" id="track2-note-${order.id}" class="tracking-note-input" placeholder="Note" value="${order.tracking_note || ''}">
                            <button class="btn-save-tracking" onclick="updateTrackingQuick(${order.id}, this)">
                                <i class="bi bi-check-lg"></i>Save
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function updateTrackingQuick(orderId, btn) {
    const select = document.getElementById('track2-select-' + orderId);
    const noteInput = document.getElementById('track2-note-' + orderId);
    const trackingStatus = select.value;
    const trackingNote = noteInput.value;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i>Saving...';
    
    const formData = new FormData();
    formData.append('action', 'update_tracking');
    formData.append('order_id', orderId);
    formData.append('tracking_status', trackingStatus);
    formData.append('tracking_note', trackingNote);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAdminToast('✓ Tracking updated: ' + trackingStatuses[trackingStatus].label, 'success');
            loadTrackingStats();
            setTimeout(() => loadTrackingOrders(currentTrackingFilter), 500);
        } else {
            showAdminToast(data.message || 'Failed', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i>Save';
        }
    })
    .catch(err => {
        console.error(err);
        showAdminToast('Connection error', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i>Save';
    });
}

// ==========================
// CONFIRM ORDER
// ==========================
function confirmOrder(orderId) {
    if (!confirm('Are you sure you want to confirm this order?')) return;
    
    const formData = new FormData();
    formData.append('action', 'confirm_order');
    formData.append('order_id', orderId);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAdminToast('✓ Order confirmed & stock updated!', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showAdminToast(data.message || 'Failed', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showAdminToast('Connection error.', 'error');
    });
}

function viewScreenshot(src) {
    document.getElementById('modalImage').src = src;
    document.getElementById('screenshotModal').classList.add('active');
}
function closeScreenshotModal() {
    document.getElementById('screenshotModal').classList.remove('active');
}

// ==========================
// SECTION SWITCHING
// ==========================
function showStockSection(element) {
    if (element) {
        document.querySelectorAll('.admin-sidebar .nav-link').forEach(l => l.classList.remove('active'));
        element.classList.add('active');
    }
    document.getElementById('orderManagementSection').style.display = 'none';
    document.getElementById('heroBannerSection').style.display = 'none';
    document.getElementById('flashBannerSection').style.display = 'none';
    document.getElementById('productManagementSection').style.display = 'none';
    document.getElementById('trackingManagementSection').style.display = 'none';
    document.getElementById('returnsManagementSection').style.display = 'none';
    document.getElementById('soldItemsSection').style.display = 'none';
    document.getElementById('monthlyReportSection').style.display = 'none';
    document.getElementById('stockManagementSection').style.display = 'block';
}

function showHeroBannerSection(element) {
    if (element) {
        document.querySelectorAll('.admin-sidebar .nav-link').forEach(l => l.classList.remove('active'));
        element.classList.add('active');
    }
    document.getElementById('orderManagementSection').style.display = 'none';
    document.getElementById('productManagementSection').style.display = 'none';
    document.getElementById('stockManagementSection').style.display = 'none';
    document.getElementById('trackingManagementSection').style.display = 'none';
    document.getElementById('returnsManagementSection').style.display = 'none';
    document.getElementById('flashBannerSection').style.display = 'none';
    document.getElementById('soldItemsSection').style.display = 'none';
    document.getElementById('monthlyReportSection').style.display = 'none';
    document.getElementById('heroBannerSection').style.display = 'block';
}

function showFlashBannerSection(element) {
    if (element) {
        document.querySelectorAll('.admin-sidebar .nav-link').forEach(l => l.classList.remove('active'));
        element.classList.add('active');
    }
    document.getElementById('orderManagementSection').style.display = 'none';
    document.getElementById('productManagementSection').style.display = 'none';
    document.getElementById('stockManagementSection').style.display = 'none';
    document.getElementById('trackingManagementSection').style.display = 'none';
    document.getElementById('returnsManagementSection').style.display = 'none';
    document.getElementById('heroBannerSection').style.display = 'none';
    document.getElementById('soldItemsSection').style.display = 'none';
    document.getElementById('monthlyReportSection').style.display = 'none';
    document.getElementById('flashBannerSection').style.display = 'block';
}

function showProductSection(element) {
    if (element) {
        document.querySelectorAll('.admin-sidebar .nav-link').forEach(l => l.classList.remove('active'));
        element.classList.add('active');
    }
    document.getElementById('orderManagementSection').style.display = 'none';
    document.getElementById('heroBannerSection').style.display = 'none';
    document.getElementById('flashBannerSection').style.display = 'none';
    document.getElementById('stockManagementSection').style.display = 'none';
    document.getElementById('trackingManagementSection').style.display = 'none';
    document.getElementById('returnsManagementSection').style.display = 'none';
    document.getElementById('soldItemsSection').style.display = 'none';
    document.getElementById('monthlyReportSection').style.display = 'none';
    document.getElementById('productManagementSection').style.display = 'block';
}

// ==========================
// STOCK MANAGEMENT
// ==========================
function markChanged(productId) {
    changedInputs.add(productId);
    document.getElementById('save-' + productId).disabled = false;
}

function saveStock(productId) {
    const input = document.getElementById('stock-' + productId);
    const btn = document.getElementById('save-' + productId);
    const newStock = parseInt(input.value) || 0;
    
    if (newStock < 0) {
        alert('Stock cannot be negative!');
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    
    const formData = new FormData();
    formData.append('action', 'update_stock');
    formData.append('product_id', productId);
    formData.append('stock', newStock);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
            changedInputs.delete(productId);
            
            setTimeout(() => {
                if (!changedInputs.has(productId)) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="bi bi-check-lg"></i>';
                }
            }, 1000);
            
            showAdminToast(data.message, 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            btn.innerHTML = '<i class="bi bi-x-lg"></i>';
            btn.disabled = false;
            showAdminToast(data.message || 'Failed', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        btn.innerHTML = '<i class="bi bi-check-lg"></i>';
        btn.disabled = false;
        showAdminToast('Connection error', 'error');
    });
}

function quickAddStock(productId, productName) {
    document.getElementById('addStockProductId').value = productId;
    document.getElementById('addStockProductName').textContent = productName;
    document.getElementById('addStockQty').value = 10;
    document.getElementById('addStockModal').classList.add('active');
}

function closeAddStockModal() {
    document.getElementById('addStockModal').classList.remove('active');
}

function submitAddStock() {
    const productId = document.getElementById('addStockProductId').value;
    const qty = parseInt(document.getElementById('addStockQty').value) || 0;
    
    if (qty <= 0) {
        alert('Please enter valid quantity');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_stock');
    formData.append('product_id', productId);
    formData.append('quantity', qty);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeAddStockModal();
            showAdminToast(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAdminToast(data.message || 'Failed', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showAdminToast('Connection error', 'error');
    });
}

let currentStockFilter = 'all';
let currentStockSearch = '';

function filterStock(cat, element) {
    currentStockFilter = cat;
    document.querySelectorAll('#stockManagementSection .filter-tab').forEach(t => t.classList.remove('active'));
    if (element) element.classList.add('active');
    applyStockFilters();
}

function searchStock(query) {
    currentStockSearch = query.toLowerCase().trim();
    applyStockFilters();
}

function applyStockFilters() {
    const rows = document.querySelectorAll('#stockTableBody tr');
    rows.forEach(row => {
        const rowCat = row.getAttribute('data-cat');
        const rowName = row.getAttribute('data-name');
        const catMatch = (currentStockFilter === 'all') || (rowCat === currentStockFilter);
        const searchMatch = !currentStockSearch || rowName.includes(currentStockSearch);
        
        row.style.display = (catMatch && searchMatch) ? '' : 'none';
    });
}

// Toast
function showAdminToast(message, type = 'info') {
    const existing = document.getElementById('adminToast');
    if (existing) existing.remove();
    
    const bgColors = {
        success: 'linear-gradient(135deg, #10b981, #059669)',
        error: 'linear-gradient(135deg, #ef4444, #dc2626)',
        info: 'linear-gradient(135deg, #d4af37, #b8941f)'
    };
    
    const toast = document.createElement('div');
    toast.id = 'adminToast';
    toast.style.cssText = `
        position: fixed;
        top: 30px;
        right: 30px;
        z-index: 999999;
        background: ${bgColors[type] || bgColors.info};
        color: white;
        padding: 16px 26px;
        border-radius: 50px;
        font-weight: 600;
        box-shadow: 0 15px 40px rgba(0,0,0,0.4);
        transform: translateX(150%);
        transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1);
        max-width: 400px;
        font-size: 0.9rem;
    `;
    toast.innerHTML = message;
    document.body.appendChild(toast);
    
    requestAnimationFrame(() => toast.style.transform = 'translateX(0)');
    setTimeout(() => {
        toast.style.transform = 'translateX(150%)';
        setTimeout(() => toast.remove(), 500);
    }, 2800);
}

// ==========================
// HERO BANNER MANAGEMENT
// ==========================
function openHeroBannerModal() {
    document.getElementById('heroBannerForm').reset();
    document.getElementById('heroBannerId').value = '';
    document.getElementById('heroModalTitle').textContent = 'Add Hero Banner';
    document.getElementById('heroImagePreview').style.display = 'none';
    document.getElementById('heroBannerImageFile').required = true;
    document.getElementById('heroBannerModal').classList.add('active');
}

function editHeroBanner(banner) {
    document.getElementById('heroBannerId').value = banner.id;
    document.getElementById('heroBannerTitle').value = banner.title;
    document.getElementById('heroBannerSubtitle').value = banner.subtitle || '';
    document.getElementById('heroBannerBadgeText').value = banner.badge_text || '';
    document.getElementById('heroBannerBadgeColor').value = banner.badge_color || 'bg-danger';
    document.getElementById('heroBannerLinkUrl').value = banner.link_url || '#';
    document.getElementById('heroModalTitle').textContent = 'Edit Hero Banner';
    document.getElementById('heroBannerImageFile').required = false;
    
    const preview = document.getElementById('heroImagePreview');
    if (banner.image_url) {
        preview.src = banner.image_url;
        preview.style.display = 'block';
    }
    document.getElementById('heroBannerModal').classList.add('active');
}

function closeHeroBannerModal() {
    document.getElementById('heroBannerModal').classList.remove('active');
}

function saveHeroBanner(event) {
    event.preventDefault();
    const id = document.getElementById('heroBannerId').value;
    const fileInput = document.getElementById('heroBannerImageFile');
    
    if (!id && (!fileInput.files || fileInput.files.length === 0)) {
        alert('Please select a hero background image!');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', id ? 'update_banner' : 'add_banner');
    if (id) formData.append('id', id);
    formData.append('title', document.getElementById('heroBannerTitle').value);
    formData.append('subtitle', document.getElementById('heroBannerSubtitle').value);
    formData.append('badge_text', document.getElementById('heroBannerBadgeText').value);
    formData.append('badge_color', document.getElementById('heroBannerBadgeColor').value);
    formData.append('link_url', document.getElementById('heroBannerLinkUrl').value);
    formData.append('banner_type', 'hero');
    
    if (fileInput.files.length > 0) {
        formData.append('banner_image', fileInput.files[0]);
    }
    
    fetch('../index.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) { 
            alert('✅ ' + data.message); 
            location.reload(); 
        } else {
            alert('❌ ' + data.message);
        }
    });
}

function previewHeroImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById('heroImagePreview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ==========================
// FLASH BANNER MANAGEMENT
// ==========================
function openFlashBannerModal() {
    document.getElementById('flashBannerForm').reset();
    document.getElementById('flashBannerId').value = '';
    document.getElementById('flashModalTitle').textContent = 'Add Flash Banner';
    document.getElementById('flashImagePreview').style.display = 'none';
    document.getElementById('flashBannerModal').classList.add('active');
}

function editFlashBanner(banner) {
    document.getElementById('flashBannerId').value = banner.id;
    document.getElementById('flashBannerTitle').value = banner.title;
    document.getElementById('flashBannerSubtitle').value = banner.subtitle || '';
    document.getElementById('flashBannerBadgeText').value = banner.badge_text || '';
    document.getElementById('flashBannerBadgeColor').value = banner.badge_color || 'bg-danger';
    document.getElementById('flashBannerLinkUrl').value = banner.link_url || '#';
    document.getElementById('flashModalTitle').textContent = 'Edit Flash Banner';
    
    const preview = document.getElementById('flashImagePreview');
    if (banner.image_url) {
        preview.src = banner.image_url;
        preview.style.display = 'block';
    }
    document.getElementById('flashBannerModal').classList.add('active');
}

function closeFlashBannerModal() {
    document.getElementById('flashBannerModal').classList.remove('active');
}

function saveFlashBanner(event) {
    event.preventDefault();
    const id = document.getElementById('flashBannerId').value;
    const formData = new FormData();
    formData.append('action', id ? 'update_banner' : 'add_banner');
    if (id) formData.append('id', id);
    formData.append('title', document.getElementById('flashBannerTitle').value);
    formData.append('subtitle', document.getElementById('flashBannerSubtitle').value);
    formData.append('badge_text', document.getElementById('flashBannerBadgeText').value);
    formData.append('badge_color', document.getElementById('flashBannerBadgeColor').value);
    formData.append('link_url', document.getElementById('flashBannerLinkUrl').value);
    formData.append('banner_type', 'flash');
    
    const fileInput = document.getElementById('flashBannerImageFile');
    if (fileInput.files.length > 0) {
        formData.append('banner_image', fileInput.files[0]);
    }
    
    fetch('../index.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) { 
            alert('✅ ' + data.message); 
            location.reload(); 
        } else {
            alert('❌ ' + data.message);
        }
    });
}

function previewFlashImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById('flashImagePreview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function deleteBanner(id) {
    if (!confirm('Delete this banner?')) return;
    const formData = new FormData();
    formData.append('action', 'delete_banner');
    formData.append('id', id);
    fetch('../index.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

// ==========================
// PRODUCT MANAGEMENT
// ==========================
function openProductModal() {
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    document.getElementById('productModalTitle').textContent = 'Add New Product';
    document.getElementById('productStock').value = 50;
    document.getElementById('productDiscount').value = 0;
    document.getElementById('productSalePreview').value = 'PKR 0.00';
    document.getElementById('existingImagesContainer').style.display = 'none';
    document.getElementById('newImagesPreview').style.display = 'none';
    document.getElementById('newImagesGrid').innerHTML = '';
    document.getElementById('productModal').classList.add('active');
}

function editProduct(product) {
    document.getElementById('productId').value = product.id;
    document.getElementById('productName').value = product.name;
    document.getElementById('productPrice').value = product.price;
    document.getElementById('productStock').value = product.stock || 0;
    document.getElementById('productDescription').value = product.description || '';
    document.getElementById('productDiscount').value = product.discount_percent || 0;
    updateProductSalePreview();
    document.getElementById('productModalTitle').textContent = 'Edit Product';
    
    const imagesContainer = document.getElementById('existingImagesContainer');
    const imagesGrid = document.getElementById('existingImagesGrid');
    
    let imagesHtml = '';
    
    if (product.image_url) {
        imagesHtml += `
            <div class="image-item">
                <img src="${product.image_url}" alt="">
                <div class="img-primary-badge">MAIN</div>
            </div>
        `;
    }
    
    if (product.images && product.images.length > 0) {
        product.images.forEach(img => {
            imagesHtml += `
                <div class="image-item">
                    <img src="${img.image_url}" alt="">
                    <button class="img-delete" onclick="deleteProductImage(${img.id}, event)" title="Delete">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            `;
        });
    }
    
    imagesGrid.innerHTML = imagesHtml;
    imagesContainer.style.display = (imagesHtml) ? 'block' : 'none';
    
    document.getElementById('newImagesPreview').style.display = 'none';
    document.getElementById('newImagesGrid').innerHTML = '';
    document.getElementById('productModal').classList.add('active');
}

function updateProductSalePreview() {
    const price = parseFloat(document.getElementById('productPrice').value) || 0;
    const discount = parseFloat(document.getElementById('productDiscount').value) || 0;
    const salePrice = price - (price * discount / 100);
    document.getElementById('productSalePreview').value = 'PKR ' + salePrice.toFixed(2);
}

document.getElementById('productPrice')?.addEventListener('input', updateProductSalePreview);
document.getElementById('productDiscount')?.addEventListener('input', updateProductSalePreview);

function closeProductModal() {
    document.getElementById('productModal').classList.remove('active');
}

function previewMultipleImages(input) {
    const files = input.files;
    const preview = document.getElementById('newImagesPreview');
    const grid = document.getElementById('newImagesGrid');
    
    if (!files || files.length === 0) {
        preview.style.display = 'none';
        grid.innerHTML = '';
        return;
    }
    
    let html = '';
    Array.from(files).forEach((file, index) => {
        const url = URL.createObjectURL(file);
        html += `
            <div class="image-item">
                <img src="${url}" alt="">
                ${index === 0 ? '<div class="img-primary-badge">NEW</div>' : ''}
            </div>
        `;
    });
    
    grid.innerHTML = html;
    preview.style.display = 'block';
}

function deleteProductImage(imageId, event) {
    if (event) event.stopPropagation();
    if (!confirm('Delete this image?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_product_image');
    formData.append('image_id', imageId);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAdminToast('✅ Image deleted', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showAdminToast('❌ ' + (data.message || 'Failed'), 'error');
        }
    })
    .catch(() => showAdminToast('❌ Connection error', 'error'));
}

function saveProduct(event) {
    event.preventDefault();
    const id = document.getElementById('productId').value;
    const formData = new FormData();
    formData.append('action', id ? 'update_product' : 'add_product');
    if (id) formData.append('id', id);
    formData.append('name', document.getElementById('productName').value);
    formData.append('price', document.getElementById('productPrice').value);
    formData.append('stock', document.getElementById('productStock').value);
    formData.append('description', document.getElementById('productDescription').value);
    formData.append('discount_percent', document.getElementById('productDiscount').value || 0);
    
    const fileInput = document.getElementById('productImageFiles');
    if (fileInput.files.length > 0) {
        Array.from(fileInput.files).forEach(file => {
            formData.append('product_images[]', file);
        });
    }
    
    fetch('../index.php?multi_images=1', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) { 
            alert('✅ ' + data.message); 
            location.reload(); 
        }
        else alert('❌ ' + data.message);
    })
    .catch(err => {
        console.error(err);
        alert('❌ Connection error');
    });
}

function deleteProduct(id) {
    if (!confirm('Delete this product?')) return;
    const formData = new FormData();
    formData.append('action', 'delete_product');
    formData.append('id', id);
    fetch('../index.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

// ==========================
// THEME TOGGLE
// ==========================
function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('admin_theme', next);
    document.querySelectorAll('.theme-toggle i').forEach(i => {
        i.className = next === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    });
}

function loadTheme() {
    const saved = localStorage.getItem('admin_theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    document.querySelectorAll('.theme-toggle i').forEach(i => {
        i.className = saved === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    });
}

// ==========================
// INIT
// ==========================
document.addEventListener('DOMContentLoaded', function() {
    loadTheme();
    
    // Check if URL has #monthly to auto-open monthly report
    if (window.location.hash === '#monthly') {
        const monthlyLink = document.querySelector('a[onclick*="showMonthlyReportSection"]');
        if (monthlyLink) {
            showMonthlyReportSection(monthlyLink);
        }
    } else {
        loadOrders('all', document.querySelector('.admin-sidebar .nav-link'));
    }
});
</script>
</body>
</html>