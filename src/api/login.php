<?php
// src/api/login.php
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Google\Client as Google_Client;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Create a new Google client
$client = new Google_Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);
$client->addScope("email");
$client->addScope("profile");

// Check if we have a code from Google
if (isset($_GET['code'])) {
    // Exchange the code for an access token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token);
    
    // Get the user's profile info
    $google_oauth = new Google\Service\Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
    
    // Start session and store user info
    session_start();
    $_SESSION['user_id'] = $google_account_info->id;
    $_SESSION['user_email'] = $google_account_info->email;
    $_SESSION['user_name'] = $google_account_info->name;
    
    // Set a cookie for easy front-end checking - use httponly false to allow JS to read it
    // Fix: Make sure the cookie path is consistent (use '/' for root path)
    setcookie('user_logged_in', 'true', [
        'expires' => time() + 86400 * 30, // 30 days
        'path' => '/', // This is the key fix - use root path
        'httponly' => false, // Allow JavaScript to read this cookie
        'samesite' => 'Strict'
    ]);
    
    // Redirect to homepage
    header('Location: /shokudouMenu2/public/index.php');
    exit;
} else {
    // If we don't have a code, get authentication URL
    $auth_url = $client->createAuthUrl();
    header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
    exit;
}