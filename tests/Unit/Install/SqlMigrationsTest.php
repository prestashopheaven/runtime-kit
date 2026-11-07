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

namespace PrestaShopHeaven\Runtime\Kit\Tests\Unit\Install;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Runtime\Kit\Install\SqlMigrations;

/**
 * SqlMigrations reads a folder of .sql files and runs them in stamp order, statement by statement, with the table
 * prefix; it stops at the first failure. The database is replaced by a recording callable.
 */
final class SqlMigrationsTest extends TestCase
{
    private string $dir;

    /** @var list<string> */
    private array $queries = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sql-migrations-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents("$this->dir/202611020000 - A - add column.sql", "ALTER TABLE `{_DB_PREFIX_}foo` ADD `b` INT;\n");
        file_put_contents("$this->dir/202611010000 - C - foo.sql", "CREATE TABLE `{_DB_PREFIX_}foo` (`a` INT);\n\nINSERT INTO `{_DB_PREFIX_}foo` VALUES (1);\n");
        file_put_contents("$this->dir/README.txt", 'not a migration');
        $this->queries = [];
    }

    protected function tearDown(): void
    {
        foreach (glob("$this->dir/*") ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testRunAllRunsEveryStatementInStampOrderWithThePrefix(): void
    {
        self::assertTrue($this->migrations()->runAll());

        self::assertSame([
            'CREATE TABLE `ps_foo` (`a` INT)',
            'INSERT INTO `ps_foo` VALUES (1)',
            'ALTER TABLE `ps_foo` ADD `b` INT',
        ], $this->queries);
    }

    public function testStampsAreTheFileNamesBeforeTheFirstSpaceInOrder(): void
    {
        self::assertSame(['202611010000', '202611020000'], $this->migrations()->stamps());
    }

    /**
     * The ways to name one migration: its stamp, its file name, its path.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function namesOfTheSecondMigration(): iterable
    {
        yield 'stamp' => ['202611020000'];
        yield 'file name' => ['202611020000 - A - add column.sql'];
        yield 'path' => ['/any/where/202611020000 - A - add column.sql'];
    }

    #[DataProvider('namesOfTheSecondMigration')]
    public function testRunRunsOneMigration(string $name): void
    {
        self::assertTrue($this->migrations()->run($name));

        self::assertSame(['ALTER TABLE `ps_foo` ADD `b` INT'], $this->queries);
    }

    public function testAFileNamedOnlyByItsStampWorks(): void
    {
        file_put_contents("$this->dir/202611030000.sql", 'SELECT 1;');

        self::assertTrue($this->migrations()->run('202611030000'));
        self::assertSame(['SELECT 1'], $this->queries);
    }

    public function testAnUnknownMigrationIsRefused(): void
    {
        self::assertFalse($this->migrations()->run('199901010000'));
        self::assertSame([], $this->queries);
    }

    public function testRunAllStopsAtTheFirstFailingStatement(): void
    {
        $migrations = new SqlMigrations($this->dir, 'ps_', function (string $query): bool {
            $this->queries[] = $query;

            return !str_starts_with($query, 'INSERT');
        });

        self::assertFalse($migrations->runAll());
        self::assertSame(['CREATE TABLE `ps_foo` (`a` INT)', 'INSERT INTO `ps_foo` VALUES (1)'], $this->queries, 'nothing after the failure');
    }

    /**
     * Folders that cannot be used: missing, empty path.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function unusableFolders(): iterable
    {
        yield 'missing folder' => ['/no/such/folder'];
        yield 'empty path' => [''];
    }

    #[DataProvider('unusableFolders')]
    public function testAnUnusableFolderRunsNothing(string $folder): void
    {
        $migrations = new SqlMigrations($folder, 'ps_', fn (string $q): bool => (bool) ($this->queries[] = $q));

        self::assertFalse($migrations->runAll());
        self::assertFalse($migrations->run('202611010000'));
        self::assertSame([], $migrations->stamps());
        self::assertSame([], $this->queries);
    }

    public function testAFileThatDisappearsAfterReadingTheFolderFails(): void
    {
        $migrations = $this->migrations();
        self::assertCount(2, $migrations->stamps());
        unlink("$this->dir/202611010000 - C - foo.sql");

        self::assertFalse($migrations->run('202611010000'));
    }

    public function testAnEmptyFolderIsNothingToDo(): void
    {
        $empty = $this->dir . '/empty';
        mkdir($empty);
        try {
            self::assertTrue((new SqlMigrations($empty, 'ps_', fn (string $q): bool => false))->runAll());
        } finally {
            rmdir($empty);
        }
    }

    /**
     * Migrations of the temp folder that record their statements.
     */
    private function migrations(): SqlMigrations
    {
        return new SqlMigrations($this->dir, 'ps_', function (string $query): bool {
            $this->queries[] = $query;

            return true;
        });
    }
}
