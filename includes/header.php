<?php
// ==========================
// SESSION STATUS CHECK (Agar session start nahi hai toh hi start karo)
// ==========================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Agar user logged in hai toh name variable set karo
$loggedInName = isset($_SESSION['client_name']) ? $_SESSION['client_name'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LUXE SCENT · Premium E-commerce Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- Main stylesheet link -->
    <link rel="stylesheet" href="assets/frontend/css/style.css">

    <!-- ============================================ -->
    <!-- HEADER AUR NAV KI INLINE CSS -->
    <!-- ============================================ -->
    <style>
        /* --- CSS VARIABLES --- */
        :root {
            --primary-dark: #0a192f;       /* Deep Navy Blue */
            --accent-gold: #d4af37;        /* Premium Gold */
            --accent-gold-hover: #f7d26b;
            --radius-pill: 50px;
        }

        /* --- HEADER & NAV FIXES --- */
        .brand-gradient {
            background-color: var(--primary-dark);
            padding: 16px 0;
            border-bottom: 3px solid var(--accent-gold);
            position: sticky;
            top: 0;
            z-index: 1000;
            height:110px;
        }

        .gold-text { color: var(--accent-gold) !important; }

        /* Search Bar */
        .search-bar {
            background: rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-pill);
            padding: 4px 4px 4px 20px;
            display: flex;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.3s ease;
        }

        .search-bar:focus-within {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.12);
        }

        .search-bar input {
            border: none;
            background: transparent;
            color: #ffffff;
            width: 100%;
            padding: 10px 0;
            font-size: 0.9rem;
            outline: none;
        }

        .search-bar input::placeholder { color: rgba(255, 255, 255, 0.5); }

        .search-bar button {
            background: var(--accent-gold);
            border: none;
            border-radius: var(--radius-pill);
            padding: 8px 20px;
            color: var(--primary-dark);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-bar button:hover {
            background: var(--accent-gold-hover);
            transform: scale(1.02);
        }

        /* Header Icons */
        .header-icons {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
        }

        .header-icons .icon-btn {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 0.75rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: all 0.3s ease;
        }

        .header-icons .icon-btn:hover {
            color: var(--accent-gold);
        }

        .header-icons .icon-btn i { font-size: 1.4rem; margin-bottom: 2px; }

        #clientGreeting {
            font-size: 0.85rem;
            font-weight: 500;
            background: rgba(212, 175, 55, 0.15);
            padding: 4px 14px;
            border-radius: var(--radius-pill);
            border: 1px solid rgba(212, 175, 55, 0.15);
            color: #fff;
        }

        /* --- CATEGORY NAVIGATION --- */
        .category-nav {
            background: #ffffff;
            border-bottom: 1px solid #e4e7ed;
            padding: 12px 0;
        }

        .category-link {
            color: #6b7a8f;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 6px 18px;
            border-radius: var(--radius-pill);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .category-link:hover {
            color: var(--accent-gold);
            background: rgba(212, 175, 55, 0.08);
        }

        .category-link.active {
            background: var(--accent-gold);
            color: var(--primary-dark);
            font-weight: 600;
        }

        /* --- ADMIN BTN FIX --- */
        .btn-gold {
            background: var(--accent-gold);
            border: none;
            color: var(--primary-dark);
            font-weight: 700;
            padding: 12px 20px;
            border-radius: var(--radius-pill);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn-gold:hover { background: var(--accent-gold-hover); }

        /* MOBILE RESPONSIVE */
        @media (max-width: 768px) {
            .search-bar { margin: 10px 0; }
            .header-icons .icon-btn span { display: none; }
            #clientGreeting { font-size: 0.7rem; padding: 2px 10px; }
            .category-nav .d-flex { flex-wrap: nowrap; overflow-x: auto; justify-content: flex-start !important; }
            .category-link { white-space: nowrap; font-size: 0.8rem; padding: 4px 12px; }
        }
    </style>
    <!-- ============================================ -->
    <!-- HEADER CSS END -->
    <!-- ============================================ -->
</head>
<body>

<!-- Admin Login Overlay -->
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

<!-- Header -->
<header class="brand-gradient">
    <div class="container">
        <div class="row align-items-center g-2">
            <div class="col-3 col-md-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-flower1 gold-text fs-3"></i>
                    <span class="text-white fw-bold fs-4" style="letter-spacing:-1px;">LUXE<span class="gold-text">SCENT</span></span>
                </div>
            </div>
            <div class="col-6 col-md-6">
                <div class="search-bar">
                    <input type="text" placeholder="Search for perfumes, watches, glasses...">
                    <button><i class="bi bi-search"></i></button>
                </div>
            </div>
            <div class="col-3 col-md-4">
                <div class="header-icons">
                    
                    <!-- ========================================== -->
                    <!-- FIXED: User Greeting (PHP se direct load)   -->
                    <!-- ========================================== -->
                    <span id="clientGreeting" class="text-light me-2">
                        <?php if(!empty($loggedInName)): ?>
                            👋 <?php echo htmlspecialchars($loggedInName); ?>
                        <?php else: ?>
                            Guest
                        <?php endif; ?>
                    </span>
                    <!-- ========================================== -->
                    
                    <!-- Cart -->
                    <a href="#" class="icon-btn position-relative" onclick="openCart()">
                        <i class="bi bi-bag"></i>
                        <span>Cart</span>
                        <span class="badge-count cart-badge" style="display:none;">0</span>
                    </a>

                    <!-- Wishlist -->
                    <a href="wishlist.php" class="icon-btn position-relative">
                        <i class="bi bi-heart"></i>
                        <span>Wishlist</span>
                        <span class="badge-count wishlist-badge" style="display:none; background:#dc3545;">0</span>
                    </a>

                    <!-- ========================== -->
                    <!-- DYNAMIC ACCOUNT / LOGOUT   -->
                    <!-- ========================== -->
                    <?php if(isset($_SESSION['client_id'])): ?>
                        <!-- Logged In: Show Logout Button -->
                        <a href="logout.php" class="icon-btn">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Logout</span>
                        </a>
                    <?php else: ?>
                        <!-- Guest: Show Account Link -->
                        <a href="login.php" class="icon-btn">
                            <i class="bi bi-person"></i>
                            <span>Account</span>
                        </a>
                    <?php endif; ?>
                    <!-- ========================== -->

                </div>
            </div>
        </div>
    </div>
</header>

<!-- Category Navigation (FIXED: No # in URL, Scrolls to index.php) -->
<nav class="category-nav">
    <div class="container">
        <div class="d-flex justify-content-center flex-wrap gap-2">
            <!-- 'All Products' par click par index.php par jayega -->
            <a href="index.php" class="category-link active">All Products</a>
            
            <!-- Baaki categories par click par index.php par jayega aur scroll karega -->
            <a href="#" class="category-link" onclick="scrollToCategory('perfumeGrid')">Perfumes</a>
            <a href="#" class="category-link" onclick="scrollToCategory('testerGrid')">Testers</a>
            <a href="#" class="category-link" onclick="scrollToCategory('watchGrid')">Watches</a>
            <a href="#" class="category-link" onclick="scrollToCategory('glassGrid')">Glasses</a>
            <a href="orders.php" class="category-link" onclick="scrollToCategory('orderSection')">Orders</a>
        </div>
    </div>
</nav>

<!-- ========================== -->
<!-- FIXED SCROLL FUNCTION     -->
<!-- ========================== -->
<script>
function scrollToCategory(id) {
    // Pehle index.php par load ho, fir thodi der baad scroll ho
    window.location.href = 'index.php';
    setTimeout(function() {
        const element = document.getElementById(id);
        if(element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, 500);
}
</script>