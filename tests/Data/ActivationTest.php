<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Data;

use DuckDev\Freemius\Data\Activation;
use DuckDev\Freemius\Tests\TestCase;

final class ActivationTest extends TestCase {

	private function sampleData(): array {
		return array(
			'install_id'        => 12345,
			'date'              => '2024-01-02 03:04:05',
			'status'            => Activation::STATUS_ACTIVATED,
			'activation_params' => array(
				'license_key' => 'KEY-1',
				'uid'         => 'abc123',
				'url'         => 'https://example.com',
				'version'     => '1.0.0',
			),
			'install_data'      => array(
				'install_public_key' => 'pk_x',
				'install_secret_key' => 'sk_x',
			),
		);
	}

	public function test_empty_activation_returns_defaults(): void {
		$activation = new Activation();

		$this->assertTrue( $activation->is_empty() );
		$this->assertFalse( $activation->is_active() );
		$this->assertSame( '', $activation->install_id() );
		$this->assertSame( '', $activation->license_key() );
		$this->assertSame( '', $activation->uid() );
		$this->assertSame( '', $activation->status() );
		$this->assertSame( '', $activation->date() );
		$this->assertSame( array(), $activation->activation_params() );
		$this->assertSame( array(), $activation->install_data() );
	}

	public function test_from_array_returns_equivalent_instance(): void {
		$data       = $this->sampleData();
		$activation = Activation::from_array( $data );

		$this->assertSame( $data, $activation->to_array() );
	}

	public function test_accessors_return_persisted_values(): void {
		$activation = new Activation( $this->sampleData() );

		$this->assertSame( '12345', $activation->install_id() );
		$this->assertSame( 'KEY-1', $activation->license_key() );
		$this->assertSame( 'abc123', $activation->uid() );
		$this->assertSame( Activation::STATUS_ACTIVATED, $activation->status() );
		$this->assertSame( '2024-01-02 03:04:05', $activation->date() );
		$this->assertNotEmpty( $activation->activation_params() );
		$this->assertNotEmpty( $activation->install_data() );
	}

	public function test_api_keys_built_from_install_data(): void {
		$keys = ( new Activation( $this->sampleData() ) )->api_keys();

		$this->assertSame( 'pk_x', $keys->get_public_key() );
		$this->assertSame( 'sk_x', $keys->get_secret_key() );
		$this->assertTrue( $keys->is_signable() );
	}

	public function test_api_keys_unsignable_when_install_data_missing(): void {
		$keys = ( new Activation() )->api_keys();

		$this->assertFalse( $keys->is_signable() );
	}

	public function test_has_required_keys_false_when_any_missing(): void {
		$data = $this->sampleData();
		unset( $data['install_id'] );

		$this->assertFalse( ( new Activation( $data ) )->has_required_keys() );
	}

	public function test_is_active_requires_status_activated(): void {
		$data           = $this->sampleData();
		$data['status'] = Activation::STATUS_DEACTIVATED;

		$this->assertFalse( ( new Activation( $data ) )->is_active() );
	}

	public function test_is_active_true_for_complete_activated_record(): void {
		$this->assertTrue( ( new Activation( $this->sampleData() ) )->is_active() );
	}

	public function test_with_returns_new_instance_with_overrides(): void {
		$original = new Activation( $this->sampleData() );
		$mutated  = $original->with( array( 'status' => Activation::STATUS_DEACTIVATED ) );

		$this->assertNotSame( $original, $mutated );
		$this->assertSame( Activation::STATUS_ACTIVATED, $original->status() );
		$this->assertSame( Activation::STATUS_DEACTIVATED, $mutated->status() );
	}

	public function test_with_scrubbed_license_clears_only_license_key(): void {
		$activation = ( new Activation( $this->sampleData() ) )->with_scrubbed_license();

		$this->assertSame( '', $activation->license_key() );
		$this->assertSame( 'abc123', $activation->uid() );
		$this->assertSame( '12345', $activation->install_id() );
	}

	public function test_with_scrubbed_license_is_noop_when_no_key(): void {
		$activation = ( new Activation() )->with_scrubbed_license();

		$this->assertSame( '', $activation->license_key() );
	}
}
