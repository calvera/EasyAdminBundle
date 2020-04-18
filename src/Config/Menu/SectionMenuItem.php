<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Config\Menu;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;

/**
 * @see EasyCorp\Bundle\EasyAdminBundle\Configuration\MenuItem::section()
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class SectionMenuItem implements MenuItemInterface
{
    use MenuItemTrait {
        setLinkRel as private;
        setLinkTarget as private;
    }

    public function __construct(string $label, ?string $icon)
    {
        $this->dto = new MenuItemDto();

        $this->dto->setType(MenuItemDto::TYPE_SECTION);
        $this->dto->setLabel($label);
        $this->dto->setIcon($icon);
    }
}