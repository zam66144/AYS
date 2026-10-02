<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

$client_id = $_SESSION['client_id'];
$current = $_POST['current_password'] ?? '';
$new = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if (empty($current) || empty($new) || empty($confirm)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if ($new !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
    exit;
}

if (strlen($new) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    exit;
}

try {
    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $stored = $stmt->fetchColumn();
    
    if (!$stored || !password_verify($current, $stored)) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }
    
    // Update password
    $hashed = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE clients SET password = ? WHERE id = ?");
    $stmt->execute([$hashed, $client_id]);
    
    echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to change password: ' . $e->getMessage()]);
}