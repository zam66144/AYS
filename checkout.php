<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header("Location: login.php");
    exit();
}

$client_id = $_SESSION['client_id'];

// Get client info
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'place_order') {
        $product_id = (int)$_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        $total = (float)$_POST['total'];
        $product_name = $_POST['product_name'];
        $address = $_POST['address'];
        $screenshot = $_POST['screenshot'] ?? '';
        
        // Generate receipt number
        $receiptNumber = 'LUXE-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));
        
        // Insert order
        $stmt = $pdo->prepare("INSERT INTO orders (client_id, receipt_number, product_id, product_name, quantity, total, status, screenshot, address) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)");
        
        if ($stmt->execute([$client_id, $receiptNumber, $product_id, $product_name, $quantity, $total, $screenshot, $address])) {
            $orderId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Order placed successfully!', 
                'order_id' => $orderId,
                'receipt_number' => $receiptNumber
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to place order.']);
            exit;
        }
    }
    
    // Check order status
    if ($action === 'check_order_status') {
        $order_id = (int)$_POST['order_id'];
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND client_id = ?");
        $stmt->execute([$order_id, $client_id]);
        $order = $stmt->fetch();
        if ($order) {
            echo json_encode(['success' => true, 'status' => $order['status']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
        }
        exit;
    }
}

// Check if order was just placed
$showConfirmation = isset($_GET['order_placed']) && $_GET['order_placed'] == '1';
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// Get receipt number for confirmation page
$receipt_number = '';
if ($showConfirmation && $order_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT receipt_number FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $receipt_number = $stmt->fetchColumn();
    } catch (Exception $e) {
        $receipt_number = 'LUXE-' . date('Y') . '-' . str_pad($order_id, 6, '0', STR_PAD_LEFT);
    }
}
?>

<?php if($showConfirmation): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - LUXE SCENT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800;900&family=Cormorant+Garamond:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --accent-gold: #d4af37; --primary-dark: #0a192f; --text-muted: #8892b0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: radial-gradient(ellipse at center, #112240 0%, #0a192f 60%, #050e1a 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 20px; position: relative; overflow-x: hidden;
        }
        body::before {
            content: ''; position: fixed; inset: 0;
            background-image: 
                linear-gradient(rgba(212, 175, 55, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(212, 175, 55, 0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: patternMove 40s linear infinite;
            pointer-events: none;
        }
        @keyframes patternMove { 0% { transform: translate(0, 0); } 100% { transform: translate(60px, 60px); } }
        
        .confirmation-card {
            background: linear-gradient(145deg, #ffffff 0%, #fdfbf7 100%);
            border-radius: 24px; padding: 50px 40px; max-width: 600px; width: 100%;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(212, 175, 55, 0.3), 0 0 80px rgba(212, 175, 55, 0.15);
            text-align: center; border: 2px solid var(--accent-gold);
            position: relative; overflow: hidden;
            animation: cardEntrance 1s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes cardEntrance { from { opacity: 0; transform: scale(0.9) translateY(30px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .confirmation-card::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 3px;
            background: linear-gradient(90deg, transparent, var(--accent-gold), #fff8dc, var(--accent-gold), transparent);
            animation: cardShimmer 3s ease-in-out infinite;
        }
        @keyframes cardShimmer { 0% { left: -100%; } 50%, 100% { left: 100%; } }
        
        .confirmation-icon {
            width: 100px; height: 100px;
            background: linear-gradient(135deg, #ffc107, #f59e0b);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 25px; font-size: 3rem; color: white;
            position: relative; animation: iconPulse 2s ease-in-out infinite;
            box-shadow: 0 15px 40px rgba(255, 193, 7, 0.4);
        }
        .confirmation-icon::before, .confirmation-icon::after {
            content: ''; position: absolute; inset: -15px; border-radius: 50%;
            border: 2px solid rgba(255, 193, 7, 0.3);
            animation: rippleRing 3s ease-out infinite;
        }
        .confirmation-icon::after { animation-delay: 1.5s; }
        @keyframes iconPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }
        @keyframes rippleRing { 0% { transform: scale(1); opacity: 0.8; } 100% { transform: scale(1.6); opacity: 0; } }
        
        .confirmation-card h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem; font-weight: 700; color: var(--primary-dark); margin-bottom: 10px;
        }
        .confirmation-card p.subtitle { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 20px; }
        
        .receipt-box {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border: 2px dashed var(--accent-gold); border-radius: 14px;
            padding: 20px; margin: 20px 0;
        }
        .receipt-label {
            font-size: 0.7rem; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 2px;
            font-weight: 700; margin-bottom: 8px;
        }
        .receipt-number {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.6rem; font-weight: 700; color: var(--primary-dark);
            letter-spacing: 2px;
        }
        
        .status-badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 22px; border-radius: 50px;
            font-weight: 700; font-size: 0.85rem; margin-top: 15px;
            animation: badgePulse 2s ease-in-out infinite;
        }
        @keyframes badgePulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.03); } }
        .status-pending { background: linear-gradient(135deg, #fff3cd, #fde68a); color: #856404; border: 1px solid #fcd34d; }
        .status-processed { background: linear-gradient(135deg, #d4edda, #a7f3d0); color: #155724; border: 1px solid #6ee7b7; }
        
        .btn-gold { 
            background: linear-gradient(135deg, var(--accent-gold), #b8941f);
            color: var(--primary-dark); font-weight: 800; padding: 14px 32px;
            border-radius: 50px; border: none; margin-top: 20px; cursor: pointer;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: inline-flex; align-items: center; gap: 10px;
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.35);
            font-size: 0.9rem; letter-spacing: 1px; text-transform: uppercase;
            position: relative; overflow: hidden;
        }
        .btn-gold::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.5), transparent);
            transition: left 0.6s ease;
        }
        .btn-gold:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 20px 45px rgba(212, 175, 55, 0.5); }
        .btn-gold:hover::before { left: 100%; }
        
        .btn-outline { 
            background: transparent; border: 2px solid var(--accent-gold);
            color: var(--primary-dark); padding: 12px 28px; border-radius: 50px;
            margin-top: 10px; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
            font-weight: 700; transition: all 0.3s ease;
            font-size: 0.85rem; letter-spacing: 0.5px;
        }
        .btn-outline:hover { background: var(--accent-gold); color: var(--primary-dark); transform: translateY(-2px); }
        
        .status-text { font-size: 0.9rem; color: var(--text-muted); margin-top: 15px; }
        
        @media (max-width: 500px) {
            .confirmation-card { padding: 35px 25px; }
            .confirmation-card h2 { font-size: 1.5rem; }
            .receipt-number { font-size: 1.3rem; }
            .confirmation-icon { width: 80px; height: 80px; font-size: 2.3rem; }
        }
    </style>
</head>
<body>
    <div class="confirmation-card">
        <div class="confirmation-icon">
            <i class="bi bi-hourglass-split"></i>
        </div>
        
        <h2>Your Order is Placed!</h2>
        <p class="subtitle">Please wait for admin confirmation</p>
        
        <div class="receipt-box">
            <div class="receipt-label">Receipt Number</div>
            <div class="receipt-number"><?php echo htmlspecialchars($receipt_number ?: 'LUXE-' . date('Y') . '-' . str_pad($order_id, 6, '0', STR_PAD_LEFT)); ?></div>
        </div>
        
        <span class="status-badge status-pending" id="statusBadge">
            <i class="bi bi-clock me-1"></i>Waiting for Confirmation
        </span>
        
        <div id="orderStatusMessage" class="status-text"></div>
        
        <div>
            <button class="btn-gold" onclick="checkOrderStatus(<?php echo $order_id; ?>)">
                <i class="bi bi-arrow-repeat"></i>
                Check Status
            </button>
        </div>
        
        <div>
            <a href="orders.php" class="btn-outline">
                <i class="bi bi-box-seam"></i>
                View My Orders
            </a>
        </div>
    </div>

<script>
function checkOrderStatus(orderId) {
    const statusMessage = document.getElementById('orderStatusMessage');
    const statusBadge = document.getElementById('statusBadge');
    statusMessage.innerHTML = '<i class="bi bi-hourglass-split"></i> Checking...';
    
    fetch('checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=check_order_status&order_id=' + orderId
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (data.status === 'processed') {
                statusBadge.className = 'status-badge status-processed';
                statusBadge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Order Confirmed!';
                statusMessage.innerHTML = '<p style="color: #155724; font-weight: 600;">✓ Your order has been confirmed. Thank you!</p>';
            } else if (data.status === 'cancelled') {
                statusBadge.className = 'status-badge status-pending';
                statusBadge.style.background = 'linear-gradient(135deg, #f8d7da, #fecaca)';
                statusBadge.style.color = '#721c24';
                statusBadge.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i>Cancelled';
                statusMessage.innerHTML = '<p style="color: #721c24;">This order has been cancelled. Contact support for details.</p>';
            } else {
                statusMessage.innerHTML = '<p style="color: #856404;">Still waiting for admin confirmation...</p>';
            }
        } else {
            statusMessage.innerHTML = '<span style="color: #dc3545;">' + data.message + '</span>';
        }
    })
    .catch(err => {
        statusMessage.innerHTML = '<span style="color: #dc3545;">Connection error. Please try again.</span>';
    });
}
</script>
</body>
</html>
<?php exit(); ?>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - LUXE SCENT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --accent-gold: #d4af37; --primary-dark: #0a192f; --text-muted: #8892b0; --text-light: #a0b8cc; }
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; }
        .checkout-card { background: white; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); padding: 30px; border: 1px solid #e4e7ed; }
        .btn-gold { background: var(--accent-gold); color: var(--primary-dark); font-weight: 700; padding: 12px 28px; border-radius: 50px; border: none; }
        .upload-zone { border: 2px dashed var(--accent-gold); border-radius: 12px; padding: 20px; text-align: center; }
        .bank-info { background: #f8f9fa; padding: 15px; border-radius: 10px; border-left: 4px solid var(--accent-gold); }
        .gold-text { color: var(--accent-gold) !important; }
        
        .empty-cart-state {
            text-align: center; padding: 60px 20px; background: white;
            border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e4e7ed; position: relative; overflow: hidden;
        }
        .empty-cart-state::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 3px;
            background: linear-gradient(90deg, transparent, var(--accent-gold), #fff8dc, var(--accent-gold), transparent);
            animation: shimmerLine 4s ease-in-out infinite;
        }
        @keyframes shimmerLine { 0% { left: -100%; } 50%, 100% { left: 100%; } }
        
        .empty-cart-icon {
            width: 120px; height: 120px;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.15), rgba(212, 175, 55, 0.05));
            border: 2px solid rgba(212, 175, 55, 0.3); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 30px; font-size: 3rem; color: var(--accent-gold);
            position: relative; animation: floatIcon 4s ease-in-out infinite;
        }
        .empty-cart-icon::before, .empty-cart-icon::after {
            content: ''; position: absolute; inset: -10px; border-radius: 50%;
            border: 1px solid rgba(212, 175, 55, 0.2);
            animation: rippleRing 3s ease-out infinite;
        }
        .empty-cart-icon::after { animation-delay: 1.5s; }
        @keyframes floatIcon { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        @keyframes rippleRing { 0% { transform: scale(1); opacity: 0.8; } 100% { transform: scale(1.5); opacity: 0; } }
        
        .empty-cart-title { font-size: 1.8rem; font-weight: 800; color: var(--primary-dark); margin-bottom: 12px; letter-spacing: -0.5px; }
        .empty-cart-text { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 35px; max-width: 420px; margin-left: auto; margin-right: auto; line-height: 1.7; }
        
        .btn-continue-shopping {
            background: linear-gradient(135deg, var(--accent-gold), #b8941f);
            color: var(--primary-dark); font-weight: 800; font-size: 0.9rem;
            letter-spacing: 1.5px; text-transform: uppercase; padding: 16px 40px;
            border-radius: 50px; border: none; text-decoration: none;
            display: inline-flex; align-items: center; gap: 10px;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 12px 30px rgba(212, 175, 55, 0.4);
            position: relative; overflow: hidden;
        }
        .btn-continue-shopping::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.5), transparent);
            transition: left 0.6s ease;
        }
        .btn-continue-shopping:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 20px 45px rgba(212, 175, 55, 0.55);
            color: var(--primary-dark);
            background: linear-gradient(135deg, #f7d26b, var(--accent-gold));
        }
        .btn-continue-shopping:hover::before { left: 100%; }
        
        .quick-links {
            margin-top: 30px; padding-top: 25px; border-top: 1px solid #e4e7ed;
            display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;
        }
        .quick-link {
            color: var(--text-muted); font-size: 0.85rem; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.3s ease; font-weight: 600;
        }
        .quick-link:hover { color: var(--accent-gold); transform: translateY(-2px); }
        .quick-link i { font-size: 1rem; }
        
        .loading-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(10, 25, 47, 0.85); backdrop-filter: blur(8px);
            z-index: 99999; align-items: center; justify-content: center; flex-direction: column;
        }
        .loading-overlay.active { display: flex; }
        .loading-spinner {
            width: 70px; height: 70px;
            border: 4px solid rgba(212, 175, 55, 0.2);
            border-top-color: var(--accent-gold); border-radius: 50%;
            animation: spin 1s linear infinite; margin-bottom: 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { color: white; font-weight: 600; font-size: 1rem; letter-spacing: 1px; }
        .loading-text small { display: block; color: rgba(255, 255, 255, 0.6); font-size: 0.8rem; margin-top: 6px; font-weight: 400; letter-spacing: 0.5px; }

        /* ========================== */
        /* PREMIUM FOOTER              */
        /* ========================== */
        .footer {
            background: #0a192f;
            color: var(--text-muted);
            border-top: 4px solid var(--accent-gold);
            padding: 50px 0 25px;
            position: relative;
            margin-top: auto;
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
        .footer .copyright i { color: var(--accent-gold); margin-right: 4px; }
        
        @media (max-width: 768px) {
            .footer { padding: 35px 0 20px; text-align: center; }
            .footer .social-links { justify-content: center; }
        }
    </style>
</head>
<body>

<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
    <div class="loading-text">
        Placing Your Order...
        <small>Please wait a moment</small>
    </div>
</div>

<div class="container py-5" style="flex: 1;">
    <div class="row justify-content-center">
        <div class="col-lg-8" id="checkoutContent">
            
            <div class="empty-cart-state" id="emptyCartState" style="display: none;">
                <div class="empty-cart-icon">
                    <i class="bi bi-cart-x"></i>
                </div>
                <h2 class="empty-cart-title">Your Cart is Empty</h2>
                <p class="empty-cart-text">
                    Looks like you haven't added anything to your cart yet. 
                    Explore our premium luxury collection and find something you love!
                </p>
                <a href="index.php" class="btn-continue-shopping">
                    <i class="bi bi-bag-plus-fill"></i>
                    Continue Shopping
                </a>
                
                <div class="quick-links">
                    <a href="wishlist.php" class="quick-link">
                        <i class="bi bi-heart-fill"></i> View Wishlist
                    </a>
                    <a href="orders.php" class="quick-link">
                        <i class="bi bi-box-seam-fill"></i> My Orders
                    </a>
                    <a href="index.php" class="quick-link">
                        <i class="bi bi-grid-fill"></i> Browse All Products
                    </a>
                </div>
            </div>
            
            <div class="checkout-card" id="checkoutForm">
                <h2 class="mb-4"><i class="bi bi-bag-check gold-text me-2"></i>Checkout</h2>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <h5 class="mb-3"><i class="bi bi-box-seam me-2"></i>Order Summary</h5>
                        <div id="orderSummary" class="p-3" style="background: #f8f9fa; border-radius: 10px;">
                            <p class="text-muted">Loading cart...</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h5 class="mb-3"><i class="bi bi-bank me-2"></i>Payment Details</h5>
                        <div class="bank-info mb-3">
                            <p class="mb-1"><strong>Bank Name:</strong> Nayapay</p>
                            <p class="mb-1"><strong>Account Name:</strong> Asfand Yar</p>
                            <p class="mb-1"><strong>Account Number:</strong> 03263368118</p>
                            <p class="mb-0"><strong>IBAN:</strong> PK95NAYA1234503263368118</p>
                        </div>
                        
                        <div class="upload-zone">
                            <i class="bi bi-cloud-arrow-up fs-2 gold-text"></i>
                            <p class="mb-2">Upload payment screenshot</p>
                            <input type="file" id="paymentScreenshot" accept="image/*" class="form-control" style="max-width: 250px; margin: 0 auto;">
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h5 class="mb-3"><i class="bi bi-geo-alt me-2"></i>Delivery Address</h5>
                    <textarea class="form-control" id="deliveryAddress" rows="3" placeholder="Enter your complete delivery address..." required></textarea>
                </div>
                
                <button class="btn-gold w-100 mt-4" onclick="placeOrder()">
                    <i class="bi bi-check-circle me-2"></i>Confirm Order & Pay
                </button>
                
                <div class="text-center mt-3">
                    <a href="index.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.85rem; transition: all 0.3s ease;" 
                       onmouseover="this.style.color='var(--accent-gold)'" 
                       onmouseout="this.style.color='var(--text-muted)'">
                        <i class="bi bi-arrow-left me-1"></i> Continue Shopping
                    </a>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- ========================== -->
<!-- PREMIUM FOOTER              -->
<!-- ========================== -->
<footer class="footer">
    <div class="container">
        <div class="row g-4">
            <!-- Brand -->
            <div class="col-lg-4 col-md-6">
                <h6 class="gold-text">Mr.AYS</h6>
                <p class="brand-desc">
                    Premium luxury products since 2026. Discover our exclusive range of perfumes, testers, watches, and premium eyewear.
                </p>
                <div class="social-links">
                    <a href="https://www.facebook.com/profile.php?id=61593054195065&rdid=B8vJjskA9o9Ho0jJ&share_url=https%3A%2F%2Fwww.facebook.com%2Fshare%2F1LjNSGnjJx%2F#"><i class="bi bi-facebook"></i></a>
                    <a href="https://www.instagram.com/mr.ays_officiall?stkn=MWUzbGM1dHdxNXFkcQ=="><i class="bi bi-instagram"></i></a>
                    <a href="#"><i class="bi bi-twitter-x"></i></a>
                    <a href="https://wa.me/923263368118?text=Hello%20AYS%2C%20I%20want%20to%20know%20more%20about%20your%20perfumes." target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-whatsapp"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-4 col-md-6">
                <h6>Quick Links</h6>
                <ul>
                    <li><a href="index.php"><i class="bi bi-chevron-right"></i> Shop Now</a></li>
                    <li><a href="orders.php"><i class="bi bi-chevron-right"></i> My Orders</a></li>
                    <li><a href="wishlist.php"><i class="bi bi-chevron-right"></i> My Wishlist</a></li>
                    <li><a href="account.php"><i class="bi bi-chevron-right"></i> My Account</a></li>
                </ul>
            </div>

            <!-- Policies -->
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

        <!-- Divider -->
        <div class="footer-divider"></div>

        <!-- Copyright -->
        <div class="copyright">
            &copy; 2026 AYS. All Rights Reserved. powered by <a href="https://zam2.vercel.app" target="_blank" rel="noopener noreferrer" style="color: #ffcc00; text-decoration: none; font-weight: bold;">ZAM Digital Agency</a>
        </div>
    </div>
</footer>

<script>
let cart = JSON.parse(localStorage.getItem('luxeCart')) || [];
const orderSummary = document.getElementById('orderSummary');
const emptyCartState = document.getElementById('emptyCartState');
const checkoutForm = document.getElementById('checkoutForm');

if (cart.length === 0) {
    emptyCartState.style.display = 'block';
    checkoutForm.style.display = 'none';
} else {
    emptyCartState.style.display = 'none';
    checkoutForm.style.display = 'block';
    
    let total = 0;
    let summaryHtml = '';
    
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        summaryHtml += `
            <div class="d-flex justify-content-between mb-2">
                <span>${item.name} x ${item.quantity}</span>
                <strong>PKR ${itemTotal.toFixed(2)}</strong>
            </div>
        `;
    });
    
    summaryHtml += `<hr><div class="d-flex justify-content-between">
        <strong>Total:</strong>
        <strong class="gold-text">PKR ${total.toFixed(2)}</strong>
    </div>`;
    
    orderSummary.innerHTML = summaryHtml;
}

function placeOrder() {
    const address = document.getElementById('deliveryAddress').value;
    const fileInput = document.getElementById('paymentScreenshot');
    
    if (!address) { alert('Please enter your delivery address.'); return; }
    if (!fileInput.files.length) { alert('Please upload payment screenshot.'); return; }
    
    document.getElementById('loadingOverlay').classList.add('active');
    const item = cart[0];
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const screenshot = e.target.result;
        
        fetch('checkout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=place_order&product_id=' + item.id + '&quantity=' + item.quantity + '&total=' + (item.price * item.quantity) + '&product_name=' + encodeURIComponent(item.name) + '&address=' + encodeURIComponent(address) + '&screenshot=' + encodeURIComponent(screenshot)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                localStorage.removeItem('luxeCart');
                window.location.href = 'checkout.php?order_placed=1&order_id=' + data.order_id;
            } else {
                document.getElementById('loadingOverlay').classList.remove('active');
                alert(data.message);
            }
        })
        .catch(err => {
            document.getElementById('loadingOverlay').classList.remove('active');
            alert('Something went wrong. Please try again.');
        });
    };
    reader.readAsDataURL(fileInput.files[0]);
}
</script>

</body>
</html>