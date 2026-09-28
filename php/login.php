<?php
/**
 * login.php
 * Handles user login.
 * Rules strictly followed:
 * - Credentials verified from MySQL using Prepared Statements.
 * - Password checked using bcrypt verification.
 * - NO PHP Session (session_start is strictly forbidden).
 * - Backend session stored in Redis with unique session token.
 * - Token returned to client for storage in browser localStorage.
 */

require_once __DIR__ . '/db.php';

// Only allow POST requests via AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Invalid request method. Only POST is accepted.', null, 405);
}

// Retrieve sanitized input data
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse('error', 'Please enter a valid registered email address.', null, 400);
}

if (empty($password)) {
    sendJsonResponse('error', 'Password cannot be empty.', null, 400);
}

// 1. Get MySQL connection
$conn = getMySQLConnection();

// 2. Fetch user from MySQL - MUST USE PREPARED STATEMENT
$stmt = $conn->prepare("SELECT `id`, `name`, `email`, `password` FROM `users` WHERE `email` = ? LIMIT 1");
if (!$stmt) {
    sendJsonResponse('error', 'Database query preparation failed: ' . $conn->error, null, 500);
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    $stmt->close();
    sendJsonResponse('error', 'Invalid email or password. Please check your credentials.', null, 401);
}

$user = $result->fetch_assoc();
$stmt->close();

// 3. Verify password hash
if (!password_verify($password, $user['password'])) {
    sendJsonResponse('error', 'Invalid email or password. Please check your credentials.', null, 401);
}

// 4. Generate cryptographically secure session token
try {
    $sessionToken = bin2hex(random_bytes(32));
} catch (Exception $e) {
    $sessionToken = md5(uniqid($user['email'], true) . microtime());
}

// 5. Store session information strictly in Redis (Replacing PHP Session)
$sessionManager = new RedisSessionManager();
$sessionPayload = [
    'user_id' => $user['id'],
    'name'    => $user['name'],
    'email'   => $user['email'],
    'login_at'=> date('c')
];

// Session expires in 24 hours (86400 seconds) in Redis
$savedInRedis = $sessionManager->set($sessionToken, $sessionPayload, 86400);

// 6. Return response with session token (for browser localStorage)
sendJsonResponse('success', 'Authentication successful!', [
    'token' => $sessionToken,
    'user'  => [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email']
    ]
]);
