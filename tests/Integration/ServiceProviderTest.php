<?php
/**
 * Service Provider Integration Tests.
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
 * Service provider integration test case.
 *
 * @since 1.0.0
 */
class ServiceProviderTest extends TestCase {
	/**
	 * Test plugin kernel initializes correctly.
	 *
	 * @return void
	 */
	public function testPluginKernelInitialization(): void {
		$plugin_file = __FILE__; // Mock plugin file
		$kernel = new Plugin( $plugin_file );

		$this->assertInstanceOf( Plugin::class, $kernel );
		$this->assertNotNull( $kernel->getContainer() );
	}

	/**
	 * Test service providers can be registered.
	 *
	 * @return void
	 */
	public function testServiceProviderRegistration(): void {
		$plugin_file = __FILE__;
		$kernel = new Plugin( $plugin_file );

		$kernel->registerProviders();

		$container = $kernel->getContainer();

		// Check that foundation services are registered
		$this->assertTrue( $container->has( 'logger' ) || $container->has( 'cache' ) || $container->has( 'options' ) );
	}

	/**
	 * Test service providers can boot.
	 *
	 * @return void
	 */
	public function testServiceProviderBoot(): void {
		$plugin_file = __FILE__;
		$kernel = new Plugin( $plugin_file );

		$kernel->registerProviders();
		$kernel->boot();

		// If we get here without exceptions, boot was successful
		$this->assertTrue( true );
	}

	/**
	 * Test FoundationServiceProvider provides list.
	 *
	 * @return void
	 */
	public function testFoundationServiceProviderProvides(): void {
		$provider = new FoundationServiceProvider();
		$provides = $provider->provides();

		$this->assertIsArray( $provides );
		$this->assertContains( 'logger', $provides );
		$this->assertContains( 'cache', $provides );
		$this->assertContains( 'options', $provides );
	}

	/**
	 * Test CoreServiceProvider provides list.
	 *
	 * @return void
	 */
	public function testCoreServiceProviderProvides(): void {
		$provider = new CoreServiceProvider();
		$provides = $provider->provides();

		$this->assertIsArray( $provides );
		$this->assertNotEmpty( $provides );
	}
}









