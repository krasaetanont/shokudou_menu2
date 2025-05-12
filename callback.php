<?php
require_once 'vendor/autoload.php';

$client = new Google_Client();
$client->setClientId('1072704995156-73kgv35ivvfna54n6021gsfva32162cd.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/shokudouMenu2/callback.php');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token['access_token']);

    // Get profile info
    $oauth = new Google_Service_Oauth2($client);
    $userinfo = $oauth->userinfo->get();

    echo "Welcome, " . $userinfo->name . "<br>";
    echo "Email: " . $userinfo->email . "<br>";
    echo "<img src='" . $userinfo->picture . "'>";
} else {
    echo "No code found.";
}
