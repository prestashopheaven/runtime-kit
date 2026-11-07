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
use PrestaShopHeaven\Runtime\Kit\Updater\UpdaterNotice;
use PrestaShopHeaven\Runtime\Kit\Updater\UpdaterNoticeTexts;

require_once __DIR__ . '/../prestashop-stubs.php';

/**
 * Which PrestaHaven bar shows where (updater bar, slim help bar, none), what each contains, the help button and its
 * ticket-form URL, and the PrestaShop glue (render(), helpLink()) against stand-ins for the PrestaShop classes.
 */
final class UpdaterNoticeTest extends TestCase
{
    private const OWN = ['AdminHeavenpricedropalertSubscriptions'];
    private const SUPPORT = 'https://updates.prestahaven.com/support?module=heavenpricedropalert&version=1.2.0&ps=9.2.0&php=8.1';

    protected function setUp(): void
    {
        $employee = new \Employee();
        $language = new \Language();
        $language->iso_code = 'en';
        $context = new \Context();
        $context->employee = $employee;
        $context->language = $language;
        $context->controller = null;
        $context->link = new \Link();
        \Context::$instance = $context;
        \Context::$fail = false;
        \Module::$installed = [];
        \Tools::$values = ['controller' => 'AdminModules', 'configure' => 'heavenpricedropalert'];
        \Access::$granted = true;
        unset($_COOKIE[UpdaterNotice::HIDE_COOKIE]);
    }

    protected function tearDown(): void
    {
        \Context::$instance = null;
        \Context::$fail = false;
        \Module::$installed = [];
        \Tools::$values = [];
        unset($_COOKIE[UpdaterNotice::HIDE_COOKIE]);
    }

    /**
     * @return iterable<string, array{string, string, string, bool, string, string, bool, bool}>
     */
    public static function pages(): iterable
    {
        $u = UpdaterNotice::BAR_UPDATER;
        $s = UpdaterNotice::BAR_SUPPORT;
        $n = UpdaterNotice::BAR_NONE;
        //            expected, module, key, updater installed, controller, configure, logged in, hidden
        yield 'configure page' => [$u, 'heavenpricedropalert', '', false, 'AdminModules', 'heavenpricedropalert', true, false];
        yield 'configure page, any case' => [$u, 'heavenpricedropalert', '', false, 'adminmodules', 'HeavenPriceDropAlert', true, false];
        yield 'own tab page' => [$u, 'heavenpricedropalert', '', false, 'AdminHeavenpricedropalertSubscriptions', '', true, false];
        yield 'own tab page, any case, key of spaces' => [$u, 'heavenpricedropalert', '  ', false, 'adminheavenpricedropalertsubscriptions', '', true, false];
        yield 'updater installed: slim bar' => [$s, 'heavenpricedropalert', '', true, 'AdminModules', 'heavenpricedropalert', true, false];
        yield 'updater installed, own tab: slim bar' => [$s, 'heavenpricedropalert', '', true, 'AdminHeavenpricedropalertSubscriptions', '', true, false];
        yield 'hidden for 30 days: slim bar' => [$s, 'heavenpricedropalert', '', false, 'AdminModules', 'heavenpricedropalert', true, true];
        yield 'hidden and updater installed: slim bar' => [$s, 'heavenpricedropalert', '', true, 'AdminModules', 'heavenpricedropalert', true, true];
        yield 'the updater itself: slim bar' => [$s, 'heavenupdater', '', false, 'AdminModules', 'heavenupdater', true, false];
        yield 'the updater itself, installed: slim bar' => [$s, 'heavenupdater', '', true, 'AdminModules', 'heavenupdater', true, false];
        yield 'another module\'s configure page' => [$n, 'heavenpricedropalert', '', false, 'AdminModules', 'heavenlangimages', true, false];
        yield 'module manager list' => [$n, 'heavenpricedropalert', '', false, 'AdminModules', '', true, false];
        yield 'another page' => [$n, 'heavenpricedropalert', '', false, 'AdminProducts', '', true, false];
        yield 'another page, updater installed' => [$n, 'heavenpricedropalert', '', true, 'AdminProducts', '', true, false];
        yield 'no controller' => [$n, 'heavenpricedropalert', '', false, '', '', true, false];
        yield 'Addons copy (module_key)' => [$n, 'heavenpricedropalert', 'a1b2c3', false, 'AdminModules', 'heavenpricedropalert', true, false];
        yield 'Addons copy, updater installed' => [$n, 'heavenpricedropalert', 'a1b2c3', true, 'AdminModules', 'heavenpricedropalert', true, false];
        yield 'not logged in' => [$n, 'heavenpricedropalert', '', false, 'AdminModules', 'heavenpricedropalert', false, false];
        yield 'not logged in, updater installed' => [$n, 'heavenpricedropalert', '', true, 'AdminModules', 'heavenpricedropalert', false, false];
    }

    #[DataProvider('pages')]
    public function testBar(string $expected, string $module, string $key, bool $updaterInstalled, string $controller, string $configure, bool $loggedIn, bool $hidden): void
    {
        self::assertSame($expected, UpdaterNotice::bar($module, $key, $updaterInstalled, $controller, $configure, self::OWN, $loggedIn, $hidden));
    }

    #[DataProvider('pages')]
    public function testShouldShowOnlyForTheUpdaterBar(string $expected, string $module, string $key, bool $updaterInstalled, string $controller, string $configure, bool $loggedIn, bool $hidden): void
    {
        self::assertSame(UpdaterNotice::BAR_UPDATER === $expected, UpdaterNotice::shouldShow($module, $key, $updaterInstalled, $controller, $configure, self::OWN, $loggedIn, $hidden));
    }

    public function testTheNoticeNeedsOnlyItsOwnHook(): void
    {
        self::assertSame(['displayAdminAfterHeader'], UpdaterNotice::hooks());
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function moduleKeys(): iterable
    {
        yield 'empty' => [true, ''];
        yield 'spaces' => [true, "  \t"];
        yield 'Addons key' => [false, 'a1b2c3d4e5f60718293a4b5c6d7e8f90'];
        yield 'one character' => [false, 'x'];
    }

    #[DataProvider('moduleKeys')]
    public function testIsDirectCopy(bool $expected, string $moduleKey): void
    {
        self::assertSame($expected, UpdaterNotice::isDirectCopy($moduleKey));
    }

    /**
     * @return iterable<string, array{string, string, string, string, string}>
     */
    public static function supportUrls(): iterable
    {
        $base = UpdaterNotice::SUPPORT_URL;
        //                  expected, module, version, PrestaShop, PHP
        yield 'all values' => [self::SUPPORT, 'heavenpricedropalert', '1.2.0', '9.2.0', '8.1.27'];
        yield 'PHP major.minor only' => [$base . '?module=heavenpricedropalert&version=1.2.0&ps=9.2.0&php=8.3', 'heavenpricedropalert', '1.2.0', '9.2.0', '8.3.0-dev'];
        yield 'PHP without patch' => [$base . '?module=heavenpricedropalert&version=1.2.0&ps=9.2.0&php=8.4', 'heavenpricedropalert', '1.2.0', '9.2.0', '8.4'];
        yield 'PHP 10.0' => [$base . '?module=heavenpricedropalert&version=1.2.0&ps=9.2.0&php=10.0', 'heavenpricedropalert', '1.2.0', '9.2.0', '10.0.1'];
        yield 'leading zeros dropped' => [$base . '?module=heavenpricedropalert&version=1.2.0&ps=9.2.0&php=8.1', 'heavenpricedropalert', '1.2.0', '9.2.0', '08.01.2'];
        yield 'module name lower-cased and trimmed' => [self::SUPPORT, ' HeavenPriceDropAlert ', '1.2.0', '9.2.0', '8.1.27'];
        yield 'versions trimmed' => [self::SUPPORT, 'heavenpricedropalert', ' 1.2.0 ', " 9.2.0\n", ' 8.1.27 '];
        yield 'pre-release versions' => [$base . '?module=heaven_x2&version=2.0.0-beta.1%2Bbuild&ps=8.2.0-rc&php=8.2', 'heaven_x2', '2.0.0-beta.1+build', '8.2.0-rc', '8.2.5'];
        yield 'module name of 64 characters' => [$base . '?module=' . str_repeat('a', 64), str_repeat('a', 64), '', '', ''];
        yield 'module name of 65 characters left out' => [$base . '?version=1.2.0', str_repeat('a', 65), '1.2.0', '', ''];
        yield 'module name with a dash left out' => [$base . '?version=1.2.0', 'heaven-price', '1.2.0', '', ''];
        yield 'module name with an injection left out' => [$base . '?ps=9.2.0', 'x&email=a@b.c', '', '9.2.0', ''];
        yield 'version of 32 characters' => [$base . '?version=' . str_repeat('1', 32), '', str_repeat('1', 32), '', ''];
        yield 'version of 33 characters left out' => [$base . '?ps=9.2.0', '', str_repeat('1', 33), '9.2.0', ''];
        yield 'version with a space left out' => [$base . '?module=heavenpricedropalert', 'heavenpricedropalert', '1.2 beta', '', ''];
        yield 'version with markup left out' => [$base . '?module=heavenpricedropalert', 'heavenpricedropalert', '"><script>', '<b>', ''];
        yield 'PHP without minor left out' => [$base . '?module=heavenpricedropalert', 'heavenpricedropalert', '', '', '8'];
        yield 'PHP minor of three digits left out' => [$base . '?module=heavenpricedropalert', 'heavenpricedropalert', '', '', '8.123'];
        yield 'PHP garbage left out' => [$base . '?module=heavenpricedropalert', 'heavenpricedropalert', '', '', 'php8.1'];
        yield 'nothing usable: the bare form' => [$base, '', '', '', ''];
        yield 'only spaces: the bare form' => [$base, '  ', '  ', '  ', '  '];
    }

    #[DataProvider('supportUrls')]
    public function testSupportUrl(string $expected, string $module, string $version, string $prestashop, string $php): void
    {
        self::assertSame($expected, UpdaterNotice::supportUrl($module, $version, $prestashop, $php));
    }

    public function testHelpButton(): void
    {
        $html = UpdaterNotice::helpButton(UpdaterNoticeTexts::forLanguage('en'), self::SUPPORT);

        self::assertSame(
            '<a class="btn btn-default prestahaven-help" href="https://updates.prestahaven.com/support?module=heavenpricedropalert&amp;version=1.2.0&amp;ps=9.2.0&amp;php=8.1" target="_blank" rel="noopener" title="Open a ticket and we will reply within 48 hours.">Help &amp; support</a>',
            $html,
        );
    }

    public function testSmallHelpButtonInAnotherLanguage(): void
    {
        $html = UpdaterNotice::helpButton(UpdaterNoticeTexts::forLanguage('de'), self::SUPPORT, true);

        self::assertStringStartsWith('<a class="btn btn-default btn-sm prestahaven-help" ', $html);
        self::assertStringContainsString('>Hilfe &amp; Support</a>', $html);
    }

    public function testHelpButtonEscapesEverything(): void
    {
        $texts = ['help' => '<b>Help</b>', 'help_text' => '"quoted"'] + UpdaterNoticeTexts::forLanguage('en');
        $html = UpdaterNotice::helpButton($texts, 'javascript:"x"><img src=x>');

        self::assertStringContainsString('href="javascript:&quot;x&quot;&gt;&lt;img src=x&gt;"', $html);
        self::assertStringContainsString('title="&quot;quoted&quot;"', $html);
        self::assertStringContainsString('>&lt;b&gt;Help&lt;/b&gt;</a>', $html);
        self::assertStringNotContainsString('<img', $html);
    }

    public function testHelpLinkForADirectCopy(): void
    {
        $html = UpdaterNotice::helpLinkFor('heavenproductchangelog', '', '1.1.0', 'nl');

        self::assertStringContainsString('class="btn btn-default btn-sm prestahaven-help"', $html);
        self::assertStringContainsString(
            'href="https://updates.prestahaven.com/support?module=heavenproductchangelog&amp;version=1.1.0&amp;ps=0.0.0-unit&amp;php=' . \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION . '"',
            $html,
        );
        self::assertStringContainsString('target="_blank" rel="noopener"', $html);
        self::assertStringContainsString('>Hulp &amp; support</a>', $html);
    }

    public function testHelpLinkForAnUnknownLanguageIsEnglish(): void
    {
        self::assertStringContainsString('>Help &amp; support</a>', UpdaterNotice::helpLinkFor('heavenproductchangelog', '', '1.1.0', 'xx'));
    }

    public function testHelpLinkForReturnsNothingForAnAddonsCopy(): void
    {
        self::assertSame('', UpdaterNotice::helpLinkFor('heavenproductchangelog', 'a1b2c3', '1.1.0', 'en'));
    }

    public function testSlimHtml(): void
    {
        $html = UpdaterNotice::slimHtml('Price Drop Alerts', UpdaterNoticeTexts::forLanguage('en'), self::SUPPORT);

        self::assertStringStartsWith('<div id="prestahaven-support-bar" class="alert alert-info" role="note"', $html);
        self::assertStringContainsString('border-left:4px solid #E31B87;background:#fdf2f8', $html);
        self::assertStringContainsString('width="20" height="20"', $html, 'the small PrestaHaven logo');
        self::assertStringContainsString('<strong>Questions about Price Drop Alerts?</strong> Open a ticket and we will reply within 48 hours.', $html);
        self::assertStringContainsString('class="btn btn-default btn-sm prestahaven-help" href="https://updates.prestahaven.com/support?module=heavenpricedropalert&amp;version=1.2.0', $html);
        self::assertStringNotContainsString('prestahaven-updater-hide', $html, 'no hide link');
        self::assertStringNotContainsString('<script', $html, 'no script');
        self::assertStringNotContainsString('data-install-url', $html);
    }

    public function testSlimHtmlInDutch(): void
    {
        $html = UpdaterNotice::slimHtml('Prijsdalingsmeldingen', UpdaterNoticeTexts::forLanguage('nl'), self::SUPPORT);

        self::assertStringContainsString('<strong>Vragen over Prijsdalingsmeldingen?</strong> Maak een ticket aan, dan krijg je binnen 48 uur antwoord.', $html);
        self::assertStringContainsString('>Hulp &amp; support</a>', $html);
    }

    public function testSlimHtmlEscapesEverything(): void
    {
        $html = UpdaterNotice::slimHtml('<script>alert(1)</script>', UpdaterNoticeTexts::forLanguage('en'), '"><img src=x onerror=alert(1)>');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('Questions about &lt;script&gt;alert(1)&lt;/script&gt;?', $html);
        self::assertStringNotContainsString('"><img', $html);
        self::assertStringContainsString('href="&quot;&gt;&lt;img src=x onerror=alert(1)&gt;"', $html);
    }

    public function testHtmlWithInstallButton(): void
    {
        $html = UpdaterNotice::html('Price Drop Alerts', UpdaterNoticeTexts::forLanguage('en'), 'https://shop.test/admin/modules/manage/action/install/heavenupdater?_token=abc&source=https%3A%2F%2Fupdates.prestahaven.com%2Fdownload%2Fheavenupdater.zip', 'https://shop.test/admin/updates', self::SUPPORT);

        self::assertStringContainsString('Get one-click updates for Price Drop Alerts', $html);
        self::assertStringContainsString('data-install-url="https://shop.test/admin/modules/manage/action/install/heavenupdater?_token=abc&amp;source=https%3A%2F%2Fupdates.prestahaven.com%2Fdownload%2Fheavenupdater.zip"', $html);
        self::assertStringContainsString('data-label="Install now"', $html);
        self::assertStringContainsString('data-open-url="https://shop.test/admin/updates"', $html);
        self::assertStringContainsString('data-download-url="' . UpdaterNotice::DOWNLOAD_URL . '"', $html);
        self::assertStringContainsString('href="' . UpdaterNotice::INFO_URL . '"', $html);
        self::assertStringContainsString(UpdaterNotice::HIDE_COOKIE . '=1; max-age=2592000', $html);
        self::assertStringContainsString('width="34" height="34"', $html, 'the PrestaHaven logo');
    }

    public function testHtmlHasTheHelpButtonNextToMoreInformation(): void
    {
        $html = UpdaterNotice::html('Price Drop Alerts', UpdaterNoticeTexts::forLanguage('en'), null, 'https://shop.test/admin/updates', self::SUPPORT);

        self::assertStringContainsString(
            '>More information</a> <a class="btn btn-default prestahaven-help" href="https://updates.prestahaven.com/support?module=heavenpricedropalert&amp;version=1.2.0&amp;ps=9.2.0&amp;php=8.1" target="_blank" rel="noopener" title="Open a ticket and we will reply within 48 hours.">Help &amp; support</a> <a href="#" class="prestahaven-updater-hide"',
            $html,
        );
    }

    public function testHtmlWithoutASupportUrlHasNoHelpButton(): void
    {
        $html = UpdaterNotice::html('Price Drop Alerts', UpdaterNoticeTexts::forLanguage('en'), null, 'https://shop.test/admin/updates');

        self::assertStringNotContainsString('prestahaven-help', $html);
        self::assertStringContainsString('>More information</a> <a href="#" class="prestahaven-updater-hide"', $html);
    }

    public function testHtmlWithoutTheRightToInstallHasOnlyTheLinks(): void
    {
        $html = UpdaterNotice::html('Price Drop Alerts', UpdaterNoticeTexts::forLanguage('nl'), null, 'https://shop.test/admin/updates', self::SUPPORT);

        self::assertStringNotContainsString('data-install-url="', $html, 'no install button');
        self::assertStringContainsString('Updates met één klik voor Price Drop Alerts', $html);
        self::assertStringContainsString('Meer informatie', $html);
        self::assertStringContainsString('Hulp &amp; support', $html);
    }

    public function testHtmlEscapesEverything(): void
    {
        $html = UpdaterNotice::html('<script>alert(1)</script>', UpdaterNoticeTexts::forLanguage('en'), '"><img src=x onerror=alert(1)>', 'javascript:"x"', '"><svg onload=alert(1)>');

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('"><img', $html);
        self::assertStringNotContainsString('"><svg onload', $html);
        self::assertStringContainsString('&quot;&gt;&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringContainsString('data-open-url="javascript:&quot;x&quot;"', $html);
        self::assertStringContainsString('href="&quot;&gt;&lt;svg onload=alert(1)&gt;"', $html);
    }

    // render(): the PrestaShop glue, against the stand-ins of prestashop-stubs.php.

    public function testRenderShowsTheUpdaterBarWithInstallAndHelpButtons(): void
    {
        \Context::getContext()->language->iso_code = 'nl';

        $html = UpdaterNotice::render($this->module(), self::OWN);

        self::assertStringContainsString('id="prestahaven-updater-notice"', $html);
        self::assertStringContainsString('Updates met één klik voor Price Drop Alerts', $html);
        self::assertStringContainsString('data-install-url="https://shop.test/admin/AdminModulesSf?route=admin_module_manage_action&amp;action=install&amp;module_name=heavenupdater&amp;source=https%3A%2F%2Fupdates.prestahaven.com%2Fdownload%2Fheavenupdater.zip"', $html);
        self::assertStringContainsString('data-open-url="https://shop.test/admin/AdminHeavenupdaterUpdates"', $html);
        self::assertStringContainsString('href="' . $this->expectedSupportUrl() . '"', $html);
        self::assertStringContainsString('>Hulp &amp; support</a>', $html);
    }

    public function testRenderWithoutTheRightToInstallHasNoInstallButton(): void
    {
        \Access::$granted = false;

        $html = UpdaterNotice::render($this->module(), self::OWN);

        self::assertStringContainsString('id="prestahaven-updater-notice"', $html);
        self::assertStringNotContainsString('data-install-url="', $html);
        self::assertStringContainsString('prestahaven-help', $html);
    }

    public function testRenderShowsTheSlimBarWhenTheUpdaterIsInstalled(): void
    {
        \Module::$installed = ['heavenupdater'];

        $html = UpdaterNotice::render($this->module(), self::OWN);

        self::assertStringStartsWith('<div id="prestahaven-support-bar"', $html);
        self::assertStringContainsString('Questions about Price Drop Alerts?', $html);
        self::assertStringContainsString('href="' . $this->expectedSupportUrl() . '"', $html);
        self::assertStringNotContainsString('prestahaven-updater-notice', $html);
    }

    public function testRenderShowsTheSlimBarWhenTheUpdaterBarIsHidden(): void
    {
        $_COOKIE[UpdaterNotice::HIDE_COOKIE] = '1';

        self::assertStringStartsWith('<div id="prestahaven-support-bar"', UpdaterNotice::render($this->module(), self::OWN));
    }

    public function testRenderUsesTheControllerOfTheContext(): void
    {
        $controller = new \Controller();
        $controller->controller_name = 'AdminHeavenpricedropalertSubscriptions';
        \Context::getContext()->controller = $controller;
        \Tools::$values = ['controller' => 'AdminProducts'];

        self::assertStringContainsString('id="prestahaven-updater-notice"', UpdaterNotice::render($this->module(), self::OWN));
    }

    public function testRenderFallsBackToTheControllerParameter(): void
    {
        \Context::getContext()->controller = new \Controller(); // no controller_name
        \Tools::$values = ['controller' => 'AdminHeavenpricedropalertSubscriptions'];

        self::assertStringContainsString('id="prestahaven-updater-notice"', UpdaterNotice::render($this->module(), self::OWN));
    }

    public function testRenderWithoutALanguageIsEnglish(): void
    {
        \Context::getContext()->language = null;

        self::assertStringContainsString('Get one-click updates for Price Drop Alerts', UpdaterNotice::render($this->module(), self::OWN));
    }

    public function testRenderOnAnotherPageIsEmpty(): void
    {
        \Tools::$values = ['controller' => 'AdminProducts'];

        self::assertSame('', UpdaterNotice::render($this->module(), self::OWN));
    }

    public function testRenderForAnAddonsCopyIsEmpty(): void
    {
        \Module::$installed = ['heavenupdater'];

        self::assertSame('', UpdaterNotice::render($this->module('a1b2c3'), self::OWN));
    }

    public function testRenderWithoutALoggedInEmployeeIsEmpty(): void
    {
        \Context::getContext()->employee->loggedBack = false;

        self::assertSame('', UpdaterNotice::render($this->module(), self::OWN));
    }

    public function testRenderWithoutAnEmployeeIsEmpty(): void
    {
        \Context::getContext()->employee = null;

        self::assertSame('', UpdaterNotice::render($this->module(), self::OWN));
    }

    public function testRenderWithoutAContextIsEmpty(): void
    {
        \Context::$instance = null;

        self::assertSame('', UpdaterNotice::render($this->module(), self::OWN));
    }

    public function testRenderNeverBreaksThePage(): void
    {
        \Context::$fail = true;

        self::assertSame('', UpdaterNotice::render($this->module(), self::OWN));
    }

    // helpLink(): the button a module embeds in its own template.

    public function testHelpLinkInTheEmployeesLanguage(): void
    {
        \Context::getContext()->language->iso_code = 'de';

        $html = UpdaterNotice::helpLink($this->module());

        self::assertStringStartsWith('<a class="btn btn-default btn-sm prestahaven-help" href="' . $this->expectedSupportUrl() . '" target="_blank" rel="noopener"', $html);
        self::assertStringContainsString('>Hilfe &amp; Support</a>', $html);
    }

    public function testHelpLinkShowsOnAnyPage(): void
    {
        \Tools::$values = ['controller' => 'AdminProducts'];
        \Module::$installed = ['heavenupdater'];

        self::assertStringContainsString('prestahaven-help', UpdaterNotice::helpLink($this->module()));
    }

    public function testHelpLinkWithoutALanguageIsEnglish(): void
    {
        \Context::getContext()->language = null;

        self::assertStringContainsString('>Help &amp; support</a>', UpdaterNotice::helpLink($this->module()));
    }

    public function testHelpLinkForAnAddonsCopyIsEmpty(): void
    {
        self::assertSame('', UpdaterNotice::helpLink($this->module('a1b2c3')));
    }

    public function testHelpLinkWithoutALoggedInEmployeeIsEmpty(): void
    {
        \Context::getContext()->employee->loggedBack = false;

        self::assertSame('', UpdaterNotice::helpLink($this->module()));
    }

    public function testHelpLinkWithoutAnEmployeeIsEmpty(): void
    {
        \Context::getContext()->employee = null;

        self::assertSame('', UpdaterNotice::helpLink($this->module()));
    }

    public function testHelpLinkWithoutAContextIsEmpty(): void
    {
        \Context::$instance = null;

        self::assertSame('', UpdaterNotice::helpLink($this->module()));
    }

    public function testHelpLinkNeverBreaksThePage(): void
    {
        \Context::$fail = true;

        self::assertSame('', UpdaterNotice::helpLink($this->module()));
    }

    private function module(string $moduleKey = ''): \Module
    {
        $module = new \Module();
        $module->name = 'heavenpricedropalert';
        $module->displayName = 'Price Drop Alerts';
        $module->version = '1.2.0';
        $module->module_key = $moduleKey;

        return $module;
    }

    private function expectedSupportUrl(): string
    {
        return 'https://updates.prestahaven.com/support?module=heavenpricedropalert&amp;version=1.2.0&amp;ps=0.0.0-unit&amp;php=' . \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION;
    }
}
