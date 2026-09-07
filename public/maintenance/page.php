<?php

declare(strict_types=1);

/**
 * Vue de la page de maintenance.
 *
 * Page autonome : aucun asset compile, aucune requete base de donnees.
 * Les chemins sont racine-absolus car la vue est rendue sur l'URL demandee par le visiteur.
 * Couleurs, rayons et typographies reprennent assets/scss/front/default/variables.scss
 * ($primary #b48608, $light #f4f0f1, $dark #141414, $font-primary Museosans, $font-script
 * Augustscript, $border-radius 1.5rem) ainsi que le traitement du bloc title-header
 * (titre capitales a .4em, sous-titre script en chevauchement). Le voile de lisibilite est
 * degrade de 40 a 65 % la ou title-header se contente de 30 % : la page porte un paragraphe
 * et des boutons, la ou l'entete de page ne porte qu'un titre.
 * Ces valeurs sont figees ici faute de compilation SCSS, de meme que les media queries brutes,
 * la fonction mediaQuery() n'etant pas disponible hors SCSS : 991px correspond a max-lg, 767px a max-md.
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Site en maintenance - H&ocirc;tel Parister</title>
    <meta name="description" content="Le site de l&rsquo;H&ocirc;tel Parister est momentan&eacute;ment indisponible pour une mise &agrave; jour technique.">
    <link rel="icon" type="image/png" sizes="32x32" href="/maintenance/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/maintenance/images/favicon-16x16.png">
    <link rel="apple-touch-icon" href="/maintenance/images/apple-touch-icon.png">
    <link rel="preload" href="/maintenance/images/background.jpg" as="image" fetchpriority="high">
    <link rel="preload" href="/maintenance/fonts/museosans-700.woff2" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face {
            font-family: Museosans;
            font-style: normal;
            font-weight: 400 500;
            font-display: swap;
            src: url("/maintenance/fonts/museosans-500.woff2") format("woff2");
        }

        @font-face {
            font-family: Museosans;
            font-style: normal;
            font-weight: 600 700;
            font-display: swap;
            src: url("/maintenance/fonts/museosans-700.woff2") format("woff2");
        }

        @font-face {
            font-family: Augustscript;
            font-style: normal;
            font-weight: 400;
            font-display: swap;
            src: url("/maintenance/fonts/august-script.ttf") format("truetype");
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            height: 100%;
        }

        body {
            margin: 0;
            min-height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background-color: #141414;
            background-image: linear-gradient(180deg, rgba(0, 0, 0, .4) 0%, rgba(0, 0, 0, .65) 100%), url("/maintenance/images/background.jpg");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: #f4f0f1;
            font-family: Museosans, system-ui, -apple-system, "Segoe UI", sans-serif;
            font-size: 16px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .maintenance-card {
            width: 100%;
            max-width: 720px;
            text-align: center;
        }

        .maintenance-logo {
            display: block;
            width: 91px;
            height: 123px;
            margin: 0 auto 48px;
        }

        /* Titre du bloc title-header : Museo 700, capitales, interlettrage .4em. */
        .maintenance-title {
            margin: 0;
            font-size: 38px;
            line-height: 1.21;
            font-weight: 700;
            letter-spacing: .4em;
            text-transform: uppercase;
        }

        /* Sous-titre script chevauchant le titre, comme sur les entetes de page. */
        .maintenance-sub-title {
            display: block;
            margin: -1rem 0 0;
            font-family: Augustscript, Museosans, cursive;
            font-size: 100px;
            line-height: .76;
            font-weight: 400;
            letter-spacing: 0;
            text-transform: none;
        }

        .maintenance-text {
            margin: 40px auto 0;
            max-width: 540px;
            color: rgba(244, 240, 241, .85);
        }

        .maintenance-actions {
            margin-top: 40px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 16px;
        }

        /* Boutons .btn-primary : or signature, capitales, interlettrage .2em, rayon 1.5rem. */
        .maintenance-call {
            display: inline-block;
            min-width: 175px;
            padding: 1rem 2rem .9rem;
            border: 1px solid #b48608;
            border-radius: 1.5rem;
            background-color: #b48608;
            color: #fff;
            font-size: 14px;
            line-height: 1rem;
            font-weight: 700;
            letter-spacing: .2em;
            text-transform: uppercase;
            text-decoration: none;
            transition: background-color .3s ease, border-color .3s ease, color .3s ease;
        }

        .maintenance-call:hover,
        .maintenance-call:focus-visible {
            border-color: #fff;
            background-color: #fff;
            color: #b48608;
        }

        .maintenance-call.outline {
            border-color: #f4f0f1;
            background-color: transparent;
            color: #f4f0f1;
        }

        .maintenance-call.outline:hover,
        .maintenance-call.outline:focus-visible {
            background-color: #f4f0f1;
            color: #141414;
        }

        .maintenance-call:focus-visible {
            outline: 2px solid #ff9800;
            outline-offset: 3px;
        }

        .maintenance-contact {
            margin: 56px 0 0;
            padding-top: 32px;
            border-top: 1px solid rgba(244, 240, 241, .2);
            font-style: normal;
            font-size: 14px;
            line-height: 1.7;
            color: rgba(244, 240, 241, .7);
        }

        .maintenance-contact .heading {
            display: block;
            margin-bottom: 12px;
            color: #b48608;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .3em;
            text-transform: uppercase;
        }

        .maintenance-contact a {
            border-bottom: 1px solid rgba(180, 134, 8, .6);
            color: inherit;
            text-decoration: none;
        }

        .maintenance-contact a:hover,
        .maintenance-contact a:focus-visible {
            color: #b48608;
        }

        @media (max-width: 991px) {

            .maintenance-title {
                font-size: 30px;
                letter-spacing: .3em;
            }

            .maintenance-sub-title {
                font-size: 84px;
            }
        }

        @media (max-width: 767px) {

            body {
                background-attachment: scroll;
            }

            .maintenance-logo {
                width: 67px;
                height: 91px;
                margin-bottom: 32px;
            }

            .maintenance-title {
                font-size: 22px;
                letter-spacing: .25em;
            }

            /* Taille calee pour que le sous-titre script tienne sur une seule ligne des 360px. */
            .maintenance-sub-title {
                margin-top: -.15rem;
                font-size: 44px;
            }

            .maintenance-text {
                margin-top: 32px;
            }

            .maintenance-actions {
                margin-top: 32px;
            }

            .maintenance-call {
                width: 100%;
            }

            .maintenance-contact {
                margin-top: 40px;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .maintenance-call {
                transition: none;
            }
        }
    </style>
</head>
<body>
    <main class="maintenance-card">
        <img src="/maintenance/images/logo.svg" alt="H&ocirc;tel Parister" width="91" height="123" class="maintenance-logo" decoding="async">
        <h1 class="maintenance-title">
            Site en maintenance
            <span class="maintenance-sub-title">Bient&ocirc;t de retour</span>
        </h1>
        <p class="maintenance-text">
            Une mise &agrave; jour technique est en cours. Le site sera de nouveau accessible tr&egrave;s prochainement.
            Notre &eacute;quipe reste joignable par t&eacute;l&eacute;phone et par e-mail pour vos r&eacute;servations.
        </p>
        <div class="maintenance-actions">
            <a class="maintenance-call" href="tel:+33180509191">+33 (0)1 80 50 91 91</a>
            <a class="maintenance-call outline" href="mailto:bonjour@hotelparister.com">Nous &eacute;crire</a>
        </div>
        <address class="maintenance-contact">
            <span class="heading">H&ocirc;tel Parister</span>
            19 rue Saulnier, 75009 Paris<br>
            <a href="mailto:bonjour@hotelparister.com">bonjour@hotelparister.com</a>
        </address>
    </main>
</body>
</html>
