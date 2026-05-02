# OZ Refactor Plan — `oz/leads.php` (2551 řádků) → 3 obrazovky

> Filozofie: **paralelní migrace**. Staré routes zůstanou funkční až do konce. Žádný big-bang. Každý krok má clear deliverable + approval gate.

---

## Cílová architektura

| URL | Soubor (nový) | Účel | Sekundární / detail | Klávesy |
|---|---|---|---|---|
| `/oz/queue` | `app/views/oz/queue.php` | Příchozí leady od navolávaček + Přijmout | renewal alerty | ↑↓ J/K + Enter |
| `/oz/work?id=X` | `app/views/oz/work.php` | Call screen — fokus na 1 lead | poznámky, akce | 1-5 = akce |
| `/oz` | `app/views/oz/dashboard.php` (refactor existing index.php) | Dashboard — kvóta, závod, milestones, stages | sbalené `<details>` | žádné |

**Stará URL `/oz/leads`**: zůstává funkční až do dokončení migrace, pak deprecate (redirect → `/oz/queue`).

---

## Krok 0 — Přípravná infrastruktura ⏸ APPROVAL

**Co se udělá:**
1. Vytvoří se nový CSS soubor `public/assets/css/oz_kit.css` s tokeny z `OZ_UI_KIT.md` (sekce 12 cheat sheet) + base komponenty (`.oz-btn-*`, `.oz-tab*`, `.oz-badge*`).
2. CSS se importuje **jen v nových views** přes `<link>`. Stará `oz/leads.php` ho neuvidí.

**Riziko**: nulové. Nový soubor, nikdo ho nepoužívá.

**Deliverable**: 1 CSS soubor s ~200 řádky tokenů + komponenta. Žádný PHP, žádné views.

**Approval potřeba**: Ano — ujistit se, že hodnoty (zejména barvy) sedí podle UI kit.

---

## Krok 1 — `/oz/queue` (incoming pending) ⏸ APPROVAL

**Co se udělá:**
1. Nová route `GET /oz/queue` v `app/Router.php` (role: obchodak)
2. Nová metoda `OzController::getQueue()` — načte pending kontakty od navolávaček + renewal alerts
3. Nový view `app/views/oz/queue.php` — minimální:
   - Header: „📋 Příchozí leady (X)"
   - List karet pending leadů. Každá karta:
     - Header: jméno + region badge
     - Detail (sbalené): tel, email, IČO
     - Akce: **`[Přijmout]`** primary, **[Odmítnout]** negative
   - Klávesy: ↑↓ navigace + Enter = přijmout
4. Reuse stávajících controller metod pro „Accept/Reject" pending lead (žádný backend refactor)

**Riziko**: nízké. Nová route, neovlivňuje existující.

**Migration ze starého**: V `/oz/leads` zůstane levý sidebar s pending stacks. Kdo chce „lepší UX", půjde na `/oz/queue`. Po skončení migrace se levý sidebar z `/oz/leads` odstraní (nebo `/oz/leads` celé deprecate).

**Deliverable**: funkční `/oz/queue`, 1 nová route, ~250 řádků nového kódu (controller + view).

**Approval potřeba**: Ano — ukázat screenshot/wireframe, schválit prioritu prvků.

---

## Krok 2 — `/oz/work?id=X` (call screen) ⏸ APPROVAL

**Co se udělá:**
1. Nová route `GET /oz/work` v `app/Router.php`
2. Nová metoda `OzController::getWork()` — načte 1 kontakt podle `?id=X` + jeho poznámky a historii akcí
3. Nový view `app/views/oz/work.php` — fokusovaný call screen:
   - Header s velkým jménem + telefon
   - Detail sbalený default
   - Textarea poznámka
   - 4-5 akcí: 📞 Zavolat (primary), Nabídka, Schůzka, Callback (secondary), Nezájem (negative)
   - **NIC** dalšího — žádné stats, závod, milestones
4. Reuse stávajících POST endpointů pro akce (status update, callback, atd.) — žádný backend refactor

**Klíčový UX detail**: Po kliknutí akce → buď:
   - (a) zůstat na call screenu pro další akci (např. po Callback) → reload `/oz/work?id=X`
   - (b) přesměrovat na další pending lead → redirect `/oz/queue` nebo `/oz/work?id=NEXT`

→ K tomuto chování se rozhodneme v Kroku 2a.

**Migration**: V `/oz/queue` má každý pending lead button „Přijmout", který přesměruje na `/oz/work?id=X`. V `/oz/leads` může vzniknout link „Otevřít fokus" který udělá totéž.

**Riziko**: středně nízké. Nová route, nová logika navigace mezi obrazovkami.

**Deliverable**: funkční `/oz/work?id=X`, ~400 řádků nového kódu.

**Approval potřeba**: Ano — schválit chování po akci (zůstat / další lead).

---

## Krok 3 — `/oz` dashboard refactor ⏸ APPROVAL

**Co se udělá:**
1. Stávající `app/views/oz/index.php` (Moje leady) se přejmenuje konceptuálně na „dashboard"
2. Stats bar zůstane, ale **sbalit do `<details>` default zavřené** pro „📊 Souhrn měsíce"
3. Závod (snail race) z `/oz/leads` se přesune sem do dalšího `<details>` „🐌 Sněží závod"
4. Měsíční regiony přehled zůstane (je hlavní obsah dashboardu)
5. Top toolbar: jediný link „💼 Pracovní plocha" → `/oz/queue` (změna z dnešního `/oz/leads`)

**Riziko**: nízké. Není tu žádný refactor logiky, jen přeskupení existujícího markupu.

**Deliverable**: existující `/oz` view zjednodušený, žádné stat boxy v hlavní zóně, závod sbalený.

**Approval potřeba**: Ano — schválit, co se sbalí a co zůstane.

---

## Krok 4 — Migrace stages / milestones / team ⏸ APPROVAL

**Co se udělá:**
- Stages, personal milestones, team stats — všechno přesunout buď:
  - (a) do `/oz/dashboard` jako další `<details>`
  - (b) do samostatné stránky `/oz/dashboard/details` (větší dataset)

→ K tomu se rozhodneme v Kroku 4a.

**Riziko**: nízké — read-only displeje.

**Deliverable**: stages/milestones/team odstraněné z `/oz/leads`, přesunuté do dashboardu nebo samostatné stránky.

**Approval potřeba**: Ano — schválit destinaci.

---

## Krok 5 — Deprecate `/oz/leads` ⏸ APPROVAL

**Co se udělá:**
1. `/oz/leads` route → 301 redirect na `/oz/queue` (s `?id=X` parametrem se udělá redirect na `/oz/work?id=X`)
2. Soubor `app/views/oz/leads.php` (2551 řádků) přepsán na **stub** s komentářem (jako jsme udělali u `AdminContactsAssignmentController`) — uživatel ho pak ručně smaže
3. Controller metoda `OzController::getLeads()` přepsána na `crm_redirect('/oz/queue')`

**Riziko**: středně vysoké. Je to **finální migrace** — pokud se v krocích 1-4 zapomnělo na něco, co byl `/oz/leads`, tady to praskne.

**Před tímto krokem**: kompletní QA průchod od admina + jednoho OZ. Žádný regrese funkcionality.

**Deliverable**: stará URL deprecated, kód odstraněn.

**Approval potřeba**: Ano — finální schválení po QA.

---

## Krok 6 — Cleanup ⏸ APPROVAL

**Co se udělá:**
1. Nepoužívané CSS pravidla z `public/assets/css/app.css` (jen ta, co se týkala výhradně `/oz/leads` a nikoho jiného)
2. Nepoužívané PHP funkce v `OzController` (které servisovaly jen `/oz/leads`)
3. Update `CRM_DOKUMENTACE.md` — nové URL, smazat staré

**Riziko**: středně vysoké. Pozor na CSS pravidla, která se používají sdíleně (např. `.cist-row` se používá i na čističce).

**Deliverable**: čistý kód, žádný dead weight.

**Approval potřeba**: Ano — review diffů.

---

## Časový odhad (orientační)

| Krok | Effort | Vyžaduje approval |
|---|---|---|
| 0 (CSS infra) | 1 hodina | rychlý |
| 1 (`/oz/queue`) | 3-4 hodiny | UX wireframe |
| 2 (`/oz/work`) | 4-6 hodin | UX + flow |
| 3 (dashboard refactor) | 2-3 hodiny | sbalení widgets |
| 4 (stages/milestones) | 2-3 hodiny | destinace |
| 5 (deprecate) | 1 hodina po QA | finální OK |
| 6 (cleanup) | 2 hodiny | review diffů |

**Celkem**: 15-20 hodin práce + QA + iterace UX.
**Realisticky**: 1-2 týdny po malých dávkách, ne najednou.

---

## Pravidla po celou dobu refactoru

1. **Každý krok je samostatný PR / commit**. Žádné „zatím to slepím a pak to vyleštím".
2. **Stará `/oz/leads` musí fungovat** až do kroku 5. Žádné částečné rozbití.
3. **Žádné mazání bez approvalu**. Při deprecate (krok 5+6) se píše stub s komentářem, fyzické smazání ručně.
4. **Pro každý nový view**: 100% použít tokeny z `OZ_UI_KIT.md`. Nepoužívat hardcoded barvy (`#2ecc71` → `var(--oz-primary)`).
5. **Po každém kroku**: poslat screenshot pro vizuální kontrolu před commitem do master.
6. **Backend bez změn** v krocích 1-4. Reuse stávajících POST endpointů. Refactor backendu = samostatný projekt.

---

## Rozhodnutí (✅ schváleno uživatelem 2026-05-01)

### A) Přijmout = OZ získává kontakt do své moci
**Mechanismus** (využití existující backend logiky):
- Pending lead = `assigned_sales_id = OZ` AND `stav = 'CALLED_OK'` AND `oz_contact_workflow` neexistuje pro tento OZ
- Klik **Přijmout** → POST `/oz/accept-lead` (**EXISTUJÍCÍ ENDPOINT**, řádek 705 v `OzController.php`)
- Backend: INSERT do `oz_contact_workflow` + `UPDATE contacts SET stav='NOVE'`
- Po Přijmutí: redirect na `/oz/work?id=X` (call screen)
- Lead zmizí z `/oz/queue` (protože už má workflow záznam)

**Žádná nová migrace**, žádný nový stav, žádný nový endpoint. Backend zůstává nedotčený. ✓

### B) Po akci na call screenu = stay + ruční „Další lead"
- Po kliknutí akce (Nabídka / Schůzka / Callback / Nezájem) → reload `/oz/work?id=X` se confirmation toast
- Lead se zobrazí v aktualizovaném stavu (přesune se do správného tabu při dalším návratu na dashboard)
- Vedle nahoře button „📋 Další lead" který vrátí na `/oz/queue`
- (Auto-flow „další lead automaticky" lze přidat později jako opt-in v settings)

**Důvod**: OZ často potřebuje na jednom kontaktu udělat víc kroků (callback → poznámka → nabídka → atd.). Auto-redirect by ho rušil.

### C) Renewal alerts → `/oz/queue`
Layout queue obrazovky:
1. **⚠ Renewal alerty** (X) — pokud existují
2. **🔥 Callback dnes** (X) — pokud existují
3. **📋 Příchozí leady** (X) — hlavní obsah
4. (volitelně) **🐌 Závod náhled** — sbalený `<details>`

---

## Status

| Krok | Status |
|---|---|
| 0 | ⏸ čekám na approval |
| 1 | ⏸ |
| 2 | ⏸ |
| 3 | ⏸ |
| 4 | ⏸ |
| 5 | ⏸ |
| 6 | ⏸ |
