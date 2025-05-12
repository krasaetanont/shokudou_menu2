<?php
require_once 'vendor/autoload.php';

$client = new Google_Client();
$client->setClientId('1072704995156-73kgv35ivvfna54n6021gsfva32162cd.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/shokudouMenu2/callback.php');
$client->addScope("email");
$client->addScope("profile");

$auth_url = $client->createAuthUrl();
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
