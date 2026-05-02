# Import staré databáze — návod

> Jak nahrát starou databázi (Excel / CSV) do CRM, aniž bys cokoli rozbil. **Read tohle před prvním importem.**

---

## TL;DR

1. Otevři `/admin/import`
2. Nahraj CSV nebo XLSX soubor (max 200 MB / 300 000 řádků)
3. Klikni **Analyzovat** → CRM ti ukáže **preview** s počty a duplicitami
4. Per-řádek rozhodni **skip / update / add / merge**
5. Klikni **Potvrdit import** → data se zapíšou
6. Hotovo!

**Žádný big-bang.** Pokud si nejsi jistý → **Zrušit** a nic se nestane.

---

## Co kam patří v CSV

### Povinné sloupce
| Sloupec | Popis | Příklad |
|---|---|---|
| `firma` (nebo `nazev_firmy`) | Název firmy | `ABC s.r.o.` |
| `kraj` (nebo `region`) | Lze i město → kraj se odvodí | `Jihomoravský kraj` nebo `Brno` |

### Volitelné — základní kontakt
| Sloupec | Popis | Příklad |
|---|---|---|
| `ico` (nebo `ičo`) | IČO firmy | `12345678` |
| `adresa` | Plná adresa | `Praha 1, Václavské nám. 1` |
| `telefon` (nebo `mobil`) | Hlavní telefon | `731234567` |
| `email` | Hlavní e-mail | `info@abc.cz` |
| `operator` | Aktuální operátor | `O2`, `TM`, `Vodafone` |
| `poznamka` | Cokoli relevantního | `Vrátit příští týden` |
| `narozeniny_majitele` | Datum narození | `25.04.1980` |

### 💡 Speciální — pro staré uzavřené smlouvy
Tohle je nový sloupec, který nám vytváří **rovnou kompletní historii** pro výročí:

| Sloupec | Popis | Příklad |
|---|---|---|
| `datum_uzavreni` | Datum podpisu smlouvy | `15.03.2024` |
| `vyrocni_smlouvy` *(volitelné)* | Pokud chceš ručně určit jiné výročí | `15.03.2027` |

**Co se stane, když vyplníš `datum_uzavreni`:**
- Kontakt se založí ve stavu `UZAVRENO` (ne `NEW`)
- `vyrocni_smlouvy` se dopočítá automaticky jako `datum_uzavreni + 3 roky`
- Vznikne řádek v `oz_contact_workflow` s podpisem potvrzeným ✓
- Objeví se hned v BO **Uzavřeno** tabu
- 6 měsíců před výročím spadne do widgetu *„Blíží se výročí"* OZ

**Pro nové leady (k oslovování) — sloupec necháš prázdný.** Kontakt se založí jako `NEW` a projde standardním workflow čistička → navolávačka → OZ → BO.

---

## Formáty data

CRM si přečte všechny tyhle varianty:
- `25.04.2026` (česky)
- `2026-04-25` (ISO)
- `25/04/2026` (US-EU mix)
- `45406` (Excel serial number — když si Excel "přečetl" datum jako číslo)

---

## Jak vypadá kraj

CRM tě nenechá importovat řádek bez kraje. Akceptuje:

- **Kód:** `jihomoravsky`, `praha`, `plzensky`, …
- **Český název:** `Jihomoravský kraj`, `Hlavní město Praha`, `Plzeňský kraj`
- **Město:** `Brno` → Jihomoravský, `Praha 1` → HMP, `Plzeň` → Plzeňský

Pokud město nezná, tak ti řádek označí jako chybu v preview a můžeš ho přeskočit nebo opravit.

---

## Detekce duplicit (vychytávka)

Když nahraješ soubor, CRM **nejdřív skenuje**:

### V samotném souboru (in-file)
Hledá řádky, které mají stejné:
- IČO
- E-mail
- Telefon (po normalizaci — bez mezer / +420 / pomlček)

Per-řádek pak rozhodneš:
- **Skip** — neimportuj, nech v souboru
- **Add** — přidej oba (vznikne duplicita; pro výjimečné případy)
- **Merge** — sloučit do jednoho

### Vůči existující DB
Pokud už daný kontakt v DB je, dostaneš na výběr:
- **Skip** — nedělej nic, nech původní záznam
- **Update** — aktualizuj prázdná pole novými daty
- **Add** — přidej nový (vznikne duplicita)
- **Merge** — slouč data

**Doporučení pro starou databázi:** většinou si vybereš **skip** (nepřepiš to, co už máš) nebo **update** (doplň chybějící).

---

## Preview obrazovka — co tam uvidíš

```
┌─ Souhrn ─────────────────────────────────────┐
│  ✓ 1 247 řádků k importu                      │
│  ⚠ 12 chyb (chybí firma nebo kraj)            │
│  📋 8 duplicit v souboru                       │
│  📂 23 duplicit vs. DB                         │
└───────────────────────────────────────────────┘

┌─ Chyby v souboru (12) ──────────────────────┐
│  Řádek 47: chybí firma                        │
│  Řádek 153: nelze určit kraj z města "XYZ"    │
│  …                                            │
└───────────────────────────────────────────────┘

┌─ Duplicity vs. DB (23) ──────────────────────┐
│  Řádek 12 ↔ ID 342 (telefon 731234567)        │
│   → [skip ▾] [update] [add] [merge]            │
│  Řádek 18 ↔ ID 891 (IČO 12345678)             │
│   → [skip ▾] [update] [add] [merge]            │
│  …                                            │
└───────────────────────────────────────────────┘

[Zrušit]            [✓ Potvrdit import]
```

Klikneš **Potvrdit** a CRM provede zápis. Vrátí ti hlášku jako:
> ✓ Importováno 1 224 nových, aktualizováno 18, přeskočeno 5 duplicit, 12 chyb.

---

## Po importu — kontrola

Hned po importu si projdi:

1. **`/admin/datagrid`** — Live přehled celé DB (Excel-like)
   - Filtruj `Stav = UZAVRENO` → uvidíš všechny staré uzavřené smlouvy
   - Sleduj sloupec **Výročí** — měl by být dopočítaný (datum + 3 roky)
   - Zkontroluj sloupec **Smlouva** (číslo + datum)
2. **`/admin/duplicates`** — pokud vznikly duplicity, projeď a vyřeš
3. **`/bo`** → tab **Uzavřeno** — uvidíš tam staré smlouvy z importu (pokud jsi vyplnil `datum_uzavreni`)
4. **`/oz`** → widget **„Blíží se výročí"** — ukáže smlouvy s výročím do 180 dní

---

## Časté chyby a co s nimi

| Chyba | Řešení |
|---|---|
| `Chybí firma` | Doplň ve sloupci `firma` nebo `nazev_firmy` |
| `Nelze určit kraj` | Doplň `kraj` nebo zadej rozeznatelné město |
| `Datum_uzavreni v budoucnosti` | Zkontroluj překlep — datum nesmí být po dnešku |
| `Telefon ve špatném formátu` | CRM normalizuje +420, mezery a pomlčky. Pokud má pořád problém, zkrať na 9 číslic |
| `Encoding rozhozený` | Ulož CSV jako **UTF-8 with BOM** v Excelu (Save As → CSV UTF-8) |
| `Středník místo čárky` | CRM si poradí s obojím — ale udělej jen jeden ve celém souboru |

---

## Vzorový CSV soubor

Najdeš v `docs/import_sample.csv`. Obsahuje 5 řádků pokrývajících:
- Aktivní lead bez kontaktu
- Stará uzavřená smlouva s `datum_uzavreni`
- Lead s minimem údajů
- Zákazník s narozeninami i datem podpisu
- Test row pro ověření importu

Otevři ho v Excelu nebo textovém editoru, abys viděl, jak má vypadat tvoje vlastní CSV.

---

## Pro ostrý nahrání staré databáze (před deployem)

**Doporučený workflow:**

1. **Test na malém vzorku (10-50 řádků):**
   - Vyber pár řádků ze starého Excelu
   - Nahraj přes `/admin/import`
   - V `/admin/datagrid` zkontroluj, že vše vypadá správně
   - Pokud jo → smaž testovací řádky a pokračuj

2. **Ostré nahrání:**
   - Před importem: **`/admin/duplicates`** zkontroluj stav DB
   - Nahraj kompletní CSV
   - V preview pečlivě projdi duplicity (skip / update)
   - Klikni **Potvrdit**

3. **Po importu:**
   - **`/admin/duplicates`** znovu — měl by být čistý
   - **`/admin/datagrid`** filtruj UZAVRENO → ověř, že počty sedí
   - **`/oz`** widget výročí → ověř, že se objevují smlouvy

4. **Cleanup (volitelný):**
   - Pokud je v Uzavřeno něco, co nemělo být UZAVRENO (chyba v CSV) → klikni v BO **Otevřít znovu** a vrátí se do V práci

---

## Když něco selže

1. **Žádný flash error po Potvrdit** → import proběhl, jen překontroluj `/admin/datagrid`
2. **"500 Internal Server Error"** → koukni do `storage/logs/error.log` (na serveru)
3. **Soubor moc velký** → rozděl na 2-3 menší CSV (každý do 100 000 řádků)
4. **Encoding problém** → otevři v Notepad++, ulož jako UTF-8 with BOM

---

## FAQ

**Q: Co se stane, když nahraju ten samý soubor 2×?**
A: Druhý import detekuje 100 % duplicit. Doporučím pro všechny vybrat *skip* a nic se nezmění.

**Q: Můžu nahrát Excel `.xlsx` přímo?**
A: Ano, podporujeme `.xlsx`, `.xls` i `.csv`.

**Q: Co když nemám IČO ani e-mail, jen telefon?**
A: Telefon stačí jako unikátní identifikátor (CRM normalizuje). Detekce duplicit funguje.

**Q: Můžu importovat i kontakty bez čísla smlouvy, jen s `datum_uzavreni`?**
A: Ano! `cislo_smlouvy` zůstane NULL — to je v pořádku pro legacy data. Když pak BO znovuotevře a uzavře, doplní se.

**Q: Co když chci importovat smlouvu na jiné trvání než 3 roky?**
A: V CSV vyplň `vyrocni_smlouvy` ručně (např. `01.06.2028` pro 5letou smlouvu od `01.06.2023`). CRM ho použije místo auto-výpočtu.

---

## Technické detaily (pro vývojáře)

- **Endpoint:** `/admin/import` (POST), `/admin/import/preview` (GET), `/admin/import/commit` (POST)
- **Controller:** `AdminImportController.php`
- **Helpers:** `import_csv.php`, `import_xlsx.php`
- **Limit:** 200 MB / 300 000 řádků / 500 batch insert
- **Tabulky:** `contacts`, `oz_contact_workflow` (pokud `datum_uzavreni`), `workflow_log`, `import_log`
- **Encoding:** UTF-8 (BOM nebo bez)
- **Delimiter detection:** `;` nebo `,` automaticky

---

**Když budeš mít otázku, nebo se ti něco nezdá → zastav se a zeptej, místo abys něco mazal.** 🛡
