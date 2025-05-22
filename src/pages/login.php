<?php
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

$url = $client->createAuthUrl();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Shokudou</title>
    <link rel="stylesheet" href="/shokudouMenu2/public/assets/style.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .login-container h1 {
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .error {
            color: var(--unavailable-color);
            margin-bottom: 20px;
            padding: 10px;
            background-color: rgba(231, 111, 81, 0.1);
            border-radius: 4px;
        }
        
        .login-button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4285F4;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .login-button:hover {
            background-color: #357ae8;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <h1>Login to Shokudou</h1>
            <a href="<?= $url ?>" class="login-button">
                Login with Google
            </a>
        </div>
    </div>
</body>
</html>