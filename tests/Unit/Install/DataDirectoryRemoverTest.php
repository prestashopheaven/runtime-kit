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
use PrestaShopHeaven\Runtime\Kit\Install\DataDirectoryRemover;

/**
 * The remover deletes only checked paths below the root, and whole trees without following links.
 */
final class DataDirectoryRemoverTest extends TestCase
{
    private string $root;

    /**
     * Creates an empty temporary PrestaShop root.
     */
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/module-kit-remover-' . bin2hex(random_bytes(6));
        mkdir($this->root);
    }

    /**
     * Deletes what a test left in the temporary root.
     */
    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            $this->deleteTree($this->root);
        }
    }

    public function testRejectsARootThatIsNotADirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DataDirectoryRemover($this->root . '/missing', 'mymodule');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidModuleNames(): array
    {
        return ['empty' => [''], 'capitals' => ['MyModule'], 'starts with a digit' => ['1module'], 'slash' => ['my/module'], 'dots' => ['..']];
    }

    #[DataProvider('invalidModuleNames')]
    public function testRejectsAnInvalidModuleName(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DataDirectoryRemover($this->root, $name);
    }

    public function testResolvesARelativePathBelowTheRoot(): void
    {
        $remover = new DataDirectoryRemover($this->root, 'mymodule');

        self::assertSame($this->root . '/img/modules/mymodule', $remover->resolve('img/modules/mymodule'));
        self::assertSame($this->root . '/var/modules/mymodule', $remover->resolve('var/modules/mymodule/'));
        self::assertSame($this->root . '/mymodule', $remover->resolve('mymodule'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafePaths(): array
    {
        return [
            'empty' => [''],
            'absolute' => ['/img/modules/mymodule'],
            'parent segment' => ['img/../modules/mymodule'],
            'parent at the end' => ['img/mymodule/..'],
            'current segment' => ['img/./mymodule'],
            'double slash' => ['img//mymodule'],
            'backslash' => ['img\\mymodule'],
            'space' => ['img/my module'],
            'no module segment' => ['img/modules'],
            'module name as part of a segment only' => ['img/mymodule-old'],
            'core folder' => ['img'],
            'nul byte' => ["img/mymodule\0"],
        ];
    }

    #[DataProvider('unsafePaths')]
    public function testResolveRejectsAnUnsafePath(string $path): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DataDirectoryRemover($this->root, 'mymodule'))->resolve($path);
    }

    #[DataProvider('unsafePaths')]
    public function testRemoveRejectsAnUnsafePathAndDeletesNothing(string $path): void
    {
        mkdir($this->root . '/img/modules', 0777, true);
        try {
            (new DataDirectoryRemover($this->root, 'mymodule'))->remove($path);
            self::fail('No exception for ' . $path);
        } catch (\InvalidArgumentException) {
            self::assertDirectoryExists($this->root . '/img/modules');
        }
    }

    public function testRemovesATreeWithFilesAndSubdirectories(): void
    {
        mkdir($this->root . '/img/modules/mymodule/sub/deeper', 0777, true);
        file_put_contents($this->root . '/img/modules/mymodule/a.png', 'a');
        file_put_contents($this->root . '/img/modules/mymodule/.htaccess', 'deny');
        file_put_contents($this->root . '/img/modules/mymodule/sub/deeper/b.svg', 'b');

        self::assertTrue((new DataDirectoryRemover($this->root, 'mymodule'))->remove('img/modules/mymodule'));
        self::assertDirectoryDoesNotExist($this->root . '/img/modules/mymodule');
        self::assertDirectoryExists($this->root . '/img/modules');
    }

    public function testAMissingDirectoryIsNoError(): void
    {
        self::assertTrue((new DataDirectoryRemover($this->root, 'mymodule'))->remove('img/modules/mymodule'));
    }

    public function testAFileAtThePathIsDeleted(): void
    {
        mkdir($this->root . '/img/modules', 0777, true);
        file_put_contents($this->root . '/img/modules/mymodule', 'x');

        self::assertTrue((new DataDirectoryRemover($this->root, 'mymodule'))->remove('img/modules/mymodule'));
        self::assertFileDoesNotExist($this->root . '/img/modules/mymodule');
    }

    public function testALinkInsideTheTreeIsRemovedButItsTargetIsKept(): void
    {
        mkdir($this->root . '/img/modules/mymodule', 0777, true);
        mkdir($this->root . '/keep');
        file_put_contents($this->root . '/keep/important.txt', 'keep');
        if (!@symlink($this->root . '/keep', $this->root . '/img/modules/mymodule/link')) {
            self::markTestSkipped('This file system or user cannot create symbolic links.');
        }

        self::assertTrue((new DataDirectoryRemover($this->root, 'mymodule'))->remove('img/modules/mymodule'));
        self::assertDirectoryDoesNotExist($this->root . '/img/modules/mymodule');
        self::assertFileExists($this->root . '/keep/important.txt');
    }

    /**
     * Test helper: deletes a temporary tree.
     */
    private function deleteTree(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            is_dir($path) && !is_link($path) ? $this->deleteTree($path) : unlink($path);
        }
        rmdir($directory);
    }
}
