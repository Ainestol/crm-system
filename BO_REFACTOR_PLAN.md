# BO (Backoffice) Refactor Plan

> Strategy: **paralelní migrace**. Stará UI funguje až do dokončení. Žádný big-bang. Reuse oz_kit.css design tokens. Backend 100% netknutý.

---

## Současný stav

- View: `app/views/backoffice/index.php` — 956 řádků s **inline styles** (žádný separátní CSS)
- Controller: `app/controllers/BackofficeController.php` — 709 řádků
- Routes: `/bo` (GET) + `/bo/start-work`, `/bo/return-oz`, `/bo/close`, `/bo/action/add`, atd.

**5 tabů**:
1. 📥 K přípravě (BO_PREDANO + SMLOUVA)
2. 🔧 V práci (BO_VPRACI)
3. ↩ Vráceno OZ (BO_VRACENO)
4. ✅ Uzavřeno (UZAVRENO)
5. ✗ Nezájem (vše OZ) — grouped per OZ

**Akce per tab**:
- K přípravě: `Začít zpracovávat` (modrofialová) + `Nezájem` (červená)
- V práci: `Vrátit OZ` (oranžová) + `Uzavřít smlouvu` (zelená) + `Nezájem`
- Vráceno OZ / Uzavřeno: jen sledování + Pracovní deník
- Nezájem: jen sledování (read-only)

**Hlavní UX problémy** (analogicky s OZ):
- Rainbow tabů + tlačítek
- Vše default rozbalené (Pracovní deník, BO postup s 4 checkboxy)
- Inline styles → ztížená udržba
- Karty se silným borderem v plné variant barvě

---

## Cílová architektura

| URL | View | Co tam je |
|---|---|---|
| `/bo` | `backoffice/index.php` (REFACTORED) | Tab list — sjednocené barvy, sbalené sekce, čistý layout |

Backend **bez změn** — veškerý refactor je view + CSS.

---

## Krok 0 — Příprava CSS infrastruktury ⏸ APPROVAL

**Co se udělá:**
1. Vytvořit nový soubor `public/assets/css/bo_kit.css` který:
   - Importuje tokeny z `oz_kit.css` (reuse design system)
   - Definuje BO-specific styles: `.bo-card`, `.bo-tabs`, `.bo-tab`, `.bo-tab--active`
   - Akční tlačítka: `.bo-btn-primary`, `.bo-btn-secondary`, `.bo-btn-negative`
   - Pracovní deník + BO postup: `.bo-section-collapsible`

**Riziko**: nulové. Nový soubor, nikdo ho zatím nepoužívá.
**Effort**: ~30 minut.

---

## Krok 1 — Tabs unification ⏸ APPROVAL

**Co se udělá:**
1. V `backoffice/index.php` extrahovat inline tab styling do `.bo-tab` třídy
2. Aplikovat oz_kit hierarchy: aktivní = primary color + bold + bg, neaktivní = opacity 0.55 šedé, sémantika v emoji ikoně
3. Tab counts jako neutral pill badge

**Riziko**: nízké. Pouze CSS, žádná business logic.
**Effort**: ~30 minut.

---

## Krok 2 — Karty: levý border + neutrální shell ⏸ APPROVAL

**Co se udělá:**
1. Karta kontaktu: neutrální border + 4px LEFT border ve variant barvě stavu
2. Sjednotit padding/spacing
3. Status badge v hlavičce: neutrální fill + bold text (ne neonový)

**Riziko**: nízké.
**Effort**: ~45 minut.

---

## Krok 3 — Pracovní deník + BO postup: sbalit default ⏸ APPROVAL

**Co se udělá:**
1. Pracovní deník v `<details>` default **zavřený**, summary ukazuje **latest preview** + počet záznamů
2. BO postup (4 checkboxy) v `<details>` default **zavřený**, summary ukazuje **`X/4 hotovo`**
3. Když OZ klikne sbalit/rozbalit, LocalStorage si pamatuje preference per-card

**Riziko**: středně nízké — JS toggle + LocalStorage.
**Effort**: ~60 minut.

**Alternativa**: pokud chceš mít deník pořád viditelný (workflow priorita), nesbalíme ho default. Rozhodneme se na approval gate.

---

## Krok 4 — Akční tlačítka: sjednotit styl ⏸ APPROVAL

**Co se udělá:**
1. **Primary** akce (1 per tab): `Začít zpracovávat` (k přípravě), `Uzavřít smlouvu` (v práci) — solid green
2. **Secondary** akce: `Vrátit OZ` (warning amber), `Přidat záznam do deníku` — outline
3. **Negative**: `Nezájem` — destructive red, **2-step inline confirm** (jako u OZ chybný lead)
4. Sjednocené padding, font-size, border-radius

**Riziko**: středně nízké.
**Effort**: ~45 minut.

---

## Krok 5 — Karta stat overview ⏸ APPROVAL

**Co se udělá:**
1. Nahoře `/bo` (pod tabem) přidat **stat overview** sbalitelný:
   - Mám X smluv k přípravě
   - X v práci
   - X vrácených od OZ → mojí pozornosti
   - X uzavřených tento měsíc
2. Inline preview v summary, stejný princip jako `/oz` dashboard

**Riziko**: nízké, jen view-level.
**Effort**: ~30 minut.

---

## Krok 6 — Final cleanup ⏸ APPROVAL

**Co se udělá:**
1. Odstranit nepoužité inline styles (po extrakci do bo_kit.css)
2. Konsolidovat duplicitní CSS
3. Update CRM_DOKUMENTACE.md

**Riziko**: nízké.
**Effort**: ~30 minut.

---

## Časový odhad

| Krok | Effort |
|---|---|
| 0 — bo_kit.css | 30 min |
| 1 — Tabs | 30 min |
| 2 — Karty levý border | 45 min |
| 3 — Sbalitelný deník + postup | 60 min |
| 4 — Akční tlačítka | 45 min |
| 5 — Stat overview | 30 min |
| 6 — Cleanup | 30 min |

**Celkem**: ~4-5 hodin práce + iterace UX.

---

## Pravidla

1. **Backend bez změn** v krocích 1-5. Reuse stávajících POST endpointů.
2. **Žádné DB změny.**
3. **Stará BO route `/bo` zůstává funkční** během celého refactoru — refactor je čistě CSS/view.
4. Pro každý krok: před commit screenshot pro vizuální kontrolu.
5. Pokud uživatel během kteréhokoli kroku řekne "stop, takhle to nechci", vrátíme se k předchozímu stavu.
