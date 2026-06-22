# Design system Figma → SCSS — récap unifié (542:1592)

> Vue consolidée AVANT intégration : la **vérité nommée** du design system (palette + échelle typo)
> confrontée au **SCSS du projet** (variables existantes à réutiliser / à ajouter). Généré par
> `tooling/design-system.mjs` (orchestre figma-named-styles + reconcile-colors + reconcile-typography).

## 1. Styles nommés (palette + échelle typographique)
```
Styles nommés — 25 (9 FILL, 16 TEXT)  |  portée usage : «[page|home]»

### Palette (couleurs nommées) → variables SCSS
  $creme: #f4f0f1;  // « Crème »  ×110
  $blanc: #ffffff;  // « Blanc »  ×50
  $noir: #141414;  // « Noir »  ×33
  $gold: #b48608;  // « Gold »  ×32
  $bleu-canard: #8fb3b1;  // « Bleu Canard »  ×8
  $brun: #402624;  // « Brun »  ×8
  $bleu: #001e56;  // « Bleu »  ×6
  $vert: #00561b;  // « Vert »  ×5
  $noir: (non résolu);  // « Noir »  (non utilisé ici)

### Échelle typographique (styles texte nommés)
  — Desktop —
  « Sous-titre H1 » = 133.2px / 400 / August Script Bold free / lh 101  (non utilisé ici)
  « Sous-titre H2 » = 96px / 400 / August Script Bold free / lh 101  (non utilisé ici)
  « Sous-titre H3 » = 54px / 400 / August Script Bold free / lh 101  ×15
  « H2 » = 32px / 700 / Museo Sans / tracking 12.8 / lh 38 / UPPER  (non utilisé ici)
  « H3 » = 24px / 700 / Museo Sans / lh 24 / UPPER  ×15
  « p/16 » = 16px / 300 / Museo Sans / lh 20  ×4
  « Citaion » = 16px / 700 / Museo Sans / tracking 6.4 / lh 19 / UPPER  (non utilisé ici)
  « H4 » = 14px / 700 / Museo Sans / tracking 2.8 / lh 17 / UPPER  ×5
  « H1 » = (non résolu)  (non utilisé ici)
  — Mobile —
  « Sous-titre H1 Mobile » = 80px / 400 / August Script Bold free / lh 139  (non utilisé ici)
  « Sous-titre H2 Mobile » = 64px / 400 / August Script Bold free / lh 101  (non utilisé ici)
  « Sous-titre H3 Mobile » = 54px / 400 / August Script Bold free / lh 101  (non utilisé ici)
  « H1 Mobile » = 32px / 700 / Museo Sans / tracking 12.8 / lh 38 / UPPER  (non utilisé ici)
  « H3 Mobile » = 24px / 700 / Museo Sans / lh 24 / UPPER  (non utilisé ici)
  « H2 Mobile » = 24px / 700 / Museo Sans / tracking 9.6 / lh 29 / UPPER  (non utilisé ici)
  « H4 Mobile » = 14px / 700 / Museo Sans / tracking 2.8 / lh 17 / UPPER  (non utilisé ici)

Non résolus (aucun nœud référent trouvé) : Noir, H1

──────────
Usage : reporter la palette dans variables.scss ($creme, $gold…) et caler l'échelle de titres
($font-size-h2/h3…) sur les styles « Hn » ; les « Sous-titre Hn » = polices script décoratives
(classe dédiée). Ces NOMS expliquent les « orphelins » de reconcile-typography (54px = Sous-titre H3).
```

## 2. Couleurs de la page → palette nommée + variables SCSS
```
Couleurs de la page : 10 hex distincts  |  palette nommée : 8  |  vars SCSS : 23  |  tol 8/canal

✓ #f4f0f1  ×110  «[page|home]»  → « Crème » $creme  [SCSS $beige]
✓ #ffffff  ×104  «Component 10»  → « Blanc » $blanc  [SCSS $gray-100]
✓ #141414  ×33   «Rectangle 28»  → « Noir » $noir  [SCSS $dark]
✓ #b48608  ×32   «l’exposition de Huang Xiaoli»  → « Gold » $gold  [SCSS $primary]
✓ #8fb3b1  ×8    «Votre parenthèse»  → « Bleu Canard » $bleu-canard  [SCSS $teal]
✓ #402624  ×8    «Rectangle 28»  → « Brun » $brun  [SCSS $brown]
✓ #001e56  ×6    «Rectangle 30»  → « Bleu » $bleu  [SCSS $secondary]
✗ ANONYME #000000  ×6    «Parister-13 4»  hors palette nommée & SCSS — one-off / couleur d'image / à nommer
✓ #00561b  ×5    «Les Passerelles»  → « Vert » $vert  [SCSS $success]
✓ #383d38  ×2    «Vector»  → $gray-800 (SCSS, hors palette nommée) Δ4

──────────
Palette nommée : 8  |  SCSS seul : 1  |  anonymes : 1
ANONYMES (hors palette) — à arbitrer :
  #000000 ×6 «Parister-13 4»
Reconciliation couleurs : OK
```

## 3. Typographie de la page → échelle SCSS (annotée des styles nommés)
```
Échelle SCSS (assets/scss/front/default/variables.scss) : 16px, 18px, 25px, 30px, 32px, 35px, 48px, 60px, 64px, 80px, 98px
Tailles Figma sur la page : 13  |  tolérance 1.5px  |  styles nommés : 9 tailles

✗ ORPHELINE 528.2px  ×2    «parister»  aucun slot (≤1.5px) — plus proche : 98px (.fz-xxl, Δ430.2px)
✗ ORPHELINE 416.7px  ×1    «workspaces»  aucun slot (≤1.5px) — plus proche : 98px (.fz-xxl, Δ318.7px)
◆ STYLE NOMMÉ 133.2px  ×1    «Parister»  « Sous-titre H1 » — hors échelle SCSS → variable/classe dédiée (intentionnel, pas du bruit)
◆ STYLE NOMMÉ    96px  ×5    «parisienne»  « Sous-titre H2 » — hors échelle SCSS → variable/classe dédiée (intentionnel, pas du bruit)
◆ STYLE NOMMÉ    54px  ×15   «supérieure»  « Sous-titre H3 / Sous-titre H3 Mobile » — hors échelle SCSS → variable/classe dédiée (intentionnel, pas du bruit)
✗ ORPHELINE  38.4px  ×1    «boutique hôtel & spa»  aucun slot (≤1.5px) — plus proche : 35px (h2/.card-title, Δ3.4px)
✓    32px  ×5    «Votre parenthèse»  → .fz-sm  ← « H2 / H1 Mobile »
✓    26px  ×1    «4,7/5»  → h3  (Δ1.0px)
✓    24px  ×19   «derniers événements»  → h3  (Δ1.0px)  ← « H3 / H3 Mobile / H2 Mobile »
✓    16px  ×17   «Chambres, suites, offres et disp»  → base / p / .fz-xs  ← « p/16 / Citaion »
◆ STYLE NOMMÉ    14px  ×52   «Découvrir les chambres»  « H4 / H4 Mobile » — hors échelle SCSS → variable/classe dédiée (intentionnel, pas du bruit)
✗ ORPHELINE    13px  ×1    «J’accepte que la société Pariste»  aucun slot (≤1.5px) — plus proche : 16px (base / p/.fz-xs, Δ3.0px)
✗ ORPHELINE    12px  ×5    «19 Rue Saulnier, 75009 Paris +33»  aucun slot (≤1.5px) — plus proche : 16px (base / p/.fz-xs, Δ4.0px)

──────────
Mappées échelle : 4/13  |  styles nommés hors échelle : 4  |  orphelines anonymes : 5
STYLES NOMMÉS hors échelle → classe/variable DÉDIÉE (reprendre le nom Figma) :
  • 133.2px « Sous-titre H1 » → ex. `.fz-sous-titre-h1` { @include rfs(133.2px); } (ou $font-size-sous-titre-h1).
  • 96px « Sous-titre H2 » → ex. `.fz-sous-titre-h2` { @include rfs(96px); } (ou $font-size-sous-titre-h2).
  • 54px « Sous-titre H3 / Sous-titre H3 Mobile » → ex. `.fz-sous-titre-h3` { @include rfs(54px); } (ou $font-size-sous-titre-h3).
  • 14px « H4 / H4 Mobile » → ex. `.fz-h4` { @include rfs(14px); } (ou $font-size-h4).
ORPHELINES anonymes (pas de style nommé) — à arbitrer/ajouter :
  • 528.2px (×2, «parister») → `'528': ('rfs': true, 'size': 528.2px, …)` dans $font-sizes-app (.fz-528) ou variable dédiée.
  • 416.7px (×1, «workspaces») → `'417': ('rfs': true, 'size': 416.7px, …)` dans $font-sizes-app (.fz-417) ou variable dédiée.
  • 38.4px (×1, «boutique hôtel & spa») → `'38': ('rfs': true, 'size': 38.4px, …)` dans $font-sizes-app (.fz-38) ou variable dédiée.
  • 13px (×1, «J’accepte que la société Pariste») → `'13': ('rfs': true, 'size': 13px, …)` dans $font-sizes-app (.fz-13) ou variable dédiée.
  • 12px (×5, «19 Rue Saulnier, 75009 Paris +33») → `'12': ('rfs': true, 'size': 12px, …)` dans $font-sizes-app (.fz-12) ou variable dédiée.
Reconciliation typo : OK
```

## À reporter dans variables.scss
- **Couleurs** : réutiliser les variables SCSS pointées (✓) ; ajouter celles listées « À AJOUTER ».
- **Titres** : caler `$font-size-h*` sur les styles « Hn » ; créer une **classe dédiée** pour les
  styles nommés hors échelle (« Sous-titre Hn »…). Arbitrer les **orphelins anonymes** (one-off).
- **Espacements** : `reconcile-margins.mjs` (échelle `$margins`) — complément de ce récap.
