=== mobile.de Sync ===
Contributors: dmnktoe
Tags: mobile.de, vehicles, dealership, import, facetwp
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Synchronises a dealer's vehicle inventory from mobile.de into the "fahrzeuge" custom post type.

== Description ==

Fetches a dealer's vehicle inventory through the mobile.de Search API and
stores it as WordPress posts. The vehicle data lives in post meta under stable
field names — ready to use for your own templates, queries and filters.

* CPT `fahrzeuge`, shortcode `[vehicles]`
* One meta key per property, directly usable as FacetWP facet sources
* Manufacturer logos via `[vehicle-logo]`, extendable without a plugin update
* German emission stickers via `[emission-sticker]`
* Incremental sync: each run fetches only what changed
* WP-CLI: `wp wmds sync`, `status`, `test`, `flush-cache`
* German translation included

= In the admin =

The sync state — not configured, running, overdue, up to date — appears in the
admin bar, in a dashboard widget and on every admin screen that needs to warn
you, all reading the same verdict.

The vehicle list shows photo, make and model, price, mileage, first
registration and the ad ID, sorts by price, mileage and first registration, and
filters by make. Each row can be reloaded from mobile.de or opened there.

The settings screen is split into Connection, Schedule, Status & log and Tools.
"Test connection" and "Sync now" run without a page reload, the sync showing
real progress as it works through the inventory in batches.

= Error handling =

Every API response is validated before it is used. An outage, a rate limit or
an error message leads to a clean abort with a log entry, never to a fatal
error.

An aborted run deletes nothing. Sold vehicles are removed only after a
complete reconciliation, they go to the trash rather than being deleted, and a
run that would remove more than 30 percent of the inventory aborts with a
reason instead.

== Installation ==

1. Upload the folder to `wp-content/plugins/` and activate it.
2. Deactivate other mobile.de plugins that populate the same post type.
3. Under **Vehicles → Sync Settings** enter username, password and seller ID
   (or, failing that, the dealer name from `home.mobile.de/NAME`).
4. **Test connection.**
5. Start the first full import from the command line:
   `wp wmds sync --full --all`
6. Re-index FacetWP.

= Scheduling =

WP-Cron is triggered by page views, not by a clock. On a low-traffic site runs
are delayed considerably as a result. Recommended:

    define( 'DISABLE_WP_CRON', true );   // wp-config.php

plus a cron job at your host calling `wp cron event run --due-now` or
`wp-cron.php` every 15 minutes.

= Icons =

The list view expects `mileage.svg`, `fuel.svg`, `gearbox.svg` and
`emission.svg` under `wp-content/icons/`. For a different path:

    define( 'WMDS_ICONS', 'https://example.com/path/to/icons/' );

== Frequently Asked Questions ==

= Why are no vehicles being imported? =

"Test connection" on the settings screen states the reason in plain words. The
most common causes are a wrong seller ID, the Search API not being enabled for
the account, or the schedule never being triggered.

= A vehicle shows MANUAL_GEAR instead of a readable label. =

The labels come from mobile.de's reference data and are cached. If that fetch
failed, the raw key is used as a fallback. "Re-fetch reference data" fixes it.

= A make has no logo. =

Drop your own file into `wp-content/uploads/wmds-logos/`, named after the make,
e.g. `Tesla.png`. Case and special characters do not matter. Where mobile.de
and the file disagree on the name — "VW" against "Volkswagen" — either name
finds the file.

= Can I run the plugin in another language? =

German ships with the plugin. Source strings are English and translatable
through the `wp-mobile-de-sync` text domain; for another language put a further
`.mo` file into `languages/`. The reference-data labels come from mobile.de in
the language you configure.

Note that labels are resolved at import time and stored in post meta, so a
language change takes effect after the next full sync.

The consumption disclaimer on the detail page is prescribed verbatim by the
Pkw-EnVKV, so the bundled German catalogue carries the statutory wording rather
than a translation of the English source.

== Changelog ==

= 2.0.0 =
* The shortcodes are English: `[vehicles]` and `[vehicle-logo]`. The German
  names keep working, so no page needs editing.
* `[vehicles]` filters on any meta key. `[vehicles fuel="Diesel" make="Audi"]`
  needs no whitelist, and the five old filter attributes still apply.
* Fixed: the shortcode rendered unstyled on ordinary pages. The stylesheet was
  tied to the post type, so any page carrying the shortcode loaded the markup
  without the grid.
* Fixed: "Test the connection" never turned green on the checklist, because it
  read the last sync run rather than whether a test had passed.
* Fixed: an import cut short by a PHP time limit left its lock behind and
  blocked every following run for 15 minutes. The lock is refreshed while a run
  works and can be released from Tools.
* Equipment is read from whatever the feed sends instead of a fixed list of 28
  features, so Bluetooth, CD player, radio, alloy wheels, tow bar and the rest
  arrive on their own.
* New fields: vehicle class, climatisation, airbag, country version, axles,
  height, length, parking assistants and the full seller address.
* Templates are looked up in `wp-mobile-de-sync/` in the child and parent theme
  as well as in the theme root, through the new `wmds_template` filter.
* The vehicle card is a template part of its own, shared by the archive and the
  shortcode and overridable on its own.
* The German emission stickers ship as SVG and are rendered by the new
  `[emission-sticker]` shortcode, so no other plugin has to supply the images.
* Upgrading needs one full sync for the new fields: `wp wmds sync --full --all`

= 1.0.2 =
* The plugin has an icon. The updates screen showed the grey placeholder plug
  because the update response carried none.
* Tested against WordPress 7.0.

= 1.0.1 =
* Fixed: every incremental run failed with HTTP 400 because the timezone
  offset in `modificationTime.min` reached mobile.de unencoded.
* Fixed: no logo for Volkswagen — the feed sends "VW", the file is called
  "Volkswagen.png". Equivalent names now find the same file.
* Dates and times follow the site's own format, with the exact stamp in the
  tooltip.
* New "Once a day" sync schedule.
* The watermark is called a change marker in the English strings too.

= 1.0.0 =
* First release.
* Import through the mobile.de Search API into the "fahrzeuge" custom post
  type, with an incremental sync and per-vehicle detail calls.
* Sold vehicles go to the trash, guarded by three independent limits against
  mass deletion.
* Swapped photos are detected via the image checksums in the feed.
* Manufacturer logos plus the `[fahrzeug-logo]` shortcode, extendable without
  a plugin update.
* Bundled templates without a CSS framework, overridable from the theme.
* WP-CLI commands and updates through GitHub releases.
* English source strings with full internationalisation.
