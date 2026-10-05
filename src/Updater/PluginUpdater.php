<?php
namespace WooFlexibleShipping\Updater;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight embedded GitHub Release auto-updater for Woo Flexible Shipping.
 */
class PluginUpdater {

	/**
	 * GitHub repository path.
	 *
	 * @var string
	 */
	private string $repository = 'agency/woo-flexible-shipping';

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Main plugin file path.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @param string $version Current version.
	 * @param string $repository GitHub repository (owner/repo).
	 */
	public function __construct( string $plugin_file, string $version, string $repository = 'agency/woo-flexible-shipping' ) {
		$this->plugin_file = $plugin_file;
		$this->version     = $version;
		$this->repository  = $repository;
	}

	/**
	 * Register updater hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
	}

	/**
	 * Check for updates against GitHub Releases.
	 *
	 * @param object $transient Update transient.
	 * @return object Modified transient.
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release || empty( $release['tag_name'] ) ) {
			return $transient;
		}

		$latest_version = ltrim( $release['tag_name'], 'v' );

		if ( version_compare( $this->version, $latest_version, '<' ) ) {
			$plugin_slug = plugin_basename( $this->plugin_file );
			$download_url = $release['zipball_url'] ?? '';

			if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
				foreach ( $release['assets'] as $asset ) {
					if ( isset( $asset['browser_download_url'] ) && strpos( $asset['browser_download_url'], '.zip' ) !== false ) {
						$download_url = $asset['browser_download_url'];
						break;
					}
				}
			}

			$res              = new \stdClass();
			$res->slug        = 'woo-flexible-shipping';
			$res->plugin      = $plugin_slug;
			$res->new_version = $latest_version;
			$res->url         = $release['html_url'] ?? '';
			$res->package     = $download_url;

			$transient->response[ $plugin_slug ] = $res;
		}

		return $transient;
	}

	/**
	 * Plugin details popup in WordPress admin plugin list.
	 *
	 * @param mixed $res Response.
	 * @param string $action Action name.
	 * @param object $args Arguments.
	 * @return mixed Response object.
	 */
	public function plugin_popup( $res, string $action, $args ) {
		if ( 'plugin_information' !== $action || 'woo-flexible-shipping' !== ( $args->slug ?? '' ) ) {
			return $res;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $res;
		}

		$res                = new \stdClass();
		$res->name          = 'Woo Flexible Shipping Table Rate';
		$res->slug          = 'woo-flexible-shipping';
		$res->version       = ltrim( $release['tag_name'] ?? $this->version, 'v' );
		$res->author        = 'Agency Engineering';
		$res->homepage      = $release['html_url'] ?? '';
		$res->download_link = $release['zipball_url'] ?? '';
		$res->sections      = array(
			'description' => 'High-performance, zero-bloat WooCommerce table rate shipping engine.',
			'changelog'   => wp_strip_all_tags( $release['body'] ?? 'Release notes unavailable.' ),
		);

		return $res;
	}

	/**
	 * Fetch latest release from GitHub API with caching.
	 *
	 * @return array|null Release array or null.
	 */
	private function get_latest_release(): ?array {
		$cache_key = 'wfs_latest_github_release';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$url      = 'https://api.github.com/repos/' . $this->repository . '/releases/latest';
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress-WooFlexibleShipping/' . $this->version,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $cache_key, array(), 1800 ); // Cache failure 30 min.
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $data ) ) {
			set_transient( $cache_key, $data, 43200 ); // Cache success 12 hrs.
			return $data;
		}

		return null;
	}
}
