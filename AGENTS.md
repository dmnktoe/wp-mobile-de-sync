# Coding Agent Guidelines

Rules, domain decisions and cross-file gotchas for this repo. Mirror existing conventions; don't invent new patterns without a strong reason. State each rule once and edit the existing entry instead of appending. How a single file works doesn't belong here, and neither does it belong in a code comment: the code carries no explanatory comments (see Code conventions).

## Product intent (mobile.de Sync)

A WordPress plugin that keeps a car dealer's inventory from the **mobile.de Search API** ([manual](https://services.mobile.de/manual/search-api.html)) in WordPress as ordinary posts of the custom post type `fahrzeuge`, and brings what a dealer site needs around it: vehicle overview with filters, vehicle page, enquiry form, blocks and shortcodes, structured data. It works with any theme, classic or block. Not affiliated with mobile.de; Search API access is arranged with mobile.de per dealer.

Who it serves: the dealer's site visitors (browse, filter, enquire), the site owner (status, enquiries, settings) and the developer building the theme (templates, hooks, meta keys). User-facing documentation lives in `docs/*.md`; the project site is built from it.

### Sync invariants

- **mobile.de is the source of truth.** Vehicles are never edited in WordPress: the importer owns every `fahrzeuge` post and overwrites it on the next change.
- **A regular run reads only what changed** since the change marker (`wmds_watermark`), then compares the inventory size with mobile.de in one request; when the site would not hold the same number, the pass reads the full listing instead. Once a day a full reconciliation runs anyway.
- **Removal needs a complete full listing**, never a changes-only one, and three guards: nothing is removed from a listing cut short (time limit, 2000-vehicle pagination cap), from a run that saw no vehicle (a wrong seller ID answers with an empty result, not an error), or when more than 30 % of the inventory would go (`WMDS_Sync_Plan::REMOVAL_RATIO`, floor of three vehicles). Removed vehicles go to the trash, their pictures are deleted at once; a vehicle that comes back is restored under its old address.
- **The change marker only moves past a vehicle that was written completely**: a failed detail call or picture keeps it, so the next run lists that vehicle again. A picture that will not download is retried for three passes, then let go.
- **Large inventories run in batches of 20 across passes.** Follow-up passes are booked on their own hook (`wmds_import_followup`), never the regular one: WordPress refuses a single event within ten minutes of an identical one. "Full" and "forced" are stored state (no marker means full, no stored modification date means re-read), because later passes start without arguments.
- **One run at a time**: a lock, released only by the request that took it; a fatal error releases it and books a follow-up; a lock from a killed run is released under Tools & system.
- **Every API response is validated** before use; an outage, rate limit or error message aborts cleanly with a log entry and is recorded as a run. An error response is never parsed as a result list.
- **Labels are resolved at import time**, in the site's language (the run switches locale), never the language of the admin who pressed the button. Changing the label language takes effect with the next full sync.
- **The data contract is stable within a major version**: post type `fahrzeuge`, one meta key per property (`make`, `model`, `price_raw`, `mileage`, `firstRegistration`, …, plus every equipment feature), `mobileAdId` linking a post to its ad, and the FacetWP sources `cf/<key>`. Renaming or removing one is a major release.
- **Inventory is addressed by seller ID** (the only documented route); the dealer name from `home.mobile.de/NAME` is accepted but undocumented. Never both.

### Website

- **Pages work without setup**: `/fahrzeuge/` (archive with filters), `/fahrzeuge/<slug>/` (vehicle page). The address of a removed vehicle leads to the archive filtered by its make with a note, never to a 404.
- **Templates** resolve through `WMDS_Templates` (child theme `wp-mobile-de-sync/<file>`, then `<file>`, then `templates/`). Page templates open and close with `WMDS_Templates::header()` / `footer()`, never `get_header()` / `get_footer()`: block themes have no `header.php`. A theme's content wrapper comes through `wmds_theme_wrapper`.
- **Filtering is the plugin's own** (`WMDS_Facets` and `WMDS_Facet_*`); FacetWP is optional and only gets meta keys, sort orders and a re-index after a vehicle is written. The filter bar is a plain GET form and works without JavaScript; filters live in the URL. Counts are against the rest of the selection, cached ten minutes and dropped when a vehicle changes.
- **Numbers** have two readers and no general parser: `WMDS_Num::from_input()` for what a person types (`12.500` = twelve and a half thousand), `WMDS_Num::from_feed()` for what the API sends (`12.500` = twelve and a half).
- **Styling** is scoped under `.wmds-scope` with custom properties (`--wmds-accent`, `--wmds-radius`, …); the templates undo what themes do to lists, headings and selects. The dark set is opt-in (`wmds-dark`) and never follows `prefers-color-scheme`: that describes the visitor's system, not the theme.
- **Structured data**: a full `Car` + `Offer` in JSON-LD with UN/CEFACT unit codes; what the feed doesn't state is left out (no invented brand, no offer without a price). Open Graph and Twitter tags are suppressed when an SEO plugin (Yoast, Rank Math, SEOPress, AIOSEO, The SEO Framework) is active; the JSON-LD stays.
- **Logos and emission stickers**: logos in `assets/logos/<Make>.png`, overridable from `wp-content/uploads/wmds-logos/`; the lookup normalises case, spaces and special characters (`VW` finds `Volkswagen`). The consumption disclaimer on the vehicle page is prescribed verbatim by the Pkw-EnVKV, so the German catalogue carries the statutory wording, not a translation.

### Enquiries and alerts

- **Every enquiry is stored as well as mailed** (`WMDS_Leads`), with what became of its mail; it is "new" until marked answered. Mail is the part that fails.
- **Spam guards a person never notices**: honeypot, minimum fill time, five enquiries per visitor per hour, and a proof of work in the ALTCHA format (`WMDS_Challenge`): signed puzzle fetched through `admin-post.php` because vehicle pages are cached, each answer accepted once; no captcha and no external service. What gets through is judged by `WMDS_Spam` (spam words, links, blocklist): stored as status `spam` with its reason, mailed to nobody, deleted after 30 days; marking an enquiry spam teaches the blocklist (domain for a company, address for shared providers). Nonces only for logged-in users, because vehicle pages are cached. Reply-To is the enquirer and can never carry a second header. The seller's copy goes only to the mail for the dealer, never to the visitor's confirmation. `wmds_enquiry_recipients` has the last word.
- **Contact Form 7**: mail tags read the vehicle from the page the form was sent from; only submissions from a vehicle page are stored.
- **Alerts** (`WMDS_Alerts`) report a failed or overdue sync once per cooldown; a different problem and the recovery go out at once; "not configured" is never mailed. Every surface (admin bar, dashboard widget, notice, Overview tab) reads one health verdict from `WMDS_Status`.

---

## Architecture

**Stack:** PHP ≥ 7.0, WordPress ≥ 5.8, no runtime dependencies. Composer only for dev tooling (coding standards, project site), never shipped.

```
wp-mobile-de-sync.php   Plugin header and constants; calls WMDS_Plugin::boot()
includes/               One class per file, loaded on first use
  tabs/                 One admin screen (settings tab) per class
  integrations/         Code that talks to another plugin (CF7, FacetWP, SMTP plugins)
templates/              Front-end templates, overridable from the theme
assets/                 CSS, JS, make logos, emission stickers
languages/              German catalogue (.po + .mo)
tests/                  Unit tests (plain PHP), integration test, fixtures
bin/                    bump.sh, po2mo.php, build-site.php
docs/                   User documentation and screenshots (source of the project site)
site/                   Templates of the project site
```

- **Autoloading** maps a class name to its file (`WMDS_Facet_Store` → `includes/class-wmds-facet-store.php`, `WMDS_Tab_Tools` → `includes/tabs/…`); adding a class means adding a file. A file that does something when it loads (registers a shortcode or a WP-CLI command: logos, stickers, CLI) is required outright, never autoloaded; `tests/test-boot.php` and `tests/test-autoloader.php` enforce it.
- **Integrations** load on `plugins_loaded`, check that the other plugin is there before hooking anything, and leave the site working without it.
- **Settings** are described once in `WMDS_Settings::fields()` (type, default, sanitising). A tab is one form and names its fields; saving a tab touches only them. A new setting is a line in `fields()` and a field in a tab.
- **Admin actions** go through `admin-post.php` and leave through a redirect, so a reload never repeats them. Only what is offered as a link may arrive as one.
- **Updates** come from the GitHub releases of this repo (`WMDS_Updater`, `Update URI` in the header), not from wordpress.org.
- **Hooks** are public API: every filter and action is listed in `docs/hooks.md`; a new one goes there in the same change.

---

## Code conventions

- **PHP 7.0 is the floor** for syntax and functions (PHPCompatibility, CI lints 7.0, 7.4 and 8.3). WordPress 5.8 is the floor for APIs.
- **WordPress Coding Standards** (`phpcs.xml`), long array syntax, prefix `wmds_` / `WMDS_`, text domain `wp-mobile-de-sync`. Every exclusion in `phpcs.xml` states why it exists; a new one does too.
- **No explanatory comments in code.** No comment says what code does or why; rationale goes into `docs/` or `CHANGELOG.md`. What stays: `phpcs:` annotations (with their reason after `--`), `translators:` comments, the plugin headers WordPress reads, and the type part of `@param` / `@return` / `@var` tags.
- **English is the source language** for code, strings, docs and commit messages; German exists only in `languages/` (`tests/no-german.sh` checks the tree).
- **No credentials or customer data in the repository.** Fixtures are anonymised but structurally real API responses; e-mail addresses in them use `example.invalid`, `example.com` or `example.org`. A CI job checks both; its patterns stay generic, because a filter that names actual customers publishes them.

---

## Internationalization

- Source strings are English; `languages/wp-mobile-de-sync-de_DE.po` is the German catalogue. After editing it, rebuild the binary with `php bin/po2mo.php languages/wp-mobile-de-sync-de_DE.po` (no `msgfmt` needed).
- A string with placeholders carries a `translators:` comment.
- Vehicle labels (fuel, transmission, colours …) come from mobile.de's reference data in the configured language, not from the catalogue.

---

## Tooling & testing

- `sh tests/run.sh` runs every test without WordPress, PHPUnit or Composer: `tests/wp-fake.php` and `tests/wp-admin-fake.php` stand in for WordPress, `tests/test-admin.php` drives every tab and action through the real entry points. A behaviour change gets its test in the matching `tests/test-*.php`.
- `sh tests/integration/run.sh` installs a real WordPress (MySQL, WP-CLI) against a mock mobile.de server and fails on any PHP notice, under a block and a classic theme.
- `composer install && composer lint` runs phpcs; the tooling needs PHP 8.2+.
- `php bin/build-site.php` builds the project site into `build/site`; a link to a missing file, page or section fails it. Links in `README.md` and `docs/*.md` stay relative file links (`sync.md#scheduling`), so they work on GitHub and on the site.
- **Dependencies**: Renovate (`renovate.json`) keeps Composer dev tooling and GitHub Actions current: Mondays, no dependency dashboard, coding-standards packages in one PR, actions pinned to digests, minor and patch updates merge themselves when CI passes. PHP_CodeSniffer stays on 3 while WPCS 3 requires it; the MySQL version of the integration test (an LTS line) is changed by hand.

---

## Docs, commits & changelog

- User-facing behaviour changes update `docs/*.md` and, where it is a highlight, `README.md` in the same change.
- **PR titles follow Conventional Commits**: `type(scope): summary`, types `feat`, `fix`, `perf`, `refactor`, `docs`, `test`, `ci`, `build`, `chore`; scopes `sync`, `website`, `enquiries`, `admin`, `i18n`, `site`, `deps`. Each PR gets labels matching its type and area.
- **`CHANGELOG.md`** follows Keep a Changelog, written for site owners (what changed for them, not which class moved); `readme.txt` carries the same entry in WordPress format.

## Releases & deploys

- **The version is stated in three places** (plugin header `Version`, `WMDS_VERSION`, `Stable tag` in `readme.txt`): set them with `sh bin/bump.sh X.Y.Z`, then write the entries in `CHANGELOG.md` and `readme.txt`. `tests/test-version.php` fails a half-finished bump.
- **Merging a new version to `main` is the release**: the Release workflow runs the tests, builds the ZIP, tags `vX.Y.Z` and publishes the GitHub release; sites pick it up through the built-in updater. A version that is already tagged publishes nothing.
- **The ZIP's top-level folder must be `wp-mobile-de-sync`**, or WordPress unpacks an update beside the installed plugin and deactivates it; GitHub's source download is unusable for that reason. Dev-only files are excluded in `.github/workflows/release.yml`; a new one goes on that list.
- **The project site** (GitHub Pages, source "GitHub Actions") deploys after every successful Release run on `main`, so the version it names is published. Its checkout needs the full history (`fetch-depth: 0`): the sitemap dates each page by its last commit.
