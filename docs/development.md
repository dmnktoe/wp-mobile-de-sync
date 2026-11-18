# Development

[← README](../README.md)

- [Commands](#commands)
- [How the code is put together](#how-the-code-is-put-together)
- [Tests](#tests)
- [Translations](#translations)
- [Releasing](#releasing)

## Commands

```sh
sh tests/run.sh                 # all tests, no PHPUnit and no Composer
sh tests/integration/run.sh     # end to end: real WordPress, real database, mock API
composer install                # tooling for the coding standards
composer lint                   # phpcs (WordPress standards + PHP compatibility)
sh bin/bump.sh 2.5.1            # set the version everywhere it is stated
php bin/po2mo.php languages/wp-mobile-de-sync-de_DE.po   # rebuild a catalogue
```

## How the code is put together

The plugin file defines the constants and calls `WMDS_Plugin::boot()`.
Everything else is a class in `includes/`, loaded on first use.

| Class | Does |
|---|---|
| `WMDS_Plugin` | wires the plugin into WordPress: post type, cron events, activation |
| `WMDS_Settings` | every setting, its type and default, and reading a submitted form |
| `WMDS_Client` | the mobile.de Search API |
| `WMDS_Json_Mapper`, `WMDS_Refdata` | an ad into post meta, with labels in the configured language |
| `WMDS_Sync_Plan` | what a listing means: create, update, skip, remove — pure decisions |
| `WMDS_Importer` | a sync pass: listing, plan, vehicles, removal |
| `WMDS_Images` | the pictures of a vehicle, and deleting them once it is gone |
| `WMDS_Log` | the sync log, with a level per entry |
| `WMDS_Status`, `WMDS_Alerts` | the health verdict, and the mails about it |
| `WMDS_Vehicle` | a vehicle as templates read it |
| `WMDS_Templates`, `WMDS_Blocks` | template lookup, shortcodes and blocks |
| `WMDS_Facets` and `WMDS_Facet_*` | the filters: definitions, request, query, counts |
| `WMDS_Similar`, `WMDS_Removed` | similar vehicles; the address of a removed vehicle |
| `WMDS_Seo` | structured data and social previews |
| `WMDS_Leads`, `WMDS_Lead_Form`, `WMDS_Lead_Mail` | stored enquiries; the form; the mails it sends |
| `WMDS_Admin*`, `WMDS_Tab_*` | the settings screen, one class per tab |
| `WMDS_Updater` | updates from GitHub releases |

### How a class gets loaded

`WMDS_Autoloader` resolves `WMDS_Facet_Store` to
`includes/class-wmds-facet-store.php`, `WMDS_Tab_Tools` to
`includes/tabs/class-wmds-tab-tools.php` and `WMDS_Cf7` to
`includes/integrations/class-wmds-cf7.php`. Adding a class means adding a
file; there is no map.

`tabs/` holds one admin screen each. `integrations/` is the code that talks to
another plugin: it is loaded on `plugins_loaded`, checks that the plugin is
there before it hooks anything, and leaves the site working when it is not.

The logo and sticker files are required outright, and the WP-CLI file is
required when WP-CLI runs: **a file that does something when it loads cannot be
loaded lazily.** Each registers a shortcode or command at file level, and
nothing on a page that only writes `[vehicle-logo]` would ever mention
`WMDS_Logos`. `tests/test-boot.php` and `tests/test-autoloader.php` fail if
one of those requires disappears.

### Settings and tabs

Every setting is described once, in `WMDS_Settings::fields()`: its type, its
default and therefore how a posted value is cleaned. A tab with settings is
one form and names the settings it holds in a `fields()` method;
`WMDS_Admin::forms()` maps tabs to those lists and the save handler reads
exactly them, so saving one tab never touches another's settings. A new
setting is a line in `fields()` and a field in a tab.

Every action goes through `admin-post.php` and leaves through a redirect, so a
reload never repeats it.

### Reading numbers

`WMDS_Num` deliberately offers no general "parse a number". A person typing
`12.500` into a price filter means twelve and a half thousand; the API sending
`12.500` means twelve and a half. Reading either with the other's rule is wrong
by a factor of a thousand, so the two are named for what they read —
`from_input()` and `from_feed()`.

## Tests

The tests run without WordPress, without PHPUnit and without Composer. The
pure decisions — interpreting a response, mapping fields, building a plan,
deciding the health state — need only direct calls. For the import run itself
`tests/wp-fake.php` provides a WordPress in process memory, so `run()` executes
end to end: initial import, follow-up run, a change, a swapped photo, batching
across passes, an API outage, the inventory count check and all three removal
guards. `tests/wp-admin-fake.php` does the same for the admin screens, and
`tests/test-admin.php` drives every tab and action through the real entry
points.

The fixtures are anonymised but structurally real API responses.
**Credentials and customer data do not belong in this repository**; a CI job
checks that on every push. English is the source language, and
`tests/no-german.sh` flags German prose outside `languages/`.

### Integration test

`tests/integration/run.sh` installs a real WordPress on a real database,
activates the plugin and drives the sync through WP-CLI, while a local mock
server answers as mobile.de from the same fixtures. It then serves the site and
fetches the archive, a filtered archive, a vehicle and a page of shortcodes and
blocks — under a block theme and a classic one — and fails on any PHP notice.

It needs PHP with `mysqli` and `gd`, WP-CLI on the `PATH` and a MySQL to talk
to; the variables are documented at the top of the script. CI runs it on every
push. `"generate": 40` in the mock's state file adds forty vehicles derived
from the fixture, enough to see pagination, facet counts and sliders work.

## Translations

Source strings are English and translatable through the `wp-mobile-de-sync`
text domain; a German catalogue ships in `languages/`. After editing the
`.po`, rebuild the binary catalogue WordPress reads:

```sh
php bin/po2mo.php languages/wp-mobile-de-sync-de_DE.po
```

That tool exists because the project has no build step and `msgfmt` is not
installed everywhere. The consumption disclaimer is prescribed verbatim by the
Pkw-EnVKV, so the German catalogue carries the statutory wording rather than a
translation of the English source.

## Releasing

`sh bin/bump.sh <version>`, write the entry in `CHANGELOG.md` and
`readme.txt`, merge to `main`. That is the whole release: the workflow reads
the version out of the plugin header, stops if that tag already exists, runs
the tests, builds the ZIP and publishes the tag and the release. Sites pick it
up through the built-in updater.

`tests/test-version.php` holds the three places that state the version to the
same answer and insists on a changelog entry for it, so a half-finished bump
fails before it reaches `main`.
