<?php
// Sdílené CSS pro role-based dashboardy (čistička/navolávačka/oz/bo)
?>
<style>
.rd-wrap { padding: 1rem 1.4rem; max-width: 1100px; }
.rd-primary-card {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff; border-radius: 16px;
    padding: 1.5rem 2rem;
    box-shadow: 0 8px 24px rgba(37, 99, 235, .25);
    margin-bottom: 1.4rem;
    display: flex; align-items: center; justify-content: space-between;
    gap: 1rem; flex-wrap: wrap;
}
.rd-primary-left { flex: 1; min-width: 240px; }
.rd-primary-label {
    font-size: .82rem; opacity: .85; letter-spacing: .08em;
    text-transform: uppercase; margin-bottom: .4rem;
}
.rd-primary-headline { font-size: 1.4rem; font-weight: 700; margin-bottom: .3rem; }
.rd-primary-sub { opacity: .9; font-size: .95rem; }
.rd-primary-btn {
    background: #fff; color: #1d4ed8;
    padding: .85rem 1.6rem; border-radius: 10px;
    font-weight: 700; font-size: 1.05rem; text-decoration: none;
    box-shadow: 0 4px 12px rgba(0,0,0,.15);
    transition: transform 120ms, box-shadow 120ms;
    display: inline-flex; align-items: center; gap: .5rem;
}
.rd-primary-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,0,0,.2); }

.rd-stats {
    display: grid; gap: 12px; grid-template-columns: 1fr;
    margin-bottom: 1.4rem;
}
@media (min-width: 700px) { .rd-stats { grid-template-columns: repeat(3, 1fr); } }
.rd-stat {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    padding: 1.1rem 1.2rem; box-shadow: 0 1px 2px rgba(0,0,0,.04);
}
.rd-stat-label { color: #6b7280; font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; }
.rd-stat-value { font-size: 2rem; font-weight: 700; color: #111827; margin: .25rem 0 .15rem; line-height: 1; }
.rd-stat-sub { color: #9ca3af; font-size: .82rem; }

.rd-quick-actions {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    padding: 1rem 1.25rem;
}
.rd-quick-actions h3 { margin: 0 0 .7rem; font-size: 1rem; color: #111827; }
.rd-quick-list { display: flex; gap: .5rem; flex-wrap: wrap; }
.rd-quick-btn {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .55rem .9rem; border-radius: 8px;
    background: #f3f4f6; color: #374151;
    text-decoration: none; font-size: .88rem; font-weight: 500;
    transition: background 120ms;
}
.rd-quick-btn:hover { background: #e5e7eb; color: #111827; }

.rd-tip {
    background: #fffbeb; border: 1px solid #fcd34d; color: #92400e;
    padding: .9rem 1.1rem; border-radius: 10px; font-size: .9rem;
    margin-bottom: 1rem;
}
.rd-tip strong { color: #78350f; }
</style>
