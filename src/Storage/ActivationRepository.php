<?php
/**
 * Activation repository.
 *
 * Owns the WordPress option (`duckdev_freemius_activation_data`)
 * under which every host plugin's activation is persisted. Replaces
 * the `get_activation_data()` / `set_activation_data()` helpers that
 * used to live on the old `Service` base class — those helpers mixed
 * persistence with service logic and made the base class harder to
 * reuse.
 *
 * Activations are keyed by plugin ID inside the option so that a
 * single site running multiple Duck Dev premium plugins only needs
 * one option row.
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

use DuckDev\Freemius\Data\Activation;

/**
 * Class ActivationRepository.
 */
class ActivationRepository {

	/**
	 * Option key that stores activations for all Duck Dev plugins.
	 *
	 * @since 2.0.0
	 */
	const OPTION_KEY = 'duckdev_freemius_activation_data';

	/**
	 * Get the activation for a plugin.
	 *
	 * Always returns an {@see Activation} — callers should use
	 * {@see Activation::is_empty()} to detect the no-activation case
	 * rather than null-checking the result.
	 *
	 * @since 2.0.0
	 *
	 * @param int $plugin_id Freemius plugin ID.
	 *
	 * @return Activation
	 */
	public function get( int $plugin_id ): Activation {
		$all = get_option( self::OPTION_KEY, array() );

		return new Activation( $all[ $plugin_id ] ?? array() );
	}

	/**
	 * Persist an activation for a plugin.
	 *
	 * @since 2.0.0
	 *
	 * @param int        $plugin_id  Freemius plugin ID.
	 * @param Activation $activation Activation to persist.
	 *
	 * @return bool True when the underlying `update_option()` succeeded.
	 */
	public function save( int $plugin_id, Activation $activation ): bool {
		$all               = get_option( self::OPTION_KEY, array() );
		$all[ $plugin_id ] = $activation->to_array();

		return update_option( self::OPTION_KEY, $all );
	}

	/**
	 * Remove the activation for a plugin entirely.
	 *
	 * @since 2.0.0
	 *
	 * @param int $plugin_id Freemius plugin ID.
	 *
	 * @return bool
	 */
	public function clear( int $plugin_id ): bool {
		$all = get_option( self::OPTION_KEY, array() );
		unset( $all[ $plugin_id ] );

		return update_option( self::OPTION_KEY, $all );
	}
}
