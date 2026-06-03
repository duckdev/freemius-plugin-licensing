<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Api;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use DuckDev\Freemius\Api\Client;
use DuckDev\Freemius\Tests\TestCase;
use WP_Error;

final class ClientTest extends TestCase {

	private function stubCommonWp(): void {
		Functions\when( 'get_bloginfo' )->justReturn( '6.5' );
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $args, string $url ) {
				return $url . '?' . http_build_query( $args );
			}
		);
	}

	public function test_prepare_response_passes_through_wp_error(): void {
		$err = new WP_Error( 'x', 'y' );

		$this->assertSame( $err, ( new Client( '1' ) )->prepare_response( $err ) );
	}

	public function test_prepare_response_surfaces_top_level_error_envelope(): void {
		$response = ( new Client( '1' ) )->prepare_response(
			array(
				'error' => array(
					'code'    => 'bad',
					'message' => 'nope',
				),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'bad', $response->get_error_code() );
		$this->assertSame( 'nope', $response->get_error_message() );
	}

	public function test_prepare_response_decodes_json_body(): void {
		$response = ( new Client( '1' ) )->prepare_response(
			array( 'body' => json_encode( array( 'ok' => true ) ) )
		);

		$this->assertSame( array( 'ok' => true ), $response );
	}

	public function test_prepare_response_surfaces_decoded_error_envelope(): void {
		$response = ( new Client( '1' ) )->prepare_response(
			array(
				'body' => json_encode(
					array(
						'error' => array(
							'code'    => 'oops',
							'message' => 'broken',
						),
					)
				),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'oops', $response->get_error_code() );
	}

	public function test_get_uses_query_string_and_decoded_body(): void {
		$this->stubCommonWp();

		Functions\expect( 'wp_remote_request' )
			->once()
			->andReturnUsing(
				static function ( string $url, array $args ) {
					\PHPUnit\Framework\Assert::assertSame( 'GET', $args['method'] );
					\PHPUnit\Framework\Assert::assertNull( $args['body'] );
					\PHPUnit\Framework\Assert::assertStringContainsString( 'foo=bar', $url );
					\PHPUnit\Framework\Assert::assertStringContainsString( '/v1/plugins/1/info.json', $url );
					return array( 'body' => json_encode( array( 'ok' => 1 ) ) );
				}
			);

		$result = ( new Client( '1' ) )->get( 'info.json', array( 'foo' => 'bar' ) );

		$this->assertSame( array( 'ok' => 1 ), $result );
	}

	public function test_post_serialises_body_to_json_and_sets_content_type(): void {
		$this->stubCommonWp();

		Functions\expect( 'wp_remote_request' )
			->once()
			->andReturnUsing(
				static function ( string $url, array $args ) {
					\PHPUnit\Framework\Assert::assertSame( 'POST', $args['method'] );
					\PHPUnit\Framework\Assert::assertSame( 'application/json', $args['headers']['Content-type'] );
					\PHPUnit\Framework\Assert::assertSame( json_encode( array( 'a' => 1 ) ), $args['body'] );
					\PHPUnit\Framework\Assert::assertStringNotContainsString( '?', $url );
					return array( 'body' => json_encode( array() ) );
				}
			);

		( new Client( '1' ) )->post( 'activate.json', array( 'a' => 1 ) );
	}

	public function test_put_and_delete_use_correct_methods_and_json_body(): void {
		$this->stubCommonWp();

		$seen_methods = array();
		Functions\when( 'wp_remote_request' )->alias(
			static function ( string $url, array $args ) use ( &$seen_methods ) {
				$seen_methods[] = $args['method'];
				return array( 'body' => json_encode( array() ) );
			}
		);

		$client = new Client( '1' );
		$client->put( 'a', array( 'a' => 1 ) );
		$client->delete( 'b', array( 'b' => 2 ) );

		$this->assertSame( array( 'PUT', 'DELETE' ), $seen_methods );
	}

	public function test_scope_segment_uses_plural_form(): void {
		$this->stubCommonWp();

		Functions\expect( 'wp_remote_request' )
			->once()
			->andReturnUsing(
				static function ( string $url ) {
					\PHPUnit\Framework\Assert::assertStringContainsString( '/v1/installs/9/', $url );
					return array( 'body' => '[]' );
				}
			);

		( new Client( '9', 'install' ) )->get( 'updates/latest.json' );
	}

	public function test_request_args_filter_can_modify_args(): void {
		$this->stubCommonWp();

		Filters\expectApplied( 'duckdev_freemius_api_request_args' )
			->once()
			->andReturnUsing(
				static function ( array $args ) {
					$args['timeout'] = 1;
					return $args;
				}
			);

		Filters\expectApplied( 'duckdev_freemius_api_request_verify_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_request' )
			->once()
			->andReturnUsing(
				static function ( string $url, array $args ) {
					\PHPUnit\Framework\Assert::assertSame( 1, $args['timeout'] );
					\PHPUnit\Framework\Assert::assertFalse( $args['sslverify'] );
					return array( 'body' => '[]' );
				}
			);

		( new Client( '1' ) )->get( 'info.json' );
	}

	public function test_endpoint_leading_slash_is_normalised(): void {
		$this->stubCommonWp();

		Functions\expect( 'wp_remote_request' )
			->once()
			->andReturnUsing(
				static function ( string $url ) {
					\PHPUnit\Framework\Assert::assertStringContainsString( '/v1/plugins/1/info.json', $url );
					\PHPUnit\Framework\Assert::assertStringNotContainsString( '//info.json', $url );
					return array( 'body' => '[]' );
				}
			);

		( new Client( '1' ) )->get( '/info.json' );
	}
}
