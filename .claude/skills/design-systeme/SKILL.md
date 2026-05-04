# Skill — Connect'Academia Design System

## Déclenchement

Utiliser ce skill quand l'utilisateur demande :
- "applique le design system"
- "améliore le design de cette page"
- "cette page ressemble à une page IA"
- "refactorise le front-office / back-office"
- "mets à jour le style de [composant]"
- `/design` ou `/ds`

---

## Ce que fait ce skill

Analyse la page ou le composant cible, puis applique le design system Connect'Academia complet :
tokens CSS, typographie Montserrat, palette Purple Neon, composants (Bento, boutons, inputs, badges), icônes Lucide, animations, et règles de voix.

---

## ÉTAPE 1 — Prérequis : importer les tokens

Le fichier de tokens est **`public/assets/css/colors_and_type.css`**. Il doit être importé une seule fois dans le `<head>` de chaque page ou dans le CSS global :

```html
<link rel="stylesheet" href="/assets/css/colors_and_type.css">
```

En PHP avec `BASE_URL` :

```php
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/colors_and_type.css">
```

---

## ÉTAT D'INTÉGRATION (à jour)

### Fichier tokens
`public/assets/css/colors_and_type.css` — présent, **pas encore importé** dans les layouts.

### Layouts existants et leur CSS actuel

| Layout | Fichier | Police actuelle | Statut migration |
|--------|---------|----------------|-----------------|
| `app/Views/layouts/main.php` | `global.css` | Inter + Poppins | ❌ non migré |
| `app/Views/layouts/admin.php` | `admin.css` | non définie | ❌ non migré |
| `app/Views/layouts/auth.php` | à vérifier | à vérifier | ❌ non migré |
| `app/Views/layouts/admin-auth.php` | à vérifier | à vérifier | ❌ non migré |

### Conflits de variables dans `global.css`

`global.css` définit ses propres tokens CSS avec des **noms différents** :

| global.css (ancien) | colors_and_type.css (DS) | Différence |
|--------------------|-----------------------------|------------|
| `--primary: #8B52FA` | `--purple-neon: #8B52FA` | nom différent |
| `--text-primary: #1A1A2E` | `--text-primary: #1A1A1A` | même nom, hex légèrement différent |
| `--text-secondary: #6B7280` | `--text-secondary: #4A4A4A` | même nom, hex différent |
| `--bg-primary: #FFFFFF` | `--bg-main: #FFFFFF` | nom différent |
| — | `--bg-surface: #F5F5F7` | absent dans global |

### Comment ajouter les tokens dans les layouts

Ajouter **après** `global.css` (pour que les tokens DS écrasent les conflits) :

Dans `main.php` :
```php
<link rel="stylesheet" href="<?= asset('css/global.css') ?>">
<link rel="stylesheet" href="<?= asset('css/colors_and_type.css') ?>">
```

Dans `admin.php` :
```php
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<link rel="stylesheet" href="<?= asset('css/colors_and_type.css') ?>">
```

Remplacer aussi **Inter + Poppins** par **Montserrat** dans `main.php` :
```html
<!-- Supprimer -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">

<!-- Ajouter à la place — colors_and_type.css l'importe déjà via @import -->
<!-- Donc supprimer la ligne Google Fonts du layout suffit si colors_and_type.css est chargé -->
```

> **Note :** `colors_and_type.css` contient déjà `@import url('https://fonts.googleapis.com/css2?family=Montserrat:...')`. Pas besoin de double import.

---

## ÉTAPE 2 — Tokens de référence

### Couleurs brand (jamais hardcoder les hex)

```css
var(--purple-neon)      /* #8B52FA — accent principal, CTA, focus */
var(--purple-hover)     /* #9D6EFB — hover bouton primary */
var(--purple-press)     /* #7440D9 — press / active */
var(--purple-tint)      /* #F3EFFF — fond léger purple, sélection */
var(--purple-glow-30)   /* rgba(139,82,250,0.30) */
var(--purple-glow-20)   /* rgba(139,82,250,0.20) */
```

### Couleurs sémantiques (feedback only, jamais déco)

```css
var(--success)  /* #00C853 */
var(--error)    /* #FF5252 */
var(--warning)  /* #FFAB00 */
```

### Surfaces & textes (s'adaptent au mode)

```css
var(--bg-main)          /* #FFFFFF  / #1A1A1A dark */
var(--bg-surface)       /* #F5F5F7  / #2D2D2D dark */
var(--bg-input)         /* #EEEEEE  / #121212 dark */

var(--text-primary)     /* #1A1A1A  / #FFFFFF dark */
var(--text-secondary)   /* #4A4A4A  / #E0E0E0 dark */
var(--text-muted)       /* #9E9E9E  / #A0A0A0 dark */
var(--text-on-purple)   /* #FFFFFF  toujours */
```

### Bordures

```css
var(--border-subtle)    /* rgba(26,26,26,0.08)  / rgba(255,255,255,0.08) dark */
var(--border-strong)    /* rgba(26,26,26,0.16)  / rgba(255,255,255,0.16) dark */
var(--border-focus)     /* var(--purple-neon) */
```

### Ombres

```css
var(--shadow-sm)    /* resting chrome */
var(--shadow-md)    /* cards */
var(--shadow-lg)    /* modals, menus flottants */
var(--shadow-glow)  /* CTA actif, carte promue — 0 8px 24px purple-glow-30 */
```

### Glass

```css
var(--glass-bg)      /* headers sticky, modals, panel IA */
var(--glass-border)
var(--glass-shadow)
```

### Radii

```css
var(--radius-sm)    /* 8px */
var(--radius-md)    /* 12px */
var(--radius-lg)    /* 16px */
var(--radius-xl)    /* 24px — signature, cartes Bento */
var(--radius-pill)  /* 999px — boutons, badges, chips */
```

### Espacement (grille 4pt)

```css
var(--space-1)  /* 4px */
var(--space-2)  /* 8px */
var(--space-3)  /* 12px */
var(--space-4)  /* 16px */
var(--space-5)  /* 20px */
var(--space-6)  /* 24px */
var(--space-8)  /* 32px */
var(--space-10) /* 40px */
var(--space-12) /* 48px */
var(--space-16) /* 64px */
```

### Typographie

```css
var(--font-sans)      /* 'Montserrat', system-ui */

var(--fs-h1)          /* 28px */
var(--fs-h2)          /* 20px */
var(--fs-h3)          /* 18px */
var(--fs-body-lg)     /* 16px */
var(--fs-body)        /* 14px */
var(--fs-button)      /* 16px */
var(--fs-caption)     /* 12px */

var(--fw-light)       /* 300 */
var(--fw-regular)     /* 400 */
var(--fw-medium)      /* 500 */
var(--fw-semibold)    /* 600 */
var(--fw-bold)        /* 700 */
var(--fw-black)       /* 800 */

var(--lh-tight)       /* 1.2  — titres */
var(--lh-snug)        /* 1.4  — sous-titres */
var(--lh-normal)      /* 1.55 — corps */
```

Classes utilitaires prêtes : `.ca-h1` `.ca-h2` `.ca-h3` `.ca-body` `.ca-body-lg` `.ca-button-text` `.ca-caption` `.ca-glass-card`

---

## ÉTAPE 3 — Composants

### Boutons

```css
/* Base commune */
.btn {
  font-family: var(--font-sans);
  font-weight: var(--fw-medium);
  font-size: var(--fs-button);
  letter-spacing: 0.01em;
  border: 0;
  cursor: pointer;
  border-radius: var(--radius-pill);
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  transition: all 200ms cubic-bezier(.2,.8,.2,1);
}

/* Primary — Purple plein + glow */
.btn-primary {
  padding: 14px 24px;
  background: var(--purple-neon);
  color: var(--text-on-purple);
  box-shadow: var(--shadow-glow);
}
.btn-primary:hover  { background: var(--purple-hover); }
.btn-primary:active { background: var(--purple-press); transform: scale(0.98); box-shadow: 0 4px 12px var(--purple-glow-20); }
.btn-primary:disabled { background: #C7B0F8; box-shadow: none; cursor: not-allowed; opacity: 0.7; }

/* Secondary — outline purple */
.btn-secondary {
  padding: 12.5px 22.5px;
  background: transparent;
  color: var(--purple-neon);
  border: 1.5px solid var(--purple-neon);
}
.btn-secondary:hover  { background: var(--purple-tint); }
.btn-secondary:active { transform: scale(0.98); }

/* Tertiary — texte seul */
.btn-tertiary {
  padding: 14px 8px;
  background: transparent;
  color: var(--purple-neon);
}
.btn-tertiary:hover { text-decoration: underline; }

/* Tailles */
.btn-sm { padding: 8px 14px; font-size: 13px; }
.btn-lg { padding: 18px 32px; font-size: 17px; }

/* Icône seul */
.btn-icon {
  width: 44px; height: 44px;
  padding: 0;
  justify-content: center;
}
```

### Inputs

```css
.ca-input {
  font-family: var(--font-sans);
  font-size: var(--fs-body);
  background: var(--bg-input);
  color: var(--text-primary);
  border: 1px solid transparent;
  border-radius: var(--radius-md);     /* 12px */
  padding: 14px 16px;
  width: 100%;
  outline: none;
  transition: all 150ms ease;
}
.ca-input::placeholder { color: var(--text-muted); }
.ca-input:focus {
  border-color: var(--purple-neon);
  box-shadow: 0 0 0 4px rgba(139,82,250,0.18);
}
.ca-input.error {
  border-color: var(--error);
  box-shadow: 0 0 0 4px rgba(255,82,82,0.15);
}

/* Input avec icône */
.ca-input-wrap { position: relative; }
.ca-input-wrap .ca-input { padding-left: 44px; }
.ca-input-wrap .ca-input-icon {
  position: absolute; left: 14px; top: 50%;
  transform: translateY(-50%);
  width: 18px; height: 18px;
  color: var(--text-muted);
  pointer-events: none;
}
.ca-input-wrap:focus-within .ca-input-icon { color: var(--purple-neon); }

/* Label */
.ca-label {
  font-family: var(--font-sans);
  font-size: 12px;
  font-weight: var(--fw-semibold);
  color: var(--text-primary);
  margin-bottom: var(--space-2);
  display: block;
}

/* Help / error text */
.ca-help  { font-size: 11px; color: var(--text-muted); margin-top: var(--space-1); display: block; }
.ca-error { font-size: 11px; color: var(--error); margin-top: var(--space-1); display: flex; align-items: center; gap: 6px; }
```

### Bento Cards

```css
/* Grille Bento */
.ca-bento {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  grid-auto-rows: minmax(120px, auto);
  gap: var(--space-4);          /* 16px */
}
@media (max-width: 768px) {
  .ca-bento { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
  .ca-bento { grid-template-columns: 1fr; }
}

/* Carte standard */
.ca-card {
  border-radius: var(--radius-xl);    /* 24px */
  padding: var(--space-5);            /* 20px */
  background: var(--bg-surface);
  box-shadow: var(--shadow-md);
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  transition: transform 200ms cubic-bezier(.2,.8,.2,1),
              box-shadow 200ms cubic-bezier(.2,.8,.2,1);
}
.ca-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-glow);
}

/* Carte promue — Purple plein */
.ca-card-promoted {
  background: var(--purple-neon);
  color: var(--text-on-purple);
  box-shadow: 0 16px 40px rgba(139,82,250,0.35);
  position: relative;
  overflow: hidden;
}
.ca-card-promoted::before {
  content: "";
  position: absolute; right: -30px; bottom: -40px;
  width: 200px; height: 200px;
  background: radial-gradient(circle, rgba(255,255,255,0.18), transparent 65%);
  border-radius: 50%;
}

/* Card Dark Mode — override automatique via tokens */
[data-theme="dark"] .ca-card {
  background: var(--bg-surface);
  border: 1px solid var(--border-subtle);
  box-shadow: none;
}

/* Spans */
.span-2 { grid-column: span 2; }
.span-3 { grid-column: span 3; }
.span-4 { grid-column: span 4; }
.row-2  { grid-row: span 2; }
```

### Badges & Chips

```css
/* Badge */
.ca-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: var(--radius-pill);
  font-family: var(--font-sans);
  font-size: var(--fs-caption);
  font-weight: var(--fw-semibold);
}
.ca-badge-brand       { background: var(--purple-neon); color: #fff; }
.ca-badge-brand-soft  { background: var(--purple-tint); color: var(--purple-press); }
.ca-badge-outline     { background: transparent; color: var(--purple-neon); border: 1px solid var(--purple-neon); }
.ca-badge-success     { background: rgba(0,200,83,0.12); color: #007A33; }
.ca-badge-error       { background: rgba(255,82,82,0.12); color: #B23535; }
.ca-badge-warning     { background: rgba(255,171,0,0.16); color: #8A5C00; }
.ca-badge-muted       { background: var(--bg-input); color: var(--text-secondary); }

/* Badge notif (count) */
.ca-badge-notif {
  min-width: 18px; height: 18px;
  padding: 0 5px;
  border-radius: var(--radius-pill);
  background: var(--error);
  color: #fff;
  font-size: 10px;
  font-weight: var(--fw-semibold);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

/* Chip filtre */
.ca-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: var(--radius-pill);
  font-family: var(--font-sans);
  font-size: 13px;
  font-weight: var(--fw-medium);
  background: var(--bg-main);
  border: 1px solid var(--border-subtle);
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 150ms ease;
}
.ca-chip.active,
.ca-chip:hover { background: var(--purple-neon); color: #fff; border-color: var(--purple-neon); }
```

### Avatar

```css
.ca-avatar {
  width: 40px; height: 40px;
  border-radius: var(--radius-pill);
  background: linear-gradient(135deg, var(--purple-neon), var(--purple-press));
  display: grid; place-items: center;
  color: #fff;
  font-family: var(--font-sans);
  font-weight: var(--fw-bold);
  font-size: 14px;
  border: 2px solid var(--bg-surface);
  flex-shrink: 0;
}
.ca-avatar-stack { display: flex; }
.ca-avatar-stack .ca-avatar { margin-left: -10px; }
.ca-avatar-stack .ca-avatar:first-child { margin-left: 0; }

/* Indicateur en ligne */
.ca-avatar-status {
  position: relative;
}
.ca-avatar-status::after {
  content: "";
  position: absolute; right: -1px; bottom: -1px;
  width: 12px; height: 12px;
  border-radius: var(--radius-pill);
  border: 2px solid var(--bg-surface);
  background: var(--success);
}
```

### Icon container (cap)

```css
.ca-icon-cap {
  width: 36px; height: 36px;
  border-radius: var(--radius-md);
  background: var(--purple-tint);
  display: grid; place-items: center;
  color: var(--purple-neon);
  flex-shrink: 0;
}
.ca-card-promoted .ca-icon-cap {
  background: rgba(255,255,255,0.18);
  color: #fff;
}
```

### Progress bar

```css
.ca-progress {
  height: 6px;
  background: var(--border-subtle);
  border-radius: var(--radius-pill);
  overflow: hidden;
}
.ca-progress-fill {
  height: 100%;
  background: var(--purple-neon);
  border-radius: var(--radius-pill);
  transition: width 300ms cubic-bezier(.2,.8,.2,1);
}
.ca-card-promoted .ca-progress { background: rgba(255,255,255,0.25); }
.ca-card-promoted .ca-progress-fill { background: #fff; }
```

### Eyebrow label

```css
.ca-eyebrow {
  font-family: var(--font-sans);
  font-size: 10px;
  font-weight: var(--fw-bold);
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--text-muted);
}
.ca-card-promoted .ca-eyebrow { color: rgba(255,255,255,0.80); }
```

### Glass header / sticky nav

```html
<header style="
  position: sticky; top: 0; z-index: 100;
  background: var(--glass-bg);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--glass-border);
  box-shadow: var(--glass-shadow);
">
```

### Toast / notification

```css
.ca-toast {
  display: inline-flex;
  align-items: center;
  gap: var(--space-3);
  padding: 12px 16px;
  border-radius: var(--radius-lg);
  background: var(--bg-surface);
  box-shadow: var(--shadow-lg);
  font-family: var(--font-sans);
  font-size: var(--fs-body);
  color: var(--text-primary);
  max-width: 340px;
}
.ca-toast-success { border-left: 3px solid var(--success); }
.ca-toast-error   { border-left: 3px solid var(--error); }
.ca-toast-warning { border-left: 3px solid var(--warning); }
```

---

## ÉTAPE 4 — Icônes Lucide

Charger depuis CDN (une seule fois, avant `</body>`) :

```html
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>lucide.createIcons();</script>
```

Utilisation :

```html
<!-- Outline, 24×24 par défaut -->
<i data-lucide="book-open"></i>

<!-- Taille custom -->
<i data-lucide="search" style="width:20px; height:20px;"></i>

<!-- Couleur active purple -->
<i data-lucide="home" style="color: var(--purple-neon); fill: var(--purple-neon);"></i>
```

Règles :
- Style : **outline**, stroke ~1.75px (défaut Lucide), caps arrondis
- Tailles : 16px inline · 20px bouton compact · 24px nav (défaut) · 32px empty state
- Couleur par défaut : `currentColor` (hérite du texte)
- État actif : `color: var(--purple-neon)` OU variante filled — cohérent par surface
- Désactivé : `color: var(--text-muted)`
- **Jamais** d'emoji comme icône dans le chrome de l'app

---

## ÉTAPE 5 — Mode sombre

Appliquer `data-theme` sur `<html>` :

```html
<html lang="fr" data-theme="light">
<!-- ou data-theme="dark" -->
<!-- ou rien → suit prefers-color-scheme -->
```

Toggle JS minimal :

```js
function toggleTheme() {
  const html = document.documentElement;
  html.dataset.theme = html.dataset.theme === 'dark' ? 'light' : 'dark';
  localStorage.setItem('ca-theme', html.dataset.theme);
}

// Appliquer au chargement
(function() {
  const saved = localStorage.getItem('ca-theme');
  if (saved) document.documentElement.dataset.theme = saved;
})();
```

---

## ÉTAPE 6 — Animations

```css
/* Easing unique — doux, éducatif, pas de rebond */
--ease-out: cubic-bezier(0.2, 0.8, 0.2, 1);

/* Durées */
/* 120ms — tap/press feedback */
/* 200ms — hover */
/* 300ms — transitions nav */
/* 400ms — modal / sheet entrance */
```

Pattern modal entrance :

```css
@keyframes ca-slide-up {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}
.ca-modal {
  animation: ca-slide-up 400ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
}
```

---

## ÉTAPE 7 — Layout

**Mobile-first.** Grille 4 colonnes desktop, 2 colonnes tablette, 1 colonne mobile.

```css
.ca-layout {
  display: grid;
  grid-template-columns: 240px 1fr;  /* rail gauche + contenu */
  min-height: 100vh;
}
@media (max-width: 768px) {
  .ca-layout { grid-template-columns: 1fr; }
  /* Bottom tab bar sur mobile */
}
```

Gutters : `24px` desktop · `16px` mobile.

---

## ÉTAPE 8 — Voix & contenu

- Langue : **français** exclusivement dans l'UI
- Adresse : **"tu"** dans l'app, **"vous"** uniquement pages légales
- Casse : Sentence case partout. UPPERCASE réservé aux eyebrow labels et tagline logo
- CTA examples : `Continuer` · `Commencer` · `Rejoindre` · `S'inscrire` · `Valider`
- Toast succès : `"Compte créé. Bienvenue !"`
- Toast erreur : `"Mot de passe incorrect. Réessaie."`
- État vide : `"Pas encore de question. Pose la première."`
- Apostrophe brand : toujours `Connect'Academia` (apostrophe courbe `'`, jamais droite)
- Ponctuation française : espace insécable avant `: ; ! ?`

---

## ÉTAPE 9 — Anti-patterns (JAMAIS faire)

| ❌ Interdit | ✅ Correct |
|------------|-----------|
| Hardcoder `#8B52FA` ou autre hex | Utiliser `var(--purple-neon)` |
| Ajouter une 2e couleur chromatique | Purple Neon uniquement comme accent |
| Dégradé décoratif sur un fond | Dégradé réservé au logo mark |
| Coins anguleux (`border-radius: 0`) | Minimum `var(--radius-sm)` = 8px |
| `box-shadow` en Dark Mode sur les cartes | Utiliser `border: 1px solid var(--border-subtle)` |
| Emoji comme icône dans le chrome | Utiliser Lucide |
| Inversion totale de carte au hover | `translateY(-2px)` + `--shadow-glow` seulement |
| Rebond / spring dans les animations | `cubic-bezier(0.2, 0.8, 0.2, 1)` |
| Photographie plein écran | Pas de photo décorative — info-dense |
| Police autre que Montserrat | Montserrat uniquement |
| `font-weight: 400` sur les boutons | Toujours `500` (Medium) |
| Semantic colors (vert/rouge) pour décoration | Feedback uniquement |
| Backdrop-filter sur fond plat blanc | Glass seulement sur fond coloré/photo |

---

## ÉTAPE 10 — Processus d'application sur une page existante

1. **Lire** la page cible (HTML/PHP + CSS associé)
2. **Vérifier** que `colors_and_type.css` est importé (sinon l'ajouter)
3. **Vérifier** que Lucide est chargé (sinon l'ajouter avant `</body>`)
4. **Identifier** les éléments à migrer : titres, boutons, inputs, cartes, badges, nav
5. **Remplacer** les classes/styles inline par les patterns du design system :
   - Titres → `.ca-h1/.ca-h2/.ca-h3`
   - Boutons → `.btn.btn-primary / .btn-secondary / .btn-tertiary`
   - Inputs → `.ca-input` avec `.ca-label` et `.ca-help`
   - Cartes → `.ca-card` dans `.ca-bento`
   - Badges → `.ca-badge-*`
   - Chips → `.ca-chip`
   - Icônes → `<i data-lucide="..."></i>`
6. **Supprimer** les couleurs hardcodées, polices alternatives, `border-radius: 0`
7. **Tester** light mode ET dark mode en ajoutant `data-theme="dark"` sur `<html>`
8. **Vérifier** sur mobile (viewport 375px) — bottom tab bar si nav présente

---

## Référence rapide — classes utilitaires

```
.ca-h1 .ca-h2 .ca-h3           Titres Montserrat
.ca-body .ca-body-lg            Corps de texte
.ca-caption                     Caption 12px light
.ca-eyebrow                     Label uppercase tracké
.ca-button-text                 Texte bouton medium 16px
.ca-glass-card                  Surface glass (header, modal, IA panel)

.btn .btn-primary               Bouton primaire purple
.btn .btn-secondary             Bouton outline purple
.btn .btn-tertiary              Bouton texte
.btn-sm .btn-lg                 Tailles
.btn-icon                       Bouton icône carré 44px

.ca-input                       Champ texte
.ca-label .ca-help .ca-error    Composants du champ
.ca-input-wrap .ca-input-icon   Champ avec icône

.ca-card                        Carte Bento standard
.ca-card-promoted               Carte purple promue
.ca-bento                       Grille Bento
.span-2 .span-3 .row-2          Spans de grille

.ca-badge-brand                 Badge purple
.ca-badge-brand-soft            Badge purple tint
.ca-badge-outline               Badge outline
.ca-badge-success/error/warning Badges sémantiques
.ca-badge-muted                 Badge gris
.ca-badge-notif                 Compteur rond

.ca-chip .ca-chip.active        Filtre chip

.ca-avatar .ca-avatar-stack     Avatar et pile
.ca-avatar-status               Avatar avec indicateur en ligne

.ca-icon-cap                    Conteneur d'icône arrondi
.ca-progress .ca-progress-fill  Barre de progression
.ca-toast                       Notification
```
