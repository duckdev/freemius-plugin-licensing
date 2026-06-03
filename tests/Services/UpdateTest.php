<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Services;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use DuckDev\Freemius\Api\ApiFactory;
use DuckDev\Freemius\Contracts\ApiClientInterface;
use DuckDev\Freemius\Contracts\CacheInterface;
use DuckDev\Freemius\Data\Activation;
use DuckDev\Freemius\Data\Plugin;
use DuckDev\Freemius\Services\Update;
use DuckDev\Freemius\Storage\ActivationRepository;
use DuckDev\Freemius\Tests\TestCase;
use WP_Error;

final class UpdateTest extends TestCase {

	protected function tearDown(): void {
		unset( $_GET['force-check'] );
		parent::tearDown();
	}

	private function plugin( bool $premium = true ): Plugin {
		$plugin = $this->getMockBuilder( Plugin::class )
			->setConstructorArgs(
				array(
					1,
					array(
						'is_premium' => $premium,
						'slug'       => 'duck',
						'main_file'  => '/abs/main.php',
					),
				)
			)
			->onlyMethods( array( 'get_data' ) )
			->getMock();
		$plugin->method( 'get_data' )->willReturn(
			array(
				'Name'    => 'Duck',
				'Author'  => 'DuckDev',
				'Version' => '1.0.0',
			)
		);

		return $plugin;
	}

	private function active_activation(): Activation {
		return new Activation(
			array(
				'install_id'        => 1,
				'status'            => Activation::STATUS_ACTIVATED,
				'activation_params' => array(
					'license_key' => 'KEY',
					'uid'         => 'uid',
				),
				'install_data'      => array(
					'install_public_key' => 'pk',
					'install_secret_key' => 'sk',
				),
			)
		);
	}

	public function test_boot_skips_hooks_for_non_premium(): void {
		Actions\expectAdded( 'upgrader_process_complete' )->never();
		Filters\expectAdded( 'plugins_api' )->never();
		Filters\expectAdded( 'site_transient_update_plugins' )->never();

		( new Update(
			$this->plugin( false ),
			$this->createMock( ActivationRepository::class ),
			$this->createMock( CacheInterface::class ),
			$this->createMock( ApiFactory::class )
		) )->boot();
	}

	public function test_boot_registers_hooks_for_premium(): void {
		Filters\expectAdded( 'plugins_api' )->once();
		Filters\expectAdded( 'site_transient_update_plugins' )->once();
		Actions\expectAdded( 'upgrader_process_complete' )->once();

		( new Update(
			$this->plugin( true ),
			$this->createMock( ActivationRepository::class ),
			$this->createMock( CacheInterface::class ),
			$this->createMock( ApiFactory::class )
		) )->boot();
	}

	public function test_get_update_data_returns_cached_when_present(): void {
		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->with( 'update_data' )->willReturn( array( 'version' => '9.9' ) );

		$factory = $this->createMock( ApiFactory::class );
		$factory->expects( $this->never() )->method( 'make_for_install' );

		$result = ( new Update(
			$this->plugin(),
			$this->createMock( ActivationRepository::class ),
			$cache,
			$factory
		) )->get_update_data();

		$this->assertSame( array( 'version' => '9.9' ), $result );
	}

	public function test_get_update_data_fetches_when_cache_miss_and_persists_for_a_day(): void {
		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->willReturn( false );
		$cache->method( 'is_throttled' )->willReturn( false );
		$cache->expects( $this->once() )
			->method( 'set' )
			->with( 'update_data', array( 'version' => '1.1' ), DAY_IN_SECONDS );

		$repo = $this->createMock( ActivationRepository::class );
		$repo->method( 'get' )->willReturn( $this->active_activation() );

		$api = $this->createMock( ApiClientInterface::class );
		$api->method( 'get' )->willReturn( array( 'version' => '1.1' ) );

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_for_install' )->willReturn( $api );

		$result = ( new Update( $this->plugin(), $repo, $cache, $factory ) )->get_update_data();

		$this->assertSame( array( 'version' => '1.1' ), $result );
	}

	public function test_get_update_data_does_not_cache_wp_error(): void {
		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->willReturn( false );
		$cache->method( 'is_throttled' )->willReturn( true );
		$cache->expects( $this->never() )->method( 'set' );

		$result = ( new Update(
			$this->plugin(),
			$this->createMock( ActivationRepository::class ),
			$cache,
			$this->createMock( ApiFactory::class )
		) )->get_update_data();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'too_many_requests', $result->get_error_code() );
	}

	public function test_get_update_data_force_deletes_cache_before_fetch(): void {
		$cache = $this->createMock( CacheInterface::class );
		$cache->expects( $this->once() )->method( 'delete' )->with( 'update_data' );
		$cache->method( 'get' )->willReturn( array( 'version' => '2.0' ) );

		( new Update(
			$this->plugin(),
			$this->createMock( ActivationRepository::class ),
			$cache,
			$this->createMock( ApiFactory::class )
		) )->get_update_data( true );
	}

	public function test_force_check_query_arg_bypasses_cache(): void {
		$_GET['force-check'] = '1';

		$cache = $this->createMock( CacheInterface::class );
		$cache->expects( $this->once() )->method( 'delete' )->with( 'update_data' );
		$cache->method( 'get' )->willReturn( array( 'version' => '2.0' ) );

		( new Update(
			$this->plugin(),
			$this->createMock( ActivationRepository::class ),
			$cache,
			$this->createMock( ApiFactory::class )
		) )->get_update_data();
	}

	public function test_remote_latest_errors_when_license_inactive(): void {
		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->willReturn( false );
		$cache->method( 'is_throttled' )->willReturn( false );

		$repo = $this->createMock( ActivationRepository::class );
		$repo->method( 'get' )->willReturn( new Activation() );

		$result = ( new Update(
			$this->plugin(),
			$repo,
			$cache,
			$this->createMock( ApiFactory::class )
		) )->get_update_data();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'license_not_active', $result->get_error_code() );
	}

	public function test_get_plugin_info_caches_on_success_only(): void {
		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->with( 'plugin_info' )->willReturn( false );
		$cache->expects( $this->once() )
			->method( 'set' )
			->with( 'plugin_info', array( 'banner_url' => 'b' ), DAY_IN_SECONDS );

		$api = $this->createMock( ApiClientInterface::class );
		$api->method( 'get' )->willReturn( array( 'banner_url' => 'b' ) );

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_for_plugin' )->willReturn( $api );

		( new Update(
			$this->plugin(),
			$this->createMock( ActivationRepository::class ),
			$cache,
			$factory
		) )->get_plugin_info();
	}

	public function test_get_plugin_info_does_not_cache_error(): void {
		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->willReturn( false );
		$cache->expects( $this->never() )->method( 'set' );

		$api = $this->createMock( ApiClientInterface::class );
		$api->method( 'get' )->willReturn( new WP_Error( 'x', 'y' ) );

		$factory = $this->createMock( ApiFactory::class );
		$factory->method( 'make_for_plugin' )->willReturn( $api );

		$result = ( new Update(
			$this->plugin(),
			$this->createMock( ActivationRepository::class ),
			$cache,
			$factory
		) )->get_plugin_info();

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_purge_plugin_clears_cache_only_for_matching_plugin(): void {
		Functions\when( 'plugin_basename' )->justReturn( 'duck/duck.php' );

		$cache = $this->createMock( CacheInterface::class );
		$cache->expects( $this->once() )->method( 'delete' )->with( 'update_data' );

		( new Update(
			$this->plugin(),
			$this->createMock( ActivationRepository::class ),
			$cache,
			$this->createMock( ApiFactory::class )
		) )->purge_plugin(
			null,
			array(
				'action'  => 'update',
				'type'    => 'plugin',
				'plugins' => array( 'duck/duck.php' ),
			)
		);
	}

	public function test_purge_plugin_noop_for_other_action(): void {
		Functions\when( 'plugin_basename' )->justReturn( 'duck/duck.php' );

		$cache = $this->createMock( CacheInterface::class );
		$cache->expects( $this->never() )->method( 'delete' );

		( new Update(
			$this->plugin(),
			$this->createMock( ActivationRepository::class ),
			$cache,
			$this->createMock( ApiFactory::class )
		) )->purge_plugin(
			null,
			array(
				'action'  => 'install',
				'type'    => 'plugin',
				'plugins' => array( 'duck/duck.php' ),
			)
		);
	}

	public function test_purge_plugin_noop_when_other_plugin_updated(): void {
		Functions\when( 'plugin_basename' )->justReturn( 'duck/duck.php' );

		$cache = $this->createMock( CacheInterface::class );
		$cache->expects( $this->never() )->method( 'delete' );

		( new Update(
			$this->plugin(),
			$this->createMock( ActivationRepository::class ),
			$cache,
			$this->createMock( ApiFactory::class )
		) )->purge_plugin(
			null,
			array(
				'action'  => 'update',
				'type'    => 'plugin',
				'plugins' => array( 'other/other.php' ),
			)
		);
	}

	public function test_plugins_api_filter_passes_through_for_other_action(): void {
		$update = new Update(
			$this->plugin(),
			$this->createMock( ActivationRepository::class ),
			$this->createMock( CacheInterface::class ),
			$this->createMock( ApiFactory::class )
		);

		$args  = (object) array( 'slug' => 'duck' );
		$input = (object) array( 'untouched' => true );

		$this->assertSame( $input, $update->plugins_api_filter( $input, 'other', $args ) );
	}

	public function test_plugins_api_filter_passes_through_for_other_slug(): void {
		$update = new Update(
			$this->plugin(),
			$this->createMock( ActivationRepository::class ),
			$this->createMock( CacheInterface::class ),
			$this->createMock( ApiFactory::class )
		);

		$args = (object) array( 'slug' => 'other' );

		$this->assertFalse( $update->plugins_api_filter( false, 'plugin_information', $args ) );
	}

	public function test_plugins_api_filter_passes_through_when_inactive(): void {
		$repo = $this->createMock( ActivationRepository::class );
		$repo->method( 'get' )->willReturn( new Activation() );

		$update = new Update(
			$this->plugin(),
			$repo,
			$this->createMock( CacheInterface::class ),
			$this->createMock( ApiFactory::class )
		);

		$args = (object) array( 'slug' => 'duck' );

		$this->assertFalse( $update->plugins_api_filter( false, 'plugin_information', $args ) );
	}

	public function test_plugins_api_filter_builds_payload_on_success(): void {
		Functions\when( 'wp_parse_args' )->alias(
			static function ( $a, $b ) {
				return array_merge( (array) $b, (array) $a );
			}
		);

		$repo = $this->createMock( ActivationRepository::class );
		$repo->method( 'get' )->willReturn( $this->active_activation() );

		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->willReturnOnConsecutiveCalls(
			array(
				'version'                               => '2.0',
				'url'                                   => 'https://dl',
				'updated'                               => '2024-01-01',
				'requires_platform_version'             => '6.0',
				'requires_programming_language_version' => '7.4',
				'tested_up_to_version'                  => '6.5',
			),
			array(
				'banner_url'      => 'b1',
				'card_banner_url' => 'b2',
				'description'     => 'desc',
			)
		);

		$update = new Update(
			$this->plugin(),
			$repo,
			$cache,
			$this->createMock( ApiFactory::class )
		);

		$args   = (object) array( 'slug' => 'duck' );
		$result = $update->plugins_api_filter( false, 'plugin_information', $args );

		$this->assertSame( 'Duck', $result->name );
		$this->assertSame( '2.0', $result->version );
		$this->assertSame( 'https://dl', $result->download_link );
		$this->assertSame( '2024-01-01', $result->last_updated );
		$this->assertSame( 'b1', $result->banners['high'] );
		$this->assertSame( 'desc', $result->sections['description'] );
	}

	public function test_plugin_updates_transient_skips_when_no_checked(): void {
		$update = new Update(
			$this->plugin(),
			$this->createMock( ActivationRepository::class ),
			$this->createMock( CacheInterface::class ),
			$this->createMock( ApiFactory::class )
		);

		$transient = (object) array();

		$this->assertSame( $transient, $update->plugin_updates_transient( $transient ) );
	}

	public function test_plugin_updates_transient_injects_response_when_newer_compatible(): void {
		Functions\when( 'plugin_basename' )->justReturn( 'duck/duck.php' );
		Functions\when( 'get_bloginfo' )->justReturn( '6.5' );

		$repo = $this->createMock( ActivationRepository::class );
		$repo->method( 'get' )->willReturn( $this->active_activation() );

		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->willReturn(
			array(
				'version'                               => '2.0',
				'url'                                   => 'https://dl',
				'requires_platform_version'             => '5.0',
				'requires_programming_language_version' => '7.0',
			)
		);

		$update = new Update(
			$this->plugin(),
			$repo,
			$cache,
			$this->createMock( ApiFactory::class )
		);

		$transient = (object) array(
			'checked'  => array( 'duck/duck.php' => '1.0.0' ),
			'response' => array(),
		);
		$result    = $update->plugin_updates_transient( $transient );

		$this->assertArrayHasKey( 'duck/duck.php', $result->response );
		$this->assertSame( '2.0', $result->response['duck/duck.php']->new_version );
	}

	public function test_plugin_updates_transient_skips_when_not_newer(): void {
		Functions\when( 'plugin_basename' )->justReturn( 'duck/duck.php' );
		Functions\when( 'get_bloginfo' )->justReturn( '6.5' );

		$repo = $this->createMock( ActivationRepository::class );
		$repo->method( 'get' )->willReturn( $this->active_activation() );

		$cache = $this->createMock( CacheInterface::class );
		$cache->method( 'get' )->willReturn(
			array(
				'version'                               => '1.0.0',
				'url'                                   => 'https://dl',
				'requires_platform_version'             => '5.0',
				'requires_programming_language_version' => '7.0',
			)
		);

		$update = new Update(
			$this->plugin(),
			$repo,
			$cache,
			$this->createMock( ApiFactory::class )
		);

		$transient = (object) array(
			'checked'  => array( 'duck/duck.php' => '1.0.0' ),
			'response' => array(),
		);
		$result    = $update->plugin_updates_transient( $transient );

		$this->assertSame( array(), $result->response );
	}
}
