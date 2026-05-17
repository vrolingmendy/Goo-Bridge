<?php

declare(strict_types=1);

require_once __DIR__ . '/paths.php';

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * @param array<string, mixed> $cfg
 */
function contact_mail_configure_smtp(PHPMailer $mail, array $cfg): void
{
    $mail->isSMTP();
    $mail->Host = $cfg['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $cfg['username'];
    $mail->Password = $cfg['password'];
    $mail->Port = (int) $cfg['port'];

    // EHLO / Message-ID : éviter localhost ou nom de machine (meilleure délivrabilité)
    $helo = isset($cfg['smtp_hostname']) && $cfg['smtp_hostname'] !== ''
        ? (string) $cfg['smtp_hostname']
        : (string) $cfg['host'];
    $mail->Hostname = $helo;

    if (($cfg['encryption'] ?? '') === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif (($cfg['encryption'] ?? '') === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->CharSet = PHPMailer::CHARSET_UTF8;
}

/**
 * @param array<string, mixed> $cfg
 * @param array{name: string, email: string, phone?: string, service: string, message: string} $record
 */
function contact_mail_send_visitor_confirmation(array $cfg, array $record): void
{
    $nameEsc = htmlspecialchars($record['name'], ENT_QUOTES, 'UTF-8');
    $contactEsc = htmlspecialchars($cfg['to_email'], ENT_QUOTES, 'UTF-8');
    $siteHomeEsc = htmlspecialchars(absolute_url_from_path(url('index.php')), ENT_QUOTES, 'UTF-8');

    $confirm = new PHPMailer(true);
    try {
        contact_mail_configure_smtp($confirm, $cfg);
        $confirm->setFrom($cfg['from_email'], 'Goo-Bridge');
        $confirm->addAddress($record['email'], $record['name']);
        $confirm->addReplyTo($cfg['to_email'], 'Goo-Bridge');
        $confirm->Subject = 'Votre demande Goo-Bridge a bien été reçue';
        $confirm->isHTML(true);
        $confirm->Body = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0;font-family:system-ui,-apple-system,sans-serif;line-height:1.55;color:#1a2e1a;background:#f7faf7;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" style="max-width:520px;background:#fff;border-radius:12px;border:1px solid rgba(22,163,74,.18);overflow:hidden;">
<tr><td style="padding:28px 28px 8px;">
<p style="margin:0 0 12px;font-size:15px;">Bonjour {$nameEsc},</p>
<p style="margin:0 0 14px;font-size:15px;">Nous avons bien reçu votre message et vous remercions pour l’intérêt porté à <strong>Goo-Bridge</strong>.</p>
<p style="margin:0 0 14px;font-size:15px;">Notre équipe vous contactera pour échanger plus en détail sur votre projet dans un délai de <strong>24 à 72 heures</strong> (jours ouvrés).</p>
<p style="margin:0;font-size:14px;color:#3d5c3d;">Si votre demande est urgente, vous pouvez nous écrire directement à <a href="mailto:{$contactEsc}" style="color:#16a34a;">{$contactEsc}</a>.</p>
<p style="margin:14px 0 0;font-size:13px;color:#647864;">Site : <a href="{$siteHomeEsc}" style="color:#16a34a;font-weight:600;text-decoration:none;">goo-bridge.com</a></p>
</td></tr>
<tr><td style="padding:16px 28px 28px;border-top:1px solid rgba(0,0,0,.06);">
<p style="margin:0;font-size:13px;color:#647864;">Cordialement,<br><strong>L’équipe Goo-Bridge</strong></p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
        $confirm->AltBody = "Bonjour {$record['name']},\n\n"
            . "Nous avons bien reçu votre message. Notre équipe vous contactera pour plus de détails sous 24 à 72 heures (jours ouvrés).\n\n"
            . 'Pour une demande urgente : ' . $cfg['to_email'] . "\n"
            . 'Site : ' . absolute_url_from_path(url('index.php')) . "\n\n"
            . "Cordialement,\nL'équipe Goo-Bridge";
        $confirm->send();
    } catch (MailException $e) {
        error_log('contact_mail (confirmation visiteur): ' . $confirm->ErrorInfo);
    } catch (Throwable $e) {
        error_log('contact_mail (confirmation visiteur): ' . $e->getMessage());
    }
}

/**
 * @param array<string, mixed> $cfg
 * @param array{name: string, email: string, phone?: string, service: string, message: string} $record
 */
function contact_mail_send_admin_notification(array $cfg, array $record): void
{
    $serviceLine = $record['service'] !== '' ? $record['service'] : 'Non précisé';
    $whenLabel = date('d/m/Y') . ' à ' . date('H:i');
    $phone = isset($record['phone']) ? trim((string) $record['phone']) : '';

    $nameEsc = htmlspecialchars($record['name'], ENT_QUOTES, 'UTF-8');
    $emailEsc = htmlspecialchars($record['email'], ENT_QUOTES, 'UTF-8');
    $serviceEsc = htmlspecialchars($serviceLine, ENT_QUOTES, 'UTF-8');
    $messageEsc = htmlspecialchars($record['message'], ENT_QUOTES, 'UTF-8');
    $messageHtml = nl2br($messageEsc, false);
    $mailtoEsc = htmlspecialchars('mailto:' . $record['email'], ENT_QUOTES, 'UTF-8');
    $siteHomeEsc = htmlspecialchars(absolute_url_from_path(url('index.php')), ENT_QUOTES, 'UTF-8');

    $phoneEsc = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
    $telDial = preg_replace('/[^\d+]/', '', $phone);
    $telHrefEsc = htmlspecialchars('tel:' . $telDial, ENT_QUOTES, 'UTF-8');
    $phoneRowHtml = $phone !== ''
        ? <<<PHONE
              <tr>
                <td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;">
                  <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#94a3b8;margin-bottom:6px;">Téléphone</div>
                  <a href="{$telHrefEsc}" style="font-size:16px;font-weight:600;color:#16a34a;text-decoration:none;border-bottom:2px solid rgba(22,163,74,0.35);">{$phoneEsc}</a>
                </td>
              </tr>
PHONE
        : '';

    $adminMail = new PHPMailer(true);
    try {
        contact_mail_configure_smtp($adminMail, $cfg);
        $adminMail->setFrom($cfg['from_email'], $cfg['from_name']);
        $adminMail->addAddress($cfg['to_email'], $cfg['to_name']);
        $adminMail->addReplyTo($record['email'], $record['name']);
        $adminMail->Subject = '[Goo-Bridge] Nouvelle demande — ' . $record['name'];
        $adminMail->isHTML(true);
        $adminMail->Body = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nouvelle demande</title>
</head>
<body style="margin:0;padding:0;background:#ecfdf3;-webkit-font-smoothing:antialiased;">
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#ecfdf3;">
  <tr>
    <td align="center" style="padding:36px 16px 48px;">
      <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:580px;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 22px 55px rgba(15, 81, 50, 0.12), 0 2px 0 rgba(255,255,255,0.9) inset;border:1px solid rgba(22, 163, 74, 0.14);">
        <tr>
          <td bgcolor="#15803d" style="background:linear-gradient(135deg,#14532d 0%,#15803d 38%,#22c55e 100%);padding:32px 36px 28px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td valign="middle">
                  <div style="font-family:Segoe UI,system-ui,-apple-system,sans-serif;font-size:11px;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:rgba(255,255,255,0.82);margin-bottom:10px;">Formulaire site</div>
                  <div style="font-family:Segoe UI,system-ui,-apple-system,sans-serif;font-size:26px;font-weight:800;letter-spacing:-0.04em;color:#ffffff;line-height:1.15;">Nouvelle demande</div>
                  <div style="font-family:Segoe UI,system-ui,-apple-system,sans-serif;font-size:15px;color:rgba(255,255,255,0.92);margin-top:10px;line-height:1.45;">{$nameEsc}</div>
                </td>
                <td width="72" valign="middle" align="right">
                  <div style="width:56px;height:56px;border-radius:14px;background:rgba(255,255,255,0.18);border:1px solid rgba(255,255,255,0.35);font-family:Segoe UI,system-ui,sans-serif;font-size:17px;font-weight:800;color:#fff;text-align:center;line-height:56px;">GB</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:22px 36px 8px;font-family:Segoe UI,system-ui,-apple-system,sans-serif;">
            <span style="display:inline-block;background:#dcfce7;color:#166534;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;padding:7px 14px;border-radius:100px;border:1px solid rgba(22,163,74,0.25);">À traiter</span>
            <p style="margin:18px 0 0;font-size:13px;color:#64748b;line-height:1.5;">Reçu le <strong style="color:#334155;">{$whenLabel}</strong> · Répondre à ce message pour contacter directement le prospect.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:12px 36px 28px;font-family:Segoe UI,system-ui,-apple-system,sans-serif;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0 12px;">
              <tr>
                <td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;">
                  <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#94a3b8;margin-bottom:6px;">Nom</div>
                  <div style="font-size:17px;font-weight:700;color:#0f172a;letter-spacing:-0.02em;">{$nameEsc}</div>
                </td>
              </tr>
              <tr>
                <td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;">
                  <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#94a3b8;margin-bottom:6px;">Email</div>
                  <a href="{$mailtoEsc}" style="font-size:16px;font-weight:600;color:#16a34a;text-decoration:none;border-bottom:2px solid rgba(22,163,74,0.35);">{$emailEsc}</a>
                </td>
              </tr>
{$phoneRowHtml}
              <tr>
                <td style="background:linear-gradient(180deg,#fffbeb 0%,#fef9c3 100%);border:1px solid #fde047;border-radius:14px;padding:18px 20px;">
                  <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#a16207;margin-bottom:6px;">Type de projet</div>
                  <div style="font-size:16px;font-weight:700;color:#713f12;">{$serviceEsc}</div>
                </td>
              </tr>
              <tr>
                <td style="background:#ffffff;border:1px solid #d1fae5;border-radius:14px;padding:20px 22px;">
                  <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#059669;margin-bottom:12px;">Message</div>
                  <div style="font-size:15px;line-height:1.65;color:#1e293b;">{$messageHtml}</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="background:#f0fdf4;padding:20px 36px;border-top:1px solid rgba(22,163,74,0.12);font-family:Segoe UI,system-ui,-apple-system,sans-serif;text-align:center;">
            <p style="margin:0;font-size:12px;color:#3f6212;line-height:1.5;"><strong style="color:#166534;">Goo-Bridge</strong> · Notification automatique du formulaire de contact</p>
          </td>
        </tr>
      </table>
      <p style="font-family:Segoe UI,system-ui,sans-serif;font-size:11px;color:#86a893;margin:20px 8px 0;text-align:center;">Cet email vous est envoyé car une personne a utilisé le formulaire sur <a href="{$siteHomeEsc}" style="color:#15803d;font-weight:600;text-decoration:none;">goo-bridge.com</a></p>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

        $altPhone = $phone !== '' ? 'Téléphone : ' . $phone . "\n" : '';
        $adminMail->AltBody = 'Nouvelle demande — ' . $record['name'] . "\n"
            . 'Reçu le : ' . $whenLabel . "\n\n"
            . 'Nom : ' . $record['name'] . "\n"
            . 'Email : ' . $record['email'] . "\n"
            . $altPhone
            . 'Type de projet : ' . $serviceLine . "\n\n"
            . "Message :\n" . $record['message'] . "\n";

        $adminMail->send();
    } catch (MailException $e) {
        error_log('contact_mail (admin): ' . $adminMail->ErrorInfo);
    } catch (Throwable $e) {
        error_log('contact_mail (admin): ' . $e->getMessage());
    }
}

/**
 * Confirmation au visiteur + notification équipe (les deux sont tentées même si l’une échoue).
 *
 * @param array{name: string, email: string, phone?: string, service: string, message: string} $record
 */
function send_contact_mail(array $record): void
{
    if (!is_file(__DIR__ . '/../vendor/autoload.php')) {
        error_log('contact_mail: vendor/autoload.php manquant — exécutez composer install');

        return;
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    /** @var array<string, mixed> $cfg */
    $cfg = require __DIR__ . '/../config/mail.php';

    if (!$cfg['enabled'] || ($cfg['password'] ?? '') === '' || ($cfg['username'] ?? '') === '') {
        return;
    }

    // D’abord le visiteur : il reçoit l’accusé même si la boîte contact échoue
    contact_mail_send_visitor_confirmation($cfg, $record);
    contact_mail_send_admin_notification($cfg, $record);
}
