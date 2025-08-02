<?php
/**
 * This class provides methods to perform HTTP requests to Freemius API.
 *
 * @link       https://duckdev.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      1.0.0
 */

namespace DuckDev\Freemius\Api;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use WP_Error;

/**
 * Class Api.
 */
class Api {

	/**
	 * The base URL of the API.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $base_url = 'https://api.freemius.com';

	/**
	 * Entity ID.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $id;

	/**
	 * API scope for request.
	 *
	 * @since 1.0.0
	 *
	 * @var string $scope user|install|plugin
	 */
	protected string $scope = 'plugin';

	/**
	 * Public key for authentication.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $public_key = '';

	/**
	 * Secret key for authentication.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $secret_key = '';

	/**
	 * Api constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $scope Type of scope (e.g., plugin, user, install).
	 * @param string $id    Entity ID.
	 */
	protected function __construct( string $id, string $scope = 'plugin' ) {
		$this->id    = $id;
		$this->scope = $scope;
	}

	/**
	 * Get the singleton instance of the public API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id    Entity ID.
	 * @param string $scope Scope for API.
	 *
	 * @return Api
	 */
	public static function get_instance( string $id, string $scope = 'plugin' ): Api {
		static $instances = array();

		// Create new instance only if doesn't exist.
		if ( ! isset( $instances["$scope.$id"] ) ) {
			$instances["$scope.$id"] = new self( $id, $scope );
		}

		return $instances["$scope.$id"];
	}

	/**
	 * Get the singleton instance of the authenticated API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id         Entity ID.
	 * @param string $public_key Public key.
	 * @param string $secret_key Secret key.
	 * @param string $scope      Scope for API.
	 *
	 * @return Api
	 */
	public static function get_auth_instance( string $id, string $public_key, string $secret_key, string $scope = 'user' ): Api {
		$instance             = self::get_instance( $id, $scope );
		$instance->public_key = $public_key;
		$instance->secret_key = $secret_key;

		return $instance;
	}

	/**
	 * Perform a GET request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $endpoint Endpoint.
	 * @param array  $params   Request params.
	 *
	 * @return array|WP_Error
	 */
	public function get( string $endpoint, array $params = array() ) {
		return $this->prepare_request( 'GET', $endpoint, $params );
	}

	/**
	 * Perform a POST request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $endpoint Endpoint.
	 * @param array  $data     Request data.
	 *
	 * @return array|WP_Error
	 */
	public function post( string $endpoint, array $data = array() ) {
		return $this->prepare_request( 'POST', $endpoint, $data );
	}

	/**
	 * Perform a PUT request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $endpoint Endpoint.
	 * @param array  $data     Request data.
	 *
	 * @return array|WP_Error
	 */
	public function put( string $endpoint, array $data = array() ) {
		return $this->prepare_request( 'PUT', $endpoint, $data );
	}

	/**
	 * Perform a DELETE request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $endpoint Endpoint.
	 * @param array  $data     Request data.
	 *
	 * @return array|WP_Error
	 */
	public function delete( string $endpoint, array $data = array() ) {
		return $this->prepare_request( 'DELETE', $endpoint, $data );
	}

	/**
	 * Validate an HTTP response.
	 *
	 * Double check responses for errors.
	 *
	 * @since 1.0.0
	 *
	 * @param array|WP_Error $response Response data.
	 *
	 * @return mixed|WP_Error
	 */
	public function prepare_response( $response ) {
		// If WP error, return.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Create new WP error instance and return.
		if ( isset( $response['error']['code'], $response['error']['message'] ) ) {
			return new WP_Error( $response['error']['code'], $response['error']['message'] );
		}

		// Decode json data.
		$response = json_decode( $response['body'], true );

		// Create new WP error instance and return.
		if ( isset( $response['error']['code'], $response['error']['message'] ) ) {
			return new WP_Error( $response['error']['code'], $response['error']['message'] );
		}

		return $response;
	}

	/**
	 * Prepare an authenticated request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Data to be sent in the request.
	 *
	 * @return array|WP_Error
	 */
	protected function prepare_request( string $method, string $endpoint, array $data = array() ) {
		$endpoint = $this->prepare_endpoint( $endpoint );
		$url      = $this->prepare_url( $method, $endpoint, $data );

		$headers = array();

		// Sign the request for auth.
		if ( $this->public_key && $this->secret_key ) {
			$headers = $this->get_signed_headers(
				$endpoint,
				$method,
				$data,
				$this->id,
				$this->public_key,
				$this->secret_key
			);
		}

		return $this->perform_http_request( $method, $url, $data, $headers );
	}

	/**
	 * Prepare API url for request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Data to be sent in the request.
	 *
	 * @return string
	 */
	protected function prepare_url( string $method, string $endpoint, array $data = array() ): string {
		$url = $this->base_url . $endpoint;

		if ( $method === 'GET' && ! empty( $data ) ) {
			$url = add_query_arg( $data, $url );
		}

		return $url;
	}

	/**
	 * Prepare API endpoint for request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $endpoint API endpoint.
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

		return join( '/', $url_parts );
	}

	/**
	 * Execute the HTTP request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $method  HTTP method.
	 * @param string $url     Request URL.
	 * @param array  $data    Request body.
	 * @param array  $headers Request headers.
	 *
	 * @return array|WP_Error
	 */
	protected function perform_http_request( string $method, string $url, array $data = array(), array $headers = array() ) {
		$method = strtoupper( $method );
		$body   = null;
		if ( in_array( $method, array( 'POST', 'PUT', 'DELETE' ) ) ) {
			$headers['Content-type'] = 'application/json';
			$body                    = json_encode( $data );
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
		 * Filters the arguments used in the API request.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $args    Request arguments.
		 * @param string $method  HTTP method.
		 * @param string $url     Request URL.
		 * @param array  $data    Request body.
		 * @param array  $headers Request headers.
		 */
		$args = apply_filters( 'duckdev_freemius_api_request_args', $args, $method, $url, $data, $headers );

		// Use WP HTTP to send request.
		$response = wp_remote_request( $url, $args );

		return $this->prepare_response( $response );
	}

	/**
	 * Returns if the SSL of the store should be verified.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	protected function verify_ssl(): bool {
		/**
		 * Filter to change if the SSL of the store should be verified.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $verify Should verify?
		 * @param self $this   Current class instance.
		 */
		return (bool) apply_filters( 'duckdev_freemius_api_request_verify_ssl', true, $this );
	}

	/**
	 * Generate signature signed headers for the request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $resource_url Resource URL.
	 * @param string $method       HTTP method.
	 * @param array  $post_params  Parameters for POST requests.
	 * @param string $id           Entity ID.
	 * @param string $public_key   Public key for authentication.
	 * @param string $secret_key   Secret key for authentication.
	 *
	 * @return array
	 */
	private function get_signed_headers(
		string $resource_url,
		string $method,
		array $post_params,
		string $id,
		string $public_key,
		string $secret_key
	): array {
		$method       = strtoupper( $method );
		$eol          = "\n";
		$content_md5  = '';
		$content_type = '';
		$date         = date( 'r', time() );

		if ( in_array( $method, array( 'POST', 'PUT' ) ) ) {
			$content_type = 'application/json';
		}

		if ( ! empty( $post_params ) && 'GET' !== $method ) {
			$content_md5 = md5( json_encode( $post_params ) );
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

		// If secret and public keys are identical, it means that the signature uses public key hash encoding.
		$auth_type = ( $secret_key !== $public_key ) ? 'FS' : 'FSP';
		$hash      = hash_hmac( 'sha256', $string_to_sign, $secret_key );
		$hash      = base64_encode( $hash );
		$hash      = strtr( $hash, '+/', '-_' );
		$hash      = str_replace( '=', '', $hash );

		$auth = array(
			'Date'          => $date,
			'Authorization' => "$auth_type $id:$public_key:$hash",
		);

		if ( ! empty( $content_md5 ) ) {
			$auth['Content-MD5'] = $content_md5;
		}

		return $auth;
	}
}
