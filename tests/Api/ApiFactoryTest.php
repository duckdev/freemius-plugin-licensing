<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Api;

use DuckDev\Freemius\Api\ApiFactory;
use DuckDev\Freemius\Api\Client;
use DuckDev\Freemius\Api\RequestSigner;
use DuckDev\Freemius\Api\SignedClient;
use DuckDev\Freemius\Data\ApiKeys;
use DuckDev\Freemius\Data\Plugin;
use DuckDev\Freemius\Tests\TestCase;

final class ApiFactoryTest extends TestCase {

	public function test_make_public_returns_unsigned_client(): void {
		$client = ( new ApiFactory() )->make_public( '1', 'plugin' );

		$this->assertInstanceOf( Client::class, $client );
		$this->assertNotInstanceOf( SignedClient::class, $client );
	}

	public function test_make_signed_returns_signed_client(): void {
		$client = ( new ApiFactory() )->make_signed( '9', new ApiKeys( 'pk', 'sk' ), 'install' );

		$this->assertInstanceOf( SignedClient::class, $client );
	}

	public function test_make_for_plugin_uses_plugin_public_key_in_both_slots(): void {
		$signer  = $this->createMock( RequestSigner::class );
		$factory = new ApiFactory( $signer );

		$plugin = new Plugin(
			42,
			array(
				'public_key' => 'pk_test',
			)
		);

		$client = $factory->make_for_plugin( $plugin );

		$this->assertInstanceOf( SignedClient::class, $client );

		// Reflect into the internal keys to confirm both slots use the public key (FSP).
		$ref  = new \ReflectionObject( $client );
		$prop = $ref->getProperty( 'keys' );
		$prop->setAccessible( true );
		$keys = $prop->getValue( $client );
		$this->assertInstanceOf( ApiKeys::class, $keys );

		$this->assertSame( 'pk_test', $keys->get_public_key() );
		$this->assertSame( 'pk_test', $keys->get_secret_key() );
	}

	public function test_make_for_install_uses_provided_keys(): void {
		$keys   = new ApiKeys( 'pk', 'sk' );
		$client = ( new ApiFactory() )->make_for_install( '42', $keys );

		$ref  = new \ReflectionObject( $client );
		$prop = $ref->getProperty( 'keys' );
		$prop->setAccessible( true );

		$this->assertSame( $keys, $prop->getValue( $client ) );
	}

	public function test_factory_returns_fresh_instances_per_call(): void {
		$factory = new ApiFactory();
		$a       = $factory->make_public( '1' );
		$b       = $factory->make_public( '1' );

		$this->assertNotSame( $a, $b );
	}
}
