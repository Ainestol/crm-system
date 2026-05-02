# OZ UI KIT — Design Tokens & Komponenty

> Účel: jednotný vizuální systém pro OZ obrazovky.
> Princip: **1 věc křičí, ostatní šeptají**. Méně barev = větší srozumitelnost.
> Cílová platforma: dark mode, dark theme stávajícího CRM.

---

## 1. Barevný systém

### 1.1 Pravidlo 3 aktivních barev

| Role | Barva | Hex | Použití |
|---|---|---|---|
| **Primary** | zelená | `#2ecc71` | Pozitivní akce (Přijmout, Schůzka, Win, Pokračovat). Aktivní stav (filtr, tab). Progress fill. |
| **Error** | červená | `#e74c3c` | Pouze negativní akce (Nezájem, Smazat) a chybové stavy. NIKDY pro „upozornění". |
| **Warning amber** | oranžová | `#f0a030` | Pouze časově citlivé (callback dnes, schůzka za hodinu, smlouva blíží konce). Maximálně **1× na obrazovce**. |

**Zakázané:** modrá, fialová, žlutá, růžová pro běžné stavy. Pokud potřebuješ rozlišit kategorii (TM/O2/VF), použij šedou variantu + textový label, ne barvu.

### 1.2 Neutrální paleta

```css
:root {
  --oz-bg:           #0d141f;     /* hlavní pozadí */
  --oz-card:         #121a26;     /* karty */
  --oz-card-hover:   #18222e;     /* hover stav karty */
  --oz-border:       rgba(255,255,255,0.08);
  --oz-border-soft:  rgba(255,255,255,0.04);
  --oz-text:         #e6edf3;     /* hlavní text */
  --oz-text-2:       #a8b2c0;     /* sekundární text */
  --oz-text-3:       #6b7785;     /* tlumený text (metadata, hints) */
  --oz-text-disabled:#3d4654;
}
```

### 1.3 Aktivní barvy — utility

```css
:root {
  --oz-primary:      #2ecc71;
  --oz-primary-50:   rgba(46,204,113,0.10);  /* fill jemný */
  --oz-primary-20:   rgba(46,204,113,0.20);  /* fill střední */
  --oz-primary-40:   rgba(46,204,113,0.40);  /* outline focus */

  --oz-error:        #e74c3c;
  --oz-error-50:     rgba(231,76,60,0.10);
  --oz-error-20:     rgba(231,76,60,0.20);

  --oz-warning:      #f0a030;
  --oz-warning-50:   rgba(240,160,48,0.10);
}
```

### 1.4 Pravidla použití

- **Aktivní filtr / tab** = primary border-bottom + primary text. Ostatní = neutrální `--oz-text-3` + bez borderu.
- **Aktivní karta** (současný lead) = primary outline + primary fill 20%. Ostatní opacity 0.55.
- **Tlačítka** — viz sekce 4.
- **Badges** — viz sekce 5. Většinou neutrální (šedý fill + šedý text). Barevné jen výjimečně.

---

## 2. Typografická škála

```css
:root {
  --oz-text-xs:   0.70rem;   /* 11px — metadata, kbd hints */
  --oz-text-sm:   0.82rem;   /* 13px — sekundární text */
  --oz-text-base: 0.92rem;   /* 15px — body */
  --oz-text-lg:   1.10rem;   /* 18px — subnadpisy */
  --oz-text-xl:   1.40rem;   /* 22px — nadpisy karet */
  --oz-text-2xl:  1.80rem;   /* 29px — hero čísla, jméno v call screenu */
}
```

**Pravidlo hierarchie**:
- Jméno kontaktu na call screenu = `2xl`, weight 700
- Telefon (klikací) = `xl`, weight 500
- Region badge = `sm`, weight 600 v badge stylu
- Detail (email, IČO, adresa) = `sm`, color `--oz-text-3`
- Časové metadata (`před 2 h`, `dnes 14:30`) = `xs`, color `--oz-text-3`

**Font-weight**:
- 400 = běžný text
- 500 = phone, action labels
- 600 = badges, button labels
- 700 = nadpisy, jména
- **NIKDY** kombinovat 3 různé weighty na jednom řádku.

---

## 3. Spacing scale

```css
:root {
  --oz-space-0:  0;
  --oz-space-1:  0.25rem;  /* 4px */
  --oz-space-2:  0.5rem;   /* 8px */
  --oz-space-3:  0.75rem;  /* 12px */
  --oz-space-4:  1rem;     /* 16px */
  --oz-space-5:  1.5rem;   /* 24px */
  --oz-space-6:  2rem;     /* 32px */
  --oz-space-8:  3rem;     /* 48px */
}
```

**Pravidlo „dech"**: mezi nesouvisejícími sekcemi `--oz-space-5` nebo `--oz-space-6`. Mezi souvisejícími prvky `--oz-space-2` nebo `--oz-space-3`.

---

## 4. Tlačítka — hierarchie

### 4.1 Pravidlo „1 primární na obrazovce"

Každá obrazovka má **MAX 1 primární tlačítko** (CTA = Call To Action). Vše ostatní = secondary nebo ghost. Když máš 4 akce stejně důležité — žádná není primární, použij secondary.

### 4.2 4 styly tlačítek

```css
/* PRIMARY — největší, plný fill, jen 1× na obrazovce */
.oz-btn-primary {
  background: var(--oz-primary);
  color: #fff;
  border: none;
  padding: 0.7rem 1.4rem;
  font-size: var(--oz-text-base);
  font-weight: 600;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.15s, transform 0.1s;
}
.oz-btn-primary:hover  { background: #27ae60; }
.oz-btn-primary:active { transform: translateY(1px); }

/* SECONDARY — outline, neutrální, hlavní pracovní akce */
.oz-btn-secondary {
  background: transparent;
  color: var(--oz-text);
  border: 1px solid var(--oz-border);
  padding: 0.6rem 1.2rem;
  font-size: var(--oz-text-base);
  font-weight: 500;
  border-radius: 8px;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.oz-btn-secondary:hover {
  border-color: var(--oz-primary-40);
  background: var(--oz-primary-50);
  color: var(--oz-primary);
}

/* NEGATIVE — pro destruktivní/odmítavé akce. Subtle, ne křičí. */
.oz-btn-negative {
  background: transparent;
  color: var(--oz-text-3);
  border: 1px solid var(--oz-border);
  padding: 0.5rem 1rem;
  font-size: var(--oz-text-sm);
  font-weight: 500;
  border-radius: 8px;
  cursor: pointer;
  transition: color 0.15s, border-color 0.15s;
}
.oz-btn-negative:hover {
  color: var(--oz-error);
  border-color: var(--oz-error);
}

/* GHOST — ikony, edit buttons, ne-akce */
.oz-btn-ghost {
  background: transparent;
  color: var(--oz-text-3);
  border: none;
  padding: 0.4rem 0.6rem;
  font-size: var(--oz-text-sm);
  cursor: pointer;
  border-radius: 6px;
}
.oz-btn-ghost:hover {
  background: var(--oz-border-soft);
  color: var(--oz-text);
}
```

### 4.3 Velikosti

| Třída | Padding | Font | Použití |
|---|---|---|---|
| `.oz-btn-lg` | 0.9rem 1.6rem | `--oz-text-lg` | Hlavní akce v call screenu |
| (default) | 0.7rem 1.4rem | `--oz-text-base` | Standardní |
| `.oz-btn-sm` | 0.4rem 0.85rem | `--oz-text-sm` | Inline akce, toolbary |
| `.oz-btn-icon` | 0.5rem | (ikona) | Jen ikona, čtverec |

### 4.4 Příklad správné hierarchie (call screen)

```
[ 📞 ZAVOLAT ]   ← primary, large
[ ✉ Nabídka ]   [ 📅 Schůzka ]   [ ↻ Callback ]   ← 3× secondary
                                                    [ Nezájem ]   ← negative, vpravo
```

Jen 1 primary, 3 secondary, 1 negative — celkem 5 akcí. Dál už je to chaos.

---

## 5. Badges & štítky

### 5.1 Pravidlo

Badge = **strukturní informace** (region, stav, typ), NE „upozornění". Pro upozornění použij ikonu/text vedle.

### 5.2 4 styly

```css
/* NEUTRAL — default, většina případů */
.oz-badge {
  display: inline-block;
  font-size: var(--oz-text-xs);
  font-weight: 600;
  padding: 0.18rem 0.5rem;
  border-radius: 4px;
  background: rgba(255,255,255,0.06);
  color: var(--oz-text-2);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

/* PRIMARY — aktivní stav */
.oz-badge--primary {
  background: var(--oz-primary-20);
  color: var(--oz-primary);
}

/* WARNING — jen časově kritické (max 1× na obrazovce) */
.oz-badge--warning {
  background: var(--oz-warning-50);
  color: var(--oz-warning);
}

/* ERROR — chybný lead, smazaný účet */
.oz-badge--error {
  background: var(--oz-error-50);
  color: var(--oz-error);
}
```

### 5.3 Pravidlo „Chybný lead"

❌ **Špatně dnes**: velký červený badge `⚡ Chybný lead - Důvod: XXX` napříč kartou.
✅ **Správně**: malá ikona ⚠ vedle jména + tooltip s důvodem. Nebo subtle červená tečka. Při kliknutí rozbalí detail.

---

## 6. Layout: Card kontaktu (3 zóny)

```
┌─────────────────────────────────────────────────┐
│ HEADER (dominant)                                │
│   Pavel Novák              [Olomoucký]          │
│   📞 776 123 456                                 │
├─────────────────────────────────────────────────┤
│ DETAIL (default sbalitelné, šedé, malé)          │
│   ▾ Kontakty: pavel@... · IČO 1234... · adresa │
├─────────────────────────────────────────────────┤
│ POZNÁMKY (focus zone)                            │
│   [textarea]                                     │
├─────────────────────────────────────────────────┤
│ AKCE (primary akce dominantní)                   │
│   [📞 ZAVOLAT]                                   │
│   [Nabídka] [Schůzka] [Callback]   [Nezájem]   │
└─────────────────────────────────────────────────┘
```

### 6.1 HEADER

- Jméno: `--oz-text-2xl`, weight 700, color `--oz-text`
- Telefon: `--oz-text-xl`, weight 500, color `--oz-primary` (klikací = `<a href="tel:...">`)
- Region badge: vpravo, `.oz-badge` (neutrální)
- Chybný lead: `⚠` ikona vedle jména, `--oz-error`, malá

### 6.2 DETAIL

- `<details>` element, default `<summary>` ukazuje "▾ Detail kontaktu"
- Po rozbalení: jeden grid 2 sloupce, `--oz-text-sm`, color `--oz-text-3`
- Edit ikony `.oz-btn-ghost` jen na hover (opacity 0 → 0.7)

### 6.3 POZNÁMKY

- Textarea, `--oz-text-base`, neutrální border
- Focus: border `--oz-primary-40`, background subtle změna
- Ne-aktivní: `border: 1px solid var(--oz-border)`, žádný outline
- Min height: 80px, ale resize: vertical

### 6.4 AKCE

- 1 primary tlačítko nahoře nebo vlevo (např. „📞 ZAVOLAT")
- Sekundární akce v řadě, gap `--oz-space-3`
- Negativní akce vpravo, oddělená `margin-left: auto`

---

## 7. Layout: Filter tabs

```css
.oz-tabs {
  display: flex;
  gap: 0.1rem;
  border-bottom: 2px solid var(--oz-border);
  margin-bottom: var(--oz-space-4);
}
.oz-tab {
  padding: 0.55rem 1rem;
  color: var(--oz-text-3);
  opacity: 0.55;
  font-weight: 500;
  font-size: var(--oz-text-base);
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  border-radius: 6px 6px 0 0;
  cursor: pointer;
  transition: opacity 0.15s, color 0.15s, background 0.15s;
}
.oz-tab:hover {
  opacity: 0.9;
  color: var(--oz-text);
  background: var(--oz-border-soft);
}
.oz-tab--active {
  opacity: 1;
  color: var(--oz-primary);
  border-bottom-color: var(--oz-primary);
  background: var(--oz-primary-50);
  font-weight: 700;
}
.oz-tab__badge {
  font-size: var(--oz-text-xs);
  margin-left: 0.3rem;
  background: rgba(255,255,255,0.08);
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  opacity: 0.7;
}
.oz-tab--active .oz-tab__badge {
  background: var(--oz-primary-20);
  color: var(--oz-primary);
  opacity: 1;
}
```

**Pravidlo**: Aktivní tab = jediná barva v řadě. Ostatní šedé. Žádné win=zelený, loss=červený, callback=oranžový jako dnes — to mate.

---

## 8. Layout: Stats / dashboard widgets

Stats jsou „dashboard view", NE „call view". Default zavřené, sbalené pod `<details>`:

```html
<details class="oz-stats-details">
  <summary>📊 Souhrn měsíce</summary>
  <div class="oz-stats-grid">
    <!-- 5 stat cards -->
  </div>
</details>
```

```css
.oz-stats-details summary {
  font-size: var(--oz-text-sm);
  color: var(--oz-text-3);
  cursor: pointer;
  padding: 0.5rem 0.8rem;
  border: 1px solid var(--oz-border);
  border-radius: 8px;
  list-style: none;
  user-select: none;
}
.oz-stats-details summary:hover {
  color: var(--oz-text);
  background: var(--oz-border-soft);
}
.oz-stats-details[open] summary {
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
}
```

**Pravidlo**: Závody, milníky, team progress — **vše do `<details>` default zavřeného**. OZ to potřebuje 1× za den, ne pořád na očích.

---

## 9. Stavové transitions (mikrointerakce)

| Akce | Transition |
|---|---|
| Hover (button, tab, card) | `0.15s ease` |
| Focus (input) | `0.18s ease` |
| Active (klik) | `transform: translateY(1px)`, `0.1s` |
| Sliding panel open/close | `0.25s cubic-bezier(.4,0,.2,1)` |
| Card change focus (active highlight) | `0.18s ease`, žádný animation, jen transition |

**Žádné animace > 0.5s.** Žádný bouncing, žádné rotace, žádná emojí animace. UX má být klidné.

---

## 10. Accessibility minimum

- Focus outline: `outline: 2px solid var(--oz-primary-40); outline-offset: 2px;` na **všech** clickable
- Click target: minimum 36×36 px (touch-friendly)
- Kontrast textu na pozadí: alespoň 4.5:1 pro běžný text, 3:1 pro velký text
- Keyboard navigace: tab order musí dávat smysl. Aktivní tlačítka mají `:focus-visible` style stejný jako hover
- `<a>` vs `<button>`: linky jen pro navigaci (URL change), buttony pro akce (form submit, JS callback)

---

## 11. Anti-patterns — co nedělat

❌ Mít 4+ barev v jednom řádku
❌ Více než 1 primary button na obrazovce
❌ Stejně velké všechno (jméno + email + IČO všechno 14px)
❌ Animované ikony pro běžné stavy
❌ Plný red badge pro „warning" (má být subtle ⚠ ikona)
❌ Stat boxy na call screenu (focus mode = NIC navíc)
❌ Více než 5 akcí v jedné skupině tlačítek
❌ Rgba s alpha < 0.04 (uživatel to nevidí na 99 % monitorů)
❌ Border-radius nesourodý (4px, 6px, 8px, 10px na jedné obrazovce)

---

## 12. Quick reference — copy-paste cheat sheet

```css
/* === OZ DESIGN TOKENS === */
:root {
  /* Colors */
  --oz-primary: #2ecc71;
  --oz-primary-50: rgba(46,204,113,0.10);
  --oz-primary-20: rgba(46,204,113,0.20);
  --oz-primary-40: rgba(46,204,113,0.40);
  --oz-error: #e74c3c;
  --oz-error-50: rgba(231,76,60,0.10);
  --oz-warning: #f0a030;
  --oz-warning-50: rgba(240,160,48,0.10);
  --oz-bg: #0d141f;
  --oz-card: #121a26;
  --oz-card-hover: #18222e;
  --oz-border: rgba(255,255,255,0.08);
  --oz-border-soft: rgba(255,255,255,0.04);
  --oz-text: #e6edf3;
  --oz-text-2: #a8b2c0;
  --oz-text-3: #6b7785;

  /* Typography */
  --oz-text-xs: 0.70rem;
  --oz-text-sm: 0.82rem;
  --oz-text-base: 0.92rem;
  --oz-text-lg: 1.10rem;
  --oz-text-xl: 1.40rem;
  --oz-text-2xl: 1.80rem;

  /* Spacing */
  --oz-space-1: 0.25rem;
  --oz-space-2: 0.5rem;
  --oz-space-3: 0.75rem;
  --oz-space-4: 1rem;
  --oz-space-5: 1.5rem;
  --oz-space-6: 2rem;
}
```

---

## 13. Co dál

Tento dokument je **zdroj pravdy** pro veškeré OZ obrazovky.
Při refactoru `oz/leads.php` (viz `OZ_REFACTOR_PLAN.md`) se z něj načerpají všechny styly.
Když přijdou nové obrazovky (`/oz/queue`, `/oz/work`, `/oz/dashboard`), použijí se přímo tyto tokeny.

**Zlatá pravidla pro každou novou OZ obrazovku:**
1. Max 3 aktivní barvy (primary + error + 1 warning, jen pokud potřeba)
2. Max 1 primary button
3. Hierarchie textu: 1 hero, 1 body, 1 muted — ne víc
4. Stats default sbalené v `<details>`
5. Karta kontaktu: 3 zóny (Header / Detail / Akce), nic víc
