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

// Clear the login cookie
setcookie('user_logged_in', '', time() - 3600, '/');

// Redirect back to the main page
header('Location: /shokudouMenu2/public/index.php');
exit;