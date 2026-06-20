# Mapping convention Figma → mécanique CMS (BlockType / Modules)

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
> `.claude/figma/integration/media/<slug>/`. **Résolution** : scale calculé par média pour viser
> ~3840px de large pour une image pleine largeur (retina ≤1920px sinon), **sans jamais
> dépasser 3840px** (plafond dur, arrondi vers le bas, réduit aussi les nœuds plus larges).
> **Format** : WebP **lossless** (`IMG_WEBP_LOSSLESS`) — compression sans perte de qualité.
> Reste à extraire le contenu texte de chaque slide.

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

## Pièges confirmés

- **`layout-slider`** (id 29, catégorie `layout`) n'est **PAS** utilisé pour une Page.
  La catégorie `layout` (21-35) est réservée aux listings/fiches
  (Newscast Category, Catalog, Product, Portfolio) — cf. `LayoutFixtures::getConfiguration()`.
  Une Page n'a accès qu'aux catégories `content` + `global` + modules.
- `[nav]` / `[footer]` : layout de base, intégrés **une seule fois**, exclus de la
  génération par page (cf. `integration-prompts.md`).
