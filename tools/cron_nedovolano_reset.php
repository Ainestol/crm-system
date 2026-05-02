<?php
// e:\Snecinatripu\tools\cron_nedovolano_reset.php
// Spouštět cron každý den krátce po půlnoci, např.:
//   0 0 * * * php /var/www/crm/tools/cron_nedovolano_reset.php >> /var/log/crm_cron.log 2>&1
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'constants.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';

$pdo = crm_pdo();

// Přesun NEDOVOLANO → ASSIGNED pro kontakty, jejichž nedovolání bylo PŘED dnešním dnem.
// Příslušné assigned_caller_id zůstává — kontakt zůstane u stejné navolávačky.
$stmt = $pdo->prepare(
    "UPDATE contacts
     SET stav = 'ASSIGNED', updated_at = NOW(3)
     WHERE stav = 'NEDOVOLANO'
       AND assigned_caller_id IS NOT NULL
       AND DATE(updated_at) < CURDATE()"
);
$stmt->execute();
$count = $stmt->rowCount();

echo date('Y-m-d H:i:s') . " – Cron nedovolano reset: přesunuto {$count} kontaktů NEDOVOLANO → ASSIGNED\n";
