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
 * Runs the SQL migrations of a module.
 *
 * Files are named "<YYYYMMDDHHMM> - <C|A|D|I|U> - <subject>.sql" and use {_DB_PREFIX_}.
 * SqlMigrations reads and runs the files; this class is the single place modules talk to.
 */
final class Migrator
{
    /** Database error of the last failed migration, for error messages. */
    private string $lastError = '';

    /** The message of a database exception during the run ('' when the database answered false instead). */
    private string $exceptionMessage = '';

    /**
     * Creates a migrator for one migration directory.
     *
     * @param string $directory absolute path of the directory with the .sql files
     */
    public function __construct(private readonly string $directory)
    {
    }

    /**
     * Runs every migration in the directory, in filename (timestamp) order.
     */
    public function runAll(): bool
    {
        $success = $this->engine()->runAll();
        $this->rememberError($success);

        return $success;
    }

    /**
     * Runs only the given migrations, in the given order (used by upgrade scripts).
     *
     * @param string[] $timestamps e.g. ['202611011200']
     */
    public function run(array $timestamps): bool
    {
        $engine = $this->engine();
        foreach ($timestamps as $timestamp) {
            if (!$engine->run($timestamp)) {
                $this->rememberError(false, $timestamp);

                return false;
            }
        }

        return true;
    }

    /**
     * Returns the reason the last migration failed, or an empty string.
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Prepares the migration engine for this directory and the shop's table prefix.
     */
    private function engine(): SqlMigrations
    {
        $this->exceptionMessage = '';

        return new SqlMigrations($this->directory, _DB_PREFIX_, function (string $query): bool {
            // In dev mode (_PS_MODE_DEV_) PrestaShop throws on a failing query instead of returning false: a failed
            // migration must fail the install with its message, not break it with an exception.
            try {
                return (bool) \Db::getInstance()->execute($query);
            } catch (\PrestaShopException $e) {
                $this->exceptionMessage = strip_tags(str_replace(['<br />', '<pre>'], ' ', $e->getMessage()));

                return false;
            }
        });
    }

    /**
     * Stores the database error after a failed run.
     *
     * @param bool $success result of the run
     * @param string $timestamp migration that failed, when known
     */
    private function rememberError(bool $success, string $timestamp = ''): void
    {
        if ($success) {
            $this->lastError = '';

            return;
        }
        $where = $timestamp !== '' ? "migration $timestamp" : 'migrations in ' . $this->directory;
        $this->lastError = trim($where . ': ' . ($this->exceptionMessage !== '' ? $this->exceptionMessage : \Db::getInstance()->getMsgError()));
    }
}
