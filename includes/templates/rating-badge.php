<?php
defined( 'ABSPATH' ) || exit;

/**
 * Template: Rating Badge
 * Shortcode: [reviews_badge]
 */
return array(
	'id'        => 'rating-badge',
	'label'     => __( 'Rating Badge', 'reviews-importer' ),
	'shortcode' => 'reviews_badge',
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
			'key'     => 'count_color',
			'label'   => __( 'Count text color', 'reviews-importer' ),
			'type'    => 'color',
			'default' => '#6b7280',
		),
		array(
			'key'         => 'label',
			'label'       => __( 'Count suffix', 'reviews-importer' ),
			'type'        => 'text',
			'default'     => 'reviews',
			'placeholder' => __( 'e.g. reviews', 'reviews-importer' ),
		),
	),
	'render'    => function ( $atts ) {
		$stats   = RI_Shortcodes::get_stats( $atts['category'] );
		$average = $stats['average'];
		$total   = $stats['total'];

		if ( $total === 0 ) {
			return '';
		}

		$suffix = ! empty( $atts['label'] ) ? esc_html( $atts['label'] ) : esc_html__( 'reviews', 'reviews-importer' );
		$stars  = RI_Shortcodes::render_stars( $average );

		$style = 'style="'
			. '--ri-star:' . esc_attr( $atts['star_color'] ) . ';'
			. '--ri-score:' . esc_attr( $atts['score_color'] ) . ';'
			. '--ri-count:' . esc_attr( $atts['count_color'] ) . ';"';

		ob_start();
		?>
		<span class="ri-badge" <?php echo $style; // phpcs:ignore ?>>
			<span class="ri-badge__score"><?php echo esc_html( number_format( $average, 1 ) ); ?></span>
			<span class="ri-badge__stars"><?php echo $stars; // phpcs:ignore ?></span>
			<span class="ri-badge__count"><?php echo esc_html( $total ); ?>+ <?php echo $suffix; // phpcs:ignore ?></span>
		</span>
		<?php
		return ob_get_clean();
	},
);
