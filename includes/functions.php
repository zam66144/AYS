<?php
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function formatPrice($price) {
    return '$' . number_format($price, 2);
}

function getOrderStatusBadge($status) {
    $badges = [
        'pending' => 'bg-warning text-dark',
        'processed' => 'bg-success',
        'returned' => 'bg-danger'
    ];
    return $badges[$status] ?? 'bg-secondary';
}

function generateOrderMessage($product, $quantity, $total) {
    return "Hello LUXE SCENT, I just ordered {$product} (x{$quantity}) - Total: $${total}. Thank you!";
}

function getStatusLabel($status) {
    $labels = [
        'pending' => '⏳ Pending',
        'processed' => '✅ Processed',
        'returned' => '↩️ Returned'
    ];
    return $labels[$status] ?? $status;
}
?>