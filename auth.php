<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'login') {
        login();
    } elseif ($action === 'register') {
        register();
    } else {
        sendError('Invalid action');
    }
} elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
} elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check') {
    checkSession();
} else {
    sendError('Method not allowed', 405);
}

function login() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['username']) || !isset($data['password'])) {
        sendError('Username and password are required');
    }
    
    $username = sanitizeInput($data['username']);
    $password = $data['password'];
    
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT id, username, password, full_name, email, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendError('Invalid username or password');
    }
    
    $user = $result->fetch_assoc();
    
    if (!password_verify($password, $user['password'])) {
        sendError('Invalid username or password');
    }
    
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    
    unset($user['password']);
    
    sendSuccess('Login successful', $user);
}

function register() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['username', 'password', 'full_name', 'email'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendError("$field is required");
        }
    }
    
    $username = sanitizeInput($data['username']);
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $full_name = sanitizeInput($data['full_name']);
    $email = sanitizeInput($data['email']);
    $role = isset($data['role']) ? sanitizeInput($data['role']) : 'staff';
    
    $conn = getDBConnection();
    
    // Check if username or email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendError('Username or email already exists');
    }
    
    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $password, $full_name, $email, $role);
    
    if ($stmt->execute()) {
        sendSuccess('Registration successful');
    } else {
        sendError('Registration failed');
    }
}

function logout() {
    session_start();
    session_destroy();
    sendSuccess('Logout successful');
}

function checkSession() {
    session_start();
    if (isset($_SESSION['user_id'])) {
        sendSuccess('Session valid', [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['role']
        ]);
    } else {
        sendError('No active session', 401);
    }
}
?>