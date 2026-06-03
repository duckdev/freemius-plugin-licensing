<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Data;

use DuckDev\Freemius\Data\ApiKeys;
use DuckDev\Freemius\Tests\TestCase;

final class ApiKeysTest extends TestCase {

	public function test_distinct_keys_round_trip(): void {
		$keys = new ApiKeys( 'pk_pub', 'sk_secret' );

		$this->assertSame( 'pk_pub', $keys->get_public_key() );
		$this->assertSame( 'sk_secret', $keys->get_secret_key() );
		$this->assertTrue( $keys->is_signable() );
	}

	public function test_empty_secret_falls_back_to_public_key_fsp_mode(): void {
		$keys = new ApiKeys( 'pk_pub' );

		$this->assertSame( 'pk_pub', $keys->get_public_key() );
		$this->assertSame( 'pk_pub', $keys->get_secret_key() );
		$this->assertTrue( $keys->is_signable() );
	}

	public function test_is_signable_false_when_public_key_empty(): void {
		$keys = new ApiKeys( '', 'sk' );
		$this->assertFalse( $keys->is_signable() );
	}

	public function test_is_signable_false_when_both_empty(): void {
		$keys = new ApiKeys( '', '' );
		$this->assertFalse( $keys->is_signable() );
	}
}
