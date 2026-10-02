<?php
// ============================================ //
// 1. PHP LOGIC: FETCH BANNERS FROM DATABASE    //
// ============================================ //
$bannerStmt = $pdo->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY id LIMIT 3");
$banners = $bannerStmt->fetchAll();

// Fetch all products for JS loading
$productStmt = $pdo->query("SELECT * FROM products");
$allProducts = $productStmt->fetchAll();
?>

<!-- ============================================ -->
<!-- 2. COMPLETE INLINE CSS START -->
<!-- ============================================ -->
<style>
    /* --- CSS VARIABLES (Premium Colors) --- */
    :root {
        --primary-dark: #0a192f;       /* Deep Navy Blue */
        --accent-gold: #d4af37;        /* Premium Gold */
        --accent-gold-hover: #f7d26b;  /* Gold Hover */
        --light-bg: #f4f6f9;
        --white: #ffffff;
        --radius: 12px;
        --radius-pill: 50px;
        --shadow-sm: 0 2px 8px rgba(10, 25, 47, 0.08);
        --shadow-lg: 0 15px 40px rgba(10, 25, 47, 0.15);
    }

    /* --- GLOBAL & CONTAINER FIX --- */
    body { background-color: var(--light-bg); font-family: 'Inter', sans-serif; }
    .gold-text { color: var(--accent-gold) !important; }
    
    .container { max-width: 1700px !important; width: 100%; padding-left: 20px; padding-right: 20px; margin-left: auto; margin-right: auto; }

    /* ==========================
       1. HERO BANNER
       ========================== */
    .hero-section {
        background: linear-gradient(135deg, #0a192f 0%, #112240 100%);
        padding: 80px 0 100px; border-bottom: 3px solid var(--accent-gold);
    }
    .hero-section .hero-content h1 { font-size: 3.5rem; font-weight: 800; line-height: 1.1; }
    .hero-section .hero-content h1 .highlight { color: var(--accent-gold); }
    .hero-section .hero-content p { color: rgba(255, 255, 255, 0.7); font-size: 1.1rem; max-width: 480px; }
    .hero-section .hero-image img { max-height: 360px; width: auto; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); animation: floatImage 6s ease-in-out infinite; }
    @keyframes floatImage { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-12px); } }

    /* ==========================
       2. BUTTONS
       ========================== */
    .btn-gold { background: var(--accent-gold); border: none; color: var(--primary-dark); font-weight: 700; padding: 12px 28px; border-radius: var(--radius-pill); transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
    .btn-gold:hover { background: var(--accent-gold-hover); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(212,175,55,0.3); }
    .btn-outline-gold { background: transparent; border: 2px solid var(--accent-gold); color: #fff; font-weight: 600; padding: 12px 28px; border-radius: var(--radius-pill); transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
    .btn-outline-gold:hover { background: var(--accent-gold); color: var(--primary-dark); transform: translateY(-2px); }
    .btn-outline-light { background: transparent; border: 2px solid #fff; color: #fff; padding: 12px 28px; border-radius: var(--radius-pill); transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
    .btn-outline-light:hover { background: #fff; color: var(--primary-dark); }

    /* ==========================
       3. FLASH SALE BANNER
       ========================== */
    .flash-sale { background: var(--white); border-radius: var(--radius); padding: 20px 30px; box-shadow: var(--shadow-sm); margin-top: -30px; position: relative; z-index: 10; border-left: 5px solid #ff4757; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 15px; }
    .flash-sale .timer { display: flex; gap: 6px; justify-content: center; }
    .flash-sale .timer .time-block { background: var(--primary-dark); color: #fff; padding: 5px 12px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; text-align: center; min-width: 44px; }
    .flash-sale .timer .time-block small { display: block; font-size: 0.5rem; font-weight: 400; opacity: 0.6; }
    .flash-sale .banner-item { display: flex; align-items: center; gap: 10px; padding: 5px 15px; border-radius: 50px; background: #f8f9fa; transition: all 0.3s ease; text-decoration: none; color: #333; }
    .flash-sale .banner-item:hover { background: rgba(212, 175, 55, 0.1); transform: scale(1.02); }
    .flash-sale .banner-item img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
    .flash-sale .banner-item .badge-text { font-weight: 700; font-size: 0.8rem; }

    /* ==========================
       4. SECTION HEADERS & PRODUCTS
       ========================== */
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 12px; border-bottom: 2px solid #e4e7ed; }
    .section-header h2 { font-size: 1.5rem; font-weight: 700; margin: 0; color: var(--primary-dark); }
    .section-header .view-all { color: var(--accent-gold); text-decoration: none; font-weight: 600; font-size: 0.85rem; transition: all 0.3s ease; display: flex; align-items: center; gap: 4px; }
    .section-header .view-all:hover { gap: 8px; }

    .product-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px; }
    .product-card { background: var(--white); border-radius: var(--radius); overflow: hidden; transition: all 0.3s ease; border: 1px solid #e4e7ed; box-shadow: var(--shadow-sm); cursor: pointer; }
    .product-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--accent-gold); }
    .product-card .product-img { height: 180px; width: 100%; object-fit: cover; transition: all 0.3s ease; }
    .product-card:hover .product-img { transform: scale(1.03); }
    .product-card .product-body { padding: 14px; text-align: center; }
    .product-card .product-title { font-size: 0.85rem; font-weight: 600; min-height: 38px; }
    .product-card .product-price { font-size: 1rem; font-weight: 700; color: var(--primary-dark); margin-bottom: 8px; }
    .product-card .product-actions { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }

    /* ==========================
       5. PRODUCT DETAIL MODAL
       ========================== */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: none; justify-content: center; align-items: center; backdrop-filter: blur(8px); }
    .modal-overlay.active { display: flex; }
    .modal-content { background: var(--white); border-radius: var(--radius-lg); max-width: 900px; width: 95%; max-height: 90vh; overflow-y: auto; padding: 30px; position: relative; box-shadow: 0 30px 80px rgba(0,0,0,0.3); animation: modalSlideIn 0.3s ease; }
    @keyframes modalSlideIn { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .modal-close { position: absolute; top: 15px; right: 20px; font-size: 2rem; cursor: pointer; color: #999; transition: 0.3s; background: none; border: none; }
    .modal-close:hover { color: #333; transform: rotate(90deg); }
    .modal-img { width: 100%; height: 400px; object-fit: cover; border-radius: var(--radius); }
    .modal-title { font-size: 2rem; font-weight: 700; margin-top: 15px; }
    .modal-price { font-size: 1.5rem; font-weight: 700; color: var(--accent-gold); }
    .modal-desc { color: #666; line-height: 1.6; margin: 10px 0; }
    .modal-meta { display: flex; gap: 20px; flex-wrap: wrap; margin: 15px 0; padding: 15px; background: var(--light-bg); border-radius: var(--radius); }
    .modal-meta span { font-size: 0.95rem; }
    .modal-meta .label { font-weight: 600; color: #555; }
    .modal-meta .value { font-weight: 500; color: var(--primary-dark); }
    .modal-actions { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 20px; }

    /* ==========================
       6. CART SIDEBAR STYLES
       ========================== */
    .cart-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9998; display: none; }
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

    /* ==========================
       7. RESPONSIVE
       ========================== */
    @media (max-width: 1400px) { .product-grid { grid-template-columns: repeat(5, 1fr); } }
    @media (max-width: 1200px) { .product-grid { grid-template-columns: repeat(4, 1fr); } }
    @media (max-width: 992px) { .product-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 768px) { 
        .product-grid { grid-template-columns: repeat(3, 1fr); } 
        .hero-section .hero-content h1 { font-size: 2.2rem; }
        .hero-section .hero-image img { max-height: 250px; }
        .flash-sale { margin-top: -15px; padding: 15px; flex-direction: column; align-items: stretch; text-align: center; }
        .flash-sale .timer { justify-content: center; }
        .flash-sale .timer .time-block { min-width: 36px; font-size: 0.8rem; }
        .btn-gold, .btn-outline-gold, .btn-outline-light { padding: 10px 20px; font-size: 0.85rem; width: 100%; justify-content: center; }
        .cart-sidebar { width: 320px; right: -320px; }
        .modal-content { padding: 20px; }
        .modal-img { height: 250px; }
    }
    @media (max-width: 576px) { 
        .product-grid { grid-template-columns: repeat(2, 1fr); } 
        .product-card .product-img { height: 140px; }
        .product-card .product-title { font-size: 0.8rem; min-height: 32px; }
        .product-card .product-price { font-size: 0.9rem; }
        .cart-sidebar { width: 100%; right: -100%; }
        .modal-img { height: 200px; }
        .modal-title { font-size: 1.5rem; }
    }
</style>
<!-- ============================================ -->
<!-- CSS END -->
<!-- ============================================ -->


<!-- ============================================ -->
<!-- 3. CART SIDEBAR HTML START -->
<!-- ============================================ -->
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
            <span id="cartTotal">$0.00</span>
        </div>
        <button class="btn-gold checkout-btn" onclick="checkout()">
            <i class="bi bi-bag-check me-2"></i>Proceed to Checkout
        </button>
    </div>
</div>
<!-- ============================================ -->
<!-- CART SIDEBAR HTML END -->
<!-- ============================================ -->


<!-- ============================================ -->
<!-- 4. PRODUCT DETAIL MODAL HTML START -->
<!-- ============================================ -->
<div class="modal-overlay" id="productModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeProductModal()"><i class="bi bi-x-lg"></i></button>
        <div class="row g-4">
            <div class="col-md-6">
                <img src="" id="modalImg" class="modal-img" alt="Product Image">
            </div>
            <div class="col-md-6">
                <h2 class="modal-title" id="modalTitle">Product Name</h2>
                <p class="modal-price" id="modalPrice">$0.00</p>
                
                <div class="modal-meta">
                    <span><span class="label">Color:</span> <span class="value" id="modalColor">Gold</span></span>
                    <span><span class="label">Stock:</span> <span class="value" id="modalStock">In Stock</span></span>
                    <span><span class="label">SKU:</span> <span class="value" id="modalSku">LX-001</span></span>
                </div>
                
                <p class="modal-desc" id="modalDesc">Product description goes here.</p>
                
                <div class="modal-actions">
                    <button class="btn-gold" onclick="addToCartFromModal()">
                        <i class="bi bi-bag-plus me-2"></i>Add to Cart
                    </button>
                    <button class="btn-outline-gold" onclick="addToWishlistFromModal()">
                        <i class="bi bi-heart me-2"></i>Add to Wishlist
                    </button>
                    <button class="btn-outline-light" style="border-color:#333; color:#333;" onclick="closeProductModal()">
                        <i class="bi bi-arrow-left me-2"></i>Back
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- ============================================ -->
<!-- PRODUCT DETAIL MODAL HTML END -->
<!-- ============================================ -->


<!-- ============================================ -->
<!-- 5. YOUR HTML CONTENT STARTS HERE -->
<!-- ============================================ -->

<!-- Hero Banner -->
<section class="hero-section text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 hero-content">
                <h1 class="display-4 fw-bold">Premium <span class="highlight">Luxury</span> Collection</h1>
                <p class="lead mt-3">Discover our exclusive range of perfumes, testers, watches, and premium eyewear. Elevate your style with LUXE SCENT.</p>
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

<!-- Flash Sale Banner (Dynamic from Database) -->
<section class="py-4">
    <div class="container">
        <div class="flash-sale">
            <!-- Left: Flash Sale Title -->
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-lightning-fill text-danger fs-3"></i>
                <div>
                    <h5 class="fw-bold mb-0 text-danger">FLASH SALE</h5>
                    <small class="text-muted">Limited time offer</small>
                </div>
            </div>

            <!-- Middle: Countdown Timer -->
            <div class="timer" id="countdownTimer">
                <div class="time-block"><span id="hours">12</span> <small>hrs</small></div>
                <div class="time-block"><span id="minutes">45</span> <small>min</small></div>
                <div class="time-block"><span id="seconds">30</span> <small>sec</small></div>
            </div>

            <!-- Right: Dynamic Banners from DB -->
            <div class="d-flex flex-wrap gap-2">
                <?php foreach($banners as $banner): ?>
                <a href="<?php echo htmlspecialchars($banner['link_url'] ?? '#'); ?>" class="banner-item text-decoration-none">
                    <?php if(!empty($banner['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($banner['image_url']); ?>" alt="<?php echo htmlspecialchars($banner['title']); ?>">
                    <?php endif; ?>
                    <span class="badge-text <?php echo htmlspecialchars($banner['badge_color'] ?? 'bg-danger'); ?> text-white px-2 py-1 rounded-pill small">
                        <?php echo htmlspecialchars($banner['badge_text']); ?>
                    </span>
                    <small class="text-muted d-none d-md-block"><?php echo htmlspecialchars($banner['title']); ?></small>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- Perfumes -->
<section class="py-4">
    <div class="container">
        <div class="section-header">
            <h2><i class="bi bi-flower1 gold-text me-2"></i>Premium Perfumes</h2>
            <a href="#" class="view-all">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="product-grid" id="perfumeGrid"></div>
    </div>
</section>

<!-- Testers -->
<section class="py-4" style="background: white;">
    <div class="container">
        <div class="section-header">
            <h2><i class="bi bi-flask gold-text me-2"></i>Tester Collection</h2>
            <a href="#" class="view-all">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="product-grid" id="testerGrid"></div>
    </div>
</section>

<!-- Watches -->
<section class="py-4">
    <div class="container">
        <div class="section-header">
            <h2><i class="bi bi-clock gold-text me-2"></i>Luxury Watches</h2>
            <a href="#" class="view-all">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="product-grid" id="watchGrid"></div>
    </div>
</section>

<!-- Glasses -->
<section class="py-4" style="background: white;">
    <div class="container">
        <div class="section-header">
            <h2><i class="bi bi-eyeglasses gold-text me-2"></i>Premium Eyewear</h2>
            <a href="#" class="view-all">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="product-grid" id="glassGrid"></div>
    </div>
</section>


<!-- ============================================ -->
<!-- 6. COMPLETE JAVASCRIPT START -->
<!-- ============================================ -->
<script>
    // ==========================
    // 1. Helper Scroll Function
    // ==========================
    function scrollToCategory(id) {
        document.getElementById(id).scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ==========================
    // 2. Admin Login Functions
    // ==========================
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

    // ==========================
    // 3. Countdown Timer (Persistent across reloads)
    // ==========================
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
            if (seconds > 0) {
                seconds--;
            } else if (minutes > 0) {
                minutes--;
                seconds = 59;
            } else if (hours > 0) {
                hours--;
                minutes = 59;
                seconds = 59;
            } else {
                hours = 24;
                minutes = 0;
                seconds = 0;
            }

            hoursEl.textContent = hours.toString().padStart(2, '0');
            minutesEl.textContent = minutes.toString().padStart(2, '0');
            secondsEl.textContent = seconds.toString().padStart(2, '0');

            localStorage.setItem('luxeTimer', `${hours}:${minutes}:${seconds}`);
        }

        setInterval(tick, 1000);
    }

    // ==========================
    // 4. CART FUNCTIONS
    // ==========================
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

    function addToCart(productId, productName, productPrice, productImage) {
        const existing = cart.find(item => item.id === productId);
        if (existing) {
            existing.quantity += 1;
        } else {
            cart.push({
                id: productId,
                name: productName,
                price: parseFloat(productPrice),
                image: productImage || 'https://via.placeholder.com/60',
                quantity: 1
            });
        }
        saveCart();
        updateCartUI();
        showToast('Product added to cart!', 'success');
    }

    function removeFromCart(productId) {
        cart = cart.filter(item => item.id !== productId);
        saveCart();
        updateCartUI();
    }

    function updateQuantity(productId, change) {
        const item = cart.find(item => item.id === productId);
        if (item) {
            item.quantity += change;
            if (item.quantity <= 0) {
                removeFromCart(productId);
            } else {
                saveCart();
                updateCartUI();
            }
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
            cartTotal.textContent = '$0.00';
            if (cartBadge) cartBadge.style.display = 'none';
            return;
        }

        let total = 0;
        let itemsHtml = '';

        cart.forEach(item => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;
            itemsHtml += `
                <div class="cart-item">
                    <img src="${item.image}" alt="${item.name}">
                    <div class="item-details">
                        <h6>${item.name}</h6>
                        <p>$${item.price.toFixed(2)} x ${item.quantity}</p>
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
        cartTotal.textContent = '$' + total.toFixed(2);
        
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
        showToast('Redirecting to checkout...', 'info');
        setTimeout(() => {
            closeCart();
        }, 1000);
    }

    // ==========================
    // 5. WISHLIST FUNCTIONS
    // ==========================
    function addToWishlist(productId, productName, productPrice, productImage) {
        let wishlist = JSON.parse(localStorage.getItem('luxeWishlist')) || [];
        
        const exists = wishlist.find(item => item.id === productId);
        if (exists) {
            showToast('Item already in wishlist!', 'warning');
            return;
        }

        wishlist.push({
            id: productId,
            name: productName,
            price: parseFloat(productPrice),
            image: productImage || 'https://via.placeholder.com/60'
        });

        localStorage.setItem('luxeWishlist', JSON.stringify(wishlist));
        updateWishlistBadge();
        showToast('Added to wishlist!', 'success');
    }

    function updateWishlistBadge() {
        const wishlist = JSON.parse(localStorage.getItem('luxeWishlist')) || [];
        const badge = document.querySelector('.wishlist-badge');
        if (badge) {
            badge.textContent = wishlist.length;
            badge.style.display = wishlist.length > 0 ? 'block' : 'none';
        }
    }

    // ==========================
    // 6. PRODUCT DETAIL MODAL FUNCTIONS
    // ==========================
    let currentModalProduct = null;

    function openProductModal(product) {
        currentModalProduct = product;
        
        document.getElementById('modalImg').src = product.image_url;
        document.getElementById('modalTitle').textContent = product.name;
        document.getElementById('modalPrice').textContent = '$' + parseFloat(product.price).toFixed(2);
        document.getElementById('modalDesc').textContent = product.description || 'No description available.';
        
        // Mock data for Color, Stock, SKU (Since database doesn't have these columns yet)
        document.getElementById('modalColor').textContent = 'Gold / Silver';
        document.getElementById('modalStock').textContent = 'In Stock';
        document.getElementById('modalSku').textContent = 'LX-' + product.id.toString().padStart(3, '0');
        
        document.getElementById('productModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeProductModal() {
        document.getElementById('productModal').classList.remove('active');
        document.body.style.overflow = 'auto';
        currentModalProduct = null;
    }

    function addToCartFromModal() {
        if (currentModalProduct) {
            addToCart(currentModalProduct.id, currentModalProduct.name, currentModalProduct.price, currentModalProduct.image_url);
            closeProductModal();
        }
    }

    function addToWishlistFromModal() {
        if (currentModalProduct) {
            addToWishlist(currentModalProduct.id, currentModalProduct.name, currentModalProduct.price, currentModalProduct.image_url);
            closeProductModal();
        }
    }

    // ==========================
    // 7. TOAST NOTIFICATION
    // ==========================
    function showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toastContainer') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast-item ${type}`;
        toast.innerHTML = message;
        toastContainer.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease';
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

    // ==========================
    // 8. Load Products into Grids
    // ==========================
    document.addEventListener('DOMContentLoaded', function() {
        startCountdown();
        loadCart();
        updateWishlistBadge();

        const products = <?php echo json_encode($allProducts); ?>;
        
        const perfumeGrid = document.getElementById('perfumeGrid');
        const testerGrid = document.getElementById('testerGrid');
        const watchGrid = document.getElementById('watchGrid');
        const glassGrid = document.getElementById('glassGrid');

        products.forEach(product => {
            // Create Product Card
            const col = document.createElement('div');
            col.className = 'product-card';
            col.onclick = function() { openProductModal(product); };
            col.innerHTML = `
                <img src="${product.image_url}" class="product-img" alt="${product.name}">
                <div class="product-body">
                    <h5 class="product-title">${product.name}</h5>
                    <p class="product-price">$${parseFloat(product.price).toFixed(2)}</p>
                    <div class="product-actions">
                        <button class="btn-gold btn-sm" onclick="event.stopPropagation(); addToCart(${product.id}, '${product.name}', ${product.price}, '${product.image_url}')">
                            <i class="bi bi-bag-plus"></i>
                        </button>
                        <button class="btn-outline-gold btn-sm" onclick="event.stopPropagation(); addToWishlist(${product.id}, '${product.name}', ${product.price}, '${product.image_url}')">
                            <i class="bi bi-heart"></i>
                        </button>
                    </div>
                </div>
            `;

            const nameLower = product.name.toLowerCase();
            
            if (nameLower.includes('tester')) {
                testerGrid.appendChild(col);
            } else if (nameLower.includes('watch')) {
                watchGrid.appendChild(col);
            } else if (nameLower.includes('glass') || nameLower.includes('eyewear')) {
                glassGrid.appendChild(col);
            } else {
                perfumeGrid.appendChild(col);
            }
        });
    });
</script>
<!-- ============================================ -->
<!-- JAVASCRIPT END -->
<!-- ============================================ -->