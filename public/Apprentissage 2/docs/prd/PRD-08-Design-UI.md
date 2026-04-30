# 🎨 PRD-08 — Design & Identité Visuelle
## Connect'Academia — Charte Graphique et Composants UI

> **Référence** : PRD principal v1.0 — Sections 6, 3.2
> **Usage Cursor** : Référence design à consulter lors de la création de tout fichier CSS ou HTML.
> **Inspirations** : Coursera (espace élève) + Linear / Attio (back-office admin)

---

## 1. Palette de couleurs

| Rôle | Nom | Hex | Usage principal |
|---|---|---|---|
| **Primaire** | Violet | `#8B52FA` | Boutons CTA, liens actifs, accents, barres de progression |
| **Primaire clair** | Violet pâle | `#F3EFFF` | Fonds de cartes, hover states, badges |
| **Sombre** | Charcoal | `#2D2D2D` | Sidebar admin, textes foncés, sections hero |
| **Blanc** | Blanc pur | `#FFFFFF` | Fonds principaux, cartes, zones de contenu |
| **Texte** | Gris foncé | `#333333` | Corps de texte principal |
| **Sous-texte** | Gris moyen | `#6B7280` | Textes secondaires, labels |
| **Bordure** | Gris clair | `#E5E7EB` | Dividers, bordures de cartes |
| **Succès** | Vert | `#2DC653` | Confirmations, "Terminé" |
| **Alerte** | Orange | `#E85D04` | Avertissements |
| **Info** | Bleu | `#4A90D9` | Informations, série C |

---

## 2. Typographie

### Polices
- **Principale** : `Inter` (Google Fonts) — pour les corps de texte
- **Titres** : `Poppins` (Google Fonts) — pour les H1/H2
- **Fallback** : `system-ui, -apple-system, sans-serif`

```html
<!-- Dans le <head> de chaque page -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
```

### Échelle typographique

| Élément | Police | Taille | Poids | Couleur |
|---|---|---|---|---|
| H1 | Poppins | `36px` | 700 | `#2D2D2D` |
| H2 | Poppins | `26px` | 600 | `#2D2D2D` |
| H3 | Inter | `20px` | 600 | `#333333` |
| Corps | Inter | `15px` | 400 | `#333333` |
| Label | Inter | `13px` | 500 | `#6B7280` |
| Badge | Inter | `12px` | 600 | selon badge |

---

## 3. Composants UI — CSS

### 3.1 Boutons

```css
/* Bouton Primaire */
.btn-primary {
    background: #8B52FA;
    color: #FFFFFF;
    border: none;
    border-radius: 8px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
}
.btn-primary:hover { background: #7540E0; }
.btn-primary:active { transform: scale(0.98); }

/* Bouton Secondaire */
.btn-secondary {
    background: #F3EFFF;
    color: #8B52FA;
    border: 1px solid #8B52FA;
    border-radius: 8px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-secondary:hover { background: #E8DEFF; }

/* Bouton Danger */
.btn-danger {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FECACA;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 500;
}
.btn-danger:hover { background: #FEE2E2; }
```

### 3.2 Cartes Ressource (style Coursera)

```css
.resource-card {
    background: #FFFFFF;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}
.resource-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(139,82,250,0.15);
}

.resource-card__icon {
    width: 48px;
    height: 48px;
    background: #F3EFFF;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8B52FA;
}

.resource-card__title {
    font-size: 15px;
    font-weight: 600;
    color: #2D2D2D;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.resource-card__meta {
    font-size: 12px;
    color: #6B7280;
}

.resource-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
}
```

### 3.3 Badges

```css
.badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

/* Type de ressource */
.badge-cours          { background: #F3EFFF; color: #8B52FA; }
.badge-td             { background: #EFF6FF; color: #3B82F6; }
.badge-ancienne_epreuve { background: #FFF7ED; color: #E85D04; }

/* Série */
.badge-serie-A1 { background: #F3EFFF; color: #8B52FA; }
.badge-serie-A2 { background: #EDE9FE; color: #6C3FC9; }
.badge-serie-B  { background: #EFF6FF; color: #4A90D9; }
.badge-serie-C  { background: #FFF7ED; color: #E85D04; }
.badge-serie-D  { background: #ECFDF5; color: #2DC653; }

/* Statut progression */
.badge-en-cours { background: #FFF7ED; color: #D97706; }
.badge-termine  { background: #ECFDF5; color: #059669; }
```

### 3.4 Barre de progression

```css
.progress-bar-container {
    background: #F3EFFF;
    border-radius: 10px;
    height: 6px;
    overflow: hidden;
}
.progress-bar-fill {
    background: #8B52FA;
    height: 100%;
    border-radius: 10px;
    transition: width 0.5s ease;
}

/* Grande barre (page matière) */
.progress-bar-container.lg { height: 10px; }

/* Cercle de progression (page progression globale) */
.progress-circle {
    width: 120px;
    height: 120px;
    /* Implémentation via SVG ou CSS conic-gradient */
    background: conic-gradient(#8B52FA var(--pct), #F3EFFF 0);
    border-radius: 50%;
}
```

### 3.5 Sidebar Front-Office

```css
.sidebar {
    width: 240px;
    height: 100vh;
    background: #FFFFFF;
    border-right: 1px solid #E5E7EB;
    display: flex;
    flex-direction: column;
    padding: 20px 0;
    position: fixed;
    left: 0;
    top: 0;
    z-index: 100;
}

.sidebar__logo { padding: 0 20px 24px; border-bottom: 1px solid #E5E7EB; }

.sidebar__nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 20px;
    color: #6B7280;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.15s;
    border-radius: 8px;
    margin: 2px 8px;
}
.sidebar__nav-item:hover   { background: #F3EFFF; color: #8B52FA; }
.sidebar__nav-item.active  { background: #F3EFFF; color: #8B52FA; font-weight: 600; }

.sidebar__user {
    margin-top: auto;
    padding: 16px 20px;
    border-top: 1px solid #E5E7EB;
    display: flex;
    align-items: center;
    gap: 10px;
}
```

### 3.6 Sidebar Admin (fond sombre)

```css
.admin-sidebar {
    width: 240px;
    height: 100vh;
    background: #2D2D2D;
    display: flex;
    flex-direction: column;
    padding: 20px 0;
    position: fixed;
}

.admin-sidebar__nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 20px;
    color: #9CA3AF;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.15s;
    border-radius: 8px;
    margin: 2px 8px;
}
.admin-sidebar__nav-item:hover  { background: rgba(255,255,255,0.08); color: #FFFFFF; }
.admin-sidebar__nav-item.active { background: #8B52FA; color: #FFFFFF; font-weight: 600; }
```

### 3.7 Cartes KPI (Dashboard)

```css
.kpi-card {
    background: #FFFFFF;
    border-radius: 12px;
    border: 1px solid #E5E7EB;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.kpi-card__icon {
    width: 40px;
    height: 40px;
    background: #F3EFFF;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8B52FA;
}
.kpi-card__value { font-size: 28px; font-weight: 700; color: #2D2D2D; }
.kpi-card__label { font-size: 13px; color: #6B7280; }
```

---

## 4. Logo

- **Fichier** : `assets/img/logo.svg` (fourni : `Logo_1_CA_COMPLET.svg`)
- **Usage** : header front-office, page login, sidebar admin, favicon
- **Règles** :
  - Ne jamais déformer (respecter le ratio)
  - Ne jamais recolorer
  - Taille minimale : 32px de hauteur

```html
<!-- Usage standard -->
<img src="/assets/img/logo.svg" alt="Connect'Academia" height="36">

<!-- Version compacte sidebar admin -->
<img src="/assets/img/logo.svg" alt="Connect'Academia" height="28">
```

---

## 5. Icônes — Lucide Icons

Intégration via CDN :
```html
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>lucide.createIcons();</script>
```

Usage HTML :
```html
<i data-lucide="book"></i>
<i data-lucide="calculator"></i>
<i data-lucide="leaf"></i>
```

### Icônes utilisées par contexte

| Contexte | Icône Lucide |
|---|---|
| Dashboard | `layout-dashboard` |
| Matières | `book-open` |
| Favoris | `star` |
| Progression | `bar-chart-2` |
| Notifications | `bell` |
| Profil | `user` |
| Déconnexion | `log-out` |
| Cours | `book` |
| TD | `pencil` |
| Épreuve | `file-text` |
| Upload | `upload-cloud` |
| Recherche | `search` |
| Paramètres | `settings` |
| Timer | `timer` |
| Maths | `calculator` |
| SVT | `leaf` |
| Physique | `flask-conical` |
| Histoire-Géo | `map` |
| Économie | `trending-up` |
| Philosophie | `lightbulb` |
| Anglais | `globe` |
| Comptabilité | `receipt` |

---

## 6. Layout pages (structure HTML type)

### Template page élève connecté
```html
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Titre — Connect'Academia</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/front.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-layout">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <?php include 'includes/partials/sidebar.php'; ?>
        </aside>
        
        <!-- MAIN CONTENT -->
        <main class="main-content">
            <div class="page-container">
                <!-- Contenu de la page -->
            </div>
        </main>
    </div>
    
    <!-- Scripts -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/assets/js/main.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>
```

### CSS Layout principal
```css
.app-layout {
    display: flex;
    min-height: 100vh;
}
.main-content {
    flex: 1;
    margin-left: 240px; /* largeur sidebar */
    background: #F9FAFB;
    min-height: 100vh;
}
.page-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 32px 24px;
}

@media (max-width: 1024px) {
    .main-content { margin-left: 64px; }
}
@media (max-width: 768px) {
    .main-content { margin-left: 0; }
}
```

---

## 7. États & Interactions

### Empty states
```html
<!-- Aucun résultat / liste vide -->
<div class="empty-state">
    <i data-lucide="inbox" class="empty-state__icon"></i>
    <h3>Aucune ressource disponible</h3>
    <p>Les cours pour cette matière seront bientôt ajoutés.</p>
</div>
```

### Loading skeleton
```css
.skeleton {
    background: linear-gradient(90deg, #F0F0F0 25%, #E0E0E0 50%, #F0F0F0 75%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s infinite;
    border-radius: 8px;
}
@keyframes skeleton-loading {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
```

### Focus visible (accessibilité)
```css
*:focus-visible {
    outline: 2px solid #8B52FA;
    outline-offset: 2px;
}
```

---

*PRD-08 Design & Identité Visuelle — Connect'Academia v1.0*
