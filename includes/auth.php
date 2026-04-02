<?php
// ==============================================
// auth.php — Simple authentication helpers
// ==============================================
// Basic register/login/logout using database + sessions

require_once __DIR__ . '/db.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect to login if not authenticated
 */
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

/**
 * Get current logged-in user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    $db = getDB();
    $stmt = $db->prepare('SELECT id, username, email, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Register a new user
 * Returns: ['success' => true] or ['error' => 'message']
 */
function registerUser($username, $email, $password, $confirmPassword) {
    // Validate inputs
    $username = trim($username);
    $email = trim($email);
    
    if (empty($username) || empty($email) || empty($password)) {
        return ['error' => 'All fields are required.'];
    }
    if (strlen($username) < 3 || strlen($username) > 50) {
        return ['error' => 'Username must be 3-50 characters.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'Invalid email address.'];
    }
    if (strlen($password) < 6) {
        return ['error' => 'Password must be at least 6 characters.'];
    }
    if ($password !== $confirmPassword) {
        return ['error' => 'Passwords do not match.'];
    }
    
    $db = getDB();
    
    // Check if email or username already exists
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        return ['error' => 'Email or username already taken.'];
    }
    
    // Create user
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$username, $email, $hash]);
    
    // Auto-login
    $_SESSION['user_id'] = $db->lastInsertId();
    
    return ['success' => true];
}

/**
 * Login a user
 * Returns: ['success' => true] or ['error' => 'message']
 */
function loginUser($email, $password) {
    $email = trim($email);
    
    if (empty($email) || empty($password)) {
        return ['error' => 'Email and password are required.'];
    }
    
    $db = getDB();
    $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['error' => 'Invalid email or password.'];
    }
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    
    return ['success' => true];
}

/**
 * Logout the current user
 */
function logoutUser() {
    $_SESSION = [];
    session_destroy();
}
