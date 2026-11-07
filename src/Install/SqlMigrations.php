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

namespace PrestaShopHeaven\Runtime\Kit\Install;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The SQL files of one migration folder (the engine behind Migrator; was vanengers/prestashop-library-product-admin-helper's
 * Migration class). A file is "<stamp>.sql" or "<stamp> <anything>.sql", for example "202611011200 - C - rules.sql";
 * the part before the first space is its stamp. Files run in stamp order, each statement separately (split on ";"),
 * with {_DB_PREFIX_} replaced by the shop's table prefix.
 */
final class SqlMigrations
{
    /** @var array<string, string>|null stamp => file name without .sql, sorted by stamp; null until read */
    private ?array $index = null;

    /** @var callable(string): bool */
    private $execute;

    /**
     * Reads the migrations of one folder; nothing runs until runAll() or run().
     *
     * @param string $directory folder with the .sql files
     * @param string $prefix the table prefix that replaces {_DB_PREFIX_}
     * @param callable(string): bool $execute runs one statement (Migrator passes the PrestaShop database)
     */
    public function __construct(private readonly string $directory, private readonly string $prefix, callable $execute)
    {
        $this->execute = $execute;
    }

    /**
     * Runs every migration in stamp order; false at the first failure, or when the folder cannot be read.
     */
    public function runAll(): bool
    {
        $index = $this->index();
        if ($index === null) {
            return false;
        }
        foreach (array_keys($index) as $stamp) {
            if (!$this->run((string) $stamp)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Runs one migration by its stamp (a file name or path works too); false when it is unknown or a statement fails.
     */
    public function run(string $stamp): bool
    {
        $index = $this->index();
        $stamp = self::stampOf(pathinfo(basename($stamp), PATHINFO_EXTENSION) === 'sql' ? pathinfo(basename($stamp), PATHINFO_FILENAME) : basename($stamp));
        if ($index === null || !isset($index[$stamp])) {
            return false;
        }
        $contents = @file_get_contents($this->folder() . $index[$stamp] . '.sql');
        if ($contents === false) {
            return false;
        }
        foreach (explode(';', str_replace('{_DB_PREFIX_}', $this->prefix, $contents)) as $query) {
            $query = trim($query);
            if ($query !== '' && !($this->execute)($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The stamps of the migrations in the folder, in the order they run (empty when the folder cannot be read).
     *
     * @return list<string>
     */
    public function stamps(): array
    {
        return array_map('strval', array_keys($this->index() ?? []));
    }

    /**
     * The folder with a trailing separator.
     */
    private function folder(): string
    {
        return rtrim($this->directory, '/\\') . '/';
    }

    /**
     * Reads the folder once: stamp => file name, sorted by stamp; null when the folder cannot be read.
     *
     * @return array<string, string>|null
     */
    private function index(): ?array
    {
        if ($this->index !== null) {
            return $this->index;
        }
        if ($this->directory === '' || !is_dir($this->directory)) {
            return null;
        }
        $index = [];
        foreach (scandir($this->directory) ?: [] as $file) {
            if (str_ends_with($file, '.sql')) {
                $name = substr($file, 0, -4);
                $index[self::stampOf($name)] = $name;
            }
        }
        ksort($index, SORT_STRING);

        return $this->index = $index;
    }

    /**
     * The stamp of a file name: the part before the first space.
     */
    private static function stampOf(string $name): string
    {
        $space = strpos($name, ' ');

        return $space === false ? $name : substr($name, 0, $space);
    }
}
