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

namespace PrestaShopHeaven\Runtime\Kit\Updater;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The passive PrestaHaven bar (update-system/PLAN.md §6). It only reads local state: no network call, no dependency,
 * and the module works the same without it. Shown on the module's own back-office pages (configure page and own admin
 * tabs), only for direct-sale copies (empty module_key) and a logged-in employee:
 *
 * - heavenupdater not installed and the bar not hidden: the updater bar with "Install now", "More information",
 *   "Help & support" and "Hide for 30 days". "Install now" posts to PrestaShop's own Module Manager route (install from
 *   the public zip URL), so PrestaShop checks the employee's rights and demo mode;
 * - otherwise (updater installed, updater bar hidden for 30 days, or heavenupdater itself): a slim one-line bar
 *   "Questions about <module>?" with only the "Help & support" button.
 *
 * "Help & support" opens the public ticket form of the update server in a new tab, prefilled with the module's
 * technical name and version, the PrestaShop version and PHP major.minor (supportUrl(); nothing else is sent).
 * A module without pages of its own can embed the button in its own template with helpLink().
 *
 * The Addons build leaves this folder out (ps-module build --channel=addons): then the hook is not registered,
 * ModuleBase::hookDisplayAdminAfterHeader() returns '' and a module's class_exists() guard around helpLink() is false.
 */
final class UpdaterNotice
{
    public const HOOK = 'displayAdminAfterHeader';
    public const UPDATER_MODULE = 'heavenupdater';
    public const UPDATER_PAGE = 'AdminHeavenupdaterUpdates';
    public const DOWNLOAD_URL = 'https://updates.prestahaven.com/download/heavenupdater.zip';
    public const INFO_URL = 'https://updates.prestahaven.com/';
    /** The public ticket form; prefilled through the query parameters module, version, ps and php. */
    public const SUPPORT_URL = 'https://updates.prestahaven.com/support';
    /** Set by "Hide for 30 days" (browser cookie, all PrestaHaven modules); hides the updater part only. */
    public const HIDE_COOKIE = 'prestahaven_updater_notice_hidden';
    /** bar(): no bar on this page. */
    public const BAR_NONE = '';
    /** bar(): the "PrestaHaven Updater not installed" bar (with the help button). */
    public const BAR_UPDATER = 'updater';
    /** bar(): the slim "Questions about <module>?" bar with only the help button. */
    public const BAR_SUPPORT = 'support';

    private const LOGO = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="%1$d" height="%1$d" aria-hidden="true"><polygon points="62,105 193,0 336,72 205,182" fill="#802982"/><polygon points="62,105 205,182 205,512 62,435" fill="#E31B87"/><polygon points="256,203 350,126 452,178 358,256" fill="#802982"/><polygon points="256,203 358,256 358,512 256,460" fill="#E31B87"/></svg>';

    /**
     * The hooks the notice needs (registered by the Installer next to the module's own hooks).
     *
     * @return list<string>
     */
    public static function hooks(): array
    {
        return [self::HOOK];
    }

    /**
     * Which bar belongs on this page: BAR_UPDATER, BAR_SUPPORT or BAR_NONE.
     *
     * @param string $controller the current back-office controller (legacy name; Symfony pages give their _legacy_controller)
     * @param string $configure the "configure" request parameter (module configuration page)
     * @param list<string> $ownControllers the module's own admin controllers (its tabs' class names)
     * @param bool $hidden the updater bar is hidden for 30 days (HIDE_COOKIE): the slim bar shows instead
     */
    public static function bar(
        string $moduleName,
        string $moduleKey,
        bool $updaterInstalled,
        string $controller,
        string $configure,
        array $ownControllers,
        bool $employeeLoggedIn,
        bool $hidden,
    ): string {
        if (!$employeeLoggedIn || !self::isDirectCopy($moduleKey)) {
            return self::BAR_NONE;
        }
        $onConfigurePage = 0 === strcasecmp($controller, 'AdminModules') && 0 === strcasecmp($configure, $moduleName);
        $onOwnPage = '' !== $controller && \in_array(strtolower($controller), array_map('strtolower', $ownControllers), true);
        if (!$onConfigurePage && !$onOwnPage) {
            return self::BAR_NONE;
        }

        return $updaterInstalled || $hidden || self::UPDATER_MODULE === $moduleName ? self::BAR_SUPPORT : self::BAR_UPDATER;
    }

    /**
     * Whether the "PrestaHaven Updater not installed" bar belongs on this page (bar() is BAR_UPDATER).
     *
     * @param list<string> $ownControllers the module's own admin controllers (its tabs' class names)
     */
    public static function shouldShow(
        string $moduleName,
        string $moduleKey,
        bool $updaterInstalled,
        string $controller,
        string $configure,
        array $ownControllers,
        bool $employeeLoggedIn,
        bool $hidden,
    ): bool {
        return self::BAR_UPDATER === self::bar($moduleName, $moduleKey, $updaterInstalled, $controller, $configure, $ownControllers, $employeeLoggedIn, $hidden);
    }

    /** Whether this is a direct-sale copy (empty module_key); Addons copies have a module_key and get no bar or link. */
    public static function isDirectCopy(string $moduleKey): bool
    {
        return '' === trim($moduleKey);
    }

    /**
     * The ticket form URL, prefilled with the module's technical name and version, the PrestaShop version and PHP
     * major.minor. Nothing else is sent. A value that does not fit its format is left out (the form works without it).
     *
     * @param string $phpVersion PHP_VERSION (only major.minor is sent)
     */
    public static function supportUrl(string $moduleName, string $moduleVersion, string $prestashopVersion, string $phpVersion): string
    {
        $query = [];
        $name = strtolower(trim($moduleName));
        if (1 === preg_match('/^[a-z0-9_]{1,64}$/', $name)) {
            $query['module'] = $name;
        }
        foreach (['version' => $moduleVersion, 'ps' => $prestashopVersion] as $key => $value) {
            $value = trim($value);
            if (1 === preg_match('/^[0-9A-Za-z.+-]{1,32}$/', $value)) {
                $query[$key] = $value;
            }
        }
        if (1 === preg_match('/^(\d{1,2})\.(\d{1,2})(?:\D|$)/', trim($phpVersion), $match)) {
            $query['php'] = (int) $match[1] . '.' . (int) $match[2];
        }

        return [] === $query ? self::SUPPORT_URL : self::SUPPORT_URL . '?' . http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
    }

    /**
     * The "Help & support" button (escaped): opens $supportUrl in a new tab.
     *
     * @param array<string, string> $texts UpdaterNoticeTexts::forLanguage()
     * @param bool $small the compact size (slim bar, helpLink())
     */
    public static function helpButton(array $texts, string $supportUrl, bool $small = false): string
    {
        return '<a class="btn btn-default' . ($small ? ' btn-sm' : '') . ' prestahaven-help" href="' . self::escape($supportUrl)
            . '" target="_blank" rel="noopener" title="' . self::escape($texts['help_text']) . '">' . self::escape($texts['help']) . '</a>';
    }

    /**
     * The compact "Help & support" button for a module's own template, or '' for an Addons copy (module_key set).
     * The part of helpLink() that does not need PrestaShop.
     *
     * @param string $language PrestaShop language code (UpdaterNoticeTexts::forLanguage())
     */
    public static function helpLinkFor(string $moduleName, string $moduleKey, string $moduleVersion, string $language): string
    {
        if (!self::isDirectCopy($moduleKey)) {
            return '';
        }

        return self::helpButton(UpdaterNoticeTexts::forLanguage($language), self::supportUrl($moduleName, $moduleVersion, _PS_VERSION_, \PHP_VERSION), true);
    }

    /**
     * The compact "Help & support" button for a module's own template (e.g. a panel in the product form, for a module
     * without pages of its own), in the employee's language; '' for an Addons copy, without a logged-in employee, or on
     * any error. The Addons build has no Updater folder, so always guard the call (::class, so scoping renames it):
     *
     * `$help = class_exists(UpdaterNotice::class) ? UpdaterNotice::helpLink($this) : '';`
     *
     * The result is escaped HTML: print it raw (`{{ help|raw }}` in Twig, `{$help nofilter}` in Smarty).
     */
    public static function helpLink(\Module $module): string
    {
        try {
            $context = \Context::getContext();
            if (null === $context || !$context->employee instanceof \Employee || !$context->employee->isLoggedBack()) {
                return '';
            }
            $language = $context->language instanceof \Language ? (string) $context->language->iso_code : 'en';

            return self::helpLinkFor((string) $module->name, (string) $module->module_key, (string) $module->version, $language);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * The slim one-line bar (escaped): "Questions about <module>? Open a ticket…" plus the help button; no hide link.
     *
     * @param array<string, string> $texts UpdaterNoticeTexts::forLanguage()
     */
    public static function slimHtml(string $moduleDisplayName, array $texts, string $supportUrl): string
    {
        return '<div id="prestahaven-support-bar" class="alert alert-info" role="note" style="display:flex;gap:10px;align-items:center;border-left:4px solid #E31B87;background:#fdf2f8;color:#3C3C3B;margin:10px 0;padding:6px 12px">'
            . \sprintf(self::LOGO, 20)
            . '<div style="flex:1"><strong>' . self::escape(\sprintf($texts['help_title'], $moduleDisplayName)) . '</strong> ' . self::escape($texts['help_text']) . '</div>'
            . self::helpButton($texts, $supportUrl, true)
            . '</div>';
    }

    /**
     * The updater bar's HTML (escaped). Without an install URL (no right to install modules) only the links are shown;
     * without a support URL there is no help button.
     *
     * @param array<string, string> $texts UpdaterNoticeTexts::forLanguage()
     * @param string $supportUrl supportUrl(), or '' for no help button
     */
    public static function html(string $moduleDisplayName, array $texts, ?string $installUrl, string $updaterPageUrl, string $supportUrl = ''): string
    {
        $e = static fn (string $value): string => self::escape($value);
        $id = 'prestahaven-updater-notice';
        $install = null === $installUrl ? '' : \sprintf(
            '<button type="button" class="btn btn-primary" data-label="%s" data-install-url="%s" data-installing="%s" data-installed="%s" data-open="%s" data-open-url="%s" data-failed="%s" data-download="%s" data-download-url="%s" style="background:#E31B87;border-color:#E31B87">%s</button> ',
            $e($texts['install']),
            $e($installUrl),
            $e($texts['installing']),
            $e($texts['installed']),
            $e($texts['open']),
            $e($updaterPageUrl),
            $e($texts['failed']),
            $e($texts['download']),
            $e(self::DOWNLOAD_URL),
            $e($texts['install']),
        );

        return '<div id="' . $id . '" class="alert alert-info" role="status" style="display:flex;gap:14px;align-items:center;border-left:4px solid #E31B87;background:#fdf2f8;color:#3C3C3B;margin:10px 0">'
            . \sprintf(self::LOGO, 34)
            . '<div style="flex:1"><strong>' . $e(\sprintf($texts['title'], $moduleDisplayName)) . '</strong><br>' . $e($texts['text'])
            . '<div class="prestahaven-updater-actions" style="margin-top:8px">' . $install
            . '<a class="btn btn-default" href="' . $e(self::INFO_URL) . '" target="_blank" rel="noopener">' . $e($texts['info']) . '</a> '
            . ('' === $supportUrl ? '' : self::helpButton($texts, $supportUrl) . ' ')
            . '<a href="#" class="prestahaven-updater-hide" style="margin-left:8px">' . $e($texts['hide']) . '</a>'
            . '<span class="prestahaven-updater-result" style="margin-left:8px"></span></div></div></div>'
            . self::script($id);
    }

    /** HTML-escapes a value for text and attributes (UTF-8, invalid characters replaced). */
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /** The bar's small script: install through PrestaShop's Module Manager route, or hide for 30 days. */
    private static function script(string $id): string
    {
        $cookie = self::HIDE_COOKIE;

        return <<<HTML
            <script>
            (function () {
              var bar = document.getElementById('{$id}');
              if (!bar) { return; }
              var result = bar.querySelector('.prestahaven-updater-result');
              var show = function (text, href, label) {
                result.textContent = text + ' ';
                if (href) { var a = document.createElement('a'); a.href = href; a.textContent = label; result.appendChild(a); }
              };
              bar.querySelector('.prestahaven-updater-hide').addEventListener('click', function (event) {
                event.preventDefault();
                document.cookie = '{$cookie}=1; max-age=2592000; path=/; SameSite=Lax';
                bar.parentNode.removeChild(bar);
              });
              var button = bar.querySelector('[data-install-url]');
              if (!button) { return; }
              button.addEventListener('click', function () {
                var d = button.dataset;
                button.disabled = true;
                button.textContent = d.installing;
                fetch(d.installUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                  .then(function (response) { return response.json(); })
                  .then(function (json) {
                    var answer = json && json.heavenupdater ? json.heavenupdater : json;
                    if (answer && answer.status === true) {
                      button.style.display = 'none';
                      show(d.installed, d.openUrl, d.open);
                    } else {
                      throw new Error(answer && answer.msg ? answer.msg : '');
                    }
                  })
                  .catch(function (error) {
                    button.disabled = false;
                    button.textContent = d.label;
                    show(d.failed + ' ' + (error && error.message ? error.message : ''), d.downloadUrl, d.download);
                  });
              });
            })();
            </script>
            HTML;
    }

    /**
     * Renders the bar for a module on the current back-office page (bar()), or '' (also on any error: it never breaks a
     * page).
     *
     * @param list<string> $ownControllers the module's admin controllers (its tabs' class names)
     */
    public static function render(\Module $module, array $ownControllers): string
    {
        try {
            $context = \Context::getContext();
            if (null === $context || !$context->employee instanceof \Employee || !$context->employee->isLoggedBack()) {
                return '';
            }
            $controller = $context->controller instanceof \Controller && \is_string($context->controller->controller_name ?? null)
                ? $context->controller->controller_name
                : (string) \Tools::getValue('controller');
            $bar = self::bar(
                (string) $module->name,
                (string) $module->module_key,
                (bool) \Module::isInstalled(self::UPDATER_MODULE),
                $controller,
                (string) \Tools::getValue('configure'),
                $ownControllers,
                true,
                isset($_COOKIE[self::HIDE_COOKIE]),
            );
            if (self::BAR_NONE === $bar) {
                return '';
            }
            $language = $context->language instanceof \Language ? (string) $context->language->iso_code : 'en';
            $texts = UpdaterNoticeTexts::forLanguage($language);
            $supportUrl = self::supportUrl((string) $module->name, (string) $module->version, _PS_VERSION_, \PHP_VERSION);
            if (self::BAR_SUPPORT === $bar) {
                return self::slimHtml((string) $module->displayName, $texts, $supportUrl);
            }
            $link = $context->link;
            $installUrl = null;
            if (\Access::isGranted('ROLE_MOD_TAB_ADMINMODULESSF_CREATE', (int) $context->employee->id_profile)) {
                $url = $link->getAdminLink('AdminModulesSf', true, ['route' => 'admin_module_manage_action', 'action' => 'install', 'module_name' => self::UPDATER_MODULE]);
                $installUrl = $url . (str_contains($url, '?') ? '&' : '?') . 'source=' . rawurlencode(self::DOWNLOAD_URL);
            }

            return self::html((string) $module->displayName, $texts, $installUrl, $link->getAdminLink(self::UPDATER_PAGE), $supportUrl);
        } catch (\Throwable) {
            return '';
        }
    }
}
