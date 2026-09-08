# Traductions FR → EN du contenu intl

Session du 2026-09-07, base locale `hotel_parister_2026`. Travail arrêté à 16:45 (heure française) sur consigne.

## Contexte

1. Purge préalable des locales `es` / `zh` (faite : ~558 lignes supprimées sur 99 tables).
2. Audit des contenus `intl` où seul `fr` était renseigné, puis traduction anglaise.

Critère de « à traduire », repris de `ExportService::getIntlHaveContent()` :
la valeur `fr` est non vide après `strip_tags`, et la contrepartie `en` est absente ou vide.

Champs traduisibles selon `ExportService::getCsvIntlsIndex()` :
`title`, `subTitle`, `introduction`, `body`, `targetLink`, `targetLabel`,
`placeholder`, `help`, `error`.

## Périmètre validé par l'utilisateur

- Front éditorial uniquement, bandeau cookies RGPD inclus.
- Slugs d'URL EN (`cms_seo_url`) et métas SEO EN.
- Chaque URL `en` passée en `online = 1` quand l'URL `fr` correspondante est en ligne.
- Liens de menu EN — **en ajoutant des liens aux menus existants, sans dupliquer les menus**.
- Lorem ipsum : recopié tel quel de `fr` vers `en`.
- **Exclu** : back-office (`cms_core_module_intls` 84 champs, `cms_layout_block_type_intls` 42,
  `cms_security_message_intls` 7, `cms_layout_field_value_intls` 2,
  `cms_module_newsletter_campaign_intls` 1) — reste en français.

## Décisions de méthode

- Traductions rédigées à la main (pas le `DeepLService` du CMS, sur demande explicite).
- Lignes `en` créées uniquement là où il y avait du contenu à traduire, pour ne pas
  semer de lignes vides susceptibles de court-circuiter le fallback `fr`.
- Balisage HTML préservé à l'identique (`<p>`, `<span>`, `<br>`, `<sup>`, `&eacute;`,
  `&hellip;`, `&sup2;`…) : seul le texte change, chaque ligne conservant son propre style
  d'encodage (entités ou caractères accentués).
- Noms propres et titres d'œuvres laissés en français : `Les Passerelles`, `Hôtel Parister`,
  « Connectés », « Âme d'enfants », « Les voiles de l'aube », « Les Italiens »,
  « Élémentsbis », « Fais signe ». Seuls les mots autour ont été traduits.
- Chaque écriture passée en transaction, avec une exécution en simulation (rollback)
  validée avant le `--commit`.
- Sauvegarde préalable : `scratchpad/backup-hotel_parister_2026-20260907-134921.sql` (1 Mo).

## Ce qui a été fait

### 1. Contenu intl traduit — 270 champs, 150 lignes `en`

128 lignes `en` créées, 22 complétées.

| Table | Entités | Champs |
|---|---|---|
| `cms_module_newscast_intls` | 25 | 75 |
| `cms_gdpr_group_intls` | 21 | 59 |
| `cms_layout_block_intls` | 39 | 56 |
| `cms_module_catalog_feature_value_intls` | 43 | 43 |
| `cms_module_catalog_product_intls` | 7 | 22 |
| `cms_module_catalog_feature_intls` | 10 | 10 |
| `cms_layout_page_intls`, `cms_information_intls`, teasers produit et actu, catégorie actu | 5 | 5 |

### 2. Lorem ipsum recopié `fr` → `en` — 71 champs, 36 lignes

`cms_module_catalog_feature_value_intls` (56), `cms_module_catalog_feature_intls` (12),
`cms_module_newscast_category_intls` (2), `cms_layout_block_intls` (1).

### 3. URLs EN — 50 URLs, toutes en ligne

- 44 URLs `en` créées avec slug traduit, plus la ligne de jointure vers leur propriétaire
  (`cms_layout_page_urls`, `cms_module_catalog_product_urls`, `cms_module_newscast_urls`).
- 5 URLs `en` préexistantes avec `code = NULL` complétées.
- 5 URLs `en` passées en ligne pour s'aligner sur le `fr`.
- Résultat : 50 `fr` / 50 `en`, toutes `online = 1`, aucun code manquant,
  aucune URL orpheline, aucun doublon de code au sein d'une même locale.
- Les slugs `fr` des chambres étaient déjà en anglais (`superior-room`, `junior-suite`…) :
  les slugs `en` sont identiques. Sans risque, `UrlRepository` filtrant par locale.

### 4. Liens de menu EN — 30 liens, 30 intl

- Les 6 menus existants sont **inchangés** ; seuls des liens `en` y ont été ajoutés.
- Hiérarchie du menu principal reconstruite : 12 relations parent/enfant remappées
  vers les nouveaux liens `en` (passe 2 du script).
- Résultat symétrique : `main` 16/16, `footer-hotel` 4/4, `footer-utiles` 4/4,
  `footer-actualites` 3/3, `footer-legal` 2/2, `footer-passerelles` 1/1.

### 5. Correctif `website_id`

3 URLs `en` préexistantes (ids 50 `home`, 53, 60 `rooms-suites`) avaient
`website_id = NULL` alors que leur homologue `fr` porte `website_id = 1`.
C'est ce qui faisait répondre 404 à l'accueil EN. Corrigé en reprenant la valeur
du `fr` via les tables de jointure. Défaut préexistant, pas introduit par cette session.

### 6. mediaRelation EN — 59 lignes

Créées en miroir des `fr` (même média, même position) :
`cms_module_newscast_media_relations` 0 → 25, `cms_module_catalog_product_media_relations`
0 → 33, `cms_module_slider_media_relations` 10 → 11. Symétrie atteinte en base.

### 7. Parité structurelle FR / EN — atteinte

Audit systématique via `scratchpad/parity.php` : balaie **toutes** les tables portant une
colonne `locale`, apparie les lignes `fr` et `en` sur (propriétaire [, categorySlug]
[, position]) et classe chaque écart en quatre catégories.

| | Avant | Après |
|---|---|---|
| lignes `en` absentes | 185 | **0** |
| configurations divergentes | 85 | **45, toutes voulues** |
| champs traduisibles vides en `en` | 398 | **0** |

Les 45 écarts subsistants sont intentionnels : libellés traduits (« Book » vs « Réserver »),
`adminName` et `parent_id` des liens de menu anglais, et une adresse e-mail qui diffère
en base (`no-reply@` vs `noreply@`, donnée métier).

Corrections apportées :

| Écart | Correction |
|---|---|
| **Doubles boutons** : `targetPageSecondary_id`, `targetStyleSecondary`, `targetLabelSecondary` vides en `en` | 5 blocs alignés ; libellés traduits (« Réserver » → Book, « Réserver un soin » → Book a treatment) |
| **Dimensions médias** : `maxWidth` / `maxHeight` vides en `en` | 8 mediaRelation de blocs, variantes tablette et mobile incluses |
| Drapeau `init` inversé | 8 mediaRelation de zones et de pages |
| `axeptioExternal` | 1 ligne `cms_api_custom_intl` |
| **Contenu des slides de carrousel** | 33 champs traduits, 5 `cms_media_relation_intl` créées et rattachées |
| `targetLink` des slides | remappés sur les slugs anglais (`/spa-wellness-fitness`, `/rooms-suites`, `/meeting-rooms-events`) |
| mediaRelation RGPD | 20 lignes `en` créées |
| Médias divergents | bloc 36 (80 → 296) et une zone (560 → 562) réalignés sur le `fr` |

### 8. Vérification navigateur des 17 pages

Mesuré dans Chrome via `playwright-cli` (scroll complet, attente de résolution des
fragments) : `images / chargées / cassées / <picture> / fragments / liens`.

**Images identiques sur les 17 pages, aucune image cassée nulle part.** Home :
53 / 51 / 0 / 49 / 0 en FR comme en EN. Fiche produit `superior-room` : 9 / 7 / 0 / 5 / 0
dans les deux langues.

### 9. Configuration de lien des mediaRelation intl

`cms_media_relation_intl` n'a **aucune FK vers son propriétaire** (c'est la mediaRelation
qui la porte, via `intl_id`). Elle était donc classée « sans clé d'appariement » par
`parity.php` et n'a jamais été comparée : angle mort de l'audit, corrigé par
`scratchpad/fix-mr-intl-links.php`, qui apparie en passant par la relation propriétaire
sur (propriétaire [, categorySlug] [, position]).

Écarts trouvés et corrigés — 4 lignes, 6 colonnes :

| intl `en` | Depuis `fr` | Colonnes |
|---|---|---|
| 27 (Stay) | 25 | `targetPage_id` = 1, `titleForce` = 3 |
| 28 (Eat & drink) | 31 | `targetPage_id` = 1 |
| 34 (Unwind) | 33 | `targetPage_id` = 1, `titleForce` = 3 |
| 60 | 62 | `titleForce` = 3 |

C'est ce qui laissait les trois slides de `#slider-container-home-services-main` sans page
associée en anglais. `targetPage_id` pointe une Page, entité indépendante de la locale :
recopier la valeur du `fr` est correct, la résolution d'URL choisit ensuite l'URL `en`.
`targetLink`, lui, est un chemin localisé et n'est pas recopié.

### 10. Pages cibles du slider services — corrigées dans les deux langues

Après alignement du point 9, les trois slides de `#slider-container-home-services-main`
pointaient toutes vers la page 1 (l'accueil) — **y compris en français**. Erreur de saisie
d'origine, confirmée par l'utilisateur, corrigée dans les deux langues :

| Slide | `targetPage_id` | URL `fr` | URL `en` |
|---|---|---|---|
| Séjourner / Stay | 1 → **2** | `/chambres-suites` | `/rooms-suites` |
| Boire & manger / Eat & drink | 1 → **3** | `/restaurant-bar-a-cocktail` | `/restaurant-cocktail-bar` |
| Se détendre / Unwind | 1 → **4** | `/sport-bien-etre` | `/spa-wellness-fitness` |

Lignes touchées : intl 25 et 27 → page 2, intl 31 et 28 → page 3, intl 33 et 34 → page 4.
Les six URLs cibles répondent 200 et sont en ligne dans les deux locales.

C'est la seule modification de cette session qui touche le contenu **français**.

Harmonisation au passage : le même texte `fr` « Intimiste, contemporain et chaleureux »
avait deux traductions `en` divergentes (« cosy » préexistante sur l'intl 63,
« welcoming » sur l'intl 60). Aligné sur « cosy », la formulation déjà en place.

## Le piège du media loader du CMS (à connaître)

Le CMS diffère le rendu d'une mediaRelation via un fragment ESI `<hx:include>` tant que
son cache n'est pas construit, et affiche un placeholder `alt="SVG Loader"` en attendant.
Ce cache est indexé sur l'**`id` de la mediaRelation** : toutes les lignes `en` créées
dans cette session ont donc des ids neufs, sans cache.

Conséquences pratiques :

- Ce n'est **pas** un défaut de données. Vérifié sur les relations produit en cause
  (ids 72, 77, 86, 95) : identiques au bit près à leurs homologues `fr` — même `media_id`,
  même `cacheDate`, mêmes drapeaux, mêmes dimensions.
- **`curl` ne peut pas le mesurer** : les fragments sont résolus côté navigateur par
  hinclude.js. Toute comparaison FR/EN par `curl` sous-estime l'anglais. Utiliser un
  vrai navigateur.
- Le cache se construit au fil des visites : sur `/rooms-suites`, les fragments non
  résolus sont passés de 27 à 17 puis 15 sur trois passages. Les images, elles, sont
  déjà toutes présentes et chargées (17 / 15 / 0, identique au FR) — seuls les
  `<source>` responsives des fragments différés manquent encore.
- Avant une recette visuelle, amorcer les pages ou laisser tourner `app:thumbs:generate`.

## Reste à faire — PRIORITÉ

0. ~~**Parité de rendu FR / EN non atteinte.**~~ **Traitée**, voir points 7 et 8.
   Ancien constat, conservé pour mémoire : l'exigence était
   `http://hotel-parister-2026.local/` et `http://en.hotel-parister-2026.local/` doivent
   être identiques, contenu en anglais et liens vers les pages anglaises — et cela pour
   toutes les pages, fiches produit comprises.

   Mesures relevées sur l'accueil, après tous les correctifs ci-dessus et vidage du cache :

   | | FR | EN |
   |---|---|---|
   | poids HTML | 475 Ko | 265 Ko |
   | `<img>` | 49 | 19 |
   | `<picture>` | 45 | 15 |
   | `href="` | 127 | 69 |

   Ce qui a déjà été écarté comme cause :
   - la structure est identique : 12 sections, mêmes `id="zone-*"` de part et d'autre ;
   - aucun `hideLocales` ne masque `en` (`cms_layout_block`, `_zone`, `_col` : rien) ;
   - `cms_layout_block_media_relations` 24/24, `_zone_` 8/8, `_page_` 3/3 : symétriques ;
   - newscast, produit et slider media relations désormais symétriques (point 6),
     sans effet mesurable sur l'accueil — donc la cause de l'écart est ailleurs.

   Piste à explorer en premier : `cms_core_configuration_media_relations` est
   **asymétrique en sens inverse** (49 `fr` pour 84 `en`), ce qui suggère un appariement
   par `position` faussé sur les médias de configuration. Vérifier ensuite comment les
   blocs teaser de l'accueil résolvent leur média selon la locale
   (`ExportService` gère un cas `isMediaMulti` apparié sur `position`, voir
   `ExportService.php` autour de la ligne 315 — la même logique de position est
   probablement en jeu au rendu).

   Cause réelle identifiée depuis : `cms_layout_action_intl` comptait 11 lignes `fr`
   pour 1 seule `en`. Les actions de listing n'étant pas liées en anglais, les lames
   (actualités, chambres, carrousels) ne rendaient rien. Les 10 lignes créées, l'EN est
   passé de 264 à 447 Ko. La piste `cms_core_configuration_media_relations` que
   j'évoquais était une fausse piste : ses 84 lignes `en` pour 49 `fr` sont des
   doublons vides, sans incidence sur le rendu (voir point 3 ci-dessous).

1. **`__Découvrir` / `__Réserver` : bug visible aussi sur le site français.**
   Libellé de repli `'Découvrir'|trans`
   (`templates/front/default/actions/slider/template/include/card.html.twig:10`),
   utilisé quand une slide n'a pas de `targetLabel`. La clé n'est traduite dans
   **aucune** locale : le FR affiche donc « __Découvrir » à ses visiteurs, 10 fois sur
   la home. Domaine de traduction Symfony (`cms_translation` : 1676 unités mais
   seulement 768 traductions `fr` contre 1676 `en`), pas du contenu intl — hors du
   périmètre fixé, mais à corriger.

2. **Métas SEO EN** — non faites, et c'est volontaire : la table `cms_seo` est **vide**
   (les 9 lignes ont `metaTitle`, `metaDescription`, `breadcrumbTitle`… à `NULL`) et
   46 des 50 URLs n'ont même pas de `seo_id`. Il n'existe donc **aucune méta française
   à traduire** : les rédiger en anglais relèverait de la création de contenu, pas de la
   traduction. À arbitrer — rédaction FR d'abord, puis traduction.
3. **`cms_media_intls`** — 57 `title` non traduits, volontairement. Ce sont des
   identifiants techniques (`logo`, `favicon`, `hero-boutique-hotel`, `marker-blue`,
   `php-gdpr`…), pas des textes alternatifs. Si la logique « non traduisible ⇒ recopier
   le `fr` » doit s'y appliquer aussi, c'est une seule commande à lancer.
4. **Liens cookies du bandeau RGPD** — les 18 `targetLink` de `cms_gdpr_group_intls`
   ont été recopiés à l'identique, donc pointent vers les politiques en version
   française (`hl=fr`, `/hc/fr/`, `fr-fr.facebook.com`, `_l=fr_FR`). Les basculer en
   anglais demande de vérifier chaque URL une par une ; fabriquer les variantes sans
   contrôle exposait à des 404.
5. **`cms_layout_block_intls` fr_id=3** — le `body` français est inachevé : il mêle une
   phrase réelle et un fragment tronqué suivi de lorem (« …disponibilités. Lorem ipsum
   dolor sit amet del cironcstum… t&eacute;s. Lorem ipsum… »). Seule la phrase réelle a
   été traduite. Le texte FR est à reprendre côté rédaction.
6. **Lien externe Forstyle** — le lien `fr` du footer pointe vers
   `https://www.forstyle-hotels.com/fr`. Le lien `en` a été mis sur la racine
   `https://www.forstyle-hotels.com/` plutôt que sur un `/en` non vérifié.
7. **Contenu de fixtures à remplacer** — 71 champs sont du lorem ipsum jamais remplacé
   par du vrai contenu, désormais identique en `fr` et en `en` :
   `cms_module_catalog_feature_value_intls` (28 `introduction` + 28 `body`),
   `cms_module_catalog_feature_intls` (6 + 6), `cms_module_newscast_category_intls` (2),
   `cms_layout_block_intls` (1). À rédiger côté client.

## Points à signaler

- **`src/Service/Translation/DeepLService.php:20` : clé d'API DeepL en dur dans le code
  source** (`f4f69e71-…:fx`), et le service n'est appelé nulle part. À sortir du dépôt
  (variable d'environnement) ou à supprimer — une clé committée est à considérer
  comme compromise et à révoquer.
- `ExportService.php:65` : `generateSeo()` est commenté, donc l'export natif du CMS
  ne couvre ni les URLs ni les métas SEO. C'est pourquoi ces deux volets ont été
  traités à part.
- `http://en.hotel-parister-2026.local/` répondait 404 avant le correctif `website_id`
  du point 5 ci-dessus. Le vhost Apache et l'entrée `hosts` sont bien en place, et
  Symfony répondait déjà en anglais (`lang="en"`).

## Fichiers de travail (scratchpad de session)

| Fichier | Rôle |
|---|---|
| `backup-hotel_parister_2026-20260907-134921.sql` | sauvegarde avant toute écriture |
| `audit.php` | audit lecture seule des champs à traduire |
| `lorem.php` | détection du lorem ipsum (liste de mots latins + seuil 0,45) |
| `extract.php` → `todo.json` / `todo-clean.json` | contenu `fr` à traduire avec `fr_id`, `en_id`, `owner` |
| `translations.json`, `translations2.json`, `translations3.json` → `translations-all.json` | les 270 traductions |
| `apply.php` | écrit les traductions (INSERT si ligne `en` absente, UPDATE sinon) |
| `apply-lorem.php` | recopie le lorem `fr` → `en` |
| `slugs.json` + `apply-urls.php` | slugs EN, création des URLs, alignement de la mise en ligne |
| `menu-links.json` + `apply-menus.php` | libellés et slugs des liens de menu EN, remappage de la hiérarchie |

Tous les scripts acceptent `--commit` ; sans cet argument ils tournent en simulation
et annulent la transaction.
