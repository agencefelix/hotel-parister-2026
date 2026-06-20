# Intégration de prompts provenant de Figma

## Règle préalable (obligatoire)

Avant d'intégrer une maquette ou un prompt provenant de Figma, **consulter
systématiquement la convention de nommage de référence** :

👉 https://figma-doc.agence-felix.fr/ 

Cette convention fait la **correspondance entre le nommage des calques/composants
de la maquette Figma et la mécanique du CMS** (Symfony 7.4 SFCMS 7). Elle doit
être appliquée pour chaque import Figma → CMS.

> **Générique vs projet.** Ce dossier `.claude/figma/` ne contient que des règles
> **génériques, réutilisables sur tout projet Figma → CMS** (`integration-prompts.md`,
> `mapping-blocktypes.md`) et les gabarits (`models/`). Toute **spécificité d'un
> projet** va dans le sous-dossier **`.claude/figma/integration/`**. Ne jamais mêler
> du spécifique projet aux fichiers génériques.

> **Arborescence (où va quoi) :**
> - `.claude/figma/` (générique, réutilisable) : `integration-prompts.md`, `mapping-blocktypes.md`, `models/` (gabarits + `cms-catalog.json` + `portability-risks.md`).
> - `.claude/figma/integration/` = **le projet** (tout le spécifique) : la spec projet `<nom-du-projet>.md`, `config.json`, `prod-urls.json`, `seo.json`, et les dossiers `pages/`, `layout/`, `screenshots/`, `media/`, `interactions/`.
>
> Au démarrage, **bootstrapper `integration/` en copiant les gabarits de `models/`**
> (comme un nouveau projet), puis remplir. Dans la suite de ce document, tout chemin
> relatif (`config.json`, `prod-urls.json`, `seo.json`, `pages/`, `layout/`,
> `screenshots/`, `media/`, `interactions/`) désigne **toujours** son emplacement
> sous `.claude/figma/integration/`.

> ⚠️ **La convention évolue.** Le site est la **seule source de vérité** et peut
> être mis à jour à tout moment. À **chaque** intégration Figma, **re-consulter
> la page en ligne** (ne jamais se fier au seul rappel ci-dessous, qui peut être
> obsolète). En cas d'écart entre le site et ce fichier : **le site prime**, et
> mettre à jour ce rappel en conséquence.

## Procédure complète de reproduction (playbook — à suivre intégralement)

> **But.** Repartir d'un projet où **seul `.claude/figma/` subsiste** (le reste du code a été
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

**Phase 1 — Kickoff** (§ « deux URLs » + « Connexion ») : demander **URL prod** + **URL proto** ;
bootstrapper `integration/` depuis `models/`.

**Phase 2 — Dry-run par page** (§ capture / déduction / interactions / captures synchronisées) :
pour **chaque `[page|…]`** → `figma:parse-page` (=> `pages/<slug>.json`, `screenshots/<slug>/`,
`media/<slug>/`) ; `figma:capture-layout` (=> `screenshots/layout/`) ; interactions proto =>
`interactions/`. **Toutes** les pages doivent avoir leurs captures.

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

À la **première intégration** d'une maquette (ou dès que l'info manque), **demander
impérativement à l'utilisateur deux URLs** :

1. **L'URL du site de prod existant** → données TEXTE/config (domaines, GTM/GA,
   réseaux sociaux, légal, multilingue, redirections). Alimente `config.json`
   (`prod_url`, défaut `"URL PROD"`) et `prod-urls.json`.
2. **L'URL du prototype Figma** (`figma.com/proto/...`) → **détection des animations
   et interactions** (ouvertures de menu/overlay, hover, transitions). Sans elle, on
   ne peut pas cartographier les comportements (cf. « Lire les interactions du
   prototype »). Conserver le node-id de départ du proto.

Ne pas démarrer l'interprétation fine sans ces deux URLs (ou sans avoir acté leur
absence avec l'utilisateur).

> **Présentation (console).** Poser ces deux questions **en évidence sur fond bleu**
> dans la console. La sortie Markdown ne gère pas les fonds : utiliser un `printf`
> avec codes ANSI (fond bleu `\033[44m`, texte clair `\033[97m`, reset `\033[0m`),
> ex. `printf '\033[44;97;1m … \033[0m\n'`. Ces deux URLs sont les **seules**
> questions à mettre ainsi en avant.

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
dans un **sous-dossier par page** : `.claude/figma/integration/screenshots/<slug-page>/`
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
`.claude/figma/integration/screenshots/layout/` (ex. `layout/nav.png`, `layout/footer.png`),
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
  pas un fond (piège avéré : un filigrane « parister » blanc/or pris pour un fond) ;
- `SOLID` → `#rrggbb` (`#rrggbbaa` si semi-transparent) ; `GRADIENT` →
  `gradient(linear #a,#b)` ; sinon **repli sur le fond de page** ;
- fond **IMAGE** (hero/photo pleine largeur) → `background: null` (pas de couleur unie).
- **Croiser avec la capture** de la bande : la géométrie seule trompe (overlay, image
  par-dessus une couleur). La capture tranche le fond réellement visible.

## Garder les captures synchronisées (obligatoire)

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
pages) a son **descripteur JSON** dans `.claude/figma/integration/layout/` (ex. `nav.json`,
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

Artefact : `.claude/figma/integration/config.json` (mêmes clés que `default.yaml`, éditable).
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

Stocker le tout dans `.claude/figma/integration/prod-urls.json`, **groupé par langue** :
`{default_locale, locales:[...], total, by_language:{<lang>:{domain, sitemap, count, urls:[{url,path}]}}}`.
Utilité : mapper les pages, cibler les liens de menu vers de vraies URLs, préparer
les redirections, gérer le multilingue. Donnée texte/config — pas du design.

### Récupérer le SEO de toutes les URLs

Pour **toutes** les URLs trouvées (toutes langues), crawler chaque page et extraire
son **SEO** : `title`, meta `description`, `keywords`, `robots`, `canonical`,
Open Graph (`og:title` / `og:description` / `og:image`), `h1`. Stocker dans
`.claude/figma/integration/seo.json`, groupé par langue
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
   `DB_PORT`, `DB_VERSION`) et **`DB_NAME` = nom du projet avec des `_`** (ex. projet `hotel-parister-2026`
   → `DB_NAME=hotel_parister_2026`). **Si la base n'existe pas**, la créer puis monter le schéma :
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

## Polices & variables SCSS (front)

**Vérifier TOUTES les polices de la maquette** (sur **tous** les écrans, pas que la home) :
scanner les styles de texte Figma → recenser **chaque famille ET chaque graisse/style** réellement
utilisées (typiquement : une police de **corps** en plusieurs graisses + une **cursive décorative**
pour les titres + une police **secondaire**). **Intégrer TOUTES ces familles**, pas seulement la
principale. **Signaler les écarts** : une graisse présente dans la maquette mais absente des
webfonts de prod doit être sourcée (licence) ou mappée sur la plus proche.

**Récupérer les polices du projet** (depuis le CSS de prod : règles `@font-face` / `font-family`,
ou à défaut les styles de texte Figma) et **les intégrer EN LOCAL** (jamais de CDN externe) :
- **Fichiers de police** (woff2/woff…) → `assets/lib/fonts/`.
- **Déclarations `@font-face`** → `assets/scss/front/default/fonts.scss` (pointant vers `assets/lib/fonts/`).
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
- **Exclus de la génération par page** : lors du parsing d'un `[page…]`, ignorer les sous-arbres `[nav]` et `[footer]` — ne créer ni Zone, ni Col, ni Block pour eux **au niveau de la page**.
- Pour chaque page, ne générer que le **contenu propre à la page**, situé entre la nav et le footer.
- Conséquence : si la nav/le footer existent déjà dans le layout de base, ne pas les recréer ; sinon, les créer **une fois** puis les réutiliser sur toutes les pages.

## Lire les interactions du prototype (menus, hover, animations)

Le prototype Figma (URL `figma.com/proto/...`, ou nœud lu en mode dev) est **lisible
en données** via l'API REST (`/v1/files/:key/nodes?ids=<id>&depth=N`, scope
`file_content:read`) : chaque nœud porte le cas échéant un tableau `interactions`.
On peut donc reconstruire le graphe d'interactions, mais **pas** « jouer » le
prototype en vidéo.

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
