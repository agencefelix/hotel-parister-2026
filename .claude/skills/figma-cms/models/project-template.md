# Spécificités projet — MODÈLE (à copier)

> **Modèle réutilisable.** Copier ce fichier en `figma/integration/<nom-du-projet>.md`
> au démarrage d'un nouveau projet Figma → CMS, puis remplir les valeurs `null` /
> placeholders. Ce fichier ne doit contenir QUE le **spécifique projet** : les règles
> génériques restent dans `integration-prompts.md` et `mapping-blocktypes.md`. Les
> artefacts (pages, layout, screenshots, media, interactions) vont sous
> `.claude/skills/figma-cms/integration/`.

## URLs à demander au démarrage (OBLIGATOIRE)

Cf. `integration-prompts.md` (« Au démarrage d'un projet : deux URLs à demander »).

| Donnée | Valeur |
|---|---|
| URL site de prod | `null` |
| URL prototype Figma (proto) | `null` |
| Node-id de départ du prototype | `null` |

## Fichier Figma

- File key : `null`
- URL type : `figma.com/design/<FILE_KEY>/<NomProjet>?node-id=<n>-<m>`
- Rappel : dans l'URL le séparateur de node-id est `-` (ex. `95-957`) ; l'API REST attend `:` (ex. `95:957`).
- Connexion : `FIGMA_TOKEN` + `FIGMA_FILE_KEY` dans `.env` ; lecture via `App\Service\Figma\FigmaApiClient` (scope `file_content:read`).

## Pages

> Une entrée par page intégrée. Renseigner le node-id `[page|slug]` et l'état du balisage.

| Page (slug) | Node-id `[page]` | Balisage (zones/cols posés ?) |
|---|---|---|
| `null` | `null` | `null` |

### Zones (par page)

> Si `[zone]` posés : structure faisant foi. Sinon : zones **déduites** (géométrie +
> captures HD), compte indicatif (±1), à confirmer. Renseigner la **couleur de fond**
> (`background`, extrait par `PageParser` : `#rrggbb` / `#rrggbbaa` / `null` si image).

| # | Zone | Fond (background) |
|---|---|---|
| `null` | `null` | `null` |

## Éléments de layout (intégrés une seule fois)

> Descripteurs déclaratifs dans `integration/layout/*.json` ; captures
> **exclusivement** dans `integration/screenshots/layout/` (basename). Cf.
> `integration-prompts.md`.

| Élément | État | node-id Figma | Capture | CMS (entité / slug / rôle) |
|---|---|---|---|---|
| nav | fermée | `null` | `null` | `Menu` slug `main` |
| nav | ouverte | `null` | `null` | idem |
| footer | défaut | `null` | `null` | `Menu` slug `footer` |
| newsletter | défaut | `null` | `null` | module `newsletter` / `ROLE_NEWSLETTER` (à activer) |
| social-wall | défaut | `null` | `null` | blocType `social-networks` (global) ou config `social_networks` — ⚠️ vérifier en base, pas de module dédié garanti |

## Interactions du prototype

> Cartographiées depuis le proto (cf. `integration-prompts.md` « Lire les interactions
> du prototype »). Artefact : `integration/interactions/proto-<node>.json`.

| Élément | Trigger | Action / destination | Transition |
|---|---|---|---|
| `null` | `null` | `null` | `null` |

## Multilingue

> Cf. `prod-urls.json` (`alternates` par URL langue par défaut + `language_groups`).

| Donnée | Valeur |
|---|---|
| Locale par défaut | `null` |
| Autres locales | `null` |
| Domaines par langue | `null` |

## Notes / anomalies projet

- `null`
