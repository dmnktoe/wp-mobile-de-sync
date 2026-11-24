=== mobile.de Sync ===
Contributors: dmnktoe
Tags: mobile.de, vehicles, dealership, facetwp, contact form 7
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 2.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Synchronises a dealer's vehicle inventory from mobile.de into the "fahrzeuge" custom post type.

== Description ==

Fetches a dealer's vehicle inventory through the mobile.de Search API and
stores it as WordPress posts. The vehicle data lives in post meta under stable
field names — ready to use for your own templates, queries and filters.

* CPT `vehicles`, shortcodes `[vehicles]`, `[vehicle-filter]`, `[vehicle-count]`
* Blocks for the same: Vehicles, Vehicle filter, Vehicle count, Vehicle enquiry
* Usage tab in Sync Settings: blocks, shortcodes and the dashboard widget
* Filtering of its own: dropdowns, checkboxes, radio buttons, range sliders,
  search and sorting — no second plugin needed
* One meta key per property, listed as FacetWP facet sources under "mobile.de"
* Contact Form 7: mail tags for the vehicle a visitor is looking at, and the
  submission filed under Enquiries
* Names the mailer plugin that sends the mail, and keeps the last failure
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

The settings screen is split into Connection, Schedule, Enquiries,
Integrations, Status & log and Tools.
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
6. On a site that runs FacetWP, index it once. From then on each vehicle is
   re-indexed as the sync writes it.

= Scheduling =

WP-Cron is triggered by page views, not by a clock. On a low-traffic site runs
are delayed considerably as a result. Recommended:

    define( 'DISABLE_WP_CRON', true );   // wp-config.php

plus a cron job at your host calling `wp cron event run --due-now` or
`wp-cron.php` every 15 minutes.

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

= 2.6.0 =
* Added: proof of work on the enquiry form, in the format of ALTCHA - no
  external service, no cookie, nothing to click; can be switched off.
* Added: spam status for enquiries. Spam words, three or more links or a
  blocked sender keep an enquiry out of the mail; spam is deleted after 30 days.
* Added: "Mark as spam" / "Not spam", also as bulk actions; marking teaches the
  blocklist, which can be edited on the Enquiries tab.
* Added: a notice for enquiries whose mail did not go out, and a list view for them.
* Added: filters `wmds_spam_words` and `wmds_enquiry_spam`.

= 2.5.1 =
* Fixed: make logos on vehicle cards sat too high and too large when the list
  was placed inside a page-builder column.

= 2.5.0 =
* Added: vehicles taken off mobile.de leave the site with the next scheduled run.
* Added: the address of a removed vehicle leads to the overview with a note.
* Added: similar vehicles at the end of a vehicle page.
* Added: enquiry status (new / answered), count in the menu, reply by e-mail.
* Added: Opel and Hyundai logos; a tool to delete unused vehicle pictures.
* Changed: five settings tabs instead of nine, starting on an overview.
* Changed: pictures of a removed vehicle are deleted right away.

= 2.4.6 =
* Changed: the sync log is written in the site's language.

= 2.4.5 =
* Added: each enquiry records whether its mail was handed over, and to whom.
* Changed: one test mail on the Integrations tab for both the enquiry and the
  alert address; the separate test alert is gone.
* Changed: English and German texts revised for a professional tone.
* Fixed: the consent notice on a stored enquiry showed raw markup.

= 2.4.4 =
* Fixed: the German label for unrepaired damage called any damaged vehicle an
  accident vehicle; it now uses mobile.de's own wording.

= 2.4.3 =
* Changed: sold vehicles are removed as soon as a full sync has read the whole
  listing, not after its last batch - so the site never shows more vehicles
  than mobile.de. The guards against removing too much are unchanged.

= 2.4.2 =
* Fixed: between two passes of an unfinished run the status said "in sync";
  it now says "Sync in progress" with the number still to go.
* Fixed: an unfinished run continued only every fifteen minutes, because
  WordPress dropped the follow-up booked a minute later. It has a hook of its
  own now.

= 2.4.1 =
* Fixed: a sync schedule WordPress had dropped - during an update, for
  instance - stayed gone until the schedule was saved by hand. The plugin now
  puts it back by itself.

= 2.4.0 =
* Changed: the archive and the detail page are redesigned - cards with the
  key figures as icons, a gallery with arrows and a counter, a price box that
  stays in view, a filter bar that folds the rarer filters away.
* Changed: the templates bring their own sizes and controls and look the same
  in any theme; colours and radii are custom properties on `.wmds-scope`.
* Fixed: under Salient the revealed footer covered the vehicles, and under
  Salient 18 the page shrank to the width of its text.
* Fixed: sorting did not reload the list where selects are replaced by select2.
* Fixed: the first-registration range was written with a thousands separator.

= 2.3.1 =
* Changed: the post type is `fahrzeuge` again, and so are `/fahrzeuge/`,
  `single-fahrzeuge.php` and `archive-fahrzeuge.php`. Updating from 2.1 or 2.2
  keeps the vehicles, their addresses and any theme templates.
* Added: the address of the archive and the vehicles can be changed under
  Settings → Usage → Address, or with the `wmds_slug` filter.

= 2.3.0 =
* Changed: the post type, rewrite slug and templates are English — `vehicles`,
  `/vehicles/`, `single-vehicles.php`, `vehicle-list.php`. German shortcode
  aliases and attributes are removed.
* Fixed: the bundled pages render inside block themes. They called
  `get_header()`, which a block theme answers with a deprecation and a page
  without the theme's header, styles or width.
* Fixed: on a card the manufacturer logo covered the vehicle photo.
* Fixed: the archive, the list and the filter bar died with "Call to undefined
  method".
* Fixed: a full sync ended after its first batch and never removed anything;
  a failed detail call emptied the vehicle; an aborted run showed as "Up to
  date"; vehicles in the bin tripped the removal guard.
* Fixed: range filters refused ordinary figures, a shortcode's fixed filter was
  unknown to the filter bar, and facet counts went stale after every sync.
* Fixed: the enquiry form bounced the first submission from a cached page.
* New: Contact Form 7, mail delivery and FacetWP integrations, four blocks, a
  Usage tab, and enquiries in WordPress's personal data export and erasure.
* The full list is in CHANGELOG.md.

= 2.2.0 =
* New: filtering of its own. `[vehicle-filter]` renders dropdowns, radio
  buttons, checkboxes, two-handle range sliders, a search box and eight sort
  orders over the fields the feed already fills. No second plugin needed.
* New: every option states how many vehicles are behind it, counted against
  the rest of the selection rather than against the whole inventory.
* New: the vehicle archive reads the filters directly; `[vehicles]` carries
  them anywhere else with `filters="yes"`.
* New: `[vehicle-count]`, and `columns`, `layout`, `pagination` and `heading`
  on `[vehicles]`.
* The filters live in the URL and work without JavaScript, so a filtered list
  can be linked, bookmarked and paginated.
* New: an enquiry form on the vehicle page. It reaches the dealer by e-mail
  with the vehicle it is about and answers to the enquirer, and every enquiry
  is filed under Vehicles → Enquiries so a mail that fails is not simply gone.
  Honeypot, time trap and a per-visitor limit keep the robots out.
* New: an "Enquiries" settings tab — recipients, copy to the seller,
  confirmation to the enquirer, and the privacy notice to agree to.
* New: `[vehicle-enquiry]` puts the form anywhere else.
* New: structured data. A vehicle page carries a complete `Car` node with an
  `Offer` in JSON-LD, the archive an `ItemList` — so Google can show price,
  mileage and first registration in the result.
* New: Open Graph and Twitter cards, so a shared link shows the photo, the
  title and the price. Suppressed when an SEO plugin already emits them.
* New: notification by e-mail when the sync fails or falls behind — once per
  problem, not once per run, with the recovery sent straight away. Optional
  weekly summary, and a test alert to prove the site can send mail at all.
* Changed: the detail page no longer carries microdata; the JSON-LD replaces
  it and says more.
* Changed: the bundled templates were reworked — themeable custom properties,
  a dark variant, badges on the cards, a sticky summary and a gallery that
  swaps the main image in place.
* Fixed: saving one settings tab emptied the settings the other tabs own —
  saving the schedule cleared the credentials, saving the connection reset the
  interval.

= 2.1.1 =
* Fixed: the German translation never applied. The compiled catalogue was
  written with an empty hash-table address, and WordPress derives the length of
  the translation table from exactly that address — so it rejected the file and
  fell back to the English source strings everywhere. Every release since the
  translation was added was affected.
* Fixed: strings with a context (`_x()`) were compiled without it and could
  never be found.
* The compiled catalogue is now checked by the test suite the way WordPress
  reads it, including context and both plural forms.

= 2.1.0 =
* New: a "System" tab that checks PHP and WordPress version, extensions, GD or
  Imagick, execution time and memory, the uploads folder, permalinks and
  mod_rewrite, WP-Cron and outbound requests.
* New: an "About" tab with version, links and what the plugin registers.
* Fixed: a sync run was killed by PHP's execution limit while resizing images,
  which also took the rest of that WP-Cron run with it. A pass now stops on its
  own clock and leaves the rest pending for the next run.
* Fixed: a run killed by a fatal error left its lock behind and blocked every
  run for the next fifteen minutes.
* Fixed: a vehicle lost its images when a run was interrupted while importing
  them. The existing gallery is kept until the new one is in.
* Fixed: vehicles could come out as changed on every run and be written again
  and again, because the stored modification date was not the one the sync
  compares against.
* Fixed: updates are written into the WordPress update transient as well, so a
  failed call to api.wordpress.org no longer hides them.

= 2.0.1 =
* Fixed: 2.0.0 was never offered as an update. The lookup against GitHub was
  cached for six hours with no way around it, so a site that had checked while
  1.0.2 was current kept being told 1.0.2 was current. The cache lives an hour
  now, is dropped when the installed version changes, and "Check again" gets
  past it.
* New: "Check for updates" on the plugins screen, and a failed check now states
  its reason there instead of looking like "you are up to date".

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
