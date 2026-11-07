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

namespace PrestaShopHeaven\Runtime\Kit\Tests\Integration\Upgrade;

use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Runtime\Kit\Upgrade\Upgrade;

/**
 * Upgrade::apply() as an upgrade-X.Y.Z.php script calls it: the new migrations run and the definition is synced (a key
 * that is missing gets its default, existing values stay). A module without ModuleBase is refused and logged.
 */
final class UpgradeTest extends TestCase
{
    public function testApplyRunsTheMigrationsAndSyncsTheDefinition(): void
    {
        $module = \Module::getInstanceByName(HEAVEN_FIXTURE_MODULE);
        \Configuration::deleteByName('HEAVENMONOCORE_ENABLED');
        try {
            self::assertTrue(Upgrade::apply($module, ['202611070000']));
            self::assertSame('1', (string) \Configuration::getGlobalValue('HEAVENMONOCORE_ENABLED'));
            self::assertTrue(Upgrade::apply($module));
        } finally {
            \Configuration::updateGlobalValue('HEAVENMONOCORE_ENABLED', 1);
        }
    }

    public function testAModuleWithoutModuleBaseIsRefused(): void
    {
        $plain = new class extends \Module {
            /**
             * A module without ModuleBase.
             */
            public function __construct()
            {
                $this->name = 'heavenmonocoreplain';
            }
        };

        self::assertFalse(Upgrade::apply($plain));
    }
}
