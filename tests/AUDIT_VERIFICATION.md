# 🔍 Verifikace fixů z ranního auditu

Tři artefakty pro ověření, že fixy #1–#4 fungují i v praxi.

---

## 📄 1. `test_import_audit.csv` — Fix #1 + Fix #2

**Co testuje:**
- Fix #1: Import workflow stavy (ZPRACOVAVA, SCHUZKA, BO_PREDANO + české aliasy)
- Fix #2: Role validace u oz_email (musí to být obchodák, ne navolávačka)

**Jak otestovat:**

1. Otevři `http://localhost:8080/admin/import` jako majitel/superadmin
2. Nahraj `test_import_audit.csv`
3. **Před importem** se zobrazí preview se zelenými a červenými řádky

**Očekávané chování (8 řádků v CSV):**

| Řádek | Firma   | Stav v CSV          | Očekávaný výsledek                                                          |
|-------|---------|---------------------|------------------------------------------------------------------------------|
| 1     | ALFA    | `NEW`               | ✅ Importuje se jako `contacts.stav = NEW`                                  |
| 2     | BETA    | `ZPRACOVAVA`        | ✅ Workflow stage — vytvoří se řádek v `oz_contact_workflow` (Fix #1)       |
| 3     | GAMA    | `SCHUZKA`           | ✅ Workflow + assigned_sales_id (oz_email validní)                          |
| 4     | DELTA   | `bo_predano`        | ✅ Workflow BO_PREDANO (Fix #1: case-insensitive)                           |
| 5     | EPSILON | `bo předáno` (CZ)   | ✅ Workflow BO_PREDANO (Fix #1: český alias s diakritikou)                  |
| 6     | ZETA    | `blabla_neplatne`   | ❌ ERROR „Neznámý stav 'blabla_neplatne'" — řádek se nepřidá                |
| 7     | ETA     | `CHCE` bez emailu   | ❌ ERROR „Stav CHCE musí mít vyplněný sloupec oz_email"                     |
| 8     | THETA   | `CHCE` + navolavačka| ❌ ERROR „oz_email 'navolavacka@example.com' není OZ" (Fix #2!)             |

**⚠ Před nahráním:** uprav v CSV `ester@example.com` na **skutečný email aktivního obchodáka** ve tvé DB (jinak řádky 3–5 také selžou s „není OZ"). A `navolavacka@example.com` na **skutečný email aktivní navolávačky** (jinak Fix #2 test nedává smysl).

**Pokud chceš import provést „nanečisto":** v preview je vidět, co se stane, ale ještě nic není uložené. Tlačítko **Zrušit** to celé zahodí. Tlačítko **Provést import** zapíše do DB.

---

## 🗄 2. `verify_fixes.sql` — read-only diagnostika

**Co dělá:** spustí 7 SELECT dotazů nad aktuální produkční DB. **Nemění nic.** Ukazuje:

- Fix #1: Distribuci workflow stavů v `oz_contact_workflow`
- Fix #2: Kdo je aktivní obchodák (povolené `oz_email`) vs navolávačka (zakázané)
- Fix #3: Existující expired/failed rescue requests + souhrn clawback per navolávačka
- Fix #4: Kolik kontaktů má vyplněné `queue_mix_seq` (= mix proběhl)

**Spuštění:**

```bash
# Cesta k MariaDB závisí na tvém Laragonu — typicky:
mariadb -u root crm_db < E:\Snecinatripu\tests\verify_fixes.sql

# Nebo přes phpMyAdmin (Laragon → MySQL → phpMyAdmin → SQL):
#   1. Otevři SQL záložku
#   2. Copy-paste obsah verify_fixes.sql
#   3. Provést
```

**Co od výstupu očekávat:**

- **Fix #1 výstup**: tabulka jako `NOVE: 12 | ZPRACOVAVA: 8 | SCHUZKA: 3 | NABIDKA: 2 | ...` — pokud máš jen `FOR_SALES`, fix sice je v kódu, ale ještě jsi neimportoval data, která by ho aktivovala.
- **Fix #2 výstup**: seznam obchodáků (Ester, případně další) + seznam navolávaček (ti, kteří NESMĚJÍ být v oz_email).
- **Fix #3 výstup**: pravděpodobně **0 řádků** = ještě jsi nikomu nedal expirovat záchranu. To je OK — kód je připravený, jakmile k tomu dojde, clawback se uplatní.
- **Fix #4 výstup**: pokud jsi pouštěl mix, uvidíš `pct_mixed = 80%+`. Pokud ne, `100% without_seq` = mix se ještě nepouštěl.

---

## 🎯 3. End-to-end manuál pro Fix #3 (Rescue clawback)

Tento test vyžaduje vytvoření reálné expirované záchrany. **Nedělá se automaticky** — je to manuální scénář.

**Postup (5 kroků):**

1. Jako **admin** přihlas se → `/admin/rescue` (nebo kde se zakládá rescue request)
2. Vytvoř nový rescue request pro libovolný kontakt → přiřaď navolávačce X
3. Otevři DB a posuň `created_at` o 15 dnů zpět:
   ```sql
   UPDATE rescue_requests
   SET created_at = NOW() - INTERVAL 15 DAY
   WHERE id = <ID právě vytvořené záchrany>;
   ```
4. Spusť cron / lazy expirace (přihlas se jako caller na `/caller`) — pending starší než 14 dní se automaticky překlopí na `outcome = 'expired'`
5. Otevři `/caller/payout-print` pro navolávačku X → musíš vidět sekci **„⚠ Odečty za záchrany"** s tímto kontaktem + odpovídající minus částku

Pokud sekce „Odečty" chybí, fix #3 nesedí. (Ale podle code review na ř. 1895–1924 je správně.)

---

## 📊 Performance test (Fix #4 — Mix batching)

Bez fake dat na 5000+ kontaktech se to dělá těžce. Doporučení:

1. V Laragonu zapni **slow query log** dočasně:
   ```sql
   SET GLOBAL slow_query_log = 'ON';
   SET GLOBAL long_query_time = 0.1;
   ```
2. Spusť mix přes `/admin/contacts/mix` → tlačítko **Spustit mix**
3. Zkontroluj `slow_query.log` (typicky `C:\laragon\data\mysql\<hostname>-slow.log`)

Před fixem: viděl bys **5000+ záznamů** s jednotlivými UPDATE.
Po fixu: vidíš **max 20 záznamů** (10 batchů × 2 typy) s `UPDATE ... CASE WHEN ... WHERE id IN (...)`.

---

## 🟢 Souhrn

- Fix #1 + #2 → **manuální test přes CSV import** v `/admin/import`
- Fix #3 → **manuální E2E scénář** (vyžaduje expiraci) + SQL kontrola současného stavu
- Fix #4 → **manuální test přes `/admin/contacts/mix`** + slow query log

Žádný fix nevyžaduje rollback v DB — všechny artefakty jsou opt-in. Pokud uděláš import a chceš ho vrátit, kontakty `ALFA–THETA` smaž tímto:

```sql
DELETE FROM contacts WHERE ico IN ('12345001','12345002','12345003','12345004','12345005','12345006','12345007','12345008');
```
