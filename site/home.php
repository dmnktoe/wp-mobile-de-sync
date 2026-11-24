<?php
$wmds_latest = $releases ? $releases[0] : null;
?>
<div class="wrap">

<header class="intro">
	<h1><?php echo $site->e( $meta['name'] ); ?></h1>
	<p class="intro__tagline"><?php echo $site->md( $tagline ); ?></p>

	<div class="intro__text">
		<?php echo $lead; ?>
	</div>

	<p class="intro__get">
		<a class="button" href="<?php echo $site->e( $site->download_url() ); ?>">Download <?php echo $site->e( $meta['version'] ); ?></a>
		<span class="intro__meta">
			<?php if ( $wmds_latest && '' !== $wmds_latest['date'] ) : ?>
				<a href="changelog.html#v<?php echo $site->e( $wmds_latest['version'] ); ?>">Released <?php echo $site->e( $site->date( $wmds_latest['date'] ) ); ?></a>
				·
			<?php endif; ?>
			WordPress <?php echo $site->e( $meta['requires_wp'] ); ?>+ · PHP <?php echo $site->e( $meta['requires_php'] ); ?>+ · <?php echo $site->e( $meta['license'] ); ?>
		</span>
	</p>
</header>

<?php if ( $hero_image ) : ?>
	<figure class="figure">
		<img src="<?php echo $site->e( $hero_image['src'] ); ?>" alt="<?php echo $site->e( $hero_image['alt'] ); ?>" width="<?php echo (int) $hero_image['width']; ?>" height="<?php echo (int) $hero_image['height']; ?>">
		<figcaption><?php echo $site->e( $hero_image['alt'] ); ?></figcaption>
	</figure>
<?php endif; ?>

<?php if ( $highlights ) : ?>
	<section class="section" id="highlights">
		<h2>Highlights</h2>
		<div class="section__body">
			<div class="columns">
				<?php foreach ( $highlights as $wmds_group ) : ?>
					<div class="prose">
						<h3><?php echo $site->md( $wmds_group['title'] ); ?></h3>
						<?php echo $wmds_group['html']; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $screenshots ) : ?>
				<div class="columns">
					<?php foreach ( $screenshots as $wmds_shot ) : ?>
						<figure class="figure">
							<a href="<?php echo $site->e( $wmds_shot['src'] ); ?>">
								<img src="<?php echo $site->e( $wmds_shot['src'] ); ?>" alt="<?php echo $site->e( $wmds_shot['alt'] ); ?>" width="<?php echo (int) $wmds_shot['width']; ?>" height="<?php echo (int) $wmds_shot['height']; ?>" loading="lazy" decoding="async">
							</a>
							<figcaption><?php echo $site->e( $wmds_shot['alt'] ); ?></figcaption>
						</figure>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>

<?php if ( $releases ) : ?>
	<section class="section" id="releases">
		<h2>Recent releases</h2>
		<div class="section__body">
			<?php foreach ( $releases as $wmds_release ) : ?>
				<div class="release">
					<h3>
						<a href="changelog.html#v<?php echo $site->e( $wmds_release['version'] ); ?>"><?php echo $site->e( $wmds_release['version'] ); ?></a>
						<time datetime="<?php echo $site->e( $wmds_release['date'] ); ?>"><?php echo $site->e( $site->date( $wmds_release['date'] ) ); ?></time>
					</h3>
					<ul>
						<?php foreach ( $wmds_release['items'] as $wmds_item ) : ?>
							<li><?php echo $wmds_item; ?></li>
						<?php endforeach; ?>
						<?php if ( $wmds_release['more'] ) : ?>
							<li><a href="changelog.html#v<?php echo $site->e( $wmds_release['version'] ); ?>"><?php echo (int) $wmds_release['more']; ?> more</a></li>
						<?php endif; ?>
					</ul>
				</div>
			<?php endforeach; ?>
			<p><a href="changelog.html">Full changelog</a></p>
		</div>
	</section>
<?php endif; ?>

<?php foreach ( $sections as $wmds_section ) : ?>
	<section class="section" id="<?php echo $site->e( $wmds_section['id'] ); ?>">
		<h2><?php echo $site->e( $wmds_section['title'] ); ?></h2>
		<div class="section__body prose">
			<?php if ( 'docs' === $wmds_section['kind'] ) : ?>
				<dl class="doc-list">
					<?php foreach ( $docs as $wmds_doc ) : ?>
						<div>
							<dt><a href="<?php echo $site->e( $wmds_doc['slug'] ); ?>.html"><?php echo $site->e( $wmds_doc['title'] ); ?></a></dt>
							<dd><?php echo $site->e( ucfirst( $wmds_doc['summary'] ) ); ?></dd>
						</div>
					<?php endforeach; ?>
					<div>
						<dt><a href="changelog.html">Changelog</a></dt>
						<dd>Every release, newest first</dd>
					</div>
				</dl>
			<?php else : ?>
				<?php echo $wmds_section['html']; ?>
			<?php endif; ?>
		</div>
	</section>
<?php endforeach; ?>

</div>
