// ===== FRONTEND APPLICATION =====
let currentOrderId = null;
let currentClient = null;
let orderStatusCheck = null;

// ===== API HELPER =====
async function apiCall(action, data = {}) {
    const formData = new FormData();
    formData.append('action', action);
    Object.keys(data).forEach(key => formData.append(key, data[key]));
    const response = await fetch(window.location.href, {
        method: 'POST',
        body: formData
    });
    return await response.json();
}

// ===== TOAST =====
function showToast(message, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast-item ${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.4s ease forwards';
        setTimeout(() => toast.remove(), 500);
    }, 4000);
}

// ===== LOAD PRODUCTS =====
async function loadProducts() {
    try {
        const response = await fetch(`${window.location.href}?ajax=1&action=get_products`);
        const products = await response.json();
        
        const categories = {
            perfume: 'perfumeGrid',
            tester: 'testerGrid',
            watch: 'watchGrid',
            glass: 'glassGrid'
        };
        
        Object.keys(categories).forEach(cat => {
            const filtered = products.filter(p => p.category === cat);
            renderProducts(categories[cat], filtered);
        });
        
        // Populate order select
        const sel = document.getElementById('orderProduct');
        if (sel) {
            sel.innerHTML = '<option value="">Choose a product...</option>' + 
                products.map(p => `<option value="${p.id}" data-name="${p.name}" data-price="${p.price}">${p.name} ($${Number(p.price).toFixed(2)})</option>`).join('');
        }
    } catch(e) {
        console.error('Error loading products:', e);
    }
}

function renderProducts(gridId, products) {
    const grid = document.getElementById(gridId);
    if (!grid) return;
    
    if (!products || products.length === 0) {
        grid.innerHTML = `<div class="col-12 text-center py-4 text-muted">No products available</div>`;
        return;
    }
    
    grid.innerHTML = products.map(p => `
        <div class="product-card">
            ${p.discount ? `<span class="discount-tag">${p.discount}% OFF</span>` : ''}
            <img src="${p.image_url}" class="product-img" alt="${p.name}" loading="lazy">
            <div class="product-body">
                <div class="product-rating">
                    <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-half"></i>
                    <span>(${Math.floor(Math.random() * 500) + 50})</span>
                </div>
                <div class="product-title">${p.name}</div>
                <div class="product-price">
                    $${Number(p.price).toFixed(2)}
                    ${p.original_price ? `<span class="original">$${Number(p.original_price).toFixed(2)}</span>` : ''}
                </div>
                <button class="add-cart-btn" onclick="quickOrder(${p.id}, '${p.name}', ${p.price})">
                    <i class="bi bi-cart-plus me-1"></i>Add to Cart
                </button>
            </div>
        </div>
    `).join('');
}

// ===== REGISTRATION =====
async function registerClient(e) {
    e.preventDefault();
    const name = document.getElementById('regName').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const password = document.getElementById('regPassword').value;
    
    if (!name || !email || password.length < 4) {
        document.getElementById('registerMessage').innerHTML = 
            '<div class="status-msg warning"><i class="bi bi-exclamation-triangle me-2"></i>Please fill all fields correctly.</div>';
        return;
    }
    
    const result = await apiCall('register', { name, email, password });
    if (result.success) {
        currentClient = { name: result.name, email };
        document.getElementById('registerMessage').innerHTML = 
            `<div class="status-msg success"><i class="bi bi-check-circle me-2"></i>Welcome ${result.name}!</div>`;
        updateGreeting();
        document.getElementById('registerForm').reset();
        showToast(`Welcome ${result.name}!`, 'success');
        document.getElementById('paymentSection').classList.add('active');
    } else {
        document.getElementById('registerMessage').innerHTML = 
            `<div class="status-msg danger"><i class="bi bi-x-circle me-2"></i>${result.message}</div>`;
    }
}

// ===== QUICK ORDER =====
function quickOrder(id, name, price) {
    if (!currentClient) {
        showToast('Please register first.', 'warning');
        document.getElementById('orderSection').scrollIntoView({ behavior: 'smooth' });
        return;
    }
    document.getElementById('orderProduct').value = id;
    document.getElementById('orderQty').value = 1;
    document.getElementById('orderSection').scrollIntoView({ behavior: 'smooth' });
    setTimeout(() => placeOrder(new Event('submit')), 300);
}

// ===== PLACE ORDER =====
async function placeOrder(e) {
    e.preventDefault();
    if (!currentClient) {
        showToast('Please register first.', 'warning');
        return;
    }
    
    const select = document.getElementById('orderProduct');
    const pid = parseInt(select.value);
    const qty = parseInt(document.getElementById('orderQty').value) || 1;
    
    if (!pid) {
        showToast('Please select a product.', 'warning');
        return;
    }
    
    const opt = select.options[select.selectedIndex];
    const name = opt.dataset.name;
    const price = parseFloat(opt.dataset.price);
    const total = price * qty;
    
    const result = await apiCall('place_order', {
        product_id: pid,
        product_name: name,
        quantity: qty,
        total: total
    });
    
    if (result.success) {
        currentOrderId = result.order_id;
        document.getElementById('paymentSection').classList.add('active');
        document.getElementById('orderStatus').innerHTML = `
            <div class="d-flex justify-content-between">
                <span><strong>${name}</strong> x${qty}</span>
                <span class="fw-bold gold-text">$${total.toFixed(2)}</span>
            </div>
            <div class="mt-1"><span class="badge-pending">⏳ Awaiting Payment</span></div>
            <small class="text-muted">Transfer total to bank account below.</small>
        `;
        document.getElementById('paymentMessage').innerHTML = '';
        document.getElementById('whatsappFloat').style.display = 'none';
        showToast('Order placed! Complete payment.', 'success');
    } else {
        showToast(result.message, 'danger');
    }
}

// ===== SUBMIT SCREENSHOT =====
async function submitScreenshot() {
    const fileInput = document.getElementById('screenshotInput');
    if (!fileInput.files || !fileInput.files[0]) {
        document.getElementById('paymentMessage').innerHTML = 
            '<div class="status-msg warning"><i class="bi bi-exclamation-triangle me-2"></i>Please select a screenshot.</div>';
        return;
    }
    
    const file = fileInput.files[0];
    if (!file.type.startsWith('image/')) {
        document.getElementById('paymentMessage').innerHTML = 
            '<div class="status-msg danger"><i class="bi bi-x-circle me-2"></i>Please upload an image file.</div>';
        return;
    }
    
    const reader = new FileReader();
    reader.onload = async function(ev) {
        const base64 = ev.target.result;
        document.getElementById('paymentMessage').innerHTML = 
            '<div class="status-msg info"><i class="bi bi-hourglass-split me-2"></i>Uploading screenshot...</div>';
        
        const result = await apiCall('upload_screenshot', {
            order_id: currentOrderId,
            screenshot: base64
        });
        
        if (result.success) {
            document.getElementById('paymentMessage').innerHTML = 
                '<div class="status-msg info"><i class="bi bi-hourglass-split me-2"></i>⏳ Waiting for admin verification...</div>';
            
            if (orderStatusCheck) clearInterval(orderStatusCheck);
            orderStatusCheck = setInterval(checkOrderStatus, 3000);
        } else {
            document.getElementById('paymentMessage').innerHTML = 
                `<div class="status-msg danger"><i class="bi bi-x-circle me-2"></i>${result.message}</div>`;
        }
    };
    reader.readAsDataURL(file);
}

// ===== CHECK ORDER STATUS =====
async function checkOrderStatus() {
    try {
        const response = await fetch(`${window.location.href}?ajax=1&action=get_order_status&order_id=${currentOrderId}`);
        const data = await response.json();
        
        if (data.status === 'processed') {
            clearInterval(orderStatusCheck);
            document.getElementById('paymentMessage').innerHTML = 
                '<div class="status-msg success"><i class="bi bi-check-circle me-2"></i>✅ Payment confirmed! Order processed.</div>';
            document.getElementById('orderStatus').innerHTML += '<br><span class="badge-processed">✅ Verified</span>';
            
            const waMsg = `Hello LUXE SCENT, I placed an order for ${data.product_name}. Order #${currentOrderId}. Thank you!`;
            const waUrl = `https://wa.me/971501234567?text=${encodeURIComponent(waMsg)}`;
            const waBtn = document.getElementById('whatsappFloat');
            waBtn.href = waUrl;
            waBtn.style.display = 'flex';
            waBtn.innerHTML = `<i class="bi bi-whatsapp fs-4"></i> Confirm Order on WhatsApp`;
            showToast('Order confirmed! Check WhatsApp.', 'success');
        } else if (data.status === 'cancelled') {
            clearInterval(orderStatusCheck);
            document.getElementById('paymentMessage').innerHTML = 
                '<div class="status-msg danger"><i class="bi bi-x-circle me-2"></i>❌ Order cancelled by admin.</div>';
            document.getElementById('orderStatus').innerHTML += '<br><span class="badge-cancelled">❌ Cancelled</span>';
            document.getElementById('whatsappFloat').style.display = 'none';
            showToast('Order cancelled.', 'danger');
        }
    } catch(e) {
        console.error('Error checking status:', e);
    }
}

// ===== ADMIN LOGIN =====
async function adminLogin() {
    const user = document.getElementById('adminUser').value;
    const pass = document.getElementById('adminPass').value;
    
    if (!user || !pass) {
        showToast('Please enter credentials.', 'warning');
        return;
    }
    
    const result = await apiCall('admin_login', { username: user, password: pass });
    if (result.success) {
        window.location.href = 'admin/index.php';
    } else {
        showToast('Invalid credentials.', 'danger');
    }
}

function closeAdminLogin() {
    document.getElementById('adminOverlay').classList.remove('active');
}

function showAdminLogin() {
    document.getElementById('adminOverlay').classList.add('active');
}

// ===== UPDATE GREETING =====
function updateGreeting() {
    const el = document.getElementById('clientGreeting');
    if (el && currentClient) {
        el.textContent = `👋 ${currentClient.name}`;
    }
}

// ===== RESTORE CLIENT =====
async function restoreClient() {
    try {
        const response = await fetch(`${window.location.href}?ajax=1&action=get_client`);
        const client = await response.json();
        if (client) {
            currentClient = client;
            updateGreeting();
        }
    } catch(e) {
        console.error('Error restoring client:', e);
    }
}

// ===== DOUBLE CLICK FOR ADMIN =====
document.addEventListener('dblclick', function(e) {
    if (e.target.closest('.brand-logo') || e.target.closest('.gold-text')) {
        showAdminLogin();
    }
});

// ===== INIT =====
document.addEventListener('DOMContentLoaded', function() {
    loadProducts();
    restoreClient();
});

console.log('✨ LUXE SCENT · Store Loaded');