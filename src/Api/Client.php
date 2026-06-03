<?php
/**
 * Public (unsigned) Freemius API client.
 *
 * Stateless HTTP wrapper around `wp_remote_request()` that handles
 * the Freemius URL conventions — scoping, JSON serialisation, and
 * error envelope normalisation. Replaces the static-singleton `Api`
 * class from the pre-refactor library: each call site now obtains a
 * fresh client from {@see ApiFactory}, eliminating the credential
 * leak that the old singleton suffered from.
 *
 * Subclasses (see {@see SignedClient}) override {@see build_headers()}
 * to inject authentication.
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
use WP_Error;

/**
 * Class Client.
 */
class Client implements ApiClientInterface {

	/**
	 * Base URL for the Freemius API.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected string $base_url = 'https://api.freemius.com';

	/**
	 * Entity ID injected into the scoped URL path.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected string $id;

	/**
	 * API scope (user / install / plugin).
	 *
	 * Mapped to the URL segment `/{scope}s/{id}/`.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected string $scope;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id    Entity ID.
	 * @param string $scope API scope. Defaults to `plugin`.
	 */
	public function __construct( string $id, string $scope = 'plugin' ) {
		$this->id    = $id;
		$this->scope = $scope;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function get( string $endpoint, array $params = array() ) {
		return $this->prepare_request( 'GET', $endpoint, $params );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function post( string $endpoint, array $data = array() ) {
		return $this->prepare_request( 'POST', $endpoint, $data );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function put( string $endpoint, array $data = array() ) {
		return $this->prepare_request( 'PUT', $endpoint, $data );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function delete( string $endpoint, array $data = array() ) {
		return $this->prepare_request( 'DELETE', $endpoint, $data );
	}

	/**
	 * Normalise an HTTP response into either a decoded array or a WP_Error.
	 *
	 * Surfaces error envelopes at two levels:
	 * 1. The outer wp_remote_request response when it already carries
	 *    an `error` array (rare).
	 * 2. The JSON-decoded body when it carries an `error.code` /
	 *    `error.message` pair — the standard Freemius shape.
	 *
	 * @since 2.0.0
	 *
	 * @param array|\WP_Error $response Raw response from `wp_remote_request()`.
	 *
	 * @return array|\WP_Error
	 */
	public function prepare_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response['error']['code'], $response['error']['message'] ) ) {
			return new WP_Error( $response['error']['code'], $response['error']['message'] );
		}

		$decoded = json_decode( $response['body'] ?? '', true );

		if ( isset( $decoded['error']['code'], $decoded['error']['message'] ) ) {
			return new WP_Error( $decoded['error']['code'], $decoded['error']['message'] );
		}

		return $decoded;
	}

	/**
	 * Prepare URL/headers and dispatch a request.
	 *
	 * @since 2.0.0
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint Caller-supplied endpoint (relative to the entity scope).
	 * @param array  $data     Data sent in the query string (GET) or body (everything else).
	 *
	 * @return array|\WP_Error
	 */
	protected function prepare_request( string $method, string $endpoint, array $data = array() ) {
		$endpoint = $this->prepare_endpoint( $endpoint );
		$url      = $this->prepare_url( $method, $endpoint, $data );
		$headers  = $this->build_headers( $method, $endpoint, $data );

		return $this->perform_http_request( $method, $url, $data, $headers );
	}

	/**
	 * Build the request headers.
	 *
	 * Returns an empty array for the unsigned client. Subclasses override
	 * to add `Authorization`, `Date`, and `Content-MD5` headers.
	 *
	 * @since 2.0.0
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint Prepared endpoint path (after `prepare_endpoint()`).
	 * @param array  $data     Body / query data.
	 *
	 * @return array
	 */
	protected function build_headers( string $method, string $endpoint, array $data ): array {
		unset( $method, $endpoint, $data );

		return array();
	}

	/**
	 * Compose the full request URL.
	 *
	 * For GET requests, body data is folded into the query string;
	 * other verbs leave the URL alone and serialise the body to JSON
	 * inside {@see perform_http_request()}.
	 *
	 * @since 2.0.0
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint Prepared endpoint path.
	 * @param array  $data     Data to attach.
	 *
	 * @return string Fully-qualified URL.
	 */
	protected function prepare_url( string $method, string $endpoint, array $data = array() ): string {
		$url = $this->base_url . $endpoint;

		if ( 'GET' === $method && ! empty( $data ) ) {
			$url = add_query_arg( $data, $url );
		}

		return $url;
	}

	/**
	 * Build the scoped endpoint path: `/v1/{scope}s/{id}/{endpoint}`.
	 *
	 * @since 2.0.0
	 *
	 * @param string $endpoint Caller-supplied endpoint (with or without leading slash).
	 *
	 * @return string
	 */
	protected function prepare_endpoint( string $endpoint ): string {
		$url_parts = array(
			'',
			'v1',
			$this->scope . 's',
			$this->id,
			ltrim( $endpoint, '/' ),
		);

		return implode( '/', $url_parts );
	}

	/**
	 * Dispatch the HTTP request via `wp_remote_request()`.
	 *
	 * @since 2.0.0
	 *
	 * @param string $method  HTTP method (already uppercased upstream is fine).
	 * @param string $url     Full URL.
	 * @param array  $data    Body data (ignored for GET).
	 * @param array  $headers Request headers, to which `Content-type: application/json` is added for mutating verbs.
	 *
	 * @return array|\WP_Error
	 */
	protected function perform_http_request( string $method, string $url, array $data, array $headers ) {
		$method = strtoupper( $method );
		$body   = null;

		if ( in_array( $method, array( 'POST', 'PUT', 'DELETE' ), true ) ) {
			$headers['Content-type'] = 'application/json';
			$body                    = wp_json_encode( $data );
		}

		$args = array(
			'method'           => $method,
			'connect_timeout'  => 10,
			'timeout'          => 60,
			'sslverify'        => $this->verify_ssl(),
			'follow_redirects' => true,
			'redirection'      => 5,
			'user-agent'       => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
			'blocking'         => true,
			'headers'          => $headers,
			'body'             => $body,
		);

		/**
		 * Filter the arguments used in the API request.
		 *
		 * @since 2.0.0
		 *
		 * @param array  $args    Request arguments.
		 * @param string $method  HTTP method.
		 * @param string $url     Request URL.
		 * @param array  $data    Request body.
		 * @param array  $headers Request headers.
		 */
		$args = apply_filters( 'duckdev_freemius_api_request_args', $args, $method, $url, $data, $headers );

		$response = wp_remote_request( $url, $args );

		return $this->prepare_response( $response );
	}

	/**
	 * Whether to verify the SSL certificate of `api.freemius.com`.
	 *
	 * Filterable via `duckdev_freemius_api_request_verify_ssl` for the
	 * rare case where a local dev environment needs to disable it.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function verify_ssl(): bool {
		/**
		 * Filter to change if the API SSL should be verified.
		 *
		 * @since 2.0.0
		 *
		 * @param bool   $verify Should verify?
		 * @param Client $client Current client instance.
		 */
		return (bool) apply_filters( 'duckdev_freemius_api_request_verify_ssl', true, $this );
	}
}
