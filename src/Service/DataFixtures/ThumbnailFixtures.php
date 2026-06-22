<?php

declare(strict_types=1);

namespace App\Service\DataFixtures;

use App\Entity\Core\Website;
use App\Entity\Layout as LayoutEntities;
use App\Entity\Media as MediaEntities;
use App\Entity\Module\Catalog as CatalogEntities;
use App\Entity\Module\Newscast as NewscastEntities;
use App\Entity\Module\Slider\Slider;
use App\Entity\Security\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * ThumbnailFixtures.
 *
 * Thumbnail Fixtures management
 *
 * @author Sébastien FOURNIER <fournier.sebastien@outlook.com>
 */
#[Autoconfigure(tags: [
    ['name' => ThumbnailFixtures::class, 'key' => 'thumbnail_fixtures'],
])]
class ThumbnailFixtures
{
    private ?User $user;
    private Website $website;
    private int $position = 1;

    /**
     * ThumbnailFixtures constructor.
     */
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Add ThumbnailFixtures.
     */
    public function add(Website $website, ?User $user = null, ?Website $websiteToDuplicate = null): void
    {
        $this->user = $user;
        $this->website = $website;
        if ($websiteToDuplicate instanceof Website) {
            $this->addDbThumbs($websiteToDuplicate);
        } else {
            $this->addThumbs();
        }
    }

    /**
     * To add DB ThumbConfiguration.
     */
    private function addDbThumbs(Website $websiteToDuplicate): void
    {
        $thumbs = $this->entityManager->getRepository(MediaEntities\ThumbConfiguration::class)->findBy(['configuration' => $websiteToDuplicate->getConfiguration()]);
        foreach ($thumbs as $referThumb) {
            $configuration = new MediaEntities\ThumbConfiguration();
            $configuration->setAdminName($referThumb->getAdminName());
            $configuration->setWidth($referThumb->getWidth());
            $configuration->setHeight($referThumb->getHeight());
            $configuration->setConfiguration($this->website->getConfiguration());
            $configuration->setPosition($referThumb->getPosition());
            $configuration->setScreen($referThumb->getScreen());
            $configuration->setCreatedBy($this->user);
            foreach ($referThumb->getActions() as $referAction) {
                $action = new MediaEntities\ThumbAction();
                $action->setAdminName($referAction->getAdminName());
                $action->setNamespace($referAction->getNamespace());
                $action->setAction($referAction->getAction());
                $action->setCreatedBy($this->user);
                $action->setBlockType($referAction->getBlockType());
                if ($referAction->getActionFilter() && !is_numeric($referAction->getActionFilter())) {
                    $action->setActionFilter($referAction->getActionFilter());
                }
                $configuration->addAction($action);
            }
            $this->entityManager->persist($configuration);
        }
    }

    /**
     * To add ThumbConfiguration.
     */
    private function addThumbs(): void
    {
        $teaser = $this->entityManager->getRepository(NewscastEntities\Teaser::class)->findOneBy(['website' => $this->website]);
        $heroSlider = $this->entityManager->getRepository(Slider::class)->findOneBy(['website' => $this->website, 'slug' => 'home-hero']);
        $getawaySlider = $this->entityManager->getRepository(Slider::class)->findOneBy(['website' => $this->website, 'slug' => 'home-getaway']);
        $workspacesSlider = $this->entityManager->getRepository(Slider::class)->findOneBy(['website' => $this->website, 'slug' => 'home-workspaces']);
        $universeSlider = $this->entityManager->getRepository(Slider::class)->findOneBy(['website' => $this->website, 'slug' => 'home-universe']);
        $spaSlider = $this->entityManager->getRepository(Slider::class)->findOneBy(['website' => $this->website, 'slug' => 'home-spa-services']);
        $headerTitle = $this->entityManager->getRepository(LayoutEntities\BlockType::class)->findOneBy(['slug' => 'title-header']);
        $this->addConfig('Thumbnail 443 x 300', 443, 300, 'Liste des actualités', NewscastEntities\Newscast::class, 'index');
        $this->addConfig('Thumbnail 468 x Infinite', 468, null, 'Actualité mise en avant index', NewscastEntities\Newscast::class, 'index', 'first-newscast-index');
        $this->addConfig('Thumbnail 443 x 275', 443, 275, "Teaser d'actualités associées", NewscastEntities\Newscast::class, 'view', 'associated-newscast');
        if ($teaser instanceof NewscastEntities\Teaser) {
            $this->addConfig('Thumbnail 664 x 492', 664, 492, "Teaser d'actualités accueil", NewscastEntities\Newscast::class, 'teaser', $teaser->getId());
        }
        $this->addConfig('Thumbnail 456 x 300', 456, 300, 'Fiche actualité', NewscastEntities\Newscast::class, 'view');
        // Hero plein écran (100vh) : grands formats paysage/portrait, hauteur fixe pour le cover.
        if ($heroSlider instanceof Slider) {
            $this->addConfig('Thumbnail 1920 x 1080', 1920, 1080, 'Hero accueil', Slider::class, 'view', $heroSlider->getId(), 'desktop', true);
            $this->addConfig('Thumbnail 1280 x 1200', 1280, 1200, 'Hero accueil', Slider::class, 'view', $heroSlider->getId(), 'tablet', true);
            $this->addConfig('Thumbnail 768 x 1024', 768, 1024, 'Hero accueil', Slider::class, 'view', $heroSlider->getId(), 'mobile', true);
        }
        // Bande parenthèse (image plein écran, overlay).
        if ($getawaySlider instanceof Slider) {
            $this->addConfig('Thumbnail 1920 x 900', 1920, 900, 'Bande parenthèse accueil', Slider::class, 'view', $getawaySlider->getId(), 'desktop', true);
            $this->addConfig('Thumbnail 1280 x 760', 1280, 760, 'Bande parenthèse accueil', Slider::class, 'view', $getawaySlider->getId(), 'tablet', true);
            $this->addConfig('Thumbnail 768 x 620', 768, 620, 'Bande parenthèse accueil', Slider::class, 'view', $getawaySlider->getId(), 'mobile', true);
        }
        // Bande workspaces (image plein écran, overlay).
        if ($workspacesSlider instanceof Slider) {
            $this->addConfig('Thumbnail 1920 x 900', 1920, 900, 'Bande workspaces accueil', Slider::class, 'view', $workspacesSlider->getId(), 'desktop', true);
            $this->addConfig('Thumbnail 1280 x 760', 1280, 760, 'Bande workspaces accueil', Slider::class, 'view', $workspacesSlider->getId(), 'tablet', true);
            $this->addConfig('Thumbnail 768 x 620', 768, 620, 'Bande workspaces accueil', Slider::class, 'view', $workspacesSlider->getId(), 'mobile', true);
        }
        // Cartes univers (aspect 510/456 dans le SCSS).
        if ($universeSlider instanceof Slider) {
            $this->addConfig('Thumbnail 510 x 456', 510, 456, 'Cartes univers accueil', Slider::class, 'view', $universeSlider->getId(), 'desktop', true);
            $this->addConfig('Thumbnail 483 x 432', 483, 432, 'Cartes univers accueil', Slider::class, 'view', $universeSlider->getId(), 'tablet', true);
            $this->addConfig('Thumbnail 456 x 408', 456, 408, 'Cartes univers accueil', Slider::class, 'view', $universeSlider->getId(), 'mobile', true);
        }
        // Cartes services spa (4 cartes portrait).
        if ($spaSlider instanceof Slider) {
            $this->addConfig('Thumbnail 420 x 520', 420, 520, 'Cartes spa accueil', Slider::class, 'view', $spaSlider->getId(), 'desktop', true);
            $this->addConfig('Thumbnail 380 x 470', 380, 470, 'Cartes spa accueil', Slider::class, 'view', $spaSlider->getId(), 'tablet', true);
            $this->addConfig('Thumbnail 456 x 520', 456, 520, 'Cartes spa accueil', Slider::class, 'view', $spaSlider->getId(), 'mobile', true);
        }
        $this->addConfig('Thumbnail 1920 x 300', 1920, 300, 'Block entête', LayoutEntities\Block::class, 'block', $headerTitle);
        $this->addConfig('Thumbnail 991 x 300', 991, 300, 'Block entête', LayoutEntities\Block::class, 'block', $headerTitle, 'tablet');
        $this->addConfig('Thumbnail 412 x 350', 412, 350, 'Block entête', LayoutEntities\Block::class, 'block', $headerTitle, 'mobile');
        $this->addConfig('Thumbnail 1920 x 650', 1920, 650, 'Block entête large', LayoutEntities\Block::class, 'block', $headerTitle, 'large');
        $this->addConfig('Thumbnail 363 x 240', 363, 240, 'Teaser de produits', CatalogEntities\Product::class, 'teaser');
        $this->addConfig('Thumbnail 456 x 300', 456, 300, 'Liste des produits', CatalogEntities\Product::class, 'index');
        $this->addConfig('Thumbnail 456 x 300', 456, 300, 'Fiche produit', CatalogEntities\Product::class, 'view');
        $this->addConfig('Thumbnail 1920 x 500', 1920, 500, 'Teaser de produits carrousel', CatalogEntities\Product::class, 'teaser');
        $this->addConfig('Thumbnail 443 x 275', 443, 275, 'Teaser de produits associés', CatalogEntities\Product::class, 'view', 'associated-catalogproduct');
        $this->addConfig('Infinite');
    }

    /**
     * Add configuration.
     */
    private function addConfig(
        string $thumbConfigName,
        ?int $width = null,
        ?int $height = null,
        ?string $thumbActionName = null,
        ?string $classname = null,
        ?string $actionName = null,
        mixed $filter = null,
        string $screen = 'desktop',
        bool $fixedHeight = false): void
    {
        $configurations = $this->entityManager->getRepository(MediaEntities\ThumbConfiguration::class)->findBy([
            'configuration' => $this->website->getConfiguration(),
            'width' => $width,
            'height' => $height,
            'screen' => $screen,
        ]);

        if (!empty($configurations[0])) {
            $configuration = $configurations[0];
        } else {
            $configuration = new MediaEntities\ThumbConfiguration();
            $configuration->setAdminName($thumbConfigName);
            $configuration->setWidth($width);
            $configuration->setHeight($height);
            $configuration->setFixedHeight($fixedHeight);
            $configuration->setConfiguration($this->website->getConfiguration());
            $configuration->setPosition($this->position);
            $configuration->setCreatedBy($this->user);
            if ('large' !== $screen) {
                $configuration->setScreen($screen);
            }
            ++$this->position;
        }

        if ('Infinite' === $thumbConfigName) {
            $this->addThumbConfiguration($configuration, 'Page', LayoutEntities\Page::class);
            $this->addThumbConfiguration($configuration, 'Carrousel', Slider::class);
            $blockMedia = $this->entityManager->getRepository(LayoutEntities\BlockType::class)->findOneBy(['slug' => 'media']);
            $this->addThumbConfiguration($configuration, 'Bloc média', LayoutEntities\Block::class, 'block', $blockMedia);
            $blockCard = $this->entityManager->getRepository(LayoutEntities\BlockType::class)->findOneBy(['slug' => 'card']);
            $this->addThumbConfiguration($configuration, 'Bloc mini fiche', LayoutEntities\Block::class, 'block', $blockCard);
        } else {
            $this->addThumbConfiguration($configuration, $thumbActionName, $classname, $actionName, $filter, $screen);
        }

        $this->entityManager->persist($configuration);
    }

    /**
     * Add ThumbConfiguration.
     */
    private function addThumbConfiguration(
        MediaEntities\ThumbConfiguration $configuration,
        ?string $thumbActionName = null,
        ?string $classname = null,
        ?string $actionName = null,
        mixed $filter = null,
        ?string $screen = null
    ): void {
        $action = new MediaEntities\ThumbAction();
        $action->setAdminName($thumbActionName);
        $action->setNamespace($classname);
        $action->setAction($actionName);
        $action->setCreatedBy($this->user);
        if (LayoutEntities\Block::class === $classname) {
            $action->setBlockType($filter);
        } else {
            $action->setActionFilter(strval($filter));
        }
        if ('large' === $screen) {
            $action->setActionFilter($screen);
        }
        $configuration->addAction($action);
    }
}
