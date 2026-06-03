<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Services;

use Brain\Monkey\Filters;
use DuckDev\Freemius\Api\ApiFactory;
use DuckDev\Freemius\Contracts\ApiClientInterface;
use DuckDev\Freemius\Contracts\CacheInterface;
use DuckDev\Freemius\Data\Plugin;
use DuckDev\Freemius\Services\Addon;
use DuckDev\Freemius\Tests\TestCase;
use WP_Error;

final class AddonTest extends TestCase {

	private function plugin( bool $has_addons = true ): Plugin {
		return new Plugin( 1, array( 'has_addons' => $has_addons ) );
	}

	public function test_returns_empty_when_plugin_has_no_addons(): void {
		$addon = new Addon(
			$this->plugin( false ),
			$this->createMock( CacheInterface::class ),
			$this->createMock( ApiFactory::class )
		);

		$this->assertSame( array(), $addon->get_addons() );
	}

	public function test_returns_cached_addons_when_available(): void {
		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->with( 'addons' )->willReturn(
			array( array( 'id' => 7 ) )
		);

		$factory = $this->createMock( ApiFactory::class );
		$factory->expects( $this->never() )->method( 'make_for_plugin' );

		$result = ( new Addon( $this->plugin(), $cache, $factory ) )->get_addons();

		$this->assertSame( array( array( 'id' => 7 ) ), $result );
	}

	public function test_force_bypasses_cache_and_persists_formatted_result(): void {
		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'is_throttled' )->willReturn( false );
		// `get` is consulted even on a forced refresh so the cached
		// catalog can serve as a fallback when the API call fails or
		// the throttle kicks in. Returning `false` mimics "no cache".
		$cache->method( 'get' )->willReturn( false );
		$cache->expects( $this->once() )
			->method( 'set' )
			->with(
				'addons',
				$this->callback(
					static function ( array $value ): bool {
						return isset( $value[0]['link'], $value[0]['is_premium'] )
							&& 'https://checkout.freemius.com/plugin/77' === $value[0]['link']
							&& true === $value[0]['is_premium'];
					}
				),
				DAY_IN_SECONDS
			);

		$api = $this->createMock( ApiClientInterface::class );
		$api->method( 'get' )->willReturn(
			array(
				'plugins' => array(
					array(
						'id'                 => 77,
						'is_pricing_visible' => true,
					),
				),
			)
		);

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_for_plugin' )->willReturn( $api );

		Filters\expectApplied( 'duckdev_freemius_format_addon_data' )->andReturnFirstArg();

		$result = ( new Addon( $this->plugin(), $cache, $factory ) )->get_addons( true );

		$this->assertCount( 1, $result );
		$this->assertTrue( $result[0]['is_premium'] );
	}

	public function test_returns_empty_when_api_errors_and_no_cache(): void {
		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->willReturn( false );
		$cache->method( 'is_throttled' )->willReturn( false );

		$api = $this->createMock( ApiClientInterface::class );
		$api->method( 'get' )->willReturn( new WP_Error( 'fail', 'no' ) );

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_for_plugin' )->willReturn( $api );

		$this->assertSame( array(), ( new Addon( $this->plugin(), $cache, $factory ) )->get_addons() );
	}

	public function test_force_falls_back_to_cache_when_api_errors(): void {
		$cached = array(
			array(
				'id'    => 42,
				'title' => 'Cached',
			),
		);

		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->willReturn( $cached );
		$cache->method( 'is_throttled' )->willReturn( false );
		// API failed → catalog must not be overwritten.
		$cache->expects( $this->never() )->method( 'set' );

		$api = $this->createMock( ApiClientInterface::class );
		$api->method( 'get' )->willReturn( new WP_Error( 'fail', 'no' ) );

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_for_plugin' )->willReturn( $api );

		$result = ( new Addon( $this->plugin(), $cache, $factory ) )->get_addons( true );

		$this->assertSame( $cached, $result );
	}

	public function test_throttled_request_returns_empty_when_no_cache(): void {
		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->willReturn( false );
		$cache->method( 'is_throttled' )->with( 'addons_check' )->willReturn( true );

		$api = $this->createMock( ApiClientInterface::class );
		$api->expects( $this->never() )->method( 'get' );

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_for_plugin' )->willReturn( $api );

		$this->assertSame( array(), ( new Addon( $this->plugin(), $cache, $factory ) )->get_addons() );
	}

	public function test_force_falls_back_to_cache_when_throttled(): void {
		$cached = array(
			array(
				'id'    => 9,
				'title' => 'Stale but fine',
			),
		);

		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->willReturn( $cached );
		$cache->method( 'is_throttled' )->with( 'addons_check' )->willReturn( true );
		// Throttle blocked the API call → catalog stays as it is.
		$cache->expects( $this->never() )->method( 'set' );

		$api = $this->createMock( ApiClientInterface::class );
		$api->expects( $this->never() )->method( 'get' );

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_for_plugin' )->willReturn( $api );

		$result = ( new Addon( $this->plugin(), $cache, $factory ) )->get_addons( true );

		$this->assertSame( $cached, $result );
	}

	public function test_mark_requested_called_after_request(): void {
		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->willReturn( false );
		$cache->method( 'is_throttled' )->willReturn( false );
		$cache->expects( $this->once() )->method( 'mark_requested' )->with( 'addons_check' );

		$api = $this->createMock( ApiClientInterface::class );
		$api->method( 'get' )->willReturn( array( 'plugins' => array( array( 'id' => 1 ) ) ) );

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_for_plugin' )->willReturn( $api );

		Filters\expectApplied( 'duckdev_freemius_format_addon_data' )->andReturnFirstArg();

		( new Addon( $this->plugin(), $cache, $factory ) )->get_addons( true );
	}

	public function test_empty_plugins_array_is_returned_unchanged(): void {
		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->willReturn( false );
		$cache->method( 'is_throttled' )->willReturn( false );

		$api = $this->createMock( ApiClientInterface::class );
		$api->method( 'get' )->willReturn( array() );

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_for_plugin' )->willReturn( $api );

		$this->assertSame( array(), ( new Addon( $this->plugin(), $cache, $factory ) )->get_addons( true ) );
	}
}
