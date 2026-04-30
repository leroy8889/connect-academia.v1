<?php use Core\Session; ?>

<div class="feed-layout">
    <aside class="sidebar sidebar--left">
        <nav class="sidebar__nav">
            <a href="<?= url('/') ?>" class="sidebar__link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>Home</span>
            </a>
            <a href="<?= url('/explore') ?>" class="sidebar__link sidebar__link--active">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                <span>Explore</span>
            </a>
            <a href="<?= url('/profile') ?>" class="sidebar__link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Profile</span>
            </a>
        </nav>
    </aside>

    <section class="feed">
        <h2 class="feed__title">Explorer</h2>
        <p class="feed__subtitle">Découvrez les publications populaires de la communauté</p>

        <div class="feed__posts" id="feed-container">
            <div class="feed__loading" id="feed-loading">
                <div class="skeleton-card"><div class="skeleton-card__header"><div class="skeleton skeleton--circle"></div><div class="skeleton-card__meta"><div class="skeleton skeleton--text skeleton--w60"></div><div class="skeleton skeleton--text skeleton--w40"></div></div></div><div class="skeleton skeleton--text skeleton--w100"></div><div class="skeleton skeleton--text skeleton--w80"></div></div>
            </div>
        </div>
        <div id="feed-sentinel" class="feed__sentinel"></div>
    </section>

    <aside class="sidebar sidebar--right">
        <div class="sidebar-widget">
            <h3 class="sidebar-widget__title">Trending</h3>
            <p class="sidebar-widget__empty">Fonctionnalité à venir</p>
        </div>
    </aside>
</div>

