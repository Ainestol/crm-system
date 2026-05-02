<?php
// e:\Snecinatripu\app\helpers\assignment.php
//
// ⚠ ODSTRANĚNO — viz komentář v AdminContactsAssignmentController.php.
//
// Helpery crm_assignment_contact_unlocked_sql, crm_assignment_pick_caller_round_robin,
// crm_assignment_log, crm_assignment_assert_navolavacka, crm_assignment_navolavacky_ids_for_region,
// crm_assignment_caller_load — všechny byly use-case pouze pro odstraněný
// AdminContactsAssignmentController. Žádný jiný kód v aplikaci je nepoužíval.
//
// POZN: Tabulka `assignment_log` v DB ZŮSTÁVÁ — používá ji CallerController
// (předání kontaktu obchodákovi) a AdminImportController (audit importu).
// Nesmazat z DB schématu.
//
// Soubor lze bezpečně smazat z disku.
declare(strict_types=1);
