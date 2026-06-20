---
name: figma-cms
description: >-
  Intègre une maquette Figma dans le CMS SFCMS 7 (Symfony 7.4 / PHP 8.5) de ce projet :
  dry-run maquette → arbre CMS (Page/Zones/Cols/Blocs), captures, médias, config du site,
  polices, et génération des fixtures (pages, menus, produits/actus, modules) pour reconstruire
  le site sans dev. À utiliser dès qu'on parle d'intégrer/parser une maquette ou un prototype
  Figma vers ce CMS, de générer les pages/fixtures depuis Figma, ou d'exécuter le process
  d'intégration Figma → CMS. Déclencheurs : « intègre la maquette Figma », « parse la page Figma »,
  « génère les fixtures depuis Figma », « process complet Figma », une URL figma.com/design ou /proto.
---

# Intégration Figma → CMS (SFCMS 7)

## 🛑 AU LANCEMENT — poser CES 3 questions D'ABORD (avant toute autre action)

Dès l'invocation de `/figma-cms`, **demander à l'utilisateur, et ne rien faire d'autre tant que
ce n'est pas répondu** :

1. **URL de prod ?**
2. **URL du proto (Figma) ?**
3. **Copie-colle les prompts Figma** (le design / la maquette à intégrer).

Les présenter clairement (les 3 ensemble). Ne **rien présupposer** (ni prod, ni proto, ni
node-ids) ; le fichier Figma vient de `FIGMA_FILE_KEY` (`.env`) et les pages se **découvrent**
via les calques `[page|…]`. N'enchaîner sur le playbook qu'une fois les 3 réponses obtenues
(ou leur absence actée avec l'utilisateur).

## Nomenclature de nommage = doc EN LIGNE (à charger À CHAQUE FOIS)

La **convention de nommage des calques** (quels tags écrire : `[page|…]`, `[slider|…]`, `[nav]`,
`[footer]`, `[newsletter]`, `[socialwall]`, modifiers `id:`, `|mobile`, `bg:`, `rounded`…) est la
**SEULE source de vérité** et vit **en ligne** :

👉 **https://figma-doc.agence-felix.fr/** — la **charger (WebFetch) au début de chaque intégration**
pour disposer du nommage à jour (elle évolue). **Ne pas se fier à une copie locale du nommage** ;
en cas d'écart, **le site prime**.

> **Répartition des rôles (à respecter strictement) :**
> - **Doc en ligne** = nomenclature de **nommage** (face créa) → à fetcher.
> - **Ce skill** (`integration-prompts.md` + `models/`) = **mécanique CMS / déductions / pipeline /
>   fixtures** uniquement. Ne **jamais** y dupliquer la nomenclature (risque de dérive).

## Mécanique CMS & pipeline = ce skill

**Lis et applique INTÉGRALEMENT le playbook générique du projet :**

👉 `.claude/skills/figma-cms/integration-prompts.md` (procédure complète en phases 0→8)
👉 `.claude/skills/figma-cms/models/mapping-blocktypes.md` (mapping tags ↔ mécanique CMS, BlockTypes, modules)

La convention de nommage en ligne fait foi : https://figma-doc.agence-felix.fr/ (la **re-consulter** à chaque intégration, elle évolue ; en cas d'écart, le site prime).

## Règles d'or (départ à froid — NE RIEN SUPPOSER)

- **Fichier Figma** = `FIGMA_FILE_KEY` (+ `FIGMA_TOKEN`) dans `.env`.
- **URL de prod** et **URL du prototype** : inconnues → **les DEMANDER à l'utilisateur** (jamais les présupposer ni réutiliser une mémoire d'une autre session).
- **Pages à intégrer** : **découvertes** en scannant les calques taggés `[page|…]` du fichier (jamais des node-ids supposés).
- **Aucune écriture en base ni commit git sans demande explicite** de l'utilisateur.

## Outillage : MASTER dans le skill, temporaire dans `src/`

L'outillage Figma→CMS (parser + commandes) est **conservé À JOUR dans le skill** :
`.claude/skills/figma-cms/tooling/src/` (services `Service/Figma/*` + commandes `Command/Figma/*`).
- **Phase 0 = INSTALLER depuis le skill** (copier vers le projet), **ne PAS ré-implémenter** :
  `cp -r .claude/skills/figma-cms/tooling/src/. src/`
- **Phase finale = SUPPRIMER de `src/`** une fois le site reconstruit (il ne fait pas partie du
  livrable) : `rm -rf src/Service/Figma src/Command/Figma`. **Le master reste dans le skill.**
- Si tu fais évoluer le parser, **recopier la version à jour dans `tooling/`** avant de nettoyer.

## Déroulé (cf. playbook pour le détail)

0. **Installer l'outillage depuis le skill** (`cp -r .claude/skills/figma-cms/tooling/src/. src/`) → commandes `figma:parse-page` / `figma:capture-layout` dispo. Vérifier `php -l` + un parse à blanc.
1. Kickoff : demander prod + proto ; bootstrapper `.claude/figma-cms/integration/` depuis `models/`.
2. Dry-run par page `[page|…]` (arbre + captures de bandes + médias + interactions proto).
3. Config/URLs/SEO depuis la prod → `config.json` (+ géocodage Nominatim) → `bin/data/config/default.yaml`.
4. Assets de marque (logo, favicons sur `primary`, share+logo, email-logo PNG, preloader, placeholder pastel).
5. Polices (toutes les familles, en local, `fonts.scss` + `variables.scss` + `$theme-colors` + preload + métriques fallback via capsize).
6. Base de données (`.env.local`, `DB_NAME` = projet en `_`, `doctrine:database:create` + `schema:update --force`).
7. Fixtures (pages, menus, produits/actus depuis la prod, activation modules) → `doctrine:fixtures:load`.
8. Traductions par bloc (toutes locales).
9. **Nettoyage** : `rm -rf src/Service/Figma src/Command/Figma` (le master reste dans le skill).

Suivre l'ordre, renvoyer à chaque section détaillée du playbook, et tenir le playbook à jour si la convention évolue.
