# figma-cms — Token Figma : quelles étendues activer

Le skill lit Figma via un **Personal Access Token** (dans `.env` : `FIGMA_TOKEN`, `FIGMA_FILE_KEY`).
Ce mémo dit **quoi cocher** à la génération du token (étendues vérifiées sur le terrain).

## ✅ REQUISE — et suffisante dans la quasi-totalité des cas

- **`file_content:read`** — **la seule indispensable.** Elle donne TOUT ce dont le skill a besoin :
  - arbre des nœuds, géométrie, `fills` / `strokes` / `effects`, **styles texte par nœud** ;
  - le **dictionnaire de STYLES NOMMÉS** du design system (champ `styles` du fichier) + les références
    `node.styles` → **palette + échelle typo nommées** (`tooling/figma-named-styles.mjs`,
    `reconcile-colors`, `reconcile-typography --named`) ;
  - **rendu d'images** (`/v1/images`) → captures de bandes + médias ;
  - **interactions du prototype** (`node.interactions` : triggers, transitions, `SMART_ANIMATE`)
    → animations (`tooling/prototype-interactions.mjs`, `smart-animate-diff.mjs`).
  > Tokens, styles nommés, couleurs, animations : **tout vient d'ici.** Dans le doute, n'active que ça.

## ➕ OPTIONNELLES (read), selon le projet

- **`library_content:read`** — débloque `/v1/files/:key/styles`, `/components`, `/component_sets`
  (styles/composants **publiés**). Utile seulement si le fichier publie une bibliothèque **et** que tu
  veux ces endpoints. ⚠️ **Souvent redondant** : les styles nommés sont déjà dans `file_content:read`.
- **`team_library_content:read`** — composants/styles publiés des **bibliothèques d'ÉQUIPE**.
  À activer **si** la maquette consomme un **design system d'agence partagé**.
- **`file_dev_resources:read`** — ressources **Dev Mode** (annotations, liens, Code Connect).
  Souvent **vide** → gain marginal.

## ⛔ Inutiles pour l'intégration

`current_user:read`, `file_metadata:read`, `projects:read`, `webhooks:*`, et **toutes les `:write`**.
- `file_versions:read` : utile **uniquement** pour figer/reproduire une **version précise** (la maquette
  bouge en cours de route), pas pour la fidélité visuelle.

## ⚠️ Pièges à connaître

- **`library_assets:read` ≠ lecture des styles/composants.** C'est l'API **Library Analytics**
  (statistiques d'usage). Les endpoints `/styles` `/components` renvoient **403** avec ce seul scope
  (« requires the `library_content:read` scope »). Ne pas le choisir pour lire le design system.
- **`file_variables:read` (Figma Variables) n'est PAS dans la liste** d'un PAT standard → **Enterprise**.
  C'est le plus gros levier (tokens couleur/espacement/typo **avec modes**), mais **inaccessible** hors
  Enterprise. Le skill s'en passe en exploitant les **styles nommés** (déjà dans `file_content:read`).

## En résumé

> **Active `file_content:read`. Point.**
> Ajoute `library_content:read` / `team_library_content:read` **seulement** si un système de
> styles/composants **publié** (fichier ou équipe) est réellement utilisé.
> Expiration courte conseillée ; régénère un token dédié intégration au besoin.

---

*Le reste de la doc du skill : `SKILL.md` (contrat + frontmatter), `integration-prompts.md` (playbook),
`models/` (mécanique CMS), `tooling/` (outils génériques), `integration/` (artefacts du projet courant).*
