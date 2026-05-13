<?php

declare(strict_types=1);

/**
 * Test d’envoi SMTP — usage : php mail_test.php [email@destinataire]
 * À supprimer ou désactiver après diagnostic en production.
 */
if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Exécutez depuis le terminal : php mail_test.php';
    exit(1);
}

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

$dest = $argv[1] ?? 'vroling@groupeisi.com';

if (!filter_var($dest, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Email destinataire invalide : {$dest}\n");
    exit(1);
}

if (!is_readable(__DIR__ . '/config/mail.php')) {
    fwrite(STDERR, "config/mail.php introuvable.\n");
    exit(1);
}

/** @var array<string, mixed> $cfg */
$cfg = require __DIR__ . '/config/mail.php';

if (empty($cfg['enabled'])) {
    fwrite(STDERR, "Envoi désactivé dans la config (enabled => false). Utilisez config/mail.local.php.\n");
    exit(1);
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = (string) $cfg['host'];
    $mail->SMTPAuth = true;
    $mail->Username = (string) $cfg['username'];
    $mail->Password = (string) $cfg['password'];
    $mail->Port = (int) $cfg['port'];

    if (($cfg['encryption'] ?? '') === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif (($cfg['encryption'] ?? '') === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->CharSet = PHPMailer::CHARSET_UTF8;

    $helo = isset($cfg['smtp_hostname']) && $cfg['smtp_hostname'] !== ''
        ? (string) $cfg['smtp_hostname']
        : (string) $cfg['host'];
    $mail->Hostname = $helo;

    // Certains hébergeurs XAMPP / CA : décommentez si erreur SSL en local
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ],
    ];

    $mail->SMTPDebug = 2;
    $mail->Debugoutput = static function (string $str, int $level): void {
        fwrite(STDERR, $str . "\n");
    };

    $mail->setFrom((string) $cfg['from_email'], (string) ($cfg['from_name'] ?? 'Test'));
    $mail->addAddress($dest);
    $mail->Subject = '[Test Goo-Bridge] Envoi SMTP ' . date('Y-m-d H:i:s');
    $mail->Body = "Si vous recevez ce message, la configuration SMTP fonctionne.\r\n\r\nDestinataire test : {$dest}\r\n";

    $mail->send();
    echo "OK — message envoyé vers {$dest}\n";
    exit(0);
} catch (MailException $e) {
    fwrite(STDERR, 'PHPMailer : ' . $mail->ErrorInfo . "\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
