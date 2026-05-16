<?php

declare(strict_types=1);

/** @return array<string, string> slug => label FR */
function support_ticket_categories(): array
{
    return [
        'correction' => 'Correction / erreur sur le site',
        'update' => 'Mise à jour ou évolution',
        'help' => 'Aide sur un projet ou une fonctionnalité',
        'other' => 'Autre demande',
    ];
}

/**
 * Métadonnées étendues pour les cartes de catégorie (icône SVG + description courte).
 *
 * @return array<string, array{label: string, desc: string, icon: string}>
 */
function support_ticket_categories_meta(): array
{
    return [
        'correction' => [
            'label' => 'Correction / erreur',
            'desc'  => 'Un bug, un affichage cassé, une page en panne…',
            'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9.5 14.5 5-5"/><path d="m12 2 1.5 3.5L17 7l-3.5 1.5L12 12l-1.5-3.5L7 7l3.5-1.5L12 2Z"/><path d="M5 16l1 2 2 1-2 1-1 2-1-2-2-1 2-1 1-2Z"/><path d="M18 17l.7 1.4L20 19l-1.3.6L18 21l-.7-1.4L16 19l1.3-.6.7-1.4Z"/></svg>',
        ],
        'update' => [
            'label' => 'Mise à jour / évolution',
            'desc'  => 'Ajout, modification ou amélioration d’une partie du site.',
            'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>',
        ],
        'help' => [
            'label' => 'Aide / accompagnement',
            'desc'  => 'Question, prise en main, aide sur une fonctionnalité.',
            'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        ],
        'other' => [
            'label' => 'Autre demande',
            'desc'  => 'Vous ne savez pas où classer ? On répond quand même.',
            'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>',
        ],
    ];
}

function support_ticket_category_label(string $slug): string
{
    return support_ticket_categories()[$slug] ?? $slug;
}

/** @return array<string, string> slug => label FR */
function support_ticket_priorities(): array
{
    return [
        'low'      => 'Faible',
        'normal'   => 'Normale',
        'high'     => 'Haute',
        'critical' => 'Critique',
    ];
}

/**
 * Métadonnées priorité : libellé + couleur + description.
 *
 * @return array<string, array{label: string, desc: string, color: string}>
 */
function support_ticket_priorities_meta(): array
{
    return [
        'low' => [
            'label' => 'Faible',
            'desc'  => 'Sans impact immédiat',
            'color' => '#64748b',
        ],
        'normal' => [
            'label' => 'Normale',
            'desc'  => 'Réponse sous quelques jours',
            'color' => '#16a34a',
        ],
        'high' => [
            'label' => 'Haute',
            'desc'  => 'Gêne fortement l’usage',
            'color' => '#f59e0b',
        ],
        'critical' => [
            'label' => 'Critique',
            'desc'  => 'Site indisponible / urgent',
            'color' => '#dc2626',
        ],
    ];
}

function support_ticket_priority_label(string $slug): string
{
    return support_ticket_priorities()[$slug] ?? $slug;
}

function support_ticket_priority_color(string $slug): string
{
    return support_ticket_priorities_meta()[$slug]['color'] ?? '#64748b';
}

/** Décode les pièces jointes stockées en JSON (chemins relatifs au document root). */
function support_ticket_decode_attachments(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }
    return array_values(array_filter(
        $decoded,
        static fn($v) => is_string($v) && $v !== ''
    ));
}
