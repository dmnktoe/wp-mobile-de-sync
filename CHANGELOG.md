# Changelog

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [Semantic Versioning](https://semver.org/).

## [1.0.0] – 2026-07-30

First release.

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
