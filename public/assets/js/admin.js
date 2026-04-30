/* Connect'Academia — Admin JS
   Interactions: sidebar, modals, toggles, upload, search, CRUD complet
   CTO: ONA-DAVID LEROY — Phase 10 */

document.addEventListener('DOMContentLoaded', () => {

  /* ── CSRF helper ─────────────────────────────────────────── */
  const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.content || '';

  /* ── Base URL ────────────────────────────────────────────── */
  const base = () => window.CA_ADMIN?.baseUrl ?? '';

  /* ── Sidebar toggle mobile ───────────────────────────────── */
  const sidebar   = document.getElementById('admin-sidebar');
  const hamburger = document.getElementById('sidebar-toggle');
  const overlay   = document.getElementById('sidebar-overlay');

  if (hamburger) {
    hamburger.addEventListener('click', () => {
      sidebar?.classList.toggle('open');
      overlay?.classList.toggle('visible');
    });
  }
  if (overlay) {
    overlay.addEventListener('click', () => {
      sidebar?.classList.remove('open');
      overlay.classList.remove('visible');
    });
  }

  /* ── Password toggle ─────────────────────────────────────── */
  document.querySelectorAll('.password-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = btn.closest('.admin-input-wrap').querySelector('input');
      if (!input) return;
      const isText = input.type === 'text';
      input.type = isText ? 'password' : 'text';
      btn.querySelector('.icon-eye')?.classList.toggle('hidden', !isText);
      btn.querySelector('.icon-eye-off')?.classList.toggle('hidden', isText);
    });
  });

  /* ── OTP auto-advance ────────────────────────────────────── */
  const otpInputs = document.querySelectorAll('.otp-input');
  otpInputs.forEach((inp, i) => {
    inp.addEventListener('input', () => {
      if (inp.value.length === 1 && i < otpInputs.length - 1) otpInputs[i + 1].focus();
      syncOtpHidden();
    });
    inp.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && inp.value === '' && i > 0) otpInputs[i - 1].focus();
    });
    inp.addEventListener('paste', e => {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
      [...text].forEach((ch, j) => { if (otpInputs[i + j]) otpInputs[i + j].value = ch; });
      otpInputs[Math.min(i + text.length - 1, otpInputs.length - 1)]?.focus();
      syncOtpHidden();
    });
  });
  function syncOtpHidden() {
    const hidden = document.getElementById('otp-code-hidden');
    if (hidden) hidden.value = [...otpInputs].map(i => i.value).join('');
  }

  /* ── Modal générique ─────────────────────────────────────── */
  document.querySelectorAll('[data-modal-open]').forEach(btn => {
    btn.addEventListener('click', () => openModal(btn.dataset.modalOpen));
  });
  document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.dataset.modalClose));
  });
  document.querySelectorAll('.admin-modal-overlay').forEach(ov => {
    ov.addEventListener('click', e => { if (e.target === ov) ov.classList.remove('open'); });
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape')
      document.querySelectorAll('.admin-modal-overlay.open').forEach(m => m.classList.remove('open'));
  });

  function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
  function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

  /* ── Helper : requête JSON ───────────────────────────────── */
  async function apiJson(url, method, body = {}) {
    const res = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ ...body, _csrf_token: csrfToken() }),
    });
    return res.json();
  }

  /* ── Helper : requête FormData ───────────────────────────── */
  // Toujours envoyer en POST + champ _method pour que PHP peuple $_POST
  // (PHP ne peuple pas $_POST pour PATCH/PUT/DELETE multipart)
  async function apiForm(url, method, form) {
    const fd = new FormData(form);
    fd.append('_csrf_token', csrfToken());
    if (method !== 'POST') fd.append('_method', method);
    const res = await fetch(url, {
      method:  'POST',
      headers: { 'X-CSRF-Token': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
      body: fd,
    });
    return res.json();
  }

  /* ── Helper : bouton en attente ──────────────────────────── */
  function setBtnLoading(btn, loading, original) {
    if (loading) { btn._orig = btn.textContent; btn.disabled = true; btn.textContent = '…'; }
    else { btn.disabled = false; btn.textContent = original ?? btn._orig ?? 'OK'; }
  }

  /* ── Toast notification ──────────────────────────────────── */
  window.showToast = function(message, type = 'info') {
    let container = document.getElementById('admin-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'admin-toast-container';
      container.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
      document.body.appendChild(container);
    }
    const colors = { success: '#22C55E', error: '#EF4444', info: '#3B82F6', warning: '#F59E0B' };
    const toast = document.createElement('div');
    toast.style.cssText = `
      background:#fff;border:1px solid #EBEBF5;border-left:4px solid ${colors[type] ?? colors.info};
      border-radius:10px;padding:12px 16px;font-family:'Manrope',sans-serif;font-size:13px;
      color:#1A1A2E;box-shadow:0 4px 12px rgba(0,0,0,0.1);max-width:320px;word-break:break-word;
      animation:slideInRight 0.25s ease;pointer-events:auto;
    `;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .3s'; setTimeout(() => toast.remove(), 320); }, 3200);
  };
  const showToast = window.showToast;

  /* ═══════════════════════════════════════════════════════════
     UTILISATEURS — CRUD
  ═══════════════════════════════════════════════════════════ */

  /* ── Toggle activer/suspendre ────────────────────────────── */
  document.querySelectorAll('.user-toggle').forEach(toggle => {
    toggle.addEventListener('change', async () => {
      const userId = toggle.dataset.userId;
      const row    = toggle.closest('tr');
      const badge  = row?.querySelector('.status-badge');
      try {
        const data = await apiJson(`${base()}/admin/api/utilisateurs/${userId}/toggle`, 'PATCH');
        if (!data.success) { toggle.checked = !toggle.checked; showToast(data.message ?? 'Erreur', 'error'); return; }
        if (badge) {
          badge.className = 'badge badge-' + (toggle.checked ? 'actif' : 'suspendu') + ' status-badge';
          badge.textContent = toggle.checked ? 'Actif' : 'Suspendu';
        }
        showToast(data.message ?? 'Mis à jour', 'success');
      } catch { toggle.checked = !toggle.checked; showToast('Erreur réseau', 'error'); }
    });
  });

  /* ── Créer utilisateur ───────────────────────────────────── */
  const btnSubmitCreateUser = document.getElementById('btn-submit-create-user');
  if (btnSubmitCreateUser) {
    btnSubmitCreateUser.addEventListener('click', async () => {
      const form = document.getElementById('form-create-user');
      if (!form.checkValidity()) { form.reportValidity(); return; }
      setBtnLoading(btnSubmitCreateUser, true);
      try {
        const data = await apiForm(`${base()}/admin/api/utilisateurs`, 'POST', form);
        if (data.success) {
          closeModal('modal-create-user');
          form.reset();
          showToast(data.message, 'success');
          setTimeout(() => location.reload(), 900);
        } else {
          showToast(data.message ?? 'Erreur', 'error');
        }
      } catch { showToast('Erreur réseau', 'error'); }
      finally { setBtnLoading(btnSubmitCreateUser, false, 'Créer l\'utilisateur'); }
    });
  }

  /* ── Ouvrir modal édition utilisateur ────────────────────── */
  document.querySelectorAll('.btn-edit-user').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('edit-user-id').value    = btn.dataset.userId;
      document.getElementById('edit-prenom').value     = btn.dataset.prenom;
      document.getElementById('edit-nom').value        = btn.dataset.nom;
      document.getElementById('edit-email').value      = btn.dataset.email;
      document.getElementById('edit-role').value       = btn.dataset.role;
      const serieSelect = document.getElementById('edit-serie-id');
      if (serieSelect) serieSelect.value = btn.dataset.serieId ?? '';
      openModal('modal-edit-user');
    });
  });

  /* ── Soumettre édition utilisateur ──────────────────────── */
  const btnSubmitEditUser = document.getElementById('btn-submit-edit-user');
  if (btnSubmitEditUser) {
    btnSubmitEditUser.addEventListener('click', async () => {
      const form   = document.getElementById('form-edit-user');
      const userId = document.getElementById('edit-user-id').value;
      if (!form.checkValidity()) { form.reportValidity(); return; }
      setBtnLoading(btnSubmitEditUser, true);
      try {
        const data = await apiForm(`${base()}/admin/api/utilisateurs/${userId}`, 'PATCH', form);
        if (data.success) {
          closeModal('modal-edit-user');
          showToast(data.message, 'success');
          setTimeout(() => location.reload(), 900);
        } else {
          showToast(data.message ?? 'Erreur', 'error');
        }
      } catch { showToast('Erreur réseau', 'error'); }
      finally { setBtnLoading(btnSubmitEditUser, false, 'Enregistrer'); }
    });
  }

  /* ── Supprimer utilisateur ───────────────────────────────── */
  document.querySelectorAll('.btn-delete-user').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id   = btn.dataset.userId;
      const name = btn.dataset.name;
      if (!confirm(`Supprimer définitivement "${name}" ?\n\nCette action est irréversible.`)) return;
      try {
        const data = await apiJson(`${base()}/admin/api/utilisateurs/${id}`, 'DELETE');
        if (data.success) {
          btn.closest('tr')?.remove();
          showToast(data.message, 'success');
        } else {
          showToast(data.message ?? 'Erreur', 'error');
        }
      } catch { showToast('Erreur réseau', 'error'); }
    });
  });

  /* ═══════════════════════════════════════════════════════════
     RESSOURCES — CRUD
  ═══════════════════════════════════════════════════════════ */

  /* ── Upload nouvelle ressource ───────────────────────────── */
  const uploadForm = document.getElementById('upload-ressource-form');
  if (uploadForm) {
    const dropZone     = document.getElementById('drop-zone');
    const fileInput    = document.getElementById('fichier-input');
    const fileNameEl   = document.getElementById('file-name');
    const progressWrap = document.getElementById('upload-progress-wrap');
    const progressFill = document.getElementById('upload-progress-fill');

    ['dragenter','dragover'].forEach(ev =>
      dropZone?.addEventListener(ev, e => { e.preventDefault(); dropZone.classList.add('dragover'); })
    );
    ['dragleave','drop'].forEach(ev =>
      dropZone?.addEventListener(ev, e => { e.preventDefault(); dropZone.classList.remove('dragover'); })
    );
    dropZone?.addEventListener('drop', e => {
      const file = e.dataTransfer?.files[0];
      if (file && fileInput) {
        const dt = new DataTransfer(); dt.items.add(file); fileInput.files = dt.files;
        if (fileNameEl) fileNameEl.textContent = file.name;
      }
    });
    fileInput?.addEventListener('change', () => {
      if (fileNameEl && fileInput.files[0]) fileNameEl.textContent = fileInput.files[0].name;
    });

    // Charger matières selon série (endpoint admin)
    const serieSelect   = uploadForm.querySelector('[name="serie_id"]');
    const matiereSelect = uploadForm.querySelector('[name="matiere_id"]');
    serieSelect?.addEventListener('change', () => loadMatieres(serieSelect.value, matiereSelect, 'Choisir une matière'));

    uploadForm.addEventListener('submit', async e => {
      e.preventDefault();
      const submitBtn = uploadForm.querySelector('[type="submit"]');
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Envoi…'; }
      if (progressWrap) progressWrap.classList.add('visible');

      const fd = new FormData(uploadForm);
      fd.append('_csrf_token', csrfToken());

      const xhr = new XMLHttpRequest();
      xhr.open('POST', `${base()}/admin/api/contenu/ressource`);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.upload.onprogress = ev => {
        if (ev.lengthComputable && progressFill)
          progressFill.style.width = Math.round((ev.loaded / ev.total) * 100) + '%';
      };
      xhr.onload = () => {
        try {
          const data = JSON.parse(xhr.responseText);
          if (data.success) {
            closeModal('modal-upload');
            showToast('Ressource ajoutée avec succès !', 'success');
            setTimeout(() => location.reload(), 800);
          } else {
            showToast(data.message ?? data.error?.message ?? 'Erreur lors de l\'upload', 'error');
          }
        } catch { showToast('Réponse invalide du serveur', 'error'); }
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Mettre en ligne'; }
        if (progressWrap) progressWrap.classList.remove('visible');
        if (progressFill) progressFill.style.width = '0%';
      };
      xhr.onerror = () => {
        showToast('Erreur réseau', 'error');
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Mettre en ligne'; }
      };
      xhr.send(fd);
    });
  }

  /* ── Ouvrir modal édition ressource ──────────────────────── */
  document.querySelectorAll('.btn-edit-ressource').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('edit-ressource-id').value    = btn.dataset.id;
      document.getElementById('edit-ressource-titre').value = btn.dataset.titre;
      document.getElementById('edit-ressource-type').value  = btn.dataset.type;
      document.getElementById('edit-ressource-annee').value = btn.dataset.annee ?? '';
      document.getElementById('edit-ressource-desc').value  = btn.dataset.desc ?? '';
      openModal('modal-edit-ressource');
    });
  });

  /* ── Soumettre édition ressource ─────────────────────────── */
  const btnSubmitEditRessource = document.getElementById('btn-submit-edit-ressource');
  if (btnSubmitEditRessource) {
    btnSubmitEditRessource.addEventListener('click', async () => {
      const form = document.getElementById('form-edit-ressource');
      const id   = document.getElementById('edit-ressource-id').value;
      if (!form.checkValidity()) { form.reportValidity(); return; }
      setBtnLoading(btnSubmitEditRessource, true);
      try {
        const data = await apiForm(`${base()}/admin/api/contenu/ressource/${id}`, 'PATCH', form);
        if (data.success) {
          closeModal('modal-edit-ressource');
          showToast(data.message, 'success');
          setTimeout(() => location.reload(), 900);
        } else {
          showToast(data.message ?? 'Erreur', 'error');
        }
      } catch { showToast('Erreur réseau', 'error'); }
      finally { setBtnLoading(btnSubmitEditRessource, false, 'Enregistrer'); }
    });
  }

  /* ── Supprimer ressource ─────────────────────────────────── */
  document.querySelectorAll('.btn-delete-ressource').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id    = btn.dataset.id;
      const titre = btn.dataset.titre;
      if (!confirm(`Supprimer "${titre}" ?\nCette action est irréversible.`)) return;
      try {
        const data = await apiJson(`${base()}/admin/api/contenu/ressource/${id}`, 'DELETE');
        if (data.success) {
          btn.closest('.ressource-card, tr')?.remove();
          showToast('Ressource supprimée', 'success');
        } else {
          showToast(data.message ?? data.error?.message ?? 'Erreur', 'error');
        }
      } catch { showToast('Erreur réseau', 'error'); }
    });
  });

  /* ── Filtre matières (bibliothèque) ──────────────────────── */
  const filterSerie   = document.getElementById('filter-serie');
  const filterMatiere = document.getElementById('filter-matiere');
  if (filterSerie && filterMatiere) {
    filterSerie.addEventListener('change', () =>
      loadMatieres(filterSerie.value, filterMatiere, 'Toutes les matières ▾')
    );
  }

  /* ── Charger matières (données pré-chargées en priorité, AJAX en fallback) ── */
  async function loadMatieres(serieId, selectEl, placeholder) {
    if (!selectEl) return;
    selectEl.innerHTML = `<option value="">${placeholder}</option>`;
    if (!serieId) return;

    // Priorité : données pré-chargées dans la page (plus fiable, pas d'AJAX)
    const preloaded = window.CA_MATIERES_BY_SERIE?.[serieId]
                   ?? window.CA_MATIERES_BY_SERIE?.[parseInt(serieId, 10)];
    if (preloaded) {
      preloaded.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id; opt.textContent = m.nom;
        selectEl.appendChild(opt);
      });
      return;
    }

    // Fallback AJAX
    selectEl.innerHTML = '<option value="">Chargement…</option>';
    try {
      const apiBase = base() || window.location.pathname.replace(/\/admin\/.*$/, '');
      const res  = await fetch(`${apiBase}/admin/api/matieres?serie_id=${serieId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await res.json();
      selectEl.innerHTML = `<option value="">${placeholder}</option>`;
      (data.matieres ?? []).forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id; opt.textContent = m.nom;
        selectEl.appendChild(opt);
      });
    } catch {
      selectEl.innerHTML = `<option value="">${placeholder}</option>`;
    }
  }

  /* ═══════════════════════════════════════════════════════════
     COMMUNAUTÉ — MODÉRATION
  ═══════════════════════════════════════════════════════════ */

  /* ── Épingler / désépingler un post ──────────────────────── */
  document.querySelectorAll('.btn-pin-post').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.postId;
      try {
        const data = await apiJson(`${base()}/admin/api/communaute/posts/${id}/pin`, 'PATCH');
        if (data.success) {
          const isPinned = data.pinned;
          btn.dataset.pinned = isPinned ? '1' : '0';
          btn.title = isPinned ? 'Désépingler' : 'Épingler';
          btn.style.color = isPinned ? 'var(--ap)' : 'var(--txt-l)';
          const svg = btn.querySelector('svg');
          if (svg) svg.setAttribute('fill', isPinned ? 'currentColor' : 'none');

          // Mettre à jour le badge "Épinglé" sur le post
          const postItem = btn.closest('.post-item');
          let pinnedBadge = postItem?.querySelector('.pinned-badge');
          if (isPinned && postItem && !pinnedBadge) {
            pinnedBadge = document.createElement('span');
            pinnedBadge.className = 'pinned-badge';
            pinnedBadge.style.cssText = 'font-size:10px;font-weight:600;color:var(--ap);background:var(--ap-xl);padding:1px 7px;border-radius:20px;display:inline-flex;align-items:center;gap:3px;';
            pinnedBadge.innerHTML = `<svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L8.5 8.5H2l5.5 5L5 22l7-4 7 4-2.5-8.5L22 8.5h-6.5L12 2z"/></svg> Épinglé`;
            postItem.querySelector('.user-info, div')?.after(pinnedBadge);
          } else if (!isPinned && pinnedBadge) {
            pinnedBadge.remove();
          }
          showToast(data.message, 'success');
        } else {
          showToast(data.message ?? 'Erreur', 'error');
        }
      } catch { showToast('Erreur réseau', 'error'); }
    });
  });

  /* ── Supprimer un post ───────────────────────────────────── */
  document.querySelectorAll('.btn-delete-post').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.postId;
      if (!confirm('Supprimer cette publication ?\nL\'action est irréversible.')) return;
      try {
        const data = await apiJson(`${base()}/admin/api/communaute/posts/${id}`, 'DELETE');
        if (data.success) {
          btn.closest('.post-item')?.remove();
          showToast('Publication supprimée', 'success');
        } else {
          showToast(data.message ?? 'Erreur', 'error');
        }
      } catch { showToast('Erreur réseau', 'error'); }
    });
  });

  /* ── Traiter signalement (SignalementsController) ────────── */
  document.querySelectorAll('.btn-traiter-report').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id     = btn.dataset.id;
      const action = btn.dataset.action;
      try {
        const data = await apiJson(`${base()}/admin/api/signalements/${id}`, 'PATCH', { action });
        if (data.success) {
          btn.closest('.kanban-card')?.remove();
          showToast('Signalement traité', 'success');
        } else {
          showToast(data.message ?? data.error?.message ?? 'Erreur', 'error');
        }
      } catch { showToast('Erreur réseau', 'error'); }
    });
  });

  /* ── Traiter report communauté (CommunauteController) ───── */
  document.querySelectorAll('.btn-traiter-communaute-report').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id     = btn.dataset.id;
      const action = btn.dataset.action;
      try {
        const data = await apiJson(`${base()}/admin/api/communaute/reports/${id}`, 'PATCH', { action });
        if (data.success) {
          btn.closest('.kanban-card')?.remove();
          showToast('Signalement traité', 'success');
        } else {
          showToast(data.message ?? data.error?.message ?? 'Erreur', 'error');
        }
      } catch { showToast('Erreur réseau', 'error'); }
    });
  });

  /* ═══════════════════════════════════════════════════════════
     ÉQUIPE ADMIN — GESTION
  ═══════════════════════════════════════════════════════════ */

  /* ── Créer un admin ──────────────────────────────────────── */
  const btnSubmitCreateAdmin = document.getElementById('btn-submit-create-admin');
  if (btnSubmitCreateAdmin) {
    btnSubmitCreateAdmin.addEventListener('click', async () => {
      const form = document.getElementById('form-create-admin');
      if (!form.checkValidity()) { form.reportValidity(); return; }
      setBtnLoading(btnSubmitCreateAdmin, true);
      try {
        const data = await apiForm(`${base()}/admin/api/admins`, 'POST', form);
        if (data.success) {
          closeModal('modal-create-admin');
          form.reset();
          showToast(data.message, 'success');
          setTimeout(() => location.reload(), 900);
        } else {
          showToast(data.message ?? 'Erreur', 'error');
        }
      } catch { showToast('Erreur réseau', 'error'); }
      finally { setBtnLoading(btnSubmitCreateAdmin, false, 'Créer le compte'); }
    });
  }

  /* ── Supprimer un admin ──────────────────────────────────── */
  document.querySelectorAll('.btn-delete-admin').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id   = btn.dataset.adminId;
      const name = btn.dataset.name;
      if (!confirm(`Désactiver le compte de "${name}" ?\nIl ne pourra plus se connecter.`)) return;
      try {
        const data = await apiJson(`${base()}/admin/api/admins/${id}`, 'DELETE');
        if (data.success) {
          btn.closest('.admin-team-row')?.remove();
          showToast(data.message, 'success');
        } else {
          showToast(data.message ?? 'Erreur', 'error');
        }
      } catch { showToast('Erreur réseau', 'error'); }
    });
  });

  /* ═══════════════════════════════════════════════════════════
     UTILITAIRES
  ═══════════════════════════════════════════════════════════ */

  /* ── Recherche live dans tables / grilles ────────────────── */
  document.querySelectorAll('[data-search-table]').forEach(input => {
    const tableId = input.dataset.searchTable;
    const table   = document.getElementById(tableId);
    if (!table) return;
    input.addEventListener('input', () => {
      const q = input.value.toLowerCase();
      // Tables (tbody tr)
      table.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
      // Grilles (cards)
      table.querySelectorAll('.ressource-card').forEach(card => {
        card.style.display = card.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  });

  /* ── Notifications : mark-all-read ──────────────────────── */
  const btnMarkAll = document.getElementById('btn-mark-all-read');
  if (btnMarkAll) {
    btnMarkAll.addEventListener('click', async () => {
      try {
        const data = await apiJson(`${base()}/admin/api/notifications/mark-all`, 'POST');
        if (data.success) {
          document.querySelectorAll('.notif-unread').forEach(el => el.classList.remove('notif-unread'));
          showToast('Toutes les notifications lues', 'success');
        }
      } catch { /* silently fail */ }
    });
  }

  /* ── Animation CSS keyframes ─────────────────────────────── */
  const style = document.createElement('style');
  style.textContent = `
    @keyframes slideInRight {
      from { opacity:0; transform:translateX(20px); }
      to   { opacity:1; transform:translateX(0); }
    }
  `;
  document.head.appendChild(style);

});
