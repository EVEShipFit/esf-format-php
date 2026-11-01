# esf-format

[![Packagist](https://img.shields.io/packagist/v/eveshipfit/esf-format.svg)](https://packagist.org/packages/eveshipfit/esf-format)
[![PHP](https://img.shields.io/packagist/dependency-v/eveshipfit/esf-format/php.svg)](https://packagist.org/packages/eveshipfit/esf-format)
[![CI](https://github.com/EVEShipFit/esf-format-php/actions/workflows/test.yml/badge.svg)](https://github.com/EVEShipFit/esf-format-php/actions/workflows/test.yml)

[![Discord](https://img.shields.io/badge/Discord-Join-5865F2?logo=discord&logoColor=white)](https://discord.gg/S5V5BkvNf7)

Read and write [esf/1](https://github.com/EVEShipFit/esf-format), the
plain-text format for EVE Online ship fits. Text, canonical and binary forms.
No dependencies beyond `ext-mbstring`.

```
composer require eveshipfit/esf-format
```

## Usage

```php
use EveShipFit\EsfFormat\Entry;
use EveShipFit\EsfFormat\Esf;
use EveShipFit\EsfFormat\EsfFit;
use EveShipFit\EsfFormat\Location;
use EveShipFit\EsfFormat\State;

$fit = new EsfFit(
    hull: 587, // Rifter
    name: 'Shield Buffer',
    entries: [
        new Entry(2889, count: 3, charge: 21898), // 3 guns, a full load of ammo each
        new Entry(null, location: Location::High), // an empty high slot
        new Entry(448, state: State::Off), // an offline module
        new Entry(2048), // goes in its own rack
        new Entry(2456, count: 5), // 5 drones
        new Entry(21898, count: 1000, location: Location::Cargo),
    ],
);

$text = Esf::save([$fit], $catalog); // canonical text
$data = Esf::saveBinary([$fit], $catalog); // binary
$fits = Esf::load($data, $catalog); // text or binary

$url = Esf::toBase64Url($data); // the binary form in a URL
$data = Esf::fromBase64Url($url);
```

`save()` and `saveBinary()` take entries in any order, and check every rule
of the spec. `load()` returns fits in canonical form. All three throw
`EsfError` when a rule is broken. It says where: `lineNumber` for text, or
`fitNumber` and `entryNumber` (counting from 1) for fits.

## Fits

An `EsfFit` is one fit: `hull`, `name`, tactical `mode` and its `entries`.
`hull` is `null` for a fit without a ship. A document can hold more than one
fit.

Each `Entry` is one line of the fit. `null` means the default:

| field | what it is | `null` means |
| --- | --- | --- |
| `type` | The type ID. | An empty slot. |
| `count` | How many. | One; for fighters, a full squadron. |
| `fitName` | The name of another fit in the list, which this ship or container carries. | Carries no fit. |
| `charge` | The charge loaded. | |
| `chargeCount` | How many charges. | A full load. |
| `mutaplasmid` | The mutaplasmid applied. | |
| `overrides` | Attribute values by attribute ID. | |
| `state` | `State::Off`, `On` or `Heat`. | Active if the type can be, else on. |
| `location` | `Cargo`, `Bay` or another hold; a rack only for an empty slot. | Where the type goes by default. |

## The Catalog

The library holds no game data. You give it a `Catalog`: an object that looks
up types and attributes in your copy of the SDE.

```php
use EveShipFit\EsfFormat\Catalog;

final class MyCatalog implements Catalog
{
    public function type(string $folded): ?TypeRecord { ... }
    public function typeById(int $typeId): ?TypeRecord { ... }
    public function attribute(string $folded): ?AttributeRecord { ... }
    public function attributeById(int $attributeId): ?AttributeRecord { ... }
}
```

Implement `ModeCatalog` (`modes()`) and `MutaplasmidCatalog`
(`mutaplasmids()`) too, or no hull has tactical modes and no type has
mutaplasmids.

Names are looked up folded: index them with `Esf::fold($name)`. Where
names are shared, published wins, then the lowest ID (§4.2).
[tests/JsonCatalog.php](https://github.com/EVEShipFit/esf-format-php/blob/main/tests/JsonCatalog.php) is a complete example.

## Tests

The test cases live in [esf-format](https://github.com/EVEShipFit/esf-format):

```
git clone --branch v1.0 https://github.com/EVEShipFit/esf-format
composer install
uv run --project esf-format/tools esf-test -- php tests/program.php
composer lint
composer test
```
