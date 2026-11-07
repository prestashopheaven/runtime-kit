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

namespace PrestaShopHeaven\Runtime\Kit\Definition;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Describes everything a module registers in PrestaShop: hooks, configuration keys,
 * generated secrets, back-office tabs and where its migrations live.
 *
 * Installer, Uninstaller and Upgrade read only from this definition, so a fresh
 * install and an upgraded install always end up the same.
 */
interface ModuleDefinitionInterface
{
    /**
     * Returns the hooks the module registers on install.
     *
     * @return string[]
     */
    public function hooks(): array;

    /**
     * Returns the configuration keys with their default value.
     *
     * @return array<string, string|int|bool>
     */
    public function configuration(): array;

    /**
     * Returns the configuration keys that get a random secret on install (tokens).
     *
     * @return string[]
     */
    public function generatedConfiguration(): array;

    /**
     * Returns the back-office tabs in the format of Module::$tabs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tabs(): array;

    /**
     * Returns the translation domain of the tab names ('wording' of each tab), or '' to keep the names of tabs() as
     * they are.
     */
    public function tabsWordingDomain(): string;

    /**
     * Returns the page the Configure button opens: ['controller' => legacy tab class, 'route' => Symfony route], or
     * [] for the convention Admin<Module>Configuration / <module>_configuration.
     *
     * @return array{controller?: string, route?: string}
     */
    public function configurationPage(): array;

    /**
     * Returns the scripts of the module per own back-office page: legacy controller name => script names in
     * views/js/ (without .js), loaded after the admin theme on that page only (HK-11, runtime-admin-assets).
     *
     * @return array<string, list<string>>
     */
    public function adminScripts(): array;

    /**
     * Returns the directory with the install migrations, relative to the module root.
     */
    public function migrationsUpDirectory(): string;

    /**
     * Returns the directory with the uninstall migrations, relative to the module root.
     */
    public function migrationsDownDirectory(): string;
}
