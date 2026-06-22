# Cartographie CSS natif (overriders potentiels)

```

Cartographie CSS natif — assets/scss/front/default  (350 fichiers .scss)
Overriders TOUJOURS ACTIFS (props typo/espacement/fond) :
  !important : 281   |   sélecteurs d'élément/globaux : 382   |   [class*=] : 4
  (+ 27 conditionnés par une classe d'état body/html — inactifs par défaut, cf. fin)

### 1. !important ACTIF sur propriété sensible (gagne quelle que soit la spécificité) — top 12
  assets/scss/front/default/components/blocks/_card.scss:65  font-size  { body #body-page .card-block .card .text-block * }
  assets/scss/front/default/components/blocks/_card.scss:70  padding-left  { body #body-page .card-block .card .text-block ul:not(.no-dots):not(.nav):not(.dropdown-menu):not(.reset):not(.splide__list):not(.splide__pagination):not(.pagination):not(.carousel-indicators) }
  assets/scss/front/default/components/blocks/_card.scss:73  margin-left  { body #body-page .card-block .card .text-block ul:not(.no-dots):not(.nav):not(.dropdown-menu):not(.reset):not(.splide__list):not(.splide__pagination):not(.pagination):not(.carousel-indicators) li:before }
  assets/scss/front/default/components/blocks/_card.scss:77  background-color  { body #body-page .card-block .card .text-block ul:not(.no-dots):not(.nav):not(.dropdown-menu):not(.reset):not(.splide__list):not(.splide__pagination):not(.pagination):not(.carousel-indicators) li:before }
  assets/scss/front/default/components/blocks/_card.scss:84  font-size  { body #body-page .card-block .card .link-block a, a span }
  assets/scss/front/default/components/blocks/_title-header.scss:115  background  { .product-hero-wrap .title-header-block &:after }
  assets/scss/front/default/components/blocks/_title-header.scss:131  color  { .product-hero-wrap .title-header-block .sub-title }
  assets/scss/front/default/components/form/_form.scss:18  margin  { form .layout-col > .row }
  assets/scss/front/default/components/form/_form.scss:26  font-weight  { .checkbox-group, .choice-group label }
  assets/scss/front/default/components/form/_form.scss:75  color  { form .bg-white .form-group .form-check-label }
  assets/scss/front/default/components/form/_form.scss:100  padding-left  { .form-label }
  assets/scss/front/default/components/form/_form.scss:103  color  { .form-label .label-wrap .asterisk:not(.initial):not(.btn) }
  … +269 autres

### 2. Sélecteurs d'ÉLÉMENT / globaux ACTIFS (h1-h6, p, a, body, :root, *…) — top 12
  assets/scss/front/default/components/blocks/_blockquote.scss:16  { blockquote }
  assets/scss/front/default/components/blocks/_blockquote.scss:31  { .blockquote-block i, svg * }
  assets/scss/front/default/components/blocks/_blockquote.scss:36  { .blockquote-block p }
  assets/scss/front/default/components/blocks/_card.scss:19  { .card .card-header picture, picture img, .img-hover-buttons-wrap, .img-loader-wrap }
  assets/scss/front/default/components/blocks/_card.scss:29  { .card &:not(.have-bg) .card-header picture img, .img-hover-buttons-wrap }
  assets/scss/front/default/components/blocks/_card.scss:58  { body #body-page .card-block .card }
  assets/scss/front/default/components/blocks/_card.scss:64  { body #body-page .card-block .card .text-block * }
  assets/scss/front/default/components/blocks/_card.scss:68  { body #body-page .card-block .card .text-block ul:not(.no-dots):not(.nav):not(.dropdown-menu):not(.reset):not(.splide__list):not(.splide__pagination):not(.pagination):not(.carousel-indicators) }
  assets/scss/front/default/components/blocks/_card.scss:72  { body #body-page .card-block .card .text-block ul:not(.no-dots):not(.nav):not(.dropdown-menu):not(.reset):not(.splide__list):not(.splide__pagination):not(.pagination):not(.carousel-indicators) li:before }
  assets/scss/front/default/components/blocks/_card.scss:83  { body #body-page .card-block .card .link-block a, a span }
  assets/scss/front/default/components/blocks/_media.scss:41  { body }
  assets/scss/front/default/components/blocks/_media.scss:122  { .img-hover-buttons-wrap > picture img }
  … +370 autres

### 3. Sélecteurs larges [class*=] ACTIFS
  assets/scss/front/default/components/_animate.scss:114  { .animate__animated[class*='Out'] }
  assets/scss/front/default/components/_button.scss:91  { .btn:not(.basic):not(.btn-blur) &:not(.btn-gradient):not([class*="btn-outline-"]) }
  assets/scss/front/default/vendor-desktop.scss:52  { body .btn.btn[class*="btn-outline-"] }
  assets/scss/front/default/vendor-mobile.scss:51  { body .btn.btn[class*="btn-outline-"] }

### 4. CONDITIONNÉS — n'agissent QUE si la classe d'état est posée sur body/html (inactifs par défaut)
   27 règle(s), par classe d'état :
  body.as-accessibility : 27

──────────
Priorité : traiter les overriders ACTIFS (1-3). Les conditionnés (4) ne concernent que le mode/thème
correspondant. Stratégie : pour qu'un style intégré GAGNE sans !important, le scoper par l'#id du
composant (ex. #home-hero .title — l'ID bat les classes). Sinon inspecter la règle gagnante dans le
CSS compilé / via verify-styles (qui échoue si le rendu est écrasé).
```
