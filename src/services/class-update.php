<?php
/**
 * This class handles plugin updates.
 *
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      1.0.0
 * @package    Freemius
 * @subpackage Services
 */

namespace DuckDev\Freemius\Services;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use DuckDev\Freemius\Api\Api;
use DuckDev\Freemius\Data\Plugin;
use WP_Error;

/**
 * Class Updates
 */
class Update extends Service {

	/**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Plugin $plugin Plugin data.
	 *
	 * @return void
	 */
	public function __construct( Plugin $plugin ) {
		parent::__construct( $plugin );

		// Plugin update hooks for premium plugins.
		if ( $this->plugin->is_premium() ) {
			add_filter( 'plugins_api', array( $this, 'plugins_api_filter' ), 10, 3 );
			add_filter( 'site_transient_update_plugins', array( $this, 'plugin_updates_transient' ) );
			add_action( 'upgrader_process_complete', array( $this, 'purge_plugin' ), 10, 2 );
		}
	}

	/**
	 * Get update data for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return array|WP_Error
	 */
	public function get_update_data() {
		// On force-check, delete transient.
		if ( isset( $_GET['force-check'] ) ) {
			$this->delete_transient( 'update_data' );
		}

		// Save update data to transient if required.
		$update_data = $this->get_transient( 'update_data' );
		if ( false === $update_data ) {
			// Get latest update data from the API.
			$update_data = $this->get_remote_latest();
			if ( is_wp_error( $update_data ) ) {
				$update_data = array();
			}

			// Set to cache for 1 day.
			$this->set_transient( 'update_data', $update_data, DAY_IN_SECONDS );
		}

		return $update_data;
	}

	/**
	 * Get plugin info data.
	 *
	 * @since 1.0.0
	 *
	 * @return array|WP_Error
	 */
	public function get_plugin_info() {
		// Get from the cache.
		$info = $this->get_transient( 'plugin_info' );

		if ( ! $info ) {
			// Get from the API.
			$info = $this->get_remote_plugin_info();
			if ( ! is_wp_error( $info ) ) {
				$this->set_transient( 'plugin_info', $info, DAY_IN_SECONDS );
			}
		}

		return $info;
	}

	/**
	 * Purge plugin update data from the cache.
	 *
	 * This is done only once our plugin is updated.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $upgrader   Plugin upgrader.
	 * @param array $hook_extra Array of bulk item update data.
	 *
	 * @return void
	 */
	public function purge_plugin( $upgrader, array $hook_extra ) {
		if ( 'update' === $hook_extra['action'] && 'plugin' === $hook_extra['type'] ) {
			// Plugin base name.
			$plugin_base_name = plugin_basename( $this->plugin->get_main_file() );

			// Clean the cache when new plugin version is installed.
			if ( in_array( $plugin_base_name, $hook_extra['plugins'] ) ) {
				$this->delete_transient( 'update_data' );
			}
		}
	}

	/**
	 * Filter plugin data to add our custom data.
	 *
	 * @since 1.0.0
	 *
	 * @param false|object|array $data   Plugin data.
	 * @param string             $action Current action.
	 * @param object|null        $args   Arguments.
	 *
	 * @return object|array
	 */
	public function plugins_api_filter( $data, string $action = '', object $args = null ) {
		// Do nothing if you're not getting plugin information right now.
		if ( 'plugin_information' !== $action && ! isset( $args->slug ) ) {
			return $data;
		}

		// Do nothing if it is not our plugin.
		if ( $this->plugin->get_slug() !== $args->slug ) {
			return $data;
		}

		// Only do this for activated plugins.
		if ( ! $this->is_activated() ) {
			return $data;
		}

		$plugin_data = $this->plugin->get_data();
		$update_data = $this->get_update_data();

		// Error while getting update data.
		if ( empty( $update_data ) || is_wp_error( $update_data ) ) {
			return $data;
		}

		// Get plugin info.
		$plugin_info = $this->get_plugin_info();
		if ( is_wp_error( $plugin_info ) ) {
			$plugin_info = array();
		}

		// Set data.
		$data                = $args;
		$data->name          = $plugin_data['Name'];
		$data->author        = $plugin_data['Author'];
		$data->version       = $update_data['version'];
		$data->last_updated  = ! is_null( $update_data['updated'] ) ? $update_data['updated'] : $update_data['created'];
		$data->requires      = $update_data['requires_platform_version'];
		$data->requires_php  = $update_data['requires_programming_language_version'];
		$data->tested        = $update_data['tested_up_to_version'];
		$data->download_link = $update_data['url'];
		$data->banners       = array(
			'high' => $plugin_info['banner_url'] ?? '',
			'low'  => $plugin_info['card_banner_url'] ?? '',
		);
		// Add sections.
		$data->sections = wp_parse_args(
			$update_data['readme']['sections'] ?? array(),
			array(
				'description' => $plugin_info['description'] ?? 'Upgrade ' . $plugin_data['Name'] . ' to latest.',
			)
		);

		return $data;
	}

	/**
	 * Update current plugin to latest version.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $transient Transient data.
	 *
	 * @return mixed
	 */
	public function plugin_updates_transient( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Only do this for activated plugins.
		if ( ! $this->is_activated() ) {
			return $transient;
		}

		$plugin_data = $this->plugin->get_data();
		$update_data = $this->get_update_data();

		if (
			! empty( $update_data )
			&& ! is_wp_error( $update_data )
			&& version_compare( $plugin_data['Version'], $update_data['version'], '<' )
			&& version_compare( $update_data['requires_platform_version'], get_bloginfo( 'version' ), '<=' )
			&& version_compare( $update_data['requires_programming_language_version'], PHP_VERSION, '<' )
		) {
			$res              = new \stdClass();
			$res->slug        = $this->plugin->get_slug();
			$res->plugin      = plugin_basename( $this->plugin->get_main_file() );
			$res->new_version = $update_data['version'];
			$res->tested      = $update_data['requires_platform_version'];
			$res->package     = $update_data['url'];

			$transient->response[ $res->plugin ] = $res;
		}

		return $transient;
	}

	/**
	 * Get the latest plugin version from the API.
	 *
	 * @since 1.0.0
	 *
	 * @return array|WP_Error
	 */
	protected function get_remote_latest() {
		// Prevent multiple requests.
		if ( $this->is_duplicate_request( 'update_check' ) ) {
			return new WP_Error( 'too_many_requests', __( 'Too many requests. Slow down.', 'duckdev-freemius' ) );
		}

		// Only get if plugin license is active.
		if ( ! $this->is_activated() ) {
			return new WP_Error( 'license_not_active', __( 'No valid license is active.', 'duckdev-freemius' ) );
		}

		$activation  = $this->get_activation_data();
		$plugin_data = $this->plugin->get_data();

		// Get authenticated API instance.
		$api = Api::get_auth_instance(
			$activation['install_id'],
			$activation['install_data']['install_public_key'],
			$activation['install_data']['install_secret_key'],
			'install'
		);

		// Get the latest version data.
		$updates = $api->get(
			'updates/latest.json',
			array(
				'readme'     => true, // Include readme data.
				'newer_than' => $plugin_data['Version'], // Only latest than current version.
			)
		);

		// To prevent multiple requests for 5 mins.
		$this->set_request_time( 'update_check' );

		return $updates;
	}

	/**
	 * Get the latest product info from the API.
	 *
	 * @since 1.0.0
	 *
	 * @return array|WP_Error
	 */
	protected function get_remote_plugin_info() {
		// Get authenticated API instance using public key.
		$api = Api::get_auth_instance(
			$this->plugin->get_id(),
			$this->plugin->get_public_key(),
			$this->plugin->get_public_key(), // Use public key again for secret key to use public key encryption.
			'plugin'
		);

		// Addon list from API.
		return $api->get( 'info.json' );
	}

	/**
	 * Check if a license is active on the site.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	protected function is_activated(): bool {
		$activation = $this->get_activation_data();

		// Check for uid, install id & license key.
		if (
			empty( $activation['install_id'] ) ||
			empty( $activation['activation_params']['uid'] ) ||
			empty( $activation['activation_params']['license_key'] )
		) {
			return false;
		}

		return $activation['status'] === self::ACTIVATED;
	}
}
