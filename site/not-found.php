<?php
/**
 * The page GitHub Pages serves for an address that does not exist.
 *
 * @package wp-mobile-de-sync
 */

?>
<section class="section not-found">
	<div class="wrap narrow">
		<p class="eyebrow">404</p>
		<h1>This page does not exist</h1>
		<p>It may have moved when the documentation was reorganised.</p>
		<div class="actions">
			<a class="btn btn--primary" href="./">Go to the start page</a>
			<a class="btn btn--ghost" href="<?php echo $site->e( $docs[0]['slug'] ); ?>.html">Read the docs</a>
		</div>
	</div>
</section>
