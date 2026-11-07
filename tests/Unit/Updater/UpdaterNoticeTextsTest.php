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

namespace PrestaShopHeaven\Runtime\Kit\Tests\Unit\Updater;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Runtime\Kit\Updater\UpdaterNoticeTexts;

/**
 * The texts of the PrestaHaven bar: every language has every text, and PrestaShop's language codes find them.
 */
final class UpdaterNoticeTextsTest extends TestCase
{
    private const KEYS = ['title', 'text', 'install', 'installing', 'installed', 'open', 'failed', 'download', 'info', 'hide', 'help', 'help_title', 'help_text'];
    /** The texts with the module name (%s) in them. */
    private const WITH_NAME = ['title', 'help_title'];

    public function testThereAreTheThirtyThreeModuleLanguages(): void
    {
        self::assertCount(33, UpdaterNoticeTexts::languages());
        self::assertContains('en', UpdaterNoticeTexts::languages());
        self::assertContains('nl', UpdaterNoticeTexts::languages());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function languages(): iterable
    {
        foreach (UpdaterNoticeTexts::languages() as $iso) {
            yield $iso => [$iso];
        }
    }

    #[DataProvider('languages')]
    public function testEveryLanguageHasEveryText(string $iso): void
    {
        $texts = UpdaterNoticeTexts::forLanguage($iso);

        self::assertSame(self::KEYS, array_keys($texts));
        foreach ($texts as $key => $text) {
            self::assertNotSame('', trim($text), $key);
            if (\in_array($key, self::WITH_NAME, true)) {
                self::assertSame(1, substr_count($text, '%s'), $key . ' has the module name once');
                self::assertSame(1, substr_count($text, '%'), $key . ' has no other placeholder');
            } else {
                self::assertStringNotContainsString('%', $text, $key);
            }
        }
    }

    #[DataProvider('languages')]
    public function testEveryLanguageIsTranslated(string $iso): void
    {
        if ('en' === $iso) {
            $this->expectNotToPerformAssertions();

            return;
        }
        $english = UpdaterNoticeTexts::forLanguage('en');
        $texts = UpdaterNoticeTexts::forLanguage($iso);

        foreach (['text', 'help_title', 'help_text'] as $key) {
            self::assertNotSame($english[$key], $texts[$key], $key);
        }
    }

    public function testTheHelpTextsInEnglishAndDutch(): void
    {
        $english = UpdaterNoticeTexts::forLanguage('en');
        $dutch = UpdaterNoticeTexts::forLanguage('nl');

        self::assertSame('Help & support', $english['help']);
        self::assertSame('Questions about %s?', $english['help_title']);
        self::assertStringContainsString('48 hours', $english['help_text']);
        self::assertSame('Hulp & support', $dutch['help']);
        self::assertSame('Vragen over %s?', $dutch['help_title']);
        self::assertStringContainsString('je binnen 48 uur', $dutch['help_text'], 'Dutch is informal');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function prestashopCodes(): iterable
    {
        yield 'Slovenian is si in PrestaShop' => ['si', 'sl'];
        yield 'Vietnamese is vn in PrestaShop' => ['vn', 'vi'];
        yield 'traditional Chinese falls back to Chinese' => ['tw', 'zh'];
        yield 'British English' => ['gb', 'en'];
        yield 'Brazilian Portuguese' => ['br', 'pt'];
        yield 'Mexican Spanish' => ['mx', 'es'];
        yield 'Quebec French' => ['qc', 'fr'];
        yield 'upper case and spaces' => [' DE ', 'de'];
    }

    #[DataProvider('prestashopCodes')]
    public function testPrestashopLanguageCodes(string $prestashop, string $iso): void
    {
        self::assertSame(UpdaterNoticeTexts::forLanguage($iso), UpdaterNoticeTexts::forLanguage($prestashop));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownLanguages(): iterable
    {
        yield 'unknown code' => ['xx'];
        yield 'empty' => [''];
        yield 'spaces only' => ['   '];
        yield 'a locale instead of a code' => ['nl-NL'];
    }

    #[DataProvider('unknownLanguages')]
    public function testAnUnknownLanguageFallsBackToEnglish(string $iso): void
    {
        self::assertSame(UpdaterNoticeTexts::forLanguage('en'), UpdaterNoticeTexts::forLanguage($iso));
    }
}
