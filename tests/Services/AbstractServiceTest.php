<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Services;

use DuckDev\Freemius\Data\Plugin;
use DuckDev\Freemius\Services\AbstractService;
use DuckDev\Freemius\Tests\TestCase;

final class AbstractServiceTest extends TestCase {

	public function test_get_plugin_returns_constructor_argument(): void {
		$plugin = new Plugin( 5, array() );

		$service = new class( $plugin ) extends AbstractService {};

		$this->assertSame( $plugin, $service->get_plugin() );
	}

	public function test_default_boot_is_noop(): void {
		$service = new class( new Plugin( 1, array() ) ) extends AbstractService {};

		$service->boot();
		$this->addToAssertionCount( 1 ); // No-op: success is "did not throw".
	}
}
