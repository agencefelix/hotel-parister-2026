# Cartographie CSS natif (overriders potentiels)

```

Cartographie CSS natif — assets/scss/front/default  (350 fichiers .scss)
Overriders TOUJOURS ACTIFS (props typo/espacement/fond) :
  !important : 281   |   sélecteurs d'élément/globaux : 382   |   [class*=] : 4
  (+ 27 conditionnés par une classe d'état body/html — inactifs par défaut, cf. fin)

### 1. !important ACTIF sur propriété sensible (gagne quelle que soit la spécificité) — top 60
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
  assets/scss/front/default/components/form/_form.scss:112  color  { :not(.form-floating) .form-label span }
  assets/scss/front/default/components/form/_form.scss:118  font-weight  { .form-floating .form-label }
  assets/scss/front/default/components/form/_form.scss:119  color  { .form-floating .form-label }
  assets/scss/front/default/components/form/_form.scss:122  background-color  { .form-floating .form-label span }
  assets/scss/front/default/components/form/_form.scss:123  color  { .form-floating .form-label span }
  assets/scss/front/default/components/form/_form.scss:124  line-height  { .form-floating .form-label span }
  assets/scss/front/default/components/form/_form.scss:158  background-color  { input.form-control, input.form-control:focus, select.form-select, select.form-select:focus, textarea.form-control, textarea.form-control:focus }
  assets/scss/front/default/components/form/_form.scss:160  color  { input.form-control, input.form-control:focus, select.form-select, select.form-select:focus, textarea.form-control, textarea.form-control:focus }
  assets/scss/front/default/components/form/_form.scss:161  padding-left  { input.form-control, input.form-control:focus, select.form-select, select.form-select:focus, textarea.form-control, textarea.form-control:focus }
  assets/scss/front/default/components/form/_form.scss:162  padding-right  { input.form-control, input.form-control:focus, select.form-select, select.form-select:focus, textarea.form-control, textarea.form-control:focus }
  assets/scss/front/default/components/form/_form.scss:178  padding-right  { input[type="date"] }
  assets/scss/front/default/components/form/_form.scss:199  background-color  { .form-control[type="file"] &::file-selector-button }
  assets/scss/front/default/components/form/_form.scss:200  color  { .form-control[type="file"] &::file-selector-button }
  assets/scss/front/default/components/form/_form.scss:212  background-color  { .form-control[type="file"] &::file-selector-button }
  assets/scss/front/default/components/form/_form.scss:219  background-color  { .form-control:hover:not(:disabled):not([readonly])::file-selector-button }
  assets/scss/front/default/components/form/_form.scss:220  color  { .form-control:hover:not(:disabled):not([readonly])::file-selector-button }
  assets/scss/front/default/components/form/_form.scss:264  padding  { .file-group.as-btn label.form-label }
  assets/scss/front/default/components/form/_form.scss:295  padding  { form *[type="submit"], .btn }
  assets/scss/front/default/components/form/_form.scss:299  margin  { form .zone-container > .row }
  assets/scss/front/default/components/form/_form.scss:309  padding  { form .clear-wrap }
  assets/scss/front/default/components/form/_form.scss:332  padding-left  { fieldset > div.form-check }
  assets/scss/front/default/components/form/_form.scss:377  margin-top  { .form-check-input }
  assets/scss/front/default/components/form/_form.scss:386  color  { .invalid-feedback }
  assets/scss/front/default/components/form/_form.scss:405  padding-left  { .choices[data-type="select-one"] .choices__list--dropdown }
  assets/scss/front/default/components/form/_form.scss:406  padding-right  { .choices[data-type="select-one"] .choices__list--dropdown }
  assets/scss/front/default/components/form/_form.scss:419  background-color  { .choices__inner }
  assets/scss/front/default/components/form/_form.scss:420  padding-left  { .choices__inner }
  assets/scss/front/default/components/form/_form.scss:421  padding-right  { .choices__inner }
  assets/scss/front/default/components/form/_form.scss:427  color  { .choices__placeholder, .choices__item--selectable }
  assets/scss/front/default/components/form/_form.scss:440  color  { .choices__item--selectable }
  assets/scss/front/default/components/form/_form.scss:449  background-color  { .choices__input }
  assets/scss/front/default/components/form/_form.scss:451  line-height  { .choices__input }
  assets/scss/front/default/components/form/_form.scss:452  padding-top  { .choices__input }
  assets/scss/front/default/components/form/_form.scss:453  padding-left  { .choices__input }
  assets/scss/front/default/components/form/_form.scss:454  padding-bottom  { .choices__input }
  assets/scss/front/default/components/form/_form.scss:455  margin-bottom  { .choices__input }
  assets/scss/front/default/components/form/_form.scss:456  font-size  { .choices__input }
  assets/scss/front/default/components/form/_form.scss:461  padding-bottom  { .choices__list }
  assets/scss/front/default/components/form/_form.scss:466  color  { .choices__input, .choices__list--dropdown .choices__item }
  assets/scss/front/default/components/form/_form.scss:467  font-size  { .choices__input, .choices__list--dropdown .choices__item }
  assets/scss/front/default/components/form/_form.scss:468  font-weight  { .choices__input, .choices__list--dropdown .choices__item }
  assets/scss/front/default/components/form/_form.scss:472  background-color  { .choices__list--dropdown .choices__item }
  assets/scss/front/default/components/form/_form.scss:476  background-color  { .choices__list--dropdown }
  assets/scss/front/default/components/form/_form.scss:478  padding-left  { .choices__list--dropdown }
  assets/scss/front/default/components/form/_form.scss:479  padding-right  { .choices__list--dropdown }
  assets/scss/front/default/components/form/_form.scss:576  color  { #gdpr-job * }
  assets/scss/front/default/components/form/_form.scss:602  color  { .bg-primary #gdpr-job * }
  assets/scss/front/default/components/form/_form.scss:631  color  { .form-select:not(.selected), .form-floating > .form-control::placeholder, .form-floating > .form-control-plaintext::placeholder, .form-floating > .input-group > .form-control::placeholder }
  … +221 autres

### 2. Sélecteurs d'ÉLÉMENT / globaux ACTIFS (h1-h6, p, a, body, :root, *…) — top 60
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
  assets/scss/front/default/components/blocks/_media.scss:167  { .img-hover-buttons-wrap .inner img, i }
  assets/scss/front/default/components/blocks/_media.scss:179  { .img-hover-buttons-wrap &:hover, &.focused-el > picture img }
  assets/scss/front/default/components/blocks/_media.scss:218  { .media-block &.have-pictogram .pictogram-wrap img }
  assets/scss/front/default/components/blocks/_media.scss:232  { .media-block &.have-pictogram-top .pictogram-container top: calc(-1 * # }
  assets/scss/front/default/components/blocks/_media.scss:238  { .media-block &.have-pictogram-bottom .pictogram-container bottom: calc(-1 * # }
  assets/scss/front/default/components/blocks/_media.scss:247  { .media-block &.have-pictogram-start .pictogram-container left: calc(-1 * # }
  assets/scss/front/default/components/blocks/_media.scss:253  { .media-block &.have-pictogram-end .pictogram-container right: calc(-1 * # }
  assets/scss/front/default/components/blocks/_media.scss:281  { .media-block &.have-media-secondary &.have-media-secondary-start .media-secondary-container left: calc(-1 * # }
  assets/scss/front/default/components/blocks/_media.scss:290  { .media-block &.have-media-secondary &.have-media-secondary-end .media-secondary-container right: calc(-1 * # }
  assets/scss/front/default/components/blocks/_media.scss:301  { .media-block &.have-media-secondary .media-secondary-container img }
  assets/scss/front/default/components/blocks/_modal.scss:36  { .basicLightbox .modal-dialog img }
  assets/scss/front/default/components/blocks/_modal.scss:41  { .basicLightbox small }
  assets/scss/front/default/components/blocks/_title-header.scss:74  { .title-header-block .media-content img, picture }
  assets/scss/front/default/components/blocks/_video.scss:91  { .video-block-html .overlay-video img, picture }
  assets/scss/front/default/components/blocks/_video.scss:96  { .video-block-html .overlay-video img }
  assets/scss/front/default/components/blocks/_video.scss:157  { .video-block.radius picture > img, .embed-youtube }
  assets/scss/front/default/components/blocks/_video.scss:170  { .embed-youtube img }
  assets/scss/front/default/components/blocks/_video.scss:185  { .embed-youtube img, .embed-youtube .embed-youtube-play }
  assets/scss/front/default/components/blocks/_video.scss:191  { .embed-youtube img, .embed-youtube iframe, .embed-youtube .embed-youtube-play }
  assets/scss/front/default/components/form/_flatpickr.scss:278  { .numInputWrapper input, .numInputWrapper span }
  assets/scss/front/default/components/form/_flatpickr.scss:282  { .numInputWrapper input }
  assets/scss/front/default/components/form/_flatpickr.scss:286  { .numInputWrapper input::-ms-clear }
  assets/scss/front/default/components/form/_flatpickr.scss:291  { .numInputWrapper input::-webkit-outer-spin-button, .numInputWrapper input::-webkit-inner-spin-button }
  assets/scss/front/default/components/form/_flatpickr.scss:296  { .numInputWrapper span }
  assets/scss/front/default/components/form/_flatpickr.scss:310  { .numInputWrapper span:hover }
  assets/scss/front/default/components/form/_flatpickr.scss:314  { .numInputWrapper span:active }
  assets/scss/front/default/components/form/_flatpickr.scss:318  { .numInputWrapper span:after }
  assets/scss/front/default/components/form/_flatpickr.scss:347  { .numInputWrapper span svg }
  assets/scss/front/default/components/form/_flatpickr.scss:352  { .numInputWrapper span svg path }
  assets/scss/front/default/components/form/_flatpickr.scss:360  { .numInputWrapper:hover span }
  assets/scss/front/default/components/form/_flatpickr.scss:815  { .flatpickr-time input }
  assets/scss/front/default/components/form/_flatpickr.scss:845  { .flatpickr-time input:focus }
  assets/scss/front/default/components/form/_flatpickr.scss:878  { .flatpickr-time input:hover, .flatpickr-time .flatpickr-am-pm:hover, .flatpickr-time input:focus, .flatpickr-time .flatpickr-am-pm:focus }
  assets/scss/front/default/components/form/_form.scss:25  { .checkbox-group, .choice-group label }
  assets/scss/front/default/components/form/_form.scss:29  { .checkbox-group, .choice-group &.small-size label }
  assets/scss/front/default/components/form/_form.scss:38  { .checkbox-group, .choice-group &:not(.small-size) label }
  assets/scss/front/default/components/form/_form.scss:51  { .choice-group, .form-choice-entity-group .form-select:not(.selected) ~ label }
  assets/scss/front/default/components/form/_form.scss:61  { .choice-group, .form-choice-entity-group .form-select:not(.selected) ~ label span }
  assets/scss/front/default/components/form/_form.scss:65  { .choice-group, .form-choice-entity-group .form-select ~ label }
  assets/scss/front/default/components/form/_form.scss:93  { .floating-form-text, .floating-form-choice label }
  assets/scss/front/default/components/form/_form.scss:106  { .form-label span }
  assets/scss/front/default/components/form/_form.scss:111  { :not(.form-floating) .form-label span }
  assets/scss/front/default/components/form/_form.scss:121  { .form-floating .form-label span }
  assets/scss/front/default/components/form/_form.scss:170  { input:-webkit-autofill, textarea:-webkit-autofill, select:-webkit-autofill }
  assets/scss/front/default/components/form/_form.scss:236  { .file-group.as-btn > label:first-child }
  assets/scss/front/default/components/form/_form.scss:354  { .form-check *:not(.form-check) }
  assets/scss/front/default/components/form/_form.scss:390  { html body form .form-field-none }
  assets/scss/front/default/components/form/_form.scss:541  { .img-drop-wrap .drop-preview, .drop-preview picture, .drop-preview img }
  … +322 autres

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
