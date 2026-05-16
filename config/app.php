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
 */
return [
    'base_url' => null,

    /**
     * Si true, register.php permet de créer un compte administrateur (accès public).
     * Sinon, seuls les super-administrateurs peuvent créer des comptes depuis l’interface admin.
     */
    'allow_admin_registration' => false,
];
