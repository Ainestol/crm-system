<?php
declare(strict_types=1);
/** @var array<string,mixed> $topic */
/** @var string|null $flash */

// Helpery pro zkracování inline CSS v tabulkách
$th  = 'text-align:left;padding:0.5rem 0.8rem;';
$td  = 'padding:0.5rem 0.8rem;';
$tdb = 'padding:0.5rem 0.8rem;font-weight:600;';
$mono= 'background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;font-family:monospace;';
$muted = 'padding:0.5rem 0.8rem;color:#6b7280;font-family:monospace;font-size:0.85rem;';
?>

<div style="max-width:1100px;margin:0 auto;padding:1rem;">
    <div style="margin-bottom:1rem;">
        <a href="<?= crm_url('/help') ?>" style="color:#6b7280;text-decoration:none;font-size:0.9rem;">
            ← Zpět na rozcestník nápovědy
        </a>
    </div>

    <h1 style="margin:0 0 0.4rem;"><?= $topic['icon'] ?> <?= crm_h($topic['label']) ?></h1>
    <p style="color:#6b7280;margin:0 0 1.5rem;font-size:0.95rem;"><?= crm_h($topic['short']) ?></p>

    <!-- ── Stručný přehled ── -->
    <div style="background:#dbeafe;border-left:4px solid #2563eb;padding:0.9rem 1.1rem;border-radius:0 6px 6px 0;margin-bottom:1.5rem;">
        <strong>📌 Co umí import:</strong><br>
        Naimportovat libovolný stav kontaktu — od <strong>čerstvých nových</strong> (čistička) přes <strong>provolané</strong>
        (nezájem / callback / rozjednané) až po <strong>uzavřené smlouvy</strong> z OT (s číslem objednávky a BMSL).
        Kontakty se rovnou propíšou ke správné <strong>navolávačce</strong> i <strong>OZ</strong> a objeví se jim
        v pracovní ploše ve správném tabu.
    </div>

    <!-- ── Krok za krokem ── -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">📋 Postup importu</h2>
    <ol style="margin:0 0 1rem 1.2rem;line-height:1.7;">
        <li>V CSV/XLSX si připrav sloupce — jména přesně jako v tabulkách níže (na velikosti nezáleží, alternativy fungují).</li>
        <li>Jdi na <code style="<?= $mono ?>">/admin/import</code></li>
        <li>Nahraj soubor (max 200 MB, 300 000 řádků). Kódování <strong>UTF-8</strong>.</li>
        <li>Volitelně zvol <strong>výchozí kraj</strong> (pokud v souboru chybí sloupec kraj).</li>
        <li>Zvol <strong>akci pro duplicity</strong>: Aktualizovat / Přeskočit / Vždy přidat.</li>
        <li>Klikneš <strong>Analyzovat</strong> → systém ukáže preview se statistikami, chybami a per-řádek volbou.</li>
        <li>Pokud je vše OK, klikneš <strong>Commit</strong> → uloží se do DB.</li>
        <li>Auto-mix je defaultně zapnutý → po importu se kontakty rovnou zařadí 9:1 (OSVČ:firma).</li>
    </ol>

    <!-- ══════════════════════════════════════════════════════════════
         POVINNÉ SLOUPCE
    ══════════════════════════════════════════════════════════════ -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">✅ Povinné sloupce (vždy)</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr>
                <th style="<?= $th ?>width:22%;">Sloupec</th>
                <th style="<?= $th ?>width:30%;">Alternativní názvy</th>
                <th style="<?= $th ?>">Popis / Příklad</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>color:#dc2626;">firma</td>
                <td style="<?= $muted ?>">nazev_firmy, name, jmeno, subject</td>
                <td style="<?= $td ?>">
                    Název firmy nebo jméno OSVČ.<br>
                    <code style="<?= $mono ?>">ABC Stavby s.r.o.</code> · <code style="<?= $mono ?>">Jan Novák</code>
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>color:#dc2626;">kraj <em>nebo</em> region</td>
                <td style="<?= $muted ?>">kraj, region</td>
                <td style="<?= $td ?>">
                    Kód kraje (lowercase, bez diakritiky):<br>
                    <code style="<?= $mono ?>">praha</code> · <code style="<?= $mono ?>">stredocesky</code> · <code style="<?= $mono ?>">jihocesky</code>
                    · <code style="<?= $mono ?>">jihomoravsky</code> · <code style="<?= $mono ?>">karlovarsky</code>
                    · <code style="<?= $mono ?>">kralovehradecky</code> · <code style="<?= $mono ?>">liberecky</code>
                    · <code style="<?= $mono ?>">moravskoslezsky</code> · <code style="<?= $mono ?>">olomoucky</code>
                    · <code style="<?= $mono ?>">pardubicky</code> · <code style="<?= $mono ?>">plzensky</code>
                    · <code style="<?= $mono ?>">ustecky</code> · <code style="<?= $mono ?>">vysocina</code>
                    · <code style="<?= $mono ?>">zlinsky</code><br>
                    <em>Pokud sloupec chybí, použije se „výchozí kraj" z formuláře.</em>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- ══════════════════════════════════════════════════════════════
         DOPORUČENÉ SLOUPCE (pro dedupe)
    ══════════════════════════════════════════════════════════════ -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">💡 Doporučené sloupce (pro deduplikaci)</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr>
                <th style="<?= $th ?>width:22%;">Sloupec</th>
                <th style="<?= $th ?>width:30%;">Alternativní názvy</th>
                <th style="<?= $th ?>">Popis / Příklad</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">ico</td>
                <td style="<?= $muted ?>">ic, IČO, ic_</td>
                <td style="<?= $td ?>">8 číslic, dedup-key #1.<br><code style="<?= $mono ?>">12345678</code></td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">telefon</td>
                <td style="<?= $muted ?>">tel, mobil, mobile, phone, telefonni_cislo</td>
                <td style="<?= $td ?>">Dedup-key #3. Při porovnání se berou jen číslice.<br>
                    <code style="<?= $mono ?>">+420 724 111 222</code></td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">email</td>
                <td style="<?= $muted ?>">mail, e_mail</td>
                <td style="<?= $td ?>">Dedup-key #2.<br><code style="<?= $mono ?>">info@abc.cz</code></td>
            </tr>
        </tbody>
    </table>

    <!-- ══════════════════════════════════════════════════════════════
         VOLITELNÉ SLOUPCE
    ══════════════════════════════════════════════════════════════ -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">⚙️ Volitelné sloupce (kdykoli)</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr>
                <th style="<?= $th ?>width:22%;">Sloupec</th>
                <th style="<?= $th ?>width:30%;">Alternativní názvy</th>
                <th style="<?= $th ?>">Popis</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">adresa</td>
                <td style="<?= $muted ?>">ulice, okres</td>
                <td style="<?= $td ?>"><code style="<?= $mono ?>">Wenceslas 1, 110 00 Praha 1</code></td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">mesto</td>
                <td style="<?= $muted ?>">obec, municipality</td>
                <td style="<?= $td ?>"><code style="<?= $mono ?>">Praha</code></td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">operator</td>
                <td style="<?= $muted ?>">sit, carrier</td>
                <td style="<?= $td ?>">
                    Mobilní operátor (pokud znáš, jinak zjistí čistička).<br>
                    <code style="<?= $mono ?>">TM</code> · <code style="<?= $mono ?>">O2</code> · <code style="<?= $mono ?>">VF</code>
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">poznamka</td>
                <td style="<?= $muted ?>">note, poznamky</td>
                <td style="<?= $td ?>">Volný text. Vidí ho čistička, navolávačka i OZ.</td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">prilez</td>
                <td style="<?= $muted ?>">prilezitost, produkt, opportunity</td>
                <td style="<?= $td ?>">
                    Co zákazník chce / o co má zájem.<br>
                    <code style="<?= $mono ?>">3× SIM + internet</code>
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">stav</td>
                <td style="<?= $muted ?>">status, vysledek, ne_chce, chce</td>
                <td style="<?= $td ?>">
                    V jakém je kontakt aktuálně stavu. Pokud prázdné → <code>NEW</code>.<br>
                    Viz <a href="#tabulka-stavu" style="color:#2563eb;">tabulka stavů níže</a>.
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">narozeniny_majitele</td>
                <td style="<?= $muted ?>">narozeniny</td>
                <td style="<?= $td ?>">Datum narození vlastníka. Použije se v dashboardu (gratulace).<br><code style="<?= $mono ?>">1980-04-15</code></td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">vyrocni_smlouvy</td>
                <td style="<?= $muted ?>">vyroci_smlouvy</td>
                <td style="<?= $td ?>">Datum, kdy stávající smlouva u zákazníka končí.<br><code style="<?= $mono ?>">2026-09-30</code></td>
            </tr>
        </tbody>
    </table>

    <!-- ══════════════════════════════════════════════════════════════
         PŘIŘAZOVACÍ SLOUPCE (klíčové pro hot leady / rozjednané / smlouvy)
    ══════════════════════════════════════════════════════════════ -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">👥 Přiřazovací sloupce</h2>
    <p style="color:#6b7280;font-size:0.88rem;margin-bottom:0.6rem;">
        Pokud chceš kontakt rovnou přiřadit konkrétní <strong>navolávačce</strong> nebo <strong>OZ</strong>:
    </p>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr>
                <th style="<?= $th ?>width:22%;">Sloupec</th>
                <th style="<?= $th ?>width:30%;">Alternativní názvy</th>
                <th style="<?= $th ?>">Popis</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>color:#0e7490;">caller_email</td>
                <td style="<?= $muted ?>">navolavacka_email, volajici_email, volajici</td>
                <td style="<?= $td ?>">
                    <strong>Email navolávačky</strong> v systému (lowercase). Kontakt jí padne do její Pracovní plochy.<br>
                    <code style="<?= $mono ?>">evicka@firma.cz</code><br>
                    <strong>Validace:</strong> uživatel musí v systému existovat s rolí <code>navolavacka</code>.
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>color:#7e22ce;">oz_email</td>
                <td style="<?= $muted ?>">oz, obchodak, obchodak_email, prodejce, sales, sales_email</td>
                <td style="<?= $td ?>">
                    <strong>Email obchodníka</strong> v systému (lowercase). Kontakt jde rovnou jemu do Příchozí leady.<br>
                    <code style="<?= $mono ?>">honza@firma.cz</code><br>
                    <strong>Validace:</strong> uživatel musí v systému existovat s rolí <code>obchodak</code>.<br>
                    <strong>Povinné</strong> u stavů <code>FOR_SALES</code> / <code>NABIDKA</code> / <code>SCHUZKA</code>
                    / <code>BO_PREDANO</code> / <code>SMLOUVA</code> / všech uzavřených smluv.
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">navolavacka</td>
                <td style="<?= $muted ?>">caller_name, volala, volal</td>
                <td style="<?= $td ?>">
                    Pouze <strong>jméno</strong> navolávačky (info text do poznámky). Pro přiřazení použij
                    <code>caller_email</code>.<br>
                    <code style="<?= $mono ?>">Evička</code>
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">datum_volani</td>
                <td style="<?= $muted ?>">dne, datum_telefonatu, volano_dne, date_called</td>
                <td style="<?= $td ?>">
                    Kdy navolávačka volala (historický záznam). Přidá se i do poznámky.<br>
                    <code style="<?= $mono ?>">2026-04-15</code> nebo <code style="<?= $mono ?>">15.4.2026</code>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- ══════════════════════════════════════════════════════════════
         SLOUPCE PRO SMLOUVY / OBJEDNÁVKY
    ══════════════════════════════════════════════════════════════ -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">📜 Sloupce pro uzavřené smlouvy</h2>
    <p style="color:#6b7280;font-size:0.88rem;margin-bottom:0.6rem;">
        Pokud importuješ kontakty s <strong>uzavřenou smlouvou z OT</strong>:
    </p>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr>
                <th style="<?= $th ?>width:22%;">Sloupec</th>
                <th style="<?= $th ?>width:30%;">Alternativní názvy</th>
                <th style="<?= $th ?>">Popis</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>color:#dc2626;">datum_uzavreni</td>
                <td style="<?= $muted ?>">datum_uzavreni</td>
                <td style="<?= $td ?>">
                    <strong>Povinné</strong> u všech uzavřených smluv. Datum podpisu.<br>
                    <code style="<?= $mono ?>">2026-03-15</code><br>
                    <em>Automaticky vyplní stav <code>UZAVRENO</code>, podpis_potvrzen = 1, výročí = +3 roky.</em>
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>color:#dc2626;">oz_email</td>
                <td style="<?= $muted ?>">(viz výše)</td>
                <td style="<?= $td ?>">
                    <strong>Povinné</strong> u smluv — kdo smlouvu uzavřel (do statistik).
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">cislo_smlouvy</td>
                <td style="<?= $muted ?>">cislo_objednavky, cislo_ot, cislo, contract_number, order_number</td>
                <td style="<?= $td ?>">
                    <strong>Referenční číslo</strong> z OT (max 100 znaků). Vyhledatelné v datagridu.<br>
                    <code style="<?= $mono ?>">OT-2026-04567</code> · <code style="<?= $mono ?>">SML-1234</code>
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">bmsl</td>
                <td style="<?= $muted ?>">pocet_linek, pocet_smluv, units</td>
                <td style="<?= $td ?>">
                    <strong>BMSL</strong> — Báze Měsíčních Smluvních Linek. Celé číslo.<br>
                    <code style="<?= $mono ?>">3</code> (3 linky/SIM v jedné smlouvě)
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">sale_price</td>
                <td style="<?= $muted ?>">cena, cena_smlouvy, price, castka</td>
                <td style="<?= $td ?>">
                    Hodnota smlouvy v Kč (akceptuje <code>14999</code>, <code>14 999</code>, <code>14999,50</code>).<br>
                    <code style="<?= $mono ?>">3500</code>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- ══════════════════════════════════════════════════════════════
         TABULKA STAVŮ — co napsat do sloupce `stav`
    ══════════════════════════════════════════════════════════════ -->
    <h2 id="tabulka-stavu" style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">📊 Stavy — co napsat do sloupce <code>stav</code></h2>
    <p style="color:#6b7280;font-size:0.88rem;margin-bottom:0.6rem;">
        Hodnoty jsou <strong>case-insensitive</strong>, s diakritikou i bez. Pokud sloupec <code>stav</code> chybí, použije se <code>NEW</code>.
    </p>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-size:0.88rem;">
        <thead style="background:#f3f4f6;">
            <tr>
                <th style="<?= $th ?>">Co napíšeš v CSV</th>
                <th style="<?= $th ?>">Co se s kontaktem stane</th>
                <th style="<?= $th ?>">Co k tomu potřebuješ</th>
            </tr>
        </thead>
        <tbody>
            <!-- ČERSTVÉ NOVÉ -->
            <tr style="background:#dcfce7;border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">(prázdné) <em>nebo</em> NEW</td>
                <td style="<?= $td ?>">Půjde čističce na ověření operátora.</td>
                <td style="<?= $td ?>color:#9ca3af;">—</td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">READY</td>
                <td style="<?= $td ?>">Přeskočí čističku, jde rovnou do poolu navolávačkám.</td>
                <td style="<?= $td ?>color:#9ca3af;">—</td>
            </tr>

            <!-- NAVOLÁVAČKA TVRDÁ KATEGORIZACE -->
            <tr style="background:#f3f4f6;border-top:1px solid #e5e7eb;">
                <td colspan="3" style="<?= $tdb ?>color:#6b7280;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;">
                    🔻 Provolané navolávačkou
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">CALLED_OK / OK / obvoláno</td>
                <td style="<?= $td ?>">Úspěšně obvoláno (bez předání OZ).</td>
                <td style="<?= $td ?>color:#9ca3af;"><code>caller_email</code> (doporučeno)</td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">NEZÁJEM / nechce / nedovolal / nebere / typl_to</td>
                <td style="<?= $td ?>">Odmítnuto, nepoužije se. Zůstane v historii.</td>
                <td style="<?= $td ?>color:#9ca3af;"><code>caller_email</code> (doporučeno)</td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">CALLBACK / volat zpět</td>
                <td style="<?= $td ?>">Domluvený zpětný hovor, navolávačka znovu zavolá.</td>
                <td style="<?= $td ?>color:#9ca3af;"><code>datum_volani</code></td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">CHYBNY / spatny</td>
                <td style="<?= $td ?>">Chybný kontakt (špatné číslo, neexistuje, …).</td>
                <td style="<?= $td ?>color:#9ca3af;">—</td>
            </tr>

            <!-- ROZJEDNANÉ U OZ -->
            <tr style="background:#fef3c7;border-top:1px solid #e5e7eb;">
                <td colspan="3" style="<?= $tdb ?>color:#92400e;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;">
                    🤝 Rozjednané u OZ
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">CHCE / FOR_SALES / pro_oz / rozjednany</td>
                <td style="<?= $td ?>">Kontakt jde rovnou OZ, ten ho má v <em>Příchozí leady</em>.</td>
                <td style="<?= $td ?>color:#dc2626;font-weight:600;"><code>oz_email</code></td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">NABIDKA / nabidka_odeslana</td>
                <td style="<?= $td ?>">OZ poslal nabídku, čeká reakci.</td>
                <td style="<?= $td ?>color:#dc2626;font-weight:600;"><code>oz_email</code></td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">SCHUZKA</td>
                <td style="<?= $td ?>">Domluvená schůzka s OZ.</td>
                <td style="<?= $td ?>color:#dc2626;font-weight:600;"><code>oz_email</code></td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">SANCE</td>
                <td style="<?= $td ?>">Zákazník chce, chybí jen podklady.</td>
                <td style="<?= $td ?>color:#dc2626;font-weight:600;"><code>oz_email</code></td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">BO_PREDANO / predano_bo</td>
                <td style="<?= $td ?>">OZ předal back-office ke zpracování smlouvy.</td>
                <td style="<?= $td ?>color:#dc2626;font-weight:600;"><code>oz_email</code></td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">BO_VPRACI / bo_vraceno</td>
                <td style="<?= $td ?>">BO právě zpracovává nebo vrátil OZ na doplnění.</td>
                <td style="<?= $td ?>color:#dc2626;font-weight:600;"><code>oz_email</code></td>
            </tr>

            <!-- UZAVŘENÉ -->
            <tr style="background:#f3e8ff;border-top:1px solid #e5e7eb;">
                <td colspan="3" style="<?= $tdb ?>color:#7e22ce;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;">
                    📜 Uzavřené smlouvy (legacy z OT)
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="<?= $tdb ?>">SMLOUVA / UZAVRENO / DONE</td>
                <td style="<?= $td ?>">Stav = UZAVRENO, podpis = 1, počítá se do statistik OZ.</td>
                <td style="<?= $td ?>color:#dc2626;font-weight:600;"><code>oz_email</code> + <code>datum_uzavreni</code><br>
                    + volitelně <code>cislo_smlouvy</code>, <code>bmsl</code>, <code>sale_price</code></td>
            </tr>
        </tbody>
    </table>

    <!-- ══════════════════════════════════════════════════════════════
         UKÁZKY CSV
    ══════════════════════════════════════════════════════════════ -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">📄 Ukázky CSV — kompletní scénáře</h2>

    <h3 style="margin:1rem 0 0.4rem;font-size:1rem;color:#16a34a;">✅ A) Čerstvé nové kontakty (do čističky)</h3>
    <p style="font-size:0.85rem;color:#6b7280;margin:0 0 0.4rem;">
        Default scénář — kontakty z databáze, půjdou čističce → navolávačce → OZ.
    </p>
    <pre style="background:#1f2937;color:#e5e7eb;padding:1rem;border-radius:6px;overflow:auto;font-size:0.82rem;line-height:1.5;">firma;ico;telefon;email;kraj;adresa;poznamka
"Jan Novák - elektro";12345678;724111222;jan@novak.cz;stredocesky;"Hlavní 12, Beroun";""
"ABC Stavby s.r.o.";87654321;602111333;info@abc.cz;praha;"Wenceslas 1";""
"Zedník Karel";11223344;777222111;karel@zednik.cz;jihocesky;"";"Zájem o zedničku 2026"</pre>

    <h3 style="margin:1.2rem 0 0.4rem;font-size:1rem;color:#0e7490;">📞 B) Provolané navolávačkou (NEZÁJEM / CALLBACK)</h3>
    <p style="font-size:0.85rem;color:#6b7280;margin:0 0 0.4rem;">
        Když máš export ze starého systému kde navolávačka už něco prošla — zachová se historie + přiřazení.
    </p>
    <pre style="background:#1f2937;color:#e5e7eb;padding:1rem;border-radius:6px;overflow:auto;font-size:0.82rem;line-height:1.5;">firma;ico;telefon;email;kraj;stav;caller_email;datum_volani;poznamka
"Klient A";11111111;777222333;a@a.cz;jihocesky;NEZÁJEM;evicka@firma.cz;2026-04-15;"Nechce nic měnit"
"Klient B";22222222;777333444;b@b.cz;praha;CALLBACK;evicka@firma.cz;2026-04-20;"Volat v po 14:00"
"Klient C";33333333;777444555;c@c.cz;stredocesky;CALLED_OK;evicka@firma.cz;2026-04-22;"Souhlas, posílám OZ"</pre>

    <h3 style="margin:1.2rem 0 0.4rem;font-size:1rem;color:#7e22ce;">🤝 C) Rozjednané kontakty u OZ</h3>
    <p style="font-size:0.85rem;color:#6b7280;margin:0 0 0.4rem;">
        Kontakty kde OZ už pracuje (poslal nabídku, čeká schůzku, atd.). Skip navolávačku.
    </p>
    <pre style="background:#1f2937;color:#e5e7eb;padding:1rem;border-radius:6px;overflow:auto;font-size:0.82rem;line-height:1.5;">firma;ico;telefon;email;kraj;stav;oz_email;datum_volani;prilez;poznamka
"XYZ s.r.o.";44444444;777111;x@xyz.cz;jihocesky;NABIDKA;honza@firma.cz;2026-04-15;"3× SIM";"Domluvená schůzka 5/2026"
"DEF a.s.";55555555;777222;d@def.cz;praha;SCHUZKA;marketa@firma.cz;2026-04-20;"internet";"Schůzka 28.4. v Praze"
"GHI družstvo";66666666;777333;g@ghi.cz;stredocesky;BO_PREDANO;honza@firma.cz;2026-04-22;"telefon";"Předáno BO"</pre>

    <h3 style="margin:1.2rem 0 0.4rem;font-size:1rem;color:#dc2626;">📜 D) Uzavřené smlouvy z OT (legacy)</h3>
    <p style="font-size:0.85rem;color:#6b7280;margin:0 0 0.4rem;">
        Stávající aktivní zákazníci s podepsanou smlouvou. Včetně čísla objednávky + BMSL pro statistiky.
    </p>
    <pre style="background:#1f2937;color:#e5e7eb;padding:1rem;border-radius:6px;overflow:auto;font-size:0.82rem;line-height:1.5;">firma;ico;telefon;email;kraj;oz_email;datum_uzavreni;cislo_smlouvy;bmsl;sale_price;poznamka
"Klient ABC";77777777;777111;a@abc.cz;praha;honza@firma.cz;2026-03-15;OT-2026-04567;3;3500;"Aktivní zákazník"
"Klient DEF";88888888;777222;a@def.cz;jihocesky;marketa@firma.cz;2025-12-10;OT-2025-09812;5;7200;"Smlouva na 3 roky"
"Klient GHI";99999999;777333;a@ghi.cz;ustecky;honza@firma.cz;2026-01-20;OT-2026-00123;2;2800;"BMSL = 2 linky"</pre>
    <p style="font-size:0.8rem;color:#92400e;background:#fef3c7;padding:0.5rem 0.7rem;border-radius:5px;margin-top:0.4rem;">
        💡 <strong>Tip:</strong> Když je sloupec <code>datum_uzavreni</code> vyplněný, stav se automaticky nastaví na
        <code>UZAVRENO</code> bez ohledu na obsah sloupce <code>stav</code>.
    </p>

    <h3 style="margin:1.2rem 0 0.4rem;font-size:1rem;color:#374151;">🎯 E) Mix scénářů v jednom souboru</h3>
    <p style="font-size:0.85rem;color:#6b7280;margin:0 0 0.4rem;">
        Jeden soubor, různé stavy řádků. Systém každý řádek zpracuje individuálně podle stavu.
    </p>
    <pre style="background:#1f2937;color:#e5e7eb;padding:1rem;border-radius:6px;overflow:auto;font-size:0.82rem;line-height:1.5;">firma;ico;telefon;email;kraj;stav;caller_email;oz_email;datum_volani;datum_uzavreni;cislo_smlouvy;bmsl;poznamka
"Nový A";11111111;111;a@a.cz;praha;;;;;;;;"Čerstvý"
"Nezájem B";22222222;222;b@b.cz;praha;NEZÁJEM;evicka@firma.cz;;2026-04-10;;;;"Odmítl"
"Rozjednaná C";33333333;333;c@c.cz;praha;NABIDKA;;honza@firma.cz;2026-04-15;;;;"OZ čeká reakci"
"Uzavřená D";44444444;444;d@d.cz;praha;;;honza@firma.cz;;2026-03-01;OT-001;3;"Smlouva"</pre>

    <!-- ══════════════════════════════════════════════════════════════
         TIPY A ČASTÁ ÚSKALÍ
    ══════════════════════════════════════════════════════════════ -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">💡 Tipy a častá úskalí</h2>
    <ul style="line-height:1.7;margin:0 0 1rem 1.2rem;">
        <li><strong>Encoding</strong>: vždy ulož jako <code>UTF-8</code>. Excel default ANSI rozbije diakritiku.</li>
        <li><strong>Delimiter</strong>: <code>;</code> i <code>,</code> oba fungují, systém detekuje sám.</li>
        <li><strong>Hlavička</strong>: musí být na <strong>prvním řádku</strong>.</li>
        <li><strong>Diakritika v hlavičce</strong>: nezáleží (<code>IČO</code> = <code>ico</code> = <code>IC</code>).</li>
        <li><strong>Duplicity</strong>: dedupe podle IČO → email → telefon. Default akce: aktualizovat.</li>
        <li><strong>Cizí znaky v <code>caller_email</code> / <code>oz_email</code></strong>: musí to být <strong>přesně</strong>
            email uživatele v systému (lowercase). Pokud uživatel nemá správnou roli, řádek selže.</li>
        <li><strong>Datumy</strong>: <code>YYYY-MM-DD</code> i <code>DD.MM.YYYY</code> oba OK.</li>
        <li><strong>BMSL</strong>: jen celé číslo. Mezery / desetinné se ignorují (<code>3.5</code> → <code>3</code>).</li>
        <li><strong>DNC list</strong>: kontakty na blacklistu se automaticky přeskočí (vidíš v preview).</li>
        <li><strong>Auto-mix</strong>: po importu se rovnou zamíchá 9:1. Vypneš v <code>/admin/contacts/mix → ⚙️ Nastavení</code>.</li>
        <li><strong>Test první</strong>: vždy zkus 2-3 řádky než pustíš plný import.</li>
    </ul>

    <!-- ══════════════════════════════════════════════════════════════
         FLOW DIAGRAM
    ══════════════════════════════════════════════════════════════ -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">🔄 Co se s kontaktem stane po importu</h2>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;font-family:monospace;font-size:0.82rem;line-height:1.7;white-space:pre;overflow:auto;">
[A] NEW (čerstvý)
    → Čistička v /cisticka → ověří TM/O2/VF
    → stav = READY (TM/O2) nebo VF_SKIP (Vodafone) nebo CHYBNY_KONTAKT
    → Navolávačka v /caller volá
    → Po výhře: stav = CALLED_OK + přiřazení OZ
    → OZ v /oz/queue přijme

[B] CALLED_OK / NEZÁJEM / CALLBACK (s caller_email)
    → Přiřazeno konkrétní navolávačce (Evička)
    → Najde ho ve své Pracovní ploše / historii hovorů
    → Pokud CALLED_OK + má volný OZ → padne mu

[C] FOR_SALES / NABIDKA / SCHUZKA / SANCE / BO_PREDANO (s oz_email)
    → Skip navolávačku, jde rovnou OZ (Honza)
    → OZ ho má v /oz/leads v správném tabu
    → BMSL / cislo_smlouvy už předvyplněné (pokud byly v CSV)

[D] UZAVRENO + datum_uzavreni (s oz_email)
    → Stav = UZAVRENO, podpis_potvrzen = 1
    → BMSL + cislo_smlouvy se zapíšou do workflow
    → Počítá se do měsíčních statistik OZ
    → Výročí smlouvy = datum_uzavreni + 3 roky (notifikace 180 dní před)
    </div>

    <div style="margin-top:1.5rem;background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;padding:0.9rem 1.1rem;font-size:0.88rem;color:#92400e;">
        ⚠️ <strong>POZOR:</strong> Před hromadným importem vždy vyzkoušej na <strong>2-3 řádcích</strong>. Preview ti ukáže
        co systém detekoval a co nepoznal. Až pak plný import.
    </div>
</div>
