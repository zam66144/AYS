// ===== ADMIN APPLICATION =====

// ===== THEME MANAGEMENT =====
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('admin_theme', newTheme);
    
    const icon = document.querySelector('.theme-toggle i');
    if (icon) {
        icon.className = newTheme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    }
}

function loadTheme() {
    const savedTheme = localStorage.getItem('admin_theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    const icon = document.querySelector('.theme-toggle i');
    if (icon) {
        icon.className = savedTheme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    }
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
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeScreenshotModal();
        });
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
    toast.className = `toast-notification toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.5s ease forwards';
        setTimeout(() => toast.remove(), 500);
    }, 4000);
}

// ===== LOAD ORDERS =====
async function loadOrders(filter = 'all') {
    try {
        const response = await fetch(`../index.php?ajax=1&action=get_orders&filter=${filter}`);
        const orders = await response.json();
        const container = document.getElementById('adminOrderList');
        
        if (orders.length === 0) {
            container.innerHTML = `
                <div class="col-12 text-center py-5">
                    <span style="color: var(--text-muted);">No orders in this category.</span>
                </div>
            `;
            return;
        }
        
        container.innerHTML = orders.map(o => `
            <div class="col-md-4">
                <div class="order-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="fw-bold mb-1">${o.product_name}</h6>
                            <span class="gold-text fw-bold">$${Number(o.total).toFixed(2)}</span>
                        </div>
                        <span class="badge ${o.status === 'processed' ? 'badge-processed' : o.status === 'returned' ? 'badge-returned' : o.status === 'cancelled' ? 'badge-cancelled' : 'badge-pending'}">
                            ${o.status}
                        </span>
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
                            <button class="btn btn-sm btn-danger" onclick="adminAction(${o.id}, 'cancel')">
                                <i class="bi bi-x-circle me-1"></i>Cancel
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
        showToast('Error loading orders.', 'danger');
    }
}

// ===== ADMIN ACTION =====
async function adminAction(orderId, action) {
    // Show loading state
    showToast('Processing...', 'info');
    
    const formData = new FormData();
    formData.append('action', 'admin_action');
    formData.append('order_id', orderId);
    formData.append('admin_action', action);
    
    try {
        const response = await fetch('../index.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            const msg = action === 'verify' ? '✅ Order verified successfully!' : 
                        action === 'cancel' ? '❌ Order cancelled!' : 
                        '🔄 Order updated!';
            showToast(msg, 'success');
            loadOrders('all');
        } else {
            showToast('❌ Action failed: ' + (result.message || 'Unknown error'), 'danger');
        }
    } catch(e) {
        console.error('Error:', e);
        showToast('❌ Error processing action.', 'danger');
    }
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', function() {
    loadTheme();
    loadOrders('all');
    
    // Add theme toggle to header if not exists
    const header = document.querySelector('.d-flex.justify-content-between.align-items-center.mb-4');
    if (header && !document.querySelector('.theme-toggle')) {
        const themeBtn = document.createElement('button');
        themeBtn.className = 'theme-toggle';
        themeBtn.innerHTML = `<i class="bi bi-moon-fill"></i> Theme`;
        themeBtn.onclick = toggleTheme;
        header.appendChild(themeBtn);
    }
});

console.log('🔐 LUXE SCENT · Admin Panel Loaded');