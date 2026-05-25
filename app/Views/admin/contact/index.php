<?php
$messages = $messages ?? [];
$total    = $total    ?? 0;
$unread   = $unread   ?? 0;
$page     = $page     ?? 1;
$pages    = $pages    ?? 1;

function timeAgo(string $dateStr): string {
    $diff = time() - strtotime($dateStr);
    if ($diff < 60)     return 'À l\'instant';
    if ($diff < 3600)   return floor($diff / 60) . ' min';
    if ($diff < 86400)  return floor($diff / 3600) . 'h';
    if ($diff < 604800) return floor($diff / 86400) . 'j';
    return date('d/m/Y', strtotime($dateStr));
}
?>

<style>
/* ── Contact Messages — Admin Page ── */
.contact-page { display: flex; flex-direction: column; gap: 24px; }

/* Stats row */
.contact-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.contact-stat-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--r);
  padding: 20px 24px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: var(--shadow);
}
.contact-stat-icon {
  width: 48px; height: 48px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.contact-stat-icon.purple { background: var(--ap-lt); color: var(--ap); }
.contact-stat-icon.green  { background: var(--green-bg); color: var(--green); }
.contact-stat-icon.amber  { background: var(--amber-bg); color: var(--amber); }
.contact-stat-label { font-size: 12px; color: var(--txt-m); font-weight: 500; margin-bottom: 4px; }
.contact-stat-value { font-size: 26px; font-weight: 700; color: var(--txt); font-family: var(--font-head); }

/* Toolbar */
.contact-toolbar {
  display: flex; align-items: center; gap: 12px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--r);
  padding: 14px 20px;
  box-shadow: var(--shadow);
}
.contact-toolbar-search {
  flex: 1;
  display: flex; align-items: center; gap: 8px;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--r-sm);
  padding: 8px 14px;
}
.contact-toolbar-search input {
  border: none; background: transparent; outline: none;
  font-size: 13px; color: var(--txt); width: 100%;
  font-family: var(--font-body);
}
.contact-toolbar-search input::placeholder { color: var(--txt-l); }

/* Messages list */
.contact-list-wrap {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--r);
  box-shadow: var(--shadow);
  overflow: hidden;
}
.contact-list-header {
  display: grid;
  grid-template-columns: 32px 200px 1fr 140px 90px 80px;
  gap: 12px;
  align-items: center;
  padding: 12px 20px;
  background: var(--bg);
  border-bottom: 1px solid var(--border);
  font-size: 11px;
  font-weight: 600;
  color: var(--txt-m);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.contact-row {
  display: grid;
  grid-template-columns: 32px 200px 1fr 140px 90px 80px;
  gap: 12px;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  cursor: pointer;
  transition: background 0.15s;
  position: relative;
}
.contact-row:last-child { border-bottom: none; }
.contact-row:hover { background: var(--ap-xl); }
.contact-row.unread { background: #FAFAFF; }
.contact-row.unread::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 3px;
  background: var(--ap);
  border-radius: 0 2px 2px 0;
}

.contact-row-avatar {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: var(--ap-lt);
  color: var(--ap);
  font-weight: 700;
  font-size: 13px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.contact-row-name { font-weight: 600; font-size: 13px; color: var(--txt); }
.contact-row-email { font-size: 11px; color: var(--txt-m); margin-top: 1px; }
.contact-row-preview { font-size: 13px; color: var(--txt-m); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.contact-row-preview .subject-bold { font-weight: 600; color: var(--txt); }
.contact-row-phone { font-size: 12px; color: var(--txt-m); }
.contact-row-time { font-size: 11px; color: var(--txt-l); text-align: right; }
.contact-row-actions { display: flex; gap: 4px; justify-content: flex-end; }
.contact-row-actions button {
  width: 28px; height: 28px;
  border: none; background: none; cursor: pointer;
  border-radius: 6px;
  color: var(--txt-m);
  display: flex; align-items: center; justify-content: center;
  transition: all 0.15s;
}
.contact-row-actions button:hover { background: var(--bg); color: var(--txt); }
.contact-row-actions button.del:hover { background: var(--red-bg); color: var(--red); }

/* Badge non lu */
.unread-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--ap);
  flex-shrink: 0;
}

/* Modal */
.contact-modal-overlay {
  position: fixed; inset: 0;
  background: rgba(20,20,43,0.5);
  z-index: 1000;
  display: flex; align-items: center; justify-content: center;
  padding: 24px;
  opacity: 0; pointer-events: none;
  transition: opacity 0.2s;
}
.contact-modal-overlay.open { opacity: 1; pointer-events: all; }
.contact-modal {
  background: var(--card);
  border-radius: var(--r);
  box-shadow: var(--shadow-lg);
  width: 100%; max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  transform: translateY(20px);
  transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.contact-modal-overlay.open .contact-modal { transform: translateY(0); }
.contact-modal-header {
  padding: 24px 28px 20px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: flex-start; gap: 16px;
}
.contact-modal-avatar {
  width: 48px; height: 48px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--ap), var(--ap-dk));
  color: #fff;
  font-size: 18px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.contact-modal-sender-name { font-size: 16px; font-weight: 700; color: var(--txt); }
.contact-modal-sender-email { font-size: 13px; color: var(--txt-m); margin-top: 2px; }
.contact-modal-close {
  margin-left: auto;
  width: 32px; height: 32px;
  border: 1px solid var(--border);
  background: none; cursor: pointer;
  border-radius: 8px; color: var(--txt-m);
  display: flex; align-items: center; justify-content: center;
  transition: all 0.15s;
}
.contact-modal-close:hover { background: var(--red-bg); color: var(--red); border-color: var(--red-bg); }
.contact-modal-meta {
  padding: 16px 28px;
  background: var(--bg);
  border-bottom: 1px solid var(--border);
  display: flex; gap: 24px; flex-wrap: wrap;
}
.contact-modal-meta-item { display: flex; align-items: center; gap: 8px; }
.contact-modal-meta-label { font-size: 11px; font-weight: 600; color: var(--txt-m); text-transform: uppercase; letter-spacing: 0.04em; }
.contact-modal-meta-value { font-size: 13px; color: var(--txt); }
.contact-modal-body {
  padding: 24px 28px;
}
.contact-modal-message-label {
  font-size: 11px; font-weight: 600; color: var(--txt-m);
  text-transform: uppercase; letter-spacing: 0.04em;
  margin-bottom: 12px;
}
.contact-modal-message-text {
  font-size: 14px; color: var(--txt);
  line-height: 1.7;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--r-sm);
  padding: 20px;
  white-space: pre-wrap;
}
.contact-modal-footer {
  padding: 16px 28px;
  border-top: 1px solid var(--border);
  display: flex; justify-content: space-between; align-items: center;
}

/* Pagination */
.contact-pagination {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--r);
  box-shadow: var(--shadow);
}
.pagination-info { font-size: 13px; color: var(--txt-m); }
.pagination-btns { display: flex; gap: 6px; }
.pagination-btn {
  padding: 6px 12px;
  border: 1px solid var(--border);
  background: var(--card);
  border-radius: var(--r-sm);
  font-size: 13px; cursor: pointer; color: var(--txt);
  font-family: var(--font-body);
  transition: all 0.15s;
}
.pagination-btn:hover { border-color: var(--ap); color: var(--ap); }
.pagination-btn.active { background: var(--ap); color: #fff; border-color: var(--ap); }
.pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* Empty state */
.contact-empty {
  padding: 64px 24px;
  text-align: center;
  color: var(--txt-m);
}
.contact-empty-icon {
  width: 72px; height: 72px;
  border-radius: 50%;
  background: var(--ap-lt);
  color: var(--ap);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 20px;
}
.contact-empty h3 { font-size: 16px; font-weight: 600; color: var(--txt); margin-bottom: 8px; }
.contact-empty p { font-size: 13px; }

/* Toast */
.ca-toast {
  position: fixed; bottom: 28px; right: 28px; z-index: 9999;
  background: var(--txt);
  color: #fff;
  padding: 14px 20px;
  border-radius: var(--r);
  font-size: 13px;
  display: flex; align-items: center; gap: 10px;
  box-shadow: var(--shadow-lg);
  transform: translateY(80px);
  opacity: 0;
  transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
  max-width: 340px;
}
.ca-toast.show { transform: translateY(0); opacity: 1; }
.ca-toast.success { background: var(--green); }
.ca-toast.error   { background: var(--red); }

@media (max-width: 900px) {
  .contact-stats { grid-template-columns: 1fr 1fr; }
  .contact-list-header,
  .contact-row { grid-template-columns: 28px 1fr 90px 80px; }
  .contact-list-header > :nth-child(3),
  .contact-row > :nth-child(3),
  .contact-list-header > :nth-child(4),
  .contact-row > :nth-child(4) { display: none; }
}
@media (max-width: 600px) {
  .contact-stats { grid-template-columns: 1fr; }
  .contact-list-header,
  .contact-row { grid-template-columns: 1fr 80px; }
  .contact-list-header > :nth-child(1),
  .contact-row > :nth-child(1),
  .contact-list-header > :nth-child(3),
  .contact-row > :nth-child(3),
  .contact-list-header > :nth-child(5),
  .contact-row > :nth-child(5) { display: none; }
}
</style>

<div class="contact-page">

  <!-- Header -->
  <div class="admin-page-header-row">
    <div>
      <h1>Messages de contact</h1>
      <p><?= $total ?> message(s) reçu(s) · <?= $unread ?> non lu(s)</p>
    </div>
    <div style="display:flex;gap:10px;">
      <?php if ($unread > 0): ?>
      <button class="btn-ghost" id="btn-mark-all-read">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
        Tout marquer lu
      </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Stats -->
  <div class="contact-stats">
    <div class="contact-stat-card">
      <div class="contact-stat-icon purple">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
          <polyline points="22,6 12,13 2,6"/>
        </svg>
      </div>
      <div>
        <div class="contact-stat-label">Total reçus</div>
        <div class="contact-stat-value"><?= $total ?></div>
      </div>
    </div>
    <div class="contact-stat-card">
      <div class="contact-stat-icon amber">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
      </div>
      <div>
        <div class="contact-stat-label">Non lus</div>
        <div class="contact-stat-value"><?= $unread ?></div>
      </div>
    </div>
    <div class="contact-stat-card">
      <div class="contact-stat-icon green">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      </div>
      <div>
        <div class="contact-stat-label">Lus</div>
        <div class="contact-stat-value"><?= $total - $unread ?></div>
      </div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="contact-toolbar">
    <div class="contact-toolbar-search">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--txt-l)">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input type="text" id="search-contact" placeholder="Rechercher par nom, email ou message…">
    </div>
    <select id="filter-read" style="padding:8px 12px;border:1px solid var(--border);border-radius:var(--r-sm);font-size:13px;font-family:var(--font-body);background:var(--bg);color:var(--txt);cursor:pointer;outline:none;">
      <option value="all">Tous</option>
      <option value="unread">Non lus</option>
      <option value="read">Lus</option>
    </select>
  </div>

  <!-- Liste -->
  <div class="contact-list-wrap">
    <div class="contact-list-header">
      <div></div>
      <div>Expéditeur</div>
      <div>Message</div>
      <div>Téléphone</div>
      <div>Date</div>
      <div style="text-align:right">Actions</div>
    </div>

    <div id="contact-rows">
      <?php if (empty($messages)): ?>
        <div class="contact-empty">
          <div class="contact-empty-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
              <polyline points="22,6 12,13 2,6"/>
            </svg>
          </div>
          <h3>Aucun message reçu</h3>
          <p>Les messages du formulaire de contact apparaîtront ici.</p>
        </div>
      <?php else: ?>
        <?php foreach ($messages as $msg): ?>
          <?php
            $initiale = strtoupper(mb_substr($msg['prenom'], 0, 1));
            $isUnread = !$msg['is_read'];
            $preview  = mb_substr($msg['message'], 0, 80) . (mb_strlen($msg['message']) > 80 ? '…' : '');
          ?>
          <div class="contact-row <?= $isUnread ? 'unread' : '' ?>"
               data-id="<?= $msg['id'] ?>"
               data-nom="<?= htmlspecialchars($msg['nom'], ENT_QUOTES) ?>"
               data-prenom="<?= htmlspecialchars($msg['prenom'], ENT_QUOTES) ?>"
               data-email="<?= htmlspecialchars($msg['email'], ENT_QUOTES) ?>"
               data-tel="<?= htmlspecialchars($msg['telephone'], ENT_QUOTES) ?>"
               data-message="<?= htmlspecialchars($msg['message'], ENT_QUOTES) ?>"
               data-date="<?= htmlspecialchars($msg['created_at'], ENT_QUOTES) ?>"
               data-read="<?= $msg['is_read'] ? '1' : '0' ?>"
               onclick="openModal(this)">
            <div>
              <?php if ($isUnread): ?>
                <div class="unread-dot"></div>
              <?php endif; ?>
            </div>
            <div>
              <div style="display:flex;align-items:center;gap:10px;">
                <div class="contact-row-avatar"><?= $initiale ?></div>
                <div>
                  <div class="contact-row-name"><?= e($msg['prenom']) ?> <?= e($msg['nom']) ?></div>
                  <div class="contact-row-email"><?= e($msg['email']) ?></div>
                </div>
              </div>
            </div>
            <div class="contact-row-preview">
              <?php if ($isUnread): ?><span class="subject-bold"><?= e($preview) ?></span><?php else: ?><?= e($preview) ?><?php endif; ?>
            </div>
            <div class="contact-row-phone"><?= e($msg['telephone']) ?></div>
            <div class="contact-row-time"><?= timeAgo($msg['created_at']) ?></div>
            <div class="contact-row-actions" onclick="event.stopPropagation()">
              <?php if ($isUnread): ?>
              <button title="Marquer comme lu" onclick="markRead(<?= $msg['id'] ?>, this)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
              </button>
              <?php endif; ?>
              <button class="del" title="Supprimer" onclick="deleteMessage(<?= $msg['id'] ?>, this)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6"/>
                  <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                </svg>
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div class="contact-pagination">
    <div class="pagination-info">Page <?= $page ?> sur <?= $pages ?> — <?= $total ?> message(s)</div>
    <div class="pagination-btns">
      <button class="pagination-btn" onclick="goPage(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>>← Préc.</button>
      <?php for ($p = max(1, $page-2); $p <= min($pages, $page+2); $p++): ?>
        <button class="pagination-btn <?= $p === $page ? 'active' : '' ?>" onclick="goPage(<?= $p ?>)"><?= $p ?></button>
      <?php endfor; ?>
      <button class="pagination-btn" onclick="goPage(<?= $page + 1 ?>)" <?= $page >= $pages ? 'disabled' : '' ?>>Suiv. →</button>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- Modal lecture message -->
<div class="contact-modal-overlay" id="modal-overlay" onclick="if(event.target===this)closeModal()">
  <div class="contact-modal" id="modal-box">
    <div class="contact-modal-header">
      <div class="contact-modal-avatar" id="modal-avatar">A</div>
      <div>
        <div class="contact-modal-sender-name" id="modal-name">—</div>
        <div class="contact-modal-sender-email" id="modal-email">—</div>
      </div>
      <button class="contact-modal-close" onclick="closeModal()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="contact-modal-meta">
      <div class="contact-modal-meta-item">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--txt-m)">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
        <span class="contact-modal-meta-label">Tél.</span>
        <span class="contact-modal-meta-value" id="modal-tel">—</span>
      </div>
      <div class="contact-modal-meta-item">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--txt-m)">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        <span class="contact-modal-meta-label">Reçu le</span>
        <span class="contact-modal-meta-value" id="modal-date">—</span>
      </div>
    </div>
    <div class="contact-modal-body">
      <div class="contact-modal-message-label">Message</div>
      <div class="contact-modal-message-text" id="modal-message">—</div>
    </div>
    <div class="contact-modal-footer">
      <span style="font-size:12px;color:var(--txt-m)" id="modal-read-status"></span>
      <div style="display:flex;gap:8px;">
        <button class="btn-ghost btn-sm" id="modal-del-btn">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="3 6 5 6 21 6"/>
            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
          </svg>
          Supprimer
        </button>
        <a id="modal-whatsapp-btn" href="#" target="_blank" rel="noopener noreferrer"
           class="btn-sm" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;background:#25D366;color:#fff;border:none;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:600;font-family:var(--font-body);cursor:pointer;transition:background 0.2s,transform 0.15s,box-shadow 0.15s;"
           onmouseover="this.style.background='#1ebe5d';this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 12px rgba(37,211,102,0.35)'"
           onmouseout="this.style.background='#25D366';this.style.transform='';this.style.boxShadow=''">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/>
          </svg>
          Répondre sur WhatsApp
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="ca-toast" id="ca-toast"></div>

<script>
(function () {
  const BASE = window.CA_ADMIN?.baseUrl ?? '';
  const CSRF = window.CA_ADMIN?.csrfToken ?? '';

  let currentRow = null;
  let currentId  = null;

  /* ── Toast ── */
  function toast(msg, type = 'success') {
    const el = document.getElementById('ca-toast');
    el.textContent = msg;
    el.className = 'ca-toast ' + type + ' show';
    setTimeout(() => el.classList.remove('show'), 3200);
  }

  /* ── Modal ── */
  window.openModal = function (row) {
    currentRow = row;
    currentId  = +row.dataset.id;

    const prenom = row.dataset.prenom;
    const nom    = row.dataset.nom;
    const email  = row.dataset.email;

    document.getElementById('modal-avatar').textContent = prenom[0].toUpperCase();
    document.getElementById('modal-name').textContent   = prenom + ' ' + nom;
    document.getElementById('modal-email').textContent  = email;
    document.getElementById('modal-tel').textContent    = row.dataset.tel;
    document.getElementById('modal-message').textContent = row.dataset.message;

    const d = new Date(row.dataset.date);
    document.getElementById('modal-date').textContent = d.toLocaleDateString('fr-FR', {
      day: '2-digit', month: 'long', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });

    const isUnread = row.dataset.read === '0';
    document.getElementById('modal-read-status').textContent = isUnread ? '● Non lu' : '✓ Lu';
    document.getElementById('modal-read-status').style.color = isUnread ? 'var(--amber)' : 'var(--green)';

    // WhatsApp — formate le numéro en E.164 sans le + pour wa.me
    const rawTel = row.dataset.tel || '';
    let cleanTel = rawTel.replace(/[^\d]/g, ''); // garder chiffres uniquement
    if (cleanTel.startsWith('00')) cleanTel = cleanTel.slice(2); // 00241... → 241...
    const waText = encodeURIComponent(
      'Bonjour ' + prenom + ',\n\nNous avons bien reçu votre message sur Connect\'Academia. '
    );
    document.getElementById('modal-whatsapp-btn').href =
      'https://wa.me/' + cleanTel + '?text=' + waText;

    document.getElementById('modal-del-btn').onclick = () => {
      closeModal();
      deleteMessage(currentId, null);
    };

    document.getElementById('modal-overlay').classList.add('open');

    if (isUnread) {
      markRead(currentId, null, true);
    }
  };

  window.closeModal = function () {
    document.getElementById('modal-overlay').classList.remove('open');
    currentRow = null;
    currentId  = null;
  };

  /* ── Mark read ── */
  window.markRead = async function (id, btn, silent = false) {
    try {
      const res = await fetch(BASE + '/admin/api/contact/' + id + '/read', {
        method: 'PATCH',
        headers: { 'X-CSRF-Token': CSRF, 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await res.json();
      if (data.success) {
        const row = document.querySelector('.contact-row[data-id="' + id + '"]');
        if (row) {
          row.dataset.read = '1';
          row.classList.remove('unread');
          const dot = row.querySelector('.unread-dot');
          if (dot) dot.remove();
          if (btn) btn.remove();
          const previewEl = row.querySelector('.subject-bold');
          if (previewEl) {
            previewEl.className = '';
          }
          const beforeEl = row.querySelector(':before');
        }
        updateUnreadCount(-1);
        if (!silent) toast('Message marqué comme lu.');
      }
    } catch (e) {
      if (!silent) toast('Erreur réseau.', 'error');
    }
  };

  /* ── Mark all read ── */
  const btnAll = document.getElementById('btn-mark-all-read');
  if (btnAll) {
    btnAll.addEventListener('click', async () => {
      try {
        const res = await fetch(BASE + '/admin/api/contact/mark-all-read', {
          method: 'POST',
          headers: { 'X-CSRF-Token': CSRF, 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.success) {
          document.querySelectorAll('.contact-row.unread').forEach(row => {
            row.classList.remove('unread');
            row.dataset.read = '1';
            const dot = row.querySelector('.unread-dot');
            if (dot) dot.remove();
            const bold = row.querySelector('.subject-bold');
            if (bold) bold.className = '';
            const markBtn = row.querySelector('.contact-row-actions button:not(.del)');
            if (markBtn) markBtn.remove();
          });
          document.querySelectorAll('.contact-stat-value')[1].textContent = '0';
          btnAll.style.display = 'none';
          toast('Tous les messages marqués comme lus.', 'success');
        }
      } catch (e) {
        toast('Erreur réseau.', 'error');
      }
    });
  }

  /* ── Delete ── */
  window.deleteMessage = async function (id, btn) {
    if (!confirm('Supprimer ce message définitivement ?')) return;
    try {
      const res = await fetch(BASE + '/admin/api/contact/' + id, {
        method: 'DELETE',
        headers: { 'X-CSRF-Token': CSRF, 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await res.json();
      if (data.success) {
        const row = document.querySelector('.contact-row[data-id="' + id + '"]');
        if (row) {
          const wasUnread = row.dataset.read === '0';
          row.style.transition = 'all 0.3s';
          row.style.opacity = '0';
          row.style.transform = 'translateX(20px)';
          setTimeout(() => row.remove(), 300);
          if (wasUnread) updateUnreadCount(-1);
          updateTotalCount(-1);
        }
        toast('Message supprimé.', 'success');
      }
    } catch (e) {
      toast('Erreur réseau.', 'error');
    }
  };

  function updateUnreadCount(delta) {
    const el = document.querySelectorAll('.contact-stat-value')[1];
    if (el) el.textContent = Math.max(0, +el.textContent + delta);
    const read = document.querySelectorAll('.contact-stat-value')[2];
    if (read && delta < 0) read.textContent = Math.max(0, +read.textContent - delta);
  }

  function updateTotalCount(delta) {
    const el = document.querySelectorAll('.contact-stat-value')[0];
    if (el) el.textContent = Math.max(0, +el.textContent + delta);
  }

  /* ── Search client-side ── */
  const searchInput = document.getElementById('search-contact');
  const filterSelect = document.getElementById('filter-read');

  function applyFilter() {
    const q      = searchInput.value.toLowerCase();
    const filter = filterSelect.value;

    document.querySelectorAll('.contact-row').forEach(row => {
      const name    = (row.dataset.prenom + ' ' + row.dataset.nom + ' ' + row.dataset.email + ' ' + row.dataset.message).toLowerCase();
      const matchQ  = !q || name.includes(q);
      const isRead  = row.dataset.read === '1';
      const matchF  = filter === 'all' || (filter === 'read' && isRead) || (filter === 'unread' && !isRead);
      row.style.display = matchQ && matchF ? '' : 'none';
    });
  }

  if (searchInput)  searchInput.addEventListener('input', applyFilter);
  if (filterSelect) filterSelect.addEventListener('change', applyFilter);

  /* ── Pagination ── */
  window.goPage = function (p) {
    const url = new URL(window.location);
    url.searchParams.set('page', p);
    window.location = url.toString();
  };

  /* Keyboard close modal */
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
})();
</script>
