<?php
/**
 * Freemius API request signer.
 *
 * Pure function object that produces the auth headers Freemius
 * expects on signed requests. Extracted from the old monolithic
 * `Api` class so the cryptographic portion can be unit-tested
 * without WordPress in the loop.
 *
 * Two header schemes are produced depending on the supplied
 * {@see \DuckDev\Freemius\Data\ApiKeys}:
 *
 * - `FS` — standard scheme. Used when the pair contains distinct
 *   public and secret keys (install / user scope).
 * - `FSP` — public-key-hash scheme. Used when the pair has the
 *   public key in both slots (plugin scope — only the public key is
 *   known to the client).
 *
 * The signature input is `METHOD\nMD5(body)\nCONTENT_TYPE\nDATE\nRESOURCE_URL`
 * matching the official Freemius SDK behaviour.
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
 * Class RequestSigner.
 */
class RequestSigner {

	/**
	 * Build the signed header set for an outgoing request.
	 *
	 * @since 2.0.0
	 *
	 * @param string  $resource_url Prepared endpoint path (the value `prepare_endpoint()` produced).
	 * @param string  $method       HTTP method (any case).
	 * @param array   $post_params  Body parameters. Only used when method is not GET.
	 * @param string  $id           Entity ID embedded in the Authorization header.
	 * @param ApiKeys $keys         Key pair used to sign.
	 *
	 * @return array Header map ready to be merged into the request's `headers` arg.
	 */
	public function sign( string $resource_url, string $method, array $post_params, string $id, ApiKeys $keys ): array {
		$method       = strtoupper( $method );
		$eol          = "\n";
		$content_md5  = '';
		$content_type = '';
		$date         = gmdate( 'r' );

		// Only mutating verbs carry a JSON body.
		if ( in_array( $method, array( 'POST', 'PUT' ), true ) ) {
			$content_type = 'application/json';
		}

		// MD5 of the body is part of the signature when a body is sent.
		if ( ! empty( $post_params ) && 'GET' !== $method ) {
			$content_md5 = md5( wp_json_encode( $post_params ) );
		}

		$string_to_sign = implode(
			$eol,
			array(
				$method,
				$content_md5,
				$content_type,
				$date,
				$resource_url,
			)
		);

		$public_key = $keys->get_public_key();
		$secret_key = $keys->get_secret_key();

		// Identical keys signal FSP (public-key-hash) auth; otherwise standard FS.
		$auth_type = ( $secret_key !== $public_key ) ? 'FS' : 'FSP';

		// URL-safe base64 of the HMAC-SHA256, without padding.
		$hash = hash_hmac( 'sha256', $string_to_sign, $secret_key );
		$hash = base64_encode( $hash );
		$hash = strtr( $hash, '+/', '-_' );
		$hash = str_replace( '=', '', $hash );

		$headers = array(
			'Date'          => $date,
			'Authorization' => "$auth_type $id:$public_key:$hash",
		);

		// Include Content-MD5 only when a body is sent — must match what
		// went into $string_to_sign or the server will reject the request.
		if ( '' !== $content_md5 ) {
			$headers['Content-MD5'] = $content_md5;
		}

		return $headers;
	}
}
