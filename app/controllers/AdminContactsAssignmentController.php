<?php
// e:\Snecinatripu\app\controllers\AdminContactsAssignmentController.php
//
// ┌──────────────────────────────────────────────────────────────────┐
// │  ⚠ TENTO CONTROLLER BYL ODSTRANĚN                                │
// │                                                                  │
// │  Bývalá funkcionalita /admin/contacts/assignment* (auto-distribute,│
// │  bulk reassign, cherry-pick) byla zrušena, protože KOLIDOVALA s  │
// │  novým čističkovým workflow:                                     │
// │     NEW  →  cisticka ověří  →  READY (TM/O2)  →  shared pool    │
// │     pro navolávačky (round-robin per region s lockingem).        │
// │                                                                  │
// │  Hard-assign (NEW → ASSIGNED přeskakující čističku) byl historický│
// │  přežitek z pre-cisticka éry a vedl by k obejití povinného       │
// │  ověření operátora (TM/O2/VF).                                   │
// │                                                                  │
// │  Pro emergency edge cases (odchod navolávačky → přesun callback  │
// │  ≤30 dní) lze přesun udělat přímo v DB:                          │
// │     UPDATE contacts                                              │
// │     SET assigned_caller_id = :new_caller                         │
// │     WHERE assigned_caller_id = :old_caller                       │
// │       AND stav IN ('CALLBACK','ASSIGNED');                       │
// │                                                                  │
// │  Soubor lze bezpečně smazat z disku.                             │
// └──────────────────────────────────────────────────────────────────┘
declare(strict_types=1);
