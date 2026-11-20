<?php
$wmds_latest = $releases ? $releases[0] : null;
?>
<section class="hero">
	<div class="wrap hero__grid">
		<div class="hero__text">
			<?php if ( $wmds_latest ) : ?>
				<a class="hero__release" href="changelog.html#v<?php echo $site->e( $wmds_latest['version'] ); ?>">
					<span class="pill">v<?php echo $site->e( $wmds_latest['version'] ); ?></span>
					<span>Released <time datetime="<?php echo $site->e( $wmds_latest['date'] ); ?>"><?php echo $site->e( $site->date( $wmds_latest['date'] ) ); ?></time></span>
					<span aria-hidden="true">→</span>
				</a>
			<?php endif; ?>

			<h1>mobile.de <b>Sync</b></h1>
			<p class="hero__lede"><?php echo $site->md( $tagline ); ?></p>

			<div class="actions">
				<a class="btn btn--primary" href="<?php echo $site->e( $site->download_url() ); ?>">
					<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0-5-5m5 5 5-5M4 19h16"/></svg>
					<span>Download v<?php echo $site->e( $meta['version'] ); ?></span>
				</a>
				<a class="btn btn--ghost" href="<?php echo $site->e( $docs[0]['slug'] ); ?>.html">Read the docs</a>
			</div>

			<ul class="hero__facts">
				<li>WordPress <?php echo $site->e( $meta['requires_wp'] ); ?>+<?php echo $meta['tested_wp'] ? ' · tested up to ' . $site->e( $meta['tested_wp'] ) : ''; ?></li>
				<li>PHP <?php echo $site->e( $meta['requires_php'] ); ?>+</li>
				<li><?php echo $site->e( $meta['license'] ); ?></li>
			</ul>
		</div>

		<?php if ( $hero_image ) : ?>
			<figure class="hero__shot">
				<div class="frame">
					<img src="<?php echo $site->e( $hero_image['src'] ); ?>" alt="<?php echo $site->e( $hero_image['alt'] ); ?>" width="<?php echo (int) $hero_image['width']; ?>" height="<?php echo (int) $hero_image['height']; ?>" fetchpriority="high">
				</div>
			</figure>
		<?php endif; ?>
	</div>
</section>

<section class="section section--intro">
	<div class="wrap narrow prose prose--lead">
		<?php echo $lead; ?>
	</div>
</section>

<?php if ( $highlights ) : ?>
	<section class="section section--alt" id="highlights" aria-labelledby="highlights-title">
		<div class="wrap">
			<h2 class="section__title" id="highlights-title">Highlights</h2>

			<div class="cards">
				<?php foreach ( $highlights as $wmds_group ) : ?>
					<article class="card">
						<h3><?php echo $site->md( $wmds_group['title'] ); ?></h3>
						<div class="prose prose--compact"><?php echo $wmds_group['html']; ?></div>
					</article>
				<?php endforeach; ?>
			</div>

			<?php if ( $screenshots ) : ?>
				<div class="shots">
					<?php foreach ( $screenshots as $wmds_shot ) : ?>
						<figure class="shot">
							<a class="frame" href="<?php echo $site->e( $wmds_shot['src'] ); ?>">
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
	<section class="section" id="releases" aria-labelledby="releases-title">
		<div class="wrap">
			<div class="section__head">
				<h2 class="section__title" id="releases-title">Recent releases</h2>
				<a class="more" href="changelog.html">Full changelog <span aria-hidden="true">→</span></a>
			</div>

			<ol class="releases">
				<?php foreach ( $releases as $wmds_release ) : ?>
					<li class="release">
						<a class="release__head" href="changelog.html#v<?php echo $site->e( $wmds_release['version'] ); ?>">
							<strong><?php echo $site->e( $wmds_release['version'] ); ?></strong>
							<time datetime="<?php echo $site->e( $wmds_release['date'] ); ?>"><?php echo $site->e( $site->date( $wmds_release['date'] ) ); ?></time>
						</a>
						<ul>
							<?php foreach ( $wmds_release['items'] as $wmds_item ) : ?>
								<li><?php echo $wmds_item; ?></li>
							<?php endforeach; ?>
						</ul>
						<?php if ( $wmds_release['more'] ) : ?>
							<a class="release__more" href="changelog.html#v<?php echo $site->e( $wmds_release['version'] ); ?>">and <?php echo (int) $wmds_release['more']; ?> more</a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</section>
<?php endif; ?>

<?php foreach ( $sections as $wmds_index => $wmds_section ) : ?>
	<section class="section<?php echo 0 === $wmds_index % 2 ? ' section--alt' : ''; ?>" id="<?php echo $site->e( $wmds_section['id'] ); ?>" aria-labelledby="<?php echo $site->e( $wmds_section['id'] ); ?>-title">
		<?php if ( 'docs' === $wmds_section['kind'] ) : ?>
			<div class="wrap">
				<h2 class="section__title" id="<?php echo $site->e( $wmds_section['id'] ); ?>-title"><?php echo $site->e( $wmds_section['title'] ); ?></h2>
				<ul class="doc-cards">
					<?php foreach ( $docs as $wmds_doc ) : ?>
						<li>
							<a class="doc-card" href="<?php echo $site->e( $wmds_doc['slug'] ); ?>.html">
								<strong><?php echo $site->e( $wmds_doc['title'] ); ?> <span aria-hidden="true">→</span></strong>
								<?php if ( '' !== $wmds_doc['summary'] ) : ?>
									<span><?php echo $site->e( ucfirst( $wmds_doc['summary'] ) ); ?></span>
								<?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
					<li>
						<a class="doc-card" href="changelog.html">
							<strong>Changelog <span aria-hidden="true">→</span></strong>
							<span>Every release, newest first</span>
						</a>
					</li>
				</ul>
			</div>
		<?php else : ?>
			<div class="wrap narrow prose">
				<h2 class="section__title" id="<?php echo $site->e( $wmds_section['id'] ); ?>-title"><?php echo $site->e( $wmds_section['title'] ); ?></h2>
				<?php echo $wmds_section['html']; ?>
			</div>
		<?php endif; ?>
	</section>
<?php endforeach; ?>

