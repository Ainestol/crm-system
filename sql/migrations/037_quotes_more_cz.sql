-- e:\Snecinatripu\sql\migrations\037_quotes_more_cz.sql
-- ════════════════════════════════════════════════════════════════════
-- VÍCE CITÁTŮ — primárně česky, plus známé světové
--
-- Účel: rozšířit pool ze ~25 na 100+ citátů, aby se nepokukoval pořád
-- ten samý. Většina v češtině, sem tam české překlady světových.
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

INSERT IGNORE INTO `quotes` (`text`, `author`) VALUES
    -- Tomáš Baťa
    ('Nehledej v ničem důvod, proč to nejde — hledej cestu, jak to udělat.', 'Tomáš Baťa'),
    ('Není problém vydělat peníze. Problém je vydělat je čestně.', 'Tomáš Baťa'),
    ('Co dělají druzí — to dělejme lépe. Co druzí nedělají — to udělejme.', 'Tomáš Baťa'),
    ('Práce nás nedělá nešťastnými — práce nás dělá lidmi.', 'Tomáš Baťa'),
    ('Příčinou krize je morální bída. Přelom krize? — morální obroda.', 'Tomáš Baťa'),
    ('Naším úkolem není zachraňovat svět, ale postarat se, aby se naši zákazníci měli lépe.', 'Tomáš Baťa'),
    ('Nikdy neříkej: „Nejde to.“ Vždycky říkej: „Zatím jsem nepřišel na to, jak.“', 'Tomáš Baťa'),
    ('Mladý člověk, který se nepokouší o vlastní cestu, je mrtvý.', 'Tomáš Baťa'),
    ('Nemyslete za druhé — ať myslí. Pak budou pracovat.', 'Tomáš Baťa'),
    ('Krize nás nezničí. Naše malověrnost nás zničí.', 'Tomáš Baťa'),

    -- Karel Čapek / Masaryk / další česká inspirace
    ('Nemůžete naučit člověka ničemu — můžete mu jen pomoci, aby to v sobě sám objevil.', 'T. G. Masaryk'),
    ('Demokracie není jen forma vlády. Je to způsob, jak žít s druhými.', 'T. G. Masaryk'),
    ('Ti, kdo si přejí žít v lepším světě, musí žít lépe.', 'T. G. Masaryk'),
    ('Nebojte se chybovat. Bojte se jen, že přestanete zkoušet.', 'Anonymní'),
    ('Život není o tom najít sám sebe — je o tom vytvořit sám sebe.', 'George Bernard Shaw'),

    -- Tomáš Baťa — pokračování
    ('Stroje musí pracovat — lidé musí myslet.', 'Tomáš Baťa'),
    ('Tvořit hodnoty znamená sloužit druhým. To je nejvyšší smysl podnikání.', 'Tomáš Baťa'),

    -- Universal motivace (česky překlad)
    ('Klíč k úspěchu je začít. Klíč k začátku je rozdělit velký úkol na malé kroky.', 'Mark Twain'),
    ('Většina lidí promarní více času a energie mluvením o problémech než jejich řešením.', 'Henry Ford'),
    ('Kdo neumí naslouchat, neumí prodávat.', 'Anonymní'),
    ('Cíl bez plánu je jen přání.', 'Antoine de Saint-Exupéry'),
    ('Začni tam, kde jsi. Použij, co máš. Udělej, co můžeš.', 'Arthur Ashe'),
    ('Nejlepší čas zasadit strom byl před 20 lety. Druhý nejlepší čas je teď.', 'Čínské přísloví'),
    ('Trpělivost je hořká, ale její plody jsou sladké.', 'Jean-Jacques Rousseau'),
    ('Nejtěžší věc na světě je být sám sebou ve světě, který se ti snaží být něčím jiným.', 'E. E. Cummings'),
    ('Změna je jediná konstanta v životě.', 'Hérakleitos'),
    ('Štěstí přeje připraveným.', 'Louis Pasteur'),
    ('Není nic praktického, jako dobrá teorie.', 'Kurt Lewin'),
    ('Lépe rozsvítit svíčku, než proklínat tmu.', 'Konfucius'),

    -- Práce a tým
    ('Jeden den naplněný úspěchem stojí za týdny průměrnosti.', 'Anonymní'),
    ('Nikdo z nás není tak chytrý jako my všichni.', 'Ken Blanchard'),
    ('Tým, který se baví, vyhrává. Tým, který se hádá, prohrává.', 'Anonymní'),
    ('Šéf říká „jdi“, vedoucí říká „pojďme“.', 'E. M. Kelly'),
    ('Pochval nahlas — kritizuj v soukromí.', 'Anonymní'),
    ('Nejlepší investice manažera je čas s lidmi.', 'Anonymní'),
    ('Kultura sní strategii k snídani.', 'Peter Drucker'),
    ('Vedení není o titulech ani moci. Je o jednom životě, který ovlivní druhé.', 'John C. Maxwell'),

    -- Telefonický prodej / navolávačka
    ('Nejlepší prodejce neprodává — pomáhá zákazníkovi vyřešit problém.', 'Anonymní'),
    ('Klient nekupuje výrobek. Kupuje výsledek, který výrobek přinese.', 'Anonymní'),
    ('Ne je jen krok blíž k Ano.', 'Anonymní'),
    ('Když nezvedají telefon, voláš v nesprávný čas. Zkus to zítra.', 'Anonymní'),
    ('Cena se zapomene. Zážitek z koupě zůstane.', 'Anonymní'),
    ('Prodávat znamená naslouchat. Mluvit může každý.', 'Anonymní'),
    ('Sebevědomí v hlasu prodá víc než dokonalé argumenty.', 'Anonymní'),
    ('Otázky prodávají. Tvrzení dokazují.', 'Brian Tracy'),
    ('Lidé kupují od lidí, kterým věří. Důvěra se získává časem.', 'Anonymní'),
    ('Každý hovor je nová šance. Předchozí ne nehraje roli.', 'Anonymní'),

    -- Obchodní zástupce
    ('Schůzka začíná tím, jak se podáš ruce. Končí tím, kdy zákazník zavolá tobě.', 'Anonymní'),
    ('Nejcennější informace získáš v prvních 5 minutách. Pak už jen utvrzuješ.', 'Anonymní'),
    ('Klient nepotřebuje znát všechno. Potřebuje vědět, že tomu rozumíš ty.', 'Anonymní'),
    ('Vyhrávají ti, kdo myslí na zákazníka i o víkendu.', 'Anonymní'),
    ('Smlouva není konec — je to začátek dlouhodobého vztahu.', 'Anonymní'),
    ('Nejhorší prodejní strategie je „budeme čekat, až se zákazník ozve“.', 'Anonymní'),

    -- Krátké pop quotes
    ('Hotovo je lepší než dokonalé.', 'Sheryl Sandberg'),
    ('Pokud nejsi nervózní, neusiluješ o nic významného.', 'Anonymní'),
    ('Risk je jediná cesta, jak se posunout.', 'Anonymní'),
    ('Sníš velké, jdeš v malých krocích.', 'Anonymní'),
    ('Nech mluvit výsledky — bude to nejhlasitější řeč.', 'Anonymní'),
    ('Kdo si stěžuje, ten neroste. Kdo roste, ten nemá čas si stěžovat.', 'Anonymní'),

    -- Klasika světových
    ('Nejdřív se ti smějí, pak tě ignorují, pak proti tobě bojují — a pak vyhraješ.', 'Mahátma Gándhí'),
    ('Buď změnou, kterou chceš vidět ve světě.', 'Mahátma Gándhí'),
    ('Slabý se nikdy nedokáže omluvit. Omlouvat se je síla.', 'Mahátma Gándhí'),
    ('Strach z neúspěchu nás vede k tomu, abychom selhávali předem.', 'Paulo Coelho'),
    ('Pokud opravdu něco chceš, celý vesmír se spikne, abys to získal.', 'Paulo Coelho'),
    ('Cesta tisíce mil začíná jedním krokem.', 'Lao-c'''),
    ('Když znáš sebe i nepřítele, sto bitev — sto vítězství.', 'Sun-c'''),
    ('Spěch je nepřítelem kvality.', 'Lao-c'''),
    ('Nepřišel jsem na svět, abych ho měnil — přišel jsem, abych v něm dělal své věci dobře.', 'Anonymní'),

    -- Pro morálku v těžkých dnech
    ('Po každé bouřce vyjde slunce.', 'České přísloví'),
    ('Trpělivá ovce dojde na pastvu.', 'České přísloví'),
    ('Bez práce nejsou koláče.', 'České přísloví'),
    ('Kdo se moc ptá, hodně se dozví.', 'České přísloví'),
    ('Lepší vrabec v hrsti, než holub na střeše.', 'České přísloví'),
    ('Stokrát opakovaná lež se stane pravdou — ale pravda zůstane pravdou navždy.', 'Anonymní'),
    ('Nikdy není pozdě začít znovu. Vždy je pozdě začít zítra.', 'Anonymní'),
    ('Štěstí je být zdravý — vše ostatní jsou nadstandardy.', 'Anonymní'),
    ('Den bez smíchu je promarněný den.', 'Charlie Chaplin'),

    -- Drobné z byznysu
    ('Cash flow je king. Zisk je názor.', 'Anonymní'),
    ('Reklama dostane zákazníka. Servis ho udrží.', 'Anonymní'),
    ('Konkurence není nepřítel — je to zrcadlo.', 'Anonymní'),
    ('Pokud nemáš strach z konkurence, máš strach z růstu.', 'Anonymní'),
    ('Malá firma má jednu velkou výhodu: rozhoduje rychle.', 'Anonymní'),
    ('Velká vize bez denní práce je jen sen.', 'Anonymní'),
    ('Tvrdá práce neporazí talent — ale tvrdě pracující talent porazí všechno.', 'Tim Notke'),
    ('Lidé si pamatují, jak ses k nim choval, ne co jsi jim řekl.', 'Anonymní');
