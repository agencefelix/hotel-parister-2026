# Spécificités projet — hotel-parister-2026

> Spec **propre au projet**. Les règles génériques sont dans `integration-prompts.md`
> et `mapping-blocktypes.md` ; les gabarits dans `models/`. Tous les artefacts du
> projet vivent sous `.claude/figma/integration/`.

## Entrées (kickoff 2026-06-20)

| Donnée | Valeur |
|---|---|
| URL site de prod | https://www.hotelparister.com |
| URL prototype Figma | `figma.com/proto/VxyHdf12DFWhx0I6SxX9ce/Parister?node-id=410-4801` |
| Node de départ proto | `410:4801` (frame « A ») |
| File key | `VxyHdf12DFWhx0I6SxX9ce` (= `FIGMA_FILE_KEY` du `.env`) |
| Connexion | token `FIGMA_TOKEN` (`.env`), scope `file_content:read`, via `App\Service\Figma\FigmaApiClient` |

## Les 30 nœuds livrés (classés)

> Rappel : node-id URL `-` → API REST `:`. « Implémenter » = dry-run (architecture +
> captures), **aucune écriture base** tant que non validé.

### Pages (desktop)

| Node | Nom | Taille | Note |
|---|---|---|---|
| `542:1592` | `[page\|home]` | 1440×11866 | home — **seule taggée** |
| `410:4801` | A | 1440×11866 | home (frame du **proto**, porte les interactions) |
| `417:6577` | B | 1440×11866 | variante/état de page (à lever : A vs B vs home) |
| `516:2245` | Chambre | 1440×4589 | page Chambre |

### Pages (mobile)

| Node | Nom | Taille |
|---|---|---|
| `484:675` | Homepage Mobile | 412×10835 |
| `501:3911` | Menu | 412×1310 |

### Layout (intégrés une seule fois)

| Node | Élément | Taille |
|---|---|---|
| `55:3139` | nav fermée (barre) | 1360×123 |
| `386:1793` | nav ouverte (méga-menu) | 1440×970 |
| `42:1745` | Sticky Menu (desktop) | 1440×48 |
| `484:3706` | Sticky Menu (mobile) | 412×82 |

### Design system — atomes / CTA / icônes / component sets

| Node | Type | Nom |
|---|---|---|
| `410:4616` | INSTANCE | CTA |
| `410:4608` | COMPONENT_SET | CTA |
| `410:4669` | COMPONENT_SET | CTA |
| `15:387` | COMPONENT | CTA Label |
| `6:178` | COMPONENT | CTA Box |
| `145:3601` | COMPONENT | CTA Box |
| `33:1165` | GROUP | Group 48 (CTA ?) |
| `145:3641` | GROUP | Group 111 (barre CTA ?) |
| `145:3596` | COMPONENT | ICONS / FLÈCHE DROITE (→ indice « lien ») |
| `403:1237` | COMPONENT | ICONS / PARISTER PATTERN |
| `516:1982` | COMPONENT_SET | ICONS |
| `106:698` | COMPONENT_SET | Component 14 (4599×10201) |
| `484:3607` | COMPONENT_SET | Component 10 |
| `410:4682` | COMPONENT_SET | Component 8 |
| `20:561` | COMPONENT_SET | Component 8 |
| `126:1588` | COMPONENT_SET | Component 15 |
| `95:957` | COMPONENT_SET | Group 79 |

### Divers / à clarifier

| Node | Type | Nom |
|---|---|---|
| `542:1591` | RECTANGLE | Rectangle 49 (7309×504 — board couleurs/bandeau ?) |
| `91:175` | TEXT | texte lorem d'exemple |
| `410:4858` | TEXT | « séminaires • réunions • événements » |

## Pages

| Page (slug) | Node-id `[page]` | Balisage |
|---|---|---|
| home | `542:1592` | `[page\|home]`, `[slider]`, `[nav]`, `[footer]` (zones/cols à poser) |

## Éléments de layout

| Élément | État | node-id | Capture | CMS |
|---|---|---|---|---|
| nav | fermée | `55:3139` | `nav-closed.png` | `Menu` slug `main` |
| nav | ouverte | `386:1793` | `nav-open.png` | idem |
| nav | sticky desktop | `42:1745` | `sticky-desktop.png` | template nav |
| nav | sticky mobile | `484:3706` | `sticky-mobile.png` | template nav |
| footer | défaut | `null` (à localiser dans une page) | `footer.png` | `Menu` slug `footer` |
| newsletter | défaut | `null` | `newsletter.png` | module `newsletter` (à activer) |
| social-wall | défaut | `null` | `social-wall.png` | blocType `social-networks` / config |

## Interactions du prototype (node `410:4801`)
> À cartographier → `integration/interactions/proto-410-4801.json`.

## Multilingue
> Voir `prod-urls.json`. fr = www.hotelparister.com (défaut) ; en/es/zh détectés via hreflang.

## Notes / anomalies projet
- ⚠️ API Figma : rate limit 429 atteint en sondant — **espacer les rendus d'images** (capture par lots, pauses).
- À lever : relation entre `542:1592` (home taggée), `410:4801` (A) et `417:6577` (B) — probablement la même home en états/variantes.
