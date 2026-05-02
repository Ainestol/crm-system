-- sql/migrations/006_caller_performance_index.sql
-- Kompozitní index pro výkonnostní dotazy navolávačky
-- Optimalizuje filtrování workflow_log dle uživatele, stavu a data

ALTER TABLE `workflow_log`
    ADD KEY `idx_workflow_user_status_created` (`user_id`, `new_status`, `created_at`);
