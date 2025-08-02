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

		// Plugin update hooks.
		add_filter( 'plugins_api', array( $this, 'plugins_api_filter' ), 10, 3 );
		add_filter( 'site_transient_update_plugins', array( $this, 'updates_transient' ) );
		add_action( 'upgrader_process_complete', array( $this, 'purge_plugin' ), 10, 2 );
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
		if ( ! $update_data ) {
			$update_data = $this->get_remote_latest();
			$this->set_transient( 'update_data', $update_data, DAY_IN_SECONDS );
		}

		return $update_data;
	}

	/**
	 * Purge plugin update data from the transient.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $upgrader Plugin upgrader.
	 * @param array $options  Options.
	 *
	 * @return void
	 */
	public function purge_plugin( $upgrader, array $options ) {
		if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			// Clean the cache when new plugin version is installed.
			$this->delete_transient( 'update_data' );
		}
	}

	/**
	 * Filter plugin data to add our custom data.
	 *
	 * @since 1.0.0
	 *
	 * @param array       $data   Plugin data.
	 * @param string      $action Current action.
	 * @param object|null $args   Arguments.
	 *
	 * @return object|array
	 */
	public function plugins_api_filter( array $data, string $action = '', object $args = null ) {
		// Do nothing if you're not getting plugin information right now.
		if ( 'plugin_information' !== $action ) {
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

		$plugin_data = $this->get_plugin_data();
		$update_data = $this->get_update_data();

		// Error while getting update data.
		if ( empty( $update_data ) || is_wp_error( $update_data ) ) {
			return $data;
		}

		// Set data.
		$data                = $args;
		$data->name          = $plugin_data['Name'];
		$data->author        = $plugin_data['Author'];
		$data->sections      = array(
			'description' => 'Upgrade ' . $plugin_data['Name'] . ' to latest.',
		);
		$data->version       = $update_data['version'];
		$data->last_updated  = ! is_null( $update_data['updated'] ) ? $update_data['updated'] : $update_data['created'];
		$data->requires      = $update_data['requires_platform_version'];
		$data->requires_php  = $update_data['requires_programming_language_version'];
		$data->tested        = $update_data['tested_up_to_version'];
		$data->download_link = $update_data['url'];

		return $data;
	}

	/**
	 * Update current plugin to latest version.
	 *
	 * @since 1.0.0
	 *
	 * @param object $transient Transient data.
	 *
	 * @return object
	 */
	public function updates_transient( object $transient ): object {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Only do this for activated plugins.
		if ( ! $this->is_activated() ) {
			return $transient;
		}

		$plugin_data = $this->get_plugin_data();
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
		if ( ! $this->is_activated() ) {
			return new WP_Error( 'not_active', __( 'License not active.', 'duckdev-freemius' ) );
		}

		$activation = $this->get_activation_data();

		// Get authenticated API instance.
		$api = Api::get_auth_instance(
			$activation['install_id'],
			$activation['install_data']['install_public_key'],
			$activation['install_data']['install_secret_key'],
			'install'
		);

		// Get the download URL for the latest version.
		return $api->get(
			'updates/latest.json',
			array(
				'is_premium' => 'true',
				'newer_than' => '1.0.0',
			)
		);
	}
}
