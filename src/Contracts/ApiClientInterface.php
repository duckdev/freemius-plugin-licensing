<?php
/**
 * API client contract.
 *
 * Describes the minimal verb surface that the Freemius API clients in
 * the {@see \DuckDev\Freemius\Api} namespace expose to the rest of
 * the library. Services depend on this interface rather than on a
 * concrete client so they can be unit-tested with a mock.
 *
 * Every method returns either the decoded JSON payload as an
 * associative array or a {@see \WP_Error} when the request fails or
 * the API returns an error envelope.
 *
 * @link       https://duckdev.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      1.0.0
 * @package    Freemius
 * @subpackage Contracts
 */

namespace DuckDev\Freemius\Contracts;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

/**
 * Interface ApiClientInterface.
 */
interface ApiClientInterface {

	/**
	 * Perform a GET request against a scoped endpoint.
	 *
	 * @since 2.0.0
	 *
	 * @param string $endpoint Endpoint relative to the entity scope (e.g. "info.json").
	 * @param array  $params   Query string parameters.
	 *
	 * @return array|\WP_Error Decoded response or WP_Error on failure.
	 */
	public function get( string $endpoint, array $params = array() );

	/**
	 * Perform a POST request against a scoped endpoint.
	 *
	 * @since 2.0.0
	 *
	 * @param string $endpoint Endpoint relative to the entity scope.
	 * @param array  $data     Body data — JSON-encoded by the client.
	 *
	 * @return array|\WP_Error Decoded response or WP_Error on failure.
	 */
	public function post( string $endpoint, array $data = array() );

	/**
	 * Perform a PUT request against a scoped endpoint.
	 *
	 * @since 2.0.0
	 *
	 * @param string $endpoint Endpoint relative to the entity scope.
	 * @param array  $data     Body data — JSON-encoded by the client.
	 *
	 * @return array|\WP_Error Decoded response or WP_Error on failure.
	 */
	public function put( string $endpoint, array $data = array() );

	/**
	 * Perform a DELETE request against a scoped endpoint.
	 *
	 * @since 2.0.0
	 *
	 * @param string $endpoint Endpoint relative to the entity scope.
	 * @param array  $data     Body data — JSON-encoded by the client.
	 *
	 * @return array|\WP_Error Decoded response or WP_Error on failure.
	 */
	public function delete( string $endpoint, array $data = array() );
}
