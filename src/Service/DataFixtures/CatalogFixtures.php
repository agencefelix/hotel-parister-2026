<?php

declare(strict_types=1);

namespace App\Service\DataFixtures;

use App\Entity\Core\Website;
use App\Entity\Media as MediaEntities;
use App\Entity\Module\Catalog as CatalogEntities;
use App\Entity\Security\User;
use App\Entity\Seo\Url;
use App\Model\Module\ProductModel;
use App\Service\Content\LayoutGeneratorService;
use App\Service\Core\Urlizer;
use App\Service\Interface\CoreLocatorInterface;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Finder\Finder;

/**
 * CatalogFixtures.
 *
 * Catalog Fixtures management
 *
 * @author Sébastien FOURNIER <fournier.sebastien@outlook.com>
 */
#[Autoconfigure(tags: [
    ['name' => CatalogFixtures::class, 'key' => 'catalog_fixtures'],
])]
class CatalogFixtures
{
    private const int LIMIT = 15;
    /**
     * Caractéristiques réelles d'une chambre d'hôtel (source : maquette Figma product-view
     * + prod hotelparister.com/chambres-suites, section « Services »). Feature => valeurs possibles.
     */
    private const array FEATURES = [
        'Superficie' => ['17 m²', '20 m²', '22 m²', '28 m²', '30 m²', '40 m²', '52 m²'],
        'Capacité' => ['1 à 2 personnes', '2 à 3 personnes', '3 à 4 personnes'],
        'Vue' => ['Sur cour', 'Sur rue calme', 'Sur les toits de Paris'],
        'Terrasse' => ['Avec terrasse privative', 'Sans terrasse'],
        'Équipements' => ['Wi-Fi gratuit', 'Climatisation', 'Minibar', 'Coffre-fort', 'TV écran plat', 'Machine à café', 'Peignoirs & chaussons', 'Sèche-cheveux'],
        'Services inclus' => ['Accès hammam', 'Accès salle de sport', 'Accès piscine', 'Petit-déjeuner', 'Room service 24h/24'],
    ];
    /** Équipements communs à toutes les chambres. */
    private const array COMMON_EQUIPMENTS = ['Wi-Fi gratuit', 'Climatisation', 'Minibar', 'Coffre-fort', 'TV écran plat', 'Machine à café', 'Peignoirs & chaussons', 'Sèche-cheveux'];

    /** Nom du catalogue selon le CONTEXTE du projet (ici : un hôtel → les chambres). */
    private const string CATALOG_NAME = 'Chambres & Suites';
    /** Tous les slugs (identifiants) générés sont en ANGLAIS ; les codes URL suivent la prod. */
    private const string LISTING_SLUG = 'rooms';
    /** Slugs anglais des features (la convention impose des slugs EN). */
    private const array FEATURE_SLUGS = [
        'Superficie' => 'surface',
        'Capacité' => 'capacity',
        'Vue' => 'view',
        'Terrasse' => 'terrace',
        'Équipements' => 'equipment',
        'Services inclus' => 'included-services',
    ];

    /** Chambres & suites réelles du Parister (source : prod hotelparister.com/chambres-suites). Slugs EN. */
    private const array ROOMS = [
        ['title' => 'Chambre Supérieure', 'slug' => 'superior-room', 'surface' => '17 m²', 'capacite' => '1 à 2 personnes', 'vue' => 'Sur cour', 'terrasse' => false, 'services' => ['Accès hammam', 'Accès salle de sport'], 'intro' => 'Idéale pour une personne ou un couple à Paris. Accès hammam et salle de sport inclus.'],
        ['title' => 'Chambre Deluxe', 'slug' => 'deluxe-room', 'surface' => '20 m²', 'capacite' => '1 à 2 personnes', 'vue' => 'Sur rue calme', 'terrasse' => false, 'services' => ['Accès hammam', 'Accès salle de sport'], 'intro' => 'Plus d\'espace et de lumière, dans une atmosphère intimiste et contemporaine.'],
        ['title' => 'Chambre Deluxe avec terrasse', 'slug' => 'deluxe-room-terrace', 'surface' => '22 m²', 'capacite' => '1 à 2 personnes', 'vue' => 'Sur les toits de Paris', 'terrasse' => true, 'services' => ['Accès hammam', 'Accès salle de sport'], 'intro' => 'Une chambre Deluxe prolongée d\'une terrasse privative sur les toits de Paris.'],
        ['title' => 'Junior Suite', 'slug' => 'junior-suite', 'surface' => '28 m²', 'capacite' => '2 à 3 personnes', 'vue' => 'Sur cour', 'terrasse' => false, 'services' => ['Accès hammam', 'Accès salle de sport', 'Accès piscine'], 'intro' => 'Un coin salon distinct pour profiter pleinement de votre séjour parisien.'],
        ['title' => 'Junior Suite Terrasse', 'slug' => 'junior-suite-terrace', 'surface' => '30 m²', 'capacite' => '2 à 3 personnes', 'vue' => 'Sur les toits de Paris', 'terrasse' => true, 'services' => ['Accès hammam', 'Accès salle de sport', 'Accès piscine'], 'intro' => 'Le confort d\'une Junior Suite avec une terrasse privative.'],
        ['title' => 'Suite Duplex', 'slug' => 'duplex-suite', 'surface' => '40 m²', 'capacite' => '3 à 4 personnes', 'vue' => 'Sur les toits de Paris', 'terrasse' => false, 'services' => ['Accès hammam', 'Accès salle de sport', 'Accès piscine', 'Petit-déjeuner', 'Room service 24h/24'], 'intro' => 'Deux niveaux pour un séjour d\'exception au cœur du 9ᵉ arrondissement.'],
        ['title' => 'Suite Parister', 'slug' => 'parister-suite', 'surface' => '52 m²', 'capacite' => '3 à 4 personnes', 'vue' => 'Sur les toits de Paris', 'terrasse' => true, 'services' => ['Accès hammam', 'Accès salle de sport', 'Accès piscine', 'Petit-déjeuner', 'Room service 24h/24'], 'intro' => 'La plus grande suite de l\'hôtel : l\'expérience Parister dans sa forme la plus aboutie.'],
    ];
    private Generator $faker;
    private Website $website;
    private ?User $user;
    private string $locale = '';

    /**
     * CatalogFixtures constructor.
     */
    public function __construct(
        private readonly CoreLocatorInterface $coreLocator,
        private readonly LayoutGeneratorService $layoutGenerator,
        private readonly UploadedFileFixtures $uploadedFileFixtures,
    ) {
    }

    /**
     * Add Product.
     *
     * @throws \Exception
     */
    public function add(Website $website, ?User $user = null): void
    {
        if (count($this->coreLocator->em()->getRepository(CatalogEntities\Product::class)->findBy(['website' => $website])) > 0) {
            return;
        }

        $this->faker = Factory::create();
        $this->website = $website;
        $this->user = $user;
        $this->locale = $website->getConfiguration()->getLocale();

        if (empty($this->coreLocator->em()->getRepository(CatalogEntities\Feature::class)->findAll())) {
            $finder = Finder::create();
            $iconsDirname = $this->coreLocator->projectDir().'\public\medias\icons\light';
            $iconsDirname = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $iconsDirname);
            $icons = [];
            foreach ($finder->in($iconsDirname) as $file) {
                $icons[] = $file->getFilename();
            }
            $featurePosition = 0;
            foreach (self::FEATURES as $featureName => $values) {
                ++$featurePosition;
                $feature = new CatalogEntities\Feature();
                $feature->setAdminName($featureName);
                $feature->setSlug(self::FEATURE_SLUGS[$featureName] ?? Urlizer::urlize($featureName));
                $feature->setWebsite($website);
                $feature->setPosition($featurePosition);
                $feature->setCreatedBy($this->user);
                $feature->setCreatedAt(new \DateTime('now', new \DateTimeZone('Europe/Paris')));
                $this->generateIntl($featureName, $feature);
                // Pas de media-relation sur une caractéristique (ex. « 17 m² ») : inutile et coûteux.
                $valuePosition = 0;
                foreach ($values as $label) {
                    ++$valuePosition;
                    $featureValue = new CatalogEntities\FeatureValue();
                    $featureValue->setAdminName($label);
                    $featureValue->setSlug((self::FEATURE_SLUGS[$featureName] ?? Urlizer::urlize($featureName)).'-'.$valuePosition);
                    $featureValue->setWebsite($website);
                    $featureValue->setPosition($valuePosition);
                    if ($icons) {
                        $featureValue->setIconClass('/medias/icons/light/'.$icons[($featurePosition + $valuePosition) % count($icons)]);
                    }
                    $featureValue->setCreatedBy($this->user);
                    $featureValue->setCreatedAt(new \DateTime('now', new \DateTimeZone('Europe/Paris')));
                    $feature->addValue($featureValue);
                    $this->generateIntl($label, $featureValue);
                    $this->coreLocator->em()->persist($featureValue);
                }
                $this->coreLocator->em()->persist($feature);
            }
            $this->coreLocator->em()->flush(); // un seul flush pour tout le bloc features
            $this->coreLocator->em()->flush();
        }

        /* Index des valeurs réelles par [feature][label] pour l'affectation par chambre. */
        $valueIndex = [];
        foreach ($this->coreLocator->em()->getRepository(CatalogEntities\FeatureValue::class)->findAll() as $value) {
            $featureName = $value->getCatalogfeature()?->getAdminName();
            if ($featureName) {
                $valueIndex[$featureName][$value->getAdminName()] = $value;
            }
        }
        $catalog = $this->generateCatalog();
        $this->generateTeaser($catalog);

        $i = 0;
        foreach (self::ROOMS as $room) {
            ++$i;
            $title = $room['title'];
            $product = new CatalogEntities\Product();
            $product->setAdminName($title);
            $product->setPublicationStart(new \DateTime(sprintf('-%d days', rand(1, 100))));
            $product->setCatalog($catalog);
            $product->setWebsite($website);
            $product->setPosition($i);
            $product->setCreatedAt(new \DateTime('now', new \DateTimeZone('Europe/Paris')));
            $product->setCreatedBy($this->user);
            $this->generateIntl($title, $product, $room['intro'], '<p>'.$room['intro'].'</p><p>Superficie : '.$room['surface'].'.</p>');
            $this->generateMediaRelation($product, 'room-'.$i.'.jpg');
            $this->generateUrl($product, $room['slug']);
            $position = 0;
            foreach ($this->roomFeatureValues($room) as $featureName => $labels) {
                foreach ($labels as $label) {
                    $value = $valueIndex[$featureName][$label] ?? null;
                    if (!$value instanceof CatalogEntities\FeatureValue) {
                        continue;
                    }
                    ++$position;
                    $valueProduct = new CatalogEntities\FeatureValueProduct();
                    $valueProduct->setValue($value);
                    $valueProduct->setFeature($value->getCatalogfeature());
                    $valueProduct->setProduct($product);
                    $valueProduct->setCreatedBy($this->user);
                    $valueProduct->setPosition($position);
                    $valueProduct->setFeaturePosition($position);
                    $valueProduct->setCreatedAt(new \DateTime('now', new \DateTimeZone('Europe/Paris')));
                    $product->addValue($valueProduct);
                }
            }

            ProductModel::fromEntity($product, $this->coreLocator);
            $this->coreLocator->em()->persist($product);
        }

        $this->coreLocator->em()->flush();
    }

    /**
     * Caractéristiques réelles affectées à une chambre (feature => valeurs).
     *
     * @return array<string, string[]>
     */
    private function roomFeatureValues(array $room): array
    {
        return [
            'Superficie' => [$room['surface']],
            'Capacité' => [$room['capacite']],
            'Vue' => [$room['vue']],
            'Terrasse' => [$room['terrasse'] ? 'Avec terrasse privative' : 'Sans terrasse'],
            'Équipements' => self::COMMON_EQUIPMENTS,
            'Services inclus' => $room['services'],
        ];
    }

    /**
     * Generate Category.
     */
    private function generateCatalog(): CatalogEntities\Catalog
    {
        $catalog = new CatalogEntities\Catalog();
        $catalog->setAdminName(self::CATALOG_NAME);
        $catalog->setWebsite($this->website);
        $catalog->setCreatedBy($this->user);
        $this->coreLocator->em()->persist($catalog);
        $this->addListing($catalog);
        $this->generateLayout($catalog);

        return $catalog;
    }

    /**
     * Generate Listing.
     */
    private function addListing(CatalogEntities\Catalog $catalog): void
    {
        $listing = new CatalogEntities\Listing();
        $listing->addCatalog($catalog);
        $listing->setAdminName(self::CATALOG_NAME);
        $listing->setWebsite($this->website);
        $listing->setSlug(self::LISTING_SLUG);
        $listing->setCreatedBy($this->user);
        $this->coreLocator->em()->persist($listing);
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
        $intl->setWebsite($this->website);
        $intl->setIntroduction($introduction ?? $this->faker->text(150));
        $intl->setBody($body ?? $this->faker->text(600));
        $intl->setCreatedBy($this->user);
        $this->coreLocator->em()->persist($intl);
        $entity->addIntl($intl);
    }

    /**
     * Generate MediaRelation.
     */
    private function generateMediaRelation(mixed $entity, ?string $imageFilename = null): void
    {
        // Vraie image produit extraite de la maquette (sinon fallback média 'share').
        $media = null;
        if ($imageFilename) {
            $path = $this->coreLocator->projectDir().'/.claude/skills/figma-cms/integration/media/home/'.$imageFilename;
            $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            if (is_file($path)) {
                $media = $this->uploadedFileFixtures->uploadedFile($this->website, $path, $this->locale, null, null, null, $this->user);
                if ($media instanceof MediaEntities\Media) {
                    foreach ($media->getIntls() as $mediaIntl) {
                        $mediaIntl->setTitle('');
                    }
                }
            }
        }
        if (!$media instanceof MediaEntities\Media) {
            $media = $this->coreLocator->em()->getRepository(MediaEntities\Media::class)->findOneBy([
                'website' => $this->website,
                'category' => 'share',
            ]);
        }

        $mediaClassname = $this->coreLocator->metadata($entity, 'mediaRelations')->targetEntity;
        $mediaRelation = new $mediaClassname();
        $mediaRelation->setLocale($this->locale);
        $mediaRelation->setMedia($media);
        $mediaRelation->setMain(true); // média principal → alimente ViewModel.mainMedia (cartes/teaser).
        $mediaRelation->setPopup(false);
        $mediaRelation->setDownloadable(false);
        $entity->addMediaRelation($mediaRelation);
    }

    /**
     * Generate Url.
     */
    private function generateUrl(CatalogEntities\Product $product, ?string $code = null): void
    {
        $url = new Url();
        // Code URL = slug ANGLAIS de la chambre (pas d'URL prod dédiée par chambre).
        $url->setCode($code ?? Urlizer::urlize($product->getAdminName()));
        $url->setLocale($this->locale);
        $url->setOnline(true);
        $url->setWebsite($this->website);
        $url->setCreatedBy($this->user);
        $product->addUrl($url);
        $this->coreLocator->em()->persist($product);
    }

    /**
     * Generate Layout.
     */
    private function generateLayout(CatalogEntities\Catalog $catalog): void
    {
        $layout = $this->layoutGenerator->addLayout($this->website, [
            'adminName' => 'Fiche produit principale',
            'slug' => 'main-catalog',
            'catalog' => $catalog,
        ]);

        /** Title */
        $zoneEntitled = $this->layoutGenerator->addZone($layout, ['position' => 1, 'fullSize' => true, 'paddingTop' => 'pt-0', 'paddingBottom' => 'pb-0']);
        $col = $this->layoutGenerator->addCol($zoneEntitled, ['size' => 12, 'paddingRight' => 'pe-0', 'paddingLeft' => 'ps-0']);
        $block = $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-title-header']);
        $block->setPaddingRight('pe-0');
        $block->setPaddingLeft('ps-0');

        /** Content */
        $zoneContent = $this->layoutGenerator->addZone($layout, ['position' => 2, 'fullSize' => false, 'paddingTop' => null, 'paddingBottom' => null]);
        /** Content column one */
        $col = $this->layoutGenerator->addCol($zoneContent, ['size' => 6, 'paddingRight' => 'pe-md']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-published-date', 'size' => 6, 'miniPcSize' => 6, 'tabletSize' => 6, 'mobileSize' => 6, 'marginBottom' => 'mb-sm']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-share', 'size' => 6, 'miniPcSize' => 6, 'tabletSize' => 6, 'mobileSize' => 6, 'alignment' => 'end', 'marginBottom' => 'mb-sm']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-intro']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-body']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-link']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-back-button', 'marginTop' => 'mt-lg', 'hideMobile' => true, 'hideTablet' => true]);
        /** Content column two */
        $col = $this->layoutGenerator->addCol($zoneContent, ['size' => 6]);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-video']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-slider']);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-back-button', 'marginTop' => 'mt-md', 'hideMiniPc' => true, 'hideDesktop' => true]);
        /** Associated entities */
        $zoneAssociated = $this->layoutGenerator->addZone($layout, ['position' => 3, 'fullSize' => false, 'paddingTop' => null, 'paddingBottom' => null, 'backgroundColor' => 'bg-light']);
        $col = $this->layoutGenerator->addCol($zoneAssociated, ['size' => 12]);
        $this->layoutGenerator->addBlock($col, ['blockType' => 'layout-associated-entities']);

        $catalog->setLayout($layout);
    }

    /**
     * Generate Teaser.
     */
    private function generateTeaser(CatalogEntities\Catalog $catalog): void
    {
        $teaser = new CatalogEntities\Teaser();
        $teaser->setAdminName('Principal');
        $teaser->setWebsite($this->website);
        $teaser->setSlug('main');
        $teaser->setPromoteFirst(true);
        $teaser->setCreatedBy($this->user);
        $teaser->addCatalog($catalog);
        $this->coreLocator->em()->persist($teaser);
    }
}
