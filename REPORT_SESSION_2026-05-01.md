# Session Report — 2026-05-01

> Krátký, výstižný přehled co bylo v této session uděláno a kde co je.
> Pro detailní dokumentaci viz `CRM_DOKUMENTACE.md` a `CRM_PROJEKT_SOUHRN.md`.

---

## 1. TL;DR — co aplikace dělá

Vlastní CRM pro telemarketingovou firmu (B2B prodej internetu + Vodafone mobilů).
Čistý PHP 8.x + MariaDB + vanilla JS. Žádný framework, žádný Composer.

**Hlavní tok kontaktu (lead lifecycle):**

```
Import CSV/XLSX
   ↓
Čistička ověří kontakty  → ⚠ Sdílený telefon? Renewal? Goal counter
   ↓
Navolávačka volá          → ⚠ Caller warning při sdíleném tel.
   ↓
OZ pracuje s leadem       → tabs: Nové, Zpracovávám, Schůzka, Callback,
                                   Smlouva, BO, Šance, Reklamace, Nezájem
   ↓
Předáno do Back-office    → BO postup: Příprava, UBot, Datovka, Podpis
   ↓
Uzavřeno (provize OZ)
```

---

## 2. Architektura — entry points

```
Browser → public/index.php (front controller, .htaccess rewrite)
          ↓
       app/Router.php  (definice rout + dispatch)
          ↓
       app/controllers/*Controller.php
          ↓ (volá render)
       app/views/{section}/{page}.php  (ob_start → require → ob_get_clean)
          ↓
       app/views/layout/base.php  (společný layout + header)
```

**Klíčové helpery (vyhledávat v `app/helpers/`):**

| Helper | Použití |
|---|---|
| `crm_h($s)` | Escape pro HTML output (vždy!) |
| `crm_url('/cesta')` | URL builder (řeší prefix + dev-server) |
| `crm_csrf_token()` / `crm_csrf_validate()` | CSRF na všech POST |
| `crm_csrf_field_name()` | Jméno pole pro hidden CSRF input |
| `crm_redirect('/path')` | PRG redirect |
| `crm_flash_set('msg', 'success')` / `crm_flash_take()` | Flash zprávy |
| `crm_require_user()` / `crm_require_roles($u, ['admin'])` | Middleware |
| `crm_db()` | PDO singleton |
| `crm_db_log_error($e, $context)` | Logování PDOException (ne tichý catch) |
| `crm_region_label('PRG')` | Mapping zkratek krajů |

---

## 3. Role uživatelů

| Role | Co vidí/dělá |
|---|---|
| `superadmin` / `majitel` | Vše: import, kvóty, statistiky, admin sekce |
| `navolavacka` | Volá kontakty, předává leady OZ |
| `cisticka` | Ověřuje operátory kontaktů, cíle podle krajů |
| `obchodak` | OZ pracovní plocha (`/oz/leads`) — workflow leadů |
| `backoffice` | BO sekce — vrací nebo dokončuje smlouvy |

---

## 4. Workflow stavy (sloupec `oz_contact_workflow.stav`)

```
NEW          ← import (čerstvý kontakt)
READY        ← čistička ověřila, připravený k volání
VF_SKIP      ← čistička přeskočila (chybný / Veřejně-Foulé)
ASSIGNED     ← navolávačka přidělila OZ
CALLED_OK    ← úspěšný hovor
CALLED_BAD   ← špatný hovor (ne lead)
NEDOVOLANO   ← nedovoláno (zkusit znovu)
NEZAJEM      ← zákazník odmítl
CALLBACK     ← naplánován callback (datum)
SCHUZKA      ← naplánována schůzka (datum)
NABIDKA      ← OZ připravil nabídku
SANCE        ← lead "warm" (sleduje se v Šance tabu, gold barva)
FOR_SALES    ← předáno OZ (z navolávačky)
SMLOUVA      ← OZ podepsal smlouvu (BMSL částka)
BO_PREDANO   ← OZ poslal BO ke zpracování
BO_VPRACI    ← BO právě zpracovává
BO_VRACENO   ← BO vrátil OZ k doplnění
UZAVRENO     ← Smlouva podepsaná & aktivovaná (provize OZ)
IZOLACE      ← OZ odložil (drží stranou)
```

**Render barev v UI**: definováno přes CSS proměnné v `public/assets/css/oz_leads.css`
(`--oz-nove`, `--oz-schuzka`, `--oz-bo`, atd.). Šance má vlastní zlatou `#d4a017`.

---

## 5. Klíčové DB tabulky

| Tabulka | Co tam je |
|---|---|
| `users` | Uživatelé + role + must_change_password |
| `contacts` | Hlavní pool kontaktů (firma, IČO, telefon, region) |
| `oz_contact_workflow` | Stav leadu u OZ (FK contact_id) + schuzka_at, callback_at, BMSL, BO progress checkboxy |
| `oz_contact_notes` | Historie poznámek OZ (chronologicky per kontakt) |
| `oz_contact_actions` | Pracovní deník OZ (řádky s datem + textem) |
| `contact_oz_flags` | Reklamační flagy + chybný lead |
| `workflow_log` | Audit log: kdo/kdy/co změnil |
| `oz_targets` | Kvóty OZ per region per měsíc |
| `cisticka_region_goals` | Cíle čističky per kraj (kumulativní counter) |
| `caller_pending_leads` | Předané, zatím nepřijaté leady (pending sidebar) |
| `import_jobs` / `import_csv_files` | Tracking importů |

---

## 6. Co bylo uděláno v této session (chronologicky)

### Bezpečnostní opravy & infrastruktura
- **AES-CBC → AES-GCM** s backward-compat (`config/constants.php`, prefix `v2:`)
- **Tiché PDOException → logované** přes `crm_db_log_error()`
- **CSV cleanup script** — `tools/cleanup_imports.php`
- **must_change_password enforcement** v `app/helpers/middleware.php`
- **storage/.htaccess** — deny all
- **Audit report** — `AUDIT_PROJEKTU_2026-04-29.txt`

### Import CSV/XLSX (dvoufázový)
- **`app/helpers/import_xlsx.php`** — streaming XLSX parser bez Composeru (ZipArchive + XMLReader)
  - Workbook order pro detekci prvního sheetu
  - Auto-skip empty/title rows
  - Kritická oprava: removed `next()` calls v iteraci elementů
- **`app/controllers/AdminImportController.php`** — dvoufázový flow:
  - `postImport` → analyze (CSV nebo XLSX) → uložit do `import_csv_files`
  - `getPreview` → ukázat duplicity, errors, statistiky
  - `postCommit` / `postCancel` → potvrdit/zrušit
- **`app/views/admin/import/preview.php`** — UX redesign:
  - Strategy cards: merge_smart / keep_all / skip_all
  - Per-row dropdowns + bulk actions
  - Side-by-side snapshot comparison
- **`app/views/admin/import/form.php`** — drag-drop + progress bar
- Per-row override pro DB i file duplicity
- Merge action: spojit phones + zachovat starší kontakt

### Čistička UX & cíle
- **DISTINCT contact_id** ve statistice (předtím počítalo každý workflow_log)
- **Aktivní řádek + auto-advance + zkratky** (Q/W/E + Space pro kontrolu)
- **Bigger name+phone** + collapse regions
- **`app/controllers/CistickaController.php`**:
  - `loadRegionGoalsWithProgress()` — kumulativní counter (bez time-filtru)
  - `getAdminGoals()` / `postAdminGoals()` — admin nastavení cílů
  - `enrichSharedPhoneInfo()` — caller warning při sdíleném tel.
- **`app/views/admin/cisticka_goals.php`** (NOVÉ) — admin panel cílů
- **`sql/fix_cisticka_goals_backfill.sql`** — backfill pro existující data

### OZ pracovní plocha — feature & polish
- **Šance tab**: gold barva `#d4a017`
- **BO sidebar — věž s přesahem** (analogicky pending stack)
- **Scroll preservation (varianta C)** — anchor + sessionStorage Y
- **BO popover** s výpisem firem
- **Renewal stack** — zelená věž pod pending (blížící se konec smluv)
  - `app/views/oz/leads.php` query v `OzController.php`
  - Test seed: `tools/seed_renewal_test.php`
- **Kompaktní BO postup** — 4 checkboxy na jednom řádku (bez collapse)
- **Pořadí**: Příprava → UBot → Datovka → Podpis
- **BMSL badge** — zobrazuje se v Smlouva + BO_VPRACI
- **VF skip fix** — BO_VRACENO → BO_PREDANO bez poznámky (data-optional="1")
- **Předat BO fix** — XHR + flash hlášky správně
- **Pending leads jen v sidebaru**, ne automaticky v Nové
- **Dashboard role-grouped** — řádky čistička/navolávačky/OZ/BO

### Refactor `app/views/oz/leads.php` (toto bylo cílem této session)
**Fáze 1 — CSS extrakce:**
- `<style>` blok (řádky 55-1029, 974 řádků) → `public/assets/css/oz_leads.css`
- View nahrazuje block jediným `<link rel="stylesheet" href="...">` tagem

**Fáze 2 — JS extrakce s PHP-bridge:**
- Pure JS (řádky 2380-3346, 967 řádků) → `public/assets/js/oz_leads.js`
- PHP-bridge inline `<script>` definuje `window.OZ_CONFIG`:
  ```js
  window.OZ_CONFIG = {
      userId: <?= (int)$user['id'] ?>,
      csrf: ..., csrfField: ...,
      urls: { callerSearch, ozRaceJson, ozAresLookup,
              ozTabReorder, boCheckboxToggle, ozCheckboxToggle }
  };
  ```
- External `<script src="...oz_leads.js"></script>` po bridge
- Deaktivovaný blok `<?php if(false){?><script>...</script><?php}?>` zůstal inline
  (PHP-templated `crm_offered_services_catalog()` potřebuje PHP-context)
- Inline data injection (`window._ozRenewals`, `_ozPending`, `_ozBoData`) zůstaly v PHP

**Fáze 3 — HTML partials:** *odložena*
- Inner contact-card render loop má komplexní `$variable` scope
- Riziko vs. zisk nevychází — phases 1+2 už zredukovaly soubor o 37%
- Pro budoucí refactor: zvážit jen safe extrakce (`_modal.php`, `_pending_sidebar.php`)

**Statistiky refactoru:**
| | Před | Po Fázi 1 | Po Fázi 2 |
|---|---|---|---|
| Řádků v `oz/leads.php` | ~4060 | ~3086 | ~2551 |
| % redukce | 0% | -24% | **-37%** |

---

## 7. Kde co najdete (rychlá navigace)

```
E:\Snecinatripu\
├── public/                                  ← webroot
│   ├── index.php                            ← FRONT CONTROLLER (vše jde sem)
│   ├── dev-router.php                       ← pro PHP built-in server
│   ├── .htaccess                            ← Apache rewrites
│   └── assets/
│       ├── css/
│       │   ├── app.css                      ← globální CSS
│       │   └── oz_leads.css                 ← NOVÉ (Phase 1 extrakce)
│       └── js/
│           └── oz_leads.js                  ← NOVÉ (Phase 2 extrakce)
│
├── app/
│   ├── Router.php                           ← všechny routes
│   ├── controllers/
│   │   ├── OzController.php                 ← /oz/* — pracovní plocha OZ
│   │   ├── BoController.php                 ← /bo/* — back-office
│   │   ├── CallerController.php             ← /caller/* — navolávačka
│   │   ├── CistickaController.php           ← /cisticka/* — ověřování
│   │   ├── AdminImportController.php        ← /admin/import/* — XLSX/CSV
│   │   ├── AccountController.php            ← /account/* — heslo (must_change)
│   │   ├── DashboardController.php          ← / — homepage podle role
│   │   └── ...
│   │
│   ├── views/
│   │   ├── layout/
│   │   │   └── base.php                     ← společný layout (header, logout)
│   │   ├── oz/
│   │   │   └── leads.php                    ← OZ pracovní plocha (refactorováno)
│   │   ├── cisticka/
│   │   │   └── index.php                    ← čistička UX + goals panel
│   │   ├── admin/
│   │   │   ├── cisticka_goals.php           ← NOVÉ admin panel cílů
│   │   │   └── import/
│   │   │       ├── form.php                 ← drag-drop upload
│   │   │       └── preview.php              ← strategy cards + per-row override
│   │   └── ...
│   │
│   └── helpers/
│       ├── audit.php                        ← workflow_log + crm_db_log_error
│       ├── middleware.php                   ← crm_require_user, must_change_pwd
│       ├── csrf.php                         ← crm_csrf_*
│       ├── flash.php                        ← crm_flash_*
│       ├── url.php                          ← crm_url, crm_h
│       ├── import_xlsx.php                  ← NOVÉ: streaming XLSX parser
│       └── ...
│
├── config/
│   └── constants.php                        ← AES-GCM (v2: prefix), DB creds
│
├── sql/
│   ├── fix_cisticka_goals_backfill.sql      ← NOVÉ
│   ├── truncate_contacts.sql
│   └── ...
│
├── storage/
│   ├── .htaccess                            ← deny all (NOVÉ)
│   ├── csv_imports/                         ← user uploads (auto-cleanup)
│   └── workflow_log_errors.log              ← PDOException log
│
├── tools/
│   ├── cleanup_imports.php                  ← NOVÉ: CSV retention
│   ├── reset_contacts.php                   ← NOVÉ: CLI reset
│   └── seed_renewal_test.php                ← NOVÉ: test data
│
├── CRM_DOKUMENTACE.md                       ← ★ Komplexní dokumentace
├── CRM_PROJEKT_SOUHRN.md                    ← Stručný souhrn
├── CHANGELOG_SESSION.md                     ← Session changelog
├── AUDIT_PROJEKTU_2026-04-29.txt            ← Audit report
└── REPORT_SESSION_2026-05-01.md             ← TENTO soubor
```

---

## 8. Nejčastější úkoly — jak začít

### Přidat nový tab do OZ pracovní plochy
1. Definuj klíč v `app/Router.php` (mapování stav → tab key)
2. CSS: `.oz-tab--newkey` + `.oz-contact--newkey` v `public/assets/css/oz_leads.css`
3. Color variable v `:root`
4. Optional: přidat do super-tab dropdownu (Plán nebo BO)
5. Default order v `OzController::loadTabOrder()`

### Přidat nový stav do workflow
1. SQL: `ALTER TABLE oz_contact_workflow MODIFY stav ENUM('...','NEW_STATE','...')`
2. Workflow validace: `OzController::validStates()`
3. Tab mapování: `OzController::stateToTab()`
4. Badge CSS: `.badge--newstate` v `oz_leads.css`
5. Button v body view + akce v controller (`postSetState`)

### Přidat nového helpera
1. Vytvořit `app/helpers/{name}.php` s `crm_*` prefixem
2. Require v `public/index.php` (autoload section)
3. `declare(strict_types=1)` na první řádek

### Debug PDO chyb
- `tail -f E:\Snecinatripu\storage\workflow_log_errors.log`
- Hledat `crm_db_log_error` calls v controllers (nahradí silent catches)

### Reset DB pro testování
- `php tools/reset_contacts.php` (CLI, opt-in flag)
- Nebo HeidiSQL: `SOURCE E:/Snecinatripu/sql/truncate_contacts.sql;`

### Změnit barvy / vizuální stav OZ tabu
- `public/assets/css/oz_leads.css` → `:root` proměnné nebo `.oz-tab--*` třídy
- Nepouštět JS změny — barvy jsou všechny CSS

### Přidat URL do OZ_CONFIG bridge (pro JS)
1. `app/views/oz/leads.php` (kolem řádku 2385) — přidat do `urls: {...}`
2. `public/assets/js/oz_leads.js` — použít jako `OZC.urls.{newKey}`

---

## 9. Otevřené možnosti (pokud budou potřeba)

- **Phase 3 HTML partials** — pokud se file ještě zvětší, lze bezpečně extrahovat:
  - `_modal.php` (custom confirm — 14 řádků)
  - `_pending_sidebar.php`, `_bo_sidebar.php` — well-bounded
  - Card render loop *NE* — komplexní state, vysoké riziko
- **Composer** pro autoload — momentálně manual require v `public/index.php`
- **PHPStan/Psalm** — static analysis (deklarace v `declare(strict_types=1)` jsou už OK)
- **Unit testy** — žádné momentálně; PHPUnit by se hodil pro `crm_*` helpery
- **Cron jobs** — `tools/cleanup_imports.php` zatím manual, lze nahodit Windows Task Scheduler
- **Email notifikace** — schůzky/callback zatím jen v UI (alert banner)

---

## 10. Quick troubleshooting

| Symptom | Pravděpodobná příčina | Kde hledat |
|---|---|---|
| "Chyba při ukládání" v OZ | PDO exception | `storage/workflow_log_errors.log` |
| Karta neskáče po stav-change | scroll preservation broken | `oz_leads.js` `ozScrollPreservation()` |
| BO progress checkbox nefunguje | OZ_CONFIG.urls.boCheckboxToggle missing | view řádek ~2390, `oz_leads.js` `ozCheckboxToggle()` |
| Goals counter ukazuje 0 | `goal_started_at` v budoucnu | `sql/fix_cisticka_goals_backfill.sql` |
| Sdílený telefon warning chybí | `enrichSharedPhoneInfo()` neběží | `CallerController.php` |
| Renewal stack prázdný | žádné kontakty s `vyrocni_smlouvy` v +90 dnech | `OzController::loadRenewals()` |
| XLSX parser čte jen půlku řádků | `next()` v iteraci (už opraveno) | `app/helpers/import_xlsx.php` |

---

**Konec session reportu.** Pro detail viz `CRM_DOKUMENTACE.md`.
