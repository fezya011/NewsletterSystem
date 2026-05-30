<?php

// security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// security settings
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../bootstrap.php';

// token validation function
function validate_token($token) {
    return preg_match('/^[a-f0-9]{64}$/', $token);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['token'] ?? null;

    if (!$token || !validate_token($token)) {
        show_error_page('Invalid or missing token');
        exit();
    }

    show_unsubscribe_page($token);
    exit();
}

// POST request - process unsubscribe
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? null;

    if (!$token || !validate_token($token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit();
    }

    $result = process_unsubscribe($token);
    echo json_encode($result);
    exit();
}

// not allowed methods
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
exit();

// process unsubscribe from email
function process_unsubscribe($token) {
    try {
        $db = nl_db();

        $stmt = $db->prepare("
            SELECT id, email, push_endpoint, subscription_type, status
            FROM nl_subscribers 
            WHERE unsubscribe_token = ? AND status != 'unsubscribed' AND email IS NOT NULL
        ");
        $stmt->execute([$token]);
        $subscriber = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$subscriber) {
            return ['success' => false, 'message' => 'Invalid or expired link'];
        }

        // only email subscription
        if ($subscriber['subscription_type'] === 'email') {
            $stmt = $db->prepare("
                UPDATE nl_subscribers 
                SET status = 'unsubscribed', unsubscribed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$subscriber['id']]);
            return [
                'success' => true,
                'message' => 'You have been unsubscribed from the email newsletter.'
            ];
        }

        // both email and push - remove only email
        if ($subscriber['subscription_type'] === 'both') {
            $stmt = $db->prepare("
                UPDATE nl_subscribers 
                SET subscription_type = 'push', email = NULL
                WHERE id = ?
            ");
            $stmt->execute([$subscriber['id']]);
            return [
                'success' => true,
                'message' => 'Email unsubscribed. Push notifications remain active.'
            ];
        }

        return ['success' => false, 'message' => 'No active email subscription found'];

    } catch (PDOException $e) {
        error_log("Unsubscribe DB Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    } catch (Exception $e) {
        error_log("Unsubscribe Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Internal error'];
    }
}

// get subscriber language by token
function get_subscriber_language($token) {
    try {
        $db = nl_db();
        $stmt = $db->prepare("SELECT language FROM nl_subscribers WHERE unsubscribe_token = ?");
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if ($row && in_array($row['language'], ['de', 'en', 'fr', 'it', 'ru'])) {
            return $row['language'];
        }
    } catch (Exception $e) {
        error_log("Language detection error: " . $e->getMessage());
    }
    return 'en';
}

// show unsubscribe confirmation page
function show_unsubscribe_page($token) {
    $language = get_subscriber_language($token);

    $texts = [
        'de' => [
            'title' => 'Newsletter abbestellen',
            'header' => 'E-Mail Newsletter abbestellen',
            'confirm_text' => 'Möchten Sie sich von unserem E-Mail-Newsletter abmelden?',
            'warning' => 'Sie erhalten dann keine E-Mails mehr. Push-Benachrichtigungen bleiben aktiv.',
            'confirm_btn' => 'Ja, abbestellen',
            'cancel_btn' => 'Abbrechen',
            'processing' => 'Wird bearbeitet...',
            'success_title' => 'Abgemeldet',
            'success_msg' => 'Sie wurden erfolgreich abgemeldet.',
            'success_note' => 'Sie erhalten keine weiteren E-Mails.',
            'home_btn' => 'Zur Startseite',
            'error_title' => 'Fehler',
            'error_msg' => 'Ungültiger oder abgelaufener Link.',
            'error_btn' => 'Zur Startseite'
        ],
        'en' => [
            'title' => 'Unsubscribe',
            'header' => 'Unsubscribe from Email Newsletter',
            'confirm_text' => 'Are you sure you want to unsubscribe from our email newsletter?',
            'warning' => 'You will no longer receive emails. Push notifications remain active.',
            'confirm_btn' => 'Yes, unsubscribe',
            'cancel_btn' => 'Cancel',
            'processing' => 'Processing...',
            'success_title' => 'Unsubscribed',
            'success_msg' => 'You have been successfully unsubscribed.',
            'success_note' => 'You will no longer receive emails from us.',
            'home_btn' => 'Homepage',
            'error_title' => 'Error',
            'error_msg' => 'Invalid or expired link.',
            'error_btn' => 'Homepage'
        ],
        'fr' => [
            'title' => 'Désabonnement',
            'header' => 'Se désabonner de la newsletter',
            'confirm_text' => 'Voulez-vous vous désabonner de notre newsletter par email?',
            'warning' => 'Vous ne recevrez plus d\'emails. Les notifications push restent actives.',
            'confirm_btn' => 'Oui, me désabonner',
            'cancel_btn' => 'Annuler',
            'processing' => 'Traitement...',
            'success_title' => 'Désabonné',
            'success_msg' => 'Vous avez été désabonné avec succès.',
            'success_note' => 'Vous ne recevrez plus d\'emails.',
            'home_btn' => 'Accueil',
            'error_title' => 'Erreur',
            'error_msg' => 'Lien invalide ou expiré.',
            'error_btn' => 'Accueil'
        ],
        'it' => [
            'title' => 'Annulla iscrizione',
            'header' => 'Annulla iscrizione alla newsletter',
            'confirm_text' => 'Sei sicuro di volerti disiscrivere dalla nostra newsletter email?',
            'warning' => 'Non riceverai più email. Le notifiche push rimarranno attive.',
            'confirm_btn' => 'Sì, disiscrivimi',
            'cancel_btn' => 'Annulla',
            'processing' => 'Elaborazione...',
            'success_title' => 'Disiscritto',
            'success_msg' => 'Ti sei disiscritto con successo.',
            'success_note' => 'Non riceverai più email da noi.',
            'home_btn' => 'Home',
            'error_title' => 'Errore',
            'error_msg' => 'Link non valido o scaduto.',
            'error_btn' => 'Home'
        ],
        'ru' => [
            'title' => 'Отписка',
            'header' => 'Отписка от email рассылки',
            'confirm_text' => 'Вы уверены, что хотите отписаться от email рассылки?',
            'warning' => 'Вы больше не будете получать письма. Push-уведомления останутся активными.',
            'confirm_btn' => 'Отписаться',
            'cancel_btn' => 'Отмена',
            'processing' => 'Обработка...',
            'success_title' => 'Отписано',
            'success_msg' => 'Вы успешно отписались от рассылки.',
            'success_note' => 'Вы больше не будете получать письма.',
            'home_btn' => 'На главную',
            'error_title' => 'Ошибка',
            'error_msg' => 'Неверная или устаревшая ссылка.',
            'error_btn' => 'На главную'
        ]
    ];

    $t = $texts[$language] ?? $texts['en'];
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo htmlspecialchars($language); ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($t['title']); ?></title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:#f5f5f5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
            .card{max-width:500px;width:100%;background:#fff;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);overflow:hidden}
            .card-header{background:#1a1a2e;padding:28px 24px;text-align:center}
            .card-header h1{color:#fff;font-size:20px;font-weight:500}
            .card-body{padding:32px}
            h2{font-size:18px;color:#1a1a2e;text-align:center;margin-bottom:16px;font-weight:500}
            .confirm-text{text-align:center;color:#555;margin-bottom:12px;line-height:1.5;font-size:14px}
            .warning-text{text-align:center;color:#856404;font-size:13px;background:#fff3cd;padding:12px;border-radius:6px;margin:20px 0}
            .btn{display:block;width:100%;padding:12px;border:none;border-radius:6px;font-size:15px;font-weight:500;cursor:pointer;text-align:center;text-decoration:none}
            .btn-danger{background:#dc3545;color:#fff;margin-bottom:12px}
            .btn-danger:hover{background:#c82333}
            .btn-outline{background:transparent;color:#666;border:1px solid #ddd}
            .btn-outline:hover{background:#f8f9fa}
            .btn-home{background:#4a5568;color:#fff}
            .btn-home:hover{background:#2d3748}
            .loading{display:inline-block;width:16px;height:16px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:8px}
            @keyframes spin{to{transform:rotate(360deg)}}
            .success-icon{text-align:center;margin-bottom:20px}
            .success-icon .check{display:inline-block;width:56px;height:56px;background:#28a745;color:#fff;border-radius:50%;line-height:56px;font-size:28px}
            .error-icon{text-align:center;margin-bottom:20px}
            .error-icon .cross{display:inline-block;width:56px;height:56px;background:#dc3545;color:#fff;border-radius:50%;line-height:56px;font-size:28px}
            .success-note{text-align:center;color:#666;font-size:13px;margin-top:16px}
        </style>
    </head>
    <body>
    <div class="card">
        <div class="card-header">
            <h1><?php echo htmlspecialchars($t['header']); ?></h1>
        </div>
        <div class="card-body" id="content">
            <div class="warning-icon"></div>
            <h2><?php echo htmlspecialchars($t['confirm_text']); ?></h2>
            <div class="warning-text"><?php echo htmlspecialchars($t['warning']); ?></div>
            <button class="btn btn-danger" id="confirmBtn"><?php echo htmlspecialchars($t['confirm_btn']); ?></button>
            <a href="/" class="btn btn-outline"><?php echo htmlspecialchars($t['cancel_btn']); ?></a>
        </div>
    </div>
    <script>
        (function() {
            var confirmBtn = document.getElementById('confirmBtn');
            var content = document.getElementById('content');
            var texts = <?php echo json_encode($t); ?>;
            var token = '<?php echo htmlspecialchars($token, ENT_QUOTES); ?>';

            if (confirmBtn) {
                confirmBtn.addEventListener('click', function() {
                    confirmBtn.disabled = true;
                    confirmBtn.innerHTML = '<span class="loading"></span> ' + texts.processing;

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ token: token })
                    })
                        .then(function(response) { return response.json(); })
                        .then(function(result) {
                            if (result.success) {
                                content.innerHTML = '<div class="success-icon"><div class="check">✓</div></div>' +
                                    '<h2>' + texts.success_title + '</h2>' +
                                    '<div class="confirm-text">' + texts.success_msg + '</div>' +
                                    '<div class="success-note">' + texts.success_note + '</div>' +
                                    '<a href="/" class="btn btn-home" style="margin-top:20px;">' + texts.home_btn + '</a>';
                            } else {
                                content.innerHTML = '<div class="error-icon"><div class="cross">✗</div></div>' +
                                    '<h2>' + texts.error_title + '</h2>' +
                                    '<div class="confirm-text">' + (result.message || texts.error_msg) + '</div>' +
                                    '<a href="/" class="btn btn-home" style="margin-top:20px;">' + texts.error_btn + '</a>';
                            }
                        })
                        .catch(function() {
                            content.innerHTML = '<div class="error-icon"><div class="cross">✗</div></div>' +
                                '<h2>' + texts.error_title + '</h2>' +
                                '<div class="confirm-text">Network error. Please try again.</div>' +
                                '<a href="/" class="btn btn-home" style="margin-top:20px;">' + texts.home_btn + '</a>';
                        });
                });
            }
        })();
    </script>
    </body>
    </html>
    <?php
}

// show error page
function show_error_page($message) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f5f5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
            .card{max-width:400px;width:100%;background:#fff;border-radius:12px;padding:32px;text-align:center}
            h2{margin-bottom:16px;color:#333;font-weight:500}
            p{color:#666;margin-bottom:24px}
            .btn{display:inline-block;padding:10px 24px;background:#4a5568;color:#fff;text-decoration:none;border-radius:6px}
        </style>
    </head>
    <body>
    <div class="card">
        <h2>Error</h2>
        <p><?php echo htmlspecialchars($message); ?></p>
        <a href="/" class="btn">Homepage</a>
    </div>
    </body>
    </html>
    <?php
}