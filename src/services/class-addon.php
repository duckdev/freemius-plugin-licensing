<?php
/**
 * This class handles addons data.
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
use WP_Error;

/**
 * Class Addon
 */
class Addon extends Service {

	/**
	 * Get the list of addons.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force Should force update cache.
	 *
	 * @return array
	 */
	public function get_addons( bool $force = false ): array {
		// Only if current plugin has addons.
		if ( ! $this->plugin->has_addons() ) {
			return array();
		}

		// Get from cache first.
		if ( ! $force ) {
			$addons = $this->get_transient( 'addons' );
			// If found is cache, return it.
			if ( false !== $addons ) {
				return $addons;
			}
		}

		// Get from the API.
		$addons = $this->get_remote_addons();

		if ( ! is_wp_error( $addons ) ) {
			// Format the data.
			$addons = array_map( array( $this, 'format_addon_data' ), $addons );
			// Save to cache.
			$this->set_transient( 'addons', $addons );

			return $addons;
		}

		return array();
	}

	/**
	 * Get the list of addons from the API.
	 *
	 * @since 1.0.0
	 *
	 * @return array|WP_Error
	 */
	protected function get_remote_addons() {
		// Avoid multiple requests.
		if ( $this->is_duplicate_request( 'addons_check' ) ) {
			return new WP_Error( 'too_many_requests', __( 'Too many requests. Slow down.', 'duckdev-freemius' ) );
		}

		// Get authenticated API instance using public key.
		$api = Api::get_auth_instance(
			$this->plugin->get_id(),
			$this->plugin->get_public_key(),
			$this->plugin->get_public_key(), // Use public key again for secret key to use public key encryption.
			'plugin'
		);

		// Addon list from the API.
		$response = $api->get(
			'addons.json',
			array(
				'enriched'     => true, // Get addon info.
				'show_pending' => false, // Get only released addons.
			)
		);

		// To prevent multiple requests for 5 mins.
		$this->set_request_time( 'addons_check' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['plugins'] ?? array();
	}

	/**
	 * Format the addon list to add additional data.
	 *
	 * @since 1.0.0
	 *
	 * @return array|string|WP_Error
	 */
	protected function format_addon_data( $addon ): array {
		// Add checkout link.
		$addon['link'] = "https://checkout.freemius.com/plugin/{$addon['id']}";
		// Premium if pricing is visible.
		$addon['is_premium'] = $addon['is_pricing_visible'] ?? false;

		/**
		 * Filter to modify addon data.
		 *
		 * @since 1.0.0
		 *
		 * @param array $addon Addon data.
		 * @param Addon $this  Current class instance.
		 */
		return apply_filters( 'duckdev_freemius_format_addon_data', $addon, $this );
	}
}
