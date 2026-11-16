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
 * Makes the back-office tabs of an installed module match its definition (ModuleDefinition::TABS), so an upgrade gives
 * the same menu as a fresh install (INS-07): PrestaShop only creates tabs on install.
 *
 * - a missing tab is created (a parent of the definition before its children), with its translated names;
 * - an existing tab gets the parent, route, visibility, icon and names of the definition (a tab that moves to another
 *   parent goes to the end of it, Tab::update());
 * - a tab of the module that is no longer in the definition is removed, unless a route of the module still uses it as
 *   `_legacy_controller` (PrestaShop registers those itself, hidden, and checks the rights on them);
 * - the copy "<Parent>_MTR" that PrestaShop's tab register makes of a parent tab of the module when the first child is
 *   added (ModuleTabRegister::duplicateParentIfAlone()) is removed: for a parent the module owns it is a second menu
 *   entry with the same page.
 */
final class TabSynchronizer
{
    /** Suffix of the copy PrestaShop makes of a parent tab without children (ModuleTabRegister::SUFFIX). */
    public const PARENT_COPY_SUFFIX = '_MTR';

    /** @var string[] messages of the steps that failed */
    private array $errors = [];

    /**
     * Creates the synchronizer of one module.
     *
     * @param \Module $module the installed module (its name owns the tabs)
     * @param array<int|string, array<string, mixed>> $tabs the tabs of the definition, as Module::getTabs() gives them:
     *                                                      class_name, route_name, parent_class_name, visible, icon,
     *                                                      and 'name' (one text, or per locale / language code / iso code)
     */
    public function __construct(
        private readonly \Module $module,
        private readonly array $tabs,
    ) {
    }

    /**
     * Returns the messages of the steps that failed.
     *
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Creates, updates and removes the module's tabs until they match the definition.
     *
     * @return bool true when every step succeeded; failures are kept in getErrors()
     */
    public function sync(): bool
    {
        $ok = true;
        foreach ($this->ordered() as $tab) {
            $ok = $this->saveTab($tab) && $ok;
        }
        $ok = $this->removeLeftovers() && $ok;
        \Tab::resetStaticCache();

        return $ok;
    }

    /**
     * The tabs of the definition with a class name, parents of the definition before their children (a tab whose
     * parent is another tab of the definition waits until that one is placed; a cycle keeps the definition order).
     *
     * @return list<array<string, mixed>>
     */
    private function ordered(): array
    {
        $pending = [];
        foreach ($this->tabs as $tab) {
            if (is_string($tab['class_name'] ?? null) && $tab['class_name'] !== '') {
                $pending[$tab['class_name']] = $tab;
            }
        }
        $ordered = [];
        while ($pending !== []) {
            $placed = false;
            foreach ($pending as $className => $tab) {
                $parent = (string) ($tab['parent_class_name'] ?? '');
                if ($parent === '' || !isset($pending[$parent]) || $parent === $className) {
                    $ordered[] = $tab;
                    unset($pending[$className]);
                    $placed = true;
                }
            }
            if (!$placed) {
                $ordered = array_merge($ordered, array_values($pending));
                $pending = [];
            }
        }

        return $ordered;
    }

    /**
     * Creates the tab, or brings the existing one in line with the definition.
     *
     * @param array<string, mixed> $definition
     */
    private function saveTab(array $definition): bool
    {
        $className = (string) $definition['class_name'];
        $id = (int) \Tab::getIdFromClassName($className);
        $tab = new \Tab($id > 0 ? $id : null);
        if ($id > 0 && (string) $tab->module !== $this->module->name) {
            // Never take over a tab of PrestaShop or of another module.
            return $this->fail(sprintf('Tab "%s" belongs to "%s", not to this module.', $className, (string) $tab->module));
        }
        $tab->class_name = $className;
        $tab->module = $this->module->name;
        $tab->route_name = (string) ($definition['route_name'] ?? '');
        $tab->active = !array_key_exists('visible', $definition) || (bool) $definition['visible'];
        $tab->icon = (string) ($definition['icon'] ?? '');
        $tab->id_parent = $this->parentId($definition);
        $tab->name = $this->names($definition['name'] ?? $className);
        if (isset($definition['wording'])) {
            $tab->wording = (string) $definition['wording'];
        }
        if (isset($definition['wording_domain'])) {
            $tab->wording_domain = (string) $definition['wording_domain'];
        }
        if ($id <= 0) {
            $tab->enabled = true;
        }
        $saved = $id > 0 ? $tab->update() : $tab->add();

        return $saved || $this->fail(sprintf('Saving tab "%s" failed.', $className));
    }

    /**
     * The id of the tab's parent: the parent class of the definition, 0 when there is none (a hidden tab) or when the
     * parent does not exist. For a parent of PrestaShop or another module its copy "<parent>_MTR" comes first, as in
     * PrestaShop's tab register (the children of a copied parent live under the copy); the module's own parents are
     * never copied.
     *
     * @param array<string, mixed> $definition
     */
    private function parentId(array $definition): int
    {
        $parent = (string) ($definition['parent_class_name'] ?? '');
        if ($parent === '') {
            return 0;
        }
        if (!in_array($parent, $this->definedClassNames(), true)) {
            $copy = (int) \Tab::getIdFromClassName($parent . self::PARENT_COPY_SUFFIX);
            if ($copy > 0) {
                return $copy;
            }
        }

        return (int) \Tab::getIdFromClassName($parent);
    }

    /**
     * The name per language id from the name of the definition (one text for every language, or per locale, language
     * code or iso code, else the first one), as PrestaShop's tab register does.
     *
     * @return array<int, string>
     */
    private function names(mixed $names): array
    {
        $result = [];
        foreach (\Language::getLanguages(false) as $language) {
            if (!is_array($names)) {
                $result[(int) $language['id_lang']] = (string) $names;
                continue;
            }
            $name = $names[$language['locale']] ?? $names[$language['language_code']] ?? $names[$language['iso_code']] ?? reset($names);
            $result[(int) $language['id_lang']] = (string) $name;
        }

        return $result;
    }

    /**
     * Removes the module's tabs that are not in the definition (and not the legacy controller of one of its routes),
     * including the copies "_MTR" of its own parent tabs.
     */
    private function removeLeftovers(): bool
    {
        $keep = array_merge($this->definedClassNames(), $this->routeControllers(), $this->legacyControllers());
        $rows = \Db::getInstance()->executeS(
            'SELECT `id_tab`, `class_name` FROM `' . _DB_PREFIX_ . 'tab` WHERE `module` = \'' . pSQL($this->module->name) . '\''
        );
        $ok = true;
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (in_array((string) $row['class_name'], $keep, true)) {
                continue;
            }
            $tab = new \Tab((int) $row['id_tab']);
            if (!$tab->delete()) {
                $ok = $this->fail(sprintf('Removing tab "%s" failed.', (string) $row['class_name']));
            }
        }

        return $ok;
    }

    /**
     * The class names of the definition.
     *
     * @return string[]
     */
    private function definedClassNames(): array
    {
        $names = [];
        foreach ($this->tabs as $tab) {
            if (is_string($tab['class_name'] ?? null) && $tab['class_name'] !== '') {
                $names[] = $tab['class_name'];
            }
        }

        return $names;
    }

    /**
     * The `_legacy_controller` values of the module's config/routes.yml: PrestaShop registers a hidden tab for each
     * one that is not in the definition, and checks the employee's rights on it.
     *
     * @return string[]
     */
    private function routeControllers(): array
    {
        $file = $this->module->getLocalPath() . 'config/routes.yml';
        if (!is_file($file)) {
            return [];
        }
        $content = (string) \Tools::file_get_contents($file);
        if (!preg_match_all('/_legacy_controller:\s*[\'"]?([A-Za-z0-9_]+)/', $content, $matches)) {
            return [];
        }

        return $matches[1];
    }

    /**
     * The legacy admin controllers of the module (controllers/admin/<Name>Controller.php): PrestaShop registers a
     * hidden tab for each one that is not in the definition.
     *
     * @return string[]
     */
    private function legacyControllers(): array
    {
        $names = [];
        foreach (glob($this->module->getLocalPath() . 'controllers/admin/*Controller.php') ?: [] as $file) {
            $names[] = substr(basename($file), 0, -strlen('Controller.php'));
        }

        return $names;
    }

    /**
     * Records a failed step: keeps the message and logs it.
     *
     * @return bool always false
     */
    private function fail(string $message): bool
    {
        $this->errors[] = $message;
        \PrestaShopLogger::addLog(sprintf('[%s] %s', $this->module->name, $message), \PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR);

        return false;
    }
}
