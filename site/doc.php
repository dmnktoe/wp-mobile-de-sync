<?php
/**
 * One documentation page from docs/.
 *
 * @package wp-mobile-de-sync
 */

$wmds_prev = null;
$wmds_next = null;

foreach ( $docs as $wmds_index => $wmds_entry ) {
	if ( $wmds_entry['slug'] === $doc['slug'] ) {
		$wmds_prev = $wmds_index > 0 ? $docs[ $wmds_index - 1 ] : null;
		$wmds_next = isset( $docs[ $wmds_index + 1 ] ) ? $docs[ $wmds_index + 1 ] : null;
	}
}

$wmds_active = $doc['slug'];
require __DIR__ . '/parts/docs-nav.php';
?>
<div class="docs">
	<?php echo $wmds_docs_nav; ?>

	<article class="doc">
		<header class="doc__head">
			<p class="eyebrow">Documentation</p>
			<h1><?php echo $site->e( $doc['title'] ); ?></h1>
			<?php if ( '' !== $summary ) : ?>
				<p class="doc__lede"><?php echo $site->e( $summary ); ?></p>
			<?php endif; ?>
		</header>

		<div class="prose">
			<?php echo $html; ?>
		</div>

		<footer class="doc__foot">
			<nav class="pager" aria-label="More documentation">
				<?php if ( $wmds_prev ) : ?>
					<a class="pager__link" href="<?php echo $site->e( $wmds_prev['slug'] ); ?>.html" rel="prev">
						<span>Previous</span>
						<strong>← <?php echo $site->e( $wmds_prev['title'] ); ?></strong>
					</a>
				<?php endif; ?>
				<?php if ( $wmds_next ) : ?>
					<a class="pager__link pager__link--next" href="<?php echo $site->e( $wmds_next['slug'] ); ?>.html" rel="next">
						<span>Next</span>
						<strong><?php echo $site->e( $wmds_next['title'] ); ?> →</strong>
					</a>
				<?php endif; ?>
			</nav>
			<a class="edit" href="<?php echo $site->e( $edit ); ?>">Edit this page on GitHub</a>
		</footer>
	</article>

	<?php if ( count( $toc ) > 1 ) : ?>
		<aside class="toc" aria-labelledby="toc-title">
			<p class="toc__title" id="toc-title">On this page</p>
			<ul>
				<?php foreach ( $toc as $wmds_item ) : ?>
					<li class="toc__l<?php echo (int) $wmds_item['level']; ?>"><a href="#<?php echo $site->e( $wmds_item['id'] ); ?>"><?php echo $site->e( $wmds_item['text'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</aside>
	<?php endif; ?>
</div>
