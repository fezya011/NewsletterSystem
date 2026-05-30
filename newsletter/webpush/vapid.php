<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

echo "Generating VAPID Keys\n\n";

try {
    // method 1: via the library
    $keys = VAPID::createVapidKeys();

    $publicKey = $keys['publicKey'];
    $privateKey = $keys['privateKey'];

    echo "Keys generated via WebPush library\n\n";
    echo "Public Key: {$publicKey}\n";
    echo "Private Key: {$privateKey}\n\n";

} catch (Exception $e) {
    echo "Library method failed: " . $e->getMessage() . "\n";
    echo "Trying manual generation...\n\n";

    // method 2: manual generation via OpenSSL
    $privateKeyFile = sys_get_temp_dir() . '/private_ec_key.pem';
    $publicKeyFile = sys_get_temp_dir() . '/public_ec_key.pem';

    // Generate an EC key on curve prime256v1
    $cmd = 'openssl ecparam -name prime256v1 -genkey -noout -out ' . $privateKeyFile;
    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0) {
        die("OpenSSL not available. Please install OpenSSL first.\n");
    }

    // extract the public key
    $cmd = 'openssl ec -in ' . $privateKeyFile . ' -pubout -out ' . $publicKeyFile;
    exec($cmd);

    $privatePem = file_get_contents($privateKeyFile);
    $publicPem = file_get_contents($publicKeyFile);

    // convert to VAPID format (Base64 URL-safe)
    function pemToBase64Url($pem) {
        $der = base64_decode(preg_replace('/-----(BEGIN|END) (PUBLIC|PRIVATE) KEY-----/', '', $pem));
        return rtrim(strtr(base64_encode($der), '+/', '-_'), '=');
    }

    $publicKey = pemToBase64Url($publicPem);
    $privateKey = pemToBase64Url($privatePem);

    // cleanup
    @unlink($privateKeyFile);
    @unlink($publicKeyFile);

    echo "Keys generated via OpenSSL\n\n";
    echo "Public Key: {$publicKey}\n";
    echo "Private Key: {$privateKey}\n\n";
}

$configFile = __DIR__ . '/../config.php';

if (file_exists($configFile)) {
    $content = file_get_contents($configFile);

    // update or add keys
    if (strpos($content, "'webpush'") !== false) {
        $content = preg_replace("/'vapid_public_key'\s*=>\s*'[^']*'/", "'vapid_public_key' => '{$publicKey}'", $content);
        $content = preg_replace("/'vapid_private_key'\s*=>\s*'[^']*'/", "'vapid_private_key' => '{$privateKey}'", $content);
    } else {
        // add a section
        $webpushSection = "\n    // WebPush VAPID\n    'webpush' => [\n";
        $webpushSection .= "        'vapid_public_key' => '{$publicKey}',\n";
        $webpushSection .= "        'vapid_private_key' => '{$privateKey}',\n";
        $webpushSection .= "        'vapid_subject' => 'mailto:newsletter@localhost',\n";
        $webpushSection .= "    ],\n";

        // insert before the last bracket
        $content = str_replace('];', $webpushSection . '];', $content);
    }

    if (file_put_contents($configFile, $content)) {
        echo "Keys saved to config.php\n";
    }
}

// save to file for verification
file_put_contents(__DIR__ . '/vapid_keys_valid.json', json_encode([
    'publicKey' => $publicKey,
    'privateKey' => $privateKey,
    'generated_at' => date('Y-m-d H:i:s'),
    'valid_for' => 'WebPush'
], JSON_PRETTY_PRINT));

echo "\nKeys also saved to vapid_keys_valid.json\n";
echo "\nRESTART YOUR SERVER\n";