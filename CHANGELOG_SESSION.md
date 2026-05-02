# CRM — Přehled změn (session)

## Co jsme postavili

### 1. Sledování výkonu navolávačky (`/caller/stats`)
Každá navolávačka vidí svůj vlastní výkon za libovolný měsíc.

**Nové soubory:**
- `app/views/caller/stats.php` — osobní statistiky, souhrnné karty + denní tabulka

**Upravené soubory:**
- `app/controllers/CallerController.php` — přidány metody `getStats()`, `parseMonthKey()`, `czechMonthName()`
- `app/views/caller/index.php` — přidána záložka 📊 Výkon

**Sledované stavy:** `CALLED_OK`, `CALLED_BAD`, `CALLBACK`, `NEZAJEM`, `NEDOVOLANO`, `IZOLACE`, `CHYBNY_KONTAKT`

---

### 2. Admin přehled navolávačů (`/admin/caller-stats`)
Majitel vidí srovnávací tabulku všech navolávačů vedle sebe.

**Nové soubory:**
- `app/controllers/AdminCallerStatsController.php`
- `app/views/admin/caller_stats.php` — pivot tabulka: řádky = navolávačky, sloupce = stavy, progress bar úspěšnosti, 🏆 pro nejlepšího

---

### 3. Sledování výkonu čističky (`/cisticka/stats`)
Každá čistička vidí svůj výkon — TM ověřeno, O2 ověřeno, VF přeskočeno.

**Nové soubory:**
- `app/views/cisticka/stats.php` — souhrnné karty + denní tabulka

**Upravené soubory:**
- `app/controllers/CistickaController.php` — přidány metody `getStats()`, `parseMonthKey()`, `czechMonthName()`
- `app/views/cisticka/index.php` — přidána záložka 📊 Výkon

**Poznámka:** TM vs O2 se rozlišuje JOINem na tabulku `contacts` (sloupec `operator`), protože obojí je v `workflow_log` jako stav `READY`.

---

### 4. Sjednocená admin statistika týmu (`/admin/team-stats`)
Jeden controller + view pro všechny role. Přepínání rolí přes navigaci nahoře.

**Nové soubory:**
- `app/controllers/AdminTeamStatsController.php` — konstanta `ROLE_CONFIG` definuje sledované stavy pro každou roli
- `app/views/admin/team_stats.php` — navigace mezi rolemi, filtr měsíce, pivot tabulka

**Podporované role:**
| Role | Sledované stavy | Výhra = |
|---|---|---|
| `navolavacka` | CALLED_OK, CALLED_BAD, CALLBACK, NEZAJEM, NEDOVOLANO, IZOLACE, CHYBNY_KONTAKT | called_ok |
| `cisticka` | READY (TM/O2), VF_SKIP | ready_total |
| `obchodak` | FOR_SALES, APPROVED_BY_SALES, REJECTED_BY_SALES, DONE, ACTIVATED, CANCELLED | approved |
| `backoffice` | BACKOFFICE, DONE, ACTIVATED, CANCELLED | done |

---

### 5. Databázová migrace
**Nový soubor:** `sql/migrations/006_caller_performance_index.sql`
```sql
ALTER TABLE `workflow_log`
    ADD KEY `idx_workflow_user_status_created` (`user_id`, `new_status`, `created_at`);
```
Composite index pro rychlé dotazy na statistiky (user + status + datum).

---

### 6. Router — nové routy
**Upravený soubor:** `app/Router.php`

```
GET  /caller/stats        → CallerController::getStats           [navolavacka]
GET  /admin/caller-stats  → AdminCallerStatsController::getIndex [majitel, superadmin]
GET  /admin/team-stats    → AdminTeamStatsController::getIndex   [majitel, superadmin]
GET  /cisticka/stats      → CistickaController::getStats         [cisticka, majitel, superadmin]
```

---

### 7. Fix: index.php — chybějící require_once
**Upravený soubor:** `public/index.php`

Byly přidány dva chybějící řádky které způsobovaly fatal error:
```php
require_once ... 'AdminCallerStatsController.php';
require_once ... 'AdminTeamStatsController.php';
```

---

### 8. Fix: filtr měsíce (month_key)
**Problém:** Kliknutí na jiný měsíc vždy vrátilo duben 2026 (aktuální měsíc).

**Příčina:** `onchange="this.form.submit()"` odeslalo formulář dříve, než JS stihl naplnit hidden fieldy `year` a `month`.

**Řešení:** Odebrány hidden fieldy a veškerý JS. Všechny controllery nyní parsují `month_key` ve formátu `YYYY-MM` přímo v PHP metodou `parseMonthKey()`.

---

### 9. Fix: strftime() na Windows
**Problém:** `strftime()` na Windows vrací anglické názvy měsíců nebo selhává.

**Řešení:** Ve všech controllerech nahrazeno statickým PHP polem `czechMonthName(int $m): string`.

---

### 10. Čistička UX — modré počty krajů na záložce K ověření
**Upravený soubor:** `app/views/cisticka/index.php`

Na záložce "K ověření" jsou čísla krajů modrá (`.cist-region-cnt--new { color: var(--accent); }`).
Na ostatních záložkách jsou šedá (výchozí).

---

### 11. Čistička UX — auto-remove řádku po undo expiraci
**Upravený soubor:** `app/views/cisticka/index.php`

Po kliknutí na TM/O2/VF se spustí 5s odpočet. Když vyprší:
- kontakt se plynule vybledne a sroluje (CSS transition)
- odstraní se z DOM bez refreshe stránky
- číslo u příslušného kraje se automaticky sníží o 1

Klíčové technické detaily:
- každý řádek kontaktu má `data-region="..."` atribut
- badge krajů mají `id="region-cnt-{region}"`
- JS funkce `cistRemoveRow(contactId)` najde řádek, přečte region, dekrementuje badge, animuje a odstraní

---

### 12. Month picker — +1 měsíc dopředu + zvýraznění aktuálního
Ve všech stats controllerech:
- loop `for ($i = -1; $i < 17; $i++)` — zobrazuje 1 měsíc dopředu + 17 zpět
- `$realMonthKey` (formát `YYYY-MM`) předán do view
- aktuální měsíc má v `<option>` světle zelené pozadí + text "— nyní"

---

### 13. Header — redesign
**Upravené soubory:** `app/views/layout/base.php`, `public/assets/css/app.css`

Nový flex header:
- **Vlevo:** text "CRM" (modře)
- **Vpravo** (jen pro přihlášené): jméno uživatele · role badge · 🏠 Dashboard · Odhlásit

Odhlašovací tlačítko obsahuje CSRF token generovaný přímo v `base.php` přes `crm_csrf_token()`.
Na stránkách bez přihlášení (login) se pravá část nezobrazí — `isset($user)` check.

---

## Architektura — rychlý přehled

```
public/index.php          ← front controller, require_once všech controllerů
app/Router.php            ← routovací tabulka
app/bootstrap.php         ← helpers, PDO, constants
config/constants.php      ← CRM_BASE_PATH, CRM_PUBLIC_PATH, atd.

app/controllers/          ← logika, dotazy, předání dat do view
app/views/                ← čisté PHP šablony (žádný framework)
app/views/layout/base.php ← hlavní layout (header + main)
app/helpers/              ← csrf.php, auth.php, url.php, flash.php, ...

public/assets/css/app.css ← jediný CSS soubor, vše v jednom
public/assets/img/        ← obrázky (logo.png sem, až bude)
sql/migrations/           ← SQL migrace číslované 001–006+
```

## Důležité konvence

- **CSRF:** každý POST formulář musí mít `crm_csrf_field_name()` + `crm_csrf_token()`
- **Escapování:** vždy `crm_h($hodnota)` při výpisu do HTML
- **URL:** vždy `crm_url('/cesta')` — nikdy natvrdo
- **Měsíce:** `czechMonthName(int $m)` — statické pole, ne strftime()
- **Měsíc filtr:** parametr `month_key` formátu `YYYY-MM`, parsuje `parseMonthKey()`
- **PDO:** pojmenované parametry nelze v jednom dotazu použít dvakrát — použij `?` nebo unikátní názvy (`:yr1`, `:yr2`)
- **Workflow log:** tabulka `workflow_log` má `created_at DATETIME(3)` — základ pro všechny statistiky, žádná extra tabulka nebyla potřeba
