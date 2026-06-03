<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Data;

use Brain\Monkey\Functions;
use DuckDev\Freemius\Data\Plugin;
use DuckDev\Freemius\Tests\TestCase;

final class PluginTest extends TestCase {

	public function test_defaults_when_args_omitted(): void {
		$plugin = new Plugin( 100, array() );

		$this->assertSame( 100, $plugin->get_id() );
		$this->assertSame( '', $plugin->get_slug() );
		$this->assertSame( '', $plugin->get_main_file() );
		$this->assertSame( '', $plugin->get_public_key() );
		$this->assertFalse( $plugin->is_premium() );
		$this->assertFalse( $plugin->has_addons() );
	}

	public function test_accessors_return_constructor_args(): void {
		$plugin = new Plugin(
			42,
			array(
				'slug'       => 'duck',
				'main_file'  => '/abs/main.php',
				'public_key' => 'pk_abc',
				'is_premium' => true,
				'has_addons' => true,
			)
		);

		$this->assertSame( 42, $plugin->get_id() );
		$this->assertSame( 'duck', $plugin->get_slug() );
		$this->assertSame( '/abs/main.php', $plugin->get_main_file() );
		$this->assertSame( 'pk_abc', $plugin->get_public_key() );
		$this->assertTrue( $plugin->is_premium() );
		$this->assertTrue( $plugin->has_addons() );
	}

	public function test_get_data_delegates_to_get_plugin_data(): void {
		Functions\stubs(
			array(
				'get_plugin_data' => array(
					'Name'    => 'Duck',
					'Version' => '1.2.3',
				),
			)
		);

		$plugin = new Plugin( 1, array( 'main_file' => '/x.php' ) );
		$data   = $plugin->get_data();

		$this->assertSame( 'Duck', $data['Name'] );
		$this->assertSame( '1.2.3', $data['Version'] );
	}

	public function test_get_data_is_cached_on_instance(): void {
		$calls = 0;
		Functions\when( 'get_plugin_data' )->alias(
			static function () use ( &$calls ) {
				$calls++;
				return array( 'Name' => 'X' );
			}
		);

		$plugin = new Plugin( 1, array( 'main_file' => '/x.php' ) );
		$plugin->get_data();
		$plugin->get_data();

		$this->assertSame( 1, $calls );
	}
}
