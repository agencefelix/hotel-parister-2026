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
disable-model-invocation: true
allowed-tools:
  - "Bash(cp -r .claude/skills/figma-cms/tooling/src/. src/)"
  - "Bash(php -l *)"
  - "Bash(php bin/console figma:parse-page *)"
  - "Bash(php bin/console figma:capture-layout *)"
  - "Bash(php bin/console cache:clear*)"
  - "Bash(php bin/console doctrine:*)"
  - "Bash(php bin/console dbal:run-sql *)"
  - "Bash(php bin/console fos:js-routing:dump*)"
  - "Bash(rm -rf src/Service/Figma src/Command/Figma)"
  - "Bash(rm -f .claude/skills/figma-cms/integration/media/*)"
  - "Bash(node .claude/skills/figma-cms/tooling/*.mjs *)"
  - "WebFetch(domain:figma-doc.agence-felix.fr)"
---

# Intégration Figma → CMS (SFCMS 7)

## 🎯 CONTRAT QUALITÉ (non négociable — lire EN PREMIER)

L'objectif est un rendu **ISO maquette ≥ 95 %**, **dès le premier jet**, **sans que l'utilisateur ait
à corriger**. Il fournit déjà tout (prompts, captures, node Figma, prod) : à moi d'en tirer un rendu
fidèle, en autonomie. Règles **impératives** (détail dans `integration-prompts.md`) :

1. **RELEVER les vraies valeurs en dev mode Figma, jamais approximer** : couleurs (`fills`→hex),
   `fontSize`, `fontWeight`, `letterSpacing`, `lineHeightPx`, `textCase`, **marges/paddings**. Outils :
   `tooling/figma-tokens.py` + `tooling/figma-export-tokens.py` → `integration/figma-styles.md` (résumé)
   + `integration/figma-tokens.<page>.json` (exhaustif). Les **consulter avant de styler**. Puis
   `tooling/reconcile-typography.mjs integration/figma-tokens.<page>.json` pour **caler l'échelle SCSS**
   (tailles orphelines → ajouter une `.fz-*`/variable AVANT d'intégrer, sinon snap silencieux sur 16px).
2. **VÉRIFIER sur Chrome, en MESURANT** (pas « à l'œil ») : **GATE styles automatique**
   `tooling/verify-styles.mjs <url> integration/figma-tokens.<page>.json` qui mesure `getComputedStyle`
   et **échoue (exit 1)** si `font-size`/`font-weight`/`letter-spacing`/`line-height`/`text-transform`/
   `color` (TEXT) **ou les `padding` des conteneurs** divergent des tokens — **relancer par largeur**
   (`--width`) pour le responsive. Compléter avec
   `tooling/capture.mjs` (captures, états repos/scroll/hover/ouvert via **vraies interactions**
   `mouse.wheel`/`click`/`mouse.move`), `getComputedStyle` manuel des `::before`/`::after`, contraintes
   numériques (hauteurs, fit 100dvh), **comparaison ZOOMÉE bande par bande**. Itérer **jusqu'à GATE au vert**.
3. **NE JAMAIS surestimer ni annoncer « fidèle » sans preuve** : un % ne s'annonce qu'**après**
   re-vérification élément par élément, captures à l'appui. En cas de doute, annoncer plus bas.
4. **Éléments de LAYOUT (nav, footer, newsletter, socialwall…) : RÉÉCRIRE le CSS proprement** (ne pas
   empiler des overrides `!important` sur le CSS de base → conflits) ; supprimer le code mort
   (`@if($flag:false)`). **Un composant = SON fichier** (`_navigation.scss`, `_footer.scss`,
   `form/_newsletter.scss`…), jamais de CSS d'un composant dans le fichier d'un autre.
5. **AUTONOMIE** : exécuter la boucle (tokens → intégration → build → capture Chrome → mesure → itère)
   **systématiquement, sans qu'on le demande**, pour chaque page ET chaque élément (desktop + mobile).
6. **Boutons** : via `$buttons` (`variables.scss`) + `_button.scss`/`_mixin-button.scss` ; ne pas
   préfixer `btn` dans les fixtures (le template l'ajoute). **`[section]` Figma = une ZONE CMS (1:1)**.

> Le **maître mot est RIGUEUR**. Mesurer, prouver, ne rien laisser au hasard, enrichir le playbook
> (`integration-prompts.md`) au fil des découvertes (en restant générique, sans référence projet).

### ✅ CHECKLIST BLOQUANTE par élément — `models/element-dod.md` (À EXÉCUTER)
Pour CHAQUE élément (bande, nav, footer, bouton, carte… desktop ET mobile), **dérouler la Definition
of Done** de `.claude/skills/figma-cms/models/element-dod.md` : tokens relevés → intégration → build →
**capture Chrome + mesure computed styles + contraintes chiffrées + comparaison zoomée** → itérer.
**Aucun élément n'est « fait » sans ces artefacts.** C'est le garde-fou qui empêche d'approximer, de
juger sur vignette, d'empiler des overrides et de surestimer — les erreurs qui ont coûté des heures.

> 🚧 **GATE — AVANT tout rapport ou toute question** : la DoD doit être **entièrement déroulée** pour
> l'élément concerné, **comparaison côte à côte + diff incluse** (`tooling/compare.sh <maquette> <rendu>
> <out>` → `-side.png` + `-diff.png`, à MÊME largeur). **Faire cette compara SYSTÉMATIQUEMENT** quand elle
> est utile, sans qu'on le demande. Ne pas répondre/rapporter « conforme » ni poser de question avant.
> Et **croire la MESURE (`getComputedStyle`) plutôt que l'œil sur une image rétrécie** (le downscale fait
> paraître l'or sombre, etc.).

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

## 🚧 GATE — VALIDATION DES SCREENSHOTS avant toute intégration (BLOQUANT)

Une fois le dry-run d'une page terminé (parse → `pages/<slug>.json` + captures de bandes dans
`screenshots/<slug>/`), **NE PAS attaquer l'intégration** (fixtures + front) tant que l'utilisateur
n'a pas **vérifié et validé les screenshots** de cette page. Demander explicitement, mot pour mot :

> **« Veuillez vérifier et valider les screenshots pour continuer l'intégration. »**

Règles **impératives** :
- **Validation PAR PAGE.** Chaque page taggée `[page|…]` se valide indépendamment via ses captures
  `screenshots/<slug>/`. On n'intègre **que** les pages dont les screenshots sont **validés**.
- **Pas de validation = pas d'intégration.** Si les screenshots d'une page ne sont **pas** validés
  (ou non encore revus), **ne pas aller plus loin sur cette page** : ni fixtures, ni front. On reste
  bloqué sur cette page jusqu'à validation.
- Exemple : si `[page|home]` est validée mais `[page|cms]` ne l'est pas → on intègre la home,
  **on ne touche pas** à la page `cms`.
- Présenter clairement à l'utilisateur **où regarder** (le dossier `screenshots/<slug>/` et la liste
  des bandes) pour qu'il puisse valider en connaissance de cause. Attendre sa réponse avant d'enchaîner.

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
1. Kickoff : demander prod + proto ; bootstrapper `.claude/skills/figma-cms/integration/` depuis `models/`.
2. Dry-run par page `[page|…]` (arbre + captures de bandes + médias + interactions proto).
3. Config/URLs/SEO depuis la prod → `config.json` (+ géocodage Nominatim) → `bin/data/config/default.yaml`.
4. Assets de marque (logo, favicons sur `primary`, share+logo, email-logo PNG, preloader, placeholder pastel).
5. Polices (toutes les familles, en local, `fonts.scss` + `variables.scss` + `$theme-colors` + preload + métriques fallback via capsize).
6. Base de données (`.env.local`, `DB_NAME` = projet en `_`, `doctrine:database:create` + `schema:update --force`).
7. Fixtures (pages, menus, produits/actus depuis la prod, activation modules) → `doctrine:fixtures:load`.
8. Traductions par bloc (toutes locales).
9. **Nettoyage** : `rm -rf src/Service/Figma src/Command/Figma` (le master reste dans le skill).

Suivre l'ordre, renvoyer à chaque section détaillée du playbook, et tenir le playbook à jour si la convention évolue.
