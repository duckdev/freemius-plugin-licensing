<?php
/**
 * Plugin data class.
 *
 * @link       https://duckdev.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      1.0.0
 */

namespace DuckDev\Freemius\Data;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

/**
 * Class Plugin.
 */
class Plugin {

	/**
	 * Plugin ID.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * Plugin slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Plugin main file.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $main_file;

	/**
	 * Plugin class constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $id        Plugin ID.
	 * @param string $slug      Plugin slug.
	 * @param string $main_file Plugin main file.
	 *
	 * @return void
	 */
	public function __construct( int $id, string $slug, string $main_file ) {
		$this->id        = $id;
		$this->slug      = $slug;
		$this->main_file = $main_file;
	}

	/**
	 * Gets the plugin ID.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * Gets the plugin slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Gets the plugin main file.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_main_file(): string {
		return $this->main_file;
	}
}
