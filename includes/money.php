<?php

declare(strict_types=1);

/**
 * Interprète une saisie prix (virgule ou point, espaces).
 * Retourne un montant ≥ 0 plafonné (12 digits avant virgule).
 */
function parse_money_amount(mixed $raw): float
{
    if ($raw === null) {
        return 0.0;
    }
    $s = trim((string) $raw);
    if ($s === '') {
        return 0.0;
    }
    $s = str_replace(["\xc2\xa0", ' '], '', $s);
    $s = str_replace(',', '.', $s);
    if ($s === '' || !is_numeric($s)) {
        return 0.0;
    }
    $v = round((float) $s, 2);

    return max(0.0, min(9999999999.99, $v));
}

function normalize_billing_currency(string $currency): string
{
    return strtoupper(trim($currency)) === 'XOF' ? 'XOF' : 'EUR';
}

function billing_currency_label(string $currency): string
{
    return normalize_billing_currency($currency) === 'XOF'
        ? 'Franc CFA (XOF)'
        : 'Euro (EUR)';
}

function format_money_eur(float $amount): string
{
    return number_format($amount, 2, ',', ' ') . ' €';
}

/** Franc CFA (BCEAO / XOF) : montants entiers courants. */
function format_money_cfa(float $amount): string
{
    $n = (int) round($amount);

    return number_format($n, 0, ',', ' ') . ' F CFA';
}

function format_money_display(float $amount, string $currency): string
{
    return normalize_billing_currency($currency) === 'XOF'
        ? format_money_cfa($amount)
        : format_money_eur($amount);
}

/** Valeur pour champ texte de saisie (vide si 0). */
function format_money_for_input(float $amount, string $currency = 'EUR'): string
{
    if ($amount <= 0) {
        return '';
    }

    if (normalize_billing_currency($currency) === 'XOF') {
        return number_format((int) round($amount), 0, ',', '');
    }

    return number_format($amount, 2, ',', '');
}
