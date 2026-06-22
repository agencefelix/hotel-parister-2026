# DEFINITION OF DONE — checklist BLOQUANTE par élément (à exécuter, pas à survoler)

> Un élément (bande, nav, footer, bouton, carte…) n'est **PAS** « fait » tant que CHAQUE case ci-dessous
> n'est pas cochée **avec son artefact**. Interdit d'annoncer « conforme/fait/95 % » sans ces preuves.
> Objectif : ISO ≥ 95 % **dès le 1er jet**, sans correction de l'utilisateur. Si une case manque → pas fini.

## Pour CHAQUE élément, dans l'ordre :

1. **[ ] Tokens relevés** — exécuter `tooling/figma-tokens.py <node>` (ou lire `figma-tokens.<page>.json`)
   et **noter** : couleur(s) hex, `fontSize`, `fontWeight`, `letterSpacing`, `lineHeight`, `textCase`,
   marges/paddings/gap. ⟶ artefact : la liste des valeurs citées dans le message.
2. **[ ] Référence maquette exportée** — image du node (Figma `/v1/images`) pour comparer.
3. **[ ] Intégration** — appliquer EXACTEMENT ces valeurs (fixtures + SCSS). Pour un élément de **layout**,
   **réécrire le fichier proprement** (pas d'overrides empilés) ; **un composant = son fichier**.
4. **[ ] Build** — `yarn build` (exit 0) ; regen si fixtures touchées.
5. **[ ] Capture Chrome** — `tooling/capture.mjs` ; états **repos / scroll / hover / ouvert** via vraies
   interactions (`mouse.wheel`, `click`, `mouse.move`). ⟶ artefact : la/les PNG.
6. **[ ] GATE styles AUTOMATIQUE (BLOQUANT)** — lancer la boucle fermée qui **mesure le rendu
   (`getComputedStyle`) et le confronte aux tokens Figma**, puis **échoue (exit 1)** en cas d'écart :
   ```
   node .claude/skills/figma-cms/tooling/verify-styles.mjs <url> .claude/skills/figma-cms/integration/figma-tokens.<page>.json [--width 1440]
   ```
   Vérifie `font-size`, `font-weight`, `letter-spacing`, `line-height`, `text-transform`, `color`
   (appariement par texte ; `--map <map.json>` pour forcer un sélecteur ; `--out report.json`).
   ⟶ artefact : la sortie du script. **La case n'est cochée que si `GATE STYLES : OK` (exit 0).**
   Compléter par `getComputedStyle` manuel sur `::before`/`::after` (non couverts par le script).
7. **[ ] Contraintes numériques** — vérifier les exigences chiffrées (ex. nav ≤ 10dvh, mega-menu
   `scrollHeight ≤ innerHeight`, logo `centerX == innerWidth/2`). ⟶ artefact : les nombres.
8. **[ ] Comparaison ZOOMÉE côte à côte** maquette ↔ rendu (crop + `-resize 2x`), bande par bande.
   Lister chaque écart restant (structure, couleur, taille, poids, casse, marge, overlay, alignement).
9. **[ ] Itérer** jusqu'à ce que le **GATE styles sorte 0** ET ≤ 5 % d'écart visuel. Sinon, corriger le
   SCSS/les fixtures et relancer (build → gate → compara). Ne jamais annoncer « fait » gate au rouge.
10. **[ ] Responsive** — refaire 5→9 à plusieurs largeurs (≥320, 375, 768, 992, 1440…), en **relançant
   le GATE styles par largeur** (`--width 375`, `--width 768`…) puisque les tailles sont responsive (RFS).

## ⚠️ Sur un élément, VÉRIFIER TOUT (pas un échantillon)
Quand on contrôle un élément (nav, footer, bande…), **mesurer TOUS ses sous-éléments et TOUS ses états**,
pas un seul : nav = barre fermée (top + scroll), mega-menu ouvert (✕/menu, logo, liens topbar, **switcher
+ chaque langue active/inactive**, **titres de colonne ET chaque lien**, CTA, **socials**, **adresse/tél/
email**, logo Forstyle), desktop **ET** mobile. Mesurer **l'élément réellement VISIBLE au point** (un
parent peut être recouvert par un autre) via `elementFromPoint`, pas seulement le sélecteur supposé.
Lister chaque sous-élément contrôlé avec sa valeur mesurée. Ne jamais conclure sur un échantillon.

## Anti-patterns qui ont coûté des heures (NE PLUS refaire)
- Approximer une couleur/taille « à l'œil » au lieu de relever le token. → étape 1.
- Juger sur une **vignette** globale → conclure « fidèle » à tort. → étape 8 (zoom).
- Empiler des `!important` qui **perdent** quand même (spécificité/ordre) sur le CSS de base. → étape 3
  (réécrire le layout) + inspecter le **CSS compilé** pour trouver la règle gagnante.
- **Surestimer** le % / annoncer « fait » sans mesure. → étapes 6-9.
- Mettre le CSS d'un composant dans le fichier d'un autre. → « un composant = son fichier ».
- Oublier les **vraies interactions** (scroll/hover/open) → l'état n'est pas testé. → étape 5.
