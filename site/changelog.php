<?php
$wmds_active = 'changelog';
require __DIR__ . '/parts/docs-nav.php';
?>
<div class="docs">
	<?php echo $wmds_docs_nav; ?>

	<article class="doc">
		<header class="doc__head">
			<h1>Changelog</h1>
			<div class="doc__lede"><?php echo $intro; ?></div>
		</header>

		<?php foreach ( $entries as $wmds_entry ) : ?>
			<section class="version" id="<?php echo $site->e( $wmds_entry['id'] ); ?>" aria-labelledby="<?php echo $site->e( $wmds_entry['id'] ); ?>-title">
				<header class="version__head">
					<h2 id="<?php echo $site->e( $wmds_entry['id'] ); ?>-title">
						<a href="#<?php echo $site->e( $wmds_entry['id'] ); ?>"><?php echo $site->e( $wmds_entry['version'] ); ?></a>
					</h2>
					<?php if ( '' !== $wmds_entry['date'] ) : ?>
						<time datetime="<?php echo $site->e( $wmds_entry['date'] ); ?>"><?php echo $site->e( $site->date( $wmds_entry['date'] ) ); ?></time>
					<?php endif; ?>
					<a class="version__release" href="<?php echo $site->e( $wmds_entry['url'] ); ?>">Release on GitHub</a>
				</header>
				<div class="prose prose--changelog">
					<?php echo $wmds_entry['html']; ?>
				</div>
			</section>
		<?php endforeach; ?>

		<footer class="doc__foot">
			<a class="edit" href="<?php echo $site->e( $edit ); ?>">Edit this page on GitHub</a>
		</footer>
	</article>

	<aside class="toc" aria-labelledby="toc-title">
		<p class="toc__title" id="toc-title">Releases</p>
		<ul>
			<?php foreach ( $entries as $wmds_entry ) : ?>
				<li class="toc__l2"><a href="#<?php echo $site->e( $wmds_entry['id'] ); ?>"><?php echo $site->e( $wmds_entry['version'] ); ?> <small><?php echo $site->e( $wmds_entry['date'] ); ?></small></a></li>
			<?php endforeach; ?>
		</ul>
	</aside>
</div>
