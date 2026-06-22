# Exemples de Fixtures réutilisables (SFCMS 7)

> Snippets **génériques** validés en projet, à réutiliser tels quels (adapter les libellés/valeurs).
> Pièges importants notés. Voir aussi `integration-prompts.md` (playbook) et `mapping-blocktypes.md`.

## Classes de Fixtures (identiques sur TOUT projet — s'appuyer dessus, ne rien réinventer)
Toutes dans `src/Service/DataFixtures/` ; orchestrées par **`WebsiteFixtures::initialize()`** qui appelle
les sous-fixtures. API stable réutilisable d'un projet à l'autre :
- **`WebsiteFixtures::initialize()`** — orchestrateur (configure website + appelle toutes les sous-fixtures).
  Contient `getPagesParams()` (déclaration des pages : `reference`, `url`, `menus`, `template`, `disable`).
- **`PageFixtures`** — helpers publics : `addLayout`, **`addZone($layout, $position, $fullSize, $noPadding, $customId)`**,
  **`addCol($zone, $position, $size)`**, **`addBlock($col, $blockTypeSlug, $actionSlug, $actionFilter, $position, $size)`**,
  `setForm`, `addFieldConfiguration`. (+ helpers projet : `mediaBlock`, `setContent`, `importMedia`.)
- **`MenuFixtures::add()`** — menus `main`/`footer` (+ hiérarchie via une constante de groupes).
- **`CatalogFixtures::add()`** — catalogue, produits, `Feature`/`FeatureValue`, `Teaser`, médias produit.
- **`NewscastFixtures::add()`** — actualités + `Teaser`.
- **`UploadedFileFixtures::uploadedFile($website, $path, $locale, …)`** — upload d'un fichier → `Media` (pour rattacher les vraies images).
- **`ConfigurationFixtures` / `SeoFixtures` / `InformationFixtures` / `ColorFixtures` / `GdprFixtures` /
  `NewsletterFixtures` / `MapFixtures` / `ApiFixtures` / `TranslationsFixtures`** — config, SEO, infos
  (adresses/tél/emails par zones), couleurs, RGPD, newsletter, carte, API, traductions.
- **`DefaultMediasFixtures`** — médias de marque (logo, favicon, share, email-logo, preloader…).

> Sur un nouveau projet : modifier le **contenu** (constantes/params dans ces classes), pas l'API.

## 1) Zone avec `customId` (cibler chaque bande)
```php
// customId EN ANGLAIS + tirets ; rendu front : id="zone-<customId>"
$zone = $this->addZone($layout, $position, fullSize: true, customId: 'home-hero');
$zone->setBackgroundColor('bg-dark');   // bg:* de la maquette → bg-primary/secondary/dark/light/...
$zone->setColToRight(true);             // [zone|col-to-right]
$col  = $this->addCol($zone, 1, 6);     // grille Bootstrap 12
```
Helper `addZone` : ajouter le param `?string $customId = null` → `if ($customId) $zone->setCustomId($customId);`.

## 2) Bloc média avec VRAIE image + sans légende Faker
```php
private function mediaBlock(Layout\Col $col, string $filename, int $position = 1): Layout\Block
{
    $block = $this->addBlock($col, 'media', null, null, $position);
    $media = $this->importMedia($filename); // upload depuis .claude/skills/figma-cms/integration/media/...
    if ($media instanceof Media) {
        $media->setTitlePosition(null);                 // ← supprime la figcaption Faker (clé !)
        foreach ($media->getIntls() as $i) { $i->setTitle(''); } // alt non-Faker
        $rel = $block->getMediaRelations()->first();
        if ($rel instanceof Layout\BlockMediaRelation) { $rel->setMedia($media); }
    }
    return $block;
}
```
⚠️ Vider le titre à `null` NE suffit pas (un filler Faker re-remplit au flush) → `setTitlePosition(null)`.
⚠️ `BlockMediaRelation` n'a **pas** `getIntls()`.

## 3) Helper `setContent` (title/text/link) — labels & styles de lien
```php
$intl = $block->getIntls()->first();
$intl->setTitle($data['title'] ?? null);
$intl->setSubTitle($data['subTitle'] ?? null);
$intl->setIntroduction($data['introduction'] ?? null);
$intl->setBody($data['body'] ?? null);
$intl->setTargetLink($data['targetLink'] ?? null);
$intl->setTargetLabel($data['linkLabel'] ?? null);   // ← LABEL du lien (PAS title !)
$intl->setTargetStyle($data['linkStyle'] ?? null);   // 'link' (texte) ou 'btn btn-primary' (bouton)
```

## 4) Lien / CTA
```php
// CTA bouton plein (couleur de charte relevée du fill maquette)
$this->setContent($this->addBlock($col, 'link', null, null, 3),
    ['linkLabel' => 'Réserver', 'linkStyle' => 'btn btn-primary', 'targetLink' => '/...']);
// Lien texte/souligné
$this->setContent($this->addBlock($col, 'link', null, null, 3),
    ['linkLabel' => 'Découvrir les chambres', 'linkStyle' => 'link', 'targetLink' => '/...']);
```
⚠️ Le bloc lien lit `intl.linkLabel`/`intl.linkStyle` (← `setTargetLabel`/`setTargetStyle`), jamais `title`.

## 5) Menu hiérarchique (mega-menu en colonnes)
```php
private const MAIN_GROUPS = [
  ['title' => '<Rubrique>', 'children' => [
     ['ref' => '<refPage>', 'title' => '<Libellé exact>'],   // enfant → page existante
     ['title' => '<Libellé>', 'link' => '#'],                // enfant sans page (placeholder)
  ]],
];
// parent: setLevel(1) (titre, sans page) ; enfant: setLevel(2) + setParent($parent) + targetPage/targetLink
```

## 6) Teaser produits (slider de cartes) dans une zone
```php
$teaser = $em->getRepository(Catalog\Teaser::class)->findOneBy(['website' => $this->website]);
$teaser->setTemplate('slider'); $teaser->setItemsPerSlide(3); $teaser->setNbrItems(8);
$col = $this->addCol($zone, 3, 12);
$this->setContent($this->addBlock($col, 'title'), ['title' => '<Titre>', 'titleForce' => 2]);
$this->addBlock($col, 'core-action', 'catalog-teaser', $teaser->getId(), 2);
// (équivalent actus : action 'newscast-teaser' + Newscast\Teaser)
```

## 7) Vraies images sur les PRODUITS (et actus)
```php
// dans generateMediaRelation($entity, $imageFilename) : importer la vraie image, sinon fallback 'share'
$media = $this->uploadedFileFixtures->uploadedFile($website, $path, $locale, null, null, null, $user);
foreach ($media->getIntls() as $i) { $i->setTitle(''); }
// → la fiche produit (hero + galerie) et les teasers/sliders affichent la vraie photo
```

## 8) Slider d'univers (splide) — cartes avec label
```php
$intl = new MediaRelationIntl();
$intl->setTitle('<Titre>'); $intl->setIntroduction('<sous-titre script>'); $intl->setBody('<p>...</p>');
$intl->setTargetLink('/...'); $intl->setTargetLabel('Découvrir'); $intl->setTargetStyle('link');
```

## Rappels transverses
- **Flags SCSS** `$enable-*` à `true` pour les modules actifs (sinon styles non compilés).
- **mediaRelations** : `popup`/`download` désactivés en fixtures (sauf page `components` existante).
- **Exit code reload** : ne pas masquer via `| tail` ; rediriger vers fichier et tester `$?`.
- **RIGUEUR** : récupérer TOUS les éléments de chaque zone (toutes les images, pas la 1ʳᵉ seulement).

---

# Exemples COMPLETS (bout-en-bout)

## A) Orchestration — `WebsiteFixtures::initialize()`
Ordre d'appel des sous-fixtures (via le locator `$this->fixtures->xxx()`), gardé par les modules actifs :
```php
$this->fixtures->configuration()->add($website, $yaml, $locale, self::DEV_MODE, self::DEFAULTS_MODULES, self::OTHERS_MODULES, $user, $dup);
$this->fixtures->information()->add($website, $yaml, $user);   // adresses/tél/emails par zones
$this->fixtures->api()->add($website, $yaml);
$this->fixtures->seo()->add($website, $user);
$webmasterFolder = $this->fixtures->defaultMedias()->add($website, $yaml, $user); // logo/favicon/share...
$this->fixtures->blockType()->add($configuration, self::DEV_MODE, $dup);
$this->fixtures->color()->add($configuration, $yaml, $user, $dup);
$this->fixtures->transition()->add($configuration, $user, $dup);
if (in_array('ROLE_NEWSCAST', self::DEFAULTS_MODULES)) { $this->fixtures->newscast()->add($website, $user); }
if (in_array('ROLE_CATALOG',  self::DEFAULTS_MODULES)) { $this->fixtures->catalog()->add($website, $user); }
$this->fixtures->newsletter()->add($website, $user);
$pages = $this->fixtures->page()->add($website, $yaml, $pagesParams, $user, true, self::MAIN_PAGES);
$this->fixtures->layout()->add($configuration, self::DEV_MODE, self::DEFAULTS_MODULES, self::OTHERS_MODULES, $user, $dup);
$this->fixtures->menu()->add($website, $pages, $pagesParams, $user, $dup);
$this->fixtures->gdpr()->add($webmasterFolder, $website, $user);
$this->fixtures->map()->add($webmasterFolder, $website, $user);
$this->fixtures->thumbnail()->add($website, $user, $dup);
```
- **Pages déclarées dans `getPagesParams()`** : `['name','reference','url','menus'=>['main','footer'],'template','urlAsIndex','deletable','disable'=>!in_array('ROLE_X', DEFAULTS)]`.
- `DEFAULTS_MODULES` = liste des `ROLE_*` actifs (CATALOG, NEWSCAST, GALLERY, MAP, CONTACT, NEWSLETTER, FAQ, SOCIAL_WALL…).

## B) Zone HOME multi-images dans le BON ORDRE (grille 2×2) + customId
```php
// Ordre de lecture maquette : [texte | image1] / [image2 | image3]
$zone = $this->addZone($layout, 5, customId: 'home-restaurant');
$zone->setBackgroundColor('bg-light');
$textCol = $this->addCol($zone, 1, 6);                                  // 1) texte (haut-gauche)
$this->setContent($this->addBlock($textCol, 'title'), ['title'=>'<Titre>', 'subTitle'=>'<sous-titre>', 'titleForce'=>2]);
$b = $this->setContent($this->addBlock($textCol, 'text', null, null, 2), ['body'=>'<p>…</p>']); $b->setMarginBottom('mb-md');
$this->setContent($this->addBlock($textCol, 'link', null, null, 3), ['linkLabel'=>'Voir le menu', 'linkStyle'=>'btn btn-primary', 'targetLink'=>'/...']);
$this->mediaBlock($this->addCol($zone, 2, 6), 'restaurant-plat.jpg');   // 2) image (haut-droite)
$this->mediaBlock($this->addCol($zone, 3, 6), 'restaurant-cocktail.jpg'); // 3) image (bas-gauche)
$this->mediaBlock($this->addCol($zone, 4, 6), 'restaurant-dessert.jpg');  // 4) image (bas-droite)
```

## C) Produit catalog COMPLET (vraie image + caractéristiques + URL + teaser)
```php
foreach (self::ROOMS as $i => $room) {            // ROOMS = données réelles (slug/surface/capacité/vue/services/intro)
    $product = new CatalogEntities\Product();
    $product->setAdminName($room['title']); $product->setCatalog($catalog); $product->setWebsite($website);
    $product->setPosition($i+1); $product->setCreatedBy($user);
    $this->generateIntl($room['title'], $product, $room['intro'], '<p>'.$room['intro'].'</p>');
    $this->generateMediaRelation($product, 'room-'.($i+1).'.jpg');   // VRAIE image (sinon fallback 'share')
    $this->generateUrl($product, $room['slug']);                    // code URL = slug anglais
    foreach ($this->roomFeatureValues($room) as $featureName => $labels) { /* rattacher FeatureValueProduct réels */ }
}
// Teaser produits (slider de cartes) réutilisable dans une zone : action 'catalog-teaser'
$teaser->setTemplate('slider'); $teaser->setItemsPerSlide(3); $teaser->setNbrItems(8);
```

## D) Actualité COMPLÈTE (vrai article + image `.block_entete` prod)
```php
foreach (self::EVENTS as $event) {                 // EVENTS = articles réels {title, url(slug prod), intro}
    $newscast = new NewscastEntities\Newscast();
    $newscast->setAdminName($event['title']); $newscast->setWebsite($website);
    $newscast->setPublicationStart(new \DateTime()); $newscast->setCreatedBy($user);
    $this->generateIntl($event['title'], $newscast, $event['intro'], '<p>'.$event['intro'].'</p>');
    $this->generateMediaRelation($newscast, 'news-'.$event['url'].'.jpg'); // image téléchargée du .block_entete prod
    $this->generateUrl($newscast, $event['url']);  // continuité SEO = slug prod
}
```
Récupération des images : pour chaque slug, charger `https://<prod>/<slug>`, lire
`getComputedStyle('.block_entete').backgroundImage` (Puppeteer/UA + délai anti-429), télécharger l'image
(conversion base64 **par chunks** sinon stack overflow) dans `media/news/news-<slug>.jpg`.

## E) Câbler une NOUVELLE fixture (ex. `FaqFixtures`) via le locator
1. `src/Service/DataFixtures/FaqFixtures.php` : `class FaqFixtures` + `#[Autoconfigure(tags: [...])]` + `public function add(Website $website, ?User $user): void` (créer `Faq` + `Question`/`QuestionIntl`, `setTitle`=question, `setBody`=réponse).
2. Ajouter l'accessor au locator : `DataFixturesInterface::faq(): FaqFixtures` + impl `DataFixturesLocator::faq()`.
3. Appel dans `WebsiteFixtures::initialize()` : `if (in_array('ROLE_FAQ', self::DEFAULTS_MODULES)) { $this->fixtures->faq()->add($website, $user); }`.

## F) Menu hiérarchique COMPLET (mega-menu colonnes) — `MenuFixtures`
```php
private const MAIN_GROUPS = [
  ['title'=>'Rubrique A', 'children'=>[ ['ref'=>'page1','title'=>'Libellé 1'], ['title'=>'Externe','link'=>'#'] ]],
  // ...
];
// parent: new Link()->setLevel(1)->setMenu($menu)->setIntl(LinkIntl(title)) (sans page)
// enfant: new Link()->setLevel(2)->setParent($parent) ; LinkIntl->setTargetPage($page) OU setTargetLink($url)
// template menu 'main' (mega-menu) / 'footer' ; setExpand('xxxl') pour overlay permanent.
```
