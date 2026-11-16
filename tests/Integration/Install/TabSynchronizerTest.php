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
use PrestaShopHeaven\Runtime\Kit\Install\Installer;
use PrestaShopHeaven\Runtime\Kit\Install\TabSynchronizer;

/**
 * TabSynchronizer in a real shop, with the tabs of the installed fixture module (HEAVEN_FIXTURE_MODULE): an own menu
 * under SELL with a child, a hidden page, and a route whose legacy controller is not in the definition. The install left
 * exactly that (no "_MTR" copy of the own parent); a sync after the menu was changed by hand (an older version) puts it
 * back; leftovers are removed, tabs of others are never touched.
 */
final class TabSynchronizerTest extends TestCase
{
    private const PARENT = 'AdminParentHeavenmonocore';
    private const CHILD = 'AdminHeavenmonocoreItems';
    private const HIDDEN = 'AdminHeavenmonocoreConfiguration';
    private const ROUTE_CONTROLLER = 'AdminHeavenmonocoreExtra';

    /**
     * Puts the menu back as the definition wants it after each test (also when an assertion failed).
     */
    protected function tearDown(): void
    {
        $module = $this->module();
        (new Installer($module, $module->getModuleDefinition()))->syncTabs();
    }

    public function testTheInstallMadeTheOwnMenuWithoutACopyOfTheParent(): void
    {
        $parent = $this->tab(self::PARENT);
        $child = $this->tab(self::CHILD);

        self::assertSame((int) \Tab::getIdFromClassName('SELL'), (int) $parent->id_parent);
        self::assertSame((int) $parent->id, (int) $child->id_parent);
        self::assertTrue((bool) $parent->active);
        self::assertTrue((bool) $child->active);
        self::assertSame('extension', (string) $parent->icon);
        self::assertSame('heavenmonocore_configuration', (string) $child->route_name);
        self::assertFalse((bool) $this->tab(self::HIDDEN)->active);
        self::assertSame(0, (int) \Tab::getIdFromClassName(self::PARENT . TabSynchronizer::PARENT_COPY_SUFFIX));
        self::assertSame('Core fixture items', $child->name[(int) \Configuration::get('PS_LANG_DEFAULT')] ?? null);
        self::assertGreaterThan(0, (int) \Tab::getIdFromClassName(self::ROUTE_CONTROLLER), 'PrestaShop registers the legacy controller of a route');
    }

    public function testSyncPutsAChangedMenuBackAndRemovesLeftovers(): void
    {
        $module = $this->module();
        // As an older version left it: the child under Orders and hidden, the parent gone, a tab that is no longer
        // defined, and the "_MTR" copy PrestaShop makes of a parent without children.
        $child = $this->tab(self::CHILD);
        $child->id_parent = (int) \Tab::getIdFromClassName('AdminParentOrders');
        $child->active = false;
        $child->name = array_fill_keys(array_map('intval', array_column(\Language::getLanguages(false), 'id_lang')), 'Old name');
        self::assertTrue((bool) $child->update());
        $this->removeTab(self::PARENT);
        $old = $this->addTab('AdminHeavenmonocoreOld', $module->name);
        $copy = $this->addTab(self::PARENT . TabSynchronizer::PARENT_COPY_SUFFIX, $module->name);
        $foreign = $this->addTab('AdminHeavenmonocoreForeign', 'someothermodule');
        try {
            $synchronizer = new TabSynchronizer($module, $module->getDefinedTabs());

            self::assertTrue($synchronizer->sync(), implode(' ', $synchronizer->getErrors()));

            $parent = $this->tab(self::PARENT);
            $child = $this->tab(self::CHILD);
            self::assertSame((int) \Tab::getIdFromClassName('SELL'), (int) $parent->id_parent);
            self::assertSame((int) $parent->id, (int) $child->id_parent);
            self::assertTrue((bool) $child->active);
            self::assertSame('Core fixture items', $child->name[(int) \Configuration::get('PS_LANG_DEFAULT')] ?? null);
            self::assertSame(0, (int) \Tab::getIdFromClassName('AdminHeavenmonocoreOld'), 'a tab no longer defined is removed');
            self::assertSame(0, (int) \Tab::getIdFromClassName(self::PARENT . TabSynchronizer::PARENT_COPY_SUFFIX), 'the copy of an own parent is removed');
            self::assertGreaterThan(0, (int) \Tab::getIdFromClassName(self::ROUTE_CONTROLLER), 'the legacy controller of a route stays');
            self::assertGreaterThan(0, (int) \Tab::getIdFromClassName('AdminHeavenmonocoreForeign'), 'a tab of another module stays');
            self::assertSame([], $synchronizer->getErrors());
        } finally {
            $this->removeTab('AdminHeavenmonocoreForeign');
            $this->removeTab('AdminHeavenmonocoreOld');
            unset($old, $copy, $foreign);
        }
    }

    public function testATabOfAnotherModuleIsNeverTakenOver(): void
    {
        $module = $this->module();
        $this->addTab('AdminHeavenmonocoreTaken', 'someothermodule');
        try {
            $synchronizer = new TabSynchronizer($module, [
                ['class_name' => 'AdminHeavenmonocoreTaken', 'route_name' => 'heavenmonocore_configuration', 'parent_class_name' => '', 'visible' => false, 'name' => 'Taken'],
            ]);

            self::assertFalse($synchronizer->sync());
            self::assertStringContainsString('belongs to "someothermodule"', implode(' ', $synchronizer->getErrors()));
            self::assertSame('someothermodule', (string) $this->tab('AdminHeavenmonocoreTaken')->module);
        } finally {
            $this->removeTab('AdminHeavenmonocoreTaken');
        }
    }

    public function testParentsOfTheDefinitionAreCreatedBeforeTheirChildrenWhateverTheOrder(): void
    {
        $module = $this->module();
        $this->removeTab(self::CHILD);
        $this->removeTab(self::PARENT);
        $tabs = array_reverse($module->getDefinedTabs());

        $synchronizer = new TabSynchronizer($module, $tabs);

        self::assertTrue($synchronizer->sync(), implode(' ', $synchronizer->getErrors()));
        self::assertSame((int) $this->tab(self::PARENT)->id, (int) $this->tab(self::CHILD)->id_parent);
    }

    public function testACycleInTheDefinitionStillSavesEveryTab(): void
    {
        $module = $this->module();
        $synchronizer = new TabSynchronizer($module, [
            ['class_name' => 'AdminHeavenmonocoreCycleA', 'route_name' => 'heavenmonocore_configuration', 'parent_class_name' => 'AdminHeavenmonocoreCycleB', 'visible' => false, 'name' => ['en-US' => 'A']],
            ['class_name' => 'AdminHeavenmonocoreCycleB', 'route_name' => 'heavenmonocore_configuration', 'parent_class_name' => 'AdminHeavenmonocoreCycleA', 'visible' => false, 'name' => ['xx' => 'B']],
            ['class_name' => ''],
        ]);
        try {
            self::assertTrue($synchronizer->sync(), implode(' ', $synchronizer->getErrors()));
            self::assertGreaterThan(0, (int) \Tab::getIdFromClassName('AdminHeavenmonocoreCycleA'));
            self::assertGreaterThan(0, (int) \Tab::getIdFromClassName('AdminHeavenmonocoreCycleB'));
            self::assertSame('B', $this->tab('AdminHeavenmonocoreCycleB')->name[(int) \Configuration::get('PS_LANG_DEFAULT')] ?? null, 'an unknown language key falls back to the first name');
        } finally {
            $this->removeTab('AdminHeavenmonocoreCycleA');
            $this->removeTab('AdminHeavenmonocoreCycleB');
        }
    }

    public function testGetTabsGivesPrestaShopOnlyTheMissingTabsRightAfterInstall(): void
    {
        $module = $this->module();
        self::assertCount(count($module->getModuleDefinition()->tabs()), $module->getTabs(), 'outside an install every tab');

        $flag = new \ReflectionProperty($module, 'kitCreatedTabs');
        $flag->setValue($module, true);
        $this->removeTab(self::CHILD);

        self::assertSame([self::CHILD], array_column($module->getTabs(), 'class_name'), 'right after install only the missing tab');
        self::assertCount(count($module->getModuleDefinition()->tabs()), $module->getTabs(), 'only once');
    }

    /**
     * The installed fixture module.
     */
    private function module(): \Module
    {
        $module = \Module::getInstanceByName(HEAVEN_FIXTURE_MODULE);
        self::assertInstanceOf(\Module::class, $module);
        self::assertTrue(method_exists($module, 'getDefinedTabs'));

        return $module;
    }

    /**
     * The tab with this class name (fails the test when it does not exist).
     */
    private function tab(string $className): \Tab
    {
        \Tab::resetStaticCache();
        $id = (int) \Tab::getIdFromClassName($className);
        self::assertGreaterThan(0, $id, "tab $className");

        return new \Tab($id);
    }

    /**
     * Adds a hidden tab owned by a module.
     */
    private function addTab(string $className, string $moduleName): \Tab
    {
        $tab = new \Tab();
        $tab->class_name = $className;
        $tab->module = $moduleName;
        $tab->route_name = 'heavenmonocore_configuration';
        $tab->active = false;
        $tab->id_parent = 0;
        $tab->name = array_fill_keys(array_map('intval', array_column(\Language::getLanguages(false), 'id_lang')), $className);
        self::assertTrue((bool) $tab->add());
        \Tab::resetStaticCache();

        return $tab;
    }

    /**
     * Removes a tab when it exists.
     */
    private function removeTab(string $className): void
    {
        \Tab::resetStaticCache();
        $id = (int) \Tab::getIdFromClassName($className);
        if ($id > 0) {
            (new \Tab($id))->delete();
        }
        \Tab::resetStaticCache();
    }
}
