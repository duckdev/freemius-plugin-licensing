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
	 * Option key for activation data.
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
		$activation = get_option( self::OPTION_KEY, array() );

		return $activation[ $this->plugin->get_id() ] ?? array();
	}

	/**
	 * Set plugin activation data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Data.
	 *
	 * @return bool
	 */
	public function set_activation_data( array $data ): bool {
		// Get existing activation data.
		$activation = get_option( self::OPTION_KEY, array() );
		// Update the plugin's data.
		$activation[ $this->plugin->get_id() ] = $data;

		return update_option( self::OPTION_KEY, $activation );
	}

	/**
	 * Get current plugin data object.
	 *
	 * @since 1.0.0
	 *
	 * @return Plugin
	 */
	public function get_plugin(): Plugin {
		return $this->plugin;
	}

	/**
	 * Get a transient key.
	 *
	 * Always use this method to generate unique keys for each plugins.
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
		return get_transient( $this->get_transient_key( $key ) );
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
		return set_transient( $this->get_transient_key( $key ), $value, $expiration );
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
		return delete_transient( $this->get_transient_key( $key ) );
	}

	/**
	 * Checks if a request is too frequent.
	 *
	 * Use this to avoid multiple HTTP requests being fired in a short period.
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
	 * This request flag is valid only for 5 mins.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Transient key.
	 *
	 * @return bool
	 */
	protected function set_request_time( string $key ): bool {
		return $this->set_transient( $key, time(), MINUTE_IN_SECONDS * 5 );
	}
}
