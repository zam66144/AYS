<?php
session_start();
require_once 'config.php';

$orderId = $_GET['order_id'] ?? '';
$order = null;
$error = '';
$trackingLogs = [];

// Tracking status definitions
$statusSteps = [
    'order_placed' => ['label' => 'Order Placed', 'desc' => 'Your order has been received', 'icon' => 'bi-bag-check-fill', 'color' => '#6366f1'],
    'confirmed' => ['label' => 'Confirmed', 'desc' => 'Your order is confirmed', 'icon' => 'bi-check-circle-fill', 'color' => '#3b82f6'],
    'packed' => ['label' => 'Packed', 'desc' => 'Your order has been packed', 'icon' => 'bi-box-seam-fill', 'color' => '#8b5cf6'],
    'dispatched' => ['label' => 'Dispatched', 'desc' => 'Your order has been dispatched', 'icon' => 'bi-truck', 'color' => '#f59e0b'],
    'in_transit' => ['label' => 'In Transit', 'desc' => 'Your order is on the way', 'icon' => 'bi-geo-alt-fill', 'color' => '#0ea5e9'],
    'out_for_delivery' => ['label' => 'Out for Delivery', 'desc' => 'Your order is out for delivery', 'icon' => 'bi-bicycle', 'color' => '#10b981'],
    'delivered' => ['label' => 'Delivered', 'desc' => 'Your order has been delivered', 'icon' => 'bi-check2-all', 'color' => '#059669'],
];

$statusOrder = ['order_placed', 'confirmed', 'packed', 'dispatched', 'in_transit', 'out_for_delivery', 'delivered'];

// Search for order if order_id provided
if (!empty($orderId)) {
    // Remove # and leading zeros for query (to be flexible)
    $searchId = ltrim($orderId, '0');
    if (empty($searchId)) $searchId = 0;
    
    try {
        // Find order by id (flexible: with or without leading zeros)
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? OR id = ?");
        $stmt->execute([$orderId, $searchId]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Fetch tracking logs
            try {
                $logStmt = $pdo->prepare("SELECT * FROM order_tracking_logs WHERE order_id = ? ORDER BY created_at ASC");
                $logStmt->execute([$order['id']]);
                $trackingLogs = $logStmt->fetchAll();
            } catch (Exception $e) {
                $trackingLogs = [];
            }
        } else {
            $error = 'Order not found. Please check your order number and try again.';
        }
    } catch (Exception $e) {
        $error = 'Error searching for order. Please try again.';
    }
}

// Get current tracking status
$currentStatus = $order['tracking_status'] ?? 'order_placed';
$currentStepIndex = array_search($currentStatus, $statusOrder);
if ($currentStepIndex === false) $currentStepIndex = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order - LUXE SCENT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent-gold: #d4af37;
            --primary-dark: #0a192f;
            --primary-light: #112240;
            --light-bg: #f4f6f9;
            --text-muted: #8892b0;
            --text-light: #a0b8cc;
        }
        html, body { height: 100%; margin: 0; padding: 0; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--light-bg);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .main-content { flex: 1; }
        .gold-text { color: var(--accent-gold) !important; }
        
        .brand-gradient {
            background: var(--primary-dark);
            border-bottom: 3px solid var(--accent-gold);
            padding: 16px 0;
        }
        
        .btn-outline-light {
            background: transparent;
            border: 2px solid #fff;
            color: #fff;
            padding: 8px 20px;
            border-radius: 50px;
            transition: 0.3s;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .btn-outline-light:hover {
            background: #fff;
            color: var(--primary-dark);
        }
        
        /* ==========================
           SEARCH FORM
           ========================== */
        .track-form-container {
            max-width: 650px;
            margin: 0 auto 40px;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(10, 25, 47, 0.08);
            border: 1px solid #e4e7ed;
            position: relative;
            overflow: hidden;
        }
        .track-form-container::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--accent-gold), #f7d26b, var(--accent-gold));
        }
        .track-input-group {
            display: flex;
            gap: 10px;
        }
        .track-input {
            flex: 1;
            padding: 16px 22px;
            border: 2px solid #e4e7ed;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 2px;
            outline: none;
            transition: all 0.3s ease;
            text-align: center;
            text-transform: uppercase;
        }
        .track-input:focus {
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.12);
        }
        .btn-gold {
            background: var(--accent-gold);
            color: var(--primary-dark);
            font-weight: 700;
            padding: 14px 28px;
            border-radius: 50px;
            border: none;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-gold:hover {
            background: #f7d26b;
            transform: translateY(-2px);
            color: var(--primary-dark);
        }
        
        /* ==========================
           ORDER DETAILS CARD
           ========================== */
        .order-info-card {
            background: white;
            border-radius: 20px;
            padding: 28px;
            border: 1px solid #e4e7ed;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .order-info-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 15px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f2f5;
            margin-bottom: 20px;
        }
        .order-num-big {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary-dark);
        }
        .order-num-big small {
            display: block;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-muted);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-item {
            padding: 12px 0;
        }
        .info-label {
            font-size: 0.72rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--primary-dark);
        }
        
        /* Product Preview */
        .product-preview {
            display: flex;
            gap: 15px;
            align-items: center;
            background: #f8fafc;
            border-radius: 14px;
            padding: 14px;
            border: 1px solid #e4e7ed;
        }
        .product-preview img {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            object-fit: cover;
        }
        
        /* ==========================
           TRACKING TIMELINE
           ========================== */
        .tracking-container {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            border: 1px solid #e4e7ed;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .tracking-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .tracking-subtitle {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 35px;
        }
        
        .timeline {
            position: relative;
            padding-left: 50px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 15px;
            bottom: 15px;
            width: 3px;
            background: linear-gradient(180deg, var(--accent-gold) 0%, #e4e7ed 100%);
            border-radius: 3px;
        }
        
        .timeline-step {
            position: relative;
            padding-bottom: 35px;
        }
        .timeline-step:last-child { padding-bottom: 0; }
        
        .timeline-step .step-marker {
            position: absolute;
            left: -50px;
            top: 0;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: white;
            border: 3px solid #e4e7ed;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: #b0b8c4;
            transition: all 0.4s ease;
            z-index: 2;
        }
        
        .timeline-step.completed .step-marker {
            background: linear-gradient(135deg, var(--accent-gold), #b8941f);
            border-color: var(--accent-gold);
            color: white;
            box-shadow: 0 0 0 5px rgba(212, 175, 55, 0.15);
        }
        
        .timeline-step.active .step-marker {
            background: linear-gradient(135deg, #6366f1, #4338ca);
            border-color: #6366f1;
            color: white;
            animation: pulseActive 1.8s infinite;
            box-shadow: 0 0 0 5px rgba(99, 102, 241, 0.2);
        }
        @keyframes pulseActive {
            0%, 100% { box-shadow: 0 0 0 5px rgba(99, 102, 241, 0.2); }
            50% { box-shadow: 0 0 0 12px rgba(99, 102, 241, 0); }
        }
        
        .step-content {
            padding-left: 5px;
        }
        .step-title {
            font-weight: 700;
            font-size: 1rem;
            color: #b0b8c4;
            margin-bottom: 4px;
            transition: color 0.4s ease;
        }
        .timeline-step.completed .step-title { color: var(--primary-dark); }
        .timeline-step.active .step-title { color: #6366f1; font-weight: 800; }
        
        .step-desc {
            font-size: 0.82rem;
            color: #b0b8c4;
            margin-bottom: 4px;
        }
        .timeline-step.completed .step-desc,
        .timeline-step.active .step-desc { color: var(--text-muted); }
        
        .step-time {
            font-size: 0.72rem;
            color: var(--text-muted);
            font-style: italic;
        }
        
        /* Alert */
        .alert-luxe {
            padding: 20px 24px;
            border-radius: 16px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 25px;
        }
        .alert-luxe.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        .alert-luxe i { font-size: 1.4rem; }
        
        /* Footer */
        .footer {
            background: var(--primary-dark);
            color: var(--text-muted);
            border-top: 4px solid var(--accent-gold);
            padding: 50px 0 25px;
            position: relative;
            margin-top: 60px;
        }
        .footer h6 { color: #fff; font-weight: 700; margin-bottom: 20px; font-size: 1.1rem; }
        .footer ul { padding-left: 0; }
        .footer ul li { list-style: none; margin-bottom: 10px; }
        .footer ul li a {
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
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
        
        @media (max-width: 768px) {
            .track-form-container { padding: 30px 20px; }
            .track-input-group { flex-direction: column; }
            .tracking-container { padding: 28px 20px; }
            .timeline { padding-left: 42px; }
            .timeline::before { left: 12px; }
            .timeline-step .step-marker { left: -42px; width: 30px; height: 30px; font-size: 0.8rem; }
            .order-info-header { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="brand-gradient">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <a href="index.php" class="text-decoration-none text-white fw-bold fs-4">LUXE<span class="gold-text">SCENT</span></a>
            <a href="orders.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> My Orders</a>
        </div>
    </div>
</header>

<!-- Main Content -->
<div class="main-content">
    <div class="container py-5">
        
        <!-- Search Form -->
        <div class="track-form-container">
            <div style="text-align: center; margin-bottom: 25px;">
                <div style="width:70px;height:70px;background:linear-gradient(135deg,var(--accent-gold),#b8941f);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:2rem;color:white;box-shadow:0 15px 35px rgba(212,175,55,0.35);margin-bottom:15px;">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <h2 style="font-size:1.6rem;font-weight:800;color:var(--primary-dark);margin-bottom:6px;">Track Your Order</h2>
                <p style="color:var(--text-muted);font-size:0.88rem;margin:0;">Enter your order number to see live status</p>
            </div>
            
            <form method="GET" action="">
                <div class="track-input-group">
                    <input 
                        type="text" 
                        name="order_id"
                        class="track-input" 
                        placeholder="Enter Order Number"
                        value="<?php echo htmlspecialchars($orderId); ?>"
                        autocomplete="off"
                        maxlength="10"
                        required
                    >
                    <button type="submit" class="btn-gold">
                        <i class="bi bi-search"></i> Track
                    </button>
                </div>
            </form>
        </div>
        
        <?php if($error): ?>
        <div class="container" style="max-width: 650px;">
            <div class="alert-luxe error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    <strong>Order Not Found</strong>
                    <div style="font-size:0.85rem;margin-top:4px;"><?php echo htmlspecialchars($error); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if($order): ?>
        
        <!-- Order Info Card -->
        <div class="container" style="max-width: 900px;">
            <div class="order-info-card">
                <div class="order-info-header">
                    <div>
                        <div class="order-num-big">
                            <small>Order Number</small>
                            #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span class="badge" style="background: <?php echo $statusSteps[$currentStatus]['color'] ?? '#6366f1'; ?>; color: white; padding: 8px 18px; border-radius: 50px; font-size: 0.8rem; font-weight: 700;">
                            <i class="bi <?php echo $statusSteps[$currentStatus]['icon'] ?? 'bi-clock'; ?> me-1"></i>
                            <?php echo $statusSteps[$currentStatus]['label'] ?? 'Processing'; ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Order Date</div>
                        <div class="info-value"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Total Amount</div>
                        <div class="info-value gold-text">PKR <?php echo number_format($order['total'], 2); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Quantity</div>
                        <div class="info-value"><?php echo (int)$order['quantity']; ?> item(s)</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Order Status</div>
                        <div class="info-value"><?php echo ucfirst(htmlspecialchars($order['status'])); ?></div>
                    </div>
                </div>
                
                <!-- Product Preview -->
                <div class="product-preview">
                    <?php
                        // Get product image
                        $productImg = 'https://via.placeholder.com/70';
                        try {
                            $pStmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
                            $pStmt->execute([$order['product_id']]);
                            $pImg = $pStmt->fetchColumn();
                            if ($pImg) $productImg = $pImg;
                        } catch (Exception $e) {}
                    ?>
                    <img src="<?php echo htmlspecialchars($productImg); ?>" alt="Product">
                    <div>
                        <div style="font-weight:700;font-size:0.95rem;color:var(--primary-dark);"><?php echo htmlspecialchars($order['product_name']); ?></div>
                        <div style="font-size:0.8rem;color:var(--text-muted);margin-top:3px;">
                            <i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($order['address'] ?: 'Address not specified'); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tracking Timeline -->
            <div class="tracking-container">
                <div class="tracking-title">
                    <i class="bi bi-truck gold-text"></i>
                    Live Tracking
                </div>
                <p class="tracking-subtitle">Track your order journey from placement to delivery</p>
                
                <?php if($order['status'] === 'returned'): ?>
                    <div class="alert-luxe error">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <div>
                            <strong>Order Returned</strong>
                            <div style="font-size:0.85rem;margin-top:4px;">This order has been returned. Contact support for more info.</div>
                        </div>
                    </div>
                <?php else: ?>
                
                <div class="timeline">
                    <?php 
                    foreach($statusOrder as $index => $statusKey): 
                        $step = $statusSteps[$statusKey];
                        $stepIndex = array_search($statusKey, $statusOrder);
                        $isCompleted = $stepIndex < $currentStepIndex;
                        $isActive = $stepIndex === $currentStepIndex;
                        
                        // Find log timestamp if available
                        $logTime = '';
                        foreach($trackingLogs as $log) {
                            if ($log['status'] === $statusKey) {
                                $logTime = date('M j, Y - g:i A', strtotime($log['created_at']));
                                break;
                            }
                        }
                    ?>
                    <div class="timeline-step <?php echo $isCompleted ? 'completed' : ''; ?> <?php echo $isActive ? 'active' : ''; ?>">
                        <div class="step-marker">
                            <i class="bi <?php echo $step['icon']; ?>"></i>
                        </div>
                        <div class="step-content">
                            <div class="step-title"><?php echo $step['label']; ?></div>
                            <div class="step-desc"><?php echo $step['desc']; ?></div>
                            <?php if($logTime): ?>
                                <div class="step-time"><i class="bi bi-clock me-1"></i><?php echo $logTime; ?></div>
                            <?php endif; ?>
                            <?php if($isActive && $order['tracking_note']): ?>
                                <div style="margin-top:8px;padding:8px 12px;background:rgba(99,102,241,0.08);border-left:3px solid #6366f1;border-radius:6px;font-size:0.8rem;color:#4f46e5;">
                                    <i class="bi bi-info-circle me-1"></i><?php echo htmlspecialchars($order['tracking_note']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php endif; ?>
                
                <!-- Delivery Estimate -->
                <?php if($currentStatus !== 'delivered' && $order['status'] !== 'returned'): ?>
                <div style="margin-top:30px;padding:18px 22px;background:linear-gradient(135deg,#fef9e7,#fef3c7);border-radius:14px;border-left:4px solid var(--accent-gold);">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <i class="bi bi-calendar-check gold-text" style="font-size:1.4rem;"></i>
                        <div>
                            <div style="font-weight:700;color:#92400e;font-size:0.9rem;">Estimated Delivery</div>
                            <div style="font-size:0.82rem;color:#92400e;opacity:0.8;">
                                <?php 
                                $orderDate = strtotime($order['created_at']);
                                $estDelivery = strtotime('+5 days', $orderDate);
                                echo date('l, F j, Y', $estDelivery);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
        
        <?php endif; ?>
        
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <h6 class="gold-text">Mr.AYS</h6>
                <p class="brand-desc">Premium luxury products since 2026. Discover our exclusive range of perfumes, testers, watches, and premium eyewear.</p>
                <div class="social-links">
                    <a href="https://www.facebook.com/profile.php?id=61593054195065&rdid=B8vJjskA9o9Ho0jJ&share_url=https%3A%2F%2Fwww.facebook.com%2Fshare%2F1LjNSGnjJx%2F#"><i class="bi bi-facebook"></i></a>
                    <a href="https://www.instagram.com/mr.ays_officiall?stkn=MWUzbGM1dHdxNXFkcQ=="><i class="bi bi-instagram"></i></a>
                    <a href="#"><i class="bi bi-twitter-x"></i></a>
                    <a href="https://wa.me/923263368118?text=Hello%20AYS%2C%20I%20want%20to%20know%20more%20about%20your%20perfumes." target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-whatsapp"></i>
                    </a>
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
            &copy; 2026 AYS. All Rights Reserved. powered by <a href="https://zam2.vercel.app" target="_blank" rel="noopener noreferrer" style="color: #ffcc00; text-decoration: none; font-weight: bold;">ZAM Digital Agency</a>
        </div>
    </div>
</footer>

</body>
</html>