<?php

declare(strict_types=1);

/**
 * Copiez ce fichier vers mail.local.php et renseignez les valeurs.
 * mail.local.php ne doit pas être versionné (voir .gitignore).
 *
 * En production sur goo-bridge.com, vous pouvez aussi utiliser les variables
 * d'environnement suivantes, sans créer ce fichier :
 *
 * MAIL_ENABLED=true
 * SMTP_HOST=goo-bridge.com
 * SMTP_PORT=465
 * SMTP_ENCRYPTION=ssl
 * SMTP_USERNAME=contact@goo-bridge.com
 * SMTP_PASSWORD=mot_de_passe_de_la_boite_mail
 * MAIL_FROM_EMAIL=contact@goo-bridge.com
 * MAIL_FROM_NAME="Goo-Bridge — formulaire"
 * SMTP_HOSTNAME=goo-bridge.com
 * MAIL_TO_EMAIL=contact@goo-bridge.com
 * MAIL_TO_NAME="Goo-Bridge Contact"
 */
return [
    'enabled' => false,
    'host' => 'goo-bridge.com',
    'port' => 465,
    /** ssl pour le port 465 (SMTPS) */
    'encryption' => 'ssl',
    'username' => 'no-reply@votredomaine.com',
    'password' => '',
    'from_email' => 'no-reply@votredomaine.com',
    'from_name' => 'Goo-Bridge — formulaire',
    /** Nom d’hôte annoncé en EHLO (délivrabilité). Souvent identique au domaine du mail. */
    'smtp_hostname' => 'goo-bridge.com',
    /** Destinataire des messages du formulaire contact */
    'to_email' => 'contact@goo-bridge.com',
    'to_name' => 'Goo-Bridge Contact',
];
