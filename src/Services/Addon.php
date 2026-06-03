<?php
/**
 * Addons listing service.
 *
 * Fetches the addon list for the host plugin from the Freemius API
 * and enriches each entry with the Freemius checkout link and a
 * derived `is_premium` flag. Output is cached for a day and the
 * outbound request is throttled.
 *
 * The service does not register any WordPress hooks — addons are
 * fetched lazily from the host plugin's admin UI when needed.
 *
 * @link       https://duckdev.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      1.0.0
 * @package    Freemius
 * @subpackage Services
 */

namespace DuckDev\Freemius\Services;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use DuckDev\Freemius\Api\ApiFactory;
use DuckDev\Freemius\Contracts\CacheInterface;
use DuckDev\Freemius\Data\Plugin;
use WP_Error;

/**
 * Class Addon.
 */
class Addon extends AbstractService {

	/**
	 * Prefix for the Freemius hosted checkout page.
	 *
	 * The addon ID is appended at format time. Extracted as a
	 * constant so it is easy to find when Freemius changes the
	 * checkout domain or path.
	 *
	 * @since 2.0.0
	 */
	const CHECKOUT_URL = 'https://checkout.freemius.com/plugin/';

	/**
	 * Cache used to memoise the addon list and throttle API calls.
	 *
	 * @since 2.0.0
	 *
	 * @var CacheInterface
	 */
	private CacheInterface $cache;

	/**
	 * Factory used to obtain API clients.
	 *
	 * @since 2.0.0
	 *
	 * @var ApiFactory
	 */
	private ApiFactory $api_factory;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Plugin         $plugin      Plugin instance.
	 * @param CacheInterface $cache       Cache.
	 * @param ApiFactory     $api_factory API factory.
	 */
	public function __construct( Plugin $plugin, CacheInterface $cache, ApiFactory $api_factory ) {
		parent::__construct( $plugin );

		$this->cache       = $cache;
		$this->api_factory = $api_factory;
	}

	/**
	 * Get the list of addons for the host plugin.
	 *
	 * Returns an empty array (rather than a WP_Error) when:
	 * - the host plugin does not declare `has_addons`, or
	 * - the API request fails with no cached catalog to fall back on.
	 *
	 * Use `$force = true` to bypass the cache when the user explicitly
	 * asks for a refresh from the host plugin's UI. A forced call that
	 * the throttle blocks (or that the API rejects) transparently falls
	 * back to the cached catalog — the host UI keeps showing the
	 * last-known list instead of emptying out.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $force Whether to bypass the cache.
	 *
	 * @return array List of addons (associative arrays).
	 */
	public function get_addons( bool $force = false ): array {
		if ( ! $this->plugin->has_addons() ) {
			return array();
		}

		$cached = $this->cache->get( 'addons' );
		$cached = ( false !== $cached && is_array( $cached ) ) ? $cached : array();

		if ( ! $force && ! empty( $cached ) ) {
			return $cached;
		}

		$addons = $this->get_remote_addons();
		if ( is_wp_error( $addons ) ) {
			return $cached;
		}

		$addons = array_map( array( $this, 'format_addon_data' ), $addons );
		$this->cache->set( 'addons', $addons, DAY_IN_SECONDS );

		return $addons;
	}

	/**
	 * Fetch the raw addon list from the Freemius API.
	 *
	 * Uses the plugin-scoped signed client (FSP encoding) since this
	 * endpoint is reachable with only the public key.
	 *
	 * @since 2.0.0
	 *
	 * @return array|\WP_Error List of addons or WP_Error on failure / throttle.
	 */
	protected function get_remote_addons() {
		if ( $this->cache->is_throttled( 'addons_check' ) ) {
			return new WP_Error( 'too_many_requests', __( 'Too many requests. Slow down.', 'duckdev-freemius' ) );
		}

		$api      = $this->api_factory->make_for_plugin( $this->plugin );
		$response = $api->get(
			'addons.json',
			array(
				'enriched'     => true,
				'show_pending' => false,
			)
		);

		$this->cache->mark_requested( 'addons_check' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['plugins'] ?? array();
	}

	/**
	 * Enrich a single addon entry with computed fields.
	 *
	 * Adds:
	 * - `link`       — Freemius checkout URL for the addon.
	 * - `is_premium` — boolean mirror of `is_pricing_visible`.
	 *
	 * @since 2.0.0
	 *
	 * @param array $addon Raw addon entry from the API.
	 *
	 * @return array
	 */
	protected function format_addon_data( array $addon ): array {
		$addon['link']       = self::CHECKOUT_URL . ( $addon['id'] ?? '' );
		$addon['is_premium'] = $addon['is_pricing_visible'] ?? false;

		/**
		 * Filter the formatted addon data before it is returned / cached.
		 *
		 * @since 2.0.0
		 *
		 * @param array $addon Addon data.
		 * @param Addon $self  Current service instance.
		 */
		return apply_filters( 'duckdev_freemius_format_addon_data', $addon, $this );
	}
}
