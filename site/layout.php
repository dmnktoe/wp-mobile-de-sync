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
		<a class="brand" href="./">
			<img src="icon.svg" alt="" width="24" height="24">
			<?php echo $site->e( $meta['name'] ); ?>
		</a>

		<nav class="topnav" aria-label="Main">
			<a href="<?php echo $site->e( $docs[0]['slug'] ); ?>.html"<?php echo $wmds_in_docs ? ' aria-current="page"' : ''; ?>>Documentation</a>
			<a href="changelog.html"<?php echo 'changelog' === $page['active'] ? ' aria-current="page"' : ''; ?>>Changelog</a>
			<a href="<?php echo $site->e( $site->repo_url() ); ?>">GitHub</a>
		</nav>
	</div>
</header>

<main id="main">
<?php echo $page['body']; ?>
</main>

<footer class="footer">
	<div class="wrap">
		<p>
			<?php echo $site->e( $meta['name'] ); ?> <?php echo $site->e( $meta['version'] ); ?>
			by <a href="<?php echo $site->e( $meta['author_url'] ); ?>"><?php echo $site->e( $meta['author'] ); ?></a>,
			<?php echo $site->e( $meta['license'] ); ?>.
			Not affiliated with mobile.de.
		</p>
		<p>
			<a href="<?php echo $site->e( $site->repo_url() ); ?>">Source</a> ·
			<a href="<?php echo $site->e( $site->repo_url() ); ?>/releases">Releases</a> ·
			<a href="<?php echo $site->e( $site->repo_url() ); ?>/issues">Issues</a> ·
			<a href="https://services.mobile.de/manual/search-api.html">mobile.de Search API</a>
		</p>
	</div>
</footer>

</body>
</html>
