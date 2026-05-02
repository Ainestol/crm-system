# CRM Systém — Komplexní dokumentace projektu
> Poslední aktualizace: 2026-04-29  
> Určeno pro: nového spolupracovníka / pokračování vývoje

---

## 1. Co to je a proč to existuje

Vlastní CRM pro telemarketingovou firmu, která prodává internetové připojení a mobilní služby (Vodafone). Žádný framework, žádný Composer — čistý PHP 8.x + MariaDB + vanilla JS. Systém řídí celý životní cyklus zákazníka od prvního hovoru až po podpis smlouvy a její aktivaci.

**Hlavní tok zákazníka:**

```
Import CSV  →  Čistička ověří data  →  Navolávačka zavolá  →
OZ jedná (nabídka/schůzka/Šance/...)  →  Předá BO  →  BO zpracuje (datovka, podpis, OKU)  →
Smlouva uzavřena (Dokončené) → provize OZ
```

---

## 2. Technologický stack

| Vrstva | Technologie |
|---|---|
| Backend | PHP 8.x, `declare(strict_types=1)` na všech souborech |
| DB | MariaDB (PDO, prepared statements, žádný ORM) |
| Frontend | Vanilla JS, inline CSS proměnné, žádný framework |
| Šablony | PHP views (ob_start / ob_get_clean) |
| Server | Apache + mod_rewrite (vše jde přes `public/index.php`) |
| Dev | PHP built-in server přes `public/dev-router.php` |
| 3rd-party | ARES JSON API (proxy), žádné jiné externí závislosti |

---

## 3. Struktura složek

```
E:\Snecinatripu\
├── public/
│   ├── index.php              ← Front controller (všechny requesty sem)
│   ├── dev-router.php         ← PHP dev server helper
│   ├── .htaccess              ← mod_rewrite → index.php
│   └── assets/
│       ├── css/app.css        ← Globální styly (CSS proměnné, komponenty)
│       └── img/               ← Logo apod.
│
├── app/
│   ├── bootstrap.php          ← PDO připojení, session start, require helpers
│   ├── Router.php             ← Registr všech rout (metoda + path → controller)
│   │
│   ├── controllers/           ← Jeden soubor = jedna třída = jedna role/oblast
│   │   ├── LoginController.php
│   │   ├── HomeController.php
│   │   ├── DashboardController.php
│   │   ├── RegionController.php
│   │   ├── AdminUsersController.php
│   │   ├── AdminImportController.php
│   │   ├── AdminContactsAssignmentController.php
│   │   ├── AdminDailyGoalsController.php
│   │   ├── AdminCallerStatsController.php
│   │   ├── AdminTeamStatsController.php
│   │   ├── AdminOzTargetsController.php
│   │   ├── AdminOzStagesController.php
│   │   ├── AdminOzMilestonesController.php
│   │   ├── CistickaController.php
│   │   ├── CallerController.php             ← Navolávačka
│   │   ├── OzController.php                 ← Obchodní zástupce
│   │   ├── OzPerformanceController.php      ← Výkon týmu OZ
│   │   └── BackofficeController.php         ← Back-office (NOVÉ)
│   │
│   ├── views/
│   │   ├── layout/base.php        ← HTML obal (nav, flash, $content)
│   │   ├── login/
│   │   ├── dashboard/
│   │   ├── admin/
│   │   ├── cisticka/
│   │   ├── caller/
│   │   │   ├── index.php
│   │   │   ├── calendar.php
│   │   │   ├── search.php
│   │   │   ├── stats.php
│   │   │   └── _notifications.php
│   │   ├── oz/
│   │   │   ├── index.php          ← Dashboard OZ
│   │   │   ├── leads.php          ← Pracovní plocha OZ (~2700 ř.)
│   │   │   └── performance.php
│   │   └── backoffice/
│   │       └── index.php          ← Pracovní plocha BO (NOVÉ)
│   │
│   └── helpers/               ← Globální funkce (bez tříd)
│       ├── auth.php            ← crm_auth_current_user(), crm_redirect()
│       ├── middleware.php      ← crm_require_user(), crm_require_roles()
│       ├── csrf.php            ← crm_csrf_token(), crm_csrf_validate()
│       ├── flash.php           ← crm_flash_set(), crm_flash_take()
│       ├── url.php             ← crm_url(), crm_h(), crm_normalize_ico()
│       ├── session.php
│       ├── audit.php
│       ├── assignment.php
│       ├── import_csv.php
│       ├── commissions.php
│       ├── region.php
│       ├── users_admin.php
│       ├── encryption.php
│       ├── mail.php
│       ├── sms.php
│       ├── totp.php
│       ├── api_auth.php
│       └── services_catalog.php   ← Katalog mobilních/internet tarifů (DEAKTIVOVÁNO v UI)
│
├── config/
│   ├── constants.php
│   ├── db.php
│   ├── mail.php
│   └── sms.php
│
└── storage/                    ← (uploaded files, imports)
```

**Velikost projektu (k 2026-04-29):** ~73 souborů, ~22 000 řádků kódu (PHP + CSS).

Největší soubor: `app/views/oz/leads.php` (~2 700 řádků). Kandidát na refactoring na partials, ale nepřekáží.

---

## 4. Jak funguje routing

Vše jde přes `public/index.php` → `Router::dispatch()`.

Router je statické pole v `Router::routes()`. Každá routa má:

- `method` — GET nebo POST
- `path` — přesná cesta (žádné parametry v URL, vše přes GET/POST)
- `auth` — true = vyžaduje přihlášení
- `roles` — seznam povolených rolí
- `handler` — `[ControllerClass, 'methodName']`

**Příklad přidání nové routy:**

```php
[
    'method'  => 'POST',
    'path'    => '/oz/neco-noveho',
    'auth'    => true,
    'roles'   => ['obchodak', 'majitel', 'superadmin'],
    'handler' => [OzController::class, 'postNecoNoveho'],
],
```

Poté přidat controller metodu + view + `require_once` v `public/index.php` (nové controllery se musí načíst).

---

## 5. Klíčové konvence a pravidla

### POST → Redirect → GET (PRG pattern)
Každý POST formulář po zpracování volá `crm_redirect('/cesta')`. Nikdy nerendovat HTML po POSTu.

**Výjimka:** AJAX endpointy vrací JSON (detekce přes `Accept: application/json` header). Vzor:
```php
$isAjax = (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json'))
       || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
```

### CSRF ochrana — povinná na každém formuláři

```php
// V controlleru — validace:
if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
    crm_flash_set('Neplatný CSRF token.');
    crm_redirect('/oz/leads');
}

// Ve view — pole formuláře:
<input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
```

### HTML escaping — VŽDY `crm_h()`

```php
echo crm_h($promenna);  // htmlspecialchars wrapper
```

### Flash zprávy

```php
crm_flash_set('Zpráva uživateli.');  // nastaví do session
$flash = crm_flash_take();           // přečte a smaže ze session
```

### DB migrace — try/catch vzor

Nové sloupce se přidávají inline v controlleru. Pokud sloupec existuje, PDOException se tiše ignoruje:

```php
try {
    $this->pdo->exec('ALTER TABLE `tabulka` ADD COLUMN `novy_sloupec` VARCHAR(100) NULL');
} catch (\PDOException) {}
```

**KRITICKÉ:** Migrace musí běžet PŘED jakýmkoliv SELECT, který nový sloupec používá. Vždy dávat na vrchol metody (typicky v `ensure*Table()` privátních metodách).

### URL helper

```php
crm_url('/oz/leads')                 // vrátí absolutní URL
crm_url('/oz/leads?tab=bo')          // s query stringem
```

### IČO normalizace

```php
crm_normalize_ico('1234567')  // "01234567"
crm_normalize_ico('123')      // "00000123"
crm_normalize_ico('12345678') // "12345678" (beze změny)
```

Aplikováno všude (view, edit form, ARES lookup, postContactEdit). Definováno v `app/helpers/url.php`.

---

## 6. Role uživatelů

| Role | Česky | Co vidí / dělá |
|---|---|---|
| `superadmin` | Superadmin | Vše |
| `majitel` | Majitel | Vše + admin sekce |
| `navolavacka` | Navolávačka | `/caller` — volání, stavování kontaktů |
| `obchodak` | Obchodní zástupce (OZ) | `/oz/leads` — práce s leady |
| `backoffice` | Back-office (BO) | `/bo` — datovka, podpis, OKU, uzavírání smluv |
| `cisticka` | Čistička | `/cisticka` — ověřování kontaktů |

V topbaru (base.php) se zobrazí badge s rolí + tlačítko **🏢 Back-office** pro role backoffice/majitel/superadmin.

---

## 7. Databázové tabulky

### Základ

- `users` — uživatelé (id, jmeno, email, role, aktivni, ...)
- `contacts` — kontakty/firmy (id, firma, telefon, email, ico, adresa, region, operator, stav, assigned_caller_id, assigned_sales_id, ...)
- `user_regions` — přiřazené regiony navolávačce (user_id, region)

### Stavy kontaktu (`contacts.stav`)

```
NEW → READY → ASSIGNED → (NEDOVOLANO) → CALLED_OK / CALLED_BAD / NEZAJEM / IZOLACE / CHYBNY_KONTAKT
```

- `READY` — prošel čističkou, čeká na navolávačku
- `ASSIGNED` — přiřazen navolávačce
- `CALLED_OK` — navolávačka ho odeslala OZ → OZ s ním pracuje
- Ostatní jsou terminální nebo dočasné stavy

### Workflow OZ (`oz_contact_workflow`)

Jeden řádek = jeden OZ + jeden kontakt. Sleduje stav jednání.

**Workflow stavy:**

```
NOVE → ZPRACOVAVA → NABIDKA → SCHUZKA → SANCE
                                       ↘ CALLBACK
                                       ↘ NEZAJEM / NERELEVANTNI
                                       ↘ REKLAMACE (= Chybný lead)
                                       ↘ BO_PREDANO → BO_VPRACI → UZAVRENO
                                                              ↘ BO_VRACENO
```

**Klíčové sloupce:**

| Sloupec | Popis |
|---|---|
| `stav` | viz výše |
| `started_at` | kdy OZ převzal |
| `poznamka` | povinná poznámka při změně stavu |
| `callback_at` | datum a čas callbacku (nepovinné — může být NULL = "kdykoliv později") |
| `schuzka_at` | datum a čas schůzky |
| `schuzka_acknowledged` | OZ odklikl notifikaci o schůzce |
| `bmsl` | Bod měsíčního smluvního limitu (Kč) |
| `smlouva_date` | datum podpisu smlouvy |
| `nabidka_id` | ID nabídky z OT (Vodafone Order Tool) |
| `closed_at` | kdy BO uzavřel kontrakt — DATETIME nebo NULL (NOVÉ) |
| `install_internet`, `install_adresy` | legacy, UI odebráno (data zůstávají) |

**Stavy SANCE / BO_VPRACI / closed_at jsou nové (přidané v dubnu 2026).**

### Chybné leady (`contact_oz_flags`)

OZ může označit lead jako chybně navolaný. Ping-pong systém mezi OZ a navolávačkou.

| Sloupec | Popis |
|---|---|
| `contact_id`, `oz_id` | kdo a kde |
| `reason` | důvod od OZ |
| `flagged_at` | kdy bylo nahlášeno |
| `caller_comment` | komentář navolávačky |
| `caller_confirmed` | navolávačka klikla "Uzavřít" |
| `oz_comment` | odpověď OZ |
| `oz_confirmed` | OZ klikl "Uzavřít" |

Případ uzavřen až když oba kliknou "Uzavřít". Lead zůstává jako chybný a nepočítá se do výplaty navolávačky.

### Pracovní deník (`oz_contact_actions`) — NOVÉ

Sdílený mezi OZ a BO. Manuální záznamy o průběhu zakázky (datum + popis).

| Sloupec | Popis |
|---|---|
| `contact_id` | kontakt |
| `oz_id` | autor (může být OZ i BO i majitel — ID uživatele) |
| `action_date` | datum úkonu (může být i v budoucnosti — TODO) |
| `action_text` | popis |
| `created_at` | kdy se zápis vytvořil |

Ve view se autor zobrazuje s ikonou: 🏢 BO / 🛒 OZ / 📞 Caller / 👑 Majitel.

Barevné rozlišení v UI:
- modrá = OZ
- zelená = BO
- zlatá = automatický záznam "↩ Vráceno OZ" (od BO)

### Nabídnuté služby (`oz_contact_offered_services` + `_items`) — DEAKTIVOVÁNO

Strukturovaný katalog nabídnutých služeb. Připraveno v DB i kódu, ale UI je obaleno `if (false)` — nepoužívá se. Místo toho se používá Pracovní deník + ID nabídky z OT.

**Pokud bude potřeba reaktivovat:** vyhledat "DEAKTIVOVÁNO" v `app/views/oz/leads.php` a změnit `if (false)` na `if (true)`. Backend, routy a tabulky jsou funkční.

### Per-user tab prefs (`oz_tab_prefs`) — NOVÉ

| Sloupec | Popis |
|---|---|
| `user_id` | OZ |
| `hidden_tabs` | JSON array skrytých tabů |
| `tab_order` | JSON array vlastní pořadí tabů (drag & drop) |
| `updated_at` | poslední změna |

OZ si může schovat tab křížkem (×), pak se objeví v mini panelu "Skryté:" a může ho připnout zpět. Pořadí tabů přes drag & drop.

### Workflow log + audit + ostatní

- `workflow_log` — log změn stavu (auto-zápis)
- `oz_contact_notes` — auto-poznámky z workflow změn (stará tabulka, dál se používá)
- `oz_monthly_targets` — kvóty OZ per region/měsíc
- `oz_team_stages` — stage cíle týmu v BMSL
- `oz_personal_milestones` — osobní milníky OZ
- `daily_goals` — denní cíle navolávaček
- `caller_performance_log` — log výkonu navolávačky

---

## 8. Moduly po rolích

### 8.1 Čistička (`/cisticka`)

- Prochází kontakty ve stavu `CALLED_BAD` nebo podobných
- Ověřuje platnost, překlasifikuje
- Akce: verify, verify-batch, undo, reclassify

### 8.2 Navolávačka (`/caller`)

**Taby (URL: `/caller?tab=xxx`):**

| Tab | Co zobrazuje | Filtr měsíce? |
|---|---|---|
| `aktivni` | Kontakty k provolání (READY/ASSIGNED) | ne (živá fronta) |
| `callback` | Callbacky | ne |
| `nedovolano` | Nedovolané | ne |
| `navolane` | Odeslané OZ (CALLED_OK + FOR_SALES) | **ano** (NOVÉ) |
| `prohra` | Prohry (CALLED_BAD, NEZAJEM…) | **ano** (NOVÉ) |
| `izolace` | IZOLACE | ne |
| `chybny` | CHYBNY_KONTAKT | **ano** (NOVÉ) |
| `chybne_oz` | Leady označené OZ jako chybné — disputa | **ano** (NOVÉ) |
| `vykon` | Měsíční výkon a výplata | vlastní filter |

**Filtr měsíce u rostoucích tabů:** dropdown s posledními 12 měsíci, default = aktuální měsíc.

**Kontakty s flagem od OZ se nezobrazují v `navolane`** (patří jen do `chybne_oz`).

**Výplata navolávačky:**
- Sazba: 200 Kč/kus (editovatelné adminem)
- Výpočet: `výher − chybných = placených`
- Zobrazení: `⚠ X výher − Y chybných = Z placených`

### 8.3 OZ — Dashboard (`/oz`)

- Přehled leadů per region + kvóty
- Filtr po měsících
- Chybné leady (flagované)
- Odkaz na pracovní plochu

### 8.4 OZ — Pracovní plocha (`/oz/leads`) — největší modul

**HEADER BLOK** (vrchní sekce, vizuálně oddělená containerem s gradient pozadím):
- 🐌 Šněčí závody OZ
- Topbar statistiky (smluv/měsíc, BMSL/měsíc, NOVÉ, OBVOLÁNO, CALLBACKY, U BO)
- Výkon tento měsíc (osobní + týmové bonusy)

Pod tím **vizuální oddělovač** s badge **„📋 Moje pracovní plocha"** — jasně odděluje nahoru přehled od dolů pracovních karet.

**Taby (přizpůsobitelné, drag & drop pořadí, lze skrýt × křížkem):**

| Tab | Stav workflow | Popis |
|---|---|---|
| `nove` | NOVE + OBVOLANO + ZPRACOVAVA | Nové + odpracované |
| `nabidka` | NABIDKA | Nabídka odeslána |
| `callback` | CALLBACK | Callbacky (datum nepovinné) |
| `schuzka` | SCHUZKA | Schůzky |
| `sance` | SANCE | Šance — zákazník chce, ale chybí mu administrativa |
| `bo` | SMLOUVA + BO_PREDANO + BO_VPRACI + BO_VRACENO | U BO ke zpracování |
| `dokonceno` | UZAVRENO | Dokončené smlouvy (zelený) + filtr měsíců |
| `reklamace` | REKLAMACE | Chybné leady |
| `nezajem` | NEZAJEM + NERELEVANTNI | Bez zájmu |

**Defaultní pořadí:** Nové · Odeslané nabídky · Callbacky · Schůzky · Šance · Předáno BO · Dokončené · Chybné leady · Nezájem.

**Drag & drop:** OZ chytne tab myší a přetáhne — nové pořadí se uloží do `oz_tab_prefs.tab_order`. Per-user.

**Skrýt/připnout tab:** klik na × vedle tabu → zmizí. V mini panelu pod taby "📂 Skryté: [+ Tab (X)]" — klik vrátí. OZ zůstane na aktuálním tabu.

**Tab Dokončené má dropdown měsíc/rok** — statistika podpisů. Default = aktuální měsíc.

**Karta kontaktu** (`oz-contact` div) — výrazně vystouplý panel s 3-vrstvým stínem, hover zvedne. V levém horním rohu **visačka s pořadovým číslem** `#3/12` (3. ze 12 v této záložce).

**Hlavička karty:** Firma · ⚠ Chybný lead · meta (Olomoucký kraj · navolán před 2 d) · Stav badge · 🔖 ID nabídky badge (jen v BO stavech).

**Pod hlavičkou různé pásky podle stavu:**
- Schůzka: 📅 datum
- Callback: 📞 zelená/žlutá/oranžová podle budoucnosti/dnes/prošlosti
- BMSL: 💰 + datum podpisu (pro SMLOUVA+ stavy)

**Tělo karty (2 sloupce):**

LEVÝ — info zákazníka:
- ✏️ Upravit kontakt (modré tlačítko nahoře vlevo)
- Tel · E-mail · IČO + 🔗 ARES + 📋 Načíst z ARES (v edit režimu) · Adresa · Operátor (neutrální text)

PRAVÝ — komunikace:
- 📞 Poznámka navolávačky
- 📝 Moje poznámky (auto-log z workflow změn)

**Pod tělem (full-width):**

🔖 **ID nabídky (OT)** — zobrazí se jen pokud je vyplněné (v aktivních stavech jako velký zelený panel s ✏️; v BO stavech jen mini badge v hlavičce).

📋 **Pracovní deník** — viditelný **jen v BO_VRACENO a UZAVRENO** stavu (jinak skrytý). Sbalitelný (klik na hlavičku rozbalí/sbalí).

**Akční formulář** (jen v aktivních stavech):
- Poznámka (povinná, nebo collapsed v BO_VRACENO jako "+ Přidat krátkou poznámku k akci")
- Tlačítka: 📨 Nabídka odeslána · 📅 Schůzka · 📞 Callback · 💡 Šance · 📤 Předat BO · ✗ Nezájem · 💾 Uložit poznámku
- (každé tlačítko se schová, pokud je odpovídající tab skrytý)

**📤 Předat BO** logika:
- Pokud je vyplněné ID nabídky → confirm dialog → POST
- Pokud chybí → klik otevře růžový inline dialog s polem pro ID + tlačítko "Uložit a předat BO"

**Stav UZAVRENO (v Dokončené):**
- ✅ Dokončeno banner + 📤 Znovu předat BO tlačítko (vrátí kontakt do BO_PREDANO, vynuluje closed_at)

**Stav BO_VRACENO:**
- Pracovní deník viditelný (BO vrátil s důvodem)
- Poznámka collapsed
- 📤 Předat BO znova k dispozici

**Custom confirm dialog:** krásný overlay (`ozShowConfirm`) místo browser `confirm()`.

**Pořadí kontaktů v záložkách:**
- `callback`: podle data callbacku (NULL na konec)
- `schuzka`: podle data schůzky
- `dokonceno`: podle `closed_at` DESC (nejnovější nahoře)
- ostatní: podle `started_at` (kdy OZ převzal) ASC — **karty po update neskáčou!**

### 8.5 OZ — Výkon týmu (`/oz/performance`)

Přehled výkonu všech OZ za zvolený měsíc, stage progression.

### 8.6 BO — Pracovní plocha (`/bo`) — NOVÉ

Tabbed dashboard:

| Tab | Workflow stav | Popis |
|---|---|---|
| `k_priprave` | BO_PREDANO + SMLOUVA | Nově předáno OZ-em, čeká na převzetí |
| `v_praci` | BO_VPRACI | BO převzal a zpracovává |
| `vraceno_oz` | BO_VRACENO | Vráceno OZ k opravě |
| `uzavreno` | UZAVRENO | Dokončené smlouvy (provize) |

**Karta kontaktu BO:**
- Hlavička: firma + barevný stav badge + meta (kraj, OZ, navolávačka)
- Tělo: info zákazníka + 🔗 ARES + Poznámka navolávačky + **📝 Poznámky OZ** (auto-log) + BMSL/podpis
- 🔖 ID nabídky panel
- 📋 **Pracovní deník — defaultně rozbalený** (BO hned vidí, o co jde)

**Akční tlačítka per tab:**
- **K přípravě:** 🔧 Začít zpracovávat (BO_PREDANO → BO_VPRACI, zápis do deníku)
- **V práci:** ↩ Vrátit OZ (s povinným důvodem — zápis "↩ Vráceno OZ: ..." do deníku, BO_VPRACI → BO_VRACENO) + ✅ Uzavřít smlouvu (BO_VPRACI → UZAVRENO, nastaví `closed_at`)
- **Uzavřeno:** 🔄 Otevřít znovu (s volitelným důvodem — UZAVRENO → BO_VPRACI, nuluje `closed_at`)

**BO může psát do Pracovního deníku** ve všech stavech kromě UZAVRENO (kde to už nemá smysl — kontakt je hotov, nebo se musí znovuotevřít).

**Mazání záznamů v deníku** — jen vlastní (BO smaže jen své záznamy).

### 8.7 Admin sekce (`/admin/*`)

| URL | Controller | Popis |
|---|---|---|
| `/admin/users` | AdminUsersController | CRUD uživatelů, reset hesla, 2FA |
| `/admin/import` | AdminImportController | Import CSV kontaktů |
| `/admin/daily-goals` | AdminDailyGoalsController | Denní cíle navolávačů |
| `/admin/caller-stats` | AdminCallerStatsController | Výkon navolávačů |
| `/admin/team-stats` | AdminTeamStatsController | Sjednocený výkon týmu |
| `/admin/oz-targets` | AdminOzTargetsController | Kvóty OZ per region per měsíc |
| `/admin/oz-stages` | AdminOzStagesController | Stage cíle týmu v BMSL |
| `/admin/oz-milestones` | AdminOzMilestonesController | Osobní milníky OZ |

---

## 9. Klíčové flow a interakce

### 9.1 OZ → BO → Dokončené (hlavní tok)

1. **OZ** dostane lead, pracuje s ním (NOVE → NABIDKA → SCHUZKA atd.)
2. OZ vyplní **ID nabídky z OT** (ručně, nebo přes 📤 Předat BO dialog)
3. OZ klikne **📤 Předat BO** → workflow `BO_PREDANO`
4. **BO** otevře `/bo`, vidí kontakt v **K přípravě** (s rozbaleným deníkem)
5. BO klikne **🔧 Začít zpracovávat** → `BO_VPRACI` (V práci)
6. BO si píše do deníku co dělá (datovka, mail, OKU…)
7. Buď:
   - BO klikne **↩ Vrátit OZ** s důvodem → `BO_VRACENO` → OZ doplní co chybí, znovu Předá BO
   - BO klikne **✅ Uzavřít smlouvu** → `UZAVRENO` + `closed_at = NOW`
8. **OZ** vidí kontakt v záložce **✅ Dokončené** (zelený), s filtrem podle měsíce
9. Pokud zákazník později chce dokoupit službu, OZ klikne **📤 Znovu předat BO** → `BO_PREDANO`, `closed_at = NULL` → kolečko od kroku 4

### 9.2 Chybné leady (ping-pong) OZ ↔ Navolávačka

Beze změny od minulé verze dokumentace — viz původní popis. Klíčové: kontakt s flagem v `contact_oz_flags` se v `navolane` tabu Caller **NEzobrazuje** (patří jen do `chybne_oz`).

### 9.3 ARES auto-fill

OZ klikne **✏️ Upravit kontakt** → vyplní IČO → klikne **📋 z ARES** → backend volá `https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/{ico}` → JSON s názvem firmy a adresou se nahraje do polí. OZ zkontroluje a 💾 Uloží.

IČO je automaticky padded zleva nulami na 8 znaků.

### 9.4 Drag & drop pořadí tabů

OZ chytí tab myší (cursor:grab) → přetáhne na novou pozici → AJAX uloží do `oz_tab_prefs.tab_order` → příště se načte stejné pořadí.

---

## 10. Kompletní seznam rout

### Veřejné

| Metoda | Cesta | Popis |
|---|---|---|
| GET | `/` | Root redirect |
| GET/POST | `/login` | Přihlášení |
| GET/POST | `/login/two-factor` | 2FA |

### Přihlášení (všechny role)

| Metoda | Cesta | Popis |
|---|---|---|
| GET | `/dashboard` | Dashboard |
| POST | `/logout` | Odhlášení |

### Navolávačka

| Metoda | Cesta | Popis |
|---|---|---|
| GET | `/caller` | Hlavní plocha (s taby) |
| POST | `/caller/status` | Aktualizace stavu kontaktu |
| POST | `/caller/assign-sales` | Přiřazení OZ |
| GET | `/caller/calendar` | Kalendář callbacků |
| GET | `/caller/callbacks.json` | JSON callbacků |
| POST | `/caller/set-default-sales` | Výchozí OZ |
| POST | `/caller/contact/edit` | Editace kontaktu |
| GET | `/caller/search` | Vyhledávání |
| GET | `/caller/stats` | Statistiky |
| GET | `/caller/pool-count.json` | Počet v poolu |
| GET | `/caller/race.json` | Šněčí závody JSON |
| POST | `/caller/flag-mismatch` | Nahlášení neshody |
| POST | `/caller/chybny-objection` | Námitka / uzavření chybného leadu |

### OZ

| Metoda | Cesta | Popis |
|---|---|---|
| GET | `/oz` | Dashboard (Moje leady & kvóty) |
| GET | `/oz/leads` | Pracovní plocha |
| POST | `/oz/lead-status` | Aktualizace stavu leadu |
| GET | `/oz/race.json` | Šněčí závody JSON |
| POST | `/oz/acknowledge-meeting` | Potvrzení schůzky |
| POST | `/oz/accept-lead` | Přijetí leadu |
| POST | `/oz/accept-all-leads` | Přijetí všech leadů |
| POST | `/oz/reklamace` | Označení jako reklamace |
| POST | `/oz/flag` | Flag (z OZ dashboardu) |
| POST | `/oz/chybny-comment` | OZ napíše odpověď navolávačce |
| POST | `/oz/chybny-close` | OZ uzavře případ z jeho strany |
| GET | `/oz/performance` | Výkon týmu |
| POST | `/oz/contact/edit` | Editace údajů kontaktu (firma/tel/email/IČO/adresa) |
| GET | `/oz/ares-lookup` | ARES proxy (`?ico=...`) → JSON |
| POST | `/oz/set-offer-id` | ID nabídky z OT (+ volitelně rovnou předat BO) |
| POST | `/oz/action/add` | Přidat záznam do Pracovního deníku (AJAX/HTML) |
| POST | `/oz/action/delete` | Smazat svůj záznam |
| POST | `/oz/tab/hide` | Skrýt záložku |
| POST | `/oz/tab/show` | Připnout záložku |
| POST | `/oz/tab/reorder` | Uložit pořadí tabů (drag & drop, AJAX) |
| POST | `/oz/offered-service/add` | DEAKTIVOVÁNO (ale routa funguje) |
| POST | `/oz/offered-service/delete` | DEAKTIVOVÁNO |
| POST | `/oz/offered-service/edit` | DEAKTIVOVÁNO |
| POST | `/oz/offered-service-item/oku` | DEAKTIVOVÁNO |

### BO (Back-office) — NOVÉ

| Metoda | Cesta | Popis |
|---|---|---|
| GET | `/bo` | Pracovní plocha BO (4 taby) |
| POST | `/bo/start-work` | K přípravě → V práci (BO převzal) |
| POST | `/bo/return-oz` | Vrátit OZ s povinným důvodem |
| POST | `/bo/close` | Uzavřít smlouvu (UZAVRENO + closed_at) |
| POST | `/bo/reopen` | Znovu otevřít z UZAVRENO |
| POST | `/bo/action/add` | BO přidá záznam do deníku |
| POST | `/bo/action/delete` | BO smaže svůj záznam |

### Admin

| Metoda | Cesta | Popis |
|---|---|---|
| GET/POST | `/admin/users/*` | CRUD uživatelů |
| GET/POST | `/admin/import` | Import CSV |
| GET/POST | `/admin/daily-goals/*` | Denní cíle |
| GET | `/admin/caller-stats` | Výkon navolávačů |
| GET | `/admin/team-stats` | Výkon týmu |
| GET/POST | `/admin/oz-targets/*` | Kvóty OZ |
| GET/POST | `/admin/oz-stages/*` | Stage cíle |
| GET/POST | `/admin/oz-milestones/*` | Osobní milníky |

### Čistička

| Metoda | Cesta | Popis |
|---|---|---|
| GET | `/cisticka` | Hlavní plocha |
| GET | `/cisticka/stats` | Statistiky |
| POST | `/cisticka/verify` | Ověření kontaktu |
| POST | `/cisticka/verify-batch` | Hromadné ověření |
| POST | `/cisticka/undo` | Vrácení akce |
| POST | `/cisticka/reclassify` | Překlasifikace |

---

## 11. AJAX endpointy a JS funkce

### AJAX endpointy

- `POST /oz/action/add` — přidat záznam do deníku (Accept: application/json) → JSON response s nově vytvořeným řádkem. JS pak vloží řádek do DOM bez reloadu.
- `GET /oz/ares-lookup?ico=...` → JSON `{ok, firma, adresa}`. cURL fetch ARES, timeout 10s.
- `POST /oz/tab/reorder` — JSON response `{ok}`. Volá se z drag-and-drop handleru.
- `POST /caller/contact/edit` — JSON response (existující legacy endpoint).

### Klíčové JS funkce v `app/views/oz/leads.php`

| Funkce | Co dělá |
|---|---|
| `ozSubmit(cId, stav)` | Submitne hlavní lead-status form |
| `ozConfirm(cId, stav, msg, icon)` | Krásný confirm dialog → ozSubmit |
| `ozShowConfirm(title, body, callback, icon)` | Custom modal overlay |
| `ozRequireNote(cId)` | Validace povinné poznámky (data-optional respekt) |
| `ozTogglePanel(panelId, cId)` | Toggle datetime panelu (schůzka, callback) |
| `ozToggleSmlouvaPanel(cId)` | DEAKTIVOVÁNO (panel zůstal pro legacy) |
| `ozToggleReklamacePanel(cId)` | Toggle nahlášení chybného leadu |
| `ozActionsToggle(cId)` | Pracovní deník sbalit/rozbalit |
| `ozNoteExpand(cId)` / `ozNoteCollapse(cId)` | Poznámka v BO_VRACENO |
| `ozAresLookup(cId)` | ARES auto-fill v edit režimu |
| `ozContactEditToggle(cId)` | View ↔ Edit režim kontaktu |
| `ozOfferIdToggle(cId)` | View ↔ Edit ID nabídky |
| `ozPredatBoDialogToggle(cId)` | Předat BO dialog (když chybí ID) |
| `ozReopenFromUzavreno(cId)` | Znovu předat BO z Dokončené |
| `ozOkuToggle(itemId)` | DEAKTIVOVÁNO |
| `ozOfferedToggleForm(cId)` / `ozOfferedTypeChanged(cId)` | DEAKTIVOVÁNO |

**Drag & drop tabů** je v IIFE bloku — vlastní listener na všech `.oz-tab-wrap[draggable]`.

**Pracovní deník AJAX** — globální `document.addEventListener('submit', ...)` s detekcí action `/oz/action/add`.

---

## 12. Jak přidat nový controller

1. Vytvořit `app/controllers/NovyController.php` s `declare(strict_types=1)` a třídou `final class NovyController`
2. Přidat `require_once` do `public/index.php`
3. Přidat routy do `Router::routes()`
4. Vytvořit view v `app/views/nova_sekce/index.php`
5. Renderovat view pomocí vzoru:

```php
$title = 'Název stránky';
ob_start();
require dirname(__DIR__) . '/views/nova_sekce/index.php';
$content = (string) ob_get_clean();
require dirname(__DIR__) . '/views/layout/base.php';
```

---

## 13. Jak spustit lokálně

```bash
# PHP built-in server
cd E:\Snecinatripu\public
php -S localhost:8000 dev-router.php
```

Nebo Apache s virtual hostem → `DocumentRoot` na `E:\Snecinatripu\public`.

---

## 14. Co je hotovo / co se plánuje

### Hotovo

- Login + 2FA
- Dashboard
- Import CSV kontaktů
- Přiřazení kontaktů (auto + bulk + cherry-pick)
- Čistička (ověřování kontaktů)
- Navolávačka — kompletní pracovní plocha (8 tabů + výkon + šněčí závody + měsíční filtry)
- OZ — pracovní plocha s plným workflow (9 tabů)
- OZ — dashboard Moje leady & kvóty
- BMSL systém (smlouvy + body měsíčního limitu)
- Schůzky (notifikace, potvrzení)
- Admin: kvóty, stage cíle, osobní milníky
- Chybné leady — kompletní ping-pong systém
- Výpočet výplaty navolávačky
- Výkon týmu OZ + performance stránka
- **BO Workspace — fáze 1 hotová** (4 taby, akce vrátit/uzavřít/znovuotevřít, deník)
- **Pracovní deník** — sdílený OZ + BO, AJAX přidávání, autor, barevné rozlišení, sbalitelný
- **Tab Šance** — pro kontakty bez administrativy
- **Tab Dokončené** — UZAVRENO, zelený, filtr měsíců (statistika podpisů)
- **ID nabídky z OT** — recyklovaný `nabidka_id`, inline edit, propojený s Předat BO
- **Edit kontaktu** — firma/tel/email/IČO/adresa, ARES auto-fill
- **IČO normalizace** — padding zleva 8 znaků
- **ARES proxy** — backend cURL na `ares.gov.cz`
- **Per-user UI prefs** — drag & drop pořadí tabů, skrýt/připnout
- **UX vylepšení** — header oddělený, karty výrazně oddělené, číslování karet, custom confirm dialog
- **Volitelné datum callbacku** — "Bez data" tlačítko, barevné rozlišení (budoucí/dnes/prošlý)
- **Stabilní řazení** — karty po update neskáčou nahoru

### V přípravě (zmiňované, ne hotové)

- **Provizní systém** — `closed_at` se eviduje, ale automatický výpočet provize OZ ještě nikde
- **TODO seznam pro BO** — user navrhl, odloženo
- **Mini kalendář v Pracovním deníku** — uloženo do Fáze 3
- **Reaktivace Nabídnutých služeb** — DB + backend hotové, UI deaktivované
- **PWA / mobilní aplikace** — diskutováno (PWA → Capacitor → React Native), žádný kód

### Případné drobnosti k pozornosti

- `oz/leads.php` má **~2 700 řádků** — velký soubor, jednou možná rozdělit na partials (`_card.php`, `_actions.php`, `_workdiary.php`)
- DB sloupce `install_internet`, `install_adresy` v `oz_contact_workflow` jsou opuštěné (UI odebráno, data zůstávají)
- Tab "obvolano" byl smazán — workflow stavy `OBVOLANO`/`ZPRACOVAVA` se zobrazují v tabu Nové

---

## 15. Nejčastější chyby a jak je řešit

**"Unknown column 'X.sloupec'" při spuštění**
Migrace (`ALTER TABLE`) musí běžet PŘED query. Přesuň `ALTER TABLE` blok na samý vrchol metody nebo do `ensure*Table()` privátní metody volané z konstruktoru / hlavní metody.

**500 po přidání routy**
Zkontroluj: je controller načten v `public/index.php`? Existuje metoda v controlleru? Není překlep v routě?

**Formulář nic nedělá (přesměruje bez efektu)**
CSRF token chybí nebo nesedí. Zkontroluj `<input type="hidden" name="...csrf...">` ve formuláři.

**Flash zpráva se nezobrazí**
`crm_flash_set()` musí být PŘED `crm_redirect()`. Flash se čte v `base.php` přes `crm_flash_take()`.

**Parse error: Unmatched '}' in view**
PHP komentář `<?php /* ... */ ?>` špatně reaguje na `?>` uvnitř (bere to jako konec PHP módu). Pro vypínání HTML+PHP bloků používej **`<?php if (false) { ?> ... <?php } ?>`** vzor.

**AJAX endpoint vrátí HTML místo JSON**
Server detekuje AJAX přes `Accept: application/json` nebo `X-Requested-With: XMLHttpRequest`. Pokud klient posílá oba headery a stejně dostane HTML, zkontroluj vetev v controlleru.

**Tab "callback" nefunguje (nebo callbacky)**
Historicky byl bug — view odkazoval `?tab=callbacky` (s `y`), validation list měl `callback`. Opraveno. Pokud se objeví znovu, hledej překlep.

---

## 16. Důležité konvence pojmenování

- Controller metody: `getIndex()`, `postSave()`, `postDelete()` — prefix = HTTP metoda
- DB tabulky: `snake_case`, English nebo Czech zkratky
- PHP proměnné ve views: `$contacts`, `$tabCounts`, `$flash`, `$csrf`, `$tab`, `$user`
- CSS třídy: BEM-ish, prefix podle oblasti: `oz-`, `caller-`, `admin-`, `oz-card-index`, `oz-tab-wrap`
- Flash zprávy: Vždy česky, emoji prefix pro typ: `✓ Hotovo`, `⚠ Chyba`, `✗ Nelze`, `🗑 Smazáno`
- AJAX endpointy: vždy `header('Content-Type: application/json; charset=UTF-8');` + `JSON_UNESCAPED_UNICODE`
- JS funkce v leads.php: prefix `oz` (např. `ozSubmit`, `ozConfirm`)

---

## 17. Změny od minulé verze dokumentace (2026-04-27 → 2026-04-29)

### Přidáno
- Helper `crm_normalize_ico()` v `helpers/url.php`
- Tabulka `oz_contact_actions` (Pracovní deník)
- Tabulka `oz_tab_prefs` (per-user UI)
- Sloupec `closed_at` v `oz_contact_workflow`
- BackofficeController + view backoffice/index.php (BO Workspace)
- ARES proxy endpoint `getAresLookup`
- Drag & drop pořadí tabů
- Tab Šance (workflow stav SANCE)
- Tab Dokončené (UZAVRENO + filtr měsíců)
- Stav BO_VPRACI (BO převzal — odděleně od BO_PREDANO)
- Editace údajů kontaktu (firma/tel/email/IČO/adresa)
- Custom confirm dialog (`ozShowConfirm`) místo browser `confirm()`
- Měsíční filtr v Caller tabech (navolane, prohra, chybny, chybne_oz)
- Volitelné datum callbacku ("Bez data")
- Vylepšení UX: header block, oddělené karty, číslování karet, hover efekty
- Barevné rozlišení autorů v deníku (OZ modrá, BO zelená, Vráceno OZ zlatá)
- Předat BO inline dialog (když chybí ID nabídky)
- Znovu předat BO z Dokončené (vrátit do procesu)

### Změněno
- Tab "smlouva" zrušen → SMLOUVA spadá do `bo`
- Tab "obvolano" zrušen → OBVOLANO/ZPRACOVAVA spadají do `nove`
- Tab "callback" → callback typo opraveno (dříve `callbacky`)
- Předáno BO tab přejmenován z "U BO" → "Předáno BO"
- Tlačítko "Smlouva" odstraněno z OZ akčního řádku (BO uzavírá)
- Tlačítko "Obvoláno" odstraněno z OZ akčního řádku
- Operátor (TM/O2/VF) bez barevného badge → neutrální text
- Pracovní deník u OZ viditelný jen v BO_VRACENO + UZAVRENO
- Po `lead-status` redirect zůstává OZ na původním tabu (kontakt se přesune sám)
- Stabilní řazení (`started_at` ASC) — karty po update neskáčou nahoru
- Po Předat BO se kontakt objeví v BO **K přípravě** (ne V práci) — BO klikne **🔧 Začít zpracovávat**
- BO Pracovní deník default rozbalený (vidí historii hned)

### Vyřešené chyby
- Caller `chybne_oz` — chybělo `f.oz_comment` v SELECTu
- Caller textarea předvyplněná předchozím komentářem (po reloadu zůstávala)
- Caller tab `callback` vs `callbacky` typo (validation neprobíhalo)
- Flagged kontakty se zobrazovaly v `navolane` tabu Caller (duplicitně)
- OZ X/Y dropdown v "Předat výhru" — chyběl `FOR_SALES` ve filtru received
- OZ-strana `oz_comment` textarea taky předvyplněná
- ID nabídky panel se zobrazoval i když byl prázdný
- Card-index `#X/Y` se kryl s "Chybný lead" tlačítkem (přesunut na visačku)
- Pracovní deník po přidání skákal celá karta nahoru (řazení podle updated_at)
- Callback badge se zobrazoval i u kontaktů, kde už callback neměl smysl

---

## 18. Pro nového kolegu — co vědět nejdřív

1. **Otevřít `oz/leads.php`** — to je nejdůležitější view, ~2700 řádků. Zde se odehrává 80% interakce.
2. **Otevřít `OzController.php`** — všechny POST endpointy + getIndex se SQL filtry tabů.
3. **Mrknout na `Router.php`** — vidět všechny routy v jedné rovině.
4. **Pochopit workflow stavy** kontaktu (sekce 7 této dokumentace).
5. **Spustit lokálně** přes PHP built-in server (sekce 13).
6. **Vytvořit testovací uživatele** přes `/admin/users` — jeden na každou roli (caller, OZ, BO, admin).
7. **Importovat pár kontaktů** přes `/admin/import` — CSV formát, vzorové soubory v `storage/imports`.

**Nikdy NEMĚNIT existující logiku bez pochopení** — projekt je solidní, ale závislosti mezi role-kontroly, CSRF, sessions a workflow stavy jsou propletené. Vždy postup: Read tool → pochopit → krátký plán → implementovat → ověřit.

**Když je něco nejasné — zeptat se vlastníka projektu (Aines).** CLAUDE.md říká explicitně: "Never delete or change existing logic without approval".

---

## 19. Kontakty / vlastník

- **Vlastník projektu:** ainestol@gmail.com
- **Stack:** PHP 8.x, MariaDB, vanilla JS — žádný framework, žádný Composer
- **Filozofie:** simplicity & performance, žádné externí závislosti pokud to jde

Hodně zdaru s pokračováním! 🚀
