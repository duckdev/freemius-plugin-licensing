<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Storage;

use Brain\Monkey\Functions;
use DuckDev\Freemius\Data\Activation;
use DuckDev\Freemius\Storage\ActivationRepository;
use DuckDev\Freemius\Tests\TestCase;

final class ActivationRepositoryTest extends TestCase {

	public function test_get_returns_empty_activation_when_option_missing(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( ActivationRepository::OPTION_KEY, array() )
			->andReturn( array() );

		$activation = ( new ActivationRepository() )->get( 1 );

		$this->assertInstanceOf( Activation::class, $activation );
		$this->assertTrue( $activation->is_empty() );
	}

	public function test_get_returns_activation_keyed_by_plugin_id(): void {
		Functions\expect( 'get_option' )
			->once()
			->andReturn(
				array(
					42 => array( 'install_id' => 99 ),
				)
			);

		$activation = ( new ActivationRepository() )->get( 42 );

		$this->assertSame( '99', $activation->install_id() );
	}

	public function test_save_writes_full_map_with_new_entry(): void {
		Functions\expect( 'get_option' )
			->once()
			->andReturn( array( 1 => array( 'install_id' => 1 ) ) );

		Functions\expect( 'update_option' )
			->once()
			->with(
				ActivationRepository::OPTION_KEY,
				array(
					1 => array( 'install_id' => 1 ),
					2 => array( 'install_id' => 2 ),
				)
			)
			->andReturn( true );

		$result = ( new ActivationRepository() )->save( 2, new Activation( array( 'install_id' => 2 ) ) );

		$this->assertTrue( $result );
	}

	public function test_clear_unsets_only_target_plugin_entry(): void {
		Functions\expect( 'get_option' )
			->once()
			->andReturn(
				array(
					1 => array( 'a' => 1 ),
					2 => array( 'b' => 2 ),
				)
			);

		Functions\expect( 'update_option' )
			->once()
			->with(
				ActivationRepository::OPTION_KEY,
				array( 2 => array( 'b' => 2 ) )
			)
			->andReturn( true );

		$this->assertTrue( ( new ActivationRepository() )->clear( 1 ) );
	}
}
