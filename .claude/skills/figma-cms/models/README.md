# Modèles réutilisables (Figma → CMS)

Ce dossier centralise **tous les modèles vides** (squelettes à valeurs `null`)
servant de référence pour structurer les artefacts d'un projet d'intégration
Figma → CMS — **les prochains projets comme celui-ci**.

## Usage

Au démarrage d'un projet, **copier** ces modèles dans **`.claude/skills/figma-cms/integration/`**
(qui représente le projet : `config.json`, `prod-urls.json`, `seo.json`, la spec
`<nom-du-projet>.md`, et les dossiers `pages/`, `layout/`, `screenshots/`, `media/`,
`interactions/`), puis remplir les valeurs. Ne jamais modifier les fichiers de ce
dossier `figma/models/` avec des données réelles : ce sont des **gabarits** qui
doivent rester vides.

> Les **règles** d'intégration (génériques) restent dans
> `.claude/skills/figma-cms/integration-prompts.md` et `mapping-blocktypes.md`. Ici on ne
> stocke que des **structures de fichiers** vides.

## Contenu

| Modèle | Correspond à | Rôle |
|---|---|---|
| `project-template.md` | `figma/integration/<nom-du-projet>.md` | Spécificités projet (file key, pages, layout, interactions, multilingue) |
| `config.json` | `figma/integration/config.json` | Config globale du site (alignée sur `bin/data/config/default.yaml`) |
| `prod-urls.json` | `figma/integration/prod-urls.json` | URLs de prod par langue + appariement multilingue (hreflang) |
| `seo.json` | `figma/integration/seo.json` | SEO crawlé par URL, groupé par langue |
| `pages/page.json` | `figma/integration/pages/<slug>.json` | Architecture CMS d'une page (Zones → Cols → Blocs) |
| `layout/nav.json` | `figma/integration/layout/nav.json` | Descripteur layout nav |
| `layout/footer.json` | `figma/integration/layout/footer.json` | Descripteur layout footer |
| `layout/newsletter.json` | `figma/integration/layout/newsletter.json` | Descripteur layout newsletter (module à activer) |
| `layout/social-wall.json` | `figma/integration/layout/social-wall.json` | Descripteur layout mur social (≠ footer) |
| `interactions/proto.json` | `figma/integration/interactions/proto-<node>.json` | Cartographie des interactions du prototype |

`null` = à remplir. Les `_note` / `_source` expliquent l'origine attendue de la donnée.
