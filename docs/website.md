# Website

[← README](../README.md)

- [Pages that work out of the box](#pages-that-work-out-of-the-box)
- [Filtering](#filtering)
- [Shortcodes](#shortcodes)
- [Blocks](#blocks)
- [Templates](#templates)
- [Styling](#styling)
- [Logos and emission stickers](#logos-and-emission-stickers)
- [Structured data and social previews](#structured-data-and-social-previews)
- [FacetWP](#facetwp)

## Pages that work out of the box

| Address | Shows |
|---|---|
| `/fahrzeuge/` | every vehicle in stock, with the filter bar and pagination |
| `/fahrzeuge/audi-a4-avant/` | one vehicle: gallery, key facts, equipment, enquiry form, similar vehicles |
| `/fahrzeuge/<a vehicle that is gone>/` | the overview, filtered by its make, with a note that it is no longer available |

The address is set under *Settings → Website → Address*, or in code with the
`wmds_slug` filter. Old links are not redirected when it changes.

**Similar vehicles** at the end of a vehicle page are the same make first,
then the closest in price within 30 percent. They can be switched off under
*Settings → Website*.

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

```text
[vehicle-filter]
[vehicle-filter layout="sidebar" facets="make,price,fuel"]
[vehicles filters="yes" pagination="yes" posts_per_page="12"]
```

The bundled archive template already carries the bar, so `/fahrzeuge/` filters
without a shortcode anywhere.

Every option states how many vehicles are behind it, counted against the rest
of the selection rather than against the whole inventory — an option that
would leave nothing says so rather than promising a result it cannot keep.
Counts and slider bounds are cached for ten minutes and dropped whenever a
vehicle changes.

The bar is a plain GET form. Without JavaScript the sliders fall back to the
number fields they mirror and nothing else changes — the filters live in the
URL, so a filtered list can be linked, bookmarked, paginated and cached.

`[vehicle-count]` prints how many vehicles the current filters leave;
`[vehicle-count filtered="no"]` prints the size of the whole inventory.

## Shortcodes

```text
[vehicles posts_per_page="6" orderby="date" order="DESC"]
```

| Attribute | Does |
|---|---|
| `posts_per_page`, `orderby`, `order`, `meta_key` | steer the query |
| `columns` | 2, 3 or 4 |
| `layout` | `grid` or `list` |
| `pagination` | `yes` for page links |
| `filters` | `yes` for the filter bar above the list |
| `heading` | `h2`, `h3` or `h4` for the card titles |
| anything else | filters on the meta key of the same name |

```text
[vehicles make="Audi" fuel="Diesel" interior_type="Teilleder"]
```

An attribute that names a filter (`make`, `model`, `fuel` …) is a filter the
visitor starts with, so `[vehicles make="BMW" filters="yes"]` offers the BMW
models with the right counts.

| Shortcode | Renders |
|---|---|
| `[vehicles]` | a list or grid of vehicles |
| `[vehicle-filter]` | the filter components |
| `[vehicle-count]` | how many vehicles match |
| `[vehicle-enquiry]` | the enquiry form — see [Enquiries](enquiries.md) |
| `[vehicle-logo make="BMW" width="40"]` | a manufacturer logo |
| `[emission-sticker width="40"]` | the emission sticker of the vehicle in the loop |

## Blocks

Under **mobile.de Sync** in the block inserter. Each block renders the
shortcode it stands for, and its sidebar controls map onto the attributes.

| Block | Shortcode |
|---|---|
| Vehicles | `[vehicles]` |
| Vehicle filter | `[vehicle-filter]` |
| Vehicle count | `[vehicle-count]` |
| Vehicle enquiry | `[vehicle-enquiry]` |

## Templates

Every template is resolved through the same hierarchy, first match wins:

1. `wp-mobile-de-sync/<file>` in the child theme, then in the parent theme
2. `<file>` in the child theme, then in the parent theme
3. the bundled file under `templates/`

| File | Rendered for |
|---|---|
| `single-fahrzeuge.php` | the detail page |
| `archive-fahrzeuge.php` | the vehicle archive |
| `vehicle-list.php` | `[vehicles]` |
| `parts/vehicle-card.php` | one card, used by the last two |
| `parts/vehicle-gallery.php` | the photos on the detail page |
| `parts/filter-bar.php` | the filter components |
| `parts/enquiry-form.php` | the enquiry form |
| `parts/similar-vehicles.php` | similar vehicles at the end of the detail page |

The card is a part of its own, so a theme can restyle the grid item without
copying the loop around it. It reads `$args['heading']` (`h2` to `h4`) and
`$args['warnings']`.

A page template opens with `WMDS_Templates::header()` and closes with
`WMDS_Templates::footer()` rather than `get_header()` / `get_footer()`: in a
classic theme they are the same thing, in a block theme — which has no
`header.php` — they wrap the page in the theme's header and footer parts.
Where a classic theme expects its own content container around a page, the
two open and close it: Salient's is built in, because its revealed footer
covers anything outside it; any other comes through `wmds_theme_wrapper`.

A theme that overrides `parts/filter-bar.php` talks to `WMDS_Facets` and
nothing else.

## Styling

The bundled stylesheet loads on the vehicle archive, on a detail page and on
any post whose content renders a shortcode; the shortcode enqueues it as well
when it runs, which covers widgets and page builders.

The templates take the font and the text colour from the theme and bring
everything else: sizes, spacing, cards, controls. They undo what themes do to
content inside them — bullets on lists, oversized headings, restyled selects
(select2 included) — so they look the same in any theme. Colours and radii are
custom properties, and a handful of them restyle the lot:

```css
.wmds-scope {
	--wmds-accent: #c8102e;     /* buttons, active filters, slider */
	--wmds-on-accent: #fff;     /* text on the accent */
	--wmds-radius: 6px;         /* cards; -md and -sm for controls */
	--wmds-line: #d8d8d8;       /* borders */
	--wmds-surface: #fafafa;    /* cards and controls */
	--wmds-sticky-top: 7rem;    /* where the price box sticks below a fixed header */
}
```

A dark set of values ships with it and is opt-in: put the class `wmds-dark` on
`<body>` or on any wrapper. It deliberately does not follow
`prefers-color-scheme` — that describes the visitor's system, not the theme.

## Logos and emission stickers

32 manufacturer logos ship with the plugin, from Abarth to Volvo, output via
`[vehicle-logo]` and on every card and detail page. For a missing make, drop a
file into `wp-content/uploads/wmds-logos/` named after the make (`Tesla.png`).
Case, spaces and special characters do not matter — the lookup normalises both
sides, and `VW` finds `Volkswagen`.

The three German emission stickers ship as SVG, named after the colour the
feed reports: `assets/emission-stickers/{red,yellow,green}.svg`. Group 1
carries no sticker and resolves to nothing. Inside the loop the shortcode reads
`emissionSticker_key` from the vehicle; pass `sticker="EMISSIONSSTICKER_GREEN"`
to force one. `WMDS_Stickers::url()` and `$vehicle->emission_sticker_url()`
return the file for a template.

The consumption disclaimer on the detail page is prescribed verbatim by the
Pkw-EnVKV, so the German catalogue carries the statutory wording rather than a
translation of the English source.

## Structured data and social previews

A vehicle page carries a complete `Car` node in JSON-LD: make as a `Brand`,
model, body type, colour, fuel, transmission, doors, seats, previous owners,
first registration as a date, CO₂, and engine power and displacement as
quantities with their UN/CEFACT unit codes — `KWT`, `CMQ`, `KMT` — so a number
is read as a number rather than as text. The price, the currency, the
availability and the dealer sit in an `Offer`. The archive carries an
`ItemList` of the vehicles on the page.

**What the feed does not state is left out.** No brand is invented for a
vehicle without a make, no offer is claimed for a vehicle without a price, and
an engine power of zero is not an engine specification.

Open Graph and Twitter cards go out alongside, so a link shared anywhere shows
the photo, the title and the price. They are **suppressed automatically** when
Yoast, Rank Math, SEOPress, AIOSEO or The SEO Framework is active: two sets of
`og:` tags on one page are worse than none. The JSON-LD stays either way,
since those plugins do not describe a vehicle.

## FacetWP

The plugin filters on its own and needs FacetWP for nothing. A site that was
built on FacetWP keeps working all the same:

- the meta keys appear in the facet source list under **mobile.de** with
  readable labels, instead of being typed as `cf/price_raw` from memory
- the sort orders the filter bar offers are offered to a FacetWP template too
- a vehicle is re-indexed once the import has finished writing it

The last one is a fix, not a convenience. FacetWP indexes when a post is
saved; the import saves the post first and writes the meta afterwards, so what
FacetWP indexed on its own was the state *before* the update. It can be
switched off under *Embedding* where the index is maintained elsewhere.
