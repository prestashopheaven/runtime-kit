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

namespace PrestaShopHeaven\Runtime\Kit\Tests\PhpStan;

use PrestaShopHeaven\Runtime\Kit\ConfigurationPage;
use PrestaShopHeaven\Runtime\Kit\Definition\AbstractModuleDefinition;
use PrestaShopHeaven\Runtime\Kit\Definition\ModuleDefinitionInterface;
use PrestaShopHeaven\Runtime\Kit\ModuleBase;

/**
 * Example module that uses ModuleBase and ConfigurationPage, so PHPStan analyses the traits in the context
 * of a real Module subclass. Not used at runtime.
 */
final class ExampleModule extends \Module
{
    use ModuleBase;
    use ConfigurationPage;

    /**
     * Returns an empty definition.
     */
    public function getModuleDefinition(): ModuleDefinitionInterface
    {
        return new class extends AbstractModuleDefinition {
        };
    }
}
