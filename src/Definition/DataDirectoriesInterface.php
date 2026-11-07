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

namespace PrestaShopHeaven\Runtime\Kit\Definition;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * A module definition that names the directories the module writes outside its own folder
 * (uploads in img/, files in var/modules/). The Uninstaller deletes them, so an uninstall
 * leaves no files behind.
 *
 * Separate from ModuleDefinitionInterface, so definitions that implement that interface
 * directly keep working; AbstractModuleDefinition implements both.
 */
interface DataDirectoriesInterface
{
    /**
     * Returns the directories to delete on uninstall, relative to the PrestaShop root
     * (for example 'img/modules/heavenfoo'). Each path must contain the technical name of
     * the module as one of its segments.
     *
     * @return string[]
     */
    public function dataDirectories(): array;
}
