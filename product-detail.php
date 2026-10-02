<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;

if ($product_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
}

if (!$product) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - LUXE SCENT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --accent-gold: #d4af37; --primary-dark: #0a192f; }
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; }
        .gold-text { color: var(--accent-gold) !important; }
        .brand-gradient { background: var(--primary-dark); border-bottom: 3px solid var(--accent-gold); padding: 16px 0; }
        .product-detail-card { background: white; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); padding: 40px; border: 1px solid #e4e7ed; }
        .product-img { width: 100%; height: 500px; object-fit: cover; border-radius: 12px; }
        .product-title { font-size: 2.5rem; font-weight: 700; }
        .product-price { font-size: 2rem; font-weight: 700; color: var(--accent-gold); }
        .product-meta { display: flex; gap: 20px; flex-wrap: wrap; padding: 15px; background: #f8f9fa; border-radius: 8px; margin: 15px 0; }
        .product-meta span { font-size: 0.95rem; }
        .btn-gold { background: var(--accent-gold); color: var(--primary-dark); font-weight: 700; padding: 12px 28px; border-radius: 50px; border: none; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-gold:hover { background: #f7d26b; transform: translateY(-2px); }
        .btn-outline-gold { background: transparent; border: 2px solid var(--accent-gold); color: var(--accent-gold); padding: 12px 28px; border-radius: 50px; font-weight: 600; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-outline-gold:hover { background: var(--accent-gold); color: var(--primary-dark); }
        @media (max-width: 768px) { .product-img { height: 300px; } .product-title { font-size: 1.8rem; } }
    </style>
</head>
<body>

<header class="brand-gradient">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <a href="index.php" class="text-decoration-none text-white fw-bold fs-4">LUXE<span class="gold-text">SCENT</span></a>
            <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Back to Store</a>
        </div>
    </div>
</header>

<div class="container py-5">
    <div class="product-detail-card">
        <div class="row g-5">
            <div class="col-md-6">
                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" class="product-img" alt="<?php echo htmlspecialchars($product['name']); ?>">
            </div>
            <div class="col-md-6">
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                <p class="product-price">$<?php echo number_format($product['price'], 2); ?></p>
                
                <div class="product-meta">
                    <span><strong>Color:</strong> Gold / Silver</span>
                    <span><strong>Stock:</strong> In Stock</span>
                    <span><strong>SKU:</strong> LX-<?php echo str_pad($product['id'], 3, '0', STR_PAD_LEFT); ?></span>
                </div>
                
                <p class="mt-3" style="line-height:1.8; color:#555;">
                    <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available.')); ?>
                </p>
                
                <div class="mt-4 d-flex gap-3 flex-wrap">
                    <!-- Add to Cart Button -->
                    <button class="btn-gold" onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>, '<?php echo addslashes($product['image_url']); ?>')">
                        <i class="bi bi-bag-plus me-2"></i>Add to Cart
                    </button>
                    
                    <!-- Add to Wishlist Button (Like Add to Cart) -->
                    <button class="btn-outline-gold" onclick="addToWishlist(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>, '<?php echo addslashes($product['image_url']); ?>')">
                        <i class="bi bi-heart me-2"></i>Add to Wishlist
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ==========================
// ADD TO CART FUNCTION
// ==========================
function addToCart(id, name, price, image) {
    let cart = JSON.parse(localStorage.getItem('luxeCart')) || [];
    const existing = cart.find(item => item.id === id);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({ id, name, price: parseFloat(price), image, quantity: 1 });
    }
    localStorage.setItem('luxeCart', JSON.stringify(cart));
    alert('Added to cart!');
}

// ==========================
// ADD TO WISHLIST FUNCTION (Database)
// ==========================
function addToWishlist(id, name, price, image) {
    // Check if user is logged in
    fetch('index.php?ajax=1&action=get_client')
    .then(res => res.json())
    .then(client => {
        if (!client) {
            alert('Please login to add items to wishlist.');
            window.location.href = 'login.php';
            return;
        }
        
        // Send request to add to wishlist
        fetch('add-to-wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'product_id=' + id
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
            } else {
                alert(data.message);
            }
        });
    });
}
</script>

</body>
</html>