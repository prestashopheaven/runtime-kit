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

use PrestaShopHeaven\Runtime\Kit\Definition\DataDirectoriesInterface;
use PrestaShopHeaven\Runtime\Kit\Definition\ModuleDefinitionInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Removes everything a module created, after parent::uninstall().
 * Hooks and tabs are removed by PrestaShop itself; this class runs the down
 * migrations, deletes the configuration keys and the data directories of the module.
 */
final class Uninstaller
{
    /** @var string[] messages of failed steps, shown in the back office */
    private array $errors = [];

    /**
     * Creates an uninstaller for a module and its definition.
     *
     * @param \Module $module the module being uninstalled (its local path and name are used)
     * @param ModuleDefinitionInterface $definition configuration and migrations of the module
     */
    public function __construct(
        private readonly \Module $module,
        private readonly ModuleDefinitionInterface $definition,
    ) {
    }

    /**
     * Returns the messages of the steps that failed.
     *
     * @return string[] messages to show in the back office
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Runs the full uninstall: down migrations, configuration, then the data directories.
     */
    public function uninstall(): bool
    {
        return $this->runMigrations()
            && $this->deleteConfiguration()
            && $this->removeDataDirectories();
    }

    /**
     * Deletes the directories the module wrote outside its own folder (DataDirectoriesInterface),
     * relative to the PrestaShop root. A definition without that interface has none.
     */
    private function removeDataDirectories(): bool
    {
        if (!$this->definition instanceof DataDirectoriesInterface || $this->definition->dataDirectories() === []) {
            return true;
        }
        try {
            $remover = new DataDirectoryRemover(_PS_ROOT_DIR_, (string) $this->module->name);
            foreach ($this->definition->dataDirectories() as $directory) {
                if (!$remover->remove($directory)) {
                    return $this->fail(sprintf('Deleting the directory "%s" failed.', $directory));
                }
            }
        } catch (\InvalidArgumentException $e) {
            return $this->fail('Invalid data directory: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Runs every down migration in filename (timestamp) order. Down migrations must be
     * idempotent (DROP TABLE IF EXISTS, ...).
     */
    private function runMigrations(): bool
    {
        $migrator = new Migrator($this->module->getLocalPath() . $this->definition->migrationsDownDirectory());

        return $migrator->runAll() || $this->fail('Running the uninstall migrations failed: ' . $migrator->getLastError());
    }

    /**
     * Deletes every configuration key of the definition, including generated secrets.
     */
    private function deleteConfiguration(): bool
    {
        $keys = array_merge(array_keys($this->definition->configuration()), $this->definition->generatedConfiguration());
        foreach ($keys as $key) {
            if (!\Configuration::deleteByName($key)) {
                return $this->fail(sprintf('Deleting configuration "%s" failed.', $key));
            }
        }

        return true;
    }

    /**
     * Records a failed step: keeps the message for the back office and logs it.
     *
     * @return bool always false, so a step can `return $this->fail(...)`
     */
    private function fail(string $message): bool
    {
        $this->errors[] = $message;
        \PrestaShopLogger::addLog(sprintf('[%s] %s', $this->module->name, $message), 3);

        return false;
    }
}
