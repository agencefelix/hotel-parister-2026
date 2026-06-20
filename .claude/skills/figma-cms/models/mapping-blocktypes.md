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
| `[col]` | `Layout\Col` | `setSize()` = colonnes Bootstrap (1-12) |

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
| `[catalog]` | `catalog-index` | `Module\Catalog` |
| `[newscast]` | `newscast-index` (ou `newscast-teaser`) | `Module\Newscast` |
| `[portfolio]` | `portfolio-index` | `Module\Portfolio` |
| `[form]` / `[contact]` | `form-view` | `Module\Form\Form` |
| `[tab]` | `tab-view` | Module Tab |
| (carte/map) | `map-view` | `Module\Map` |

> Autres Actions disponibles : `faq-view`, `faq-teaser`, `agenda-view`,
> `timeline-view`, `table-view`, `recruitment-index`, `search-result-view`,
> `sitemap-view`, `information-view`, `menu-view`, `pages-navigation-view`…

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
> `.claude/figma-cms/integration/media/<slug>/`. **Résolution** : scale calculé par média pour viser
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
  en haut ET en bas). Les **filigranes TEXT** pleine largeur (« parister », « workspaces ») ne
  créent **pas** de frontière de bande.
- **Teaser qui déborde à droite** (élément > largeur de page) → zone **`colToRight`**
  (`Zone::setColToRight(true)`).
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
- `[nav]` / `[footer]` : layout de base, intégrés **une seule fois**, exclus de la
  génération par page (cf. `integration-prompts.md`).
