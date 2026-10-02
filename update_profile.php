<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

$client_id = $_SESSION['client_id'];
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$city = trim($_POST['city'] ?? '');
$address = trim($_POST['address'] ?? '');

if (empty($name) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Name and email are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

try {
    // Check if email is taken by another user
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? AND id != ?");
    $stmt->execute([$email, $client_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already in use by another account.']);
        exit;
    }
    
    // Check if phone column exists, build dynamic query
    $columns = $pdo->query("SHOW COLUMNS FROM clients")->fetchAll(PDO::FETCH_COLUMN);
    $hasPhone = in_array('phone', $columns);
    $hasCity = in_array('city', $columns);
    $hasAddress = in_array('address', $columns);
    
    $sql = "UPDATE clients SET name = ?, email = ?";
    $params = [$name, $email];
    
    if ($hasPhone) { $sql .= ", phone = ?"; $params[] = $phone; }
    if ($hasCity) { $sql .= ", city = ?"; $params[] = $city; }
    if ($hasAddress) { $sql .= ", address = ?"; $params[] = $address; }
    
    $sql .= " WHERE id = ?";
    $params[] = $client_id;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Update session name
    $_SESSION['client_name'] = $name;
    
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $e->getMessage()]);
}