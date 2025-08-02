<?php
/**
 * The license manager service class.
 *
 * @link       https://duckdev.com/products/loggedin-limit-active-logins/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      1.0.0
 * @package    Freemius
 * @subpackage Services
 */

namespace DuckDev\Freemius\Services;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use DateTime;
use DuckDev\Freemius\Api\Api;
use WP_Error;

/**
 * Class Licenses
 */
class License extends Service {

	/**
	 * Get the license key for the site.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_license_key(): string {
		$activation = $this->get_activation_data();
		// Return if key found.
		if ( ! empty( $activation['activation_params']['license_key'] ) ) {
			return $activation['activation_params']['license_key'];
		}

		return '';
	}

	/**
	 * Activates the license key for the site.
	 *
	 * This also saves a unique ID based on the site URL to the database.
	 * This will be used to cross check during deactivation on whether or
	 * not to continue deactivating. We do not store the site URL directly.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key License key.
	 *
	 * @return bool|WP_Error
	 */
	public function activate( string $key ) {
		// We need a key!.
		if ( empty( $key ) ) {
			return new WP_Error( 'empty_activation_key', __( 'Empty activation key.', 'duckdev-freemius' ) );
		}

		$plugin_data = $this->get_plugin_data();
		$args        = array(
			'license_key' => $key,
			'uid'         => $this->get_current_site_uid(),
			'url'         => get_site_url(),
			'version'     => $plugin_data['Version'],
		);

		// Add any existing install ID if it exists so we don't add a new entry.
		$activation = $this->get_activation_data();
		if ( ! empty( $activation['install_id'] ) ) {
			$args['install_id'] = $activation['install_id'];
		} else {
			$activation = array();
		}

		// Remotely activate the license.
		$api      = Api::get_instance( $this->plugin->get_id() );
		$response = $api->post( 'activate.json', $args );
		// Request failed.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Save the activation data.
		if ( isset( $response['install_id'] ) ) {
			$activation['activation_params'] = $args;
			$activation['install_id']        = $response['install_id'];
			$activation['date']              = ( new DateTime() )->format( 'Y-m-d H:i:s' );
			$activation['status']            = self::ACTIVATED;
			$activation['install_data']      = $response;
			// Update activation data.
			update_option( self::OPTION_KEY, $activation, 'no' );

			return true;
		}

		return new WP_Error( 'unknown_error', __( 'Unknown error.', 'duckdev-freemius' ) );
	}

	/**
	 * Deactivates a license key.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|array|WP_Error
	 */
	public function deactivate() {
		$activation = $this->get_activation_data();

		if ( ! $this->can_deactivate( $activation ) ) {
			return new WP_Error( 'invalid_activation_data', __( 'Invalid activation data.', 'duckdev-freemius' ) );
		}

		$args = array(
			// Required data.
			'uid'         => $activation['activation_params']['uid'],
			'install_id'  => $activation['install_id'],
			'license_key' => $activation['activation_params']['license_key'],
			'url'         => get_site_url(),
		);

		// Remotely activate the license.
		$api      = Api::get_instance( $this->plugin->get_id() );
		$response = $api->post( 'deactivate.json', $args );
		// Request failed.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response['id'] ) ) {
			$activation['status'] = self::DEACTIVATED;
			// Remove the license key so it's not visible in the database.
			if ( ! empty( $activation['activation_params']['license_key'] ) ) {
				$activation['activation_params']['license_key'] = '';
			}
			// Update activation data.
			update_option( self::OPTION_KEY, $activation, 'no' );

			return true;
		}

		return new WP_Error( 'unknown_error', __( 'Unknown error.', 'duckdev-freemius' ) );
	}

	/**
	 * Check if current license can be deactivated.
	 *
	 * @since 1.0.0
	 *
	 * @param array $activation Activation data.
	 *
	 * @return bool
	 */
	protected function can_deactivate( array $activation ): bool {
		// We need activation data.
		if ( empty( $activation ) ) {
			return false;
		}

		// Check for uid, install id & license key.
		if ( empty( $activation['install_id'] ) || empty( $activation['activation_params']['uid'] ) || empty( $activation['activation_params']['license_key'] ) ) {
			return false;
		}

		// Current site id should match.
		if ( $activation['activation_params']['uid'] !== $this->get_current_site_uid() ) {
			return false;
		}

		return true;
	}

	/**
	 * Get a unique UUID for current site.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_current_site_uid(): string {
		$blog_id        = get_current_blog_id();
		$site_url       = get_site_url( $blog_id );
		$site_url_parts = parse_url( $site_url );

		$data = array( $site_url_parts['host'], $blog_id );
		if ( isset( $site_url_parts['path'] ) ) {
			$data[] = $site_url_parts['path'];
		}

		return md5( implode( '-', $data ) );
	}
}
