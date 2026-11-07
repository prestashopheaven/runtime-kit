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

namespace PrestaShopHeaven\Runtime\Kit\Tests\Integration\Install;

use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Runtime\Kit\Definition\AbstractModuleDefinition;
use PrestaShopHeaven\Runtime\Kit\Install\Uninstaller;

/**
 * Uninstaller in a real shop: the down migrations, the configuration keys and secrets, and the data folders are gone
 * afterwards. Runs on a definition of its own (a temporary table, key and folder), so the installed fixture module
 * stays as it is for the other tests; the full uninstall of the module is covered by ps-module shops run.
 */
final class UninstallerTest extends TestCase
{
    private string $migrations;

    protected function setUp(): void
    {
        $module = \Module::getInstanceByName(HEAVEN_FIXTURE_MODULE);
        $this->migrations = 'var/uninstaller-test-' . bin2hex(random_bytes(4)) . '/';
        @mkdir($module->getLocalPath() . $this->migrations, 0777, true);
        file_put_contents($module->getLocalPath() . $this->migrations . '202611070001 - D - temp.sql', 'DROP TABLE IF EXISTS `{_DB_PREFIX_}heavenmonocore_uninstall_test`;');
        \Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'heavenmonocore_uninstall_test` (`a` INT)');
        \Configuration::updateGlobalValue('HEAVENMONOCORE_UNINSTALL_TEST', 1);
        \Configuration::updateGlobalValue('HEAVENMONOCORE_UNINSTALL_SECRET', 'secret');
        @mkdir(_PS_ROOT_DIR_ . '/img/modules/heavenmonocore/uninstall-test/deeper', 0777, true);
        file_put_contents(_PS_ROOT_DIR_ . '/img/modules/heavenmonocore/uninstall-test/deeper/file.txt', 'x');
    }

    protected function tearDown(): void
    {
        $module = \Module::getInstanceByName(HEAVEN_FIXTURE_MODULE);
        @unlink($module->getLocalPath() . $this->migrations . '202611070001 - D - temp.sql');
        @rmdir($module->getLocalPath() . $this->migrations);
        \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'heavenmonocore_uninstall_test`');
        // A test that fails before the keys are deleted must not leave them behind (ps-module shops checks that).
        \Configuration::deleteByName('HEAVENMONOCORE_UNINSTALL_TEST');
        \Configuration::deleteByName('HEAVENMONOCORE_UNINSTALL_SECRET');
        $dir = _PS_ROOT_DIR_ . '/img/modules/heavenmonocore';
        if (is_dir($dir)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($dir);
        }
    }

    public function testUninstallRunsTheDownMigrationsDeletesTheKeysAndTheDataFolders(): void
    {
        $uninstaller = new Uninstaller(\Module::getInstanceByName(HEAVEN_FIXTURE_MODULE), $this->definition(['img/modules/heavenmonocore/uninstall-test']));

        self::assertTrue($uninstaller->uninstall(), implode(' ', $uninstaller->getErrors()));
        self::assertSame([], \Db::getInstance()->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . 'heavenmonocore_uninstall_test"') ?: []);
        self::assertFalse(\Configuration::hasKey('HEAVENMONOCORE_UNINSTALL_TEST'));
        self::assertFalse(\Configuration::hasKey('HEAVENMONOCORE_UNINSTALL_SECRET'));
        self::assertDirectoryDoesNotExist(_PS_ROOT_DIR_ . '/img/modules/heavenmonocore/uninstall-test');
    }

    public function testAnInvalidDataFolderFailsWithAMessage(): void
    {
        $uninstaller = new Uninstaller(\Module::getInstanceByName(HEAVEN_FIXTURE_MODULE), $this->definition(['../outside']));

        self::assertFalse($uninstaller->uninstall());
        self::assertStringContainsString('Invalid data directory', implode(' ', $uninstaller->getErrors()));
    }

    public function testAMissingDownFolderFailsWithAMessage(): void
    {
        $uninstaller = new Uninstaller(\Module::getInstanceByName(HEAVEN_FIXTURE_MODULE), new class extends AbstractModuleDefinition {
            public const MIGRATIONS_DOWN = 'no-such-folder/';
        });

        self::assertFalse($uninstaller->uninstall());
        self::assertStringContainsString('Running the uninstall migrations failed', implode(' ', $uninstaller->getErrors()));
    }

    /**
     * A definition with the temporary down migration, key, secret and the given data folders.
     *
     * @param list<string> $folders
     */
    private function definition(array $folders): AbstractModuleDefinition
    {
        $definition = new class extends AbstractModuleDefinition {
            public const CONFIGURATION = ['HEAVENMONOCORE_UNINSTALL_TEST' => 1];
            public const GENERATED_CONFIGURATION = ['HEAVENMONOCORE_UNINSTALL_SECRET'];

            /** @var list<string> */
            public array $folders = [];

            /** The down migrations of this test. */
            public string $down = '';

            /**
             * The data folders of this test.
             *
             * @return list<string>
             */
            public function dataDirectories(): array
            {
                return $this->folders;
            }

            /**
             * The temporary down folder of this test.
             */
            public function migrationsDownDirectory(): string
            {
                return $this->down;
            }
        };
        $definition->folders = $folders;
        $definition->down = $this->migrations;

        return $definition;
    }
}
