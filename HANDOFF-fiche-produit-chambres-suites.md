Implémentez ce design à partir de Figma.
@https://www.figma.com/design/VxyHdf12DFWhx0I6SxX9ce/Parister?node-id=516-2245&m=dev

# Reprise — Fiche produit « chambres-suites » (ISO maquette Figma)

> Objectif : rendre la vue produit dédiée `chambres-suites` conforme à la maquette Figma
> (node `516-2245`, fichier `VxyHdf12DFWhx0I6SxX9ce`). Cible qualité visée : 9/10 ISO maquette.
> URL de test : `http://hotel-parister-2026.local/chambres-suites/fiche-produit/<slug>`
> (superior-room, duplex-suite, junior-suite, deluxe-terrasse…). **Servir en HTTP** (le HTTPS local
> sert un autre projet, cf. vhost `httpd-ssl.conf`).

---

## Fichiers modifiés

- `templates/front/default/actions/catalog/view/chambres-suites.html.twig` — template de la vue.
- `assets/scss/front/default/templates/catalog.scss` — styles de la vue.
- `assets/js/front/bootstrap/modules/carousel.js` — compteur « 1 / N » générique (galerie).
- `src/Form/Type/Module/Catalog/ProductType.php` — champ `placeholder` ajouté après `subTitle`.
- `src/Command/Catalog/ProductEnrichCommand.php` — **nouveau** (voir « Données » ci-dessous).

## Données modifiées en base (sans régénération)

Commande `php bin/console app:catalog:enrich-products` (idempotente) déjà exécutée :
- **Titres** : `intl.title` de chaque produit enrobe le 2ᵉ mot d'un `<span>`
  (`Chambre Supérieure` → `Chambre <span>supérieure</span>`), toutes locales. Rend le kicker + script
  **depuis la donnée** (pas de split Twig). Le `<span>` est stylé côté SCSS (script) dans l'entête
  ET dans la colonne infos.
- **Galerie** : 3 photos de salle de bain ajoutées par chambre → **4 médias/chambre** (le compteur
  « 1 / 4 » et les flèches n'apparaissent que si `medias|length > 1`).

⚠️ **Divergence fixtures** : `CatalogFixtures` génère toujours des titres SANS span et 1 seule image
par produit. Un `doctrine:fixtures:load` complet **écrase** ces enrichissements → relancer la commande
après, OU reporter la logique (span + galerie) dans `CatalogFixtures` pour que ce soit durable.

---

## Ce qui est fait (vérifié par capture 1:1 à l'échelle maquette)

### Entête
- Inclusion du **bloc de base** `blocks/title-header/default.html.twig` (et non un hero custom) :
  gère titre + baseline. Passé : `pageTitle`, `customMedia: heroMedia`, `baseline` (= `placeholder`),
  `titleForce: 1` (h1 dans l'entête), `isIndex: true` (supprime le fil d'Ariane), `styleClass: 'product-header'`.
- Le style entête maquette existe déjà dans `components/blocks/_title-header.scss` : `.title` (kicker 38px),
  `.sub-title` (script 133px), `.baseline-bar` (bande 69px, filet + **motifs de traits verticaux** à 40px des bords).
- Kicker + script rendus via le `<span>` du titre. Règle scopée dans `catalog.scss` :
  `.title-header-block.product-header .title span` (script clair `$light`).
- **Image entête** : `room-*.jpg` est **portrait (1163×1600)** ; `thumbConfigurationHeader` la recadrait
  en bande large (tête de lit/bois). Bypassé via `thumbConfiguration: {}` → image pleine, chambre visible.

### Détail (galerie + infos)
- Galerie = **carousel Bootstrap** (`data-component="carousel-bootstrap"`), fade, `screenSizes` desktop **690×648**
  (aspect 1.065, confirmé JSON). Voile `::after` `rgba($black,.2)`.
- **Flèches** = carrés arrondis **80×80**, translucides, ombre douce, **chevron bleu marine `$secondary`**,
  débordant à mi-bord (`left/right: -40px`). Valeurs relevées sur le JSON Figma (composant « CTA Box »).
- **Compteur « 1 / N »** sous la galerie (14px / .2em / UPPER / `$dark`), mis à jour au slide via un
  ajout générique dans `carousel.js` (attribut `data-carousel-counter`).
- Titre infos en `<strong>` : kicker (`$dark`) + `span` script (`$primary`/or) + surface « 17 M² ».
- **UN** filet horizontal `#141414` entre l'intro et l'accordéon (`.product-info-divider`).
- Accordéon : flèches **↑/↓** or (`icon-arrow-up/down`, rotation sur `:not(.collapsed)`),
  `.accordion-item` **sans bordure** (consigne utilisateur).
- Bordures : `#product-view` **border-bottom 1px #141414** (= « Line 16 » pleine largeur maquette) ;
  `.product-info-col` **border-left 1px #141414** (= « Line 14 » verticale maquette).
- CTA « Réserver cette chambre » : barre or pleine largeur, padding vertical ajouté.

### Teaser « Les autres chambres »
- Rendu **identique au teaser home** `.products-slider-multi-teaser-container` (mêmes `data-*`,
  offset 250, flèches macro, cartes `card-cover`), dans un `container-fluid-right`.
- Correctif clé : passer `thumbConfiguration: {}` à `card-cover` (sinon image partielle + aplat de couleur).

---

## Méthode de capture FIABLE (indispensable)

Les captures « rapides » sont **fausses** : l'`IntersectionObserver` qui initialise les sliders Splide
ne se déclenche pas → teaser vide, sliders non initialisés. Toujours :
1. `puppeteer-core` (installé en `--no-save`), Chrome `C:/Program Files/Google/Chrome/Application/chrome.exe`.
2. `deviceScaleFactor: 2` → capture **2880px de large = échelle maquette** (comparaison 1:1).
3. **Scroll lent** (pas ~350px / ~150ms) sur toute la page **puis attendre ~2,5 s** (init sliders),
   retour en haut, retirer `#sfToolbar`/`.sf-toolbar`/`[class*=axeptio]`, screenshot `fullPage`.
4. Comparer en découpant les zones à la **même échelle** que la maquette (JSON = source de vérité pour
   la géométrie : positions/tailles via `absoluteBoundingBox`, origine page `27517,-910`).

Scripts jetables dans le scratchpad de session (`shotf.mjs`, `heroimg.mjs`, `teaser.mjs`, comparaisons `x-/y-/w-`).

---

## Pièges rencontrés (à retenir)

- **`.img-loader-wrap` a `z-index:10` global** → masque voile `::after`, flèches et caption sur toute
  superposition. Réempiler explicitement (image z1 / voile z2 / contrôles z3). Corrigé sur hero et galerie.
- **`|file(thumbConfiguration, {screensSizes})`** : si un `thumbConfiguration` non vide est passé, il
  **prime** et les `screensSizes` sont ignorés. Passer `{}` pour imposer ses propres dimensions/crop.
- **Tables préfixées `cms_`** (Doctrine au runtime) : le SQL brut sur `media`/`product` échoue ; passer par l'ORM.

---

## Reste à faire / à décider (reprise)

1. ✅ **Cadrage image entête** (fait 2026-07-16) : la photo produit est portrait ≠ mockup Figma.
   `object-position: 50% 65%` scopé `.title-header-block.product-header .media-content img` (dans
   `catalog.scss`) pour révéler le lit plutôt que le mur/plafond. La règle globale `50% 50%` de
   `_title-header.scss` est inchangée (partagée par tous les entêtes du site).
2. ✅ **Filets bord-à-bord** (fait 2026-07-16) : le `product-info-divider` s'étend jusqu'au filet vertical
   (`margin-left: rem(-40px)` sous `min-lg`, annule le padding gauche colonne).
3. ✅ **Séparateurs d'accordéon** (tranché 2026-07-16) : l'affirmation « hairlines entre items » **était
   FAUSSE** — vérification sur l'image rendue du node Figma : la maquette n'a **AUCUN filet entre les items**
   (un seul filet, entre l'intro et l'accordéon). Consigne « pas de border sur `.accordion-item` » = ISO maquette.
   Ne pas ré-ajouter de hairlines.
4. ✅ **Ratio colonnes** (fait 2026-07-16) : grille maquette 690 / gap 80 / infos 590 appliquée sous `min-lg`
   dans `catalog.scss` (`width: 50.735%` / `43.382%` + `margin-left: 5.882%`), row en `g-0`, `pe-lg-*` retirés.
5. ✅ **Durabilité** (fait 2026-07-16) : `ProductEnrichCommand` conservé (idempotent) ; rappel documenté
   dans le docblock de `CatalogFixtures` (relancer `app:catalog:enrich-products` après `fixtures:load`).
6. ✅ **Chambres vérifiées** : `superior-room`, `duplex-suite`, `junior-suite` répondent HTTP 200.
7. **Nettoyage** : `puppeteer-core` reste en `--no-save` (pas dans `package.json`).
8. **Hors périmètre mais visible** : sections newsletter / socialwall / footer (blocs de layout) diffèrent
   de la maquette — non traitées ici.

---

## Rappel process (CLAUDE.md)
Aucun commit sans demande explicite. Rigueur ISO maquette : extraire le node (image + JSON), relever les
valeurs exactes, builder, **capturer en local (méthode fiable ci-dessus) et comparer 1:1** avant de conclure.
