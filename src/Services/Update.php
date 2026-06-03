<?php
/**
 * Plugin update service.
 *
 * Wires the host plugin into the WordPress update pipeline so that
 * premium builds can be installed and upgraded directly from the
 * WP-Admin Plugins screen. Three filters/actions are registered:
 *
 * - `plugins_api`                  — supplies the "view details" payload.
 * - `site_transient_update_plugins` — announces available updates.
 * - `upgrader_process_complete`    — purges the cache after an update runs.
 *
 * Hooks are attached inside {@see boot()} rather than the constructor,
 * which means simply instantiating the container has no side effects.
 *
 * Updates are gated on three things: the host plugin must be the
 * premium build, the license must be active, and the API must return
 * a newer version that is compatible with the current WP / PHP runtime.
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
use DuckDev\Freemius\Storage\ActivationRepository;
use stdClass;
use WP_Error;

/**
 * Class Update.
 */
class Update extends AbstractService {

	/**
	 * Repository used to read the current activation.
	 *
	 * @since 2.0.0
	 *
	 * @var ActivationRepository
	 */
	private ActivationRepository $activations;

	/**
	 * Cache used to throttle and memoise API calls.
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
	 * @param Plugin               $plugin      Plugin instance.
	 * @param ActivationRepository $activations Activation repository.
	 * @param CacheInterface       $cache       Cache.
	 * @param ApiFactory           $api_factory API factory.
	 */
	public function __construct(
		Plugin $plugin,
		ActivationRepository $activations,
		CacheInterface $cache,
		ApiFactory $api_factory
	) {
		parent::__construct( $plugin );

		$this->activations = $activations;
		$this->cache       = $cache;
		$this->api_factory = $api_factory;
	}

	/**
	 * Register update-related hooks.
	 *
	 * Only attaches when the host plugin is the premium build — the
	 * free build does not consume the Freemius update API.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( ! $this->plugin->is_premium() ) {
			return;
		}

		add_filter( 'plugins_api', array( $this, 'plugins_api_filter' ), 10, 3 );
		add_filter( 'site_transient_update_plugins', array( $this, 'plugin_updates_transient' ) );
		add_action( 'upgrader_process_complete', array( $this, 'purge_plugin' ), 10, 2 );
	}

	/**
	 * Get the latest update payload for the plugin.
	 *
	 * Cached for a day. Pass `$force = true` (or visit any page with
	 * `?force-check=1`, the WordPress convention) to bypass the cache
	 * and re-fetch.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $force Whether to bypass the cache.
	 *
	 * @return array|\WP_Error Decoded update payload, or an empty array
	 *                        when the API returned an error.
	 */
	public function get_update_data( bool $force = false ) {
		// Honour `?force-check=1` on the updates screen.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['force-check'] ) ) {
			$force = true;
		}

		if ( $force ) {
			$this->cache->delete( 'update_data' );
		}

		$update_data = $this->cache->get( 'update_data' );
		if ( false === $update_data ) {
			$update_data = $this->get_remote_latest();
			if ( is_wp_error( $update_data ) ) {
				// Don't cache failures — a transient error (throttle, network blip,
				// license-not-yet-active) would otherwise suppress updates for a
				// full day. Callers already handle WP_Error as "no update".
				return $update_data;
			}

			$this->cache->set( 'update_data', $update_data, DAY_IN_SECONDS );
		}

		return $update_data;
	}

	/**
	 * Get the product info payload (banners, description).
	 *
	 * Cached for a day on success.
	 *
	 * @since 2.0.0
	 *
	 * @return array|\WP_Error
	 */
	public function get_plugin_info() {
		$info = $this->cache->get( 'plugin_info' );

		if ( false === $info || empty( $info ) ) {
			$info = $this->get_remote_plugin_info();
			if ( ! is_wp_error( $info ) ) {
				$this->cache->set( 'plugin_info', $info, DAY_IN_SECONDS );
			}
		}

		return $info;
	}

	/**
	 * Purge the update cache once this plugin has been upgraded.
	 *
	 * Bound to `upgrader_process_complete`. The check ensures we only
	 * purge when the upgrader actually touched THIS plugin.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $upgrader   Plugin upgrader. Unused.
	 * @param array $hook_extra Hook context from WordPress.
	 *
	 * @return void
	 */
	public function purge_plugin( $upgrader, array $hook_extra ): void {
		unset( $upgrader );

		if ( ! isset( $hook_extra['action'], $hook_extra['type'] ) ) {
			return;
		}

		if ( 'update' !== $hook_extra['action'] || 'plugin' !== $hook_extra['type'] ) {
			return;
		}

		$plugin_base_name = plugin_basename( $this->plugin->get_main_file() );
		$updated_plugins  = $hook_extra['plugins'] ?? array();

		if ( in_array( $plugin_base_name, $updated_plugins, true ) ) {
			$this->cache->delete( 'update_data' );
		}
	}

	/**
	 * Hook callback for `plugins_api`.
	 *
	 * Produces the "View details" payload shown in the modal on the
	 * Plugins screen. Returns the original `$data` argument untouched
	 * when the request is not for this plugin.
	 *
	 * @since 2.0.0
	 *
	 * @param false|object|array $data   Existing payload from upstream filters.
	 * @param string             $action The current `plugins_api` action.
	 * @param object|null        $args   Arguments from the API call.
	 *
	 * @return object|array|false
	 */
	public function plugins_api_filter( $data, string $action = '', ?object $args = null ) {
		// Only respond to the plugin information action with a slug.
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) ) {
			return $data;
		}

		// Bail out when it isn't our plugin.
		if ( $this->plugin->get_slug() !== $args->slug ) {
			return $data;
		}

		// Only do this for activated plugins.
		if ( ! $this->is_activated() ) {
			return $data;
		}

		$plugin_data = $this->plugin->get_data();
		$update_data = $this->get_update_data();

		if ( empty( $update_data ) || is_wp_error( $update_data ) ) {
			return $data;
		}

		$plugin_info = $this->get_plugin_info();
		if ( is_wp_error( $plugin_info ) || empty( $plugin_info ) ) {
			$plugin_info = array();
		}

		$data                = $args;
		$data->name          = $plugin_data['Name'] ?? '';
		$data->author        = $plugin_data['Author'] ?? '';
		$data->version       = $update_data['version'] ?? '';
		$data->last_updated  = ! empty( $update_data['updated'] )
			? $update_data['updated']
			: ( $update_data['created'] ?? '' );
		$data->requires      = $update_data['requires_platform_version'] ?? '';
		$data->requires_php  = $update_data['requires_programming_language_version'] ?? '';
		$data->tested        = $update_data['tested_up_to_version'] ?? '';
		$data->download_link = $update_data['url'] ?? '';
		$data->banners       = array(
			'high' => $plugin_info['banner_url'] ?? '',
			'low'  => $plugin_info['card_banner_url'] ?? '',
		);
		$data->sections      = wp_parse_args(
			$update_data['readme']['sections'] ?? array(),
			array(
				'description' => $plugin_info['description'] ?? 'Upgrade ' . ( $plugin_data['Name'] ?? '' ) . ' to latest.',
			)
		);

		return $data;
	}

	/**
	 * Hook callback for `site_transient_update_plugins`.
	 *
	 * Injects our plugin's update info into the standard WP transient
	 * so the core Plugins screen offers the upgrade.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $transient Existing transient value.
	 *
	 * @return mixed Mutated transient.
	 */
	public function plugin_updates_transient( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		if ( ! $this->is_activated() ) {
			return $transient;
		}

		$plugin_data = $this->plugin->get_data();
		$update_data = $this->get_update_data();

		if (
			! empty( $update_data )
			&& ! is_wp_error( $update_data )
			&& isset(
				$update_data['version'],
				$update_data['requires_platform_version'],
				$update_data['requires_programming_language_version'],
				$update_data['url']
			)
			&& version_compare( $plugin_data['Version'] ?? '0', $update_data['version'], '<' )
			&& version_compare( $update_data['requires_platform_version'], get_bloginfo( 'version' ), '<=' )
			&& version_compare( $update_data['requires_programming_language_version'], PHP_VERSION, '<=' )
		) {
			$res              = new stdClass();
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
	 * Fetch the latest version payload from the Freemius API.
	 *
	 * Throttled to one request per cache throttle window (5 minutes
	 * by default). Requires an active license — returns a WP_Error
	 * otherwise so the caller can cache an empty result and avoid
	 * re-hitting the API on every page load.
	 *
	 * @since 2.0.0
	 *
	 * @return array|\WP_Error
	 */
	protected function get_remote_latest() {
		if ( $this->cache->is_throttled( 'update_check' ) ) {
			return new WP_Error( 'too_many_requests', __( 'Too many requests. Slow down.', 'duckdev-freemius' ) );
		}

		$activation = $this->activations->get( $this->plugin->get_id() );
		if ( ! $activation->is_active() ) {
			return new WP_Error( 'license_not_active', __( 'No valid license is active.', 'duckdev-freemius' ) );
		}

		$plugin_data = $this->plugin->get_data();

		$api     = $this->api_factory->make_for_install( $activation->install_id(), $activation->api_keys() );
		$updates = $api->get(
			'updates/latest.json',
			array(
				'readme'     => true,
				'newer_than' => $plugin_data['Version'] ?? '',
			)
		);

		$this->cache->mark_requested( 'update_check' );

		return $updates;
	}

	/**
	 * Fetch the product info payload from the Freemius API.
	 *
	 * @since 2.0.0
	 *
	 * @return array|\WP_Error
	 */
	protected function get_remote_plugin_info() {
		$api = $this->api_factory->make_for_plugin( $this->plugin );

		return $api->get( 'info.json' );
	}

	/**
	 * Whether the host plugin currently has an active license.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function is_activated(): bool {
		return $this->activations->get( $this->plugin->get_id() )->is_active();
	}
}
