<?php
defined( 'ABSPATH' ) || exit;

/**
 * Plugin Name: Reviews Importer
 * Description: Import testimonials (us_testimonial).
 * Version:     1.1.0
 * Author:      Bent Becker
 * GitHub Plugin URI: https://github.com/BentBecker/impreza-review-importer
 */

define( 'RI_VERSION', '1.1.0' );
define( 'RI_FILE', __FILE__ );
define( 'RI_DIR', plugin_dir_path( __FILE__ ) );
define( 'RI_URL', plugin_dir_url( __FILE__ ) );
define( 'RI_GITHUB_REPO', 'BentBecker/impreza-review-importer' );

require_once RI_DIR . 'includes/class-github-updater.php';
require_once RI_DIR . 'includes/class-shortcodes.php';

RI_GitHub_Updater::init( RI_FILE, RI_GITHUB_REPO );
add_action( 'init', array( 'RI_Shortcodes', 'init' ) );

add_action( 'admin_menu', 'ri_admin_menu' );
add_action( 'admin_enqueue_scripts', 'ri_enqueue_assets' );
add_action( 'wp_ajax_ri_import', 'ri_ajax_import' );

function ri_admin_menu() {
	add_menu_page(
		__( 'Reviews Importer', 'reviews-importer' ),
		__( 'Reviews Importer', 'reviews-importer' ),
		'manage_options',
		'reviews-importer',
		'ri_admin_page',
		'dashicons-star-filled',
		30
	);
}

function ri_enqueue_assets( $hook ) {
	if ( $hook !== 'toplevel_page_reviews-importer' ) {
		return;
	}

	wp_enqueue_style(
		'ri-admin',
		RI_URL . 'assets/admin.css',
		array(),
		RI_VERSION
	);

	// Also load frontend CSS so the preview panel is correctly styled.
	wp_enqueue_style(
		'ri-frontend',
		RI_URL . 'assets/frontend.css',
		array(),
		RI_VERSION
	);

	wp_enqueue_script(
		'ri-admin',
		RI_URL . 'assets/admin.js',
		array( 'jquery' ),
		RI_VERSION,
		true
	);

	wp_localize_script( 'ri-admin', 'reviewsRI', array(
		'ajax_url'      => admin_url( 'admin-ajax.php' ),
		'nonce'         => wp_create_nonce( 'ri_import_nonce' ),
		'preview_nonce' => wp_create_nonce( 'ri_preview_nonce' ),
		'i18n'          => array(
			'importing'       => __( 'Importing…', 'reviews-importer' ),
			'done'            => __( 'Import complete.', 'reviews-importer' ),
			'error_parse'     => __( 'Could not parse JSON file.', 'reviews-importer' ),
			'error_server'    => __( 'Server error. Please try again.', 'reviews-importer' ),
			'copied'          => __( 'Copied!', 'reviews-importer' ),
			'copy'            => __( 'Copy', 'reviews-importer' ),
			'preview_loading' => __( 'Loading preview…', 'reviews-importer' ),
			'preview_empty'   => __( 'No reviews found for this category.', 'reviews-importer' ),
			'preview_error'   => __( 'Preview unavailable.', 'reviews-importer' ),
		),
	) );
}

function ri_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'reviews-importer' ) );
	}

	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'import'; // phpcs:ignore

	$categories = get_terms( array(
		'taxonomy'   => 'us_testimonial_category',
		'hide_empty' => false,
	) );
	?>
	<div class="wrap ri-wrap">
		<h1><?php esc_html_e( 'Reviews Importer', 'reviews-importer' ); ?></h1>

		<nav class="nav-tab-wrapper ri-tabs">
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'import', menu_page_url( 'reviews-importer', false ) ) ); ?>"
			   class="nav-tab<?php echo $active_tab === 'import' ? ' nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Import', 'reviews-importer' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'shortcodes', menu_page_url( 'reviews-importer', false ) ) ); ?>"
			   class="nav-tab<?php echo $active_tab === 'shortcodes' ? ' nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Shortcodes', 'reviews-importer' ); ?>
			</a>
		</nav>

		<div class="ri-tab-content">
		<?php if ( $active_tab === 'import' ) : ?>

			<p><?php esc_html_e( 'Import testimonials from a JSON file. Each item must follow the expected schema below.', 'reviews-importer' ); ?></p>

			<details class="ri-schema">
				<summary><?php esc_html_e( 'Expected JSON schema', 'reviews-importer' ); ?></summary>
				<pre>[
  {
    "review_id": "unique-id",
    "author":    "John Doe",
    "rating":    5,
    "text":      "Review text / post content",
    "date":      "2025-06-01",
    "category":  "google"
  }
]</pre>
			</details>

			<form id="ri-form" enctype="multipart/form-data">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="ri-file"><?php esc_html_e( 'JSON File', 'reviews-importer' ); ?></label>
							</th>
							<td>
								<input type="file" id="ri-file" name="json_file" accept=".json" required>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ri-category"><?php esc_html_e( 'Default Category', 'reviews-importer' ); ?></label>
							</th>
							<td>
								<select id="ri-category" name="category">
									<option value=""><?php esc_html_e( '— none —', 'reviews-importer' ); ?></option>
									<?php if ( ! is_wp_error( $categories ) ) : ?>
										<?php foreach ( $categories as $cat ) : ?>
											<option value="<?php echo esc_attr( $cat->slug ); ?>">
												<?php echo esc_html( $cat->name ); ?>
											</option>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Used when the JSON item does not specify a category.', 'reviews-importer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Duplicate handling', 'reviews-importer' ); ?>
							</th>
							<td>
								<label>
									<input type="radio" name="duplicate" value="skip" checked>
									<?php esc_html_e( 'Skip duplicates (same title + author)', 'reviews-importer' ); ?>
								</label><br>
								<label>
									<input type="radio" name="duplicate" value="overwrite">
									<?php esc_html_e( 'Overwrite duplicates', 'reviews-importer' ); ?>
								</label>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Import Reviews', 'reviews-importer' ), 'primary', 'submit', false ); ?>
			</form>

			<div id="ri-progress" style="display:none;">
				<p><strong id="ri-status"></strong></p>
				<div class="ri-bar-wrap"><div id="ri-bar"></div></div>
			</div>

			<div id="ri-results" style="display:none;">
				<h2><?php esc_html_e( 'Results', 'reviews-importer' ); ?></h2>
				<ul id="ri-log"></ul>
			</div>

		<?php elseif ( $active_tab === 'shortcodes' ) : ?>

			<?php ri_shortcodes_tab( $categories ); ?>

		<?php endif; ?>
		</div>
	</div>
	<?php
}

function ri_shortcodes_tab( $categories ) {
	$templates = RI_Shortcodes::get_templates();
	$first_id  = key( $templates );
	?>
	<div class="ri-sc-builder">

		<p><?php esc_html_e( 'Pick a template, tweak colors and options, then copy the shortcode into any page or widget.', 'reviews-importer' ); ?></p>

		<!-- Template picker — full-width row of cards -->
		<div class="ri-sc-tpl-list">
			<?php foreach ( $templates as $tpl ) : ?>
				<label class="ri-sc-tpl-item">
					<input type="radio" name="ri_tpl"
						   value="<?php echo esc_attr( $tpl['id'] ); ?>"
						   data-shortcode="<?php echo esc_attr( $tpl['shortcode'] ); ?>"
						   <?php checked( $tpl['id'], $first_id ); ?>>
					<span class="ri-sc-tpl-label"><?php echo esc_html( $tpl['label'] ); ?></span>
					<code class="ri-sc-tpl-tag">[<?php echo esc_html( $tpl['shortcode'] ); ?>]</code>
				</label>
			<?php endforeach; ?>
		</div>

		<!-- Two-column: options left, preview + shortcode right -->
		<div class="ri-sc-layout">

			<!-- Left: options per template (one panel each, shown/hidden by JS) -->
			<div id="ri-sc-opts" class="ri-sc-opts">
				<?php foreach ( $templates as $tpl ) : ?>
					<div class="ri-sc-opts-panel" data-tpl="<?php echo esc_attr( $tpl['id'] ); ?>" <?php echo $tpl['id'] !== $first_id ? 'hidden' : ''; ?>>

						<!-- Category filter (same for all templates) -->
						<div class="ri-sc-field">
							<label class="ri-sc-field-label"><?php esc_html_e( 'Category', 'reviews-importer' ); ?></label>
							<select data-attr="category" data-default="">
								<option value=""><?php esc_html_e( '— all —', 'reviews-importer' ); ?></option>
								<?php if ( ! is_wp_error( $categories ) ) : ?>
									<?php foreach ( $categories as $cat ) : ?>
										<option value="<?php echo esc_attr( $cat->slug ); ?>"><?php echo esc_html( $cat->name ); ?></option>
									<?php endforeach; ?>
								<?php endif; ?>
							</select>
						</div>

						<!-- Template-specific options -->
						<?php foreach ( $tpl['options'] ?? array() as $opt ) : ?>
							<div class="ri-sc-field">
								<label class="ri-sc-field-label"><?php echo esc_html( $opt['label'] ); ?></label>
								<?php if ( $opt['type'] === 'color' ) : ?>
									<div class="ri-sc-color-wrap">
										<input type="color"
											   value="<?php echo esc_attr( $opt['default'] ); ?>"
											   data-attr="<?php echo esc_attr( $opt['key'] ); ?>"
											   data-default="<?php echo esc_attr( $opt['default'] ); ?>">
										<span class="ri-sc-color-value"><?php echo esc_html( $opt['default'] ); ?></span>
										<button type="button"
												class="ri-sc-color-reset button-link"
												data-reset="<?php echo esc_attr( $opt['default'] ); ?>"
												title="<?php esc_attr_e( 'Reset to default', 'reviews-importer' ); ?>">&#x21ba;</button>
									</div>
								<?php elseif ( $opt['type'] === 'text' ) : ?>
									<input type="text"
										   class="regular-text"
										   value="<?php echo esc_attr( $opt['default'] ); ?>"
										   placeholder="<?php echo esc_attr( $opt['placeholder'] ?? '' ); ?>"
										   data-attr="<?php echo esc_attr( $opt['key'] ); ?>"
										   data-default="<?php echo esc_attr( $opt['default'] ); ?>">
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Right: live preview + generated shortcode -->
			<div class="ri-sc-output">
				<h3><?php esc_html_e( 'Preview', 'reviews-importer' ); ?></h3>
				<div id="ri-sc-preview" class="ri-sc-preview">
					<p class="ri-preview-placeholder"><?php esc_html_e( 'Loading preview…', 'reviews-importer' ); ?></p>
				</div>

				<h3><?php esc_html_e( 'Your Shortcode', 'reviews-importer' ); ?></h3>
				<div class="ri-sc-code-wrap">
					<code id="ri-sc-code" class="ri-sc-code"></code>
					<button type="button" id="ri-sc-copy" class="button button-secondary">
						<?php esc_html_e( 'Copy', 'reviews-importer' ); ?>
					</button>
				</div>
			</div>

		</div>
	</div>
	<?php
}

/**
 * AJAX handler — receives a chunk of reviews as JSON string.
 */
function ri_ajax_import() {
	// Suppress debug output so JSON response stays clean.
	ini_set( 'display_errors', '0' ); // phpcs:ignore

	check_ajax_referer( 'ri_import_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Insufficient permissions.', 'reviews-importer' ), 403 );
	}

	$raw_items   = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '';
	$default_cat = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
	$duplicate   = isset( $_POST['duplicate'] ) ? sanitize_text_field( wp_unslash( $_POST['duplicate'] ) ) : 'skip';

	// Guard against excessively large payloads (5 MB limit).
	if ( strlen( $raw_items ) > 5 * 1024 * 1024 ) {
		wp_send_json_error( __( 'Payload too large. Split the JSON into smaller chunks.', 'reviews-importer' ), 413 );
	}

	$items = json_decode( $raw_items, true );

	if ( ! is_array( $items ) ) {
		wp_send_json_error( __( 'Invalid JSON payload.', 'reviews-importer' ) );
	}

	$results = array();

	foreach ( $items as $item ) {
		$result = ri_insert_testimonial( $item, $default_cat, $duplicate );
		$results[] = $result;
	}

	wp_send_json_success( $results );
}

/**
 * Insert or update a single us_testimonial post.
 *
 * @param array  $item        Raw item from JSON.
 * @param string $default_cat Default category slug.
 * @param string $duplicate   'skip' or 'overwrite'.
 * @return array
 */
function ri_insert_testimonial( array $item, $default_cat, $duplicate ) {
	// Support both 'text' (Google Reviews export) and 'content' field names.
	$content_raw = $item['content'] ?? $item['text'] ?? '';

	$title      = sanitize_text_field( $item['title'] ?? $item['author'] ?? '' );
	$content    = wp_kses_post( $content_raw );
	$author     = sanitize_text_field( $item['author']    ?? '' );
	$role       = sanitize_text_field( $item['role']      ?? '' );
	$company    = sanitize_text_field( $item['company']   ?? '' );
	$link       = esc_url_raw( $item['link']              ?? '' );
	$rating     = ri_sanitize_rating( $item['rating'] ?? '' );
	$date       = ri_sanitize_date( $item['date']    ?? '' );
	$cat        = sanitize_text_field( $item['category']  ?? $default_cat );
	$review_id  = sanitize_text_field( $item['review_id'] ?? '' );

	if ( empty( $title ) ) {
		return array( 'status' => 'error', 'title' => '(empty)', 'message' => __( 'Skipped: title/author is required.', 'reviews-importer' ) );
	}

	// Prefer dedup by review_id meta (stable across re-imports), fallback to title+author.
	$existing_id = $review_id ? ri_find_existing_by_review_id( $review_id ) : ri_find_existing( $title, $author );

	if ( $existing_id && $duplicate === 'skip' ) {
		return array( 'status' => 'skipped', 'title' => $title, 'message' => __( 'Skipped: duplicate.', 'reviews-importer' ) );
	}

	$post_data = array(
		'post_type'    => 'us_testimonial',
		'post_title'   => $title,
		'post_content' => $content,
		'post_status'  => 'publish',
	);

	if ( $date ) {
		$post_data['post_date']     = $date . ' 00:00:00';
		$post_data['post_date_gmt'] = get_gmt_from_date( $date . ' 00:00:00' );
	}

	if ( $existing_id && $duplicate === 'overwrite' ) {
		$post_data['ID'] = $existing_id;
		$post_id = wp_update_post( $post_data, true );
		$action  = 'updated';
	} else {
		$post_id = wp_insert_post( $post_data, true );
		$action  = 'inserted';
	}

	if ( is_wp_error( $post_id ) ) {
		return array( 'status' => 'error', 'title' => $title, 'message' => $post_id->get_error_message() );
	}

	// Meta fields used by Impreza / us-core.
	update_post_meta( $post_id, 'us_testimonial_author', $author );
	update_post_meta( $post_id, 'us_testimonial_role', $role );
	update_post_meta( $post_id, 'us_testimonial_company', $company );
	update_post_meta( $post_id, 'us_testimonial_rating', $rating );

	// Link is stored as JSON by us-core.
	$link_value = $link ? wp_json_encode( array( 'url' => $link ) ) : '{"url":""}';
	update_post_meta( $post_id, 'us_testimonial_link', $link_value );

	// Store original review_id for Phase 2 Google sync deduplication.
	if ( $review_id ) {
		update_post_meta( $post_id, 'ri_review_id', $review_id );
	}

	// Flag whether this review has body text. Use in Impreza to
	// conditionally show/hide the content element:
	//   Conditions → Custom field "ri_has_content" equals "1"
	update_post_meta( $post_id, 'ri_has_content', trim( $content ) !== '' ? '1' : '0' );

	// Taxonomy.
	if ( $cat ) {
		$term = get_term_by( 'slug', $cat, 'us_testimonial_category' );
		if ( ! $term ) {
			$term = get_term_by( 'name', $cat, 'us_testimonial_category' );
		}
		if ( $term ) {
			wp_set_object_terms( $post_id, $term->term_id, 'us_testimonial_category' );
		}
	}

	return array(
		'status'  => $action,
		'title'   => $title,
		/* translators: %d: post ID */
		'message' => sprintf( __( 'Post ID %d — %s.', 'reviews-importer' ), $post_id, $action ),
	);
}

/**
 * Find an existing us_testimonial by the original review_id meta.
 */
function ri_find_existing_by_review_id( $review_id ) {
	$posts = get_posts( array(
		'post_type'      => 'us_testimonial',
		'post_status'    => array( 'publish', 'draft', 'private' ),
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array(
				'key'   => 'ri_review_id',
				'value' => $review_id,
			),
		),
	) );
	return ! empty( $posts ) ? (int) $posts[0] : 0;
}

/**
 * Find an existing us_testimonial by title + author meta.
 */
function ri_find_existing( $title, $author ) {
	global $wpdb;

	$post_id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			WHERE p.post_type   = 'us_testimonial'
			  AND p.post_status != 'trash'
			  AND p.post_title  = %s
			  AND pm.meta_key   = 'us_testimonial_author'
			  AND pm.meta_value = %s
			LIMIT 1",
			$title,
			$author
		)
	);

	return (int) $post_id;
}

/**
 * Sanitize rating: accepts 1–5 (int or float) or 'none'.
 */
function ri_sanitize_rating( $value ) {
	// Cast float (e.g. 5.0 from JSON) to int first, then string.
	$int = (int) round( (float) $value );
	if ( $int >= 1 && $int <= 5 ) {
		return (string) $int;
	}
	return 'none';
}

/**
 * Sanitize date, returns Y-m-d or empty string.
 */
function ri_sanitize_date( $value ) {
	if ( empty( $value ) ) {
		return '';
	}
	$ts = strtotime( sanitize_text_field( (string) $value ) );
	if ( ! $ts ) {
		return '';
	}
	return gmdate( 'Y-m-d', $ts );
}
