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
use PrestaShopHeaven\Runtime\Kit\Install\TabBuilder;

require_once __DIR__ . '/../prestashop-stubs.php';

/**
 * TabBuilder gives every tab a name per shop language, translated in the module's tab domain, and the domain itself
 * (so PrestaShop can translate the name for a language added later). The real languages and translator are covered in
 * a real shop by the fixture module (ModuleBaseTest).
 */
final class TabBuilderTest extends TestCase
{
    private const LANGUAGES = [['locale' => 'en-US'], ['locale' => 'nl-NL']];

    public function testEveryTabGetsATranslatedNamePerLanguageAndTheDomain(): void
    {
        $tabs = TabBuilder::build(new \Module(), [
            ['class_name' => 'AdminFooRules', 'wording' => 'Rules', 'parent_class_name' => 'AdminParentOrders'],
            ['class_name' => 'AdminFooLog'],
        ], 'Modules.Heavenfoo.Admin', self::LANGUAGES);

        self::assertSame([
            [
                'class_name' => 'AdminFooRules',
                'wording' => 'Rules',
                'parent_class_name' => 'AdminParentOrders',
                'name' => ['en-US' => 'Rules [Modules.Heavenfoo.Admin/en-US]', 'nl-NL' => 'Rules [Modules.Heavenfoo.Admin/nl-NL]'],
                'wording_domain' => 'Modules.Heavenfoo.Admin',
            ],
            [
                'class_name' => 'AdminFooLog',
                // Without a wording the class name is the text.
                'name' => ['en-US' => 'AdminFooLog [Modules.Heavenfoo.Admin/en-US]', 'nl-NL' => 'AdminFooLog [Modules.Heavenfoo.Admin/nl-NL]'],
                'wording_domain' => 'Modules.Heavenfoo.Admin',
            ],
        ], $tabs);
    }

    public function testNoTabsGiveNoTabs(): void
    {
        self::assertSame([], TabBuilder::build(new \Module(), [], 'Modules.Heavenfoo.Admin', self::LANGUAGES));
    }

    /**
     * Tabs without a usable class name.
     *
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function tabsWithoutClassName(): iterable
    {
        yield 'missing' => [['wording' => 'Rules']];
        yield 'empty' => [['class_name' => '']];
        yield 'not a string' => [['class_name' => 12]];
    }

    /**
     * @param array<string, mixed> $tab
     */
    #[DataProvider('tabsWithoutClassName')]
    public function testATabWithoutClassNameIsRefused(array $tab): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tab 0 of ModuleDefinition::TABS has no class_name.');

        TabBuilder::build(new \Module(), [$tab], 'Modules.Heavenfoo.Admin', self::LANGUAGES);
    }

    public function testAnEmptyDomainIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TabBuilder::build(new \Module(), [['class_name' => 'AdminFoo']], '', self::LANGUAGES);
    }
}
