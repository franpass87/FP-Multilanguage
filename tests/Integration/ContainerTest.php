<?php
/**
 * Container Integration Tests.
 *
 * @package FP_Multilanguage
 * @since 1.0.0
 */

namespace FP\Multilanguage\Tests\Integration;

use PHPUnit\Framework\TestCase;
use FP\Multilanguage\Kernel\Container;
use FP\Multilanguage\Kernel\Plugin;
use FP\Multilanguage\Providers\FoundationServiceProvider;
use FP\Multilanguage\Providers\CoreServiceProvider;

/**
 * Container integration test case.
 *
 * @since 1.0.0
 */
class ContainerTest extends TestCase {
	/**
	 * Test container can resolve services.
	 *
	 * @return void
	 */
	public function testContainerResolvesServices(): void {
		$container = new Container();

		// Register a test service
		$container->bind( 'test.service', function() {
			return new \stdClass();
		}, true );

		$service = $container->get( 'test.service' );
		$this->assertInstanceOf( \stdClass::class, $service );
	}

	/**
	 * Test container singleton behavior.
	 *
	 * @return void
	 */
	public function testContainerSingleton(): void {
		$container = new Container();

		$container->bind( 'test.singleton', function() {
			return new \stdClass();
		}, true );

		$service1 = $container->get( 'test.singleton' );
		$service2 = $container->get( 'test.singleton' );

		$this->assertSame( $service1, $service2 );
	}

	/**
	 * Test container aliases.
	 *
	 * @return void
	 */
	public function testContainerAliases(): void {
		$container = new Container();

		$container->bind( 'test.service', function() {
			return new \stdClass();
		}, true );

		$container->alias( 'test.alias', 'test.service' );

		$service = $container->get( 'test.alias' );
		$this->assertInstanceOf( \stdClass::class, $service );
	}

	/**
	 * Test FoundationServiceProvider registers services.
	 *
	 * @return void
	 */
	public function testFoundationServiceProvider(): void {
		$container = new Container();
		$provider = new FoundationServiceProvider();

		$provider->register( $container );

		// Check that services are registered
		$this->assertTrue( $container->has( 'logger' ) );
		$this->assertTrue( $container->has( 'cache' ) );
		$this->assertTrue( $container->has( 'options' ) );
		$this->assertTrue( $container->has( 'validator' ) );
		$this->assertTrue( $container->has( 'sanitizer' ) );
	}

	/**
	 * Test CoreServiceProvider registers services.
	 *
	 * @return void
	 */
	public function testCoreServiceProvider(): void {
		$container = new Container();
		
		// First register Foundation services (dependencies)
		$foundation_provider = new FoundationServiceProvider();
		$foundation_provider->register( $container );

		$core_provider = new CoreServiceProvider();
		$core_provider->register( $container );

		// Check that core services are registered
		$this->assertTrue( $container->has( 'queue' ) || $container->has( 'translation.manager' ) );
	}
}









