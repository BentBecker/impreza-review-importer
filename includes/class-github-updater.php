<?php
defined( 'ABSPATH' ) || exit;

/**
 * GitHub Updater for Reviews Importer.
 *
 * Checks the latest release on GitHub and tells WordPress when an update
 * is available, so the standard dashboard update flow works without
 * publishing to WordPress.org.
 *
 * Usage: RI_GitHub_Updater::init( RI_FILE, 'BentBecker/impreza-review-importer' );
 */
class RI_GitHub_Updater {

	/** @var string Absolute path to the main plugin file. */
	private $plugin_file;

	/** @var string Plugin slug (folder/file.php). */
	private $plugin_slug;

	/** @var string GitHub repository in "owner/repo" format. */
	private $github_repo;

	/** @var string|null Optional GitHub Personal Access Token. */
	private $token;

	/** @var object|null Cached release data from the GitHub API. */
	private $release = null;

	/**
	 * Bootstrap the updater.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @param string $github_repo Repository in "owner/repo" format.
	 */
	public static function init( $plugin_file, $github_repo ) {
		$instance = new self( $plugin_file, $github_repo );
		$instance->register_hooks();
	}

	private function __construct( $plugin_file, $github_repo ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_slug = plugin_basename( $plugin_file );
		$this->github_repo = $github_repo;
		$this->token       = defined( 'RI_GITHUB_TOKEN' ) ? RI_GITHUB_TOKEN : null;
	}

	private function register_hooks() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_pre_install', array( $this, 'pre_install' ), 10, 2 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
	}

	// -------------------------------------------------------------------------
	// GitHub API
	// -------------------------------------------------------------------------

	/**
	 * Fetch the latest release from the GitHub API (cached for 12 hours).
	 *
	 * @return object|false
	 */
	private function get_release() {
		if ( $this->release !== null ) {
			return $this->release;
		}

		$transient_key = 'ri_github_release_' . md5( $this->github_repo );
		$cached        = get_transient( $transient_key );

		if ( $cached !== false ) {
			$this->release = $cached;
			return $this->release;
		}

		$url  = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';
		$args = array(
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			),
			'timeout' => 10,
		);

		if ( $this->token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->token;
		}

		$response = wp_remote_get( esc_url_raw( $url ), $args );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $body->tag_name ) ) {
			return false;
		}

		$this->release = $body;
		set_transient( $transient_key, $body, 12 * HOUR_IN_SECONDS );

		return $this->release;
	}

	/**
	 * Clean a GitHub tag name to a plain version string.
	 * e.g. "v1.0.8" → "1.0.8"
	 *
	 * @param string $tag
	 * @return string
	 */
	private function clean_version( $tag ) {
		return ltrim( $tag, 'v' );
	}

	/**
	 * Return the download URL for the release zip.
	 * Prefers an uploaded release asset; falls back to the auto-generated
	 * source zip that GitHub always provides.
	 *
	 * @param object $release
	 * @return string
	 */
	private function zip_url( $release ) {
		// Prefer a release asset named *.zip.
		if ( ! empty( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( substr( $asset->name, -4 ) === '.zip' ) {
					return $this->token
						? add_query_arg( 'access_token', $this->token, $asset->browser_download_url )
						: $asset->browser_download_url;
				}
			}
		}

		// Fall back to the auto-generated source zip.
		return 'https://github.com/' . $this->github_repo . '/archive/refs/tags/' . rawurlencode( $release->tag_name ) . '.zip';
	}

	// -------------------------------------------------------------------------
	// WordPress hooks
	// -------------------------------------------------------------------------

	/**
	 * Inject update info into the update_plugins transient when a newer
	 * version is available on GitHub.
	 *
	 * @param object $transient
	 * @return object
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = $this->clean_version( $release->tag_name );
		$local_version  = $transient->checked[ $this->plugin_slug ] ?? RI_VERSION;

		if ( version_compare( $remote_version, $local_version, '>' ) ) {
			$transient->response[ $this->plugin_slug ] = (object) array(
				'slug'        => dirname( $this->plugin_slug ),
				'plugin'      => $this->plugin_slug,
				'new_version' => $remote_version,
				'url'         => 'https://github.com/' . $this->github_repo,
				'package'     => $this->zip_url( $release ),
				'icons'       => array(),
				'banners'     => array(),
				'tested'      => '',
				'requires_php'=> '',
			);
		}

		return $transient;
	}

	/**
	 * Populate the "View version details" modal in the WordPress admin.
	 *
	 * @param false|object|array $result
	 * @param string             $action
	 * @param object             $args
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) {
			return $result;
		}

		$release = $this->get_release();
		if ( ! $release ) {
			return $result;
		}

		$plugin_data = get_plugin_data( $this->plugin_file );

		return (object) array(
			'name'          => $plugin_data['Name'],
			'slug'          => dirname( $this->plugin_slug ),
			'version'       => $this->clean_version( $release->tag_name ),
			'author'        => $plugin_data['Author'],
			'homepage'      => 'https://github.com/' . $this->github_repo,
			'short_description' => $plugin_data['Description'],
			'sections'      => array(
				'description' => $plugin_data['Description'],
				'changelog'   => nl2br( esc_html( $release->body ?? '' ) ),
			),
			'download_link' => $this->zip_url( $release ),
			'requires'      => '6.0',
			'tested'        => '6.8',
			'last_updated'  => $release->published_at ?? '',
			'banners'       => array(),
			'icons'         => array(),
		);
	}

	/**
	 * Verify the current user can update plugins before installation starts.
	 *
	 * @param bool|WP_Error $response
	 * @param array         $hook_extra
	 * @return bool|WP_Error
	 */
	public function pre_install( $response, $hook_extra ) {
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
			return $response;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			return new WP_Error( 'ri_no_permission', __( 'You do not have permission to update plugins.', 'reviews-importer' ) );
		}

		return $response;
	}

	/**
	 * GitHub archives unzip to a folder named "repo-tag" instead of the
	 * expected plugin folder name. Rename it so WordPress places files
	 * in the correct location.
	 *
	 * @param string      $source        Path to the unzipped source.
	 * @param string      $remote_source Path to the remote source (zip).
	 * @param WP_Upgrader $upgrader
	 * @param array       $hook_extra
	 * @return string
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
			return $source;
		}

		$plugin_folder   = dirname( $this->plugin_slug );
		$corrected_source = trailingslashit( dirname( $source ) ) . $plugin_folder . '/';

		if ( $source !== $corrected_source ) {
			$wp_filesystem->move( $source, $corrected_source );
			return $corrected_source;
		}

		return $source;
	}
}
