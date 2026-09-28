<?php
/**
 * profile.php
 * Handles user profile management.
 * Rules strictly followed:
 * - Session validated exclusively from Redis via token (No PHP session).
 * - Profile details (age, dob, contact, address, etc.) fetched and stored in MongoDB.
 * - Logout removes the session token from Redis.
 */

require_once __DIR__ . '/db.php';

// Extract token from GET, POST, or Authorization header
$token = '';
if (isset($_REQUEST['token'])) {
    $token = trim($_REQUEST['token']);
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $token = $matches[1];
    }
}

if (empty($token)) {
    sendJsonResponse('unauthorized', 'Session token is required. Please log in.', null, 401);
}

// 1. Verify session in Redis
$sessionManager = new RedisSessionManager();
$session = $sessionManager->get($token);

if (!$session || !isset($session['email'])) {
    sendJsonResponse('unauthorized', 'Your session has expired or is invalid. Please sign in again.', null, 401);
}

$userEmail = $session['email'];
$mongoManager = new MongoProfileManager();

// Determine Action
$action = isset($_REQUEST['action']) ? strtolower(trim($_REQUEST['action'])) : 'get';

// ---------------------------------------------------------
// Action: LOGOUT (Deletes session from Redis)
// ---------------------------------------------------------
if ($action === 'logout') {
    $sessionManager->delete($token);
    sendJsonResponse('success', 'Logged out successfully from Redis session.');
}

// ---------------------------------------------------------
// Action: UPDATE PROFILE (Saves data to MongoDB)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $age = isset($_POST['age']) ? trim($_POST['age']) : '';
    $dob = isset($_POST['dob']) ? trim($_POST['dob']) : '';
    $contact = isset($_POST['contact']) ? trim($_POST['contact']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $bio = isset($_POST['bio']) ? trim($_POST['bio']) : '';

    $profileUpdate = [
        'user_id' => $session['user_id'] ?? null,
        'email' => $userEmail,
        'name' => $session['name'] ?? '',
        'age' => $age,
        'dob' => $dob,
        'contact' => $contact,
        'city' => $city,
        'address' => $address,
        'bio' => $bio,
        'updated_at' => date('c')
    ];

    $saved = $mongoManager->updateProfile($userEmail, $profileUpdate);

    if ($saved) {
        sendJsonResponse('success', 'Profile details saved successfully in MongoDB!', [
            'profile' => $profileUpdate
        ]);
    } else {
        sendJsonResponse('error', 'Failed to save profile in MongoDB.', null, 500);
    }
}

// ---------------------------------------------------------
// Action: GET PROFILE (Fetches details from MongoDB)
// ---------------------------------------------------------
$profileData = $mongoManager->getProfile($userEmail);

sendJsonResponse('success', 'Profile loaded successfully.', [
    'user' => [
        'id' => $session['user_id'] ?? null,
        'name' => $session['name'] ?? 'User',
        'email' => $userEmail,
        'login_at' => $session['login_at'] ?? null
    ],
    'profile' => $profileData
]);
