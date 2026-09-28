<?php
/**
 * register.php
 * Handles user registration.
 * Rules strictly followed:
 * - MySQL used for storing registered credentials.
 * - Always uses Prepared Statements (NO simple SQL concatenation).
 * - MongoDB initializes the user profile document.
 * - Pure backend endpoint, returns JSON for jQuery AJAX.
 */

require_once __DIR__ . '/db.php';

// Only allow POST requests via AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Invalid request method. Only POST is accepted.', null, 405);
}

// Retrieve sanitized input data
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validation
if (empty($name)) {
    sendJsonResponse('error', 'Full Name is required.', null, 400);
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse('error', 'A valid email address is required.', null, 400);
}

if (strlen($password) < 6) {
    sendJsonResponse('error', 'Password must be at least 6 characters long.', null, 400);
}

// 1. Get MySQL connection
$conn = getMySQLConnection();

// 2. Check if email already registered - MUST USE PREPARED STATEMENT
$checkStmt = $conn->prepare("SELECT `id` FROM `users` WHERE `email` = ? LIMIT 1");
if (!$checkStmt) {
    sendJsonResponse('error', 'Database query preparation failed: ' . $conn->error, null, 500);
}

$checkStmt->bind_param("s", $email);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult && $checkResult->num_rows > 0) {
    $checkStmt->close();
    sendJsonResponse('error', 'This email is already registered. Please sign in or use another email.', null, 409);
}
$checkStmt->close();

// 3. Hash password securely
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// 4. Insert registered user into MySQL - MUST USE PREPARED STATEMENT
$insertStmt = $conn->prepare("INSERT INTO `users` (`name`, `email`, `password`) VALUES (?, ?, ?)");
if (!$insertStmt) {
    sendJsonResponse('error', 'Database insertion preparation failed: ' . $conn->error, null, 500);
}

$insertStmt->bind_param("sss", $name, $email, $hashedPassword);

if (!$insertStmt->execute()) {
    $err = $insertStmt->error;
    $insertStmt->close();
    sendJsonResponse('error', 'Registration failed to save in database: ' . $err, null, 500);
}

$newUserId = $insertStmt->insert_id;
$insertStmt->close();

// 5. Initialize user profile document in MongoDB
try {
    $mongoManager = new MongoProfileManager();
    $mongoManager->updateProfile($email, [
        'user_id' => $newUserId,
        'email' => $email,
        'name' => $name,
        'age' => '',
        'dob' => '',
        'contact' => '',
        'city' => '',
        'address' => '',
        'bio' => '',
        'created_at' => date('c'),
        'updated_at' => date('c')
    ]);
} catch (Exception $e) {
    // Non-fatal if initial profile doc fails, user is already registered in MySQL
}

// Return success response to jQuery AJAX
sendJsonResponse('success', 'Account registered successfully! You can now log in.', [
    'user_id' => $newUserId,
    'email' => $email,
    'name' => $name
], 201);
