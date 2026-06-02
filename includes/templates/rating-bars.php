<?php
defined( 'ABSPATH' ) || exit;

/**
 * Template: Rating Bars
 * Shortcode: [reviews_bars]
 *
 */
return array(
	'id'        => 'rating-bars',
	'label'     => __( 'Rating Bars', 'reviews-importer' ),
	'shortcode' => 'reviews_bars',
	'options'   => array(
		array(
			'key'     => 'bar_color',
			'label'   => __( 'Bar fill color', 'reviews-importer' ),
			'type'    => 'color',
			'default' => '#152d4c',
		),
		array(
			'key'     => 'track_color',
			'label'   => __( 'Track background', 'reviews-importer' ),
			'type'    => 'color',
			'default' => '#f3f4f6',
		),
		array(
			'key'     => 'text_color',
			'label'   => __( 'Label & count color', 'reviews-importer' ),
			'type'    => 'color',
			'default' => '#0f1932',
		),
	),
	'render'    => function ( $atts ) {
		$stats        = RI_Shortcodes::get_stats( $atts['category'] );
		$distribution = $stats['distribution'];
		$max_count    = max( $distribution ) ?: 1;

		if ( $stats['total'] === 0 ) {
			return '';
		}

		$style = 'style="'
			. '--ri-bar:' . esc_attr( $atts['bar_color'] ) . ';'
			. '--ri-track:' . esc_attr( $atts['track_color'] ) . ';'
			. '--ri-text:' . esc_attr( $atts['text_color'] ) . ';"';

		ob_start();
		?>
		<div class="ri-bars" <?php echo $style; // phpcs:ignore ?>>
			<?php for ( $star = 5; $star >= 1; $star-- ) : ?>
				<?php
				$count = (int) ( $distribution[ $star ] ?? 0 );
				$pct   = round( ( $count / $max_count ) * 100 );
				?>
				<div class="ri-bars__row">
					<span class="ri-bars__label"><?php echo esc_html( $star ); ?></span>
					<div class="ri-bars__track">
						<div class="ri-bars__fill" style="width:<?php echo esc_attr( $pct ); ?>%"></div>
					</div>
					<span class="ri-bars__count"><?php echo esc_html( $count ); ?></span>
				</div>
			<?php endfor; ?>
		</div>
		<?php
		return ob_get_clean();
	},
);
