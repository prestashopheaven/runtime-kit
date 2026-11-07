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

namespace PrestaShopHeaven\Runtime\Kit\Tests\Integration\Shop;

use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Runtime\Kit\Shop\ShopScope;

/**
 * ShopScope in a real shop (single shop and, in the multistore round of ps-module shops run, two shops in groups):
 * the callback runs in the "all shops" context and the previous context comes back, also after an exception.
 */
final class ShopScopeTest extends TestCase
{
    public function testRunInAllShopsReturnsTheCallbackValueAndRestoresTheShopContext(): void
    {
        $shopId = (int) \Configuration::get('PS_SHOP_DEFAULT');
        \Shop::setContext(\Shop::CONTEXT_SHOP, $shopId);
        $inside = null;

        $result = ShopScope::runInAllShops(static function () use (&$inside): string {
            $inside = \Shop::getContext();

            return 'done';
        });

        self::assertSame('done', $result);
        if (\Shop::isFeatureActive()) {
            self::assertSame(\Shop::CONTEXT_ALL, $inside);
        }
        self::assertSame(\Shop::CONTEXT_SHOP, \Shop::getContext());
        self::assertSame($shopId, (int) \Shop::getContextShopID());
    }

    public function testTheGroupContextComesBackAfterAnException(): void
    {
        if (!\Shop::isFeatureActive()) {
            self::markTestSkipped('Multistore is off: there is no group context (the multistore round covers it).');
        }
        $groupId = (int) \Shop::getGroupFromShop((int) \Configuration::get('PS_SHOP_DEFAULT'));
        \Shop::setContext(\Shop::CONTEXT_GROUP, $groupId);
        try {
            ShopScope::runInAllShops(static function (): void {
                throw new \RuntimeException('Failed inside.');
            });
            self::fail('The exception must reach the caller.');
        } catch (\RuntimeException $e) {
            self::assertSame('Failed inside.', $e->getMessage());
        }

        self::assertSame(\Shop::CONTEXT_GROUP, \Shop::getContext());
        self::assertSame($groupId, (int) \Shop::getContextShopGroupID());
        \Shop::setContext(\Shop::CONTEXT_SHOP, (int) \Configuration::get('PS_SHOP_DEFAULT'));
    }

    public function testShopIdsAndActiveInShop(): void
    {
        $ids = ShopScope::shopIds();
        $module = \Module::getInstanceByName(HEAVEN_FIXTURE_MODULE);

        self::assertContains((int) \Configuration::get('PS_SHOP_DEFAULT'), $ids);
        self::assertContainsOnly('int', ShopScope::shopIds(false));
        self::assertTrue(ShopScope::isModuleActiveInShop($module, $ids[0]));
        if (\Shop::isFeatureActive()) {
            self::assertFalse(ShopScope::isModuleActiveInShop($module, 999999), 'a shop that does not exist');
        }
    }
}
