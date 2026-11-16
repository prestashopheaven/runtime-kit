# Changelog

## [1.1.0] - 2026-11-16

### Toegevoegd
- `Install\TabSynchronizer`: maakt de admin-tabs van een geïnstalleerde module gelijk aan `ModuleDefinition::TABS`:
  ontbrekende tabs aanmaken (ouders van de definitie vóór hun kinderen), bestaande tabs verplaatsen en bijwerken
  (ouder, route, zichtbaar, icoon, vertaalde namen), tabs van de module die niet meer in de definitie staan verwijderen
  (behalve de `_legacy_controller` van een route of een controller in `controllers/admin`), en de kopie
  `<ouder>_MTR` die PrestaShop van een eigen oudertab maakt verwijderen. Tabs van PrestaShop of andere modules blijven
  altijd staan.
- `Installer::syncTabs()`; `install()` en `syncDefinition()` (dus `Upgrade::apply()`) roepen hem aan: een upgrade
  geeft nu hetzelfde menu als een verse installatie (INS-07 automatisch), ook een eigen menu onder SELL.
- `ModuleBase::getDefinedTabs()`: alle tabs van de definitie met vertaalde namen.

### Gewijzigd
- `ModuleBase::getTabs()` geeft PrestaShop direct na `install()` alleen de tabs die de kit niet kon maken (normaal
  geen): de tab-register van PrestaShop zou anders een eigen oudertab dubbel zetten (`_MTR`) en "already exists" loggen.
  Elke andere aanroep geeft alle tabs.

## [1.5.0] - 2026-10-28

### Toegevoegd
- `AbstractModuleDefinition::DATA_DIRECTORIES` en `dataDirectories()` (interface `DataDirectoriesInterface`): mappen
  die een module buiten zijn eigen map schrijft (bijv. uploads in `img/modules/<module>`), relatief aan de
  PrestaShop-root. De `Uninstaller` verwijdert ze als laatste stap (INS-04, SEC-07).
- `Install\DataDirectoryRemover`: controleert het pad (relatief, geen `.`/`..`, de modulenaam als segment) en
  verwijdert de map recursief zonder symbolische links te volgen.

## [1.4.0] - 2026-10-25

### Toegevoegd
- Knop "Help & support" in de PrestaHaven-balk: opent het ticketformulier van de update-server
  (`UpdaterNotice::SUPPORT_URL`) in een nieuw tabblad, vooraf ingevuld met de technische naam en versie
  van de module, de PrestaShop-versie en PHP major.minor (`UpdaterNotice::supportUrl()`). Verder wordt er niets
  meegestuurd. Teksten in alle 33 talen.
- Staat de Updater er al, is de updater-melding 30 dagen verborgen, of gaat het om `heavenupdater` zelf, dan toont de
  module een smalle balk van één regel ("Vragen over <module>? …") met alleen de helpknop, zonder verberglink.
- `UpdaterNotice::helpLink($module)`: de compacte helpknop voor een eigen template van de module (bijv. een paneel in
  het productformulier, voor een module zonder eigen pagina's); leeg voor Addons-kopieën. Altijd aanroepen met
  `class_exists(UpdaterNotice::class)`, want de Addons-build laat `src/Updater` weg.
- `UpdaterNotice::bar()` (welke balk: updater, smal of geen), `isDirectCopy()`, `helpButton()`, `slimHtml()`.

### Gewijzigd
- `UpdaterNotice::html()` heeft een optionele parameter `$supportUrl` voor de helpknop naast "Meer informatie".

## [1.3.0] - 2026-10-25

### Toegevoegd
- Updater-melding (`Updater\UpdaterNotice`, `Updater\UpdaterNoticeTexts`): een balk op de eigen backoffice-pagina's
  van de module wanneer de gratis PrestaHaven Updater niet geïnstalleerd is, alleen voor directe kopieën (lege
  `module_key`). "Nu installeren" gebruikt de Module Manager-route van PrestaShop (rechten en demomodus van PrestaShop
  zelf), plus "Meer informatie" en "30 dagen verbergen". Teksten in de 33 talen van de modules; geen netwerkverkeer.
- `ModuleBase::hookDisplayAdminAfterHeader()` toont de melding; de Installer registreert de hook alleen als de melding
  in de build zit (de Addons-build van `ps-module build` laat `src/Updater` weg). Een module met een eigen
  `hookDisplayAdminAfterHeader` lost de botsing op met `insteadof ModuleBase` (UPD-03).

## [1.2.1] - 2026-10-23

### Gewijzigd
- Onderhoud: bijgewerkte package-metadata en bestandsheaders.

## [1.2.0] - 2026-10-17

### Toegevoegd
- `Shop\ShopScope`: `runInAllShops()`, `isModuleActiveInShop()`, `shopIds()` (multistore, MS-02/09/11).

### Gewijzigd
- Install, uninstall en `Upgrade::apply()` draaien in de context "alle winkels": de module staat aan in
  elke winkel, ook bij installeren vanuit één winkel of `bin/console` (MS-02).
- Standaardwaarden en gegenereerde tokens zijn globaal (`updateGlobalValue`); een upgrade overschrijft
  nooit winkel- of groepswaarden; een verse install ruimt oude winkelwaarden op (MS-03).

## [1.1.0] - 2026-10-17

### Toegevoegd
- `Installer::runAllMigrations()`; `runMigrations([])` doet niets meer (geen per ongeluk opnieuw
  draaien van alle migraties in een upgrade).
- CI- en release-workflows: checks (PHPStan tegen PS 8.1.0 en 9.2.0), tag, GitHub-release, Packagist.

### Gewijzigd
- Publiek op github.com/prestashopheaven/module-kit en Packagist; dev-tools via Packagist.

## [1.0.0] - 2026-10-17

### Toegevoegd
- Eerste versie.
