<?php
defined( 'ABSPATH' ) || exit;

/**
 * Shortcode template registry.
 * Drop a new file into includes/templates/ to register a new template automatically.
 */
class RI_Shortcodes {

	/** @var array<string, array> Registered templates keyed by id. */
	private static $templates = array();

	public static function init() {
		self::load_templates();

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
		add_action( 'wp_ajax_ri_preview', array( __CLASS__, 'ajax_preview' ) );

		foreach ( self::$templates as $tpl ) {
			add_shortcode( $tpl['shortcode'], array( __CLASS__, 'dispatch_shortcode' ) );
		}
	}

	/** Load all template definitions from includes/templates/. */
	private static function load_templates() {
		$dir = RI_DIR . 'includes/templates/';
		foreach ( glob( $dir . '*.php' ) as $file ) {
			$tpl = require $file;
			if ( is_array( $tpl ) && ! empty( $tpl['id'] ) ) {
				self::$templates[ $tpl['id'] ] = $tpl;
			}
		}
	}

	public static function enqueue_frontend_assets() {
		wp_enqueue_style(
			'ri-frontend',
			RI_URL . 'assets/frontend.css',
			array(),
			RI_VERSION
		);
	}

	/** Route any registered shortcode to the correct template render callback. */
	public static function dispatch_shortcode( $atts, $content, $tag ) {
		foreach ( self::$templates as $tpl ) {
			if ( $tpl['shortcode'] === $tag ) {
				// Build defaults from options array + any explicit defaults.
				$defaults = array( 'category' => '' );
				foreach ( $tpl['options'] ?? array() as $opt ) {
					$defaults[ $opt['key'] ] = $opt['default'];
				}
				$defaults = array_merge( $defaults, $tpl['defaults'] ?? array() );
				$atts     = shortcode_atts( $defaults, $atts, $tag );
				return call_user_func( $tpl['render'], $atts );
			}
		}
		return '';
	}

	/** AJAX: render a shortcode and return the HTML for the admin preview. */
	public static function ajax_preview() {
		check_ajax_referer( 'ri_preview_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}

		$shortcode = isset( $_POST['shortcode'] ) ? sanitize_text_field( wp_unslash( $_POST['shortcode'] ) ) : '';

		// Only allow our own registered shortcode tags.
		$allowed = array_column( array_values( self::$templates ), 'shortcode' );
		$valid   = false;
		foreach ( $allowed as $tag ) {
			if ( strpos( $shortcode, '[' . $tag ) === 0 ) {
				$valid = true;
				break;
			}
		}

		if ( ! $valid ) {
			wp_send_json_error( 'Invalid shortcode.' );
		}

		wp_send_json_success( do_shortcode( $shortcode ) );
	}

	/** Return all registered templates (used by admin). */
	public static function get_templates() {
		return self::$templates;
	}

	/**
	 * Shared helper: query aggregated stats from us_testimonial posts.
	 *
	 * @param string $category us_testimonial_category slug (empty = all).
	 * @return array{average: float, total: int, distribution: array<int,int>}
	 */
	public static function get_stats( $category = '' ) {
		$args = array(
			'post_type'      => 'us_testimonial',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		if ( $category ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'us_testimonial_category',
					'field'    => 'slug',
					'terms'    => sanitize_text_field( $category ),
				),
			);
		}

		$ids = get_posts( $args );

		$distribution = array( 5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0 );
		$total_rated  = 0;
		$sum          = 0;

		foreach ( $ids as $id ) {
			$rating = get_post_meta( $id, 'us_testimonial_rating', true );
			$int    = (int) $rating;
			if ( $int >= 1 && $int <= 5 ) {
				$distribution[ $int ]++;
				$sum += $int;
				$total_rated++;
			}
		}

		return array(
			'average'      => $total_rated > 0 ? round( $sum / $total_rated, 1 ) : 0.0,
			'total'        => count( $ids ),
			'total_rated'  => $total_rated,
			'distribution' => $distribution,
		);
	}

	/**
	 * Render star HTML using Font Awesome 5 icons (full / half / empty).
	 *
	 * Rounding: fraction < 0.25 → empty, 0.25–0.74 → half, ≥ 0.75 → full.
	 *
	 * @param float $rating  0–5.
	 * @param int   $max     Total stars (default 5).
	 * @return string HTML.
	 */
	public static function render_stars( $rating, $max = 5 ) {
		$html = '';

		for ( $i = 1; $i <= $max; $i++ ) {
			$fraction = $rating - ( $i - 1 );
			if ( $fraction >= 0.75 ) {
				$html .= '<i class="fas fa-star ri-star ri-star--full" aria-hidden="true"></i>';
			} elseif ( $fraction >= 0.25 ) {
				$html .= '<i class="fas fa-star-half-alt ri-star ri-star--half" aria-hidden="true"></i>';
			} else {
				$html .= '<i class="far fa-star ri-star ri-star--empty" aria-hidden="true"></i>';
			}
		}

		return sprintf(
			'<span class="ri-stars" aria-label="%s %s %s">%s</span>',
			esc_attr( $rating ),
			esc_attr__( 'out of', 'reviews-importer' ),
			esc_attr( $max ),
			$html
		);
	}
}
