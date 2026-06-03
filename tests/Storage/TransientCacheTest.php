<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Storage;

use Brain\Monkey\Functions;
use DuckDev\Freemius\Data\Plugin;
use DuckDev\Freemius\Storage\TransientCache;
use DuckDev\Freemius\Tests\TestCase;

final class TransientCacheTest extends TestCase {

	private function plugin(): Plugin {
		return new Plugin( 7, array() );
	}

	public function test_get_uses_prefixed_transient_key(): void {
		Functions\expect( 'get_transient' )
			->once()
			->with( 'duckdev_freemius_7_my_key' )
			->andReturn( 'cached' );

		$this->assertSame( 'cached', ( new TransientCache( $this->plugin() ) )->get( 'my_key' ) );
	}

	public function test_set_passes_key_value_and_expiration(): void {
		Functions\expect( 'set_transient' )
			->once()
			->with( 'duckdev_freemius_7_foo', 'bar', 120 )
			->andReturn( true );

		$this->assertTrue( ( new TransientCache( $this->plugin() ) )->set( 'foo', 'bar', 120 ) );
	}

	public function test_delete_returns_bool_from_delete_transient(): void {
		Functions\expect( 'delete_transient' )
			->once()
			->with( 'duckdev_freemius_7_foo' )
			->andReturn( false );

		$this->assertFalse( ( new TransientCache( $this->plugin() ) )->delete( 'foo' ) );
	}

	public function test_is_throttled_false_when_key_missing(): void {
		Functions\expect( 'get_transient' )->once()->andReturn( false );

		$this->assertFalse( ( new TransientCache( $this->plugin() ) )->is_throttled( 'k' ) );
	}

	public function test_is_throttled_true_when_value_present(): void {
		Functions\expect( 'get_transient' )->once()->andReturn( time() );

		$this->assertTrue( ( new TransientCache( $this->plugin() ) )->is_throttled( 'k' ) );
	}

	public function test_mark_requested_writes_with_default_window(): void {
		Functions\expect( 'set_transient' )
			->once()
			->with( 'duckdev_freemius_7_k', \Mockery::type( 'int' ), 5 * MINUTE_IN_SECONDS )
			->andReturn( true );

		$this->assertTrue( ( new TransientCache( $this->plugin() ) )->mark_requested( 'k' ) );
	}

	public function test_custom_throttle_window_is_honoured(): void {
		Functions\expect( 'set_transient' )
			->once()
			->with( \Mockery::any(), \Mockery::any(), 30 )
			->andReturn( true );

		( new TransientCache( $this->plugin(), 30 ) )->mark_requested( 'k' );
	}

	public function test_non_positive_window_falls_back_to_default(): void {
		Functions\expect( 'set_transient' )
			->once()
			->with( \Mockery::any(), \Mockery::any(), 5 * MINUTE_IN_SECONDS )
			->andReturn( true );

		( new TransientCache( $this->plugin(), 0 ) )->mark_requested( 'k' );
	}
}
