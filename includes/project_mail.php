<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/contact_mail.php';

/**
 * Envoi d'une notification au contact d'une entreprise pour une tâche projet effectuée.
 *
 * @param array{
 *     company_name: string,
 *     contact_name?: string|null,
 *     email: string,
 *     project_type?: string|null
 * } $client
 * @param array{
 *     title: string,
 *     description?: string|null,
 *     completed_at?: string|null
 * } $task
 *
 * @return array{ok: bool, message: string}
 */
function send_project_task_completion_mail(array $client, array $task): array
{
    if (!is_file(__DIR__ . '/../vendor/autoload.php')) {
        $msg = 'vendor/autoload.php manquant — exécutez composer install';
        error_log('project_mail: ' . $msg);

        return ['ok' => false, 'message' => $msg];
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    /** @var array<string, mixed> $cfg */
    $cfg = require __DIR__ . '/../config/mail.php';

    if (empty($cfg['enabled']) || ($cfg['password'] ?? '') === '' || ($cfg['username'] ?? '') === '') {
        return ['ok' => false, 'message' => 'Envoi désactivé (SMTP non configuré).'];
    }

    $clientEmail = trim((string) ($client['email'] ?? ''));
    if ($clientEmail === '' || !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Aucune adresse email valide pour ce client.'];
    }

    $companyName = trim((string) ($client['company_name'] ?? ''));
    $contactName = trim((string) ($client['contact_name'] ?? ''));
    $projectType = trim((string) ($client['project_type'] ?? ''));
    $greeting = $contactName !== '' ? $contactName : ($companyName !== '' ? $companyName : 'Bonjour');

    $title = trim((string) $task['title']);
    $description = trim((string) ($task['description'] ?? ''));
    $completedAt = (string) ($task['completed_at'] ?? '');
    $completedTs = $completedAt !== '' ? strtotime($completedAt) : time();
    if ($completedTs === false) {
        $completedTs = time();
    }
    $completedLabel = date('d/m/Y à H:i', $completedTs);

    $greetingEsc = htmlspecialchars($greeting, ENT_QUOTES, 'UTF-8');
    $companyEsc = htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8');
    $projectEsc = htmlspecialchars($projectType !== '' ? $projectType : 'Votre projet', ENT_QUOTES, 'UTF-8');
    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $descriptionEsc = $description !== '' ? nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8'), false) : '';
    $completedEsc = htmlspecialchars($completedLabel, ENT_QUOTES, 'UTF-8');
    $contactEmailEsc = htmlspecialchars((string) $cfg['to_email'], ENT_QUOTES, 'UTF-8');

    $descriptionBlock = $descriptionEsc !== ''
        ? <<<HTML
              <tr>
                <td style="background:#ffffff;border:1px solid #d1fae5;border-radius:14px;padding:20px 22px;">
                  <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#059669;margin-bottom:12px;">Détail de l'intervention</div>
                  <div style="font-size:15px;line-height:1.65;color:#1e293b;">{$descriptionEsc}</div>
                </td>
              </tr>
HTML
        : '';

    $mail = new PHPMailer(true);
    try {
        contact_mail_configure_smtp($mail, $cfg);
        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($clientEmail, $contactName !== '' ? $contactName : $companyName);
        $mail->addReplyTo($cfg['to_email'], $cfg['to_name']);
        $mail->Subject = '[Goo-Bridge] Tâche réalisée — ' . $title;
        $mail->isHTML(true);
        $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tâche réalisée</title>
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
                  <div style="font-family:Segoe UI,system-ui,-apple-system,sans-serif;font-size:11px;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:rgba(255,255,255,0.82);margin-bottom:10px;">Suivi de projet</div>
                  <div style="font-family:Segoe UI,system-ui,-apple-system,sans-serif;font-size:26px;font-weight:800;letter-spacing:-0.04em;color:#ffffff;line-height:1.15;">Tâche réalisée</div>
                  <div style="font-family:Segoe UI,system-ui,-apple-system,sans-serif;font-size:15px;color:rgba(255,255,255,0.92);margin-top:10px;line-height:1.45;">{$projectEsc}</div>
                </td>
                <td width="72" valign="middle" align="right">
                  <div style="width:56px;height:56px;border-radius:14px;background:rgba(255,255,255,0.18);border:1px solid rgba(255,255,255,0.35);font-family:Segoe UI,system-ui,sans-serif;font-size:22px;font-weight:800;color:#fff;text-align:center;line-height:56px;">✓</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:24px 36px 8px;font-family:Segoe UI,system-ui,-apple-system,sans-serif;">
            <p style="margin:0 0 6px;font-size:16px;color:#0f172a;line-height:1.5;">Bonjour <strong>{$greetingEsc}</strong>,</p>
            <p style="margin:0;font-size:14px;color:#475569;line-height:1.55;">Une étape de votre projet vient d'être complétée par notre équipe.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:18px 36px 28px;font-family:Segoe UI,system-ui,-apple-system,sans-serif;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0 12px;">
              <tr>
                <td style="background:linear-gradient(180deg,#f0fdf4 0%,#dcfce7 100%);border:1px solid rgba(22,163,74,0.28);border-radius:14px;padding:20px 22px;">
                  <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#166534;margin-bottom:8px;">Tâche</div>
                  <div style="font-size:18px;font-weight:800;color:#14532d;line-height:1.3;letter-spacing:-0.02em;">{$titleEsc}</div>
                </td>
              </tr>
              <tr>
                <td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;">
                  <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#94a3b8;margin-bottom:6px;">Réalisée le</div>
                  <div style="font-size:16px;font-weight:700;color:#0f172a;">{$completedEsc}</div>
                </td>
              </tr>
{$descriptionBlock}
            </table>
            <p style="margin:18px 0 0;font-size:13px;color:#64748b;line-height:1.6;">Pour toute question ou retour sur cette intervention, vous pouvez répondre directement à ce message ou nous écrire à <a href="mailto:{$contactEmailEsc}" style="color:#16a34a;text-decoration:none;font-weight:600;">{$contactEmailEsc}</a>.</p>
          </td>
        </tr>
        <tr>
          <td style="background:#f0fdf4;padding:20px 36px;border-top:1px solid rgba(22,163,74,0.12);font-family:Segoe UI,system-ui,-apple-system,sans-serif;text-align:center;">
            <p style="margin:0;font-size:12px;color:#3f6212;line-height:1.5;"><strong style="color:#166534;">Goo-Bridge</strong> · Notification de suivi de projet pour {$companyEsc}</p>
          </td>
        </tr>
      </table>
      <p style="font-family:Segoe UI,system-ui,sans-serif;font-size:11px;color:#86a893;margin:20px 8px 0;text-align:center;">Vous recevez cet email car votre entreprise est suivie par Goo-Bridge.</p>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

        $altDescription = $description !== '' ? "\n\nDétail :\n" . $description . "\n" : '';
        $mail->AltBody = "Bonjour {$greeting},\n\n"
            . "Une étape de votre projet vient d'être complétée par notre équipe.\n\n"
            . 'Tâche : ' . $title . "\n"
            . 'Réalisée le : ' . $completedLabel . "\n"
            . $altDescription
            . "\nPour toute question : " . $cfg['to_email'] . "\n\n"
            . "Cordialement,\nL'équipe Goo-Bridge";

        $mail->send();

        return ['ok' => true, 'message' => 'Notification envoyée à ' . $clientEmail . '.'];
    } catch (MailException $e) {
        error_log('project_mail: ' . $mail->ErrorInfo);

        return ['ok' => false, 'message' => 'Envoi du mail impossible — ' . $mail->ErrorInfo];
    } catch (Throwable $e) {
        error_log('project_mail: ' . $e->getMessage());

        return ['ok' => false, 'message' => 'Envoi du mail impossible — ' . $e->getMessage()];
    }
}
