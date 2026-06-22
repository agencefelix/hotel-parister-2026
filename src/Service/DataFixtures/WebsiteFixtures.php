<?php

declare(strict_types=1);

namespace App\Service\DataFixtures;

use App\Entity\Core\Website;
use App\Entity\Security\User;
use App\Repository\Core\WebsiteRepository;
use App\Service\Development\EntityService;
use App\Service\Interface\DataFixturesInterface;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * WebsiteFixtures.
 *
 * WebsiteModel Fixtures management
 *
 * @author Sébastien FOURNIER <fournier.sebastien@outlook.com>
 */
#[Autoconfigure(tags: [
    ['name' => WebsiteFixtures::class, 'key' => 'website_fixtures'],
])]
class WebsiteFixtures
{
    private const bool DEV_MODE = false;
    private const bool GENERATE_TRANSLATIONS = false;
    private const array MAIN_PAGES = ['contact'];
    private const array DEFAULTS_MODULES = [
        'ROLE_EDIT',
        'ROLE_INFORMATION',
        'ROLE_PAGE',
        'ROLE_MEDIA',
        'ROLE_NEWSCAST',
        'ROLE_SEO',
        'ROLE_SLIDER',
        'ROLE_TRANSLATION',
        'ROLE_NAVIGATION',
        'ROLE_FORM',
        'ROLE_USERS',
        // Modules requis par la maquette Parister :
        'ROLE_CATALOG',     // chambres & suites (catalogue produits)
        'ROLE_GALLERY',     // galerie photos
        'ROLE_MAP',         // carte d'accès
        'ROLE_CONTACT',     // accès & contact
        'ROLE_NEWSLETTER',  // inscription newsletter (bloc layout)
        'ROLE_FAQ',         // questions fréquentes
    ];
    private const array OTHERS_MODULES = [
        'ROLE_SECURE_PAGE',
        'ROLE_STEP_FORM',
        'ROLE_TABLE',
        'ROLE_SITE_MAP',
        'ROLE_SOCIAL_WALL',
        'ROLE_TAB',
        'ROLE_SEARCH_ENGINE',
        'ROLE_AGENDA',
        'ROLE_PORTFOLIO',
        'ROLE_FORM_CALENDAR',
        'ROLE_TIMELINE',
        'ROLE_RECRUITMENT',
    ];

    private array $websites = [];
    private array $yamlConfiguration = [];

    /**
     * WebsiteFixtures constructor.
     */
    public function __construct(
        private readonly DataFixturesInterface $fixtures,
        private readonly EntityService $entityService,
        private readonly WebsiteRepository $websiteRepository,
        private readonly string $projectDir,
    ) {
    }

    /**
     * Get Yaml WebsiteModel configuration.
     *
     * @throws Exception
     */
    private function getYamlConfiguration(?string $yamlConfigDirname = null): void
    {
        $this->websites = $this->websiteRepository->findAll();
        $filesystem = new Filesystem();
        $configDirname = $this->projectDir.'/bin/data/config/';
        $configDirname = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configDirname);
        $configFileDirname = 0 === count($this->websites) ? $configDirname.'default.yaml' : ($yamlConfigDirname ? $configDirname.$yamlConfigDirname.'.yaml' : null);

        if ($configFileDirname && !is_dir($configFileDirname) && $filesystem->exists($configFileDirname)) {
            $configuration = Yaml::parseFile($configFileDirname);
            $this->yamlConfiguration = is_array($configuration) ? $configuration : $this->yamlConfiguration;
        }
    }

    /**
     * Initialize WebsiteModel.
     *
     * @throws Exception
     */
    public function initialize(
        Website $website,
        string $locale,
        ?User $user = null,
        ?string $yamlConfigDirname = null,
        ?Website $websiteToDuplicate = null,
    ): void {

        $this->getYamlConfiguration($yamlConfigDirname);

        $asMainWebsite = 0 === count($this->websites);

        if ($asMainWebsite) {
            $website->setActive(true);
        }

        $website->setCreatedBy($user);
        $website->setCacheClearDate(new \DateTime('now', new \DateTimeZone('Europe/Paris')));
        $website->setUploadDirname(uniqid());

        $pagesParams = $this->getPagesParams();
        $yamlConfiguration = $this->yamlConfiguration;
        $locale = !empty($yamlConfiguration['locale']) && $asMainWebsite ? $yamlConfiguration['locale'] : $locale;

        $this->fixtures->configuration()->add($website, $yamlConfiguration, $locale, self::DEV_MODE, self::DEFAULTS_MODULES, self::OTHERS_MODULES, $user, $websiteToDuplicate);
        $this->fixtures->security()->execute($website);
        $configuration = $website->getConfiguration();
        $this->fixtures->information()->add($website, $yamlConfiguration, $user);
        $this->fixtures->api()->add($website, $yamlConfiguration);
        $this->fixtures->seo()->add($website, $user);
        $webmasterFolder = $this->fixtures->defaultMedias()->add($website, $yamlConfiguration, $user);
        $this->fixtures->blockType()->add($configuration, self::DEV_MODE, $websiteToDuplicate);
        $this->fixtures->color()->add($configuration, $yamlConfiguration, $user, $websiteToDuplicate);
        $this->fixtures->transition()->add($configuration, $user, $websiteToDuplicate);
        if (in_array('ROLE_NEWSCAST', self::DEFAULTS_MODULES)) {
            $this->fixtures->newscast()->add($website, $user);
        }
        if (in_array('ROLE_CATALOG', self::DEFAULTS_MODULES)) {
            $this->fixtures->catalog()->add($website, $user);
        }
        $this->fixtures->newsletter()->add($website, $user);
        $pages = $asMainWebsite || !$websiteToDuplicate instanceof Website
                ? $this->fixtures->page()->add($website, $yamlConfiguration, $pagesParams, $user, true, self::MAIN_PAGES)
            : $this->fixtures->pageDuplication()->add($website, $websiteToDuplicate);
        $this->fixtures->layout()->add($configuration, self::DEV_MODE, self::DEFAULTS_MODULES, self::OTHERS_MODULES, $user, $websiteToDuplicate);
        $this->fixtures->menu()->add($website, $pages, $pagesParams, $user, $websiteToDuplicate);
        $this->fixtures->gdpr()->add($webmasterFolder, $website, $user);
        $this->fixtures->map()->add($webmasterFolder, $website, $user);
        $this->entityService->website($website);
        $this->entityService->createdBy($user);
        $this->entityService->execute($website, $locale);
        $this->fixtures->thumbnail()->add($website, $user, $websiteToDuplicate);
        $this->fixtures->command()->add($website, $user);
        if ($asMainWebsite && self::GENERATE_TRANSLATIONS) {
            $this->fixtures->translations()->generate($configuration, $this->websites);
        }
    }

    /**
     * Get Pages[] params.
     */
    private function getPagesParams(): array
    {
        // Pages de la maquette Parister (le menu principal/footer se déduit de 'menus').
        // CONVENTION : 'reference' = slug interne en ANGLAIS ; 'url' = code URL public = chemin de PROD
        // (continuité SEO). Si 'url' absent, le code est dérivé du slug anglais.
        return [
            ['name' => 'Accueil', 'asIndex' => true, 'reference' => 'home', 'url' => '', 'menus' => [], 'template' => 'home', 'urlAsIndex' => true, 'deletable' => true],
            ['name' => 'Chambres & Suites', 'asIndex' => false, 'reference' => 'products', 'url' => 'chambres-suites', 'menus' => ['main', 'footer'], 'template' => 'cms', 'urlAsIndex' => true, 'deletable' => true, 'disable' => !in_array('ROLE_CATALOG', self::DEFAULTS_MODULES)],
            ['name' => 'Restaurant & Bar à cocktails', 'asIndex' => false, 'reference' => 'restaurant', 'url' => 'restaurant-bar-a-cocktail', 'menus' => ['main', 'footer'], 'template' => 'cms', 'urlAsIndex' => true, 'deletable' => true],
            ['name' => 'Spa, Bien-être & Sport', 'asIndex' => false, 'reference' => 'spa', 'url' => 'sport-bien-etre', 'menus' => ['main', 'footer'], 'template' => 'cms', 'urlAsIndex' => true, 'deletable' => true],
            ['name' => 'Séminaires & Réunions', 'asIndex' => false, 'reference' => 'meetings', 'url' => 'salle-de-reunion-evenementiel', 'menus' => ['main', 'footer'], 'template' => 'cms', 'urlAsIndex' => true, 'deletable' => true],
            ['name' => 'La vie au Parister', 'asIndex' => false, 'reference' => 'news', 'url' => 'la-vie-au-parister', 'menus' => ['main'], 'template' => 'cms', 'urlAsIndex' => true, 'deletable' => true, 'disable' => !in_array('ROLE_NEWSCAST', self::DEFAULTS_MODULES)],
            ['name' => 'Galerie photos', 'asIndex' => false, 'reference' => 'gallery', 'url' => 'galerie-photos', 'menus' => ['main', 'footer'], 'template' => 'cms', 'urlAsIndex' => true, 'deletable' => true],
            ['name' => 'Presse', 'asIndex' => false, 'reference' => 'press', 'url' => 'presse', 'menus' => ['main', 'footer'], 'template' => 'cms', 'urlAsIndex' => true, 'deletable' => true],
            ['name' => 'Accès & Contact', 'asIndex' => false, 'reference' => 'contact', 'url' => 'acces-et-contact', 'menus' => ['main'], 'template' => 'cms', 'urlAsIndex' => true, 'deletable' => true],
            ['name' => 'Bons cadeaux', 'asIndex' => false, 'reference' => 'gift-cards', 'url' => 'bons-cadeaux', 'menus' => ['main'], 'template' => 'cms', 'urlAsIndex' => true, 'deletable' => true],
            // Pages légales : hors menu (rendues dans la barre basse du footer), restent des pages accessibles.
            ['name' => 'Plan de site', 'asIndex' => false, 'reference' => 'sitemap', 'url' => 'plan-du-site', 'menus' => [], 'template' => 'cms', 'urlAsIndex' => true, 'deletable' => true],
            ['name' => 'Mentions légales', 'asIndex' => false, 'reference' => 'legals', 'url' => 'mentions-legales', 'menus' => [], 'template' => 'legacy', 'urlAsIndex' => false, 'deletable' => true],
            ['name' => 'Politique relative aux cookies', 'asIndex' => false, 'reference' => 'cookies', 'url' => 'politique-cookies', 'menus' => [], 'template' => 'legacy', 'urlAsIndex' => false, 'deletable' => true],
            ['name' => 'Erreurs', 'asIndex' => false, 'reference' => 'error', 'url' => 'erreur', 'menus' => [], 'template' => 'error', 'urlAsIndex' => false, 'deletable' => false],
        ];
    }
}
