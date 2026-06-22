# Projet Parister — mémoire d'intégration Figma → CMS

> Md **projet** (spécifique). Le playbook générique reste dans
> `.claude/skills/figma-cms/integration-prompts.md`.

## Sources
- **Figma file key** : `VxyHdf12DFWhx0I6SxX9ce`
- **Prod** : https://www.hotelparister.com
- **Local** : https://hotel-parister-2026.local/ (+ `en.` / `es.` / `cn.` par locale)
- **DB** : `hotel_parister_2026`

## Nodes Figma de référence
| Élément | node-id |
|---|---|
| Page Home `[page|home]` | `542:1592` |
| Nav (mega-menu) `[nav]` | `386:1793` |
| Footer | `516:2344` |
| Page produit `[page|product-view]` | `516:2245` |
| Sticky menu (barre fermée) | `42:1745` |

## Médias extraits de la maquette (dans `public/medias/`)
| Fichier | node-id | usage |
|---|---|---|
| `facade-parister.jpg` | 516:2360 / 386:1840 | photo façade (footer + mega-menu) |
| `forstyle-logo.png` (or) | 386:1845 | logo Forstyle sur fond clair (mega-menu) |
| `forstyle-logo-white.png` | 516:2365 | logo Forstyle blanc (footer or) |
| `paris-jetaime.png` | 516:2398 | logo « Paris je t'aime » (footer) |
| `footer-logo-white.png` | 516:2407 | lockup « PARISTER / HÔTEL » (footer) |
| `insta-1..3.jpg` | 542:1661/1662/1663 | socialwall |
| dans `media/home/room-1..7.jpg` | 542:1602..1608 | images des 7 chambres (produits) |

## Boutons OUTLINE = style « 2 filets »
- **Tous les boutons `btn-outline-*`, quelle que soit la couleur**, n'ont PAS de bordure pleine ni de
  fond au hover : **deux filets** (haut + bas, couleur du bouton) raccourcis de **40px de chaque côté**
  au repos, qui passent à **100%** au survol. Géré globalement dans `components/_button.scss`.

## Police script — fallback à revoir
- ⚠️ Le **fallback de `Parister Script`** (cursive générique / Museo) **ne ressemble pas** à la vraie
  police script de la maquette. → s'assurer que **la vraie police `Parister Script` est bien chargée
  en local** (`@font-face`, woff2 dans `assets/lib/fonts/…`) et, à défaut, choisir un **fallback cursif
  beaucoup plus proche** (script élégant) plutôt qu'une cursive système générique.

## Couleurs / polices
- `$primary` **#b48608** (or signature) · `$secondary`/`$navy` **#001e56** · `$teal` **#8fb3b1** ·
  `$beige` **#f4f0f1** · `$dark` #141414
- `$font-primary` = Museo Sans · `$font-script` = Parister Script (titres cursifs)

## Registre `customId` (Layout) — à compléter au fil de la génération
> Convention : anglais + tirets `<page>-<section>[-<element>]`. Permet de cibler chaque élément un
> par un. **Rendu front** : le `customId` sort en `id="zone-<customId>"` (préfixe `zone-`) sur les
> zones — ex. `setCustomId('home-hero')` → `<section id="zone-home-hero">`.

Une `[section]` Figma = **une zone** (1:1). Les 12 zones home (ordre maquette 542:1592) :

| # | customId | Élément (zone home) |
|---|---|---|
| 1 | `home-alert` | Bandeau alerte (bloc `alert`, fond or) |
| 2 | `home-hero` | Hero plein écran (slider, h1 « Boutique hôtel & spa / Parister ») |
| 3 | `home-universe` | Cartes univers (séjourner / boire / détendre) |
| 4 | `home-getaway` | Bande image plein écran (overlay script + kicker) |
| 5 | `home-rooms` | Chambres (navy) : intro « Votre parenthèse / parisienne » (texte teal) |
| 6 | `home-rooms-products` | **Slider produits chambres** (section dédiée, navy) |
| 7 | `home-restaurant` | Les passerelles (clair, texte vert `$success`) + grille 3 images |
| 8 | `home-spa` | Spa, bien-être & sport (teal, texte navy) |
| 9 | `home-spa-services` | **Slider 4 services spa** (section dédiée, teal) |
| 10 | `home-workspaces` | Workspaces (image pleine, overlay) |
| 11 | `home-art` | Art & rencontres (clair, texte or) |
| 12 | `home-events` | Teaser actualités « Derniers événements » (or) |

## Tokens maquette (relevés dev mode Figma 542:1592)
- **Couleurs** : page `#f4f0f1` ($light) · or `#b48608` ($primary) · navy `#001e56` · teal `#8fb3b1` ·
  vert restaurant `#00561b` ($success) · texte sombre `#141414` ($dark) · cartes produits `#402624`.
- **Couleur de texte par section** : chambres→**teal**, spa→**navy**, passerelles→**vert**, art→**or**.
- **Typo** : titre de bande 32px/700/0.4em UPPER ; titre de carte 24px/700/ls0 UPPER ; script (sous-titre)
  weight 400 (54px cartes / 96px bandes) ; body **16px/300** ; CTA 14px/700/0.2em UPPER.

## État des bandes HOME — ✅ structure + couleurs + typo calées sur les tokens Figma
Reste à affiner si besoin : overlays au pixel (hero/getaway), cartes des sliders produits/spa
(titres en overlay bas d'image), rythme vertical fin entre bandes.

## TODO contenu (présent en PROD, manquant/à compléter en DB)
- **Actualités** : 25 fiches réelles intégrées (titres + slugs prod, cf. `NewscastFixtures::EVENTS`).
  ⏳ **Image principale de chaque actu** = à récupérer dans le bloc **`.block_entete`** de la fiche prod
  (`https://www.hotelparister.com/<slug>`) → télécharger + rattacher en `mainMedia` du newscast.
- **FAQ** : la prod a une FAQ → **créer `src/Service/DataFixtures/FaqFixtures.php`** (module `faq`,
  `Faq` + `Question`/`QuestionIntl`), câbler via le locator (`$this->fixtures->faq()->add(...)` →
  ajouter l'accessor `faq()` au `DataFixturesInterface`/`DataFixturesLocator`) et appeler dans
  `WebsiteFixtures::initialize()` (gardé par `ROLE_FAQ`). ⚠️ URL/page FAQ prod à localiser (absente de
  `/faq` et de la home SSR — probablement rendue en JS ou sur une page dédiée).
- Vérifier les autres types (avis/témoignages, etc.) présents en prod.

## TODO labels
- Reste des « En savoir + » par défaut sur certaines cartes teaser/templates → remplacer par le libellé maquette.

## Comportements UI (spécifiques projet)
- **Menu principal (barre fermée)** : toujours en **`position: fixed`** en haut de page.
  - **En haut de page (scroll = 0)** : barre **transparente** (overlay sur le hero, logo/liens en blanc).
  - **Au scroll** : la barre **devient blanche** (fond blanc, texte/logo en couleur de charte).
  - Transition douce entre les deux états ; gérer l'état via une classe ajoutée au scroll
    (ex. `is-scrolled` / `scrolled`) sur le conteneur du menu, pas en JS inline.
  - **En haut de page (avant scroll)** : **logo ET burger en BLANC** (overlay sur le hero). Ils ne
    passent en couleur de charte (navy) **qu'au scroll** ou **quand le menu est ouvert**.
  - **Logo de la nav principale = parfaitement centré dans la fenêtre** (centré sur la largeur du
    viewport, pas seulement dans sa colonne). Les groupes gauche (burger/Menu) et droite
    (Réserver/Bons cadeaux) doivent être de **largeurs égales** pour que le logo reste au centre
    optique de l'écran quel que soit leur contenu.

## Mega-menu (sous-nav) — visibilité
- **En `lg`+, quand le mega-menu est ouvert, tout son contenu doit tenir dans la fenêtre** (hauteur
  `100dvh`, pas de débordement). Actuellement il est **coupé en bas** → on doit scroller pour voir la
  fin de la sous-nav. À corriger : contraindre la hauteur de l'overlay à la fenêtre et répartir/condenser
  les colonnes (ou `overflow` interne maîtrisé) pour que **réservation/contact/partenaires** restent visibles
  sans scroll.

## Acquis transverses
- **Faker éliminé** (légendes média via `Media::setTitlePosition(null)`).
- **Footer** conforme maquette (logo lockup, socials/note, menu, photo, partenaires, barre légale).
- **Mega-menu** conforme (2 panneaux, tout en or, scroll interne).
