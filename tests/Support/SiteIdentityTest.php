<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Support;

use Brain\Monkey\Functions;
use DuckDev\Freemius\Support\SiteIdentity;
use DuckDev\Freemius\Tests\TestCase;

final class SiteIdentityTest extends TestCase {

	public function test_uid_combines_host_blog_id_and_path(): void {
		Functions\when( 'get_current_blog_id' )->justReturn( 3 );
		Functions\when( 'get_site_url' )->justReturn( 'https://example.com/sub' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$expected = md5( 'example.com-3-/sub' );

		$this->assertSame( $expected, ( new SiteIdentity() )->get_uid() );
	}

	public function test_uid_omits_path_when_absent(): void {
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		Functions\when( 'get_site_url' )->justReturn( 'https://example.com' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$expected = md5( 'example.com-1' );

		$this->assertSame( $expected, ( new SiteIdentity() )->get_uid() );
	}

	public function test_uid_returns_32_char_hex(): void {
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		Functions\when( 'get_site_url' )->justReturn( 'https://example.com' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$uid = ( new SiteIdentity() )->get_uid();

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $uid );
	}

	public function test_uid_differs_per_blog_for_multisite(): void {
		Functions\when( 'get_site_url' )->alias(
			static fn( $id ) => 'https://example.com/sub' . $id
		);
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		Functions\when( 'get_current_blog_id' )->justReturn( 2 );
		$uid_a = ( new SiteIdentity() )->get_uid();

		Functions\when( 'get_current_blog_id' )->justReturn( 3 );
		$uid_b = ( new SiteIdentity() )->get_uid();

		$this->assertNotSame( $uid_a, $uid_b );
	}
}
