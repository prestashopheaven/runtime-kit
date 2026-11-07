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
 * Turns the tabs of a module definition into the format of Module::$tabs, with the name translated into every shop
 * language (was a copy in each module: src/Install/TabBuilder.php).
 */
final class TabBuilder
{
    /**
     * Returns the tabs with a translated 'name' per locale and their 'wording_domain', so PrestaShop can translate the
     * name again when a language is added later. Built on demand (install, module list), not in the constructor.
     *
     * @param array<int|string, array<string, mixed>> $tabs the tabs of the definition, each with class_name and wording
     * @param string $domain translation domain of the wordings, e.g. 'Modules.Heavenfoo.Admin'
     * @param array<int, array{locale: string}>|null $languages the shop languages; default all of them (Language::getLanguages)
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \InvalidArgumentException when a tab has no class_name or the domain is empty
     */
    public static function build(\Module $module, array $tabs, string $domain, ?array $languages = null): array
    {
        if ($domain === '') {
            throw new \InvalidArgumentException('The translation domain of the tab names is empty (ModuleDefinition::TABS_WORDING_DOMAIN).');
        }
        $translator = $module->getTranslator();
        $languages ??= \Language::getLanguages(false);
        $result = [];
        foreach ($tabs as $index => $tab) {
            if (!is_string($tab['class_name'] ?? null) || $tab['class_name'] === '') {
                throw new \InvalidArgumentException(sprintf('Tab %s of ModuleDefinition::TABS has no class_name.', $index));
            }
            $wording = is_string($tab['wording'] ?? null) && $tab['wording'] !== '' ? $tab['wording'] : $tab['class_name'];
            $names = [];
            foreach ($languages as $language) {
                // translations-ignore: wordings are extracted from ModuleDefinition::TABS
                $names[$language['locale']] = $translator->trans($wording, [], $domain, $language['locale']);
            }
            $result[] = $tab + ['name' => $names, 'wording_domain' => $domain];
        }

        return $result;
    }
}
