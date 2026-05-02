<?php
declare(strict_types=1);

/**
 * Katalog nabízených služeb pro OZ.
 *
 * Strukturovaný seznam tarifů + modemů — zdrojem je tento soubor.
 * Při změně produktové nabídky se upravuje JEN tento soubor.
 *
 * Funkce:
 *   crm_offered_services_catalog()  → array kategorií + skupin + tarifů
 *   crm_offered_services_modems()   → array modemů
 *   crm_offered_services_flat($t)   → flat seznam tarifů pro daný typ
 *   crm_offered_services_label($t)  → uživatelský label pro typ (📱 Mobil…)
 *   crm_offered_services_types()    → ['mobil','internet','tv','data']
 */

/** @return array<string, array{label:string, groups: array<string, list<string>>}> */
function crm_offered_services_catalog(): array
{
    return [
        // ──────────────────────────────────────────────────────────────────
        'mobil' => [
            'label'  => '📱 Mobil',
            'groups' => [
                'SMART' => [
                    'Business Smart Start',
                    'Business Smart Standard',
                    'Business Smart Medium',
                    'Business Mini',
                    'Start 130 minut',
                    'Start 250 minut',
                ],
                'VOLUME' => [
                    'Business Start',
                    'Business Standard',
                    'Business Medium',
                    'Business Maxi',
                    'Red Naplno člen',
                ],
                'SPEED' => [
                    'Business Neomezený Start',
                    'Business Neomezený Standard',
                    'Business Neomezený Medium',
                    'Business Neomezený Maxi 5G',
                    'Business Neomezený Extra 5G OneNumber',
                    'Business Neomezený Extra 5G Stream',
                    'Business Neomezený Elite 5G',
                ],
                'NEOMEZENÝ' => [
                    'Neomezený Business Basic',
                    'Neomezený Business Basic+',
                    'Neomezený Business Premium 5G',
                    'Neomezený Business Super',
                    'Neomezený Business Super+',
                ],
            ],
        ],

        // ──────────────────────────────────────────────────────────────────
        'internet' => [
            'label'  => '🌐 Pevný internet',
            'groups' => [
                'Fibre Cetin' => [
                    'Internet Fibre 100 (100/100 Mbps)',
                    'Internet Fibre 250 (250/250 Mbps)',
                    'Internet Fibre 500 (500/500 Mbps)',
                    'Internet Fibre 1000 (1000/1000 Mbps)',
                    'Internet Fibre 2000 (2000/1000 Mbps)',
                ],
                'DSL Cetin' => [
                    'Internet DSL 8 (8/0,5 Mbps)',
                    'Internet DSL 20 (20/2 Mbps)',
                    'Internet DSL 50 (50/5 Mbps)',
                    'Internet DSL 100 (100/20 Mbps)',
                    'Internet DSL 250 (250/50 Mbps)',
                ],
                'Internet+ (Vodafone)' => [
                    'Internet Start+ (100/100 Mbps)',
                    'Internet Basic+ (250/100 Mbps)',
                    'Internet Super+ (500/200 Mbps)',
                    'Internet Premium+ (1000/200 Mbps)',
                    'Internet Ultra+ (2000/400 Mbps)',
                    'Internet Basic+ Profi (400/200 Mbps)',
                    'Internet Premium+ Profi (1000/300 Mbps)',
                    'Internet Ultra+ Profi (2000/400 Mbps)',
                    'Internet Start+ Profi (100/100 Mbps)',
                ],
                'Internet (Vodafone)' => [
                    'Internet Start (100/10 Mbps)',
                    'Internet Basic (250/25 Mbps)',
                    'Internet Super (500/50 Mbps)',
                    'Internet Premium (no upgrade) (1000/60 Mbps)',
                    'Internet Premium (upgrade) (1000/100 Mbps)',
                    'Internet Premium Fibre (1000/1000 Mbps)',
                    'Internet Super Fibre (500/500 Mbps)',
                    'Internet Ultra Fibre (2000/1000 Mbps)',
                    'Internet Ultra+ Fibre (2000/2000 Mbps)',
                    'Internet Basic Fibre (250/250 Mbps)',
                    'Internet Start Fibre (100/20 Mbps)',
                ],
                'Internet Profi (Vodafone)' => [
                    'Internet Start Profi (100/50 Mbps)',
                    'Internet Basic Profi (400/50 Mbps)',
                    'Internet Basic Profi (400/60 Mbps)',
                    'Internet Super Profi no upgrade (800/60 Mbps)',
                    'Internet Super Profi upgrade (800/100 Mbps)',
                    'Internet Premium Profi no upgrade (1000/60 Mbps)',
                    'Internet Premium Profi upgrade (1000/100 Mbps)',
                ],
                'Kabel (UPC)' => [
                    'Pevný internet Kabel 200 Mbps Profi',
                    'Pevný internet Kabel 300 Mbps',
                    'Pevný internet Kabel 350 Mbps Profi',
                    'Pevný internet Kabel 500 Mbps',
                    'Pevný internet Kabel 500 Mbps Profi',
                    'Pevný internet Kabel 1 Gbps (1000/50 Mbps)',
                ],
                'WTTx (5G)' => [
                    'Pevný internet 5G Profi 50 (50/5 Mbps)',
                    'Pevný internet 5G Profi 100 (100/10 Mbps)',
                    'Pevný internet 5G Profi 150 (150/15 Mbps)',
                    'Pevný internet 30/5 Mbps WTTX',
                    'Pevný internet 50/5 Mbps WTTX',
                    'Pevný internet 10/2 Mbps WTTX',
                    'Pevný internet 20/10 Mbps WTTX',
                    'Pevný internet 10/10 Mbps WTTX',
                    'Pevný internet 4/4 Mbps WTTX',
                ],
                'LTE (4G)' => [
                    'Pevný internet LTE Profi 10 (10/2 Mbps)',
                    'Pevný internet LTE Profi 30 (30/5 Mbps)',
                ],
                'ADSL Cetin (legacy)' => [
                    'Pevný internet 20 Mbps ADSL',
                    'Pevný internet 50 Mbps ADSL',
                    'Pevný internet 100 Mbps ADSL',
                    'Pevný internet 250 Mbps ADSL',
                ],
                'Bez kabelu (Naplno)' => [
                    'Připojení bez kabelu Naplno (20/4 Mbps)',
                    'Připojení bez kabelu Naplno 5G (150/30 Mbps)',
                ],
            ],
        ],

        // ──────────────────────────────────────────────────────────────────
        'tv' => [
            'label'  => '📺 TV',
            'groups' => [
                'Vodafone TV' => [
                    'Vodafone TV Lite',
                    'Vodafone TV',
                    'Vodafone TV+',
                    'Vodafone TV+ 2.0',
                ],
            ],
        ],

        // ──────────────────────────────────────────────────────────────────
        'data' => [
            'label'  => '📡 Datové tarify',
            'groups' => [
                'Business data' => [
                    'Business data Start (7 GB)',
                    'Business data Standard (15 GB)',
                    'Business data Neomezeně Medium',
                    'Business data Neomezeně Maxi 5G',
                    'Business Data Neomezeně člen',
                ],
                'Mobilní připojení' => [
                    'Mobilní připojení 500 MB',
                    'Mobilní připojení 1,5 GB',
                    'Mobilní připojení 5 GB',
                    'Mobilní připojení 10 GB',
                    'Mobilní připojení 20 GB',
                    'Mobilní připojení 30 GB',
                    'Mobilní připojení 40 GB',
                    'Mobilní připojení 50 GB',
                ],
                'IoT' => [
                    'Business IoT Start (5 MB)',
                    'Business IoT Standard (150 MB)',
                    'Business IoT Medium (500 MB)',
                    'Business IoT Maxi (3 GB)',
                ],
            ],
        ],
    ];
}

/**
 * Katalog modemů / routerů.
 *
 * Placeholder — bude rozšířen, až dorazí přesný seznam HW.
 *
 * @return list<string>
 */
function crm_offered_services_modems(): array
{
    return [
        'Vodafone Station Fibre + Router Zyxel Wi-Fi 7',
        'Router Zyxel Wi-Fi 7',
        'Optický převodník',
        // — vlastní zařízení zákazníka — (volba bez modelu)
        'Vlastní zařízení zákazníka',
    ];
}

/** @return list<string>  ['mobil','internet','tv','data'] */
function crm_offered_services_types(): array
{
    return array_keys(crm_offered_services_catalog());
}

/** Vrátí uživatelský label (vč. emoji) pro daný typ. */
function crm_offered_services_label(string $type): string
{
    $cat = crm_offered_services_catalog();
    return $cat[$type]['label'] ?? $type;
}

/**
 * Vrátí flat seznam všech tarifů pro daný typ (napříč skupinami).
 *
 * @return list<string>
 */
function crm_offered_services_flat(string $type): array
{
    $cat = crm_offered_services_catalog();
    if (!isset($cat[$type])) {
        return [];
    }
    $out = [];
    foreach ($cat[$type]['groups'] as $items) {
        foreach ($items as $item) {
            $out[] = $item;
        }
    }
    return $out;
}

/**
 * Ověří, že daný (type, label) je v katalogu.
 * Slouží jako whitelist před uložením do DB.
 */
function crm_offered_services_is_valid(string $type, string $label): bool
{
    return in_array($label, crm_offered_services_flat($type), true);
}

/**
 * Ověří, že daný modem je v katalogu (NULL/'' je validní = nezadáno).
 */
function crm_offered_services_is_valid_modem(?string $modem): bool
{
    if ($modem === null || $modem === '') {
        return true;
    }
    return in_array($modem, crm_offered_services_modems(), true);
}
