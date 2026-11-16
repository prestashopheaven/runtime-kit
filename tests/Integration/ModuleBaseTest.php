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

namespace PrestaShopHeaven\Runtime\Kit\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * ModuleBase in a real shop, on the installed fixture module: the tabs are translated into every shop language, the
 * new translation system is on, the PrestaHaven bar renders, and the module is installed and active in every shop.
 * install() and uninstall() themselves run in ps-module shops run (install, uninstall leaves nothing, re-install).
 */
final class ModuleBaseTest extends TestCase
{
    public function testTheTabsAreTranslatedIntoEveryShopLanguage(): void
    {
        $tabs = $this->module()->getTabs();

        self::assertSame(
            ['AdminParentHeavenmonocore', 'AdminHeavenmonocoreItems', 'AdminHeavenmonocoreConfiguration'],
            array_column($tabs, 'class_name')
        );
        self::assertSame($tabs, $this->module()->getDefinedTabs(), 'outside an install getTabs() gives every tab');
        foreach ($tabs as $tab) {
            self::assertSame('Modules.Heavenmonocore.Admin', $tab['wording_domain']);
            foreach (\Language::getLanguages(false) as $language) {
                self::assertNotSame('', (string) ($tab['name'][$language['locale']] ?? ''), "name of {$tab['class_name']} in {$language['locale']}");
            }
        }
    }

    public function testTheModuleUsesTheNewTranslationSystem(): void
    {
        self::assertTrue($this->module()->isUsingNewTranslationSystem());
    }

    public function testTheBarRendersAsAString(): void
    {
        self::assertIsString($this->module()->hookDisplayAdminAfterHeader([]));
    }

    public function testTheModuleIsInstalledInEveryShop(): void
    {
        $module = $this->module();
        foreach (\Shop::getShops(false, null, true) as $shopId) {
            self::assertTrue(\PrestaShopHeaven\Runtime\Kit\Shop\ShopScope::isModuleActiveInShop($module, (int) $shopId), "shop $shopId");
        }
    }

    /**
     * The installed fixture module.
     */
    private function module(): \Module
    {
        $module = \Module::getInstanceByName(HEAVEN_FIXTURE_MODULE);
        self::assertInstanceOf(\Module::class, $module);

        return $module;
    }
}
