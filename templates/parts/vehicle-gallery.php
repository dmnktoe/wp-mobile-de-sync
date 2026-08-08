<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wmds_args = isset( $args ) && is_array( $args ) ? $args : array();

$wmds_images = isset( $wmds_args['images'] ) && is_array( $wmds_args['images'] ) ? $wmds_args['images'] : array();

if ( ! $wmds_images ) :
	if ( has_post_thumbnail() ) :
		?>
		<div class="wmds-gallery">
			<div class="wmds-gallery__main">
				<?php the_post_thumbnail( 'large', array( 'itemprop' => 'image' ) ); ?>
			</div>
		</div>
		<?php
	endif;

	return;
endif;
?>
<div class="wmds-gallery" data-wmds-gallery>
	<a class="wmds-gallery__main" href="<?php echo esc_url( $wmds_images[0]['url'] ); ?>" data-wmds-gallery-link>
		<img src="<?php echo esc_url( $wmds_images[0]['url'] ); ?>"
			alt="<?php echo esc_attr( $wmds_images[0]['alt'] ); ?>"
			itemprop="image" data-wmds-gallery-main>
	</a>

	<?php if ( count( $wmds_images ) > 1 ) : ?>
		<ul class="wmds-gallery__thumbs">
			<?php foreach ( $wmds_images as $wmds_index => $wmds_image ) : ?>
				<li>
					<a href="<?php echo esc_url( $wmds_image['url'] ); ?>"
						class="<?php echo ( 0 === $wmds_index ) ? 'is-current' : ''; ?>"
						data-wmds-gallery-thumb data-full="<?php echo esc_url( $wmds_image['url'] ); ?>"
						aria-current="<?php echo ( 0 === $wmds_index ) ? 'true' : 'false'; ?>">
						<img src="<?php echo esc_url( $wmds_image['thumb'] ); ?>"
							alt="<?php echo esc_attr( $wmds_image['alt'] ); ?>" loading="lazy">
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
