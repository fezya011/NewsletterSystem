<?php

// security headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// security settings
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');
error_reporting(E_ALL);

// force HTTPS in production
//if ($_SERVER['HTTP_HOST'] !== 'localhost' && empty($_SERVER['HTTPS'])) {
//    http_response_code(403);
//    echo json_encode(['success' => false, 'message' => 'HTTPS required']);
//    exit();
//}

// rate limiting
session_start();
$rate_limit_key = 'nl_rate_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!isset($_SESSION[$rate_limit_key . '_time'])) {
    $_SESSION[$rate_limit_key . '_time'] = time();
    $_SESSION[$rate_limit_key] = 0;
}

if (time() - $_SESSION[$rate_limit_key . '_time'] > 60) {
    $_SESSION[$rate_limit_key] = 0;
    $_SESSION[$rate_limit_key . '_time'] = time();
}
if ($_SESSION[$rate_limit_key] > 50) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
    exit();
}
$_SESSION[$rate_limit_key]++;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/../bootstrap.php';

function generate_token() {
    return bin2hex(random_bytes(32));
}

// function for merge categories when you add a push/email
function merge_categories($existing, $new) {
    $existingCats = [];
    if (!empty($existing)) {
        if (is_string($existing)) {
            $existingCats = json_decode($existing, true) ?: [];
        } else {
            $existingCats = $existing ?: [];
        }
    }

    $newCats = [];
    if (!empty($new)) {
        if (is_string($new)) {
            $newCats = json_decode($new, true) ?: [];
        } else {
            $newCats = $new ?: [];
        }
    }

    $merged = array_unique(array_merge($existingCats, $newCats));
    return json_encode($merged);
}


$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

$type = $input['type'] ?? null;
$website = $input['website'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
$language = $input['language'] ?? 'en';
$context = $input['context'] ?? [];
$categories = $context['categories'] ?? [];
$keywords = $context['keywords'] ?? [];

try {
    $db = nl_db();

    // email subscription
    if ($type === 'email') {
        $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $pushEndpoint = $input['push_endpoint'] ?? null;

        if (!$email) {
            echo json_encode(['success' => false, 'message' => 'Invalid email']);
            exit();
        }

        // search by email
        $stmt = $db->prepare("SELECT id, email, push_endpoint, subscription_type, categories FROM nl_subscribers WHERE email = ?");
        $stmt->execute([$email]);
        $existingByEmail = $stmt->fetch();

        if ($existingByEmail) {
            if ($pushEndpoint && empty($existingByEmail['push_endpoint'])) {
                $mergedCats = merge_categories($existingByEmail['categories'], $categories);
                $stmt = $db->prepare("UPDATE nl_subscribers SET push_endpoint = ?, subscription_type = 'both', status = 'active', categories = ? WHERE id = ?");
                $stmt->execute([$pushEndpoint, $mergedCats, $existingByEmail['id']]);
                echo json_encode(['success' => true, 'message' => 'Push added to email subscription', 'subscription_type' => 'both']);
            } else {
                echo json_encode(['success' => true, 'message' => 'Email already subscribed', 'subscription_type' => $existingByEmail['subscription_type'] ?? 'email']);
            }
            exit();
        }

        // search by push_endpoint
        if ($pushEndpoint) {
            $stmt = $db->prepare("SELECT id, email, push_endpoint, categories FROM nl_subscribers WHERE push_endpoint = ?");
            $stmt->execute([$pushEndpoint]);
            $existingByPush = $stmt->fetch();

            if ($existingByPush) {
                $mergedCats = merge_categories($existingByPush['categories'], $categories);
                $stmt = $db->prepare("UPDATE nl_subscribers SET email = ?, subscription_type = 'both', categories = ? WHERE id = ?");
                $stmt->execute([$email, $mergedCats, $existingByPush['id']]);
                echo json_encode(['success' => true, 'message' => 'Email added to push subscription', 'subscription_type' => 'both']);
                exit();
            }
        }

        // new entry
        $token = generate_token();
        $stmt = $db->prepare("INSERT INTO nl_subscribers (email, subscription_type, status, website, language, categories, unsubscribe_token, subscribed_at) VALUES (?, 'email', 'active', ?, ?, ?, ?, NOW())");
        $stmt->execute([$email, $website, $language, json_encode($categories), $token]);
        echo json_encode(['success' => true, 'message' => 'Email subscription successful', 'subscription_type' => 'email']);
    }

    // push subscription
    elseif ($type === 'push') {
        $subscription = $input['subscription'] ?? null;

        if (!$subscription || !isset($subscription['endpoint'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid push subscription']);
            exit();
        }

        $endpoint = $subscription['endpoint'];
        $auth = $subscription['keys']['auth'] ?? '';
        $p256dh = $subscription['keys']['p256dh'] ?? '';
        $email = $input['email'] ?? null;

        // search by push_endpoint
        $stmt = $db->prepare("SELECT id, email, subscription_type, categories FROM nl_subscribers WHERE push_endpoint = ?");
        $stmt->execute([$endpoint]);
        $existingByPush = $stmt->fetch();

        if ($existingByPush) {
            if ($email && empty($existingByPush['email'])) {
                $mergedCats = merge_categories($existingByPush['categories'], $categories);
                $stmt = $db->prepare("UPDATE nl_subscribers SET email = ?, subscription_type = 'both', categories = ? WHERE id = ?");
                $stmt->execute([$email, $mergedCats, $existingByPush['id']]);
                echo json_encode(['success' => true, 'message' => 'Email linked to push', 'subscription_type' => 'both']);
            } else {
                echo json_encode(['success' => true, 'message' => 'Push already active', 'subscription_type' => $existingByPush['subscription_type']]);
            }
            exit();
        }

        // search by email
        if ($email) {
            $stmt = $db->prepare("SELECT id, email, push_endpoint, subscription_type, categories FROM nl_subscribers WHERE email = ?");
            $stmt->execute([$email]);
            $existingByEmail = $stmt->fetch();

            if ($existingByEmail) {
                $mergedCats = merge_categories($existingByEmail['categories'], $categories);
                $stmt = $db->prepare("UPDATE nl_subscribers SET push_endpoint = ?, push_auth = ?, push_p256dh = ?, subscription_type = 'both', categories = ? WHERE id = ?");
                $stmt->execute([$endpoint, $auth, $p256dh, $mergedCats, $existingByEmail['id']]);
                echo json_encode(['success' => true, 'message' => 'Push added to email', 'subscription_type' => 'both']);
                exit();
            }
        }

        // new entry
        $token = generate_token();
        $stmt = $db->prepare("INSERT INTO nl_subscribers (push_endpoint, push_auth, push_p256dh, subscription_type, status, website, language, categories, unsubscribe_token, subscribed_at, confirmed_at) VALUES (?, ?, ?, 'push', 'active', ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$endpoint, $auth, $p256dh, $website, $language, json_encode($categories), $token]);
        echo json_encode(['success' => true, 'message' => 'Push notifications enabled', 'subscription_type' => 'push']);
    }

    else {
        echo json_encode(['success' => false, 'message' => 'Invalid subscription type']);
    }

} catch (PDOException $e) {
    error_log("Newsletter DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("Newsletter Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal error']);
}