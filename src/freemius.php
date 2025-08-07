<?php
/**
 * The Freemius SDK for Duck Dev plugins.
 *
 * This library does not perform any permission checks or nonce
 * verifications. Plugins should do it before processing any forms.
 *
 * References:
 * - https://github.com/Freemius/wp-sdk-lite
 * - https://github.com/gambitph/freemius-lite-activation
 *
 * @link    https://duckdev.com/
 * @license http://www.gnu.org/licenses/ GNU General Public License
 * @author  Joel James <me@joelsays.com>
 * @since   1.0.0
 */

namespace DuckDev\Freemius;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use DuckDev\Freemius\Data\Plugin;
use DuckDev\Freemius\Services\Addon;
use DuckDev\Freemius\Services\License;
use DuckDev\Freemius\Services\Update;

/**
 * Class Freemius.
 */
class Freemius {

	/**
	 * License manager service instance.
	 *
	 * @since 1.0.0
	 *
	 * @var License
	 */
	private License $license;

	/**
	 * Updates manager service instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Update
	 */
	private Update $update;

	/**
	 * Addons manager service instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Addon
	 */
	private Addon $addon;

	/**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Plugin ID.
	 * @param array $args Arguments.
	 */
	protected function __construct( int $id, array $args ) {
		// Create a plugin data instance.
		$plugin = new Plugin( $id, $args );
		// Create services.
		$this->license = new License( $plugin );
		$this->update  = new Update( $plugin );
		$this->addon   = new Addon( $plugin );
	}

	/**
	 * Get the singleton instance of the class.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Plugin ID.
	 * @param array $args Arguments.
	 *
	 * @return Freemius
	 */
	public static function get_instance( int $id, array $args ): Freemius {
		static $instances = array();

		// Create new instance only if doesn't exist.
		if ( ! isset( $instances[ $id ] ) || ! $instances[ $id ] instanceof Freemius ) {
			$instances[ $id ] = new self( $id, $args );
		}

		return $instances[ $id ];
	}

	/**
	 * Get the addons manager service instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Addon
	 */
	public function addon(): Addon {
		return $this->addon;
	}

	/**
	 * Get the license manager service instance.
	 *
	 * @since 1.0.0
	 *
	 * @return License
	 */
	public function license(): License {
		return $this->license;
	}

	/**
	 * Get the updates manager service instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Update
	 */
	public function update(): Update {
		return $this->update;
	}
}