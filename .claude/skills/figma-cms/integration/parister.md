# Intégration Figma → CMS — Hôtel Parister (2026)

Spec projet (spécifique). Générique = `.claude/skills/figma-cms/`.

## Sources
- **Fichier Figma** : `VxyHdf12DFWhx0I6SxX9ce` (clé dans `.env`).
- **Prototype** : node `410-4801` (frame "A", brouillon home desktop).
- **Prod** (texte/config uniquement) : https://www.hotelparister.com
- **Nomenclature** : https://figma-doc.agence-felix.fr/ (source de vérité, re-consultée à chaque intégration).

## Pages `[page|…]` découvertes
| Node | Tag | Rôle | Slug CMS |
|---|---|---|---|
| 542:1592 | `[page\|home]` | Home desktop | `home` |
| 516:2245 | `[page\|product-view]` | Fiche chambre | layout catalogue `main-catalog` |
| 484:675 | `[page\|home\|mobile]` | Variante mobile (≠ page) | — |

> Frames "A" (410:4801) et "B" (417:6577) = itérations de travail **non taggées** → ignorées.
> Composants (CTA, icônes, Sticky Menu, patterns) = non taggés `[page]` → ignorés.

## Contextualisation produit
- **Produit phare = la Chambre** (`Module\Catalog\Product`). Catalogue « Principal », layout `main-catalog`.
- **Actualités = « La vie au Parister »** (`Module\Newscast`) : événements, expositions, parenthèses littéraires.

## Couleurs (EXCLUSIVEMENT Figma — fills SOLID)
| Rôle | Hex |
|---|---|
| primary (or signature) | `#b48608` |
| secondary / navy (chambres) | `#001e56` |
| teal/sage (spa) | `#8fb3b1` |
| light (rosé/écru) | `#f4f0f1` |
| dark | `#141414` |
| brun (accents) | `#402624` |

→ `$theme-colors` SCSS : ajout `navy`, `teal`, `brown`, `beige` (+ `$default-bootstrap-colors`).

## Polices
- **Museo Sans** (300/500/700/900) — corps + titres. Récupérée du CSS prod (`/fonts/Museo`), intégrée en local (`assets/lib/fonts/parister/`, woff2). Fallback anti-CLS recalculé via capsize.
- **Parister Script** (cursive décorative : « Parister », « parisienne », « rencontres »…).
  ⚠ Maquette = **August Script Bold free** (absente de la prod, à licencier). Substitut OFL : Yellowtail (`script.woff2`).

## Structure home (8 zones, fidèle aux 10 bandes maquette)
1. Hero pleine page (slider `banner`) — « Boutique hôtel & spa / Parister » + CTA Réservez un séjour.
2. Teasers d'univers (3 col) — Séjourner / Boire et manger / Se détendre.
3. Votre parenthèse parisienne (hero).
4. Chambres & Suites (navy) — intro + CTA + image.
5. Les passerelles, restaurant & bar (light) — image + intro + CTA.
6. Spa, bien-être & sport (teal) — intro + CTA + image.
7. Workspaces / séminaires & événements (hero).
8. Teaser actualités « Art & rencontres / Derniers événements » (newscast-teaser).

## Layout (intégré une seule fois)
- `[nav]` (386:1793) : ☰ MENU · logo PARISTER HOTEL · RÉSERVER ‖ BONS CADEAUX. Menu ouvert → arbo complète.
- `[footer]` : bandeau or, menu, FORSTYLE, adresse, note 4,7/5.
- `[newsletter]` + `[socialwall]` (« Suivez-nous sur Insta ») = layout, exclus des pages.

## Modules activés (WebsiteFixtures)
`ROLE_CATALOG` (chambres), `ROLE_GALLERY`, `ROLE_MAP`, `ROLE_CONTACT`, `ROLE_NEWSLETTER`, `ROLE_FAQ`
(+ defaults : PAGE, NEWSCAST, SLIDER, SEO, FORM, NAVIGATION, MEDIA, TRANSLATION…).

## Config (prod sauf couleurs)
- Société : FORSTYLE HOTELS COLLECTION — capital 2 000 000 € — TVA FR82810709113.
- Adresse : 19 rue Saulnier, 75009 Paris — `48.8753155, 2.3443779` (Nominatim).
- Tél : +33 (0)1 80 50 91 91 — Email : bonjour@hotelparister.com.
- GTM : `GTM-PR9R52V`. Réseaux : Facebook, Instagram, LinkedIn, TikTok.
- Locales : `fr` (défaut) + `en`, `es`, `zh`. Domaines : hotelparister.com / paristerhotel.com / es. / cn.

## Données réelles injectées
- **Chambres** (CatalogFixtures) : Supérieure, Deluxe, Deluxe terrasse, Junior Suite, Junior Suite Terrasse, Suite Duplex, Suite Parister.
- **Actualités** (NewscastFixtures) : Céline Dion/Accor Arena, parenthèses littéraires, expositions, Morning Wellness Club, Paris Cocktail Week, concerts jazz…

## Reste à parfaire (back-office / dev)
- Imagerie de section home : actuellement médias de marque (hero/share) ; brancher les visuels réels (`media/home/*.jpg`) via `UploadedFileFixtures` pour chaque bloc.
- Contenu multilingue par bloc (en/es/zh) : SEO home capturé par locale (`seo.json`) ; saisie back-office ou extension fixtures multi-locale.
- Police script définitive (licence August Script Bold).
