<?php
/**
 * Service contract.
 *
 * Every service registered with the {@see \DuckDev\Freemius\Freemius}
 * container implements this interface. Two responsibilities are
 * captured here: exposing the {@see \DuckDev\Freemius\Data\Plugin}
 * the service belongs to, and a deferred {@see ServiceInterface::boot()}
 * hook where WordPress filters/actions are registered.
 *
 * Hook registration is intentionally kept out of constructors so that
 * instantiating the container has no side effects — this makes the
 * library safe to wire up early in the request lifecycle and trivial
 * to test in isolation.
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

use DuckDev\Freemius\Data\Plugin;

/**
 * Interface ServiceInterface.
 *
 * Marks a class as a Freemius service that the container can manage.
 */
interface ServiceInterface {

	/**
	 * Get the plugin data instance the service belongs to.
	 *
	 * Useful when a caller has a service reference and needs to inspect
	 * the host plugin (slug, ID, premium flag, etc.) without going back
	 * through the container.
	 *
	 * @since 2.0.0
	 *
	 * @return Plugin
	 */
	public function get_plugin(): Plugin;

	/**
	 * Register WordPress hooks for the service.
	 *
	 * Called once by {@see \DuckDev\Freemius\Freemius::boot()} after
	 * every service has been constructed. Implementations may early
	 * return when there is nothing to register (for example when the
	 * host plugin is the free build).
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function boot(): void;
}
