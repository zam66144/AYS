<?php
// ==========================
// 1. PHP SESSION & DATABASE CONNECTION
// ==========================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// ==========================
// 2. IMAGE UPLOAD HANDLER (Auto-Resize)
// ==========================
function uploadImage($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload failed.'];
    }
    
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed)) {
        return ['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are allowed.'];
    }
    
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . rand(1000, 9999) . '.' . $ext;
    $targetPath = $uploadDir . $filename;
    
    $maxWidth = 1600;
    $maxHeight = 900;
    $quality = 85;
    
    list($width, $height) = getimagesize($file['tmp_name']);
    
    $source = null;
    switch ($file['type']) {
        case 'image/jpeg': $source = imagecreatefromjpeg($file['tmp_name']); break;
        case 'image/png': $source = imagecreatefrompng($file['tmp_name']); break;
        case 'image/webp': $source = imagecreatefromwebp($file['tmp_name']); break;
    }
    
    if (!$source) {
        return ['success' => false, 'message' => 'Unable to process image.'];
    }
    
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
// 2.5 HANDLE MULTI-IMAGE PRODUCT SAVE
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['multi_images'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_product' || $action === 'update_product') {
        $id = (int)($_POST['id'] ?? 0);
        $name = $_POST['name'] ?? '';
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $description = $_POST['description'] ?? '';
        $discountPercent = (float)($_POST['discount_percent'] ?? 0);
        
        $salePrice = $discountPercent > 0 ? ($price - ($price * $discountPercent / 100)) : null;
        $isOnSale = $discountPercent > 0 ? 1 : 0;
        
        try {
            if ($action === 'add_product') {
                $stmt = $pdo->prepare("INSERT INTO products (name, price, stock, description, discount_percent, sale_price, is_on_sale) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $price, $stock, $description, $discountPercent, $salePrice, $isOnSale]);
                $productId = $pdo->lastInsertId();
            } else {
                $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, stock = ?, description = ?, discount_percent = ?, sale_price = ?, is_on_sale = ? WHERE id = ?");
                $stmt->execute([$name, $price, $stock, $description, $discountPercent, $salePrice, $isOnSale, $id]);
                $productId = $id;
            }
            
            if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
                $files = $_FILES['product_images'];
                $firstImage = null;
                
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                    
                    $singleFile = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];
                    
                    $result = uploadImage($singleFile);
                    if ($result['success']) {
                        $imgStmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, sort_order, is_primary) VALUES (?, ?, ?, ?)");
                        $imgStmt->execute([$productId, $result['path'], $i, $i === 0 ? 1 : 0]);
                        
                        if ($i === 0) $firstImage = $result['path'];
                    }
                }
                
                if ($firstImage) {
                    $checkStmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
                    $checkStmt->execute([$productId]);
                    $currentImage = $checkStmt->fetchColumn();
                    
                    if (empty($currentImage)) {
                        $stmt = $pdo->prepare("UPDATE products SET image_url = ? WHERE id = ?");
                        $stmt->execute([$firstImage, $productId]);
                    }
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Product saved successfully!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
}

// ==========================
// 3. FETCH BANNERS
// ==========================
$heroBannerStmt = $pdo->query("
    SELECT * FROM banners 
    WHERE is_active = 1 
    AND banner_type = 'hero'
    AND image_url IS NOT NULL 
    AND image_url != '' 
    ORDER BY id DESC 
    LIMIT 10
");
$banners = $heroBannerStmt->fetchAll();

$flashBannerStmt = $pdo->query("
    SELECT * FROM banners 
    WHERE is_active = 1 
    AND banner_type = 'flash'
    ORDER BY id DESC 
    LIMIT 10
");
$flashBanners = $flashBannerStmt->fetchAll();

$productStmt = $pdo->query("SELECT id, name, price, image_url, description, stock, discount_percent, sale_price, is_on_sale FROM products ORDER BY id ASC");
$allProducts = $productStmt->fetchAll();

$productImagesMap = [];
try {
    $imgStmt = $pdo->query("SELECT * FROM product_images ORDER BY product_id, sort_order, id");
    while ($img = $imgStmt->fetch()) {
        $productImagesMap[$img['product_id']][] = $img;
    }
} catch (Exception $e) {}

foreach ($allProducts as &$p) {
    $p['images'] = $productImagesMap[$p['id']] ?? [];
    if (!empty($p['discount_percent']) && $p['discount_percent'] > 0) {
        if (empty($p['sale_price'])) {
            $p['sale_price'] = $p['price'] - ($p['price'] * $p['discount_percent'] / 100);
        }
        $p['is_on_sale'] = 1;
    }
}
unset($p);

// ==========================
// 4. AJAX REQUESTS
// ==========================
if (isset($_GET['ajax'])) {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'get_products') {
        $stmt = $pdo->query("SELECT id, name, price, image_url, description, stock, discount_percent, sale_price, is_on_sale FROM products");
        echo json_encode($stmt->fetchAll());
        exit;
    }
    
    if ($action === 'add_to_wishlist') {
        if (!isset($_SESSION['client_id'])) {
            echo json_encode(['success' => false, 'message' => 'Please login first.']);
            exit();
        }
        $client_id = $_SESSION['client_id'];
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        
        if ($product_id > 0) {
            $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE client_id = ? AND product_id = ?");
            $stmt->execute([$client_id, $product_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Product already in wishlist.']);
                exit();
            }
            
            $stmt = $pdo->prepare("INSERT INTO wishlist (client_id, product_id) VALUES (?, ?)");
            if ($stmt->execute([$client_id, $product_id])) {
                echo json_encode(['success' => true, 'message' => 'Added to wishlist!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add to wishlist.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid product.']);
        }
        exit;
    }
    
    if ($action === 'get_client') {
        $client_id = $_SESSION['client_id'] ?? 0;
        if ($client_id) {
            $stmt = $pdo->prepare("SELECT name, email FROM clients WHERE id = ?");
            $stmt->execute([$client_id]);
            echo json_encode($stmt->fetch());
        } else {
            echo json_encode(null);
        }
        exit;
    }
    
    if ($action === 'get_stock') {
        $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
        if ($product_id > 0) {
            $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $stock = $stmt->fetchColumn();
            echo json_encode(['stock' => (int)$stock]);
        } else {
            echo json_encode(['stock' => 0]);
        }
        exit;
    }
    
    if ($action === 'get_product_details') {
        $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
        if ($product_id > 0) {
            $stmt = $pdo->prepare("SELECT id, name, price, image_url, description, stock, discount_percent, sale_price FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            if ($product) {
                echo json_encode(['success' => true, 'product' => $product]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid product']);
        }
        exit;
    }
}

// ==========================
// 5. POST REQUESTS
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'register') { echo json_encode(handleRegistration($pdo)); exit; }
    if ($action === 'login') { echo json_encode(handleLogin($pdo)); exit; }
    if ($action === 'admin_login') { echo json_encode(handleAdminLogin()); exit; }
    if ($action === 'admin_logout') { unset($_SESSION['admin_logged']); echo json_encode(['success' => true]); exit; }

    if ($action === 'add_banner') {
        $title = $_POST['title'] ?? '';
        $subtitle = $_POST['subtitle'] ?? '';
        $badge_text = $_POST['badge_text'] ?? '';
        $badge_color = $_POST['badge_color'] ?? 'bg-danger';
        $link_url = $_POST['link_url'] ?? '#';
        $banner_type = $_POST['banner_type'] ?? 'hero';
        
        $image_url = '';
        if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadImage($_FILES['banner_image']);
            if ($uploadResult['success']) {
                $image_url = $uploadResult['path'];
            } else {
                echo json_encode(['success' => false, 'message' => $uploadResult['message']]);
                exit;
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO banners (title, subtitle, badge_text, badge_color, banner_type, image_url, link_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $subtitle, $badge_text, $badge_color, $banner_type, $image_url, $link_url])) {
            echo json_encode(['success' => true, 'message' => ucfirst($banner_type) . ' banner added successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add banner.']);
        }
        exit;
    }
    
    if ($action === 'update_banner') {
        $id = (int)$_POST['id'] ?? 0;
        $title = $_POST['title'] ?? '';
        $subtitle = $_POST['subtitle'] ?? '';
        $badge_text = $_POST['badge_text'] ?? '';
        $badge_color = $_POST['badge_color'] ?? 'bg-danger';
        $link_url = $_POST['link_url'] ?? '#';
        $banner_type = $_POST['banner_type'] ?? 'hero';
        
        $stmt = $pdo->prepare("SELECT image_url FROM banners WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        $image_url = $existing['image_url'] ?? '';
        
        if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadImage($_FILES['banner_image']);
            if ($uploadResult['success']) {
                $image_url = $uploadResult['path'];
            } else {
                echo json_encode(['success' => false, 'message' => $uploadResult['message']]);
                exit;
            }
        }
        
        $stmt = $pdo->prepare("UPDATE banners SET title = ?, subtitle = ?, badge_text = ?, badge_color = ?, banner_type = ?, image_url = ?, link_url = ? WHERE id = ?");
        if ($stmt->execute([$title, $subtitle, $badge_text, $badge_color, $banner_type, $image_url, $link_url, $id])) {
            echo json_encode(['success' => true, 'message' => 'Banner updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update banner.']);
        }
        exit;
    }
    
    if ($action === 'delete_banner') {
        $id = (int)$_POST['id'] ?? 0;
        
        $stmt = $pdo->prepare("DELETE FROM banners WHERE id = ?");
        if ($stmt->execute([$id])) {
            echo json_encode(['success' => true, 'message' => 'Banner deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete banner.']);
        }
        exit;
    }

    if ($action === 'add_product') {
        $name = $_POST['name'] ?? '';
        $price = $_POST['price'] ?? 0;
        $stock = (int)($_POST['stock'] ?? 50);
        $description = $_POST['description'] ?? '';
        $discountPercent = (float)($_POST['discount_percent'] ?? 0);
        
        $image_url = '';
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadImage($_FILES['product_image']);
            if ($uploadResult['success']) {
                $image_url = $uploadResult['path'];
            } else {
                echo json_encode(['success' => false, 'message' => $uploadResult['message']]);
                exit;
            }
        }
        
        $salePrice = $discountPercent > 0 ? ($price - ($price * $discountPercent / 100)) : null;
        $isOnSale = $discountPercent > 0 ? 1 : 0;
        
        $stmt = $pdo->prepare("INSERT INTO products (name, price, stock, image_url, description, discount_percent, sale_price, is_on_sale) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $price, $stock, $image_url, $description, $discountPercent, $salePrice, $isOnSale])) {
            echo json_encode(['success' => true, 'message' => 'Product added with stock: ' . $stock]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add product.']);
        }
        exit;
    }
    
    if ($action === 'update_product') {
        $id = (int)$_POST['id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $price = $_POST['price'] ?? 0;
        $stock = (int)($_POST['stock'] ?? 0);
        $description = $_POST['description'] ?? '';
        $discountPercent = (float)($_POST['discount_percent'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        $image_url = $existing['image_url'] ?? '';
        
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadImage($_FILES['product_image']);
            if ($uploadResult['success']) {
                $image_url = $uploadResult['path'];
            } else {
                echo json_encode(['success' => false, 'message' => $uploadResult['message']]);
                exit;
            }
        }
        
        $salePrice = $discountPercent > 0 ? ($price - ($price * $discountPercent / 100)) : null;
        $isOnSale = $discountPercent > 0 ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, stock = ?, image_url = ?, description = ?, discount_percent = ?, sale_price = ?, is_on_sale = ? WHERE id = ?");
        if ($stmt->execute([$name, $price, $stock, $image_url, $description, $discountPercent, $salePrice, $isOnSale, $id])) {
            echo json_encode(['success' => true, 'message' => 'Product updated with stock: ' . $stock]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update product.']);
        }
        exit;
    }
    
    if ($action === 'delete_product') {
        $id = (int)$_POST['id'] ?? 0;
        
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        if ($stmt->execute([$id])) {
            echo json_encode(['success' => true, 'message' => 'Product deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete product.']);
        }
        exit;
    }

    if ($action === 'confirm_order') {
        if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
            exit;
        }
        
        $order_id = (int)$_POST['order_id'] ?? 0;
        
        if ($order_id > 0) {
            try {
                $stmt = $pdo->prepare("SELECT product_id, quantity, status FROM orders WHERE id = ?");
                $stmt->execute([$order_id]);
                $order = $stmt->fetch();
                
                if (!$order) {
                    echo json_encode(['success' => false, 'message' => 'Order not found.']);
                    exit;
                }
                
                if ($order['status'] === 'processed') {
                    echo json_encode(['success' => false, 'message' => 'Order already processed.']);
                    exit;
                }
                
                $stmt = $pdo->prepare("UPDATE orders SET status = 'processed' WHERE id = ?");
                $stmt->execute([$order_id]);
                
                if ($order['product_id'] > 0) {
                    $stmt = $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?");
                    $stmt->execute([$order['quantity'], $order['product_id']]);
                }
                
                echo json_encode(['success' => true, 'message' => 'Order confirmed & stock updated!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid order ID.']);
        }
        exit;
    }
}

$loggedInName = isset($_SESSION['client_name']) ? $_SESSION['client_name'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AYS · Premium Luxury E-commerce Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;600;700;800;900&family=Noto+Nastaliq+Urdu:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        :root {
            --primary-dark: #0a192f;
            --primary-light: #112240;
            --accent-gold: #d4af37;
            --accent-gold-hover: #f7d26b;
            --light-bg: #f4f6f9;
            --white: #ffffff;
            --text-muted: #8892b0;
            --text-light: #a0b8cc;
            --danger: #ef4444;
            --success: #10b981;
            --radius: 12px;
            --radius-pill: 50px;
            --shadow-sm: 0 2px 8px rgba(10, 25, 47, 0.08);
            --shadow-lg: 0 15px 40px rgba(10, 25, 47, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; margin: 0; padding: 0; }
        body {
            background-color: var(--light-bg);
            font-family: 'Inter', sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: background-color 0.4s ease;
        }
        .gold-text { color: var(--accent-gold) !important; }
        .main-content { flex: 1; }
        .container { max-width: 1700px !important; width: 100%; padding-left: 20px; padding-right: 20px; margin-left: auto; margin-right: auto; }

        .brand-gradient {
            background-color: var(--primary-dark);
            padding: 16px 0;
            border-bottom: 3px solid var(--accent-gold);
            position: sticky;
            top: 0;
            z-index: 1000;
            height:110px;
        }
        .search-bar {
            background: rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-pill);
            padding: 4px 4px 4px 20px;
            display: flex;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .search-bar:focus-within {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.12);
        }
        .search-bar input {
            border: none; background: transparent; color: #ffffff;
            width: 100%; padding: 10px 0; font-size: 0.9rem; outline: none;
        }
        .search-bar input::placeholder { color: rgba(255, 255, 255, 0.5); }
        .search-bar button {
            background: var(--accent-gold); border: none; border-radius: var(--radius-pill);
            padding: 8px 20px; color: var(--primary-dark); font-weight: 600; cursor: pointer; transition: all 0.3s ease;
        }
        .search-bar button:hover { background: var(--accent-gold-hover); transform: scale(1.02); }
        .header-icons {
            display: flex; align-items: center; justify-content: flex-end; gap: 12px;
        }
        .header-icons .icon-btn {
            color: rgba(255, 255, 255, 0.8); text-decoration: none; font-size: 0.75rem;
            display: flex; flex-direction: column; align-items: center; transition: all 0.3s ease;
        }
        .header-icons .icon-btn:hover { color: var(--accent-gold); }
        .header-icons .icon-btn i { font-size: 1.4rem; margin-bottom: 2px; }
        #clientGreeting {
            font-size: 0.85rem; font-weight: 500; background: rgba(212, 175, 55, 0.15);
            padding: 4px 14px; border-radius: var(--radius-pill); border: 1px solid rgba(212, 175, 55, 0.15);
            color: #fff;
        }
        .category-nav { background: #ffffff; border-bottom: 1px solid #e4e7ed; padding: 12px 0; transition: background-color 0.4s ease, border-color 0.4s ease; }
        .category-link {
            color: #6b7a8f; text-decoration: none; font-size: 0.9rem; font-weight: 500;
            padding: 6px 18px; border-radius: var(--radius-pill); transition: all 0.3s ease;
            cursor: pointer; user-select: none;
        }
        .category-link:hover { color: var(--accent-gold); background: rgba(212, 175, 55, 0.08); }
        .category-link.active { background: var(--accent-gold); color: var(--primary-dark); font-weight: 600; }
        .btn-gold { background: var(--accent-gold); border: none; color: var(--primary-dark); font-weight: 700; padding: 12px 20px; border-radius: var(--radius-pill); transition: all 0.3s ease; cursor: pointer; }
        .btn-gold:hover { background: var(--accent-gold-hover); }

        /* HERO SECTION */
        .hero-section {
            background: linear-gradient(135deg, #0a192f 0%, #112240 100%);
            padding: 80px 0 100px;
            border-bottom: 3px solid var(--accent-gold);
            position: relative;
            overflow: hidden;
            min-height: 520px;
        }
        .hero-banner-bg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; }
        .hero-banner-slide {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-size: cover; background-position: center; background-repeat: no-repeat;
            opacity: 0; transition: opacity 1.2s ease-in-out, transform 8s ease-in-out;
            transform: scale(1);
        }
        .hero-banner-slide.active { opacity: 1; transform: scale(1.08); }
        .hero-banner-slide::after {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, rgba(10, 25, 47, 0.88) 0%, rgba(10, 25, 47, 0.72) 40%, rgba(10, 25, 47, 0.55) 100%);
        }
        .hero-banner-slide .banner-caption {
            position: absolute; top: 30px; right: 40px; z-index: 5; text-align: right;
            animation: bannerCaptionFade 1s ease both;
        }
        @keyframes bannerCaptionFade { from { opacity: 0; transform: translateY(-15px); } to { opacity: 1; transform: translateY(0); } }
        .hero-banner-slide .banner-badge {
            display: inline-block; padding: 8px 22px; border-radius: 50px;
            font-size: 0.75rem; font-weight: 800; letter-spacing: 2px;
            text-transform: uppercase; color: #fff;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            animation: badgePulse 2s ease-in-out infinite;
        }
        @keyframes badgePulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }
        .hero-banner-slide .banner-title {
            display: block; margin-top: 10px; color: #fff;
            font-size: 1rem; font-weight: 600; text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }
        .hero-banner-dots {
            position: absolute; bottom: 25px; left: 50%; transform: translateX(-50%);
            display: flex; gap: 10px; z-index: 10;
        }
        .hero-banner-dots .dot {
            width: 12px; height: 12px; border-radius: 50%;
            background: rgba(255, 255, 255, 0.4); cursor: pointer;
            transition: all 0.3s ease; border: 2px solid transparent;
        }
        .hero-banner-dots .dot.active {
            background: var(--accent-gold); transform: scale(1.3);
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.7);
        }
        .hero-banner-dots .dot:hover { background: var(--accent-gold-hover); transform: scale(1.2); }
        .hero-section .container { position: relative; z-index: 5; }
        .hero-section .hero-content h1 { font-size: 3.5rem; font-weight: 800; line-height: 1.1; }
        .hero-section .hero-content h1 .highlight { color: var(--accent-gold); position: relative; }
        .hero-section .hero-content h1 .highlight::after {
            content: ''; position: absolute; bottom: 2px; left: 0; width: 100%; height: 4px;
            background: var(--accent-gold); border-radius: 4px;
        }
        .hero-section .hero-content p { color: rgba(255, 255, 255, 0.7); font-size: 1.1rem; max-width: 480px; }
        .hero-section .hero-image img { max-height: 360px; width: auto; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); animation: floatImage 6s ease-in-out infinite; }
        @keyframes floatImage { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-12px); } }
        .btn-outline-gold { background: transparent; border: 2px solid var(--accent-gold); color: #fff; font-weight: 600; padding: 12px 28px; border-radius: var(--radius-pill); transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
        .btn-outline-gold:hover { background: var(--accent-gold); color: var(--primary-dark); transform: translateY(-2px); }

        .flash-sale { background: var(--white); border-radius: var(--radius); padding: 20px 30px; box-shadow: var(--shadow-lg); margin-top: -30px; position: relative; z-index: 10; border-left: 5px solid #ff4757; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 15px; transition: background-color 0.4s ease; }
        .flash-sale .timer { display: flex; gap: 6px; justify-content: center; }
        .flash-sale .timer .time-block { background: var(--primary-dark); color: #fff; padding: 5px 12px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; text-align: center; min-width: 44px; }
        .flash-sale .timer .time-block small { display: block; font-size: 0.5rem; font-weight: 400; opacity: 0.6; }
        .flash-sale .banner-item { display: flex; align-items: center; gap: 10px; padding: 5px 15px; border-radius: 50px; background: #f8f9fa; transition: all 0.3s ease; text-decoration: none; color: #333; }
        .flash-sale .banner-item:hover { background: rgba(212, 175, 55, 0.1); transform: scale(1.02); }
        .flash-sale .banner-item img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .flash-sale .banner-item .badge-text { font-weight: 700; font-size: 0.8rem; }

        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 12px; border-bottom: 2px solid #e4e7ed; }
        .section-header h2 { font-size: 1.5rem; font-weight: 700; margin: 0; color: var(--primary-dark); }
        .section-header .view-all { color: var(--accent-gold); text-decoration: none; font-weight: 600; font-size: 0.85rem; transition: all 0.3s ease; display: flex; align-items: center; gap: 4px; }
        .section-header .view-all:hover { gap: 8px; }
        .product-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px; }
        
        /* PRODUCT CARD WITH SLIDER */
        .product-card { 
            background: var(--white); border-radius: var(--radius); overflow: hidden; 
            transition: all 0.3s ease; border: 1px solid #e4e7ed; 
            box-shadow: var(--shadow-sm); cursor: pointer; position: relative; 
            animation: cardFadeIn 0.4s ease; 
        }
        .product-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--accent-gold); }
        
        .product-image-slider {
            position: relative;
            height: 180px;
            width: 100%;
            overflow: hidden;
            background: #f4f6f9;
        }
        .product-slider-track {
            display: flex;
            height: 100%;
            transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1);
            will-change: transform;
        }
        .product-slider-track img {
            min-width: 100%;
            height: 100%;
            object-fit: cover;
            flex-shrink: 0;
            user-select: none;
            -webkit-user-drag: none;
        }
        
        .slider-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.9);
            color: var(--primary-dark);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.85rem;
            z-index: 4;
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            pointer-events: auto;
        }
        .product-card:hover .slider-arrow { opacity: 1; }
        .slider-arrow:hover {
            background: var(--accent-gold);
            color: var(--primary-dark);
            transform: translateY(-50%) scale(1.1);
        }
        .slider-arrow.prev { left: 6px; }
        .slider-arrow.next { right: 6px; }
        
        .slider-dots {
            position: absolute;
            bottom: 8px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 5px;
            z-index: 4;
            background: rgba(10, 25, 47, 0.5);
            padding: 4px 8px;
            border-radius: 20px;
            backdrop-filter: blur(4px);
        }
        .slider-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            padding: 0;
        }
        .slider-dot.active {
            background: var(--accent-gold);
            width: 16px;
            border-radius: 4px;
        }
        
        .multi-image-badge {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(10, 25, 47, 0.85);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 700;
            z-index: 4;
            display: flex;
            align-items: center;
            gap: 3px;
            backdrop-filter: blur(4px);
        }
        
        .discount-badge-card {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            z-index: 5;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.5);
            animation: pulseDiscountBadge 2s ease-in-out infinite;
            display: flex;
            align-items: center;
            gap: 3px;
        }
        @keyframes pulseDiscountBadge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }
        
        .product-card .product-body { padding: 14px; text-align: center; }
        .product-card .product-rating { color: #f39c12; font-size: 0.75rem; margin-bottom: 4px; }
        .product-card .product-title { font-size: 0.85rem; font-weight: 600; min-height: 38px; }
        
        .product-card .product-price-area {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 8px;
            min-height: 28px;
        }
        .product-card .price-sale {
            font-size: 1rem;
            font-weight: 800;
            color: var(--danger);
        }
        .product-card .price-original {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-decoration: line-through;
        }
        .product-card .price-normal {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-dark);
        }
        
        .product-card .product-actions { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .product-card.hidden { display: none !important; }
        
        .stock-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            z-index: 5;
            display: flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stock-badge.in-stock { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .stock-badge.low-stock { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; animation: pulseLow 2s ease-in-out infinite; }
        .stock-badge.out-of-stock { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        @keyframes pulseLow {
            0%, 100% { box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4); }
            50% { box-shadow: 0 4px 20px rgba(245, 158, 11, 0.8); }
        }
        
        .product-card.sold-out .product-image-slider { filter: grayscale(80%) brightness(0.7); }
        .product-card.sold-out .product-body { opacity: 0.7; }
        .product-card.sold-out .product-actions .btn-gold,
        .product-card.sold-out .product-actions .btn-outline-gold {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .stock-info {
            font-size: 0.72rem;
            color: #059669;
            font-weight: 600;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        .stock-info.low { color: #d97706; }
        .stock-info.out { color: #dc2626; }
        
        @keyframes cardFadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        .products-wrapper { transition: opacity 0.3s ease; }
        .no-results { text-align: center; padding: 60px 20px; color: var(--text-muted); display: none; }
        .no-results.show { display: block; }
        .no-results i { font-size: 4rem; color: var(--accent-gold); margin-bottom: 15px; display: block; }
        .no-results h4 { font-weight: 700; color: var(--primary-dark); }

        .search-clear {
            background: transparent; border: none; color: rgba(255,255,255,0.5);
            cursor: pointer; padding: 5px 10px; font-size: 1.1rem;
            transition: 0.3s; display: none;
        }
        .search-clear.show { display: block; }
        .search-clear:hover { color: var(--accent-gold); }

        .search-info {
            display: none; padding: 12px 20px;
            background: rgba(212, 175, 55, 0.08);
            border-left: 4px solid var(--accent-gold);
            border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;
            color: var(--primary-dark); align-items: center;
            justify-content: space-between; flex-wrap: wrap; gap: 10px;
        }
        .search-info.show { display: flex; }
        .search-info strong { color: var(--accent-gold); }

        /* CART */
        .cart-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9998; display: none; backdrop-filter: blur(4px); }
        .cart-overlay.active { display: block; }
        .cart-sidebar { position: fixed; top: 0; right: -450px; width: 400px; height: 100%; background: var(--white); z-index: 9999; transition: all 0.3s ease; box-shadow: -10px 0 30px rgba(0,0,0,0.1); display: flex; flex-direction: column; }
        .cart-sidebar.active { right: 0; }
        .cart-header { padding: 20px; border-bottom: 1px solid #e4e7ed; display: flex; justify-content: space-between; align-items: center; }
        .cart-header h4 { font-weight: 700; margin: 0; }
        .cart-close { cursor: pointer; font-size: 1.5rem; transition: 0.3s; }
        .cart-close:hover { color: var(--accent-gold); }
        .cart-items { flex: 1; overflow-y: auto; padding: 20px; }
        .cart-item { display: flex; align-items: center; gap: 15px; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .cart-item img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
        .cart-item .item-details { flex: 1; }
        .cart-item .item-details h6 { font-size: 0.9rem; font-weight: 600; margin: 0; }
        .cart-item .item-details p { font-size: 0.85rem; color: var(--accent-gold); font-weight: 700; margin: 0; }
        .cart-item .item-actions { display: flex; align-items: center; gap: 10px; }
        .cart-item .item-actions button { background: none; border: none; cursor: pointer; font-size: 1rem; transition: 0.3s; }
        .cart-item .item-actions button:hover { color: #dc3545; }
        .cart-footer { padding: 20px; border-top: 1px solid #e4e7ed; }
        .cart-footer .total-row { display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: 700; margin-bottom: 10px; }
        .cart-footer .checkout-btn { width: 100%; }

        #scrollToTop {
            position: fixed; bottom: 30px; right: 100px; width: 50px; height: 50px;
            background: var(--accent-gold); color: var(--primary-dark);
            border: none; border-radius: 50%; font-size: 1.5rem; cursor: pointer;
            display: none; align-items: center; justify-content: center;
            transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
            z-index: 9997;
        }
        #scrollToTop:hover {
            background: var(--accent-gold-hover);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.4);
        }

        /* CHAT */
        .chat-button {
            position: fixed; bottom: 30px; right: 30px;
            width: 60px; height: 60px;
            background: var(--accent-gold); color: var(--primary-dark);
            border: none; border-radius: 50%; font-size: 1.8rem; cursor: pointer;
            box-shadow: 0 4px 20px rgba(212, 175, 55, 0.4);
            z-index: 9999; transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center;
        }
        .chat-button:hover { transform: scale(1.08); background: var(--accent-gold-hover); }
        .chat-button .notification-dot {
            position: absolute; top: -2px; right: -2px;
            width: 12px; height: 12px; background: #ff4757;
            border-radius: 50%; border: 2px solid var(--white);
            animation: pulse 2s infinite;
        }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.2); } 100% { transform: scale(1); } }

        .chat-window {
            position: fixed; bottom: 100px; right: 30px;
            width: 410px; height: 580px;
            background: var(--white); border-radius: var(--radius);
            box-shadow: var(--shadow-lg); border: 1px solid var(--accent-gold);
            z-index: 9998; display: none; flex-direction: column;
            overflow: hidden; animation: slideUp 0.3s ease;
            transition: background-color 0.4s ease, border-color 0.4s ease;
        }
        .chat-window.open { display: flex; }

        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .chat-header {
            background: var(--primary-dark); color: #fff;
            padding: 15px 20px; display: flex;
            justify-content: space-between; align-items: center;
            border-bottom: 2px solid var(--accent-gold);
            transition: background-color 0.4s ease;
        }
        .chat-header .bot-info { display: flex; align-items: center; gap: 10px; }
        .chat-header .bot-info .avatar {
            width: 40px; height: 40px; background: var(--accent-gold); color: var(--primary-dark);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1.2rem; position: relative;
        }
        .chat-header .bot-info .avatar::after {
            content: ''; position: absolute; bottom: 0; right: 0;
            width: 10px; height: 10px; background: #22c55e;
            border-radius: 50%; border: 2px solid var(--primary-dark);
        }
        .chat-header .bot-info h6 { margin: 0; font-weight: 700; font-size: 0.9rem; }
        .chat-header .bot-info small { color: rgba(255,255,255,0.6); font-size: 0.68rem; display: flex; align-items: center; gap: 4px; }
        
        .chat-header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .theme-toggle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(212, 175, 55, 0.3);
            color: var(--accent-gold);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .theme-toggle:hover {
            background: var(--accent-gold);
            color: var(--primary-dark);
            transform: rotate(20deg) scale(1.1);
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.5);
        }
        
        .theme-toggle i { transition: transform 0.4s ease; }
        .theme-toggle:hover i { transform: rotate(-20deg); }
        
        .chat-header .close-chat { cursor: pointer; font-size: 1.2rem; transition: 0.3s; padding: 4px; }
        .chat-header .close-chat:hover { color: var(--accent-gold); transform: rotate(90deg); }
        
        .lang-indicator {
            font-size: 0.6rem;
            background: rgba(212, 175, 55, 0.2);
            color: var(--accent-gold);
            padding: 2px 7px;
            border-radius: 50px;
            margin-left: 4px;
            border: 1px solid rgba(212, 175, 55, 0.3);
            font-weight: 600;
        }

        .chat-body {
            flex: 1; padding: 15px; overflow-y: auto;
            background: #f9fafb; display: flex;
            flex-direction: column; gap: 10px;
            transition: background-color 0.4s ease;
        }
        .chat-body .message {
            padding: 10px 15px; border-radius: 12px;
            max-width: 85%; font-size: 0.9rem;
            line-height: 1.5; animation: fadeIn 0.3s ease;
            word-wrap: break-word;
        }
        .chat-body .message.urdu {
            font-family: 'Noto Nastaliq Urdu', 'Inter', sans-serif;
            direction: rtl; text-align: right;
            font-size: 1rem; line-height: 2;
        }
        .chat-body .message.roman-urdu {
            font-style: italic;
            background: linear-gradient(135deg, #112240, #0a192f);
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        .chat-body .message.bot {
            background: var(--primary-dark); color: #fff;
            align-self: flex-start; border-bottom-left-radius: 2px;
        }
        .chat-body .message.user {
            background: var(--accent-gold); color: var(--primary-dark);
            align-self: flex-end; border-bottom-right-radius: 2px;
        }
        .chat-body .typing-dots {
            display: flex; gap: 4px; align-self: flex-start;
            padding: 8px 12px; background: var(--primary-dark);
            border-radius: 12px; border-bottom-left-radius: 2px;
        }
        .chat-body .typing-dots span {
            width: 8px; height: 8px; background: #fff; border-radius: 50%;
            animation: typing 1.4s infinite;
        }
        .chat-body .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .chat-body .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing { 0%, 60%, 100% { transform: translateY(0); opacity: 0.4; } 30% { transform: translateY(-5px); opacity: 1; } }

        .chat-body .quick-questions {
            display: flex; flex-wrap: wrap; gap: 6px; margin-top: 5px;
        }
        .chat-body .quick-questions button {
            background: transparent; border: 1px solid var(--accent-gold);
            color: var(--primary-dark); padding: 5px 12px;
            border-radius: 50px; font-size: 0.7rem;
            cursor: pointer; transition: 0.3s; font-family: inherit;
        }
        .chat-body .quick-questions button:hover { background: var(--accent-gold); color: var(--primary-dark); }
        .chat-body .quick-questions button.urdu-btn {
            font-family: 'Noto Nastaliq Urdu', 'Inter', sans-serif;
            font-size: 0.8rem; line-height: 1.8;
        }
        .chat-body .quick-questions button.roman-btn {
            font-style: italic;
            border-color: #22c55e;
            color: #22c55e;
        }
        .chat-body .quick-questions button.roman-btn:hover {
            background: #22c55e;
            color: white;
        }
        .chat-body .quick-questions button.greet-btn {
            border-color: #8b5cf6;
            color: #8b5cf6;
        }
        .chat-body .quick-questions button.greet-btn:hover {
            background: #8b5cf6;
            color: white;
        }

        .chat-footer {
            padding: 12px 15px; border-top: 1px solid #e4e7ed;
            display: flex; gap: 10px; background: var(--white);
            align-items: center;
            transition: background-color 0.4s ease, border-color 0.4s ease;
        }
        .chat-footer input {
            flex: 1; border: 2px solid #e4e7ed;
            border-radius: 50px; padding: 10px 15px;
            outline: none; font-size: 0.9rem; font-family: inherit;
            transition: all 0.3s ease;
        }
        .chat-footer input:focus { border-color: var(--accent-gold); }
        .chat-footer button {
            background: var(--accent-gold); border: none;
            border-radius: 50px; padding: 10px 20px;
            color: var(--primary-dark); font-weight: 600;
            cursor: pointer; transition: 0.3s;
        }
        .chat-footer button:hover { background: var(--accent-gold-hover); }

        .chat-window.dark-theme {
            background: #0a0f1a;
            border-color: rgba(212, 175, 55, 0.5);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.7), 0 0 40px rgba(212, 175, 55, 0.15);
        }
        .chat-window.dark-theme .chat-header {
            background: #050a13;
            border-bottom-color: rgba(212, 175, 55, 0.5);
        }
        .chat-window.dark-theme .chat-body { background: #0d1420; }
        .chat-window.dark-theme .chat-body .message.bot {
            background: #1a2332;
            border: 1px solid rgba(212, 175, 55, 0.15);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        .chat-window.dark-theme .chat-body .message.user {
            background: linear-gradient(135deg, var(--accent-gold), #b8941f);
            box-shadow: 0 2px 12px rgba(212, 175, 55, 0.3);
        }
        .chat-window.dark-theme .chat-body .message.roman-urdu {
            background: linear-gradient(135deg, #0d1a2d, #050a13);
            border: 1px solid rgba(212, 175, 55, 0.2);
        }
        .chat-window.dark-theme .chat-body .typing-dots {
            background: #1a2332;
            border: 1px solid rgba(212, 175, 55, 0.15);
        }
        .chat-window.dark-theme .chat-footer {
            background: #050a13;
            border-top-color: rgba(212, 175, 55, 0.2);
        }
        .chat-window.dark-theme .chat-footer input {
            background: #0d1420;
            border-color: rgba(212, 175, 55, 0.3);
            color: #fff;
        }
        .chat-window.dark-theme .chat-footer input::placeholder { color: rgba(255, 255, 255, 0.4); }
        .chat-window.dark-theme .chat-footer input:focus {
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
        }
        .chat-window.dark-theme .quick-questions button {
            color: rgba(255, 255, 255, 0.75);
            border-color: rgba(212, 175, 55, 0.4);
        }
        .chat-window.dark-theme .quick-questions button:hover { color: var(--primary-dark); }
        .chat-window.dark-theme .quick-questions button.roman-btn {
            color: #4ade80;
            border-color: #4ade80;
        }
        .chat-window.dark-theme .quick-questions button.greet-btn {
            color: #a78bfa;
            border-color: #a78bfa;
        }

        .chat-window, .chat-header, .chat-body, .chat-footer {
            transition: background-color 0.4s ease, border-color 0.4s ease, color 0.4s ease;
        }

        .footer {
            background: var(--primary-dark); color: var(--text-muted);
            border-top: 4px solid var(--accent-gold);
            padding: 50px 0 25px; position: relative; margin-top: 0;
        }
        .footer h6 { color: #fff; font-weight: 700; margin-bottom: 20px; font-size: 1.1rem; letter-spacing: 0.5px; }
        .footer ul { padding-left: 0; }
        .footer ul li { list-style: none; margin-bottom: 10px; }
        .footer ul li a {
            color: var(--text-muted); text-decoration: none;
            transition: all 0.3s ease; display: inline-flex;
            align-items: center; gap: 6px; font-size: 0.9rem;
        }
        .footer ul li a:hover { color: var(--accent-gold); transform: translateX(4px); }
        .footer .brand-desc { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 15px; line-height: 1.6; }
        .footer .social-links { display: flex; gap: 12px; margin-top: 10px; }
        .footer .social-links a {
            display: inline-flex; align-items: center; justify-content: center;
            width: 42px; height: 42px; background: rgba(255,255,255,0.05);
            border-radius: 50%; color: var(--text-light); font-size: 1.2rem;
            transition: all 0.3s ease; text-decoration: none;
        }
        .footer .social-links a:hover {
            background: var(--accent-gold); color: var(--primary-dark); transform: translateY(-4px);
        }
        .footer .footer-divider { border-top: 1px solid rgba(255,255,255,0.06); margin: 30px 0 20px; }
        .footer .copyright { text-align: center; font-size: 1rem; color: var(--text-muted); }
        .footer .copyright i { color: var(--accent-gold); margin-right: 4px; }
        .footer .back-to-top {
            position: absolute; right: 30px; top: -20px;
            background: var(--accent-gold); color: var(--primary-dark);
            width: 44px; height: 44px; border-radius: 50%;
            border: none; font-size: 1.2rem; cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
            display: flex; align-items: center; justify-content: center;
        }
        .footer .back-to-top:hover { background: #f7d26b; transform: translateY(-4px); }

        /* QUICK VIEW MODAL */
        .qv-modal-overlay {
            position: fixed;
            inset: 0;
            background: radial-gradient(ellipse at center, rgba(10, 25, 47, 0.92) 0%, rgba(0, 0, 0, 0.97) 100%);
            backdrop-filter: blur(20px) saturate(140%);
            -webkit-backdrop-filter: blur(20px) saturate(140%);
            z-index: 100000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.5s cubic-bezier(0.22, 1, 0.36, 1);
            overflow: hidden;
        }
        .qv-modal-overlay.active { display: flex; opacity: 1; }
        
        .qv-bg-particles {
            position: absolute; inset: 0;
            pointer-events: none; overflow: hidden;
        }
        .qv-bg-particles span {
            position: absolute; width: 4px; height: 4px;
            background: var(--accent-gold); border-radius: 50%; opacity: 0;
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.9);
            animation: qvParticleFloat 8s ease-in-out infinite;
        }
        @keyframes qvParticleFloat {
            0% { opacity: 0; transform: translateY(100vh) scale(0); }
            10% { opacity: 1; transform: translateY(90vh) scale(1); }
            90% { opacity: 0.6; }
            100% { opacity: 0; transform: translateY(-10vh) scale(0.5); }
        }
        .qv-bg-particles span:nth-child(1) { left: 5%; animation-delay: 0s; }
        .qv-bg-particles span:nth-child(2) { left: 15%; animation-delay: -1.5s; width: 3px; height: 3px; }
        .qv-bg-particles span:nth-child(3) { left: 28%; animation-delay: -3s; width: 5px; height: 5px; }
        .qv-bg-particles span:nth-child(4) { left: 42%; animation-delay: -4.5s; }
        .qv-bg-particles span:nth-child(5) { left: 55%; animation-delay: -2s; width: 3px; height: 3px; }
        .qv-bg-particles span:nth-child(6) { left: 68%; animation-delay: -6s; width: 6px; height: 6px; }
        .qv-bg-particles span:nth-child(7) { left: 82%; animation-delay: -5s; }
        .qv-bg-particles span:nth-child(8) { left: 92%; animation-delay: -7s; width: 4px; height: 4px; }
        
        .qv-glow-orb {
            position: absolute; border-radius: 50%;
            filter: blur(80px); opacity: 0.5;
            pointer-events: none;
            animation: qvOrbFloat 15s ease-in-out infinite;
        }
        .qv-glow-orb.orb-1 {
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.4), transparent 70%);
            top: -15%; left: -10%;
        }
        .qv-glow-orb.orb-2 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(100, 130, 200, 0.3), transparent 70%);
            bottom: -20%; right: -15%;
            animation-delay: -7s;
        }
        @keyframes qvOrbFloat {
            0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.5; }
            33% { transform: translate(50px, -40px) scale(1.15); opacity: 0.7; }
            66% { transform: translate(-40px, 50px) scale(0.9); opacity: 0.4; }
        }
        
        .qv-modal {
            background: linear-gradient(145deg, #ffffff 0%, #fdfbf7 50%, #f8f6f1 100%);
            border-radius: 28px;
            max-width: 1000px;
            width: 100%;
            max-height: 92vh;
            overflow: hidden;
            box-shadow: 
                0 40px 100px rgba(0, 0, 0, 0.6),
                0 0 0 1px rgba(212, 175, 55, 0.3),
                0 0 80px rgba(212, 175, 55, 0.2);
            display: flex;
            flex-direction: column;
            transform: scale(0.7) translateY(60px) rotateX(-5deg);
            transition: transform 0.7s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 5;
        }
        
        .qv-modal-overlay.active .qv-modal {
            transform: scale(1) translateY(0) rotateX(0);
        }
        
        .qv-close-btn {
            position: absolute;
            top: 20px; right: 20px;
            width: 44px; height: 44px;
            background: linear-gradient(135deg, var(--primary-dark), #1a2c4a);
            color: #fff;
            border: 2px solid rgba(212, 175, 55, 0.4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 100;
            box-shadow: 0 10px 30px rgba(10, 25, 47, 0.3);
        }
        .qv-close-btn:hover {
            background: linear-gradient(135deg, var(--accent-gold), #b8941f);
            color: var(--primary-dark);
            transform: rotate(180deg) scale(1.15);
        }
        
        .qv-modal-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow-y: auto;
            max-height: 92vh;
        }
        
        .qv-image-side {
            background: 
                radial-gradient(circle at 30% 30%, rgba(212, 175, 55, 0.15), transparent 60%),
                linear-gradient(145deg, #0a192f 0%, #1a2c4a 50%, #0a192f 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 50px 40px;
            position: relative;
            overflow: hidden;
            min-height: 540px;
        }
        
        .qv-slider-wrapper {
            position: relative;
            z-index: 3;
            padding: 20px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(212, 175, 55, 0.3);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 100%;
        }
        .qv-slider-main {
            position: relative;
            width: 100%;
            height: 380px;
            border-radius: 16px;
            overflow: hidden;
            background: #0a192f;
        }
        .qv-slider-track {
            display: flex;
            height: 100%;
            transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .qv-slider-track img {
            min-width: 100%;
            height: 100%;
            object-fit: contain;
            background: #0a192f;
            user-select: none;
            -webkit-user-drag: none;
        }
        
        .qv-nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            color: white;
            border: 2px solid rgba(212, 175, 55, 0.5);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            z-index: 10;
        }
        .qv-nav-arrow:hover {
            background: var(--accent-gold);
            color: var(--primary-dark);
            transform: translateY(-50%) scale(1.15);
        }
        .qv-nav-arrow.prev { left: 15px; }
        .qv-nav-arrow.next { right: 15px; }
        
        .qv-thumbnails {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .qv-thumb {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid rgba(212, 175, 55, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        .qv-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .qv-thumb:hover { border-color: var(--accent-gold); transform: scale(1.08); }
        .qv-thumb.active {
            border-color: var(--accent-gold);
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.7);
        }
        
        .qv-image-counter {
            position: absolute;
            top: 30px;
            right: 30px;
            background: rgba(10, 25, 47, 0.85);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            z-index: 10;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(212, 175, 55, 0.4);
        }
        
        .qv-info-side {
            padding: 50px 45px;
            display: flex;
            flex-direction: column;
            background: radial-gradient(circle at 100% 0%, rgba(212, 175, 55, 0.05), transparent 40%),
                        linear-gradient(180deg, #ffffff 0%, #fdfbf7 100%);
        }
        
        .qv-category {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            background: linear-gradient(135deg, var(--accent-gold), #b8941f);
            color: var(--primary-dark);
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 20px;
            align-self: flex-start;
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.4);
        }
        
        .qv-product-name {
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--primary-dark);
            line-height: 1.15;
            margin-bottom: 18px;
            letter-spacing: -1px;
        }
        
        .qv-price-section {
            padding: 24px 0;
            border-top: 1px dashed rgba(212, 175, 55, 0.4);
            border-bottom: 1px dashed rgba(212, 175, 55, 0.4);
            margin-bottom: 24px;
            display: flex;
            align-items: baseline;
            gap: 16px;
            flex-wrap: wrap;
        }
        .qv-price-current {
            font-size: 2.8rem;
            font-weight: 900;
            line-height: 1;
            display: flex;
            align-items: flex-start;
            gap: 6px;
            letter-spacing: -2px;
            color: var(--primary-dark);
        }
        .qv-price-current.on-sale { color: var(--danger); }
        .qv-price-current .currency {
            font-size: 1.4rem;
            color: var(--accent-gold);
            font-weight: 800;
            margin-top: 6px;
        }
        .qv-price-original {
            font-size: 1.2rem;
            text-decoration: line-through;
            color: var(--text-muted);
            font-weight: 600;
        }
        .qv-discount-tag {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 1px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            animation: pulseDiscountBadge 2s ease-in-out infinite;
        }
        
        .qv-stock-section {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 18px 22px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 18px;
            margin-bottom: 24px;
            border: 1px solid rgba(212, 175, 55, 0.15);
            position: relative;
        }
        .qv-stock-section::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent-gold);
        }
        .qv-stock-icon {
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
        .qv-stock-icon.in-stock { background: linear-gradient(135deg, #10b981, #059669); }
        .qv-stock-icon.low-stock { background: linear-gradient(135deg, #f59e0b, #d97706); animation: qvStockPulse 2s ease-in-out infinite; }
        .qv-stock-icon.out-of-stock { background: linear-gradient(135deg, #ef4444, #dc2626); }
        @keyframes qvStockPulse {
            0%, 100% { box-shadow: 0 8px 25px rgba(245, 158, 11, 0.35); }
            50% { box-shadow: 0 8px 35px rgba(245, 158, 11, 0.6); }
        }
        
        .qv-stock-info { flex: 1; }
        .qv-stock-label {
            font-size: 0.68rem;
            color: #6b7a8f;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .qv-stock-value {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--primary-dark);
        }
        .qv-stock-value.green { color: #059669; }
        .qv-stock-value.orange { color: #d97706; }
        .qv-stock-value.red { color: #dc2626; }
        
        .qv-description-title {
            font-size: 0.72rem;
            color: var(--accent-gold);
            text-transform: uppercase;
            letter-spacing: 3px;
            font-weight: 800;
            margin-bottom: 12px;
        }
        .qv-description {
            font-size: 0.9rem;
            line-height: 1.75;
            color: #4b5563;
            margin-bottom: 28px;
            flex: 1;
            padding: 16px 20px;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.03), transparent);
            border-left: 3px solid var(--accent-gold);
            border-radius: 0 12px 12px 0;
        }
        
        .qv-quantity-row {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }
        .qv-quantity-label {
            font-size: 0.78rem;
            font-weight: 800;
            color: #4b5563;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .qv-quantity-selector {
            display: flex;
            align-items: center;
            border: 2px solid rgba(212, 175, 55, 0.3);
            border-radius: 50px;
            overflow: hidden;
            background: #fff;
        }
        .qv-qty-btn {
            background: transparent;
            border: none;
            width: 46px;
            height: 46px;
            cursor: pointer;
            font-size: 1.15rem;
            color: var(--primary-dark);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .qv-qty-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--accent-gold), #b8941f);
        }
        .qv-qty-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        .qv-qty-value {
            min-width: 50px;
            text-align: center;
            font-weight: 900;
            font-size: 1.05rem;
            color: var(--primary-dark);
            padding: 0 5px;
        }
        
        .qv-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .qv-btn-cart {
            flex: 1;
            min-width: 200px;
            padding: 18px 26px;
            background: linear-gradient(135deg, var(--accent-gold) 0%, #b8941f 100%);
            color: var(--primary-dark);
            border: none;
            border-radius: 50px;
            font-weight: 900;
            font-size: 0.9rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 12px 30px rgba(212, 175, 55, 0.4);
        }
        .qv-btn-cart:hover:not(:disabled) {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 20px 45px rgba(212, 175, 55, 0.55);
        }
        .qv-btn-cart:disabled { opacity: 0.5; cursor: not-allowed; }
        .qv-btn-wishlist {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid rgba(212, 175, 55, 0.3);
            color: #6b7a8f;
            cursor: pointer;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            flex-shrink: 0;
        }
        .qv-btn-wishlist:hover {
            border-color: #dc3545;
            color: #dc3545;
            transform: scale(1.1) rotate(-10deg);
        }
        
        @media (max-width: 900px) {
            .qv-modal-body { grid-template-columns: 1fr; }
            .qv-image-side { min-height: 400px; padding: 30px 20px; }
            .qv-slider-main { height: 300px; }
            .qv-info-side { padding: 35px 28px; }
            .qv-product-name { font-size: 1.7rem; }
            .qv-price-current { font-size: 2.2rem; }
        }
        @media (max-width: 500px) {
            .qv-slider-main { height: 240px; }
            .qv-product-name { font-size: 1.4rem; }
            .qv-price-current { font-size: 1.9rem; }
            .qv-nav-arrow { width: 36px; height: 36px; font-size: 1rem; }
            .qv-thumb { width: 50px; height: 50px; }
            .qv-actions { flex-direction: column; }
            .qv-btn-wishlist { width: 100%; height: 52px; border-radius: 50px; }
        }

        /* PRELOADER */
        #aysPreloader {
            position: fixed; inset: 0;
            background: radial-gradient(ellipse at center, #0a192f 0%, #000 100%);
            z-index: 999999; display: flex; align-items: center; justify-content: center;
            overflow: hidden;
            transition: opacity 1s cubic-bezier(0.77, 0, 0.18, 1),
                        visibility 1s cubic-bezier(0.77, 0, 0.18, 1),
                        transform 1s cubic-bezier(0.77, 0, 0.18, 1);
        }
        #aysPreloader.hidden { opacity: 0; visibility: hidden; transform: scale(1.15); pointer-events: none; }
        .preloader-bg-glow {
            position: absolute; width: 800px; height: 800px; border-radius: 50%;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 65%);
            animation: rotateGlow 8s linear infinite;
        }
        .preloader-bg-glow::before {
            content: ''; position: absolute; inset: 60px; border-radius: 50%;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);
            animation: rotateGlow 6s linear infinite reverse;
        }
        @keyframes rotateGlow {
            from { transform: rotate(0deg) scale(1); }
            to { transform: rotate(360deg) scale(1.1); }
        }
        .ays-logo-wrapper {
            position: relative; z-index: 10; text-align: center;
            display: flex; flex-direction: column; align-items: center; gap: 25px;
        }
        .ays-letters { display: flex; gap: 0.05em; perspective: 1000px; }
        .letter {
            font-size: clamp(5rem, 18vw, 13rem);
            font-weight: 900; letter-spacing: 0.05em; line-height: 1;
            background: linear-gradient(135deg, #d4af37 0%, #f7d26b 25%, #d4af37 50%, #fff8dc 75%, #d4af37 100%);
            background-size: 300% 300%;
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 30px rgba(212, 175, 55, 0.6));
            opacity: 0; transform: translateY(120px) rotateX(-90deg) scale(0.3);
            animation: letterReveal 1s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            animation-delay: calc(var(--i) * 0.25s + 0.3s);
        }
        @keyframes letterReveal {
            0% { opacity: 0; transform: translateY(120px) rotateX(-90deg) scale(0.3); }
            60% { opacity: 1; transform: translateY(-15px) rotateX(10deg) scale(1.08); }
            100% { opacity: 1; transform: translateY(0) rotateX(0) scale(1); }
        }
        .ays-tagline {
            font-size: clamp(0.65rem, 1.8vw, 1rem); font-weight: 300;
            letter-spacing: 0.65em; color: rgba(255, 255, 255, 0.85);
            text-transform: uppercase; opacity: 0; transform: translateY(20px);
            animation: taglineFade 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            animation-delay: 1.5s; padding-left: 0.65em;
        }
        @keyframes taglineFade {
            0% { opacity: 0; transform: translateY(20px); letter-spacing: 0.2em; }
            100% { opacity: 1; transform: translateY(0); letter-spacing: 0.65em; }
        }
        .loader-bar-container {
            width: clamp(200px, 50vw, 420px); height: 3px;
            background: rgba(255, 255, 255, 0.08); border-radius: 50px;
            overflow: hidden; opacity: 0;
            animation: barFadeIn 0.6s ease forwards;
            animation-delay: 1.8s;
        }
        @keyframes barFadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .loader-bar {
            height: 100%; width: 0%;
            background: linear-gradient(90deg, #d4af37, #f7d26b, #fff8dc, #d4af37);
            background-size: 200% 100%;
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.8), 0 0 40px rgba(212, 175, 55, 0.4);
            transition: width 0.15s linear;
            animation: barColorShift 2s linear infinite;
        }
        @keyframes barColorShift { 0% { background-position: 0% 50%; } 100% { background-position: 200% 50%; } }
        .loader-percentage {
            font-size: 0.85rem; font-weight: 600; color: var(--accent-gold);
            letter-spacing: 0.15em; opacity: 0;
            animation: barFadeIn 0.6s ease forwards;
            animation-delay: 2s;
        }
        .particles { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
        .particles span {
            position: absolute; width: 4px; height: 4px;
            background: var(--accent-gold); border-radius: 50%; opacity: 0;
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.8);
            animation: particleFloat 4s ease-in-out infinite;
        }
        .particles span:nth-child(1) { left: 15%; top: 20%; animation-delay: 0.2s; }
        .particles span:nth-child(2) { left: 85%; top: 30%; animation-delay: 0.8s; width: 3px; height: 3px; }
        .particles span:nth-child(3) { left: 25%; top: 75%; animation-delay: 1.4s; }
        .particles span:nth-child(4) { left: 75%; top: 80%; animation-delay: 0.5s; width: 5px; height: 5px; }
        .particles span:nth-child(5) { left: 45%; top: 10%; animation-delay: 2s; }
        .particles span:nth-child(6) { left: 60%; top: 65%; animation-delay: 1.1s; width: 3px; height: 3px; }
        .particles span:nth-child(7) { left: 10%; top: 55%; animation-delay: 2.5s; }
        .particles span:nth-child(8) { left: 90%; top: 50%; animation-delay: 0.3s; width: 5px; height: 5px; }
        .particles span:nth-child(9) { left: 35%; top: 40%; animation-delay: 1.8s; width: 3px; height: 3px; }
        .particles span:nth-child(10) { left: 70%; top: 15%; animation-delay: 2.2s; }
        .particles span:nth-child(11) { left: 5%; top: 85%; animation-delay: 0.9s; width: 6px; height: 6px; }
        .particles span:nth-child(12) { left: 50%; top: 90%; animation-delay: 1.6s; }
        @keyframes particleFloat {
            0% { opacity: 0; transform: translateY(0) scale(0); }
            20% { opacity: 1; transform: translateY(-20px) scale(1); }
            80% { opacity: 0.8; transform: translateY(-100px) scale(1); }
            100% { opacity: 0; transform: translateY(-150px) scale(0); }
        }
        @keyframes preloaderExit {
            0% { transform: scale(1); } 20% { transform: scale(1.02) rotate(1deg); }
            40% { transform: scale(0.98) rotate(-1deg); }
            60% { transform: scale(1.03) rotate(0.5deg); }
            80% { transform: scale(0.99) rotate(-0.5deg); }
            100% { transform: scale(1.15); }
        }
        #aysPreloader.exiting { animation: preloaderExit 0.7s cubic-bezier(0.36, 0.07, 0.19, 0.97) forwards; }

        .main-content, .brand-gradient, .category-nav, .footer {
            opacity: 0; transform: translateY(20px);
            transition: opacity 1s cubic-bezier(0.22, 1, 0.36, 1),
                        transform 1s cubic-bezier(0.22, 1, 0.36, 1);
        }
        body.website-loaded .main-content,
        body.website-loaded .brand-gradient,
        body.website-loaded .category-nav,
        body.website-loaded .footer {
            opacity: 1; transform: translateY(0);
        }
        body.website-loaded .brand-gradient { transition-delay: 0.15s; }
        body.website-loaded .category-nav { transition-delay: 0.3s; }
        body.website-loaded .main-content { transition-delay: 0.45s; }
        body.website-loaded .footer { transition-delay: 0.6s; }

        @media (max-width: 1400px) { .product-grid { grid-template-columns: repeat(5, 1fr); } }
        @media (max-width: 1200px) { .product-grid { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 992px) { 
            .product-grid { grid-template-columns: repeat(3, 1fr); } 
            .hero-banner-slide .banner-caption { top: 20px; right: 20px; }
        }
        @media (max-width: 768px) { 
            .product-grid { grid-template-columns: repeat(3, 1fr); } 
            .hero-section .hero-content h1 { font-size: 2.2rem; }
            .hero-section { padding: 60px 0 80px; min-height: 450px; }
            .flash-sale { margin-top: -15px; padding: 15px; flex-direction: column; text-align: center; }
            .cart-sidebar { width: 320px; right: -320px; }
            .chat-window { width: 92%; right: 4%; bottom: 80px; height: 70vh; }
            .chat-button { width: 50px; height: 50px; font-size: 1.5rem; bottom: 20px; right: 20px; }
            .footer { padding: 30px 0 20px; text-align: center; }
        }
        @media (max-width: 576px) { 
            .product-grid { grid-template-columns: repeat(2, 1fr); } 
            .product-image-slider { height: 140px; }
            .cart-sidebar { width: 100%; right: -100%; }
            .chat-window { width: 96%; right: 2%; }
        }
    </style>
</head>
<body>

<!-- AYS PRELOADER -->
<div id="aysPreloader">
    <div class="preloader-bg-glow"></div>
    <div class="ays-logo-wrapper">
        <div class="ays-letters">
            <span class="letter" style="--i:0">A</span>
            <span class="letter" style="--i:1">Y</span>
            <span class="letter" style="--i:2">S</span>
        </div>
        <div class="ays-tagline">PREMIUM LUXURY EXPERIENCE</div>
        <div class="loader-bar-container"><div class="loader-bar"></div></div>
        <div class="loader-percentage">0%</div>
    </div>
    <div class="particles">
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span>
    </div>
</div>

<!-- QUICK VIEW MODAL WITH SLIDER -->
<div class="qv-modal-overlay" id="qvModalOverlay" onclick="if(event.target === this) closeQuickView()">
    <div class="qv-bg-particles">
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
    </div>
    <div class="qv-glow-orb orb-1"></div>
    <div class="qv-glow-orb orb-2"></div>
    
    <div class="qv-modal">
        <button class="qv-close-btn" onclick="closeQuickView()" title="Close">
            <i class="bi bi-x-lg"></i>
        </button>
        
        <div class="qv-modal-body">
            <div class="qv-image-side">
                <span class="qv-image-counter" id="qvImageCounter">1 / 1</span>
                
                <div class="qv-slider-wrapper">
                    <div class="qv-slider-main">
                        <div class="qv-slider-track" id="qvSliderTrack">
                            <img src="" alt="Product" id="qvProductImage">
                        </div>
                        
                        <button class="qv-nav-arrow prev" id="qvPrevBtn" onclick="qvSlidePrev()" style="display:none;">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button class="qv-nav-arrow next" id="qvNextBtn" onclick="qvSlideNext()" style="display:none;">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                    
                    <div class="qv-thumbnails" id="qvThumbnails"></div>
                </div>
            </div>
            
            <div class="qv-info-side">
                <span class="qv-category" id="qvCategory">
                    <i class="bi bi-flower1"></i> Perfume
                </span>
                
                <h2 class="qv-product-name" id="qvProductName">Product Name</h2>
                
                <div class="qv-price-section">
                    <div class="qv-price-current" id="qvPriceCurrent">
                        <span class="currency">PKR</span>
                        <span id="qvProductPrice">0.00</span>
                    </div>
                    <span class="qv-price-original" id="qvOriginalPrice" style="display:none;">PKR 0.00</span>
                    <span class="qv-discount-tag" id="qvDiscountTag" style="display:none;">
                        <i class="bi bi-tag-fill"></i> <span id="qvDiscountPercent">0</span>% OFF
                    </span>
                </div>
                
                <div class="qv-stock-section">
                    <div class="qv-stock-icon in-stock" id="qvStockIcon">
                        <i class="bi bi-box-seam-fill"></i>
                    </div>
                    <div class="qv-stock-info">
                        <div class="qv-stock-label">Available Stock</div>
                        <div class="qv-stock-value green" id="qvStockValue">
                            <span id="qvStockCount">0</span> items available
                        </div>
                    </div>
                </div>
                
                <div class="qv-description-title">Description</div>
                <p class="qv-description" id="qvProductDescription">
                    No description available.
                </p>
                
                <div class="qv-quantity-row">
                    <span class="qv-quantity-label">QUANTITY</span>
                    <div class="qv-quantity-selector">
                        <button class="qv-qty-btn" onclick="changeQVQuantity(-1)" id="qvQtyMinus">
                            <i class="bi bi-dash"></i>
                        </button>
                        <span class="qv-qty-value" id="qvQuantity">1</span>
                        <button class="qv-qty-btn" onclick="changeQVQuantity(1)" id="qvQtyPlus">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                </div>
                
                <div class="qv-actions">
                    <button class="qv-btn-cart" id="qvAddToCartBtn" onclick="qvAddToCart()">
                        <i class="bi bi-bag-plus-fill"></i>
                        <span>Add to Cart</span>
                    </button>
                    <button class="qv-btn-wishlist" id="qvWishlistBtn" onclick="qvAddToWishlist()" title="Add to Wishlist">
                        <i class="bi bi-heart"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ADMIN LOGIN OVERLAY -->
<div id="adminLoginOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(8px);">
    <div class="glass-card p-4" style="max-width:420px; width:90%; background:white; border-radius: 12px;">
        <h4 class="text-center gold-text"><i class="bi bi-shield-lock-fill me-2"></i>Admin Access</h4>
        <p class="text-muted text-center small">Enter credentials to manage orders</p>
        <input type="text" id="adminUser" class="form-control mb-3" placeholder="Username" value="admin" style="border-radius:40px; padding:14px 20px;">
        <input type="password" id="adminPass" class="form-control mb-3" placeholder="Password" value="luxe123" style="border-radius:40px; padding:14px 20px;">
        <button class="btn-gold w-100" onclick="adminLogin()"><i class="bi bi-box-arrow-in-right me-2"></i>Unlock Dashboard</button>
        <p class="text-center text-muted mt-3 small" style="cursor:pointer;" onclick="closeAdminLogin()">✕ Close</p>
    </div>
</div>

<!-- HEADER -->
<header class="brand-gradient">
    <div class="container">
        <div class="row align-items-center g-2">
            <div class="col-3 col-md-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-white fw-bold fs-4" style="letter-spacing:-1px;">
                    Mr.<span style="color:#D4AF37;">AYS</span>
                    </span>
                </div>
            </div>
            <div class="col-6 col-md-6">
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Search for perfumes, watches, glasses..." autocomplete="off">
                    <button class="search-clear" id="searchClear" onclick="clearSearch()" title="Clear search"><i class="bi bi-x-lg"></i></button>
                    <button onclick="performSearch()"><i class="bi bi-search"></i></button>
                </div>
            </div>
            <div class="col-3 col-md-4">
                <div class="header-icons">
                    <span id="clientGreeting" class="text-light me-2">
                        <?php if(!empty($loggedInName)): ?>
                            👋 <?php echo htmlspecialchars($loggedInName); ?>
                        <?php else: ?>
                            Guest
                        <?php endif; ?>
                    </span>
                    
                    <a href="#" class="icon-btn position-relative" onclick="openCart()">
                        <i class="bi bi-bag"></i>
                        <span>Cart</span>
                        <span class="badge-count cart-badge" style="display:none;">0</span>
                    </a>

                    <a href="wishlist.php" class="icon-btn position-relative">
                        <i class="bi bi-heart"></i>
                        <span>Wishlist</span>
                    </a>

                   <?php if(isset($_SESSION['client_id'])): ?>
    <a href="account.php" class="icon-btn">
        <i class="bi bi-person-circle"></i>
        <span>My Account</span>
    </a>
    <a href="logout.php" class="icon-btn">
        <i class="bi bi-box-arrow-right"></i>
        <span>Logout</span>
    </a>
<?php else: ?>
    <a href="login.php" class="icon-btn">
        <i class="bi bi-person"></i>
        <span>Account</span>
    </a>
<?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- CATEGORY NAVIGATION -->
<nav class="category-nav">
    <div class="container">
        <div class="d-flex justify-content-center flex-wrap gap-2">
            <a href="#" class="category-link active" data-category="all" onclick="filterCategory('all', this); return false;">All Products</a>
            <a href="#" class="category-link" data-category="perfume" onclick="filterCategory('perfume', this); return false;">Perfumes</a>
            <a href="#" class="category-link" data-category="tester" onclick="filterCategory('tester', this); return false;">Testers</a>
            <a href="#" class="category-link" data-category="watch" onclick="filterCategory('watch', this); return false;">Watches</a>
            <a href="#" class="category-link" data-category="glass" onclick="filterCategory('glass', this); return false;">Glasses</a>
            <a href="orders.php" class="category-link">Orders</a>
        </div>
    </div>
</nav>

<button id="scrollToTop" onclick="scrollToTop()"><i class="bi bi-arrow-up"></i></button>

<!-- SMART MULTILINGUAL CHATBOT -->
<button class="chat-button" onclick="toggleChat()">
    <i class="bi bi-chat-dots"></i>
    <span class="notification-dot"></span>
</button>

<div class="chat-window" id="chatWindow">
    <div class="chat-header">
        <div class="bot-info">
            <div class="avatar">🤖</div>
            <div>
                <h6>LUXE AI Assistant <span class="lang-indicator" id="langIndicator">EN</span></h6>
                <small><i class="bi bi-circle-fill" style="color:#22c55e; font-size:0.5rem;"></i> English · اردو · Roman Urdu</small>
            </div>
        </div>
        <div class="chat-header-actions">
            <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Toggle Dark/Light Theme">
                <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
            </button>
            <span class="close-chat" onclick="toggleChat()"><i class="bi bi-x-lg"></i></span>
        </div>
    </div>
    <div class="chat-body" id="chatBody">
        <div class="message bot" id="welcomeMessage">
            👋 Hello! Welcome to <strong>Mr.AYS</strong>.<br>
            Ask me anything in <strong>English</strong>, <strong>اردو</strong>, or <strong>Roman Urdu</strong>!<br>
            <small style="opacity:0.8;">Aap Roman Urdu mein bhi baat kar sakte hain!<br>🌙 Tap the moon icon to switch themes!</small>
        </div>
        <div class="quick-questions" id="quickQuestions">
            <button class="greet-btn" onclick="sendQuickMessage('Hi')">👋 Hi</button>
            <button class="greet-btn" onclick="sendQuickMessage('Hello')">🙂 Hello</button>
            <button class="greet-btn" onclick="sendQuickMessage('Assalam o Alaikum')">☪️ Assalam</button>
            <button class="greet-btn" onclick="sendQuickMessage('Good morning')">🌅 Morning</button>
            <button class="greet-btn" onclick="sendQuickMessage('Good evening')">🌙 Evening</button>
            <button onclick="sendQuickMessage('Best selling perfumes')">🔥 Best Sellers</button>
            <button onclick="sendQuickMessage('Recommend latest perfume')">✨ New Arrivals</button>
            <button onclick="sendQuickMessage('Where is my order?')">📦 Order Status</button>
            <button onclick="sendQuickMessage('How to contact support?')">📞 Contact</button>
            <button onclick="sendQuickMessage('Payment methods')">💳 Payment</button>
            <button onclick="sendQuickMessage('Discounts and offers')">🎁 Offers</button>
            <button class="roman-btn" onclick="sendQuickMessage('Ye kitne ka hai?')">💰 Ye kitne ka hai?</button>
            <button class="roman-btn" onclick="sendQuickMessage('Best perfume konsa hai?')">🔥 Best Perfume?</button>
            <button class="roman-btn" onclick="sendQuickMessage('Kya haal hai?')">😊 Kya haal hai?</button>
            <button class="urdu-btn" onclick="sendQuickMessage('السلام علیکم')">☪️ السلام علیکم</button>
            <button class="urdu-btn" onclick="sendQuickMessage('کیا حال ہے؟')">😊 کیا حال ہے؟</button>
        </div>
    </div>
    <div class="chat-footer">
        <input type="text" id="chatInput" placeholder="Type in English, اردو, or Roman Urdu..." onkeypress="if(event.key==='Enter') sendMessage()">
        <button onclick="sendMessage()"><i class="bi bi-send"></i></button>
    </div>
</div>

<!-- CART SIDEBAR -->
<div class="cart-overlay" id="cartOverlay" onclick="closeCart()"></div>
<div class="cart-sidebar" id="cartSidebar">
    <div class="cart-header">
        <h4><i class="bi bi-cart gold-text me-2"></i>Your Cart</h4>
        <span class="cart-close" onclick="closeCart()"><i class="bi bi-x-lg"></i></span>
    </div>
    <div class="cart-items" id="cartItems">
        <div class="text-center text-muted py-5">
            <i class="bi bi-cart3 fs-1 d-block mb-3"></i>
            Your cart is empty.
        </div>
    </div>
    <div class="cart-footer">
        <div class="total-row">
            <span>Total:</span>
            <span id="cartTotal">PKR 0.00</span>
        </div>
        <button class="btn-gold checkout-btn" onclick="checkout()">
            <i class="bi bi-bag-check me-2"></i>Proceed to Checkout
        </button>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    <section class="hero-section text-white" id="heroSection">
        <div class="hero-banner-bg" id="heroBannerBg">
            <?php if(!empty($banners)): ?>
                <?php 
                $bannersWithImages = array_filter($banners, function($b) { 
                    return !empty($b['image_url']); 
                });
                $bannersWithImages = array_values($bannersWithImages);
                ?>
                <?php if(!empty($bannersWithImages)): ?>
                    <?php foreach($bannersWithImages as $index => $banner): ?>
                        <div class="hero-banner-slide <?php echo $index === 0 ? 'active' : ''; ?>" 
                             style="background-image: url('<?php echo htmlspecialchars($banner['image_url']); ?>');">
                            <?php if(!empty($banner['badge_text']) || !empty($banner['title'])): ?>
                                <div class="banner-caption">
                                    <?php if(!empty($banner['badge_text'])): ?>
                                        <span class="banner-badge <?php echo htmlspecialchars($banner['badge_color'] ?? 'bg-danger'); ?>">
                                            <?php echo htmlspecialchars($banner['badge_text']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if(!empty($banner['title'])): ?>
                                        <span class="banner-title"><?php echo htmlspecialchars($banner['title']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="hero-banner-slide active" 
                         style="background-image: url('https://images.pexels.com/photos/965989/pexels-photo-965989.jpeg?auto=compress&cs=tinysrgb&w=1200');">
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="hero-banner-slide active" 
                     style="background-image: url('https://images.pexels.com/photos/965989/pexels-photo-965989.jpeg?auto=compress&cs=tinysrgb&w=1200');">
                </div>
            <?php endif; ?>
        </div>

        <?php if(count($banners) > 1): ?>
        <div class="hero-banner-dots" id="heroBannerDots">
            <?php foreach($banners as $index => $banner): ?>
                <span class="dot <?php echo $index === 0 ? 'active' : ''; ?>" 
                      onclick="goToBannerSlide(<?php echo $index; ?>)"></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <h1 class="display-4 fw-bold">Premium <span class="highlight">Luxury</span> Collection</h1>
                    <p class="lead mt-3">Discover our exclusive range of perfumes, testers, watches, and premium eyewear. Elevate your style with Mr.AYS.</p>
                    <div class="mt-4 d-flex gap-3">
                        <button class="btn-gold" onclick="document.getElementById('perfumeGrid').scrollIntoView({behavior:'smooth'})">
                            <i class="bi bi-grid me-2"></i>Shop Now
                        </button>
                        <button class="btn-outline-gold" onclick="openCart()">
                            <i class="bi bi-bag me-2"></i>View Cart
                        </button>
                    </div>
                </div>
                <div class="col-lg-6 hero-image text-center">
                    <img src="https://images.pexels.com/photos/965989/pexels-photo-965989.jpeg?auto=compress&cs=tinysrgb&w=600" 
                         alt="Luxury Collection" class="img-fluid rounded-4">
                </div>
            </div>
        </div>
    </section>

    <section class="py-4">
        <div class="container">
            <div class="flash-sale">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-lightning-fill text-danger fs-3"></i>
                    <div>
                        <h5 class="fw-bold mb-0 text-danger">FLASH SALE</h5>
                        <small class="text-muted">Limited time offer</small>
                    </div>
                </div>

                <div class="timer" id="countdownTimer">
                    <div class="time-block"><span id="hours">12</span> <small>hrs</small></div>
                    <div class="time-block"><span id="minutes">45</span> <small>min</small></div>
                    <div class="time-block"><span id="seconds">30</span> <small>sec</small></div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <?php if(!empty($flashBanners)): ?>
                        <?php foreach($flashBanners as $banner): ?>
                        <a href="<?php echo htmlspecialchars($banner['link_url'] ?? '#'); ?>" class="banner-item text-decoration-none">
                            <?php if(!empty($banner['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($banner['image_url']); ?>" alt="">
                            <?php endif; ?>
                            <?php if(!empty($banner['badge_text'])): ?>
                                <span class="badge-text <?php echo htmlspecialchars($banner['badge_color'] ?? 'bg-danger'); ?> text-white px-2 py-1 rounded-pill small">
                                    <?php echo htmlspecialchars($banner['badge_text']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if(!empty($banner['title'])): ?>
                                <small class="text-muted d-none d-md-block"><?php echo htmlspecialchars($banner['title']); ?></small>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted small fst-italic">No flash offers right now. Check back soon!</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="pt-4">
        <div class="container">
            <div class="search-info" id="searchInfo">
                <span><i class="bi bi-search gold-text me-2"></i>Showing results for: <strong id="searchInfoTerm"></strong></span>
                <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="clearSearch()">
                    <i class="bi bi-x-circle me-1"></i>Clear Search
                </button>
            </div>
        </div>
    </section>

    <section class="py-4 products-wrapper" id="section-perfume">
        <div class="container">
            <div class="section-header">
                <h2><i class="bi bi-flower1 gold-text me-2"></i>Premium Perfumes</h2>
                <a href="#" class="view-all" onclick="filterCategory('perfume', document.querySelector('[data-category=perfume]')); return false;">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="product-grid" id="perfumeGrid"></div>
        </div>
    </section>

    <section class="py-4 products-wrapper" id="section-tester" style="background: white;">
        <div class="container">
            <div class="section-header">
                <h2><i class="bi bi-flask gold-text me-2"></i>Tester Collection</h2>
                <a href="#" class="view-all" onclick="filterCategory('tester', document.querySelector('[data-category=tester]')); return false;">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="product-grid" id="testerGrid"></div>
        </div>
    </section>

    <section class="py-4 products-wrapper" id="section-watch">
        <div class="container">
            <div class="section-header">
                <h2><i class="bi bi-clock gold-text me-2"></i>Luxury Watches</h2>
                <a href="#" class="view-all" onclick="filterCategory('watch', document.querySelector('[data-category=watch]')); return false;">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="product-grid" id="watchGrid"></div>
        </div>
    </section>

    <section class="py-4 products-wrapper" id="section-glass" style="background: white;">
        <div class="container">
            <div class="section-header">
                <h2><i class="bi bi-eyeglasses gold-text me-2"></i>Premium Eyewear</h2>
                <a href="#" class="view-all" onclick="filterCategory('glass', document.querySelector('[data-category=glass]')); return false;">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="product-grid" id="glassGrid"></div>
        </div>
    </section>

    <div class="container">
        <div class="no-results" id="noResults">
            <i class="bi bi-search"></i>
            <h4>No products found</h4>
            <p>We couldn't find any products matching your search. Try a different keyword.</p>
        </div>
    </div>
</div>

<!-- FOOTER -->
<footer class="footer">
    <button class="back-to-top" onclick="window.scrollTo({top:0, behavior:'smooth'})">
        <i class="bi bi-chevron-up"></i>
    </button>

    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <h6 class="gold-text">Mr.AYS</h6>
                <p class="brand-desc">
                    Premium luxury products since 2026. Discover our exclusive range of perfumes, testers, watches, and premium eyewear.
                </p>
                <div class="social-links">
                    <a href="https://www.facebook.com/profile.php?id=61593054195065"><i class="bi bi-facebook"></i></a>
                    <a href="https://www.instagram.com/mr.ays_officiall"><i class="bi bi-instagram"></i></a>
                    <a href="#"><i class="bi bi-twitter-x"></i></a>
                    <a href="https://wa.me/923263368118" target="_blank"><i class="bi bi-whatsapp"></i></a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <h6>Quick Links</h6>
                <ul>
                    <li><a href="index.php"><i class="bi bi-chevron-right"></i> Shop Now</a></li>
                    <li><a href="orders.php"><i class="bi bi-chevron-right"></i> My Orders</a></li>
                    <li><a href="wishlist.php"><i class="bi bi-chevron-right"></i> My Wishlist</a></li>
                    <li><a href="account.php"><i class="bi bi-chevron-right"></i> My Account</a></li>
                </ul>
            </div>

            <div class="col-lg-4 col-md-6">
                <h6>Policies</h6>
                <ul>
                    <li><a href="return-policy.php"><i class="bi bi-chevron-right"></i> Return Policy</a></li>
                    <li><a href="privacy-policy.php"><i class="bi bi-chevron-right"></i> Privacy Policy</a></li>
                    <li><a href="terms-conditions.php"><i class="bi bi-chevron-right"></i> Terms &amp; Conditions</a></li>
                    <li><a href="shipping-info.php"><i class="bi bi-chevron-right"></i> Shipping Info</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-divider"></div>

        <div class="copyright">
            &copy; 2026 AYS. All Rights Reserved. powered by <a href="https://zam2.vercel.app" target="_blank" style="color: #ffcc00; text-decoration: none; font-weight: bold;">ZAM Digital Agency</a>
        </div>
    </div>
</footer>

<script>
// ==========================
// HERO BANNER AUTO-ROTATION
// ==========================
(function initHeroBannerRotation() {
    const slides = document.querySelectorAll('.hero-banner-slide');
    const dots = document.querySelectorAll('.hero-banner-dots .dot');
    
    if (slides.length <= 1) return;
    
    let currentSlide = 0;
    let rotationInterval = null;
    const ROTATION_DELAY = 2000;
    
    function showSlide(index) {
        slides.forEach(slide => slide.classList.remove('active'));
        dots.forEach(dot => dot.classList.remove('active'));
        slides[index].classList.add('active');
        if (dots[index]) dots[index].classList.add('active');
        currentSlide = index;
    }
    
    function nextSlide() {
        const nextIndex = (currentSlide + 1) % slides.length;
        showSlide(nextIndex);
    }
    
    function startRotation() {
        if (rotationInterval) clearInterval(rotationInterval);
        rotationInterval = setInterval(nextSlide, ROTATION_DELAY);
    }
    
    function stopRotation() {
        if (rotationInterval) {
            clearInterval(rotationInterval);
            rotationInterval = null;
        }
    }
    
    window.goToBannerSlide = function(index) {
        if (index < 0 || index >= slides.length) return;
        showSlide(index);
        stopRotation();
        startRotation();
    };
    
    const heroSection = document.getElementById('heroSection');
    if (heroSection) {
        heroSection.addEventListener('mouseenter', stopRotation);
        heroSection.addEventListener('mouseleave', startRotation);
    }
    
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) stopRotation();
        else startRotation();
    });
    
    startRotation();
})();

// ==========================
// PRODUCT DATA
// ==========================
const allProductsData = <?php echo json_encode($allProducts); ?>;

function getProductCategory(product) {
    const nameLower = product.name.toLowerCase();
    if (nameLower.includes('tester')) return 'tester';
    if (nameLower.includes('watch')) return 'watch';
    if (nameLower.includes('glass') || nameLower.includes('eyewear')) return 'glass';
    return 'perfume';
}

// ==========================
// QUICK VIEW WITH SLIDER
// ==========================
let qvCurrentProduct = null;
let qvCurrentQuantity = 1;
let qvCurrentSlideIndex = 0;
let qvImages = [];

function openQuickView(productId) {
    const product = allProductsData.find(p => p.id == productId);
    if (!product) {
        showToast('Product not found', 'error');
        return;
    }
    
    qvCurrentProduct = product;
    qvCurrentQuantity = 1;
    qvCurrentSlideIndex = 0;
    
    qvImages = [];
    if (product.image_url) qvImages.push(product.image_url);
    if (product.images && product.images.length > 0) {
        product.images.forEach(img => {
            if (img.image_url && !qvImages.includes(img.image_url)) {
                qvImages.push(img.image_url);
            }
        });
    }
    if (qvImages.length === 0) qvImages.push('https://via.placeholder.com/600');
    
    const stock = parseInt(product.stock) || 0;
    const isOutOfStock = stock <= 0;
    const isLowStock = stock > 0 && stock < 10;
    const category = getProductCategory(product);
    
    const discountPercent = parseFloat(product.discount_percent) || 0;
    const originalPrice = parseFloat(product.price);
    const salePrice = product.sale_price ? parseFloat(product.sale_price) : originalPrice;
    const isOnSale = discountPercent > 0;
    
    const catMap = {
        'perfume': { icon: 'bi-flower1', label: 'Perfume' },
        'tester': { icon: 'bi-flask', label: 'Tester' },
        'watch': { icon: 'bi-clock-fill', label: 'Watch' },
        'glass': { icon: 'bi-eyeglasses', label: 'Eyewear' }
    };
    const cat = catMap[category] || catMap.perfume;
    
    const track = document.getElementById('qvSliderTrack');
    track.innerHTML = qvImages.map(img => `<img src="${img}" alt="${product.name}" draggable="false">`).join('');
    track.style.transform = 'translateX(0%)';
    
    const thumbsContainer = document.getElementById('qvThumbnails');
    if (qvImages.length > 1) {
        thumbsContainer.style.display = 'flex';
        thumbsContainer.innerHTML = qvImages.map((img, i) => 
            `<div class="qv-thumb ${i === 0 ? 'active' : ''}" onclick="qvGoToSlide(${i})">
                <img src="${img}" alt="">
            </div>`
        ).join('');
    } else {
        thumbsContainer.style.display = 'none';
    }
    
    document.getElementById('qvPrevBtn').style.display = qvImages.length > 1 ? 'flex' : 'none';
    document.getElementById('qvNextBtn').style.display = qvImages.length > 1 ? 'flex' : 'none';
    document.getElementById('qvImageCounter').textContent = `1 / ${qvImages.length}`;
    
    document.getElementById('qvProductName').textContent = product.name;
    document.getElementById('qvProductDescription').textContent = product.description || 'Exclusive luxury item curated just for you.';
    document.getElementById('qvCategory').innerHTML = `<i class="bi ${cat.icon}"></i> ${cat.label}`;
    
    const priceEl = document.getElementById('qvPriceCurrent');
    const origPriceEl = document.getElementById('qvOriginalPrice');
    const discountTag = document.getElementById('qvDiscountTag');
    
    if (isOnSale) {
        document.getElementById('qvProductPrice').textContent = salePrice.toFixed(2);
        priceEl.classList.add('on-sale');
        origPriceEl.style.display = 'inline';
        origPriceEl.textContent = `PKR ${originalPrice.toFixed(2)}`;
        discountTag.style.display = 'inline-flex';
        document.getElementById('qvDiscountPercent').textContent = discountPercent;
    } else {
        document.getElementById('qvProductPrice').textContent = originalPrice.toFixed(2);
        priceEl.classList.remove('on-sale');
        origPriceEl.style.display = 'none';
        discountTag.style.display = 'none';
    }
    
    document.getElementById('qvStockCount').textContent = stock;
    const stockIcon = document.getElementById('qvStockIcon');
    const stockValue = document.getElementById('qvStockValue');
    
    if (isOutOfStock) {
        stockIcon.className = 'qv-stock-icon out-of-stock';
        stockIcon.innerHTML = '<i class="bi bi-x-circle-fill"></i>';
        stockValue.className = 'qv-stock-value red';
    } else if (isLowStock) {
        stockIcon.className = 'qv-stock-icon low-stock';
        stockIcon.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i>';
        stockValue.className = 'qv-stock-value orange';
    } else {
        stockIcon.className = 'qv-stock-icon in-stock';
        stockIcon.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
        stockValue.className = 'qv-stock-value green';
    }
    
    document.getElementById('qvQuantity').textContent = '1';
    document.getElementById('qvQtyPlus').disabled = (stock <= 1);
    document.getElementById('qvQtyMinus').disabled = true;
    
    const cartBtn = document.getElementById('qvAddToCartBtn');
    if (isOutOfStock) {
        cartBtn.disabled = true;
        cartBtn.innerHTML = '<i class="bi bi-x-circle"></i><span>Out of Stock</span>';
    } else {
        cartBtn.disabled = false;
        cartBtn.innerHTML = '<i class="bi bi-bag-plus-fill"></i><span>Add to Cart</span>';
    }
    
    const overlay = document.getElementById('qvModalOverlay');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function qvGoToSlide(index) {
    if (index < 0 || index >= qvImages.length) return;
    qvCurrentSlideIndex = index;
    const track = document.getElementById('qvSliderTrack');
    track.style.transform = `translateX(-${index * 100}%)`;
    document.getElementById('qvImageCounter').textContent = `${index + 1} / ${qvImages.length}`;
    document.querySelectorAll('.qv-thumb').forEach((t, i) => {
        t.classList.toggle('active', i === index);
    });
}

function qvSlideNext() {
    const nextIndex = (qvCurrentSlideIndex + 1) % qvImages.length;
    qvGoToSlide(nextIndex);
}

function qvSlidePrev() {
    const prevIndex = (qvCurrentSlideIndex - 1 + qvImages.length) % qvImages.length;
    qvGoToSlide(prevIndex);
}

function closeQuickView() {
    document.getElementById('qvModalOverlay').classList.remove('active');
    document.body.style.overflow = 'auto';
    qvCurrentProduct = null;
    qvCurrentQuantity = 1;
    qvCurrentSlideIndex = 0;
}

function changeQVQuantity(delta) {
    if (!qvCurrentProduct) return;
    const stock = parseInt(qvCurrentProduct.stock) || 0;
    const newQty = qvCurrentQuantity + delta;
    if (newQty < 1 || newQty > stock) return;
    qvCurrentQuantity = newQty;
    document.getElementById('qvQuantity').textContent = newQty;
    document.getElementById('qvQtyMinus').disabled = (newQty <= 1);
    document.getElementById('qvQtyPlus').disabled = (newQty >= stock);
}

function qvAddToCart() {
    if (!qvCurrentProduct) return;
    const stock = parseInt(qvCurrentProduct.stock) || 0;
    if (stock <= 0) { showToast('❌ Product is out of stock!', 'error'); return; }
    
    const discountPercent = parseFloat(qvCurrentProduct.discount_percent) || 0;
    const originalPrice = parseFloat(qvCurrentProduct.price);
    const priceToUse = discountPercent > 0 && qvCurrentProduct.sale_price 
        ? parseFloat(qvCurrentProduct.sale_price) : originalPrice;
    
    const existing = cart.find(item => item.id == qvCurrentProduct.id);
    const existingQty = existing ? existing.quantity : 0;
    
    if (existingQty + qvCurrentQuantity > stock) {
        showToast(`⚠️ Only ${stock} available in stock!`, 'warning');
        return;
    }
    
    if (existing) {
        existing.quantity += qvCurrentQuantity;
    } else {
        cart.push({
            id: qvCurrentProduct.id,
            name: qvCurrentProduct.name,
            price: priceToUse,
            original_price: originalPrice,
            discount_percent: discountPercent,
            image: qvCurrentProduct.image_url,
            quantity: qvCurrentQuantity,
            maxStock: stock
        });
    }
    
    saveCart();
    updateCartUI();
    showToast(`✅ Added ${qvCurrentQuantity} item(s) to cart!`, 'success');
    closeQuickView();
}

function qvAddToWishlist() {
    if (!qvCurrentProduct) return;
    addToWishlist(qvCurrentProduct.id, qvCurrentProduct.name, qvCurrentProduct.price, qvCurrentProduct.image_url);
    closeQuickView();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeQuickView();
    if (document.getElementById('qvModalOverlay').classList.contains('active')) {
        if (e.key === 'ArrowRight') qvSlideNext();
        if (e.key === 'ArrowLeft') qvSlidePrev();
    }
});

// ==========================
// PRODUCT CARD SLIDER
// ==========================
function initCardSliders() {
    document.querySelectorAll('.product-image-slider').forEach(slider => {
        const track = slider.querySelector('.product-slider-track');
        const images = track.querySelectorAll('img');
        if (images.length <= 1) return;
        
        let currentIndex = 0;
        let startX = 0;
        let currentX = 0;
        let isDragging = false;
        
        const dots = slider.querySelectorAll('.slider-dot');
        const prevBtn = slider.querySelector('.slider-arrow.prev');
        const nextBtn = slider.querySelector('.slider-arrow.next');
        
        function goToSlide(index) {
            if (index < 0) index = images.length - 1;
            if (index >= images.length) index = 0;
            currentIndex = index;
            track.style.transform = `translateX(-${index * 100}%)`;
            dots.forEach((d, i) => d.classList.toggle('active', i === index));
        }
        
        if (prevBtn) prevBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            goToSlide(currentIndex - 1);
        });
        if (nextBtn) nextBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            goToSlide(currentIndex + 1);
        });
        
        dots.forEach((dot, i) => {
            dot.addEventListener('click', (e) => {
                e.stopPropagation();
                goToSlide(i);
            });
        });
        
        slider.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            isDragging = true;
            track.style.transition = 'none';
        }, { passive: true });
        
        slider.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            currentX = e.touches[0].clientX;
            const diff = currentX - startX;
            track.style.transform = `translateX(calc(-${currentIndex * 100}% + ${diff}px))`;
        }, { passive: true });
        
        slider.addEventListener('touchend', () => {
            if (!isDragging) return;
            isDragging = false;
            track.style.transition = 'transform 0.4s cubic-bezier(0.22, 1, 0.36, 1)';
            const diff = currentX - startX;
            if (Math.abs(diff) > 50) {
                if (diff < 0) goToSlide(currentIndex + 1);
                else goToSlide(currentIndex - 1);
            } else {
                goToSlide(currentIndex);
            }
        });
        
        slider.addEventListener('mousedown', (e) => {
            if (e.target.closest('.slider-arrow') || e.target.closest('.slider-dot')) return;
            startX = e.clientX;
            isDragging = true;
            track.style.transition = 'none';
            e.preventDefault();
        });
        
        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            currentX = e.clientX;
            const diff = currentX - startX;
            track.style.transform = `translateX(calc(-${currentIndex * 100}% + ${diff}px))`;
        });
        
        document.addEventListener('mouseup', () => {
            if (!isDragging) return;
            isDragging = false;
            track.style.transition = 'transform 0.4s cubic-bezier(0.22, 1, 0.36, 1)';
            const diff = currentX - startX;
            if (Math.abs(diff) > 50) {
                if (diff < 0) goToSlide(currentIndex + 1);
                else goToSlide(currentIndex - 1);
            } else {
                goToSlide(currentIndex);
            }
        });
    });
}

// ==========================
// CATEGORY FILTER
// ==========================
let currentCategory = 'all';
let currentSearchTerm = '';

function filterCategory(category, element) {
    currentCategory = category;
    document.querySelectorAll('.category-link[data-category]').forEach(link => {
        link.classList.remove('active');
    });
    if (element) element.classList.add('active');
    else {
        const target = document.querySelector(`.category-link[data-category="${category}"]`);
        if (target) target.classList.add('active');
    }
    
    applyFilters();
    
    const targetSection = document.getElementById('section-perfume');
    if (targetSection) {
        setTimeout(() => {
            targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    }
}

function performSearch() {
    const input = document.getElementById('searchInput');
    currentSearchTerm = input.value.trim().toLowerCase();
    const clearBtn = document.getElementById('searchClear');
    if (currentSearchTerm) clearBtn.classList.add('show');
    else clearBtn.classList.remove('show');
    applyFilters();
    if (currentSearchTerm) {
        document.getElementById('section-perfume').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    currentSearchTerm = '';
    document.getElementById('searchClear').classList.remove('show');
    applyFilters();
}

let searchDebounceTimer;
document.getElementById('searchInput').addEventListener('input', function(e) {
    clearTimeout(searchDebounceTimer);
    const val = this.value.trim();
    const clearBtn = document.getElementById('searchClear');
    if (val) clearBtn.classList.add('show');
    else clearBtn.classList.remove('show');
    searchDebounceTimer = setTimeout(() => {
        currentSearchTerm = val.toLowerCase();
        applyFilters();
    }, 250);
});

document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') performSearch();
});

function applyFilters() {
    const allCards = document.querySelectorAll('.product-card');
    let visibleCount = 0;
    const perCategoryCount = { perfume: 0, tester: 0, watch: 0, glass: 0 };

    allCards.forEach(card => {
        const cardCategory = card.getAttribute('data-category');
        const cardName = (card.getAttribute('data-name') || '').toLowerCase();
        const categoryMatch = (currentCategory === 'all') || (cardCategory === currentCategory);
        const searchMatch = !currentSearchTerm || cardName.includes(currentSearchTerm);
        if (categoryMatch && searchMatch) {
            card.classList.remove('hidden');
            visibleCount++;
            if (perCategoryCount[cardCategory] !== undefined) perCategoryCount[cardCategory]++;
        } else {
            card.classList.add('hidden');
        }
    });

    const sections = {
        perfume: document.getElementById('section-perfume'),
        tester: document.getElementById('section-tester'),
        watch: document.getElementById('section-watch'),
        glass: document.getElementById('section-glass')
    };

    if (currentCategory === 'all') {
        Object.entries(sections).forEach(([key, section]) => {
            if (section) section.style.display = (perCategoryCount[key] === 0) ? 'none' : '';
        });
    } else {
        Object.entries(sections).forEach(([key, section]) => {
            if (section) section.style.display = (key === currentCategory) ? '' : 'none';
        });
    }

    const noResults = document.getElementById('noResults');
    if (visibleCount === 0) noResults.classList.add('show');
    else noResults.classList.remove('show');

    const searchInfo = document.getElementById('searchInfo');
    const searchInfoTerm = document.getElementById('searchInfoTerm');
    if (currentSearchTerm) {
        searchInfo.classList.add('show');
        searchInfoTerm.textContent = `"${currentSearchTerm}" (${visibleCount} result${visibleCount !== 1 ? 's' : ''})`;
    } else {
        searchInfo.classList.remove('show');
    }
}

// ==========================
// BUILD PRODUCT CARDS WITH SLIDER
// ==========================
document.addEventListener('DOMContentLoaded', function() {
    startCountdown();
    loadCart();

    const perfumeGrid = document.getElementById('perfumeGrid');
    const testerGrid = document.getElementById('testerGrid');
    const watchGrid = document.getElementById('watchGrid');
    const glassGrid = document.getElementById('glassGrid');

    allProductsData.forEach(product => {
        const category = getProductCategory(product);
        const stock = parseInt(product.stock) || 0;
        const isOutOfStock = stock <= 0;
        const isLowStock = stock > 0 && stock < 10;
        
        const discountPercent = parseFloat(product.discount_percent) || 0;
        const originalPrice = parseFloat(product.price);
        const salePrice = product.sale_price ? parseFloat(product.sale_price) : originalPrice;
        const isOnSale = discountPercent > 0;
        
        let images = [];
        if (product.image_url) images.push(product.image_url);
        if (product.images && product.images.length > 0) {
            product.images.forEach(img => {
                if (img.image_url && !images.includes(img.image_url)) {
                    images.push(img.image_url);
                }
            });
        }
        if (images.length === 0) images.push('https://via.placeholder.com/300');
        
        const col = document.createElement('div');
        col.className = 'product-card' + (isOutOfStock ? ' sold-out' : '');
        col.setAttribute('data-category', category);
        col.setAttribute('data-name', product.name);
        col.setAttribute('data-price', product.price);
        col.setAttribute('data-stock', stock);
        col.setAttribute('data-id', product.id);
        
        const safeName = product.name.replace(/'/g, "\\'");
        
        let stockBadge = '';
        if (isOutOfStock) {
            stockBadge = '<span class="stock-badge out-of-stock"><i class="bi bi-x-circle-fill"></i> SOLD OUT</span>';
        } else if (isLowStock) {
            stockBadge = `<span class="stock-badge low-stock"><i class="bi bi-exclamation-triangle-fill"></i> Only ${stock} left!</span>`;
        } else {
            stockBadge = '<span class="stock-badge in-stock"><i class="bi bi-check-circle-fill"></i> In Stock</span>';
        }
        
        let discountBadge = '';
        if (isOnSale) {
            discountBadge = `<div class="discount-badge-card"><i class="bi bi-tag-fill"></i> -${discountPercent}%</div>`;
        }
        
        let sliderHtml = `
            <div class="product-image-slider">
                <div class="product-slider-track">
                    ${images.map(img => `<img src="${img}" alt="${product.name}" loading="lazy" draggable="false">`).join('')}
                </div>
        `;
        
        if (images.length > 1) {
            sliderHtml += `
                <button class="slider-arrow prev" onclick="event.stopPropagation();">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <button class="slider-arrow next" onclick="event.stopPropagation();">
                    <i class="bi bi-chevron-right"></i>
                </button>
                <div class="slider-dots">
                    ${images.map((_, i) => `<button class="slider-dot ${i === 0 ? 'active' : ''}" data-index="${i}"></button>`).join('')}
                </div>
                <div class="multi-image-badge">
                    <i class="bi bi-images"></i> ${images.length}
                </div>
            `;
        }
        
        sliderHtml += '</div>';
        
        let stockInfo = '';
        if (isOutOfStock) {
            stockInfo = '<div class="stock-info out"><i class="bi bi-x-circle"></i>Out of Stock</div>';
        } else if (isLowStock) {
            stockInfo = `<div class="stock-info low"><i class="bi bi-fire"></i>Hurry! Only ${stock} left</div>`;
        } else {
            stockInfo = `<div class="stock-info"><i class="bi bi-box-seam"></i>${stock} in stock</div>`;
        }
        
        let priceHtml = '';
        if (isOnSale) {
            priceHtml = `
                <div class="product-price-area">
                    <span class="price-sale">PKR ${salePrice.toFixed(2)}</span>
                    <span class="price-original">PKR ${originalPrice.toFixed(2)}</span>
                </div>
            `;
        } else {
            priceHtml = `
                <div class="product-price-area">
                    <span class="price-normal">PKR ${originalPrice.toFixed(2)}</span>
                </div>
            `;
        }
        
        let actionButtons = '';
        if (isOutOfStock) {
            actionButtons = `
                <button class="btn-gold btn-sm" disabled style="opacity:0.5;cursor:not-allowed;">
                    <i class="bi bi-x-circle"></i> Sold Out
                </button>
            `;
        } else {
            actionButtons = `
                <button class="btn-gold btn-sm" onclick="event.stopPropagation(); addToCart(${product.id}, '${safeName}', ${salePrice}, '${product.image_url}', ${stock})">
                    <i class="bi bi-bag-plus"></i>
                </button>
                <button class="btn-outline-gold btn-sm" onclick="event.stopPropagation(); addToWishlist(${product.id}, '${safeName}', ${product.price}, '${product.image_url}')">
                    <i class="bi bi-heart"></i>
                </button>
            `;
        }
        
        col.innerHTML = `
            ${stockBadge}
            ${discountBadge}
            ${sliderHtml}
            <div class="product-body">
                <div class="product-rating">
                    <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i>
                    <span>(4.5)</span>
                </div>
                <h5 class="product-title">${product.name}</h5>
                ${priceHtml}
                ${stockInfo}
                <div class="product-actions">
                    ${actionButtons}
                </div>
            </div>
        `;
        
        col.addEventListener('click', function(e) {
            if (e.target.closest('button')) return;
            openQuickView(product.id);
        });

        if (category === 'tester') testerGrid.appendChild(col);
        else if (category === 'watch') watchGrid.appendChild(col);
        else if (category === 'glass') glassGrid.appendChild(col);
        else perfumeGrid.appendChild(col);
    });

    applyFilters();
    initCardSliders();
});

// ==========================
// EXISTING FUNCTIONS
// ==========================
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

window.addEventListener('scroll', function() {
    const scrollBtn = document.getElementById('scrollToTop');
    if (window.scrollY > 300) scrollBtn.style.display = 'flex';
    else scrollBtn.style.display = 'none';
});

function adminLogin() {
    const user = document.getElementById('adminUser').value;
    const pass = document.getElementById('adminPass').value;
    fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=admin_login&username=' + encodeURIComponent(user) + '&password=' + encodeURIComponent(pass)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Admin logged in successfully!');
            location.reload();
        } else {
            alert('Invalid credentials!');
        }
    });
}

function closeAdminLogin() {
    document.getElementById('adminLoginOverlay').style.display = 'none';
}

function startCountdown() {
    const hoursEl = document.getElementById('hours');
    const minutesEl = document.getElementById('minutes');
    const secondsEl = document.getElementById('seconds');

    let savedTime = localStorage.getItem('luxeTimer');
    let hours = 12, minutes = 45, seconds = 30;

    if (savedTime) {
        const parts = savedTime.split(':');
        hours = parseInt(parts[0]);
        minutes = parseInt(parts[1]);
        seconds = parseInt(parts[2]);
    }

    function tick() {
        if (seconds > 0) seconds--;
        else if (minutes > 0) { minutes--; seconds = 59; }
        else if (hours > 0) { hours--; minutes = 59; seconds = 59; }
        else { hours = 24; minutes = 0; seconds = 0; }

        hoursEl.textContent = hours.toString().padStart(2, '0');
        minutesEl.textContent = minutes.toString().padStart(2, '0');
        secondsEl.textContent = seconds.toString().padStart(2, '0');
        localStorage.setItem('luxeTimer', `${hours}:${minutes}:${seconds}`);
    }

    setInterval(tick, 1000);
}

let cart = [];

function loadCart() {
    const saved = localStorage.getItem('luxeCart');
    if (saved) {
        cart = JSON.parse(saved);
        updateCartUI();
    }
}

function saveCart() {
    localStorage.setItem('luxeCart', JSON.stringify(cart));
}

function addToCart(productId, productName, productPrice, productImage, productStock = null) {
    const product = allProductsData.find(p => p.id == productId);
    const currentStock = productStock !== null ? productStock : (product ? parseInt(product.stock) : 999);
    
    if (currentStock <= 0) {
        showToast('❌ Product is out of stock!', 'error');
        return;
    }
    
    let priceToUse = parseFloat(productPrice);
    let originalPrice = null;
    let discountPercent = 0;
    
    if (product) {
        discountPercent = parseFloat(product.discount_percent) || 0;
        originalPrice = parseFloat(product.price);
        if (discountPercent > 0 && product.sale_price) {
            priceToUse = parseFloat(product.sale_price);
        }
    }
    
    const existing = cart.find(item => item.id == productId);
    const currentQtyInCart = existing ? existing.quantity : 0;
    
    if (currentQtyInCart + 1 > currentStock) {
        showToast(`⚠️ Only ${currentStock} available in stock!`, 'warning');
        return;
    }
    
    if (existing) existing.quantity += 1;
    else {
        cart.push({
            id: productId,
            name: productName,
            price: priceToUse,
            original_price: originalPrice,
            discount_percent: discountPercent,
            image: productImage || 'https://via.placeholder.com/60',
            quantity: 1,
            maxStock: currentStock
        });
    }
    saveCart();
    updateCartUI();
    showToast('✅ Product added to cart!', 'success');
}

function removeFromCart(productId) {
    cart = cart.filter(item => item.id != productId);
    saveCart();
    updateCartUI();
    showToast('Product removed from cart!', 'warning');
}

function updateQuantity(productId, change) {
    const item = cart.find(item => item.id == productId);
    if (item) {
        const newQty = item.quantity + change;
        if (change > 0) {
            const product = allProductsData.find(p => p.id == productId);
            const stock = product ? parseInt(product.stock) : 999;
            if (newQty > stock) {
                showToast(`⚠️ Only ${stock} available in stock!`, 'warning');
                return;
            }
        }
        item.quantity = newQty;
        if (item.quantity <= 0) removeFromCart(productId);
        else { saveCart(); updateCartUI(); }
    }
}

function updateCartUI() {
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');
    const cartBadge = document.querySelector('.cart-badge');

    if (cart.length === 0) {
        cartItems.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-cart3 fs-1 d-block mb-3"></i>
                Your cart is empty.
            </div>
        `;
        cartTotal.textContent = 'PKR 0.00';
        if (cartBadge) cartBadge.style.display = 'none';
        return;
    }

    let total = 0;
    let itemsHtml = '';

    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        
        let priceDisplay = `PKR ${item.price.toFixed(2)}`;
        if (item.discount_percent > 0 && item.original_price) {
            priceDisplay = `
                <span style="color:#ef4444;font-weight:700;">PKR ${item.price.toFixed(2)}</span>
                <span style="text-decoration:line-through;color:#8892b0;font-size:0.7rem;margin-left:5px;">PKR ${item.original_price.toFixed(2)}</span>
            `;
        }
        
        itemsHtml += `
            <div class="cart-item">
                <img src="${item.image}" alt="${item.name}">
                <div class="item-details">
                    <h6>${item.name}</h6>
                    <p>${priceDisplay} x ${item.quantity}</p>
                </div>
                <div class="item-actions">
                    <button onclick="updateQuantity(${item.id}, -1)"><i class="bi bi-dash-circle"></i></button>
                    <span>${item.quantity}</span>
                    <button onclick="updateQuantity(${item.id}, 1)"><i class="bi bi-plus-circle"></i></button>
                    <button onclick="removeFromCart(${item.id})"><i class="bi bi-trash3 text-danger"></i></button>
                </div>
            </div>
        `;
    });

    cartItems.innerHTML = itemsHtml;
    cartTotal.textContent = 'PKR ' + total.toFixed(2);
    
    if (cartBadge) {
        cartBadge.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
        cartBadge.style.display = 'block';
    }
}

function openCart() {
    document.getElementById('cartOverlay').classList.add('active');
    document.getElementById('cartSidebar').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeCart() {
    document.getElementById('cartOverlay').classList.remove('active');
    document.getElementById('cartSidebar').classList.remove('active');
    document.body.style.overflow = 'auto';
}

function checkout() {
    if (cart.length === 0) {
        showToast('Your cart is empty!', 'warning');
        return;
    }
    localStorage.setItem('luxeCart', JSON.stringify(cart));
    window.location.href = 'checkout.php';
}

function addToWishlist(productId, productName, productPrice, productImage) {
    fetch('index.php?ajax=1&action=get_client')
    .then(res => res.json())
    .then(client => {
        if (!client) {
            alert('Please login to add items to wishlist.');
            window.location.href = 'login.php';
            return;
        }
        fetch('index.php?ajax=1&action=add_to_wishlist', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'product_id=' + productId
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) showToast(data.message, 'success');
            else showToast(data.message, 'warning');
        });
    });
}

function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast-item ${type}`;
    toast.style.cssText = `
        background: ${type === 'success' ? 'linear-gradient(135deg,#10b981,#059669)' : type === 'warning' ? 'linear-gradient(135deg,#f59e0b,#d97706)' : type === 'error' ? 'linear-gradient(135deg,#ef4444,#dc2626)' : 'linear-gradient(135deg,#d4af37,#b8941f)'};
        color: white;
        padding: 14px 22px;
        border-radius: 50px;
        font-weight: 600;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        animation: slideInRight 0.3s ease;
        font-size: 0.9rem;
    `;
    toast.innerHTML = message;
    toastContainer.appendChild(toast);
    setTimeout(() => {
        toast.style.transform = 'translateX(150%)';
        toast.style.transition = 'transform 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.style.cssText = `
        position: fixed; top: 80px; right: 20px; z-index: 99999;
        display: flex; flex-direction: column; gap: 8px;
    `;
    document.body.appendChild(container);
    return container;
}

// ==========================================
// THEME TOGGLE
// ==========================================
function toggleTheme() {
    const chatWindow = document.getElementById('chatWindow');
    const themeIcon = document.getElementById('themeIcon');
    chatWindow.classList.toggle('dark-theme');
    const isDark = chatWindow.classList.contains('dark-theme');
    if (isDark) themeIcon.className = 'bi bi-sun-fill';
    else themeIcon.className = 'bi bi-moon-stars-fill';
    localStorage.setItem('luxeChatTheme', isDark ? 'dark' : 'light');
    themeIcon.style.transform = 'scale(1.4) rotate(360deg)';
    setTimeout(() => { themeIcon.style.transform = 'scale(1) rotate(0deg)'; }, 400);
}

(function loadTheme() {
    const savedTheme = localStorage.getItem('luxeChatTheme');
    if (savedTheme === 'dark') {
        document.getElementById('chatWindow').classList.add('dark-theme');
        document.getElementById('themeIcon').className = 'bi bi-sun-fill';
    }
})();

// ==========================================
// PRELOADER
// ==========================================
(function() {
    const preloader = document.getElementById('aysPreloader');
    const loaderBar = document.querySelector('.loader-bar');
    const percentageEl = document.querySelector('.loader-percentage');
    const body = document.body;
    
    body.style.overflow = 'hidden';
    let progress = 0;
    const loadingSpeed = () => Math.random() * 5 + 2;
    
    const progressInterval = setInterval(() => {
        progress += loadingSpeed();
        if (progress >= 100) {
            progress = 100;
            clearInterval(progressInterval);
            loaderBar.style.width = '100%';
            percentageEl.textContent = '100%';
            setTimeout(() => {
                preloader.classList.add('exiting');
                setTimeout(() => {
                    preloader.classList.add('hidden');
                    body.style.overflow = 'auto';
                    setTimeout(() => {
                        preloader.style.display = 'none';
                        document.body.classList.add('website-loaded');
                    }, 1200);
                }, 700);
            }, 900);
        } else {
            loaderBar.style.width = progress + '%';
            percentageEl.textContent = Math.floor(progress) + '%';
        }
    }, 90);
    
    setTimeout(() => {
        if (!preloader.classList.contains('hidden')) {
            clearInterval(progressInterval);
            preloader.classList.add('hidden');
            body.style.overflow = 'auto';
            document.body.classList.add('website-loaded');
            setTimeout(() => { preloader.style.display = 'none'; }, 1000);
        }
    }, 12000);
})();

// ==========================================
// SMART MULTILINGUAL CHATBOT - FULL VERSION
// ==========================================
let currentLanguage = 'en';

function toggleChat() {
    const window = document.getElementById('chatWindow');
    window.classList.toggle('open');
    if (window.classList.contains('open')) {
        document.getElementById('chatInput').focus();
    }
}

function isUrduScript(text) {
    const urduRegex = /[\u0600-\u06FF\u0750-\u077F\uFB50-\uFDFF\uFE70-\uFEFF]/;
    return urduRegex.test(text);
}

function isRomanUrdu(text) {
    const t = text.toLowerCase();
    const romanKeywords = [
        'kya', 'kia', 'kyaa', 'hai', 'hain', 'ha', 'he', 'ho', 'hun', 'hoon',
        'kitne', 'kitna', 'kitni', 'ka', 'ki', 'ke', 'ko', 'se', 'me', 'mein',
        'ye', 'yeh', 'wo', 'woh', 'is', 'us', 'mujhe', 'muje', 'mujhay', 'tumhe',
        'aap', 'ap', 'tum', 'main', 'mein', 'hum', 'ham',
        'chahiye', 'chahiyay', 'chahta', 'chahti', 'chahte',
        'batao', 'bataen', 'bata', 'bolo', 'bol', 'sunao', 'suna',
        'konsa', 'konsi', 'konsay', 'kaunsa', 'kaun', 'kab', 'kahan', 'kaha',
        'kaise', 'kaisay', 'kyun', 'kyu', 'kiun', 'kyunke',
        'milega', 'milegi', 'milta', 'milti', 'mile',
        'dijiye', 'dedo', 'do', 'dena', 'dein', 'dunga', 'dega',
        'karo', 'karein', 'kar', 'karna', 'karne',
        'nahi', 'nahin', 'na', 'haan', 'han', 'jee',
        'perfume', 'khushbu', 'khushboo', 'itr', 'attar',
        'ghari', 'ghadi', 'watch', 'chashma', 'chashmay', 'ainak',
        'tester', 'paise', 'paisa', 'price', 'rate', 'qemat', 'qeemat',
        'order', 'dilivery', 'delivery', 'wapsi', 'wapas',
        'shukriya', 'shukria', 'mehrbani', 'meharbani',
        'salam', 'assalam', 'adaab', 'khuda', 'hafiz',
        'kaisa', 'kaisi', 'kaisay', 'acha', 'achi', 'achay', 'behtreen', 'behtareen',
        'zyada', 'ziyada', 'kam', 'bohat', 'bohot', 'bahut',
        'naya', 'nayi', 'naye', 'purana', 'purani',
        'sab', 'sabse', 'sirf', 'bas', 'abhi', 'phir',
        'aur', 'ya', 'lekin', 'magar', 'agar', 'to', 'tu',
        'dikhao', 'dikha', 'show', 'lagao', 'lagay',
        'kharidna', 'kharid', 'khareed', 'lena', 'layna', 'leni',
        'available', 'stock', 'ready', 'ab', 'abhi',
        'sasta', 'sasti', 'mehnga', 'mehngi', 'sale', 'discount', 'off',
        'kaam', 'kaamka', 'kaamki',
        'ban', 'bana', 'bani', 'banaya',
        'haal', 'hal', 'chal', 'chalta',
        'subah', 'shaam', 'raat', 'dopahar',
        'assalamualaikum', 'walaikum', 'alaikum',
        'khush', 'aamdeed', 'aamdid', 'khushamdeed'
    ];
    
    const words = t.split(/\s+/);
    let romanMatchCount = 0;
    
    words.forEach(word => {
        const clean = word.replace(/[^a-z]/g, '');
        if (clean.length >= 2 && romanKeywords.includes(clean)) romanMatchCount++;
    });
    
    return romanMatchCount >= 1 && !isUrduScript(text);
}

function detectLanguage(text) {
    if (isUrduScript(text)) return 'ur';
    if (isRomanUrdu(text)) return 'roman';
    return 'en';
}

function getTimeOfDay() {
    const hour = new Date().getHours();
    if (hour < 12) return 'morning';
    if (hour < 17) return 'afternoon';
    if (hour < 21) return 'evening';
    return 'night';
}

function randomPick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

function getGreetingResponse(lang) {
    const timeOfDay = getTimeOfDay();
    
    if (lang === 'ur') {
        const urduGreetings = {
            morning: ['🌅 صبح بخیر! <strong>Mr.AYS</strong> میں خوش آمدید۔<br>آپ کی کیا مدد کر سکتا ہوں؟', '☀️ صبح بخیر! آج آپ کیسے ہیں؟<br>Mr.AYS میں آپ کا استقبال ہے!'],
            afternoon: ['🌤️ دوپہر بخیر! <strong>Mr.AYS</strong> میں خوش آمدید۔<br>کیا ڈھونڈ رہے ہیں آپ؟', '☀️ اچھی دوپہر! آج کیا خریدنا چاہیں گے؟'],
            evening: ['🌆 شام بخیر! <strong>Mr.AYS</strong> میں خوش آمدید۔<br>کیا مدد کر سکتا ہوں؟', '🌙 اچھی شام! آج کیا دیکھنا چاہیں گے؟'],
            night: ['🌙 شب بخیر! <strong>Mr.AYS</strong> میں خوش آمدید۔<br>کیا ڈھونڈ رہے ہیں؟', '✨ رات بخیر! آپ کی کیا مدد کر سکتا ہوں؟']
        };
        return randomPick(urduGreetings[timeOfDay]);
    }
    
    if (lang === 'roman') {
        const romanGreetings = {
            morning: ['🌅 Subah bakhair! <strong>Mr.AYS</strong> mein khush aamdeed.<br>Main aap ki kya madad kar sakta hoon? 😊', '☀️ Good morning! Aaj aap kaise hain?<br>Mr.AYS mein aap ka istaqbaal hai!'],
            afternoon: ['🌤️ Dopahar bakhair! <strong>Mr.AYS</strong> mein khush aamdeed.<br>Kya dhoondh rahe hain aap?', '☀️ Achi dopahar! Aaj kya khareedna chahenge?'],
            evening: ['🌆 Shaam bakhair! <strong>Mr.AYS</strong> mein khush aamdeed.<br>Kya madad kar sakta hoon?', '🌙 Achi shaam! Aaj kya dekhna chahenge?'],
            night: ['🌙 Shab bakhair! <strong>Mr.AYS</strong> mein khush aamdeed.<br>Kya dhoondh rahe hain?', '✨ Raat bakhair! Aap ki kya madad kar sakta hoon?']
        };
        return randomPick(romanGreetings[timeOfDay]);
    }
    
    const engGreetings = {
        morning: ['🌅 Good morning! Welcome to <strong>Mr.AYS</strong>.<br>How can I help you today? 😊', '☀️ Good morning! Hope you\'re having a wonderful day.<br>What can I do for you?'],
        afternoon: ['🌤️ Good afternoon! Welcome to <strong>Mr.AYS</strong>.<br>What are you looking for today?', '☀️ Good afternoon! Ready to explore our luxury collection?'],
        evening: ['🌆 Good evening! Welcome to <strong>Mr.AYS</strong>.<br>How may I assist you?', '🌙 Good evening! Looking for something special?'],
        night: ['🌙 Good night! Welcome to <strong>Mr.AYS</strong>.<br>What brings you here tonight?', '✨ Good evening! How can I help you?']
    };
    return randomPick(engGreetings[timeOfDay]);
}

function isGreeting(text) {
    const t = text.toLowerCase().trim();
    const greetingPhrases = [
        'hello', 'hi', 'hey', 'heyy', 'hii', 'helo',
        'good morning', 'good afternoon', 'good evening', 'good night',
        'greetings', 'howdy', 'sup', 'whats up', "what's up",
        'how are you', 'how r u', 'how are u', 'hows it going',
        'سلام', 'السلام علیکم', 'وعلیکم السلام', 'ہیلو', 'ہائے', 'خوش آمدید',
        'صبح بخیر', 'شام بخیر', 'شب بخیر', 'دوپہر بخیر',
        'کیا حال ہے', 'کیسے ہیں', 'کیسی ہیں',
        'salam', 'assalam', 'assalamualaikum', 'assalam o alaikum',
        'asalam', 'aoa', 'adaab', 'adab',
        'subah bakhair', 'sham bakhair', 'shab bakhair',
        'kaise ho', 'kaisi ho', 'kaisay ho', 'kya haal', 'kya hal',
        'kya haal hai', 'kia hal hai', 'haal chal', 'hal chal',
        'kya chal raha', 'kia chal raha',
        'khush aamdeed', 'khushamdeed'
    ];
    return greetingPhrases.some(phrase => t.includes(phrase));
}

function updateLanguageIndicator(lang) {
    const indicator = document.getElementById('langIndicator');
    if (lang === 'ur') {
        indicator.textContent = 'اردو';
        indicator.style.background = 'rgba(34, 197, 94, 0.2)';
        indicator.style.color = '#22c55e';
        indicator.style.borderColor = 'rgba(34, 197, 94, 0.3)';
    } else if (lang === 'roman') {
        indicator.textContent = 'Roman';
        indicator.style.background = 'rgba(59, 130, 246, 0.2)';
        indicator.style.color = '#3b82f6';
        indicator.style.borderColor = 'rgba(59, 130, 246, 0.3)';
    } else {
        indicator.textContent = 'EN';
        indicator.style.background = 'rgba(212, 175, 55, 0.2)';
        indicator.style.color = 'var(--accent-gold)';
        indicator.style.borderColor = 'rgba(212, 175, 55, 0.3)';
    }
}

function sendMessage() {
    const input = document.getElementById('chatInput');
    const msg = input.value.trim();
    if (msg === '') return;
    
    const lang = detectLanguage(msg);
    currentLanguage = lang;
    updateLanguageIndicator(lang);
    
    addMessage(msg, 'user', lang);
    input.value = '';
    
    showTypingDots();
    setTimeout(() => {
        hideTypingDots();
        const response = getBotResponse(msg, lang);
        addMessage(response.text, 'bot', lang);
    }, 1400);
}

function sendQuickMessage(msg) {
    const lang = detectLanguage(msg);
    currentLanguage = lang;
    updateLanguageIndicator(lang);
    
    addMessage(msg, 'user', lang);
    showTypingDots();
    setTimeout(() => {
        hideTypingDots();
        const response = getBotResponse(msg, lang);
        addMessage(response.text, 'bot', lang);
    }, 1400);
}

function addMessage(text, sender, lang = 'en') {
    const body = document.getElementById('chatBody');
    const div = document.createElement('div');
    div.className = `message ${sender}`;
    if (lang === 'ur') div.classList.add('urdu');
    else if (lang === 'roman') div.classList.add('roman-urdu');
    div.innerHTML = text;
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
}

function showTypingDots() {
    const body = document.getElementById('chatBody');
    const dots = document.createElement('div');
    dots.className = 'typing-dots';
    dots.id = 'typingDots';
    dots.innerHTML = '<span></span><span></span><span></span>';
    body.appendChild(dots);
    body.scrollTop = body.scrollHeight;
}

function hideTypingDots() {
    const dots = document.getElementById('typingDots');
    if (dots) dots.remove();
}

// ==========================================
// PRODUCT HELPERS
// ==========================================
function getBestSellers(limit = 3) {
    const sorted = [...allProductsData].sort((a, b) => {
        const aTest = a.name.toLowerCase().includes('tester') ? 1 : 0;
        const bTest = b.name.toLowerCase().includes('tester') ? 1 : 0;
        if (aTest !== bTest) return aTest - bTest;
        return parseFloat(b.price) - parseFloat(a.price);
    });
    return sorted.slice(0, limit);
}

function getNewestProducts(limit = 3) {
    return [...allProductsData].sort((a, b) => b.id - a.id).slice(0, limit);
}

function getPerfumes(limit = 3) {
    return allProductsData.filter(p => {
        const n = p.name.toLowerCase();
        return !n.includes('tester') && !n.includes('watch') && !n.includes('glass') && !n.includes('eyewear');
    }).slice(0, limit);
}

function getWatches(limit = 3) {
    return allProductsData.filter(p => p.name.toLowerCase().includes('watch')).slice(0, limit);
}

function getGlasses(limit = 3) {
    return allProductsData.filter(p => {
        const n = p.name.toLowerCase();
        return n.includes('glass') || n.includes('eyewear');
    }).slice(0, limit);
}

function getTesters(limit = 3) {
    return allProductsData.filter(p => p.name.toLowerCase().includes('tester')).slice(0, limit);
}

function getOnSaleProducts(limit = 5) {
    return allProductsData.filter(p => parseFloat(p.discount_percent) > 0).slice(0, limit);
}

function formatProductPrice(p) {
    const discountPercent = parseFloat(p.discount_percent) || 0;
    const originalPrice = parseFloat(p.price);
    const salePrice = p.sale_price ? parseFloat(p.sale_price) : originalPrice;
    
    if (discountPercent > 0) {
        return `<span style="color:#ef4444;font-weight:800;">PKR ${salePrice.toFixed(2)}</span> <span style="text-decoration:line-through;color:#8892b0;font-size:0.85em;">PKR ${originalPrice.toFixed(2)}</span> <span style="background:#ef4444;color:white;padding:2px 6px;border-radius:10px;font-size:0.7em;font-weight:800;">-${discountPercent}%</span>`;
    }
    return `<span style="color:#d4af37;font-weight:700;">PKR ${originalPrice.toFixed(2)}</span>`;
}

function buildProductList(products, lang) {
    if (products.length === 0) {
        if (lang === 'ur') return 'معذرت، اس وقت کوئی پروڈکٹ دستیاب نہیں ہے۔';
        if (lang === 'roman') return 'Maazrat, is waqt koi product available nahi hai.';
        return 'Sorry, no products available right now.';
    }
    let html = '';
    products.forEach(p => {
        const stock = parseInt(p.stock) || 0;
        let stockTag = '';
        if (stock <= 0) {
            stockTag = lang === 'ur' ? ' <span style="color:#ef4444;">[ختم]</span>' : ' <span style="color:#ef4444;">[Sold Out]</span>';
        } else if (stock < 10) {
            stockTag = lang === 'ur' ? ` <span style="color:#f59e0b;">[صرف ${stock} باقی]</span>` : ` <span style="color:#f59e0b;">[Only ${stock} left]</span>`;
        }
        html += `• <strong>${p.name}</strong> — ${formatProductPrice(p)}${stockTag}<br>`;
    });
    return html;
}

// ==========================================
// NEW ARRIVALS - TOP 3 WITH MEDALS
// ==========================================
function buildNewArrivalsHTML(products, lang) {
    if (products.length === 0) {
        if (lang === 'ur') return '✨ <strong>اس وقت کوئی نئی آمد نہیں ہے۔</strong><br>براہ کرم تھوڑی دیر بعد چیک کریں یا ہم سے رابطہ کریں۔';
        if (lang === 'roman') return '✨ <strong>Is waqt koi nayi arrival nahi hai.</strong><br>Please thori der baad check karein ya hum se rabta karein.';
        return '✨ <strong>No new arrivals right now.</strong><br>Please check back soon or contact us for details.';
    }
    
    let headerText = '';
    let footerText = '';
    let containerStyle = 'background: rgba(212,175,55,0.08); padding: 12px; border-radius: 10px; border-left: 3px solid #d4af37;';
    
    if (lang === 'ur') {
        headerText = '✨ <strong>🌟 ہماری ٹاپ 3 نئی آمد:</strong><br><br>';
        footerText = '<br>🔥 <em>یہ ہماری سب سے نئی پرفیوم ہیں! کیا آپ ان میں سے کوئی خریدنا چاہیں گے؟</em>';
        containerStyle += ' direction: rtl; text-align: right;';
    } else if (lang === 'roman') {
        headerText = '✨ <strong>🌟 Hamari Top 3 Nayi Arrivals:</strong><br><br>';
        footerText = '<br>🔥 <em>Ye hamari sab se nayi perfumes hain! Kya aap in mein se koi khareedna chahenge?</em>';
    } else {
        headerText = '✨ <strong>🌟 Our Top 3 Latest Arrivals:</strong><br><br>';
        footerText = '<br>🔥 <em>These are our newest perfumes! Would you like to purchase any of them?</em>';
    }
    
    let html = headerText + '<div style="' + containerStyle + '">';
    
    products.forEach((p, index) => {
        const stock = parseInt(p.stock) || 0;
        const medal = ['🥇', '🥈', '🥉'][index] || '•';
        
        let stockTag = '';
        if (stock > 0) {
            if (lang === 'ur') stockTag = `<span style="color:#10b981; font-size:0.75rem;">✅ اسٹاک میں (${stock})</span>`;
            else if (lang === 'roman') stockTag = `<span style="color:#10b981; font-size:0.75rem;">✅ Stock mein (${stock})</span>`;
            else stockTag = `<span style="color:#10b981; font-size:0.75rem;">✅ In Stock (${stock})</span>`;
        } else {
            if (lang === 'ur') stockTag = `<span style="color:#ef4444; font-size:0.75rem;">❌ اسٹاک ختم</span>`;
            else if (lang === 'roman') stockTag = `<span style="color:#ef4444; font-size:0.75rem;">❌ Stock khatam</span>`;
            else stockTag = `<span style="color:#ef4444; font-size:0.75rem;">❌ Sold Out</span>`;
        }
        
        const desc = p.description ? `<div style="font-size:0.78rem; color:#8892b0; margin-top:4px;">${p.description.substring(0, 80)}${p.description.length > 80 ? '...' : ''}</div>` : '';
        
        html += `
            <div style="padding: 10px 0; border-bottom: 1px dashed rgba(212,175,55,0.2);">
                <div style="font-size: 0.95rem; font-weight: 700;">
                    ${medal} ${index + 1}. <strong>${p.name}</strong>
                </div>
                <div style="margin-top: 4px;">
                    💰 ${formatProductPrice(p)}
                </div>
                <div style="margin-top: 4px;">${stockTag}</div>
                ${desc}
            </div>
        `;
    });
    
    html += '</div>' + footerText;
    return html;
}

// ==========================================
// UNKNOWN QUESTION HANDLER
// ==========================================
function getUnknownResponse(lang, userInput) {
    const contactInfo = `
        <div style="background: rgba(212,175,55,0.1); padding: 12px; border-radius: 10px; margin-top: 12px; border-left: 3px solid #d4af37;">
            <div style="font-weight: 700; margin-bottom: 8px; color: #d4af37;">
                <i class="bi bi-headset"></i> Contact Support
            </div>
            <div style="font-size: 0.85rem; line-height: 1.8;">
                📧 <strong>Email:</strong> mrays@gmail.com<br>
                📱 <strong>Phone:</strong> 03263368118<br>
                💬 <strong>WhatsApp:</strong> 03263368118<br>
                🏢 <strong>Address:</strong> Awan Town, Lahore
            </div>
        </div>
    `;

    const predefinedQuestions = {
        en: `<div style="margin-top: 10px; font-size: 0.85rem;">
            <strong style="color: #d4af37;">📋 You can ask me:</strong><br>
            • 🔥 Best selling perfumes<br>
            • ✨ Latest new arrivals<br>
            • 🌟 Product recommendations<br>
            • 💰 Product prices<br>
            • 🎁 Discounts & offers<br>
            • 📦 Order status<br>
            • 🚚 Shipping & delivery<br>
            • 💳 Payment methods<br>
            • 📞 Contact information
        </div>`,
        ur: `<div style="margin-top: 10px; font-size: 0.85rem; direction: rtl; text-align: right;">
            <strong style="color: #d4af37;">📋 آپ مجھ سے یہ پوچھ سکتے ہیں:</strong><br>
            • 🔥 بہترین فروخت ہونے والے پرفیوم<br>
            • ✨ نئی آمد<br>
            • 🌟 پروڈکٹ کی تجاویز<br>
            • 💰 پروڈکٹ کی قیمتیں<br>
            • 🎁 رعایات اور آفرز<br>
            • 📦 آرڈر کی صورتحال<br>
            • 🚚 شپنگ اور ڈیلیوری<br>
            • 💳 ادائیگی کے طریقے<br>
            • 📞 رابطے کی معلومات
        </div>`,
        roman: `<div style="margin-top: 10px; font-size: 0.85rem;">
            <strong style="color: #d4af37;">📋 Aap mujh se ye pooch sakte hain:</strong><br>
            • 🔥 Best selling perfumes<br>
            • ✨ Nayi arrivals<br>
            • 🌟 Product recommendations<br>
            • 💰 Product ki qeemtein<br>
            • 🎁 Discounts aur offers<br>
            • 📦 Order ki status<br>
            • 🚚 Shipping aur delivery<br>
            • 💳 Payment methods<br>
            • 📞 Contact information
        </div>`
    };

    const responses = {
        en: `🤖 <strong>Sorry, I'm not sure about this.</strong><br><br>
            I'm an AI assistant designed to help with <strong>general questions</strong> about our products and services. 
            For this specific query, I don't have enough information.<br><br>
            Could you please ask me something from my <strong>predefined questions</strong>? 
            ${predefinedQuestions.en}
            <br>
            <em style="color: #8892b0;">If you need more detailed knowledge, please contact our support team directly:</em>
            ${contactInfo}`,
        ur: `🤖 <strong>معذرت، مجھے اس بارے میں یقین نہیں ہے۔</strong><br><br>
            میں ایک AI اسسٹنٹ ہوں جو آپ کی <strong>عمومی سوالات</strong> میں مدد کے لیے ڈیزائن کیا گیا ہوں۔
            اس مخصوص سوال کے لیے میرے پاس کافی معلومات نہیں ہیں۔<br><br>
            کیا آپ میرے <strong>پہلے سے طے شدہ سوالات</strong> میں سے کچھ پوچھ سکتے ہیں؟
            ${predefinedQuestions.ur}
            <br>
            <em style="color: #8892b0;">اگر آپ کو مزید تفصیلی معلومات چاہیے تو براہ کرم ہماری سپورٹ ٹیم سے براہ راست رابطہ کریں:</em>
            ${contactInfo}`,
        roman: `🤖 <strong>Sorry to say, mujhe is baray mein yaqeen nahi hai.</strong><br><br>
            Main ek AI assistant hoon jo aap ki <strong>general questions</strong> mein madad ke liye design kiya gaya hoon.
            Is specific sawal ke liye mere paas kaafi maloomat nahi hain.<br><br>
            Kya aap mere <strong>predefined questions</strong> mein se kuch pooch sakte hain?
            ${predefinedQuestions.roman}
            <br>
            <em style="color: #8892b0;">Agar aap ko zyada detailed knowledge chahiye to please hamari support team se direct contact karein:</em>
            ${contactInfo}`
    };

    return responses[lang] || responses.en;
}

// ==========================================
// BOT RESPONSE ENGINE - FULL
// ==========================================
function getBotResponse(input, lang = 'en') {
    const q = input.toLowerCase().trim();
    
    // REAL-TIME PRODUCT SEARCH
    const matchedProduct = allProductsData.find(p => 
        q.length > 3 && (
            q.includes(p.name.toLowerCase()) || 
            p.name.toLowerCase().includes(q)
        )
    );
    
    if (matchedProduct) {
        const stock = parseInt(matchedProduct.stock) || 0;
        let stockMsg = '';
        
        if (lang === 'ur') {
            stockMsg = stock > 0 ? `✅ دستیاب (${stock} اسٹاک میں)` : '❌ اسٹاک ختم';
            return { text: `🔍 <strong>${matchedProduct.name}</strong><br><br>💰 قیمت: ${formatProductPrice(matchedProduct)}<br>📦 ${stockMsg}<br><br><em>${matchedProduct.description || ''}</em>` };
        }
        if (lang === 'roman') {
            stockMsg = stock > 0 ? `✅ Available (${stock} in stock)` : '❌ Out of stock';
            return { text: `🔍 <strong>${matchedProduct.name}</strong><br><br>💰 Qeemat: ${formatProductPrice(matchedProduct)}<br>📦 ${stockMsg}<br><br><em>${matchedProduct.description || ''}</em>` };
        }
        stockMsg = stock > 0 ? `✅ Available (${stock} in stock)` : '❌ Out of stock';
        return { text: `🔍 <strong>${matchedProduct.name}</strong><br><br>💰 Price: ${formatProductPrice(matchedProduct)}<br>📦 ${stockMsg}<br><br><em>${matchedProduct.description || ''}</em>` };
    }

    // GREETINGS
    if (isGreeting(input)) {
        const t = q;
        
        if (t.match(/how are you|how r u|kya haal|kya hal|kia hal|haal chal|hal chal|کیا حال|کیسے ہیں|کیسی ہیں/)) {
            if (lang === 'ur') return { text: randomPick(['😊 میں بالکل ٹھیک ہوں، شکریہ پوچھنے کے لیے!<br>آپ کیسے ہیں؟ میں آپ کی کیا مدد کر سکتا ہوں؟', '🌟 میں بہت اچھا ہوں! آپ کا دن کیسا جا رہا ہے؟<br>Mr.AYS میں کیا ڈھونڈ رہے ہیں؟']) };
            if (lang === 'roman') return { text: randomPick(['😊 Main bilkul theek hoon, shukriya poochne ke liye!<br>Aap kaise hain?', '🌟 Main bohat acha hoon! Aap ka din kaisa ja raha hai?']) };
            return { text: randomPick(['😊 I\'m doing great, thanks for asking!<br>How are you today?', '🌟 I\'m wonderful! How is your day going?']) };
        }
        
        if (t.match(/good morning|subah bakhair|صبح بخیر/)) {
            if (lang === 'ur') return { text: '🌅 صبح بخیر! <strong>Mr.AYS</strong> میں خوش آمدید۔<br>آپ کی کیا مدد کر سکتا ہوں؟' };
            if (lang === 'roman') return { text: '🌅 Subah bakhair! <strong>Mr.AYS</strong> mein khush aamdeed.<br>Aap ki kya madad kar sakta hoon?' };
            return { text: '🌅 Good morning! Welcome to <strong>Mr.AYS</strong>.<br>How can I help you today?' };
        }
        
        if (t.match(/good afternoon|dopahar bakhair|دوپہر بخیر/)) {
            if (lang === 'ur') return { text: '🌤️ دوپہر بخیر! <strong>Mr.AYS</strong> میں خوش آمدید۔<br>کیا ڈھونڈ رہے ہیں آپ؟' };
            if (lang === 'roman') return { text: '🌤️ Dopahar bakhair! <strong>Mr.AYS</strong> mein khush aamdeed.<br>Kya dhoondh rahe hain aap?' };
            return { text: '🌤️ Good afternoon! Welcome to <strong>Mr.AYS</strong>.<br>What are you looking for today?' };
        }
        
        if (t.match(/good evening|sham bakhair|شام بخیر/)) {
            if (lang === 'ur') return { text: '🌆 شام بخیر! <strong>Mr.AYS</strong> میں خوش آمدید۔<br>کیا مدد کر سکتا ہوں؟' };
            if (lang === 'roman') return { text: '🌆 Shaam bakhair! <strong>Mr.AYS</strong> mein khush aamdeed.<br>Kya madad kar sakta hoon?' };
            return { text: '🌆 Good evening! Welcome to <strong>Mr.AYS</strong>.<br>How may I assist you?' };
        }
        
        if (t.match(/good night|shab bakhair|شب بخیر/)) {
            if (lang === 'ur') return { text: '🌙 شب بخیر! <strong>Mr.AYS</strong> میں خوش آمدید۔<br>کیا ڈھونڈ رہے ہیں؟' };
            if (lang === 'roman') return { text: '🌙 Shab bakhair! <strong>Mr.AYS</strong> mein khush aamdeed.<br>Kya dhoondh rahe hain?' };
            return { text: '🌙 Good night! Welcome to <strong>Mr.AYS</strong>.<br>What brings you here tonight?' };
        }
        
        if (t.match(/salam|assalam|asalam|aoa|adaab|adab|سلام|السلام علیکم|وعلیکم السلام/)) {
            if (lang === 'ur') return { text: randomPick(['☪️ وعلیکم السلام! <strong>Mr.AYS</strong> میں خوش آمدید۔<br>آپ کی کیا خدمت کر سکتا ہوں؟', '☪️ وعلیکم السلام ورحمۃ اللہ!<br>Mr.AYS میں آپ کا خیر مقدم ہے۔ کیا مدد چاہیے؟']) };
            if (lang === 'roman') return { text: randomPick(['☪️ Walaikum Assalam! <strong>Mr.AYS</strong> mein khush aamdeed.<br>Aap ki kya khidmat kar sakta hoon?', '☪️ Walaikum Assalam wa Rehmatullah!<br>Mr.AYS mein aap ka khair maqdam hai. Kya madad chahiye?']) };
            return { text: '☪️ Walaikum Assalam! Welcome to <strong>Mr.AYS</strong>.<br>How can I help you today?' };
        }
        
        if (t.match(/\b(hello|hi|hey|heyy|hii|helo|sup|howdy|ہیلو|ہائے)\b/)) {
            if (lang === 'ur') return { text: randomPick(['👋 ہیلو! <strong>Mr.AYS</strong> میں خوش آمدید۔<br>آپ کی کیا مدد کر سکتا ہوں؟', '👋 ہائے! کیسے ہیں آپ؟<br>Mr.AYS میں آپ کا استقبال ہے!']) };
            if (lang === 'roman') return { text: randomPick(['👋 Hello! <strong>Mr.AYS</strong> mein khush aamdeed.<br>Aap ki kya madad kar sakta hoon?', '👋 Hi! Kaise hain aap?<br>Mr.AYS mein aap ka istaqbaal hai!']) };
            return { text: randomPick(['👋 Hello! Welcome to <strong>Mr.AYS</strong>.<br>How can I help you today?', '🙂 Hi there! Welcome to Mr.AYS.<br>What can I do for you?', '👋 Hey! Great to see you here.<br>How may I assist you today?']) };
        }
        
        if (t.match(/khush aamdeed|khushamdeed|welcome|خوش آمدید/)) return { text: getGreetingResponse(lang) };
        return { text: getGreetingResponse(lang) };
    }

    // ROMAN URDU RESPONSES
    if (lang === 'roman') {
        if (q.match(/(naya|nayi|naye|new|latest|taza|arrival|recent|abhi|haal hi)/)) {
            const products = getNewestProducts(3);
            return { text: buildNewArrivalsHTML(products, 'roman') };
        }
        if (q.match(/(kitne|kitna|kitni|price|rate|qeemat|paise|paisa|kya rate|how much)/)) {
            const products = getBestSellers(3);
            return { text: '💰 <strong>Hamari qeemtein:</strong><br><br>' + buildProductList(products, 'roman') };
        }
        if (q.match(/(best|behtreen|behtareen|sabse|zyada|ziyada|popular|top|selling|famous|mashhoor)/)) {
            const products = getBestSellers(3);
            return { text: '🔥 <strong>Sabse zyada bikne wale perfumes:</strong><br><br>' + buildProductList(products, 'roman') + '<br>Ye hamare sabse pasandeeda perfumes hain! 💫' };
        }
        if (q.match(/(recommend|suggest|batao|bata|konsa|kaunsa|kaun|mashwara|salah)/)) {
            const products = getBestSellers(2);
            return { text: '🌟 <strong>Meri taraf se recommendation:</strong><br><br>' + buildProductList(products, 'roman') + '<br>Ye aap ke liye behtareen hain! 😊' };
        }
        if (q.match(/(perfume|khushbu|khushboo|itr|attar|scent|fragrance|chahiye|chahiyay)/)) {
            const products = getPerfumes(3);
            return { text: '🌸 <strong>Hamare premium perfumes:</strong><br><br>' + buildProductList(products, 'roman') };
        }
        if (q.match(/(watch|ghari|ghadi|time|waqt)/)) {
            const products = getWatches(3);
            return { text: '⌚ <strong>Hamari luxury watches:</strong><br><br>' + buildProductList(products, 'roman') };
        }
        if (q.match(/(glass|chashma|chashmay|ainak|eyewear|sunglass)/)) {
            const products = getGlasses(3);
            return { text: '🕶️ <strong>Hamare premium glasses:</strong><br><br>' + buildProductList(products, 'roman') };
        }
        if (q.match(/(tester|sample|try)/)) {
            const products = getTesters(3);
            return { text: '🧪 <strong>Hamare tester collection:</strong><br><br>' + buildProductList(products, 'roman') };
        }
        if (q.match(/(sale|discount|off|raayat|rabat|sasti|sasta)/)) {
            const products = getOnSaleProducts(5);
            if (products.length === 0) return { text: '🎁 Is waqt koi sale nahi chal rahi. Check back soon!' };
            return { text: '🎁 <strong>Sale par products:</strong><br><br>' + buildProductList(products, 'roman') };
        }
        if (q.match(/(contact|rabta|number|numbar|phone|call|email|whatsapp|helpline)/)) {
            return { text: '📞 <strong>Hum se rabta karein:</strong><br><br>📧 Email: <strong>mrays@gmail.com</strong><br>📱 Phone: <strong>03263368118</strong><br>💬 WhatsApp: <strong>03263368118</strong><br>🏢 Address: Awan Town, Lahore<br><br>Hum 12 ghante mein jawab dete hain! ⏰' };
        }
        if (q.match(/(order|mera order|track|kahan|kaha|delivery|dilivery)/)) {
            return { text: '📦 <strong>Order ki maloomat:</strong><br><br>Apna order track karne ke liye <strong>"My Orders"</strong> page par jayein.<br>Koi masla ho to call karein: <strong>03263368118</strong>' };
        }
        if (q.match(/(return|wapas|wapsi|refund|paise wapas|exchange)/)) {
            return { text: '🔄 <strong>Return Policy:</strong><br><br>Hum <strong>30 din</strong> ki return policy dete hain.<br>Product istemaal na hui ho to wapas kar sakte hain.' };
        }
        if (q.match(/(shipping|delivery|dilivery|free shipping|kitne din)/)) {
            return { text: '🚚 <strong>Shipping ki maloomat:</strong><br><br>✅ PKR 10,000 se zyada order par <strong>free shipping</strong><br>⚡ Express delivery available hai<br>⏰ 1-2 din mein delivery' };
        }
        if (q.match(/(payment|pay|adaigi|paise|card|cash|bank|easypaisa|jazzcash)/)) {
            return { text: '💳 <strong>Payment ke tareeqay:</strong><br><br>✅ Bank Transfer<br>✅ Cash on Delivery<br>✅ EasyPaisa / JazzCash<br>' };
        }
        if (q.match(/(discount|offer|sale|rabat|raayat|rashayat|coupon|promo|sasta)/)) {
            return { text: '🎁 <strong>Maujooda offers:</strong><br><br>🔥 Flash Sale - <strong>30% tak raayat</strong><br>🎉 Pehli kharidari par <strong>10% raayat</strong><br>📧 Newsletter subscribe karein mazeed offers ke liye!' };
        }
        if (q.match(/(wishlist|pasand|favorite|save|dil)/)) {
            return { text: '❤️ <strong>Wishlist:</strong><br><br>Kisi bhi product par <strong>dil ka nishan</strong> daba kar wishlist mein shamil karein.' };
        }
        if (q.match(/(cart|checkout|tokri|basket)/)) {
            return { text: '🛒 <strong>Cart aur Checkout:</strong><br><br>Product cart mein daalein aur <strong>"Proceed to Checkout"</strong> dabayein.' };
        }
        if (q.match(/(timing|timings|waqt|kab|khula|band|open|close|hours)/)) {
            return { text: '🕒 <strong>Hamare timings:</strong><br><br>Peer se Hafta: <strong>Subah 10 - Raat 10</strong><br>Itwaar: <strong>Dopahar 12 - Raat 9</strong><br><br>🌐 Online store: <strong>24/7</strong>' };
        }
        if (q.match(/(shukriya|shukria|mehrbani|meharbani|thanks|thank)/)) {
            return { text: '🙏 Aap ka bhi shukriya!<br><strong>Mr.AYS</strong> se kharidari ka shukriya.<br>Koi aur madad chahiye to bataiye! 😊' };
        }
        if (q.match(/(help|madad|kya kar|kya kar sakte|kaise)/)) {
            return { text: '🤖 Main aap ki in cheezon mein madad kar sakta hoon:<br><br>• 🔥 Best selling perfumes<br>• ✨ Nayi products<br>• 🌟 Recommendations<br>• 💰 Qeemtein<br>• 🎁 Sale/Discounts<br>• 📦 Order status<br>• 🚚 Shipping<br>• 📞 Contact numbers<br>• 💳 Payment methods<br>• 🕒 Timings<br><br>Bas poochein!' };
        }
        return { text: getUnknownResponse('roman', input) };
    }

    // URDU SCRIPT RESPONSES
    if (lang === 'ur') {
        if (q.match(/نیا|نئی|نئے|تازہ|تازہ ترین|latest|new|arrival|حال ہی/)) {
            const products = getNewestProducts(3);
            return { text: buildNewArrivalsHTML(products, 'ur') };
        }
        if (q.match(/قیمت|کتنا|کتنی|کتے|rate|price/)) {
            const products = getBestSellers(3);
            return { text: '💰 <strong>ہماری قیمتیں:</strong><br><br>' + buildProductList(products, 'ur') };
        }
        if (q.match(/بہترین|سب سے زیادہ|مقبول|فروخت|best|selling|popular/)) {
            const products = getBestSellers(3);
            return { text: '🔥 <strong>سب سے زیادہ فروخت ہونے والے پرفیوم:</strong><br><br>' + buildProductList(products, 'ur') + '<br>یہ ہمارے سب سے مقبول پرفیوم ہیں! 💫' };
        }
        if (q.match(/تجویز|سفارش|رائے|recommend|suggest/)) {
            const products = getBestSellers(2);
            return { text: '🌟 <strong>میری تجویز:</strong><br><br>' + buildProductList(products, 'ur') + '<br>یہ پرفیوم آپ کے لیے بہترین ہیں! 😊' };
        }
        if (q.match(/پرفیوم|خوشبو|عطر|perfume|chahiye/)) {
            const products = getPerfumes(3);
            return { text: '🌸 <strong>ہمارے پریمیم پرفیوم:</strong><br><br>' + buildProductList(products, 'ur') };
        }
        if (q.match(/گھڑی|واچ|watch/)) {
            const products = getWatches(3);
            return { text: '⌚ <strong>ہماری لگژری گھڑیاں:</strong><br><br>' + buildProductList(products, 'ur') };
        }
        if (q.match(/چشمہ|گلاس|عینک|glass|eyewear/)) {
            const products = getGlasses(3);
            return { text: '🕶️ <strong>ہمارے پریمیم چشمے:</strong><br><br>' + buildProductList(products, 'ur') };
        }
        if (q.match(/ٹیسٹر|tester/)) {
            const products = getTesters(3);
            return { text: '🧪 <strong>ہمارے ٹیسٹر مجموعے:</strong><br><br>' + buildProductList(products, 'ur') };
        }
        if (q.match(/سیل|رعایت|ڈسکاؤنٹ|sale|discount/)) {
            const products = getOnSaleProducts(5);
            if (products.length === 0) return { text: '🎁 اس وقت کوئی سیل نہیں چل رہی۔' };
            return { text: '🎁 <strong>سیل والی پروڈکٹس:</strong><br><br>' + buildProductList(products, 'ur') };
        }
        if (q.match(/رابطہ|نمبر|فون|contact|phone|call/)) {
            return { text: '📞 <strong>ہم سے رابطہ کریں:</strong><br><br>📧 ای میل: <strong>mrays@gmail.com</strong><br>📱 فون: <strong>03263368118</strong><br>📱 واٹس ایپ: <strong>03263368118</strong><br>🏢 پتہ: عوان ٹاؤن، لاہور' };
        }
        if (q.match(/آرڈر|ڈیلیوری|order|status|delivery/)) {
            return { text: '📦 <strong>آرڈر کی صورتحال:</strong><br><br>اپنا آرڈر ٹریک کرنے کے لیے <strong>"My Orders"</strong> صفحہ پر جائیں۔' };
        }
        if (q.match(/واپس|واپسی|رقم|return|refund/)) {
            return { text: '🔄 <strong>واپسی کی پالیسی:</strong><br><br>ہم <strong>30 دن</strong> کی واپسی کی سہولت دیتے ہیں۔' };
        }
        if (q.match(/شپنگ|ڈیلیوری|shipping|delivery|free/)) {
            return { text: '🚚 <strong>شپنگ کی معلومات:</strong><br><br>✅ PKR 10,000 سے زیادہ کے آرڈر پر <strong>مفت شپنگ</strong><br>⏰ 2-5 کاروباری دنوں میں ڈیلیوری' };
        }
        if (q.match(/ادائیگی|پیمنٹ|payment|pay/)) {
            return { text: '💳 <strong>ادائیگی کے طریقے:</strong><br><br>✅ کریڈٹ/ڈیبٹ کارڈ<br>✅ بینک ٹرانسفر<br>✅ کیش آن ڈیلیوری<br>✅ ایزی پیسہ / جاز کیش' };
        }
        if (q.match(/رعایت|ڈسکاؤنٹ|آفر|discount|offer|sale/)) {
            return { text: '🎁 <strong>موجودہ آفرز:</strong><br><br>🔥 فلیش سیل - 30% تک رعایت<br>🎉 پہلی خریداری پر 10% رعایت' };
        }
        if (q.match(/پسند|ولش لسٹ|wishlist/)) {
            return { text: '❤️ <strong>ولش لسٹ:</strong><br><br>کسی بھی پروڈکٹ پر <strong>دل کا نشان</strong> دبا کر ولش لسٹ میں شامل کریں۔' };
        }
        if (q.match(/کارٹ|cart|checkout/)) {
            return { text: '🛒 <strong>کارٹ اور چیک آؤٹ:</strong><br><br>پروڈکٹ کارٹ میں ڈالیں اور <strong>"Proceed to Checkout"</strong> دبائیں۔' };
        }
        if (q.match(/اوقات|ٹائم|timing|hour|open|close/)) {
            return { text: '🕒 <strong>ہمارے اوقات کار:</strong><br><br>پیر تا ہفتہ: <strong>صبح 10 - رات 10</strong><br>اتوار: <strong>دوپہر 12 - رات 9</strong>' };
        }
        if (q.match(/شکریہ|مہربانی|thanks|thank/)) {
            return { text: '🙏 آپ کا بھی شکریہ!<br>Mr.AYS سے خریداری کا شکریہ۔' };
        }
        return { text: getUnknownResponse('ur', input) };
    }

    // ENGLISH RESPONSES
    if (q.match(/new\s*arrival|latest|newest|new\s*perfume|just\s*arrived|recommend\s*latest|recent|fresh/)) {
        const products = getNewestProducts(3);
        return { text: buildNewArrivalsHTML(products, 'en') };
    }
    
    if (q.match(/best\s*sell|top\s*sell|most\s*popular|best\s*perfume|popular\s*perfume|highest\s*sell/)) {
        const products = getBestSellers(3);
        return { text: '🔥 <strong>Our Best Selling Perfumes:</strong><br><br>' + buildProductList(products, 'en') + '<br>These are our most loved fragrances! 💫' };
    }
    
    if (q.match(/recommend|suggest|what\s*should\s*i\s*buy|advice/)) {
        const products = getBestSellers(2);
        return { text: '🌟 <strong>My Recommendations for You:</strong><br><br>' + buildProductList(products, 'en') + '<br>These are hand-picked based on what our customers love most! 😊' };
    }
    
    if (q.match(/show\s*me\s*perfume|all\s*perfume|list\s*perfume|perfumes/)) {
        const products = getPerfumes(3);
        return { text: '🌸 <strong>Our Premium Perfumes:</strong><br><br>' + buildProductList(products, 'en') };
    }
    
    if (q.match(/show\s*me\s*watch|all\s*watch|list\s*watch|watches/)) {
        const products = getWatches(3);
        return { text: '⌚ <strong>Our Luxury Watches:</strong><br><br>' + buildProductList(products, 'en') };
    }
    
    if (q.match(/show\s*me\s*glass|all\s*glass|eyewear|glasses/)) {
        const products = getGlasses(3);
        return { text: '🕶️ <strong>Our Premium Eyewear:</strong><br><br>' + buildProductList(products, 'en') };
    }
    
    if (q.match(/tester/)) {
        const products = getTesters(3);
        return { text: '🧪 <strong>Our Tester Collection:</strong><br><br>' + buildProductList(products, 'en') };
    }

    if (q.match(/sale|discount|offer|deal|promo/)) {
        const products = getOnSaleProducts(5);
        if (products.length === 0) return { text: '🎁 No active sales right now. Check back soon!' };
        return { text: '🎁 <strong>Products on Sale:</strong><br><br>' + buildProductList(products, 'en') };
    }
    
    if (q.match(/order/) && q.match(/where|status|track|my\s*order/)) {
        return { text: '📦 <strong>Order Status:</strong><br><br>You can check your order status by going to <strong>"My Orders"</strong> page.<br>For urgent help, contact us at <strong>03263368118</strong>' };
    }
    
    if (q.match(/return|refund|exchange/)) {
        return { text: '🔄 <strong>Return Policy:</strong><br><br>We offer a <strong>30-day return policy</strong> on all unused products.' };
    }
    
    if (q.match(/shipping|delivery|free\s*shipping|ship/)) {
        return { text: '🚚 <strong>Shipping Info:</strong><br><br>✅ <strong>Free standard shipping</strong> on orders above <strong>PKR 10,000</strong><br>⏰ Delivery in 2-5 business days' };
    }
    
    if (q.match(/contact|support|help|phone|call|email|number/)) {
        return { text: '📞 <strong>Contact Support:</strong><br><br>📧 Email: <strong>mrays@gmail.com</strong><br>📱 Phone: <strong>03263368118</strong><br>💬 WhatsApp: <strong>03263368118</strong><br>🏢 Address: Awan Town, Lahore' };
    }
    
    if (q.match(/payment|pay|card|cash|method/)) {
        return { text: '💳 <strong>Payment Methods:</strong><br><br>✅ Bank Transfer<br>✅ Cash on Delivery<br>✅ EasyPaisa / JazzCash' };
    }
    
    if (q.match(/discount|offer|sale|coupon|promo/)) {
        return { text: '🎁 <strong>Current Offers:</strong><br><br>🔥 Flash Sale - Up to <strong>30% OFF</strong><br>🎉 <strong>10% OFF</strong> on first purchase' };
    }
    
    if (q.match(/wishlist|favorite|save/)) {
        return { text: '❤️ <strong>Wishlist:</strong><br><br>You can add items to your wishlist by clicking the <strong>heart icon</strong> on any product.' };
    }
    
    if (q.match(/cart|checkout|buy/)) {
        return { text: '🛒 <strong>Cart & Checkout:</strong><br><br>To checkout, simply add items to your cart and click the <strong>"Proceed to Checkout"</strong> button.' };
    }
    
    if (q.match(/timing|hour|open|close|when.*open/)) {
        return { text: '🕒 <strong>Store Timings:</strong><br><br>Mon - Sat: <strong>10:00 AM - 10:00 PM</strong><br>Sunday: <strong>12:00 PM - 9:00 PM</strong><br><br>🌐 Online store: <strong>24/7</strong>' };
    }
    
    if (q.match(/thank|thanks|appreciate/)) {
        return { text: '🙏 You\'re welcome!<br>Thank you for choosing <strong>Mr.AYS</strong>.<br>Let me know if you need anything else! 😊' };
    }

    if (q.match(/\b(urdu|roman|dark|theme|night|light)\b/)) {
        return { text: '🌓 <strong>Theme Toggle Available!</strong><br><br>Tap the <i class="bi bi-moon-stars-fill"></i> moon icon at the top of the chat to switch between <strong>Light</strong> and <strong>Dark</strong> themes.' };
    }
    
    return { text: getUnknownResponse(lang, input) };
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>