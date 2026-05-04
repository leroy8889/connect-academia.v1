# Connect'Academia — Design System

> Votre passerelle vers l'avenir.

Connect'Academia is a Gabonese all-in-one educational web application for students and pupils preparing for the **Bac** and choosing their academic path. It bundles three core surfaces:

1. **Apprentissage** — courses, revisions, and an educational AI tutor for Bac prep.
2. **Communauté** — an academic news feed where students post questions and get answers.
3. **Orientation** — discover your path and the school that fits you.

The product targets a young, mobile-first French-speaking audience in Gabon, and is delivered as a web app with a strong identity built around a single brand color — **Purple Neon (#8B52FA)** — rendered against a default Light Mode and an opt-in Dark Mode.

---

## Sources used to build this system

- **`uploads/charte-grapique.JPEG`** — The official charte graphique (4-color identity board: Purple Neon, Purple Tint, Charcoal, White).
- **`uploads/logo-officiel.png`** — Hi-res transparent PNG of the woven-rings logomark (6400×6400).
- **`uploads/logo1.jpeg`** — Vertical lockup: mark above wordmark with tagline "VOTRE PASSERELLE VERS L'AVENIR".
- **`uploads/logo2.jpeg`** — Horizontal lockup: mark left of wordmark, separated by a thin purple rule.
- **Style guide v1.0 (30 Dec 2025)** — Pasted style guide with full color/type/component rules, transcribed into `colors_and_type.css`.

There is no codebase or Figma file attached for this system; all decisions below come from the charte + style guide above.

---

## Index — what's in this folder

```
README.md                   ← you are here
SKILL.md                    ← portable skill manifest for Claude Code
colors_and_type.css         ← all design tokens (CSS vars, light + dark)

assets/
  logo-mark.png             ← transparent woven-rings mark
  logo-vertical.jpeg        ← stacked lockup with tagline
  logo-horizontal.jpeg      ← horizontal lockup with tagline
  charte-graphique.jpeg     ← original 4-color identity board

preview/                    ← design-system preview cards
  colors-*.html             ← brand, surface, text, semantic palettes
  type-*.html               ← scale, weights, specimen
  spacing-*.html            ← radii, spacing, shadow, glass
  components-*.html         ← buttons, inputs, cards, badges, nav, toasts
  brand-*.html              ← logo lockups, dark/light surfaces

ui_kits/
  webapp/                   ← Connect'Academia web app UI kit
    index.html              ← interactive click-thru prototype
    *.jsx                   ← modular React components
    README.md
```

---

## CONTENT FUNDAMENTALS

**Language.** All copy is in **French (fr-FR)**, with the local Gabonese student context in mind. Mix in English brand terms only where they're already part of the product DNA (e.g. "Bento Grid", "Dark Mode" in dev docs — never in user-facing UI).

**Voice.** Encouraging, peer-level, never condescending. The student is on a journey toward the Bac and beyond; the product is the **passerelle** (bridge / gateway) — that metaphor sits behind every line.

**Person.** Address the student as **"tu"** in the app (warm, peer, common in edtech for teens), and **"vous"** only in legal/formal surfaces (CGU, footer, error pages from the system).

**Casing.** Sentence case for almost everything — buttons ("Continuer", not "CONTINUER"), card titles ("Mes objectifs"), nav items ("Apprentissage", "Communauté", "Orientation"). UPPERCASE is reserved for the wordmark tagline lockup ("VOTRE PASSERELLE VERS L'AVENIR") and tiny eyebrow labels above sections (tracked +0.08em).

**Punctuation.** French typographic rules — non-breaking space before `: ; ! ?`, French quotes « » in long-form copy, straight quotes are acceptable inside the UI for density. Apostrophe in the brand name is always a curly **'** (Connect**'**Academia), never straight.

**Tone examples.**
- Onboarding hero: "Ta passerelle vers le Bac et au-delà."
- CTA primary: "Commencer", "Continuer", "Rejoindre", "S'inscrire"
- Success toast: "Compte créé. Bienvenue !"
- Error toast: "Mot de passe incorrect. Réessaie."
- Empty state: "Pas encore de question. Pose la première."
- Quiz feedback: "Bonne réponse !" / "Pas tout à fait — regarde l'explication."
- Section eyebrow: "ORIENTATION POST-BAC"

**Emoji.** Used sparingly and only as **content garnish** (e.g. inside a community post a student wrote, in a celebratory toast like "🎉 Compte créé"). Never as iconography in the chrome of the app — that role belongs to outline icons. One emoji at a time, never strings.

**Numbers.** French formatting — `12 345` (thin space), `4,5/20` (comma decimal). Time uses 24h with `h` separator: `14h30`.

---

## VISUAL FOUNDATIONS

**One color does the heavy lifting.** Purple Neon `#8B52FA` is the only chromatic accent. Every CTA, every active state, every focus ring, every chart bar — purple. Neutrals (white / charcoal `#2D2D2D` / black `#1A1A1A`) carry everything else. **Never** introduce a second hue for "variety" — semantic green/red/orange exist only for feedback, never decoration.

**Two modes, one identity.** Light Mode is the default. Dark Mode is a respected first-class mode. The brand purple stays identical in both modes; only neutrals flip. In Light Mode, surfaces lean on **stronger drop shadows** for separation; in Dark Mode, they lean on **lighter surface fills** (`#2D2D2D` on `#1A1A1A`).

**Type.** Single family: **Montserrat** (Google Font). Geometric, modern, very legible on mobile. The full weight ladder (300 Light → 700 Bold) is in active use:
- H1 28px / 700 Bold — page titles
- H2 20px / 600 SemiBold — Bento card titles
- Body 14–16px / 400 Regular — paragraphs, chatbot
- Button 16px / 500 Medium — buttons
- Caption 12px / 300 Light — dates, "Vu à 14h30"

**Backgrounds.** Almost always solid neutral. Full-bleed photography is **not** part of the system — the product is information-dense, not editorial. **No decorative gradients** anywhere except the brand mark itself (the woven-rings logo has soft 3D shading). The one subtle exception is a faint radial **purple-glow halo** behind the active CTA or a key card.

**Cards (Bento Grid).** The signature container.
- `border-radius: 24px` — never sharp corners
- `padding: 16–20px` internal
- `gap: 16px` between cards
- Light Mode: `#F5F5F7` fill + `--shadow-md`
- Dark Mode: `#2D2D2D` fill + 1px `rgba(255,255,255,0.08)` border, no drop shadow

**Glass card** (`.ca-glass-card`). For overlays and special-emphasis surfaces. Translucent fill, 20px backdrop-blur, soft 1px inner border, 24px radius. Critical: the blur **must** read against a noisy/colored background — never stack glass on flat white.

**Borders.** Default = none. Borders show up only in two places: (1) inside Dark Mode cards (`rgba(255,255,255,0.08)`) and (2) on Secondary buttons (purple outline). On focus, an input's purple border (1px) appears.

**Shadow system (Light Mode).** Three steps — `--shadow-sm` for resting chrome, `--shadow-md` for cards, `--shadow-lg` for modals/floating menus. Plus `--shadow-glow` (24px purple-tinted blur) reserved for the **active** primary CTA and "promoted" cards (e.g. today's quiz on the home Bento).

**Hover states.** Slight lift + slight purple tint. Buttons brighten (`#9D6EFB`); cards translate up 2px and gain `--shadow-glow`; nav items go from `--text-secondary` to `--text-primary` and surface a small purple dot. **Never** invert the whole card.

**Press states.** Color **darkens** (`#7440D9`), and the element **shrinks 2%** (`transform: scale(0.98)`) with a 120ms ease-out — gives that satisfying tap feedback critical on mobile.

**Animation.** Easing is `cubic-bezier(0.2, 0.8, 0.2, 1)` (gentle ease-out). Durations: 120ms for taps, 200ms for hover, 300ms for nav transitions, 400ms for modal/sheet entrances. **No bounces.** **No spring overshoot.** Movement is calm and educational, not playful.

**Corner radii.** `8 / 12 / 16 / 24 / 999`. The system gravitates toward **24px** — that's the signature. Pills (999) only on tag chips and small action buttons.

**Iconography.** Outline (not filled), 24×24, 1.5–2px stroke, rounded line caps. Lucide is the chosen library (closest match to the spec's example). Active state: the icon **fills with Purple Neon** OR swaps to the filled variant — pick one per surface and stay consistent.

**Imagery.** None of the system's atomic decisions assume photography. The brand asset *is* the woven-rings mark; anything else (avatars, course thumbnails) should be neutral, slightly desaturated, and never overpower the purple.

**Layout.** Mobile-first. Viewports clamp to a 16-column grid on desktop with 24px gutters; mobile drops to a 4-column grid. Bottom tab bar on mobile (3–5 tabs); persistent left rail on desktop. Top headers are sticky and translucent (glass) over scrolled content.

**Use of transparency / blur.** Reserved. Glass is for: (1) sticky headers over scrolled content, (2) modal scrims, (3) the AI chatbot panel surface. Never for resting cards in the Bento grid.

---

## ICONOGRAPHY

**Library:** [Lucide](https://lucide.dev) — the style guide explicitly names "Lucide React or Heroicons" as the reference. Loaded from CDN (`https://unpkg.com/lucide@latest`) so the system stays single-source and tree-shakeable in production. We standardize on Lucide for visual consistency; Heroicons is acceptable as a fallback on a per-surface basis but should not be mixed within the same screen.

**Style:** outline, 24×24, ~1.75px stroke, rounded caps & joins. This matches Lucide's defaults — no overrides needed.

**Active / selected state:** the icon either (a) fills with Purple Neon, or (b) swaps to the filled variant of the same glyph. Both are valid; pick one per surface (e.g. the bottom tab bar uses fill-swap; the side nav uses color-fill). The label below the icon goes `--text-primary` and bold simultaneously.

**Sizes.**
- 16×16 — inline with body text
- 20×20 — inside compact buttons
- 24×24 — navigation, default
- 32×32 — empty state hero icons (rare)

**Color rules.** Default: `currentColor` so icons inherit text color. Active: `var(--purple-neon)`. Disabled: `var(--text-muted)`. Never color icons with the semantic palette (success / error / warning) unless the icon is *literally* communicating that status (a check inside a success toast, an exclamation inside an error banner).

**Emoji as icons:** ❌ — emoji is content, not chrome. The only place emoji belongs in chrome is inside user-generated content (community posts, chat messages, profile descriptions).

**Unicode glyphs as icons:** ❌ — never. Use a real Lucide icon. The one exception is mathematical/special characters appearing inside *content* (a `÷` inside a math problem statement), not chrome.

**Custom SVGs.** The brand mark (woven rings) is the only custom SVG-class asset. It lives in `assets/logo-mark.png`. If a vector version is needed for crisp scaling under 64px, request it from the user — we should not redraw it.

---

## How to use this system

1. Drop `colors_and_type.css` into your project and import it once.
2. Apply `data-theme="light"` or `data-theme="dark"` to `<html>` (or omit and let `prefers-color-scheme` decide).
3. Use the CSS variables — never hardcode hex outside this file.
4. Pull components from `ui_kits/webapp/` as your starting point; they're already wired to the tokens.
5. Icons: load Lucide from CDN and use `<i data-lucide="book-open"></i>` then call `lucide.createIcons()`.

---

## Caveats & known substitutions

- **Montserrat** is loaded from Google Fonts CDN. No local `.ttf` was provided; if production needs offline fonts, drop them into `fonts/`.
- **Lucide** is used as the icon library to match the spec's "Lucide React or Heroicons" guidance. Loaded from CDN.
- The 4-color charte board includes a **Purple Tint `#F3EFFF`** (color 02) that the v1.0 style guide does not name. We've kept it in the token file as `--purple-tint` for use in promoted-card backgrounds and selection highlights — flag if you'd rather drop it.
