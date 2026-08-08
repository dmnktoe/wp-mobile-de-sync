# Changelog

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [Semantic Versioning](https://semver.org/).

## [2.2.0] – 2026-08-08

### Added
- Filtering of its own, so an inventory can be searched without a second
  plugin. `[vehicle-filter]` renders a bar of components over the fields the
  feed already fills: dropdowns for make, model and body type, radio buttons
  for the condition, checkboxes for fuel, transmission and colour, and
  two-handle sliders for price, mileage, power and first registration. A
  search box and eight sort orders sit alongside them, and every active filter
  becomes a chip that removes itself when clicked.
- The vehicle archive reads those filters directly, so the bundled archive
  template needs no shortcode, and `[vehicles]` accepts `filters="yes"` to
  carry them anywhere else.
- Every option states how many vehicles are behind it, counted against the
  rest of the selection rather than against the whole inventory — so an option
  that would leave nothing says so instead of promising a result it cannot
  keep. The counts and the slider bounds are cached and dropped whenever a
  vehicle changes.
- The whole thing works without JavaScript: the bar is a GET form, the sliders
  fall back to the number fields they mirror, and the filters live in the URL,
  so a filtered list can be linked, bookmarked and paginated.
- `[vehicle-count]` prints how many vehicles the current filters leave.
- `[vehicles]` learned `columns`, `layout="list"`, `pagination` and `heading`.
- Two filters for themes: `wmds_facets` to add, remove or reorder the
  components, `wmds_facet_sorts` for the sort orders.

- An enquiry form on the vehicle page. It reaches the dealer by e-mail with
  the vehicle it is about — title, listing number, price and a link back —
  and answers to the enquirer rather than to the site. Optionally it also
  copies the address the feed carries for that vehicle, and sends the
  enquirer a confirmation of what they wrote.
- Every enquiry is filed under **Vehicles → Enquiries** as well, so one that
  fails to be delivered is not simply gone. What was asked, by whom, about
  which vehicle and under which privacy notice is on the record.
- Three guards a person never notices: a honeypot field, a form that was
  submitted faster than anyone could type it, and a limit of five enquiries
  per visitor per hour. A submission that fails validation comes back with
  what was typed still in the fields.
- A **Enquiries** tab with the recipients, the copy to the seller, the
  confirmation, whether to keep a record, and the privacy notice the visitor
  has to agree to.
- `[vehicle-enquiry]` puts the form anywhere else; a filter
  `wmds_enquiry_recipients` has the last word on where an enquiry goes.

- Structured data. A vehicle page carries a complete `Car` node in JSON-LD —
  make as a brand, model, body type, colour, fuel, transmission, doors, seats,
  previous owners, first registration as a date, CO₂, engine power and
  displacement as quantities with their unit codes, and an `Offer` with the
  price, the currency, the availability and the dealer. The archive carries an
  `ItemList` of what is on the page. What the feed does not state is left out
  rather than guessed.
- Open Graph and Twitter cards, so a link to a vehicle shared anywhere shows
  its photo, its title and its price instead of the site's name. They are
  suppressed when Yoast, Rank Math, SEOPress, AIOSEO or The SEO Framework is
  active, because two sets of `og:` tags on one page are worse than none.
- `condition_key` is stored alongside the localised condition label, so "new"
  and "used" can be told apart without reading a translated string. A vehicle
  imported before this falls back to its mileage.
- Three filters: `wmds_jsonld`, `wmds_social_meta` and `wmds_seo_output`.

- Notification by e-mail when the sync needs attention. A failed or an overdue
  run is reported once and then not again until a cooldown has passed — a sync
  that fails every fifteen minutes should produce one mail, not ninety-six a
  day. A *different* problem, and the recovery, are sent straight away: "it is
  fixed" is the one message nobody wants to wait six hours for.
- A test alert, so an installation that cannot send mail at all fails on the
  settings screen rather than on the day it matters.
- An optional weekly summary that goes out even when nothing is wrong, so
  silence stays evidence rather than an assumption.
- A `wmds_run_finished` action, fired with the statistics of a completed run.

### Changed
- The detail page no longer carries microdata. It described the same vehicle
  a second time and less completely than the JSON-LD now does, and a price
  marked up as `itemprop` on formatted text is a worse answer than the number
  in the `Offer`.
- The bundled templates were reworked. Colour, spacing and radii come from
  custom properties on `.wmds-scope`, which a theme can override in one place
  instead of selector by selector; the dark variant follows
  `prefers-color-scheme`. Cards carry badges for condition, availability and
  warnings along with the manufacturer logo, the summary on the detail page
  stays in view while the specifications scroll past, and the gallery swaps
  the main image in place rather than leaving the page — with every thumbnail
  still a plain link to the full image when JavaScript is off.
- The gallery moved into `parts/vehicle-gallery.php`, so a theme can restyle
  it without copying the detail page around it.

### Fixed
- Saving one settings tab emptied the settings the other tabs own. Each tab is
  a form of its own and posts only its own fields, but the handler read every
  setting out of whatever was submitted — so saving the schedule cleared the
  username and the seller ID, and saving the connection reset the interval to
  fifteen minutes. Each tab saves its own fields now.

## [2.1.1] – 2026-07-31

### Fixed
- The German translation never applied — not on the front end, not in the
  admin. `bin/po2mo.php` wrote the compiled catalogue with a hash-table address
  of zero, and WordPress derives the length of the translation table from
  `hash_addr - translations_addr`. That subtraction came out negative, the file
  was rejected, and every string fell back to its English source. The address
  now points at the end of the translation table, where an empty hash table
  would begin. Every release since the translation was added carried this.
- Strings with a context were compiled without one, so `_x()` could never find
  them. `msgctxt` is read now and stored under the `context \4 msgid` key
  gettext expects.

### Added
- `tests/test-i18n.php` reads the compiled catalogue the way WordPress does,
  including the two table-length checks that made the file unreadable, and
  verifies context and both plural forms resolve. It also fails when the `.mo`
  has fallen behind its `.po`.

## [2.1.0] – 2026-07-31

### Added
- A "System" tab that checks what the plugin needs from the server: PHP and
  WordPress version, the extensions it uses, GD or Imagick for the images,
  execution time and memory limit, a writable uploads folder, permalinks and
  mod_rewrite, WP-Cron, and whether outbound requests to mobile.de and GitHub
  are allowed at all. Each entry says what it found and what to do about it.
- An "About" tab with the installed version, the vehicles published, the post
  type and shortcodes the plugin registers, and links to the source, the issue
  tracker and the API documentation.

### Fixed
- A sync run was killed by PHP's execution limit. A batch is twenty vehicles
  with up to fifteen images each, every one of them downloaded and resized,
  which does not fit into the sixty seconds a lot of hosting allows — the run
  died in the middle of resizing an image and took the rest of that WP-Cron
  run with it. A pass now lifts the limit where the host permits it and, where
  it does not, stops on its own clock with time to spare and leaves the rest
  pending for the next run.
- A run killed by a fatal error left its lock behind, so every run for the next
  fifteen minutes gave up with "an import is already running". The lock is
  released on shutdown now and a retry is queued for a minute later.
- A vehicle lost its images when the run was killed while they were being
  imported: the old ones were deleted before the first new one arrived. The
  existing gallery is now kept until at least one new image is in, so an
  interrupted import leaves the vehicle as it was rather than empty.
- Vehicles could come out as changed on every single run. The modification date
  was stored as the detail call worded it while the sync plan compares it
  against the search result, and the two need not word the same moment the same
  way. The date the plan compares against is the one stored.
- Updates are written into WordPress's update transient as well. The
  `Update URI` header is the documented route, but core only evaluates it after
  its own call to api.wordpress.org has succeeded, and takes every self-hosted
  update down with it when that call fails.

## [2.0.1] – 2026-07-31

### Fixed
- 2.0.0 never showed up as an update. The lookup against GitHub was cached for
  six hours and nothing could get past that cache — not the plugins screen, not
  "Check again" on Dashboard → Updates. A site that had looked while 1.0.2 was
  the newest release kept being told 1.0.2 was the newest release, and 2.0.0
  came out under two hours later. The cache lives an hour now, is dropped as
  soon as the installed version changes, and "Check again", WP-CLI and the new
  `wmds_force_update_check` filter go straight past it.
- The update check told WordPress the PHP version a release needs but not the
  WordPress version, so the compatibility line on the updates screen had
  nothing to go on.

### Added
- "Check for updates" next to "Settings" on the plugins screen. It discards
  both caches and takes you to the updates screen with a forced check.
- A failed check states its reason under the plugin on the plugins screen. A
  used-up GitHub rate limit, a blocked outbound request and a release without a
  ZIP were all indistinguishable from "you are up to date".

## [2.0.0] – 2026-07-31

### Changed
- The shortcodes are English: `[vehicles]` and `[vehicle-logo]`. The German
  names stay registered as aliases, so no page content has to be touched.
- `[vehicles]` filters on any meta key. Attributes that are not one of
  `posts_per_page`, `orderby`, `order` and `meta_key` become a meta query, so
  `[vehicles fuel="Diesel" interior_type="Teilleder"]` works without the plugin
  knowing those fields. The five German filter attributes still map onto their
  English keys.
- Equipment is no longer a fixed list of 28 features. Every boolean the feed
  sets is taken as a feature and stored under the upper-case form of its field
  name — the naming the feed itself uses, which `alloyWheels` in the fixtures
  confirms. Curated labels stay for translation; anything unknown gets a
  readable fallback. `feature_keys` records what a vehicle actually carries.

### Fixed
- The shortcode rendered as an unstyled list on ordinary pages. The stylesheet
  was enqueued for `is_singular( 'fahrzeuge' )` and the post type archive only,
  and a page carrying the shortcode is neither. The decision now follows the
  templates: a post whose content contains the shortcode counts, and the
  shortcode enqueues the file when it runs, which also covers a widget, a page
  builder or a template part the main query knows nothing about.
- "Test the connection" never turned green on the getting-started checklist. It
  read the last sync run, and a connection test recorded nothing — so the step
  only completed once an import had gone through. The outcome is stored now.
- An import cut short by a PHP time limit left its lock behind, and every run
  for the next fifteen minutes aborted with "An import is already running". The
  lock is refreshed as the run works, so a live import holds it and a dead one
  does not, and Tools offers to release it with the age of the run stated.
- Three meta keys never matched what the templates of the earlier solution
  read: `cubicCapacity`, `roadworthy` and `seller_company_name`. They are
  written alongside the existing names.

### Added
- Vehicle class, climatisation, airbag, country version, axles, height, length
  and the parking assistants, plus the seller's ID, street, postcode, city,
  country, homepage and the date they started selling. All of it was in the
  feed and none of it was stored.
- A template hierarchy. All templates resolve through
  `WMDS_Templates::locate()`, which looks in `wp-mobile-de-sync/` in the
  stylesheet directory, then the template directory, then the theme root — the
  place an earlier solution expected `mob_vehicle-list.php`, which therefore
  keeps working — and falls back to the bundled file. The new `wmds_template`
  filter has the last word.
- `templates/parts/vehicle-card.php`. The archive and the shortcode rendered
  the same card twice, in two copies that had already begun to drift.
- The three German emission stickers, as SVG under
  `assets/emission-stickers/`, named after the colour the feed reports.
  `[emission-sticker]` renders one, `WMDS_Stickers::url()` and
  `$vehicle->emission_sticker_url()` resolve it for a template, and the bundled
  detail page shows it. Templates carried over from an earlier solution loaded
  these images out of that plugin's directory and lost them when it went.
  `emissionSticker_key` keeps the raw key beside the resolved label, because a
  label reads differently in every language.

### Upgrading
- The new fields are written at import time. One full sync fills them in:
  `wp wmds sync --full --all`.

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
