# DB Migration Runner

Jednoduchý CLI nástroj pro správu DB migrací.

## Co řeší

Před tím bylo nutné:
- Pamatovat si, které migrace už proběhly na deploy serveru
- Ručně spouštět `mariadb crm < sql/migrations/0XX.sql` jeden po druhém
- Kontrolovat schématy přes `SHOW COLUMNS`

Po nasazení runneru:
- `php bin/migrate.php status` ukáže přesný stav
- `php bin/migrate.php up` spustí všechny čekající migrace najednou
- Tabulka `schema_migrations` eviduje co kdy proběhlo

## Bootstrap (jednorázově při instalaci)

Pokud DB už obsahuje schémata 001-029 (= jsi v rozjeté instalaci),
musíš jednorázově označit existující migrace jako spuštěné:

```bash
# Lokálně
php bin/migrate.php mark-applied

# Na deploy serveru
cd /var/www/crm
DB_PASS='heslo' php bin/migrate.php mark-applied
```

Příkaz se zeptá `Pokračovat? (napiš 'ano')` — napiš ano.

Pak vytvoří tabulku `schema_migrations` a zapíše do ní všech 30 migrací
(001-030) jako bootstrap. **Žádné SQL se nepřespustí** — jen se to označí.

## Běžné použití

```bash
# Co je nového?
php bin/migrate.php status

# Spustit čekající
php bin/migrate.php up

# Spustit jen do verze 035 (skipnout vyšší)
php bin/migrate.php up --to=035

# Vytvořit novou migraci (autoincrementuje číslo)
php bin/migrate.php new tenants_foundation
```

## Konfigurace

Defaulty jsou Laragon-friendly (`root` bez hesla, `crm`, localhost).
Pro deploy server přes ENV:

```bash
DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=crm \
DB_USER=dbuser DB_PASS='heslo' \
php bin/migrate.php up
```

Nebo edituj defaulty v `bin/migrate.php` (řádky 17-22).

## Bezpečnost

- Runner je idempotentní — spuštění `up` 2× v řadě nic nepokazí
- Při chybě v migraci se zastaví a vypíše error — úspěšné migrace už jsou
  v `schema_migrations`, takže po opravě a re-runu se nepřespustí
- `checksum` (SHA-256) v `schema_migrations` zachytí pokud někdo později
  upraví už spuštěný soubor — `status` to ukáže jako warning

## Workflow při nové změně schématu

1. `php bin/migrate.php new add_column_xyz` → vytvoří `031_add_column_xyz.sql`
2. Edituj soubor, napiš SQL
3. `php bin/migrate.php up` → spustí lokálně
4. `git add` + `git commit` + `git push`
5. Na deploy serveru: `git pull` + `php bin/migrate.php up`
6. Hotovo.
