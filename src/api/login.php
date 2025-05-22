<?php
session_start();
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

if (isset($_GET['code'])) {
    // Exchange authorization code for access token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    // Store the token in the session
    $_SESSION['access_token'] = $token;
    
    // Redirect to the main page
    header('Location: /shokudouMenu2/public/index.php');
    exit();
} elseif (isset($_SESSION['access_token']) && $_SESSION['isLoggedIn']) {
    // User is already authenticated, redirect to the main page
    header('Location: /shokudouMenu2/public/index.php');
    exit();
} else {
    // Generate the authentication URL
    $authUrl = $client->createAuthUrl();
    
    // Redirect to Google's OAuth 2.0 server
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
}