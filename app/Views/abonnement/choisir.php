<div class="container section">
  <div style="max-width:640px;margin:0 auto;text-align:center;padding:3rem 1rem">
    <div style="width:64px;height:64px;background:var(--primary-light);border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem">
      <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#8B52FA" stroke-width="2">
        <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
      </svg>
    </div>
    <h1 style="font-size:1.75rem;font-weight:800;margin-bottom:.75rem">Choisissez votre plan</h1>
    <p style="color:var(--text-secondary);margin-bottom:2.5rem">Accès complet à tous les modules : Apprentissage et Communauté</p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;text-align:left">
      <!-- Mensuel -->
      <div style="background:var(--bg-primary);border:1.5px solid var(--border);border-radius:var(--radius-lg);padding:1.5rem">
        <h3 style="font-weight:700;margin-bottom:.5rem">Mensuel</h3>
        <div style="font-size:2rem;font-weight:800;color:var(--primary);margin-bottom:1rem">2 000 <span style="font-size:1rem;font-weight:400;color:var(--text-secondary)">XAF</span></div>
        <a href="<?= url('/hub') ?>" class="btn btn--outline btn--full" style="margin-top:.5rem">Bientôt disponible</a>
      </div>
      <!-- Annuel -->
      <div style="background:var(--primary);border-radius:var(--radius-lg);padding:1.5rem;color:white">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
          <h3 style="font-weight:700">Annuel</h3>
          <span style="background:rgba(255,255,255,0.2);padding:.15rem .5rem;border-radius:999px;font-size:.75rem;font-weight:600">-37%</span>
        </div>
        <div style="font-size:2rem;font-weight:800;margin-bottom:1rem">15 000 <span style="font-size:1rem;font-weight:400;opacity:.7">XAF</span></div>
        <a href="<?= url('/hub') ?>" class="btn btn--full" style="background:white;color:var(--primary);margin-top:.5rem">Bientôt disponible</a>
      </div>
    </div>

    <p style="margin-top:1.5rem;color:var(--text-secondary);font-size:.875rem">
      Le paiement en ligne sera disponible prochainement.<br>
      <a href="<?= url('/hub') ?>">← Retour au Hub</a>
    </p>
  </div>
</div>
