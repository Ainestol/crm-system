-- Migration 005: role čistička + status READY/VF_SKIP
-- Spustit: mysql -u root crm < E:\Snecinatripu\sql\migrations\005_cisticka_role.sql

ALTER TABLE `users`
  MODIFY COLUMN `role`
    ENUM('superadmin','majitel','navolavacka','obchodak','backoffice','cisticka') NOT NULL;

-- Index pro rychlé hledání READY kontaktů podle regionu a operátora
ALTER TABLE `contacts`
  ADD KEY `idx_contacts_ready_operator` (`stav`, `operator`, `region`);
