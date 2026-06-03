<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Api;

use Brain\Monkey\Functions;
use DuckDev\Freemius\Api\RequestSigner;
use DuckDev\Freemius\Api\SignedClient;
use DuckDev\Freemius\Data\ApiKeys;
use DuckDev\Freemius\Tests\TestCase;

final class SignedClientTest extends TestCase {

	private function stubCommonWp(): void {
		Functions\when( 'get_bloginfo' )->justReturn( '6.5' );
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $args, string $url ) {
				return $url . '?' . http_build_query( $args );
			}
		);
	}

	public function test_signed_request_includes_signer_headers(): void {
		$this->stubCommonWp();

		$signer = $this->createMock( RequestSigner::class );
		$signer->expects( $this->once() )
			->method( 'sign' )
			->willReturn(
				array(
					'Authorization' => 'FS 9:pk:sig',
					'Date'          => 'Wed, 01 Jan 2020 00:00:00 GMT',
				)
			);

		$keys = new ApiKeys( 'pk', 'sk' );

		Functions\expect( 'wp_remote_request' )
			->once()
			->andReturnUsing(
				static function ( string $url, array $args ) {
					\PHPUnit\Framework\Assert::assertSame( 'FS 9:pk:sig', $args['headers']['Authorization'] );
					return array( 'body' => '[]' );
				}
			);

		( new SignedClient( '9', $keys, $signer, 'install' ) )->get( 'updates/latest.json' );
	}

	public function test_unsignable_keys_skip_signer_and_send_no_auth_headers(): void {
		$this->stubCommonWp();

		$signer = $this->createMock( RequestSigner::class );
		$signer->expects( $this->never() )->method( 'sign' );

		$keys = new ApiKeys( '', '' );

		Functions\expect( 'wp_remote_request' )
			->once()
			->andReturnUsing(
				static function ( string $url, array $args ) {
					\PHPUnit\Framework\Assert::assertArrayNotHasKey( 'Authorization', $args['headers'] );
					return array( 'body' => '[]' );
				}
			);

		( new SignedClient( '9', $keys, $signer, 'install' ) )->get( 'updates/latest.json' );
	}
}
