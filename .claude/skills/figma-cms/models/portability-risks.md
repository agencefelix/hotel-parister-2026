# Risques de portabilité — mécanique Figma → CMS

> Fichier **vivant** : à compléter au fil du dev du module Figma. Recense ce qui
> pourrait **poser souci en réutilisant cette mécanique sur un autre projet** (autre
> maquette, autre site de prod, autre instance SFCMS). Chaque point = symptôme +
> parade.

## 1. Catalogue CMS dépendant de la base du projet

- **Souci** : BlockTypes / Actions / Modules sont lus **en base** (`cms_layout_block_type`,
  `cms_layout_action`, `cms_core_module`). Les slugs/rôles varient selon la version
  SFCMS et la config du site. `model/cms-catalog.json` reflète **ce projet**, pas une
  vérité universelle.
- **Parade** : **re-extraire le catalogue par projet** (`php bin/console dbal:run-sql`)
  avant tout mapping ; ne jamais réutiliser `cms-catalog.json` tel quel.

## 2. Module référencé sans exister (ex. mur social)

- **Souci** : `ROLE_SOCIAL_WALL` est listé dans `WebsiteFixtures::OTHERS_MODULES`
  mais **aucun `Module` ni `Action`** ne lui correspond en base. Croire qu'un module
  existe parce qu'un rôle est cité mène à activer du vide.
- **Parade** : valider l'existence réelle (Module + Action) en base avant d'annoncer
  un module. Pour le mur social ici : blocType `social-networks` (global) ou config,
  pas un module. Un feed = dev spécifique.

## 3. Modules non actifs par défaut

- **Souci** : la répartition `DEFAULTS_MODULES` vs `OTHERS_MODULES` (WebsiteFixtures)
  décide ce qui est actif. Elle **diffère par projet**. Newsletter ici = OTHERS (à activer).
- **Parade** : ne jamais supposer qu'un module est actif ; vérifier et activer si besoin.

## 4. API REST Figma — cache / lag

- **Souci** : `lastModified` peut rester en retard de plusieurs minutes ; les **tags
  récents ne remontent pas immédiatement** (observé : tags posés à 16h+ invisibles,
  API bloquée à 15:54). Risque de conclure « rien n'a changé ».
- **Parade** : vérifier `version`/`lastModified` ; attendre / forcer un bump (sauvegarde
  Figma) ; prévenir l'utilisateur quand l'API sert un instantané périmé.

## 5. API REST Figma — `destinationId` d'OVERLAY souvent `null`

- **Souci** : pour une action `OVERLAY`, l'API ne donne pas toujours la frame cible
  (`destinationId: null`). Ici inféré car **une seule** frame sur la page « Overlay ».
  Avec plusieurs overlays, l'inférence ne tient plus.
- **Parade** : lister les frames d'overlay, et si ambiguïté, demander/recouper
  visuellement. Documenter l'inférence comme telle.

## 6. Interactions portées par des instances / variantes de composants

- **Souci** : les hovers pointent vers des variantes (`Propriété 1=Variante2`) de
  composants **hors-frame** ; les overlays sont définis sur le **composant source**,
  pas l'instance. Lire l'instance seule ne suffit pas.
- **Parade** : résoudre les composants sources (id après `;` dans `I…;…`) et les
  component sets pour nommer les états.

## 7. Scope du token Figma (lecture seule)

- **Souci** : `FIGMA_TOKEN` en `file_content:read` → `/v1/me` en 403, **aucune
  écriture** vers Figma. Suffisant pour Figma→code, bloquant pour code→Figma.
- **Parade** : pour écrire vers Figma, basculer sur le MCP Figma (OAuth, navigateur).

## 8. hreflang de prod incohérents

- **Souci** : doublons, back-links vers des pages **hors sitemap** (copies mortes),
  déclarations une-direction. L'appariement naïf sur-fusionne.
- **Parade** : modèle adopté = back-link de la cible prioritaire, sinon déclaration fr
  unique ; signaler les anomalies. ⚠️ Cas **non encore gérés** : `x-default`, variantes
  régionales (`fr-FR`/`fr-CA`), domaines partagés. À renforcer si un autre site les utilise.

## 9. Sitemaps incomplets → crawl de découverte

- **Souci** : sitemaps étrangers très partiels (6 URLs vs 27 réelles). Le crawl BFS a
  un garde-fou (`MAX=400`) et des **filtres d'exclusion spécifiques** (`/404`, `/direct/`,
  assets). Un autre site peut exiger d'autres filtres (pagination, facettes, params de
  tracking) ou dépasser le plafond.
- **Parade** : adapter les filtres et le plafond par site ; toujours `log()` ce qui est
  tronqué/exclu.

## 10. Maquette non balisée / à plat (mode dégradé)

- **Souci** : sans tags `[zone]`/`[col]`, la structure est **déduite** de la géométrie
  (±1 zone), et des éléments de layout (newsletter, mur social) posés à plat sont pris
  pour des sections de page.
- **Parade** : capturer en HD avant d'interpréter ; détecter les éléments de layout par
  contenu ; **inviter le créa à baliser**. Un `[zone]`/`[col]` explicite lève l'ambiguïté.

## 11. Couleur de fond des zones — pièges de détection

- **Souci** : (a) un fond ne commence pas forcément au sommet de la bande — un même
  rectangle peut **couvrir 2 bandes** (navy/teal) ; (b) les fills de nœuds **TEXT**
  (filigrane) sont pris à tort pour un fond (faux `#ffffff`/`#b48608` observés) ;
  (c) un overlay/une image par-dessus une couleur trompe la géométrie.
- **Parade (implémentée)** : fond = candidat full-width qui **couvre le centre** de la
  bande, **hors TEXT/LINE/VECTOR**, SOLID/GRADIENT sinon repli fond de page, IMAGE→null.
  **Toujours croiser avec la capture**. Reste fragile si le fond est un calque non
  full-width, un masque ou un blend mode : vérifier visuellement.

## 12. Scripts de crawl ad hoc (hors application)

- **Souci** : la découverte d'URLs, l'appariement hreflang et la cartographie proto ont
  été faits par **scripts scratchpad** (curl direct), hors `FigmaApiClient`. Non
  réexécutables tels quels, et le crawl de prod sort du périmètre du service Figma.
- **Parade** : si réutilisation récurrente, **wirer en commandes** (`figma:*`) et faire
  passer les lectures Figma par `FigmaApiClient` (la règle « interdit curl brut » vise
  le code applicatif durable).
