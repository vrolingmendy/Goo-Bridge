<?php

declare(strict_types=1);

/**
 * Copiez ce fichier vers mail.local.php et renseignez les valeurs.
 * mail.local.php ne doit pas être versionné (voir .gitignore).
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
