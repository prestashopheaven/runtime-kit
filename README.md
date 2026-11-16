# prestashopheaven/module-kit

Gedeelde basis voor alle PrestaHaven-modules (PrestaShop 8.0 – 9.x, PHP 8.1+).

| Onderdeel | Wat |
|---|---|
| `ModuleBase` (trait) | `install()`, `uninstall()`, `getTabs()`, `getDefinedTabs()`, `isUsingNewTranslationSystem()` voor de hoofdklasse |
| `Definition\AbstractModuleDefinition` | Basis voor de `ModuleDefinition` van een module: alleen constanten overschrijven |
| `Install\Installer` / `Uninstaller` | Migraties, hooks, configuratie, gegenereerde secrets, tabs |
| `Install\TabSynchronizer` | Tabs gelijk aan `TABS` bij installatie en upgrade (ook een eigen menu, vanaf 1.1.0) |
| `Install\Migrator` | SQL-migraties (`migrations/up` + `down`), engine: vanengers/prestashop-library-product-admin-helper |
| `Upgrade\Upgrade` | Helper voor `upgrade/upgrade-X.Y.Z.php` |
| `Shop\ShopScope` | Multistore: `runInAllShops()`, `isModuleActiveInShop()`, `shopIds()` |

## Gebruik in een module

```php
// heavenfoo.php: alleen de constructor, de rest via traits
class Heavenfoo extends Module
{
    use \PrestaShopHeaven\Runtime\Kit\ModuleBase;
    use \PrestaShopHeaven\Foo\Module\Helper\Definition; // getModuleDefinition()

    public function __construct() { /* identiteit */ }
}
```

```php
// src/Install/ModuleDefinition.php
final class ModuleDefinition extends AbstractModuleDefinition
{
    public const HOOKS = ['displayHome'];
    public const CONFIGURATION = ['HEAVENFOO_LIMIT' => 50];
    public const GENERATED_CONFIGURATION = ['HEAVENFOO_CRON_TOKEN'];
    public const DATA_DIRECTORIES = ['img/modules/heavenfoo']; // weg bij uninstall
}
```

`DATA_DIRECTORIES` (vanaf 1.5.0): mappen die de module buiten zijn eigen map schrijft (uploads in `img/`,
bestanden in `var/modules/`), relatief aan de PrestaShop-root. De Uninstaller verwijdert ze met alles erin
(`Install\DataDirectoryRemover`). Een pad moet relatief zijn, zonder `.`/`..`, en de technische naam van de module
als segment hebben; anders mislukt de uninstall met een melding. Symbolische links worden verwijderd, niet gevolgd.

```php
// upgrade/upgrade-1.1.0.php
function upgrade_module_1_1_0($module): bool
{
    return \PrestaShopHeaven\Runtime\Kit\Upgrade\Upgrade::apply($module, ['202611011200']);
}
```

`Upgrade::apply()` draait de nieuwe migraties en registreert daarna alle hooks en ontbrekende
config-sleutels uit de definitie, en zet de tabs gelijk aan `TABS` (vanaf 1.1.0, `Install\TabSynchronizer`). Een
upgrade komt zo op hetzelfde uit als een verse installatie.

### Eigen menu (vanaf 1.1.0)

Een module kan een eigen menu-item met submenu hebben: een oudertab onder `SELL` (of `IMPROVE`, `CONFIGURE`) met een
route (de pagina waar het item heen gaat) en de pagina's als kinderen. De kit maakt de tabs zelf aan bij installatie en
bij elke upgrade, dus ook het verplaatsen van bestaande pagina's naar het nieuwe menu gaat vanzelf. De kopie
`<ouder>_MTR` die PrestaShop anders van een eigen oudertab maakt (een dubbel menu-item), komt er niet.

```php
public const TABS = [
    ['class_name' => 'AdminParentHeavenfoo', 'route_name' => 'heavenfoo_items', 'parent_class_name' => 'SELL',
        'visible' => true, 'icon' => 'timeline', 'wording' => 'Foo'],
    ['class_name' => 'AdminHeavenfooItems', 'route_name' => 'heavenfoo_items', 'parent_class_name' => 'AdminParentHeavenfoo',
        'visible' => true, 'wording' => 'Items'],
];
```

Tabs van de module die niet meer in `TABS` staan, worden bij een upgrade verwijderd (behalve de `_legacy_controller`
van een route of een controller in `controllers/admin`, die PrestaShop zelf registreert).

## Multistore

Install, uninstall en upgrade draaien in de context "alle winkels" (`ShopScope::runInAllShops()`):
de module staat in elke winkel aan en standaardwaarden en tokens zijn globaal, ook als de module
vanuit één winkel of via `bin/console` wordt geïnstalleerd. Een upgrade overschrijft nooit waarden die
de winkelier per winkel of groep heeft gezet.

## PrestaHaven-balk en helpknop (alleen directe build)

`ModuleBase::hookDisplayAdminAfterHeader()` toont op de eigen backoffice-pagina's van de module
(configuratiepagina en eigen admin-tabs) een balk, alleen voor directe kopieën (lege `module_key`):

- PrestaHaven Updater niet geïnstalleerd: de updater-melding met "Nu installeren", "Meer informatie",
  "Help & support" en "30 dagen verbergen";
- anders (Updater geïnstalleerd, melding verborgen, of `heavenupdater` zelf): een smalle balk van één
  regel, "Vragen over <module>? …", met alleen "Help & support".

"Help & support" opent het ticketformulier van de update-server (`UpdaterNotice::SUPPORT_URL`) in een nieuw tabblad, vooraf ingevuld
met `module`, `version`, `ps` en `php` (major.minor); verder niets. Geen netwerkverkeer vanuit de winkel.

Heeft een module geen eigen pagina (bijv. alleen een paneel in het productformulier), zet de helpknop
dan zelf in het template. De Addons-build laat `src/Updater` weg, dus altijd met deze guard (met
`::class`, zodat het scopen de naam meeneemt):

```php
use PrestaShopHeaven\Runtime\Kit\Updater\UpdaterNotice;

$help = class_exists(UpdaterNotice::class) ? UpdaterNotice::helpLink($this) : '';
// Twig: {{ help|raw }}    Smarty: {$help nofilter}   (de knop is al ge-escapete HTML)
```

`helpLink()` geeft `''` voor een Addons-kopie, zonder ingelogde medewerker of bij een fout.

## Waarom een trait en geen basisklasse

De Addons-validator eist dat de hoofdklasse direct `Module`, `PaymentModule` of
`CarrierModule` uitbreidt. Een eigen basisklasse ertussen wordt afgekeurd.

## Meerdere modules in één winkel

Elke module levert zijn eigen kopie van dit package mee in `vendor/`. Draaien twee modules met
verschillende versies, dan zou PHP maar één versie laden. Daarom geeft `ps-module build`
(module-dev-tools) de namespaces van dit package en van de migratie-engine in de release-zip
een prefix per module (`extra.prestashopheaven.scope` in de composer.json van de module).

## Ontwikkelen

```bash
composer install
composer check     # lint, cs, PHPStan tegen PS 8 + 9, compat, conventies, tests
```

Modules halen dit package van Packagist. Wil je een wijziging meteen in een module proberen:
`vendor/bin/ps-module link` in die module (daarna `ps-module unlink`).

## Release

1. `version` in `composer.json` en `CHANGELOG.md` ophogen.
2. Commit met het bericht `Release X.Y.Z - <omschrijving>` en push naar `main`.
3. De release-workflow controleert de versie, draait alle checks (ook PHPStan tegen PS 8 en 9),
   maakt tag `vX.Y.Z` met een GitHub-release en werkt Packagist bij.
4. In de modules: `composer update prestashopheaven/module-kit`.
