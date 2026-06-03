<?php
/**
 * Signed Freemius API client.
 *
 * Extends {@see Client} with FS / FSP authenticated headers. Each
 * instance carries its own {@see \DuckDev\Freemius\Data\ApiKeys}
 * pair — the credentials are never shared between clients, which is
 * the fix for the credential-leak bug that the singleton-based
 * pre-refactor `Api::get_auth_instance()` suffered from.
 *
 * Instances are normally obtained from {@see ApiFactory} rather than
 * constructed directly.
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

use DuckDev\Freemius\Data\ApiKeys;

/**
 * Class SignedClient.
 */
class SignedClient extends Client {

	/**
	 * Key pair used to sign each request.
	 *
	 * @since 2.0.0
	 *
	 * @var ApiKeys
	 */
	private ApiKeys $keys;

	/**
	 * Collaborator that produces the auth headers.
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
	 * @param string        $id     Entity ID.
	 * @param ApiKeys       $keys   Key pair to sign with.
	 * @param RequestSigner $signer Signer used to build the header set.
	 * @param string        $scope  API scope. Defaults to `plugin`.
	 */
	public function __construct( string $id, ApiKeys $keys, RequestSigner $signer, string $scope = 'plugin' ) {
		parent::__construct( $id, $scope );

		$this->keys   = $keys;
		$this->signer = $signer;
	}

	/**
	 * Build signed auth headers for the outgoing request.
	 *
	 * Returns an empty header set (falling back to an unsigned request)
	 * when the configured key pair is not signable — for example when
	 * an activation's install data did not include both keys. The
	 * caller will receive whatever the Freemius API returns in that
	 * case (typically an authentication error).
	 *
	 * @since 2.0.0
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint Prepared endpoint path.
	 * @param array  $data     Request data.
	 *
	 * @return array
	 */
	protected function build_headers( string $method, string $endpoint, array $data ): array {
		if ( ! $this->keys->is_signable() ) {
			return array();
		}

		return $this->signer->sign( $endpoint, $method, $data, $this->id, $this->keys );
	}
}
