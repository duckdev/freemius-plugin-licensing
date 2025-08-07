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
			return new WP_Error( 'empty_activation_key', __( 'License key is empty.', 'duckdev-freemius' ) );
		}

		// Only a premium plugin requires a license.
		if ( ! $this->plugin->is_premium() ) {
			return new WP_Error( 'not_premium', __( 'Not a premium plugin.', 'duckdev-freemius' ) );
		}

		// Get current plugin data.
		$plugin_data = $this->plugin->get_data();
		// Prepare activation args.
		$args = array(
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
		$response = Api::get_instance( $this->plugin->get_id() )->post( 'activate.json', $args );
		// Request failed.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Save the activation data after successful activation.
		if ( isset( $response['install_id'] ) ) {
			$activation['activation_params'] = $args;
			$activation['install_id']        = $response['install_id'];
			$activation['date']              = ( new DateTime() )->format( 'Y-m-d H:i:s' );
			$activation['status']            = self::ACTIVATED;
			$activation['install_data']      = $response;

			// Update activation data.
			$success = $this->set_activation_data( $activation );

			/**
			 * Action hook to trigger after a plugin license is activated.
			 *
			 * @since 1.0.0
			 *
			 * @param array $activation Activation data.
			 * @param bool  $success    Was the update successful.
			 */
			do_action( 'duckdev_freemius_license_activated', $activation, $success );

			return $success;
		}

		// Unknown error, but this shouldn't be happening.
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
		// Get activation data.
		$activation = $this->get_activation_data();

		// Check if we can deactivate.
		if ( ! $this->can_deactivate() ) {
			return new WP_Error( 'invalid_activation_data', __( 'Invalid activation data.', 'duckdev-freemius' ) );
		}

		// Prepare deactivation args.
		$args = array(
			'uid'         => $activation['activation_params']['uid'],
			'install_id'  => $activation['install_id'],
			'license_key' => $activation['activation_params']['license_key'],
			'url'         => get_site_url(),
		);

		// Remotely deactivate the license.
		$response = Api::get_instance( $this->plugin->get_id() )->post( 'deactivate.json', $args );
		// Request failed.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Save the data.
		if ( isset( $response['id'] ) ) {
			$activation['status'] = self::DEACTIVATED;
			// Remove the license key so it's not visible in the database.
			if ( ! empty( $activation['activation_params']['license_key'] ) ) {
				$activation['activation_params']['license_key'] = '';
			}

			// Update deactivation data.
			$success = $this->set_activation_data( $activation );

			/**
			 * Action hook to trigger after a plugin license is deactivated.
			 *
			 * @since 1.0.0
			 *
			 * @param array $activation Activation data.
			 * @param bool  $success    Was the update successful.
			 */
			do_action( 'duckdev_freemius_license_deactivated', $activation, $success );

			return $success;
		}

		// Unknown error, but this shouldn't be happening.
		return new WP_Error( 'unknown_error', __( 'Unknown error.', 'duckdev-freemius' ) );
	}

	/**
	 * Check if current license can be deactivated.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	protected function can_deactivate(): bool {
		$activation = $this->get_activation_data();

		// We need activation data.
		if ( empty( $activation ) ) {
			return false;
		}

		// Check for uid, install id & license key.
		if (
			empty( $activation['install_id'] ) ||
			empty( $activation['activation_params']['uid'] ) ||
			empty( $activation['activation_params']['license_key'] )
		) {
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
