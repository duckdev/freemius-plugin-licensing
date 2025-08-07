<?php
/**
 * Plugin data class.
 *
 * @link       https://duckdev.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      1.0.0
 * @package    Freemius
 * @subpackage Data
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
	 * Plugin public key.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $public_key = '';

	/**
	 * Is a premium plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	private bool $is_premium;

	/**
	 * Has addons.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	private bool $has_addons;

	/**
	 * Plugin data.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private array $data = array();

	/**
	 * Plugin class constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Plugin ID.
	 * @param array $args Arguments.
	 *
	 * @return void
	 */
	public function __construct( int $id, array $args ) {
		$this->id         = $id;
		$this->slug       = $args['slug'] ?? '';
		$this->is_premium = $args['is_premium'] ?? false;
		$this->has_addons = $args['has_addons'] ?? false;
		$this->main_file  = $args['main_file'] ?? '';
		$this->public_key = $args['public_key'] ?? '';
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

	/**
	 * Gets the plugin public key.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_public_key(): string {
		return $this->public_key;
	}

	/**
	 * Check if current plugin is premium.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_premium(): bool {
		return $this->is_premium;
	}

	/**
	 * Check if current plugin has addons.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function has_addons(): bool {
		return $this->has_addons;
	}

	/**
	 * Get current plugin data.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_data(): array {
		if ( empty( $this->data ) ) {
			if ( ! function_exists( '\get_plugin_data' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}

			$this->data = \get_plugin_data( $this->main_file );
		}

		return $this->data;
	}
}
