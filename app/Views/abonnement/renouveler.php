<?php
$expired = $expired ?? null;
$finFr   = $expired ? date('d/m/Y', strtotime($expired['fin'])) : null;
?>

<div style="min-height:calc(100vh - var(--navbar-h,64px));display:flex;align-items:center;justify-content:center;padding:40px 20px;">
  <div style="background:#fff;border-radius:var(--radius-xl,24px);padding:48px 40px;max-width:440px;width:100%;text-align:center;box-shadow:var(--shadow-lg);position:relative;overflow:hidden;">

    <!-- Top bar orange -->
    <div style="position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#f59e0b,#ef4444);"></div>

    <!-- Icon -->
    <div style="width:72px;height:72px;background:linear-gradient(135deg,#f59e0b,#ef4444);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;box-shadow:0 8px 20px rgba(245,158,11,0.3);">
      <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
    </div>

    <h1 style="font-family:'Montserrat',sans-serif;font-size:1.6rem;font-weight:800;color:var(--text-primary,#1A1A2E);margin-bottom:10px;">Abonnement expiré</h1>

    <?php if ($finFr): ?>
    <p style="color:var(--text-secondary,#6B7280);font-size:0.9375rem;margin-bottom:24px;">
      Votre abonnement a expiré le <strong style="color:#ef4444;"><?= $finFr ?></strong>.<br>
      Renouvelez maintenant pour continuer à accéder à vos cours et à la communauté.
    </p>
    <?php else: ?>
    <p style="color:var(--text-secondary,#6B7280);font-size:0.9375rem;margin-bottom:24px;">
      Renouvelez votre abonnement pour continuer à accéder à tous les modules Connect'Academia.
    </p>
    <?php endif; ?>

    <a href="<?= url('/abonnement/choisir') ?>"
       style="display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:15px;background:linear-gradient(135deg,#8B52FA,#7440D9);color:white;border-radius:var(--radius-md,12px);font-weight:700;font-size:15px;text-decoration:none;box-shadow:0 6px 20px rgba(139,82,250,0.3);transition:opacity 0.2s;"
       onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
      Renouveler mon abonnement
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </a>

    <p style="margin-top:16px;font-size:13px;color:var(--text-secondary,#9CA3AF);">
      <a href="<?= url('/hub') ?>" style="color:var(--primary,#8B52FA);">← Retour au Hub</a>
    </p>

  </div>
</div>
