<?php
/**
 * Plugin data value object.
 *
 * Immutable representation of the host WordPress plugin that the
 * library is acting on behalf of. Constructed once by the
 * {@see \DuckDev\Freemius\Freemius} container from the arguments
 * passed to `get_instance()` and then handed to every service.
 *
 * Accepts a small associative arguments array rather than a long
 * positional constructor:
 *
 * - `slug`       (string)  Unique Freemius slug for the plugin.
 * - `main_file`  (string)  Absolute path to the plugin's main PHP file.
 * - `public_key` (string)  Freemius public key (`pk_…`) used for FSP auth.
 * - `is_premium` (bool)    Whether this build is the premium build.
 *                          Update hooks only register when true.
 * - `has_addons` (bool)    Whether the product has addons to list.
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
	 * Freemius product ID.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * Freemius product slug.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Absolute path to the plugin's main PHP file.
	 *
	 * Required by WordPress to look up plugin headers and compute
	 * `plugin_basename()`.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private string $main_file;

	/**
	 * Freemius public key (`pk_…`).
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private string $public_key;

	/**
	 * Whether this is the premium build of the plugin.
	 *
	 * Used by services to decide whether to register update hooks or
	 * accept license activation requests.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	private bool $is_premium;

	/**
	 * Whether the plugin has addons to list.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	private bool $has_addons;

	/**
	 * Cached output of `get_plugin_data()` for the main file.
	 *
	 * Populated lazily on first call to {@see get_data()}.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private array $data = array();

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $id   Freemius product ID.
	 * @param array $args Plugin args (see class docblock for the schema).
	 */
	public function __construct( int $id, array $args ) {
		$this->id         = $id;
		$this->slug       = (string) ( $args['slug'] ?? '' );
		$this->main_file  = (string) ( $args['main_file'] ?? '' );
		$this->public_key = (string) ( $args['public_key'] ?? '' );
		$this->is_premium = (bool) ( $args['is_premium'] ?? false );
		$this->has_addons = (bool) ( $args['has_addons'] ?? false );
	}

	/**
	 * Get the Freemius product ID.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * Get the Freemius product slug.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Get the absolute path to the plugin's main file.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_main_file(): string {
		return $this->main_file;
	}

	/**
	 * Get the Freemius public key.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_public_key(): string {
		return $this->public_key;
	}

	/**
	 * Whether the host plugin is the premium build.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_premium(): bool {
		return $this->is_premium;
	}

	/**
	 * Whether the host plugin has addons to list.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function has_addons(): bool {
		return $this->has_addons;
	}

	/**
	 * Read the plugin headers using WordPress's `get_plugin_data()`.
	 *
	 * The result is cached on the instance after the first read. The
	 * helper from `wp-admin/includes/plugin.php` is loaded on demand
	 * because it isn't available on front-end requests by default.
	 *
	 * @since 2.0.0
	 *
	 * @return array Plugin headers as returned by `get_plugin_data()`.
	 */
	public function get_data(): array {
		if ( empty( $this->data ) ) {
			if ( ! function_exists( '\get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$this->data = \get_plugin_data( $this->main_file );
		}

		return $this->data;
	}
}
