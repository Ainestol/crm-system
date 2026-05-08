<?php
// e:\Snecinatripu\app\views\oz\premium\new.php
declare(strict_types=1);
/** @var array<string,mixed>             $user */
/** @var string                          $csrf */
/** @var ?string                         $flash */
/** @var list<array<string,mixed>>       $callers */
/** @var list<string>                    $regions */
/** @var int                             $availableTotal */
/** @var array<string,int>               $availPerRegion */
/** @var array<string,mixed>             $form */
?>

<style>
.po-new { max-width: 760px; margin: 0 auto; }
.po-new h1 { margin: 0 0 0.4rem; font-size: 1.4rem; }
.po-new .lead {
    color: var(--color-text-muted);
    font-size: 0.85rem;
    margin-bottom: 1.2rem;
    line-height: 1.5;
}
.po-form { display: grid; gap: 0.85rem; }
.po-form__row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.85rem;
}
@media (max-width: 600px) {
    .po-form__row { grid-template-columns: 1fr; }
}
.po-form label {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--color-text);
}
.po-form label .req { color: #e74c3c; margin-left: 2px; }
.po-form label .hint {
    font-size: 0.7rem;
    font-weight: 400;
    color: var(--color-text-muted);
    margin-top: 0.15rem;
}
.po-form input[type="number"],
.po-form input[type="text"],
.po-form select,
.po-form textarea {
    background: #fff;
    color: var(--color-text);
    border: 1px solid var(--color-border-strong);
    border-radius: 5px;
    padding: 0.45rem 0.6rem;
    font-size: 0.9rem;
    font-family: var(--font-main);
}
.po-form textarea { min-height: 70px; resize: vertical; }

.po-info-box {
    background: #f5f0fc;
    border: 1px solid #d8c5fa;
    border-left: 4px solid #7e3ff2;
    border-radius: 0 6px 6px 0;
    padding: 0.65rem 0.85rem;
    font-size: 0.8rem;
    color: #4a2480;
    margin-bottom: 1rem;
    line-height: 1.5;
}
.po-info-box strong { color: #2d1554; }

.po-avail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 0.4rem;
    margin-top: 0.5rem;
}
.po-avail-tile {
    border: 1px solid var(--color-border);
    border-radius: 4px;
    padding: 0.35rem 0.55rem;
    background: #fafafa;
    font-size: 0.78rem;
    display: flex;
    justify-content: space-between;
    gap: 0.3rem;
}
.po-avail-tile strong { color: #7e3ff2; }

.po-region-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.3rem;
    border: 1px solid var(--color-border);
    border-radius: 5px;
    padding: 0.5rem;
    background: #fff;
    max-height: 220px;
    overflow-y: auto;
}
.po-region-grid label {
    flex-direction: row;
    align-items: center;
    gap: 0.4rem;
    font-weight: 400;
    font-size: 0.8rem;
    cursor: pointer;
}

.po-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
    flex-wrap: wrap;
}
.po-btn-primary {
    background: linear-gradient(135deg,#7e3ff2 0%,#a056ff 100%);
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 0.6rem 1.4rem;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(126,63,242,0.3);
}
.po-btn-primary:hover { filter: brightness(1.07); }
.po-btn-secondary {
    background: #fff;
    color: var(--color-text);
    border: 1px solid var(--color-border-strong);
    border-radius: 5px;
    padding: 0.55rem 1.1rem;
    font-size: 0.88rem;
    cursor: pointer;
    text-decoration: none;
}
</style>

<section class="card po-new">
    <h1>💎 Nová premium objednávka</h1>
    <p class="lead">
        Objednej druhé čištění už jednou pročištěných leadů. Čistička je projde
        ještě jednou, označí <strong>obchodovatelné</strong> nebo <strong>neobchodovatelné</strong>,
        a obchodovatelné půjdou jen <strong>tobě</strong> přes určenou navolávačku.
    </p>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></p>
    <?php } ?>

    <div class="po-info-box">
        💡 <strong>Aktuálně dostupných leadů</strong> ve standardním poolu:
        <strong style="font-size:1rem;"><?= (int) $availableTotal ?></strong>
        (READY, ještě bez navolávačky a mimo jakoukoliv premium objednávku).
        Pokud objednáš víc než je teď k dispozici, zarezervuje se kolik jde
        a zbytek se postupně doplní, jak budou nové leady k dispozici.

        <?php if ($availPerRegion !== []) { ?>
            <div class="po-avail-grid">
                <?php foreach ($availPerRegion as $reg => $cnt) { ?>
                    <div class="po-avail-tile">
                        <span><?= crm_h(crm_region_label((string) $reg)) ?></span>
                        <strong><?= (int) $cnt ?></strong>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <form method="post" action="<?= crm_h(crm_url('/oz/premium/create')) ?>" class="po-form" autocomplete="off">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">

        <!-- Počet leadů + cena -->
        <div class="po-form__row">
            <label>
                Počet leadů <span class="req">*</span>
                <input type="number" name="requested_count" min="1" max="10000" required
                       value="<?= crm_h((string)($form['requested_count'] ?? '')) ?>"
                       placeholder="50">
                <span class="hint">Kolik chceš leadů projetých druhým čištěním.</span>
            </label>
            <label>
                Cena za 1 lead pro čističku (Kč) <span class="req">*</span>
                <input type="text" name="price_per_lead" required
                       pattern="[0-9]+([.,][0-9]{1,2})?"
                       value="<?= crm_h((string)($form['price_per_lead'] ?? '')) ?>"
                       placeholder="2.00">
                <span class="hint">Kolik platíš čističce za jeden vyčištěný lead.</span>
            </label>
        </div>

        <!-- Bonus pro navolávačku + preferred caller -->
        <div class="po-form__row">
            <label>
                Bonus navolávačce za úspěšný hovor (Kč)
                <input type="text" name="caller_bonus_per_lead"
                       pattern="[0-9]+([.,][0-9]{1,2})?"
                       value="<?= crm_h((string)($form['caller_bonus_per_lead'] ?? '0')) ?>"
                       placeholder="0">
                <span class="hint">
                    Volitelné. <strong>0 = bez bonusu</strong> — navolávačka
                    pak žádný extra Kč nevidí. Když dáš 50, uvidí
                    štítek „💎 +50 Kč" u každého premium leadu.
                </span>
            </label>
            <label>
                Konkrétní navolávačka
                <select name="preferred_caller_id">
                    <option value="0">— rotace mezi všemi —</option>
                    <?php
                    $sel = (int)($form['preferred_caller_id'] ?? 0);
                    foreach ($callers as $c) {
                        $cid = (int)($c['id'] ?? 0);
                        $s   = $cid === $sel ? 'selected' : '';
                        echo '<option value="' . $cid . '" ' . $s . '>'
                           . crm_h((string)($c['jmeno'] ?? '')) . '</option>';
                    }
                    ?>
                </select>
                <span class="hint">Když nevybereš, premium leady půjdou všem navolávačkám.</span>
            </label>
        </div>

        <!-- Filtr krajů -->
        <label>
            Kraje (volitelné — když nezaškrtneš nic, vybírá se ze všech krajů)
            <div class="po-region-grid">
                <?php
                $selRegs = is_array($form['regions'] ?? null) ? $form['regions'] : [];
                foreach ($regions as $rc) {
                    $checked = in_array($rc, $selRegs, true) ? 'checked' : '';
                    $avail   = $availPerRegion[$rc] ?? 0;
                    echo '<label>
                            <input type="checkbox" name="regions[]" value="' . crm_h($rc) . '" ' . $checked . '>
                            <span>' . crm_h(crm_region_label($rc)) . '</span>
                            <span style="color:var(--color-text-muted); font-size:0.7rem; margin-left:auto;">' . (int) $avail . '</span>
                          </label>';
                }
                ?>
            </div>
        </label>

        <!-- Poznámka -->
        <label>
            Poznámka (volitelné)
            <textarea name="note" maxlength="1000"
                      placeholder="např. „rychlovka pro výběrko v Praze, do týdne"…"><?= crm_h((string)($form['note'] ?? '')) ?></textarea>
        </label>

        <div class="po-actions">
            <button type="submit" class="po-btn-primary">
                💎 Objednat a zarezervovat leady
            </button>
            <a href="<?= crm_h(crm_url('/oz/premium')) ?>" class="po-btn-secondary">Zpět</a>
        </div>
    </form>
</section>
