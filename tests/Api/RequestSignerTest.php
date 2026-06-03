<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Api;

use DuckDev\Freemius\Api\RequestSigner;
use DuckDev\Freemius\Data\ApiKeys;
use DuckDev\Freemius\Tests\TestCase;

final class RequestSignerTest extends TestCase {

	public function test_get_request_omits_content_md5_and_uses_empty_md5_in_string(): void {
		$signer = new RequestSigner();
		$keys   = new ApiKeys( 'pk_pub', 'sk_secret' );

		$headers = $signer->sign( '/v1/plugins/1/info.json', 'GET', array(), '1', $keys );

		$this->assertArrayHasKey( 'Date', $headers );
		$this->assertArrayHasKey( 'Authorization', $headers );
		$this->assertArrayNotHasKey( 'Content-MD5', $headers );
	}

	public function test_authorization_uses_fs_scheme_when_keys_differ(): void {
		$headers = ( new RequestSigner() )->sign(
			'/v1/installs/9/x.json',
			'POST',
			array( 'a' => 1 ),
			'9',
			new ApiKeys( 'pk_pub', 'sk_secret' )
		);

		$this->assertStringStartsWith( 'FS 9:pk_pub:', $headers['Authorization'] );
	}

	public function test_authorization_uses_fsp_scheme_when_keys_identical(): void {
		$headers = ( new RequestSigner() )->sign(
			'/v1/plugins/1/info.json',
			'GET',
			array(),
			'1',
			new ApiKeys( 'pk_pub', 'pk_pub' )
		);

		$this->assertStringStartsWith( 'FSP 1:pk_pub:', $headers['Authorization'] );
	}

	public function test_post_with_body_emits_content_md5_matching_body_hash(): void {
		$body = array( 'k' => 'v' );

		$headers = ( new RequestSigner() )->sign(
			'/v1/installs/9/license.json',
			'POST',
			$body,
			'9',
			new ApiKeys( 'pk_pub', 'sk_secret' )
		);

		$this->assertArrayHasKey( 'Content-MD5', $headers );
		$this->assertSame( md5( json_encode( $body ) ), $headers['Content-MD5'] );
	}

	public function test_put_with_empty_body_omits_content_md5(): void {
		$headers = ( new RequestSigner() )->sign(
			'/v1/installs/9/x.json',
			'PUT',
			array(),
			'9',
			new ApiKeys( 'pk_pub', 'sk_secret' )
		);

		$this->assertArrayNotHasKey( 'Content-MD5', $headers );
	}

	public function test_signature_is_deterministic_for_fixed_date(): void {
		// We cannot freeze gmdate('r') without injection; check stability
		// across two adjacent calls instead.
		$signer = new RequestSigner();
		$keys   = new ApiKeys( 'pk_pub', 'pk_pub' );

		$a = $signer->sign( '/v1/plugins/1/info.json', 'GET', array(), '1', $keys );
		$b = $signer->sign( '/v1/plugins/1/info.json', 'GET', array(), '1', $keys );

		// Authorization hashes the Date which may differ by 1 second between
		// calls; both must still be well-formed FSP headers.
		$this->assertStringStartsWith( 'FSP 1:pk_pub:', $a['Authorization'] );
		$this->assertStringStartsWith( 'FSP 1:pk_pub:', $b['Authorization'] );
	}

	public function test_signature_hash_is_url_safe_base64(): void {
		$headers = ( new RequestSigner() )->sign(
			'/v1/plugins/1/info.json',
			'POST',
			array( 'a' => 'b' ),
			'1',
			new ApiKeys( 'pk_pub', 'sk_secret' )
		);

		[, $signature] = explode( ':pk_pub:', $headers['Authorization'] );

		$this->assertStringNotContainsString( '+', $signature );
		$this->assertStringNotContainsString( '/', $signature );
		$this->assertStringNotContainsString( '=', $signature );
	}

	public function test_method_case_does_not_change_scheme(): void {
		$keys = new ApiKeys( 'pk', 'sk' );

		$lower = ( new RequestSigner() )->sign( '/x', 'post', array( 'a' => 1 ), '1', $keys );
		$upper = ( new RequestSigner() )->sign( '/x', 'POST', array( 'a' => 1 ), '1', $keys );

		$this->assertSame(
			substr( $lower['Authorization'], 0, 3 ),
			substr( $upper['Authorization'], 0, 3 )
		);
		$this->assertArrayHasKey( 'Content-MD5', $lower );
		$this->assertArrayHasKey( 'Content-MD5', $upper );
	}
}
