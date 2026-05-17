<?php

declare(strict_types=1);

/**
 * Configuration application.
 *
 * base_url : URL de base du projet depuis la racine du site (sans slash final).
 *   - null  = détection automatique
 *   - ''    = site à la racine du domaine (ex. https://goo-bridge.com/)
 *   - '/bridge' = projet dans un sous-dossier (ex. https://goo-bridge.com/bridge/)
 *
 * Si après connexion vous n’atterrissez pas sur le dashboard, forcez la valeur adaptée.
 *
 * public_origin : URL absolue du site en production (schéma + hôte, sans slash final),
 *   utilisée pour les liens dans les e-mails (support, contact, maintenance, etc.).
 *   Ainsi les destinataires ne reçoivent pas de liens http://localhost/... en dev.
 *   Exemple : 'https://goo-bridge.com'. Laisser null pour déduire depuis la requête HTTP.
 */
return [
    'base_url' => null,
    'public_origin' => 'https://goo-bridge.com',

    /**
     * Si true, register.php permet de créer un compte administrateur (accès public).
     * Sinon, seuls les super-administrateurs peuvent créer des comptes depuis l’interface admin.
     */
    'allow_admin_registration' => false,
];
