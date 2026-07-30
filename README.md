# wp-mobile-de-sync

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

The seller ID is the numeric dealer identifier and the only route mobile.de
documents for addressing an inventory. If you don't have it, the vanity name
from `home.mobile.de/NAME` works instead — but that parameter is undocumented
and may disappear at any time.

## Language

Source strings are English. The reference-data labels (transmission, fuel,
colours …) come from mobile.de in whatever language you configure, defaulting
to the site language.

Plugin strings are translatable through the `wp-mobile-de-sync` text domain;
drop a `.mo` file into `languages/`. One caveat for German sites: the
consumption disclaimer on the detail page is prescribed verbatim by the
Pkw-EnVKV, so a German translation must carry the statutory wording rather
than a translation of the English source.

Note that labels are resolved **at import time** and stored in post meta.
Changing the site language therefore takes effect after the next full sync,
not immediately.

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
```

The tests run without WordPress, without PHPUnit and without Composer. The
pure decisions — interpreting a response, mapping fields, rendering a
description, building a plan — need only direct calls. For the import run
itself `tests/wp-fake.php` provides a WordPress in process memory, so `run()`
executes end to end: initial import, follow-up run, a change, a swapped photo,
batching across several passes, an API outage and all three removal guards.
What that does not cover is documented at the top of the file.

The fixtures are anonymised but structurally real API responses.

**Credentials and customer data do not belong in this repository.** A CI job
checks that on every push.

## Multiple sites

Nothing is tied to one installation — credentials, seller ID, interval,
language and logos are configurable per site. Extension points:

| Filter | Purpose |
|---|---|
| `wmds_logo_url` | override the logo URL |
| `wmds_enqueue_styles` | switch off the bundled stylesheet |

Templates are taken from the theme as soon as `single-fahrzeuge.php`,
`archive-fahrzeuge.php` or `mob_vehicle-list.php` exist in the stylesheet
directory. The bundled templates require no CSS framework and work with the
active theme's `get_header()` / `get_footer()`.

If an existing site brings its own templates that call helper functions from
an earlier solution, `includes/class-wmds-compat.php` provides equivalents —
but only when they are not already defined.

## Known limits

- Across paginated pages the API hands out at most 2000 vehicles. Larger
  inventories need the fetch split up; the run logs when the ceiling is hit.
- `generalInspection` is optional, and dealers who sell with a fresh
  inspection do not maintain it — they set `newHuAu` instead. Both are mapped,
  with `newHuAu` as the fallback.
- The bundled templates are deliberately plain. They are a usable starting
  point, not a finished design — for a customer site, copy them into the theme
  and adapt them there.
