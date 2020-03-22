<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Factory;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MainMenuDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\UserMenuDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\CrudUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MenuFactory
{
    public const ITEM_TYPE_CRUD = 'crud';
    public const ITEM_TYPE_DASHBOARD = 'dashboard';
    public const ITEM_TYPE_EXIT_IMPERSONATION = 'exit_impersonation';
    public const ITEM_TYPE_LOGOUT = 'logout';
    public const ITEM_TYPE_ROUTE = 'route';
    public const ITEM_TYPE_SECTION = 'section';
    public const ITEM_TYPE_SUBMENU = 'submenu';
    public const ITEM_TYPE_URL = 'url';

    private $adminContextProvider;
    private $authChecker;
    private $translator;
    private $urlGenerator;
    private $logoutUrlGenerator;
    private $crudRouter;

    public function __construct(AdminContextProvider $adminContextProvider, AuthorizationCheckerInterface $authChecker, TranslatorInterface $translator, UrlGeneratorInterface $urlGenerator, LogoutUrlGenerator $logoutUrlGenerator, CrudUrlGenerator $crudRouter)
    {
        $this->adminContextProvider = $adminContextProvider;
        $this->authChecker = $authChecker;
        $this->translator = $translator;
        $this->urlGenerator = $urlGenerator;
        $this->logoutUrlGenerator = $logoutUrlGenerator;
        $this->crudRouter = $crudRouter;
    }

    /**
     * @param MenuItem[] $menuItems
     */
    public function createMainMenu(array $menuItems, int $selectedIndex, int $selectedSubIndex): MainMenuDto
    {
        return new MainMenuDto($this->buildMenuItems($menuItems), $selectedIndex, $selectedSubIndex);
    }

    public function createUserMenu(UserMenu $userMenu): UserMenuDto
    {
        $userMenuDto = $userMenu->getAsDto();
        $builtUserMenuItems = $this->buildMenuItems($userMenuDto->getItems());

        return $userMenuDto->with([
            'items' => $builtUserMenuItems,
        ]);
    }

    /**
     * @param MenuItem[] $menuItems
     *
     * @return MenuItemDto[]
     */
    private function buildMenuItems(array $menuItems): array
    {
        $adminContext = $this->adminContextProvider->getContext();
        $defaultTranslationDomain = $adminContext->getI18n()->getTranslationDomain();
        $dashboardRouteName = $adminContext->getDashboardRouteName();

        $builtItems = [];
        /** @var MenuItem $menuItem */
        foreach ($menuItems as $i => $menuItem) {
            $menuItemDto = $menuItem->getAsDto();
            if (false === $this->authChecker->isGranted(Permission::EA_VIEW_MENU_ITEM, $menuItemDto)) {
                continue;
            }

            $subItems = [];
            /** @var MenuItem $menuSubItem */
            foreach ($menuItemDto->getSubItems() as $j => $menuSubItem) {
                $menuSubItemDto = $menuSubItem->getAsDto();
                if (false === $this->authChecker->isGranted(Permission::EA_VIEW_MENU_ITEM, $menuSubItemDto)) {
                    continue;
                }

                $subItems[] = $this->buildMenuItem($menuSubItemDto, [], $i, $j, $defaultTranslationDomain, $dashboardRouteName);
            }

            $builtItems[] = $this->buildMenuItem($menuItemDto, $subItems, $i, -1, $defaultTranslationDomain, $dashboardRouteName);
        }

        return $builtItems;
    }

    private function buildMenuItem(MenuItemDto $menuItemDto, array $subItemsContext, int $index, int $subIndex, string $defaultTranslationDomain, string $dashboardRouteName): MenuItemDto
    {
        $label = $this->translator->trans($menuItemDto->getLabel(), [], $menuItemDto->getTranslationDomain() ?? $defaultTranslationDomain);
        $url = $this->generateMenuItemUrl($menuItemDto, $dashboardRouteName, $index, $subIndex);

        return $menuItemDto->with([
            'index' => $index,
            'subIndex' => $subIndex,
            'label' => $label,
            'linkUrl' => $url,
            'subItems' => $subItemsContext,
        ]);
    }

    private function generateMenuItemUrl(MenuItemDto $menuItemDto, string $dashboardRouteName, int $index, int $subIndex): string
    {
        $menuItemType = $menuItemDto->getType();

        if (self::ITEM_TYPE_CRUD === $menuItemType) {
            // add the index and subIndex query parameters to display the selected menu item
            // remove the 'query' parameter to not perform a search query when clicking on menu items
            $defaultRouteParameters = ['menuIndex' => $index, 'submenuIndex' => $subIndex, 'query' => null];
            $routeParameters = array_merge($defaultRouteParameters, $menuItemDto->getRouteParameters());

            if (null === $routeParameters['crudController'] && null !== $entityFqcn = $routeParameters['entityFqcn']) {
                $controllerRegistry = $this->adminContextProvider->getContext()->getCrudControllers();
                $routeParameters['crudController'] = $controllerRegistry->getControllerFqcnByEntityFqcn($entityFqcn);
            }

            if (null !== $routeParameters['crudController']) {
                unset($routeParameters['entityFqcn']);
            }

            return $this->crudRouter->build()->setQueryParameters($routeParameters)->generateUrl();
        }

        if (self::ITEM_TYPE_DASHBOARD === $menuItemType) {
            return $this->urlGenerator->generate($dashboardRouteName);
        }

        if (self::ITEM_TYPE_ROUTE === $menuItemType) {
            // add the index and subIndex query parameters to display the selected menu item
            // remove the 'query' parameter to not perform a search query when clicking on menu items
            $defaultRouteParameters = ['menuIndex' => $index, 'submenuIndex' => $subIndex, 'query' => null];
            $routeParameters = array_merge($defaultRouteParameters, $menuItemDto->getRouteParameters());

            return $this->urlGenerator->generate($menuItemDto->getRouteName(), $routeParameters);
        }

        if (self::ITEM_TYPE_SECTION === $menuItemType) {
            return '#';
        }

        if (self::ITEM_TYPE_LOGOUT === $menuItemType) {
            return $this->logoutUrlGenerator->getLogoutPath();
        }

        if (self::ITEM_TYPE_EXIT_IMPERSONATION === $menuItemType) {
            // the switch parameter name can be changed, but this code assumes it's always
            // the default one because Symfony doesn't provide a generic exitImpersonationUrlGenerator
            return '?_switch_user=_exit';
        }

        return '';
    }
}