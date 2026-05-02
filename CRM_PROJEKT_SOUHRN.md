# Shrnutí CRM projektu — pro dalšího pomocníka

## Co je to za projekt

Vlastní CRM systém v čistém PHP (bez frameworku) + MariaDB + vanilla JS.  
Žádný Composer, žádný ORM.  
Architektura: **front controller → Router → Controller → ob_start/require View → base layout**

---

## Klíčové konvence

- `declare(strict_types=1)` musí být první řádek každého PHP souboru
- POST → validace → flash → `crm_redirect()` (PRG pattern)
- CSRF: `crm_csrf_token()` / `crm_csrf_validate()` na všech POST formulářích
- Přístup: `crm_require_user()` + `crm_require_roles($user, ['role1', ...])`
- Flashe: `crm_flash_set()` / `crm_flash_take()`
- Escape: vždy `crm_h($string)` v HTML
- URL: vždy `crm_url('/cesta')` — řeší prefix i cli-server
- DB: `CREATE TABLE IF NOT EXISTS` + `ALTER TABLE ... ADD COLUMN` (každý v separátním try/catch) — žádné migrace

---

## Role v systému

| Role | Co dělá |
|------|---------|
| `superadmin` / `majitel` | Admin, kvóty, import, statistiky |
| `navolavacka` | Volá kontakty, nastavuje výsledky |
| `cisticka` | Ověřuje operátory kontaktů |
| `obchodak` | Zpracovává navolané leady |

---

## Hlavní části co jsme postavili (tento projekt)

1. **OZ Pracovní plocha** (`/oz/leads`) — tab workflow: Nové → Zpracovávám → Schůzka/Callback/Smlouva/Nezájem
2. **OZ Kvóty dashboard** (`/oz`) — plnění kvót per region per měsíc
3. **Admin kvóty OZ** (`/admin/oz-targets`) — nastavení cílů + detail + PDF tisk
4. **Reklamace** — OZ flaguje špatně navolané kontakty, admin vidí v detailu
5. **Historie poznámek OZ** — tabulka `oz_contact_notes`, chronologicky per kontakt
6. **Notifikace schůzek** — banner musí OZ odkliknout před zmizením
7. **Šněčí závody OZ** — výhry = smlouvy, šnek otočen doprava

---

## DB tabulky přidané v tomto projektu

- `oz_targets` — kvóty per OZ per region per měsíc
- `oz_contact_workflow` — stav kontaktu u OZ (NOVE→ZPRACOVAVA→SCHUZKA atd.), `schuzka_at`, `schuzka_acknowledged`
- `oz_contact_notes` — historie poznámek OZ per kontakt
- `contact_oz_flags` — reklamace OZ

---

## Soubory projektu (klíčové)

```
app/
  Router.php                    — všechny routes
  controllers/
    OzController.php            — getLeads, postLeadStatus, getRaceJson, postAcknowledgeMeeting, getIndex, postFlag
    AdminOzTargetsController.php — getIndex, postSave, getDetail, getPrint
    CallerController.php        — getIndex (obsahuje OZ progress pro dropdown)
  views/
    oz/leads.php                — pracovní plocha OZ
    oz/index.php                — kvóty dashboard
    admin/oz_targets.php        — admin tabulka kvót
    admin/oz_targets_detail.php — detail per caller
    admin/oz_targets_print.php  — standalone tiskový pohled
  helpers/
    url.php                     — crm_url() má cli-server výjimku!
public/
  assets/css/app.css            — .op-tm/.op-o2/.op-vf, .dash-card, snail race
  dev-router.php                — PHP built-in server router
```

---

## Bezpečné vypnutí Laragonu a start zítra

### Dnešní večer — správné vypnutí

1. Ulož všechnu práci (kód je uložen v souborech, DB je v Laragonu)
2. Laragon nevypínej přes "X" okna — použij správné tlačítko:
   - Klikni na ikonu Laragonu v **system tray** (pravý dolní roh)
   - → **Stop All** (zastaví Apache + MariaDB čistě)
   - Pak teprve zavři Laragon nebo vypni PC
3. **Proč:** MariaDB má write-ahead log. Tvrdé zabití procesu může způsobit pomalejší obnovu nebo v krajním případě poškození InnoDB tabulek.

### Zítřejší ráno — spuštění

1. Otevři Laragon
2. Klikni **Start All** — spustí Apache + MariaDB
3. Otevři terminál a spusť dev server:

```bash
cd E:\Snecinatripu
php -S localhost:8080 -t public public/dev-router.php
```

4. Otevři prohlížeč → `http://localhost:8080`

---

## Kde jsou data (DB)

Data jsou v MariaDB, která je součástí Laragonu. Fyzicky na disku:

```
C:\laragon\data\mysql\
```

Tato složka = celá databáze. Pokud chceš zálohu, zkopíruj ji.

### Pokud se stane problém se startem

- Laragon → Menu → MySQL → **"Open error log"** — ukáže co se stalo
- Nebo: Laragon → Stop All → Start All (restart pomůže 99% případů)
- DB se nikdy nesmaže jen vypnutím PC — data jsou na disku
