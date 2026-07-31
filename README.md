# wp-mobile-de-sync

[![Release](https://img.shields.io/github/v/release/dmnktoe/wp-mobile-de-sync?label=release&color=21759b)](https://github.com/dmnktoe/wp-mobile-de-sync/releases/latest)
[![Downloads](https://img.shields.io/github/downloads/dmnktoe/wp-mobile-de-sync/total?label=downloads&color=21759b)](https://github.com/dmnktoe/wp-mobile-de-sync/releases)
[![CI](https://img.shields.io/github/actions/workflow/status/dmnktoe/wp-mobile-de-sync/ci.yml?branch=main&label=CI)](https://github.com/dmnktoe/wp-mobile-de-sync/actions/workflows/ci.yml)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.0%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/github/license/dmnktoe/wp-mobile-de-sync?color=blue)](https://www.gnu.org/licenses/gpl-2.0.html)

Synchronises a dealer's vehicle inventory from the mobile.de **Search API**
into the `fahrzeuge` custom post type — with bundled templates that any theme
can override, and ready for FacetWP.

## Data contract

Vehicle data lands in post meta under stable field names. That is the
interface for templates, facets and custom queries, and it does not change
within a major version:

- CPT `fahrzeuge`, shortcode `[fahrzeuge-anzeigen]`
- one meta key per vehicle property, plus the 28 equipment features
- FacetWP sources usable as-is (`cf/make`, `cf/price_raw`, `cf/mileage_raw`, …)

## Requirements

| | |
|---|---|
| WordPress | 5.8 or newer |
| PHP | 7.0 or newer (CI checks 7.0, 7.4 and 8.3) |
| PHP extensions | `json`, `curl` or `allow_url_fopen`, `gd` or `imagick` |
| Outbound access to | `services.mobile.de` |
| Optional | FacetWP for filtering, WP-CLI for the initial import |

## Installation

1. Copy the folder into `wp-content/plugins/` and activate it.
2. Deactivate other mobile.de plugins — two importers on the same post type
   overwrite each other.
3. **Vehicles → Sync Settings**: username, password, seller ID.
4. **Test connection** — expect “Connection OK – N vehicles in the inventory”.
5. Run the initial import from the command line: `wp wmds sync --full --all`
6. Re-index FacetWP.

The settings screen carries a checklist through those steps and ticks each one
off as it completes, so the state of a half-finished setup is visible rather
than guessed at.

The seller ID is the numeric dealer identifier and the only route mobile.de
documents for addressing an inventory. If you don't have it, the vanity name
from `home.mobile.de/NAME` works instead — but that parameter is undocumented
and may disappear at any time.

## Language

Source strings are English. The reference-data labels (transmission, fuel,
colours …) come from mobile.de in whatever language you configure, defaulting
to the site language.

Plugin strings are translatable through the `wp-mobile-de-sync` text domain.
**A German translation ships with the plugin** under
`languages/wp-mobile-de-sync-de_DE.po`. For another language, drop a further
`.mo` file into the same directory.

The consumption disclaimer on the detail page is prescribed verbatim by the
Pkw-EnVKV, so the German catalogue carries the statutory wording rather than a
translation of the English source. Keep that in mind when editing it.

After changing a `.po`, rebuild the binary catalogue WordPress actually reads:

```
php bin/po2mo.php languages/wp-mobile-de-sync-de_DE.po
```

That tool exists because the project has no build step and `msgfmt` is not
installed everywhere.

Note that labels are resolved **at import time** and stored in post meta.
Changing the site language therefore takes effect after the next full sync,
not immediately.

## In the admin

The sync has one health verdict — not configured, running, waiting for the
first run, overdue, last run had failures, up to date — and every surface reads
it from the same place, so they cannot contradict each other:

- an **admin bar item** with the inventory size and a coloured state, in the
  back end and on the front end. Its menu carries the last result, the time
  until the next run, “Sync now”, and on a vehicle page a link to the source ad
  plus a reload for that one vehicle
- a **dashboard widget** with the newest vehicles — photo, price, mileage,
  first registration — configurable in count and sort order
- an **entry in “At a Glance”** with the number of published vehicles
- a **notice on every admin screen** when the sync needs attention,
  dismissible, and re-armed when the state changes to something else

The **vehicle list** shows photo, make and model, price, mileage, first
registration and the mobile.de ad ID. Price, mileage and first registration are
sortable; vehicles missing the sorted-on value stay in the list rather than
falling out of it. A filter by make sits next to the date filter, and each row
offers “Reload from mobile.de” and “View on mobile.de”.

The **edit form** says outright that the importer owns the record, and a meta
box shows the ad ID, the modification date, the number of imported photos and a
button that re-reads this one vehicle.

The **settings screen** is split into Connection, Schedule, Status & log and
Tools, with the status card above the tabs. Every action goes through
`admin-post.php` and leaves through a redirect, so a reload never repeats it.
“Test connection” and “Sync now” run over AJAX; the sync drives one batch per
request and shows real progress instead of a spinner over a request that may
time out. The log can be filtered down to problems, downloaded and cleared, and
a full sync can be forced without waiting for the daily reconciliation.

## Scheduling

WP-Cron is the interface here, not the timer: it is triggered by page views.
On a low-traffic site “every 15 minutes” effectively means “eventually”. A
real cron job is recommended:

```php
// wp-config.php
define( 'DISABLE_WP_CRON', true );
```

```cron
*/15 * * * * cd /path/to/site && wp cron event run --due-now
```

The settings screen warns when the last run is more than six hours old.

**A regular run fetches only what changed.** Once a day a full reconciliation
runs in addition — and only after one of those are sold vehicles removed.

## Error handling

Every API response is validated before it is used. An outage, a rate limit or
an error message leads to a clean abort with a log entry, never to a fatal
error. In particular an error response is never parsed as if it were a result
list — that case is covered by a test.

Three independent guards apply to removal:

1. Nothing is ever removed after a partial pass.
2. A run that saw no vehicle at all removes nothing — this happens with a
   wrong seller ID, where the API answers with an empty result rather than an
   error.
3. If more than 30 percent of the inventory would disappear, the run aborts
   with a reason. Below that sits an absolute floor of three vehicles, so
   small inventories are not permanently blocked.

Removed vehicles go to the **trash**, not into the void.

## WP-CLI

```
wp wmds sync --full --all      full sync until nothing is pending
wp wmds sync                   one pass, changes only
wp wmds status                 schedule, last run, log
wp wmds test                   check the connection without writing
wp wmds flush-cache            re-fetch reference data
```

## Logos

30 manufacturer logos ship with the plugin, output via `[fahrzeug-logo]`. For a
missing make, drop a file into `wp-content/uploads/wmds-logos/` named after the
make (`Tesla.png`). Case, spaces and special characters do not matter — the
lookup normalises both sides.

## List-view icons

`mileage.svg`, `fuel.svg`, `gearbox.svg` and `emission.svg` are expected under
`wp-content/icons/`. For a different path:

```php
define( 'WMDS_ICONS', 'https://example.com/path/to/icons/' );
```

## Development

```
sh tests/run.sh          all tests, no PHPUnit and no Composer
composer install         tooling for the coding standards
composer lint            phpcs (WordPress standards + PHP compatibility)
php bin/po2mo.php ...    rebuild a translation catalogue after editing a .po
```

The tests run without WordPress, without PHPUnit and without Composer. The
pure decisions — interpreting a response, mapping fields, rendering a
description, building a plan, deciding which health state the admin shows —
need only direct calls. For the import run
itself `tests/wp-fake.php` provides a WordPress in process memory, so `run()`
executes end to end: initial import, follow-up run, a change, a swapped photo,
batching across several passes, an API outage and all three removal guards.
What that does not cover is documented at the top of the file.

The fixtures are anonymised but structurally real API responses.

**Credentials and customer data do not belong in this repository.** A CI job
checks that on every push.

### Integration test

Stubs prove the logic, not that the plugin works inside WordPress.
`tests/integration/run.sh` closes that gap: it installs a real WordPress on a
real database, activates the plugin and drives the sync through WP-CLI, while
a local mock server answers as mobile.de from the same fixtures.

```
sh tests/integration/run.sh
```

It needs PHP with `mysqli` and `gd`, WP-CLI on the `PATH` and a MySQL to talk
to. Database, port, workspace and WordPress version are environment variables,
documented at the top of the script. CI runs it on every push against MySQL 8
and the current WordPress.

The plugin itself knows nothing about the test.
`tests/integration/mu-plugin.php` hooks `pre_http_request`, points the
mobile.de and image URLs at the mock server and blocks every other host — so
the plugin's own `wp_remote_get()` and `download_url()` calls are made, and
answered, over real HTTP.

The scenarios run in sequence: the health state of a fresh install, a
paginated full sync, an incremental pass with nothing to do, one changed
vehicle, a single vehicle reloaded through `WMDS_Importer::refresh()`, a
vehicle withdrawn from the feed, wrong credentials, an API outage, the German
catalogue, and the uninstall. What is asserted afterwards is the state
WordPress ended up in — posts, post meta, image files on disk, the featured
image, reference-data transients, the watermark, the log, the lock and what
`WMDS_Status::get()` makes of all of it — read back out of the database rather
than out of a return value.

The plugin is copied into the installation rather than symlinked, so the
uninstall scenario cannot reach the working tree. It runs with
`--skip-delete`, and afterwards the vehicles are still there while the
plugin's own options, transients, user meta and cron events are gone.

## Customising

Every template is resolved through the same hierarchy, first match wins:

1. `wp-mobile-de-sync/<file>` in the child theme, then in the parent theme
2. `<file>` in the child theme, then in the parent theme — where an earlier
   solution expected `mob_vehicle-list.php`
3. the bundled file under `templates/`

| File | Rendered for |
|---|---|
| `single-fahrzeuge.php` | the detail page |
| `archive-fahrzeuge.php` | the vehicle archive |
| `mob_vehicle-list.php` | `[fahrzeuge-anzeigen]` |
| `parts/vehicle-card.php` | one card, used by the last two |

The card is a part of its own, so a theme can restyle the grid item without
copying the loop around it. It reads `$args['heading']` (`h2` to `h4`) and
`$args['warnings']`. The bundled templates require no CSS framework and work
with the active theme's `get_header()` / `get_footer()`.

The bundled stylesheet loads on the vehicle archive, on a detail page and on
any post whose content renders the shortcode; the shortcode enqueues it as
well when it runs, which covers widgets and page builders.

| Filter | Purpose |
|---|---|
| `wmds_logo_url` | override the logo URL |
| `wmds_enqueue_styles` | switch off the bundled stylesheet |
| `wmds_template` | point a template at a different file |

If a site brings its own templates that call helper functions from an earlier
solution, `includes/class-wmds-compat.php` provides equivalents — but only
when they are not already defined.

## Known limits

- Across paginated pages the API hands out at most 2000 vehicles. Larger
  inventories need the fetch split up; the run logs when the ceiling is hit.
- `generalInspection` is optional, and dealers who sell with a fresh
  inspection do not maintain it — they set `newHuAu` instead. Both are mapped,
  with `newHuAu` as the fallback.
- The bundled templates are deliberately plain. They are a usable starting
  point, not a finished design — for a customer site, copy them into the theme
  and adapt them there.
