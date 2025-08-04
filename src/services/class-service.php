<?php
/**
 * Base class to be extended by services.
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

use DuckDev\Freemius\Data\Plugin;

/**
 * Class Service
 */
class Service {

	/**
	 * Option key for activation details.
	 *
	 * @since 1.0.0
	 */
	const OPTION_KEY = 'duckdev_freemius_activation_data';

	/**
	 * Activated status.
	 *
	 * @since 1.0.0
	 */
	const ACTIVATED = 'activated';

	/**
	 * Deactivated status.
	 *
	 * @since 1.0.0
	 */
	const DEACTIVATED = 'deactivated';

	/**
	 * Plugin data instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Plugin $plugin
	 */
	protected Plugin $plugin;

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
		$this->plugin = $plugin;
	}

	/**
	 * Get plugin activation data.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_activation_data(): array {
		return get_option( self::OPTION_KEY, array() );
	}

	/**
	 * Get plugin data.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	protected function get_plugin_data(): array {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		return get_plugin_data( $this->plugin->get_main_file() );
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
		if ( empty( $activation['install_id'] ) || empty( $activation['activation_params']['uid'] ) || empty( $activation['activation_params']['license_key'] ) ) {
			return false;
		}

		return $activation['status'] === self::ACTIVATED;
	}

	/**
	 * Get a transient key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Transient key.
	 *
	 * @return string
	 */
	protected function get_transient_key( string $key ): string {
		return "duckdev_freemius_{$this->plugin->get_id()}_$key";
	}

	/**
	 * Get a transient value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Transient key.
	 *
	 * @return mixed
	 */
	protected function get_transient( string $key ) {
		return get_site_transient( $this->get_transient_key( $key ) );
	}

	/**
	 * Sets a transient value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key        Transient key.
	 * @param mixed  $value      Transient value.
	 * @param int    $expiration Expiration.
	 *
	 * @return bool
	 */
	protected function set_transient( string $key, $value, int $expiration = 0 ): bool {
		return set_site_transient( $this->get_transient_key( $key ), $value, $expiration );
	}

	/**
	 * Delete a transient value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Transient key.
	 *
	 * @return bool
	 */
	protected function delete_transient( string $key ): bool {
		return delete_site_transient( $this->get_transient_key( $key ) );
	}

	/**
	 * Checks if a request is too frequent.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Transient key.
	 *
	 * @return bool
	 */
	protected function is_duplicate_request( string $key ): bool {
		return $this->get_transient( $key ) ?? false;
	}

	/**
	 * Sets a flag for request time.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key        Transient key.
	 * @param int    $expiration Expiration.
	 *
	 * @return bool
	 */
	protected function set_request_time( string $key, $expiration = MINUTE_IN_SECONDS * 5 ): bool {
		return $this->set_transient( $key, time(), $expiration );
	}
}
