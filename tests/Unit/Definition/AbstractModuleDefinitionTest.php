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

namespace PrestaShopHeaven\Runtime\Kit\Tests\Unit\Definition;

use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Runtime\Kit\Definition\AbstractModuleDefinition;
use PrestaShopHeaven\Runtime\Kit\Definition\DataDirectoriesInterface;

/**
 * A module definition only overrides constants; the base methods must return them.
 */
final class AbstractModuleDefinitionTest extends TestCase
{
    /**
     * An empty definition returns empty lists and the default migration directories.
     */
    public function testDefaults(): void
    {
        $definition = new class extends AbstractModuleDefinition {
        };

        self::assertSame([], $definition->hooks());
        self::assertSame([], $definition->configuration());
        self::assertSame([], $definition->generatedConfiguration());
        self::assertSame([], $definition->tabs());
        self::assertSame('migrations/up/', $definition->migrationsUpDirectory());
        self::assertSame('migrations/down/', $definition->migrationsDownDirectory());
        self::assertSame([], $definition->dataDirectories());
        self::assertSame('', $definition->tabsWordingDomain());
        self::assertSame([], $definition->configurationPage());
        self::assertSame([], $definition->adminScripts());
        self::assertInstanceOf(DataDirectoriesInterface::class, $definition);
    }

    /**
     * The tab domain and the configuration page come from their constants.
     */
    public function testTabsWordingDomainAndConfigurationPage(): void
    {
        $definition = new class extends AbstractModuleDefinition {
            public const TABS_WORDING_DOMAIN = 'Modules.Mymodule.Admin';
            public const CONFIGURATION_PAGE = ['controller' => 'AdminMyModuleSettings', 'route' => 'mymodule_settings'];
            public const ADMIN_SCRIPTS = ['AdminMyModuleSettings' => ['admin-settings']];
        };

        self::assertSame(['AdminMyModuleSettings' => ['admin-settings']], $definition->adminScripts());

        self::assertSame('Modules.Mymodule.Admin', $definition->tabsWordingDomain());
        self::assertSame(['controller' => 'AdminMyModuleSettings', 'route' => 'mymodule_settings'], $definition->configurationPage());
    }

    /**
     * Constants overridden in the module's definition are returned (late static binding).
     */
    public function testOverriddenConstantsAreReturned(): void
    {
        $definition = new class extends AbstractModuleDefinition {
            public const HOOKS = ['displayHome', 'actionCartSave'];
            public const CONFIGURATION = ['MYMODULE_LIMIT' => 10];
            public const GENERATED_CONFIGURATION = ['MYMODULE_TOKEN'];
            public const TABS = [['class_name' => 'AdminMyModule', 'name' => 'My module']];
            public const MIGRATIONS_UP = 'sql/up/';
            public const DATA_DIRECTORIES = ['img/modules/mymodule'];
        };

        self::assertSame(['displayHome', 'actionCartSave'], $definition->hooks());
        self::assertSame(['MYMODULE_LIMIT' => 10], $definition->configuration());
        self::assertSame(['MYMODULE_TOKEN'], $definition->generatedConfiguration());
        self::assertSame([['class_name' => 'AdminMyModule', 'name' => 'My module']], $definition->tabs());
        self::assertSame('sql/up/', $definition->migrationsUpDirectory());
        self::assertSame(['img/modules/mymodule'], $definition->dataDirectories());
    }
}
