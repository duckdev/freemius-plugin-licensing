<?php
/**
 * Transient-backed cache implementation.
 *
 * Default {@see \DuckDev\Freemius\Contracts\CacheInterface} for the
 * library. Each instance is scoped to one host plugin, so cache
 * collisions between multiple Duck Dev plugins on the same site are
 * impossible — every key is prefixed with `duckdev_freemius_{id}_`.
 *
 * Services receive a `CacheInterface` (not this concrete class), so
 * tests can swap in a memory implementation without touching WP.
 *
 * @link       https://duckdev.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      1.0.0
 * @package    Freemius
 * @subpackage Storage
 */

namespace DuckDev\Freemius\Storage;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use DuckDev\Freemius\Contracts\CacheInterface;
use DuckDev\Freemius\Data\Plugin;

/**
 * Class TransientCache.
 */
class TransientCache implements CacheInterface {

	/**
	 * Plugin instance the cache is scoped to.
	 *
	 * @since 2.0.0
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Throttle window length in seconds.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private int $throttle_window;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Plugin $plugin          Plugin instance the cache is scoped to.
	 * @param int    $throttle_window Throttle window in seconds. Defaults to
	 *                                5 minutes when 0 or negative.
	 */
	public function __construct( Plugin $plugin, int $throttle_window = 0 ) {
		$this->plugin          = $plugin;
		$this->throttle_window = $throttle_window > 0 ? $throttle_window : 5 * MINUTE_IN_SECONDS;
	}

	/**
	 * Build a plugin-scoped transient key from a caller-supplied key.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Caller key (un-prefixed).
	 *
	 * @return string Fully prefixed transient key.
	 */
	private function build_key( string $key ): string {
		return 'duckdev_freemius_' . $this->plugin->get_id() . '_' . $key;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function get( string $key ) {
		return get_transient( $this->build_key( $key ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function set( string $key, $value, int $expiration = 0 ): bool {
		return (bool) set_transient( $this->build_key( $key ), $value, $expiration );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function delete( string $key ): bool {
		return (bool) delete_transient( $this->build_key( $key ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function is_throttled( string $key ): bool {
		return false !== $this->get( $key );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function mark_requested( string $key ): bool {
		return $this->set( $key, time(), $this->throttle_window );
	}
}
