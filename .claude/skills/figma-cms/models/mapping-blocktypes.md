Normale# Mapping convention Figma → mécanique CMS (BlockType / Modules)

> Référence d'intégration. Slugs vérifiés en base (`cms_layout_block_type`,
> `cms_layout_action`) et mécanisme prouvé par `App\Service\DataFixtures\PageFixtures`.
> La convention de nommage reste pilotée par https://figma-doc.agence-felix.fr/
> (à re-consulter, elle évolue — cf. `integration-prompts.md`).

## Principe fondamental : deux mécanismes de rendu

**A. Blocs atomiques** → un `BlockType` désigné par son **slug**.
Un Block référence son type par slug : `addBlock($col, 'title')`.

**B. Modules métier** → bloc générique **`core-action`** + une **Action** (`*-view`)
+ une **entité module** instanciée et référencée par son id.
Patron canonique : `PageFixtures::addHomeLayout()` (le carrousel home).

```php
// Module : on crée l'entité, puis on la pose via core-action + l'action
$slider = new Slider(); /* + SliderMediaRelation (slides) */
$em->persist($slider);
$block = $this->addBlock($col, 'core-action', 'slider-view', $slider->getId());
```

On ne **crée jamais de BlockType** à l'intégration : tous existent déjà. Pour un
module, l'objet créé est l'**entité module** (Slider, Form, etc.), pas un BlockType.

## Structurel (entités, pas des BlockType)

| Préfixe | Entité | Note |
|---|---|---|
| `[page]` | `Layout\Page` (+ `Layout\Layout`) | |
| `[zone]` | `Layout\Zone` | `setFullSize()`, paddings |
| `[section]` / `[zone\|section]` | `Layout\Zone` | **= une ZONE** rendue en `<section>` (champ `semantic: "section"` dans le JSON). 1 calque `[section]` = 1 zone (jamais un bloc/colonne). |
| `[section\|N]` | `Layout\Zone` | **Ordre forcé** : le numéro fixe la position de la zone (`[section\|1]`, `[section\|2]`…). Sans numéro → ordre d'apparition dans Figma. Implémenté : `PageParser::zonePosition()` + tri stable. |
| `[col]` | `Layout\Col` | `setSize()` = colonnes Bootstrap (1-12). **Alignement = propriétés d'entité, JAMAIS du CSS sur la colonne** : contenu/texte **centré verticalement** → `Col::setVerticalAlign(true)` ; contenu **aligné en fin** (bas/droite selon le contexte) → `Col::setEndAlign(true)`. (hérités de `BaseConfiguration`, défaut `false`). |

> **Alignement d'une colonne (`Col`) — règle impérative** : quand une colonne (ou le texte qu'elle
> contient) est **centrée verticalement**, **ne pas écrire de CSS** sur la colonne → poser
> `Col::setVerticalAlign(true)`. De même pour un contenu **aligné en fin** → `Col::setEndAlign(true)`.
> En Figma, ces cas correspondent à un auto-layout vertical dont l'alignement principal est
> `CENTER` (→ verticalAlign) ou `MAX`/fin (→ endAlign). Ne pas confondre avec `Zone::colToRight` /
> `Zone::colToEnd` (qui poussent les colonnes dans la ZONE) : ici c'est l'alignement DU CONTENU
> **dans** la colonne.

## Marges & paddings — ÉCHELLE SÉMANTIQUE (jamais de px en dur)

Les espacements (Zone / Col / Block) **ne sont JAMAIS des px CSS** : ce sont des **niveaux** d'une
échelle responsive, posés par les **propriétés d'entité** (`MarginType` : `marginTop/Right/Bottom/Left`
+ `paddingTop/Right/Bottom/Left`, **chacune × 4 écrans** — base / `…MiniPc` / `…Tablet` / `…Mobile`).

- **Valeur = token de classe** : `{m|p}{t|b|s|e}-{niveau}` avec niveau ∈ `0, xs, sm, md, lg, xl, xxl`.
  - `m`=margin, `p`=padding ; `t`=top, `b`=bottom, `s`=start/left, `e`=end/right (logique Bootstrap).
  - Marges **négatives** (débordement/chevauchement voulu) : suffixe `-neg` (ex. `mt-lg-neg`). Padding : pas de négatif.
  - Ex. fixtures : `setMarginBottom('mb-md')`, `setPaddingRight('pe-0')`, `setPaddingTop('pt-0')`.
- **Le px derrière un niveau est RESPONSIVE et dépend de l'AXE** — défini une fois dans
  `assets/scss/front/default/variables.scss` (`$margins`, `$marginsScreens`) + `utilities/_mixin-margin*.scss` :
  - **x ≠ y** pour un même niveau (ex. au plus grand écran : `md` x≈120px, `md` y≈90px) ;
  - la valeur **change par breakpoint** (ex. `lg` y : 120px xxl, 90px lg, 45px md, 30px sm, 1rem xs) ;
  - `0` et `sm` x s'appuient sur les **gouttières** (`--bs-gutter-*-margin`).
- **Conséquence intégration** : relever l'espacement en dev mode Figma puis choisir le **NIVEAU dont le
  px (au breakpoint + axe visés) approche la maquette** — ne pas écrire `margin: 73px`.
- **Outil** : `node tooling/reconcile-margins.mjs integration/figma-tokens.<page>.json` mappe chaque
  padding/gap d'auto-layout au **niveau le plus proche** (par axe) et **propose le token** à poser
  (`pt-md`, `pe-sm`, `mb-xs`…) ; signale les valeurs **orphelines** (hors-échelle, à arbitrer). Parallèle
  de `reconcile-typography`. Référence = breakpoint desktop (`--bp`, défaut `xxl`).
- **Vérification** (`tooling/verify-styles.mjs`) : **scale-aware** — le px Figma attendu est **snappé au
  niveau d'échelle le plus proche** (le rendu CMS étant quantifié), donc un écart signale un **vrai**
  problème de rendu (mauvais niveau posé), pas une valeur Figma hors-échelle. Responsive → relancer
  **par breakpoint** (`--width` + `--bp`). `--no-scale` pour comparer au px brut.

## A — Blocs atomiques (catégories `content` + `global` — seules dispo pour une Page)

| Préfixe | slug BlockType | Catégorie |
|---|---|---|
| `[title]` | `title` | global |
| `[intro]` | `title-header` | content |
| `[text]` | `text` | global |
| `[image]` | `media` | global |
| `[video]` | `video` | content |
| `[blockquote]` | `blockquote` | content |
| `[card]` | `card` | content |
| `[modal]` | `modal` | content |
| `[icon]` | `icon` | global |
| `[btn]` / `[cta]` | `link` | global |
| `[separator]` | `separator` | global |
| `[counter]` | `counter` | global |
| `[alert]` | `alert` | global |

> Les blocs `title/text/link/media` portent un contenu Intl (`BlockIntl` /
> `MediaRelationIntl`) — voir `PageFixtures::addAction()`.

## B — Modules métier (bloc `core-action` + Action + entité module)

| Préfixe | Action slug | Entité module |
|---|---|---|
| `[slider]` | `slider-view` | `Module\Slider\Slider` (+ `SliderMediaRelation`) |
| `[gallery]` | `gallery-view` | Module Gallery |
| `[catalog]` / `[catalog\|index]` | `catalog-index` | `Module\Catalog` (grille/liste produits) |
| `[newscast]` / `[newscast\|index]` | `newscast-index` | `Module\Newscast` (liste actus) |

### Teasers (aperçu/carrousel d'un autre module)

Un teaser se reconnaît au jeton **`*-teaser`**, où qu'il soit placé. Le parser route sur ce jeton
**avant** le mapping `[slider]`/`[catalog]`/`[newscast]` générique (sinon un teaser retomberait en
slider ou en index). Implémenté : `ConventionMapper::resolveTeaser()`.

**Forme canonique** (à utiliser en maquette) : `[<domaine>|teaser|<layout>]` où `<layout>` =
`slider|splide` (carrousel Splide) **ou** `list` (liste). Le 3ᵉ segment décide :

| Préfixe canonique | Action slug | `template` | Entité module |
|---|---|---|---|
| `[catalog\|teaser\|slider\|splide]` | `catalog-teaser` | `slider` | `Module\Catalog\Teaser` |
| `[catalog\|teaser\|list]` | `catalog-teaser` | `list` | `Module\Catalog\Teaser` |
| `[newscast\|teaser\|slider\|splide]` | `newscast-teaser` | `slider` | `Module\Newscast\Teaser` |
| `[newscast\|teaser\|list]` | `newscast-teaser` | `list` | `Module\Newscast\Teaser` |

> **Résolution du template** (`ConventionMapper::teaserTemplate()`) : `slider`/`splide` → `slider`
> (`splide` = la lib qui propulse le carrousel), `list` → `list` ; `list` l'emporte si les deux
> apparaissent. Le `template` est exposé via `ParsedBlock::moduleTemplate`.
>
> **Le 4ᵉ segment `splide` est optionnel — c'est le défaut** : `[catalog|teaser|slider]` ≡
> `[catalog|teaser|slider|splide]` → template `slider` (idem newscast). Autrement dit, un teaser en
> `slider` est **par défaut un carrousel Splide**. N'utiliser `list` que pour forcer la liste.
>
> **Formes héritées encore acceptées** (tolérance, non recommandées) : `[catalog|teaser]`,
> `[teaser|catalog-teaser]`, `[teaser|catalog]`, `[catalog-teaser]` (idem newscast, + `[slider|newscast-teaser]`).
> `[teaser]` seul (sans domaine) est **ambigu** → bloc `unknown` avec note invitant à préciser.
| `[portfolio]` | `portfolio-index` | `Module\Portfolio` |
| `[form]` / `[contact]` | `form-view` | `Module\Form\Form` |
| `[tab]` | `tab-view` | Module Tab |
| (carte/map) | `map-view` | `Module\Map` |

> Autres Actions disponibles : `faq-view`, `faq-teaser`, `agenda-view`,
> `timeline-view`, `table-view`, `recruitment-index`, `search-result-view`,
> `sitemap-view`, `information-view`, `menu-view`, `pages-navigation-view`…

### Texte des slides/cards (dry-run) → champs de fixtures

Le parser extrait le **texte structuré** de chaque slide/card dans `ParsedBlock.media[]`
(`{title, introduction, targetLabel, style}`, via `PageParser::cardText()`). Où le **router** à
l'intégration dépend du type de module :

| Module | Chaque `media[]` (card/slide) alimente | Champs |
|---|---|---|
| `slider-view` (`[slider]`) | une **`SliderMediaRelation` + son intl** (le slider porte son contenu) | `intl.title` ← `title` ; `intl.introduction` ← `introduction` ; `intl.body` ; `intl.targetLabel` ← `targetLabel` ; `intl.targetLink` (URL via interaction proto) ; + `setMedia()` (image de la card) |
| `catalog-teaser` / `newscast-teaser` | un **item d'entité** (`Module\Catalog\Product` / `Module\Newscast\Newscast`), **PAS** le bloc teaser | `ProductIntl`/`NewscastIntl` : `title` ← `title`, `introduction` ← `introduction`, `body` ; + `XxxMediaRelation->setMain(true)` (sinon card `no-media`) |

> ⚠️ **Distinction clé** : un **slider** porte son contenu sur sa relation média (slide) ; un **teaser**
> n'a pas de contenu propre par card — il **affiche les items** d'un module (produits/actus). Donc le
> texte des cards d'un teaser **seed les fixtures d'items** (Catalog/Newscast), jamais le bloc teaser.
> Le `targetLabel` (CTA, ex. « Découvrir ») est en général **un libellé générique du template**, pas
> un champ par item — ne le reporter que s'il varie réellement par card.
> La sélection des items affichés par un teaser (récents, `nbrItems`, `promoteFirst`, catégorie) est une
> **config du teaser**, indépendante du texte extrait.

## Détail module : Slider (`[slider]`)

Patron : `PageFixtures::addHomeLayout()`. Entité `Module\Slider\Slider` +
`SliderMediaRelation` (un par slide et par locale).

### 1. La variante `|xxx` → `template` (et NE PAS régler le reste à la main)

`Slider::prePersist()` cascade automatiquement la config selon `template`.
Mapper la variante de la convention sur `setTemplate()` et laisser faire :

| Préfixe Figma | `setTemplate()` | Conséquences auto (prePersist) |
|---|---|---|
| `[slider]` (défaut) | `bootstrap` | `arrowAlignment='bottom-end'` |
| `[slider\|splide]` | `splide` | `progress=true`, `itemsPerSlide=4/3/2/1` (desktop/miniPC/tablet/mobile), `arrowAlignment='top-end'` |
| `[slider\|banner]` | `banner` | `intervalDuration=15000` |

> Ne PAS poser à la main les ~10 champs déjà gérés par `prePersist`.
> Ne régler explicitement que ce que la maquette impose et qui n'est pas couvert.

> **Dry-run** : le parser extrait déjà les **images de slides** (nœuds à fill `IMAGE`)
> dans `block.media` (`{figmaNodeId, image, imageRef, width}`) et les rend dans
> `.claude/skills/figma-cms/integration/media/<slug>/`. **Résolution** : scale calculé par média pour viser
> ~3840px de large pour une image pleine largeur (retina ≤1920px sinon), **sans jamais
> dépasser 3840px** (plafond dur, arrondi vers le bas, réduit aussi les nœuds plus larges).
> **Format** : WebP **lossless** (`IMG_WEBP_LOSSLESS`) — compression sans perte de qualité.
> Reste à extraire le contenu texte de chaque slide.

### 1bis. Identifiant de slider + slides rattachées par id

- **Id du slider** : modifier `id:` → `[slider|id:home-1]` (ou `[slider|splide|id:home-1]`).
  Le parser expose `id` + `moduleTemplate` (résolu depuis la variante). Forme abrégée
  tolérée : `[slider-home-1]` (tiret) → `id=home-1` ; faute proche signalée
  (`silder` → « vouliez-vous [slider|id:home-1] ? »).
- **Slides posées séparément** : `[slide-N|<sliderId>]` (ex. `[slide-1|home-1]`,
  `[slide-2|home-1]`). Le parser les collecte dans toute la page, les **trie par
  position N** et les rattache au `[slider|id:<sliderId>]` comme média (slides). Elles ne
  sont jamais rendues comme blocs autonomes.

### 2. Importer les slides (médias + contenu) — le cœur du travail

Pour CHAQUE slide de la maquette :
1. **Exporter l'image** du nœud Figma via l'API `/v1/images` (cf. règle « capturer avant d'interpréter »).
2. **Importer comme `Media`** dans le CMS (uploads) — jamais un simple placeholder.
3. Lire le **contenu** du slide dans les calques (titre, intro, texte, CTA/lien) → remplir l'`intl` (`title`, `introduction`, `body`, `targetLink`, `newTab`).
4. Créer un `SliderMediaRelation` { `position`, `locale`, `Media`, `intl` } et `addMediaRelation()`.

### 3. Config déduite du visuel + modificateurs

| Source maquette | Champ Slider |
|---|---|
| nb d'items visibles par vue (1 = hero, N = carrousel) | `itemsPerSlide*` |
| modificateur `bg:` | `backgroundColor` |
| couleur des flèches | `arrowColor` |
| flèches / puces visibles | `control` / `indicators` |
| effet (fondu, slide) | `effect` |

### 4. Multilingue (obligatoire)

`SliderMediaRelation` porte une `locale`. Créer un jeu de relations **par locale
active** du site (comme `addHomeLayout` avec `$this->locale`).

## Arbitrage restant

- **`[faq]`** : bloc atomique `collapse` (accordéon simple) **ou** module `faq-view`
  (module FAQ géré). À décider selon l'usage voulu de la maquette.

## Déductions automatiques du parser (dry-run)

Sans balisage explicite, le parser déduit — toujours « indicatif », à figer par des tags :

- **Grille Bootstrap 12** : les colonnes d'une ligne se partagent 12 unités (gouttières =
  padding interne, non comptées). 2 colonnes égales → **6/6**, 3 → **4/4/4** ; la ligne somme à 12.
- **Image plein écran = une zone** : un fond IMAGE pleine largeur est auto-contenu (frontière
  en haut ET en bas). Les **filigranes TEXT** pleine largeur (gros mots d'ambiance décoratifs) ne
  créent **pas** de frontière de bande.
- **Teaser qui déborde à droite** (élément > largeur de page) → zone **`colToRight`**
  (`Zone::setColToRight(true)`).
- **Zone croppée à droite = `slider|splide` (sauf teaser actu/produit).** Une bande dont un élément est
  **coupé sur le bord droit** (rangée de cards qui déborde, flèches de carrousel précédent/suivant) est,
  par convention, un **carrousel `slider|splide`** — **à moins** qu'elle ne soit déjà un **teaser
  d'actualités** (`newscast-teaser`) ou un **teaser de produits** (`catalog-teaser`). Implémenté :
  `PageParser::buildZoneFromElements()` remplace les colonnes déduites par un bloc module
  `core-action` / `slider-view` (template **splide**) portant les images des cards comme slides,
  dès que `colToRight` est vrai et qu'aucun module n'est déjà présent. **Le TITRE de la section
  (s'il existe) est conservé dans la MÊME zone**, au-dessus du carrousel.
- **Suite d'images se terminant par un élément croppé → zone DÉDIÉE.** Qu'il s'agisse d'un carrousel
  `slider|splide` OU d'un **teaser** (`newscast-teaser`, `catalog-teaser`), dès qu'une rangée d'images
  alignées **se termine par un élément coupé** à droite : tout va dans **une zone dédiée** (avec son
  **titre** s'il y en a un), `Zone::setColToRight(true)`, et **`padding-right = 0` appliqué sur la ZONE,
  la COL et le BLOC** (`setPaddingRight('pe-0')` aux trois niveaux).
- **Images non balisées** → blocs `[image]` (BlockType `media`).
- **CTA détecté par nom de calque** : un calque dont le nom contient `cta`/`bouton`/`btn`/`button`
  → bloc `[link]` (BlockType `link`) ; le libellé = premier texte trouvé dans le sous-arbre.
- **Textes non balisés** : titres `[title]` avec niveau **h1…h6 déduit par taille de police**
  (la plus fréquente = corps ; tailles supérieures classées décroissant) ; sinon bloc `[text]`.
  ⚠️ Garde-fou à trancher : un texte **long** (paragraphe) reste `text` même en grande police.
- **Forme abrégée de tag** : `[type-suffixe]` (tiret) où `type` est connu → `type` + `id:suffixe`
  (ex. `[slider-home-1]` ≡ `[slider|id:home-1]`). Préfixe inconnu **proche** d'un type connu →
  **suggestion de correction** dans la note (ex. `silder` → « vouliez-vous [slider…] ? »).
- **`*|mobile`** : variante mobile générique d'un élément (`[nav|mobile]`, `[slider|mobile]`…).

## Normalisation du contenu texte (obligatoire)

Tout texte récupéré (titres, textes, libellés de CTA, slides) est normalisé :
- **jamais tout en majuscules** (un display all-caps « POUR VOS … » → casse de phrase « Pour vos … ») ;
- **toujours une majuscule en première lettre** (« réservez un séjour » → « Réservez un séjour »).
Le texte en casse mixte garde sa casse (hors 1ʳᵉ lettre). Implémenté : `PageParser::normalizeText()`.

## Rapprochement CMS explicite par bloc

Chaque bloc de l'arbre porte un champ **`cms`** = la traduction en appel `PageFixtures::addBlock()` :
- **atome** : `{ entity: "…Layout\\Block", blockType: "<slug>", fixture: "addBlock($col, '<slug>')" }` ;
- **module** : `{ entity: "…Layout\\Block + <entité module>", action: "<action>", template: "<template>",
  fixture: "addBlock($col, 'core-action', '<action>', $entity->getId())" }`.
Objectif : passer du dry-run aux fixtures **sans réinterpréter** le mapping.

## Export des images (le CMS dérivera le WebP)

Images exportées en **×2 retina**, format source — **jamais en WebP** (le CMS s'en charge) :
- **photo / image simple** → `jpg` ; **transparence** → `png` ; **logo** → `svg` si possible.
- Implémenté dans `PageScreenshotter::captureMedia()` (groupé par format+scale, téléchargement
  natif Figma sans ré-encodage) ; format choisi par `PageParser::mediaFormat()`.
- **Dimensions** : une **image plein écran / pleine largeur** est rendue à **≥ 3840px** de large
  (hero haute définition) ; les **autres** images en **retina ×2** plafonné (~2048px). Le format
  natif est conservé (le WebP final reste au CMS).
- **Optimisation web** : ré-encodage JPEG progressif (q82) / PNG compressé pour rester léger ;
  les images **non plein écran** visent **< 1 Mo** (le hero ≥3840px peut être plus lourd, c'est voulu).
- ⚠️ **Renommage selon le VISUEL** : nommer chaque image récupérée d'après **ce qu'elle montre**
  (le contenu visuel : `chambre-vue-jardin`, `piscine`, `bar-cocktails`…), pas `media-<nodeId>` ni un
  nom d'appareil photo (`IMG_2829`). S'appuyer sur le **contenu de l'image** (analyse visuelle) + le
  contexte du calque/section. Pour une slide de slider : `slide-<sliderId>-<position>`.

## Contextualisation « produit » par projet

Les pages/teasers `product-*` / `catalog` désignent l'**entité phare du projet**, variable
(chambre pour un hôtel, formation, yaourt, véhicule…). Déclarer le mapping
« produit → entité métier » **une fois par projet** dans sa spec (`<projet>.md`).

## Produits & catalogue (fiches à BlockTypes `layout-*`)

- **Un produit appartient TOUJOURS à un catalogue** (`Module\Catalog\Catalog`). Pas de produit hors catalogue.
- **Le Layout d'un produit = le Layout du catalogue** : la fiche produit n'a pas de layout propre ;
  elle utilise le **layout de catalogue** (ex. slug `main-catalog`, partagé par toutes les fiches),
  généré via `CatalogFixtures::generateLayout()` (`addLayout(..., ['catalog' => $catalog])`).
- **BlockTypes des fiches = ceux dont le slug commence par `layout-`** (catégorie `layout`) :
  `layout-title-header`, `layout-intro`, `layout-body`, `layout-link`, `layout-share`,
  `layout-published-date`, `layout-back-button`, `layout-media`, `layout-slider`… Ce sont les seuls
  utilisables dans une fiche produit/listing (ils mappent les champs de l'entité produit).
- **Actualités (Newscast)** : même principe, mais le Layout d'une **actu = celui de sa CATÉGORIE**
  (Newscast Category) — ou, à défaut, celui de la **catégorie principale**. L'article n'a pas de layout
  propre ; il hérite du layout de sa catégorie, lui aussi composé de BlockTypes `layout-*`.
- Idem pour les autres listings/fiches (Portfolio…) : layout du module/catégorie + blocs `layout-*`.

## Pièges confirmés

- **`layout-slider`** (id 29, catégorie `layout`) n'est **PAS** utilisé pour une Page.
  La catégorie `layout` (21-35) est réservée aux listings/fiches
  (Newscast Category, Catalog, Product, Portfolio) — cf. `LayoutFixtures::getConfiguration()`.
  Une Page n'a accès qu'aux catégories `content` + `global` + modules.
- `[nav]` / `[footer]` / `[newsletter]` / `[socialwall]` : layout de base, intégrés **une seule fois**,
  exclus de la génération par page. **Source unique** : les récupérer **uniquement** depuis `[page|home]`
  ou depuis des **frames isolés dédiés** (`[nav]`, `[nav|mobile]`, `[footer]`…) — jamais ré-extraits
  d'une autre `[page|…]` (cf. `integration-prompts.md` § « Cas particulier : nav & footer »).

## Du BlockType / Action au HTML (Twig) puis au CSS (SCSS)

> Carte « **où agir pour modifier un rendu** » — à **enrichir au fil des intégrations** avec chaque
> relation découverte. Chaîne : **BlockType → (Action) → template Twig → classes HTML → cible SCSS.**

**Chaîne générale :**
1. **BlockType** (slug dans la fixture : `title`, `text`, `link`, `media`, `core-action`…). Les blocs
   « riches » passent par une **action** (`core-action` + `actionSlug` : `slider-view`, `catalog-teaser`,
   `newscast-teaser`, `map-view`, `form-view`…).
2. **Action** → template sous `templates/front/default/actions/<domaine>/…` ; reçoit l'entité liée
   (Slider, Teaser…) et choisit un **sous-template**.
3. **HTML** : le sous-template produit le markup + des **classes dérivées d'un id d'entité**
   (`slider-container-<slug>`, `carousel-<slug>`, `zone-<customId>`…).
4. **CSS** : styler via ces classes dans le SCSS de page/composant
   (`assets/scss/front/default/templates/<page>.scss` ou `components/`).

**Atomes → templates :** bloc `title`/`text`/`link` → `blocks/{title,text,link}/default.html.twig` ;
bloc `media` → filtre `|file` (figure/picture, wrapper `.img-loader-wrap` en `z-index:10` — penser au
stacking si overlay) ; `alert` → `blocks/alert/…` (+ JS `#website-alert`).

**Exemple détaillé — Slider (`core-action` / `slider-view`) :**
- `addBlock($col, 'core-action', 'slider-view', $slider->getId())` → rend `actions/slider/view.html.twig`.
- `view.html.twig` choisit le template **par `slider.template`** (`splide`, `main-home`, `two-columns`…)
  **ou par `slider.slug`** : si `actions/slider/template/<slug>.html.twig` existe, il **prime**
  (template 100 % custom pour ce slider).
- Pour customiser **seulement la card** d'un slider donné sans dupliquer la mécanique du carrousel :
  dans le sous-template (ex. `splide.html.twig`), **condition sur l'id du slider**
  (`{% if slider.slug == 'home-universe' %}`) → include d'une **card dédiée**
  (`template/include/card-<x>.html.twig`). Classes exposées : `slider-container-<slug>`,
  `carousel-<slug>` / `splide-container` → cibles SCSS.

**Teasers :** `catalog-teaser` → `actions/catalog/teaser/{slider,slider-multi}.html.twig` ;
`newscast-teaser` → `actions/newscast/teaser/slider.html.twig`.

**⚠️ Piège média des cartes/teasers (`ViewModel.mainMedia`) :** les cartes (`macros/card.html.twig`)
affichent l'image via `intl.mainMedia`, alimentée par `MediasModel.main` = la **relation média flaggée
`main`**. Dans les fixtures de modules (produits, actus…), `XxxMediaRelation` **doit appeler
`->setMain(true)`** sinon `mainMedia` est **null** → carte rendue `no-media` (image absente). Toujours
marquer la relation principale `setMain(true)` (en plus de `setMedia`/`setLocale`).
