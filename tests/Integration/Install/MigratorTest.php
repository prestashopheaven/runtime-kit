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
use PrestaShopHeaven\Runtime\Kit\Install\Migrator;

/**
 * Migrator on the shop's database: runAll() and run() with the real table prefix, and the database error of a failing
 * statement in getLastError().
 */
final class MigratorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/migrator-test-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->dir);
        file_put_contents($this->dir . '202611070001 - C - temp.sql', 'CREATE TABLE IF NOT EXISTS `{_DB_PREFIX_}heavenmonocore_migrator_test` (`a` INT);');
        file_put_contents($this->dir . '202611070002 - I - temp.sql', 'INSERT INTO `{_DB_PREFIX_}heavenmonocore_migrator_test` VALUES (7);');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
        \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'heavenmonocore_migrator_test`');
    }

    public function testRunAllAndRunUseTheShopDatabaseAndPrefix(): void
    {
        $migrator = new Migrator($this->dir);

        self::assertTrue($migrator->runAll());
        self::assertSame('', $migrator->getLastError());
        self::assertTrue($migrator->run(['202611070002']));
        self::assertSame('2', (string) \Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'heavenmonocore_migrator_test` WHERE `a` = 7'));
    }

    public function testAFailingStatementGivesTheDatabaseError(): void
    {
        file_put_contents($this->dir . '202611070003 - U - broken.sql', 'UPDATE `{_DB_PREFIX_}heavenmonocore_no_such_table` SET `a` = 1;');
        $migrator = new Migrator($this->dir);

        self::assertFalse($migrator->runAll());
        self::assertStringContainsString('migrations in ' . $this->dir, $migrator->getLastError());
        self::assertFalse($migrator->run(['202611070003']));
        self::assertStringContainsString('migration 202611070003', $migrator->getLastError());
        self::assertStringContainsString('heavenmonocore_no_such_table', $migrator->getLastError());
    }
}
