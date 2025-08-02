<?php
/**
 * The Freemius SDK for Duck Dev plugins.
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
	 * Class constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $id   Plugin ID.
	 * @param string $slug Plugin slug.
	 * @param string $file Plugin file.
	 * @param array  $args Arguments.
	 */
	protected function __construct( int $id, string $slug, string $file, array $args = array() ) {
		$plugin        = new Plugin( $id, $slug, $file );
		$this->license = new License( $plugin );
		$this->update  = new Update( $plugin );
	}

	/**
	 * Get the singleton instance of the class.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $id   Plugin ID.
	 * @param string $slug Plugin slug.
	 * @param string $file Plugin file.
	 * @param array  $args Arguments.
	 *
	 * @return Freemius
	 */
	public static function get_instance( int $id, string $slug, string $file, array $args = array() ): Freemius {
		static $instances = array();

		// Create new instance only if doesn't exist.
		if ( ! isset( $instances[ $id ] ) || ! $instances[ $id ] instanceof Freemius ) {
			$instances[ $id ] = new self( $id, $slug, $file, $args );
		}

		return $instances[ $id ];
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