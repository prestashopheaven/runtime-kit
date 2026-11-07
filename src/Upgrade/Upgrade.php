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

namespace PrestaShopHeaven\Runtime\Kit\Upgrade;

use PrestaShopHeaven\Runtime\Kit\Definition\ModuleDefinitionInterface;
use PrestaShopHeaven\Runtime\Kit\Install\Installer;
use PrestaShopHeaven\Runtime\Kit\Shop\ShopScope;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Helper for upgrade/upgrade-X.Y.Z.php scripts.
 *
 * An upgrade runs the migrations that are new in that version and then makes the
 * installation match the module definition (new hooks, new configuration keys, new
 * secrets). The result equals a fresh install of the new version. It runs in the "all shops"
 * context, so new hooks and defaults reach every shop (MS-02, MS-03).
 */
final class Upgrade
{
    /**
     * Applies an upgrade for the given module.
     *
     * @param \Module $module the module instance PrestaShop passes to upgrade_module_X_Y_Z()
     * @param string[] $migrations timestamps of the up migrations that are new in this version
     *
     * @return bool true when everything succeeded; failures are logged
     */
    public static function apply(\Module $module, array $migrations = []): bool
    {
        if (!method_exists($module, 'getModuleDefinition')) {
            \PrestaShopLogger::addLog(sprintf('[%s] Upgrade: the module does not use ModuleBase.', $module->name), 3);

            return false;
        }
        $definition = $module->getModuleDefinition();
        if (!$definition instanceof ModuleDefinitionInterface) {
            return false;
        }

        $installer = new Installer($module, $definition);

        return ShopScope::runInAllShops(
            static fn (): bool => $installer->runMigrations($migrations) && $installer->syncDefinition()
        );
    }
}
