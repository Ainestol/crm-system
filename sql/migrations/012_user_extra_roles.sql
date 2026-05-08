-- e:\Snecinatripu\sql\migrations\012_user_extra_roles.sql
-- ════════════════════════════════════════════════════════════════════
-- Multi-role uživatelské účty
--
-- Princip:
--   • `users.role` zůstává PRIMÁRNÍ role (pro login + default po přihlášení)
--   • Nový sloupec `roles_extra` JSON obsahuje POLE dalších rolí
--     - Příklad: user je majitel + obchodak  →  role='majitel', roles_extra=["obchodak"]
--   • Po přihlášení s víc rolemi se zobrazí výběr "Jako kým pracovat?"
--   • Vybraná role se ukládá do session jako 'crm_active_role'
--   • Cookie `crm_preferred_role` zapamatuje volbu pro příště (1 rok)
--
-- Spuštění lokálně:
--   mysql -u root -p crm < E:\Snecinatripu\sql\migrations\012_user_extra_roles.sql
--
-- Spuštění na serveru:
--   sudo mariadb crm < /var/www/crm/sql/migrations/012_user_extra_roles.sql
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

ALTER TABLE `users`
    ADD COLUMN `roles_extra` JSON NULL DEFAULT NULL
        COMMENT 'JSON array dalších rolí (kromě primární role). Příklad: ["obchodak"] = user je primárně majitel a navíc obchodák.'
        AFTER `role`;
