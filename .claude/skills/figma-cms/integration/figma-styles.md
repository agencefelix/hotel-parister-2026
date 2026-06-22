# Styles Figma importés (dev mode) — RÉFÉRENCE projet

> Tokens **relevés en dev mode** sur le fichier Figma du projet (node home `542:1592`).
> **Consulter ce fichier AVANT de styler** une bande pour ne pas deviner couleurs/tailles/poids.
> ⚠️ Le fichier Figma n'a **aucun style PUBLIÉ** (`/v1/files/:key/styles` → 0) : les styles sont
> appliqués en **valeurs brutes** dans les calques. On les **extrait des nodes** via
> `python .claude/skills/figma-cms/tooling/figma-tokens.py <nodeId>` → cette palette est LA référence.
> À compléter en lançant l'extracteur sur **chaque page** de la maquette (pas seulement la home).
>
> 📦 **Ce `.md` est un RÉSUMÉ.** La référence **exhaustive par token** (tous les nodes : font-size,
> couleur, ombre/shadow, strokes, radius, paddings, texte…) est le **JSON** `figma-tokens.<page>.json`
> dans ce dossier, généré par
> `python .claude/skills/figma-cms/tooling/figma-export-tokens.py <nodeId> integration/figma-tokens.<page>.json 12`.
> Générer ce JSON **pour chaque page** et le **consulter** pour les valeurs exactes ; tenir ce `.md` à jour comme synthèse.

## Couleurs (variables projet)
| Token | Hex | Variable SCSS |
|---|---|---|
| Page / fond clair | `#f4f0f1` | `$light` / `$beige` |
| Or (signature) | `#b48608` | `$primary` |
| Navy | `#001e56` | `$navy` / `$secondary` |
| Teal (sage) | `#8fb3b1` | `$teal` |
| Vert restaurant | `#00561b` | `$success` |
| Texte sombre | `#141414` | `$dark` |
| Blanc / clair texte | `#ffffff` / `#f4f0f1` | `$white` / `$light` |
| Cartes produits (fond) | `#402624` / `#141414` | — |

## Typographie (fontSize / fontWeight / letterSpacing / case / fill)
| Élément | Taille | Poids | Tracking | Casse | Couleur |
|---|---|---|---|---|---|
| Hero titre « boutique hôtel & spa » | 38px | 700 | 0.4em | UPPER | **#f4f0f1 (blanc)** |
| Hero script « Parister » | 133px | 400 | 0 | — | **#ffffff (blanc)** |
| Hero CTA « réservez un séjour » | 14px | 700 | 0.2em | UPPER | **#f4f0f1 (blanc)** |
| Nav liens (Réserver/Menu/Bons cadeaux) | 14px | 700 | 0.25em | UPPER | #ffffff (top) / #141414 (scroll) |
| Bandeau alerte | 14px | 700 | 0.4em | UPPER | #f4f0f1 sur fond #b48608 |
| Titre de bande (intro) | 32px | 700 | 0.4em | UPPER | accent section |
| Titre de carte (univers/produits/spa) | 24px | 700 | 0 | UPPER | accent / #141414 / #fff |
| Sous-titre script de bande | 96px | 400 | 0 | — | accent section |
| Sous-titre script de carte | 54px | 400 | 0 | — | accent section |
| Body / texte courant | 16px | **300** | 0 | — | accent / #141414 |
| Kicker (getaway/workspaces) | 16px | 700 | 0.4em | UPPER | #f4f0f1 |

## Couleur de texte par SECTION (fond → texte)
| Bande | Fond | Texte / accents |
|---|---|---|
| Hero | image | **blanc** |
| Bandeau alerte | or `#b48608` | blanc `#f4f0f1` |
| Univers | clair `#f4f0f1` | titre `#141414` + script or |
| Getaway / Workspaces | image | blanc + script filigrane |
| Chambres (rooms) | navy `#001e56` | **teal `#8fb3b1`** |
| Cartes produits | image (overlay) | blanc |
| Passerelles (restaurant) | clair | **vert `#00561b`** |
| Spa | teal `#8fb3b1` | **navy `#001e56`** |
| Art & rencontres | clair | **or `#b48608`** |
| Événements / Newsletter | clair | or `#b48608` |

## Espacements (auto-layout relevés)
- CTA Box (réservation) : padding 32px.
- Teaser produits : gap entre cartes ~150px (desktop).
- Frames de contenu : gouttières 16–24px.
