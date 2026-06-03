<?php

declare( strict_types=1 );

namespace DuckDev\Freemius\Tests\Exceptions;

use DuckDev\Freemius\Exceptions\FreemiusException;
use DuckDev\Freemius\Tests\TestCase;

final class FreemiusExceptionTest extends TestCase {

	public function test_is_exception_subclass(): void {
		$exception = new FreemiusException( 'boom', 42 );

		$this->assertInstanceOf( \Exception::class, $exception );
		$this->assertSame( 'boom', $exception->getMessage() );
		$this->assertSame( 42, $exception->getCode() );
	}
}
