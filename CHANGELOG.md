# Changelog

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [Semantic Versioning](https://semver.org/).

## [1.0.3] – 2026-07-31

### Fixed
- `[fahrzeuge-anzeigen]` rendered as an unstyled list on ordinary pages. The
  stylesheet was enqueued for `is_singular( 'fahrzeuge' )` and the post type
  archive only, and a page carrying the shortcode is neither — so the markup
  arrived without the grid that lays it out. The decision now follows the
  templates rather than the post type: a post whose content contains the
  shortcode counts, and the shortcode itself enqueues the file when it runs,
  which also covers a widget, a page builder or a template part the main
  query knows nothing about. The `wmds_enqueue_styles` filter still switches
  the file off everywhere.

### Added
- A template hierarchy. All four templates are resolved through
  `WMDS_Templates::locate()`, which looks in `wp-mobile-de-sync/` in the
  stylesheet directory, then in the template directory, then in the theme
  root — the place an earlier solution expected `mob_vehicle-list.php`, which
  therefore keeps working — and falls back to the bundled file. The new
  `wmds_template` filter has the last word.
- `templates/parts/vehicle-card.php`. The archive and the shortcode rendered
  the same card twice, in two copies that had already begun to drift. It is
  one template part now, taking a heading level and whether to show warnings,
  and a theme can override the card without copying the loop around it.

## [1.0.2] – 2026-07-31

### Added
- An icon, in mobile.de's colours with the plugin's own mark. The updates
  screen showed the grey placeholder plug, because `update-core.php` reads the
  icon from the update response and ours carried none. `WMDS_Updater` supplies
  it for the update list and for the details modal alike.

### Changed
- Tested up to WordPress 7.0, in `readme.txt` and in the release notes the
  updater reads that value from. The end-to-end suite installs the current
  WordPress on every run, so the claim is the one CI has been checking.

## [1.0.1] – 2026-07-31

### Fixed
- Every incremental run failed with HTTP 400. `add_query_arg()` leaves the
  values it adds unencoded, so the `+` of the timezone offset reached
  mobile.de as a space and `modificationTime.min` was rejected as a
  `typeMismatch`. The search URL is now built with each value encoded, and
  the test harness no longer encodes on the client's behalf: the stub and the
  mock server decode the way a real server does, so the same mistake fails in
  CI instead of in production.
- No logo for Volkswagen. The feed calls the make `VW`, the bundled file is
  `Volkswagen.png`, and the lookup matched neither against the other.
  Equivalent names now resolve to the same file, in both directions and for
  uploaded logos too.

### Changed
- Dates and times follow the site's own date and time format instead of the
  storage format `2026-07-31 11:38:12`. The last run reads as "12 mins ago"
  with the exact time in the tooltip; the log keeps its precise stamp there.
  Both are `<time>` elements with a machine-readable attribute.
- "Once a day" joins the sync schedule, for inventories that change rarely
  and hosts that would rather not run a job every 15 minutes.
- The English strings call the watermark a change marker, which is what the
  German catalogue has been calling it all along. The stored option keeps its
  name.
- README badges for release, downloads, CI, requirements and licence.

## [1.0.0] – 2026-07-31

First release.

### Added
- A German translation ships with the plugin. Until now a plugin written for
  German dealerships presented itself in English on every screen.
  `bin/po2mo.php` compiles the catalogue, because the project has no build step
  and `msgfmt` is not installed everywhere.
- An admin bar item carries the sync state and the inventory size — on the
  front end as well, which is where a stale inventory is actually noticed. Its
  menu holds the last result, the time to the next run, "Sync now", and on a
  vehicle page a link to the source ad plus a reload for that one vehicle.
- A dashboard widget with the newest vehicles, configurable in count and sort
  order, plus an entry in "At a Glance".
- The vehicle list shows photo, make and model, price, mileage, first
  registration and the ad ID. Price, mileage and first registration sort;
  vehicles missing the sorted-on value stay in the list rather than dropping
  out of it. A filter by make and the row actions "Reload from mobile.de" and
  "View on mobile.de" come with it.
- The edit form states that the importer owns the record, and a meta box shows
  the ad ID, the modification date, the photo count and a reload button. The
  importer used to overwrite manual edits without ever having said so.
- A full sync can be forced from the settings screen. The capability existed —
  `wmds_force_full_sync` discards the watermark — but hung off the daily cron
  event only, with no way to reach it.
- A notice on every admin screen when the sync needs attention, dismissible per
  user and re-armed when the state changes to something else. A broken sync was
  previously invisible from anywhere but its own settings page.
- A "Settings" link on the plugins screen, a checklist that carries a fresh
  install through setup, a log that filters down to problems and can be
  downloaded or cleared, and a button that deletes the stored credentials.
- `WMDS_Importer::refresh()` re-reads a single ad. Unlike a scheduled run it
  fails loudly: a manual repair that silently writes a half-mapped record would
  be worse than one that does nothing.

### Changed
- The settings screen is split into Connection, Schedule, Status & log and
  Tools, with the health state above the tabs.
- Actions on the settings screen now go through `admin-post.php` and leave
  through a redirect. Previously every action hung off `admin_init` on a plain
  POST, so reloading the page after "Sync now" ran the sync again.
- "Test connection" and "Sync now" run over AJAX. The sync drives one batch per
  request and reports real progress, instead of holding a single request open
  across the whole inventory and hoping it finishes before the PHP timeout.
- Seller ID and dealer name are an explicit either/or now, and saving clears
  the unused one. Two equal-looking fields, only one of which the client ever
  sends, invited exactly the wrong guess.
- The label language is a list of the languages the reference data actually
  comes in, not a free-text field.
- An integration test that runs the plugin inside a real WordPress on a real
  database, driven through WP-CLI, with a local mock server answering as
  mobile.de. It covers what stubs cannot: that the custom post type, the media
  sideload, the featured image, the transient cache, the trash, the health
  state, the German catalogue and the uninstall behave the way the plugin
  claims. The plugin is untouched by it — the harness swaps the transport at
  `pre_http_request`, so the same `wp_remote_get()` and `download_url()` calls
  run, over real HTTP, against fixtures instead of a dealer's live inventory.
  CI runs it on every push.

### Fixed
- A partial image failure was recorded as a complete import. As soon as one
  image of a vehicle arrived, the hashes of **all** of them were stored, so a
  vehicle that lost image 7 of 15 to a timeout never got it back — the run
  reported success and the gap became permanent. Only the images that actually
  landed are recorded now, which leaves the difference visible and repairs it
  on the next run.
- A vehicle's images stayed in the media library forever once the vehicle was
  gone. Deleting a vehicle for good now deletes its images with it. Trashing
  deliberately does not: a vehicle in the trash can be restored, and restoring
  it has to bring the gallery back — that recovery window is why removal uses
  the trash in the first place. WordPress empties the trash on its own
  schedule, so the images do go eventually.

### Changed
- The per-vehicle image cap is applied before the hash comparison rather than
  inside the import. With more images in the feed than the cap allows, the
  stored list would otherwise differ from the feed on every run and every run
  would re-download the whole gallery.

### Import

- Fetches a dealer's inventory through the mobile.de Search API in the current
  data format (New JSON) and stores it in the `fahrzeuge` custom post type.
- Addressing through the documented `customerId` parameter with the numeric
  seller ID. The undocumented `dealer=` remains available as a fallback.
- Incremental sync via `modificationTime.min`: a run fetches only what
  changed. The per-ad detail call happens only for new and changed vehicles,
  because the description and the seller's phone numbers exist nowhere else.
- A run processes at most 20 vehicles and reschedules itself, which keeps the
  runtime independent of the PHP timeout.
- One database query for the whole inventory instead of one per vehicle.
- Meta fields are written only on an actual change, detected via a content
  fingerprint.
- Swapped photos are detected through the image checksums in the feed.
- Labels such as the transmission type come from mobile.de's reference data
  and are cached for 30 days.

### Removal safety

Three independent guards, because an import that removes vehicles can wipe out
half a live inventory and still report success:

1. Nothing is removed after a partial pass.
2. A run that saw no vehicle at all removes nothing — the case that occurs
   with a wrong seller ID, where the API answers HTTP 200 with an empty
   result rather than an error.
3. More than 30 percent at once aborts with a reason. Below that sits an
   absolute floor of three vehicles, so small inventories are not permanently
   blocked.

Removed vehicles go to the trash. An aborted API call ends the run without
removing anything.

### Front end

- Templates for the detail, archive and list views: no CSS framework, using
  `get_header()`/`get_footer()`, escaped throughout and annotated with
  schema.org. A theme's own templates take precedence.
- `WMDS_Vehicle` as a read model over post meta, supplying formatted values
  and handling the special cases: the inspection falls back to `newHuAu`, and
  a missing VAT rate means margin-scheme taxation rather than "not stated".
- Manufacturer logos plus the `[fahrzeug-logo]` shortcode. Files under
  `uploads/wmds-logos/` take precedence, so a missing make can be added
  without a plugin update.
- A restrained stylesheet, loaded only on vehicle pages and switchable via the
  `wmds_enqueue_styles` filter.

### Operations

- WP-CLI: `wp wmds sync|status|test|flush-cache`.
- A lock against overlapping runs.
- A log of the last 50 messages in the database, visible on the settings
  screen.
- A notice on the settings screen when the schedule is not being triggered.
- A warning when an inventory exceeds the 2000 vehicles the API hands out
  across paginated pages.
- Updates through GitHub releases using the WordPress `update_plugins_*`
  filter, with no third-party update library.
- `uninstall.php` removes the plugin's data and leaves the vehicles in place.

### Internationalisation

Source strings are English and translatable through the `wp-mobile-de-sync`
text domain. Reference-data labels follow the configured language, defaulting
to the site language. Numbers are formatted through `number_format_i18n()`.

Labels are resolved at import time and stored in post meta, so a language
change takes effect after the next full sync.

### Development

- Tests run without WordPress, PHPUnit or Composer. The import run is covered
  end to end against a WordPress stubbed in process memory.
- Fixtures are anonymised but structurally real API responses.
- CI checks syntax and tests on PHP 7.0, 7.4 and 8.3, runs phpcs against the
  WordPress standards, and fails the build on credentials in the tree.
