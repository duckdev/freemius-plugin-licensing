<?php
/**
 * Base test case wiring Brain\Monkey + the WP_Error stub.
 *
 * @package DuckDev\Freemius\Tests
 */

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Default no-op stubs for the WP helpers used across the library.
		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_json_encode' )->alias(
			static function ( $data ) {
				return json_encode( $data );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
