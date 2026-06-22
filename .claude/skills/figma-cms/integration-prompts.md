# Intégration de prompts provenant de Figma

## 🛠️ Outillage réutilisable (ne pas réécrire du jetable)
Tout script/outil utile à une intégration **cohérente, structurée et ISO maquette** (capture Chrome,
comparaison maquette↔rendu, parsing de tokens Figma, montage côte à côte…) doit être **créé une fois
comme outil réutilisable** dans `.claude/skills/figma-cms/tooling/` (ex. `tooling/capture.mjs`), **pas
réécrit à chaque fois** en ad hoc. Si tu génères un tel script, **range-le dans `tooling/` et documente
son usage** ici. Réutiliser/améliorer l'existant avant d'en créer un nouveau.

## ♻️ Enrichir ce playbook au fil de l'eau
Si, **pendant une intégration**, tu découvres une règle/mécanique/piège utile pour intégrer de façon
**qualitative et complète** (un mécanisme CMS, un mapping, un piège de build, une convention), **ajoute-la
ici** (en version **générique**, sans référence à un projet précis). Le playbook doit se compléter en
continu pour rester exhaustif et réutilisable sur tout projet.

## ⚠️ RIGUEUR — principe NUMÉRO 1 (non négociable)

L'intégration doit reproduire la maquette **À LA LETTRE**. Aucune approximation, aucun
« à peu près », aucune supposition. Concrètement, à CHAQUE élément intégré :

1. **Extraire la maquette du node Figma** (image + JSON via l'API) et **la regarder** —
   ne jamais coder « de mémoire » ou par déduction.
2. **Récupérer TOUTES les vraies images** de la maquette (photos, logos, icônes) via leur
   node-id / imageRef et les poser comme **vrais médias**. **JAMAIS de `placeholder.jpg`**
   quand l'image existe dans la maquette (visuels de marque, logos, photos, etc.).
3. **Relever les valeurs exactes du JSON** : couleurs (`fills`), bordures (`strokes`),
   positions/espacements (`absoluteBoundingBox`), tailles de police. Ex. : si tout le texte
   d'un bloc partage une même couleur (`fill`), la reproduire telle quelle — pas une autre
   couleur « parce que ça semble logique ».
4. **BUILD + RELOAD si nécessaire**, puis **CAPTURER le rendu sur le domaine LOCAL dans Chrome**
   (Puppeteer) et **comparer la capture à la maquette côte à côte**. Lister les écarts et
   **itérer jusqu'à correspondance** : image présente, couleurs, bordures, espacements,
   tailles, alignements, logos.
5. **Ne jamais annoncer « conforme/terminé » sans avoir regardé la capture** et confirmé
   chaque point. Un rendu non vérifié = non fait.

> Rappel : les défauts typiques à NE PAS laisser passer — image manquante (placeholder),
> paddings inventés, réseaux sociaux trop petits/collés, logos en texte au lieu de l'image
> de la maquette, mauvaise couleur de bordure/texte, éléments non centrés.

## Consulter TOUT le dossier `models/` (au démarrage de chaque intégration)
Avant de coder, **lire l'intégralité de `.claude/skills/figma-cms/models/`** — c'est le matériel
générique réutilisable d'un projet à l'autre :
- `figma-retrieval.md` — **cookbook de récupération Figma (REST API) + scraping prod** (pages, node JSON,
  export image, imageRef, ordre de lecture, couleurs, `.block_entete`, anti-429). Recettes concrètes réutilisables.
- `fixtures-examples.md` — **snippets de fixtures validés + exemples bout-en-bout + inventaire des classes `src/Service/DataFixtures`** (API stable).
- `mapping-blocktypes.md` — mapping tags Figma ↔ BlockTypes / modules CMS.
- `project-template.md`, `portability-risks.md`, `config.json`, `seo.json`, `cms-catalog.json`,
  `prod-urls.json`, `layout/`, `pages/`, `interactions/` — gabarits & références.

S'appuyer sur ces modèles (et sur les **classes de Fixtures existantes**, identiques sur tout projet)
plutôt que de réinventer ; **enrichir `models/`** au fil des nouveaux patterns produits.

### Dossier `ressources/` — assets non récupérables automatiquement
`.claude/skills/figma-cms/ressources/` accueille les **ressources fournies manuellement** qu'on ne
peut **pas** extraire de Figma/prod par script : **fichiers de polices** (`.woff2`/`.ttf`/`.otf`),
icônes/SVG sources, médias livrés à part, etc. **Avant de recréer/chercher une font ou un asset,
regarder dans `ressources/`** ; si une font reste introuvable (non extractible), la **demander** et
la déposer ici, puis la câbler (`fonts.scss`, `assets/lib/fonts/…`).

## Découpage en ZONES (convention `[zone]` — MAJ, source = doc en ligne)

La page se découpe **Page → Zones → Colonnes → Blocs**. Le nommage fait foi en ligne
(https://figma-doc.agence-felix.fr/) ; ci-dessous le **mapping mécanique vers le CMS** :

> ⚠️ **IMPORTANT — un `[section]` Figma = UNE ZONE CMS (1:1).** Chaque calque taggé `[section]` dans
> la maquette doit devenir **une `addZone()` distincte**, dans le **même ordre vertical** que la
> maquette. Ne JAMAIS fusionner deux `[section]` dans une même zone, ni glisser une `[section]`
> comme simple colonne/bloc d'une autre zone. Cas fréquent : une bande « intro » et le **slider/teaser
> qui la suit** sont **deux `[section]` ⇒ deux zones** (ne pas mettre le slider en colonne de l'intro).
> Compter les `[section]` de la maquette = nombre de zones attendu (hors nav/footer).

| Tag Figma | Mécanique CMS (PageFixtures / Zone) |
|-----------|-------------------------------------|
| `[zone]` | zone standard (`addZone`) — souvent auto-détectée, le tag ne sert qu'à lever une ambiguïté |
| `[section]` | **= une ZONE** : un calque taggé `[section]` dans Figma correspond à **une zone CMS** (`addZone`), rendue en balise sémantique `<section>`. Toujours mapper `[section]` → zone, jamais → simple bloc/colonne. |
| `[zone\|section]` | zone rendue en balise sémantique `<section>` |
| `[zone\|fullwidth]` | zone bord à bord → `addZone(..., fullSize: true)` (pas de conteneur) |
| `[zone\|col-to-right]` | `Zone::setColToRight(true)` (colonnes poussées à droite, ex. teaser débordant) |
| `[zone\|col-to-end]` | colonnes alignées en fin de zone |
| `bg:primary\|secondary\|dark\|light` | `Zone::setBackgroundColor('bg-...')` |
| `text:primary\|dark\|white` | couleur de texte de la zone |
| `rounded` / `shadow` | coins arrondis / ombre |
| `hide-mobile` / `hide-tablet` | masquage responsive |

- **Auto-détection** : les frames de 1ᵉʳ niveau dans `[page]` = zones ; les frames en
  auto-layout **horizontal** = colonnes (grille Bootstrap 12, déduite de la largeur Figma).
- ⚠️ **Rangée d'images/cartes dont la DERNIÈRE déborde / est croppée au bord DROIT** (slider ou
  galerie qui « sort » de l'écran à droite) ⇒ la zone DOIT être en **`Zone::setColToRight(true)`**
  + **padding-right = 0** (sur la zone, la col et le bloc : `pe-0`). C'est le signal visuel d'un
  carrousel/teaser aligné à droite et débordant. Vaut pour tout slider de cartes en bord droit
  (univers, produits, services, événements…), pas seulement un cas précis.
- Modificateurs **cumulables** : `[zone|section|bg:dark|text:white]`.
- ⚠️ **Alignement du CONTENU d'une colonne = propriété d'entité, JAMAIS du CSS sur la colonne** :
  une colonne (ou son texte) **centrée verticalement** ⇒ `Col::setVerticalAlign(true)` ; un contenu
  **aligné en fin** (bas/droite) ⇒ `Col::setEndAlign(true)` (hérités de `BaseConfiguration`, défaut
  `false`). En Figma : auto-layout vertical à alignement principal `CENTER` (→ verticalAlign) ou
  `MAX`/fin (→ endAlign). À ne pas confondre avec `Zone::colToRight`/`colToEnd` (position des colonnes
  DANS la zone) : ici c'est l'alignement du contenu **dans** la colonne.
- **Re-fetcher la doc en ligne à chaque intégration** (elle évolue — ex. passage de `section` à `[zone|section]`).

> **`[section]` = une ZONE** rendue en `<section>` (jamais un simple bloc/colonne) — cf. table ci-dessus.
> Le détail des **blocs/atomes et actions** (ex. `[alert]` → bloc `alert`) vit dans
> `models/mapping-blocktypes.md` (+ doc en ligne), **pas** ici.

## Règle préalable (obligatoire)

Avant d'intégrer une maquette ou un prompt provenant de Figma, **consulter
systématiquement la convention de nommage de référence** :

👉 https://figma-doc.agence-felix.fr/ 

Cette convention fait la **correspondance entre le nommage des calques/composants
de la maquette Figma et la mécanique du CMS** (Symfony 7.4 SFCMS 7). Elle doit
être appliquée pour chaque import Figma → CMS.

> **Générique (le skill) vs projet.** Le **skill** `.claude/skills/figma-cms/` contient tout le
> **générique réutilisable** : `SKILL.md`, `integration-prompts.md` (ce playbook) et `models/`
> (gabarits + `mapping-blocktypes.md` + `cms-catalog.json` + `portability-risks.md`). Tout le
> **spécifique d'un projet** va dans **`.claude/skills/figma-cms/integration/`**. Ne jamais mêler les deux.

> **Arborescence (où va quoi) :**
> - `.claude/skills/figma-cms/` (générique, réutilisable) : `SKILL.md`, `integration-prompts.md`,
>   `models/` (gabarits + `mapping-blocktypes.md` + `cms-catalog.json` + `portability-risks.md`).
> - `.claude/skills/figma-cms/integration/` = **le projet** (tout le spécifique) : la spec projet
>   `<nom-du-projet>.md`, `config.json`, `prod-urls.json`, `seo.json`, et les dossiers
>   `pages/`, `layout/`, `screenshots/`, `media/`, `interactions/`.
>
> Au démarrage, **bootstrapper `.claude/skills/figma-cms/integration/` en copiant les gabarits depuis
> `.claude/skills/figma-cms/models/`** (comme un nouveau projet), puis remplir. Dans la suite de ce
> document, tout chemin relatif (`config.json`, `prod-urls.json`, `seo.json`, `pages/`, `layout/`,
> `screenshots/`, `media/`, `interactions/`) désigne **toujours** son emplacement sous
> `.claude/skills/figma-cms/integration/`.

> ⚠️ **La convention évolue.** Le site est la **seule source de vérité** et peut
> être mis à jour à tout moment. À **chaque** intégration Figma, **re-consulter
> la page en ligne** (ne jamais se fier au seul rappel ci-dessous, qui peut être
> obsolète). En cas d'écart entre le site et ce fichier : **le site prime**, et
> mettre à jour ce rappel en conséquence.

## Procédure complète de reproduction (playbook — à suivre intégralement)

> **But.** Repartir d'un projet où **seul `.claude/skills/figma-cms/integration/` subsiste** (le reste du code a été
> réinitialisé, `integration/` vidé) et **tout refaire à l'identique**. Suivre les phases dans
> l'ordre ; chaque phase renvoie à sa section détaillée. **Ne rien committer sans demande.**

**Phase 0 — Outillage Figma→CMS (dry-run).** S'assurer que l'outillage existe ; le **recréer** s'il a été réinitialisé :
- Services `src/Service/Figma/` : `FigmaApiClient`(+`FigmaApiClientInterface`), `ConventionMapper`,
  `PageParser`, `PageScreenshotter`, `LayoutScreenshotter`, `PageTreeExporter`,
  `Dto/Parsed{Page,Zone,Col,Block}`, `Exception/FigmaApiException`.
- Commandes `src/Command/Figma/` : `figma:parse-page <node-id>` (arbre + `pages/<slug>.json` +
  captures de bandes + médias) ; `figma:capture-layout` (captures depuis `layout/*.json`).
- **Comportements à (ré)implémenter** = TOUTES les règles de ce doc : grille **Bootstrap 12** (6/6),
  **image plein écran = 1 zone**, **colToRight** (teaser débordant), **slider `id:` + `[slide-N|id]`**
  + forme abrégée + suggestion de faute, **CTA/title(h1–h6)/text/image auto**, **normalisation texte**
  (casse phrase), champ **`cms`** par bloc, **auto-exclusion layout** (footer/newsletter/socialwall),
  **export médias** jpg/png/svg + hero **≥3840px** / autres **<1 Mo** + **noms selon le visuel**,
  **import média multilingue**.
- `.env` : `FIGMA_TOKEN` (scope `file_content:read`) + `FIGMA_FILE_KEY`.

**Phase 1 — Kickoff** (§ « deux URLs » + « Connexion ») : **NE RIEN SUPPOSER**. **Demander** à
l'utilisateur l'**URL de prod** + l'**URL du prototype** (inconnues à froid). Fichier Figma =
`FIGMA_FILE_KEY` (`.env`). **Découvrir les pages** en scannant les `[page|…]` du fichier (jamais
des node-ids présupposés). Bootstrapper `integration/` depuis `models/`.

**Phase 2 — Dry-run par page** (§ capture / déduction / interactions / captures synchronisées) :
pour **chaque `[page|…]`** → `figma:parse-page` (=> `pages/<slug>.json`, `screenshots/<slug>/`,
`media/<slug>/`) ; `figma:capture-layout` (=> `screenshots/layout/`) ; interactions proto =>
`interactions/`. **Toutes** les pages doivent avoir leurs captures.

**Phase 2bis — GATE validation des screenshots (BLOQUANT, avant toute intégration).** Une fois le
dry-run d'une page produit, **demander à l'utilisateur de vérifier et valider les screenshots** de
cette page **avant** d'attaquer l'intégration (fixtures + front). Message exact à poser :
> **« Veuillez vérifier et valider les screenshots pour continuer l'intégration. »**
- **Validation PAR PAGE** (`screenshots/<slug>/`) : on n'intègre **que** les pages validées.
- **Pas de validation = pas d'intégration** : tant que les screenshots d'une page ne sont pas
  validés, ne pas générer ses fixtures ni son front. Ex. `[page|home]` validée mais `[page|cms]`
  non validée → on intègre la home, on ne touche pas à `cms`.
- Indiquer à l'utilisateur **où regarder** (dossier `screenshots/<slug>/` + liste des bandes) et
  **attendre sa réponse** avant d'enchaîner sur les phases 3→8.

**Phase 3 — Config, URLs, SEO** (§ « Configuration globale ») : crawler la prod → `config.json`
(couleurs **Figma**, reste **prod**, lat/long via **Nominatim**), `prod-urls.json`, `seo.json`
(home par locale mini). **Reporter** dans **`bin/data/config/default.yaml`** (identité, locales,
domaines, GTM, couleurs, favicons, réseaux, tél, emails, adresses géocodées, légal). Ne pas
toucher aux emails `# REQUIRED`.

**Phase 4 — Assets de marque** (§ pipeline fixtures, point 2) dans **`assets/medias/images/default/`** :
logo **SVG**, favicons (mark sur **`primary`**), **share** (visuel + logo), **email-logo en PNG**
(+ changer l'extension dans `DefaultMediasFixtures`), **preloader** (mark), **placeholder** pastel
de `primary` (remplacer dans `default`/`front/default`/`vendor`).

**Phase 5 — Polices** (§ « Polices & variables SCSS ») : récupérer **toutes** les polices (audit
familles+graisses), intégrer **en local** (`assets/lib/fonts/`), `@font-face`
(`assets/scss/front/default/fonts.scss`), variables (`assets/scss/front/default/variables.scss` :
couleurs + `$theme-colors` + `$default-bootstrap-colors` + `$font-*`), **chemin stable**
(`copyFiles` dans `webpack.config.js`), **preload** dans `templates/front/default/base.html.twig`,
**métriques `font-fallback` recalculées** pour la nouvelle police.

**Phase 6 — Base de données** (§ pipeline fixtures, point 0) : `.env.local` (`DB_NAME` = nom projet
en `_`) ; si absente : `doctrine:database:create` + `doctrine:schema:update --force`.

**Phase 7 — Fixtures (zéro dev)** (§ « Génération des fixtures ») dans `src/Service/DataFixtures/` :
`WebsiteFixtures` (pages `[page|]` + activation modules), `PageFixtures` (layouts depuis
`pages/*.json`, `importMedia()` multilingue, `addSlider()`), `MenuFixtures` (auto depuis pages +
`nav.json`/`footer.json`), `CatalogFixtures`/`NewscastFixtures` (**produits/actus depuis la prod** :
contenu, images, **caractéristiques**, **FAQ**, SEO **multilingue** ; **layout = catalogue/catégorie**,
BlockTypes **`layout-*`**), `DefaultMediasFixtures`. Puis `doctrine:fixtures:load`.

**Phase 8 — Traductions** (§ pipeline fixtures, point 7) : par bloc dans `pages/*.json` + `seo.json`,
**toutes les locales**.

---

## Au démarrage d'un projet : deux URLs à demander (OBLIGATOIRE)

> 🛑 **NE RIEN SUPPOSER (départ à froid).** Un agent qui démarre dans une nouvelle session
> ne connaît **ni** l'URL de prod, **ni** le prototype, **ni** les node-ids des pages.
> **Interdiction de présupposer** ces valeurs (elles ne se devinent pas, ne pas réutiliser
> une mémoire d'une autre session). Seules sources légitimes :
> - **Fichier Figma** = `FIGMA_FILE_KEY` (+ `FIGMA_TOKEN`) dans `.env` — la seule chose configurée.
> - **Pages à intégrer** = **découvertes** en scannant le fichier pour les calques taggés
>   **`[page|…]`** (cf. « ne parser QUE les `[page|…]` »). Jamais des node-ids supposés.
> - **URL de prod** et **URL du prototype** = **inconnues → À DEMANDER à l'utilisateur** (ci-dessous).

À la **première intégration** d'une maquette (ou dès que l'info manque), **demander
impérativement à l'utilisateur deux URLs** :

1. **L'URL du site de prod existant** → données TEXTE/config (domaines, GTM/GA,
   réseaux sociaux, légal, multilingue, redirections). Alimente `config.json`
   (`prod_url`, défaut `"URL PROD"`) et `prod-urls.json`.
2. **L'URL du prototype Figma** (`figma.com/proto/...`) → **détection des animations
   et interactions** (ouvertures de menu/overlay, hover, transitions). Sans elle, on
   ne peut pas cartographier les comportements (cf. « Lire les interactions du
   prototype »). Conserver le node-id de départ du proto.

**Ne jamais démarrer** l'interprétation sans ces deux URLs **fournies par l'utilisateur**
(ou sans avoir acté explicitement leur absence avec lui). Découvrir les pages via `[page|…]`.

> **Présentation (console).** Poser ces deux questions **en évidence sur fond bleu**
> dans la console. La sortie Markdown ne gère pas les fonds : utiliser un `printf`
> avec codes ANSI (fond bleu `\033[44m`, texte clair `\033[97m`, reset `\033[0m`),
> ex. `printf '\033[44;97;1m … \033[0m\n'`. Ces deux URLs sont les **seules**
> questions à mettre ainsi en avant.

## Règle éditoriale : jamais de tiret long « — »

Dans **tout contenu généré** (titres, textes, intros, libellés de CTA, noms d'actus/produits,
slides, descriptions…), **ne jamais utiliser le tiret long/cadratin « — » (em dash) ni « – » (en
dash)** : utiliser **exclusivement le tiret simple « - »**. À appliquer aussi bien dans les fixtures
(PHP) que dans les JSON de pages et toute copie produite. (Implémentation : remplacer `—`/`–` par `-`.)

## Toujours capturer avant d'interpréter (obligatoire)

**Avant toute interprétation d'un élément Figma, en faire le rendu image et le
regarder.** Ne jamais conclure à partir des seuls noms de calques ou de la
structure JSON : c'est une source d'erreur avérée (ex. deux `[nav]` lus comme
un doublon, alors que ce sont en réalité deux états distincts — barre fermée vs
menu déroulé).

Méthode :
1. Récupérer l'URL de rendu via l'API : `GET /v1/images/:key?ids=<id>&format=png&scale=2` (header `X-Figma-Token`, scope `file_content:read`).
2. Télécharger l'image puis l'**examiner visuellement** avant tout commentaire ou décision d'intégration.
3. En cas d'ambiguïté (variantes, états, responsive desktop/mobile), capturer **chaque** nœud concerné et comparer les rendus.

**Résolution suffisante (impératif).** Une vignette basse résolution fait rater des
sections et mal nommer les bandes (erreur avérée). Pour une page haute, ne pas se
contenter d'un rendu global réduit : **découper le rendu en bandes lisibles**
(strips horizontaux qui se chevauchent légèrement) et examiner chaque bande.
N'interpréter une zone/un élément qu'à partir d'une capture où le texte est lisible.

La lecture du JSON sert à la **structure** ; la capture sert au **sens**. Les deux
sont nécessaires, la capture vient en premier pour l'interprétation.

**Captures par bande, rangées par page (obligatoire au parsing d'une page).**
Lors du dry-run d'une page, générer **une capture par zone/bande** et les ranger
dans un **sous-dossier par page** : `.claude/skills/figma-cms/integration/screenshots/<slug-page>/`
(ex. `screenshots/home/section-home-1.png`). Chaque zone du JSON porte une
propriété `screenshot` pointant vers son image. Objectif : identifier
visuellement chaque bande et pouvoir **corriger le JSON à la main** en connaissance
de cause. La page est rendue une fois puis découpée par la géométrie des bandes
(fonctionne aussi pour les zones déduites, qui ne sont pas des nœuds Figma).

**Pour CHAQUE page taggée `[page|…]`** (pas seulement la home), générer ses captures de bandes
dans `screenshots/<slug>/` — l'utilisateur doit pouvoir **revoir visuellement chaque page**
(ex. `pages/product-view.json` ⇒ `screenshots/product-view/`). Ne jamais livrer une page sans ses captures.

**Ne jamais inclure dans une capture de bande un élément identifié autrement.**
Les éléments de layout (`[nav]`, `[footer]`, et tout élément commun à chaque page)
ne doivent **pas** apparaître dans les captures de bandes de page : borner le
découpage à la **zone de contenu** (exclure la région verticale du footer/nav).
Ces éléments de layout ont leurs **propres captures** dans un dossier commun
`.claude/skills/figma-cms/integration/screenshots/layout/` (ex. `layout/nav.png`, `layout/footer.png`),
puisqu'ils sont intégrés une seule fois et partagés par toutes les pages.

## Corréler les calques, le contenu et le contexte (pas seulement les captures)

**Règle clé.** Une capture ne suffit pas à décider d'un bloc : toujours **croiser**
(1) la **géométrie**, (2) le **nom du calque**, (3) le **contenu texte réel**
(`characters`), et (4) le **contexte projet** (cf. contextualisation « produit »).
Exemples :
- un calque nommé `CTA`/`bouton` → bloc `link`, même sans flèche visible ;
- un texte contenant le **mot-clé produit** du projet (ex. « chambre » pour un hôtel,
  « formation », « véhicule »…) signale une section/teaser de ce produit ;
- deux colonnes « 6/6 » à la géométrie peuvent être, au contenu, un **intro + média**
  (titre + texte + CTA d'un côté, image de l'autre) — le nommer pour ce qu'il est.

Surfacer le **texte** et le **rapprochement CMS** dans l'arbre (`pages/<slug>.json`)
permet cette corrélation sans dépendre des seules images.

## Heuristiques d'interprétation visuelle (atomes)

Indices récurrents à appliquer lors de la lecture d'une maquette (après capture HD,
jamais sur le seul JSON) :

- **Flèche à droite d'un texte = lien.** Le plus souvent, un texte suivi d'une
  flèche (« → », chevron, picto fléché) à sa droite est un **lien / CTA** (atome
  `link` ou `cta`), pas un simple libellé. Le valider à la capture, puis mapper la
  destination via les interactions du prototype (cf. section dédiée) ou, à défaut,
  les vraies URLs de prod (`prod-urls.json`).
- **🛑 IMPÉRATIF : lier les VRAIS médias de la maquette (jamais un média par défaut).** Chaque bloc
  média / slide de slider DOIT pointer vers l'**image récupérée de Figma** (`media/<slug>/*`), importée
  en entité `Media` via `UploadedFileFixtures::uploadedFile()`. **Ne jamais** réutiliser `share`,
  `title-header` ou `placeholder` à la place d'un visuel de la maquette. Pattern (dans `PageFixtures`) :
  un helper `importMedia('hero-boutique-hotel.jpg')` qui upload depuis `media/home/` + un helper
  `mediaBlock($col, $filename)` qui crée le bloc `media` et **remplace** la relation média par défaut
  par la vraie image ; pour un slider, `SliderMediaRelation::setMedia($this->importMedia($file))` par slide.
- **Tout CTA pointe vers une page CMS (à créer + associer).** Chaque bouton/lien d'action
  (« Découvrir », « En savoir + », « Voir plus », « Réserver »…) suppose une **page de destination**.
  S'il existe un **intitulé au-dessus** (titre de la section : « Chambres & Suites », « Le restaurant »…)
  qui permet de **nommer** cette page : **créer la page CMS correspondante** (dans `WebsiteFixtures`)
  et **associer le link à cette page** — privilégier l'association à l'**entité Page**
  (`LinkIntl::setTargetPage()` / `targetPage` du bloc `link`) plutôt qu'une simple URL ; à défaut
  (page pas encore créée au moment du build), un `targetLink` pointant vers le **code URL réel d'une
  page existante** (chemin prod) est acceptable. Ne jamais laisser un CTA pointer dans le vide.
- **Élément croppé sur la droite = `slider|splide`.** Une zone dont le contenu (le plus souvent une
  **rangée de cards**) est visiblement **coupé sur le bord droit** — avec flèches de carrousel
  précédent/suivant — est **forcément un carrousel `slider|splide`**, **sauf** si c'est un **teaser
  d'actualités** ou un **teaser de produits** (qui ont leurs propres modules `newscast-teaser` /
  `catalog-teaser`). Ne pas l'interpréter comme une simple grille de colonnes statiques. (Implémenté
  dans `PageParser` : zone `colToRight` sans module déjà présent → bloc `core-action`/`slider-view`
  template **splide** portant les cards comme slides.)
- **Suite d'images finissant par un élément croppé → zone dédiée + titre + `colToRight` + `pe-0`.**
  Dès qu'une rangée d'images alignées **se termine par un élément coupé à droite** (carrousel `splide`
  **ou** teaser actu/produit), placer l'ensemble dans **une zone DÉDIÉE** comprenant son **titre**
  (s'il existe), avec `Zone::setColToRight(true)` et **`padding-right = 0` sur la ZONE, la COL et le
  BLOC** (`setPaddingRight('pe-0')` aux trois niveaux). Ne pas répartir ces cards en colonnes statiques.

## Déduire les zones (et colonnes) sans tags `[zone]` / `[col]`

Quand le créa n'a pas posé les tags structurels, la structure doit être **déduite**.
C'est un **mode dégradé** : un `[zone]` / `[col]` explicite fait toujours foi et
lève l'ambiguïté.

Définitions :
- **Zone** = une bande horizontale pleine largeur de la page.
- **Colonne** = une subdivision verticale interne à une zone (blocs côte à côte au même `y`).

Signaux de déduction, par ordre de fiabilité :
1. **Géométrie — fonds pleine largeur** : un rectangle/frame dont la largeur ≈ largeur de page, surtout s'il **change de couleur**, marque le fond d'une bande → frontière de zone quasi certaine.
2. **Capture HD** (cf. règle ci-dessus) : indispensable pour ne pas rater une section sans fond coloré marqué, ni mal nommer une bande.
3. **Motif récurrent** : le schéma « intro (titre + texte + CTA) + média » qui se répète signale une nouvelle zone à chaque occurrence.
4. **Éléments alignés** (même `y`, même hauteur) : une rangée d'items identiques = une zone « collection » (carrousel, cartes).

Règle de fusion en cas d'ambiguïté (l'écart se joue souvent à ±1 zone) :
- Une **intro suivie directement d'un carrousel / de cartes du même thème** se compte comme **UNE seule zone** (intro + collection), pas deux.
- Un **bandeau-titre** collé au hero se rattache au hero ou à la zone suivante — ce n'est pas une zone autonome.
- En cas de doute persistant, **privilégier le regroupement thématique** plutôt que la multiplication des zones.

Toujours **annoncer que le compte est déduit** (avec sa marge) et inviter à poser
les `[zone]` pour figer la structure.

**Couleur de fond des zones (obligatoire — être rigoureux).** Pour chaque zone,
renseigner sa **couleur de background** quand il y en a une. Règles (implémentées
dans `PageParser`, champ `background`) :
- prendre le fond **qui COUVRE** la bande (rect dont la plage verticale contient le
  centre de la bande), **pas seulement** un fond commençant à son sommet — un même
  rectangle navy/teal peut couvrir **2 bandes** consécutives ;
- **exclure les nœuds `TEXT`/`LINE`/`VECTOR`** : leur fill est la couleur du texte,
  pas un fond (piège avéré : un grand filigrane de marque blanc/or pris pour un fond) ;
- `SOLID` → `#rrggbb` (`#rrggbbaa` si semi-transparent) ; `GRADIENT` →
  `gradient(linear #a,#b)` ; sinon **repli sur le fond de page** ;
- fond **IMAGE** (hero/photo pleine largeur) → `background: null` (pas de couleur unie).
- **Croiser avec la capture** de la bande : la géométrie seule trompe (overlay, image
  par-dessus une couleur). La capture tranche le fond réellement visible.

## Garder les captures synchronisées (obligatoire)

À **chaque consultation de Figma** (nouveau node lu, page/bande revue, maquette mise à jour),
**re-capturer / mettre à jour les screenshots** correspondants (`screenshots/<page>/`,
`screenshots/layout/`) pour que les captures reflètent l'état **courant** de la maquette. Ne jamais
raisonner sur des captures périmées ; une consultation Figma qui change la compréhension d'une bande
doit produire une capture à jour (export du node) avant intégration/vérification.

À **chaque** mise à jour de la structure (reclassement d'un bloc, fusion/suppression
de zone), **resynchroniser les captures** pour ne pas laisser de fichiers orphelins
ou mal rangés :
- Une bande reclassée en **layout** (newsletter, mur social…) : **supprimer** sa
  capture de `screenshots/<slug-page>/` — son rendu vit désormais dans
  `screenshots/layout/`.
- Ne jamais laisser dans `screenshots/<page>/` une bande qui n'est plus une zone de
  la page, ni pointer un descripteur layout vers une capture du dossier `home/`.
- Les captures d'éléments de layout vont **exclusivement** dans `screenshots/layout/`
  (référencées par **basename** dans `layout/*.json`, comme `nav`/`footer`).

## Éléments de layout : descripteurs JSON dédiés

Chaque élément de layout partagé (nav, footer, et tout élément commun à toutes les
pages) a son **descripteur JSON** dans `.claude/skills/figma-cms/integration/layout/` (ex. `nav.json`,
`footer.json`). Ce fichier est la **source déclarative** : il porte le mapping CMS,
les **node-ids Figma** et les **captures** (un élément peut avoir plusieurs états —
ex. nav **fermée** + **ouverte**), ainsi que le contenu (liens, CTA, contact…).

```jsonc
{
  "element": "nav",
  "cms": { "entity": "App\\Entity\\Module\\Menu\\Menu", "slug": "main", "fixedOnScroll": true },
  "captures": [
    { "label": "closed", "figmaNodeId": "…", "screenshot": "nav-closed.png" },
    { "label": "open",   "figmaNodeId": "…", "screenshot": "nav-open.png" }
  ],
  "content": { "...": "..." }
}
```

Commande de (re)génération des captures de layout (lecture seule, déclaratif) :
`php bin/console figma:capture-layout` → lit les `layout/*.json` et écrit les PNG
dans `screenshots/layout/`. **Penser à déclarer chaque état** (un nav-open vit
souvent hors page, dans un composant séparé — il ne sera pas trouvé par le parsing
de page, d'où la déclaration explicite).

## Configuration globale du site (alignée sur les fixtures)

Au-delà des pages, récupérer aussi les **données de configuration globale** quand
elles sont présentes dans la maquette, en s'alignant sur la structure de
`bin/data/config/default.yaml` (entrée des fixtures de config) :

Sources, par origine (voir la « séparation stricte des sources » ci-dessous) :
- **Depuis Figma** (design + texte présent dans la maquette) : palette `colors`
  (fills SOLID), et — si présents footer/nav — `company_name`, `phones`, `emails`,
  `addresses`, présence des `social_networks`.
- **Depuis la prod** (texte/config, voir la section crawl) : `domains`, `apis`
  (GTM/GA), URLs réelles des `social_networks`, `legals`, et complément
  `phones`/`emails`/`addresses`.
- **Coordonnées géo (lat/long)** : **géocoder l'adresse** via l'API **OpenStreetMap /
  Nominatim** (`https://nominatim.openstreetmap.org/search?street=…&city=…&postalcode=…&country=…&format=json`,
  avec un `User-Agent` identifiant l'agence). Pas besoin de les saisir à la main.
- **Vraiment non dérivables → `null`** : `favicons`, `fonts`, `googleMapUrl`,
  horaires, et données société absentes des mentions légales (DPO, gérant, hébergeur…).

Artefact : `.claude/skills/figma-cms/integration/config.json` (mêmes clés que `default.yaml`, éditable).
**Contraintes** : chaque section porte un `_source` (`figma` | `prod`) ; les
**couleurs viennent EXCLUSIVEMENT de Figma** (jamais de la prod) ; `prod_url` par
défaut = `"URL PROD"` ; les mappings sémantiques de couleurs (primary/secondary…)
restent **à confirmer** manuellement (la maquette donne les hex, pas leur rôle).

### Reporter la config dans les fixtures (objectif : zéro dev d'injection)

Une fois `config.json` validé, **mettre à jour `bin/data/config/default.yaml`**
(entrée des fixtures de config) pour que `doctrine:fixtures:load` reconstruise le
site **sans dev supplémentaire** : `company_name`, `locale`/`locales_others`,
`domains`, `apis` (GTM), `colors` (mapping validé), `favicons` (sur la couleur
primaire), `social_networks`, `phones`, `emails`, `addresses` (avec lat/long
géocodés), `legals`.
- **Ne pas toucher** aux entrées emails marquées `# REQUIRED` (`support`,
  `no-reply`) : ce sont des emails système gérés par l'agence.
- **Domaine local** (`*.local`) : laisser un placeholder à ajuster au vhost ;
  domaines prod listés à `false` (activés selon l'environnement).

> **OBLIGATOIRE — Au moment de générer le fichier de config propre au projet,
> demander à l'utilisateur l'URL du site de prod existant.** Par défaut, le champ
> `prod_url` vaut le placeholder `"URL PROD"` tant que l'utilisateur ne l'a pas
> fournie.

### Séparation stricte des sources (règle clé)

- **Site de prod = données TEXTUELLES / de configuration uniquement** : URLs réseaux
  sociaux, noms, **GTM / GA / analytics**, emails, téléphones, adresse, liens et
  mentions légales, domaines, raison sociale… Le `prod_url` resservira à d'autres
  moments pour ce type d'infos.
- **RIEN issu de la prod ne concerne l'intégration / le design.** Pas de couleur,
  pas de mise en page, pas de structure, pas de média repris du site en ligne.
- **Figma = seule source du design** : couleurs, structure (zones/cols/blocs),
  layout, médias proviennent **exclusivement** de la maquette.

Ne jamais croiser les deux : un hex de couleur vient de Figma ; un ID GTM vient de
la prod. Jamais l'inverse.

### Obligation : si `prod_url` est renseigné, alimenter prod-urls.json ET seo.json

**Dès qu'une URL de prod est connue**, `prod-urls.json` **et** `seo.json` **doivent** être
alimentés (au moins le SEO de la **home par locale**) — sinon le process est **incomplet**.
Ne pas les laisser à l'état de gabarit vide quand `config.json.prod_url` est rempli.

### Récupérer toutes les URLs de prod (au crawl)

Au moment du crawl du site de prod, récupérer **toutes les URLs** via le
**sitemap** (le trouver d'abord dans `/robots.txt` — souvent ailleurs que
`/sitemap.xml` ; `robots.txt` liste aussi les sitemaps par langue/domaine).

**Détecter aussi le multilingue** : lire les `hreflang` de la home (langue → URL/domaine)
et/ou les sitemaps multiples du `robots.txt`. Pour **chaque langue**, récupérer son
sitemap et ses URLs. Renseigner `locale` + `locales_others` dans `config.json`
(et `domains` par langue).

Stocker le tout dans `.claude/skills/figma-cms/integration/prod-urls.json`, **groupé par langue** :
`{default_locale, locales:[...], total, by_language:{<lang>:{domain, sitemap, count, urls:[{url,path}]}}}`.
Utilité : mapper les pages, cibler les liens de menu vers de vraies URLs, préparer
les redirections, gérer le multilingue. Donnée texte/config — pas du design.

### Récupérer le SEO de toutes les URLs

Pour **toutes** les URLs trouvées (toutes langues), crawler chaque page et extraire
son **SEO** : `title`, meta `description`, `keywords`, `robots`, `canonical`,
Open Graph (`og:title` / `og:description` / `og:image`), `h1`. Stocker dans
`.claude/skills/figma-cms/integration/seo.json`, groupé par langue
(`by_language:{<lang>:{domain, count, pages:[{url, path, seo:{...}}]}}`).
Signaler les **manques SEO** rencontrés (page sans `title`, sans `description`…) :
c'est utile pour la reprise/refonte. Donnée texte/config — pas du design.

## Génération des fixtures (objectif : zéro dev d'injection)

Le livrable final n'est pas que le dry-run : **modifier EFFECTIVEMENT les fixtures du projet**
(écrire le code dans `src/(Service/)DataFixtures/`, pas seulement documenter) pour que
`php bin/console doctrine:fixtures:load` reconstruise le site, sans dev. ⚠️ Tant que
`PageFixtures`, `MenuFixtures`, `NewscastFixtures`, `CatalogFixtures`… ne sont pas modifiées,
le chantier n'est **pas** fait. Cibles :

0. **Base de données** → `.env.local` : renseigner la connexion (`DB_HOST`, `DB_USER`, `DB_PASSWORD`,
   `DB_PORT`, `DB_VERSION`) et **`DB_NAME` = nom du projet avec des `_`** (ex. projet `mon-projet-2026`
   → `DB_NAME=mon_projet_2026`). **Si la base n'existe pas**, la créer puis monter le schéma :
   ```
   php bin/console doctrine:database:create
   php bin/console doctrine:schema:update --force
   ```
   (puis `doctrine:fixtures:load` une fois les fixtures prêtes).
1. **Config** → `bin/data/config/default.yaml` (cf. section config + Nominatim ci-dessus).
2. **Médias par défaut / logos / favicons / partage** → `DefaultMediasFixtures` + dossier
   `assets/medias/images/default/` : remplacer les fichiers par défaut du CMS (catégories
   `logo`, `footer`, `email`, `admin`, `favicon*`, `share`, `title-header`, `social-network`…)
   par ceux du projet. **Logo en SVG** (extrait de la maquette, souvent dans la nav). **Générer**
   les favicons (toutes tailles, à partir du **mark** du logo sur un fond de la **couleur `primary`**
   du projet) et l'**image de partage** (1200×630) = **un visuel clé + le logo incrusté**
   (toujours mettre le logo sur le share).
   - **`email-logo` en PNG** (les clients mail ne rendent pas le SVG) : générer `email-logo.png`
     et **modifier l'extension dans `DefaultMediasFixtures`** (`email-logo.svg` → `email-logo.png`).
   - **`preloader`** : reprendre le **mark** du logo (SVG), affiché pendant le chargement.
   - Logo blanc → favicons/preloader sur fond de marque ; `footer-logo` = lockup (footer sombre).
   - **Privilégier la couleur `primary`** pour les fonds des éléments de marque (favicons, splash…).
   - **`placeholder.jpg`** : recolorer le placeholder gris du CMS dans les **tons pastel de la
     couleur `primary`** du projet, puis **scanner tout le projet et remplacer chaque
     `placeholder.jpg`** (dossiers `assets/medias/images/{default,front/default,vendor}` ; laisser
     l'`admin/`). Garder le motif (image + loupe) et les dimensions (3840×2160).
3. **Pages + blocs** → `PageFixtures` : créer Page → Zones → Cols → Blocs **depuis
   `pages/<slug>.json`** (chaque bloc porte déjà son `cms`/`addBlock()`). **Médias = les images
   RÉCUPÉRÉES** (`media/<slug>/*` importées en entités `Media`), **jamais** un média par défaut
   (`title-header`/placeholder) : importer chaque visuel et le rattacher au bloc/slide correspondant.
   **Import multilingue obligatoire** : via `UploadedFileFixtures::uploadedFile()` (depuis le chemin
   disque récupéré), puis **un `MediaIntl` par locale** (`getAllLocales()`) ; pour un slider, **une
   `SliderMediaRelation` par locale** et par slide. Implémenté : `PageFixtures::importMedia()` / `addSlider()`.
   **Ne parser/générer QUE les nœuds taggés `[page|…]`** (pas les variantes/mobile en tant que
   pages distinctes). Le slug vient de la variante (`[page|product-view]` → `product-view`).
   - **Maquette sans modèle `[page|cms]`** : si la maquette ne fournit pas de gabarit de page CMS
     générique, en **créer un dans le style de la maquette** (zones/blocs/typo/couleurs observés) et
     l'appliquer aux pages correspondantes (les items de nav qui n'ont pas d'écran dédié dans Figma).
   - **Récupération du contenu réel depuis la prod** : pour **chaque page générée** dans les fixtures,
     tenter la **correspondance avec une URL de prod** (`prod-urls.json`). Si match → **récupérer et
     intégrer le contenu réel** : textes, **images**, **FAQ**, etc. (dans toutes les langues, cf. `seo.json`
     / traductions). Pas de contenu factice quand le réel existe.
3ter. **Activer les BlockTypes nécessaires absents de la mise en page de Page.** Une Page n'a accès
   qu'aux BlockTypes des catégories `content` + `global` **ET** rattachés à la **configuration du site**
   (`LayoutFixtures::getConfiguration()` filtre sur `configuration->getBlockTypes()`). Or certains
   BlockTypes sont **désactivés par défaut** hors devMode : liste `BlockTypeFixtures::DISABLED`
   (`alert`, `blockquote`, `card`, `collapse`, `icon`, `modal`, `share`, `counter`…). **Si la maquette
   utilise un de ces blocs** (ex. `[alert]`), il faut l'**activer au moment des fixtures** : le **retirer
   de `BlockTypeFixtures::DISABLED`** (ou l'ajouter à la configuration) pour qu'il soit rattaché à la
   config → repris par `LayoutFixtures` dans la mise en page de Page → utilisable dans `addBlock()`.
   Sinon `addBlock($col, 'alert', …)` n'a aucun effet (BlockType absent de la config de Page).
3bis. **Menus** → `MenuFixtures` : créer les `Menu` (`main`, `footer`) et leurs `Link`/`LinkIntl`
   **depuis `layout/nav.json` et `layout/footer.json`** (`content.menu`, `ctas`, `languages`,
   hiérarchie via `children`). Capturer aussi l'état **`[nav|mobile]`** (menu mobile) → `screenshots/layout/nav-mobile.png`.
4. **Produits / actualités** → `CatalogFixtures` / `NewscastFixtures` : alimenter l'entité
   « produit » du projet (contextualisée) et les actus depuis les pages listing/fiche.
   **Si le module catalogue est actif (`ROLE_CATALOG`), aller sur le site de prod récupérer
   les PRODUITS réels** (ici les chambres) — titre, description, prix/infos, **images** — et les
   injecter dans `CatalogFixtures` (idem actus → `NewscastFixtures` depuis la prod). Pas de données démo.
   - **Faire matcher les contenus avec la NOUVELLE maquette** (structure/blocs de la fiche produit Figma),
     pas une simple recopie de l'ancien site.
   - **Récupérer le SEO dans TOUTES les langues** (title/description/og par locale, via les domaines/hreflang).
   - **Récupérer les images** des produits (haute déf, mêmes règles d'export/optimisation que les médias).
   - **Récupérer les CARACTÉRISTIQUES** du produit (pour une chambre : superficie, lits/capacité, vue,
     équipements, services…) et les intégrer en champs/attributs du produit (toutes langues).
   - ⚠️ **`Feature` / `FeatureValue` = valeurs RÉELLES, jamais du faker.** Le `CatalogFixtures` par
     défaut génère des features/valeurs aléatoires (`$this->faker->text()`) et **affecte les valeurs
     aux produits via `array_rand($features, 45)`** : **les DEUX sont à remplacer** par les vraies
     caractéristiques issues de la **maquette (fiche product-view)** + **prod** (section « Services »
     de la fiche produit). Méthode :
     1. Définir les `Feature` réelles et leurs `FeatureValue` réelles (ex. hôtel : *Superficie*
        `17 m²…52 m²`, *Capacité*, *Vue*, *Terrasse*, *Équipements* `Wi-Fi/Climatisation/Minibar…`,
        *Services inclus* `Accès hammam/salle de sport/piscine…`).
     2. **Indexer** les `FeatureValue` créées par `[feature][label]`.
     3. Pour **chaque produit**, créer les `FeatureValueProduct` **en sélectionnant les valeurs qui le
        décrivent vraiment** (jamais un tirage aléatoire) — typiquement : superficie/capacité/vue propres
        au produit + équipements communs + services inclus de la fiche.
     4. **Supprimer `array_rand(...)`** : avec un set de valeurs réelles (souvent < 45), il lèverait
        une exception et **ferait échouer tout le `fixtures:load`**.
   - **Nommer le catalogue/listing selon le CONTEXTE projet** (jamais « Principal ») : un hôtel →
     `Chambres & Suites`, une école → `Formations`, un concessionnaire → `Véhicules`… L'`adminName` du
     `Catalog` **et** du `Listing` portent ce nom métier (idem catégorie d'actus : nom contextuel).
4bis. **Slugs (identifiants) en ANGLAIS — codes URL alignés sur la PROD.** Règle transversale à
   **toutes les entités** générées (pages, produits, catégories, features, listings, sliders, menus,
   forms, search, map…) :
   - **Slug / identifiant interne = anglais** (ex. produit `superior-room`, feature `surface`,
     `included-services`, listing `rooms`, slider `home-hero`). Ne jamais dériver un slug d'un libellé FR.
   - **Code URL public (`Seo\Url::setCode`) = chemin de PROD** quand il existe (continuité SEO) :
     mapper chaque page/produit/actu sur son chemin réel via `prod-urls.json`
     (ex. une page listing → son chemin prod ; une actu → son slug prod).
     À défaut de chemin prod, dériver le code du **slug anglais**.
   - Concrètement : séparer **`reference`/`slug` (EN, identifiant)** du **`url` (chemin prod, code public)**
     dans les paramètres de page (`WebsiteFixtures::getPagesParams`) et passer le code à `generateUrl()`.
   - Conserver les slugs internes déjà anglais du CMS (`main`, `footer`, `contact`…) ; ne franciser aucun slug.
4ter. **FAQ depuis la prod** : si le site de prod comporte une **FAQ** (questions/réponses),
   la **réintégrer** — activer `ROLE_FAQ` et alimenter le module FAQ (ou un bloc `collapse`) avec
   les paires question/réponse réelles, dans toutes les langues. Plus largement : tout **contenu de
   module détecté sur la prod** (FAQ, agenda, témoignages…) est à récupérer et à recâbler sur le module idoine.
5. **Activation des modules** → `WebsiteFixtures` : déplacer les `ROLE_*` nécessaires de
   `OTHERS_MODULES` vers `DEFAULTS_MODULES` **en fonction des blocs/modules réellement utilisés**
   (ex. un slider → `ROLE_SLIDER` déjà actif ; un catalogue → `ROLE_CATALOG` ; une newsletter →
   `ROLE_NEWSLETTER` ; un mur social → vérifier qu'un module existe avant de l'activer).
6. **SEO par locale** → `SeoFixtures` (ou le SEO de la page) : si une **URL de prod** est
   disponible, récupérer et injecter **au moins le SEO de la home pour chaque locale**
   (title, meta description, og…), crawlé sur le domaine de chaque langue.
7. **Traductions** → **dans le JSON de la page** (`pages/<slug>.json`), **par bloc** : chaque bloc
   porte son texte `fr` + ses traductions par locale (ex. `"translations": { "en": …, "es": …, "zh": … }`),
   pour **le titre ET tout autre contenu** (titres, textes, CTA, slides ; le SEO va dans `seo.json`).
   Les fixtures **consomment** ce JSON (Intl des entités) — elles ne traduisent pas elles-mêmes.
   Source : contenu réel de prod par langue s'il existe ; sinon traduire le fr.

Règle : **ne jamais inventer** un slug/role/entité — toujours le vérifier dans les fixtures
réelles (`BlockTypeFixtures`, `ActionFixtures`, `WebsiteFixtures`, `DefaultMediasFixtures`,
`SeoFixtures`, `TranslationsFixtures`).

## Intégration front (HTML / CSS / JS) — APRÈS les fixtures

Une fois les fixtures générées (Page → Zones → Cols → **Blocks**), l'intégration visuelle des
sections se fait **exclusivement** dans :
- **HTML/Twig** : `templates/front/` (jamais ailleurs)
- **CSS/SCSS** : `assets/scss/front/`
- **JS** : `assets/js/front/`

### 🗺️ Cartographier le CSS natif AVANT de styler (surtout la 1ʳᵉ intégration) — BLOQUANT
Le projet pré-existant embarque **beaucoup de modules natifs** dont le SCSS (`assets/scss/front/default`)
peut **écraser** la nouvelle intégration (règles à forte spécificité, sélecteurs d'élément, **`!important`**).
**Avant de styler**, en prendre connaissance :
```
node .claude/skills/figma-cms/tooling/css-baseline.mjs --out .claude/skills/figma-cms/integration/css-baseline.md
```
→ liste les overriders potentiels (`!important` sur props sensibles, sélecteurs `h1-h6/p/a/body/:root/*`,
larges `[class*=]`) avec **fichier:ligne + sélecteur + propriété**, en **séparant les overriders TOUJOURS
ACTIFS** (à traiter) **de ceux CONDITIONNÉS** par une classe d'état sur body/html (mode accessibilité,
thème `[data-bs-theme]`…) — **inactifs par défaut**, donc hors-sujet sauf si ce mode est posé.
**Le consulter avant d'écrire du CSS**.

**Vigilance écrasement (à chaque bande)** : après build, **vérifier que le CSS intégré GAGNE** réellement
(rien de natif ne le surclasse). Stratégie, dans l'ordre :
1. **Scoper par l'`#id` du composant** (le `customId` → `id`, cf. ci-dessous) : un `#home-hero .title`
   (1,1,1) **bat** les classes natives **sans `!important`**.
2. **Réécrire proprement** le CSS d'un composant de layout plutôt qu'empiler des overrides.
3. Si un override **ne prend pas** : inspecter le **CSS compilé** pour trouver la **règle gagnante**
   (spécificité/ordre/`!important`) et la battre proprement.
4. `verify-styles.mjs` **échoue si le rendu est écrasé** (computed ≠ token) → c'est le filet runtime ;
   `css-baseline.md` est le filet **proactif**.

### Fichiers de templates LAYOUT (où intégrer)
- **Menu principal** : `templates/front/default/actions/menu/main.html.twig`
- **Menu footer** : `templates/front/default/actions/menu/footer.html.twig`
- **Footer (bloc complet)** : `templates/front/default/include/footer.html.twig`
C'est dans **ces fichiers** qu'on réécrit le HTML du layout pour matcher la maquette (mega-menu, footer).

### `customId` sur Zone / Col / Block (cibler chaque élément) — OBLIGATOIRE
- À la génération des layouts, **affecter un `customId`** explicite et parlant sur chaque **Zone**,
  **Col** et **Block** notable : `$zone->setCustomId('home-hero')`, `$col->setCustomId(...)`,
  `$block->setCustomId(...)`. Le `customId` est rendu en `id="…"` côté front → on peut **styler /
  cibler chaque élément individuellement** (SCSS, JS, captures) et **travailler bande par bande**.
- **Convention de nommage** : **en ANGLAIS**, **kebab-case avec des tirets `-`** (jamais d'espace,
  `_` ou `--`) : `<page>-<section>[-<element>]`, ex. `home-hero`, `home-rooms`, `home-rooms-slider`,
  `product-hero`, `footer-partners`.
- **Tenir un registre dans le md PROJET** (`.claude/skills/figma-cms/integration/PROJECT.md`) : pour chaque `customId`,
  noter ce que c'est (page, bande, rôle) afin de pouvoir reprendre n'importe quel élément un par un.

### Md PROJET (dossier `integration/`) — à créer et tenir à jour
Le dossier projet est `.claude/skills/figma-cms/integration/` (assets + mémoire du projet courant),
distinct du playbook **générique** (ce fichier). Il contient notamment :
- **`PROJECT.md`** : spécificités techniques — file key Figma + node-ids, mapping `customId` → élément,
  médias extraits (façade, logos…), couleurs/polices, registre, écarts/TODO par bande.
- **`project-requests.md`** : **demandes spécifiques du client/chef de projet** — **style, ton,
  animations, ambiance**, exceptions. ⚠️ **Enrichir ce fichier au fil des demandes** (chaque nouvelle
  consigne ponctuelle projet y est consignée pour mémoire), afin de la respecter sur toute la suite.

Le playbook + `models/` restent **génériques et réutilisables** ; le dossier `integration/` est la
**mémoire de travail propre au projet**.

### Hero / `title-header` — `titleForce = 1` (un seul H1 par page)
Dans la section **hero d'une page** (bloc `title-header`), le titre doit être en **`titleForce = 1`**
(balise `<h1>`) — c'est le titre principal de la page. Les titres de bandes/sections suivantes sont
en `titleForce = 2`+ (`<h2>`…). Un seul `<h1>` par page (SEO/accessibilité).
- ⚠️ **Hero rendu via un SLIDER** (ex. `slider-view` / template `main-home`) : le titre du hero est
  rendu `<h{{ titleForce }}>` à partir de **l'intl du SLIDE** (`MediaRelationIntl`), pas d'un bloc
  `title`/`title-header`. Le défaut est **`2`** → il faut **explicitement `$intl->setTitleForce(1)`**
  sur la `MediaRelationIntl` du premier slide du hero, sinon le titre reste `<h2>`. Ne **pas** mettre
  `titleForce(1)` sur les autres sliders/bandes (un seul `<h1>`).

### Marges / paddings — par les PROPRIÉTÉS des entités (Zone/Col/Block)
- Gérer les espacements via les **propriétés des entités** (`setMarginTop`/`setMarginBottom`,
  `setPaddingTop`/`setPaddingBottom`/`setPaddingLeft`/`setPaddingRight`…), **pas** en CSS custom.
- **Côtés gauche/droite** : utiliser des **PADDINGS**, **jamais de marges** (`setPaddingLeft`/`Right`,
  ex. `pe-0`/`ps-0`) — sauf si des **marges NÉGATIVES** sont nécessaires (débordement/chevauchement
  voulu, ex. `colToRight`, image qui déborde) où la marge (négative) est alors légitime.
- Vertical : marges (`mt-*`/`mb-*`) ou paddings selon le besoin, toujours via les propriétés.

### Contrôles de slider (flèches / indicateurs) — activation entité + accessibilité
- L'affichage des **flèches** et **indicateurs** est **administrable via l'entité `Slider`**
  (`setControl(bool)`, `setIndicators(bool)`) → activer/désactiver selon la maquette.
- **Sliders plein écran (hero, bandes fullscreen)** : si la maquette ne montre **ni flèches ni
  indicateurs**, les **retirer visuellement** (via les flags entité / CSS).
- ⚠️ **ACCESSIBILITÉ — toujours garder les flèches de contrôle dans le DOM**, même absentes de la
  maquette : navigation clavier / lecteurs d'écran. Si elles ne doivent pas se voir, les rendre
  **discrètes/visuellement masquées** (`sr-only`/opacité) **sans les supprimer du DOM** ni casser
  leur focus. Ne jamais retirer purement le contrôle clavier d'un carrousel.

### Slider template `main-home` = le HERO
Le template de slider **`main-home`** correspond en pratique au **HERO** de la page (grande bannière
plein écran en tête). **S'il y a un hero sur la maquette**, c'est un `Slider` `setTemplate('main-home')`
(image plein écran + overlay titre/h1). Les autres bandes-images/carrousels utilisent `bootstrap`
(carrousel multi-slides), `splide` (cartes), etc. — pas `main-home`.
- **Ajustement OBLIGATOIRE des marges/paddings sur la maquette** : une intégration n'est pas finie tant
  que les espacements (verticaux entre bandes, intérieurs de zones/cols, gouttières) ne sont pas
  **calés sur la maquette** (mesurer l'`absoluteBoundingBox` / l'écart `y` entre frames Figma, puis
  régler via les propriétés d'entités). Vérifier **en comparaison côte à côte** (cf. RIGUEUR) — le
  rythme vertical et les respirations latérales font partie du rendu, pas seulement le contenu.

### ⚠️ STRUCTURE SCSS — un composant = SON fichier (RIGUEUR, zéro mélange)
Le CSS d'un élément doit vivre **dans le fichier dédié à CET élément**, jamais dans le fichier d'un
autre. Interdit de mettre du CSS **newsletter dans `_socialwall.scss`**, du footer dans la nav, etc.
- Nav → `layout/_navigation.scss` · Footer → `layout/_footer.scss` · Social wall → `layout/_socialwall.scss`
  · Newsletter → `components/form/_newsletter.scss` · Boutons → `components/_button.scss` (+ `utilities/_mixin-button.scss`)
  · CSS par page → `templates/<page>.scss` · Composant réutilisable → `components/<composant>.scss`.
- Le **nom du fichier doit refléter son contenu** : on doit pouvoir deviner où est un style sans chercher.
- Si on trouve du CSS mal rangé, le **déplacer** dans son fichier propre (et non l'y laisser « parce que ça marche »).

### Emplacement des SCSS de templates de pages
Les styles **par template de page** vivent dans `assets/scss/front/default/templates/`
(ex. `home.scss`, `error.scss`…). Chaque fichier est rattaché à un entrypoint Encore via son
`templates/<page>.js` (ex. `assets/js/front/default/templates/home.js` importe `templates/home.scss`).
Mettre le CSS d'une page dans son fichier de template dédié (pas dans un SCSS global), et vérifier
que l'entrypoint correspondant est bien chargé sur la page.

### Hauteurs plein écran : privilégier `dvh` plutôt que `vh`
Pour les hauteurs « plein écran » (hero, overlays), utiliser **`100dvh`** (dynamic viewport height)
plutôt que `100vh` : `dvh` tient compte des barres d'UI mobiles (adresse/onglets) qui apparaissent et
disparaissent, évitant le décalage/scroll parasite sur mobile. Idem `svh`/`lvh` selon le besoin.

### CSS — fidélité de mise en forme + factorisation (IMPORTANT)
- **Respecter impérativement la mise en forme de la maquette** : **graisses** (`font-weight`),
  **couleurs**, **font-sizes**, interlignages, letter-spacing, casse… relevés dans le JSON Figma
  (`fontWeight`, `fills`, `fontSize`, `lineHeightPx`, `letterSpacing`, `textCase`). Ne pas approximer.
- **Penser le CSS GLOBAL** : si plusieurs éléments partagent la **même mise en forme** (mêmes titres
  de bande, mêmes sous-titres script, mêmes boutons…), **factoriser** une seule règle / classe
  réutilisable (ou variables/mixins) plutôt que dupliquer par section. Réutiliser les variables du
  projet (`$font-*`, couleurs, `$theme-colors`).
- **Ne pas alourdir le CSS** : éviter les règles redondantes, les sur-spécifications et le copier-coller.
  Une mise en forme récurrente = un style commun ; les particularités de section = surcharge minimale.

### ⚠️ Ne pas CUMULER marges utilitaires Twig (`mt-3`…) et `margin` SCSS
Des templates partagés ajoutent des classes utilitaires de marge en dur (ex. `class="introduction
text-bold mt-3"`). **Ne pas empiler** une `margin-top` SCSS par-dessus : soit on s'appuie sur
l'utilitaire, soit on neutralise (Bootstrap `mt-3` = `margin-top:1rem !important`, donc une `margin`
SCSS sans `!important` est **ignorée**, et avec `!important` elle **s'additionne visuellement** au
lieu de remplacer → décalage). Choisir UNE source : retirer/écraser l'utilitaire (`mt-0`) puis poser
la marge voulue en SCSS, ou ajuster via l'utilitaire. Vérifier l'espacement réel au rendu.

### Configurer `variables.scss` (tailles de titres, couleurs par fond `$elements`, boutons)
Beaucoup de réglages globaux passent par `assets/scss/front/default/variables.scss` — **les configurer
là plutôt que de surcharger bande par bande** :
- **AVANT de styler — relever le DESIGN SYSTEM nommé (source de vérité)** :
  `node .claude/skills/figma-cms/tooling/figma-named-styles.mjs [--node <page>]`. Si la maquette
  utilise des **styles nommés** (dictionnaire `styles` du fichier, via `file_content:read` — aucun
  scope de plus), il en sort la **palette** (→ `$creme`, `$gold`… dans `variables.scss`) et l'**échelle
  typo nommée** (`H1-H4`, `Sous-titre Hn` script, variantes Mobile, avec tracking/lh/casse). C'est la
  **référence** : caler `$font-size-h*` / la palette dessus ; `reconcile-typography` ne sert plus que pour
  les **textes NON stylés** (et explique les « orphelins » = styles nommés type `Sous-titre H3 = 54px`).
  Enchaîner : `figma-named-styles.mjs --json named.json` puis `reconcile-typography.mjs … --named named.json`
  → chaque taille est **annotée de son style nommé** et les « orphelins » qui sont des styles nommés
  (ex. `54px = Sous-titre H3`, `14px = H4`) sont requalifiés en **classe dédiée** (≠ vrais orphelins anonymes).
- **AVANT de styler — réconcilier les COULEURS** :
  `node .claude/skills/figma-cms/tooling/reconcile-colors.mjs integration/figma-tokens.<page>.json --named named.json`
  confronte les hex de la page à la **palette nommée** ET aux **variables SCSS existantes** → indique
  pour chaque couleur le `$slug` nommé + la **variable SCSS déjà définie** à réutiliser (ex. Gold→`$primary`,
  Bleu→`$secondary`), ce qu'il faut **ajouter**, et les couleurs **anonymes** (one-off / asset à vérifier).
- **AVANT de styler — réconcilier les échelles** : marges/paddings via
  `node .claude/skills/figma-cms/tooling/reconcile-margins.mjs integration/figma-tokens.<page>.json`
  (propose le token `pt-md`/`pe-sm`/`mb-xs`… par axe + signale les orphelins ; ne JAMAIS poser de px en
  dur — cf. `models/mapping-blocktypes.md` § Marges). Puis la typo (ci-dessous).
- **AVANT de styler — réconcilier l'échelle typo** : lancer
  `node .claude/skills/figma-cms/tooling/reconcile-typography.mjs integration/figma-tokens.<page>.json`.
  Il confronte les `fontSize` de la page à l'échelle du projet (`$font-size-h1..h6`, base, `.fz-*`…) et
  **liste les tailles ORPHELINES** (aucun slot → snap silencieux sur 16px). Pour chaque orpheline
  récurrente, **ajouter une entrée à `$font-sizes-app`** (→ classe `.fz-*`) ou une variable dédiée,
  AVANT d'intégrer — sinon la taille sera perdue. (Les tailles « géantes » décoratives = cas one-off.)
- **Tailles des titres** (`h1`–`h6` / échelle de titres) calées sur les `fontSize` relevés en dev mode.
- **Couleur des différents éléments PAR COULEUR DE FOND** via la map **`$elements`** : définir, pour
  chaque fond (`bg-primary`, `bg-navy`, `bg-teal`, `bg-light`…), la couleur de `title`/`sub-title`/`text`/
  `link`/`address`… → le bon contraste s'applique automatiquement à toutes les bandes de ce fond
  (au lieu de répéter des règles `#zone-x .title { color }`).
  - ⚠️ **Piège (texte sur fond coloré)** : ce système génère un gros sélecteur
    `.bg-primary strong/span/small/p/i/figcaption{ color:#fff }` (et un reboot global `span,small,strong{color:#141414; font-size:1rem}`). Dans un composant **layout** custom (footer/nav) sur fond coloré,
    les `<strong>/<span>/<small>` **bruts héritent de cette couleur générée** (souvent `#fff`) — qui peut
    différer du token maquette (ex. crème `#f4f0f1`) — et leur `font-size` est **remis à 1rem**. Si la
    teinte/taille relevée diffère, **scoper l'override par l'`#id` du composant** (`#footer strong,#footer span{color:$light;font-size:…}`) : l'ID (1,x,1) **bat** le sélecteur de classes (0,3,1) → **pas de `!important`**. Toujours **mesurer la couleur réelle** de ces nœuds (`getComputedStyle`), pas juste celle du conteneur.
- **Boutons** : couleurs/variantes (`$theme-colors`, styles `btn-*`) selon la charte.
Relever les valeurs dans `integration/figma-styles.md` puis les **reporter dans `variables.scss`**.

**Où sont gérés les BOUTONS (fichiers CSS) :**
- **Couleurs/variantes** (par nom : `primary`, `white`, `dark`…, pleins ET outline) → map **`$buttons`**
  dans `assets/scss/front/default/variables.scss` (clés `bg`/`color`/`*-hover`/`bg-outline`/`color-outline`…).
- **Styles de base des boutons** (pleins) → `assets/scss/front/default/components/_button.scss`.
- **Boutons OUTLINE** : gérés **à la fois** dans `components/_button.scss` **ET**
  `assets/scss/front/default/utilities/_mixin-button.scss` (mixin de génération) → ajuster les deux.
- Préférer un `btn btn-<couleur>` / `btn btn-outline-<couleur>` (configuré via `$buttons`) à une classe
  `link` ad hoc ; ne styler en SCSS de page que les particularités (ex. filets en pseudo-éléments).

### Responsive : traiter un MAXIMUM de tailles d'écran
Pour un responsive **parfait**, ne pas se limiter à desktop : **traiter le plus de breakpoints possible**
(mobile S/M/L, tablette portrait/paysage, petit laptop, large desktop) via la fonction `mediaQuery()` du
projet. Vérifier chaque bande **à plusieurs largeurs** sur Chrome (≥320, 375, 768, 992, 1200, 1440, 1920…)
et ajuster tailles/espacements/overlays à chaque palier. Un rendu « iso » uniquement en 1440px n'est pas terminé.

### CSS pensé en MOBILE FIRST
Écrire le CSS **mobile first** : styles de base = mobile (sans media query), puis enrichir vers le
haut avec la fonction `mediaQuery()` du projet en bornes **`min-*`** (`min-md`, `min-lg`…). Éviter de
partir du desktop pour redescendre (`max-*`) sauf exception ponctuelle justifiée. Les utilitaires
Bootstrap sont déjà mobile first (`d-lg-flex`, `col-12 col-lg-6`…) : s'appuyer dessus en priorité.

### Mécanique d'intégration : où agir pour modifier un rendu
Principe : un rendu se modifie en remontant la chaîne **BlockType → Action → template Twig → classes
HTML → SCSS**. Le **détail des correspondances** (quel tag/BlockType → quel slug → quel template Twig
→ quelles classes → quelle cible SCSS), avec exemples concrets (slider `slider-view`, card dédiée par
condition sur l'id du slider, teasers…), est documenté et **enrichi dans `models/mapping-blocktypes.md`**
(pas ici). Côté CSS : styler via les classes exposées par le template (ex. `slider-container-<slug>`,
`carousel-<slug>`, `zone-<customId>`) dans le SCSS de page/composant (cf. emplacements ci-dessus).

### Classes/ID : s'appuyer sur la safelist PurgeCSS ou des classes déjà dans le HTML
Le build front **purge le CSS non utilisé** (PurgeCSS, cf. `webpack.config.js`). PurgeCSS ne scanne
que `templates/{front/default,core,components,gdpr}/**/*.html.twig` + une **safelist** de patterns.
Conséquences impératives :
- **Privilégier des classes/ID déjà présents dans le HTML rendu** (issus des templates) ou **couverts
  par la safelist** (ex. patterns `card-*`, `carousel-*`, `zone-*`, `bg-*`, `btn-*`, `text-*`,
  `introduction`, `description`, `container`, marges/paddings `m*-`/`p*-`…). Une classe couverte par
  la safelist survit **même si** elle n'apparaît pas encore dans un template.
- Si une nouvelle classe **n'est ni dans le HTML scanné ni dans la safelist**, elle sera **purgée** :
  soit la nommer pour matcher un pattern existant (ex. préfixe `card-`), soit l'ajouter à `safeList()`.
- **Créer un nouveau template HTML ⇒ relancer `yarn build`** : PurgeCSS ne (re)connaît les classes/ID
  d'un template que s'il était présent **au moment du build**. Un template créé après le dernier build
  verra ses classes purgées tant qu'on n'a pas rebuildé.

### Penser à mettre à jour `ThumbnailFixtures.php` (formats de génération d'images)
`src/Service/DataFixtures/ThumbnailFixtures.php` définit les **formats de redimensionnement/recadrage**
générés pour chaque contexte d'image, mappés par `(classe d'entité, action, filtre, écran)` —
ex. `Slider::class 'view' <sliderId>`, `Newscast 'teaser' <teaserId>`, `BlockType media`, `title-header`…
**Dès qu'on ajoute/retaille un contexte d'image** (nouveau slider, cartes d'un format précis, hero
plein écran, teaser…), **mettre à jour ces configs** pour que l'image soit servie à la **bonne
définition et au bon ratio** (sinon image basse résolution étirée ou mal cadrée, + perte de perf).
- Cibler l'entité concernée (ex. un slider précis **par son slug** : `findOneBy(['slug' => 'home-hero'])`)
  et **toujours déclarer les TROIS écrans** `desktop` + `tablet` + `mobile` (ne pas en oublier un,
  sinon l'écran manquant retombe sur un format inadapté). `fixedHeight = true` pour un rendu *cover*.
- Aligner les dimensions sur le **rendu réel** (ex. hero 100vh → ~1920×1080 ; cartes en
  `aspect-ratio: 510/456` → thumb 510×456).
- `ThumbnailFixtures` est orchestré par `WebsiteFixtures` (locator `thumbnail`) : **régénérer la DB**
  après modification pour que les nouvelles `ThumbConfiguration` prennent effet.

### Flags SCSS `$enable-*` ↔ modules actifs (sinon styles non compilés)
Les styles de plusieurs briques sont gardés par des variables SCSS `$enable-…: false !default;`
(ex. `$enable-newsletter`, `$enable-lateral-nav`, `$enable-title-header`, `$enable-locales-switcher`…).
**Pour chaque module/brique activé sur le projet, vérifier que le flag SCSS correspondant est à `true`**
(dans `variables.scss`), sinon le CSS n'est pas généré et l'élément s'affiche sans style. À contrôler
après l'activation des modules (newsletter, switcher de langues, nav, etc.).

### Mega-menu principal (overlay 2 panneaux) — pattern réutilisable
- **Données menu hiérarchiques** : un mega-menu en colonnes suppose des liens parent/enfant.
  Dans `MenuFixtures`, construire les colonnes via une constante de groupes
  (`['title' => <rubrique>, 'children' => [<refs pages>...]]`) → parent niveau 1 (titre de rubrique,
  sans page cible) + enfants niveau 2 (`setLevel(2)` + `setParent($parent)`) pointant vers des pages
  **existantes** (zéro 404). Ne pas laisser le menu à plat.
- **Template** `actions/menu/main.html.twig` : barre fermée (burger + logo + CTA(s)) et overlay
  `#main-navigation-nav.collapse.mega-menu` (toggle Bootstrap `collapse` → classe `.show`). Itérer
  `tree.main` directement (colonnes), pas la macro dropdown. Panneau gauche = switch langue +
  colonnes de liens (+ logo « by … » si présent dans la maquette) ; panneau droit = média + CTAs +
  socials (`website.networks`) + adresse (`website.addresses`).
- **CSS overlay au TOP-LEVEL** (hors `@if`/wrapper) pour échapper au purge ;
  `.mega-menu{position:fixed;inset:0;max-height:100dvh;overflow-y:auto}` +
  `.mega-menu-inner{min-height:100dvh}` (l'overlay **ne dépasse jamais la fenêtre**, il scrolle
  dedans) + `.mega-menu.collapsing{height:100dvh!important;transition:none!important}`.
- **Centrage logo** : côtés `flex-fill` égaux (gauche/droite) + `navbar-brand` au centre. Les
  utilitaires `start-50`/`translate-middle-x` ne sont **pas** fiables ici (non générés/purgés).
- **Détails à relever du JSON** (sinon écart visible) : fond de chaque panneau, **couleur du texte**
  (relever les `fills` — souvent tout un bloc est d'**une seule** couleur), couleur des
  **bordures/flèches**, regroupement en colonnes (`links|batch(N)`), barre haute conservée en mode
  ouvert (close + logo + CTA). Items **sans page CMS** : enfant `['title' => ..., 'link' => '#']`
  (placeholder via `setTargetLink`), libellés exacts via la clé `title`.
- **Switch langue inline en toutes lettres** : piloté par les constantes du contrôleur PROJET
  `LocaleController` (`DISPLAY_FLAGS=false`, `DISPLAY_INLINE=true`, `DISPLAY_FULL_NAME=true`) — pas de
  paramètre par appel ; affichage horizontal + locale active colorée via SCSS
  `.mega-lang.locales-switcher` (`flex-direction:row`). Toutes les locales en ligne s'affichent.

### Newsletter — titre composite
- Bande au **fond clair** : wrapper `bg-*` clair dans `view.html.twig` ; template actif =
  `template/{campaign.slug}.html.twig`.
- Titre = **kicker en majuscules** (relevé maquette) + **mot en script** (`$font-script`) juste
  en dessous (léger chevauchement via `margin-top` négatif).
- Form : champ e-mail **souligné** (input transparent, bordure-bas, placeholder maj) + **séparateur
  vertical** + lien d'envoi en **texte** (PAS bouton plein). Consentement = `intro` campagne précédé
  d'une **case décorative**.
- ⚠️ **Forcer le style du form newsletter pour qu'il ne soit pas écrasé par le style des form
  globaux** : les styles génériques de formulaire (`components/_form.scss`, `.form-control`,
  `.form-group`…) s'appliquent aussi au form newsletter et écrasent l'apparence voulue. **Scoper** les
  règles newsletter sous le conteneur de la campagne (ex. `.newsletter-<slug> .form-control { … }` /
  `#newsletter… input { … }`) pour gagner en **spécificité** et reprendre la main sur input/bouton/
  placeholder. Réserver `!important` au strict nécessaire ; privilégier un sélecteur plus spécifique.

### Socialwall — widget (pas un module core gated)
- Le social wall est un **widget** (type apps-elfsight), pas un module `moduleActive` (aucun slug
  `social*` dans `cms_core_module`) → **ne pas gater** sur `moduleActive`.
- `include/socialwall.html.twig` : bande contrastée + **N images** (ratio relevé de la maquette)
  espacées (`justify-content: space-between`) + lien « suivez-nous » centré. **Images RÉELLES
  extraites de la maquette** (par node-id → `medias/…`), **jamais** de placeholder.

### Ordre des bandes & médias de marque (footer / mega-menu)
- **Ordre des bandes** = celui de la maquette : **relever les `y`** des nodes de bas de page
  (newsletter / socialwall / footer…) et les inclure dans `base.html.twig` dans cet ordre exact.
- **Images de marque récurrentes** (photo bâtiment/hero, logo « by »/partenaire, etc.) = vrais
  médias extraits **par node-id**, posés dans `medias/`. Prévoir les **variantes de couleur selon le
  fond** (ex. logo clair pour fond foncé). Footer/mega-menu affichent ces **logos image**, jamais en texte.

### Footer — séparation contenu / légal
- **Pages légales hors menu** : dans `getPagesParams()`, mettre `'menus' => []` pour les pages
  légales (mentions, cookies, plan de site) — elles restent accessibles ; le menu central du footer
  ne contient alors que les pages de **contenu**.
- **Template** `include/footer.html.twig` : reproduire la structure relevée de la maquette (souvent :
  barre haute socials / logo / note d'avis ; corps menu + média ; bandeau partenaires ; barre légale
  copyright + mentions + cookies + crédit agence). **Couleur du texte = relever le `fill`** (ex. fond
  foncé → texte clair). Liens légaux via `path('front_index', {url: <code-url>})`.

### ⚠️ Récupérer TOUT le contenu présent sur la PROD (FAQ, listings, etc.)
Avant les fixtures, **inventorier le site de prod** et **recréer tout contenu réel existant** — pas
seulement pages/produits/actus : **FAQ** (questions/réponses), **témoignages/avis**, **équipe**,
**partenaires**, **offres**, **galeries**, etc. **Si la prod a une FAQ bien fournie, la fixture DOIT
la recréer** (module `faq` actif + entrées réelles), idem pour tout autre module à contenu. Vérifier
en base que chaque type de contenu de la prod a bien des entrées (zéro entrée = oubli à corriger).

### Images des PRODUITS et des ACTUALITÉS — récupérer les vraies (pas de placeholder/Faker)
- À la génération des fixtures, **rattacher les vraies images de la maquette** à chaque **produit**
  (catalog) et chaque **actualité** (newscast) — `mainMedia` + galerie `medias` — extraites par
  node-id/imageRef depuis Figma (comme les médias de bandes), jamais le média par défaut ni du Faker.
- Ces images alimentent : fiches produit (hero + galerie), teasers/sliders de produits
  (`[teaser|product]`) et d'actualités, et les pages liste.

### Liens & CTA — labels réels + style bouton selon la charte (mécanisme EXACT)
> ⚠️ Le bloc lien **N'utilise PAS `title`** pour le libellé. Mettre le label dans `title` ne s'affiche
> jamais (le front retombe sur « En savoir + »). Les bons setters de l'intl sont :
- **Label** = `BlockIntl::setTargetLabel('<libellé exact maquette>')` → rendu `intl.linkLabel`.
  Reprendre le **libellé EXACT de la maquette** (ex. « DÉCOUVRIR LES CHAMBRES », « VOIR LE MENU »…),
  jamais générique/Faker, et le mettre à jour si la maquette change.
- **Style** = `BlockIntl::setTargetStyle('<classe>')` → rendu `intl.linkStyle` ; si la classe contient
  `btn`, le lien est rendu en **bouton**.
  - ⚠️ **NE PAS préfixer `btn` dans les fixtures** : le template de lien **ajoute déjà `btn`**. Mettre
    `setTargetStyle('btn btn-primary')` produit une **classe doublée** `btn btn btn-primary`.
    → écrire **`'btn-primary'`**, **`'btn-outline-white'`**, etc. (sans le `btn` initial). Vérifier la
    classe rendue dans le HTML.
  - ⚠️ **TOUT élément identifié comme CTA = BOUTON** → `setTargetStyle('btn btn-primary')` **par défaut**
    (ou une autre couleur de la **charte** selon la maquette : `btn-secondary`/`btn-dark`/`btn-outline-*`,
    couleur **relevée du `fill` du bouton dans le JSON**). **Ne JAMAIS laisser un CTA en `'link'`.**
  - `'link'` (texte/souligné) est réservé aux **liens secondaires** non-CTA (ex. « Blog », « Lire la suite »).
- **Cible — PRIVILÉGIER `setTargetPage($page)` plutôt que `setTargetLink('/une-url-en-dur')`** :
  associer l'entité **Page CMS** (récupérée via le repository) garantit que le lien suit le slug réel,
  reste valide si l'URL change et bénéficie du routing CMS. Réserver `setTargetLink('/url')` aux cibles
  **externes** ou aux placeholders (`'#'`) quand la page n'existe pas encore. Dans `setContent`, gérer
  explicitement `linkLabel` + `linkStyle` + `targetPage` (pas seulement `targetLink`).
- **TOUS les labels, partout** : mettre à jour les libellés au libellé maquette **non seulement en
  fixtures (DB)** mais **aussi les valeurs en dur rencontrées dans les templates Twig** — défauts
  type `'En savoir +'|trans` / `"En savoir plus"|trans` dans les blocs lien, macros de cartes
  (`include/macros/card.html.twig` → `standard`/`eventCard`), teasers (`actions/catalog/teaser/*`,
  `actions/newscast/teaser/*`), sliders (`splide`/`bootstrap`). Passer le vrai label via les
  paramètres d'include (`showLinkLabel`, `linkLabel`) ou corriger le défaut. Aucun « En savoir + »
  générique ne doit subsister si la maquette donne un libellé.

### Boutons/CTA dans les templates → réutiliser le template de lien (ne pas réécrire le markup)
Dès qu'un template doit afficher un **bouton / lien / CTA**, **inclure le template de bloc lien**
plutôt que de réécrire le `<a class="btn …">` à la main :
```twig
{% include 'front/'~websiteTemplate~'/blocks/link/default.html.twig' with {
    intl: intl,                 {# porte linkLabel / linkStyle / link / target #}
    block: block,               {# optionnel selon le contexte #}
    linkLabel: intl.linkLabel ?: 'Découvrir'|trans,
    style: 'mt-3'               {# classes supplémentaires éventuelles #}
} only %}
```
Avantage : un seul endroit gère le rendu d'un bouton (styles `btn`, `link`, libellé, cible, nouvel
onglet) → cohérence + on ne refait pas tout à chaque bouton ajouté. **Exemples d'intégration** qui
réutilisent ce pattern (à consulter comme modèles) : `actions/faq/view.html.twig`,
`actions/catalog/view/layout.html.twig`, `actions/catalog/view/default-product.html.twig`,
`actions/catalog/teaser/list.html.twig`.

### RIGUEUR — récupérer TOUS les éléments de CHAQUE zone, dans le BON ORDRE
Pour **chaque zone**, intégrer **l'intégralité** de ses éléments relevés dans le JSON : **toutes les
images** (pas seulement la première), tous les textes, liens/CTA, sous-titres, légendes, icônes.
Une section ne doit jamais avoir **moins d'images** que la maquette (piège avéré : galerie
multi-images réduite à une seule image — ex. `home-restaurant` : 3 images plat/cocktails/dessert).

**Méthode (reproductible) :**
1. **Lister tous les nœuds à fond image de la zone** dans le JSON Figma (`fills[].type == 'IMAGE'`),
   relever leur **`absoluteBoundingBox`** `{x, y, w, h}` et leur `imageRef`/node-id. Le **compte** =
   nombre de médias à intégrer (comparer au rendu : déficit = oubli).
2. **Ordre de lecture** = trier les éléments de la zone (textes + images) par **`y` puis `x`**
   (haut→bas, gauche→droite). Reproduire **cet ordre** dans les `addCol`/`addBlock` (la position des
   cols suit l'ordre de lecture). Ex. grille 2×2 → 4 cols `size 6` dans l'ordre
   `[texte | image1] / [image2 | image3]`.
3. **Extraire chaque image par node-id** (cf. `images?ids=…`), la poser en vrai média et la rattacher
   via `mediaBlock` dans la bonne col. Vérifier la capture vs maquette (nombre + position + sens).

### mediaRelations — désactiver popup + download (IMPÉRATIF, sauf page « components »)
- ⚠️ **OBLIGATOIRE** : sur **CHAQUE** `mediaRelation` créée en fixtures, appeler
  **`$relation->setPopup(false)` ET `$relation->setDownloadable(false)`** (méthodes héritées de
  `BaseMediaRelation` → valables pour `BlockMediaRelation`, `SliderMediaRelation`,
  `CatalogMediaRelation`, `NewscastMediaRelation`…). Sinon la visionneuse popup + le bouton de
  téléchargement **restent affichés** sur toutes les images (défaut récurrent à éliminer).
- À faire **partout** : `mediaBlock` (PageFixtures), relations produits (CatalogFixtures), actus
  (NewscastFixtures), **slides de sliders** (univers, services…). Vérifier le rendu (aucune icône
  popup/download sur les images).
- **EXCEPTION** : la page **`components`** = **conserver la page EXISTANTE telle quelle**
  (ne pas la régénérer/modifier) ; elle garde `popup` + `download` actifs sur ses `mediaRelation`
  (page de référence/QA kitchen-sink).

### Légendes Faker sur les blocs média (figcaption + alt) — éradication
- La **figcaption** (`.img-title`) et l'`alt` d'un bloc média viennent de l'intl Faker. La rendre
  vide ne suffit PAS : un filler Faker re-remplit les champs vides au flush (c'est pourquoi les blocs
  title/text gardent leur vrai contenu : non-vide = pas de re-fill).
- **Solution fiable** : `$media->setTitlePosition(null)` dans le helper `mediaBlock` → la figcaption
  n'est rendue que si `titlePosition ∈ top/bottom/left/right` ; à `null` elle disparaît, quel que soit
  le titre. (Le défaut de `Media` est `bottom-start`.)
- **Piège** : `BlockMediaRelation` n'a **pas** `getIntls()` (appel → fatal qui casse tout le load).
- **Piège exit code** : `fixtures:load | tail -1 && echo OK` masque l'échec (exit = `tail`). Toujours
  rediriger vers un fichier et tester `$?`, sinon un load planté passe pour réussi (et le site tombe en 500).

### Page produit (catalog) — le hero vient de `layout.html.twig`, PAS de `default-product`
- La fiche produit est composée de **blocs de layout** rendus par
  `actions/catalog/view/layout.html.twig` (switch sur `blockType` : `layout-title-header`,
  `layout-intro`, `layout-body`, `layout-gallery`/`layout-slider`, `layout-catalog-features`
  (caractéristiques), `layout-associated-entities` (produits associés)…). `default-product.html.twig`
  est une vue alternative **non utilisée** ici — ne pas perdre de temps à l'éditer.
- **Hero produit** : brancher dans `layout-title-header` → envelopper le `title-header` dans
  `.product-hero-wrap`, passer `customMedia = entity.medias|first` (image du produit) + `subTitle`
  (catégorie/tagline) + `styleClass:'text-center'`. ⚠️ `title-header` **écrase** `styleClass` quand
  `large` est faux → cibler en SCSS via le **wrapper** `.product-hero-wrap .title-header-block`
  (image plein cadre, dégradé sombre au lieu du dégradé or, titre `$font-script` blanc).

### Page de connexion BACK-OFFICE (à thémer aux couleurs du projet)
Toujours adapter la page de login admin `templates/security/base.html.twig` à l'identité du projet :
- **Couleur primaire** : appliquer la couleur primaire du projet (ex. or `$primary`) au thème security
  (variables SCSS du build `security` + boutons/liens).
- **Logo** : remplacer le logo Félix par le logo du projet via `logos['security-logo']`
  (sinon fallback `build/security/images/felix-logo.svg`). Fournir l'asset de marque.
- **Image de fond** : remplacer le fond via `logos['security-bg']` (sinon fallback
  `background-security.jpg`). Utiliser une vraie image de marque du projet.
- Vérifier le rendu de `/admin-…/login` sur le domaine local dans Chrome.

### Reconstruire le LAYOUT d'après les captures maquette (pas juste changer de variante)
Avant de styler, **exporter et REGARDER la maquette de chaque élément de layout** : menu **fermé ET
ouvert** (node `[nav]` via `figma:capture-layout` ou export image du node), footer, etc. Reproduire la
**structure réelle** — ex. un mega-menu peut être **2 panneaux** : à gauche switch langue + **colonnes
groupées** (rubriques + sous-liens) ; à droite média + CTAs + socials + contact. **Conséquences** :
- **Menu hiérarchique obligatoire** : `MenuFixtures` doit créer des **Links parents (rubriques)** avec
  **enfants** (pas une liste plate) pour rendre les colonnes du mega-menu.
- **Réécrire le template** du menu (`actions/menu/*.html.twig`) intégralement pour ce layout —
  changer la simple variante de template ne suffit JAMAIS à matcher la maquette.
- Vérifier **menu OUVERT** (clic ☰) au navigateur piloté contre la capture maquette.

### Réécrire intégralement le HTML des éléments de layout si besoin
Pour les éléments de LAYOUT (nav, footer, newsletter, socialwall), **ne pas hésiter à RÉÉCRIRE
entièrement le HTML/Twig** du template (`actions/menu/*.html.twig`, `include/footer.html.twig`…) pour
**coller à la maquette**, plutôt que de bricoler le template CMS générique avec des surcharges CSS. 
**Conserver le câblage aux données** (boucle `links`/`tree` du menu, `website.networks`, `logos`,
`information`/adresses/téléphones de la config, médias) mais réorganiser librement le markup, les
classes et la structure pour reproduire fidèlement la maquette (ordre, hiérarchie, CTAs, overlay).

### Ordre d'intégration : COMMENCER par le layout
**Démarrer l'intégration front par les éléments de LAYOUT partagés** (dans l'ordre) : **nav** (états
fermé + ouvert + mobile), **footer**, puis **newsletter** et **socialwall** — ils sont communs à toutes
les pages, donc à intégrer/styler **en premier** (templates `include/`/`actions/`, SCSS `layout/`,
JS `components/`). **Ensuite seulement** traiter les sections de page (hero, teasers, etc.). Cela évite de
re-styler le cadre à chaque page et garantit une base cohérente (cf. descripteurs `layout/*.json`).

### Chaîne de rendu (relier chaque template aux Blocks des fixtures)
`templates/front/<theme>/layout.html.twig` itère **chaque Zone** → `include/zone.html.twig` (rend les
**Cols** Bootstrap) → pour chaque **Block** inclut le template **selon son type** :
- **Bloc atomique** (`title`, `text`, `media`, `link`, `card`, `title-header`, `separator`,
  `social-networks`…) → `templates/front/<theme>/blocks/<slug>/…html.twig`.
- **Module** (bloc `core-action` + action) → `templates/front/<theme>/actions/<module>/…html.twig`
  (ex. `actions/slider/`, `actions/catalog/`, `actions/newscast/`, `actions/form/`, `actions/map/`).

→ **Le HTML d'une section n'est pas écrit "en dur"** : on personnalise le **template du BlockType /
de l'action** que les fixtures ont instancié. Repérer, pour chaque section de la maquette, le(s)
Block(s) créé(s) par les fixtures (cf. `pages/<slug>.json` champ `cms`) et adapter le template Twig
correspondant. Les variantes (ex. `slider` `bootstrap`/`splide`/`banner`, zone `colToRight`, couleurs
`bg-*`) pilotent le rendu — réutiliser ces hooks plutôt que dupliquer du HTML.

### ⚠️ PurgeCSS + nesting SCSS — pièges critiques (vécus)
1. **PurgeCSS purge les classes ajoutées dynamiquement en Twig** (ex. `not-expanded`, `nav-cta`) si elles
   ne sont pas vues comme « utilisées ». Les **ajouter à la safelist** (`safeList()` dans `webpack.config.js`,
   ex. `/not-expanded/`, `/nav-cta/`, `/main-submenu/`). Vérifier que la safelist est bien **appelée**
   (`safelist: safeList()`, pas `safelist: safeList`).
2. **Une règle SCSS imbriquée dans un wrapper qui ne matche pas le DOM réel ne s'applique pas / est purgée.**
   Symptôme vécu : `front-default-home.css` gardait le **même hash** build après build et `nav-cta` restait
   absent du CSS, alors que d'autres classes du même fichier étaient présentes. Cause : la règle était
   **nichée à la fin d'un gros sélecteur wrapper** (`#main-navigation { … }`) → sélecteur composé non
   appliqué. **Sortir ces règles globales en TOP-LEVEL** (colonne 0) du partiel SCSS. Vérifier après build :
   `grep -oc "ma-classe" public/build/.../front-default-*.css` > 0.

### CSS/SCSS — modules par section/composant
- Styles de **composant** réutilisable → `assets/scss/front/default/components/<composant>.scss`
  (ex. `_teaser-card.scss`, `_section-hero.scss`).
- Styles spécifiques à un **gabarit de page** → `assets/scss/front/default/templates/<page>.scss`.
- **Créer des modules SCSS** (un fichier par section/composant), importés depuis l'entrée front ;
  **réutiliser** les variables (`variables.scss` : `$primary/$secondary/...` + les couleurs de
  sections propres au projet, `$font-*`, `mediaQuery()`), **jamais** de `var(--x)`, jamais de `@media`
  brut, pas de `--`/`__` dans les classes.
- Mapper les classes sur les hooks CMS : `bg-*` des zones (couleurs ajoutées au `$theme-colors`),
  `colToRight`, etc.

### JS — modules ES6, chargés à la demande
- Un **module ES6 par comportement** → `assets/js/front/default/components/<module>.js`
  (ou `templates/` pour un comportement de page). Init **conditionnelle** (selon présence du sélecteur),
  **lazy-load** (`import()`) pour les comportements non critiques (sliders, maps, galleries).
- Réutiliser les **modules Bootstrap natifs** (Modal, Collapse, Dropdown, Carousel) — ne pas réinventer.

### Règle d'or
Tout ce qui est intégration front **doit** vivre dans `templates/front/`, `assets/scss/front/`,
`assets/js/front/`. Chaque `.html.twig` se relie à un **BlockType / action** instancié par les
fixtures ; le CSS/JS est **modulaire** (un module par section/composant) et réutilise l'existant.

### Routes multilingues : générer les routes manquantes par langue (Controllers)
Le site est **multi-domaines par locale** (ex. `cn.<projet>.local`, `es.…`, `en.…`). Certaines
routes (notamment les routes **fonctionnelles/non-CMS** : tableau de bord sécurisé, formulaires, recherche…)
doivent **exister pour chaque locale/domaine**, sinon **404** sur les langues secondaires. Après
l'intégration, **vérifier chaque domaine de langue** (`https://<locale>.<domaine>/…`) et **générer dans
les Controllers les routes manquantes** par langue (ex. `security_front_dashboard`, `security_login`, et
toute route nommée référencée par templates/menus). Les routes localisées sont **hardcodées par locale**
dans `#[Route(['fr'=>…,'en'=>…])]` (réparties dans `Controller/Front/IndexController`,
`Controller/Security/Front/*`, `Controller/Security/Admin/SecurityController`) → ajouter la clé de la
nouvelle locale (`'zh' => …`) dans **chacune**.
- **Deux niveaux à distinguer** : (1) **routes** manquantes → **500 « route does not exist »** ;
  (2) **URLs/contenu de pages par locale** : si les `Seo\Url`/`Intl` ne sont créés qu'en `fr`, les domaines
  secondaires renvoient **404** (locale résolue mais aucune page). → Dans les fixtures, créer un `Url` +
  les `Intl` **par locale active** (`locales_others`) pour chaque page/produit/actu.

### Vérifier VISUELLEMENT dans le navigateur (Chrome) — cohérence des images
Inspecter le rendu **dans Chrome** (capture headless possible :
`& "C:\Program Files\Google\Chrome\Application\chrome.exe" --headless --ignore-certificate-errors
--window-size=1440,2600 --screenshot=out.png https://<domaine>/`) et **comparer chaque section à la
maquette**. En cas d'**incohérence d'images** (un bloc/slide affiche une image différente de celle de
Figma) : **récupérer la BONNE image** (re-exporter le bon node via `figma:parse-page`, vérifier le
mapping `media/<slug>/<fichier>` → bloc dans `PageFixtures`), réimporter et recâbler. Ne jamais laisser
une image qui ne correspond pas au visuel de la maquette.

### Workflow AUTONOME par défaut (ne pas attendre qu'on le demande)
Pour CHAQUE élément intégré, faire **systématiquement et sans qu'on le demande** : consulter le **node
Figma** (tokens dev mode + export d'image de référence), **capturer le rendu sur Chrome** (états repos/
scroll/hover/ouvert via interactions réelles), **comparer côte à côte**, **mesurer** (computed styles +
contraintes numériques), itérer jusqu'à conformité. C'est la base, pas une option.

### Nettoyer le CODE MORT en réécrivant un fichier de layout
En réécrivant un fichier SCSS de layout, **supprimer les blocs jamais compilés** : code sous un
`@if ($flag)` dont le flag est `false` dans `variables.scss` (ex. `$enable-lateral-nav: false` →
tout le bloc `@if ($enable-lateral-nav) { … }` est mort). Les retirer allège le fichier et supprime des
sources de confusion (vérifier l'équilibre des accolades + rebuild après suppression).

### ⚠️ Éléments de LAYOUT : RÉÉCRIRE entièrement le CSS du fichier (ne rien garder du CSS de base)
Pour les éléments **communs à chaque page** (nav, footer, newsletter, social wall…), **réécrire
INTÉGRALEMENT** le SCSS du fichier concerné (`layout/_navigation.scss`, `layout/_footer.scss`…)
d'après la maquette, **sans conserver le CSS de base** du template. Empiler des overrides `!important`
sur l'ancien CSS = **conflits de spécificité/ordre** à répétition (filets écrasés, couleurs au scroll,
hauteurs, alignements…). Repartir d'un fichier propre, structuré sur les tokens Figma, évite ces
batailles. (Vu sur la nav : burger non blanc, mega-menu qui déborde, items poussés à droite — tous
dus à des règles de base résiduelles.) Idem : un template de layout peut être **réécrit** entièrement.

### 🧪 MÉTHODE de vérification rigoureuse (mesurer, pas juste regarder)
Pour fiabiliser le rendu et coller à l'attendu, ne pas se fier au seul visuel — **MESURER** sur Chrome :
1. **`getComputedStyle` de l'élément ET de ses `::before`/`::after`**, dans les états **repos ET hover**
   (couleur, `background`, `height`, `left/right`, `top`, `border`, `padding`). C'est ainsi qu'on prouve
   qu'une règle s'applique vraiment (ex. filet 1px vs fond plein hauteur, fond hover transparent vs blanc).
2. **Contraintes numériques** : vérifier `offsetHeight`/`scrollHeight` vs la fenêtre (ex. *nav ≤ 10dvh*,
   *mega-menu doit tenir dans 100dvh* → `scrollHeight ≤ innerHeight`). Mesurer, ajuster, re-mesurer.
3. **Déclencher les VRAIES interactions** : `page.mouse.wheel()` pour l'état **scroll** (un `scrollTo`
   programmatique ne déclenche pas les listeners JS → l'état `as-scroll` ne s'active pas), `click` pour
   ouvrir les menus, `mouse.move` pour le hover. Capturer chaque état.
4. **Quand un override ne « prend » pas : inspecter le CSS COMPILÉ** (`public/build/...`) pour trouver
   la règle gagnante (spécificité / ordre de source). Souvent un **système de composant existant**
   (ex. `::before` de remplissage des boutons, fill-layer) écrase. **L'exclure proprement avec `:not()`**
   (ex. `:not([class*="btn-outline-"])`) plutôt que d'empiler des `!important` qui perdent quand même.
5. **Conflit de spécificité fréquent** : une règle de même spécificité **définie plus loin** gagne ;
   un sélecteur `#id .x .y` bat `#id:not() .y`. Mesurer la spécificité réelle avant d'écrire l'override.

**Débloquages concrets observés (à réutiliser) :**
- *Bouton outline « 2 filets » blanc plein au hover + filet haut manquant* : un `::before` de **remplissage
  de fond** (`.btn:not(.basic)…:before{background:#fff;height:100%;width:0→100%}`) écrasait le filet.
  Solution : **exclure les outline** de ce `::before` (`:not([class*="btn-outline-"])`) et mesurer le
  `::before` calculé (height 1px vs 100%).
- *Burger pas blanc au top* : `#main-navigation .nav-burger … span{background:$dark}` (même spécificité
  que la règle « top » mais **définie plus loin**) gagnait → renforcer le sélecteur top
  (`#main-navigation:not(.as-scroll) .nav-burger … span`).
- *Items du menu ouvert « poussés à droite »* : groupes flex latéraux de largeurs inégales → **`flex:1 1 0`
  sur chaque groupe** recentre le logo (vérifier `logoCenterX == window.innerWidth/2`).
- *État scroll non visible en capture* : `scrollTo` ne déclenche pas le listener → utiliser `mouse.wheel`.

### Se référer aux STYLES Figma importés (intégration)
Avant/pendant l'intégration, **se référer aux fichiers de styles importés du projet** (dans
`.claude/skills/figma-cms/integration/`) plutôt que de deviner :
- **`figma-styles.md`** = résumé lisible (palette couleurs, échelle typo, couleur par section).
- **`figma-tokens.<page>.json`** = référence **exhaustive par node** (font-size, couleur, ombre,
  strokes, radius, paddings, texte…), générée par `tooling/figma-export-tokens.py`.
À **générer pour chaque page et chaque élément de layout** (nav, footer…) et à consulter pour les
valeurs exactes (cf. procédure ≥95 %).

### ⚠️ VÉRIFIER SYSTÉMATIQUEMENT SUR CHROME + relever les TOKENS Figma (dev mode) — NON NÉGOCIABLE
Dès qu'on passe à la **phase d'intégration** (HTML/CSS), **vérifier CHAQUE bande sur Chrome
(domaine local réel)** et **comparer à la maquette** — pas « à l'œil », pas d'approximation.
- **Relever les VRAIES valeurs en dev mode Figma** pour chaque élément : **couleurs** (`fills` → hex),
  **font-size** (`fontSize`), **graisse** (`fontWeight`), **letter-spacing**, **line-height**
  (`lineHeightPx`), **casse** (`textCase`), et les **espacements** (auto-layout : `paddingLeft/Right/
  Top/Bottom`, `itemSpacing`). Reporter ces valeurs dans le SCSS/les propriétés d'entités — ne jamais
  deviner une couleur/taille/marge.
- **Appliquer les MARGES/PADDINGS** relevés sur les **zones / colonnes / blocs** (propriétés d'entités),
  pas de colonnes/blocs « collés » sans respiration : une intégration sans marges = non terminée.
- **Boucle obligatoire** : intégrer une bande → `yarn build` → recharger sur Chrome → **capture +
  comparaison côte à côte** avec la maquette → itérer jusqu'à correspondance (couleurs, typo, marges).
  Tant que la comparaison Chrome ↔ maquette n'est pas faite et conforme, la bande **n'est pas finie**.

### Consulter le PROTOTYPE Figma (comportements/états), pas seulement les frames statiques
Au-delà des frames, **ouvrir le prototype Figma** pour relever les **comportements et états** : en
particulier le **menu en haut de page vs au scroll** (transparent → fond plein, changement de couleur
de texte/logo, apparition/disparition), les hovers, ouvertures de sous-menus, transitions. Reproduire
ces états (classes au scroll, transitions) conformément au proto. Cf. interactions dans `interactions/`.

### ⛔ PROCÉDURE DE VÉRIFICATION ≥ 95 % ISO (objectif : iso dès le 1er jet, tolérance MAX 5 %)
**Une page n'est PAS terminée tant que le rendu Chrome n'est pas à ≥ 95 % identique à la maquette.**
Tant que l'estimation est < 95 %, **continuer l'intégration** (ne jamais déclarer « fidèle » avant).
> 📌 **Consulter le md PROJET** (`.claude/skills/figma-cms/integration/PROJECT.md`) **pendant
> l'intégration ET au moment de valider les 95 %** : il porte les **demandes/contraintes spécifiques
> au projet** (comportements nav, couleurs d'état, exigences de bandes…) qui font partie des critères
> de conformité. Une bande « iso maquette » mais non conforme à une consigne PROJECT.md n'est pas validée.
> 🔒 **Avant d'annoncer 95 % : NE PAS s'avancer.** Faire une **passe de re-vérification finale, élément
> par élément** (chaque bande de chaque page + nav + footer + modules), capture Chrome ↔ maquette à
> l'appui. N'annoncer le chiffre **qu'après** cette re-vérification complète — jamais « de mémoire »
> ni par extrapolation. Si un seul élément n'a pas été re-contrôlé, l'annonce est prématurée.
Procédure, par page :
1. **Exporter la maquette ENTIÈRE** du node de la page (Figma `GET /v1/images/:key?ids=<node>&format=png&scale=…`)
   → image de référence complète. La re-exporter si la maquette a changé.
2. **Capturer le rendu complet** sur Chrome (`fullPage`) au même cadrage/largeur. **Script réutilisable**
   fourni : `node .claude/skills/figma-cms/tooling/capture.mjs <url> <outDir> [zoneId…]` (page entière
   sans zoneId ; sinon une PNG par `#zone-<id>` ; masque cookies/GDPR/toolbar automatiquement). Le lancer
   **depuis la racine** du projet (puppeteer-core y est installé). Ne PAS réécrire un script ad hoc à chaque fois.
3. **Comparer bande par bande** (crops à résolution lisible, côte à côte) — PAS sur une vignette globale
   (une vignette ne permet pas de juger : c'est ainsi qu'on conclut « fidèle » à tort).
   - 🔍 **ZOOMER sur les détails** : quand un élément a un traitement fin (style de bouton, filets/
     bordures, chevauchement de textes, ombres, espacements), **cropper la zone et l'agrandir**
     (ImageMagick `-crop … -resize 2x`) maquette ET rendu pour les comparer au détail. Ne jamais
     trancher un détail sur une image trop petite (ex. un CTA « souligné » vs « encadré de 2 filets »
     ne se distingue qu'en zoomant).
4. **Lister chaque écart** : structure/ordre des sections, présence d'éléments (cartes, CTA, images),
   couleurs, font-sizes, graisses, casse, letter-spacing, **marges/paddings**, overlays, alignements.
5. **Corriger**, rebuild, recapturer, recomparer. **Itérer** jusqu'à ≤ 5 % d'écart visuel.
6. Ne **lister comme “fait”** qu'après cette boucle ; sinon dire honnêtement le % estimé et ce qui manque.
> Erreurs classiques à NE PAS commettre : juger sur une vignette ; oublier des sections/cartes/CTA ;
> approximer une couleur/taille au lieu de relever le token ; laisser les blocs sans marges.

**S'applique à TOUS les éléments, pas qu'aux bandes de page** : la même rigueur (relevé tokens dev mode +
vérif Chrome côte à côte + ≥95 %) vaut pour la **nav** (barre fermée, états top/scroll, mega-menu),
le **footer**, la **newsletter**, le **social wall**, les **modules** (sliders, teasers, formulaires,
map…) et **chaque page** (home, fiches produit/actu, listings, contact…). Aucun élément n'échappe au
contrôle.

**Estimation du % — être CONSERVATEUR et FACTUEL** : n'annoncer un pourcentage **qu'après** la
comparaison Chrome ↔ maquette **bande par bande / élément par élément**. Ne JAMAIS surestimer :
- compter comme « écart » tout ce qui n'est pas iso (élément manquant, image non affichée, couleur/
  taille/poids/casse/letter-spacing différents, marge absente, overlay/alignement faux, état non géré) ;
- un seul **bloc cassé** (ex. images non rendues) ou une bande structurellement différente fait
  **chuter** le score bien en dessous de la moyenne « visuelle » ;
- en cas de doute, **annoncer plus bas** et continuer. Ne déclarer **≥ 95 %** que si **chaque** élément
  a été comparé et est conforme. Un chiffre annoncé doit être **démontrable** par les captures.
- ⚠️ **NE JAMAIS SURESTIMER.** Le biais naturel est d'annoncer trop haut (« ça ressemble globalement »).
  Une bande aux bonnes couleurs mais aux mauvaises **proportions/espacements/tailles/positions** n'est
  PAS « faite ». Compter dur : si tu hésites entre deux %, prends **le plus bas**. Un rendu « qui a l'air
  proche » en vignette est souvent à 30-50 % une fois mesuré au détail. La barre des 95 % est **élevée**.

### TOUJOURS consulter le domaine LOCAL réel (pas seulement des captures)
Vérifier l'intégration **sur le domaine local du projet** : `https://<projet>.local/` (et les
domaines de langue `en./es./cn.`). Le site **force HTTPS** (cert auto-signé). Comparer le rendu réel à la
maquette. **Toujours confirmer quel build est servi** : le HTML charge le CSS via `asset(..., webpack)` →
vérifier le hash chargé (`front-default-home.<hash>.css`) et qu'il correspond au **dernier build**
(`yarn dev`/`yarn build`) ; sinon vider le cache navigateur + Symfony (`cache:clear`) et rebuild.

### Piloter le navigateur (Puppeteer/CDP) — comparer à la maquette EN INTERAGISSANT
Une capture statique headless ne suffit pas : le **lazy-load** (contenu sous la ligne de flottaison) et
les **états interactifs** (menu/overlay ouvert, hover, accordéons, sliders) exigent un **navigateur
piloté**. Utiliser **Puppeteer** (`npm i --no-save puppeteer-core` + `executablePath` vers le Chrome
installé) ou le CDP pour : charger la page, **masquer la bannière cookies**, **scroller toute la page**
(déclenche le lazy-load) → **capture pleine page**, puis **cliquer** (hamburger → overlay nav, onglets,
collapse) et capturer chaque état. **Comparer systématiquement chaque section/état à la maquette Figma**
(les éléments de LAYOUT en premier : nav fermée + ouverte, footer) et corriger jusqu'à correspondance.

### Vérifier le rendu (obligatoire) — bugs latents des templates partagés
Après les fixtures, **charger CHAQUE page et vérifier le HTTP 200** (le site force souvent HTTPS →
`curl -sk --resolve <domaine>:443:127.0.0.1 https://<domaine>/<path>`, `cache:clear` au préalable).
Une **combinaison module/variante encore jamais utilisée** par les fixtures démo peut **exposer un bug
latent** dans un template CMS partagé (cas avéré : `actions/slider/template/splide.html.twig` utilisait
une variable `link` inexistante → 500 sur la home dès qu'un `slider|splide` porte du contenu ; corrigé
en `media.intl.link`). **Corriger dans `templates/front/`** puis **propager au cœur** (SFCMS-7). Isoler
le coupable en testant page par page (seule la page fautive renvoie 500).

## Polices & variables SCSS (front)

**Vérifier TOUTES les polices de la maquette** (sur **tous** les écrans, pas que la home) :
scanner les styles de texte Figma → recenser **chaque famille ET chaque graisse/style** réellement
utilisées (typiquement : une police de **corps** en plusieurs graisses + une **cursive décorative**
pour les titres + une police **secondaire**). **Intégrer TOUTES ces familles**, pas seulement la
principale. **Signaler les écarts** : une graisse présente dans la maquette mais absente des
webfonts de prod doit être sourcée (licence) ou mappée sur la plus proche.

> **🛑 RÈGLES POLICES (obligatoires) :**
> - **Toute police DOIT avoir un fallback** dans sa pile `font-family` (jamais une famille seule) :
>   police projet → `system-ui`/`-apple-system` → `font-fallback`/`font-fallback-android` (métriques
>   capsize anti-CLS) → générique (`sans-serif` / `cursive`). Ex. `$font-script: '<Script du projet>',
>   '<Sans du projet>', cursive;`. Une police décorative (script) doit aussi retomber sur une cursive.
> - **Précharger les polices ESSENTIELLES** (corps + titres above-the-fold) dans
>   `templates/front/<theme>/base.html.twig` via `<link rel="preload" as="font" type="font/woff2"
>   crossorigin nonce="{{ csp_nonce() }}">`. L'URL du preload doit **correspondre exactement** à l'`url()`
>   du `@font-face` (sinon double téléchargement) → pointer un **chemin stable** (Webpack `copyFiles`
>   sans hash) côté `@font-face` ET preload. Ne précharger que les graisses réellement critiques.

**Récupérer les polices du projet** (depuis le CSS de prod : règles `@font-face` / `font-family`,
ou à défaut les styles de texte Figma) et **les intégrer EN LOCAL** (jamais de CDN externe) :
- **Fichiers de police** (woff2/woff…) → `assets/lib/fonts/`.
- **Déclarations `@font-face`** → `assets/scss/front/default/fonts.scss`, en **`url()` RELATIVE** vers
  `assets/lib/fonts/…` (ex. `url('../../../lib/fonts/<famille>/x.woff2')`). ⚠️ **Ne PAS utiliser
  d'`url()` absolue `/build/…`** : `css-loader` la
  traite comme un module à résoudre et **casse le build** (« Module not found »). Le chemin relatif
  laisse Webpack émettre/hasher la police automatiquement (woff2 suffit, support universel).
- **Ajuster les variables SCSS** → `assets/scss/front/default/variables.scss` :
  - **couleurs** : reporter le mapping validé (primary/secondary/light/dark…), aligné sur `config.json`/`default.yaml`, et **alimenter le map `$theme-colors`** (ajouter les couleurs de sections projet, ex. `navy`/`teal`, + les ajouter à `$default-bootstrap-colors` pour générer `bg-*`/`text-*`) ;
  - **polices** : variables de familles (`$font-…`) pointant sur les polices intégrées.
- **Polices de secours anti-CLS (obligatoire)** : les `@font-face` `font-fallback` /
  `font-fallback-android` de `variables.scss` (avec `size-adjust`, `ascent-override`,
  `descent-override`, `line-gap-override`, sous `@if not $enable-medias-queries`) sont des
  **métriques propres à la police**. Par défaut elles sont calées sur Roboto → il faut les
  **recalculer pour la nouvelle police de corps** du projet, sinon le fallback induit du CLS.
  **Installer capsize** puis calculer les métriques à partir du `.woff2` de la police :
  ```
  npm install --no-save @capsizecss/unpack @capsizecss/core @capsizecss/metrics
  ```
  Script Node (depuis la **racine du projet**, pour résoudre les modules) : `fromFile(woff2)` →
  métriques de la police ; `createFontStack([police, arial])` et `([police, roboto])` →
  les `@font-face` de secours avec `size-adjust`/`*-override` à reporter dans `variables.scss`.
  Garder `local('Arial')` (desktop) / `local('Roboto')` (android) comme repli système.
- **Précharger les graisses critiques** (above-the-fold) dans `templates/front/default/base.html.twig`
  (`<link rel="preload" as="font" type="font/woff2" crossorigin>` + `nonce` CSP). Pour que le preload
  soit efficace, l'URL doit **correspondre exactement** à celle du `@font-face` : copier les polices
  vers un **chemin stable** (Webpack `copyFiles` → `fonts/…`, sans hash) et y pointer `@font-face` + preload.
  Limiter les graisses/styles aux besoins réels.

## Cas particulier : nav & footer (intégrés une seule fois)

`[nav]` (et variantes `[nav|fixed]`, etc.) et `[footer]` font partie du **layout
de base**, commun à toutes les pages. Ils doivent être intégrés, mais **une seule
fois**, pas à chaque page.

Règles :
- **Intégration unique** : la nav et le footer sont générés **une seule fois**, dans le **layout de base partagé** (et non dans le contenu d'une page).
- **🎯 SOURCE UNIQUE — où les récupérer** : ne relever/capturer les éléments de layout (`[nav]`,
  `[footer]`, `[newsletter]`, `[socialwall]`…) **QUE** depuis :
  1. la page de référence **`[page|home]`** (qui porte le layout complet), **OU**
  2. des **frames isolés dédiés** posés hors page, nommés directement `[nav]`, `[nav|mobile]`,
     `[footer]`, `[newsletter]`, `[socialwall]`… (et leurs variantes d'état).
  **Ne JAMAIS les ré-extraire depuis les autres `[page|…]`** : sur ces pages ils ne sont là que pour
  le contexte créa (doublons). Les `layout/*.json` doivent donc pointer les node-ids de la home ou des
  frames isolés, pas ceux d'une page intérieure. Une variante isolée (`[nav|mobile]`) est la **source
  privilégiée** pour cet état précis.
- **États de la nav principale** : `[nav|close]` (barre **fermée**, repos) — **le plus souvent posée sur
  `[page|home]`** (la barre au repos visible sur la home) ; `[nav|open]` (**méga-menu ouvert**) et
  `[nav|mobile]` (menu mobile) — sur des **frames isolés dédiés**. Renseigner les `figmaNodeId` de
  `nav.json` en conséquence. ⚠️ Si la barre fermée est **transparente sur le hero**, sa capture depuis la
  home ressort **blanche** → préférer un frame isolé `[nav|close]`, ou capturer en contexte de page
  (cf. caveat transparence ci-dessous). Un état non tagué peut aussi être atteint via une **interaction
  proto** (`actions[].destinationId`).
- **Capture (`figma:capture-layout`)** : les `layout/*.json` sont des **modèles vides** (`figmaNodeId: null`) ;
  les **renseigner** avec les node-ids relevés (home ou frames isolés) AVANT de lancer la capture, sinon
  rien n'est produit. Puis `php bin/console figma:capture-layout` écrit dans `screenshots/layout/`.
  - ⚠️ **Caveat transparence** : un élément **transparent par-dessus le hero** (cas typique de la nav
    desktop : logo/liens **blancs** sur fond transparent) **rend BLANC** s'il est capturé depuis son
    instance sur `[page|home]`. Le capturer alors depuis le **composant/frame isolé** (`[nav]` master,
    `[nav|mobile]`) qui a son propre contexte/fond. Toujours **vérifier visuellement** chaque capture
    (une PNG quasi vide / < ~10 Ko = signal d'alerte).
  - **Nettoyer les orphelins** : `screenshots/layout/` ne doit contenir QUE les sorties déclarées par les
    descripteurs (supprimer les captures d'un ancien run au nommage différent, ex. `socialwall.png` vs
    `social-wall.png`).
- **Exclus de la génération par page** : lors du parsing d'un `[page…]`, ignorer les sous-arbres `[nav]` et `[footer]` — ne créer ni Zone, ni Col, ni Block pour eux **au niveau de la page**.
- Pour chaque page, ne générer que le **contenu propre à la page**, situé entre la nav et le footer.
- Conséquence : si la nav/le footer existent déjà dans le layout de base, ne pas les recréer ; sinon, les créer **une fois** puis les réutiliser sur toutes les pages.

## Lire les interactions du prototype (menus, hover, animations)

Le prototype Figma (URL `figma.com/proto/...`, ou nœud lu en mode dev) est **lisible
en données** via l'API REST (`/v1/files/:key/nodes?ids=<id>&depth=N`, scope
`file_content:read`) : chaque nœud porte le cas échéant un tableau `interactions`.
On peut donc reconstruire le graphe d'interactions, mais **pas** « jouer » le
prototype en vidéo.

> ⚙️ **OUTIL (à lancer en 1er — sinon les animations sont RATÉES)** :
> `node .claude/skills/figma-cms/tooling/prototype-interactions.mjs [--node <page>] --out integration/interactions.md`
> Il parcourt le fichier, extrait toutes les `interactions` et les **déduplique en RECETTES**
> (`trigger | transition | easing | durée`, ex. `ON_HOVER | SMART_ANIMATE | GENTLE | 1000ms ×53`) avec un
> exemple source→cible. Sans cet outil, les centaines d'`interactions` (composants instanciés) noient
> l'info et les animations passent à la trappe. **Chaque recette = une animation à reproduire UNE fois.**

Champs exploitables par interaction :
- **`trigger.type`** : `ON_CLICK`, `ON_HOVER`, `MOUSE_ENTER`/`MOUSE_LEAVE`,
  `ON_PRESS`, `AFTER_TIMEOUT`, `ON_DRAG`… → quel geste déclenche quoi.
- **`actions[].type`** : `NODE` (naviguer vers une frame = ex. **état menu ouvert**,
  overlay), `URL`, `BACK`, `CLOSE`, `SWAP`/`OVERLAY`… + `destinationId`.
- **`actions[].transition`** : `SMART_ANIMATE`, `DISSOLVE`, `MOVE_IN`, `PUSH`… avec
  `transitionDuration` et easing → l'**animation** attendue.

Méthode : extraire ces champs, puis **rendre en image chaque état** cible
(`destinationId`) — fermé vs ouvert, normal vs hover — et l'examiner (cf. règle
« capturer avant d'interpréter » et les états des `layout/*.json`). Les ouvertures
de menu/overlay correspondent à des actions `NODE`/`OVERLAY` vers une autre frame :
les traiter comme **états** d'un même élément, pas comme des éléments distincts.

## Éléments de layout récurrents (PAS des sections de page)

> 🛑 **POINT CRITIQUE — à appliquer sur CHAQUE page, pas seulement la home.**
> Le **footer**, la **newsletter** et le **mur social** (et toute barre/élément répété en bas
> de page) sont du **LAYOUT**. Ils **ne doivent JAMAIS** apparaître :
> - ni comme **zones/cols/blocs** d'une page (`pages/<slug>.json`) ;
> - ni dans les **captures de bandes** d'une page (`screenshots/<slug>/`).
> Ils sont intégrés **une seule fois** (descripteurs `layout/*.json` + leurs propres captures
> `screenshots/layout/`). **Cas avéré** : `product-view` — bande 4 = newsletter + mur social,
> bande 5 = footer, qui ont **fui** dans les captures de la fiche produit (à exclure).
> Quand ces éléments **ne sont pas taggés** dans la maquette, les détecter (heuristiques ci-dessous),
> **recaler le bas de la zone de contenu au-dessus d'eux**, et **inviter le créa à les tagger**.

Certains éléments, bien que présents dans la maquette (souvent **en bas de page**),
appartiennent au **layout de base** partagé : les intégrer **une seule fois**, comme
la nav et le footer, et les **exclure de la génération par page** (ni Zone, ni Col,
ni Block au niveau de la page). Au besoin, les déclarer dans un descripteur
`layout/*.json` (au même titre que `nav.json` / `footer.json`).

> ⚠️ « Layout » ≠ « footer ». Chacun de ces éléments est **autonome** : le bloc
> newsletter et le **mur social ne font PAS partie du footer** (et ne sont pas non
> plus une section de page). Le `social` listé dans `footer.json` ne désigne que des
> **liens/icônes de config** dans le footer — c'est distinct du module mur social.

Cas connus :

- **Formulaire d'inscription newsletter** → activer le **module newsletter**, ne
  pas recréer un formulaire en blocs.
  - Module : entité `App\Entity\Core\Module`, slug `newsletter`, rôle
    `ROLE_NEWSLETTER` (`ModuleFixtures`). ⚠️ Sur ce projet il est listé dans
    `OTHERS_MODULES` de `WebsiteFixtures` (≠ `DEFAULTS_MODULES`) → **non actif par
    défaut** : le **passer en module actif** (l'ajouter aux modules activés du
    Website, via les fixtures de configuration).
  - Campagne : `App\Entity\Module\Newsletter\Campaign` slug `main`, créée par
    `NewsletterFixtures::add()` ; front via `Form\Type\Module\Newsletter\FrontType`
    + `Form\Manager\Front\NewsletterManager`.

- **Bloc réseaux sociaux / mur social** (image, handle/nom de compte, ou feed, le
  plus souvent vers le bas de page) → **layout**, pas une section de page.
  - Indices de détection : présence d'un nom/handle ou feed **Instagram**,
    **Facebook** ou **Youtube** (ex. « Suivez-nous sur Insta »), souvent une rangée
    de vignettes ou des picots réseaux.
  - Mécanisme CMS : blocType **`social-networks`** (catégorie `global`, posable sur
    une page) ou **config `social_networks`** du site pour de simples liens/handles.
  - ⚠️ **Vérifier en base** : sur ce projet il n'existe **aucun module**
    `social-wall`/`ROLE_SOCIAL_WALL` (la constante existe dans `WebsiteFixtures`
    mais sans `Module` ni `Action`). Ne pas activer un module inexistant ; un vrai
    **feed dynamique** relèverait d'un développement spécifique.

Toujours **se référer aux Fixtures** pour l'entité/le module exact et l'état actif
(voir `mapping-blocktypes.md`) ; ne jamais inventer de BlockType.

## Connexion à Figma (obligatoire)

La connexion / lecture des données Figma doit **obligatoirement passer par le
service applicatif** `App\Service\Figma\FigmaApiClient` (interface
`App\Service\Figma\FigmaApiClientInterface`), injecté par constructeur.

- Token : injecté depuis `FIGMA_TOKEN` (`.env`) via le binding `$figmaToken` — ne jamais le coder en dur.
- Méthodes disponibles : `getFile()`, `getFileNodes()`, `getImages()`.
- **Interdit** : appels `curl` bruts, `file_get_contents` ou tout accès direct à `api.figma.com` hors de ce service.
- Le scope actuel du token est `file_content:read` (lecture seule). Pour des écritures vers Figma, basculer sur le MCP Figma (OAuth).

---

## Rappel synthétique de la convention (instantané du 2026-06-19)

> ⚠️ Copie non contractuelle, susceptible d'être périmée. **Toujours vérifier
> sur https://figma-doc.agence-felix.fr/** avant usage — la page en ligne prime.

### Hiérarchie CMS cible
`Page → Zones → Colonnes → Blocs`
Chaque niveau d'imbrication de frames Figma correspond à ce découpage.

### 5 règles de nommage des calques
1. Préfixe entre crochets : `[slider]`, `[nav]`, `[faq]`
2. Variantes séparées par un pipe : `[slider|splide]`, `[cta|primary]`
3. Variantes cumulables : `[slider|splide|rounded]`
4. Reste du nom libre (description à convenance)
5. Insensible à la casse : `[Slider]` = `[slider]`

### Familles de préfixes

| Catégorie | Exemples |
|---|---|
| Structure | `[page]`, `[zone]`, `[col]` |
| Navigation | `[nav]`, `[footer]`, `[nav-link]` |
| Contenu | `[title]`, `[text]`, `[image]` |
| Interactions | `[btn]`, `[cta]`, `[form]` |
| Composants | `[card]`, `[slider]`, `[faq]` |
| Modules métier | `[newscast]`, `[catalog]`, `[contact]` |

### Modificateurs transversaux
- Couleur : `bg:primary`, `text:white`, `bg:#HEX`
- Forme : `rounded`, `shadow`
- Responsive : `hide-mobile`, `hide-tablet`, `hide-desktop`

### Workflow
1. Maquette Figma nommée selon la convention
2. Lecture de la structure (MCP Figma ou API REST via `FigmaApiClient`)
3. Import automatique en base (Page/Zones/Colonnes/Blocs)
4. Édition immédiate en back-office

### À la charge du développeur après import
Responsive, animations, logique métier, SEO, validation des formulaires,
intégrations tierces.
