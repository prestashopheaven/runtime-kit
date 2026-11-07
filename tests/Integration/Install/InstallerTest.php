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
use PrestaShopHeaven\Runtime\Kit\Install\Installer;

/**
 * Installer in a real shop, against the installed fixture module (HEAVEN_FIXTURE_MODULE, mono-runtime-core
 * tests/fixture): what install() left behind, syncDefinition() keeping values, upgrade migrations, and the errors.
 */
final class InstallerTest extends TestCase
{
    public function testTheInstallLeftHooksConfigurationSecretsTabsAndTables(): void
    {
        $module = $this->module();
        $definition = $module->getModuleDefinition();

        foreach ($definition->hooks() as $hook) {
            self::assertTrue((bool) $module->isRegisteredInHook($hook), "hook $hook");
        }
        foreach ($definition->configuration() as $key => $default) {
            self::assertSame((string) $default, (string) \Configuration::getGlobalValue($key), "configuration $key");
        }
        foreach ($definition->generatedConfiguration() as $key) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', (string) \Configuration::getGlobalValue($key), "secret $key");
        }
        foreach ($definition->tabs() as $tab) {
            self::assertGreaterThan(0, (int) \Tab::getIdFromClassName($tab['class_name']), "tab {$tab['class_name']}");
        }
        self::assertCount(1, \Db::getInstance()->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . 'heavenmonocore_item"') ?: []);
    }

    public function testSyncDefinitionKeepsChangedValuesAndSecretsButAddsMissingKeys(): void
    {
        $module = $this->module();
        $secret = (string) \Configuration::getGlobalValue('HEAVENMONOCORE_TOKEN');
        \Configuration::updateGlobalValue('HEAVENMONOCORE_LIMIT', 99);
        \Configuration::deleteByName('HEAVENMONOCORE_ENABLED');
        try {
            $installer = new Installer($module, $module->getModuleDefinition());

            self::assertTrue($installer->syncDefinition(), implode(' ', $installer->getErrors()));
            self::assertSame('99', (string) \Configuration::getGlobalValue('HEAVENMONOCORE_LIMIT'), 'a changed value stays');
            self::assertSame('1', (string) \Configuration::getGlobalValue('HEAVENMONOCORE_ENABLED'), 'a missing key gets its default');
            self::assertSame($secret, (string) \Configuration::getGlobalValue('HEAVENMONOCORE_TOKEN'), 'a secret is never replaced');
        } finally {
            // Back to the installed state, also when an assertion failed (the multistore round reads these keys).
            \Configuration::updateGlobalValue('HEAVENMONOCORE_LIMIT', 25);
            \Configuration::updateGlobalValue('HEAVENMONOCORE_ENABLED', 1);
        }
    }

    public function testRunMigrationsRunsTheGivenOnesAndNothingForNone(): void
    {
        $module = $this->module();
        $installer = new Installer($module, $module->getModuleDefinition());

        self::assertTrue($installer->runMigrations([]));
        self::assertTrue($installer->runMigrations(['202611070000']), implode(' ', $installer->getErrors()));
        self::assertFalse($installer->runMigrations(['199901010000']));
        self::assertStringContainsString('Running the upgrade migrations failed', implode(' ', $installer->getErrors()));
    }

    public function testAMissingMigrationFolderFailsTheInstallWithAMessage(): void
    {
        $installer = new Installer($this->module(), new class extends AbstractModuleDefinition {
            public const MIGRATIONS_UP = 'no-such-folder/';
        });

        self::assertFalse($installer->install());
        self::assertStringContainsString('Running the install migrations failed', implode(' ', $installer->getErrors()));
    }

    /**
     * The installed fixture module.
     */
    private function module(): \Module
    {
        $module = \Module::getInstanceByName(HEAVEN_FIXTURE_MODULE);
        self::assertInstanceOf(\Module::class, $module);
        self::assertTrue(method_exists($module, 'getModuleDefinition'));

        return $module;
    }
}
