# 📚 HANDOVER — Šneci na tripu CRM

> **Účel dokumentu:** Tento soubor je „onboarding" pro každého nového pomocníka (AI nebo člověka), který přebírá vývoj. Po jeho přečtení musíš znát: tech stack, role, pipeline, klíčové konvence, deploy workflow a aktuální stav projektu.

**Vlastník:** Aines (ainestol@gmail.com) · superadmin · komunikuje česky
**Úroveň:** intermediate dev — chce praktické vedení, ne hluboké framework teorie

---

## 1) 🎯 Co je tohle za projekt

CRM pro **telemarketing + obchod (Vodafone partner)**. Custom PHP, žádný framework. Tým:
- **Aines** (superadmin / dev) — vlastník + správce
- **Navolávačky** (caller role) — telefonuji leady, předají OZ
- **Čističky** (cisticka role) — předtřídí kontakty: kterého operátora má klient (TM/O2/VF) nebo zda je číslo chybné, pak posílá na navolávačky
- **OZ / obchodáci** (obchodak role) — domlouvají schůzky, uzavírají smlouvy
- **Backoffice** (backoffice role) — administrativa, papíry
- **Majitel** (majitel role) — výsledky, statistiky, kvóty

**Klient u nás žádá novou smlouvu o telekomunikačních službách.** Vodafone (VF) ho odmítne (zákazník je už náš = duplicita), TM/O2 jsou potenciální noví.

**Doména:** https://snecinatripu.eu
**Repo:** github.com/Ainestol/crm-system (private)

---

## 2) 🛠 Tech stack

| Vrstva | Technologie |
|---|---|
| PHP | **8.3** (FastCGI / php-fpm) |
| DB | **MariaDB 10.11** (utf8mb4_unicode_ci) |
| Webserver | nginx (prod) |
| Local dev | Laragon (Windows) |
| Prod OS | Ubuntu 24.04 |
| Frontend | Vanilla JS, **Grid.js** (datagrid), občas Chart.js, QRcode.js z CDN |
| Auth | Session-based + 2FA (TOTP) + trusted device cookies |
| Šifrování | argon2id (passwords), sodium (TOTP secrets) |

**Žádný framework.** Žádný npm/composer build step pro front-end. CSS je 1 soubor (`public/assets/css/app.css`). JS je často inline v views, jinde sdílené přes script tagy.

---

## 3) 📁 Struktura projektu

```
E:\Snecinatripu\                    # local (Windows)
/var/www/crm/                       # production (Ubuntu)
├── app/
│   ├── Router.php                  # 700+ route definitions (jedna pole)
│   ├── bootstrap.php               # konstanty, autoload pomocníků
│   ├── controllers/
│   │   ├── LoginController.php
│   │   ├── DashboardController.php
│   │   ├── ProfileController.php   # 2FA setup/disable
│   │   ├── CallerController.php    # navolávačka workspace
│   │   ├── CistickaController.php  # čistička workspace (Plocha 1)
│   │   ├── PremiumCistickaController.php  # Plocha 2 (premium)
│   │   ├── PremiumOrderController.php     # OZ vytvoří premium objednávku
│   │   ├── PremiumCallerController.php    # caller volá premium
│   │   ├── OzController.php        # OZ panel
│   │   ├── BackofficeController.php
│   │   ├── AdminUsersController.php
│   │   ├── AdminImportController.php  # /admin/import (2-phase preview/commit)
│   │   ├── AdminDatagridController.php  # /admin/datagrid (Grid.js)
│   │   ├── AdminOzTargetsController.php  # /admin/oz-targets (kvóty)
│   │   ├── AdminTeamStatsController.php  # /admin/team-stats
│   │   ├── AdminPremiumOverviewController.php
│   │   ├── AdminOzStagesController.php
│   │   ├── AdminOzMilestonesController.php
│   │   ├── ContactProposalsController.php
│   │   └── AccountController.php   # /account/password
│   ├── helpers/
│   │   ├── auth.php                # crm_auth_* + crm_trusted_device_* + crm_2fa_*
│   │   ├── session.php
│   │   ├── encryption.php          # crm_encrypt/crm_decrypt (sodium)
│   │   ├── totp.php                # RFC 6238 (Google Authenticator compatible)
│   │   ├── csrf.php                # crm_csrf_token / validate / field_name
│   │   ├── html.php                # crm_h (htmlspecialchars wrapper)
│   │   ├── url.php                 # crm_url, crm_redirect, crm_request_uri_path
│   │   ├── flash.php               # crm_flash_set / take
│   │   ├── audit.php               # crm_audit_log
│   │   ├── users_admin.php         # crm_region_label, crm_region_label_short, crm_region_choices
│   │   ├── commissions.php         # provize výpočet (zatím stub)
│   │   ├── sms.php                 # scaffolding (sms_send_if_enabled, SmsProviderInterface)
│   │   ├── import_csv.php          # import helpery (normalizace IČO, region, ...)
│   │   ├── import_xlsx.php         # XLSX → CSV streaming převod
│   │   ├── workflow_log.php
│   │   └── api_auth.php
│   └── views/
│       ├── layout/base.php         # ⚠ KLÍČOVÉ — všechny views přes něj (sidebar+topbar)
│       ├── login/                  # form, two_factor, select_role
│       ├── profile/                # 2fa_setup, 2fa_done, 2fa_disable
│       ├── cisticka/               # index (Plocha 1) + premium/ (Plocha 2)
│       ├── caller/                 # navolávačka panel
│       ├── oz/                     # OZ panel + premium/ + payout
│       ├── backoffice/
│       └── admin/
│           ├── import/             # form (upload), preview (s breakdown duplicit)
│           ├── datagrid/index.php  # Grid.js power view
│           ├── oz_targets.php      # kvóty obchodáků per region
│           ├── premium/overview.php
│           ├── feed/index.php      # activity feed
│           ├── team_stats.php
│           └── users/              # CRUD uživatelů
├── public/
│   ├── index.php                   # ⚠ Front controller — registrace všech kontrolerů
│   ├── assets/css/app.css          # ⚠ Jediný CSS file, ~2000+ řádků
│   └── assets/js/                  # málo, většinou inline v views
├── sql/migrations/                 # 003-015 (chronologické čísla)
├── config/
│   ├── constants.php               # CRM_STORAGE_PATH, CRM_CONFIG_PATH, ...
│   ├── db.php                      # DB credentials (encrypted)
│   └── sms.php                     # SMS provider config (zatím stub)
├── storage/
│   └── imports/imp_<hex>/          # dočasné soubory importu
└── HANDOVER.md                     # 👈 tento soubor
```

---

## 4) 🗄 Databázové schéma (klíčové tabulky)

### `contacts` — hlavní entita (firmy / lidi co volaceme)
```
id, ico, firma, adresa, telefon (VARCHAR 200), email,
operator (TM/O2/VF nebo NULL),
region (kód, např. 'praha', 'stredocesky'),
stav (NEW / READY / VF_SKIP / CHYBNY_KONTAKT / CALLED_OK / CALLED_BAD /
      CALLBACK / NEZAJEM / NEDOVOLANO / IZOLACE / FOR_SALES / DONE / UZAVRENO),
poznamka (TEXT), rejection_reason (VARCHAR — 'nezajem' / 'cena' / 'ma_smlouvu' / 'spatny_kontakt' / 'jine'),
nedovolano_count (TINYINT — po 3 pokusech auto → NEZAJEM),
assigned_caller_id, assigned_sales_id, locked_by, locked_until,
callback_at, datum_volani, datum_predani,
narozeniny_majitele, vyrocni_smlouvy, sale_price, activation_date,
prilez (obchodní příležitost — volný text),
oznaceno (TINYINT — pinned), dnc_flag, created_at, updated_at
```

### `users`
```
id, jmeno, email (UNIQUE), heslo_hash (argon2id),
role (ENUM: superadmin/majitel/navolavacka/obchodak/backoffice/cisticka/cistic),
roles_extra (JSON array — multi-role),
primary_region, aktivni (0/1),
totp_secret (VARBINARY 512, encrypted), totp_enabled (0/1),
must_change_password, created_at, deactivated_at, created_by
```

### `user_regions` — které kraje OZ obsluhuje
```
user_id, region (PK + FK)
```

### `workflow_log` — audit trail změn stavu kontaktu
```
id, contact_id, user_id, old_status, new_status, note, created_at
```

### `oz_contact_workflow` — pipeline řádek per OZ × kontakt
```
id, contact_id, oz_id, stav (FOR_SALES/SCHUZKA/NABIDKA/SANCE/UZAVRENO/...),
cislo_smlouvy, datum_uzavreni, smlouva_trvani_roky,
podpis_potvrzen, podpis_potvrzen_at, podpis_potvrzen_by,
started_at, closed_at, stav_changed_at, updated_at
```

### `premium_orders` + `premium_lead_pool` — premium pipeline
```
premium_orders (header):
  id, oz_id, year, month, requested_count, reserved_count,
  price_per_lead, caller_bonus_per_lead, preferred_caller_id,
  regions_json, status (open/cancelled/closed), note,
  paid_to_cleaner_at/by, paid_to_caller_at/by,
  accepted_by_cleaner_id, accepted_at, created_at

premium_lead_pool (lines):
  id, order_id, contact_id (UNIQUE), oz_id, cleaner_id, caller_id,
  cleaning_status (pending/tradeable/non_tradeable),
  call_status (pending/success/failed),
  flagged_for_refund, flag_reason, flagged_at,
  reserved_at, cleaned_at, called_at
```

### Další podpůrné tabulky
- `auth_trusted_devices` — 30-denní cookie tokeny (SHA256 hash) + reverify_at 7 dnů
- `totp_backup_codes` — 8 backup kódů per user (SHA256, used_at)
- `oz_targets` — kvóty OZ per region per měsíc
- `cisticka_region_goals` — cíle čističky per region
- `cisticka_rewards_config` — sazba (default 0,70 Kč)
- `caller_rewards_config` — sazba navolávačky
- `monthly_goals`, `daily_goals`, `oz_personal_milestones`, `oz_team_stages`
- `commissions`, `commission_tiers_sales`, `commission_tiers_company`
- `contact_notes`, `contact_quality_ratings`, `contact_oz_flags`, `contact_proposals`
- `dnc_list` (do-not-call), `import_log`, `audit_log`, `announcements`, `alerts`
- `sms_log` (zatím nepoužívané), `note_templates`, `password_resets`

### Migrace
Aktuální stav: **015** je poslední (auth_trusted_devices). Když přidáváš novou:
- `sql/migrations/016_*.sql`
- Idempotentní (`IF NOT EXISTS`, `MODIFY COLUMN`, try-catch pro ADD COLUMN)
- Spustit: `sudo mariadb crm < sql/migrations/016_*.sql`

---

## 5) 🎭 Role & pipeline (standard)

```
IMPORT → NEW
   │
   ▼
ČISTIČKA (Plocha 1: /cisticka)  ──┬─→ READY (TM/O2)         → navolávačka
   │ klávesy 1/2/3/4              ├─→ VF_SKIP                → konec (nečistit znova)
   │                               └─→ CHYBNY_KONTAKT         → konec (zahraniční, blbosti)
   ▼
NAVOLÁVAČKA (/caller) ──┬─→ NEZAJEM                          → konec
                         ├─→ NEDOVOLANO (3× → auto NEZAJEM)   → caller queue
                         ├─→ CALLBACK (+ callback_at)         → caller queue
                         ├─→ CALLED_BAD                       → konec
                         ├─→ CALLED_OK                        → konec
                         └─→ FOR_SALES                        → OZ
                                │
                                ▼
                         OZ (/oz) ──┬─→ SCHUZKA / NABIDKA / SANCE  → continues
                                    ├─→ UZAVRENO (+ datum_uzavreni)→ BO
                                    └─→ REJECTED_BY_SALES          → konec
                                            │
                                            ▼
                         BO (/backoffice) ──┬─→ DONE / ACTIVATED
                                            ├─→ BO_VRACENO (vrátí OZ)
                                            └─→ CANCELLED
```

### Klíčové stavy

| Stav | Význam | Kdo nastavuje |
|---|---|---|
| `NEW` | Po importu, čeká na čističku | systém (import) |
| `READY` | Pročištěno (TM/O2), čeká na callera | čistička |
| `VF_SKIP` | Vodafone zákazník → nevoláme | čistička |
| `CHYBNY_KONTAKT` | Zahraniční / nesmyslné číslo | čistička / caller |
| `CALLBACK` | Naplánovaný hovor (callback_at) | caller |
| `NEZAJEM` | Klient odmítl | caller |
| `NEDOVOLANO` | Nezvedl 1-2× (3× → auto NEZAJEM) | caller |
| `FOR_SALES` | Předáno OZ-ovi (zájem o smlouvu) | caller |
| `DONE` | Uzavřeno smlouvou | OZ (z import nebo manuálně) |

---

## 6) 💎 Premium pipeline (paralelní k standardu)

OZ může **objednat sadu kvalitních leadů** za zvýšenou cenu. Existují vlastní controllery + tabulky.

**Flow:**
1. **OZ** vytvoří `premium_orders` (vybere kraje, počet leadů, cenu/lead, bonus pro callera)
2. **Čistička** klikne „Přijmu objednávku" → `accepted_by_cleaner_id` se nastaví
3. **Čistička** označuje leady jako `tradeable` / `non_tradeable` (per kus 2 Kč odměna, jinak nastavená)
4. **Tradeable leady** se zařadí do separátní caller queue (`/caller/premium`)
5. **Caller** volá s vyšším bonusem (definován v `premium_orders.caller_bonus_per_lead`)
6. **OZ** dostane výsledné leady do svého panelu jako FOR_SALES
7. **Admin platí** přes „mark paid" tlačítka → `paid_to_cleaner_at` / `paid_to_caller_at`

**Důležité:** Premium leady jsou **vyloučené** ze standardní caller queue přes `NOT EXISTS` filter. Tedy nezpůsobí dvojité počítání.

---

## 7) 👥 Multi-role

User může mít **víc rolí** (např. majitel + obchodak). Mechanismus:

```
users.role            = primární role (=DB default, "ta hlavní")
users.roles_extra     = JSON array dalších rolí: ["obchodak", "backoffice"]
```

**Login flow:**
1. User zadá heslo (+2FA pokud zapnuté)
2. Pokud má `count(allRoles) > 1` → redirect na `/login/select-role`
3. User vybere → uloží se do session `CRM_SESSION_ACTIVE_ROLE`
4. Volitelně cookie `CRM_PREFERRED_ROLE_COOKIE` (1 rok, pro auto-výběr příště)
5. `crm_auth_current_user()` injektuje `$user['role'] = aktivní role`
6. Topbar má tlačítko **„🔄 Přepnout roli"** pro switch

**Důležité — bug ošetřený:** `crm_user_all_roles()` MUSÍ číst `$user['primary_role']` ne `$user['role']` (protože role je už po injekci aktivní). Bez toho user nemůže přepnout zpět na primární roli.

**Dotaz pro multi-role pickup:**
```sql
WHERE aktivni = 1 AND (
    role = 'obchodak'
    OR JSON_CONTAINS(IFNULL(roles_extra, '[]'), '"obchodak"')
)
```

---

## 8) 🔐 2FA + důvěryhodná zařízení

**Setup:** `/profile/2fa/setup` → naskenuje QR (otpauth URI) v Google Authenticator → zadá první kód → uloží secret + vygeneruje **8 backup kódů**.

**Login flow s 2FA:**
1. Heslo OK
2. Pokud `totp_enabled=1` → redirect `/login/two-factor`
3. Zadá 6-místný kód (nebo backup kód)
4. Po úspěchu se zaškrtne **„Důvěřovat zařízení 30 dní"** (default)
5. Vystaví se cookie `crm_trusted_device` (64 hex znaků) + DB záznam (SHA256 hash)
6. **Návrat do 7 dnů** → auto-login
7. **Návrat 7-30 dnů** → reverify (jen 2FA kód, žádné heslo) → prodlouží reverify
8. **Návrat po 30 dnech** → plný login (heslo + 2FA)

**Logout** zruší cookie i DB záznam. **Disable 2FA** zruší **všechna** trusted devices uživatele.

**Důležité — bug ošetřený:** `totp.php` musí mít RFC 4648 standardní alphabet `ABCDEFGHIJKLMNOPQRSTUVWXYZ234567` (32 znaků). Nestandardní alphabet (29 znaků bez X/Y/Z) → Google Authenticator dekóduje jinak → kód nikdy nepasuje.

**Topbar 2FA tlačítko** (v `app/views/layout/base.php`):
- Vypnuté → `🔓 Zapnout 2FA` (oranžový dashed border)
- Zapnuté → `🔐 2FA` (modře pulzující 2.4s loop) ✨

---

## 9) 📥 Import kontaktů (2-phase)

`/admin/import` (jen majitel/superadmin).

**Phase 1 — upload + analyze:**
- POST `/admin/import` → uloží do `storage/imports/imp_<hex>/data.csv`
- XLSX se streaming převede přes `XMLReader`
- Analýza projde řádky, detekuje: chyby, duplicity v souboru, duplicity v DB, DNC hits
- Per-typ breakdown duplicit (IČO/Tel/Email)
- Uloží `preview.json` v importDir

**Phase 2 — preview UI:**
- `/admin/import/preview?id=imp_<hex>`
- Per-řádek dropdowny pro duplicity: merge/add/skip/update
- Global volby: file strategy (merge_smart default) + DB strategy (skip default)
- Filter tlačítka „Vše | IČO | Telefon | Email"
- Submit → POST `/admin/import/commit`

**Phase 3 — commit:**
- Stream čte CSV znova, aplikuje volby per řádek
- Batch INSERT 500 řádků najednou
- Merge queue (post-processing pro `merge` akce — telefon i email s `;` separátorem, max 6 každého)

**Podporované sloupce (aliasy):**

| Excel hlavička | DB sloupec |
|---|---|
| `firma` / `nazev_firmy` / `subject_name` / `subject` / `jmeno` / `name` | `firma` |
| `ico` / `ic` / `ičo` | `ico` |
| `telefon` / `mobil` / `mobile` / `tel` / `phone` | `telefon` |
| `email` / `e_mail` / `mail` | `email` |
| `adresa` / `ulice` | `adresa` |
| `mesto` / `municipality` / `obec` | `mesto` (→ region) |
| `kraj` / `region` | `region` (kód) |
| `operator` / `sit` / `carrier` | `operator` |
| `prilez` / `prilezitost` / `produkt` | `prilez` |
| `poznamka` / `note` | `poznamka` |
| `narozeniny_majitele` | `narozeniny_majitele` |
| `vyrocni_smlouvy` | `vyrocni_smlouvy` |
| `datum_uzavreni` | trigger UZAVRENO režim |
| `oz_email` / `obchodak_email` / `prodejce_email` / `sales_email` | resolves to `assigned_sales_id` |
| `sale_price` / `cena` / `cena_smlouvy` / `price` | `sale_price` |
| `stav` / `status` / `vysledek` / `ne_chce` / `nechce` / `chce` | `stav` |
| `dne` / `datum_volani` / `volano_dne` | `datum_volani` |
| `navolavacka` / `caller_name` / `volala` | prefix do `poznamka` |

**Stav mapping** (Excel hodnota → DB stav):
- `NECHCE` / `NEDOVOLAL` / `NEBERE` / `TÍPL TO` / `nezajem` → **NEZAJEM**
- `CHCE` / `rozjednany` → **FOR_SALES** (vyžaduje `oz_email`!)
- prázdné → **NEW**
- s `datum_uzavreni` → **DONE** (přepíše stav_mapped)

**Speciální logika pro uzavřené smlouvy:**
- `datum_uzavreni` vyplněno → `stav=DONE`, vyrocni_smlouvy = +3 roky (pokud nezadáno)
- `assigned_sales_id` = ID z `oz_email`
- Vznikne řádek v `oz_contact_workflow` se `stav='UZAVRENO'` a správným `oz_id`
- Statistiky aktuálního měsíce **se nezvýší** (filter dle `closed_at` = historické datum z importu)

**FOR_SALES (CHCE) leady:**
- Vznikne řádek v `oz_contact_workflow` se `stav='FOR_SALES'`
- Objeví se OZ-ovi v jeho panelu „Aktivní/Přijaté"

---

## 10) 🧹 Čistička workflow

**Plocha 1 — `/cisticka`** (standardní queue):
- 4 tlačítka per řádek: `🔴 VF` (1) / `🌸 TM` (2) / `🔵 O2` (3) / `🚫 Chybný` (4)
- Klávesa 4 = chybný (zahraniční, nesmyslné) → stav `CHYBNY_KONTAKT`
- Per-region cíle + progress bary (`cisticka_region_goals`)
- Sazba: 0,70 Kč za **každé** ověření (TM, O2, VF, i CHYBNY) — `cisticka_rewards_config`
- Stats top: TM+O2 / VF / Chybné / queue
- Klik na telefon → zkopíruje do schránky + toast

**Plocha 2 — `/cisticka/premium/order?id=N`** (premium objednávky):
- Tabulka leadů s **IČO** a **Telefon** sloupci (oba klikací → copy)
- Tlačítka per řádek: `✅ Obchod.` / `❌ Neobch.`
- Po dokončení tlačítko „Uzavřít objednávku" (cleaning_status all done → status='closed')

**Důležité — bug ošetřený:** `cistVerify` má specialní větev `if (action === 'chybny')` před kontrolou `op`. Bez toho ternární operátor padl na default = `O2` ikonu (i když backend uložil CHYBNY_KONTAKT správně).

---

## 11) 🔧 Klíčové konvence kódu

### PHP
- **Žádný PSR autoloader** — všechny kontrolery se require_once v `public/index.php`
- **Strict types** každý soubor: `declare(strict_types=1);`
- **PDO** s named placeholders (`:name`), prepared statements
- **CSRF** vyžadovaný u každého POST: `crm_csrf_validate($_POST[crm_csrf_field_name()])`
- **HTML escape:** `crm_h($string)` — vždy při výstupu
- **URL build:** `crm_url('/admin/users')` — respektuje base path
- **Redirect:** `crm_redirect('/dashboard')` — končí exit
- **Flash:** `crm_flash_set('msg')` před redirect, `crm_flash_take()` ve view
- **Audit log:** `crm_audit_log($pdo, $userId, $action, $entityType, $entityId, $payloadArray, 'web')`
- **DB error log:** `crm_db_log_error($e, __METHOD__)` v catch blocích
- **Komentáře:** česky kde je to rozumné, anglicky pro technické věci

### Views
- **Globální layout:** `app/views/layout/base.php` (sidebar + topbar + flash + $content)
- Každá controller metoda dělá: `ob_start(); require .../view.php; $content = ob_get_clean(); require .../layout/base.php;`
- `@var` annotation pro každou input proměnnou ve view
- Inline CSS jen pro view-specific, globální → `app.css`
- Inline JS OK, ale komentovat účel

### CSS
- 1 soubor: `public/assets/css/app.css` (~2000 řádků)
- BEM-like naming bez modifikátor lepidla (`cist-row--active`, `dg-cell`)
- CSS proměnné: `--text`, `--muted`, `--card`, `--bg`, `--accent`, `--brand-primary`

### JS
- **Vanilla, žádný framework**
- `addEventListener` (žádné inline onclick kromě jednoduchých view-generated)
- AJAX přes `fetch` (XHR jen pro upload progress v importu)
- CSRF přes hidden `<input>` nebo `X-Requested-With: XMLHttpRequest` header

---

## 12) 🚀 Deploy workflow

### Lokální → push
1. V VS Code edituješ
2. Test lokálně (Laragon)
3. Commit:
   ```bash
   git add <files>
   git commit -m "feat: krátký popis"
   git push
   ```

### Server pull
```bash
ssh root@194.182.86.81
cd /var/www/crm
sudo git pull
sudo chown -R www-data:www-data /var/www/crm
# Pokud nová SQL migrace:
sudo mariadb crm < /var/www/crm/sql/migrations/NNN_*.sql
# Vždy:
sudo systemctl reload php8.3-fpm
# Quick check:
sudo tail -20 /var/log/php8.3-fpm.log
sudo tail -10 /var/log/nginx/error.log
```

### Backup před risky deploy
```bash
sudo mariadb-dump crm | gzip > /var/backups/crm/pre-deploy-$(date +%Y%m%d-%H%M).sql.gz
```

### Git ownership trouble?
```bash
sudo git config --global --add safe.directory /var/www/crm
```
(Jen jednou po prvním deploy.)

---

## 13) 🔧 Setup local dev

**Laragon (Windows):**
- PHP 8.3 + MariaDB
- Apache nebo nginx
- Document root: `E:\Snecinatripu\public\`
- Default URL: `http://localhost:81` (nebo dle Laragon config)

**DB:**
- DB jméno: `crm`
- User: typically `root` lokálně (žádné heslo)
- Schema: spustit všechny `sql/migrations/*.sql` v pořadí 001..015

**Encryption keys:**
- Konfigurace v `config/db.php` (nešifruje se v git — `.gitignore`)
- TOTP secret encryption — viz `helpers/encryption.php`

**Storage:**
- Vytvořit `storage/imports/`, `storage/logs/` s write právy

---

## 14) ⚠ Známé bugs / gotchas

| Issue | Status |
|---|---|
| `totp_base32_alphabet` musí být **standardní RFC 4648** (32 znaků, A-Z + 2-7). Nestandardní = 2FA nikdy nepasuje. | ✅ Opraveno |
| Multi-role `crm_user_all_roles()` musí číst `$user['primary_role']`, ne `$user['role']` (ten je už injektovaný). Bez toho přepnutí zpět selže. | ✅ Opraveno |
| `cistVerify` JS — ternární operátor pro ikonu padá na default O2 pro chybný. Backend uloží správně, UI ne. | ✅ Opraveno |
| Import flushInserts — placeholder count musí matchovat count v SQL. Při přidání sloupce kontrolovat **obojí**. | ✅ Aktuální verze OK (17 placeholders + 3 literals = 20 columns) |
| OZ targets — dotaz pro pickup obchodáků musí použít `OR JSON_CONTAINS(roles_extra, '"obchodak"')` jinak multi-role OZ nejsou vidět. | ✅ Opraveno |
| `BackofficeController::ensureWorkflowMigration` loguje „Duplicate column" warnings při každém request — neškodné, ale šumí v logu. | ⚠ Známé, neřešeno (try-catch fallback) |
| Datagrid sticky header — Grid.js nepřátelský k position:sticky; používáme max-height + internal scroll na `.gridjs-wrapper`. | ✅ Workaround |
| `/admin/users/edit` route je `?id=N`, NE `/N`. Pozor při linkování! | ✅ Aktuální |
| `contacts.telefon` má VARCHAR(200) (od migrace 014) pro merge max 6 čísel přes `;` separátor. | ✅ |

---

## 15) 📊 Aktuální stav (snapshot k 2026-05-08)

**Hotové features:**
- ✅ Standardní pipeline (čistička → caller → OZ → BO)
- ✅ Premium pipeline (separátní queue, payout PDF, mark-paid)
- ✅ Multi-role uživatelé + login flow
- ✅ 2FA (TOTP) + trusted device cookies (30 dnů + 7-denní reverify)
- ✅ Import (CSV/XLSX, 2-phase, merge, oz_email + sale_price + stav + datum_volani)
- ✅ Live datagrid (Grid.js, 31 sloupců, search, filter, top scrollbar, drag scroll)
- ✅ Admin team stats per měsíc (čistička/caller/obchodak/BO)
- ✅ OZ targets per region (kvóty)
- ✅ Activity feed (workflow_log + oz_contact_actions + audit_log UNION)
- ✅ Audit duplicit
- ✅ Cisticka — click-to-copy telefon
- ✅ Premium cisticka — click-to-copy IČO + telefon
- ✅ Cisticka — 4. tlačítko Chybný (CHYBNY_KONTAKT)
- ✅ Region labels velkým písmenem všude

**Naplánováno (NEhotovo):**
- ⏳ **SMS narozeniny** — scaffolding hotov (`helpers/sms.php`), čeká na rozhodnutí o provideru (doporučeno SMSbrana.cz ~1,30 Kč/SMS)
- ⏳ **Mazání v datagridu** — per-řádek X + bulk delete (zatím jen globální RESET v import)
- ⏳ **Trusted device per-device revoke** v profilu (jen „odhlásit ze všech" funguje)

**Otevřené technické dluhy:**
- BackofficeController logs duplicate column warnings (kosmetické)
- `commissions` tabulka existuje ale dynamicky se nepočítá — vše on-the-fly přes workflow datumy
- `monthly_salaries` tabulka existuje, nepoužívá se

---

## 16) 🧪 Test účty (produkce)

```
Aines       superadmin   primární
Honza       obchodak     +majitel
Markét      obchodak     +majitel +backoffice
Jarka       navolavacka
Adélka      cisticka
```

Plus **„testovací účty"** (bez emailu) které admin vytvoří v `/admin/users/new-test`. Login je `username@<TEST_DOMAIN>`, heslo nastavuje admin.

---

## 17) 💬 Komunikační styl s userem (Aines)

- **Česky**, neformálně ale věcně
- **Step-by-step**, max 3 kroky na jednou (z `CLAUDE.md`)
- **Plán před kódem** — ujistit se před implementací
- **Confirm před destruktivními akcemi** (reset DB, smazat data)
- **Pošli mu screenshot/log při bugu** než opravíš naslepo
- **Komentuj kód** česky kde to dává smysl
- **Nedělej assumptions** — když si nejsi jistý, **zeptej se**

User často:
- píše rychle, s překlepy → nevadí, parsuj záměr
- má dobrou intuici, ale není advanced dev → vysvětli „proč", ne jen „co"
- preferuje praktické řešení před elegantním (KISS)
- nemá rád zbytečnou složitost
- ocení humor a uvolněnou atmosféru 😊

---

## 18) 📍 Co dělat když přebíráš

1. **Přečti tenhle soubor** ☑
2. **Mrkni do `CLAUDE.md`** — má základní rules od usera
3. **Projdi recent commits**: `git log --oneline -30`
4. **Spusť lokálně** Laragon, otevři `/dashboard`
5. **Loginnij se** jako různé role (přepnutí přes topbar)
6. **Projdi pipeline:** import → čistička → caller → OZ → BO
7. **Zkus 2FA setup** na svém testovacím účtu
8. **Mrkni do `/admin/datagrid`** — to je „pravda" o stavu DB
9. **Mrkni do `/admin/team-stats`** — co tým reálně dělá

Pak teprve začni řešit user's request. **Nepiš jediný řádek kódu bez plánu předem.**

---

## 🤝 Hodně štěstí!

Aines je super člověk se kterým je radost pracovat. Buď trpělivý, vysvětluj, ptej se. Když něco posereš, **přiznej a oprav** — chyby se stávají. Mnohokrát říká „chyba je mezi židlí a klávesnicí 😄" — beze stresu.

— Sestaveno na zakázku, vydáno: **8. května 2026**
