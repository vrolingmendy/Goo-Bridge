<?php

declare(strict_types=1);

require_once __DIR__ . '/paths.php';

function client_logo_max_bytes(): int
{
    return 2 * 1024 * 1024;
}

function client_logo_abs_dir(): string
{
    return dirname(__DIR__) . '/uploads/client_logos';
}

/**
 * Valeur en base → URL publique affichable, ou null si invalide / vide.
 */
function client_logo_public_url(?string $stored): ?string
{
    if ($stored === null || $stored === '') {
        return null;
    }
    if (!preg_match('#^uploads/client_logos/[1-9][0-9]*\.(jpg|png|gif|webp)$#', $stored)) {
        return null;
    }

    return url($stored);
}

function client_logo_delete_files_for_client(int $clientId): void
{
    $dir = client_logo_abs_dir();
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . '/' . $clientId . '.*') ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/** @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null $file */
function client_logo_upload_errored(?array $file): bool
{
    if ($file === null || !isset($file['error'])) {
        return false;
    }

    return $file['error'] !== UPLOAD_ERR_OK && $file['error'] !== UPLOAD_ERR_NO_FILE;
}

/** @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null $file */
function client_logo_has_upload(?array $file): bool
{
    return $file !== null
        && isset($file['error'])
        && $file['error'] === UPLOAD_ERR_OK
        && isset($file['size'])
        && (int) $file['size'] > 0
        && isset($file['tmp_name'])
        && is_uploaded_file((string) $file['tmp_name']);
}

/**
 * Enregistre le fichier sur disque et met à jour `clients.logo_path`.
 *
 * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null $file
 */
function client_logo_save_upload(PDO $pdo, int $clientId, ?array $file): ?string
{
    if (!client_logo_has_upload($file)) {
        return null;
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($size > client_logo_max_bytes()) {
        return 'Le logo dépasse la taille maximale (2 Mo).';
    }

    if (!is_uploaded_file($tmp)) {
        return 'Fichier logo invalide.';
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    if ($mime === false) {
        return 'Impossible de détecter le type du logo.';
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => null,
    };

    if ($ext === null) {
        return 'Formats acceptés : JPG, PNG, WebP ou GIF.';
    }

    $dir = client_logo_abs_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return 'Impossible de créer le dossier d’enregistrement des logos.';
    }

    client_logo_delete_files_for_client($clientId);

    $relative = 'uploads/client_logos/' . $clientId . '.' . $ext;
    $dest = dirname(__DIR__) . '/' . $relative;

    if (!move_uploaded_file($tmp, $dest)) {
        return 'Échec de l’enregistrement du fichier.';
    }

    $stmt = $pdo->prepare('UPDATE clients SET logo_path = :path WHERE id = :id LIMIT 1');
    $stmt->execute(['path' => $relative, 'id' => $clientId]);

    return null;
}

function client_logo_clear(PDO $pdo, int $clientId): void
{
    client_logo_delete_files_for_client($clientId);
    $stmt = $pdo->prepare('UPDATE clients SET logo_path = NULL WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
}
