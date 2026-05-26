<?php
declare(strict_types=1);
/** @var array<string,mixed> $topic */
/** @var string|null $flash */
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
        <strong>📌 Co to dělá:</strong> Import nahrává nové kontakty do systému z CSV / XLSX souboru.
        Můžeš importovat <strong>čisté nové kontakty</strong> (půjdou k čističce), nebo
        <strong>rozjednané kontakty</strong> kde už OZ s nimi pracuje (rovnou ke konkrétnímu OZ).
    </div>

    <!-- ── Krok za krokem ── -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">📋 Postup importu</h2>
    <ol style="margin:0 0 1rem 1.2rem;line-height:1.7;">
        <li>Jdi na <code style="background:#f3f4f6;padding:0.1rem 0.4rem;border-radius:3px;">/admin/import</code></li>
        <li>Nahraj CSV nebo XLSX soubor (max 200 MB, 300 000 řádků)</li>
        <li>Volitelně zvol <strong>výchozí kraj</strong> (pokud v souboru chybí sloupec kraj)</li>
        <li>Zvol <strong>akci pro duplicity</strong>: Aktualizovat / Přeskočit / Vždy přidat</li>
        <li>Klikneš <strong>Analyzovat</strong> → systém zobrazí preview se statistikami a chybami</li>
        <li>Pokud je vše OK, klikneš <strong>Commit</strong> → kontakty se uloží do DB</li>
        <li>Pokud je auto-mix zapnutý (default), kontakty se rovnou zamíchají 9:1</li>
    </ol>

    <!-- ── Sloupce — povinné ── -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">✅ Povinné sloupce</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr>
                <th style="text-align:left;padding:0.5rem 0.8rem;width:25%;">Sloupec</th>
                <th style="text-align:left;padding:0.5rem 0.8rem;width:30%;">Alternativní názvy</th>
                <th style="text-align:left;padding:0.5rem 0.8rem;">Popis / Příklad</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;color:#dc2626;">firma</td>
                <td style="padding:0.5rem 0.8rem;color:#6b7280;font-family:monospace;font-size:0.85rem;">
                    nazev_firmy, name, jmeno, subject
                </td>
                <td style="padding:0.5rem 0.8rem;">
                    Název firmy nebo jméno OSVČ.<br>
                    <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">ABC Stavby s.r.o.</code> · <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">Jan Novák</code>
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;color:#dc2626;">kraj nebo region</td>
                <td style="padding:0.5rem 0.8rem;color:#6b7280;font-family:monospace;font-size:0.85rem;">
                    kraj, region
                </td>
                <td style="padding:0.5rem 0.8rem;">
                    Kód kraje (kebab-case, malá písmena).<br>
                    <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">praha</code> · <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">stredocesky</code> · <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">jihocesky</code> · <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">liberecky</code> · …<br>
                    <em>Pokud chybí, použije se „výchozí kraj" z formuláře.</em>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- ── Sloupce — doporučené ── -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">💡 Doporučené sloupce (pro dedupe)</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr>
                <th style="text-align:left;padding:0.5rem 0.8rem;width:25%;">Sloupec</th>
                <th style="text-align:left;padding:0.5rem 0.8rem;width:30%;">Alternativní názvy</th>
                <th style="text-align:left;padding:0.5rem 0.8rem;">Popis / Příklad</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">ico</td>
                <td style="padding:0.5rem 0.8rem;color:#6b7280;font-family:monospace;font-size:0.85rem;">ic, IC, IČO, ic_</td>
                <td style="padding:0.5rem 0.8rem;">
                    IČO (8 znaků). Klíč pro dedup.<br>
                    <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">12345678</code>
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">telefon</td>
                <td style="padding:0.5rem 0.8rem;color:#6b7280;font-family:monospace;font-size:0.85rem;">tel, mobil, mobile, phone, telefonni_cislo</td>
                <td style="padding:0.5rem 0.8rem;">
                    Telefon. Při dedup se porovnává jen čisté číslice.<br>
                    <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">+420 724 111 222</code> · <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">724111222</code>
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">email</td>
                <td style="padding:0.5rem 0.8rem;color:#6b7280;font-family:monospace;font-size:0.85rem;">mail, e_mail</td>
                <td style="padding:0.5rem 0.8rem;">
                    Kontaktní email firmy.<br>
                    <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">info@abc.cz</code>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- ── Sloupce — volitelné ── -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">⚙️ Volitelné sloupce</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr>
                <th style="text-align:left;padding:0.5rem 0.8rem;width:25%;">Sloupec</th>
                <th style="text-align:left;padding:0.5rem 0.8rem;width:30%;">Alternativní názvy</th>
                <th style="text-align:left;padding:0.5rem 0.8rem;">Popis / Příklad</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">adresa</td>
                <td style="padding:0.5rem 0.8rem;color:#6b7280;font-family:monospace;font-size:0.85rem;">ulice, okres</td>
                <td style="padding:0.5rem 0.8rem;"><code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">Wenceslas 1, 110 00 Praha 1</code></td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">mesto</td>
                <td style="padding:0.5rem 0.8rem;color:#6b7280;font-family:monospace;font-size:0.85rem;">obec, municipality</td>
                <td style="padding:0.5rem 0.8rem;"><code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">Praha</code></td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">operator</td>
                <td style="padding:0.5rem 0.8rem;color:#6b7280;font-family:monospace;font-size:0.85rem;">sit, carrier</td>
                <td style="padding:0.5rem 0.8rem;">
                    Mobilní operátor. Pokud znáš, můžeš naplnit (čistička jinak zjistí).<br>
                    Hodnoty: <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">TM</code> (T-Mobile) · <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">O2</code> · <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">VF</code> (Vodafone)
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">poznamka</td>
                <td style="padding:0.5rem 0.8rem;color:#6b7280;font-family:monospace;font-size:0.85rem;">note, poznamky</td>
                <td style="padding:0.5rem 0.8rem;">Volný text. Uvidí ho čistička i navolávačka.</td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">stav</td>
                <td style="padding:0.5rem 0.8rem;color:#6b7280;font-family:monospace;font-size:0.85rem;">status, vysledek, ne_chce</td>
                <td style="padding:0.5rem 0.8rem;">
                    Stav kontaktu. Pokud prázdný → NEW (čerstvý kontakt k pročištění).<br>
                    Viz tabulka stavů níže.
                </td>
            </tr>
        </tbody>
    </table>

    <!-- ── Tabulka stavů ── -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">📊 Tabulka stavů (sloupec „stav")</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr>
                <th style="text-align:left;padding:0.5rem 0.8rem;">Co napíšeš v CSV</th>
                <th style="text-align:left;padding:0.5rem 0.8rem;">Co to udělá</th>
                <th style="text-align:left;padding:0.5rem 0.8rem;">Vyžaduje navíc</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-top:1px solid #f3f4f6;background:#dcfce7;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">(prázdné) <em>nebo</em> NEW</td>
                <td style="padding:0.5rem 0.8rem;">Čerstvý kontakt → půjde čističce</td>
                <td style="padding:0.5rem 0.8rem;color:#9ca3af;">—</td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">NECHCE / NEZÁJEM / nedovolal</td>
                <td style="padding:0.5rem 0.8rem;">Označeno jako odmítnuto, nepoužije se</td>
                <td style="padding:0.5rem 0.8rem;color:#9ca3af;">—</td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;background:#fef3c7;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">CHCE / pro OZ / rozjednany</td>
                <td style="padding:0.5rem 0.8rem;">Kontakt u OZ v práci (rozjednaný)</td>
                <td style="padding:0.5rem 0.8rem;color:#dc2626;font-weight:600;">oz_email (POVINNÉ)</td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">CALLBACK / volat zpet</td>
                <td style="padding:0.5rem 0.8rem;">Domluvený zpětný hovor</td>
                <td style="padding:0.5rem 0.8rem;color:#9ca3af;">datum_volani</td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">OK / obvolano / CALLED_OK</td>
                <td style="padding:0.5rem 0.8rem;">Úspěšně obvoláno bez předání</td>
                <td style="padding:0.5rem 0.8rem;color:#9ca3af;">—</td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">CHYBNY / spatny</td>
                <td style="padding:0.5rem 0.8rem;">Chybný / nepoužitelný kontakt</td>
                <td style="padding:0.5rem 0.8rem;color:#9ca3af;">—</td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;background:#f3e8ff;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">SMLOUVA / UZAVRENO</td>
                <td style="padding:0.5rem 0.8rem;">Uzavřená smlouva (legacy záznam)</td>
                <td style="padding:0.5rem 0.8rem;color:#dc2626;font-weight:600;">oz_email + datum_uzavreni (POVINNÉ)</td>
            </tr>
        </tbody>
    </table>

    <!-- ── Sloupce pro rozjednané / uzavřené ── -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">🤝 Sloupce pro rozjednané kontakty</h2>
    <p style="color:#6b7280;font-size:0.88rem;margin-bottom:0.6rem;">
        Pokud importuješ kontakty kde už OZ pracuje (stav <code>CHCE</code> nebo <code>SMLOUVA</code>):
    </p>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr>
                <th style="text-align:left;padding:0.5rem 0.8rem;width:25%;">Sloupec</th>
                <th style="text-align:left;padding:0.5rem 0.8rem;width:30%;">Alternativní názvy</th>
                <th style="text-align:left;padding:0.5rem 0.8rem;">Popis</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;color:#7e22ce;">oz_email</td>
                <td style="padding:0.5rem 0.8rem;color:#6b7280;font-family:monospace;font-size:0.85rem;">oz, obchodak, obchodak_email, prodejce, prodejce_email, sales, sales_email</td>
                <td style="padding:0.5rem 0.8rem;">
                    Email obchodního zástupce (uživatele v systému).<br>
                    <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">honza@firma.cz</code><br>
                    <strong>Validace:</strong> uživatel musí v systému existovat s rolí obchodak.
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">datum_volani</td>
                <td style="padding:0.5rem 0.8rem;color:#6b7280;font-family:monospace;font-size:0.85rem;">dne, datum_telefonatu, volano_dne, date_called</td>
                <td style="padding:0.5rem 0.8rem;">
                    Datum kdy se naposledy volalo (historický záznam).<br>
                    <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">2026-04-15</code> nebo <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">15.4.2026</code>
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">datum_uzavreni</td>
                <td style="padding:0.5rem 0.8rem;color:#6b7280;font-family:monospace;font-size:0.85rem;">datum_uzavreni</td>
                <td style="padding:0.5rem 0.8rem;">
                    Datum podpisu smlouvy. Vyžadováno u UZAVŘENÝCH smluv.<br>
                    <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">2026-03-15</code>
                </td>
            </tr>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:600;">sale_price</td>
                <td style="padding:0.5rem 0.8rem;color:#6b7280;font-family:monospace;font-size:0.85rem;">cena, cena_smlouvy, price, castka</td>
                <td style="padding:0.5rem 0.8rem;">
                    Hodnota smlouvy v Kč (měsíční nebo celková).<br>
                    <code style="background:#f3f4f6;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.82rem;">2500</code>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- ── Ukázky CSV ── -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">📄 Ukázky CSV souborů</h2>

    <h3 style="margin:0.8rem 0 0.4rem;font-size:1rem;color:#16a34a;">✅ A) Čerstvé nové kontakty (typický import)</h3>
    <pre style="background:#1f2937;color:#e5e7eb;padding:1rem;border-radius:6px;overflow:auto;font-size:0.82rem;line-height:1.5;">firma;ico;telefon;email;kraj;adresa;poznamka
"Jan Novák - elektro";12345678;724111222;jan@novak.cz;stredocesky;"Hlavní 12, Beroun";""
"ABC Stavby s.r.o.";87654321;602111333;info@abc.cz;hlavni-mesto-praha;"Wenceslas 1, Praha";""
"Zedník Karel";11223344;777222111;karel@zednik.cz;jihocesky;"";"Zájem o zedničku 2026"</pre>
    <p style="font-size:0.85rem;color:#6b7280;margin-top:0.3rem;">
        Všechny kontakty se uloží jako <code>stav=NEW</code> a půjdou čističce. Auto-mix je zařadí v 9:1 rytmu.
    </p>

    <h3 style="margin:1rem 0 0.4rem;font-size:1rem;color:#7e22ce;">🤝 B) Rozjednané kontakty u OZ</h3>
    <pre style="background:#1f2937;color:#e5e7eb;padding:1rem;border-radius:6px;overflow:auto;font-size:0.82rem;line-height:1.5;">firma;ico;telefon;email;kraj;stav;oz_email;datum_volani;poznamka
"XYZ s.r.o.";11111111;777222333;k@xyz.cz;jihocesky;CHCE;honza@firma.cz;2026-04-15;"Domluvená schůzka 5/2026"
"DEF a.s.";22222222;777333444;k@def.cz;praha;CHCE;marketa@firma.cz;2026-04-20;"Posílám nabídku, čekám reakci"
"GHI družstvo";33333333;777444555;k@ghi.cz;stredocesky;CALLBACK;honza@firma.cz;2026-04-22;"Volá v pondělí 14:00"</pre>
    <p style="font-size:0.85rem;color:#6b7280;margin-top:0.3rem;">
        Kontakty se přiřadí přímo OZ (honza/marketa), nepujdou přes čističku ani navolávačku.
    </p>

    <h3 style="margin:1rem 0 0.4rem;font-size:1rem;color:#dc2626;">📜 C) Uzavřené smlouvy (legacy import)</h3>
    <pre style="background:#1f2937;color:#e5e7eb;padding:1rem;border-radius:6px;overflow:auto;font-size:0.82rem;line-height:1.5;">firma;ico;telefon;email;kraj;stav;oz_email;datum_uzavreni;sale_price;poznamka
"Klient ABC";44444444;777111;a@abc.cz;praha;SMLOUVA;honza@firma.cz;2026-03-15;3500;"Aktivní zákazník"
"Klient DEF";55555555;777222;a@def.cz;jihocesky;SMLOUVA;marketa@firma.cz;2025-12-10;4200;"Smlouva na 3 roky"</pre>

    <!-- ── Tipy a triky ── -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">💡 Tipy a častá úskalí</h2>
    <ul style="line-height:1.7;margin:0 0 1rem 1.2rem;">
        <li><strong>Encoding</strong>: ulož CSV jako <code>UTF-8</code>. Excel default ANSI rozbije diakritiku.</li>
        <li><strong>Delimiter</strong>: středník <code>;</code> nebo čárka <code>,</code> — oba fungují, systém detekuje sám.</li>
        <li><strong>Hlavička</strong>: musí být na <strong>prvním řádku</strong> (parser hledá v prvních 5 řádcích, ale ideálně první).</li>
        <li><strong>Velká / malá písmena</strong>: nezáleží, systém je case-insensitive.</li>
        <li><strong>Diakritika</strong>: v hlavičce sloupce nezáleží (`IČO` = `ico` = `IC`).</li>
        <li><strong>Duplicity</strong>: dedupe podle ICO → email → telefon. Default akce: aktualizovat.</li>
        <li><strong>DNC list</strong>: kontakty na blacklistu se automaticky přeskočí (vidíš to v preview).</li>
        <li><strong>Auto-mix</strong>: po importu se rovnou zamíchá 9:1 (OSVČ:firma). Vypneš v <code>/admin/contacts/mix → ⚙️ Nastavení</code>.</li>
    </ul>

    <!-- ── Co se stane kdy ── -->
    <h2 style="margin:1.5rem 0 0.6rem;font-size:1.2rem;">🔄 Co se s kontaktem stane po importu</h2>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;font-family:monospace;font-size:0.82rem;line-height:1.7;white-space:pre;overflow:auto;">
[A] NEW kontakt
   → Čistička v <?= htmlspecialchars('/cisticka', ENT_QUOTES) ?> → ověří TM/O2/VF
   → stav = READY (TM/O2) nebo VF_SKIP (Vodafone) nebo CHYBNY_KONTAKT
   → Pokud READY: Navolávačka v <?= htmlspecialchars('/caller', ENT_QUOTES) ?> volá
   → Pokud výhra: stav = CALLED_OK + přiřazení OZ
   → OZ v <?= htmlspecialchars('/oz/queue', ENT_QUOTES) ?> přijme
   → OZ pracuje → schůzka/nabídka/šance
   → Předáno BO → smlouva
   → Podpis potvrzen → stav = UZAVRENO

[B] CHCE / FOR_SALES kontakt (s oz_email)
   → Přímo v OZ workflow (stav = FOR_SALES)
   → OZ v <?= htmlspecialchars('/oz/leads', ENT_QUOTES) ?> ho vidí mezi rozjednanými
   → Stejný flow od OZ dál

[C] SMLOUVA kontakt (s oz_email + datum_uzavreni)
   → Stav = DONE, workflow stav = UZAVRENO
   → podpis_potvrzen = 1
   → OZ ho vidí v historii uzavřených smluv
    </div>

    <div style="margin-top:1.5rem;background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;padding:0.9rem 1.1rem;font-size:0.88rem;color:#92400e;">
        ⚠️ <strong>POZOR:</strong> Před hromadným importem doporučuju <strong>vždy vyzkoušet na 2-3 řádcích</strong>. Preview ti ukáže co systém detekoval a co naopak nepoznal. Teprve potom plný import.
    </div>
</div>
