<?php
// Variables: $etab (array), $errors (array), $flash (string|null), $flashError (string|null)
$etab      = $etab ?? [];
$errors    = $errors ?? [];
$flash     = $flash ?? null;
$flashError = $flashError ?? null;
$id = (int)($etab['id'] ?? 0);
?>

<?php if ($flash): ?>
<div class="flash-banner flash-success" role="alert">
    <?= e($flash) ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;float:right;font-size:18px;color:inherit;">&times;</button>
</div>
<?php endif; ?>
<?php if ($flashError): ?>
<div class="flash-banner flash-error" role="alert">
    <?= e($flashError) ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;float:right;font-size:18px;color:inherit;">&times;</button>
</div>
<?php endif; ?>

<style>
.flash-banner { border-radius:12px; padding:14px 20px; margin-bottom:20px; font-size:14px; font-weight:500; }
.flash-success { background:var(--green-bg); color:#15803d; border:1px solid #bbf7d0; }
.flash-error   { background:var(--red-bg);   color:#b91c1c; border:1px solid #fca5a5; }
.pay-form-card { background:#fff; border-radius:16px; padding:32px; box-shadow:var(--shadow-sm); border:1px solid var(--border); max-width:560px; }
.pay-form-card label { display:block; font-size:13px; font-weight:600; color:var(--txt); margin-bottom:6px; }
.pay-form-card input, .pay-form-card select {
    width:100%; padding:10px 14px; border:1.5px solid var(--border);
    border-radius:10px; font-size:14px; color:var(--txt); background:#fff;
    transition:.15s;
}
.pay-form-card input:focus, .pay-form-card select:focus {
    outline:none; border-color:var(--ap); box-shadow:0 0 0 3px var(--ap-lt);
}
.field-error { color:var(--red); font-size:12px; margin-top:4px; }
.activer-row { display:flex; align-items:center; gap:12px; background:var(--ap-lt); border-radius:10px; padding:14px 16px; margin-top:20px; }
.activer-row input[type=checkbox] { width:18px; height:18px; accent-color:var(--ap); }
.activer-row label { margin:0; font-size:13px; font-weight:600; color:var(--ap); cursor:pointer; }
.activer-note { font-size:12px; color:var(--txt-l); }
.current-compte { background:var(--bg-2,#F9F9FB); border-radius:12px; padding:16px; margin-bottom:24px; border:1px solid var(--border); }
.current-compte h3 { font-size:13px; font-weight:600; color:var(--txt-l); margin:0 0 10px; }
.current-compte dl { display:grid; grid-template-columns:auto 1fr; gap:6px 16px; margin:0; }
.current-compte dt { font-size:12px; color:var(--txt-l); }
.current-compte dd { font-size:13px; font-weight:600; color:var(--txt); margin:0; }
.badge-actif    { background:#dcfce7; color:#15803d; }
.badge-attente  { background:#fef9c3; color:#a16207; }
.badge-suspendu { background:#fee2e2; color:#b91c1c; }
</style>

<div class="admin-page-header-row">
    <div>
        <h1>Compte de reversement</h1>
        <p><?= e($etab['nom'] ?? '') ?></p>
    </div>
    <a href="<?= url('/admin/orientation') ?>" class="btn-admin-secondary">← Retour</a>
</div>

<div class="pay-form-card">
    <?php if ($etab['paiement_statut'] ?? false): ?>
    <div class="current-compte">
        <h3>Compte actuel</h3>
        <dl>
            <dt>Mode</dt>
            <dd><?= e($etab['withdraw_mode'] ?? '—') ?></dd>
            <dt>Numéro</dt>
            <dd><?= e($etab['numero_mobile_money'] ?? '—') ?></dd>
            <dt>Titulaire</dt>
            <dd><?= e($etab['titulaire'] ?? '—') ?></dd>
            <dt>Statut</dt>
            <dd>
                <?php
                $ps = $etab['paiement_statut'] ?? '';
                $pc = $ps === 'actif' ? 'badge-actif' : ($ps === 'en_attente' ? 'badge-attente' : 'badge-suspendu');
                ?>
                <span class="badge <?= $pc ?>"><?= e($ps) ?></span>
            </dd>
        </dl>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('/admin/orientation/' . $id . '/paiement') ?>" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">

        <div style="margin-bottom:18px;">
            <label for="withdraw_mode">Mode Mobile Money</label>
            <select id="withdraw_mode" name="withdraw_mode" required>
                <option value="">— Choisir —</option>
                <option value="airtel-ga"     <?= ($etab['withdraw_mode'] ?? '') === 'airtel-ga'     ? 'selected' : '' ?>>Airtel Money</option>
                <option value="moov-ga"       <?= ($etab['withdraw_mode'] ?? '') === 'moov-ga'       ? 'selected' : '' ?>>Moov Money</option>
            </select>
            <?php if (!empty($errors['withdraw_mode'])): ?>
            <p class="field-error"><?= e($errors['withdraw_mode']) ?></p>
            <?php endif; ?>
        </div>

        <div style="margin-bottom:18px;">
            <label for="numero_mobile_money">Numéro Mobile Money</label>
            <input type="tel" id="numero_mobile_money" name="numero_mobile_money"
                   value="<?= e($etab['numero_mobile_money'] ?? '') ?>"
                   placeholder="+24174000000" required>
            <?php if (!empty($errors['numero_mobile_money'])): ?>
            <p class="field-error"><?= e($errors['numero_mobile_money']) ?></p>
            <?php endif; ?>
        </div>

        <?php if (!empty($etab['numero_mobile_money'])): ?>
        <div style="margin-bottom:18px;" id="champ-confirmation" style="display:none;">
            <label for="numero_confirmation">
                Confirmer le nouveau numéro
                <span style="font-weight:400;color:var(--txt-l);">(obligatoire si vous modifiez le numéro)</span>
            </label>
            <input type="tel" id="numero_confirmation" name="numero_confirmation"
                   placeholder="Retapez le numéro exact" autocomplete="off">
            <p class="activer-note" style="margin-top:4px;color:var(--red,#b91c1c);">
                Toute modification du numéro de reversement est journalisée et irréversible sans confirmation du super-admin.
            </p>
        </div>
        <script>
        (function () {
            var original = <?= json_encode($etab['numero_mobile_money'] ?? '') ?>;
            var inputNum = document.getElementById('numero_mobile_money');
            var divConf  = document.getElementById('champ-confirmation');
            if (!inputNum || !divConf) return;
            function toggle() {
                divConf.style.display = (inputNum.value.trim() !== original) ? 'block' : 'none';
            }
            inputNum.addEventListener('input', toggle);
            toggle();
        })();
        </script>
        <?php endif; ?>

        <div style="margin-bottom:18px;">
            <label for="titulaire">Titulaire du compte (optionnel)</label>
            <input type="text" id="titulaire" name="titulaire"
                   value="<?= e($etab['titulaire'] ?? '') ?>"
                   placeholder="Nom tel qu'enregistré sur le compte">
        </div>

        <div style="margin-bottom:18px;">
            <label for="taux_commission">Commission plateforme (%)</label>
            <input type="number" id="taux_commission" name="taux_commission"
                   value="<?= e((string)($etab['taux_commission'] ?? 5)) ?>"
                   min="0" max="100" step="0.5">
            <p class="activer-note" style="margin-top:4px;">Par défaut 5 %. Le montant reversé = brut − commission.</p>
        </div>

        <div class="activer-row">
            <input type="checkbox" id="activer_inscription" name="activer_inscription" value="1"
                <?= ($etab['paiement_statut'] ?? '') === 'actif' ? 'checked' : '' ?>>
            <div>
                <label for="activer_inscription">Activer les inscriptions</label>
                <p class="activer-note">Passe le compte en statut « actif » et active les inscriptions sur la page publique.</p>
            </div>
        </div>

        <div style="margin-top:28px;display:flex;gap:12px;">
            <button type="submit" class="btn-admin-primary">Enregistrer</button>
            <a href="<?= url('/admin/orientation') ?>" class="btn-admin-secondary">Annuler</a>
        </div>
    </form>
</div>
