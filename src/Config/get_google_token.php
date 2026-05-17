<?php

require_once "../vendor/autoload.php";

function writeConfFile(array $data, string $path): bool
{
    $export  = var_export($data, true);
    $content = "<?php\n\nreturn " . $export . ";\n";

    return file_put_contents($path, $content) !== false;
}

// Créer des credentials OAuth2 (pas Service Account) dans Google Cloud Console
// Type : "Application de bureau"
$client = new Google\Client();
$client->setAuthConfig(require(__DIR__ . '/credentials/oauth-client.php'));
$client->addScope(Google\Service\Webmasters::WEBMASTERS_READONLY);
$client->setAccessType('offline');
$client->setPrompt('consent'); // Force refresh_token

// Première exécution → génère l'URL d'autorisation
$tokenFile = __DIR__ . '/credentials/token.php';

if (!file_exists($tokenFile)) {
    $authUrl = $client->createAuthUrl();
    echo "Ouvre cette URL dans ton navigateur :\n{$authUrl}\n\n";
    echo "Colle le code ici : ";
    $code = trim(fgets(STDIN));
    $token = $client->fetchAccessTokenWithAuthCode($code);

	writeConfFile($token, $tokenFile);
}

// Exécutions suivantes → utilise le token sauvegardé
$client->setAccessToken(require($tokenFile));

// Rafraîchit automatiquement si expiré
if ($client->isAccessTokenExpired()) {
    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());

	writeConfFile($client->getAccessToken(), $tokenFile);
}

