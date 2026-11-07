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
 * Base for the ModuleDefinition class of a module.
 *
 * A module only overrides the constants; the methods read them with late static
 * binding (static::HOOKS), so nothing else has to be written.
 */
abstract class AbstractModuleDefinition implements ModuleDefinitionInterface, DataDirectoriesInterface
{
    /**
     * Hooks registered on install. PrestaShop unregisters them on uninstall.
     *
     * @var string[]
     */
    public const HOOKS = [];

    /**
     * Configuration keys with their default value. Prefix keys with the module name in capitals.
     *
     * @var array<string, string|int|bool>
     */
    public const CONFIGURATION = [];

    /**
     * Configuration keys that get a random secret on install (e.g. a cron token).
     *
     * @var string[]
     */
    public const GENERATED_CONFIGURATION = [];

    /**
     * Back-office tabs in the format of Module::$tabs. PrestaShop creates them on install
     * and removes them on uninstall.
     *
     * @var array<int, array<string, mixed>>
     */
    public const TABS = [];

    /**
     * Translation domain of the tab names ('wording' of each tab), e.g. 'Modules.Heavenfoo.Admin'. With a domain the
     * names are translated into every shop language (TabBuilder); '' keeps the names of TABS as they are.
     */
    public const TABS_WORDING_DOMAIN = '';

    /**
     * The page the Configure button opens (trait ConfigurationPage): ['controller' => 'AdminHeavenfooSettings',
     * 'route' => 'heavenfoo_settings']. Empty: Admin<Module>Configuration and <module>_configuration.
     *
     * @var array{controller?: string, route?: string}
     */
    public const CONFIGURATION_PAGE = [];

    /**
     * The scripts of the module per own back-office page: ['AdminHeavenfooRules' => ['admin-rules']] loads
     * views/js/admin-rules.js on that page (runtime-admin-assets, trait AdminScripts).
     *
     * @var array<string, list<string>>
     */
    public const ADMIN_SCRIPTS = [];

    /**
     * Directories the module writes outside its own folder, relative to the PrestaShop root
     * (for example 'img/modules/heavenfoo'). The Uninstaller deletes them; each path must
     * contain the technical name of the module as a segment.
     *
     * @var string[]
     */
    public const DATA_DIRECTORIES = [];

    /** Directory with the install migrations, relative to the module root. */
    public const MIGRATIONS_UP = 'migrations/up/';

    /** Directory with the uninstall migrations, relative to the module root. */
    public const MIGRATIONS_DOWN = 'migrations/down/';

    /**
     * Returns the hooks from static::HOOKS.
     *
     * @return string[]
     */
    public function hooks(): array
    {
        return static::HOOKS;
    }

    /**
     * Returns the configuration keys and defaults from static::CONFIGURATION.
     *
     * @return array<string, string|int|bool>
     */
    public function configuration(): array
    {
        return static::CONFIGURATION;
    }

    /**
     * Returns the generated configuration keys from static::GENERATED_CONFIGURATION.
     *
     * @return string[]
     */
    public function generatedConfiguration(): array
    {
        return static::GENERATED_CONFIGURATION;
    }

    /**
     * Returns the back-office tabs from static::TABS.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tabs(): array
    {
        return static::TABS;
    }

    /**
     * Returns the translation domain of the tab names from static::TABS_WORDING_DOMAIN.
     */
    public function tabsWordingDomain(): string
    {
        return static::TABS_WORDING_DOMAIN;
    }

    /**
     * Returns the page of the Configure button from static::CONFIGURATION_PAGE.
     *
     * @return array{controller?: string, route?: string}
     */
    public function configurationPage(): array
    {
        return static::CONFIGURATION_PAGE;
    }

    /**
     * Returns the scripts per back-office page from static::ADMIN_SCRIPTS.
     *
     * @return array<string, list<string>>
     */
    public function adminScripts(): array
    {
        return static::ADMIN_SCRIPTS;
    }

    /**
     * Returns the data directories from static::DATA_DIRECTORIES.
     *
     * @return string[]
     */
    public function dataDirectories(): array
    {
        return static::DATA_DIRECTORIES;
    }

    /**
     * Returns static::MIGRATIONS_UP.
     */
    public function migrationsUpDirectory(): string
    {
        return static::MIGRATIONS_UP;
    }

    /**
     * Returns static::MIGRATIONS_DOWN.
     */
    public function migrationsDownDirectory(): string
    {
        return static::MIGRATIONS_DOWN;
    }
}
