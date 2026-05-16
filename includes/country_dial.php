<?php

declare(strict_types=1);

/**
 * Liste complète des pays (triée par nom français) avec indicatif téléphonique E.164.
 * @return list<array{iso: string, dial: string, name: string}>
 */
function country_dial_list(): array
{
    return [
        ['iso' => 'AF', 'dial' => '93',  'name' => 'Afghanistan'],
        ['iso' => 'ZA', 'dial' => '27',  'name' => 'Afrique du Sud'],
        ['iso' => 'AL', 'dial' => '355', 'name' => 'Albanie'],
        ['iso' => 'DZ', 'dial' => '213', 'name' => 'Algérie'],
        ['iso' => 'DE', 'dial' => '49',  'name' => 'Allemagne'],
        ['iso' => 'AD', 'dial' => '376', 'name' => 'Andorre'],
        ['iso' => 'AO', 'dial' => '244', 'name' => 'Angola'],
        ['iso' => 'AI', 'dial' => '1',   'name' => 'Anguilla'],
        ['iso' => 'AG', 'dial' => '1',   'name' => 'Antigua-et-Barbuda'],
        ['iso' => 'SA', 'dial' => '966', 'name' => 'Arabie saoudite'],
        ['iso' => 'AR', 'dial' => '54',  'name' => 'Argentine'],
        ['iso' => 'AM', 'dial' => '374', 'name' => 'Arménie'],
        ['iso' => 'AW', 'dial' => '297', 'name' => 'Aruba'],
        ['iso' => 'AU', 'dial' => '61',  'name' => 'Australie'],
        ['iso' => 'AT', 'dial' => '43',  'name' => 'Autriche'],
        ['iso' => 'AZ', 'dial' => '994', 'name' => 'Azerbaïdjan'],
        ['iso' => 'BS', 'dial' => '1',   'name' => 'Bahamas'],
        ['iso' => 'BH', 'dial' => '973', 'name' => 'Bahreïn'],
        ['iso' => 'BD', 'dial' => '880', 'name' => 'Bangladesh'],
        ['iso' => 'BB', 'dial' => '1',   'name' => 'Barbade'],
        ['iso' => 'BE', 'dial' => '32',  'name' => 'Belgique'],
        ['iso' => 'BZ', 'dial' => '501', 'name' => 'Belize'],
        ['iso' => 'BJ', 'dial' => '229', 'name' => 'Bénin'],
        ['iso' => 'BM', 'dial' => '1',   'name' => 'Bermudes'],
        ['iso' => 'BT', 'dial' => '975', 'name' => 'Bhoutan'],
        ['iso' => 'BY', 'dial' => '375', 'name' => 'Biélorussie'],
        ['iso' => 'MM', 'dial' => '95',  'name' => 'Birmanie'],
        ['iso' => 'BO', 'dial' => '591', 'name' => 'Bolivie'],
        ['iso' => 'BA', 'dial' => '387', 'name' => 'Bosnie-Herzégovine'],
        ['iso' => 'BW', 'dial' => '267', 'name' => 'Botswana'],
        ['iso' => 'BR', 'dial' => '55',  'name' => 'Brésil'],
        ['iso' => 'BN', 'dial' => '673', 'name' => 'Brunei'],
        ['iso' => 'BG', 'dial' => '359', 'name' => 'Bulgarie'],
        ['iso' => 'BF', 'dial' => '226', 'name' => 'Burkina Faso'],
        ['iso' => 'BI', 'dial' => '257', 'name' => 'Burundi'],
        ['iso' => 'KH', 'dial' => '855', 'name' => 'Cambodge'],
        ['iso' => 'CM', 'dial' => '237', 'name' => 'Cameroun'],
        ['iso' => 'CA', 'dial' => '1',   'name' => 'Canada'],
        ['iso' => 'CV', 'dial' => '238', 'name' => 'Cap-Vert'],
        ['iso' => 'CL', 'dial' => '56',  'name' => 'Chili'],
        ['iso' => 'CN', 'dial' => '86',  'name' => 'Chine'],
        ['iso' => 'CY', 'dial' => '357', 'name' => 'Chypre'],
        ['iso' => 'CO', 'dial' => '57',  'name' => 'Colombie'],
        ['iso' => 'KM', 'dial' => '269', 'name' => 'Comores'],
        ['iso' => 'CG', 'dial' => '242', 'name' => 'Congo'],
        ['iso' => 'KP', 'dial' => '850', 'name' => 'Corée du Nord'],
        ['iso' => 'KR', 'dial' => '82',  'name' => 'Corée du Sud'],
        ['iso' => 'CR', 'dial' => '506', 'name' => 'Costa Rica'],
        ['iso' => 'CI', 'dial' => '225', 'name' => "Côte d'Ivoire"],
        ['iso' => 'HR', 'dial' => '385', 'name' => 'Croatie'],
        ['iso' => 'CU', 'dial' => '53',  'name' => 'Cuba'],
        ['iso' => 'CW', 'dial' => '599', 'name' => 'Curaçao'],
        ['iso' => 'DK', 'dial' => '45',  'name' => 'Danemark'],
        ['iso' => 'DJ', 'dial' => '253', 'name' => 'Djibouti'],
        ['iso' => 'DM', 'dial' => '1',   'name' => 'Dominique'],
        ['iso' => 'EG', 'dial' => '20',  'name' => 'Égypte'],
        ['iso' => 'AE', 'dial' => '971', 'name' => 'Émirats arabes unis'],
        ['iso' => 'EC', 'dial' => '593', 'name' => 'Équateur'],
        ['iso' => 'ER', 'dial' => '291', 'name' => 'Érythrée'],
        ['iso' => 'ES', 'dial' => '34',  'name' => 'Espagne'],
        ['iso' => 'EE', 'dial' => '372', 'name' => 'Estonie'],
        ['iso' => 'SZ', 'dial' => '268', 'name' => 'Eswatini'],
        ['iso' => 'US', 'dial' => '1',   'name' => 'États-Unis'],
        ['iso' => 'ET', 'dial' => '251', 'name' => 'Éthiopie'],
        ['iso' => 'FJ', 'dial' => '679', 'name' => 'Fidji'],
        ['iso' => 'FI', 'dial' => '358', 'name' => 'Finlande'],
        ['iso' => 'FR', 'dial' => '33',  'name' => 'France'],
        ['iso' => 'GA', 'dial' => '241', 'name' => 'Gabon'],
        ['iso' => 'GM', 'dial' => '220', 'name' => 'Gambie'],
        ['iso' => 'GE', 'dial' => '995', 'name' => 'Géorgie'],
        ['iso' => 'GH', 'dial' => '233', 'name' => 'Ghana'],
        ['iso' => 'GI', 'dial' => '350', 'name' => 'Gibraltar'],
        ['iso' => 'GR', 'dial' => '30',  'name' => 'Grèce'],
        ['iso' => 'GD', 'dial' => '1',   'name' => 'Grenade'],
        ['iso' => 'GL', 'dial' => '299', 'name' => 'Groenland'],
        ['iso' => 'GP', 'dial' => '590', 'name' => 'Guadeloupe'],
        ['iso' => 'GU', 'dial' => '1',   'name' => 'Guam'],
        ['iso' => 'GT', 'dial' => '502', 'name' => 'Guatemala'],
        ['iso' => 'GG', 'dial' => '44',  'name' => 'Guernesey'],
        ['iso' => 'GN', 'dial' => '224', 'name' => 'Guinée'],
        ['iso' => 'GQ', 'dial' => '240', 'name' => 'Guinée équatoriale'],
        ['iso' => 'GW', 'dial' => '245', 'name' => 'Guinée-Bissau'],
        ['iso' => 'GY', 'dial' => '592', 'name' => 'Guyana'],
        ['iso' => 'GF', 'dial' => '594', 'name' => 'Guyane française'],
        ['iso' => 'HT', 'dial' => '509', 'name' => 'Haïti'],
        ['iso' => 'HN', 'dial' => '504', 'name' => 'Honduras'],
        ['iso' => 'HK', 'dial' => '852', 'name' => 'Hong Kong'],
        ['iso' => 'HU', 'dial' => '36',  'name' => 'Hongrie'],
        ['iso' => 'IM', 'dial' => '44',  'name' => 'Île de Man'],
        ['iso' => 'KY', 'dial' => '1',   'name' => 'Îles Caïmans'],
        ['iso' => 'CK', 'dial' => '682', 'name' => 'Îles Cook'],
        ['iso' => 'FK', 'dial' => '500', 'name' => 'Îles Falkland'],
        ['iso' => 'FO', 'dial' => '298', 'name' => 'Îles Féroé'],
        ['iso' => 'MH', 'dial' => '692', 'name' => 'Îles Marshall'],
        ['iso' => 'SB', 'dial' => '677', 'name' => 'Îles Salomon'],
        ['iso' => 'TC', 'dial' => '1',   'name' => 'Îles Turques-et-Caïques'],
        ['iso' => 'VG', 'dial' => '1',   'name' => 'Îles Vierges britanniques'],
        ['iso' => 'IN', 'dial' => '91',  'name' => 'Inde'],
        ['iso' => 'ID', 'dial' => '62',  'name' => 'Indonésie'],
        ['iso' => 'IQ', 'dial' => '964', 'name' => 'Irak'],
        ['iso' => 'IR', 'dial' => '98',  'name' => 'Iran'],
        ['iso' => 'IE', 'dial' => '353', 'name' => 'Irlande'],
        ['iso' => 'IS', 'dial' => '354', 'name' => 'Islande'],
        ['iso' => 'IL', 'dial' => '972', 'name' => 'Israël'],
        ['iso' => 'IT', 'dial' => '39',  'name' => 'Italie'],
        ['iso' => 'JM', 'dial' => '1',   'name' => 'Jamaïque'],
        ['iso' => 'JP', 'dial' => '81',  'name' => 'Japon'],
        ['iso' => 'JE', 'dial' => '44',  'name' => 'Jersey'],
        ['iso' => 'JO', 'dial' => '962', 'name' => 'Jordanie'],
        ['iso' => 'KZ', 'dial' => '7',   'name' => 'Kazakhstan'],
        ['iso' => 'KE', 'dial' => '254', 'name' => 'Kenya'],
        ['iso' => 'KG', 'dial' => '996', 'name' => 'Kirghizistan'],
        ['iso' => 'KI', 'dial' => '686', 'name' => 'Kiribati'],
        ['iso' => 'KW', 'dial' => '965', 'name' => 'Koweït'],
        ['iso' => 'RE', 'dial' => '262', 'name' => 'La Réunion'],
        ['iso' => 'LA', 'dial' => '856', 'name' => 'Laos'],
        ['iso' => 'LS', 'dial' => '266', 'name' => 'Lesotho'],
        ['iso' => 'LV', 'dial' => '371', 'name' => 'Lettonie'],
        ['iso' => 'LB', 'dial' => '961', 'name' => 'Liban'],
        ['iso' => 'LR', 'dial' => '231', 'name' => 'Libéria'],
        ['iso' => 'LY', 'dial' => '218', 'name' => 'Libye'],
        ['iso' => 'LI', 'dial' => '423', 'name' => 'Liechtenstein'],
        ['iso' => 'LT', 'dial' => '370', 'name' => 'Lituanie'],
        ['iso' => 'LU', 'dial' => '352', 'name' => 'Luxembourg'],
        ['iso' => 'MO', 'dial' => '853', 'name' => 'Macao'],
        ['iso' => 'MK', 'dial' => '389', 'name' => 'Macédoine du Nord'],
        ['iso' => 'MG', 'dial' => '261', 'name' => 'Madagascar'],
        ['iso' => 'MY', 'dial' => '60',  'name' => 'Malaisie'],
        ['iso' => 'MW', 'dial' => '265', 'name' => 'Malawi'],
        ['iso' => 'MV', 'dial' => '960', 'name' => 'Maldives'],
        ['iso' => 'ML', 'dial' => '223', 'name' => 'Mali'],
        ['iso' => 'MT', 'dial' => '356', 'name' => 'Malte'],
        ['iso' => 'MA', 'dial' => '212', 'name' => 'Maroc'],
        ['iso' => 'MQ', 'dial' => '596', 'name' => 'Martinique'],
        ['iso' => 'MU', 'dial' => '230', 'name' => 'Maurice'],
        ['iso' => 'MR', 'dial' => '222', 'name' => 'Mauritanie'],
        ['iso' => 'YT', 'dial' => '262', 'name' => 'Mayotte'],
        ['iso' => 'MX', 'dial' => '52',  'name' => 'Mexique'],
        ['iso' => 'FM', 'dial' => '691', 'name' => 'Micronésie'],
        ['iso' => 'MD', 'dial' => '373', 'name' => 'Moldavie'],
        ['iso' => 'MC', 'dial' => '377', 'name' => 'Monaco'],
        ['iso' => 'MN', 'dial' => '976', 'name' => 'Mongolie'],
        ['iso' => 'ME', 'dial' => '382', 'name' => 'Monténégro'],
        ['iso' => 'MS', 'dial' => '1',   'name' => 'Montserrat'],
        ['iso' => 'MZ', 'dial' => '258', 'name' => 'Mozambique'],
        ['iso' => 'NA', 'dial' => '264', 'name' => 'Namibie'],
        ['iso' => 'NR', 'dial' => '674', 'name' => 'Nauru'],
        ['iso' => 'NP', 'dial' => '977', 'name' => 'Népal'],
        ['iso' => 'NI', 'dial' => '505', 'name' => 'Nicaragua'],
        ['iso' => 'NE', 'dial' => '227', 'name' => 'Niger'],
        ['iso' => 'NG', 'dial' => '234', 'name' => 'Nigeria'],
        ['iso' => 'NU', 'dial' => '683', 'name' => 'Niue'],
        ['iso' => 'NO', 'dial' => '47',  'name' => 'Norvège'],
        ['iso' => 'NC', 'dial' => '687', 'name' => 'Nouvelle-Calédonie'],
        ['iso' => 'NZ', 'dial' => '64',  'name' => 'Nouvelle-Zélande'],
        ['iso' => 'OM', 'dial' => '968', 'name' => 'Oman'],
        ['iso' => 'UG', 'dial' => '256', 'name' => 'Ouganda'],
        ['iso' => 'UZ', 'dial' => '998', 'name' => 'Ouzbékistan'],
        ['iso' => 'PK', 'dial' => '92',  'name' => 'Pakistan'],
        ['iso' => 'PW', 'dial' => '680', 'name' => 'Palaos'],
        ['iso' => 'PS', 'dial' => '970', 'name' => 'Palestine'],
        ['iso' => 'PA', 'dial' => '507', 'name' => 'Panama'],
        ['iso' => 'PG', 'dial' => '675', 'name' => 'Papouasie-Nouvelle-Guinée'],
        ['iso' => 'PY', 'dial' => '595', 'name' => 'Paraguay'],
        ['iso' => 'NL', 'dial' => '31',  'name' => 'Pays-Bas'],
        ['iso' => 'PE', 'dial' => '51',  'name' => 'Pérou'],
        ['iso' => 'PH', 'dial' => '63',  'name' => 'Philippines'],
        ['iso' => 'PL', 'dial' => '48',  'name' => 'Pologne'],
        ['iso' => 'PF', 'dial' => '689', 'name' => 'Polynésie française'],
        ['iso' => 'PR', 'dial' => '1',   'name' => 'Porto Rico'],
        ['iso' => 'PT', 'dial' => '351', 'name' => 'Portugal'],
        ['iso' => 'QA', 'dial' => '974', 'name' => 'Qatar'],
        ['iso' => 'CF', 'dial' => '236', 'name' => 'République centrafricaine'],
        ['iso' => 'CD', 'dial' => '243', 'name' => 'République démocratique du Congo'],
        ['iso' => 'DO', 'dial' => '1',   'name' => 'République dominicaine'],
        ['iso' => 'CZ', 'dial' => '420', 'name' => 'République tchèque'],
        ['iso' => 'RO', 'dial' => '40',  'name' => 'Roumanie'],
        ['iso' => 'GB', 'dial' => '44',  'name' => 'Royaume-Uni'],
        ['iso' => 'RU', 'dial' => '7',   'name' => 'Russie'],
        ['iso' => 'RW', 'dial' => '250', 'name' => 'Rwanda'],
        ['iso' => 'EH', 'dial' => '212', 'name' => 'Sahara occidental'],
        ['iso' => 'BL', 'dial' => '590', 'name' => 'Saint-Barthélemy'],
        ['iso' => 'KN', 'dial' => '1',   'name' => 'Saint-Kitts-et-Nevis'],
        ['iso' => 'SM', 'dial' => '378', 'name' => 'Saint-Marin'],
        ['iso' => 'MF', 'dial' => '590', 'name' => 'Saint-Martin'],
        ['iso' => 'PM', 'dial' => '508', 'name' => 'Saint-Pierre-et-Miquelon'],
        ['iso' => 'VC', 'dial' => '1',   'name' => 'Saint-Vincent-et-les-Grenadines'],
        ['iso' => 'SH', 'dial' => '290', 'name' => 'Sainte-Hélène'],
        ['iso' => 'LC', 'dial' => '1',   'name' => 'Sainte-Lucie'],
        ['iso' => 'SV', 'dial' => '503', 'name' => 'Salvador'],
        ['iso' => 'WS', 'dial' => '685', 'name' => 'Samoa'],
        ['iso' => 'AS', 'dial' => '1',   'name' => 'Samoa américaines'],
        ['iso' => 'ST', 'dial' => '239', 'name' => 'Sao Tomé-et-Principe'],
        ['iso' => 'SN', 'dial' => '221', 'name' => 'Sénégal'],
        ['iso' => 'RS', 'dial' => '381', 'name' => 'Serbie'],
        ['iso' => 'SC', 'dial' => '248', 'name' => 'Seychelles'],
        ['iso' => 'SL', 'dial' => '232', 'name' => 'Sierra Leone'],
        ['iso' => 'SG', 'dial' => '65',  'name' => 'Singapour'],
        ['iso' => 'SK', 'dial' => '421', 'name' => 'Slovaquie'],
        ['iso' => 'SI', 'dial' => '386', 'name' => 'Slovénie'],
        ['iso' => 'SO', 'dial' => '252', 'name' => 'Somalie'],
        ['iso' => 'SD', 'dial' => '249', 'name' => 'Soudan'],
        ['iso' => 'SS', 'dial' => '211', 'name' => 'Soudan du Sud'],
        ['iso' => 'LK', 'dial' => '94',  'name' => 'Sri Lanka'],
        ['iso' => 'SE', 'dial' => '46',  'name' => 'Suède'],
        ['iso' => 'CH', 'dial' => '41',  'name' => 'Suisse'],
        ['iso' => 'SR', 'dial' => '597', 'name' => 'Suriname'],
        ['iso' => 'SY', 'dial' => '963', 'name' => 'Syrie'],
        ['iso' => 'TJ', 'dial' => '992', 'name' => 'Tadjikistan'],
        ['iso' => 'TW', 'dial' => '886', 'name' => 'Taïwan'],
        ['iso' => 'TZ', 'dial' => '255', 'name' => 'Tanzanie'],
        ['iso' => 'TD', 'dial' => '235', 'name' => 'Tchad'],
        ['iso' => 'TH', 'dial' => '66',  'name' => 'Thaïlande'],
        ['iso' => 'TL', 'dial' => '670', 'name' => 'Timor oriental'],
        ['iso' => 'TG', 'dial' => '228', 'name' => 'Togo'],
        ['iso' => 'TO', 'dial' => '676', 'name' => 'Tonga'],
        ['iso' => 'TT', 'dial' => '1',   'name' => 'Trinité-et-Tobago'],
        ['iso' => 'TN', 'dial' => '216', 'name' => 'Tunisie'],
        ['iso' => 'TM', 'dial' => '993', 'name' => 'Turkménistan'],
        ['iso' => 'TR', 'dial' => '90',  'name' => 'Turquie'],
        ['iso' => 'TV', 'dial' => '688', 'name' => 'Tuvalu'],
        ['iso' => 'UA', 'dial' => '380', 'name' => 'Ukraine'],
        ['iso' => 'UY', 'dial' => '598', 'name' => 'Uruguay'],
        ['iso' => 'VU', 'dial' => '678', 'name' => 'Vanuatu'],
        ['iso' => 'VA', 'dial' => '39',  'name' => 'Vatican'],
        ['iso' => 'VE', 'dial' => '58',  'name' => 'Venezuela'],
        ['iso' => 'VN', 'dial' => '84',  'name' => 'Vietnam'],
        ['iso' => 'WF', 'dial' => '681', 'name' => 'Wallis-et-Futuna'],
        ['iso' => 'YE', 'dial' => '967', 'name' => 'Yémen'],
        ['iso' => 'ZM', 'dial' => '260', 'name' => 'Zambie'],
        ['iso' => 'ZW', 'dial' => '263', 'name' => 'Zimbabwe'],
    ];
}

/** Drapeau emoji depuis le code ISO 2 lettres. */
function country_iso_flag(string $iso): string
{
    $iso = strtoupper(trim($iso));
    if (strlen($iso) !== 2 || !ctype_alpha($iso)) {
        return '🌐';
    }

    return mb_chr(0x1F1E6 + (ord($iso[0]) - 65), 'UTF-8')
        . mb_chr(0x1F1E6 + (ord($iso[1]) - 65), 'UTF-8');
}

/** Liste des indicatifs valides (uniques). */
function country_allowed_dials(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = array_values(array_unique(array_map(
            static fn($c) => $c['dial'],
            country_dial_list()
        )));
    }
    return $cache;
}

/**
 * À partir d'un numéro stocké en base, devine l'indicatif et la partie nationale.
 * Retourne ['dial' => ?, 'local' => ?] — au pire ['dial' => null, 'local' => $raw].
 *
 * @return array{dial: string|null, local: string}
 */
function country_split_phone(?string $raw): array
{
    $s = trim((string) ($raw ?? ''));
    if ($s === '') {
        return ['dial' => null, 'local' => ''];
    }

    $hasPlus = strpos($s, '+') !== false;
    $digits = preg_replace('/\D+/', '', $s);
    if ($digits === '') {
        return ['dial' => null, 'local' => $s];
    }

    if ($hasPlus) {
        $dials = country_allowed_dials();
        usort($dials, static fn($a, $b) => strlen($b) - strlen($a));
        foreach ($dials as $d) {
            if (str_starts_with($digits, $d)) {
                return ['dial' => $d, 'local' => substr($digits, strlen($d))];
            }
        }
    }

    return ['dial' => null, 'local' => $s];
}

/**
 * Variantes "chiffres uniquement" pour comparer à un numéro stocké, peu importe le format.
 * @return list<string>
 */
function country_phone_candidate_digits(string $ccDigits, string $localRaw): array
{
    $cc = preg_replace('/\D+/', '', $ccDigits);
    $loc = preg_replace('/\D+/', '', $localRaw);
    if ($cc === '' || $loc === '') {
        return [];
    }
    $national = $loc;
    if (strlen($national) >= 2 && $national[0] === '0') {
        $national = substr($national, 1);
    }
    return array_values(array_unique(array_filter([
        $cc . $national,
        $cc . $loc,
        $loc,
        $national,
        '0' . $national,
    ], static fn($v) => $v !== '')));
}

function country_phone_matches(?string $stored, string $ccDigits, string $localRaw): bool
{
    $storedDigits = preg_replace('/\D+/', '', (string) ($stored ?? ''));
    if ($storedDigits === '') {
        return false;
    }
    foreach (country_phone_candidate_digits($ccDigits, $localRaw) as $candidate) {
        if (hash_equals($storedDigits, $candidate)) {
            return true;
        }
    }
    return false;
}

/**
 * Rendu HTML d'un sélecteur d'indicatif (drapeau + nom + indicatif).
 */
function render_country_dial_select(string $name, ?string $selectedDial = '221', array $attrs = []): string
{
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= ' ' . htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8')
            . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
    }
    $html = '<select name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"' . $attrStr . '>';
    $rendered = false;
    foreach (country_dial_list() as $c) {
        $flag = country_iso_flag($c['iso']);
        $label = $flag . ' ' . $c['name'] . ' (+' . $c['dial'] . ')';
        $isSel = !$rendered && $selectedDial !== null && $selectedDial !== '' && $c['dial'] === $selectedDial;
        if ($isSel) {
            $rendered = true;
        }
        $html .= '<option value="' . htmlspecialchars($c['dial'], ENT_QUOTES, 'UTF-8') . '"'
            . ' data-iso="' . htmlspecialchars($c['iso'], ENT_QUOTES, 'UTF-8') . '"'
            . ($isSel ? ' selected' : '')
            . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $html .= '</select>';
    return $html;
}
