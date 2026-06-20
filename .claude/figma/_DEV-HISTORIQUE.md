<!-- ============================================================== -->
<!-- 🛑 NE PAS CONSULTER CE FICHIER SAUF DEMANDE EXPLICITE DE L'UTILISATEUR -->
<!-- ============================================================== -->

> # 🛑 NE PAS CONSULTER CE FICHIER SAUF SI L'UTILISATEUR LE DEMANDE EXPLICITEMENT
>
> Fichier de travail temporaire. À ignorer totalement par défaut (ne pas le lire,
> ne pas s'en servir comme contexte). Il sera supprimé à la fin du dev.

---

# 🚧 Historique de dev — module Figma → CMS (TEMPORAIRE)

> ⚠️ **FICHIER DE TRAVAIL — NE PAS EN TENIR COMPTE** sauf demande explicite de
> l'utilisateur. Sert uniquement de point de reprise pendant le dev de ce module.
> Les informations durables vont dans les autres `.md` de `.claude/figma/`
> (`integration-prompts.md`, `mapping-blocktypes.md`, `hotel-parister-2026.md`,
> `layout/*.json`). Ce fichier sera **supprimé** une fois le module calé.

Dernière mise à jour du contexte : dev en cours, **aucune écriture en base** à ce stade.

---

## Objectif du module

Pipeline semi-automatisé **maquette Figma → CMS** (SFCMS 7 / Symfony 7.4). Le
nommage des calques (convention) est le contrat designer ↔ code. Étape actuelle :
un **dry-run** qui lit une page Figma et produit son architecture CMS
(Page → Zones → Cols → Blocs/Modules) **sans rien persister**, + artefacts JSON +
captures, pour validation/correction manuelle avant toute génération en base.

## Décisions & acquis (résumé — détail dans les autres .md)

- **Connexion** : token REST dans `.env` (`FIGMA_TOKEN`, scope `file_content:read`, lecture seule) via `App\Service\Figma\FigmaApiClient`. OAuth MCP = navigateur requis, non utilisé. `FIGMA_FILE_KEY` aussi en `.env`.
- **Convention** : source de vérité en ligne https://figma-doc.agence-felix.fr/ (évolue → re-consulter à chaque fois).
- **Mapping** : atomes = `BlockType` par **slug** (title, text, media, link, card…) ; **modules** = bloc `core-action` + Action `*-view` + **entité module**. Page = catégories `content`+`global` (jamais `layout`). `layout-slider` ≠ page.
- **Slider** : `[slider|variant]` → `Slider::setTemplate()` (prePersist cascade le reste) ; slides = `SliderMediaRelation` par locale ; import média réel.
- **nav/footer** : layout de base, **intégrés une seule fois**, **exclus** de la génération par page. Descripteurs déclaratifs `layout/nav.json` + `layout/footer.json`.
- **Captures** : capturer avant d'interpréter, en **HD** (découper les pages longues). Bandes par page → `screenshots/<slug>/` (sans nav/footer). Éléments de layout → `screenshots/layout/` (un par état, ex. nav fermée/ouverte).
- **Déduction zones (sans `[zone]`)** : bandes pleine largeur (hauteur min) + x-clustering pour les cols ; ±1 d'incertitude ; un élément **pleine largeur taggé = bloc** (pas un fond) — bug corrigé.

## Ce qui est construit

**Services** (`src/Service/Figma/`) : `FigmaApiClient`(+Interface), `Exception/FigmaApiException`, `ConventionMapper`, `PageParser`, `PageTreeExporter`, `PageScreenshotter`, `LayoutScreenshotter`, `Dto/Parsed{Page,Zone,Col,Block}`.

**Commandes** (`src/Command/Figma/`) :
- `figma:parse-page <node-id>` → arbre + `pages/<slug>.json` + captures bandes (`screenshots/<slug>/`) + captures layout.
- `figma:capture-layout` → (re)génère `screenshots/layout/` depuis `layout/*.json` (déclaratif).

**Config** : `.env` (`FIGMA_TOKEN`, `FIGMA_FILE_KEY`), `parameters.yaml`, `services.yaml` (binds `$figmaToken`, `$figmaFileKey`, `$projectDir`).

**Artefacts** : `pages/home.json`, `layout/nav.json`, `layout/footer.json`, `screenshots/home/*`, `screenshots/layout/*`.

## État actuel (home = node `542:1592`)

- ✅ `[page|home]` parsé ; `[nav]` + `[footer]` exclus ; `[slider]` (hero pleine largeur) mappé module.
- ✅ `home.json` valide ; 10 captures de bandes (sans footer) ; layout : nav-closed / nav-open / footer.
- ⚠️ Zones **déduites** = 10 (sur-segmentation vs ~8-9 visuelles) : normal sans tags `[zone]`.

## Reste à faire / pistes

- [x] **Images de slider extraites** : `block.media` (`{figmaNodeId, image, imageRef, width}`) + rendu dans `.claude/figma/integration/media/<slug>/` (fills `IMAGE`). Testé sur la home (1 slide, 3845×2201px).
- [x] **Résolution média correcte** : scale par média (~3840px full-width, retina sinon), **plafond dur 3840px** (floor + réduction des nœuds plus larges). ⚠️ piège PHP corrigé : clé de tableau float tronquée en int → forcer une clé string.
- [x] **Compression sans perte** : médias encodés en **WebP lossless** (`IMG_WEBP_LOSSLESS`). Ex. home : 3831×2192px, 5340 Ko PNG → 3497 Ko webp, sans perte. (`pngquant` dispo mais lossy → écarté ; `optipng` lossless ~7% seulement.)
- [x] **Config globale extraite** (alignée sur `bin/data/config/default.yaml`) → `.claude/figma/integration/config.json` : company_name, phones, emails, addresses, social, palette colors (fills SOLID). Mapping sémantique couleurs à confirmer.
- [x] **Enrichissement via site de prod** : règle = demander l'URL de prod (`prod_url`, défaut `"URL PROD"`) à la génération du config projet. URL = https://www.hotelparister.com → complété : URLs réseaux (fb/insta/linkedin), légal (HOTEL SAULNIER, capital, TVA, SIRET, mentions/cookies), domaine, **GTM-PR9R52V**. Restent null : lat/long, tiktok, GA4/ua, DPO/gérant/hébergeur. ⚠️ Pas encore automatisé → à wirer en commande `figma:extract-config`.
- **RÈGLE CLÉ — séparation des sources** : prod = données TEXTE/config (réseaux, GTM/analytics, noms, emails, tél, légal, domaines) ; Figma = SEULE source du design (couleurs, structure, layout, médias). Jamais croiser. `prod_url` resservira pour d'autres infos textuelles.
- [x] **Toutes les URLs de prod récupérées** via sitemap (trouvé dans `/robots.txt` → `/direct/core/sitemap-xml`, PAS `/sitemap.xml`).
- [x] **Multilingue détecté + URLs par langue** : via hreflang home + sitemaps robots.txt. 4 langues — fr (www.hotelparister.com, 54), en (www.paristerhotel.com, 12), es (es.hotelparister.com, 6), zh (cn.hotelparister.com, 6) = 78 URLs. → `.claude/figma/integration/prod-urls.json` groupé par langue. `config.json` : locales_others=[en,es,zh] + domains par langue.
- [x] **SEO crawlé pour les 78 URLs** → `.claude/figma/integration/seo.json` (title, description, keywords, robots, canonical, OG, h1), groupé par langue. Manques relevés sur prod : 22 pages FR sans meta description (événements/articles), 1 page sans title (`/flash-info-apicole`). ⚠️ Crawl scripté ad hoc (scratchpad) → à wirer en commande si besoin de réexécution.
- [x] **Multilingue : appariement des URLs par langue** (2026-06-19, suite). Sitemaps étrangers incomplets → URLs **complétées par crawl des liens internes** (BFS par domaine) : fr 54→73, en 12→27, es 6→11, zh 6→10 (total 120 après exclusion `/404`). Appariement par **hreflang de la prod**, modèle robuste : on rattache chaque page étrangère à une ancre fr via (1) son back-link fr s'il est au sitemap, sinon (2) l'unique page fr qui la déclare ; conflits tranchés par le back-link. Résultat dans `prod-urls.json` : `alternates` par URL fr, `language_groups` (73 groupes fr + 11 orphelins, rien perdu), `_hreflang_anomalies` (7 vrais bugs prod : `chambres-suites` vole le trio de `a-voir-a-faire` ; trio resto back-linké vers `restaurant-bar-a-cocktail-copy-754` hors sitemap ; salle-réunion/jeu-de-piste). Scripts ad hoc en scratchpad (`discover.php`, `build_alt.php` + cache hreflang) → à wirer en commande si réexécution. **À signaler au client : corriger ces hreflang en prod.**
- [x] **Nouvelles règles d'intégration ajoutées** à `integration-prompts.md` (génériques) : (a) **flèche à droite d'un texte = lien** (atome `link`/`cta`) ; (b) **lecture des interactions du prototype** via REST (`interactions`: trigger ON_CLICK/ON_HOVER/AFTER_TIMEOUT, action NODE/OVERLAY/URL, transition SMART_ANIMATE/DISSOLVE + durée) — confirmé sur node `410:4801` (544 nœuds porteurs d'interactions) ; rendre chaque état (fermé/ouvert, hover) en image ; (c) **newsletter = layout** (module `newsletter`/`ROLE_NEWSLETTER`, dans OTHERS_MODULES → à activer ; campagne `main` via `NewsletterFixtures`) ; (d) **mur social = layout AUTONOME (≠ footer)**, détecté par Instagram/**Facebook**/**Youtube**, module `ROLE_SOCIAL_WALL`.
- [x] **Re-check maquette (home) avec les nouvelles règles** : le bloc **newsletter** (titre `542:1674` enfant direct de `[page|home]` + formulaire) et le **mur social** ('Suivez-nous sur insta', instance `542:1816`) étaient déduits comme sections de page → **reclassés en layout**. Créé `layout/newsletter.json` + `layout/social-wall.json` ; `home.json` : ancienne bande 10 supprimée, bande 9 annotée, `excluded` complété. ⚠️ Ces blocs ne sont **pas balisés** dans la maquette (posés à plat) → inviter le créa à les tagger.
- [x] **Captures dédiées layout** newsletter/social-wall : rendues et rangées dans `screenshots/layout/` (`newsletter.png` = node `542:1664`, `social-wall.png` = node `577:1371`), descripteurs corrigés pour référencer le **basename** (plus de pointeur vers `home/`). Capture `section-home-10.png` **supprimée** (c'était la bande reclassée en layout → doublon). Règle « garder les captures synchronisées » ajoutée à `integration-prompts.md`.
- [x] **Interactions du prototype cartographiées** (node `410:4801`) → `interactions/proto-410-4801.json`. 8 interactions : 4 hover (swap Variante2, SMART_ANIMATE ~1s), 2 auto (AFTER_TIMEOUT → CHANGE_TO, 10s / 3s), **2 clics → OVERLAY** (DISSOLVE 0.3s) = ouverture du **menu** (overlay `142:3426` « Frame 49 », confirmé visuellement). ⚠️ Limite API REST : `destinationId` d'un OVERLAY souvent `null` (cible inférée via l'unique frame de la page « Overlay »).
- [x] **Couleur de fond des zones** : implémentée puis **fiabilisée** dans `PageParser` (`nodeFill` + `backgroundCandidates` + `backgroundForRange`). Rigueur : fond **qui COUVRE** la bande (pas juste au sommet — un rect navy/teal couvre 2 bandes), **exclusion des TEXT/LINE/VECTOR** (piège : filigrane « parister » pris pour un fond #ffffff/#b48608), support **GRADIENT**, **repli sur le fond de page** (#f4f0f1), IMAGE → null. Croisé avec les captures. `home.json` corrigé : bandes 1/3 = null (image), 2/6/9 = #f4f0f1, 4/5 = #001e56, 7/8 = #8fb3b1. Vérifié : sortie code == valeurs retenues.
- [x] **Règle URLs au démarrage** : demander impérativement (1) URL de prod et (2) URL du prototype (pour les animations) à la première intégration → `integration-prompts.md`.
- [x] **Modèle de projet** : `project-template.md` (valeurs `null`) à copier en `<nom-du-projet>.md` pour les futurs projets.
- [x] **`integration/` = LE projet (clean slate 2026-06-20)** : utilisateur a tout vidé d'`integration/` + supprimé config/prod-urls/seo → on repart « comme un nouveau projet ». **`config.json`, `prod-urls.json`, `seo.json` passent aussi sous `integration/`** (plus à la racine). `integration/` re-bootstrappé depuis `models/` (gabarits vides : config/prod-urls/seo + `hotel-parister-2026.md` + `layout/{nav,footer,newsletter,social-wall}.json` + dossiers `pages/ screenshots/ media/ interactions/`). Aucun code ne référence config/prod-urls/seo (crawl ad hoc) ; quand `figma:extract-config` sera wiré, écrire dans `integration/`. Doc/prompts/README/`cms-catalog` paths mis à jour. ⚠️ Tous les artefacts Parister précédents (home.json, captures, proto map…) sont supprimés — à régénérer.
- [x] **Réorganisation des dossiers** : les artefacts du projet sont déplacés sous **`.claude/figma/integration/`** (`pages/`, `layout/`, `screenshots/`, `media/`, `interactions/`, + la spec projet `hotel-parister-2026.md`). Restent à la racine `.claude/figma/` : génériques (`integration-prompts.md`, `mapping-blocktypes.md`), `models/`, et config texte (`config.json`, `prod-urls.json`, `seo.json`). **Code mis à jour** : défauts de chemins dans `FigmaParsePageCommand` (pages/screenshots/media) et `FigmaCaptureLayoutCommand` (layout/screenshots-layout) → `integration/`. Doc/prompts mis à jour.
- [x] **Dossier de modèles** `.claude/figma/models/` créé (à **maintenir à jour tout au long du dev**) : `README.md`, `project-template.md` (déplacé depuis `figma/`), modèles vides JSON (`config`, `prod-urls`, `seo`, `pages/page`, `layout/{nav,footer,newsletter,social-wall}`, `interactions/proto`), **`cms-catalog.json`** (catalogue réel BlockTypes/Actions/Modules extrait de la base) et **`portability-risks.md`** (risques de réutilisation sur un autre projet, fichier vivant).
- [x] ⚠️ **Correction social-wall** : il n'existe **aucun module `social-wall`/`ROLE_SOCIAL_WALL`** en base (constante `WebsiteFixtures` sans `Module`/`Action`). Le mur social = blocType **`social-networks`** (global) ou config `social_networks` ; un feed = dev spécifique. Corrigé dans `layout/social-wall.json`, `models/*`, `cms-catalog.json`, `integration-prompts.md`.
- [x] **`.claude/figma` versionné** (2026-06-20) : la règle `.gitignore` `.claude/` empêchait la ré-inclusion `!.claude/figma/` (git interdit de ré-inclure sous un parent exclu) → passée à `.claude/*`. Dossier committé (`a3c745ab`).
- [x] **Artefacts Parister régénérés** (2026-06-20, après le clean slate) — indépendants du cache, choix utilisateur :
  - `figma:parse-page 542:1592` → `pages/home.json` (10 zones déduites), 10 captures de bandes (`screenshots/home/`), `media/home/slide-542-1793.webp` (3,5 Mo, webp lossless).
  - Descripteurs `layout/{nav,footer,newsletter,social-wall}.json` recréés avec node-ids localisés (footer `542:1676`, newsletter frame `542:1664`/titre `542:1674`, mur social `577:1371`/instance `542:1816`, nav états `55:3139`/`386:1793`/`42:1745`/`484:3706`) → `figma:capture-layout` = 7 captures `screenshots/layout/` (orphelin `nav.png` supprimé).
  - `interactions/proto-410-4801.json` régénéré (8 nœuds : 4 hover CHANGE_TO `106:673` SMART_ANIMATE 1.02s, 2 auto-timeout, 2 clics OVERLAY DISSOLVE 0.3s → cible inférée `142:3426`).
- [ ] ⚠️ **Cache API Figma TOUJOURS périmé** : au check du 2026-06-20, l'API REST sert encore `lastModified=2026-06-19T15:54:39Z` (tags récents NON visibles → home toujours 10 zones déduites, newsletter/social-wall non taggés dans la maquette). Re-scanner le balisage quand le cache se rafraîchit (ou après une sauvegarde forçant un bump de version) puis figer la structure (zones/cols, newsletter/social-wall taggés). Bandes 9/10 = newsletter + mur social posés à plat (reclassés layout via `layout/*.json`).
- [ ] **v2 — relire un JSON corrigé** comme source de vérité (au lieu de re-déduire à chaque run).
- [ ] **v2 — extraire le contenu TEXTE fin** (titres/textes/CTA des blocs et des slides) ; aujourd'hui : structure + médias + compteur de calques non balisés.
- [ ] Extraire la **couleur de fond** des bandes (`background` = null actuellement).
- [ ] Trancher `[faq]` : bloc `collapse` vs module `faq-view`.
- [ ] **Injection DB réelle** — UNIQUEMENT sur demande explicite (rien pour l'instant).
- [ ] Améliorer la déduction des cols (le full-width au-dessus de 2 colonnes peut fusionner le clustering).

## Conventions de travail à respecter

- **Aucune écriture en base** tant que le dry-run n'est pas validé.
- MD de `.claude/figma/` = **générique réutilisable** ; spécifique projet → `hotel-parister-2026.md`.
- Une capture de bande ne doit jamais contenir un élément identifié autrement (nav/footer).
