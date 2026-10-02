// ===== THEME MANAGEMENT =====
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Update icon
    const icon = document.querySelector('.theme-toggle i');
    if (icon) {
        icon.className = newTheme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    }
}

function loadTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    // Update icon
    const icon = document.querySelector('.theme-toggle i');
    if (icon) {
        icon.className = savedTheme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    }
}

// ===== GLOBAL STATE =====
let currentOrderId = null;
let currentClient = null;
let adminLogged = false;

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

// ===== TOAST NOTIFICATIONS =====
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer') || createToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = message;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.5s ease forwards';
        setTimeout(() => toast.remove(), 500);
    }, 4000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

// ===== LOAD PRODUCTS =====
async function loadProducts() {
    try {
        const response = await fetch(`${window.location.href}?ajax=1&action=get_products`);
        const products = await response.json();
        const grid = document.getElementById('productGrid');
        if (!grid) return;
        
        grid.innerHTML = products.map((p, index) => `
            <div class="col-md-3 col-6 animate-fade-up" style="animation-delay: ${index * 0.1}s">
                <div class="product-card p-2 text-center">
                    <img src="${p.image_url}" class="product-img w-100 rounded-4" alt="${p.name}" loading="lazy">
                    <h6 class="mt-3 fw-bold">${p.name}</h6>
                    <span class="gold-text fw-bold fs-5">${formatPrice(p.price)}</span>
                    <button class="btn-outline-gold btn-sm w-100 mt-2" onclick="quickOrder(${p.id}, '${p.name}', ${p.price})">
                        <i class="bi bi-cart-plus me-1"></i>Buy Now
                    </button>
                </div>
            </div>
        `).join('');
        
        const sel = document.getElementById('orderProduct');
        if (sel) {
            sel.innerHTML = products.map(p => 
                `<option value="${p.id}" data-name="${p.name}" data-price="${p.price}">${p.name} (${formatPrice(p.price)})</option>`
            ).join('');
        }
    } catch(e) {
        console.error('Error loading products:', e);
    }
}

function formatPrice(price) {
    return '$' + Number(price).toFixed(2);
}

// ===== CLIENT REGISTRATION =====
async function registerClient(e) {
    e.preventDefault();
    const name = document.getElementById('regName').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const password = document.getElementById('regPassword').value;
    
    if (!name || !email || password.length < 4) {
        document.getElementById('registerMessage').innerHTML = 
            '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Please fill all fields correctly.</span>';
        return;
    }
    
    const result = await apiCall('register', { name, email, password });
    if (result.success) {
        currentClient = { name: result.name, email };
        document.getElementById('registerMessage').innerHTML = 
            `<span class="text-success"><i class="bi bi-check-circle me-1"></i>✅ Welcome ${result.name}! You can now order.</span>`;
        updateGreeting();
        document.getElementById('registerForm').reset();
        showToast(`Welcome ${result.name}!`, 'success');
    } else {
        document.getElementById('registerMessage').innerHTML = 
            `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>${result.message || 'Registration failed'}</span>`;
    }
}

// ===== QUICK ORDER =====
function quickOrder(id, name, price) {
    if (!currentClient) {
        document.getElementById('registerSection').scrollIntoView({ behavior: 'smooth' });
        showToast('Please register or login first.', 'warning');
        return;
    }
    document.getElementById('orderProduct').value = id;
    document.getElementById('orderQty').value = 1;
    document.getElementById('registerSection').scrollIntoView({ behavior: 'smooth' });
    setTimeout(() => placeOrder(new Event('submit')), 300);
}

// ===== PLACE ORDER =====
async function placeOrder(e) {
    e.preventDefault();
    if (!currentClient) {
        showToast('Please register or login first.', 'warning');
        return;
    }
    
    const select = document.getElementById('orderProduct');
    const pid = parseInt(select.value);
    const qty = parseInt(document.getElementById('orderQty').value) || 1;
    
    if (!pid) {
        showToast('Please select a perfume.', 'warning');
        return;
    }
    
    const selectedOption = select.options[select.selectedIndex];
    const name = selectedOption.dataset.name;
    const price = parseFloat(selectedOption.dataset.price);
    const total = price * qty;
    
    const result = await apiCall('place_order', {
        product_id: pid,
        product_name: name,
        quantity: qty,
        total: total
    });
    
    if (result.success) {
        currentOrderId = result.order_id;
        document.getElementById('paymentSection').style.display = 'block';
        document.getElementById('orderStatus').innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <span><strong>🛒 ${name}</strong> x${qty}</span>
                <span class="fw-bold gold-text fs-5">${formatPrice(total)}</span>
            </div>
            <div class="mt-2">
                <span class="badge badge-pending">⏳ Awaiting payment</span>
            </div>
            <small class="text-muted d-block mt-1">Transfer total to bank account below.</small>
        `;
        document.getElementById('paymentMessage').innerHTML = '';
        document.getElementById('whatsappFloat').style.display = 'none';
        showToast('Order placed! Please complete payment.', 'success');
    } else {
        showToast(result.message || 'Failed to place order.', 'danger');
    }
}

// ===== SUBMIT SCREENSHOT =====
async function submitScreenshot() {
    const fileInput = document.getElementById('screenshotInput');
    if (!fileInput.files || !fileInput.files[0]) {
        document.getElementById('paymentMessage').innerHTML = 
            '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Please select a screenshot.</span>';
        return;
    }
    
    const file = fileInput.files[0];
    if (!file.type.startsWith('image/')) {
        document.getElementById('paymentMessage').innerHTML = 
            '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Please upload an image file.</span>';
        return;
    }
    
    const reader = new FileReader();
    reader.onload = async function(ev) {
        const base64 = ev.target.result;
        document.getElementById('paymentMessage').innerHTML = 
            '<span class="text-info"><i class="bi bi-hourglass-split me-1"></i>Uploading screenshot...</span>';
        
        const result = await apiCall('upload_screenshot', {
            order_id: currentOrderId,
            screenshot: base64
        });
        
        if (result.success) {
            document.getElementById('paymentMessage').innerHTML = 
                '<span class="text-info"><i class="bi bi-hourglass-split me-1"></i>⏳ Screenshot uploaded. Waiting for admin verification...</span>';
            
            setTimeout(() => {
                document.getElementById('paymentMessage').innerHTML = 
                    '<span class="text-success"><i class="bi bi-check-circle me-1"></i>✅ Payment confirmed! Order is being prepared.</span>';
                document.getElementById('orderStatus').innerHTML += 
                    '<br><span class="badge badge-processed">✅ Verified</span>';
                
                const waMsg = `Hello LUXE SCENT, I just placed an order for perfume. Thank you!`;
                const waUrl = `https://wa.me/971501234567?text=${encodeURIComponent(waMsg)}`;
                document.getElementById('whatsappFloat').href = waUrl;
                document.getElementById('whatsappFloat').style.display = 'flex';
                showToast('Order confirmed! Check WhatsApp.', 'success');
                
                if (adminLogged) loadAdminOrders('all');
            }, 2000 + Math.random() * 1000);
        } else {
            document.getElementById('paymentMessage').innerHTML = 
                `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>${result.message || 'Upload failed'}</span>`;
        }
    };
    reader.readAsDataURL(file);
}

// ===== ADMIN LOGIN =====
async function adminLogin() {
    const user = document.getElementById('adminUser').value;
    const pass = document.getElementById('adminPass').value;
    
    if (!user || !pass) {
        showToast('Please enter username and password.', 'warning');
        return;
    }
    
    const result = await apiCall('admin_login', { username: user, password: pass });
    if (result.success) {
        adminLogged = true;
        document.getElementById('adminLoginOverlay').classList.remove('show');
        document.getElementById('adminPanel').style.display = 'block';
        document.querySelector('header').style.display = 'none';
        document.querySelector('.hero-section').style.display = 'none';
        document.querySelector('#productsSection').style.display = 'none';
        document.querySelector('#registerSection').style.display = 'none';
        document.querySelector('footer').style.display = 'none';
        loadAdminOrders('all');
        showToast('Welcome Admin!', 'success');
    } else {
        showToast('Invalid credentials. Please try again.', 'danger');
    }
}

function closeAdminLogin() {
    document.getElementById('adminLoginOverlay').classList.remove('show');
}

async function adminLogout() {
    await apiCall('admin_logout');
    adminLogged = false;
    document.getElementById('adminPanel').style.display = 'none';
    document.querySelector('header').style.display = 'block';
    document.querySelector('.hero-section').style.display = 'block';
    document.querySelector('#productsSection').style.display = 'block';
    document.querySelector('#registerSection').style.display = 'block';
    document.querySelector('footer').style.display = 'block';
    showToast('Logged out successfully.', 'info');
}

// ===== SCREENSHOT MODAL =====
function viewScreenshot(imageSrc) {
    let modal = document.getElementById('screenshotModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'screenshotModal';
        modal.className = 'screenshot-modal';
        modal.innerHTML = `
            <button class="modal-close" onclick="closeScreenshotModal()">✕</button>
            <img class="modal-content" id="modalImage" src="" alt="Screenshot">
        `;
        document.body.appendChild(modal);
        
        // Close on click outside
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeScreenshotModal();
        });
        
        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeScreenshotModal();
        });
    }
    
    document.getElementById('modalImage').src = imageSrc;
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeScreenshotModal() {
    const modal = document.getElementById('screenshotModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// ===== LOAD ADMIN ORDERS =====
async function loadAdminOrders(filter) {
    if (!adminLogged) return;
    
    try {
        const response = await fetch(`${window.location.href}?ajax=1&action=get_orders&filter=${filter}`);
        const orders = await response.json();
        const container = document.getElementById('adminOrderList');
        
        if (!container) return;
        
        if (orders.length === 0) {
            container.innerHTML = `
                <div class="col-12 text-center py-5">
                    <i class="bi bi-inbox fs-1 d-block mb-3" style="color: var(--text-muted);"></i>
                    <span class="text-muted">No orders in this category.</span>
                </div>
            `;
            return;
        }
        
        container.innerHTML = orders.map(o => `
            <div class="col-md-4">
                <div class="order-card">
                    <div class="order-status">
                        <span class="badge ${o.status === 'processed' ? 'badge-processed' : o.status === 'returned' ? 'badge-returned' : 'badge-pending'}">
                            ${o.status}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="fw-bold mb-1">${o.product_name}</h6>
                            <span class="gold-text fw-bold">${formatPrice(o.total)}</span>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small><i class="bi bi-person me-1"></i>${o.client_name}</small>
                        <small class="d-block"><i class="bi bi-envelope me-1"></i>${o.client_email}</small>
                        <small><i class="bi bi-cart me-1"></i>Qty: ${o.quantity}</small>
                        <small class="d-block"><i class="bi bi-clock me-1"></i>${new Date(o.created_at).toLocaleString()}</small>
                    </div>
                    
                    ${o.screenshot ? `
                        <div class="mt-2">
                            <p class="small text-muted mb-1"><i class="bi bi-image me-1"></i>Payment Screenshot:</p>
                            <img src="${o.screenshot}" class="screenshot-thumb" 
                                 onclick="viewScreenshot('${o.screenshot}')" 
                                 alt="Payment Screenshot" 
                                 title="Click to view full size">
                        </div>
                    ` : '<div class="mt-2 text-muted small"><i class="bi bi-image me-1"></i>No screenshot uploaded</div>'}
                    
                    <div class="mt-3 d-flex gap-2 flex-wrap">
                        ${o.status === 'pending' ? `
                            <button class="btn btn-sm btn-success" onclick="adminAction(${o.id}, 'verify')">
                                <i class="bi bi-check-circle me-1"></i>Verify
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="adminAction(${o.id}, 'return')">
                                <i class="bi bi-x-circle me-1"></i>Reject
                            </button>
                        ` : ''}
                        ${o.status === 'processed' ? `
                            <button class="btn btn-sm btn-outline-warning" onclick="adminAction(${o.id}, 'return')">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Return
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `).join('');
    } catch(e) {
        console.error('Error loading orders:', e);
    }
}

// ===== ADMIN ACTIONS =====
async function adminAction(orderId, action) {
    const result = await apiCall('admin_action', { action, order_id: orderId });
    if (result.success) {
        loadAdminOrders('all');
        showToast(`Order ${action === 'verify' ? 'verified' : 'updated'} successfully!`, 'success');
    } else {
        showToast('Action failed.', 'danger');
    }
}

// ===== UPDATE GREETING =====
function updateGreeting() {
    const el = document.getElementById('clientGreeting');
    if (el && currentClient) {
        el.innerHTML = `<span class="badge gold-bg text-dark px-3 py-2">
            <i class="bi bi-person me-1"></i>${currentClient.name}
        </span>`;
    }
}

// ===== RESTORE CLIENT SESSION =====
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

// ===== INIT =====
document.addEventListener('DOMContentLoaded', function() {
    loadTheme();
    loadProducts();
    restoreClient();
    
    // Check if admin is already logged in
    const adminPanel = document.getElementById('adminPanel');
    if (adminPanel && adminPanel.style.display === 'block') {
        adminLogged = true;
        loadAdminOrders('all');
    }
});

// ===== ADMIN PANEL ELEMENT =====
if (!document.getElementById('adminPanel')) {
    const panel = document.createElement('section');
    panel.id = 'adminPanel';
    panel.style.display = 'none';
    panel.className = 'py-0';
    panel.innerHTML = `
        <div class="container-fluid p-0">
            <div class="row g-0">
                <div class="col-md-3 col-lg-2 admin-sidebar p-3">
                    <div class="d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-flower1 gold-text fs-3"></i>
                        <span class="text-white fw-bold">LUXE<span class="gold-text">SCENT</span></span>
                    </div>
                    <h6 class="text-muted small text-uppercase mb-3">Management</h6>
                    <nav class="nav flex-column">
                        <a class="nav-link active" onclick="loadAdminOrders('all')">
                            <i class="bi bi-inbox me-2"></i>All Orders
                        </a>
                        <a class="nav-link" onclick="loadAdminOrders('pending')">
                            <i class="bi bi-clock-history me-2"></i>Pending
                        </a>
                        <a class="nav-link" onclick="loadAdminOrders('processed')">
                            <i class="bi bi-check2-circle me-2"></i>Processed
                        </a>
                        <a class="nav-link" onclick="loadAdminOrders('returned')">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>Returns
                        </a>
                        <hr class="border-secondary">
                        <a class="nav-link text-danger" onclick="adminLogout()">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
                    </nav>
                </div>
                <div class="col-md-9 col-lg-10 p-4" style="background: var(--bg-primary); min-height: 100vh;">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="gold-text"><i class="bi bi-clipboard-data me-2"></i>Order Management</h4>
                            <p class="text-muted small mb-0">Manage and verify customer orders</p>
                        </div>
                        <button class="theme-toggle" onclick="toggleTheme()">
                            <i class="bi bi-moon-fill"></i>
                            <span>Theme</span>
                        </button>
                    </div>
                    
                    <div class="row g-3 mb-4" id="statsContainer">
                        <!-- Stats loaded via JS -->
                    </div>
                    
                    <div id="adminOrderList" class="row g-3"></div>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(panel);
}

console.log('✨ LUXE SCENT · Premium Perfume Atelier');
console.log('📊 Admin Panel: /admin/login.php');
console.log('🔐 Credentials: admin / luxe123');
console.log('🎨 Theme: Toggle with the Theme button');