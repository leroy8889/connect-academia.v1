<?php
/**
 * Sidebar Front-Office
 */
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar__logo">
    <img src="assets/img/logo.svg" alt="Connect'Academia">
    <span>Connect'Academia</span>
</div>
<nav class="sidebar__nav">
    <a href="dashboard.php" class="sidebar__nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
        <i data-lucide="layout-dashboard"></i>
        <span>Tableau de bord</span>
    </a>
    <a href="matieres.php" class="sidebar__nav-item <?= $current_page === 'matieres.php' ? 'active' : '' ?>">
        <i data-lucide="book-open"></i>
        <span>Mes Matières</span>
    </a>
    <a href="favoris.php" class="sidebar__nav-item <?= $current_page === 'favoris.php' ? 'active' : '' ?>">
        <i data-lucide="star"></i>
        <span>Mes Favoris</span>
    </a>
    <a href="progression.php" class="sidebar__nav-item <?= $current_page === 'progression.php' ? 'active' : '' ?>">
        <i data-lucide="bar-chart-2"></i>
        <span>Ma Progression</span>
    </a>
    <a href="notifications.php" class="sidebar__nav-item <?= $current_page === 'notifications.php' ? 'active' : '' ?>">
        <i data-lucide="bell"></i>
        <span>Notifications</span>
    </a>
    <a href="profil.php" class="sidebar__nav-item <?= $current_page === 'profil.php' ? 'active' : '' ?>">
        <i data-lucide="user"></i>
        <span>Mon Profil</span>
    </a>
</nav>
<div class="sidebar__user">
    <div class="sidebar__user-avatar">
        <?= strtoupper(substr($_SESSION['user_nom'] ?? 'U', 0, 1)) ?>
    </div>
    <div>
        <div style="font-weight: 600; font-size: 14px;"><?= e($_SESSION['user_nom'] ?? 'Utilisateur') ?></div>
        <div style="font-size: 12px; color: var(--color-text-light);">Futur Bachelier</div>
    </div>
</div>
<a href="logout.php" class="sidebar__nav-item" style="margin-top: 8px;">
    <i data-lucide="log-out"></i>
    <span>Déconnexion</span>
</a>

