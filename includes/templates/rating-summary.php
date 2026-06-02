<?php
defined( 'ABSPATH' ) || exit;

/**
 * Template: Rating Summary
 * Shortcode: [reviews_summary]
 *
 */
return array(
	'id'        => 'rating-summary',
	'label'     => __( 'Rating Summary', 'reviews-importer' ),
	'shortcode' => 'reviews_summary',
	'options'   => array(
		array(
			'key'     => 'star_color',
			'label'   => __( 'Star color', 'reviews-importer' ),
			'type'    => 'color',
			'default' => '#f59e0b',
		),
		array(
			'key'     => 'score_color',
			'label'   => __( 'Score color', 'reviews-importer' ),
			'type'    => 'color',
			'default' => '#0f1932',
		),
		array(
			'key'     => 'label_color',
			'label'   => __( 'Label color', 'reviews-importer' ),
			'type'    => 'color',
			'default' => '#6b7280',
		),
		array(
			'key'         => 'label',
			'label'       => __( 'Custom label', 'reviews-importer' ),
			'type'        => 'text',
			'default'     => '',
			'placeholder' => __( 'e.g. based on 40+ reviews', 'reviews-importer' ),
		),
	),
	'render'    => function ( $atts ) {
		$stats   = RI_Shortcodes::get_stats( $atts['category'] );
		$average = $stats['average'];
		$total   = $stats['total'];

		if ( $total === 0 ) {
			return '';
		}

		$label = ! empty( $atts['label'] )
			? esc_html( $atts['label'] )
			/* translators: %d: number of reviews */
			: sprintf( esc_html__( 'based on %d+ business reviews', 'reviews-importer' ), $total );

		$stars = RI_Shortcodes::render_stars( $average );

		$style = 'style="'
			. '--ri-star:' . esc_attr( $atts['star_color'] ) . ';'
			. '--ri-score:' . esc_attr( $atts['score_color'] ) . ';'
			. '--ri-label:' . esc_attr( $atts['label_color'] ) . ';"';

		ob_start();
		?>
		<div class="ri-summary" <?php echo $style; // phpcs:ignore ?>>
			<span class="ri-summary__score"><?php echo esc_html( number_format( $average, 1 ) ); ?></span>
			<div class="ri-summary__right">
				<div class="ri-summary__stars"><?php echo $stars; // phpcs:ignore ?></div>
				<p class="ri-summary__label"><?php echo $label; // phpcs:ignore ?></p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	},
);
