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

## Conventions PROJET Parister (styling — PAS de la méthodo skill générique)
Ces choix sont **spécifiques au projet** ; la méthodo skill ne fixe que les *mécanismes*, pas ces valeurs.
- **Flèches de navigation** (carrousels/teasers) = **carré** (`border-radius` ~4px, PAS rond) translucide
  blanc `rgba(white,.6)` + chevron sombre, en **overlay latéral centré sur l'image** (prev gauche / next droite).
- **Boutons pause/play** des sliders = **masqués** (`_carousel.scss` : `.btn-arrow.btn-pause/.btn-play{display:none}`).
- **Mega-menu (ouvert)** : burger + label « Menu » en **or** (`[aria-expanded=true]`), bouton fermer du
  `mega-topbar` en or ; `.mega-body` sans padding haut/bas ; `.mega-aside-body` sans padding (le padding
  est porté par `.mega-aside-contact-wrap` autour des socials + adresse) ; spans de `.mega-cta` en or ;
  socials resserrés ; `.mega-forstyle` centré (flex column center).
- **Hover** : animations/transitions sur tout élément cliquable, dès le départ.
- **⚠️ Wrappers** : les bandes de page sont sous `#body-page`, mais certains **modules persistants**
  (newsletter `keep-form-module`) sont sous `#content-page.body-home-page`, **hors `#body-page`** → un
  override scopé `#body-page …` n'y matche pas. Vérifier l'ancêtre réel (sonde) avant de scoper ; au pire
  `!important` dans le composant. (Piège SCSS aussi : un bloc imbriqué qu'on veut sortir → `@at-root`.)
- **Séparateur nav** (Réserver / Bons cadeaux) = **5 tirets VERTICAUX** (h ~18px), pas une barre unique —
  rendu via `repeating-linear-gradient` ; couleur blanche au top (overlay hero) / sombre au scroll.
- **Checkbox** = **carré** (`border-radius:0`), fond **primary** + **coche blanche** à la sélection (idem newsletter).
- **Pas de `border-radius`** sur les cards (angles vifs — annuler le `.25rem` des styles par défaut).
- **Cards `home-spa-services`** = **entièrement cliquables** (toute la card en `<a>`), « Découvrir » blanc + filets.
- **Section workspaces** : tout le contenu en **blanc** (sur image sombre).
- **« Découvrir » des cards** = blanc + **filets haut/bas** (spa) ; chambres = filets haut/bas + hover sobre.

## État des bandes HOME — ✅ structure + couleurs + typo calées sur les tokens Figma
Reste à affiner si besoin : overlays au pixel (hero/getaway), cartes des sliders produits/spa
(titres en overlay bas d'image), rythme vertical fin entre bandes.

### Revue HOME du 2026-06-22 (mesure live ; GATE agrégé bruité par homonymes « Découvrir »)
Vérifié OK (mesure live) : couleurs de titre par section (chambres **teal**, spa **navy**, restaurant
**vert #00561b**, art **or**), CTA `.btn`/`.link` 14/700/.2em UPPER. Corrigés :
- **Scripts overlay** (héritaient sombre car une règle ID `#body-page p` bat la couleur de classe) :
  getaway « Parister » → **blanc 133px/400** ; workspaces « workspaces » → **blanc 416px/400** (était
  260/700/sombre) ; **script sous-titre cartes univers → or** via `#body-page .slider-container-home-universe
  .introduction { color:$primary }` (était sombre).
- **CTA carte univers** : 12px → **14px** (tous les « Découvrir » Figma = 14/700/2.8 ; aucun à 12px).
  NB : le LIEN « Découvrir » des cartes univers est **sombre #141414** = conforme maquette (pas l'or).
- **line-height CTA texte** : 21px → **17px** (token Figma).
- GATE : 8/77 → 17/77 conformes ; le reste = bruit d'appariement (48 non appariés, 42 « Découvrir »
  homonymes) + line-heights de scripts décoratifs (overlap voulu) → non bloquant.

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
- **En `lg`+, le mega-menu ouvert tient dans la fenêtre** (`100dvh`, pas de débordement). ✅ Vérifié
  par mesure live le 2026-06-22 : `scrollH == innerH`, `overflow=false`.

## Acquis transverses
- **Faker éliminé** (légendes média via `Media::setTitlePosition(null)`).

## Revue LAYOUT du 2026-06-22 (vérifiée par mesure live `getComputedStyle` vs tokens dev-mode Figma)
> ⚠️ La capture/mesure se fait en **HTTP** (`http://hotel-parister-2026.local/`) — cf. [[local-env-http-https]].
> Le GATE `verify-styles.mjs` agrégé est **bruité** sur la home (homonymes « Découvrir »/« Réserver »
> appariés au mauvais nœud) → privilégier la **mesure live ciblée** par sélecteur.

- **Bug 500 corrigé** : `WebsiteRepository::findDefault(bool $asObject)` retournait l'entité `Website`
  sous `if ($asObject)` mais déclarait `?WebsiteModel` → TypeError sur tout le site. Type de retour
  élargi en `Website|WebsiteModel|null`.
- **Boutons (charte)** : `$btn-font-size: rem(14px)` + `_button.scss` (700 / `.2em` / uppercase) →
  tous les `.btn` à **14/700/.2em UPPER** (relevé Figma). Racine, pas d'override.
- **Nav** : la typo Figma (42:1745 / 386:1793 / 501:3911) est la **même desktop ET mobile** → remontée
  en base de `_navigation.scss`, bloc `max-lg` réduit au mobile-spécifique. Barre fermée 14/700/3.5 ;
  état scrollé **bg #f4f0f1 + texte #141414** (et non blanc/navy) ; mega : titres **24/700**, liens
  14/400/3.5 UPPER, CTA 14/700/2.8, langues **16/700**, contact 12/700. Graisses posées en SCSS
  (l'utilitaire `fw-bold` est **purgé** car inutilisé → ne pas s'y fier ; `fw-600` retiré des templates).
  ⚠️ La note antérieure « mega-menu conforme » surestimait : le **desktop** était resté en petite typo.
- **Footer** : typo déjà conforme ; **menu passé en `text-lg-start`** (était centré, Figma = gauche).
- **Newsletter** : « Envoyer » repassé à **700** (le template posait `fw-600` !important) ; placeholder
  « votre e-mail » 14/700/ls0.
- **Socialwall** : `.socialwall-follow` 14/.2em ; ajout du **filigrane script « parister »** (`::before`,
  or .08, ~440px) absent jusque-là.

> TODO layout mineurs — TRAITÉS le 2026-06-22 :
> - **Switcher langues mega-menu** : langue active remise EN TÊTE (`.mega-lang li.active-locale { order:-1 }`)
>   — le composant la rendait en dernier (Figma : Français, English, Español, 中文). Pas de « Général »
>   (mauvais déchiffrage à basse rés.).
> - **Filigrane socialwall** : `top` 38%→32% (baseline ~ centre des tuiles, Figma 630:1531).
> - **Responsive footer/newsletter** : overflow horizontal mobile corrigé — l'email long du footer
>   (`bonjour@…`, insécable + `ls .4em`) débordait à ≤375px → `overflow-wrap:anywhere` + `flex-wrap` +
>   `ls .2em` en `max-md` sur `.footer-contact` ; `overflow:hidden` ajouté sur `.newsletter-form-container`.
>   Clean à 320/375/768.
> - **Scroll horizontal fantôme (≤375px)** : flèches de carrousels en marge négative (`.controls left:-114`)
>   + peeks débordaient le viewport. Fix global : `#body-page { overflow-x: clip }` (`_elements.scss`) —
>   coupe le hors-écran au bord du viewport, **garde le peek visible**, et `clip` (≠ `hidden`) ne crée pas
>   de conteneur de scroll donc ne casse ni `position:fixed` (nav) ni `sticky`. Clean à 320/375/768/1440.

## Revue HOME — GATE styles VERT (session intégration Layout + home)
> Boucle DoD complète : tokens relevés → SCSS → build → mesure live `getComputedStyle` → `verify-styles.mjs`.

- **GATE STYLES = OK (exit 0)** sur `https://hotel-parister-2026.local/` @1440 : 41/41 éléments FIABLES
  conformes (départ 27 écarts). Rapport : `integration/verify-styles.home.json`. Correctifs apportés :
  - **Hero** : kicker `boutique hôtel & spa` couleur **#f4f0f1** (`$beige`, pas blanc) + `line-height 46/38.4` ;
    CTA `réservez un séjour` (span enfant) forcé **#f4f0f1**.
  - **Sous-titres script de bande** (96px) : `line-height: calc(101/96)` (token lh101, ratio 1.05, serré).
  - **Cartes univers** : script `.introduction` 54px → `line-height: calc(101/54)` + marges négatives de
    compensation (stacking serré maquette) ; descriptif `.body p` scopé `#body-page` → **16/300/lh20**.
  - **Cartes services spa** (`slider-multi`/`.title.fw-600`) : titre `24px/700 !important` (bat `.fw-600`) ;
    script `chauffée/de sport/de nage` 34→**54px** + `line-height calc(101/54)` + compensation overlay.
  - **Événements** : titre teaser `derniers événements` → `ls 0 / line-height 1` (token 24/ls0/lh24) ;
    `Art &` (zone `home-art`, 32px/ls.4em) intact.
  - **Workspaces** : filigrane `workspaces` `line-height: calc(316/416)` (token).
  - **Newsletter** (`form/_newsletter.scss`) : `::placeholder` **14px/#b48608 plein/lh17** (héritait 12px/.7) ;
    label a11y `[for=front_newsletter_email]` aligné typo (clippé, 0 impact visuel) ; kicker `Rejoignez notre`
    lh38 ; titre script `newsletter` lh101 ; `Envoyer` lh17.
  - **Footer** (`layout/_footer.scss`) : note avis `4,7/5` lh31, `Excellent`/`1678 avis` lh17,
    `partenaire officiel 2026` lh14.
- **COMPOSITION validée par mesure directe des 12 zones** (`#zone-home-*`) : ordre `position-1`→`position-12`
  conforme maquette (hero → bandeau or **alerte** (section 2, sous le hero, confirmé maquette) → univers →
  getaway → chambres → slider chambres → passerelles → spa → services spa → workspaces → art → events) ;
  toutes pleine largeur ; full-bleed correct (hero/getaway/rooms-products/spa-services/workspaces).
  `verify-layout.mjs` exit 1 mais **0 échec full-bleed** : les "widthFails" sont du **bruit d'ancrage texte**
  (boîte d'un titre en `col-lg-6` vs bbox glyphes Figma) — limite documentée du gate, pas un défaut de compo.
- **Layout vérifié live** : nav haut **transparent + burger/logo blancs**, scrollé **bg #f4f0f1 + dark**
  (`#main-navigation.as-scroll`), mega-menu desktop **tient en 100dvh** (scrollH=innerH), mega-menu mobile
  `overflow-y:auto` **scroll interne** ; footer/newsletter typo gate-OK. **Aucun overflow horizontal** 375/768/1440.

### ✅ Carte chambre du teaser — CÂBLÉE (titre overlay + script + CTA clair)
- **Cause racine titre vide** : `ActionController::getTeaser` passe `disabledLayout => true` → `ViewModel.intlCard`
  vaut **null** ; or la macro `standard` fait `entity.intlCard is defined ? intlCard : entity.intl`, et en Twig
  `is defined` est **vrai pour une propriété readonly à null** → la macro prenait `intlCard = null` → titre vide.
  (Le média s'affichait car passé explicitement `post.mainMedia`.)
- **Solution** : carte dédiée `templates/front/default/actions/catalog/teaser/include/card-room.html.twig`
  qui lit `post.intl` explicitement et **splitte le nom** (1er mot → kicker UPPER, reste → script cursif,
  `|lower`), reproduisant la maquette (`CHAMBRE`+`supérieure`, `JUNIOR`+`suite terrasse`, `SUITE`+`parister`).
  Routée dans `slider-multi.html.twig` via `{% if teaser.slug == 'main' %}` (le teaser chambres) ; les autres
  catalog-teasers gardent la macro `standard`.
- **Styles** (`#zone-home-rooms-products`, `home.scss`) — tokens Figma : kicker **24/700/lh24/UPPER**,
  script **54/400/lh101** (`Parister Script`, marge négative = stacking serré), `Découvrir` **14/700/.2em/lh17/UPPER**
  + filet, tout en **#f4f0f1** (couleur posée directement : l'héritage perdait contre `#body-page span`), voile
  haut+bas pour la lisibilité, titre overlay HAUT centré / CTA overlay BAS centré, image 3:4.
- **Vérifié** : GATE styles **44/44 fiables (exit 0)** — les textes de carte (`supérieure`/`suite`/`deluxe`…)
  passent du bucket CONTENU (non rendus) à FIABLE conforme ; CONTENU non apparié 48→37. Capture conforme maquette.
