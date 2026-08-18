# Recette de site - hotel-parister.felix-lait.com

Document de travail interne. La version destinee au client fait l'objet d'une relecture humaine avant envoi.

## 1. Perimetre

- **Cible** : https://hotel-parister.felix-lait.com/
- **Environnement** : preproduction (confirme : `X-Robots-Tag: noindex`, prefixe `[SEO Desactive]` dans les `<title>`, `data-axeptio=""`).
- **Type de site** : **vitrine hoteliere**. Signaux : navigation principale riche (chambres, restaurant, spa, evenementiel), pages rubriques + blog/actualites, page `/reservation` embarquant un moteur externe D-Edge (`websdk.d-edge.com`, `secure-hotel-booking.com`), aucune empreinte Prestashop ni tunnel e-commerce interne. La reservation et le paiement sont hors site, donc non testables de bout en bout.
- **Socle** : Symfony 7.4.10 / PHP 8.5.3 (tous deux en support actif), Webpack Encore, hebergement o2switch.
- **Inventaire** : 47 URLs parsees via `sitemap.xml` (source fiable). Mentions legales, politique cookies retrouvees en pied de page ; **aucune page CGV** trouvee.
- **Echantillon (15 gabarits)** : `/`, `/chambres-suites`, `/chambres-suites/fiche-produit/parister-suite`, `/la-vie-au-parister`, `/la-vie-au-parister/fiche-actualite/besoin-d-une-salle-de-reunion-a-paris`, `/blog`, `/restaurant-bar-a-cocktail`, `/sport-bien-etre`, `/salle-de-reunion-evenementiel`, `/acces-et-contact`, `/reservation`, `/bons-cadeaux`, `/galerie-photos`, `/plan-du-site`, 404 forge.
- **Accessibilite** : la racine repond HTTP 200 (~446 KB). Controle effectue une fois au cadrage, transmis aux agents.
- **Date** : 2026-08-14.
- **Agents mobilises** : audit-tech, audit-seo, audit-copy, audit-ux, audit-conversion.
- **Controles declares non applicables (type vitrine)** : controles e-commerce Prestashop (version/modules/overrides, cache Smarty, `_PS_MODE_DEV_`, stock/variantes, tunnel panier-paiement) ecartes par tech, conversion et ux. Le paiement de reservation (D-Edge, hors site) part en verification humaine. Cote seo, aucun controle ecarte (vitrine = cas nominal).

**Bilan chiffre** : 4 bloquants, 21 majeurs, 19 mineurs, 2 en escalade editoriale. Le socle technique est sain (profiler et front controller de dev non exposes, `.env` inaccessible, assets hashes et caches, compression Brotli) ; les problemes sont concentres sur le contenu non finalise, le consentement cookies, l'indexabilite et le dispositif de conversion.

## 2. Top 5 (toutes disciplines, a traiter en premier)

1. **[UX-02] Bloquant** - Le moteur de reservation D-Edge est verrouille derriere un consentement cookies qu'aucune interface ne permet d'accorder : la conversion principale du site est potentiellement inatteignable. (effort M)
2. **[UX-01] Bloquant** - Google Tag Manager (`GTM-PR9R52V`) se charge sur toutes les pages sans aucun gate de consentement, aucun bandeau cookies actif : depot de traceurs avant tout choix, exposition RGPD. (effort M)
3. **[COPY-01] Bloquant** - Texte "Lorem ipsum" servi en clair sur la page d'accueil (bloc Chambres). (effort S)
4. **[COPY-02] Bloquant** - Accroche "Lorem ipsum" sur la page de rubrique Chambres & Suites. (effort S)
5. **[SEO-01] Majeur** - H1 absent sur 13 des 14 pages indexables (cause de code identifiee : composant de titre neutralise en commentaire Twig). (effort M)

## 3. Croisements (meme cause, angles differents)

- **A. Configuration de consentement cassee** - `[UX-01]` + `[UX-02]` + `[CONV-08]` (+ renvoi tech GTM). La meme brique de consentement est a la fois trop permissive (GTM se charge sans accord) et trop bloquante (D-Edge exige un accord impossible a donner), et prive la mesure des conversions (evenements cross-domain non pilotables). Un seul chantier de fond : retablir un mecanisme de consentement fonctionnel (Axeptio ou module GDPR interne) avec refus aussi accessible que l'acceptation.
- **B. Bons cadeaux non delivres** - `[UX-03]` (cul-de-sac, aucune mecanique d'achat) + `[CONV-06]` (packaging, montants, validite non delivres) + `[UX-05]` (pas de CGV alors qu'une vente est annoncee) + renvoi copy (descriptif absent). Le meme point de rupture vu sous quatre angles : la page ne permet ni de comprendre, ni d'acheter, ni de se rassurer.
- **C. Contenu de rubrique non finalise** - `[COPY-01]` + `[COPY-02]` + `[CONV-03]` (bloc d'orientation du listing chambres = placeholder "Lorem ipsum") + `[SEO-02]` (meta description absente faute de contenu editorial detecte). Meme cause : le contenu editorial des gabarits n'est pas renseigne.
- **D. CTA "Reserver" trompeur** - `[COPY-03]` (libelle "Reserver" pointant vers `/chambres-suites`) + renvoi ux. Porte par copy (coherence libelle/destination).

## 4. Renvois traites et angles morts

Verification que chaque renvoi d'agent a bien un constat en face chez le proprietaire :

- COPY -> CONV (bons cadeaux, faiblesse de preuve) : couvert par CONV-06, CONV-01. **OK**
- COPY -> UX (404, formulaires, RGPD) : couvert par UX-04/05/11 et notes. **OK**
- CONV -> COPY (Lorem ipsum, claim 5 etoiles) : couvert par COPY-01/02/09. **OK**
- CONV -> UX (formulaire contact, confirmation post-envoi, RGPD newsletter) : couvert par UX-04, UX-11. **OK**
- UX -> COPY (libelle "Reserver") : couvert par COPY-03. **OK**
- UX -> CONV (structure bons cadeaux, avis "4,7/5 - 1678 avis" commente en pied de page, pertinence des champs) : couvert par CONV-06, CONV-01, CONV-04. **OK**
- UX -> TECH (perf D-Edge, CSP) et TECH -> UX (GTM), TECH -> SEO (noindex), SEO -> UX (pages legales), SEO -> TECH (code 404) : **OK**.

**Angles morts identifies (renvoi sans constat en face)** :

- **Rendu JavaScript des pages commerciales (a instruire en priorite)** - `audit-copy`, `audit-conversion` et `audit-ux` signalent que restaurant-bar, sport-bien-etre, salle-de-reunion-evenementiel et bons-cadeaux ne servent, sans JavaScript, qu'un en-tete, un pied de page et le message "Activez JavaScript pour charger plus de contenu interactif" ; leur corps editorial est injecte en JS. CONV et COPY renvoient ce point a `audit-tech` (fiabilite du rendu) **et** a `audit-seo` (indexabilite), mais **ni le rapport tech ni le rapport seo ne portent de constat dedie**. C'est potentiellement le risque le plus lourd du site : le contenu commercial principal de plusieurs rubrigues est hors du HTML initial, donc fragile a l'indexation et invisible si le JS echoue. A trancher : rendu serveur (SSR) ou pre-rendu de ces gabarits. Sans decision, l'ampleur SEO reelle reste non chiffree.
- **Titre de la page 404 "Erreur 404 | Agence Felix"** - COPY renvoie a SEO la marque agence affichee au lieu de la marque Parister dans le `<title>` du gabarit d'erreur ; SEO n'en fait pas un constat dedie. Mineur, a corriger avec les titres (SEO-03).

## 5. Constats par discipline

Les cinq rapports sont reproduits sans modification.

---

### 5.1 Audit TECHNIQUE

# Audit TECHNIQUE - hotel-parister.felix-lait.com (préproduction)

## Périmètre

- **Cible** : https://hotel-parister.felix-lait.com/ (préproduction), type **vitrine** hôtelière. Réservation déléguée au moteur externe D-Edge (widget `fbw-`), non testable de bout en bout.
- **Croisement code/URL** : projet local `C:\wamp64\www\hotel-parister-2026` (Symfony 7.4.10, PHP 8.5.3, Webpack Encore, hébergeur o2switch-PowerBoost-v3).
- **Méthode** : relevés `curl` (en-têtes, TTFB, compression, codes HTTP) sur l'échantillon + lecture du code source (config sécurité, webpack, subscribers, assets). Bash en lecture seule.
- **Pages examinées** : les 15 URLs de l'échantillon (relevés HTTP), plus lecture ciblée du code pour établir les causes.
- **Date** : 2026-08-14.

**Contrôles écartés (non applicables au type vitrine)** : version/modules/overrides Prestashop, cache Smarty, `_PS_MODE_DEV_`, tunnel panier-paiement - la cible n'est pas Prestashop et ne comporte pas de tunnel e-commerce interne.

**Environnement établi** : Symfony framework-bundle **v7.4.10** et PHP **8.5.3**, tous deux en support actif (aucun risque de fin de vie). Préproduction confirmée (`X-Robots-Tag: noindex`). Profiler et front controller de dev **non exposés** (`/_profiler`, `/_wdt/*`, `/app_dev.php` renvoient 404). Aucune barre de debug, aucune trace d'exception, aucun `dump()`/`console.log` rendu sur le front audité. Fichier `.env` non versionné (git) et inaccessible en HTTP (403). Assets hashés, `Cache-Control: max-age=31536000, public`, compression Brotli active. Ces points sont sains.

## Synthèse

7 constats : 0 bloquant, 2 majeurs, 5 mineurs. Point le plus urgent : **temps de réponse serveur (TTFB) mesuré à ~3,7-4,1 s sur toutes les pages**, aggravé par une politique `no-store` qui interdit toute mise en cache des pages d'un site pourtant essentiellement statique. Second sujet : la **Content-Security-Policy est désactivée en dur dans le code** alors que tout le dispositif de nonces reste généré (protection inopérante).

## Constats

### [TECH-01] Majeur - Temps de réponse serveur (TTFB) de ~4 s sur toutes les pages, sans cache HTTP

- **Preuve** : TTFB mesuré à 3 reprises sur `/` : 4,04 / 4,02 / 4,15 s ; confirmé sur `/acces-et-contact` (3,75 s), `/plan-du-site` (3,89 s), `/bons-cadeaux` (3,69 s). En-tête du document HTML : `Cache-Control: no-cache, no-store, must-revalidate` - chaque visite recalcule intégralement la page CMS, aucune réutilisation possible.
- **Impact** : chaque page met environ 4 secondes avant le premier octet, avant même le téléchargement des ressources. Perception de lenteur généralisée pour l'internaute et charge serveur inutile sur un contenu qui change rarement.
- **Correction** : profiler une page représentative (Blackfire ou profiler Symfony en local) pour isoler la cause (nombre de requêtes Doctrine par page, OPcache actif côté serveur, hydratation des blocs CMS) ; mettre en place un cache HTTP applicatif (`Cache-Control: s-maxage` + reverse proxy / `HttpCache` Symfony) ou un cache de rendu sur les pages vitrine à faible fréquence de mise à jour.
- **Effort** : L
- **Statut** : mesure = fait vérifié ; cause = hypothèse (pas d'accès aux métriques serveur ni au profilage applicatif).

### [TECH-02] Majeur - Content-Security-Policy désactivée en dur alors que le dispositif de nonces reste généré

- **Preuve** : aucune en-tête `Content-Security-Policy` servie (vérifié sur GET et HEAD de `/`), mais des nonces sont bien émis dans les en-têtes `Link` et le markup. Cause dans `src/EventSubscriber/SecurityPolicySubscriber.php:36` : `private const bool CSP_DISABLED = true;` ; en ligne 334, la branche `content-security-policy` retourne un tableau vide dès que cette constante est vraie. La politique complète (nonce, `strict-dynamic`, `trusted-types`, `object-src 'none'`, `frame-ancestors`) est pourtant entièrement écrite (lignes 365-486) mais jamais envoyée.
- **Impact** : la principale barrière contre l'injection de scripts (XSS) est inactive en préproduction et le restera en production si l'état est reporté tel quel. Le coût de génération des nonces est payé sans aucun bénéfice de protection.
- **Correction** : décider si la CSP doit être active. Si oui, passer `CSP_DISABLED` à `false` et valider la politique en `Content-Security-Policy-Report-Only` d'abord (les trackers GTM/Matomo/Clarity/D-Edge sont déjà whitelistés). Si la désactivation est volontaire, retirer le dispositif de nonces devenu inerte pour ne pas laisser croire à une protection existante.
- **Effort** : M
- **Statut** : fait vérifié (absence d'en-tête + constante en code). L'appréciation de l'exploitabilité relève de l'escalade humaine.

### [TECH-03] Mineur - En-tête `Link` de preload avec doublons massifs (logo répété 6 fois)

- **Preuve** : en-tête `Link` de la réponse `/` : `logo.svg` déclaré 6 fois en `rel="preload"; as="image"` et `logo-secondary.svg` 6 fois également, en plus des preloads de scripts/styles.
- **Impact** : en-tête de réponse inutilement volumineuse ; hints de preload redondants que le navigateur doit dédupliquer. Aucun gain, bruit sur chaque réponse.
- **Correction** : dédupliquer la génération des `Link: preload` (émettre une seule directive par ressource unique).
- **Effort** : S
- **Statut** : fait vérifié.

### [TECH-04] Mineur - Deux en-têtes `Cache-Control` contradictoires sur le document HTML

- **Preuve** : la réponse `/` porte simultanément `Cache-Control: max-age=0, must-revalidate, private` et `Cache-Control: no-cache, no-store, must-revalidate`. Le subscriber pose par ailleurs des `header()` bruts (`SecurityPolicySubscriber.php:189-199`).
- **Impact** : configuration de cache ambiguë ; comportement dépendant de l'implémentation du client HTTP/proxy. Symptôme d'une gestion du cache répartie entre plusieurs couches (framework + `header()` bruts + serveur).
- **Correction** : centraliser la politique de cache en un seul point et n'émettre qu'un `Cache-Control` cohérent par type de réponse.
- **Effort** : S
- **Statut** : fait vérifié.

### [TECH-05] Mineur - Appels `console.log/debug/warn` présents dans les bundles JS front livrés

- **Preuve** : `grep` sur `assets/js/front/default` : instructions `console.*` dans des composants réellement compilés au front, ex. `components/accessibility.js`, `components/website-alert.js` (×2), `components/newscast-teaser.js` (×3), `components/catalog/search/remove-url-param.js` (×3), `components/table.js`, `components/share.js`. (Les occurrences dans `*.min.js`/`*.map` de librairies tierces sont hors sujet.)
- **Impact** : bruit console en production, fuite d'informations de fonctionnement mineures. Non exécuté ici (pas de rendu JS), donc déclenchement non observé.
- **Correction** : retirer les `console.*` de debug des composants front, ou les supprimer au build via la config Terser (`drop_console`) en production.
- **Effort** : S
- **Statut** : fait vérifié pour la présence en source ; déclenchement runtime non vérifiable (pas d'exécution JS).

### [TECH-06] Mineur - Aucun test automatisé malgré une configuration PHPUnit présente

- **Preuve** : `phpunit.xml.dist` présent, mais `tests/` ne contient que `bootstrap.php` ; `find tests -name "*Test.php"` = 0 fichier.
- **Impact** : aucune couverture de non-régression. Sur un socle CMS partagé entre projets, tout changement se valide manuellement.
- **Correction** : introduire au minimum des tests fumée (smoke tests) sur les gabarits front (code HTTP 200, présence des blocs clés) et sur le rendu des pages de l'échantillon.
- **Effort** : L
- **Statut** : fait vérifié.

### [TECH-07] Mineur - Feuille de style de debug (`sf-dump`) compilée et préchargée sur chaque page front

- **Preuve** : entrée Webpack `webpack.config.js:80` : `.addStyleEntry('debug', ['./assets/scss/vendor/debug.scss'])` ; le fichier ne contient que des règles de coloration de `pre.sf-dump` (sortie VarDumper). `/build/vendor/debug.8d196191.css` est servi (200, 812 o) et préchargé via `Link: preload` sur `/`. Par ailleurs, 29 appels `dump()`/`dd()` subsistent dans 20 fichiers `src/` (contextes admin/commande/service ; aucun sur le chemin front audité - le seul `dump()` d'un template front, `sitemap/view.html.twig:55`, est commenté donc inerte).
- **Impact** : un asset destiné au débogage est livré et préchargé en production sur toutes les pages front. Poids négligeable, mais signale que l'outillage de dump est prévu pour tourner en prod.
- **Correction** : conditionner l'entrée `debug` et son préchargement à l'environnement de développement ; nettoyer les `dump()`/`dd()` résiduels dans `src/`.
- **Effort** : S
- **Statut** : fait vérifié.

## À vérifier humainement

- **Cause réelle du TTFB de 4 s (TECH-01)** : lancer le profiler Symfony ou Blackfire sur `/` et `/chambres-suites` en préproduction ; relever le nombre de requêtes Doctrine par page et vérifier qu'OPcache est actif côté serveur o2switch. Conclusion : si > quelques dizaines de requêtes ou OPcache inactif, la cause est applicative/config et non le seul hébergement mutualisé.
- **Intention de désactivation de la CSP (TECH-02)** : confirmer auprès de l'équipe si `CSP_DISABLED = true` est un état de préproduction temporaire ou une décision durable, et trancher (activer en report-only, ou retirer le dispositif de nonces).
- **Support HTTP/2/3** : le `curl` local ne supporte pas `--http2` (négociation impossible à tester ici, réponses en HTTP/1.1). Vérifier dans les DevTools navigateur ou WebPageTest si le serveur sert bien en HTTP/2 - un HTTP/1.1 seul pénaliserait le chargement des nombreuses ressources.
- **Erreurs JavaScript runtime** : non observables sans exécution JS. Ouvrir une session navigateur pilotée sur les 15 URLs et relever la console (erreurs, requêtes async en échec), notamment sur `/reservation` (widget D-Edge) et `/galerie-photos`.
- **Tunnel de réservation D-Edge** : moteur externe, non testable de bout en bout depuis l'audit. Faire une réservation test en environnement sandbox D-Edge.
- **Filtre XSS maison (`SecurityPolicySubscriber::xssProtection`)** : le motif `XSS_PATTERN` renvoie un 403 sur toute requête (GET/POST/URI) contenant `<img`, `<svg`, `on\w+=`, `data:text/html`, etc. Tester des saisies légitimes (formulaire de contact, paramètres d'URL) pour écarter les faux positifs qui bloqueraient un internaute.
- **Audit CVE des dépendances** : `composer` indisponible sur le PATH de l'environnement d'audit. Lancer `composer audit` pour contrôler les vulnérabilités connues des paquets verrouillés.

## Renvois

- `X-Robots-Tag: noindex` global (attendu en préproduction) et indexabilité : `audit-seo`.
- Google Tag Manager chargé inconditionnellement dans le `<head>` (traceur), enjeu de conformité/consentement : `audit-ux`.

---

### 5.2 Audit SEO

# Audit SEO — hotel-parister.felix-lait.com (préproduction)

## Périmètre

- **Cible** : https://hotel-parister.felix-lait.com/ (préproduction), type **vitrine hôtelière**.
- **Méthode** : récupération du HTML brut par requête HTTP directe (sans exécution JS) sur les 15 URL de l'échantillon + `robots.txt` + `sitemap.xml`, complétée par deux pages annexes (`/mentions-legales`, `/politique-cookies`) trouvées en pied de page. Croisement avec le code source Symfony/Twig local (`templates/front/default/include/seo.html.twig`, `src/Service/Content/SeoService.php`, `src/Service/Content/RobotsService.php`, `templates/front/default/include/locale-switcher.html.twig`, `templates/front/default/actions/vendor/include/layout-title.html.twig`) pour établir la cause des effets constatés.
- **Date** : 2026-08-14.
- **Pages effectivement examinées** : les 15 URL de l'échantillon fourni, plus `/mentions-legales` et `/politique-cookies`.
- **Contrôles écartés** : aucun. Le type vitrine est le cas nominal (section 9 des conventions) : la méthode s'applique intégralement, y compris le volet international, qui a remonté un constat malgré un site à dominante mono-locale (cf. SEO-08).
- **Condition de validité** : la racine `/` a déjà été vérifiée en HTTP 200 par l'audit technique, non refait ici.

## Synthèse

8 constats Majeurs, 2 constats Mineurs. Le point le plus large en surface : **le H1 est absent sur 13 des 14 pages indexables de l'échantillon**, avec une cause de code identifiée (composant de titre entièrement neutralisé en commentaire Twig). Le JSON-LD `Organization` est dupliqué sur chaque page avec un `name` différent à chaque fois (jamais le nom de l'hôtel), et aucun type `Hotel`/`LodgingBusiness` n'est utilisé malgré la pertinence évidente pour ce secteur. Le `noindex` et le préfixe `[SEO Désactivé]` du `<title>`, attendus sur cet environnement, ne sont pas portés en constat mais font l'objet d'une vérification unique avant mise en production.

## Constats

### [SEO-01] Majeur - H1 absent sur la quasi-totalité des pages échantillonnées
- Preuve : `curl` sur les 14 pages indexables ; H1 présent uniquement sur `/` (`<h1 class="caption-title mt-3 mb-0">Boutique hôtel & spa</h1>`). Absent sur `/chambres-suites`, `/chambres-suites/fiche-produit/parister-suite`, `/la-vie-au-parister`, `/la-vie-au-parister/fiche-actualite/besoin-d-une-salle-de-reunion-a-paris`, `/blog`, `/restaurant-bar-a-cocktail`, `/sport-bien-etre`, `/salle-de-reunion-evenementiel`, `/acces-et-contact`, `/reservation`, `/bons-cadeaux`, `/galerie-photos`, `/plan-du-site`. Sur la fiche produit, la hiérarchie démarre même par un H3 (`accordion-header`) avant tout H2, sans H1 en amont. Cause de code : `templates/front/default/actions/vendor/include/layout-title.html.twig` (lignes 3-7) a tout son contenu de sortie placé en commentaire Twig `{# ... #}` — le composant ne produit plus rien.
- Impact : les pages de rubrique, fiches produit et actualités n'affichent aucun signal de titre de page ni de hiérarchie de contenu ; seuls des H2 de blocs (souvent des titres de widgets ou de pied de page) subsistent.
- Correction : réactiver le rendu du H1 dans `layout-title.html.twig` (ou le composant équivalent utilisé par les gabarits de page CMS) et s'assurer qu'il précède toute autre balise Hn sur chaque gabarit.
- Effort : M
- Statut : fait vérifié

### [SEO-02] Majeur - Meta description absente sur la majorité des pages
- Preuve : absente sur 11 des 14 pages (`/chambres-suites`, `/la-vie-au-parister`, `/blog`, `/restaurant-bar-a-cocktail`, `/sport-bien-etre`, `/salle-de-reunion-evenementiel`, `/acces-et-contact`, `/reservation`, `/bons-cadeaux`, `/galerie-photos`, `/plan-du-site`). Présente uniquement sur `/`, la fiche produit et la fiche actualité. Cause probable : `templates/front/default/include/seo.html.twig` ligne 12-14 n'émet la balise que si `seo.description` est renseigné, et `src/Service/Content/SeoService.php::getDescription()` (lignes 374-406) ne retombe sur le contenu de page que pour les entités `Layout\Page` disposant d'un bloc de type `text` détecté par le layout — probablement absent sur ces gabarits.
- Impact : Google génère lui-même l'extrait affiché en résultat de recherche sur la quasi-totalité des pages du site, sans contrôle sur le message ni l'incitation au clic.
- Correction : renseigner une description en admin (SEO de la page) sur chacune de ces 11 pages, ou fiabiliser le fallback vers le contenu éditorial du layout.
- Effort : M
- Statut : fait vérifié

### [SEO-03] Majeur - Titres systématiquement sous-dimensionnés et sans nom de marque
- Preuve : longueurs mesurées sur les 14 pages : de 4 caractères (`Blog`) à 37 (`Besoin d'une salle de réunion à Paris`), aucune n'atteint la plage cible 50-60. Aucun titre ne se termine par le nom de l'établissement (ex. `<title>Réservation</title>`, `<title>Bons cadeaux</title>`).
- Impact : les titres n'exploitent pas l'espace disponible en page de résultats et n'assurent aucune reconnaissance de marque en SERP.
- Correction : activer le suffixe de marque ("after dash") dans la configuration SEO du site (`configuration.seoConfiguration`) et enrichir les titres courts avec un complément discriminant.
- Effort : S
- Statut : fait vérifié

### [SEO-04] Majeur - JSON-LD Organization dupliqué avec un nom divergent à chaque page
- Preuve : un bloc `"@type": "Organization"` est émis sur chaque page examinée (y compris la page 404 forgée), avec un champ `"name"` différent à chaque fois : `"Votre parenthèse"` sur `/`, `"Chambres & Suites"` sur `/chambres-suites`, `"Suite parister"` sur la fiche produit, `"Plan de site"` sur `/plan-du-site`, `"Erreurs"` sur la 404. Cause de code : `src/Service/Content/SeoService.php` ligne 592, `'name' => $this->fullTitle ?: $companyName` — le titre de la page prime sur le nom de l'entreprise.
- Impact : la même entité `Organization` déclare une identité différente sur chaque page, ce qui invalide sa cohérence pour les moteurs et empêche toute exploitation fiable en Knowledge Panel.
- Correction : forcer `name` sur le nom de l'établissement (`companyName`) indépendamment du titre de page, en inversant l'ordre de la coalescence ligne 592.
- Effort : S
- Statut : fait vérifié

### [SEO-05] Majeur - Absence de type Hotel/LodgingBusiness
- Preuve : sur les 14 pages examinées, le seul type de données structurées relatif à l'établissement est `Organization` (avec adresse et téléphone). Aucun type `Hotel` ou `LodgingBusiness` n'apparaît, y compris sur l'accueil ou la page d'accès et contact.
- Impact : le site se prive des rich results propres à l'hôtellerie (étoiles, équipements, fourchette de prix) alors que l'établissement dispose déjà des données nécessaires (adresse, contact) dans le même bloc.
- Correction : remplacer ou compléter le type `Organization` par `Hotel` (sous-type de `LodgingBusiness`) sur l'accueil et la page d'accès/contact, avec les propriétés `starRating`, `amenityFeature`, `address`, `telephone` déjà disponibles.
- Effort : M
- Statut : fait vérifié

### [SEO-06] Majeur - Type Article inadapté sur les fiches chambres, propriété auteur vide
- Preuve : la fiche produit `/chambres-suites/fiche-produit/parister-suite` et chaque élément de la liste de chambres sur `/chambres-suites` sont balisés en `"@type": "Article"` (`"headline": "Suite parister"`, etc.). Sur tous les blocs `Article` observés (fiche produit, listing chambres, fiche actualité), `"author": {"@type": "Organization", "name": ""}` est vide.
- Impact : une chambre d'hôtel n'est pas un contenu éditorial ; le type `Article` ne correspond pas à la nature de la page et la propriété `author.name`, requise par le type déclaré, est non conforme.
- Correction : retirer le type `Article` des fiches chambres (pas de type dédié pertinent tant que la réservation reste externe à D-Edge, à défaut ne rien déclarer plutôt qu'un type inadapté) et renseigner `author.name` sur les pages où `Article` reste pertinent (fiches actualité du blog).
- Effort : M
- Statut : fait vérifié

### [SEO-07] Majeur - Attribut alt généré depuis le nom de fichier, y compris des hachages de build
- Preuve : exemples relevés sur l'accueil : `alt="Room 1"`, `alt="Room 4"`, `alt="Spa piscine 6a424f603bfd3"`, `alt="Cocktail bar.b0546007"`, `alt="Art books.09ab455c"`, `alt="Forstyle logo white.95f1f348"`, `alt="News besoin d une salle de reunion a paris"`.
- Impact : le texte alternatif reproduit le nom de fichier (accents perdus, apostrophes en espaces) au lieu de décrire l'image, jusqu'à exposer des identifiants de build technique dans le contenu visible par les technologies d'assistance et les moteurs.
- Correction : renseigner un alt éditorial sur chaque média en admin ; ne conserver un alt vide que pour les images strictement décoratives.
- Effort : L
- Statut : fait vérifié

### [SEO-08] Majeur - Lien de changement de langue pointant vers un domaine local non résolvable
- Preuve : sur l'accueil, le sélecteur de langue affiche un lien `English` avec `href="https://en.hotel-parister-2026.local"` (`hreflang="en"`). Ce domaine `.local` n'est pas un nom public résolvable. Aucune balise `<link rel="alternate" hreflang>` n'est présente dans le `<head>` d'aucune des pages examinées ; le mécanisme d'alternance de langue du code (`seo.localesAlternate`, `templates/front/default/include/seo.html.twig` lignes 29-34) ne s'active que si plus d'une locale renvoie une URL valide, ce qui n'est pas le cas ici.
- Impact : un visiteur cliquant sur "English" arrive sur une adresse inutilisable ; l'attribut `hreflang` porté par un lien de navigation n'est de toute façon pas traité comme signal international par les moteurs (il doit être en `<link>` dans le `<head>`).
- Correction : configurer un domaine public valide pour la locale anglaise (entité Domaine en admin) ou retirer le sélecteur de langue tant que la version anglaise n'est pas prête.
- Effort : S
- Statut : fait vérifié

### [SEO-09] Mineur - Canonique de l'accueil sans slash final, incohérente avec l'URL servie
- Preuve : la page est servie à `https://hotel-parister.felix-lait.com/` mais déclare `<link rel="canonical" href="https://hotel-parister.felix-lait.com">` (sans slash final).
- Impact : incohérence mineure entre l'URL canonique déclarée et l'URL effectivement servie.
- Correction : aligner la génération de la canonique sur l'URL réellement servie (avec slash final) dans `src/Service/Content/SeoService.php::getCanonical()`.
- Effort : S
- Statut : fait vérifié

### [SEO-10] Mineur - Canonique de la page 404 pointant vers une URL différente de celle demandée
- Preuve : sur `/url-inexistante-recette-404` (404 forgée), `<link rel="canonical" href="https://hotel-parister.felix-lait.com/erreur">` et `<meta property="og:url" content="https://hotel-parister.felix-lait.com/erreur">`, alors que `<meta name="robots" content="noindex">` est correctement positionné.
- Impact : une page d'erreur non indexable ne doit pas déclarer de canonique renvoyant vers une autre ressource, signal contradictoire pour un robot qui ignorerait le noindex.
- Correction : supprimer la balise canonique (et l'og:url) sur le gabarit d'erreur, ou la faire pointer sur l'URL réellement demandée.
- Effort : S
- Statut : fait vérifié

## À vérifier humainement

- **Bascule "Statut SEO" avant mise en production** : `configuration.seoStatus` commande à la fois le `Disallow: /` du `robots.txt` (`src/Service/Content/RobotsService.php` ligne 45) et le préfixe `[SEO Désactivé]` visible dans le `<title>` de toutes les pages (`templates/front/default/include/seo.html.twig` lignes 8 et 61). Protocole : sur l'environnement de production, vérifier que `robots.txt` ne contient plus `Disallow: /` et qu'aucun `<title>` n'affiche plus ce préfixe.
- **Noindex des pages légales** : `/mentions-legales` et `/politique-cookies` portent `<meta name="robots" content="noindex">`. Confirmer auprès du client s'il s'agit d'un choix éditorial assumé (pratique courante) ou d'un oubli à corriger avant mise en ligne.
- **Version anglaise du site** : confirmer avec le client si une version EN est prévue à court terme ; si oui, le domaine doit être corrigé avant toute mise en ligne (cf. SEO-08) ; si non, retirer le sélecteur de langue plutôt que de le laisser pointer vers une configuration cassée.
- **Indexation réelle** : hors de portée de cet agent (nécessite Search Console une fois le site public).

## Renvois

- Code HTTP 404 correctement retourné pour l'URL forgée `/url-inexistante-recette-404` : contrôle et propriété `audit-tech`.
- Présence de `/mentions-legales` et `/politique-cookies` confirmée en pied de page (200, canonique auto-référente correcte) ; absence de page CGV sur le site ; pertinence de ces pages au regard du parcours utilisateur : propriété `audit-ux`.
- Qualité rédactionnelle des titres courts relevés en SEO-03 (au-delà de leur non-conformité technique de longueur) : propriété `audit-copy`.

---

### 5.3 Audit COPY

# Rapport d'audit COPY - Hôtel Parister (préproduction)

## Périmètre

- **Cible** : https://hotel-parister.felix-lait.com/ (préproduction). Site vitrine hôtelier haut de gamme, Paris 9e.
- **Type déclaré** : vitrine. Toute la méthode `audit-copy` s'applique ; aucun contrôle écarté au titre du type. Aucun contrôle e-commerce (hors type).
- **Pages examinées (15)** : accueil, /chambres-suites, /chambres-suites/fiche-produit/parister-suite, /la-vie-au-parister, /la-vie-au-parister/fiche-actualite/besoin-d-une-salle-de-reunion-a-paris, /blog, /restaurant-bar-a-cocktail, /sport-bien-etre, /salle-de-reunion-evenementiel, /acces-et-contact, /reservation, /bons-cadeaux, /galerie-photos, /plan-du-site, 404 forgé.
- **Date** : 2026-08-14. **Méthode** : lecture du HTML servi (WebFetch + `curl`), sans exécution JavaScript ni rendu visuel.
- **Limite structurante** : plusieurs pages intérieures (restaurant, spa/sport, séminaires, fiche-actualité) servent un HTML ne contenant que l'en-tête, le pied de page et un message `Activez JavaScript pour charger plus de contenu interactif.` : leur corps éditorial est chargé en JavaScript et **n'a pas pu être audité**. Le découpage "premier écran" est déduit de l'ordre du DOM. Voir "À vérifier humainement".

## Cible déduite (contenu seul, hypothèse)

- **À qui** : voyageurs loisirs et affaires cherchant un hôtel 5 étoiles / boutique-hôtel dans Paris, plus une clientèle événementielle (séminaires, réunions).
- **Ce qu'elle vend** : nuitées (chambres et suites), restaurant-bar, spa/bien-être, privatisation de salles, bons cadeaux.
- **Attendu du visiteur** : réserver un séjour (moteur externe), organiser un événement, acheter un bon cadeau.

Ces trois phrases sont formulables : la page d'accueil est lisible, pas de constat bloquant de lisibilité globale.

## Synthèse

10 constats : 2 bloquants, 2 majeurs, 4 mineurs, 2 en escalade humaine (claims). **Point le plus urgent** : deux blocs de texte "Lorem ipsum" non finalisés sont servis en clair, dont un sur la page d'accueil - à retirer avant toute mise en production.

## Constats

### [COPY-01] Bloquant - Texte "Lorem ipsum" non finalisé servi sur la page d'accueil
- Preuve : accueil, description du bloc "Chambres" : `"Chambres, suites, offres et disponibilités. Lorem ipsum dolor sit amet del cironcstum del doloros del ametis. tés. Lorem ipsum dolor sit amet del cironcstum del doloros del ametis."` (présent dans le HTML statique servi).
- Impact : contenu de remplissage visible sur la page la plus vue ; décrédibilise immédiatement un positionnement haut de gamme et signale un site non fini.
- Correction : remplacer par le descriptif éditorial définitif du bloc "Chambres". La direction : une phrase spécifique sur l'offre chambres/suites, pas un texte générique.
- Effort : S
- Statut : fait vérifié

### [COPY-02] Bloquant - Accroche de premier écran en "Lorem ipsum" sur la page Chambres & Suites
- Preuve : /chambres-suites, accroche sous le H1 `"Chambres & Suites"` : `"Lorem ipsum dolor sit amet, consectetur adipiscing elit."` (HTML statique).
- Impact : la page de rubrique la plus stratégique du parcours de réservation ouvre sur un texte de remplissage ; la promesse de la rubrique n'existe pas.
- Correction : rédiger l'accroche définitive de la rubrique (nature de l'offre chambres/suites, angle différenciant). Direction seulement, pas de texte final ici.
- Effort : S
- Statut : fait vérifié

### [COPY-03] Majeur - Le CTA principal "Réserver" ne mène pas à une réservation
- Preuve : en-tête (toutes pages) `<a href="/chambres-suites" ...>Réserver</a>` ; le libellé "Réserver" pointe vers la liste des chambres, pas vers un parcours ou moteur de réservation.
- Impact : rupture entre le libellé (engagement de réservation) et la destination (page de catalogue). Le visiteur prêt à réserver est renvoyé en amont, ce qui ajoute une étape et brouille l'action attendue. Une page /reservation distincte existe par ailleurs, ce qui accentue l'ambiguïté.
- Correction : soit aligner le libellé sur la destination (ex. "Voir les chambres"), soit faire pointer "Réserver" vers le parcours de réservation réel. Trancher selon l'intention.
- Effort : S
- Statut : fait vérifié

### [COPY-04] Majeur - Intertitre auto-descriptif répété trois fois à l'identique sur l'accueil
- Preuve : accueil, trois `<h2>` successifs identiques `"Intimiste, contemporain et chaleureux"`.
- Impact : trois sections de la page d'accueil portent le même titre, composé d'adjectifs auto-attribués qui ne transmettent aucune information et ne distinguent pas les blocs. La hiérarchie du message est illisible au survol.
- Correction : donner à chaque section un intertitre porteur de sens propre à son contenu (chambres, restauration, spa...). Critère : un intertitre doit rester compréhensible hors contexte.
- Effort : M
- Statut : fait vérifié

### [COPY-05] Mineur - La rubrique événementiel est nommée différemment selon les emplacements
- Preuve : `"Séminaires & Réunions"` (H1 / plan du site) ; `"Pour vos séminaires, réunions, événements"` (H2 accueil) ; `"Salle de réunion & événementiel"` (menu) ; slug `/salle-de-reunion-evenementiel`.
- Impact : un même espace porte au moins trois libellés distincts entre navigation, titres et corps ; le visiteur peut douter qu'il s'agisse de la même offre. Cohérence terminologique rompue.
- Correction : fixer une dénomination unique de la rubrique et l'appliquer en navigation, titres et teasers.
- Effort : S
- Statut : fait vérifié

### [COPY-06] Mineur - Incohérences de nommage sur les rubriques spa et chambres
- Preuve : `"Spa, Bien-être & Sport"` (H1) vs `"Sport & bien-être"` (menu) ; `"Chambres & Suites"` (H1) vs `"Chambres & Suite"` (au singulier, liste de navigation).
- Impact : mêmes objets nommés différemment (ordre des termes, singulier/pluriel) ; nuit à la cohérence perçue d'un site haut de gamme.
- Correction : harmoniser les libellés de rubrique sur une forme de référence unique.
- Effort : S
- Statut : fait vérifié

### [COPY-07] Mineur - Libellés de CTA génériques et non uniformes dans les listings
- Preuve : /chambres-suites, `"Découvrir"` répété 7 fois (un par type de chambre) ; /la-vie-au-parister, `"En savoir plus"` répété sur les items 2 à 12 mais `"En savoir +"` sur le premier item.
- Impact : libellés d'action sans objet propre (le contexte de la carte porte seul le sens) et divergence de formulation entre le premier item et les suivants. Effet de liste indifférenciée.
- Correction : soit conserver un libellé unique et strictement identique, soit porter l'objet dans le libellé (ex. "Découvrir la suite duplex"). Uniformiser "En savoir +" / "En savoir plus".
- Effort : S
- Statut : fait vérifié

### [COPY-08] Mineur - Intertitre "Chambres 45" à la formulation obscure sur l'accueil
- Preuve : accueil, intertitre relevé `"Chambres 45"`.
- Impact : formulation qui ne se lit pas (probablement "45 chambres", capacité de l'hôtel) ; l'information, si elle existe, est illisible.
- Correction : reformuler en énoncé explicite si l'intention est de communiquer le nombre de chambres. À confirmer avec l'éditeur.
- Effort : S
- Statut : hypothèse (relevé via extraction ; sens exact à confirmer sur le rendu)

## Claims - escalade humaine (aucune reformulation proposée)

### [COPY-09] Escalade - Mention de classement "5 étoiles"
- Preuve : accueil, slogan `"Un boutique hôtel 5 étoiles au cœur de Paris, où lifestyle, culture & art se rencontrent."`
- Impact : le classement en étoiles est une allégation réglementée (classement Atout France en France). Son emploi doit correspondre à un classement en cours de validité.
- Action : validation humaine/juridique de la réalité et de la validité du classement 5 étoiles. Pas d'arbitrage d'agence.
- Statut : fait vérifié (présence du texte) ; conformité non vérifiable par l'agent.

### [COPY-10] Escalade - Superlatifs auto-attribués sur la fiche Suite Parister
- Preuve : /chambres-suites/fiche-produit/parister-suite : `"La plus grande suite de l'hôtel"`, `"l'expérience Parister dans sa forme la plus aboutie"`, `"linge de lit haut de gamme"`.
- Impact : superlatifs et adjectifs auto-attribués. Le premier ("la plus grande suite de l'hôtel") est une comparaison interne factuellement vérifiable ; les autres relèvent du discours de marque. Risque juridique faible mais à couvrir.
- Action : confirmer côté client que "la plus grande suite" est exact (superficie comparée aux autres suites). Pas de reformulation.
- Statut : fait vérifié (présence du texte) ; exactitude non vérifiable par l'agent.

## À vérifier humainement

- **Corps éditorial des pages chargées en JavaScript** (restaurant-bar, spa/sport, séminaires-événementiel, fiche-actualité "Besoin d'une salle de réunion") : le HTML servi ne contient pas leur contenu (message `Activez JavaScript...`). Vérifier sur un rendu navigateur avec JS activé que ces pages disposent bien d'une accroche, d'une promesse spécifique et d'un corps rédactionnel - et non de teasers vides. Protocole : ouvrir chaque URL dans un navigateur, JS activé, et contrôler la présence d'un texte de rubrique finalisé (pas d'autre "Lorem ipsum"). Ces pages n'ont pas pu recevoir de constat COPY faute de rendu.
- **Recherche exhaustive de "Lorem ipsum"** sur l'ensemble du site après rendu JS : deux occurrences confirmées côté serveur, d'autres peuvent exister dans les blocs injectés.

## Renvois

- `<title>` affichant `[SEO Désactivé]` sur les pages auditées, et titre de la 404 `"Erreur 404 | Agence Félix"` (marque agence au lieu de la marque Parister) : **audit-seo** (balise `title`).
- Images et carrousels non chargés (SVG vides / placeholders), contenu conditionné à l'activation du JavaScript : **audit-tech**.
- Page 404 (utilité et chemin de retour "Retourner sur la page d'accueil"), champs des formulaires contact et newsletter, mentions RGPD, chemin de conversion : **audit-ux**.
- Bons cadeaux : absence de description de l'offre, des tarifs, des conditions et de la durée de validité (structure et périmètre de l'offre) : **audit-conversion**.
- Faiblesse de preuve des superlatifs/claims en tant que preuve (chiffre de superficie non sourcé, etc.) : **audit-conversion** (COPY ne porte que le risque de formulation, cf. COPY-10).

---

### 5.4 Audit UX

# Audit UX - Hôtel Parister (préproduction)

## 1. Périmètre

- **Cible** : https://hotel-parister.felix-lait.com/ - site **vitrine** hôtelier (préproduction).
- **Méthode** : lecture du HTML servi (curl + fetch), du code Twig et SCSS du projet local `C:\wamp64\www\hotel-parister-2026`. Pas de rendu visuel ni d'exécution JavaScript.
- **Date** : 2026-08-14.
- **Pages/gabarits examinés** : accueil, listing (chambres-suites), fiche produit (parister-suite), page de contenu (la-vie-au-parister + article salle de réunion), restaurant-bar, sport-bien-etre, salle-de-reunion-evenementiel, accès-et-contact (formulaire), réservation (moteur D-Edge), bons-cadeaux, galerie, plan-du-site, 404 forgé, mentions-legales, politique-cookies. Fichiers clés : `templates/front/default/base.html.twig`, `include/footer.html.twig`, `actions/menu/main.html.twig`, `templates/gdpr/body-prepend/google-tag-manager.html.twig`, `templates/gdpr/services/iframe-prototype-placeholder.html.twig`, `assets/scss/front/default/components/form/_form.scss`, `layout/_accessibility.scss`, `assets/js/front/default/vendor.js`.
- **Contrôles écartés (type vitrine)** : tunnel e-commerce, compte invité, récapitulatif panier, tableaux de données - non applicables (aucun tunnel d'achat sur le site ; réservation et paiement sont hébergés hors site chez D-Edge). Le paiement n'est donc pas testable de bout en bout (voir Renvois et vérification humaine).

## 2. Synthèse

11 constats : **2 bloquants**, **3 majeurs**, **6 mineurs**. Point le plus urgent : Google Tag Manager se charge sur toutes les pages **sans aucun gate de consentement** (`noCookiesActive = true`), alors qu'aucun bandeau cookies n'est actif (Axeptio non configuré, module GDPR interne inactif) - dépôt de traceurs avant tout choix de l'internaute. En miroir, le moteur de réservation D-Edge est, lui, verrouillé derrière un consentement cookies qui n'a aucune interface pour être accordé, ce qui peut rendre la réservation inatteignable.

## 3. Constats

### [UX-01] Bloquant - Google Tag Manager chargé sans consentement, aucun bandeau cookies actif
- Preuve : `templates/gdpr/body-prepend/google-tag-manager.html.twig` L2-4 (`$noCookiesActive = true` → `display` toujours vrai) ; HTML de l'accueil L129-133 : snippet GTM inline injectant `googletagmanager.com/gtm.js?id=GTM-PR9R52V` au chargement. Body : `data-axeptio=""` (vide) ; footer sans lien « Gestion des cookies » (branche Axeptio et branche GDPR toutes deux inactives, cf. `include/footer.html.twig` L127-131).
- Impact : des traceurs de mesure sont déposés avant que le visiteur n'ait pu accepter ou refuser. Exposition juridique du client (RGPD / directive ePrivacy) sur un site grand public français.
- Correction : conditionner l'injection GTM à un consentement effectif (activer et configurer Axeptio, ou le module GDPR interne avec bandeau proposant refus aussi accessible qu'acceptation) ; ne pas forcer `noCookiesActive`.
- Effort : M
- Statut : fait vérifié

### [UX-02] Bloquant - Moteur de réservation verrouillé par un consentement cookies sans interface pour l'accorder
- Preuve : HTML de `/reservation`, bloc `<div class="booking-block gdpr-booking-wrap" data-code="dedge" data-axeptio-consent="dedge" data-prototype="...websdk.d-edge.com..." data-prototype-placeholder="...gdpr-activation-placeholder...">` ; le widget réel n'est monté qu'après consentement, sinon reste sur le placeholder « Pour afficher l'iframe vous devez activer les cookies » (`templates/gdpr/services/iframe-prototype-placeholder.html.twig` L9). Or Axeptio est vide et aucun bandeau/contrôle de consentement n'est présent (cf. UX-01).
- Impact : si aucun mécanisme ne permet d'accorder le consentement `dedge`, le moteur de réservation resterait bloqué sur son écran d'activation - conversion principale (réserver) potentiellement impossible. Incohérence de configuration : la mesure (GTM) se charge sans consentement, mais la réservation, elle, l'exige.
- Correction : rétablir un mécanisme de consentement fonctionnel (UX-01) et vérifier que la catégorie `dedge` peut être acceptée ; à défaut, retirer le gate de consentement sur le widget de réservation.
- Effort : M
- Statut : hypothèse - à confirmer en navigateur : charger `/reservation`, vérifier si le widget D-Edge s'affiche ou reste sur le placeholder « activer les cookies », et si un bandeau de consentement apparaît.

### [UX-03] Majeur - Page « Bons cadeaux » sans aucun moyen d'acheter (cul-de-sac)
- Preuve : HTML de `/bons-cadeaux` - aucun bouton d'achat, aucun lien vers une boutique de bons cadeaux, aucun conteneur de widget (contrairement à `/reservation`, aucun `data-code`/iframe/placeholder détecté ; seuls des chargeurs d'images lazy et un bouton « Suivez-nous sur insta » sont présents).
- Impact : l'action attendue « acheter un bon cadeau » n'a aucune suite sur sa page dédiée. Le visiteur venu acheter repart sans solution.
- Correction : intégrer la mécanique d'achat (widget D-Edge gift, boutique externe ou formulaire) et un CTA explicite ; à défaut, rediriger vers le canal de vente réel.
- Effort : M
- Statut : fait vérifié (le HTML servi ne contient aucun mécanisme ; une injection JS ultérieure reste possible mais aucun conteneur n'est présent - à confirmer en navigateur).

### [UX-04] Majeur - Mentions légales incomplètes (directeur de publication et hébergeur non renseignés)
- Preuve : `/mentions-legales` - identité société et SIRET présents (FORSTYLE HOTELS COLLECTION, 19 rue Saulnier 75009), mais les champs « Responsable de publication » (nom, email) et « Hébergeur » sont vides.
- Impact : mentions obligatoires incomplètes pour un site professionnel français (LCEN) - exposition juridique et perte de crédibilité.
- Correction : renseigner le directeur de la publication et l'hébergeur (raison sociale, adresse, contact).
- Effort : S
- Statut : fait vérifié

### [UX-05] Majeur - Aucune CGV alors qu'une vente de bons cadeaux est annoncée
- Preuve : footer de toutes les pages - seuls « Mentions légales » et « Politique relative aux cookies » sont présents ; aucune CGV/conditions de vente trouvée (recherche sur l'accueil et le menu légal).
- Impact : une vente de bons cadeaux sans conditions générales de vente accessibles est non conforme et fragilise juridiquement le client dès la mise en ligne de la vente.
- Correction : publier une page CGV (bons cadeaux : validité, remboursement, utilisation) et la lier au parcours d'achat et au footer.
- Effort : M (rédaction + décision client)
- Statut : fait vérifié (absence) - la nécessité est conditionnée à l'ouverture effective de la vente (liée à UX-03).

### [UX-06] Mineur - Lien d'évitement visible uniquement via une classe JS, pas via `:focus`
- Preuve : `assets/scss/front/default/layout/_accessibility.scss` L12-24 : `.skip-link { top:-1000px; left:-1000px }` et réapparition seulement sur `&.focused-el` (classe ajoutée par JS), sans règle `:focus`/`:focus-visible`.
- Impact : si le JS ne s'exécute pas ou n'attache pas la classe, le lien « Aller au contenu principal » reste hors écran au focus clavier - lien d'évitement inopérant.
- Correction : afficher le skip-link sur `:focus`/`:focus-visible` en CSS, indépendamment du JS.
- Effort : S
- Statut : fait vérifié (CSS) ; comportement au focus à confirmer au clavier.

### [UX-07] Mineur - Liens réseaux sociaux non accessibles au clavier
- Preuve : `actions/menu/main.html.twig` L187 et `include/footer.html.twig` L100 : `<span role="button" class="... js-open-window" data-url=...>` sans `tabindex` ; `assets/js/front/default/vendor.js` L214-220 : seul un `click` est écouté, aucun `keydown`.
- Impact : les liens Instagram/Facebook/LinkedIn/TikTok ne peuvent être ni atteints ni activés au clavier.
- Correction : utiliser un `<a href>` (ouverture nouvelle fenêtre gérée nativement) ou ajouter `tabindex="0"` + gestion `keydown` (Entrée/Espace).
- Effort : S
- Statut : fait vérifié

### [UX-08] Mineur - Pas de fil d'Ariane sur les fiches produit (profondeur 3 niveaux)
- Preuve : `/chambres-suites/fiche-produit/parister-suite` - aucun élément breadcrumb dans le HTML.
- Impact : sur une arborescence à trois niveaux, l'internaute n'a pas de repère de position ni de remontée directe vers la rubrique parente (le retour dépend du méga-menu).
- Correction : ajouter un fil d'Ariane sur les pages catalogue profondes.
- Effort : S
- Statut : fait vérifié

### [UX-09] Mineur - Page courante signalée par une classe sans `aria-current`
- Preuve : `actions/menu/main.html.twig` L138-139 : classe `active` sur l'item courant, sans attribut `aria-current="page"`.
- Impact : l'état « page courante » n'est pas exposé aux lecteurs d'écran.
- Correction : ajouter `aria-current="page"` sur le lien actif.
- Effort : S
- Statut : fait vérifié

### [UX-10] Mineur - Indicateur de focus retiré sur le champ email de la newsletter
- Preuve : `assets/scss/front/default/components/form/_newsletter.scss` L181-183 : `&:focus { outline: none; box-shadow: none; }` sur le champ email, sans indicateur de remplacement visible dans la règle.
- Impact : au clavier, la prise de focus sur le champ email de la newsletter peut ne pas être perceptible.
- Correction : fournir un indicateur de focus explicite (bordure/soulignement au `:focus-visible`).
- Effort : S
- Statut : hypothèse - à confirmer visuellement au clavier (un autre style de focus peut exister ailleurs).

### [UX-11] Mineur - Texte de consentement RGPD affiché à 11px
- Preuve : `assets/scss/front/default/components/form/_form.scss` L34-37 : `.checkbox-group.small-size label { font-size: 11px }` ; le consentement du formulaire contact porte la classe `small-size` (HTML : `data-group="checkbox-group form-gdpr-group small-size"`).
- Impact : un consentement juridiquement significatif est affiché en très petit corps, au détriment de la lisibilité.
- Correction : relever la taille du label de consentement à un corps lisible (≥ 14px).
- Effort : S
- Statut : hypothèse - lisibilité réelle non vérifiable sans rendu.

*Note (à ne pas régresser) : le formulaire de contact est correctement construit - `<label for>` associés, `type` adaptés (`email`/`tel`), `autocomplete` (`family-name`, `given-name`, `email`, `tel`), consentement requis non pré-coché. Les champs de saisie sont à 16px et 50px de haut (pas de zoom iOS, cible tactile correcte). La page 404 renvoie bien un code 404, avec en-tête, pied de page et lien « Retourner sur la page d'accueil ». Le méga-menu mobile est un composant Bootstrap `collapse` avec `<button>`, `aria-controls`/`aria-expanded` - accessible au clavier.*

## 4. À vérifier humainement (protocoles)

- **Réservation D-Edge (UX-02)** : ouvrir `/reservation` dans un navigateur réel avec JS. Conclure « bloquant » si le widget reste sur l'écran « activer les cookies » sans moyen d'accorder le consentement ; « conforme » s'il se monte et permet une recherche de disponibilité. Vérifier aussi le comportement mobile.
- **Bandeau cookies en production (UX-01)** : vérifier si l'ID Axeptio est renseigné en prod (préprod : `data-axeptio=""`). Conclure sur la présence d'un bandeau avec refus aussi accessible que l'acceptation, et sur le non-dépôt de GTM avant choix (onglet Réseau / stockage cookies).
- **Achat bon cadeau (UX-03)** : confirmer en navigateur qu'aucune mécanique d'achat n'est injectée en JS sur `/bons-cadeaux`, et identifier le canal de vente réel attendu par le client.
- **Skip-link et liens sociaux (UX-06, UX-07)** : naviguer au clavier (Tab). Conclure « défaut » si le skip-link n'apparaît pas au focus, ou si les icônes sociales ne sont ni focusables ni activables à Entrée/Espace.
- **Paiement / tunnel de réservation (hors site)** : non testable ici. Réaliser une réservation test en environnement D-Edge (moyens de paiement en bac à sable), du choix de dates à la confirmation, sur desktop et mobile.
- **Contrastes** : les couleurs sont définies dans `assets/scss/front/default/variables.scss` (ex. or `#b48608`) ; le calcul de contraste réel exige un rendu. Déléguer un contrôle WCAG AA (texte or sur fond blanc/primary, labels 11px) à une session navigateur outillée.
- **Débordements mobiles** : aucune largeur fixe structurante en px repérée (les `width` fixes vus concernent des éléments secondaires : bouton newsletter 160px, zone upload 94px). À confirmer sur appareil réel (viewport 360px) l'absence de scroll horizontal.

## Renvois

- **COPY** : le lien de navigation « Réserver » pointe vers `/chambres-suites` (listing) et non vers le moteur de réservation - cohérence libellé/destination.
- **CONV** : `/bons-cadeaux` sans structure d'offre (paliers, montants, périmètre) ; note d'avis « 4,7/5 - 1678 avis » présente mais commentée dans le footer (`include/footer.html.twig` L79-81) - vérifiabilité de la preuve ; pertinence des champs du formulaire de contact au regard de l'offre.
- **SEO** : marqueur « [SEO Désactivé] » visible dans les titres de préproduction ; indexabilité et robots.
- **TECH** : iframe GTM noscript, configuration Matomo, chargement du SDK D-Edge (`websdk.d-edge.com`) et performance associée, en-têtes de sécurité/CSP (nonce présents).

---

### 5.5 Audit CONVERSION

# Audit CONVERSION - hotel-parister.felix-lait.com

## 1. Périmètre

- **Cible** : https://hotel-parister.felix-lait.com/ (préproduction), site **vitrine hôtelière**.
- **Date** : 2026-08-14. **Méthode** : lecture du rendu (WebFetch) + inspection du HTML source (curl/grep) pour le tracking, les widgets et le rendu JavaScript.
- **Pages réellement examinées (10 sur 15)** : accueil, `/chambres-suites`, `/chambres-suites/fiche-produit/parister-suite`, `/salle-de-reunion-evenementiel`, `/la-vie-au-parister/fiche-actualite/besoin-d-une-salle-de-reunion-a-paris`, `/reservation`, `/bons-cadeaux`, `/acces-et-contact`, plus contrôle HTML brut de l'accueil et du 404. Non examinées faute de valeur ajoutée pour l'angle conversion : `/blog`, `/restaurant-bar-a-cocktail`, `/sport-bien-etre`, `/galerie-photos`, `/plan-du-site`, `/la-vie-au-parister`.
- **Contrôles écartés (type vitrine)** : contrôles e-commerce Prestashop (stock, variantes, tunnel panier-paiement) non applicables ; le paiement de réservation est hors site (moteur D-Edge) et part en vérification humaine avec protocole, conformément au cadrage.
- **Limite majeure de rendu** : `/bons-cadeaux`, `/salle-de-reunion-evenementiel` et `/reservation` ne rendent **aucun contenu métier sans JavaScript** (message serveur « Activez JavaScript pour charger plus de contenu interactif »). Les constats sur le contenu de ces pages sont donc en hypothèse ou non vérifiables, et listés en vérification humaine.

## 2. Synthèse

Infrastructure de mesure présente (GTM `GTM-PR9R52V` + Matomo + `dataLayer`) et moteur de réservation D-Edge intégré : les fondations transactionnelles existent. En revanche l'échange proposé est faible sur trois axes : **preuve quasi absente** (aucun avis, note, distinction datée ni presse), **parcours chambres sans réassurance ni palier intermédiaire** (la fiche n'offre qu'un CTA fort vers le moteur), et **descriptifs d'offre non délivrés** (texte « Lorem ipsum » en accueil et listing). 6 constats Majeurs, 2 Mineurs, aucun Bloquant confirmé. Point le plus urgent : **CONV-03** (aucun critère de choix pour départager 7 chambres) et **CONV-01** (absence de preuve sur un positionnement 5 étoiles).

## 3. Constats

### [CONV-01] Majeur - Preuve quasi absente sur l'ensemble du site
- Preuve : accueil, aucun avis client, note (TripAdvisor/Google/Booking), distinction datée, extrait de presse ou témoignage attribué détecté en HTML ; seule affirmation portant l'offre : « Un boutique hôtel 5 étoiles au cœur de Paris » (auto-déclarée, sans classement Atout France ni label affiché). « Presse » n'est qu'un lien de navigation, sans reprise de citation.
- Impact : sur un positionnement haut de gamme, rien n'étaye la promesse ; le visiteur n'a aucun signal tiers pour lever le risque perçu avant de s'engager ou de réserver.
- Correction : afficher une preuve attribuée ou vérifiable près des affirmations qui portent l'offre (note et volume d'avis d'une plateforme reconnue, distinction ou classement avec année, citation presse sourcée et datée).
- Effort : M
- Statut : fait vérifié

### [CONV-02] Majeur - Fiche chambre : un seul palier d'engagement, sans prix, réassurance ni contact au point de décision
- Preuve : `/chambres-suites/fiche-produit/parister-suite` décrit surface (« 52 m² ») et équipements mais n'affiche aucun prix ni « à partir de », aucune condition (petit-déjeuner, taxes, annulation), et propose un unique CTA « Réserver cette chambre » vers `/reservation` (moteur D-Edge). Aucun téléphone, aucune option moins engageante sur la fiche.
- Impact : entre lire la fiche et entrer dans le moteur de réservation, aucun palier ; tout visiteur hésitant, sans dates arrêtées ou souhaitant une question, quitte la fiche sans point de contact.
- Correction : ajouter au niveau fiche un ordre de grandeur ou un déclencheur de disponibilité, une réassurance courte (annulation, petit-déjeuner/taxes) et un palier faible (téléphone/email ou « poser une question sur cette chambre »).
- Effort : M
- Statut : fait vérifié

### [CONV-03] Majeur - Listing chambres : aucun critère pour départager 7 offres de même niveau
- Preuve : `/chambres-suites` présente 7 types nommés (Supérieure, Deluxe, Deluxe terrasse, Junior suite, Junior suite terrasse, Suite duplex, Suite Parister) sans prix, sans surface/capacité, sans recommandation ni critère « quelle chambre pour quel profil » ; le bloc d'orientation est un placeholder « Lorem ipsum ». Tous les CTA sont identiques (« Découvrir »).
- Impact : au-delà de trois options de même niveau sans critère de choix, la décision se bloque ; le visiteur doit ouvrir jusqu'à 7 fiches (elles-mêmes sans prix) pour arbitrer.
- Correction : afficher par chambre un « à partir de », la capacité et un ou deux critères différenciants ; désigner une option recommandée ou proposer un filtre (couple, famille, vue/terrasse).
- Effort : M
- Statut : fait vérifié

### [CONV-04] Majeur - Formulaire de contact déséquilibré : téléphone obligatoire, rien annoncé en retour
- Preuve : `/acces-et-contact` demande Nom, Prénom, Email, **Téléphone (obligatoire)** et Message, sans annoncer ce que le visiteur obtient (délai de réponse, rappel, absence de démarchage). Aucune confirmation/suite annoncée après envoi (à confirmer par soumission réelle).
- Impact : le téléphone, champ intrusif, est exigé avant toute mise en confiance et sans contrepartie affichée ; le déséquilibre demande/donne décourage la prise de contact.
- Correction : rendre le téléphone facultatif ou justifier son intérêt pour le visiteur ; annoncer avant l'envoi le délai de réponse et l'absence de démarchage, et prévoir un message de confirmation explicite après envoi.
- Effort : S
- Statut : fait vérifié (post-envoi : hypothèse, à confirmer par soumission)

### [CONV-05] Majeur - Événementiel : intention non captée, dispositif de demande non délivré
- Preuve : l'article `/la-vie-au-parister/fiche-actualite/besoin-d-une-salle-de-reunion-a-paris` se termine sans CTA vers un formulaire de devis (seuls téléphone/email génériques). La page `/salle-de-reunion-evenementiel` ne rend aucun espace, capacité, tarif ni formulaire de devis sans JavaScript (contenu JS ; un seul champ email newsletter rendu côté serveur).
- Impact : là où naît l'intention événementielle, aucun palier structuré (devis, rappel) n'est offert de façon vérifiable ; l'échange se limite à un contact manuel générique.
- Correction : ajouter dans l'article et sur la page événementiel un formulaire de demande dédié (date, nombre de personnes, type d'événement) et vérifier que le dispositif se charge de façon fiable ; annoncer le délai de réponse et l'interlocuteur.
- Effort : M
- Statut : hypothèse (contenu de la page événementiel non vérifiable sans JS)

### [CONV-06] Majeur - Bons cadeaux : packaging, montants, achat et validité non délivrés
- Preuve : `/bons-cadeaux` ne rend, sans JavaScript, aucune formule, montant, mécanisme d'achat/paiement ni condition (validité, dématérialisé/physique, remboursement) ; le seul élément serveur est le champ email newsletter. Aucun widget d'achat détecté dans le HTML brut (contrairement à `/reservation` qui embarque D-Edge).
- Impact : si le bon cadeau est un produit vendu en ligne, l'offre et sa réassurance ne sont pas garanties au visiteur ; l'échange se réduit à une capture email au lieu d'un achat.
- Correction : vérifier la présence effective d'un dispositif d'achat ; à défaut, packager les bons (montants ou coffrets nommés), afficher validité et conditions d'utilisation, et proposer un achat ou une demande explicite.
- Effort : M
- Statut : hypothèse (contenu non vérifiable sans JS)

### [CONV-07] Mineur - Aucun ordre de grandeur tarifaire sur le parcours chambres
- Preuve : ni l'accueil, ni `/chambres-suites`, ni la fiche n'affichent de prix ou « à partir de » ; le seul moyen d'obtenir un tarif est d'entrer dans le moteur D-Edge (`secure-hotel-booking.com`) via `/reservation`.
- Impact : l'absence d'ancrage tarifaire est un choix défendable en hôtellerie haut de gamme et reste compensée par l'auto-qualification via le moteur ; elle prive néanmoins le visiteur d'un repère pour se pré-qualifier avant d'entrer dans le tunnel.
- Correction : envisager un « à partir de » indicatif au niveau listing/fiche, sans imposer de grille complète.
- Effort : S
- Statut : fait vérifié

### [CONV-08] Mineur - Événements de conversion et suivi cross-domain non vérifiables
- Preuve : GTM `GTM-PR9R52V`, Matomo et `dataLayer` présents sur toutes les pages ; liens `tel:+33180509191` et `mailto:bonjour@hotelparister.com` cliquables. La configuration des événements (envoi de formulaire, clics tel/mail, réservation, achat bon cadeau) réside dans le conteneur GTM, non lisible en HTML ; le tunnel de réservation part vers `secure-hotel-booking.com`, donc la confirmation de réservation n'est pas mesurable on-site sans configuration cross-domain.
- Impact : sans événements configurés et sans continuité de mesure vers D-Edge, les conversions réelles (réservation, contact, bon cadeau) restent non pilotables malgré une infrastructure en place.
- Correction : vérifier/déclarer dans GTM et Matomo les événements de conversion clés et le suivi cross-domain vers le moteur D-Edge.
- Effort : M
- Statut : non vérifiable (configuration hors HTML)

## 4. À vérifier humainement

- **Bons cadeaux (CONV-06)** : ouvrir `/bons-cadeaux` avec JavaScript actif ; relever formules, montants, mécanisme d'achat/paiement et conditions (validité, format, remboursement). Conclure : dispositif d'achat réel ou simple capture email.
- **Devis événementiel (CONV-05)** : ouvrir `/salle-de-reunion-evenementiel` avec JS ; vérifier l'existence d'un formulaire de devis, ses champs (équilibre demande/donne) et l'annonce d'un délai/interlocuteur.
- **Formulaire de contact (CONV-04)** : soumettre une demande test en préprod ; vérifier le message de confirmation, la suite annoncée et l'absence de cul-de-sac (retour accueil sans message).
- **Réassurance moteur D-Edge** : dans le widget chargé (`/reservation`), vérifier l'affichage des conditions d'annulation, taxes et éventuelle garantie meilleur prix avant paiement. Le tunnel de paiement lui-même ne se teste qu'en réservation test / mode sandbox (hors site, `secure-hotel-booking.com`).
- **Mesure (CONV-08)** : inspecter le conteneur GTM et Matomo (accès admin requis) pour confirmer les événements de conversion et le suivi cross-domain vers D-Edge.

## Renvois

- **audit-copy** : remplacement des textes « Lorem ipsum » (accueil, listing chambres) par des descriptifs réels ; formulation du claim « boutique hôtel 5 étoiles » (positionnement à surveiller si tournure superlative).
- **audit-tech** : `/bons-cadeaux`, `/salle-de-reunion-evenementiel` et `/reservation` ne rendent aucun contenu métier sans JavaScript (chargement/rendu à fiabiliser) ; page 404 renvoie bien HTTP 404.
- **audit-seo** : contenu de ces trois pages non indexable sans exécution JS.
- **audit-ux** : ergonomie du formulaire contact (message d'erreur « Veuillez remplir les champs obligatoires !! », confirmation post-envoi), libellé de consentement RGPD de la newsletter, utilité et chemin de retour de la page 404.

---

## 6. À vérifier humainement (union dédoublonnée)

Regroupement des points hors de portée des agents, avec protocole.

**Rendu et navigateur réel**
- **Contenu des pages en rendu JS** (restaurant-bar, sport-bien-etre, salle-de-reunion-evenementiel, bons-cadeaux, fiche-actualité) : ouvrir chaque URL, JS activé ; vérifier la présence d'un contenu editorial finalisé (pas d'autre "Lorem ipsum"), et pour l'événementiel/bons cadeaux la présence d'un dispositif de demande/achat. Couvre COPY (corps editorial), CONV-05, CONV-06, UX-03.
- **Réservation D-Edge (UX-02)** : charger `/reservation` en navigateur ; conclure bloquant si le widget reste sur le placeholder "activer les cookies" sans moyen de consentir, conforme s'il monte et permet une recherche de disponibilité. Tester aussi en mobile.
- **Erreurs JavaScript runtime (TECH)** : session navigateur pilotée sur les 15 URLs, relever la console (erreurs, requetes async en échec), en particulier `/reservation` et `/galerie-photos`.
- **Skip-link et liens sociaux (UX-06, UX-07)** : navigation clavier (Tab) ; conclure défaut si le skip-link n'apparait pas au focus ou si les icones sociales ne sont ni focusables ni activables (Entrée/Espace).
- **Contrastes WCAG AA (UX)** : contrôle outillé du texte or `#b48608` sur fonds clairs et des labels 11px.
- **Débordements mobiles (UX)** : viewport 360px, absence de scroll horizontal.

**Serveur, mesure et sécurité (accès requis)**
- **Cause du TTFB ~4 s (TECH-01)** : profiler `/` et `/chambres-suites` ; relever le nombre de requetes Doctrine par page et l'état d'OPcache côté o2switch.
- **Support HTTP/2 (TECH)** : DevTools ou WebPageTest.
- **Filtre XSS maison (TECH)** : tester des saisies légitimes (contact, paramètres d'URL) pour écarter les faux positifs 403.
- **`composer audit` (TECH)** : contrôle des CVE des dépendances verrouillées.
- **Mesure des conversions (CONV-08)** : inspecter GTM `GTM-PR9R52V` et Matomo (événements clés + suivi cross-domain vers D-Edge).
- **Formulaire de contact (CONV-04)** : soumission test en préprod ; message de confirmation, suite annoncée, absence de cul-de-sac.

**Angle mort à instruire (voir §4)**
- **Stratégie de rendu des gabarits commerciaux** : décider SSR / pré-rendu pour les pages dont le corps est injecté en JS, et chiffrer l'impact SEO réel. Aucun agent n'a pu le porter en constat ; c'est le point ouvert le plus important.

## 7. Escalade humaine (validation juridique, client ou métier)

Points isolés nécessitant un arbitrage hors compétence de l'agence seule.

- **[UX-01] RGPD / ePrivacy** - dépôt de traceurs (GTM) sans consentement préalable : mise en conformité du dispositif de consentement avant mise en production. Décision technique + validation conformité.
- **[UX-04] LCEN** - mentions légales incomplètes (directeur de publication, hébergeur) : à compléter par le client.
- **[UX-05] Vente en ligne** - absence de CGV alors qu'une vente de bons cadeaux est annoncée : rédaction/validation juridique conditionnée à l'ouverture effective de la vente.
- **[COPY-09] Classement réglementé** - mention "5 étoiles" : confirmer la réalité et la validité du classement Atout France. Ne pas publier sans validation.
- **[COPY-10] Superlatifs / comparaison** - "la plus grande suite de l'hôtel" et discours de marque de la fiche Suite Parister : confirmer l'exactitude factuelle côté client.
- **[TECH-02] Sécurité** - intention de désactivation de la CSP (`CSP_DISABLED = true`) : décision durable ou état temporaire ; trancher activation en report-only ou retrait du dispositif de nonces.

---

*Rapport généré par le skill `recettage` (5 audits parallèles + consolidation). Lecture seule : aucune modification du site ni du projet audité. Document de travail interne, à relire avant tout usage client.*
