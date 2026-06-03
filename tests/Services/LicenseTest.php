<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Services;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use DuckDev\Freemius\Api\ApiFactory;
use DuckDev\Freemius\Contracts\ApiClientInterface;
use DuckDev\Freemius\Data\Activation;
use DuckDev\Freemius\Data\Plugin;
use DuckDev\Freemius\Services\License;
use DuckDev\Freemius\Storage\ActivationRepository;
use DuckDev\Freemius\Support\SiteIdentity;
use DuckDev\Freemius\Tests\TestCase;
use WP_Error;

final class LicenseTest extends TestCase {

	private function plugin( bool $premium = true ): Plugin {
		$plugin = $this->getMockBuilder( Plugin::class )
			->setConstructorArgs( array( 1, array( 'is_premium' => $premium ) ) )
			->onlyMethods( array( 'get_data' ) )
			->getMock();
		$plugin->method( 'get_data' )->willReturn( array( 'Version' => '1.0.0' ) );

		return $plugin;
	}

	private function license(
		Plugin $plugin,
		ActivationRepository $repo,
		ApiFactory $factory,
		?SiteIdentity $site = null
	): License {
		if ( null === $site ) {
			$site = $this->createMock( SiteIdentity::class );
			$site->method( 'get_uid' )->willReturn( 'uid-current' );
		}
		return new License( $plugin, $repo, $factory, $site );
	}

	public function test_activate_rejects_empty_key(): void {
		$license = $this->license(
			$this->plugin(),
			$this->createMock( ActivationRepository::class ),
			$this->createMock( ApiFactory::class )
		);

		$result = $license->activate( '' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'empty_activation_key', $result->get_error_code() );
	}

	public function test_activate_rejects_non_premium_plugin(): void {
		$license = $this->license(
			$this->plugin( false ),
			$this->createMock( ActivationRepository::class ),
			$this->createMock( ApiFactory::class )
		);

		$result = $license->activate( 'KEY' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'not_premium', $result->get_error_code() );
	}

	public function test_activate_returns_api_error_unchanged(): void {
		Functions\when( 'get_site_url' )->justReturn( 'https://example.com' );

		$repo = $this->createMock( ActivationRepository::class );
		$repo->method( 'get' )->willReturn( new Activation() );

		$api = $this->createMock( ApiClientInterface::class );
		$api->method( 'post' )->willReturn( new WP_Error( 'bad', 'denied' ) );

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_public' )->willReturn( $api );

		$result = $this->license( $this->plugin(), $repo, $factory )->activate( 'KEY' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'bad', $result->get_error_code() );
	}

	public function test_activate_persists_install_data_and_fires_action(): void {
		Functions\when( 'get_site_url' )->justReturn( 'https://example.com' );

		$repo = $this->createMock( ActivationRepository::class );
		$repo->method( 'get' )->willReturn( new Activation() );
		$repo->expects( $this->once() )
			->method( 'save' )
			->with(
				1,
				$this->callback(
					static function ( Activation $a ): bool {
						return '999' === $a->install_id()
							&& Activation::STATUS_ACTIVATED === $a->status()
							&& 'KEY' === $a->license_key();
					}
				)
			)
			->willReturn( true );

		$api = $this->createMock( ApiClientInterface::class );
		$api->expects( $this->once() )
			->method( 'post' )
			->with(
				'activate.json',
				$this->callback(
					static function ( array $args ): bool {
						return 'KEY' === $args['license_key']
							&& 'uid-current' === $args['uid']
							&& '1.0.0' === $args['version']
							&& ! isset( $args['install_id'] );
					}
				)
			)
			->willReturn(
				array(
					'install_id'         => 999,
					'install_public_key' => 'pk',
					'install_secret_key' => 'sk',
				)
			);

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_public' )->with( '1', 'plugin' )->willReturn( $api );

		Actions\expectDone( 'duckdev_freemius_license_activated' )->once();

		$result = $this->license( $this->plugin(), $repo, $factory )->activate( 'KEY' );

		$this->assertTrue( $result );
	}

	public function test_activate_reuses_existing_install_id(): void {
		Functions\when( 'get_site_url' )->justReturn( 'https://example.com' );

		$existing = new Activation( array( 'install_id' => 555 ) );
		$repo     = $this->createMock( ActivationRepository::class );
		$repo->method( 'get' )->willReturn( $existing );
		$repo->method( 'save' )->willReturn( true );

		$api = $this->createMock( ApiClientInterface::class );
		$api->expects( $this->once() )
			->method( 'post' )
			->with(
				'activate.json',
				$this->callback(
					static function ( array $args ): bool {
						return isset( $args['install_id'] ) && '555' === (string) $args['install_id'];
					}
				)
			)
			->willReturn( array( 'install_id' => 555 ) );

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_public' )->willReturn( $api );

		$this->license( $this->plugin(), $repo, $factory )->activate( 'KEY' );
	}

	public function test_activate_returns_unknown_error_when_response_missing_install_id(): void {
		Functions\when( 'get_site_url' )->justReturn( 'https://example.com' );

		$repo = $this->createMock( ActivationRepository::class );
		$repo->method( 'get' )->willReturn( new Activation() );

		$api = $this->createMock( ApiClientInterface::class );
		$api->method( 'post' )->willReturn( array( 'other' => 'thing' ) );

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_public' )->willReturn( $api );

		$result = $this->license( $this->plugin(), $repo, $factory )->activate( 'KEY' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'unknown_error', $result->get_error_code() );
	}

	public function test_deactivate_rejects_empty_activation(): void {
		$repo = $this->createMock( ActivationRepository::class );
		$repo->method( 'get' )->willReturn( new Activation() );

		$result = $this->license(
			$this->plugin(),
			$repo,
			$this->createMock( ApiFactory::class )
		)->deactivate();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_activation_data', $result->get_error_code() );
	}

	public function test_deactivate_rejects_when_uid_mismatch(): void {
		$repo = $this->createMock( ActivationRepository::class );
		$repo->method( 'get' )->willReturn(
			new Activation(
				array(
					'install_id'        => 1,
					'status'            => Activation::STATUS_ACTIVATED,
					'activation_params' => array(
						'license_key' => 'KEY',
						'uid'         => 'uid-OTHER',
					),
				)
			)
		);

		$result = $this->license(
			$this->plugin(),
			$repo,
			$this->createMock( ApiFactory::class )
		)->deactivate();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_activation_data', $result->get_error_code() );
	}

	public function test_deactivate_persists_scrubbed_license_on_success(): void {
		Functions\when( 'get_site_url' )->justReturn( 'https://example.com' );

		$activation = new Activation(
			array(
				'install_id'        => 1,
				'status'            => Activation::STATUS_ACTIVATED,
				'activation_params' => array(
					'license_key' => 'KEY',
					'uid'         => 'uid-current',
				),
			)
		);

		$repo = $this->createMock( ActivationRepository::class );
		$repo->method( 'get' )->willReturn( $activation );
		$repo->expects( $this->once() )
			->method( 'save' )
			->with(
				1,
				$this->callback(
					static function ( Activation $a ): bool {
						return '' === $a->license_key()
							&& Activation::STATUS_DEACTIVATED === $a->status();
					}
				)
			)
			->willReturn( true );

		$api = $this->createMock( ApiClientInterface::class );
		$api->method( 'post' )->willReturn( array( 'id' => 1 ) );

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_public' )->willReturn( $api );

		Actions\expectDone( 'duckdev_freemius_license_deactivated' )->once();

		$result = $this->license( $this->plugin(), $repo, $factory )->deactivate();

		$this->assertTrue( $result );
	}

	public function test_deactivate_passes_api_error_through(): void {
		Functions\when( 'get_site_url' )->justReturn( 'https://example.com' );

		$repo = $this->createMock( ActivationRepository::class );
		$repo->method( 'get' )->willReturn(
			new Activation(
				array(
					'install_id'        => 1,
					'status'            => Activation::STATUS_ACTIVATED,
					'activation_params' => array(
						'license_key' => 'KEY',
						'uid'         => 'uid-current',
					),
				)
			)
		);

		$api = $this->createMock( ApiClientInterface::class );
		$api->method( 'post' )->willReturn( new WP_Error( 'denied', 'no' ) );

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_public' )->willReturn( $api );

		$result = $this->license( $this->plugin(), $repo, $factory )->deactivate();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'denied', $result->get_error_code() );
	}

	public function test_get_activation_delegates_to_repository(): void {
		$expected = new Activation( array( 'install_id' => 7 ) );

		$repo = $this->createMock( ActivationRepository::class );
		$repo->expects( $this->once() )->method( 'get' )->with( 1 )->willReturn( $expected );

		$this->assertSame(
			$expected,
			$this->license( $this->plugin(), $repo, $this->createMock( ApiFactory::class ) )->get_activation()
		);
	}
}
