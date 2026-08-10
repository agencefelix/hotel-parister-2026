<?php

declare(strict_types=1);

namespace App\Service\DataFixtures;

use App\Entity\Core\Website;
use App\Entity\Layout\Page;
use App\Entity\Module\Menu as MenuEntities;
use App\Entity\Security\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * MenuFixtures.
 *
 * Menu Fixtures management
 *
 * @author Sébastien FOURNIER <fournier.sebastien@outlook.com>
 */
#[Autoconfigure(tags: [
    ['name' => MenuFixtures::class, 'key' => 'menu_fixtures'],
])]
class MenuFixtures
{
    private string $locale = '';
    private array $pages = [];
    private ?User $user;
    private int $position = 1;

    /**
     * Mega-menu principal Parister : colonnes (titre de groupe) + enfants.
     * Enfant = ['ref' => slugInterne, 'title' => libellé] (page CMS) ou
     *          ['title' => libellé, 'link' => url] (lien externe/placeholder, page absente).
     * Libellés et structure conformes à la maquette (node 386:1793).
     *
     * @var array<int, array{title: string, children: array<int, array<string, string>>}>
     */
    private const MAIN_GROUPS = [
        ['title' => 'Hôtel', 'children' => [
            ['ref' => 'products', 'title' => 'Chambres & Suite'],
            ['ref' => 'spa', 'title' => 'Sport & bien-être'],
            ['ref' => 'meetings', 'title' => 'Salle de réunion & événementiel'],
            ['ref' => 'contact', 'title' => 'Accès et contact'],
        ]],
        ['title' => 'Les passerelles', 'children' => [
            ['ref' => 'restaurant', 'title' => 'Restaurant & bar à cocktails'],
        ]],
        ['title' => 'Utiles', 'children' => [
            ['ref' => 'gallery', 'title' => 'Galerie'],
            ['ref' => 'virtual-tours', 'title' => 'Visites virtuelles'],
            ['ref' => 'careers', 'title' => 'Carrière'],
            // Lien externe Parister : la locale courante est suffixée à l'URL (cf. localeSuffix).
            ['title' => 'Forstyle hotels collection', 'link' => 'https://www.forstyle-hotels.com/', 'localeSuffix' => true],
        ]],
        ['title' => 'Actualités', 'children' => [
            ['ref' => 'news', 'title' => 'La vie au Parister'],
            ['ref' => 'press', 'title' => 'Presse'],
            ['ref' => 'blog', 'title' => 'Blog'],
        ]],
    ];

    /**
     * Slug du menu footer ADMINISTRABLE par groupe de la maquette (node 1013:1897).
     * Un groupe = un menu distinct, géré indépendamment côté admin.
     * Footer = grille 2×2 : Hôtel / Utiles (haut), Les passerelles / Actualités (bas).
     *
     * @var array<string, string>
     */
    private const FOOTER_GROUP_SLUGS = [
        'Hôtel' => 'footer-hotel',
        'Les passerelles' => 'footer-passerelles',
        'Utiles' => 'footer-utiles',
        'Actualités' => 'footer-actualites',
    ];

    /**
     * Menu footer LÉGAL administrable (barre basse) = liens de pages.
     * Le « Gestion des cookies » (réouvre le consentement) et le crédit agence restent
     * des éléments SPÉCIAUX gérés par le template (non-pages, conditionnels) ; cf. règle cookies.
     *
     * @var array<int, array<string, string>>
     */
    private const FOOTER_LEGAL = [
        ['ref' => 'legals', 'title' => 'Mentions légales'],
        ['ref' => 'cookies', 'title' => 'Politique relative aux cookies'],
    ];

    /**
     * MenuFixtures constructor.
     */
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Add Menus.
     */
    public function add(Website $website, array $pages, array $pagesParams, ?User $user = null, ?Website $websiteToDuplicate = null): void
    {
        $this->locale = $website->getConfiguration()->getLocale();
        $this->pages = $pages;
        $this->user = $user;

        if ($websiteToDuplicate instanceof Website) {
            $this->addDbMenus($websiteToDuplicate, $website);
        } else {
            $this->addMenu($website, 'Principal', 'main');
            $this->addFooterMenus($website);
            $this->addFooterLegalMenu($website);
        }
    }

    /**
     * Crée le menu footer LÉGAL administrable (barre basse) : mentions, politique cookies…
     */
    private function addFooterLegalMenu(Website $website): void
    {
        $menu = new MenuEntities\Menu();
        $menu->setAdminName('Pied de page — légal');
        $menu->setSlug('footer-legal');
        $menu->setTemplate('footer');
        $menu->setMain(false);
        $menu->setFooter(true);
        $menu->setWebsite($website);
        $menu->setFixedOnScroll(false);
        $menu->setAlignment('start');
        $menu->setPosition($this->position);
        $menu->setCreatedBy($this->user);

        $this->entityManager->persist($menu);
        $this->addFooterGroupLinks($menu, self::FOOTER_LEGAL);
        ++$this->position;
    }

    /**
     * Crée un menu footer ADMINISTRABLE distinct par groupe de la maquette
     * (Hôtel, Les passerelles, Utiles, Actualités) : chaque colonne du pied de page
     * se gère indépendamment côté admin (réordonner/ajouter/retirer/renommer).
     */
    private function addFooterMenus(Website $website): void
    {
        foreach (self::MAIN_GROUPS as $group) {
            $slug = self::FOOTER_GROUP_SLUGS[$group['title']] ?? null;
            if (null === $slug) {
                continue;
            }

            $menu = new MenuEntities\Menu();
            $menu->setAdminName($group['title']);
            $menu->setSlug($slug);
            $menu->setTemplate('footer');
            $menu->setMain(false);
            $menu->setFooter(true);
            $menu->setWebsite($website);
            $menu->setFixedOnScroll(false);
            $menu->setAlignment('start');
            $menu->setPosition($this->position);
            $menu->setCreatedBy($this->user);

            $this->entityManager->persist($menu);
            $this->addFooterGroupLinks($menu, $group['children']);
            ++$this->position;
        }
    }

    /**
     * Liens (plats, niveau 1) d'un menu footer de groupe : page CMS (targetPage) ou lien externe
     * (avec suffixe de locale si demandé).
     *
     * @param array<int, array<string, string|bool>> $children
     */
    private function addFooterGroupLinks(MenuEntities\Menu $menu, array $children): void
    {
        $position = 1;

        foreach ($children as $childData) {
            $reference = $childData['ref'] ?? null;
            /** @var Page|null $page */
            $page = $reference ? ($this->pages[$reference] ?? null) : null;

            // Page absente et aucun lien externe : rien à pointer, on saute.
            if ($reference && !$page && empty($childData['link'])) {
                continue;
            }

            $title = $childData['title'] ?? ($page ? $page->getAdminName() : '');

            $link = new MenuEntities\Link();
            $link->setAdminName($title);
            $link->setMenu($menu);
            $link->setLocale($this->locale);
            $link->setLevel(1);
            $link->setPosition($position);

            $intl = new MenuEntities\LinkIntl();
            if ($page) {
                $intl->setTargetPage($page);
            } elseif (!empty($childData['link'])) {
                $url = $childData['link'];
                if (!empty($childData['localeSuffix'])) {
                    $url .= $this->locale;
                }
                $intl->setTargetLink($url);
            }
            $intl->setTitle($title);
            $intl->setLocale($this->locale);
            $intl->setLink($link);
            $intl->setCreatedBy($this->user);
            $intl->setWebsite($menu->getWebsite());

            $link->setIntl($intl);
            $link->setCreatedBy($this->user);

            $this->entityManager->persist($link);
            $this->entityManager->persist($intl);

            ++$position;
        }
    }

    /**
     * Add Refer DB Menus.
     */
    private function addDbMenus(Website $websiteToDuplicate, Website $website): void
    {
        $menus = $this->entityManager->getRepository(MenuEntities\Menu::class)->findBy(['website' => $websiteToDuplicate]);
        foreach ($menus as $referMenu) {
            $menu = new MenuEntities\Menu();
            $menu->setAdminName($referMenu->getAdminName());
            $menu->setSlug($referMenu->getSlug());
            $menu->setTemplate($referMenu->getTemplate());
            $menu->setMain($referMenu->isMain());
            $menu->setFooter($referMenu->isFooter());
            $menu->setWebsite($website);
            $menu->setPosition($referMenu->getPosition());
            $menu->setCreatedBy($this->user);
            $menu->setFixedOnScroll($referMenu->isFixedOnScroll());
            $this->entityManager->persist($menu);
            $this->entityManager->flush();
            $this->addDbLinks($referMenu, $menu);
        }
    }

    /**
     * Add Refer DB Links.
     */
    private function addDbLinks(MenuEntities\Menu $referMenu, MenuEntities\Menu $menu): void
    {
        $referLinks = $this->entityManager->getRepository(MenuEntities\Link::class)->findBy(['menu' => $referMenu], ['level' => 'ASC']);

        foreach ($referLinks as $referLink) {

            $link = new MenuEntities\Link();
            $link->setAdminName($referLink->getAdminName());
            $link->setMenu($menu);
            $link->setSlug($referLink->getSlug());
            $link->setLevel($referLink->getLevel());
            $link->setLocale($this->locale);
            $link->setPosition($referLink->getPosition());

            $parentLink = $referLink->getParent();
            if ($parentLink instanceof MenuEntities\Link) {
                $newParentLink = $this->entityManager->getRepository(MenuEntities\Link::class)->findOneBy(['menu' => $menu->getId(), 'slug' => $parentLink->getSlug(), 'level' => $parentLink->getLevel()]);
                $link->setParent($newParentLink);
            }

            $referIntl = $referLink->getIntl();
            $referPage = $referIntl->getTargetPage();
            $page = $referPage instanceof Page ? $this->entityManager->getRepository(Page::class)->findOneBy(['slug' => $referPage->getSlug(), 'website' => $menu->getWebsite()]) : null;

            $intl = new MenuEntities\LinkIntl();
            $intl->setLocale($this->locale);
            $intl->setTargetPage($page);
            $intl->setTargetLink($referIntl->getTargetLink());
            $intl->setTitle($referIntl->getTitle());
            $intl->setLink($link);
            $intl->setCreatedBy($this->user);
            $intl->setWebsite($menu->getWebsite());

            $link->setIntl($intl);
            $link->setCreatedBy($this->user);

            $this->entityManager->persist($link);
            $this->entityManager->persist($intl);
            $this->entityManager->flush();
        }
    }

    /**
     * Add main menu (mega-menu overlay Parister).
     */
    private function addMenu(Website $website, string $adminName, string $slug): void
    {
        $menu = new MenuEntities\Menu();
        $menu->setAdminName($adminName);
        $menu->setSlug($slug);
        $menu->setTemplate($slug);
        $menu->setMain(true);
        // Nav principale Parister : overlay ☰ permanent (hamburger à tous les breakpoints).
        $menu->setExpand('xxxl');
        $menu->setFooter(false);
        $menu->setWebsite($website);
        $menu->setFixedOnScroll(true);
        $menu->setPosition($this->position);
        $menu->setCreatedBy($this->user);

        $this->entityManager->persist($menu);
        $this->addMainGroups($menu);
        ++$this->position;
    }

    /**
     * Construit le mega-menu principal en colonnes (parent = titre de groupe, enfants = pages).
     */
    private function addMainGroups(MenuEntities\Menu $menu): void
    {
        $position = 1;

        foreach (self::MAIN_GROUPS as $group) {
            $parent = new MenuEntities\Link();
            $parent->setAdminName($group['title']);
            $parent->setMenu($menu);
            $parent->setLocale($this->locale);
            $parent->setLevel(1);
            $parent->setPosition($position);

            $parentIntl = new MenuEntities\LinkIntl();
            $parentIntl->setTitle($group['title']);
            $parentIntl->setLocale($this->locale);
            $parentIntl->setLink($parent);
            $parentIntl->setCreatedBy($this->user);
            $parentIntl->setWebsite($menu->getWebsite());

            $parent->setIntl($parentIntl);
            $parent->setCreatedBy($this->user);

            $this->entityManager->persist($parent);
            $this->entityManager->persist($parentIntl);

            $childPosition = 1;
            foreach ($group['children'] as $childData) {
                $reference = $childData['ref'] ?? null;
                /** @var Page|null $page */
                $page = $reference ? ($this->pages[$reference] ?? null) : null;

                // Enfant page CMS absente : on conserve le lien (placeholder) pour respecter la maquette.
                if ($reference && !$page && !isset($childData['link'])) {
                    continue;
                }

                $title = $childData['title'] ?? ($page ? $page->getAdminName() : '');

                $child = new MenuEntities\Link();
                $child->setAdminName($title);
                $child->setMenu($menu);
                $child->setLocale($this->locale);
                $child->setLevel(2);
                $child->setParent($parent);
                $child->setPosition($childPosition);

                $childIntl = new MenuEntities\LinkIntl();
                if ($page) {
                    $childIntl->setTargetPage($page);
                } elseif (isset($childData['link'])) {
                    $childIntl->setTargetLink($childData['link']);
                }
                $childIntl->setTitle($title);
                $childIntl->setLocale($this->locale);
                $childIntl->setLink($child);
                $childIntl->setCreatedBy($this->user);
                $childIntl->setWebsite($menu->getWebsite());

                $child->setIntl($childIntl);
                $child->setCreatedBy($this->user);

                $this->entityManager->persist($child);
                $this->entityManager->persist($childIntl);

                ++$childPosition;
            }

            ++$position;
        }
    }
}
