<?php
/**
 * Activation value object.
 *
 * Wraps the activation array stored in the WordPress options table
 * by {@see \DuckDev\Freemius\Storage\ActivationRepository}. Before
 * this object existed, every service reached into the same loose
 * string keys (`install_id`, `activation_params.uid`,
 * `install_data.install_public_key` …) — a typo in any of those
 * silently broke licensing. Centralising the shape here means a
 * single change ripples cleanly through every consumer.
 *
 * The class is intentionally immutable: mutations are expressed as
 * "with-er" methods ({@see with()}, {@see with_scrubbed_license()})
 * which return new instances. The repository is responsible for
 * persistence.
 *
 * The persisted array layout, for reference:
 *
 *     [
 *         'install_id'        => 12345,
 *         'date'              => 'Y-m-d H:i:s',
 *         'status'            => 'activated' | 'deactivated',
 *         'activation_params' => [
 *             'license_key' => 'XXXX-XXXX-XXXX',
 *             'uid'         => '<md5 site uid>',
 *             'url'         => 'https://example.com',
 *             'version'     => '1.0.0',
 *             'install_id'  => 12345, // present after first activation
 *         ],
 *         'install_data'      => [ // raw API response
 *             'install_public_key' => 'pk_…',
 *             'install_secret_key' => 'sk_…',
 *             …
 *         ],
 *     ]
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
 * Class Activation.
 */
class Activation {

	/**
	 * Active status string persisted in the activation array.
	 *
	 * @since 2.0.0
	 */
	const STATUS_ACTIVATED = 'activated';

	/**
	 * Inactive status string persisted in the activation array.
	 *
	 * @since 2.0.0
	 */
	const STATUS_DEACTIVATED = 'deactivated';

	/**
	 * Underlying activation array.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private array $data;

	/**
	 * Constructor.
	 *
	 * Accepts an empty array to represent "no activation yet" — see
	 * {@see is_empty()} for the inverse check.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data Raw activation array.
	 */
	public function __construct( array $data = array() ) {
		$this->data = $data;
	}

	/**
	 * Named constructor for clarity at call sites.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data Raw activation array.
	 *
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self( $data );
	}

	/**
	 * Return the underlying array. Used by the repository for persistence.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function to_array(): array {
		return $this->data;
	}

	/**
	 * Install ID returned by the Freemius API at activation time.
	 *
	 * @since 2.0.0
	 *
	 * @return string Empty string when no install has been created.
	 */
	public function install_id(): string {
		return (string) ( $this->data['install_id'] ?? '' );
	}

	/**
	 * License key as currently stored.
	 *
	 * Note: the key is blanked from storage on deactivation, see
	 * {@see with_scrubbed_license()}.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function license_key(): string {
		return (string) ( $this->data['activation_params']['license_key'] ?? '' );
	}

	/**
	 * Deterministic UID of the site this activation belongs to.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function uid(): string {
		return (string) ( $this->data['activation_params']['uid'] ?? '' );
	}

	/**
	 * Activation status, one of {@see STATUS_ACTIVATED} or {@see STATUS_DEACTIVATED}.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function status(): string {
		return (string) ( $this->data['status'] ?? '' );
	}

	/**
	 * Activation timestamp (formatted as `Y-m-d H:i:s`).
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function date(): string {
		return (string) ( $this->data['date'] ?? '' );
	}

	/**
	 * Raw activation parameters originally sent to the API.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function activation_params(): array {
		return $this->data['activation_params'] ?? array();
	}

	/**
	 * Raw install data as returned by the Freemius API.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function install_data(): array {
		return $this->data['install_data'] ?? array();
	}

	/**
	 * Build an {@see ApiKeys} pair from the persisted install data.
	 *
	 * Returns an empty pair if the install data is incomplete — call
	 * {@see ApiKeys::is_signable()} on the result to check.
	 *
	 * @since 2.0.0
	 *
	 * @return ApiKeys
	 */
	public function api_keys(): ApiKeys {
		$install = $this->install_data();

		return new ApiKeys(
			(string) ( $install['install_public_key'] ?? '' ),
			(string) ( $install['install_secret_key'] ?? '' )
		);
	}

	/**
	 * Whether the activation has the identifying fields needed to
	 * deactivate or to authenticate install-scoped API calls.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function has_required_keys(): bool {
		return '' !== $this->install_id()
			&& '' !== $this->license_key()
			&& '' !== $this->uid();
	}

	/**
	 * Whether the activation represents a currently-active license.
	 *
	 * Requires the identifying fields to be present and the status to
	 * be {@see STATUS_ACTIVATED}.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->has_required_keys()
			&& self::STATUS_ACTIVATED === $this->status();
	}

	/**
	 * Whether nothing has been stored for the plugin yet.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->data );
	}

	/**
	 * Return a new instance with the supplied top-level keys overridden.
	 *
	 * @since 2.0.0
	 *
	 * @param array $changes Associative array of changes.
	 *
	 * @return self
	 */
	public function with( array $changes ): self {
		return new self( array_merge( $this->data, $changes ) );
	}

	/**
	 * Return a new instance with the license key blanked.
	 *
	 * Called on deactivation so the key is not left visible in the
	 * options table while still preserving the rest of the
	 * activation context for diagnostics.
	 *
	 * @since 2.0.0
	 *
	 * @return self
	 */
	public function with_scrubbed_license(): self {
		$data = $this->data;
		if ( ! empty( $data['activation_params']['license_key'] ) ) {
			$data['activation_params']['license_key'] = '';
		}

		return new self( $data );
	}
}
