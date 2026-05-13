<?php

declare(strict_types=1);

/**
 * Configuration application.
 *
 * base_url : URL de base du projet depuis la racine du site (sans slash final).
 *   - null  = détection automatique (recommandé en général)
 *   - ''    = site à la racine du virtual host (ex. http://localhost/)
 *   - '/bridge' = projet dans un sous-dossier (ex. http://localhost/bridge/)
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
