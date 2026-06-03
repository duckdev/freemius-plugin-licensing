<?php
/**
 * API key pair value object.
 *
 * Wraps the public/secret key pair used by {@see \DuckDev\Freemius\Api\RequestSigner}
 * to sign Freemius API requests. Two modes are supported:
 *
 * - **FS** (standard) — when the public and secret keys differ.
 *   Used for `install`/`user`-scoped requests where the install secret
 *   key returned at activation is available.
 *
 * - **FSP** (public-key-hash) — when the public key is used as both
 *   the public and the secret. Used for `plugin`-scoped public
 *   endpoints (info.json, addons.json) where only the plugin public
 *   key is known to the client.
 *
 * Callers do not need to know which mode they are using —
 * {@see RequestSigner::sign()} selects the auth scheme automatically
 * based on the supplied pair.
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
 * Class ApiKeys.
 */
class ApiKeys {

	/**
	 * Public key.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private string $public_key;

	/**
	 * Secret key. Defaults to the public key when omitted (FSP mode).
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private string $secret_key;

	/**
	 * Constructor.
	 *
	 * Passing an empty string (or omitting the parameter) for the
	 * secret key copies the public key into the secret slot, which is
	 * how the signer recognises FSP mode.
	 *
	 * @since 2.0.0
	 *
	 * @param string $public_key Public key.
	 * @param string $secret_key Secret key. Optional.
	 */
	public function __construct( string $public_key, string $secret_key = '' ) {
		$this->public_key = $public_key;
		$this->secret_key = '' === $secret_key ? $public_key : $secret_key;
	}

	/**
	 * Get the public key.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_public_key(): string {
		return $this->public_key;
	}

	/**
	 * Get the secret key.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_secret_key(): string {
		return $this->secret_key;
	}

	/**
	 * Whether the pair has enough information to sign a request.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_signable(): bool {
		return '' !== $this->public_key && '' !== $this->secret_key;
	}
}
