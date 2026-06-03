<?php
/**
 * Cache contract.
 *
 * Abstraction over a per-plugin key/value cache. The default
 * implementation in this library wraps the WordPress transient API,
 * but services depend on the interface so a memory-only or mock
 * implementation can be substituted in tests.
 *
 * The interface also surfaces the throttle helpers used by services
 * to prevent excessive outbound HTTP traffic — {@see is_throttled()}
 * and {@see mark_requested()} replace the loose
 * `is_duplicate_request()` / `set_request_time()` helpers from the
 * pre-refactor base class.
 *
 * @link       https://duckdev.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      1.0.0
 * @package    Freemius
 * @subpackage Contracts
 */

namespace DuckDev\Freemius\Contracts;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

/**
 * Interface CacheInterface.
 */
interface CacheInterface {

	/**
	 * Retrieve a cached value.
	 *
	 * Implementations MUST return boolean false when the key is missing
	 * or expired so callers can rely on a strict `false === $value`
	 * comparison to detect a miss.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Cache key (unprefixed — the implementation prefixes it).
	 *
	 * @return mixed
	 */
	public function get( string $key );

	/**
	 * Persist a value in the cache.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key        Cache key (unprefixed).
	 * @param mixed  $value      Value to cache. Must be serializable.
	 * @param int    $expiration Expiration in seconds. 0 means "no expiration".
	 *
	 * @return bool True on success.
	 */
	public function set( string $key, $value, int $expiration = 0 ): bool;

	/**
	 * Delete a cached value.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Cache key (unprefixed).
	 *
	 * @return bool True when the entry existed and was removed.
	 */
	public function delete( string $key ): bool;

	/**
	 * Whether a throttle window keyed by $key is currently open.
	 *
	 * Used to short-circuit outbound requests when one has already been
	 * made recently. Always returns false until the matching
	 * {@see mark_requested()} call has been made.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Throttle key.
	 *
	 * @return bool
	 */
	public function is_throttled( string $key ): bool;

	/**
	 * Open a throttle window for the given key.
	 *
	 * After this call, {@see is_throttled()} returns true for the same
	 * key until the implementation's throttle window expires.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Throttle key.
	 *
	 * @return bool True on success.
	 */
	public function mark_requested( string $key ): bool;
}
