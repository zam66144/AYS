<?php
// ==========================
// ACCOUNT PORTAL - USER DASHBOARD
// Shows only when user is logged in
// ==========================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Redirect if not logged in
if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit;
}

$client_id = $_SESSION['client_id'];
$client_name = $_SESSION['client_name'] ?? 'User';

// Fetch client details
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Fetch user's orders
$orderStmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.image_url as product_image, p.price as product_price 
    FROM orders o 
    LEFT JOIN products p ON o.product_id = p.id 
    WHERE o.client_id = ? 
    ORDER BY o.id DESC
");
$orderStmt->execute([$client_id]);
$orders = $orderStmt->fetchAll();

// Fetch wishlist count
$wishlistStmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE client_id = ?");
$wishlistStmt->execute([$client_id]);
$wishlistCount = $wishlistStmt->fetchColumn();

// Order statistics
$totalOrders = count($orders);
$pendingOrders = count(array_filter($orders, fn($o) => $o['status'] === 'pending'));
$processedOrders = count(array_filter($orders, fn($o) => $o['status'] === 'processed'));
$cancelledOrders = count(array_filter($orders, fn($o) => $o['status'] === 'cancelled'));
$returnedOrders = count(array_filter($orders, fn($o) => ($o['return_status'] ?? '') === 'returned'));

// Total spent (excluding cancelled and returned)
$totalSpent = 0;
foreach ($orders as $o) {
    if ($o['status'] !== 'cancelled' && ($o['return_status'] ?? '') !== 'returned') {
        $totalSpent += ($o['product_price'] ?? 0) * ($o['quantity'] ?? 0);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account · LUXE SCENT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-dark: #0a192f;
            --primary-light: #112240;
            --accent-gold: #d4af37;
            --accent-gold-hover: #f7d26b;
            --light-bg: #f4f6f9;
            --white: #ffffff;
            --text-muted: #8892b0;
            --radius: 12px;
            --shadow-sm: 0 2px 8px rgba(10, 25, 47, 0.08);
            --shadow-lg: 0 15px 40px rgba(10, 25, 47, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--light-bg);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }

        .gold-text { color: var(--accent-gold) !important; }
        .container { max-width: 1400px; }

        /* HEADER */
        .account-header {
            background: var(--primary-dark);
            padding: 16px 0;
            border-bottom: 3px solid var(--accent-gold);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .account-header .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .account-header .brand i { font-size: 1.8rem; color: var(--accent-gold); }
        .account-header .brand span { color: #fff; font-weight: 700; font-size: 1.3rem; }
        .account-header .brand span .gold { color: var(--accent-gold); }

        .back-btn {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            transition: 0.3s;
        }
        .back-btn:hover { color: var(--accent-gold); }

        /* SIDEBAR */
        .account-sidebar {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            position: sticky;
            top: 100px;
        }
        .sidebar-profile {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-light));
            padding: 30px 20px;
            text-align: center;
            border-bottom: 3px solid var(--accent-gold);
        }
        .sidebar-profile .avatar {
            width: 80px;
            height: 80px;
            background: var(--accent-gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-dark);
            border: 4px solid rgba(255,255,255,0.2);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.4);
        }
        .sidebar-profile h5 {
            color: #fff;
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        .sidebar-profile small {
            color: rgba(255,255,255,0.6);
            font-size: 0.8rem;
        }
        .sidebar-menu {
            padding: 10px 0;
        }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 22px;
            color: #4b5563;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            cursor: pointer;
        }
        .sidebar-menu a:hover {
            background: rgba(212, 175, 55, 0.06);
            color: var(--accent-gold);
            border-left-color: var(--accent-gold);
        }
        .sidebar-menu a.active {
            background: rgba(212, 175, 55, 0.1);
            color: var(--accent-gold);
            border-left-color: var(--accent-gold);
            font-weight: 700;
        }
        .sidebar-menu a i { font-size: 1.1rem; width: 22px; }
        .sidebar-menu a .badge-count {
            margin-left: auto;
            background: var(--accent-gold);
            color: var(--primary-dark);
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .sidebar-menu a .badge-count.warn {
            background: #f59e0b;
            color: #fff;
        }
        .sidebar-menu a .badge-count.danger {
            background: #dc3545;
            color: #fff;
        }
        .sidebar-menu .divider {
            height: 1px;
            background: #e4e7ed;
            margin: 8px 22px;
        }
        .sidebar-menu a.logout {
            color: #dc3545;
        }
        .sidebar-menu a.logout:hover {
            background: rgba(220, 53, 69, 0.06);
            border-left-color: #dc3545;
            color: #dc3545;
        }

        /* MAIN CONTENT */
        .account-content {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            padding: 30px;
            min-height: 500px;
        }

        /* STATS CARDS */
        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow-sm);
            border: 1px solid #e4e7ed;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent-gold);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent-gold);
        }
        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 14px;
        }
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-dark);
            line-height: 1;
            margin-bottom: 6px;
        }
        .stat-card .stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ORDER CARDS */
        .order-card {
            background: var(--white);
            border: 1px solid #e4e7ed;
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        .order-card:hover {
            border-color: var(--accent-gold);
            box-shadow: var(--shadow-sm);
        }
        .order-card .order-image {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
            border: 1px solid #e4e7ed;
        }
        .order-card .order-info {
            flex: 1;
            min-width: 200px;
        }
        .order-card .order-info h6 {
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 6px;
            font-size: 1rem;
        }
        .order-card .order-info .order-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .order-card .order-info .order-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .order-card .order-price {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--primary-dark);
            white-space: nowrap;
        }
        .order-card .order-status {
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-pending {
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .status-processed {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .status-cancelled {
            background: rgba(239, 68, 68, 0.15);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .status-returned {
            background: rgba(139, 92, 246, 0.15);
            color: #7c3aed;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        /* RETURN BADGES */
        .return-badge {
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .return-requested {
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .return-approved {
            background: rgba(59, 130, 246, 0.15);
            color: #2563eb;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .return-rejected {
            background: rgba(239, 68, 68, 0.15);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .return-completed {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty-state i {
            font-size: 4rem;
            color: var(--accent-gold);
            margin-bottom: 15px;
            display: block;
            opacity: 0.5;
        }
        .empty-state h5 {
            color: var(--primary-dark);
            font-weight: 700;
            margin-bottom: 8px;
        }
        .empty-state p { font-size: 0.9rem; margin-bottom: 20px; }

        /* PROFILE FORM */
        .profile-form .form-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--primary-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .profile-form .form-control {
            border: 2px solid #e4e7ed;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .profile-form .form-control:focus {
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.12);
        }
        .btn-gold {
            background: var(--accent-gold);
            border: none;
            color: var(--primary-dark);
            font-weight: 700;
            padding: 12px 28px;
            border-radius: 50px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn-gold:hover {
            background: var(--accent-gold-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.3);
        }

        /* TABS */
        .account-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e4e7ed;
            padding-bottom: 0;
            overflow-x: auto;
        }
        .account-tabs button {
            background: transparent;
            border: none;
            padding: 12px 22px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .account-tabs button:hover {
            color: var(--accent-gold);
        }
        .account-tabs button.active {
            color: var(--accent-gold);
            border-bottom-color: var(--accent-gold);
        }
        .account-tabs button .count {
            background: #e4e7ed;
            color: #6b7a8f;
            padding: 2px 8px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .account-tabs button.active .count {
            background: var(--accent-gold);
            color: var(--primary-dark);
        }

        .tab-pane { display: none; }
        .tab-pane.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* RETURN DETAILS BOX */
        .return-info-box {
            background: #f8fafc;
            padding: 12px 16px;
            border-radius: 10px;
            border-left: 3px solid var(--accent-gold);
            margin-top: 10px;
        }
        .return-info-box .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            font-weight: 700;
            margin-bottom: 4px;
        }
        .return-info-box .value {
            font-size: 0.9rem;
            color: var(--primary-dark);
            font-weight: 600;
        }

        /* RETURN MODAL */
        .return-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(10, 25, 47, 0.7);
            backdrop-filter: blur(8px);
            z-index: 100000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .return-modal-overlay.active {
            display: flex;
        }
        .return-modal {
            background: #fff;
            border-radius: 16px;
            max-width: 500px;
            width: 100%;
            padding: 30px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.3);
            animation: modalIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes modalIn {
            from { transform: scale(0.8) translateY(30px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }
        .return-modal h4 {
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 8px;
        }
        .return-modal p.subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        .return-modal .reason-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border: 2px solid #e4e7ed;
            border-radius: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .return-modal .reason-option:hover {
            border-color: var(--accent-gold);
            background: rgba(212, 175, 55, 0.04);
        }
        .return-modal .reason-option.selected {
            border-color: var(--accent-gold);
            background: rgba(212, 175, 55, 0.08);
        }
        .return-modal .reason-option input[type="radio"] {
            accent-color: var(--accent-gold);
        }
        .return-modal .reason-option label {
            cursor: pointer;
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 0.9rem;
            margin: 0;
            flex: 1;
        }
        .return-modal textarea {
            border: 2px solid #e4e7ed;
            border-radius: 10px;
            padding: 12px 16px;
            width: 100%;
            font-size: 0.9rem;
            font-family: inherit;
            resize: none;
            margin-top: 12px;
        }
        .return-modal textarea:focus {
            border-color: var(--accent-gold);
            outline: none;
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.12);
        }
        .return-modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 22px;
        }
        .return-modal-actions button {
            flex: 1;
            padding: 13px 20px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }
        .btn-cancel {
            background: #f1f5f9;
            color: #4b5563;
        }
        .btn-cancel:hover { background: #e2e8f0; }
        .btn-submit-return {
            background: var(--accent-gold);
            color: var(--primary-dark);
        }
        .btn-submit-return:hover {
            background: var(--accent-gold-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.35);
        }

        /* RESPONSIVE */
        @media (max-width: 992px) {
            .account-sidebar { position: static; margin-bottom: 20px; }
            .account-content { padding: 20px; }
        }
        @media (max-width: 576px) {
            .order-card { flex-direction: column; text-align: center; }
            .order-card .order-info .order-meta { justify-content: center; }
            .stat-card .stat-value { font-size: 1.4rem; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="account-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <a href="index.php" class="brand">
                <span>Mr.<span class="gold">AYS</span></span>
            </a>
            <a href="index.php" class="back-btn">
                <i class="bi bi-arrow-left"></i> Back to Shop
            </a>
        </div>
    </div>
</header>

<div class="container py-4">
    <div class="row g-4">
        <!-- SIDEBAR -->
        <div class="col-lg-3">
            <div class="account-sidebar">
                <div class="sidebar-profile">
                    <div class="avatar">
                        <?php echo strtoupper(substr($client_name, 0, 1)); ?>
                    </div>
                    <h5><?php echo htmlspecialchars($client_name); ?></h5>
                    <small><?php echo htmlspecialchars($client['email'] ?? ''); ?></small>
                </div>
                <div class="sidebar-menu">
                    <a href="#" class="active" onclick="showTab('dashboard', this); return false;">
                        <i class="bi bi-grid-1x2-fill"></i> Dashboard
                    </a>
                    <a href="#" onclick="showTab('orders', this); return false;">
                        <i class="bi bi-bag-check"></i> My Orders
                        <span class="badge-count"><?php echo $totalOrders; ?></span>
                    </a>
                    <a href="#" onclick="showTab('pending', this); return false;">
                        <i class="bi bi-clock-history"></i> Pending
                        <span class="badge-count warn"><?php echo $pendingOrders; ?></span>
                    </a>
                    <a href="#" onclick="showTab('returns', this); return false;">
                        <i class="bi bi-arrow-return-left"></i> My Returns
                        <?php if ($returnedOrders > 0): ?>
                            <span class="badge-count"><?php echo $returnedOrders; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="#" onclick="showTab('cancelled', this); return false;">
                        <i class="bi bi-x-circle"></i> Cancelled
                        <span class="badge-count danger"><?php echo $cancelledOrders; ?></span>
                    </a>
                    <a href="wishlist.php">
                        <i class="bi bi-heart"></i> Wishlist
                        <span class="badge-count"><?php echo $wishlistCount; ?></span>
                    </a>
                    <div class="divider"></div>
                    <a href="#" onclick="showTab('profile', this); return false;">
                        <i class="bi bi-person-gear"></i> Profile Settings
                    </a>
                    <a href="#" onclick="showTab('addresses', this); return false;">
                        <i class="bi bi-geo-alt"></i> Addresses
                    </a>
                    <a href="#" onclick="showTab('password', this); return false;">
                        <i class="bi bi-shield-lock"></i> Change Password
                    </a>
                    <div class="divider"></div>
                    <a href="logout.php" class="logout">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="col-lg-9">
            <div class="account-content">
                
                <!-- DASHBOARD TAB -->
                <div class="tab-pane active" id="tab-dashboard">
                    <h4 class="fw-bold mb-4" style="color: var(--primary-dark);">
                        <i class="bi bi-grid-1x2-fill gold-text me-2"></i>Dashboard
                    </h4>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: rgba(212, 175, 55, 0.15); color: var(--accent-gold);">
                                    <i class="bi bi-bag-check-fill"></i>
                                </div>
                                <div class="stat-value"><?php echo $totalOrders; ?></div>
                                <div class="stat-label">Total Orders</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #d97706;">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <div class="stat-value"><?php echo $pendingOrders; ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #059669;">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="stat-value"><?php echo $processedOrders; ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: rgba(220, 53, 69, 0.15); color: #dc3545;">
                                    <i class="bi bi-currency-exchange"></i>
                                </div>
                                <div class="stat-value">PKR <?php echo number_format($totalSpent, 0); ?></div>
                                <div class="stat-label">Total Spent</div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-3" style="color: var(--primary-dark);">
                        <i class="bi bi-clock-history gold-text me-2"></i>Recent Orders
                    </h6>
                    <?php if (empty($orders)): ?>
                        <div class="empty-state">
                            <i class="bi bi-bag-x"></i>
                            <h5>No orders yet</h5>
                            <p>Start shopping to see your orders here!</p>
                            <a href="index.php" class="btn-gold">
                                <i class="bi bi-bag me-2"></i>Start Shopping
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($orders, 0, 3) as $order): ?>
                            <div class="order-card">
                                <img src="<?php echo htmlspecialchars($order['product_image'] ?? 'https://via.placeholder.com/80'); ?>" 
                                     alt="<?php echo htmlspecialchars($order['product_name'] ?? 'Product'); ?>" 
                                     class="order-image">
                                <div class="order-info">
                                    <h6><?php echo htmlspecialchars($order['product_name'] ?? 'Product'); ?></h6>
                                    <div class="order-meta">
                                        <span><i class="bi bi-hash"></i> Order #<?php echo $order['id']; ?></span>
                                        <span><i class="bi bi-calendar"></i> <?php echo date('M d, Y', strtotime($order['created_at'] ?? 'now')); ?></span>
                                        <span><i class="bi bi-box"></i> Qty: <?php echo $order['quantity']; ?></span>
                                    </div>
                                </div>
                                <div class="order-price">PKR <?php echo number_format(($order['product_price'] ?? 0) * $order['quantity'], 2); ?></div>
                                <span class="order-status status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($totalOrders > 3): ?>
                            <div class="text-center mt-3">
                                <button class="btn-gold" onclick="showTab('orders', document.querySelectorAll('.sidebar-menu a')[1])">
                                    View All Orders <i class="bi bi-arrow-right ms-1"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- ORDERS TAB -->
                <div class="tab-pane" id="tab-orders">
                    <h4 class="fw-bold mb-4" style="color: var(--primary-dark);">
                        <i class="bi bi-bag-check gold-text me-2"></i>My Orders
                    </h4>
                    
                    <div class="account-tabs">
                        <button class="active" onclick="filterOrders('all', this)">
                            All <span class="count"><?php echo $totalOrders; ?></span>
                        </button>
                        <button onclick="filterOrders('pending', this)">
                            Pending <span class="count"><?php echo $pendingOrders; ?></span>
                        </button>
                        <button onclick="filterOrders('processed', this)">
                            Completed <span class="count"><?php echo $processedOrders; ?></span>
                        </button>
                        <button onclick="filterOrders('cancelled', this)">
                            Cancelled <span class="count"><?php echo $cancelledOrders; ?></span>
                        </button>
                    </div>

                    <div id="ordersList">
                        <?php if (empty($orders)): ?>
                            <div class="empty-state">
                                <i class="bi bi-bag-x"></i>
                                <h5>No orders found</h5>
                                <p>You haven't placed any orders yet.</p>
                                <a href="index.php" class="btn-gold">
                                    <i class="bi bi-bag me-2"></i>Start Shopping
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <div class="order-card" data-status="<?php echo $order['status']; ?>">
                                    <img src="<?php echo htmlspecialchars($order['product_image'] ?? 'https://via.placeholder.com/80'); ?>" 
                                         alt="<?php echo htmlspecialchars($order['product_name'] ?? 'Product'); ?>" 
                                         class="order-image">
                                    <div class="order-info">
                                        <h6><?php echo htmlspecialchars($order['product_name'] ?? 'Product'); ?></h6>
                                        <div class="order-meta">
                                            <span><i class="bi bi-hash"></i> Order #<?php echo $order['id']; ?></span>
                                            <span><i class="bi bi-calendar"></i> <?php echo date('M d, Y', strtotime($order['created_at'] ?? 'now')); ?></span>
                                            <span><i class="bi bi-box"></i> Qty: <?php echo $order['quantity']; ?></span>
                                        </div>
                                    </div>
                                    <div class="order-price">PKR <?php echo number_format(($order['product_price'] ?? 0) * $order['quantity'], 2); ?></div>
                                    <span class="order-status status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                    
                                    <?php if ($order['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-outline-danger rounded-pill" 
                                                onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                            <i class="bi bi-x-circle me-1"></i>Cancel
                                        </button>
                                    
                                    <?php elseif (in_array($order['status'], ['processed', 'delivered']) && empty($order['return_status'])): ?>
                                        <button class="btn btn-sm btn-outline-warning rounded-pill" 
                                                onclick="openReturnModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars(addslashes($order['product_name'] ?? 'Product')); ?>')">
                                            <i class="bi bi-arrow-return-left me-1"></i>Request Return
                                        </button>
                                    
                                    <?php elseif (($order['return_status'] ?? '') === 'requested'): ?>
                                        <span class="return-badge return-requested">
                                            <i class="bi bi-hourglass-split"></i>Return Pending
                                        </span>
                                    
                                    <?php elseif (($order['return_status'] ?? '') === 'approved'): ?>
                                        <span class="return-badge return-approved">
                                            <i class="bi bi-check-circle"></i>Return Approved
                                        </span>
                                    
                                    <?php elseif (($order['return_status'] ?? '') === 'rejected'): ?>
                                        <span class="return-badge return-rejected">
                                            <i class="bi bi-x-circle"></i>Return Rejected
                                        </span>
                                    
                                    <?php elseif (($order['return_status'] ?? '') === 'returned'): ?>
                                        <span class="return-badge return-completed">
                                            <i class="bi bi-check-all"></i>Returned
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- PENDING TAB -->
                <div class="tab-pane" id="tab-pending">
                    <h4 class="fw-bold mb-4" style="color: var(--primary-dark);">
                        <i class="bi bi-clock-history gold-text me-2"></i>Pending Orders
                    </h4>
                    <?php 
                    $pendingList = array_filter($orders, fn($o) => $o['status'] === 'pending');
                    if (empty($pendingList)): ?>
                        <div class="empty-state">
                            <i class="bi bi-check-circle"></i>
                            <h5>No pending orders</h5>
                            <p>All your orders have been processed!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingList as $order): ?>
                            <div class="order-card">
                                <img src="<?php echo htmlspecialchars($order['product_image'] ?? 'https://via.placeholder.com/80'); ?>" 
                                     alt="<?php echo htmlspecialchars($order['product_name'] ?? 'Product'); ?>" 
                                     class="order-image">
                                <div class="order-info">
                                    <h6><?php echo htmlspecialchars($order['product_name'] ?? 'Product'); ?></h6>
                                    <div class="order-meta">
                                        <span><i class="bi bi-hash"></i> Order #<?php echo $order['id']; ?></span>
                                        <span><i class="bi bi-calendar"></i> <?php echo date('M d, Y', strtotime($order['created_at'] ?? 'now')); ?></span>
                                        <span><i class="bi bi-box"></i> Qty: <?php echo $order['quantity']; ?></span>
                                    </div>
                                </div>
                                <div class="order-price">PKR <?php echo number_format(($order['product_price'] ?? 0) * $order['quantity'], 2); ?></div>
                                <span class="order-status status-pending">Pending</span>
                                <button class="btn btn-sm btn-outline-danger rounded-pill" 
                                        onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                    <i class="bi bi-x-circle me-1"></i>Cancel
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- RETURNS TAB (NEW) -->
                <div class="tab-pane" id="tab-returns">
                    <h4 class="fw-bold mb-4" style="color: var(--primary-dark);">
                        <i class="bi bi-arrow-return-left gold-text me-2"></i>My Returns
                    </h4>
                    
                    <?php 
                    $returnList = array_filter($orders, fn($o) => !empty($o['return_status']));
                    if (empty($returnList)): ?>
                        <div class="empty-state">
                            <i class="bi bi-arrow-return-left"></i>
                            <h5>No returns yet</h5>
                            <p>You haven't requested any returns. If you need to return an item, go to My Orders.</p>
                            <button class="btn-gold" onclick="showTab('orders', document.querySelectorAll('.sidebar-menu a')[1])">
                                <i class="bi bi-bag me-2"></i>Go to My Orders
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($returnList as $order): ?>
                            <div class="order-card" style="flex-direction: column; align-items: stretch;">
                                <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap; width: 100%;">
                                    <img src="<?php echo htmlspecialchars($order['product_image'] ?? 'https://via.placeholder.com/80'); ?>" 
                                         alt="<?php echo htmlspecialchars($order['product_name'] ?? 'Product'); ?>" 
                                         class="order-image">
                                    <div class="order-info">
                                        <h6><?php echo htmlspecialchars($order['product_name'] ?? 'Product'); ?></h6>
                                        <div class="order-meta">
                                            <span><i class="bi bi-hash"></i> Order #<?php echo $order['id']; ?></span>
                                            <span><i class="bi bi-box"></i> Qty: <?php echo $order['quantity']; ?></span>
                                            <span><i class="bi bi-cash"></i> PKR <?php echo number_format(($order['product_price'] ?? 0) * $order['quantity'], 2); ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if (($order['return_status'] ?? '') === 'requested'): ?>
                                        <span class="return-badge return-requested">
                                            <i class="bi bi-hourglass-split"></i>Requested
                                        </span>
                                    <?php elseif (($order['return_status'] ?? '') === 'approved'): ?>
                                        <span class="return-badge return-approved">
                                            <i class="bi bi-check-circle"></i>Approved
                                        </span>
                                    <?php elseif (($order['return_status'] ?? '') === 'rejected'): ?>
                                        <span class="return-badge return-rejected">
                                            <i class="bi bi-x-circle"></i>Rejected
                                        </span>
                                    <?php elseif (($order['return_status'] ?? '') === 'returned'): ?>
                                        <span class="return-badge return-completed">
                                            <i class="bi bi-check-all"></i>Refunded
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Return Details -->
                                <div class="row g-3 mt-3">
                                    <div class="col-md-6">
                                        <div class="return-info-box">
                                            <div class="label">📝 Your Reason</div>
                                            <div class="value"><?php echo htmlspecialchars($order['return_reason'] ?? 'N/A'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="return-info-box">
                                            <div class="label">📅 Requested On</div>
                                            <div class="value">
                                                <?php echo !empty($order['return_requested_at']) 
                                                    ? date('M d, Y · h:i A', strtotime($order['return_requested_at'])) 
                                                    : 'N/A'; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (($order['return_status'] ?? '') === 'returned'): ?>
                                        <div class="col-md-6">
                                            <div class="return-info-box" style="border-left-color: #10b981;">
                                                <div class="label">💰 Refund Amount</div>
                                                <div class="value" style="color: #059669;">
                                                    PKR <?php echo number_format($order['refund_amount'] ?? 0, 2); ?> 
                                                    (<?php echo htmlspecialchars($order['refund_method'] ?? 'N/A'); ?>)
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="return-info-box" style="border-left-color: #10b981;">
                                                <div class="label">✅ Refunded On</div>
                                                <div class="value" style="color: #059669;">
                                                    <?php echo !empty($order['return_processed_at']) 
                                                        ? date('M d, Y', strtotime($order['return_processed_at'])) 
                                                        : 'N/A'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($order['admin_notes'])): ?>
                                        <div class="col-12">
                                            <div class="return-info-box" style="border-left-color: #3b82f6;">
                                                <div class="label">💬 Admin Note</div>
                                                <div class="value"><?php echo htmlspecialchars($order['admin_notes']); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (($order['return_status'] ?? '') === 'approved'): ?>
                                    <div class="alert alert-info mt-3 mb-0 py-2 px-3" style="border-radius: 10px; font-size: 0.85rem;">
                                        <i class="bi bi-truck me-1"></i>
                                        <strong>Next step:</strong> Please ship the product back to us. 
                                        Once we receive it, we'll process your refund.
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- CANCELLED TAB -->
                <div class="tab-pane" id="tab-cancelled">
                    <h4 class="fw-bold mb-4" style="color: var(--primary-dark);">
                        <i class="bi bi-x-circle gold-text me-2"></i>Cancelled Orders
                    </h4>
                    <?php 
                    $cancelledList = array_filter($orders, fn($o) => $o['status'] === 'cancelled');
                    if (empty($cancelledList)): ?>
                        <div class="empty-state">
                            <i class="bi bi-check-circle"></i>
                            <h5>No cancelled orders</h5>
                            <p>Great! You haven't cancelled any orders.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($cancelledList as $order): ?>
                            <div class="order-card">
                                <img src="<?php echo htmlspecialchars($order['product_image'] ?? 'https://via.placeholder.com/80'); ?>" 
                                     alt="<?php echo htmlspecialchars($order['product_name'] ?? 'Product'); ?>" 
                                     class="order-image">
                                <div class="order-info">
                                    <h6><?php echo htmlspecialchars($order['product_name'] ?? 'Product'); ?></h6>
                                    <div class="order-meta">
                                        <span><i class="bi bi-hash"></i> Order #<?php echo $order['id']; ?></span>
                                        <span><i class="bi bi-calendar"></i> <?php echo date('M d, Y', strtotime($order['created_at'] ?? 'now')); ?></span>
                                        <span><i class="bi bi-box"></i> Qty: <?php echo $order['quantity']; ?></span>
                                    </div>
                                </div>
                                <div class="order-price">PKR <?php echo number_format(($order['product_price'] ?? 0) * $order['quantity'], 2); ?></div>
                                <span class="order-status status-cancelled">Cancelled</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- PROFILE SETTINGS TAB -->
                <div class="tab-pane" id="tab-profile">
                    <h4 class="fw-bold mb-4" style="color: var(--primary-dark);">
                        <i class="bi bi-person-gear gold-text me-2"></i>Profile Settings
                    </h4>
                    <form class="profile-form" id="profileForm" onsubmit="updateProfile(event)">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="name" 
                                       value="<?php echo htmlspecialchars($client['name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phone" 
                                       value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" 
                                       value="<?php echo htmlspecialchars($client['city'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($client['address'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn-gold">
                                    <i class="bi bi-check-circle me-2"></i>Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ADDRESSES TAB -->
                <div class="tab-pane" id="tab-addresses">
                    <h4 class="fw-bold mb-4" style="color: var(--primary-dark);">
                        <i class="bi bi-geo-alt gold-text me-2"></i>My Addresses
                    </h4>
                    <div class="empty-state">
                        <i class="bi bi-geo-alt"></i>
                        <h5>No saved addresses</h5>
                        <p>Add your delivery addresses for faster checkout.</p>
                        <button class="btn-gold" onclick="alert('Address feature coming soon!')">
                            <i class="bi bi-plus-circle me-2"></i>Add New Address
                        </button>
                    </div>
                </div>

                <!-- CHANGE PASSWORD TAB -->
                <div class="tab-pane" id="tab-password">
                    <h4 class="fw-bold mb-4" style="color: var(--primary-dark);">
                        <i class="bi bi-shield-lock gold-text me-2"></i>Change Password
                    </h4>
                    <form class="profile-form" id="passwordForm" onsubmit="changePassword(event)">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" required minlength="6">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required minlength="6">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn-gold">
                                    <i class="bi bi-shield-check me-2"></i>Update Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- RETURN REQUEST MODAL -->
<div class="return-modal-overlay" id="returnModalOverlay">
    <div class="return-modal">
        <h4><i class="bi bi-arrow-return-left gold-text me-2"></i>Request Return</h4>
        <p class="subtitle">Product: <strong id="returnProductName">—</strong></p>
        
        <input type="hidden" id="returnOrderId" value="">
        
        <label class="form-label" style="font-size: 0.8rem; font-weight: 700; color: var(--primary-dark); text-transform: uppercase; letter-spacing: 0.5px;">
            Why are you returning this?
        </label>
        
        <div class="mt-2">
            <div class="reason-option" onclick="selectReason(this, 'Wrong item received')">
                <input type="radio" name="return_reason_radio" value="Wrong item received">
                <label>📦 Wrong item received</label>
            </div>
            <div class="reason-option" onclick="selectReason(this, 'Product damaged')">
                <input type="radio" name="return_reason_radio" value="Product damaged">
                <label>💥 Product damaged</label>
            </div>
            <div class="reason-option" onclick="selectReason(this, 'Not as described')">
                <input type="radio" name="return_reason_radio" value="Not as described">
                <label>🔍 Not as described</label>
            </div>
            <div class="reason-option" onclick="selectReason(this, 'Changed my mind')">
                <input type="radio" name="return_reason_radio" value="Changed my mind">
                <label>🤔 Changed my mind</label>
            </div>
            <div class="reason-option" onclick="selectReason(this, 'Other')">
                <input type="radio" name="return_reason_radio" value="Other">
                <label>✏️ Other reason</label>
            </div>
        </div>
        
        <textarea id="returnReasonText" rows="3" placeholder="Add more details (optional)..."></textarea>
        
        <div class="return-modal-actions">
            <button class="btn-cancel" onclick="closeReturnModal()">Cancel</button>
            <button class="btn-submit-return" onclick="submitReturnRequest()">
                <i class="bi bi-send me-1"></i>Submit Request
            </button>
        </div>
    </div>
</div>

<script>
    // ==========================
    // TAB SWITCHING
    // ==========================
    function showTab(tabName, element) {
        document.querySelectorAll('.tab-pane').forEach(tab => tab.classList.remove('active'));
        const targetTab = document.getElementById('tab-' + tabName);
        if (targetTab) targetTab.classList.add('active');
        
        document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));
        if (element) element.classList.add('active');
        
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ==========================
    // ORDER FILTERING
    // ==========================
    function filterOrders(status, btn) {
        btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        document.querySelectorAll('#ordersList .order-card').forEach(card => {
            if (status === 'all' || card.dataset.status === status) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // ==========================
    // CANCEL ORDER
    // ==========================
    function cancelOrder(orderId) {
        if (!confirm('Are you sure you want to cancel this order?')) return;
        
        fetch('cancel_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'order_id=' + orderId
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('✅ ' + data.message, 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast('❌ ' + data.message, 'error');
            }
        })
        .catch(() => showToast('❌ Something went wrong!', 'error'));
    }

    // ==========================
    // RETURN REQUEST MODAL
    // ==========================
    function openReturnModal(orderId, productName) {
        document.getElementById('returnOrderId').value = orderId;
        document.getElementById('returnProductName').textContent = productName;
        document.getElementById('returnReasonText').value = '';
        document.querySelectorAll('.reason-option').forEach(opt => opt.classList.remove('selected'));
        document.querySelectorAll('input[name="return_reason_radio"]').forEach(r => r.checked = false);
        
        document.getElementById('returnModalOverlay').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeReturnModal() {
        document.getElementById('returnModalOverlay').classList.remove('active');
        document.body.style.overflow = 'auto';
    }
    
    function selectReason(el, value) {
        document.querySelectorAll('.reason-option').forEach(opt => opt.classList.remove('selected'));
        el.classList.add('selected');
        el.querySelector('input[type="radio"]').checked = true;
    }
    
    function submitReturnRequest() {
        const orderId = document.getElementById('returnOrderId').value;
        const selectedRadio = document.querySelector('input[name="return_reason_radio"]:checked');
        const extraText = document.getElementById('returnReasonText').value.trim();
        
        if (!selectedRadio) {
            showToast('❌ Please select a reason', 'error');
            return;
        }
        
        let reason = selectedRadio.value;
        if (extraText) {
            reason += ' - ' + extraText;
        }
        
        fetch('request_return.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'order_id=' + orderId + '&reason=' + encodeURIComponent(reason)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('✅ ' + data.message, 'success');
                closeReturnModal();
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast('❌ ' + data.message, 'error');
            }
        })
        .catch(() => showToast('❌ Something went wrong!', 'error'));
    }
    
    // Close modal on overlay click
    document.getElementById('returnModalOverlay')?.addEventListener('click', function(e) {
        if (e.target === this) closeReturnModal();
    });
    
    // Close on ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeReturnModal();
    });

    // ==========================
    // UPDATE PROFILE
    // ==========================
    function updateProfile(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        fetch('update_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('✅ ' + data.message, 'success');
            } else {
                showToast('❌ ' + data.message, 'error');
            }
        })
        .catch(() => showToast('❌ Something went wrong!', 'error'));
    }

    // ==========================
    // CHANGE PASSWORD
    // ==========================
    function changePassword(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        const newPass = formData.get('new_password');
        const confirmPass = formData.get('confirm_password');
        
        if (newPass !== confirmPass) {
            showToast('❌ Passwords do not match!', 'error');
            return;
        }
        
        fetch('change_password.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('✅ ' + data.message, 'success');
                form.reset();
            } else {
                showToast('❌ ' + data.message, 'error');
            }
        })
        .catch(() => showToast('❌ Something went wrong!', 'error'));
    }

    // ==========================
    // TOAST NOTIFICATIONS
    // ==========================
    function showToast(message, type = 'info') {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.style.cssText = `
                position: fixed; top: 80px; right: 20px; z-index: 99999;
                display: flex; flex-direction: column; gap: 8px;
            `;
            document.body.appendChild(container);
        }
        
        const toast = document.createElement('div');
        toast.style.cssText = `
            padding: 14px 22px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: slideInRight 0.3s ease;
            background: ${type === 'success' ? 'linear-gradient(135deg, #10b981, #059669)' : 
                          type === 'error' ? 'linear-gradient(135deg, #ef4444, #dc2626)' : 
                          'linear-gradient(135deg, #3b82f6, #2563eb)'};
        `;
        toast.textContent = message;
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
</script>

<style>
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
</style>

</body>
</html>