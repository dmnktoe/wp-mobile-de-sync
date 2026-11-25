# Hooks

[← README](../README.md)

Every filter and action the plugin offers. All names start with `wmds_`.

## Filters

| Filter | Arguments | Use it to |
|---|---|---|
| `wmds_slug` | `string $slug` | change the address of the archive and the vehicles (default `fahrzeuge`) |
| `wmds_template` | `string $path, string $file` | point a template at a different file |
| `wmds_theme_wrapper` | `array( $open, $close )` | wrap a page in the content container a classic theme expects |
| `wmds_enqueue_styles` | `bool $enqueue` | switch off the bundled stylesheet |
| `wmds_theme_style` | `array $tokens` | set or change what is taken from the theme: `accent`, `on_accent`, `radius`, `page` |
| `wmds_archive_per_page` | `int $per_page` | vehicles per archive page (default 12) |
| `wmds_facets` | `array $facets` | add, remove or reorder the filter components |
| `wmds_facet_sorts` | `array $sorts` | change the sort orders on offer |
| `wmds_logo_url` | `string $url, string $make` | point a make at a different logo |
| `wmds_emission_sticker_url` | `string $url, string $sticker` | point a sticker at a different file |
| `wmds_jsonld` | `array $schema, array $data` | change the structured data before it is printed |
| `wmds_social_meta` | `array $meta, array $data` | change the `og:` and `twitter:` tags |
| `wmds_seo_output` | `bool $default, string $what` | switch `jsonld` or `social` output on or off |
| `wmds_enquiry_recipients` | `string[] $to` | decide where an enquiry is sent |
| `wmds_alert_recipients` | `string[] $to` | decide who is told when the sync stops |
| `wmds_client_ip` | `string $ip` | read the visitor's address from a proxy header, for the enquiry rate limit |
| `wmds_spam_words` | `string[] $words` | the words that mark an enquiry as spam |
| `wmds_enquiry_spam` | `string $reason, array $data` | decide whether an enquiry is spam: a reason for yes, `''` for no |
| `wmds_force_update_check` | `bool $forced` | make the next update check skip its cache |

## Actions

| Action | Arguments | Fires |
|---|---|---|
| `wmds_run_finished` | `array $record` | when a run has ended and the lock is released — completed, stopped early or aborted (`error` is set then) |
| `wmds_vehicle_stored` | `int $post_id, string $ad_id, string $mode` | after one vehicle is completely written — post, meta and images, which `save_post` cannot promise; `$mode` is `created` or `updated` |
| `wmds_mail_sent` | `string\|string[] $to, string $subject` | after the plugin handed a message to `wp_mail()` and it was accepted |

## Examples

Show the price box of a vehicle page below a 100px fixed header, and use the
dealer's brand colour:

```css
.wmds-scope {
	--wmds-accent: #c8102e;
	--wmds-sticky-top: 7rem;
}
```

Send enquiries about vans to a second address:

```php
add_filter( 'wmds_enquiry_recipients', function ( $to ) {
	$vehicle = isset( $_POST['wmds_vehicle'] ) ? (int) $_POST['wmds_vehicle'] : 0;

	if ( $vehicle && 'Van' === get_post_meta( $vehicle, 'class_key', true ) ) {
		$to[] = 'transporter@example.com';
	}

	return $to;
} );
```

Post to a chat whenever a run ends with an error:

```php
add_action( 'wmds_run_finished', function ( $record ) {
	if ( ! empty( $record['error'] ) ) {
		wp_remote_post( 'https://chat.example.com/hook', array(
			'body' => array( 'text' => 'Vehicle sync failed: ' . $record['error'] ),
		) );
	}
} );
```
