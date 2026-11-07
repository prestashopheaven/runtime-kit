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

use PrestaShopHeaven\Runtime\Kit\Definition\ModuleDefinitionInterface;
use PrestaShopHeaven\Runtime\Kit\Updater\UpdaterNotice;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Runs everything a module needs on install, after parent::install(), and brings an
 * existing installation in line with the module definition during upgrades.
 * Each step returns false on failure and logs the reason.
 */
final class Installer
{
    /** @var string[] messages of failed steps, shown in the back office */
    private array $errors = [];

    /**
     * Creates an installer for a module and its definition.
     *
     * @param \Module $module the module being installed (its local path and name are used)
     * @param ModuleDefinitionInterface $definition hooks, configuration and migrations of the module
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
     * Runs a full install: all up migrations, all hooks, configuration with default
     * values (overwriting existing values) and generated secrets.
     */
    public function install(): bool
    {
        return $this->runAllMigrations()
            && $this->registerHooks()
            && $this->installConfiguration(true)
            && $this->installGeneratedConfiguration();
    }

    /**
     * For upgrades: makes an existing installation match the definition. Registers
     * every hook (registering twice is harmless) and adds configuration keys that do
     * not exist yet. Existing values are never overwritten.
     */
    public function syncDefinition(): bool
    {
        return $this->registerHooks()
            && $this->installConfiguration(false)
            && $this->installGeneratedConfiguration();
    }

    /**
     * Runs every up migration of the module, in filename (timestamp) order (install).
     */
    public function runAllMigrations(): bool
    {
        $migrator = new Migrator($this->module->getLocalPath() . $this->definition->migrationsUpDirectory());

        return $migrator->runAll() || $this->fail('Running the install migrations failed: ' . $migrator->getLastError());
    }

    /**
     * Runs only the given up migrations, in the given order (upgrades). An empty list
     * runs nothing, so an upgrade without new migrations cannot re-run old ones by accident.
     *
     * @param string[] $timestamps timestamps of the migrations to run, e.g. ['202611011200']
     */
    public function runMigrations(array $timestamps): bool
    {
        if ($timestamps === []) {
            return true;
        }
        $migrator = new Migrator($this->module->getLocalPath() . $this->definition->migrationsUpDirectory());

        return $migrator->run($timestamps) || $this->fail('Running the upgrade migrations failed: ' . $migrator->getLastError());
    }

    /**
     * Registers every hook of the definition, plus the updater notice's hook when the notice is part of this build
     * (the direct-sale build; the Addons build leaves the Updater folder out).
     */
    private function registerHooks(): bool
    {
        $hooks = $this->definition->hooks();
        if (class_exists(UpdaterNotice::class)) {
            $hooks = array_values(array_unique(array_merge($hooks, UpdaterNotice::hooks())));
        }
        foreach ($hooks as $hook) {
            if (!$this->module->registerHook($hook)) {
                return $this->fail(sprintf('Registering hook "%s" failed.', $hook));
            }
        }

        return true;
    }

    /**
     * Saves the configuration keys of the definition with their default value, as global
     * values: every shop inherits them until the merchant sets a value per shop or group (MS-03).
     *
     * @param bool $overwrite true on install (also removes leftover shop and group values); false on
     *                        upgrade, so values set by the merchant stay
     */
    private function installConfiguration(bool $overwrite): bool
    {
        foreach ($this->definition->configuration() as $key => $default) {
            if (!$overwrite && \Configuration::hasKey($key, null, 0, 0)) {
                continue;
            }
            if ($overwrite) {
                \Configuration::deleteByName($key);
            }
            if (!\Configuration::updateGlobalValue($key, $default)) {
                return $this->fail(sprintf('Saving configuration "%s" failed.', $key));
            }
        }

        return true;
    }

    /**
     * Gives every generated configuration key a random global secret, unless it already has a
     * global value (a token must not change on upgrade, and must work in every shop).
     */
    private function installGeneratedConfiguration(): bool
    {
        foreach ($this->definition->generatedConfiguration() as $key) {
            if ((string) \Configuration::getGlobalValue($key) !== '') {
                continue;
            }
            if (!\Configuration::updateGlobalValue($key, bin2hex(random_bytes(16)))) {
                return $this->fail(sprintf('Saving configuration "%s" failed.', $key));
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
        \PrestaShopLogger::addLog(sprintf('[%s] %s', $this->module->name, $message), \PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR);

        return false;
    }
}
