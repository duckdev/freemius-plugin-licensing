<?php
/**
 * License manager service.
 *
 * Provides the activate / deactivate flow against the Freemius API.
 * Reads and writes the persisted state through
 * {@see \DuckDev\Freemius\Storage\ActivationRepository}, and
 * computes the host site UID through
 * {@see \DuckDev\Freemius\Support\SiteIdentity}.
 *
 * The service does NOT perform permission checks or nonce
 * verification — host plugins are expected to do that before
 * forwarding form input to {@see activate()} / {@see deactivate()}.
 *
 * @link       https://duckdev.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      1.0.0
 * @package    Freemius
 * @subpackage Services
 */

namespace DuckDev\Freemius\Services;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use DateTime;
use DuckDev\Freemius\Api\ApiFactory;
use DuckDev\Freemius\Data\Activation;
use DuckDev\Freemius\Data\Plugin;
use DuckDev\Freemius\Storage\ActivationRepository;
use DuckDev\Freemius\Support\SiteIdentity;
use WP_Error;

/**
 * Class License.
 */
class License extends AbstractService {

	/**
	 * Repository used to read and persist the activation.
	 *
	 * @since 2.0.0
	 *
	 * @var ActivationRepository
	 */
	private ActivationRepository $activations;

	/**
	 * Factory used to obtain API clients.
	 *
	 * @since 2.0.0
	 *
	 * @var ApiFactory
	 */
	private ApiFactory $api_factory;

	/**
	 * Helper used to compute the current site's UID.
	 *
	 * @since 2.0.0
	 *
	 * @var SiteIdentity
	 */
	private SiteIdentity $site;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Plugin               $plugin      Plugin instance.
	 * @param ActivationRepository $activations Activation repository.
	 * @param ApiFactory           $api_factory API factory.
	 * @param SiteIdentity         $site        Site identity helper.
	 */
	public function __construct(
		Plugin $plugin,
		ActivationRepository $activations,
		ApiFactory $api_factory,
		SiteIdentity $site
	) {
		parent::__construct( $plugin );

		$this->activations = $activations;
		$this->api_factory = $api_factory;
		$this->site        = $site;
	}

	/**
	 * Get the current activation for the host plugin.
	 *
	 * Always returns an {@see Activation} — empty when nothing is
	 * stored yet.
	 *
	 * @since 2.0.0
	 *
	 * @return Activation
	 */
	public function get_activation(): Activation {
		return $this->activations->get( $this->plugin->get_id() );
	}

	/**
	 * Activate a license key for the current site.
	 *
	 * The site UID and current plugin version are sent along with the
	 * key. On a successful response the install ID is persisted so
	 * subsequent activate calls reuse the same install.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key License key supplied by the user.
	 *
	 * @return bool|\WP_Error True/false from the underlying option update
	 *                       on success; WP_Error on validation or API failure.
	 */
	public function activate( string $key ) {
		if ( '' === $key ) {
			return new WP_Error( 'empty_activation_key', __( 'License key is empty.', 'duckdev-freemius' ) );
		}

		// Only premium plugins require a license.
		if ( ! $this->plugin->is_premium() ) {
			return new WP_Error( 'not_premium', __( 'Not a premium plugin.', 'duckdev-freemius' ) );
		}

		$plugin_data = $this->plugin->get_data();

		$args = array(
			'license_key' => $key,
			'uid'         => $this->site->get_uid(),
			'url'         => get_site_url(),
			'version'     => $plugin_data['Version'] ?? '',
		);

		// Reuse the install ID if we already have one — prevents the
		// API from creating a duplicate install entry on re-activation.
		$activation = $this->get_activation();
		if ( '' !== $activation->install_id() ) {
			$args['install_id'] = $activation->install_id();
		} else {
			$activation = new Activation();
		}

		$api      = $this->api_factory->make_public( (string) $this->plugin->get_id(), 'plugin' );
		$response = $api->post( 'activate.json', $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response['install_id'] ) ) {
			$activation = $activation->with(
				array(
					'activation_params' => $args,
					'install_id'        => $response['install_id'],
					'date'              => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
					'status'            => Activation::STATUS_ACTIVATED,
					'install_data'      => $response,
				)
			);

			$success = $this->activations->save( $this->plugin->get_id(), $activation );

			/**
			 * Fires after a plugin license is activated.
			 *
			 * @since 2.0.0
			 *
			 * @param array $activation Activation data array.
			 * @param bool  $success    Whether the option update succeeded.
			 */
			do_action( 'duckdev_freemius_license_activated', $activation->to_array(), $success );

			return $success;
		}

		return new WP_Error( 'unknown_error', __( 'Unknown error.', 'duckdev-freemius' ) );
	}

	/**
	 * Deactivate the current license.
	 *
	 * Refuses to proceed when the stored activation does not include
	 * the identifying fields, or when the site UID has changed — that
	 * happens when an activated database is moved to a different URL,
	 * in which case the new site is treated as not licensed rather
	 * than silently freeing the seat at the original host.
	 *
	 * @since 2.0.0
	 *
	 * @return bool|\WP_Error
	 */
	public function deactivate() {
		$activation = $this->get_activation();

		if ( ! $this->can_deactivate( $activation ) ) {
			return new WP_Error( 'invalid_activation_data', __( 'Invalid activation data.', 'duckdev-freemius' ) );
		}

		$args = array(
			'uid'         => $activation->uid(),
			'install_id'  => $activation->install_id(),
			'license_key' => $activation->license_key(),
			'url'         => get_site_url(),
		);

		$api      = $this->api_factory->make_public( (string) $this->plugin->get_id(), 'plugin' );
		$response = $api->post( 'deactivate.json', $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response['id'] ) ) {
			$activation = $activation
				->with( array( 'status' => Activation::STATUS_DEACTIVATED ) )
				->with_scrubbed_license();

			$success = $this->activations->save( $this->plugin->get_id(), $activation );

			/**
			 * Fires after a plugin license is deactivated.
			 *
			 * @since 2.0.0
			 *
			 * @param array $activation Activation data array.
			 * @param bool  $success    Whether the option update succeeded.
			 */
			do_action( 'duckdev_freemius_license_deactivated', $activation->to_array(), $success );

			return $success;
		}

		return new WP_Error( 'unknown_error', __( 'Unknown error.', 'duckdev-freemius' ) );
	}

	/**
	 * Whether the given activation may be deactivated from this site.
	 *
	 * Requires:
	 * 1. The activation is not empty.
	 * 2. The identifying fields (install ID, UID, license key) are present.
	 * 3. The stored UID matches the current site UID.
	 *
	 * @since 2.0.0
	 *
	 * @param Activation $activation Activation to check.
	 *
	 * @return bool
	 */
	protected function can_deactivate( Activation $activation ): bool {
		if ( $activation->is_empty() || ! $activation->has_required_keys() ) {
			return false;
		}

		return $activation->uid() === $this->site->get_uid();
	}
}
