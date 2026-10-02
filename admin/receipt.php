<?php
session_start();
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}
require_once '../config.php';
// Store constants
if (!defined('STORE_NAME')) define('STORE_NAME', 'Mr.AYS');
if (!defined('STORE_EMAIL')) define('STORE_EMAIL', 'mrays@gmail.com');
if (!defined('STORE_PHONE')) define('STORE_PHONE', '+92 3263368118');
if (!defined('STORE_ADDRESS')) define('STORE_ADDRESS', 'Awan Town, Lahore, Pakistan');
if (!defined('STORE_WEBSITE')) define('STORE_WEBSITE', 'http://localhost/luxe-scent');

// Optionally load notifications if it exists (for the constants)
$notifFile = __DIR__ . '/../includes/notifications.php';
if (file_exists($notifFile)) {
    @require_once $notifFile;
}

$order_id = (int)($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    die('Invalid order ID');
}

// Fetch order details
$stmt = $pdo->prepare("
    SELECT 
        o.*,
        c.name AS client_name,
        c.email AS client_email,
        p.image_url AS product_image,
        p.description AS product_description
    FROM orders o
    LEFT JOIN clients c ON o.client_id = c.id
    LEFT JOIN products p ON o.product_id = p.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die('Order not found');
}

// If receipt number doesn't exist, generate it
$receiptNumber = $order['receipt_number'] ?? '';
if (empty($receiptNumber)) {
    $receiptNumber = 'LUXE-' . date('Y') . '-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT);
    $updateStmt = $pdo->prepare("UPDATE orders SET receipt_number = ?, receipt_generated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$receiptNumber, $order['id']]);
}

$statusLabels = [
    'pending' => 'Pending',
    'processed' => 'Confirmed',
    'returned' => 'Returned',
    'cancelled' => 'Cancelled'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?php echo htmlspecialchars($receiptNumber); ?> - Mr.AYS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800;900&family=Cormorant+Garamond:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #e8ecf1;
            padding: 30px 15px;
            min-height: 100vh;
        }
        
        .receipt-wrapper {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .action-bar h5 {
            margin: 0;
            font-weight: 800;
            color: #0a192f;
        }
        
        .action-bar h5 i { color: #d4af37; }
        
        .btn-print {
            background: linear-gradient(135deg, #d4af37, #b8941f);
            color: #0a192f;
            border: none;
            padding: 10px 24px;
            border-radius: 50px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
        }
        
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(212, 175, 55, 0.4);
        }
        
        .btn-back {
            background: transparent;
            border: 2px solid #d4af37;
            color: #0a192f;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-back:hover {
            background: #d4af37;
            color: #0a192f;
        }
        
        .receipt {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(10, 25, 47, 0.15);
            position: relative;
        }
        
        .receipt::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #d4af37, #f7d26b, #d4af37);
        }
        
        /* Header */
        .receipt-header {
            background: linear-gradient(135deg, #0a192f, #112240);
            padding: 40px 40px 30px;
            position: relative;
            overflow: hidden;
        }
        
        .receipt-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15), transparent 70%);
            border-radius: 50%;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            z-index: 2;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .store-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        
        .store-brand .brand-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #d4af37, #b8941f);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: #0a192f;
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.4);
        }
        
        .store-brand h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.9rem;
            font-weight: 700;
            color: #fff;
            margin: 0;
            letter-spacing: 2px;
        }
        
        .store-brand h1 span { color: #d4af37; }
        
        .store-brand p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.75rem;
            margin: 3px 0 0;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        
        .receipt-info {
            text-align: right;
        }
        
        .receipt-info .receipt-label {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .receipt-info .receipt-num {
            font-size: 1.3rem;
            font-weight: 800;
            color: #d4af37;
            letter-spacing: 1px;
        }
        
        .receipt-info .receipt-date {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
            margin-top: 8px;
        }
        
        /* Status Ribbon */
        .status-ribbon {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 15px;
        }
        
        .status-ribbon.pending {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }
        
        .status-ribbon.processed {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.4);
        }
        
        .status-ribbon.cancelled,
        .status-ribbon.returned {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.4);
        }
        
        /* Body */
        .receipt-body {
            padding: 40px;
        }
        
        /* Section */
        .section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: #0a192f;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f2f5;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title::before {
            content: '';
            width: 20px;
            height: 3px;
            background: #d4af37;
            border-radius: 3px;
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-item {
            padding: 6px 0;
        }
        
        .info-label {
            font-size: 0.7rem;
            color: #8892b0;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 0.92rem;
            color: #0a192f;
            font-weight: 600;
            line-height: 1.5;
        }
        
        /* Product Table */
        .product-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 25px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e4e7ed;
        }
        
        .product-table thead {
            background: linear-gradient(135deg, #0a192f, #112240);
        }
        
        .product-table thead th {
            padding: 14px 18px;
            text-align: left;
            color: #d4af37;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
        }
        
        .product-table tbody tr {
            background: #ffffff;
        }
        
        .product-table tbody td {
            padding: 16px 18px;
            border-bottom: 1px solid #f0f2f5;
            font-size: 0.9rem;
            color: #0a192f;
            vertical-align: middle;
        }
        
        .product-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .product-cell {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        
        .product-cell img {
            width: 55px;
            height: 55px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #f0f2f5;
        }
        
        .product-cell .product-name {
            font-weight: 700;
            font-size: 0.92rem;
            color: #0a192f;
            margin-bottom: 3px;
        }
        
        .product-cell .product-desc {
            font-size: 0.72rem;
            color: #8892b0;
        }
        
        /* Total Section */
        .total-section {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 14px;
            padding: 25px;
            border: 1px solid #e4e7ed;
            margin-bottom: 25px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.9rem;
            color: #4b5563;
        }
        
        .total-row.grand {
            padding: 16px 0 0;
            margin-top: 12px;
            border-top: 2px dashed #d4af37;
            font-size: 1.3rem;
            font-weight: 900;
            color: #0a192f;
        }
        
        .total-row.grand .amount {
            color: #d4af37;
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.6rem;
        }
        
        /* Footer */
        .receipt-footer {
            background: #f8fafc;
            padding: 25px 40px;
            border-top: 2px solid #e4e7ed;
            text-align: center;
        }
        
        .thank-you {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.5rem;
            color: #d4af37;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }
        
        .thanks-note {
            color: #8892b0;
            font-size: 0.82rem;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .contact-row {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e4e7ed;
        }
        
        .contact-row span {
            font-size: 0.78rem;
            color: #4b5563;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .contact-row span i {
            color: #d4af37;
        }
        
        /* Watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            font-size: 8rem;
            font-weight: 900;
            color: rgba(212, 175, 55, 0.04);
            font-family: 'Cormorant Garamond', serif;
            letter-spacing: 10px;
            pointer-events: none;
            z-index: 0;
            white-space: nowrap;
        }
        
        .receipt-body, .receipt-header, .receipt-footer {
            position: relative;
            z-index: 1;
        }
        
        /* Print styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .action-bar {
                display: none !important;
            }
            .receipt {
                box-shadow: none;
                border-radius: 0;
            }
            .watermark {
                color: rgba(212, 175, 55, 0.08);
            }
        }
        
        @media (max-width: 600px) {
            .receipt-header { padding: 30px 25px 25px; }
            .receipt-body { padding: 25px; }
            .receipt-footer { padding: 20px 25px; }
            .header-top { flex-direction: column; }
            .receipt-info { text-align: left; }
            .info-grid { grid-template-columns: 1fr; gap: 15px; }
            .store-brand h1 { font-size: 1.5rem; }
            .watermark { font-size: 4rem; }
            .action-bar {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
</head>
<body>

<div class="receipt-wrapper">
    
    <!-- Action Bar -->
    <div class="action-bar">
        <h5><i class="bi bi-receipt-cutoff me-2"></i>Order Receipt Preview</h5>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn-print" onclick="window.print()">
                <i class="bi bi-printer-fill"></i>Print Receipt
            </button>
            <a href="index.php" class="btn-back">
                <i class="bi bi-arrow-left"></i>Back
            </a>
        </div>
    </div>
    
    <!-- Receipt -->
    <div class="receipt">
        
        <!-- Watermark -->
        <div class="watermark">Mr.AYS</div>
        
        <!-- Header -->
        <div class="receipt-header">
            <div class="header-top">
                <div class="store-brand">
                    <div class="brand-icon">
                    </div>
                    <div>
                        <h1>Mr.<span>AYS</span></h1>
                        <p>Premium Luxury Store</p>
                    </div>
                </div>
                
                <div class="receipt-info">
                    <div class="receipt-label">Receipt Number</div>
                    <div class="receipt-num"><?php echo htmlspecialchars($receiptNumber); ?></div>
                    <div class="receipt-date">
                        <i class="bi bi-calendar3 me-1"></i>
                        <?php echo date('F j, Y • g:i A', strtotime($order['created_at'])); ?>
                    </div>
                    
                    <div class="status-ribbon <?php echo htmlspecialchars($order['status']); ?>">
                        <?php if($order['status'] === 'pending'): ?>
                            <i class="bi bi-clock-fill"></i>Pending Confirmation
                        <?php elseif($order['status'] === 'processed'): ?>
                            <i class="bi bi-check-circle-fill"></i>Confirmed
                        <?php elseif($order['status'] === 'cancelled'): ?>
                            <i class="bi bi-x-circle-fill"></i>Cancelled
                        <?php elseif($order['status'] === 'returned'): ?>
                            <i class="bi bi-arrow-counterclockwise"></i>Returned
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Body -->
        <div class="receipt-body">
            
            <!-- Customer & Order Info -->
            <div class="info-grid">
                <div>
                    <div class="section-title">Customer Details</div>
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($order['client_name'] ?? 'Guest'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($order['client_email'] ?? 'N/A'); ?></div>
                    </div>
                </div>
                
                <div>
                    <div class="section-title">Order Details</div>
                    <div class="info-item">
                        <div class="info-label">Order ID</div>
                        <div class="info-value">#<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Delivery Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($order['address'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Products -->
            <div class="section-title">Order Items</div>
            <table class="product-table">
                <thead>
                    <tr>
                        <th style="width: 60%;">Product</th>
                        <th style="width: 15%; text-align: center;">Qty</th>
                        <th style="width: 25%; text-align: right;">Price</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="product-cell">
                                <img src="<?php echo htmlspecialchars($order['product_image'] ?? 'https://via.placeholder.com/55'); ?>" alt="Product" onerror="this.src='https://via.placeholder.com/55'">
                                <div>
                                    <div class="product-name"><?php echo htmlspecialchars($order['product_name']); ?></div>
                                    <div class="product-desc"><?php echo htmlspecialchars(substr($order['product_description'] ?? 'Premium luxury product', 0, 60)); ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align: center; font-weight: 700;"><?php echo (int)$order['quantity']; ?></td>
                        <td style="text-align: right; font-weight: 700;">
                            PKR <?php echo number_format($order['total'] / max(1, $order['quantity']), 2); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Totals -->
            <div class="total-section">
                <div class="total-row">
                    <span>Subtotal</span>
                    <span>PKR <?php echo number_format($order['total'], 2); ?></span>
                </div>
                <div class="total-row">
                    <span>Shipping</span>
                    <span style="color: #10b981; font-weight: 700;">
                        <?php echo ($order['total'] > 100) ? 'FREE' : 'PKR 5.99'; ?>
                    </span>
                </div>
                <div class="total-row">
                    <span>Tax</span>
                    <span>PKR 0.00</span>
                </div>
                <div class="total-row grand">
                    <span>Total Amount</span>
                    <span class="amount">PKR <?php echo number_format($order['total'], 2); ?></span>
                </div>
            </div>
            
        </div>
        
        <!-- Footer -->
        <div class="receipt-footer">
            <div class="thank-you">Thank You for Your Order!</div>
            <div class="thanks-note">
                We appreciate your business and hope you enjoy your premium luxury products.<br>
                For any queries regarding this order, please contact us.
            </div>
            
            <div class="contact-row">
                <span><i class="bi bi-envelope-fill"></i><?php echo STORE_EMAIL; ?></span>
                <span><i class="bi bi-telephone-fill"></i><?php echo STORE_PHONE; ?></span>
                <span><i class="bi bi-globe"></i><?php echo STORE_WEBSITE; ?></span>
            </div>
        </div>
        
    </div>
    
</div>

</body>
</html>