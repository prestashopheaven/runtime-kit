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

namespace PrestaShopHeaven\Runtime\Kit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Runtime\Kit\ConfigurationPage;
use PrestaShopHeaven\Runtime\Kit\Definition\AbstractModuleDefinition;
use PrestaShopHeaven\Runtime\Kit\Definition\ModuleDefinitionInterface;

require_once __DIR__ . '/prestashop-stubs.php';

/**
 * The Configure button opens the module's settings page: the page of the definition, or by convention
 * Admin<Module>Configuration with the route <module>_configuration.
 */
final class ConfigurationPageTest extends TestCase
{
    protected function setUp(): void
    {
        \Tools::$redirects = [];
    }

    public function testTheConventionalPage(): void
    {
        $module = $this->module(new class extends AbstractModuleDefinition {
        });

        self::assertSame('https://shop.test/admin/AdminHeavenfooConfiguration?route=heavenfoo_configuration', $module->configurationPageUrl());
    }

    public function testThePageOfTheDefinition(): void
    {
        $module = $this->module(new class extends AbstractModuleDefinition {
            public const CONFIGURATION_PAGE = ['controller' => 'AdminHeavenfooUpdates', 'route' => 'heavenfoo_updates'];
        });

        self::assertSame('https://shop.test/admin/AdminHeavenfooUpdates?route=heavenfoo_updates', $module->configurationPageUrl());
    }

    public function testGetContentRedirectsToThePage(): void
    {
        $module = $this->module(new class extends AbstractModuleDefinition {
        });

        $module->getContent();

        self::assertSame(['https://shop.test/admin/AdminHeavenfooConfiguration?route=heavenfoo_configuration'], \Tools::$redirects);
    }

    /**
     * A stand-in for a main class with the trait, its definition and a context with a link.
     */
    private function module(ModuleDefinitionInterface $definition): object
    {
        return new class($definition) {
            use ConfigurationPage;

            /** Technical name. */
            public string $name = 'heavenfoo';

            /** The context with the link (Module::$context). */
            public object $context;

            /**
             * Holds the definition and a context.
             */
            public function __construct(private readonly ModuleDefinitionInterface $definition)
            {
                $this->context = new \Context();
                $this->context->link = new \Link();
            }

            /**
             * The definition of the stand-in.
             */
            public function getModuleDefinition(): ModuleDefinitionInterface
            {
                return $this->definition;
            }
        };
    }
}
