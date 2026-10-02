<?php
function handleRegistration($pdo) {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($name) || empty($email) || strlen($password) < 4) {
        return ['success' => false, 'message' => 'Please fill all fields correctly.'];
    }
    
    try {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO clients (name, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $hashed]);
        $_SESSION['client_id'] = $pdo->lastInsertId();
        $_SESSION['client_name'] = $name;
        return ['success' => true, 'message' => 'Registration successful', 'name' => $name];
    } catch(PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            return handleClientLogin($pdo, $email, $password);
        }
        return ['success' => false, 'message' => 'Registration failed'];
    }
}

function handleClientLogin($pdo, $email, $password) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE email = ?");
    $stmt->execute([$email]);
    $client = $stmt->fetch();
    
    if ($client && password_verify($password, $client['password'])) {
        $_SESSION['client_id'] = $client['id'];
        $_SESSION['client_name'] = $client['name'];
        return ['success' => true, 'name' => $client['name']];
    }
    return ['success' => false, 'message' => 'Invalid credentials'];
}

function handleLogin($pdo) {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    return handleClientLogin($pdo, $email, $password);
}

function handlePlaceOrder($pdo) {
    $client_id = $_SESSION['client_id'] ?? 0;
    if (!$client_id) {
        return ['success' => false, 'message' => 'Please login first'];
    }
    
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $product_name = sanitize($_POST['product_name']);
    $total = (float)$_POST['total'];
    
    $stmt = $pdo->prepare("INSERT INTO orders (client_id, product_id, product_name, quantity, total, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$client_id, $product_id, $product_name, $quantity, $total]);
    
    return [
        'success' => true, 
        'order_id' => $pdo->lastInsertId(),
        'product' => $product_name,
        'quantity' => $quantity,
        'total' => $total
    ];
}

function handleScreenshotUpload($pdo) {
    $order_id = (int)$_POST['order_id'];
    $screenshot = $_POST['screenshot'] ?? '';
    
    if (empty($screenshot)) {
        return ['success' => false, 'message' => 'No screenshot provided'];
    }
    
    $stmt = $pdo->prepare("UPDATE orders SET screenshot = ?, status = 'pending' WHERE id = ?");
    $stmt->execute([$screenshot, $order_id]);
    
    return ['success' => true];
}

function handleAdminLogin() {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === 'admin' && $password === 'luxe123') {
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_login_time'] = time();
        return ['success' => true];
    }
    return ['success' => false, 'message' => 'Invalid credentials'];
}

function handleAdminAction($pdo) {
    // Check if admin is logged in
    if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
        return ['success' => false, 'message' => 'Unauthorized access'];
    }
    
    // Get action and order_id from POST
    $action = $_POST['admin_action'] ?? $_POST['action'] ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);
    
    if (!$order_id) {
        return ['success' => false, 'message' => 'Invalid order ID'];
    }
    
    try {
        if ($action === 'verify') {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'processed', verified = TRUE WHERE id = ?");
            $stmt->execute([$order_id]);
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Order verified successfully'];
            }
            return ['success' => false, 'message' => 'Order not found or already processed'];
        } 
        elseif ($action === 'cancel') {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$order_id]);
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Order cancelled successfully'];
            }
            return ['success' => false, 'message' => 'Order not found'];
        } 
        elseif ($action === 'return') {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'returned' WHERE id = ? AND status = 'processed'");
            $stmt->execute([$order_id]);
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Order returned successfully'];
            }
            return ['success' => false, 'message' => 'Order not found or not processed'];
        }
        return ['success' => false, 'message' => 'Invalid action'];
    } catch(PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true;
}
?>