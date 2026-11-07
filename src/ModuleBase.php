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
use PrestaShopHeaven\Runtime\Kit\Install\Installer;
use PrestaShopHeaven\Runtime\Kit\Install\TabBuilder;
use PrestaShopHeaven\Runtime\Kit\Install\Uninstaller;
use PrestaShopHeaven\Runtime\Kit\Shop\ShopScope;
use PrestaShopHeaven\Runtime\Kit\Updater\UpdaterNotice;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Standard behaviour of every PrestaHaven module, added to the main module
 * class with `use ModuleBase;`.
 *
 * It is a trait and not a base class on purpose: the Addons validator requires the
 * main class to extend Module, PaymentModule or CarrierModule directly.
 *
 * The module provides its definition through getModuleDefinition() (in the module:
 * the Definition trait). ModuleBase then handles install, uninstall, tabs and the
 * translation system, all driven by that definition.
 */
trait ModuleBase
{
    /**
     * Returns the definition of the module: hooks, configuration, tabs and migrations.
     */
    abstract public function getModuleDefinition(): ModuleDefinitionInterface;

    /**
     * Installs the module: first the PrestaShop registration (parent::install(), which
     * also creates the tabs), then migrations, hooks and configuration.
     *
     * Runs in the "all shops" context, so the module is enabled in every shop and the
     * default configuration is global (MS-02), also when installed from one shop or the CLI.
     *
     * @return bool true when every step succeeded; otherwise the reasons are in $this->_errors
     */
    public function install()
    {
        return ShopScope::runInAllShops(function (): bool {
            if (!parent::install()) {
                return false;
            }

            $installer = new Installer($this, $this->getModuleDefinition());
            if (!$installer->install()) {
                $this->_errors = array_merge($this->_errors, $installer->getErrors());

                return false;
            }

            return true;
        });
    }

    /**
     * Uninstalls the module: first the PrestaShop part (parent::uninstall(), which also
     * removes hooks and tabs), then the down migrations and configuration, for all shops.
     *
     * @return bool true when every step succeeded; otherwise the reasons are in $this->_errors
     */
    public function uninstall()
    {
        return ShopScope::runInAllShops(function (): bool {
            if (!parent::uninstall()) {
                return false;
            }

            $uninstaller = new Uninstaller($this, $this->getModuleDefinition());
            if (!$uninstaller->uninstall()) {
                $this->_errors = array_merge($this->_errors, $uninstaller->getErrors());

                return false;
            }

            return true;
        });
    }

    /**
     * Returns the back-office tabs of the module from its definition. PrestaShop reads
     * this on install (to create the tabs) and on uninstall (to remove them). With a
     * ModuleDefinition::TABS_WORDING_DOMAIN the names are translated into every shop
     * language (TabBuilder); without one they are returned as they are.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTabs()
    {
        $definition = $this->getModuleDefinition();
        $domain = $definition->tabsWordingDomain();

        return $domain === '' ? $definition->tabs() : TabBuilder::build($this, $definition->tabs(), $domain);
    }

    /**
     * Opts the module in to the new translation system, so its texts use domains like
     * Modules.Mymodule.Admin and can be translated in the back office.
     *
     * @return bool always true
     */
    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    /**
     * The PrestaHaven bar on the module's own back-office pages: "PrestaHaven Updater not installed" with a help button,
     * or a slim "Questions about <module>?" bar with only the help button (UpdaterNotice::render()). Direct-sale build
     * only: the Addons build has no Updater folder and never registers this hook.
     *
     * A module with its own hookDisplayAdminAfterHeader resolves the clash in its main class:
     * `use ModuleBase, DisplayAdminAfterHeader { DisplayAdminAfterHeader::hookDisplayAdminAfterHeader insteadof ModuleBase; }`
     *
     * @param array<string, mixed> $params hook parameters (unused)
     *
     * @return string the bar's HTML, or ''
     */
    public function hookDisplayAdminAfterHeader($params = [])
    {
        if (!class_exists(UpdaterNotice::class)) {
            return '';
        }
        $controllers = [];
        foreach ($this->getModuleDefinition()->tabs() as $tab) {
            if (is_string($tab['class_name'] ?? null)) {
                $controllers[] = $tab['class_name'];
            }
        }

        return UpdaterNotice::render($this, $controllers);
    }
}
