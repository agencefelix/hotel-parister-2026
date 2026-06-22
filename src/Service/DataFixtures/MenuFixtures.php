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
    private array $pagesParams = [];
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
            ['title' => 'Visites virtuelles', 'link' => '#'],
            ['title' => 'Carrière', 'link' => '#'],
            ['title' => 'Forstyle hotels collection', 'link' => '#'],
        ]],
        ['title' => 'Actualités', 'children' => [
            ['ref' => 'news', 'title' => 'La vie au Parister'],
            ['ref' => 'press', 'title' => 'Presse'],
            ['title' => 'Blog', 'link' => '#'],
        ]],
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
        $this->pagesParams = $pagesParams;
        $this->user = $user;

        if ($websiteToDuplicate instanceof Website) {
            $this->addDbMenus($websiteToDuplicate, $website);
        } else {
            $this->addMenu($website, 'Principal', 'main');
            $this->addMenu($website, 'Pied de page', 'footer');
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
     * Add Menu.
     */
    private function addMenu(Website $website, string $adminName, string $slug): void
    {
        $isMain = 'main' === $slug;
        $isFooter = 'footer' === $slug;
        // Nav principale = template "main" (mega-menu overlay Parister) ; footer = "footer".
        $template = str_contains($slug, 'footer') ? 'footer' : $slug;

        $menu = new MenuEntities\Menu();
        $menu->setAdminName($adminName);
        $menu->setSlug($slug);
        $menu->setTemplate($template);
        $menu->setMain($isMain);
        // Nav principale Parister : overlay ☰ permanent (hamburger à tous les breakpoints).
        if ($isMain) {
            $menu->setExpand('xxxl');
        }
        $menu->setFooter($isFooter);
        $menu->setWebsite($website);
        $menu->setFixedOnScroll($isMain);
        $menu->setPosition($this->position);
        $menu->setCreatedBy($this->user);
        if ($menu->isFooter()) {
            $menu->setAlignment('center');
        }

        $this->entityManager->persist($menu);
        if ($isMain) {
            $this->addMainGroups($menu);
        } else {
            $this->addLinks($menu);
        }
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

    /**
     * Add Link to menu.
     */
    private function addLinks(MenuEntities\Menu $menu): void
    {
        $position = 1;

        foreach ($this->pagesParams as $key => $params) {
            $params = (object) $params;
            $pageMenus = $params->menus;

            /** @var Page $page */
            $page = $this->pages[$params->reference] ?? null;

            if (in_array($menu->getSlug(), $pageMenus) && $page) {
                $link = new MenuEntities\Link();
                $link->setAdminName($page->getAdminName());
                $link->setMenu($menu);
                $link->setLocale($this->locale);
                $link->setPosition($position);

                $intl = new MenuEntities\LinkIntl();
                $intl->setTargetPage($page);
                $intl->setTitle($page->getAdminName());
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
    }
}
