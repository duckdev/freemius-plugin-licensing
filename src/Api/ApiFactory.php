<?php
/**
 * Factory for Freemius API clients.
 *
 * Replaces the pre-refactor static singletons
 * `Api::get_instance()` / `Api::get_auth_instance()`. Every call
 * returns a fresh client instance, which means concurrent callers
 * with different credentials no longer share state through a cached
 * singleton.
 *
 * Three convenience builders are exposed for the call shapes used by
 * the bundled services:
 *
 * - {@see make_public()}     — unauthenticated client.
 * - {@see make_for_plugin()} — plugin-scoped client signed with the
 *                              plugin's public key (FSP encoding).
 * - {@see make_for_install()} — install-scoped client signed with
 *                              the credentials returned at activation.
 *
 * Custom call sites can also use {@see make_signed()} directly.
 *
 * @link       https://duckdev.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      1.0.0
 * @package    Freemius
 * @subpackage Api
 */

namespace DuckDev\Freemius\Api;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use DuckDev\Freemius\Contracts\ApiClientInterface;
use DuckDev\Freemius\Data\ApiKeys;
use DuckDev\Freemius\Data\Plugin;

/**
 * Class ApiFactory.
 */
class ApiFactory {

	/**
	 * Signer reused across every signed client this factory produces.
	 *
	 * @since 2.0.0
	 *
	 * @var RequestSigner
	 */
	private RequestSigner $signer;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param RequestSigner|null $signer Signer to use. A default instance is
	 *                                   constructed when null is supplied.
	 */
	public function __construct( ?RequestSigner $signer = null ) {
		$this->signer = $signer ?? new RequestSigner();
	}

	/**
	 * Build an unauthenticated client.
	 *
	 * Used by {@see \DuckDev\Freemius\Services\License} for the
	 * activate.json / deactivate.json endpoints which accept a
	 * license key in the body and do not require signing.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id    Entity ID.
	 * @param string $scope API scope.
	 *
	 * @return ApiClientInterface
	 */
	public function make_public( string $id, string $scope = 'plugin' ): ApiClientInterface {
		return new Client( $id, $scope );
	}

	/**
	 * Build a signed client from an explicit key pair.
	 *
	 * @since 2.0.0
	 *
	 * @param string  $id    Entity ID.
	 * @param ApiKeys $keys  Key pair.
	 * @param string  $scope API scope.
	 *
	 * @return ApiClientInterface
	 */
	public function make_signed( string $id, ApiKeys $keys, string $scope = 'user' ): ApiClientInterface {
		return new SignedClient( $id, $keys, $this->signer, $scope );
	}

	/**
	 * Build a plugin-scoped client signed with the plugin's public key.
	 *
	 * Internally this uses the public key as both the public and the
	 * secret half of the pair, which causes {@see RequestSigner} to
	 * emit `FSP` (public-key-hash) authentication — the scheme
	 * Freemius expects when only the plugin public key is known to
	 * the host (info.json, addons.json).
	 *
	 * @since 2.0.0
	 *
	 * @param Plugin $plugin Plugin instance.
	 *
	 * @return ApiClientInterface
	 */
	public function make_for_plugin( Plugin $plugin ): ApiClientInterface {
		$public_key = $plugin->get_public_key();

		return $this->make_signed(
			(string) $plugin->get_id(),
			new ApiKeys( $public_key, $public_key ),
			'plugin'
		);
	}

	/**
	 * Build an install-scoped client signed with the credentials
	 * returned by the API at activation time.
	 *
	 * @since 2.0.0
	 *
	 * @param string  $install_id Install ID returned by the activation response.
	 * @param ApiKeys $keys       Install key pair (from {@see \DuckDev\Freemius\Data\Activation::api_keys()}).
	 *
	 * @return ApiClientInterface
	 */
	public function make_for_install( string $install_id, ApiKeys $keys ): ApiClientInterface {
		return $this->make_signed( $install_id, $keys, 'install' );
	}
}
