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

namespace PrestaShopHeaven\Runtime\Kit\Shop;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Multistore helpers (conventions MS-02, MS-09, MS-11).
 *
 * After Shop::initialize() the context is a single shop, also in bin/console. Module::install()
 * then enables the module only in that shop, and Configuration::updateValue() without shop
 * arguments writes a value for that shop only. Install, upgrade and uninstall therefore run in
 * the "all shops" context.
 */
final class ShopScope
{
    /**
     * Runs a callback in the "all shops" context and restores the previous context afterwards,
     * also when the callback throws. Without multistore the callback just runs.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T the return value of the callback
     */
    public static function runInAllShops(callable $callback): mixed
    {
        if (!\Shop::isFeatureActive()) {
            return $callback();
        }
        $context = \Shop::getContext();
        $groupId = \Shop::getContextShopGroupID(true);
        $shopId = \Shop::getContextShopID(true);
        \Shop::setContext(\Shop::CONTEXT_ALL);
        try {
            return $callback();
        } finally {
            if ($context === \Shop::CONTEXT_SHOP && $shopId !== null) {
                \Shop::setContext(\Shop::CONTEXT_SHOP, (int) $shopId);
            } elseif ($context === \Shop::CONTEXT_GROUP && $groupId !== null) {
                \Shop::setContext(\Shop::CONTEXT_GROUP, (int) $groupId);
            }
        }
    }

    /**
     * Tells whether the module is enabled in a given shop. Use it where PrestaShop does not
     * check this itself: Symfony subscribers, CQRS handlers, cron or webhook code that loops over
     * shops, and hooks that run in the back office for an object of another shop.
     *
     * @param \Module $module the module to check
     * @param int $shopId the shop of the object being processed (e.g. $order->id_shop)
     */
    public static function isModuleActiveInShop(\Module $module, int $shopId): bool
    {
        if (!\Shop::isFeatureActive()) {
            return (bool) $module->active;
        }

        return (bool) \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'module_shop` WHERE `id_module` = ' . (int) $module->id
            . ' AND `id_shop` = ' . $shopId
        );
    }

    /**
     * Returns the ids of the shops, for cron jobs and commands that work per shop (MS-11).
     *
     * @param bool $activeOnly true to skip disabled shops
     *
     * @return int[]
     */
    public static function shopIds(bool $activeOnly = true): array
    {
        return array_map('intval', array_values((array) \Shop::getShops($activeOnly, null, true)));
    }
}
