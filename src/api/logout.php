<?php
// src/api/logout.php
session_start();

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear the login cookie - use the same path as when it was set
// Fix: Make sure the cookie path matches the path used when setting it
setcookie('user_logged_in', '', [
    'expires' => time() - 3600,
    'path' => '/', // This is the key fix - use root path to match login.php
    'httponly' => false
]);

// Redirect back to the main page
header('Location: /shokudouMenu2/public/index.php');
exit;