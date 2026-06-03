<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests;

use Brain\Monkey\Functions;
use DuckDev\Freemius\Data\Plugin;
use DuckDev\Freemius\Freemius;
use DuckDev\Freemius\Services\Addon;
use DuckDev\Freemius\Services\License;
use DuckDev\Freemius\Services\Update;

final class FreemiusTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		// Update::boot() registers WP hooks when premium; stub them out.
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );
	}

	public function test_get_instance_returns_same_instance_for_same_id(): void {
		$args = array(
			'slug'       => 'duck',
			'public_key' => 'pk',
		);

		$a = Freemius::get_instance( 9001, $args );
		$b = Freemius::get_instance( 9001 );

		$this->assertSame( $a, $b );
	}

	public function test_get_instance_returns_distinct_instances_per_id(): void {
		$a = Freemius::get_instance( 8001, array() );
		$b = Freemius::get_instance( 8002, array() );

		$this->assertNotSame( $a, $b );
	}

	public function test_accessors_return_collaborator_types(): void {
		$f = Freemius::get_instance( 7001, array() );

		$this->assertInstanceOf( Plugin::class, $f->plugin() );
		$this->assertInstanceOf( License::class, $f->license() );
		$this->assertInstanceOf( Update::class, $f->update() );
		$this->assertInstanceOf( Addon::class, $f->addon() );
	}

	public function test_boot_is_idempotent(): void {
		$f = Freemius::get_instance( 6001, array() );

		// First boot ran inside get_instance; calling again must not throw.
		$f->boot();
		$f->boot();

		$this->addToAssertionCount( 1 );
	}
}
