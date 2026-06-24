<?php
// e:\Snecinatripu\app\controllers\HelpController.php
declare(strict_types=1);

/**
 * HelpController
 *
 * Per-role nápověda — každá role vidí návod k tomu, co reálně dělá.
 * Žádné restrikce na konkrétní role — všichni mají právo na manuál své práce.
 *
 * Routes:
 *   GET /help              → rozcestník filtrovaný podle aktuální role
 *   GET /help/topic?id=X   → detail tématu (placeholder pro neexistující template)
 */
final class HelpController
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Strom oblastí — id => label + popis + ikona + roles (kdo to vidí).
     *
     * Pravidla viditelnosti:
     *   'roles' => ['*']                                — všichni
     *   'roles' => ['cisticka']                         — jen čistička
     *   'roles' => ['majitel', 'superadmin']            — jen majitel + super-admin
     */
    private function topics(): array
    {
        return [
            // ─── Vítací landing per role (každá role uvidí svůj) ───
            'cisticka_start' => [
                'icon'  => '🧹', 'label' => 'Vítej! Tvoje práce čističky',
                'short' => 'Co děláš, kdy klikat, denní cíle, undo. Začni odsud.',
                'group' => 'start', 'order' => 1, 'roles' => ['cisticka'],
            ],
            'caller_start' => [
                'icon'  => '📞', 'label' => 'Vítej! Tvoje práce navolávačky',
                'short' => 'Fronta hovorů, zámky, výhry, callbacky, premium. Začni tady.',
                'group' => 'start', 'order' => 1, 'roles' => ['navolavacka'],
            ],
            'oz_start' => [
                'icon'  => '💼', 'label' => 'Vítej! Tvoje práce OZ',
                'short' => 'Příchozí leady, schůzky, nabídka, podpis. Pipeline od A do Z.',
                'group' => 'start', 'order' => 1, 'roles' => ['obchodak'],
            ],
            'bo_start' => [
                'icon'  => '🏢', 'label' => 'Vítej! Tvoje práce backoffice',
                'short' => 'Aktivace smluv, kontrola podpisu, finální workflow.',
                'group' => 'start', 'order' => 1, 'roles' => ['backoffice'],
            ],

            // ─── KROK 1: Příprava dat (jen majitel — on importuje) ───
            'import' => [
                'icon'  => '📥', 'label' => '1. Import kontaktů',
                'short' => 'Nahrání nových kontaktů z CSV / XLSX. Detailní tabulka sloupců + příklady.',
                'group' => 'data', 'order' => 1, 'roles' => ['majitel', 'superadmin'],
            ],
            'mix' => [
                'icon'  => '🎲', 'label' => '2. Mix 9× OSVČ + 1× firma',
                'short' => 'Promíchání fronty pro psychologicky příjemnější rytmus.',
                'group' => 'data', 'order' => 2, 'roles' => ['majitel', 'superadmin'],
            ],
            'recycle' => [
                'icon'  => '♻', 'label' => '3. Recyklace kontaktů',
                'short' => 'Vrácení starých VF/NEZAJEM kontaktů zpět do oběhu.',
                'group' => 'data', 'order' => 3, 'roles' => ['majitel', 'superadmin'],
            ],

            // ─── KROK 2: Workflow rolí — VIDÍ DOTYČNÁ ROLE + admin ───
            'cisticka' => [
                'icon'  => '🧹', 'label' => 'Detail: workflow čističky',
                'short' => 'Co je TM/O2/VF, kdy klikat, undo, denní cíle.',
                'group' => 'roles', 'order' => 4, 'roles' => ['cisticka', 'majitel', 'superadmin'],
            ],
            'caller' => [
                'icon'  => '📞', 'label' => 'Detail: workflow navolávačky',
                'short' => 'Pool, zámky 10×10 min, win/loss, šněčí závody.',
                'group' => 'roles', 'order' => 5, 'roles' => ['navolavacka', 'majitel', 'superadmin'],
            ],
            'oz' => [
                'icon'  => '💼', 'label' => 'Detail: workflow OZ',
                'short' => 'Příchozí leady, stages NABIDKA/SANCE/SMLOUVA, předání BO.',
                'group' => 'roles', 'order' => 6, 'roles' => ['obchodak', 'majitel', 'superadmin'],
            ],
            'bo' => [
                'icon'  => '🏢', 'label' => 'Detail: workflow backoffice',
                'short' => 'Aktivace, fakturace, datovka, finální podpis.',
                'group' => 'roles', 'order' => 7, 'roles' => ['backoffice', 'majitel', 'superadmin'],
            ],

            // ─── KROK 3: Pokročilé features ───
            'bet' => [
                'icon'  => '🎯', 'label' => 'Sázky / kampaně',
                'short' => 'Cílená kampaň: X kontaktů na kraj rozdělené mezi OZ + navolávačky.',
                'group' => 'features', 'order' => 8,
                // sázky se týkají caller + OZ + admin
                'roles' => ['navolavacka', 'obchodak', 'majitel', 'superadmin'],
            ],
            'rescue' => [
                'icon'  => '🆘', 'label' => 'Záchrana leadů',
                'short' => 'OZ vrátí nereagujícího klienta navolávačce na 2. šanci. Bonus 1× smlouva.',
                'group' => 'features', 'order' => 9,
                'roles' => ['navolavacka', 'obchodak', 'majitel', 'superadmin'],
            ],
            'premium' => [
                'icon'  => '💎', 'label' => 'Premium objednávky',
                'short' => 'OZ si objedná druhé čištění + speciální navolávání s bonusem.',
                'group' => 'features', 'order' => 10,
                'roles' => ['cisticka', 'navolavacka', 'obchodak', 'majitel', 'superadmin'],
            ],

            // ─── KROK 4: Admin ───
            'users' => [
                'icon'  => '👥', 'label' => 'Správa uživatelů',
                'short' => 'Role, multi-role, reset hesla, impersonate.',
                'group' => 'admin', 'order' => 11, 'roles' => ['majitel', 'superadmin'],
            ],
            'settings' => [
                'icon'  => '⚙️', 'label' => 'Nastavení systému',
                'short' => 'Cíle čističky, kvóty OZ, sazby navolávaček, kraje, mix poměr.',
                'group' => 'admin', 'order' => 12, 'roles' => ['majitel', 'superadmin'],
            ],
        ];
    }

    /** Vrátí true pokud user s danou rolí vidí topic. */
    private function topicVisibleForRole(array $topic, string $userRole): bool
    {
        $allowed = (array) ($topic['roles'] ?? ['*']);
        if (in_array('*', $allowed, true)) return true;
        return in_array($userRole, $allowed, true);
    }

    /** GET /help — rozcestník filtrovaný per-role */
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        // Žádný role restrict — každá role má právo na svůj manuál.
        $userRole = (string) ($user['role'] ?? '');

        // Filter topics podle role
        $allTopics = $this->topics();
        $topics = [];
        foreach ($allTopics as $tid => $t) {
            if ($this->topicVisibleForRole($t, $userRole)) {
                $topics[$tid] = $t;
            }
        }

        // Group definice — "start" je první (vítací landing)
        $groups = [
            'start'    => ['label' => '👋 Začni odsud',
                           'desc' => 'Rychlý průvodce tvojí prací — co dělají jednotlivá tlačítka.',
                           'topics' => []],
            'data'     => ['label' => '📦 KROK 1 — Příprava dat',
                           'desc' => 'Jak dostat kontakty do systému a připravit je pro práci.',
                           'topics' => []],
            'roles'    => ['label' => '👤 Detail workflow rolí',
                           'desc' => 'Detailní popis životního cyklu kontaktu přes všechny role.',
                           'topics' => []],
            'features' => ['label' => '⭐ Pokročilé features',
                           'desc' => 'Speciální nástroje, které tvoji práci usnadňují.',
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
        foreach ($groups as $gid => $group) {
            uasort($groups[$gid]['topics'], fn($a, $b) => ($a['order'] ?? 99) <=> ($b['order'] ?? 99));
        }

        $title = '❓ Nápověda — tvůj návod na práci';
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
        $userRole = (string) ($user['role'] ?? '');

        $topicId = (string) ($_GET['id'] ?? $_GET['topic'] ?? '');
        $allTopics = $this->topics();
        if (!isset($allTopics[$topicId])) {
            crm_redirect('/help');
        }
        $topic = $allTopics[$topicId];

        // Bezpečnostní guard: i přístup na konkrétní URL musí ctít role.
        if (!$this->topicVisibleForRole($topic, $userRole)) {
            crm_flash_set('Tento návod není určen pro tvou roli.');
            crm_redirect('/help');
        }

        $title = $topic['icon'] . ' ' . $topic['label'];
        $flash = crm_flash_take();
        $tmpl = dirname(__DIR__) . '/views/help/topic_' . $topicId . '.php';
        if (!file_exists($tmpl)) {
            $tmpl = dirname(__DIR__) . '/views/help/topic_placeholder.php';
        }
        ob_start();
        require $tmpl;
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }
}
