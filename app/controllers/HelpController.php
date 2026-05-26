<?php
// e:\Snecinatripu\app\controllers\HelpController.php
declare(strict_types=1);

/**
 * HelpController
 *
 * Interaktivní dokumentace systému — strom kartiček podle oblastí.
 * Přístup: majitel + superadmin (= ti, kdo systém spravují).
 *
 * Routes:
 *   GET /help              → rozcestník (kartičky)
 *   GET /help/{topic}      → detail konkrétní oblasti
 */
final class HelpController
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Strom oblastí — id => label + popis + ikona. Pořadí = chronologie pipeline. */
    private function topics(): array
    {
        return [
            // ─── KROK 1: Příprava dat ───
            'import' => [
                'icon'  => '📥',
                'label' => '1. Import kontaktů',
                'short' => 'Nahrání nových kontaktů z CSV / XLSX. Detailní tabulka sloupců + příklady.',
                'group' => 'data',
                'order' => 1,
            ],
            'mix' => [
                'icon'  => '🎲',
                'label' => '2. Mix 9× OSVČ + 1× firma',
                'short' => 'Promíchání fronty pro psychologicky příjemnější rytmus. Auto po importu.',
                'group' => 'data',
                'order' => 2,
            ],
            'recycle' => [
                'icon'  => '♻',
                'label' => '3. Recyklace kontaktů',
                'short' => 'Vrácení starých VF/NEZAJEM kontaktů zpět do oběhu po čase.',
                'group' => 'data',
                'order' => 3,
            ],

            // ─── KROK 2: Workflow rolí ───
            'cisticka' => [
                'icon'  => '🧹',
                'label' => '4. Čistička',
                'short' => 'Ověřuje TM/O2/VF. Kde co kliká, jak funguje undo, denní cíle.',
                'group' => 'roles',
                'order' => 4,
            ],
            'caller' => [
                'icon'  => '📞',
                'label' => '5. Navolávačka',
                'short' => 'Volá leady z poolu, zámky 10×10 min, win/loss flow, šněčí závody.',
                'group' => 'roles',
                'order' => 5,
            ],
            'oz' => [
                'icon'  => '💼',
                'label' => '6. Obchodní zástupce (OZ)',
                'short' => 'Přijímá leady, schůzky, nabídky, předání BO, podpis smlouvy.',
                'group' => 'roles',
                'order' => 6,
            ],
            'bo' => [
                'icon'  => '🏢',
                'label' => '7. Back-office',
                'short' => 'Zpracování smluv, fakturace, datovka, finální podpis.',
                'group' => 'roles',
                'order' => 7,
            ],

            // ─── KROK 3: Pokročilé features ───
            'bet' => [
                'icon'  => '🎯',
                'label' => 'Sázky / kampaně',
                'short' => 'Cílená kampaň: X kontaktů na kraj rozdělené mezi OZ + navolávačky.',
                'group' => 'features',
                'order' => 8,
            ],
            'rescue' => [
                'icon'  => '🆘',
                'label' => 'Záchrana leadů',
                'short' => 'OZ vrátí nereagujícího klienta navolávačce na 2. šanci. Bonus 1× smlouva.',
                'group' => 'features',
                'order' => 9,
            ],
            'premium' => [
                'icon'  => '💎',
                'label' => 'Premium objednávky',
                'short' => 'OZ si objedná druhé čištění + speciální navolávání s bonusem.',
                'group' => 'features',
                'order' => 10,
            ],

            // ─── KROK 4: Admin ───
            'users' => [
                'icon'  => '👥',
                'label' => 'Správa uživatelů',
                'short' => 'Role (cisticka/navolavacka/obchodak/BO), multi-role, reset hesla, impersonate.',
                'group' => 'admin',
                'order' => 11,
            ],
            'settings' => [
                'icon'  => '⚙️',
                'label' => 'Nastavení systému',
                'short' => 'Cíle čističky, kvóty OZ, sazby navolávaček, kraje, mix poměr.',
                'group' => 'admin',
                'order' => 12,
            ],
        ];
    }

    /** GET /help — rozcestník */
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        $topics = $this->topics();
        // Seskupit podle group + seřadit podle 'order' uvnitř skupiny
        $groups = [
            'data'     => ['label' => '📦 KROK 1 — Příprava dat',
                           'desc' => 'Jak dostat kontakty do systému a připravit je pro práci.',
                           'topics' => []],
            'roles'    => ['label' => '👤 KROK 2 — Workflow rolí (chronologicky)',
                           'desc' => 'Jak putuje lead přes celý pipeline od čističky až k uzavřené smlouvě.',
                           'topics' => []],
            'features' => ['label' => '⭐ Pokročilé features',
                           'desc' => 'Speciální nástroje pro cílené kampaně a recyklaci leadů.',
                           'topics' => []],
            'admin'    => ['label' => '🛠️ Admin nástroje',
                           'desc' => 'Uživatelé, role, nastavení sazeb a kvót.',
                           'topics' => []],
        ];
        foreach ($topics as $tid => $t) {
            $g = $t['group'] ?? 'admin';
            if (isset($groups[$g])) {
                $groups[$g]['topics'][$tid] = $t;
            }
        }
        // Sort each group's topics by 'order'
        foreach ($groups as $gid => $group) {
            uasort($groups[$gid]['topics'], fn($a, $b) => ($a['order'] ?? 99) <=> ($b['order'] ?? 99));
        }

        $title = '❓ Nápověda';
        $flash = crm_flash_take();
        ob_start();
        require dirname(__DIR__) . '/views/help/index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** GET /help/topic?id=X — detail tématu */
    public function getDetail(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        // Akceptujeme oba parametry: ?id= (z kartiček) i ?topic= (legacy)
        $topicId = (string) ($_GET['id'] ?? $_GET['topic'] ?? '');
        $topics = $this->topics();
        if (!isset($topics[$topicId])) {
            crm_redirect('/help');
        }
        $topic = $topics[$topicId];

        $title = $topic['icon'] . ' ' . $topic['label'];
        $flash = crm_flash_take();
        // Render specifický template podle topic ID
        $tmpl = dirname(__DIR__) . '/views/help/topic_' . $topicId . '.php';
        if (!file_exists($tmpl)) {
            // Fallback — placeholder
            $tmpl = dirname(__DIR__) . '/views/help/topic_placeholder.php';
        }
        ob_start();
        require $tmpl;
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }
}
