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
 * Deletes a data directory of a module (DataDirectoriesInterface) below the PrestaShop root.
 *
 * Plain PHP without PrestaShop, so the path checks are unit-tested. The checks keep a typo in
 * a definition from deleting a core folder: the path is relative, has no '.' or '..' segment,
 * and one of its segments is the technical name of the module. Symbolic links are removed,
 * never followed.
 */
final class DataDirectoryRemover
{
    /**
     * Takes the PrestaShop root (normally _PS_ROOT_DIR_) and the technical name of the module.
     *
     * @throws \InvalidArgumentException when the root is not a directory or the module name is empty
     */
    public function __construct(
        private readonly string $rootDirectory,
        private readonly string $moduleName,
    ) {
        if (!is_dir($rootDirectory)) {
            throw new \InvalidArgumentException(sprintf('The root "%s" is not a directory.', $rootDirectory));
        }
        if (!preg_match('/^[a-z][a-z0-9]*$/', $moduleName)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a technical module name.', $moduleName));
        }
    }

    /**
     * Returns the absolute path of a data directory after checking it.
     *
     * @throws \InvalidArgumentException when the path is absolute, empty, has a '.' or '..' segment,
     *                                   unusual characters, or no segment equal to the module name
     */
    public function resolve(string $relativePath): string
    {
        if ($relativePath === '' || $relativePath[0] === '/' || !preg_match('#^[A-Za-z0-9_./-]+$#', $relativePath)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a relative path of letters, digits, _ . - and /.', $relativePath));
        }
        $segments = explode('/', rtrim($relativePath, '/'));
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException(sprintf('"%s" has an empty, "." or ".." segment.', $relativePath));
            }
        }
        if (!in_array($this->moduleName, $segments, true)) {
            throw new \InvalidArgumentException(sprintf('"%s" has no segment "%s" (the module name).', $relativePath, $this->moduleName));
        }

        return rtrim($this->rootDirectory, '/\\') . '/' . implode('/', $segments);
    }

    /**
     * Deletes the directory with everything in it. A directory that does not exist is no error.
     *
     * @return bool false when something could not be deleted
     *
     * @throws \InvalidArgumentException when the path fails the checks of resolve()
     */
    public function remove(string $relativePath): bool
    {
        $path = $this->resolve($relativePath);
        if (is_link($path) || is_file($path)) {
            return unlink($path);
        }
        if (!is_dir($path)) {
            return true;
        }

        return $this->removeTree($path);
    }

    /**
     * Deletes a directory tree depth-first; links inside it are unlinked, not followed.
     */
    private function removeTree(string $directory): bool
    {
        // An unreadable folder gives no entries; rmdir() below then fails, so the result is false.
        $entries = scandir($directory) ?: [];
        $ok = true;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                $ok = $this->removeTree($path) && $ok;
            } else {
                $ok = unlink($path) && $ok;
            }
        }

        return rmdir($directory) && $ok;
    }
}
