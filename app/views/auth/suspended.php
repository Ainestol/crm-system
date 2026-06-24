<?php
/** @var array{title:string,body:string} $msg */
/** @var ?array $tenant */
/** @var ?array $lifecycle */
/** @var ?array $user */
?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($msg['title'], ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            color: #1f2937;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,.15);
            padding: 2.5rem 2rem;
            max-width: 540px;
            width: calc(100% - 2rem);
            text-align: center;
        }
        .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        h1 {
            margin: 0 0 1rem;
            font-size: 1.6rem;
            color: #92400e;
        }
        p {
            margin: 0 0 1.2rem;
            line-height: 1.6;
            color: #374151;
        }
        .meta {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: .8rem 1rem;
            font-size: .9rem;
            text-align: left;
            margin-bottom: 1.5rem;
        }
        .meta strong { color: #111827; }
        .contact-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 1rem 1.2rem;
            margin-bottom: 1.5rem;
            color: #1e3a8a;
        }
        .contact-box a {
            color: #1d4ed8;
            font-weight: 600;
            text-decoration: none;
        }
        .actions {
            display: flex;
            gap: .6rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: .65rem 1.25rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: .95rem;
            border: 0;
            cursor: pointer;
        }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #e5e7eb; color: #1f2937; }
        .btn-secondary:hover { background: #d1d5db; }
        .footer {
            margin-top: 2rem;
            color: #9ca3af;
            font-size: .8rem;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon"><?= htmlspecialchars(mb_substr($msg['title'], 0, 2), ENT_QUOTES, 'UTF-8') ?></div>
        <h1><?= htmlspecialchars(mb_substr($msg['title'], 3), ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($msg['body'], ENT_QUOTES, 'UTF-8') ?></p>

        <?php if ($tenant !== null): ?>
        <div class="meta">
            <strong>Firma:</strong>
            <?= htmlspecialchars((string) $tenant['name'], ENT_QUOTES, 'UTF-8') ?>
            <?php if (!empty($tenant['email_owner'])): ?>
                <br><strong>Kontakt:</strong>
                <?= htmlspecialchars((string) $tenant['email_owner'], ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
            <?php if (!empty($tenant['paid_until'])): ?>
                <br><strong>Předplatné platilo do:</strong>
                <?= htmlspecialchars(date('d.m.Y', strtotime((string) $tenant['paid_until'])), ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
            <?php if (!empty($tenant['trial_ends_at'])): ?>
                <br><strong>Trial skončil:</strong>
                <?= htmlspecialchars(date('d.m.Y', strtotime((string) $tenant['trial_ends_at'])), ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="contact-box">
            <strong>📧 Kontakt na podporu:</strong><br>
            <a href="mailto:support@snecinatripu.eu">support@snecinatripu.eu</a><br>
            <span style="font-size:.85rem;">Odpovíme vám obvykle do 24 hodin.</span>
        </div>

        <div class="actions">
            <a href="mailto:support@snecinatripu.eu?subject=Reaktivace%20%C3%BA%C4%8Dtu" class="btn btn-primary">
                ✉ Napsat podpoře
            </a>
            <a href="/logout" class="btn btn-secondary">↩ Odhlásit se</a>
        </div>

        <div class="footer">
            <?php if (!empty($user['email'])): ?>
                Přihlášen jako: <?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
