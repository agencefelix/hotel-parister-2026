<?php

declare(strict_types=1);

namespace App\Service\DataFixtures;

use App\Entity\Core\Website;
use App\Entity\Layout;
use App\Entity\Media\Media;
use App\Entity\Media\MediaRelationIntl;
use App\Entity\Module\Form as FormEntity;
use App\Entity\Module\Map as MapEntity;
use App\Entity\Module\Newscast as NewsEntity;
use App\Entity\Module\Catalog as CatalogEntity;
use App\Entity\Module\Search\Search;
use App\Entity\Module\Slider\Slider;
use App\Entity\Module\Slider\SliderMediaRelation;
use App\Entity\Security\User;
use App\Entity\Seo\Url;
use App\Form\Manager\Layout\LayoutManager;
use App\Service\Core\Urlizer;
use App\Service\Interface\CoreLocatorInterface;
use Exception;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * PageFixtures.
 *
 * Page Fixtures management
 *
 * @author Sébastien FOURNIER <fournier.sebastien@outlook.com>
 */
#[Autoconfigure(tags: [
    ['name' => PageFixtures::class, 'key' => 'page_fixtures'],
])]
class PageFixtures
{
    private const bool CONTACT_LAYOUT = true;
    private const bool CONTACT_MAP = true;
    private array $yamlConfiguration = [];
    private Generator $faker;
    private Website $website;
    private ?User $user;
    private bool $flush;
    private string $locale = '';
    private array $pages = [];
    private int $layoutPosition = 1;

    /**
     * PageFixtures constructor.
     */
    public function __construct(
        private readonly CoreLocatorInterface $coreLocator,
        private readonly LayoutManager $layoutManager,
        private readonly UploadedFileFixtures $uploadedFileFixtures,
    ) {
    }

    /**
     * Importe une VRAIE image de la maquette (media/home/<filename>) en entité Media.
     * Conforme au playbook : médias = images récupérées, jamais un média par défaut.
     */
    private function importMedia(string $filename): ?Media
    {
        $path = $this->coreLocator->projectDir().'/.claude/skills/figma-cms/integration/media/home/'.$filename;
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (!is_file($path)) {
            return null;
        }

        return $this->uploadedFileFixtures->uploadedFile($this->website, $path, $this->locale, null, null, null, $this->user);
    }

    /**
     * Crée un bloc média et y rattache la VRAIE image de la maquette (remplace le média par défaut).
     */
    private function mediaBlock(Layout\Col $col, string $filename, int $position = 1): Layout\Block
    {
        $block = $this->addBlock($col, 'media', null, null, $position);
        // Vider la légende Faker du bloc média (sinon figcaption + alt en lorem ipsum).
        $blockIntl = $block->getIntls()->first();
        if ($blockIntl instanceof Layout\BlockIntl) {
            $blockIntl->setTitle(null);
            $blockIntl->setIntroduction(null);
            $blockIntl->setBody(null);
        }
        $media = $this->importMedia($filename);
        if ($media instanceof Media) {
            // Pas de légende sur les images de bandes (maquette) : on désactive la position du titre
            // (la figcaption n'est rendue que si titlePosition ∈ top/bottom/left/right).
            $media->setTitlePosition(null);
            // Vider le titre Faker du média (sinon alt en lorem ipsum) - image décorative.
            foreach ($media->getIntls() as $mediaIntl) {
                $mediaIntl->setTitle('');
            }
            $relation = $block->getMediaRelations()->first();
            if ($relation instanceof Layout\BlockMediaRelation) {
                $relation->setMedia($media);
                $relation->setPopup(false);
                $relation->setDownloadable(false);
            }
        }

        return $block;
    }

    /**
     * Add Pages.
     *
     * @throws Exception
     */
    public function add(Website $website, array $yamlConfiguration, array $pagesParams, ?User $user = null, bool $flush = true, array $mainPages = []): array
    {
        $this->yamlConfiguration = $yamlConfiguration;
        $this->faker = Factory::create();
        $this->website = $website;
        $this->user = $user;
        $this->flush = $flush;
        $this->locale = $website->getConfiguration()->getLocale();
        $this->layoutPosition = count($this->coreLocator->em()->getRepository(Layout\Layout::class)->findBy(['website' => $this->website])) + 1;

        foreach ($pagesParams as $key => $pageParams) {
            $params = (object) $pageParams;
            $enable = !property_exists($params, 'disable') || false === $params->disable;
            if ($enable) {
                $existingPage = $this->coreLocator->em()->getRepository(Layout\Page::class)->findOneBy([
                    'website' => $website,
                    'slug' => $params->reference,
                ]);
                if (!$existingPage) {
                    $layout = $this->addLayoutPage($params);
                    $position = $website->getId() > 0 ? count($this->coreLocator->em()->getRepository(Layout\Page::class)->findByWebsiteNotArchived($website)) + 1 : $key + 1;
                    $this->generatePage($layout, $params, $position, $mainPages);
                    $this->coreLocator->em()->persist($layout);
                }
            }
        }

        return $this->pages;
    }

    /**
     * Generate Page.
     */
    private function generatePage(Layout\Layout $layout, mixed $params, int $position, array $mainPages = []): void
    {
        $page = new Layout\Page();
        $page->setAdminName($params->name);
        $page->setWebsite($this->website);
        $page->setAsIndex($params->asIndex);
        $page->setTemplate($params->template.'.html.twig');
        $page->setPosition($position);
        $page->setDeletable($params->deletable);
        $page->setSlug($params->reference);
        $page->setLayout($layout);
        $page->setCreatedBy($this->user);

        if (!$params->deletable) {
            $page->setInfill(true);
        }

        if (property_exists($params, 'secure')) {
            $page->setSecure($params->secure);
        }

        $this->coreLocator->em()->persist($page);
        $this->pages[$params->reference] = $page;

        $urlCode = property_exists($params, 'url') && '' !== $params->url ? $params->url : null;
        $this->generateUrl($page, $params->urlAsIndex, $urlCode);

        if (in_array($params->reference, $mainPages)) {
            $configuration = $this->website->getConfiguration();
            $configuration->addPage($page);
            $this->coreLocator->em()->persist($configuration);
        }
    }

    /**
     * Generate Url.
     */
    private function generateUrl(Layout\Page $page, bool $asIndex, ?string $code = null): void
    {
        $url = new Url();
        // Code URL = chemin de PROD si fourni (continuité SEO), sinon dérivé du nom.
        $url->setCode($code ?? Urlizer::urlize($page->getAdminName()));
        $url->setLocale($this->locale);
        $url->setWebsite($this->website);
        $url->setAsIndex($asIndex);
        $url->setHideInSitemap(!$asIndex);
        $url->setOnline(true);

        if (!empty($this->user)) {
            $url->setCreatedBy($this->user);
        }

        $page->addUrl($url);

        $this->coreLocator->em()->persist($page);
        if ($this->flush) {
            $this->coreLocator->em()->flush();
        }
    }

    /**
     * Generate Layout Page.
     *
     * @throws Exception
     */
    private function addLayoutPage(mixed $params): Layout\Layout
    {
        $layout = $this->addLayout($params->name);

        /* Header */
        if ('home' != $params->reference) {
            $zone = $this->addZone($layout, 1, true);
            $col = $this->addCol($zone);
            $this->addHeader($col, $params->name);
        }

        if ('home' == $params->reference) {
            $this->addHomeLayout($layout);
        } elseif ('news' == $params->reference) {
            $this->addNewscastLayout($layout);
        } elseif ('products' == $params->reference) {
            $this->addProductLayout($layout);
        } elseif ('sitemap' == $params->reference) {
            $this->addSitemapLayout($layout);
        } elseif ('contact' == $params->reference) {
            $this->addContactLayout($layout);
        } elseif ('user-dashboard' == $params->reference) {
            $this->addUserDashboardLayout($layout);
        } elseif ('search-results' == $params->reference) {
            $this->addSearchResultsLayout($layout);
        }

        $this->coreLocator->em()->persist($layout);
        if ($this->flush) {
            $this->coreLocator->em()->flush();
        }

        $this->layoutManager->setGridZone($layout);

        return $layout;
    }

    /**
     * Generate Layout.
     */
    public function addLayout(string $adminName, bool $dbPosition = false): Layout\Layout
    {
        $position = $dbPosition ? count($this->coreLocator->em()->getRepository(Layout\Layout::class)->findBy(['website' => $this->website])) + 1 : $this->layoutPosition;

        $layout = new Layout\Layout();
        $layout->setWebsite($this->website);
        $layout->setAdminName($adminName);
        $layout->setPosition($position);

        if (!empty($this->user)) {
            $layout->setCreatedBy($this->user);
        }

        ++$this->layoutPosition;

        return $layout;
    }

    /**
     * Add Zone.
     */
    public function addZone(Layout\Layout $layout, int $position, bool $fullSize = false, bool $noPadding = false, ?string $customId = null): Layout\Zone
    {
        $zone = new Layout\Zone();
        $zone->setFullSize($fullSize);
        $zone->setPosition($position);

        if ($customId) {
            $zone->setCustomId($customId);
        }

        if ($noPadding) {
            $zone->setPaddingTop('pt-0');
            $zone->setPaddingBottom('pb-0');
        }

        if (!empty($this->user)) {
            $zone->setCreatedBy($this->user);
        }

        $layout->addZone($zone);

        return $zone;
    }

    /**
     * Add Col.
     */
    public function addCol(Layout\Zone $zone, int $position = 1, int $size = 12): Layout\Col
    {
        $col = new Layout\Col();
        $col->setPosition($position);
        $col->setSize($size);
        $zone->addCol($col);

        if (!empty($this->user)) {
            $col->setCreatedBy($this->user);
        }

        return $col;
    }

    /**
     * Add header.
     */
    private function addHeader(Layout\Col $col, string $adminName): void
    {
        $col->setPaddingLeft('ps-0');
        $col->setPaddingRight('pe-0');

        $intl = new Layout\BlockIntl();
        $intl->setTitle($adminName);
        $intl->setLocale($this->locale);
        $intl->setTitleForce(1);
        $intl->setWebsite($this->website);

        $zone = $col->getZone();
        $zone->setPaddingTop('pt-0');
        $zone->setPaddingBottom('pb-0');

        if (!empty($this->user)) {
            $intl->setCreatedBy($this->user);
        }

        $block = $this->addBlock($col, 'title-header');
        $block->addIntl($intl);
        $block->setPaddingLeft('ps-0');
        $block->setPaddingRight('pe-0');
    }

    /**
     * Add Block.
     */
    public function addBlock(
        Layout\Col $col,
        ?string $blockTypeSlug = null,
        ?string $actionSlug = null,
        ?int $actionFilter = null,
        int $position = 1,
        int $size = 12,
        bool $maxTablet = false,
    ): Layout\Block {

        $block = new Layout\Block();
        $block->setPosition($position);
        $block->setSize($size);

        if (!empty($this->user)) {
            $block->setCreatedBy($this->user);
        }

        if ('form-submit' === $blockTypeSlug) {
            $block->setColor('btn-primary');
        }

        if ($maxTablet) {
            $block->setTabletSize($size);
            $block->setMiniPcSize($size);
        }

        $col->addBlock($block);

        $this->addAction($block, $blockTypeSlug, $actionSlug, $actionFilter);

        return $block;
    }

    /**
     * Add Home Layout.
     *
     * Reproduit la maquette Figma Parister (home) : hero, teasers d'univers,
     * parenthèse parisienne, chambres (navy), restaurant, spa (teal),
     * séminaires/workspaces, puis teaser actualités "Art & rencontres".
     * Le contenu texte est réel (issu de la maquette) ; l'imagerie réutilise
     * les médias de marque (hero/share) selon le pattern des fixtures du CMS.
     */
    private function addHomeLayout(Layout\Layout $layout): void
    {
        $hero = $this->coreLocator->em()->getRepository(Media::class)->findOneBy([
            'website' => $this->website,
            'category' => 'title-header',
        ]);

        /* --- Zone 1 : Hero pleine page (slider bannière) --- */
        $slider = new Slider();
        $slider->setAdminName("Hero page d'accueil");
        $slider->setWebsite($this->website);
        $slider->setSlug('home-hero');
        $slider->setTemplate('main-home');
        $slider->setArrowColor('btn-white');

        $heroIntl = new MediaRelationIntl();
        $heroIntl->setLocale($this->locale);
        $heroIntl->setTitle('Boutique hôtel & spa');
        $heroIntl->setTitleForce(1); // Hero = unique <h1> de la page (SEO/accessibilité).
        $heroIntl->setIntroduction('Parister');
        $heroIntl->setTargetLink('/chambres-suites');
        $heroIntl->setTargetLabel('Réservez un séjour');
        $heroIntl->setTargetStyle('btn-outline-white'); // CTA bouton outline blanc (filets haut/bas, cf. maquette).
        $heroIntl->setWebsite($this->website);
        $heroRelation = new SliderMediaRelation();
        $heroRelation->setPosition(1);
        $heroRelation->setLocale($this->locale);
        $heroRelation->setPopup(false);
        $heroRelation->setDownloadable(false);
        $heroRelation->setMedia($this->importMedia('hero-boutique-hotel.jpg') ?? $hero);
        $heroRelation->setIntl($heroIntl);
        $slider->addMediaRelation($heroRelation);

        $this->coreLocator->em()->persist($slider);
        if ($this->flush) {
            $this->coreLocator->em()->flush();
        }

        $heroZone = $this->addZone($layout, 1, true, true, 'home-hero');
        $heroCol = $this->addCol($heroZone);
        $heroCol->setPaddingRight('pe-0');
        $heroCol->setPaddingLeft('ps-0');
        $block = $this->addBlock($heroCol, 'core-action', 'slider-view', $slider->getId());
        $block->setPaddingLeft('ps-0');
        $block->setPaddingRight('pe-0');

        /* --- Zone 2 : Bandeau alerte (bloc `alert`) — SOUS le hero (maquette) --- */
        $alertZone = $this->addZone($layout, 2, true, true, 'home-alert');
        $alertZone->setBackgroundColor('bg-primary');
        $alertCol = $this->addCol($alertZone);
        $alertCol->setPaddingRight('pe-0');
        $alertCol->setPaddingLeft('ps-0');
        $alertBlock = $this->addBlock($alertCol, 'alert');
        $this->setContent($alertBlock, ['introduction' => 'Un boutique hôtel 5 étoiles au cœur de Paris, où lifestyle, culture & art se rencontrent.']);

        /* --- Zone 2 : Teasers d'univers (cards croppées à droite dans la maquette → slider|splide) --- */
        $teasers = [
            ['title' => 'Séjourner', 'sub' => "à l'hôtel", 'body' => 'Des espaces pensés pour se réunir, présenter et célébrer.', 'link' => '/chambres-suites', 'image' => 'teaser-sejourner.jpg'],
            ['title' => 'Boire et manger', 'sub' => 'au restaurant', 'body' => 'Une adresse vivante pour boire un verre, déjeuner ou prolonger la soirée.', 'link' => '/restaurant-bar-a-cocktail', 'image' => 'teaser-boire-manger.jpg'],
            ['title' => 'Se détendre', 'sub' => 'au spa', 'body' => "Un espace dédié au bien-être, à l'écart du rythme de la ville.", 'link' => '/sport-bien-etre', 'image' => 'teaser-se-detendre.jpg'],
            ['title' => 'Louer', 'sub' => 'une salle', 'body' => 'Des espaces raffinés pour réunir, présenter, célébrer.', 'link' => '/salle-de-reunion-evenementiel', 'image' => 'teaser-evenements.jpg'],
        ];
        $universSlider = new Slider();
        $universSlider->setAdminName("Univers de l'hôtel");
        $universSlider->setWebsite($this->website);
        $universSlider->setSlug('home-universe');
        $universSlider->setTemplate('splide');
        $sliderPosition = 1;
        foreach ($teasers as $teaserData) {
            $intl = new MediaRelationIntl();
            $intl->setLocale($this->locale);
            $intl->setTitle($teaserData['title']);
            $intl->setIntroduction($teaserData['sub']);
            $intl->setBody('<p>'.$teaserData['body'].'</p>');
            $intl->setTargetLink($teaserData['link']);
            $intl->setTargetLabel('Découvrir');
            $intl->setTargetStyle('link');
            $intl->setWebsite($this->website);
            $relation = new SliderMediaRelation();
            $relation->setPosition($sliderPosition);
            $relation->setLocale($this->locale);
            $relation->setMedia($this->importMedia($teaserData['image']) ?? $hero);
            $relation->setIntl($intl);
            $relation->setPopup(false);
            $relation->setDownloadable(false);
            $universSlider->addMediaRelation($relation);
            ++$sliderPosition;
        }
        $this->coreLocator->em()->persist($universSlider);
        if ($this->flush) {
            $this->coreLocator->em()->flush();
        }
        // Cards croppées à droite (carrousel) → zone colToRight + padding-right 0 (zone, col, bloc).
        $universZone = $this->addZone($layout, 3, customId: 'home-universe');
        $universZone->setBackgroundColor('bg-light');
        $universZone->setColToRight(true);
        $universZone->setPaddingRight('pe-0');
        $universCol = $this->addCol($universZone);
        $universCol->setPaddingRight('pe-0');
        $universBlock = $this->addBlock($universCol, 'core-action', 'slider-view', $universSlider->getId());
        $universBlock->setPaddingRight('pe-0');

        /* --- Zone 3 : Bande image plein écran = SLIDER BOOTSTRAP multi-images (maquette [slider|bootstrap]) --- */
        $getawaySlider = new Slider();
        $getawaySlider->setAdminName('Bande parenthèse (accueil)');
        $getawaySlider->setWebsite($this->website);
        $getawaySlider->setSlug('home-getaway');
        $getawaySlider->setTemplate('bootstrap');
        $getawaySlider->setEffect('fade');
        $getawaySlider->setAutoplay(true);
        $getawaySlider->setControl(true);
        $getawaySlider->setIndicators(true);
        $getawaySlider->setArrowColor('btn-white');
        $getawaySlider->setArrowAlignment('bottom-end');
        $getawayImages = ['hero-parenthese-parisienne.jpg', 'evenement-art.jpg', 'spa-piscine.jpg'];
        $getawayPos = 1;
        foreach ($getawayImages as $getawayImage) {
            $getawayIntl = new MediaRelationIntl();
            $getawayIntl->setLocale($this->locale);
            $getawayIntl->setTitle('Intimiste, contemporain et chaleureux');
            $getawayIntl->setIntroduction('Parister');
            $getawayIntl->setTargetLink('/');
            $getawayIntl->setTargetLabel("Découvrir l'hôtel");
            $getawayIntl->setTargetStyle('btn-outline-light');
            $getawayIntl->setWebsite($this->website);
            $getawayRelation = new SliderMediaRelation();
            $getawayRelation->setPosition($getawayPos);
            $getawayRelation->setLocale($this->locale);
            $getawayRelation->setPopup(false);
            $getawayRelation->setDownloadable(false);
            $getawayRelation->setMedia($this->importMedia($getawayImage) ?? $hero);
            $getawayRelation->setIntl($getawayIntl);
            $getawaySlider->addMediaRelation($getawayRelation);
            ++$getawayPos;
        }
        $this->coreLocator->em()->persist($getawaySlider);
        if ($this->flush) {
            $this->coreLocator->em()->flush();
        }
        $parentheseZone = $this->addZone($layout, 4, true, true, 'home-getaway');
        $col = $this->addCol($parentheseZone);
        $col->setPaddingRight('pe-0');
        $col->setPaddingLeft('ps-0');
        $getawayBlock = $this->addBlock($col, 'core-action', 'slider-view', $getawaySlider->getId());
        $getawayBlock->setPaddingLeft('ps-0');
        $getawayBlock->setPaddingRight('pe-0');

        /* --- Zone 4 : Chambres (navy) --- */
        $chambresZone = $this->addZone($layout, 5, customId: 'home-rooms');
        $chambresZone->setBackgroundColor('bg-navy');
        $textCol = $this->addCol($chambresZone, 1, 6);
        $this->setContent($this->addBlock($textCol, 'title'), ['title' => 'Votre parenthèse', 'subTitle' => 'parisienne', 'titleForce' => 2]);
        $block = $this->setContent($this->addBlock($textCol, 'text', null, null, 2), ['body' => '<p>Chambres, suites, offres et disponibilités, intimistes et contemporaines, pour un séjour au cœur de Paris.</p>']);
        $block->setMarginBottom('mb-md');
        $this->setContent($this->addBlock($textCol, 'link', null, null, 3), ['linkLabel' => 'Découvrir les chambres', 'linkStyle' => 'link text-uppercase', 'targetLink' => '/chambres-suites', 'newTab' => false]);
        $this->setContent($this->addBlock($textCol, 'link', null, null, 4), ['linkLabel' => 'Réserver', 'linkStyle' => 'link text-uppercase', 'targetLink' => '/chambres-suites', 'newTab' => false]);
        $imageCol = $this->addCol($chambresZone, 2, 6);
        $this->mediaBlock($imageCol, 'chambre-salle-de-bain.jpg');

        /* --- Zone 6 : Slider de cartes chambres (produits) — [teaser|product] = SECTION dédiée --- */
        $roomsTeaser = $this->coreLocator->em()->getRepository(CatalogEntity\Teaser::class)->findOneBy(['website' => $this->website]);
        if ($roomsTeaser instanceof CatalogEntity\Teaser) {
            $roomsTeaser->setTemplate('slider');
            $roomsTeaser->setItemsPerSlide(3);
            $roomsTeaser->setNbrItems(8);
            $roomsProductsZone = $this->addZone($layout, 6, true, true, 'home-rooms-products');
            $roomsProductsZone->setBackgroundColor('bg-navy');
            $roomsProductsZone->setColToRight(true);
            $roomsProductsZone->setPaddingRight('pe-0');
            $teaserCol = $this->addCol($roomsProductsZone);
            $teaserCol->setPaddingRight('pe-0');
            $productsBlock = $this->addBlock($teaserCol, 'core-action', 'catalog-teaser', $roomsTeaser->getId());
            $productsBlock->setPaddingRight('pe-0');
        }

        /* --- Zone 7 : Les passerelles (restaurant & bar) ---
           Maquette = grille 2×2 dans l'ORDRE : texte (haut-gauche) + plat (haut-droite)
           / cocktails (bas-gauche) + dessert (bas-droite). 3 images (toutes récupérées). */
        $restaurantZone = $this->addZone($layout, 7, customId: 'home-restaurant');
        $restaurantZone->setBackgroundColor('bg-light');
        // 1) Texte (haut-gauche)
        $textCol = $this->addCol($restaurantZone, 1, 6);
        $this->setContent($this->addBlock($textCol, 'title'), ['title' => 'Les passerelles', 'subTitle' => 'restaurant & bar', 'titleForce' => 2]);
        $block = $this->setContent($this->addBlock($textCol, 'text', null, null, 2), ['body' => '<p>Une adresse vivante pour boire un verre, déjeuner ou prolonger la soirée.</p>']);
        $block->setMarginBottom('mb-md');
        $this->setContent($this->addBlock($textCol, 'link', null, null, 3), ['linkLabel' => 'Réserver', 'linkStyle' => 'link text-uppercase', 'targetLink' => '/restaurant-bar-a-cocktail', 'newTab' => false]);
        $this->setContent($this->addBlock($textCol, 'link', null, null, 4), ['linkLabel' => 'Voir le menu', 'linkStyle' => 'link text-uppercase', 'targetLink' => '/restaurant-bar-a-cocktail', 'newTab' => false]);
        // 2) Plat (haut-droite)
        $this->mediaBlock($this->addCol($restaurantZone, 2, 6), 'restaurant-plat.jpg');
        // 3) Cocktails (bas-gauche)
        $this->mediaBlock($this->addCol($restaurantZone, 3, 6), 'restaurant-cocktail.jpg');
        // 4) Dessert (bas-droite)
        $this->mediaBlock($this->addCol($restaurantZone, 4, 6), 'restaurant-dessert.jpg');

        /* --- Zone 8 : Spa, bien-être & sport (teal) --- */
        $spaZone = $this->addZone($layout, 8, customId: 'home-spa');
        $spaZone->setBackgroundColor('bg-teal');
        $textCol = $this->addCol($spaZone, 1, 6);
        $this->setContent($this->addBlock($textCol, 'title'), ['title' => 'Spa, Bien-être &', 'subTitle' => 'sport', 'titleForce' => 2]);
        $block = $this->setContent($this->addBlock($textCol, 'text', null, null, 2), ['body' => "<p>Un espace dédié au lâcher-prise. Piscine, soins, fitness : tout est pensé pour se détendre et retrouver l'équilibre.</p>"]);
        $block->setMarginBottom('mb-md');
        $this->setContent($this->addBlock($textCol, 'link', null, null, 3), ['linkLabel' => 'Découvrir le spa', 'linkStyle' => 'link text-uppercase', 'targetLink' => '/sport-bien-etre', 'newTab' => false]);
        $this->setContent($this->addBlock($textCol, 'link', null, null, 4), ['linkLabel' => 'Réserver un soin', 'linkStyle' => 'link text-uppercase', 'targetLink' => '/sport-bien-etre', 'newTab' => false]);
        $imageCol = $this->addCol($spaZone, 2, 6);
        $this->mediaBlock($imageCol, 'spa-piscine.jpg');
        // Slider de 4 cartes services (splide) sous l'intro — maquette.
        $spaCards = [
            ['title' => 'Les soins', 'sub' => '& massages', 'image' => 'spa-1.jpg'],
            ['title' => 'La piscine', 'sub' => 'chauffée', 'image' => 'spa-2.jpg'],
            ['title' => 'La salle', 'sub' => 'de sport', 'image' => 'spa-3.jpg'],
            ['title' => 'Le couloir', 'sub' => 'de nage', 'image' => 'spa-4.jpg'],
        ];
        $spaSlider = new Slider();
        $spaSlider->setAdminName('Services du spa');
        $spaSlider->setWebsite($this->website);
        $spaSlider->setSlug('home-spa-services');
        $spaSlider->setTemplate('splide');
        $spaPos = 1;
        foreach ($spaCards as $card) {
            $cardIntl = new MediaRelationIntl();
            $cardIntl->setLocale($this->locale);
            $cardIntl->setTitle($card['title']);
            $cardIntl->setIntroduction($card['sub']);
            $cardIntl->setTargetLink('/sport-bien-etre');
            $cardIntl->setTargetLabel('Découvrir');
            $cardIntl->setTargetStyle('link');
            $cardIntl->setWebsite($this->website);
            $cardRelation = new SliderMediaRelation();
            $cardRelation->setPosition($spaPos);
            $cardRelation->setLocale($this->locale);
            $cardRelation->setMedia($this->importMedia($card['image']) ?? $hero);
            $cardRelation->setIntl($cardIntl);
            $cardRelation->setPopup(false);
            $cardRelation->setDownloadable(false);
            $spaSlider->addMediaRelation($cardRelation);
            ++$spaPos;
        }
        $this->coreLocator->em()->persist($spaSlider);
        if ($this->flush) {
            $this->coreLocator->em()->flush();
        }
        /* --- Zone 9 : Slider services spa ([slider|splide]) = SECTION dédiée (cartes débordant à droite) --- */
        $spaSliderZone = $this->addZone($layout, 9, true, true, 'home-spa-services');
        $spaSliderZone->setBackgroundColor('bg-teal');
        $spaSliderZone->setColToRight(true);
        $spaSliderZone->setPaddingRight('pe-0');
        $spaSliderCol = $this->addCol($spaSliderZone);
        $spaSliderCol->setPaddingRight('pe-0');
        $spaSliderBlock = $this->addBlock($spaSliderCol, 'core-action', 'slider-view', $spaSlider->getId());
        $spaSliderBlock->setPaddingRight('pe-0');

        /* --- Zone 10 : Workspaces (bande image plein écran, filigrane « workspaces ») --- */
        $workspacesSlider = new Slider();
        $workspacesSlider->setAdminName('Bande workspaces (accueil)');
        $workspacesSlider->setWebsite($this->website);
        $workspacesSlider->setSlug('home-workspaces');
        $workspacesSlider->setTemplate('main-home');
        $workspacesSlider->setArrowColor('btn-white');
        $workspacesIntl = new MediaRelationIntl();
        $workspacesIntl->setLocale($this->locale);
        $workspacesIntl->setTitle('Pour vos séminaires, réunions, événements');
        $workspacesIntl->setIntroduction('workspaces');
        $workspacesIntl->setTargetLink('/salle-de-reunion-evenementiel');
        $workspacesIntl->setTargetLabel('Organiser un événement');
        $workspacesIntl->setTargetStyle('btn-outline-light');
        $workspacesIntl->setWebsite($this->website);
        $workspacesRelation = new SliderMediaRelation();
        $workspacesRelation->setPosition(1);
        $workspacesRelation->setLocale($this->locale);
        $workspacesRelation->setPopup(false);
        $workspacesRelation->setDownloadable(false);
        $workspacesRelation->setMedia($this->importMedia('workspaces-seminaire.jpg') ?? $hero);
        $workspacesRelation->setIntl($workspacesIntl);
        $workspacesSlider->addMediaRelation($workspacesRelation);
        $this->coreLocator->em()->persist($workspacesSlider);
        if ($this->flush) {
            $this->coreLocator->em()->flush();
        }
        $workspacesZone = $this->addZone($layout, 10, true, true, 'home-workspaces');
        $wsCol = $this->addCol($workspacesZone);
        $wsCol->setPaddingRight('pe-0');
        $wsCol->setPaddingLeft('ps-0');
        $wsBlock = $this->addBlock($wsCol, 'core-action', 'slider-view', $workspacesSlider->getId());
        $wsBlock->setPaddingLeft('ps-0');
        $wsBlock->setPaddingRight('pe-0');

        /* --- Zone 8 : Intro "Art & rencontres" (média + titre + texte + 2 CTAs) ---
           Zone DISTINCTE du teaser ci-dessous (cf. maquette : intro PUIS carrousel d'événements). */
        $artZone = $this->addZone($layout, 11, customId: 'home-art');
        $artZone->setBackgroundColor('bg-light');
        $artImageCol = $this->addCol($artZone, 1, 6);
        $this->mediaBlock($artImageCol, 'evenement-art.jpg');
        $artTextCol = $this->addCol($artZone, 2, 6);
        $this->setContent($this->addBlock($artTextCol, 'title'), ['title' => 'Art &', 'subTitle' => 'rencontres', 'titleForce' => 2]);
        $block = $this->setContent($this->addBlock($artTextCol, 'text', null, null, 2), ['body' => '<p>Expositions, vernissages, cercles littéraires… Le Parister cultive un esprit vivant et ouvert, où se croisent création, idées et moments partagés.</p>']);
        $block->setMarginBottom('mb-md');
        $this->setContent($this->addBlock($artTextCol, 'link', null, null, 3), ['linkLabel' => 'Découvrir nos événements', 'linkStyle' => 'link text-uppercase', 'targetLink' => '/la-vie-au-parister', 'newTab' => false]);
        $this->setContent($this->addBlock($artTextCol, 'link', null, null, 4), ['linkLabel' => 'Blog', 'linkStyle' => 'link', 'targetLink' => '/la-vie-au-parister', 'newTab' => false]);

        /* --- Zone 9 : Teaser actualités "Derniers événements" ---
           Suite d'images se terminant par un élément croppé → zone DÉDIÉE avec son TITRE,
           Zone::colToRight(true) + padding-right = 0 (sur la zone, la col et le bloc). */
        $teaser = $this->coreLocator->em()->getRepository(NewsEntity\Teaser::class)->findOneBy(['website' => $this->website]);
        if ($teaser instanceof NewsEntity\Teaser) {
            $teaser->setItemsPerSlide(4);
            $teaser->setNbrItems(15);
            $teaser->setTemplate('slider');
            $teaserZone = $this->addZone($layout, 12, customId: 'home-events');
            $teaserZone->setBackgroundColor('bg-light');
            $teaserZone->setColToRight(true);
            $teaserZone->setPaddingRight('pe-0');
            $teaserCol = $this->addCol($teaserZone);
            $teaserCol->setPaddingRight('pe-0');
            $this->setContent($this->addBlock($teaserCol, 'title'), ['title' => 'Derniers événements', 'titleForce' => 2]);
            $block = $this->addBlock($teaserCol, 'core-action', 'newscast-teaser', $teaser->getId(), 2);
            $block->setPaddingRight('pe-0');
        }
    }

    /**
     * Set real content on a title/text/link Block (overrides the default Faker intl).
     */
    private function setContent(Layout\Block $block, array $data): Layout\Block
    {
        $intl = $block->getIntls()->first();
        if (!$intl instanceof Layout\BlockIntl) {
            return $block;
        }
        // RÉINITIALISER les champs Faker non fournis (sinon le lorem ipsum résiduel s'affiche).
        $intl->setTitle($data['title'] ?? null);
        $intl->setSubTitle($data['subTitle'] ?? null);
        $intl->setIntroduction($data['introduction'] ?? null);
        $intl->setBody($data['body'] ?? null);
        if (array_key_exists('titleForce', $data)) {
            $intl->setTitleForce($data['titleForce']);
        }
        if (array_key_exists('targetLink', $data)) {
            $intl->setTargetLink($data['targetLink']);
        } else {
            $intl->setTargetLink(null);
        }
        // Libellé + style du lien/CTA (le bloc lien lit linkLabel/linkStyle, PAS title).
        $intl->setTargetLabel($data['linkLabel'] ?? null);
        $intl->setTargetStyle($data['linkStyle'] ?? null);
        if (array_key_exists('newTab', $data)) {
            $intl->setNewTab($data['newTab']);
        }

        return $block;
    }

    /**
     * Add Newscast Layout.
     */
    private function addNewscastLayout(Layout\Layout $layout): void
    {
        $listing = $this->coreLocator->em()->getRepository(NewsEntity\Listing::class)->findOneBy(['website' => $this->website]);
        $zone = $this->addZone($layout, 2, true, true);
        $col = $this->addCol($zone);
        $col->setPaddingRight('pe-0');
        $col->setPaddingLeft('ps-0');
        $block = $this->addBlock($col, 'core-action', 'newscast-index', $listing->getId());
        $block->setPaddingLeft('ps-0');
        $block->setPaddingRight('pe-0');
    }

    /**
     * Add Products Layout.
     */
    private function addProductLayout(Layout\Layout $layout): void
    {
        $listing = $this->coreLocator->em()->getRepository(CatalogEntity\Listing::class)->findOneBy(['website' => $this->website]);
        $zone = $this->addZone($layout, 2);
        $col = $this->addCol($zone);
        $this->addBlock($col, 'core-action', 'catalog-index', $listing->getId());
    }

    /**
     * Add Sitemap Layout.
     */
    private function addSitemapLayout(Layout\Layout $layout): void
    {
        $zone = $this->addZone($layout, 2);
        $col = $this->addCol($zone);
        $this->addBlock($col, 'core-action', 'sitemap-view');
    }

    /**
     * Add Contact Layout.
     *
     * @throws Exception
     */
    private function addContactLayout(Layout\Layout $layout): void
    {
        $zone = $this->addZone($layout, 2);
        if (self::CONTACT_LAYOUT) {
            $this->addForm($zone);
            $this->addInformation($zone);
            if (self::CONTACT_MAP) {
                $this->addMap($layout);
            }
        }
    }

    /**
     * Add User Dashboard Layout.
     *
     * @throws Exception
     */
    private function addUserDashboardLayout(Layout\Layout $layout): void
    {
        $zone = $this->addZone($layout, 2);
        $col = $this->addCol($zone, 2);
        $this->addBlock($col, 'core-action', 'secure-page-dashboard');
    }

    /**
     * Add Search page Layout.
     *
     * @throws Exception
     */
    private function addSearchResultsLayout(Layout\Layout $layout): void
    {
        $search = $this->coreLocator->em()->getRepository(Search::class)->findOneBy(['slug' => 'main', 'website' => $this->website]);
        if (!$search) {
            $search = new Search();
            $search->setWebsite($this->website);
            $search->setAdminName('Principal');
            $search->setSlug('main');
            $search->setCreatedBy($this->user);
            $search->setCreatedAt(new \DateTime('now', new \DateTimeZone('Europe/Paris')));
        }

        $this->coreLocator->em()->persist($search);
        if ($this->flush) {
            $this->coreLocator->em()->flush();
        }

        $zone = $this->addZone($layout, 2);
        $col = $this->addCol($zone);
        $this->addBlock($col, 'core-action', 'search-result-view', $search->getId());
    }

    /**
     * Add Form to contact Page.
     *
     * @throws Exception
     */
    private function addForm(Layout\Zone $zone): void
    {
        $adminName = 'Formulaire de contact';
        $form = $this->setForm($adminName, 'contact');
        if (!empty($this->user)) {
            $form->setCreatedBy($this->user);
        }

        $formLayout = $this->addLayout($adminName);
        $zoneLayout = $this->addZone($formLayout, 1, false, true);
        $zoneLayout->setAsSection(false);

        $col = $this->addCol($zoneLayout);
        $name = $this->addBlock($col, 'form-text', null, null, 1, 6, true);
        $this->addFieldConfiguration($name, 'Nom', 'Saisissez votre nom', true, false, 'Veuillez saisir votre nom.', 'lastname')->setAnonymize(true);
        $firstName = $this->addBlock($col, 'form-text', null, null, 2, 6, true);
        $this->addFieldConfiguration($firstName, 'Prénom', 'Saisissez votre prénom', true, false, 'Veuillez saisir votre prénom.', 'firstname')->setAnonymize(true);
        $email = $this->addBlock($col, 'form-email', null, null, 3, 6, true);
        $this->addFieldConfiguration($email, 'Email', 'Saisissez votre email', true, false, 'Veuillez saisir votre email.', 'email')->setAnonymize(true);
        $phone = $this->addBlock($col, 'form-phone', null, null, 4, 6, true);
        $this->addFieldConfiguration($phone, 'Téléphone', 'Saisissez votre numéro de téléphone', true, false, 'Veuillez saisir votre numéro de téléphone.', 'phone')->setAnonymize(true);
        $message = $this->addBlock($col, 'form-textarea', null, null, 5);
        $this->addFieldConfiguration($message, 'Message', 'Saisissez votre message', true, false, 'Veuillez saisir un message.', 'message');
        $gdpr = $this->addBlock($col, 'form-gdpr', null, null, 6);
        $this->addFieldConfiguration($gdpr, 'RGPD', "J'accepte que mes données soient utilisées pour me recontacter dans le cadre de cette demande.", true, true, 'Veuillez accépter.', 'gdpr');
        $submit = $this->addBlock($col, 'form-submit', null, null, 7);
        $this->addFieldConfiguration($submit, 'Envoyer', 'Saisissez votre message', true, false, null, 'submit');

        $form->setLayout($formLayout);

        $this->coreLocator->em()->persist($form);
        if ($this->flush) {
            $this->coreLocator->em()->flush();
        }

        $this->layoutManager->setGridZone($formLayout);

        $col = $this->addCol($zone, 1, 8);
        $col->setPaddingRight('pe-lg');
        $block = $this->addBlock($col, 'core-action', 'form-view', $form->getId());
        $block->setPaddingLeft('ps-0');
        $block->setPaddingRight('pe-0');
    }

    /**
     * To set Form.
     */
    public function setForm(string $name, string $slug): FormEntity\Form
    {
        $form = new FormEntity\Form();
        $form->setWebsite($this->website);
        $form->setAdminName($name);
        $form->setSlug($slug);

        $configuration = new FormEntity\Configuration();
        $configuration->setSecurityKey($this->coreLocator->alphanumericKey(10));
        $form->setConfiguration($configuration);

        return $form;
    }

    /**
     * Add contact information to contact Page.
     *
     * @throws Exception
     */
    private function addInformation(Layout\Zone $zone): void
    {
        $col = $this->addCol($zone, 2, 4);
        $block = $this->addBlock($col, 'core-action', 'information-view');
        $block->setPaddingLeft('ps-0');
        $block->setPaddingRight('pe-0');
    }

    /**
     * Add Map to contact Page.
     */
    private function addMap(Layout\Layout $layout): void
    {
        $addressData = !empty($this->yamlConfiguration['addresses'][$this->locale][0]) ? $this->yamlConfiguration['addresses'][$this->locale][0] : [];

        if (!empty($addressData)) {

            $map = new MapEntity\Map();
            $map->setAdminName('Carte page de contact');
            $map->setWebsite($this->website);
            $map->setAsDefault(true);
            $map->setSlug('contact');

            $address = new MapEntity\Address();
            $address->setName($addressData['name']);
            $address->setLatitude($addressData['latitude']);
            $address->setLongitude($addressData['longitude']);
            $address->setAddress($addressData['address']);
            $address->setZipCode($addressData['zipcode']);
            $address->setCity($addressData['city']);
            $address->setDepartment($addressData['department']);
            $address->setRegion($addressData['region']);
            $address->setCountry($addressData['country']);
            $address->setGoogleMapUrl($addressData['googleMapUrl']);
            $address->setGoogleMapDirectionUrl($addressData['googleMapDirectionUrl']);

            $point = new MapEntity\Point();
            $point->setAdminName('Point principal');
            $point->setMarker('/uploads/'.$this->website->getUploadDirname().'/marker-blue.svg');
            $point->setAddress($address);

            $map->addPoint($point);

            $this->coreLocator->em()->persist($map);
            if ($this->flush) {
                $this->coreLocator->em()->flush();
            }

            $zone = $this->addZone($layout, 3, true, true);
            $col = $this->addCol($zone);
            $col->setPaddingRight('pe-0');
            $col->setPaddingLeft('ps-0');

            $block = $this->addBlock($col, 'core-action', 'map-view', $map->getId());
            $block->setPaddingRight('pe-0');
            $block->setPaddingLeft('ps-0');
        }
    }

    /**
     * Add Action.
     */
    private function addAction(Layout\Block $block, ?string $blockTypeSlug = null, ?string $actionSlug = null, ?int $actionFilter = null): void
    {
        if ($blockTypeSlug) {
            $blockType = $this->coreLocator->em()->getRepository(Layout\BlockType::class)->findOneBy(['slug' => $blockTypeSlug]);
            $block->setBlockType($blockType);

            $intlBlockTypes = ['text', 'title', 'link', 'media', 'alert'];

            if (in_array($blockTypeSlug, $intlBlockTypes)) {
                $intl = 'media' === $blockTypeSlug ? new MediaRelationIntl() : new Layout\BlockIntl();
                $intl->setLocale($this->locale);
                $intl->setWebsite($this->website);
                $intl->setTitle($this->faker->text(30));

                if ('text' === $blockTypeSlug) {
                    $intl->setSubTitle($this->faker->text(20));
                    $intl->setBody('<p>'.$this->faker->text(300).'</p><p>'.$this->faker->text(200).'</p>');
                    $intl->setIntroduction($this->faker->text(100));
                }

                if ('text' === $blockTypeSlug || 'link' === $blockTypeSlug) {
                    $intl->setTargetLink('https://www.felix-creation.fr/');
                    $intl->setNewTab(true);
                }

                if ('media' !== $blockTypeSlug) {
                    $block->addIntl($intl);
                }

                if ('media' === $blockTypeSlug) {
                    $media = $this->coreLocator->em()->getRepository(Media::class)->findOneBy([
                        'website' => $this->website,
                        'category' => 'share',
                    ]);
                    $mediaRelation = new Layout\BlockMediaRelation();
                    $mediaRelation->setLocale($this->locale);
                    $mediaRelation->setMedia($media);
                    $mediaRelation->setIntl($intl);
                    // Défaut : pas de popup ni de téléchargement sur les images générées
                    // (la page `components`, conservée et non régénérée, garde les siens).
                    $mediaRelation->setPopup(false);
                    $mediaRelation->setDownloadable(false);
                    $block->addMediaRelation($mediaRelation);
                }
            }
        }

        if ($actionSlug) {
            $action = $this->coreLocator->em()->getRepository(Layout\Action::class)->findOneBy(['slug' => $actionSlug]);
            $block->setAction($action);
        }

        if ($actionFilter) {
            $actionIntl = new Layout\ActionIntl();
            $actionIntl->setLocale($this->locale);
            $actionIntl->setBlock($block);
            $actionIntl->setBlock($block);
            $actionIntl->setActionFilter($actionFilter);
            $block->addActionIntl($actionIntl);
        }
    }

    /**
     * Add FieldConfiguration.
     */
    public function addFieldConfiguration(
        Layout\Block $block,
        string $label,
        ?string $placeholder = null,
        bool $required = true,
        bool $smallSize = false,
        ?string $error = null,
        ?string $slug = null,
    ): Layout\FieldConfiguration {
        $blockTypeSlug = $block->getBlockType()->getSlug();

        $intl = new Layout\BlockIntl();
        $intl->setLocale($this->locale);
        $intl->setWebsite($this->website);

        if ('form-gdpr' !== $blockTypeSlug) {
            $intl->setTitle($label);
            $intl->setPlaceholder($placeholder);
            $intl->setError($error);
        }

        $slug = $slug ?: Urlizer::urlize($label);
        $configuration = new Layout\FieldConfiguration();
        $configuration->setRequired($required);
        $configuration->setSmallSize($smallSize);
        $configuration->setBlock($block);
        $configuration->setSlug($slug);

        if ('form-gdpr' === $blockTypeSlug) {
            $configuration->setExpanded(true);
            $configuration->setMultiple(true);

            $valueIntl = new Layout\FieldValueIntl();
            $valueIntl->setLocale($this->locale);
            $valueIntl->setIntroduction($placeholder);
            $valueIntl->setBody('true');
            $valueIntl->setWebsite($this->website);

            $value = new Layout\FieldValue();
            $value->setAdminName($placeholder);
            $value->addIntl($valueIntl);

            $configuration->addFieldValue($value);
        }

        $block->setAdminName($label);
        $block->addIntl($intl);
        $block->setFieldConfiguration($configuration);

        $this->coreLocator->em()->persist($block);

        return $configuration;
    }

    /**
     * To set Website.
     */
    public function setWebsite(Website $website): void
    {
        $this->website = $website;
    }

    /**
     * To set locale.
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }
}
