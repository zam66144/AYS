<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header("Location: login.php");
    exit();
}

$client_id = $_SESSION['client_id'];

// Fetch all orders for this customer
$stmt = $pdo->prepare("
    SELECT * FROM orders 
    WHERE client_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$client_id]);
$orders = $stmt->fetchAll();

// Tracking status helper
function getTrackingLabel($status) {
    $labels = [
        'order_placed' => ['label' => 'Order Placed', 'icon' => 'bi-bag-check', 'color' => '#6366f1'],
        'confirmed' => ['label' => 'Confirmed', 'icon' => 'bi-check-circle', 'color' => '#3b82f6'],
        'packed' => ['label' => 'Packed', 'icon' => 'bi-box-seam', 'color' => '#8b5cf6'],
        'dispatched' => ['label' => 'Dispatched', 'icon' => 'bi-truck', 'color' => '#f59e0b'],
        'in_transit' => ['label' => 'In Transit', 'icon' => 'bi-geo-alt', 'color' => '#0ea5e9'],
        'out_for_delivery' => ['label' => 'Out for Delivery', 'icon' => 'bi-bicycle', 'color' => '#10b981'],
        'delivered' => ['label' => 'Delivered', 'icon' => 'bi-check2-all', 'color' => '#059669'],
        'cancelled' => ['label' => 'Cancelled', 'icon' => 'bi-x-circle', 'color' => '#ef4444'],
    ];
    return $labels[$status] ?? $labels['order_placed'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - LUXE SCENT</title>
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
        
        /* ==========================
           TABS STYLING
           ========================== */
        .luxury-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e4e7ed;
            padding-bottom: 0;
            flex-wrap: wrap;
        }
        .luxury-tab {
            background: transparent;
            border: none;
            padding: 14px 28px;
            font-weight: 600;
            font-size: 0.95rem;
            color: #6b7a8f;
            cursor: pointer;
            border-radius: 12px 12px 0 0;
            transition: all 0.3s ease;
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .luxury-tab:hover {
            color: var(--accent-gold);
            background: rgba(212, 175, 55, 0.05);
        }
        .luxury-tab.active {
            color: var(--primary-dark);
            background: white;
            font-weight: 700;
        }
        .luxury-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--accent-gold);
            border-radius: 3px 3px 0 0;
        }
        .luxury-tab i { font-size: 1.15rem; }
        
        .tab-content-panel {
            display: none;
            animation: fadeIn 0.4s ease;
        }
        .tab-content-panel.active { display: block; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ==========================
           ORDER CARD
           ========================== */
        .order-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 22px 24px;
            border: 1px solid #e4e7ed;
            transition: 0.3s;
            height: 100%;
        }
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.08);
            border-color: var(--accent-gold);
        }
        
        .status-badge {
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processed { background: #d4edda; color: #155724; }
        .status-returned { background: #f8d7da; color: #721c24; }
        
        .tracking-mini {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 10px;
            background: #f8fafc;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 12px;
            border: 1px solid #e4e7ed;
        }
        .tracking-mini .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #6366f1;
            animation: pulseDot 1.5s ease-in-out infinite;
        }
        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.3); }
        }
        
        .btn-gold {
            background: var(--accent-gold);
            color: var(--primary-dark);
            font-weight: 700;
            padding: 10px 24px;
            border-radius: 50px;
            border: none;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-gold:hover {
            background: #f7d26b;
            transform: translateY(-2px);
            color: var(--primary-dark);
        }
        
        .btn-track {
            background: linear-gradient(135deg, var(--primary-dark), #1a2c4a);
            color: white;
            font-weight: 700;
            padding: 9px 20px;
            border-radius: 50px;
            border: none;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            font-size: 0.82rem;
        }
        .btn-track:hover {
            background: var(--accent-gold);
            color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-outline-light {
            background: transparent;
            border: 2px solid #fff;
            color: #fff;
            padding: 8px 20px;
            border-radius: 50px;
            transition: 0.3s;
            text-decoration: none;
        }
        .btn-outline-light:hover {
            background: #fff;
            color: var(--primary-dark);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state i { font-size: 4rem; color: #ccc; }
        
        /* ==========================
           TRACK ORDER FORM
           ========================== */
        .track-form-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 20px 60px rgba(10, 25, 47, 0.1);
            border: 1px solid #e4e7ed;
            position: relative;
            overflow: hidden;
        }
        .track-form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--accent-gold), #f7d26b, var(--accent-gold));
        }
        .track-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--accent-gold), #b8941f);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 2.2rem;
            color: white;
            box-shadow: 0 15px 35px rgba(212, 175, 55, 0.4);
            animation: floatTrack 3s ease-in-out infinite;
        }
        @keyframes floatTrack {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        .track-title {
            font-size: 1.8rem;
            font-weight: 800;
            text-align: center;
            color: var(--primary-dark);
            margin-bottom: 8px;
        }
        .track-subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 35px;
        }
        .track-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
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
        .track-input::placeholder {
            letter-spacing: 1px;
            text-transform: none;
            font-weight: 500;
            color: #b0b8c4;
        }
        
        /* ==========================
           PREMIUM FOOTER UI
           ========================== */
        .footer {
            background: var(--primary-dark);
            color: var(--text-muted);
            border-top: 4px solid var(--accent-gold);
            padding: 50px 0 25px;
            position: relative;
            margin-top: 60px;
        }
        .footer h6 {
            color: #fff;
            font-weight: 700;
            margin-bottom: 20px;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
        }
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
        .footer ul li a:hover {
            color: var(--accent-gold);
            transform: translateX(4px);
        }
        .footer .brand-desc {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 15px;
            line-height: 1.6;
        }
        .footer .social-links {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }
        .footer .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            color: var(--text-light);
            font-size: 1.2rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .footer .social-links a:hover {
            background: var(--accent-gold);
            color: var(--primary-dark);
            transform: translateY(-4px);
        }
        .footer .footer-divider {
            border-top: 1px solid rgba(255,255,255,0.06);
            margin: 30px 0 20px;
        }
        .footer .copyright {
            text-align: center;
            font-size: 1rem;
            color: var(--text-muted);
        }
        .footer .copyright i {
            color: var(--accent-gold);
            margin-right: 4px;
        }
        .footer .back-to-top {
            position: absolute;
            right: 30px;
            top: -20px;
            background: var(--accent-gold);
            color: var(--primary-dark);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .footer .back-to-top:hover {
            background: #f7d26b;
            transform: translateY(-4px);
        }

        @media (max-width: 768px) {
            .luxury-tabs { justify-content: center; }
            .luxury-tab { padding: 12px 20px; font-size: 0.85rem; }
            .track-form-container { padding: 35px 22px; }
            .track-title { font-size: 1.5rem; }
            .track-input-group { flex-direction: column; }
            .footer { padding: 30px 0 20px; text-align: center; margin-top: 40px; }
            .footer .social-links { justify-content: center; }
            .footer .back-to-top { right: 50%; transform: translateX(50%); top: -22px; }
            .footer .back-to-top:hover { transform: translateX(50%) translateY(-4px); }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="brand-gradient">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <a href="index.php" class="text-decoration-none text-white fw-bold fs-4">Mr.<span class="gold-text">AYS</span></a>
            <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Back to Store</a>
        </div>
    </div>
</header>

<!-- Main Content -->
<div class="main-content">
    <div class="container py-5">
        
        <!-- ========== TABS ========== -->
        <div class="luxury-tabs">
            <button class="luxury-tab active" onclick="switchTab('orders', this)">
                <i class="bi bi-box-seam"></i> My Orders
            </button>
            <button class="luxury-tab" onclick="switchTab('track', this)">
                <i class="bi bi-geo-alt"></i> Track Order
            </button>
        </div>
        
        <!-- ========== TAB 1: MY ORDERS ========== -->
        <div class="tab-content-panel active" id="tab-orders">
            <?php if(count($orders) > 0): ?>
                <div class="row g-4">
                    <?php foreach($orders as $order): 
                        $tracking = getTrackingLabel($order['tracking_status'] ?? 'order_placed');
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="order-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="fw-bold mb-0">Order #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></h5>
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                    </small>
                                </div>
                                <span class="status-badge status-<?php echo htmlspecialchars($order['status'] ?: 'pending'); ?>">
                                    <?php echo ucfirst(htmlspecialchars($order['status'] ?: 'Pending')); ?>
                                </span>
                            </div>
                            
                            <div style="font-size: 0.88rem; color: #4b5563;">
                                <p class="mb-1"><strong>Product:</strong> <?php echo htmlspecialchars($order['product_name']); ?></p>
                                <p class="mb-1"><strong>Qty:</strong> <?php echo (int)$order['quantity']; ?></p>
                                <p class="mb-2"><strong>Total:</strong> <span class="gold-text fw-bold">PKR <?php echo number_format($order['total'], 2); ?></span></p>
                            </div>
                            
                            <!-- Mini Tracking Info -->
                            <div class="tracking-mini" style="color: <?php echo $tracking['color']; ?>;">
                                <span class="dot" style="background: <?php echo $tracking['color']; ?>;"></span>
                                <i class="bi <?php echo $tracking['icon']; ?>"></i>
                                <span><?php echo $tracking['label']; ?></span>
                            </div>
                            
                            <div class="mt-3 d-flex gap-2">
                                <a href="track-order.php?order_id=<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?>" class="btn-track">
                                    <i class="bi bi-geo-alt-fill"></i> Track
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-box-seam"></i>
                    <h4 class="text-muted mt-3">No orders found.</h4>
                    <p class="text-muted">You haven't placed any orders yet.</p>
                    <a href="index.php" class="btn-gold mt-2"><i class="bi bi-grid me-2"></i>Browse Products</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- ========== TAB 2: TRACK ORDER ========== -->
        <div class="tab-content-panel" id="tab-track">
            <div class="track-form-container">
                <div class="track-icon">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <h2 class="track-title">Track Your Order</h2>
                <p class="track-subtitle">Enter your order number to see real-time delivery status</p>
                
                <form onsubmit="trackOrder(event)">
                    <div class="track-input-group">
                        <input 
                            type="text" 
                            class="track-input" 
                            id="orderNumberInput" 
                            placeholder="e.g., 0003"
                            pattern="[0-9]*"
                            maxlength="10"
                            autocomplete="off"
                            required
                        >
                    </div>
                    <button type="submit" class="btn-gold w-100 justify-content-center" style="padding: 14px;">
                        <i class="bi bi-search"></i> Track Order Now
                    </button>
                </form>
                
                <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 12px; border-left: 4px solid var(--accent-gold);">
                    <div style="font-size: 0.82rem; color: #6b7280;">
                        <i class="bi bi-info-circle me-1 gold-text"></i>
                        <strong>Tip:</strong> Aap apna order number <strong>"My Orders"</strong> tab mein dekh sakte hain.
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- ============================================ -->
<!-- PREMIUM FOOTER                               -->
<!-- ============================================ -->
<footer class="footer">
    <button class="back-to-top" onclick="window.scrollTo({top:0, behavior:'smooth'})">
        <i class="bi bi-chevron-up"></i>
    </button>

    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <h6 class="gold-text">Mr. AYS</h6>
                <p class="brand-desc">
                    Premium luxury products since 2026. Discover our exclusive range of perfumes, testers, watches, and premium eyewear.
                </p>
                <div class="social-links">
                    <a href="https://www.facebook.com/profile.php?id=61593054195065&rdid=B8vJjskA9o9Ho0jJ&share_url=https%3A%2F%2Fwww.facebook.com%2Fshare%2F1LjNSGnjJx%2F#"><i class="bi bi-facebook"></i></a>
                    <a href="https://www.instagram.com/mr.ays_officiall?stkn=MWUzbGM1dHdxNXFkcQ=="><i class="bi bi-instagram"></i></a>
                    <a href=""><i class="bi bi-twitter-x"></i></a>
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
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Return Policy</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Privacy Policy</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Terms &amp; Conditions</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Shipping Info</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-divider"></div>

        <div class="copyright">
            &copy; 2026 AYS. All Rights Reserved. powered by <a href="https://zam2.vercel.app" target="_blank" rel="noopener noreferrer" style="color: #ffcc00; text-decoration: none; font-weight: bold;">ZAM Digital Agency</a>


        </div>
    </div>
</footer>

<script>
    // ==========================
    // TAB SWITCHING
    // ==========================
    function switchTab(tabName, element) {
        // Update active tab
        document.querySelectorAll('.luxury-tab').forEach(t => t.classList.remove('active'));
        element.classList.add('active');
        
        // Switch panels
        document.querySelectorAll('.tab-content-panel').forEach(p => p.classList.remove('active'));
        document.getElementById('tab-' + tabName).classList.add('active');
    }
    
    // ==========================
    // TRACK ORDER FUNCTION
    // ==========================
    function trackOrder(event) {
        event.preventDefault();
        const orderNum = document.getElementById('orderNumberInput').value.trim();
        
        if (!orderNum) {
            alert('Please enter an order number');
            return;
        }
        
        // Redirect to track-order.php with order number
        window.location.href = 'track-order.php?order_id=' + encodeURIComponent(orderNum);
    }
    
    // ==========================
    // CHECK URL FOR AUTO-TRACK
    // ==========================
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const trackParam = urlParams.get('track');
        
        if (trackParam === '1') {
            // Auto-switch to track tab
            const trackTab = document.querySelectorAll('.luxury-tab')[1];
            if (trackTab) switchTab('track', trackTab);
        }
    });
</script>

</body>
</html>