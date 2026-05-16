<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/contact_mail.php';
require_once __DIR__ . '/support_ticket.php';

/**
 * Notification équipe : nouvelle demande depuis le portail client.
 *
 * @param array<string, mixed> $client ligne clients (company_name, etc.)
 * @param array<string, mixed> $ticket ligne client_support_tickets
 */
function send_support_ticket_admin_mail(array $client, array $ticket): void
{
    if (!is_file(__DIR__ . '/../vendor/autoload.php')) {
        error_log('support_ticket_mail: vendor/autoload.php manquant');

        return;
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    /** @var array<string, mixed> $cfg */
    $cfg = require __DIR__ . '/../config/mail.php';

    if (empty($cfg['enabled']) || ($cfg['password'] ?? '') === '' || ($cfg['username'] ?? '') === '') {
        return;
    }

    $company = trim((string) ($client['company_name'] ?? ''));
    $subjectLine = trim((string) ($ticket['subject'] ?? ''));
    $category = support_ticket_category_label((string) ($ticket['category'] ?? 'other'));
    $messageBody = trim((string) ($ticket['message'] ?? ''));
    $reqName = trim((string) ($ticket['requester_name'] ?? ''));
    $reqEmail = trim((string) ($ticket['requester_email'] ?? ''));
    $ticketId = (int) ($ticket['id'] ?? 0);
    $clientId = (int) ($ticket['client_id'] ?? 0);

    $whenLabel = date('d/m/Y') . ' à ' . date('H:i');
    $adminDetailUrl = absolute_url_from_path(url('admin/client_detail.php?id=' . $clientId));

    $companyEsc = htmlspecialchars($company, ENT_QUOTES, 'UTF-8');
    $categoryEsc = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
    $subjectEsc = htmlspecialchars($subjectLine, ENT_QUOTES, 'UTF-8');
    $messageHtml = nl2br(htmlspecialchars($messageBody, ENT_QUOTES, 'UTF-8'), false);
    $reqBlock = '';
    if ($reqName !== '' || $reqEmail !== '') {
        $rn = htmlspecialchars($reqName !== '' ? $reqName : '—', ENT_QUOTES, 'UTF-8');
        $re = htmlspecialchars($reqEmail !== '' ? $reqEmail : '—', ENT_QUOTES, 'UTF-8');
        $mailto = $reqEmail !== '' ? htmlspecialchars('mailto:' . $reqEmail, ENT_QUOTES, 'UTF-8') : '#';
        $reqBlock = <<<HTML
              <tr>
                <td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;">
                  <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#94a3b8;margin-bottom:8px;">Demandeur</div>
                  <div style="font-size:15px;color:#0f172a;line-height:1.45;"><strong>{$rn}</strong><br>
                  <a href="{$mailto}" style="color:#16a34a;font-weight:600;">{$re}</a></div>
                </td>
              </tr>
HTML;
    }

    $detailEsc = htmlspecialchars($adminDetailUrl, ENT_QUOTES, 'UTF-8');

    $mail = new PHPMailer(true);
    try {
        contact_mail_configure_smtp($mail, $cfg);
        $mail->setFrom((string) $cfg['from_email'], (string) ($cfg['from_name'] ?? 'Goo-Bridge'));
        $mail->addAddress((string) $cfg['to_email'], (string) ($cfg['to_name'] ?? ''));
        if ($reqEmail !== '' && filter_var($reqEmail, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($reqEmail, $reqName !== '' ? $reqName : $reqEmail);
        }
        $mail->Subject = '[Goo-Bridge] Demande client — ' . ($company !== '' ? $company : 'Entreprise #' . $clientId);
        $mail->isHTML(true);
        $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0;font-family:system-ui,-apple-system,sans-serif;background:#ecfdf3;line-height:1.55;color:#1e293b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:28px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" style="max-width:560px;background:#fff;border-radius:18px;border:1px solid rgba(22,163,74,.2);overflow:hidden;">
<tr><td style="background:linear-gradient(135deg,#14532d,#15803d);padding:22px 26px;color:#fff;">
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;opacity:.9;">Portail entreprise</div>
<div style="font-size:21px;font-weight:800;margin-top:8px;">Nouvelle demande (#{$ticketId})</div>
<div style="font-size:14px;margin-top:10px;opacity:.95;">{$companyEsc}</div>
</td></tr>
<tr><td style="padding:20px 26px;">
<p style="margin:0 0 16px;font-size:13px;color:#64748b;">Reçu le <strong>{$whenLabel}</strong></p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0 10px;">
<tr><td style="background:#fef9c3;border:1px solid #fde047;border-radius:12px;padding:14px 16px;">
<div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#a16207;">Type</div>
<div style="font-size:15px;font-weight:700;color:#713f12;">{$categoryEsc}</div>
</td></tr>
<tr><td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;">
<div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#94a3b8;">Sujet</div>
<div style="font-size:16px;font-weight:700;">{$subjectEsc}</div>
</td></tr>
{$reqBlock}
<tr><td style="background:#fff;border:1px solid #d1fae5;border-radius:12px;padding:16px 18px;">
<div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#059669;margin-bottom:10px;">Message</div>
<div style="font-size:15px;">{$messageHtml}</div>
</td></tr>
</table>
<p style="margin:20px 0 0;font-size:14px;"><a href="{$detailEsc}" style="display:inline-block;background:#16a34a;color:#fff;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:12px;">Ouvrir la fiche client</a></p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
        $mail->AltBody = "Nouvelle demande #{$ticketId}\nEntreprise : {$company}\nType : {$category}\nSujet : {$subjectLine}\n\n{$messageBody}\n\nFiche : {$adminDetailUrl}\n";

        $mail->send();
    } catch (MailException $e) {
        error_log('support_ticket_mail: ' . $mail->ErrorInfo);
    } catch (Throwable $e) {
        error_log('support_ticket_mail: ' . $e->getMessage());
    }
}

/* ====================================================================== */
/*  Notifications CLIENT (envoyées au demandeur / client)                  */
/* ====================================================================== */

/**
 * Renvoie l'adresse email à utiliser pour notifier le client à propos d'un ticket.
 * Priorité : requester_email > client.email.
 */
function support_ticket_client_recipient(array $client, array $ticket): ?array
{
    $reqEmail = trim((string) ($ticket['requester_email'] ?? ''));
    $reqName = trim((string) ($ticket['requester_name'] ?? ''));
    $clientEmail = trim((string) ($client['email'] ?? ''));
    $contactName = trim((string) ($client['contact_name'] ?? ''));
    $company = trim((string) ($client['company_name'] ?? ''));

    $email = $reqEmail !== '' ? $reqEmail : $clientEmail;
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $name = $reqName !== '' ? $reqName : ($contactName !== '' ? $contactName : $company);
    return ['email' => $email, 'name' => $name];
}

/**
 * Cœur générique d'envoi : prépare un email HTML branché sur le SMTP du contact.
 *
 * @param array<string,mixed> $client
 * @param array<string,mixed> $ticket
 */
function support_ticket_send_client_mail(
    array $client,
    array $ticket,
    string $subject,
    string $headline,
    string $intro,
    string $bodyHtml,
    string $accent = '#16a34a',
    string $accentDark = '#15803d',
    string $ctaLabel = 'Voir mes tickets',
    string $logTag = 'support_ticket_client_mail'
): void {
    if (!is_file(__DIR__ . '/../vendor/autoload.php')) {
        error_log($logTag . ': vendor/autoload.php manquant');
        return;
    }
    require_once __DIR__ . '/../vendor/autoload.php';

    /** @var array<string, mixed> $cfg */
    $cfg = require __DIR__ . '/../config/mail.php';
    if (empty($cfg['enabled']) || ($cfg['password'] ?? '') === '' || ($cfg['username'] ?? '') === '') {
        return;
    }

    $recipient = support_ticket_client_recipient($client, $ticket);
    if ($recipient === null) {
        return;
    }

    $ticketId = (int) ($ticket['id'] ?? 0);
    $company = trim((string) ($client['company_name'] ?? ''));
    $subjectLine = trim((string) ($ticket['subject'] ?? ''));
    $whenLabel = date('d/m/Y') . ' à ' . date('H:i');

    // URL portail client pour suivre le ticket
    $portalToken = trim((string) ($client['ticket_portal_token'] ?? ''));
    $portalUrl = $portalToken !== ''
        ? absolute_url_from_path(url('client_support.php?t=' . rawurlencode($portalToken)))
        : absolute_url_from_path(url('support.php'));

    $companyEsc = htmlspecialchars($company, ENT_QUOTES, 'UTF-8');
    $subjectTicketEsc = htmlspecialchars($subjectLine, ENT_QUOTES, 'UTF-8');
    $portalEsc = htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8');
    $whenEsc = htmlspecialchars($whenLabel, ENT_QUOTES, 'UTF-8');
    $headlineEsc = htmlspecialchars($headline, ENT_QUOTES, 'UTF-8');
    $introEsc = nl2br(htmlspecialchars($intro, ENT_QUOTES, 'UTF-8'), false);
    $ctaEsc = htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8');
    $accentEsc = htmlspecialchars($accent, ENT_QUOTES, 'UTF-8');
    $accentDarkEsc = htmlspecialchars($accentDark, ENT_QUOTES, 'UTF-8');

    $mail = new PHPMailer(true);
    try {
        contact_mail_configure_smtp($mail, $cfg);
        $mail->setFrom((string) $cfg['from_email'], (string) ($cfg['from_name'] ?? 'Goo-Bridge'));
        $mail->addAddress($recipient['email'], $recipient['name'] !== '' ? $recipient['name'] : $recipient['email']);
        if (!empty($cfg['to_email'])) {
            $mail->addReplyTo((string) $cfg['to_email'], (string) ($cfg['to_name'] ?? 'Goo-Bridge'));
        }
        $mail->Subject = '[Goo-Bridge] ' . $subject . ' — Ticket #' . $ticketId;
        $mail->isHTML(true);
        $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0;font-family:system-ui,-apple-system,sans-serif;background:#f1f5f9;line-height:1.55;color:#1e293b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:28px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" style="max-width:560px;background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;">
<tr><td style="background:linear-gradient(135deg,{$accentDarkEsc},{$accentEsc});padding:24px 28px;color:#fff;">
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;opacity:.9;">Goo-Bridge · Support</div>
<div style="font-size:22px;font-weight:800;margin-top:8px;line-height:1.2;">{$headlineEsc}</div>
<div style="font-size:13px;margin-top:8px;opacity:.92;">Ticket #{$ticketId} · {$companyEsc}</div>
</td></tr>
<tr><td style="padding:24px 28px;">
<p style="margin:0 0 10px;font-size:13px;color:#64748b;">{$whenEsc}</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0 10px;">
<tr><td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 16px;">
<div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;">Sujet</div>
<div style="font-size:15px;font-weight:700;color:#0f172a;">{$subjectTicketEsc}</div>
</td></tr>
</table>
<div style="margin:18px 0 8px;font-size:15px;color:#1e293b;line-height:1.55;">{$introEsc}</div>
{$bodyHtml}
<p style="margin:24px 0 6px;">
  <a href="{$portalEsc}" style="display:inline-block;background:{$accentEsc};color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:12px;">{$ctaEsc}</a>
</p>
<p style="margin:18px 0 0;font-size:12px;color:#94a3b8;">Ce message est généré automatiquement par Goo-Bridge. Pour répondre, vous pouvez utiliser le portail support ou répondre directement à cet email.</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
        $mail->AltBody = $headline . "\n\n"
            . "Ticket #{$ticketId} — {$company}\n"
            . "Sujet : {$subjectLine}\n\n"
            . $intro . "\n\n"
            . "Suivre le ticket : {$portalUrl}\n";

        $mail->send();
    } catch (MailException $e) {
        error_log($logTag . ': ' . $mail->ErrorInfo);
    } catch (Throwable $e) {
        error_log($logTag . ': ' . $e->getMessage());
    }
}

/**
 * Notifie le client que son ticket est pris en charge (ou transféré).
 *
 * @param array<string,mixed> $client
 * @param array<string,mixed> $ticket
 */
function send_support_ticket_taken_client_mail(array $client, array $ticket, ?string $adminEmail = null): void
{
    $adminLine = ($adminEmail !== null && $adminEmail !== '')
        ? 'Un membre de notre équipe (' . htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') . ') vient de prendre votre ticket en charge.'
        : 'Un membre de notre équipe vient de prendre votre ticket en charge.';

    $bodyHtml = <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0 10px;">
<tr><td style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:12px;padding:14px 16px;">
<div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6366f1;">Statut</div>
<div style="font-size:15px;font-weight:700;color:#3730a3;">En traitement</div>
<div style="font-size:13px;color:#475569;margin-top:6px;">{$adminLine}</div>
</td></tr>
</table>
HTML;

    support_ticket_send_client_mail(
        $client, $ticket,
        'Votre ticket est pris en charge',
        'Votre ticket est désormais traité',
        'Bonjour, nous vous confirmons que votre demande est désormais en cours de traitement par notre équipe. Vous serez tenu(e) informé(e) à chaque étape.',
        $bodyHtml,
        '#6366f1', '#4338ca',
        'Suivre mon ticket',
        'support_ticket_taken_client_mail'
    );
}

/**
 * Notifie le client qu'une intervention a été enregistrée sur son ticket.
 */
function send_support_ticket_note_client_mail(array $client, array $ticket, string $noteBody, ?string $adminEmail = null): void
{
    $excerpt = mb_strlen($noteBody) > 700 ? mb_substr($noteBody, 0, 700) . '…' : $noteBody;
    $noteHtml = nl2br(htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8'), false);
    $byLine = ($adminEmail !== null && $adminEmail !== '')
        ? 'Ajoutée par ' . htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8')
        : 'Ajoutée par l’équipe Goo-Bridge';

    $bodyHtml = <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0 10px;">
<tr><td style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 16px;">
<div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#15803d;">Nouvelle intervention</div>
<div style="font-size:13px;color:#16a34a;margin-bottom:8px;">{$byLine}</div>
<div style="font-size:14px;color:#1e293b;line-height:1.55;">{$noteHtml}</div>
</td></tr>
</table>
HTML;

    support_ticket_send_client_mail(
        $client, $ticket,
        'Mise à jour sur votre ticket',
        'Nouvelle action sur votre ticket',
        'Bonjour, notre équipe a effectué une nouvelle action sur votre ticket. Voici un résumé de cette intervention :',
        $bodyHtml,
        '#16a34a', '#15803d',
        'Voir le détail',
        'support_ticket_note_client_mail'
    );
}

/**
 * Notifie le client que son ticket est résolu / clôturé.
 */
function send_support_ticket_closed_client_mail(array $client, array $ticket): void
{
    $bodyHtml = <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0 10px;">
<tr><td style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:14px 16px;">
<div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#047857;">Statut</div>
<div style="font-size:15px;font-weight:700;color:#065f46;">Résolu ✓</div>
<div style="font-size:13px;color:#047857;margin-top:6px;">Si la solution proposée ne correspond pas à votre besoin, vous pouvez répondre à cet email ou rouvrir le ticket depuis votre portail.</div>
</td></tr>
</table>
HTML;

    support_ticket_send_client_mail(
        $client, $ticket,
        'Votre ticket est résolu',
        'Votre ticket a été marqué comme résolu',
        'Bonjour, nous avons clôturé votre ticket. L’intervention est terminée. Vous pouvez consulter le journal complet dans votre espace de support.',
        $bodyHtml,
        '#10b981', '#047857',
        'Consulter le ticket',
        'support_ticket_closed_client_mail'
    );
}

/**
 * Notifie le client que son ticket a été rouvert.
 */
function send_support_ticket_reopened_client_mail(array $client, array $ticket): void
{
    $bodyHtml = <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0 10px;">
<tr><td style="background:#fef3c7;border:1px solid #fde68a;border-radius:12px;padding:14px 16px;">
<div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#b45309;">Statut</div>
<div style="font-size:15px;font-weight:700;color:#92400e;">Rouvert</div>
</td></tr>
</table>
HTML;

    support_ticket_send_client_mail(
        $client, $ticket,
        'Votre ticket est rouvert',
        'Votre ticket a été rouvert',
        'Bonjour, votre ticket vient d’être rouvert par notre équipe pour un complément de traitement. Nous reprenons contact avec vous prochainement.',
        $bodyHtml,
        '#f59e0b', '#b45309',
        'Suivre mon ticket',
        'support_ticket_reopened_client_mail'
    );
}
