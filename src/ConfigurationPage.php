<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @author    prestahaven.com
 * @copyright Since 2026 George van Engers
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace PrestaShopHeaven\Runtime\Kit;

use PrestaShopHeaven\Runtime\Kit\Definition\ModuleDefinitionInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The Configure button of the module: opens its Symfony settings page (was a copy in each module:
 * src/Module/Helper/ConfigForm.php). The page is ModuleDefinition::CONFIGURATION_PAGE, or by convention the tab
 * Admin<Module>Configuration with the route <module>_configuration. Only for modules with a settings page.
 */
trait ConfigurationPage
{
    /**
     * Returns the module definition (trait ModuleBase and the module's own Definition trait).
     */
    abstract public function getModuleDefinition(): ModuleDefinitionInterface;

    /**
     * Redirects to the settings page instead of rendering a legacy configuration form.
     */
    public function getContent(): void
    {
        \Tools::redirectAdmin($this->configurationPageUrl());
    }

    /**
     * The back-office URL of the settings page (with the security token).
     */
    public function configurationPageUrl(): string
    {
        $page = $this->getModuleDefinition()->configurationPage();
        $controller = $page['controller'] ?? 'Admin' . ucfirst((string) $this->name) . 'Configuration';
        $route = $page['route'] ?? $this->name . '_configuration';

        return $this->context->link->getAdminLink($controller, true, ['route' => $route]);
    }
}
