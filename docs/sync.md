# Sync

[← README](../README.md)

- [What a run does](#what-a-run-does)
- [Scheduling](#scheduling)
- [Removal and its guards](#removal-and-its-guards)
- [Error handling](#error-handling)
- [When it stops](#when-it-stops)
- [WP-CLI](#wp-cli)
- [Data contract](#data-contract)
- [Known limits](#known-limits)

## What a run does

**A regular run fetches only what changed** since the last one — mobile.de
sorts the listing by modification time, and the run asks for everything newer
than the last ad it saw (the *change marker*).

Then it asks mobile.de, in one small request, how many vehicles the whole
inventory holds. When the site would not hold the same number after the pass,
the pass reads the whole listing instead and removes what is gone, so a vehicle
taken off mobile.de leaves the site with the next scheduled run. Once a day a
full reconciliation runs in addition.

Each changed listing is fetched in full (the listing carries only part of an
ad), mapped onto post meta and written. Pictures are downloaded once and kept
by the hash mobile.de gives them, so an ad that gained one photo costs one
download, not fifteen. At most 15 pictures are kept per vehicle.

Large inventories are processed in **batches of 20 vehicles**. A pass that
leaves vehicles behind books a follow-up a minute later, until nothing is
pending. Labels are written in the site's language, whatever language the
admin who pressed the button uses.

## Scheduling

WP-Cron is triggered by page views, not by a clock. On a low-traffic site
"every 15 minutes" effectively means "eventually". A real cron job is
recommended:

```php
// wp-config.php
define( 'DISABLE_WP_CRON', true );
```

```cron
*/15 * * * * cd /path/to/site && wp cron event run --due-now
```

The interval is set under *Settings → Synchronisation*: every 5, 15 (the
default), 30 or 60 minutes, or once a day. A run is overdue when it is older
than two intervals, and never sooner than six hours.

Events the plugin relies on are checked in wp-admin and in every cron request,
and put back when WordPress has dropped them — which it does with a recurring
event that comes due in a request that does not know its interval, during a
plugin update for instance.

## Removal and its guards

Removal happens as soon as a full listing has been read, before the first
batch is written. Three independent guards apply:

1. Nothing is removed unless the whole listing was read: not by a
   changes-only run, and not by a full one whose listing was cut short by the
   execution time limit or the pagination cap.
2. A run that saw no vehicle at all removes nothing — this happens with a
   wrong seller ID, where the API answers with an empty result rather than an
   error.
3. If more than 30 percent of the inventory would disappear, the run aborts
   with a reason. Below that sits an absolute floor of three vehicles, so
   small inventories are not permanently blocked.

Removed vehicles go to the **trash**, not into the void; their pictures are
deleted from the media library at the same time. A vehicle that comes back
into the feed is restored under its old address and downloads its pictures
again. Pictures left behind by vehicles removed before 2.5.0 can be deleted
under *Tools & system → Maintenance*.

The address of a removed vehicle does not end in "page not found": it leads to
the vehicle overview, filtered by the make that was asked for while there is
still one in stock, with a note that the vehicle is no longer available.

## Error handling

Every API response is validated before it is used. An outage, a rate limit or
an error message leads to a clean abort with a log entry, never to a fatal
error. An error response is never parsed as if it were a result list — that
case is covered by a test.

A run holds a lock, so two runs never write the same vehicles at once. A run
cut short by a PHP fatal error releases it and books a follow-up; a lock left
behind by a run that was killed outright can be released under *Tools &
system*.

A vehicle whose detail call or pictures fail keeps the change marker where it
was, so the next run lists it again. A picture that will not download is
retried for three passes and then let go, so one dead link does not keep a
vehicle in the queue forever.

## When it stops

A sync that stops is invisible until somebody opens the settings screen.
*Settings → Notifications about problems* turns that around: a failed or
overdue run arrives by e-mail.

It is deliberately quiet. The same problem is reported once and then not again
until a cooldown has passed — an hour, six hours, twelve, or a day — because a
sync failing every fifteen minutes should produce one mail, not ninety-six.
A *different* problem is sent straight away, and so is the recovery. "Not
configured" is never mailed about.

The optional weekly summary goes out even when nothing is wrong, which makes
silence evidence rather than an assumption. *Enquiries → Send a test mail*
checks that mail leaves the site at all.

## WP-CLI

```text
wp wmds sync --full --all      full sync until nothing is pending
wp wmds sync                   one pass, changes only
wp wmds status                 schedule, last run, log
wp wmds test                   check the connection without writing
wp wmds flush-cache            re-fetch reference data
```

The command line has no execution time limit, which makes it the right tool
for the first import of a large inventory.

## Data contract

Vehicle data lands in post meta under stable field names. That is the
interface for templates, facets and custom queries, and it does not change
within a major version:

- post type `fahrzeuge`; archive and vehicles under `/fahrzeuge/` (changeable)
- one meta key per vehicle property — `make`, `model`, `price_raw`, `mileage`,
  `firstRegistration` … — plus every equipment feature the feed sets
- `mobileAdId` links a post to its ad
- the same keys are FacetWP sources (`cf/make`, `cf/price_raw`, …) for a site
  that already uses it

Labels are resolved **at import time** and stored in post meta. Changing the
label language therefore takes effect after the next full sync.

## Known limits

- Across paginated pages the API hands out at most 2000 vehicles. Larger
  inventories need the fetch split up; the run logs when the ceiling is hit
  and removes nothing.
- The seller ID is the only route mobile.de documents for addressing an
  inventory. The dealer name from `home.mobile.de/NAME` works too, but that
  parameter is undocumented and may disappear.
- `generalInspection` is optional, and dealers who sell with a fresh
  inspection do not maintain it — they set `newHuAu` instead. Both are mapped,
  with `newHuAu` as the fallback.
