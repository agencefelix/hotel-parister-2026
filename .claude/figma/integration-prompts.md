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

**Ne jamais inclure dans une capture de bande un élément identifié autrement.**
Les éléments de layout (`[nav]`, `[footer]`, et tout élément commun à chaque page)
ne doivent **pas** apparaître dans les captures de bandes de page : borner le
découpage à la **zone de contenu** (exclure la région verticale du footer/nav).
Ces éléments de layout ont leurs **propres captures** dans un dossier commun
`.claude/figma/integration/screenshots/layout/` (ex. `layout/nav.png`, `layout/footer.png`),
puisqu'ils sont intégrés une seule fois et partagés par toutes les pages.

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
- **Vraiment non dérivables → `null`** : `favicons`, `fonts`, lat/long,
  `googleMapUrl`, horaires, et données société absentes des mentions légales.

Artefact : `.claude/figma/integration/config.json` (mêmes clés que `default.yaml`, éditable).
**Contraintes** : chaque section porte un `_source` (`figma` | `prod`) ; les
**couleurs viennent EXCLUSIVEMENT de Figma** (jamais de la prod) ; `prod_url` par
défaut = `"URL PROD"` ; les mappings sémantiques de couleurs (primary/secondary…)
restent **à confirmer** manuellement (la maquette donne les hex, pas leur rôle).

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
