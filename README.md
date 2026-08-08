# wp-mobile-de-sync

[![Release](https://img.shields.io/github/v/release/dmnktoe/wp-mobile-de-sync?label=release&color=21759b)](https://github.com/dmnktoe/wp-mobile-de-sync/releases/latest)
[![Downloads](https://img.shields.io/github/downloads/dmnktoe/wp-mobile-de-sync/total?label=downloads&color=21759b)](https://github.com/dmnktoe/wp-mobile-de-sync/releases)
[![CI](https://img.shields.io/github/actions/workflow/status/dmnktoe/wp-mobile-de-sync/ci.yml?branch=main&label=CI)](https://github.com/dmnktoe/wp-mobile-de-sync/actions/workflows/ci.yml)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.0%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/github/license/dmnktoe/wp-mobile-de-sync?color=blue)](https://www.gnu.org/licenses/gpl-2.0.html)

**[Project page and download](https://dmnktoe.github.io/wp-mobile-de-sync/)**

Synchronises a dealer's vehicle inventory from the mobile.de **Search API**
into the `fahrzeuge` custom post type — with bundled templates that any theme
can override and filtering of its own, so an inventory can be searched without
a second plugin.

## Data contract

Vehicle data lands in post meta under stable field names. That is the
interface for templates, facets and custom queries, and it does not change
within a major version:

- CPT `fahrzeuge`, shortcodes `[vehicles]`, `[vehicle-filter]`,
  `[vehicle-count]` and `[vehicle-logo]`
- one meta key per vehicle property, plus every equipment feature the feed sets
- the same keys are FacetWP sources (`cf/make`, `cf/price_raw`, …) for a site
  that already uses it

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
6. If the site uses FacetWP, re-index it. The plugin's own filters need no
   step of their own.

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

The **settings screen** is split into Connection, Schedule, Enquiries,
Integrations, Status & log, Tools, System and About, with the status card
above the tabs.
Each tab is a form of its own and saves only what it owns. Every action goes through
`admin-post.php` and leaves through a redirect, so a reload never repeats it.
“Test connection” and “Sync now” run over AJAX; the sync drives one batch per
request and shows real progress instead of a spinner over a request that may
time out. The log can be filtered down to problems, downloaded and cleared, and
a full sync can be forced without waiting for the daily reconciliation.

## Enquiries

Every vehicle page carries a form. A submitted enquiry goes out as e-mail with
the vehicle it is about — title, listing number, price and a link back — and
its `Reply-To` is the enquirer, so hitting reply answers the person rather
than the website.

It is **also filed** under **Vehicles → Enquiries**. Mail is the part of this
that fails: a wrong SMTP setting, a full mailbox, a spam filter. An enquiry
that only ever existed as an e-mail is gone when that e-mail is. One that was
written down is not.

**Vehicles → Sync Settings → Enquiries** sets who receives them (several
addresses allowed), whether the address the feed carries for that vehicle gets
a copy, whether the enquirer gets a confirmation of what they wrote, whether a
record is kept at all, and the privacy notice the visitor has to agree to —
the checkbox appears only when that notice is filled in.

Three guards keep the robots out, none of which a person notices: a honeypot
field, a form submitted faster than anyone could type it, and a limit of five
enquiries per visitor per hour. A submission that fails validation comes back
with what was typed still in the fields and the error next to the one that is
wrong.

    [vehicle-enquiry]
    [vehicle-enquiry vehicle="123" heading="Ask us about this car"]

`wmds_enquiry_recipients` has the last word on where an enquiry goes.

## Integrations

Three plugins turn up on a dealer site often enough to be worth meeting
halfway. All three are optional, none is required, and what they add sits
under **Vehicles → Sync Settings → Integrations**.

### Contact Form 7

A dealer who already has a form does not want a second one — the bundled
enquiry form can be switched off on the Enquiries tab. What that form knows
and Contact Form 7 does not is which vehicle the visitor is looking at, and
"I am interested, when can I come by" is worth nothing without it.

Put a mail tag into the **mail template** of the form. No field in the form is
needed: the value is read from the page the form was submitted from.

| Mail tag | Value |
|---|---|
| `[_wmds_vehicle_title]` | the title |
| `[_wmds_vehicle_url]` | the address of the vehicle page |
| `[_wmds_vehicle_listing]` | the listing number |
| `[_wmds_vehicle_price]` | the price, as it is printed |
| `[_wmds_vehicle_make]`, `[_wmds_vehicle_model]` | make and model |
| `[_wmds_vehicle_year]`, `[_wmds_vehicle_mileage]` | first registration, mileage |
| `[_wmds_vehicle_fuel]`, `[_wmds_vehicle_gearbox]` | fuel, transmission |
| `[_wmds_vehicle_seller]`, `[_wmds_vehicle_seller_email]`, `[_wmds_vehicle_seller_phone]` | the seller the feed names |
| `[_wmds_vehicle_id]` | the post ID |

The same values are available as a form tag where one should travel with the
submission and be visible in the admin:

    [wmds_vehicle vehicle field:price]

`field:` takes any of the names above and defaults to the title.

A submission sent **from a vehicle page** is filed under **Vehicles →
Enquiries** like any other, so a mail that is never delivered is not simply
gone. Name, address, phone number and message are read from the form under
whatever names it uses: the conventional ones first, then by substring, and an
address is recognised by being one even when the field is called something
else. Submissions from any other page are left alone. Optionally the address
the feed carries for the vehicle is added as a recipient — to the mail that
goes to you, never to the confirmation the visitor receives.

### Mail delivery

Enquiries and alerts are sent with `wp_mail()`. Without an SMTP plugin that
means PHP's `mail()`: unauthenticated, sent from whatever address the server
decides on, and accepted by the local sendmail whether or not anything is ever
delivered. An enquiry in a spam folder is a lost customer, and nothing on the
site would say so.

The Integrations tab and **System** name the mailer plugin that is installed —
WP Mail SMTP, FluentSMTP, Post SMTP, Easy WP SMTP, WP Offload SES, Mailgun,
Brevo — and, where it can be read, what it has been told to send through. A
plugin installed but left on `mail` is called out, because that is the setting
that looks solved and is not.

The last delivery failure WordPress reported is kept and shown with the time
it happened, for **every** message the site sends rather than only ours: a
contact form that cannot be delivered says just as much, and usually fails
first. It clears itself as soon as the plugin sends one successfully.

### FacetWP

The plugin filters on its own since 2.2 and needs FacetWP for nothing. A site
that was built on FacetWP before keeps working all the same, and three things
that used to be manual stop being manual:

- the meta keys appear in the facet source list under **mobile.de** with
  readable labels, instead of being typed as `cf/price_raw` from memory
- the sort orders the filter bar offers are offered to a FacetWP template too
- a vehicle is re-indexed once the import has finished writing it

The last one is a fix, not a convenience. FacetWP indexes when a post is
saved; the import saves the post first and writes the meta afterwards, so what
FacetWP indexed on its own was the state *before* the update. That is where
stale counts came from. It can be switched off where the index is maintained
elsewhere.

## Structured data and social previews

A vehicle page carries a complete `Car` node in JSON-LD: make as a `Brand`,
model, body type, colour, fuel, transmission, doors, seats, previous owners,
first registration as a date, CO₂, and engine power and displacement as
quantities with their UN/CEFACT unit codes — `KWT`, `CMQ`, `KMT` — so a number
is read as a number rather than as text. The price, the currency, the
availability and the dealer sit in an `Offer`.

The archive carries an `ItemList` of the vehicles on the page.

**What the feed does not state is left out.** No brand is invented for a
vehicle without a make, no offer is claimed for a vehicle without a price, and
an engine power of zero is not an engine specification.

New against used is decided from `condition_key`, not from the localised
label — a vehicle imported before that key existed falls back to its mileage.

Open Graph and Twitter cards go out alongside, so a link shared anywhere shows
the photo, the title and the price. They are **suppressed automatically** when
Yoast, Rank Math, SEOPress, AIOSEO or The SEO Framework is active: two sets of
`og:` tags on one page are worse than none, because which one a scraper reads
is undefined and the answer differs per scraper. The JSON-LD stays either way,
since those plugins do not describe a vehicle.

| Filter | Purpose |
|---|---|
| `wmds_jsonld` | change the schema before it is printed |
| `wmds_social_meta` | change the `og:` and `twitter:` tags |
| `wmds_seo_output` | switch either off, per page |

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

## When it stops

A sync that stops is invisible until somebody opens this screen. **Status &
log → Tell me when something is wrong** turns that around: a failed or overdue
run arrives by e-mail.

It is deliberately quiet. The same problem is reported once and then not again
until a cooldown has passed — an hour, six hours, twelve, or a day — because a
sync failing every fifteen minutes should produce one mail, not ninety-six.
A *different* problem is sent straight away, and so is the recovery: "it is
fixed" is the one message nobody wants to wait six hours for.

"Not configured" is never mailed about. A plugin nobody has set up yet is not
a fault.

**Send a test alert** sends the mail an alert would send right now. A
WordPress installation that cannot send mail at all — no SMTP configured, the
usual case on a fresh server — fails there rather than on the day it matters.

The optional weekly summary goes out even when nothing is wrong, which is what
makes silence evidence rather than an assumption.

`wmds_alert_recipients` decides who is written to; `wmds_run_finished` fires
with the statistics of every completed run, for anything else.

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

30 manufacturer logos ship with the plugin, output via `[vehicle-logo]`. For a
missing make, drop a file into `wp-content/uploads/wmds-logos/` named after the
make (`Tesla.png`). Case, spaces and special characters do not matter — the
lookup normalises both sides.

## Emission stickers

The three German Feinstaubplaketten ship with the plugin as SVG, named after
the colour the feed reports: `assets/emission-stickers/{red,yellow,green}.svg`.
Group 1 carries no sticker and resolves to nothing.

    [emission-sticker width="40"]

Inside the loop the shortcode reads `emissionSticker_key` from the vehicle; pass
`sticker="EMISSIONSSTICKER_GREEN"` to force one. `WMDS_Stickers::url()` and
`$vehicle->emission_sticker_url()` return the file for a template, and
`wmds_emission_sticker_url` filters the result.

## List-view icons

`mileage.svg`, `fuel.svg`, `gearbox.svg` and `emission.svg` are expected under
`wp-content/icons/`. For a different path:

```php
define( 'WMDS_ICONS', 'https://example.com/path/to/icons/' );
```

## Development

```
sh tests/run.sh          all tests, no PHPUnit and no Composer
sh bin/bump.sh 2.0.2     set the version everywhere it is stated
composer install         tooling for the coding standards
composer lint            phpcs (WordPress standards + PHP compatibility)
php bin/po2mo.php ...    rebuild a translation catalogue after editing a .po
```

### How a class gets loaded

`WMDS_Autoloader` resolves `WMDS_Facet_Store` to
`includes/class-wmds-facet-store.php`, `WMDS_Tab_Tools` to
`includes/tabs/class-wmds-tab-tools.php` and `WMDS_Cf7` to
`includes/integrations/class-wmds-cf7.php`. Adding a class means adding a
file; there is no map and no list in the plugin header.

The two subdirectories are groups, not namespaces. `tabs/` is one admin screen
each. `integrations/` is the code that talks to another plugin: it is loaded
on `plugins_loaded` rather than at file level, it checks that the plugin is
there before it hooks anything, and it has to leave the site working when it
is not. Nothing outside that folder may assume any of those plugins exists.
`tests/test-autoloader.php` reads the directory list off the loader rather
than repeating it, so a directory added there cannot leave the test passing on
a tree it no longer describes.

Four files are required outright anyway, and the reason is worth knowing
before you tidy them away: **a file that does something when it loads cannot
be loaded lazily.** `class-wmds-compat.php` holds functions rather than a
class, and the logo, sticker and WP-CLI files each register something at file
level. Nothing on a page that only writes `[vehicle-logo]` would ever mention
`WMDS_Logos`, so an autoloaded file would never run and the shortcode would
simply not exist. `tests/test-boot.php` and `tests/test-autoloader.php` both
fail if that require disappears.

### How the facets are put together

| Class | Does |
|---|---|
| `WMDS_Facets` | the definitions, the two filters, the shortcodes — the one class a template addresses |
| `WMDS_Facet_Request` | what a request means, and writing a selection back out as a URL |
| `WMDS_Facet_Query` | a selection, as `WP_Query` arguments |
| `WMDS_Facet_Store` | the counts, the range bounds, and the cache under both |

Only the first is public in the sense that matters: a theme overriding
`parts/filter-bar.php` talks to `WMDS_Facets` and nothing else.

### How the admin screen is put together

| Class | Does |
|---|---|
| `WMDS_Admin` | the menu, the assets, which tab is showing |
| `WMDS_Admin_Actions` | every `admin-post.php` action and where it redirects |
| `WMDS_Admin_Ajax` | test connection, sync, dismiss |
| `WMDS_Admin_Notices` | the notice store and the screen-wide warning |
| `WMDS_Admin_Ui` | the markup shared between tabs |
| `WMDS_Tab_*` | one class per tab, in `includes/tabs/` |

A tab is a class with a `render()` method. Adding one means a class, a line
in `WMDS_Admin::tabs()` and a line in the loop that requires them.

### The admin screen has a net under it

`tests/test-admin.php` drives `WMDS_Admin` through its real entry points:
every tab is rendered, every action goes through `handle()`, and a redirect
and `wp_die()` throw instead of ending the process so the test can see where
the request wanted to go.

It asserts what a refactor breaks silently rather than loudly — that every tab
still exists and renders, that each carries its own fields, that saving one
tab leaves the settings the other tabs own alone, that an unticked checkbox
is stored as a no rather than ignored, that every action lands on the right
tab, and that the screen is shut to anybody without the capability.

`tests/wp-admin-fake.php` supplies the admin half of WordPress the way
`wp-fake.php` supplies the post and media half.

### Support classes

Four classes hold what the feature classes would otherwise each grow their
own copy of. They take no WordPress state and are tested on their own in
`tests/test-support.php`.

| Class | For |
|---|---|
| `WMDS_Str` | measuring, cutting and scrubbing text, multibyte-safe |
| `WMDS_Num` | reading a number — see below |
| `WMDS_Date` | the shapes a date arrives in, and the two it leaves in |
| `WMDS_Mail` | a settings field into a recipient list, and sending |

`WMDS_Num` deliberately offers no general "parse a number". A person typing
`12.500` into a price filter means twelve and a half thousand; the API
sending `12.500` means twelve and a half. Reading either with the other's
rule is wrong by a factor of a thousand, so the two are named for what they
read — `from_input()` and `from_feed()` — and there is no third function that
would let a caller avoid the question.

### Releasing

`sh bin/bump.sh <version>`, write the entry in `CHANGELOG.md` and `readme.txt`,
merge to `main`. That is the whole release: the workflow reads the version out
of the plugin header, stops if that tag already exists, runs the tests, builds
the ZIP and publishes the tag and the release. A tag pushed by hand still works
and does the same thing.

`tests/test-version.php` holds the three places that state the version to the
same answer and insists on a changelog entry for it, so a half-finished bump
fails before it reaches `main`.

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
| `mob_vehicle-list.php` | `[vehicles]`, and its alias `[fahrzeuge-anzeigen]` |
| `parts/vehicle-card.php` | one card, used by the last two |
| `parts/vehicle-gallery.php` | the photos on the detail page |
| `parts/filter-bar.php` | the filter components |
| `parts/enquiry-form.php` | the enquiry form |

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
| `wmds_facets` | add, remove or reorder the filter components |
| `wmds_facet_sorts` | change the sort orders on offer |
| `wmds_enquiry_recipients` | decide where an enquiry is sent |
| `wmds_alert_recipients` | decide who is told when the sync stops |

| Action | Fires |
|---|---|
| `wmds_run_finished` | after a completed run, with its statistics |
| `wmds_vehicle_stored` | after one vehicle is completely written — post, meta and images, which `save_post` cannot promise |
| `wmds_mail_sent` | after the plugin handed a message to `wp_mail()` and it was accepted |

Colour, spacing and radii are custom properties on `.wmds-scope`, so a theme
can restyle the whole thing in one rule rather than selector by selector:

```css
.wmds-scope {
	--wmds-radius: 0;
	--wmds-line: #d8d8d8;
	--wmds-surface: #fafafa;
}
```

The dark variant follows `prefers-color-scheme`. A theme that knows its own
answer overrides the properties and ours never applies.

## Filtering

The inventory filters itself. `[vehicle-filter]` renders one component per
field the feed fills:

| Component | Fields |
|---|---|
| Search box | title and description |
| Dropdown | make, model, body type |
| Radio buttons | condition |
| Checkboxes | fuel, transmission, exterior colour |
| Range slider | price, mileage, power, first registration |
| Sort | price, mileage, first registration, power, newest |

    [vehicle-filter]
    [vehicle-filter layout="sidebar" facets="make,price,fuel"]
    [vehicles filters="yes" pagination="yes" posts_per_page="12"]

The bundled archive template already carries the bar, so `/fahrzeuge/` filters
without a shortcode anywhere.

Every option states how many vehicles are behind it. That number is counted
against the rest of the selection rather than against the whole inventory, so
an option that would leave nothing says so rather than promising a result it
cannot keep. Counts and slider bounds are cached for ten minutes and dropped
whenever a vehicle changes.

The bar is a plain GET form. Without JavaScript the sliders fall back to the
number fields they mirror and nothing else changes — the filters live in the
URL, so a filtered list can be linked, bookmarked, paginated and cached.

`[vehicle-count]` prints how many vehicles the current filters leave;
`[vehicle-count filtered="no"]` prints the size of the whole inventory.

Sites that already run FacetWP keep working: the meta keys are unchanged, and
they now turn up as facet sources under their own heading rather than having
to be typed. See [Integrations](#facetwp).

## The shortcode

    [vehicles posts_per_page="6" orderby="date" order="DESC"]

Those attributes steer the query, and `columns` (2, 3 or 4), `layout`
(`grid` or `list`), `pagination`, `heading` and `filters` steer the output.
Every other attribute filters on the meta key of the same name, so nothing has
to be whitelisted first:

    [vehicles make="Audi" fuel="Diesel" interior_type="Teilleder"]

`marke`, `modell`, `zustand`, `kraftstoffart`, `getriebe` and `anzahl` map onto
their English counterparts, and `[fahrzeuge-anzeigen]` and `[fahrzeug-logo]`
stay registered, so pages written against the old names keep working.

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
