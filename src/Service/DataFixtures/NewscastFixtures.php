<?php

declare(strict_types=1);

namespace App\Service\DataFixtures;

use App\Entity\Core\Website;
use App\Entity\Media as MediaEntities;
use App\Entity\Module\Newscast as NewscastEntities;
use App\Entity\Security\User;
use App\Entity\Seo\Url;
use App\Service\Content\LayoutGeneratorService;
use App\Service\Core\Urlizer;
use App\Service\Interface\CoreLocatorInterface;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * NewscastFixtures.
 *
 * Newscast Fixtures management
 *
 * @author Sébastien FOURNIER <fournier.sebastien@outlook.com>
 */
#[Autoconfigure(tags: [
    ['name' => NewscastFixtures::class, 'key' => 'newscast_fixtures'],
])]
class NewscastFixtures
{
    private const int LIMIT = 15;
    /**
     * Actualités réelles "La vie au Parister" (source : prod hotelparister.com).
     * 'url' = code URL = chemin de PROD (continuité SEO).
     */
    private const array EVENTS = [
        ['title' => 'Hôtel Paris : concert Céline Dion à l\'Accor Arena', 'url' => 'hotel-paris-concert-celine-dion-accor-arena', 'intro' => 'L\'Hôtel Parister, votre adresse parisienne à deux pas de l\'Accor Arena.'],
        ['title' => 'Exposition « Distance » - Kevin Desca', 'url' => 'exposition-distance-kevin-desca', 'intro' => 'Une exposition photographique inédite dans les espaces du Parister.'],
        ['title' => 'Parenthèse littéraire au cœur de l\'hôtel Parister', 'url' => 'parenthese-litteraire', 'intro' => 'Rencontres, lectures et échanges autour du livre au cœur de l\'hôtel.'],
        ['title' => 'Morning Wellness Club - cours de yoga avec Clarisse Douat', 'url' => 'morning-wellness-club-cours-de-yoga-a-l-hotel-parister-avec-clarisse-douat', 'intro' => 'Commencez la journée en douceur avec nos séances de yoga.'],
        ['title' => '« All About Love » par Qin Han', 'url' => 'all-about-love-par-qin-han', 'intro' => 'Une exposition sensible présentée à l\'Hôtel Parister.'],
        ['title' => '31 décembre 2025', 'url' => '31-decembre-2025', 'intro' => 'Célébrez la Saint-Sylvestre à l\'Hôtel Parister.'],
        ['title' => 'Une piscine confidentielle au cœur de Paris', 'url' => 'une-piscine-confidentielle-au-coeur-de-paris', 'intro' => 'Plongez dans la piscine intimiste du spa Parister.'],
        ['title' => '« Connectés » par Yue Lingjun et Romain Ventura', 'url' => 'connectes-par-yue-lingjun-et-romain-ventura', 'intro' => 'Une exposition à quatre mains présentée au Parister.'],
        ['title' => 'Exposition « Âme d\'enfants » - Anne-Gaëlle Gillet', 'url' => 'exposition-anne-gaelle-gillet', 'intro' => 'L\'art contemporain s\'invite dans les murs du Parister.'],
        ['title' => 'Exposition « Les voiles de l\'aube » de Thomas Auriol', 'url' => 'exposition-auriol', 'intro' => 'Une série picturale présentée à l\'Hôtel Parister.'],
        ['title' => 'Élémentsbis - Yang Yi', 'url' => 'elementsbis-yang-yi', 'intro' => 'Une exposition signée Yang Yi au Parister.'],
        ['title' => 'Exposition « Les Italiens » par Sergio Marcelli', 'url' => 'exposition-les-italiens-par-sergio-marcelli', 'intro' => 'Un regard italien sur la création contemporaine.'],
        ['title' => 'Exposition de Huang Xiaoliang', 'url' => 'exposition-de-huang-xiaoliang', 'intro' => 'Le photographe Huang Xiaoliang expose au Parister.'],
        ['title' => 'Exposition de Capucine Néouze', 'url' => 'exposition-de-capucine-neouze', 'intro' => 'Les œuvres de Capucine Néouze investissent l\'hôtel.'],
        ['title' => 'Saint-Valentin au Parister', 'url' => 'saint-valentin-au-parister', 'intro' => 'Une parenthèse romantique au cœur du 9ᵉ arrondissement.'],
        ['title' => 'Une raclette comme à la montagne en plein Paris', 'url' => 'une-raclette-comme-a-la-montagne-en-plein-paris', 'intro' => 'Les Passerelles vous régalent d\'une raclette gourmande.'],
        ['title' => 'Flash Info Apicole', 'url' => 'flash-info-apicole', 'intro' => 'Des nouvelles de nos ruches installées sur les toits.'],
        ['title' => 'À l\'aube des Jeux Olympiques, Paris vous accueille', 'url' => 'paris-2024', 'intro' => 'Séjournez au Parister pendant les Jeux de Paris 2024.'],
        ['title' => '1, 2, 3… plongez', 'url' => '1-2-3-plongez', 'intro' => 'Profitez de la piscine et du spa de l\'hôtel.'],
        ['title' => 'Fais signe, une exposition animée au Parister', 'url' => 'fais-signe-une-exposition-animee-au-parister', 'intro' => 'Une exposition animée à découvrir dans l\'hôtel.'],
        ['title' => 'Nouveautés au déjeuner des Passerelles', 'url' => 'nouveautes-au-dejeuner-des-passerelles', 'intro' => 'Le restaurant Les Passerelles renouvelle sa carte du déjeuner.'],
        ['title' => 'Quand la boxe et l\'art se rencontrent au Parister', 'url' => 'la-boxe-et-l-art-se-rencontrent-au-parister', 'intro' => 'Un événement où sport et création se répondent.'],
        ['title' => 'Farniente sur les terrasses de l\'hôtel Parister', 'url' => 'farniente-sur-les-terrasses-de-l-hotel-parister', 'intro' => 'Profitez des terrasses de l\'hôtel aux beaux jours.'],
        ['title' => 'Où dormir à Paris à la rentrée', 'url' => 'ou-dormir-a-paris-a-la-rentree', 'intro' => 'Le Parister, votre adresse pour une rentrée parisienne.'],
        ['title' => 'Besoin d\'une salle de réunion à Paris', 'url' => 'besoin-d-une-salle-de-reunion-a-paris', 'intro' => 'Nos espaces séminaires et événementiels au cœur de Paris.'],
    ];
    private Generator $faker;
    private Website $website;
    private ?User $user;
    private string $locale = '';

    /**
     * NewscastFixtures constructor.
     */
    public function __construct(
        private readonly CoreLocatorInterface $coreLocator,
        private readonly LayoutGeneratorService $layoutGenerator,
        private readonly UploadedFileFixtures $uploadedFileFixtures,
    ) {
    }

    /**
     * Add News.
     *
     * @throws \Exception
     */
    public function add(Website $website, ?User $user = null): void
    {
        $this->faker = Factory::create();
        $this->website = $website;
        $this->user = $user;
        $this->locale = $website->getConfiguration()->getLocale();

        $category = $this->generateCategory();
        $this->generateTeaser($category);

        $i = 0;
        foreach (self::EVENTS as $event) {
            $title = $event['title'];
            $newscast = new NewscastEntities\Newscast();
            $newscast->setAdminName($title);
            $newscast->setPublicationStart(new \DateTime(sprintf('-%d days', rand(1, 100))));
            $newscast->setCategory($category);
            $newscast->setWebsite($website);
            $newscast->setPosition($i + 1);
            $newscast->setCreatedBy($this->user);
            $this->generateIntl($title, $newscast, $event['intro'], '<p>'.$event['intro'].'</p>');
            $this->generateMediaRelation($newscast, $event['url']);
            $this->generateUrl($newscast, $event['url']);
            ++$i;
        }
    }

    /**
     * Generate Category.
     */
    private function generateCategory(): NewscastEntities\Category
    {
        $category = new NewscastEntities\Category();
        $category->setAdminName('Principale');
        $category->setAsDefault(true);
        $category->setWebsite($this->website);
        $category->setSlug('main');
        $category->setCreatedBy($this->user);

        $this->coreLocator->em()->persist($category);

        $this->addListing($category);
        $this->generateLayout($category);

        $this->generateIntl('Principale', $category);

        return $category;
    }

    /**
     * Generate Listing.
     */
    private function addListing(NewscastEntities\Category $category): void
    {
        $listing = new NewscastEntities\Listing();
        $listing->addCategory($category);
        $listing->setAdminName('Principal');
        $listing->setLargeFirst(true);
        $listing->setScrollInfinite(false);
        $listing->setWebsite($this->website);
        $listing->setSlug('main');
        $listing->setCreatedBy($this->user);
        $this->coreLocator->em()->persist($listing);
    }

    /**
     * Generate MediaRelation.
     */
    private function generateMediaRelation(NewscastEntities\Newscast $newscast, ?string $slug = null): void
    {
        // Image principale réelle = .block_entete de la fiche prod (media/news/news-<slug>.jpg|png).
        $media = null;
        if ($slug) {
            $base = $this->coreLocator->projectDir().'/.claude/skills/figma-cms/integration/media/news/news-'.$slug;
            foreach (['.jpg', '.png', '.jpeg', '.webp'] as $ext) {
                $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $base.$ext);
                if (is_file($path)) {
                    $media = $this->uploadedFileFixtures->uploadedFile($this->website, $path, $this->locale, null, null, null, $this->user);
                    if ($media instanceof MediaEntities\Media) {
                        foreach ($media->getIntls() as $mediaIntl) {
                            $mediaIntl->setTitle('');
                        }
                    }
                    break;
                }
            }
        }
        if (!$media instanceof MediaEntities\Media) {
            $media = $this->coreLocator->em()->getRepository(MediaEntities\Media::class)->findOneBy([
                'website' => $this->website,
                'category' => 'share',
            ]);
        }

        $mediaRelation = new NewscastEntities\NewscastMediaRelation();
        $mediaRelation->setLocale($this->locale);
        $mediaRelation->setMedia($media);
        $mediaRelation->setMain(true); // média principal → alimente ViewModel.mainMedia (cartes/teaser).
        $mediaRelation->setPopup(false);
        $mediaRelation->setDownloadable(false);
        $newscast->addMediaRelation($mediaRelation);
    }

    /**
     * Generate Url.
     */
    private function generateUrl(NewscastEntities\Newscast $newscast, ?string $code = null): void
    {
        $url = new Url();
        // Code URL = chemin de PROD si fourni (continuité SEO), sinon dérivé du titre.
        $url->setCode($code ?? Urlizer::urlize($newscast->getAdminName()));
        $url->setLocale($this->locale);
        $url->setOnline(true);
        $url->setWebsite($this->website);
        $url->setCreatedBy($this->user);
        $newscast->addUrl($url);
        $this->coreLocator->em()->persist($newscast);
    }

    /**
     * Generate Layout.
     */
    private function generateLayout(NewscastEntities\Category $category): void
    {
        $layout = $this->layoutGenerator->addLayout($this->website, [
            'adminName' => 'Fiche actualité principale',
            'slug' => 'main-category',
            'newscastcategory' => $category,
        ]);

        /** Title */
        $zoneEntitled = $this->layoutGenerator->addZone($layout, ['position' => 1, 'fullSize' => true, 'paddingTop' => 'pt-0', 'paddingBottom' => 'pb-0']);
        $col = $this->layoutGenerator->addCol($zoneEntitled, ['size' => 12, 'paddingLeft' => 'ps-0', 'paddingRight' => 'pe-0']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-title-header', 'paddingLeft' => 'ps-0', 'paddingRight' => 'pe-0']);
        /** Content */
        $zoneContent = $this->layoutGenerator->addZone($layout, ['fullSize' => false, 'paddingTop' => null, 'paddingBottom' => null]);
        /** Content column one */
        $col = $this->layoutGenerator->addCol($zoneContent, ['position' => 2, 'size' => 6, 'paddingRight' => 'pe-md']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-published-date', 'size' => 6, 'miniPcSize' => 6, 'tabletSize' => 6, 'mobileSize' => 6, 'marginBottom' => 'mb-sm']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-share', 'size' => 6, 'miniPcSize' => 6, 'tabletSize' => 6, 'mobileSize' => 6, 'alignment' => 'end', 'marginBottom' => 'mb-sm']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-intro']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-body']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-link']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-back-button', 'marginTop' => 'mt-lg', 'hideMobile' => true, 'hideTablet' => true]);
        /** Content column two */
        $col = $this->layoutGenerator->addCol($zoneContent, ['position' => 3, 'size' => 6]);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-video']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-slider']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-back-button', 'marginTop' => 'mt-md', 'hideMiniPc' => true, 'hideDesktop' => true]);
        /** Associated entities */
        $zoneAssociated = $this->layoutGenerator->addZone($layout, ['position' => 4, 'fullSize' => false, 'paddingTop' => null, 'paddingBottom' => null, 'backgroundColor' => 'bg-light', 'colToRight' => true]);
        $col = $this->layoutGenerator->addCol($zoneAssociated, ['size' => 12]);
        $block = $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-associated-entities']);
        $block->setPaddingRight('pe-0');

        $category->setLayout($layout);
    }

    /**
     * Generate Teaser.
     */
    private function generateTeaser(NewscastEntities\Category $category): void
    {
        $teaser = new NewscastEntities\Teaser();
        $teaser->setAdminName('Principal');
        $teaser->setWebsite($this->website);
        $teaser->setSlug('main');
        $teaser->setPromoteFirst(true);
        $teaser->setCreatedBy($this->user);
        $teaser->addCategory($category);
        $this->coreLocator->em()->persist($teaser);
    }

    /**
     * Generate intl.
     */
    private function generateIntl(string $title, mixed $entity, ?string $introduction = null, ?string $body = null): void
    {
        $intlClassname = $this->coreLocator->metadata($entity, 'intls')->targetEntity;
        $intl = new $intlClassname();

        $intl->setLocale($this->locale);
        $intl->setTitle($title);
        $intl->setIntroduction($introduction ?? $this->faker->text(150));
        $intl->setBody($body ?? $this->faker->text(600));
        $intl->setCreatedBy($this->user);
        $intl->setWebsite($this->website);
        $this->coreLocator->em()->persist($intl);

        $entity->addIntl($intl);
    }
}
