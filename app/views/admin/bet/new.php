<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $ozList */
/** @var array<string,string>      $regionChoices */
/** @var string|null                $flash */
/** @var string                     $csrf */
?>
<div style="max-width:760px;margin:0 auto;padding:1rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <h1 style="margin:0;">➕ Nová sázka</h1>
        <a href="<?= crm_url('/admin/bet') ?>" style="color:#6b7280;text-decoration:none;">← Zpět na seznam</a>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-warning" style="background:#fef3c7;border:1px solid #fbbf24;padding:0.5rem 0.8rem;border-radius:6px;">
            <?= crm_h($flash) ?>
        </p>
    <?php } ?>

    <form method="POST" action="<?= crm_url('/admin/bet/create') ?>"
          style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1.4rem;">
        <input type="hidden" name="<?= crm_csrf_field_name() ?>" value="<?= crm_h($csrf) ?>">

        <div style="margin-bottom:1rem;">
            <label style="display:block;font-weight:600;margin-bottom:0.3rem;">Název sázky</label>
            <input type="text" name="name" required placeholder="např. Sázka majitele 5/2026"
                   style="width:100%;padding:0.55rem;border:1px solid #d1d5db;border-radius:6px;font-size:0.95rem;">
        </div>

        <div style="display:flex;gap:1rem;margin-bottom:1rem;">
            <div style="flex:2;">
                <label style="display:block;font-weight:600;margin-bottom:0.3rem;">Kraj</label>
                <select name="region" required
                        style="width:100%;padding:0.55rem;border:1px solid #d1d5db;border-radius:6px;font-size:0.95rem;">
                    <option value="">— vyber kraj —</option>
                    <?php
                    // crm_region_choices() vrací list (numerické indexy → kódy krajů),
                    // takže iterujeme přes hodnoty (kódy). Value selectu = kód ("ustecky"),
                    // text = lidský label ("Ústecký kraj") přes crm_region_label().
                    foreach ($regionChoices as $regionCode) {
                        $regionCode = (string) $regionCode;
                    ?>
                        <option value="<?= crm_h($regionCode) ?>"><?= crm_h(crm_region_label($regionCode)) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div style="flex:1;">
                <label style="display:block;font-weight:600;margin-bottom:0.3rem;">Cíl (počet TM+O2)</label>
                <input type="number" name="target_count" required min="1" value="300"
                       style="width:100%;padding:0.55rem;border:1px solid #d1d5db;border-radius:6px;font-size:0.95rem;">
            </div>
        </div>

        <div style="margin-bottom:1rem;">
            <label style="display:block;font-weight:600;margin-bottom:0.3rem;">Poznámka (volitelně)</label>
            <textarea name="note" rows="2" placeholder="např. Pro 5/2026 — 100 pro Honzu (call), 200 pro Markétu (email)"
                      style="width:100%;padding:0.55rem;border:1px solid #d1d5db;border-radius:6px;font-size:0.95rem;"></textarea>
        </div>

        <hr style="margin:1.5rem 0;border:none;border-top:1px solid #e5e7eb;">

        <h3 style="margin-bottom:0.6rem;">Příjemci</h3>
        <p style="font-size:0.85rem;color:#6b7280;margin-bottom:0.8rem;">
            Součet target_count u příjemců musí odpovídat cíli sázky. Pořadí (1, 2, ...) určuje chronologii:
            prvních N leadů jde příjemci #1, dalších M příjemci #2, atd.
        </p>

        <div id="recipients-container" style="display:flex;flex-direction:column;gap:0.6rem;">
            <!-- Recipient #1 -->
            <div class="recipient-row" data-idx="0"
                 style="display:flex;gap:0.5rem;align-items:end;background:#f9fafb;padding:0.6rem;border-radius:6px;">
                <div style="width:28px;text-align:center;font-weight:700;color:#6b7280;">1.</div>
                <div style="flex:2;">
                    <label style="display:block;font-size:0.78rem;color:#6b7280;">OZ</label>
                    <select name="recipients[0][oz_id]" required
                            style="width:100%;padding:0.45rem;border:1px solid #d1d5db;border-radius:5px;">
                        <option value="">— vyber OZ —</option>
                        <?php foreach ($ozList as $oz) { ?>
                            <option value="<?= (int) $oz['id'] ?>"><?= crm_h((string) $oz['jmeno']) ?> (<?= crm_h((string) $oz['email']) ?>)</option>
                        <?php } ?>
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="display:block;font-size:0.78rem;color:#6b7280;">Počet</label>
                    <input type="number" name="recipients[0][target]" required min="1" value="100"
                           style="width:100%;padding:0.45rem;border:1px solid #d1d5db;border-radius:5px;">
                </div>
                <div style="flex:1;">
                    <label style="display:block;font-size:0.78rem;color:#6b7280;">Typ</label>
                    <select name="recipients[0][delivery]"
                            style="width:100%;padding:0.45rem;border:1px solid #d1d5db;border-radius:5px;">
                        <option value="call">📞 Call (navolávačka volá)</option>
                        <option value="email">📧 Email (přeskočí pool)</option>
                    </select>
                </div>
            </div>

            <!-- Recipient #2 -->
            <div class="recipient-row" data-idx="1"
                 style="display:flex;gap:0.5rem;align-items:end;background:#f9fafb;padding:0.6rem;border-radius:6px;">
                <div style="width:28px;text-align:center;font-weight:700;color:#6b7280;">2.</div>
                <div style="flex:2;">
                    <label style="display:block;font-size:0.78rem;color:#6b7280;">OZ</label>
                    <select name="recipients[1][oz_id]"
                            style="width:100%;padding:0.45rem;border:1px solid #d1d5db;border-radius:5px;">
                        <option value="">— vyber OZ —</option>
                        <?php foreach ($ozList as $oz) { ?>
                            <option value="<?= (int) $oz['id'] ?>"><?= crm_h((string) $oz['jmeno']) ?> (<?= crm_h((string) $oz['email']) ?>)</option>
                        <?php } ?>
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="display:block;font-size:0.78rem;color:#6b7280;">Počet</label>
                    <input type="number" name="recipients[1][target]" min="0" value="200"
                           style="width:100%;padding:0.45rem;border:1px solid #d1d5db;border-radius:5px;">
                </div>
                <div style="flex:1;">
                    <label style="display:block;font-size:0.78rem;color:#6b7280;">Typ</label>
                    <select name="recipients[1][delivery]"
                            style="width:100%;padding:0.45rem;border:1px solid #d1d5db;border-radius:5px;">
                        <option value="call">📞 Call (navolávačka volá)</option>
                        <option value="email" selected>📧 Email (přeskočí pool)</option>
                    </select>
                </div>
            </div>
        </div>

        <button type="button" onclick="addRecipient()"
                style="margin-top:0.6rem;background:#e5e7eb;color:#374151;border:none;padding:0.45rem 0.85rem;
                       border-radius:5px;cursor:pointer;font-size:0.85rem;">
            ➕ Přidat dalšího příjemce
        </button>

        <hr style="margin:1.5rem 0;border:none;border-top:1px solid #e5e7eb;">

        <button type="submit"
                style="background:#16a34a;color:#fff;border:none;padding:0.65rem 1.4rem;
                       border-radius:6px;cursor:pointer;font-size:0.95rem;font-weight:600;">
            ✅ Vytvořit sázku
        </button>
    </form>
</div>

<script>
function addRecipient() {
    var container = document.getElementById('recipients-container');
    var idx = container.querySelectorAll('.recipient-row').length;
    var div = document.createElement('div');
    div.className = 'recipient-row';
    div.dataset.idx = idx;
    div.style.cssText = 'display:flex;gap:0.5rem;align-items:end;background:#f9fafb;padding:0.6rem;border-radius:6px;';
    var ozOptions = <?= json_encode(array_map(fn($oz) => [
        'id'    => (int) $oz['id'],
        'jmeno' => (string) $oz['jmeno'],
        'email' => (string) $oz['email'],
    ], $ozList), JSON_THROW_ON_ERROR) ?>;
    var ozHtml = '<option value="">— vyber OZ —</option>';
    ozOptions.forEach(function(o) {
        ozHtml += '<option value="' + o.id + '">' + o.jmeno + ' (' + o.email + ')</option>';
    });
    div.innerHTML =
        '<div style="width:28px;text-align:center;font-weight:700;color:#6b7280;">' + (idx + 1) + '.</div>' +
        '<div style="flex:2;"><label style="display:block;font-size:0.78rem;color:#6b7280;">OZ</label>' +
        '<select name="recipients[' + idx + '][oz_id]" style="width:100%;padding:0.45rem;border:1px solid #d1d5db;border-radius:5px;">' + ozHtml + '</select></div>' +
        '<div style="flex:1;"><label style="display:block;font-size:0.78rem;color:#6b7280;">Počet</label>' +
        '<input type="number" name="recipients[' + idx + '][target]" min="0" value="0" style="width:100%;padding:0.45rem;border:1px solid #d1d5db;border-radius:5px;"></div>' +
        '<div style="flex:1;"><label style="display:block;font-size:0.78rem;color:#6b7280;">Typ</label>' +
        '<select name="recipients[' + idx + '][delivery]" style="width:100%;padding:0.45rem;border:1px solid #d1d5db;border-radius:5px;">' +
        '<option value="call">📞 Call</option><option value="email">📧 Email</option></select></div>';
    container.appendChild(div);
}
</script>
