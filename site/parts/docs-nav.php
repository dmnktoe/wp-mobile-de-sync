<?php
ob_start();
?>
<nav class="docs-nav" aria-label="Documentation">
	<p class="docs-nav__title">Documentation</p>
	<ul>
		<?php foreach ( $docs as $wmds_entry ) : ?>
			<li><a href="<?php echo $site->e( $wmds_entry['slug'] ); ?>.html"<?php echo $wmds_entry['slug'] === $wmds_active ? ' aria-current="page"' : ''; ?>><?php echo $site->e( $wmds_entry['title'] ); ?></a></li>
		<?php endforeach; ?>
		<li class="docs-nav__sep"><a href="changelog.html"<?php echo 'changelog' === $wmds_active ? ' aria-current="page"' : ''; ?>>Changelog</a></li>
	</ul>
</nav>
<?php
$wmds_docs_nav = ob_get_clean();
