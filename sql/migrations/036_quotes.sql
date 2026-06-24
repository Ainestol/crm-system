-- e:\Snecinatripu\sql\migrations\036_quotes.sql
-- ════════════════════════════════════════════════════════════════════
-- QUOTES — globální pool motivačních citátů
--
-- Účel:
--   Lidský prvek na dashboardu. Náhodný citát se zobrazí v horní liště.
--
-- Scope:
--   GLOBÁLNÍ (sdílený pro všechny tenanty). Žádné tenant_id.
--   Super-admin spravuje pro všechny.
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `quotes` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `text`       TEXT NOT NULL,
    `author`     VARCHAR(120) NULL DEFAULT NULL,
    `active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`),
    KEY `idx_quotes_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `quotes` (`text`, `author`) VALUES
    ('Úspěch je 1 % inspirace a 99 % potu.', 'Thomas A. Edison'),
    ('Kdo nehledá, nenachází; kdo hledá, najde.', 'Tomáš Baťa'),
    ('Náš zákazník — náš pán.', 'Tomáš Baťa'),
    ('Mysli velkoryse a jednej rychle.', 'Tomáš Baťa'),
    ('Boty nejsou pro nohy, ale pro chůzi. Práce není pro plat, ale pro výsledek.', 'Tomáš Baťa'),
    ('Nejlepší způsob, jak předvídat budoucnost, je vytvořit ji.', 'Peter Drucker'),
    ('Co se neměří, to se neřídí.', 'Peter Drucker'),
    ('Inovace odlišují lídra od následovníka.', 'Steve Jobs'),
    ('Stay hungry, stay foolish.', 'Steve Jobs'),
    ('Kvalita znamená dělat věci správně i tehdy, když se nikdo nedívá.', 'Henry Ford'),
    ('Spojit se je začátek. Zůstat spolu je pokrok. Pracovat společně je úspěch.', 'Henry Ford'),
    ('Selhání je příležitost začít znovu — chytřeji.', 'Henry Ford'),
    ('Nejtěžší věc je rozhodnout se jednat. Zbytek je jen vytrvalost.', 'Amelia Earhartová'),
    ('Disciplína je most mezi cíli a úspěchem.', 'Jim Rohn'),
    ('Jednoduchost je vrcholem dokonalosti.', 'Leonardo da Vinci'),
    ('Není problém v tom upadnout. Problém je nevstát.', 'Vince Lombardi'),
    ('Pokud chceš, aby tvůj život byl jednoduchý, dělej věci pořádně už napoprvé.', 'Anonymní'),
    ('Klient si nepamatuje, co jsi mu řekl. Pamatuje si, jak ses k němu choval.', 'Maya Angelou'),
    ('Dnešní úsilí je zítřejší výsledek.', 'Anonymní'),
    ('Velké věci nikdy nevznikly v komfortní zóně.', 'Anonymní'),
    ('Nejlepší investice je do sebe sama.', 'Warren Buffett'),
    ('Cena je to, co platíš. Hodnota je to, co dostaneš.', 'Warren Buffett'),
    ('Pokud nepracuješ na svých snech, někdo si tě najme, abys pracoval na jeho.', 'Tony Gaskins'),
    ('Akce dnes je lepší než dokonalý plán zítra.', 'George S. Patton'),
    ('Nejlepší prodejce je ten, kdo dokáže klientovi pomoct, ne ten, kdo umí mluvit.', 'Anonymní');
