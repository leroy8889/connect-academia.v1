# 7. Design System & Identité Visuelle

## 7.1 Palette de Couleurs

| ID | Nom | Hex | RGB | Usage |
|---|---|---|---|---|
| 01 | Violet Principal | `#8B52FA` | 139 \| 82 \| 250 | CTA, boutons primaires, accents, liens actifs |
| 02 | Lavande Douce | `#F3EFFF` | 243 \| 239 \| 255 | Backgrounds secondaires, hover states, chips tags |
| 03 | Noir Charcoal | `#2D2D2D` | 45 \| 45 \| 45 | Texte principal, navbar, headers sombres |
| 04 | Blanc Pur | `#FFFFFF` | 255 \| 255 \| 255 | Background principal, cartes, surfaces |

## 7.2 Design Liquid Glass — Front-Office

Le style "Liquid Glass" (inspiré d'Apple iOS 26+) se caractérise par :

```css
/* Transparence et flou d'arrière-plan */
backdrop-filter: blur(20px) saturate(180%);

/* Surfaces semi-transparentes */
background: rgba(255, 255, 255, 0.15); /* à 0.7 selon contexte */

/* Bordures subtiles avec effet lumineux */
border: 1px solid rgba(255, 255, 255, 0.3);

/* Ombres douces et diffuses */
box-shadow: 0 8px 32px rgba(139, 82, 250, 0.12);

/* Transitions fluides */
transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

/* Gradient subtil en arrière-plan */
background: linear-gradient(135deg, #8B52FA15, #F3EFFF);
```

## 7.3 Typographie

| Élément | Police | Taille | Poids | Usage |
|---|---|---|---|---|
| Titre H1 | Inter / SF Pro | 2rem (32px) | 700 Bold | Titres de section principaux |
| Titre H2 | Inter / SF Pro | 1.5rem (24px) | 600 SemiBold | Sous-sections |
| Corps de texte | Inter / SF Pro | 1rem (16px) | 400 Regular | Contenu des posts, descriptions |
| Caption / Meta | Inter / SF Pro | 0.75rem (12px) | 400 Regular | Dates, compteurs, labels |
| Bouton | Inter / SF Pro | 0.875rem (14px) | 500 Medium | Labels de boutons |
| Back-office (titre) | Inter / DM Sans | 0.875rem | 300 Light | Titres dashboard (style Linear) |

## 7.4 Composants UI Clés

### Navbar (Front-Office)
- Barre fixe en haut, hauteur **60px**
- Logo StudyLink à gauche
- Icônes navigation (Home 🏠, Explore 🔍, Notifications 🔔, Profil 👤) centrées ou à droite
- Effet glass : `backdrop-filter` + fond semi-transparent
- Badge rouge sur l'icône notification si non lus

### Cartes de Publication (Post Cards)
```css
border-radius: 20px;
background: rgba(255, 255, 255, 0.8);
backdrop-filter: blur(16px);
box-shadow: 0 4px 24px rgba(139, 82, 250, 0.10);
padding: 20px;
/* Hover */
transform: translateY(-2px);
box-shadow: 0 8px 32px rgba(139, 82, 250, 0.18);
```

### Boutons
```css
/* Primaire */
background: #8B52FA;
color: white;
border-radius: 50px; /* pill */
padding: 12px 28px;

/* Secondaire */
background: transparent;
border: 2px solid #8B52FA;
color: #8B52FA;

/* Fantôme (ghost) */
background: rgba(139, 82, 250, 0.1);
color: #8B52FA;
```

## 7.5 Design Back-Office (Inspiré Attio/Linear)

- **Sidebar gauche** étroite (240px) avec menu minimaliste
- Typography fine (`font-weight: 300–400`) pour les labels
- Couleurs neutres dominantes (gris clair `#F8F9FA`, `#F1F3F5`)
- Accent violet `#8B52FA` utilisé avec parcimonie (seulement pour les KPI critiques et CTAs)
- Tables denses avec rows de **40px** de hauteur
- Badges de statut colorés (pills) : vert actif, orange en attente, rouge suspendu
- Hover rows avec background `#F3EFFF`
- Charts épurés, sans gridlines excessives

---
