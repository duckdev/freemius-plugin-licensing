<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Contracts;

use DuckDev\Freemius\Api\Client;
use DuckDev\Freemius\Contracts\ApiClientInterface;
use DuckDev\Freemius\Contracts\CacheInterface;
use DuckDev\Freemius\Contracts\ServiceInterface;
use DuckDev\Freemius\Data\Plugin;
use DuckDev\Freemius\Services\License;
use DuckDev\Freemius\Storage\TransientCache;
use DuckDev\Freemius\Tests\TestCase;

final class ContractsTest extends TestCase {

	public function test_default_implementations_satisfy_interfaces(): void {
		$this->assertTrue( is_subclass_of( Client::class, ApiClientInterface::class ) );
		$this->assertTrue( is_subclass_of( TransientCache::class, CacheInterface::class ) );
		$this->assertTrue( is_subclass_of( License::class, ServiceInterface::class ) );
	}

	public function test_api_client_interface_declares_expected_verbs(): void {
		$reflection = new \ReflectionClass( ApiClientInterface::class );
		foreach ( array( 'get', 'post', 'put', 'delete' ) as $method ) {
			$this->assertTrue( $reflection->hasMethod( $method ), "ApiClientInterface missing $method" );
		}
	}

	public function test_cache_interface_declares_expected_methods(): void {
		$reflection = new \ReflectionClass( CacheInterface::class );
		foreach ( array( 'get', 'set', 'delete', 'is_throttled', 'mark_requested' ) as $method ) {
			$this->assertTrue( $reflection->hasMethod( $method ), "CacheInterface missing $method" );
		}
	}

	public function test_service_interface_declares_boot_and_get_plugin(): void {
		$reflection = new \ReflectionClass( ServiceInterface::class );

		$this->assertTrue( $reflection->hasMethod( 'boot' ) );
		$this->assertTrue( $reflection->hasMethod( 'get_plugin' ) );
		$this->assertSame( Plugin::class, $reflection->getMethod( 'get_plugin' )->getReturnType()->getName() );
	}
}
