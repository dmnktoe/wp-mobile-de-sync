<?php
$wmds_title = $page['title'];
$wmds_image = $site->image();
$wmds_docs  = array();

foreach ( $docs as $wmds_doc ) {
	$wmds_docs[] = $wmds_doc['slug'];
}

$wmds_in_docs = in_array( $page['active'], $wmds_docs, true );
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ( '404.html' === $page['file'] ) : ?>
<base href="<?php echo $site->e( $site->site_url() ); ?>">
<?php endif; ?>
<title><?php echo $site->e( $wmds_title ); ?></title>
<meta name="description" content="<?php echo $site->e( $page['description'] ); ?>">
<?php if ( $page['noindex'] ) : ?>
<meta name="robots" content="noindex">
<?php else : ?>
<link rel="canonical" href="<?php echo $site->e( $page['url'] ); ?>">
<?php endif; ?>
<meta name="theme-color" content="#380050">
<meta name="color-scheme" content="light dark">
<link rel="icon" href="icon.svg" type="image/svg+xml">
<link rel="sitemap" type="application/xml" href="sitemap.xml">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?php echo $site->e( $meta['name'] ); ?>">
<meta property="og:title" content="<?php echo $site->e( $wmds_title ); ?>">
<meta property="og:description" content="<?php echo $site->e( $page['description'] ); ?>">
<meta property="og:url" content="<?php echo $site->e( $page['url'] ); ?>">
<?php if ( $wmds_image ) : ?>
<meta property="og:image" content="<?php echo $site->e( $site->site_url() . $wmds_image['src'] ); ?>">
<meta property="og:image:width" content="<?php echo (int) $wmds_image['width']; ?>">
<meta property="og:image:height" content="<?php echo (int) $wmds_image['height']; ?>">
<meta property="og:image:alt" content="<?php echo $site->e( $wmds_image['alt'] ); ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">

<script>
	try {
		var wmdsTheme = localStorage.getItem( 'wmds-theme' );
		if ( wmdsTheme === 'light' || wmdsTheme === 'dark' ) {
			document.documentElement.setAttribute( 'data-theme', wmdsTheme );
		}
	} catch ( e ) {}
</script>
<link rel="stylesheet" href="<?php echo $site->e( $site->asset( 'style.css' ) ); ?>">
<script src="<?php echo $site->e( $site->asset( 'site.js' ) ); ?>" defer></script>
<?php if ( $page['jsonld'] ) : ?>
<script type="application/ld+json"><?php echo json_encode( array_filter( $page['jsonld'] ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ); ?></script>
<?php endif; ?>
</head>
<body class="page-<?php echo $site->e( preg_replace( '/\.html$/', '', $page['file'] ) ); ?>">

<a class="skip" href="#main">Skip to content</a>

<header class="topbar">
	<div class="topbar__inner">
		<a class="brand" href="./" aria-label="<?php echo $site->e( $meta['name'] ); ?> — home">
			<img src="icon.svg" alt="" width="30" height="30">
			<span>mobile.de <b>Sync</b></span>
		</a>

		<nav class="topnav" aria-label="Main">
			<a href="<?php echo $site->e( $docs[0]['slug'] ); ?>.html"<?php echo $wmds_in_docs ? ' aria-current="page"' : ''; ?>>Docs</a>
			<a href="changelog.html"<?php echo 'changelog' === $page['active'] ? ' aria-current="page"' : ''; ?>>Changelog</a>
			<a class="topnav__github" href="<?php echo $site->e( $site->repo_url() ); ?>">
				<svg viewBox="0 0 16 16" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
				<span>GitHub</span>
			</a>
		</nav>

		<button class="theme-toggle" type="button" aria-label="Switch between light and dark">
			<svg class="theme-toggle__moon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
			<svg class="theme-toggle__sun" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 1.5v2.5M12 20v2.5M4.6 4.6l1.8 1.8M17.6 17.6l1.8 1.8M1.5 12H4M20 12h2.5M4.6 19.4l1.8-1.8M17.6 6.4l1.8-1.8"/></g></svg>
		</button>
	</div>
</header>

<main id="main">
<?php echo $page['body']; ?>
</main>

<footer class="footer">
	<div class="wrap footer__grid">
		<div class="footer__about">
			<a class="brand brand--footer" href="./">
				<img src="icon.svg" alt="" width="26" height="26">
				<span>mobile.de <b>Sync</b></span>
			</a>
			<p>
				Version <?php echo $site->e( $meta['version'] ); ?> ·
				<?php echo $site->e( $meta['license'] ); ?> ·
				made by <a href="<?php echo $site->e( $meta['author_url'] ); ?>"><?php echo $site->e( $meta['author'] ); ?></a>
			</p>
			<p class="footer__note">Not affiliated with mobile.de. Access to the Search API is arranged with mobile.de directly.</p>
		</div>

		<nav class="footer__col" aria-label="Documentation">
			<p class="footer__title">Documentation</p>
			<ul>
				<?php foreach ( $docs as $wmds_doc ) : ?>
					<li><a href="<?php echo $site->e( $wmds_doc['slug'] ); ?>.html"><?php echo $site->e( $wmds_doc['title'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<nav class="footer__col" aria-label="Project">
			<p class="footer__title">Project</p>
			<ul>
				<li><a href="<?php echo $site->e( $site->download_url() ); ?>">Download</a></li>
				<li><a href="changelog.html">Changelog</a></li>
				<li><a href="<?php echo $site->e( $site->repo_url() ); ?>/releases">Releases</a></li>
				<li><a href="<?php echo $site->e( $site->repo_url() ); ?>/issues">Issues</a></li>
				<li><a href="<?php echo $site->e( $site->repo_url() ); ?>">Source code</a></li>
				<li><a href="https://services.mobile.de/manual/search-api.html">mobile.de Search API</a></li>
			</ul>
		</nav>
	</div>
</footer>

</body>
</html>
