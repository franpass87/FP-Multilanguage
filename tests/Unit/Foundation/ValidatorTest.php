<?php
/**
 * Validator Unit Tests.
 *
 * @package FP_Multilanguage
 * @since 1.0.0
 */

namespace FP\Multilanguage\Tests\Unit\Foundation;

use PHPUnit\Framework\TestCase;
use FP\Multilanguage\Foundation\Validation\Validator;
use FP\Multilanguage\Foundation\Validation\ValidatorInterface;

/**
 * Validator test case.
 *
 * @since 1.0.0
 */
class ValidatorTest extends TestCase {
	/**
	 * Test validator implements interface.
	 *
	 * @return void
	 */
	public function testValidatorImplementsInterface(): void {
		$validator = new Validator();
		$this->assertInstanceOf( ValidatorInterface::class, $validator );
	}

	/**
	 * Test required validation.
	 *
	 * @return void
	 */
	public function testRequiredValidation(): void {
		$validator = new Validator();

		$this->assertTrue( $validator->validate( 'value', 'required' ) );
		$this->assertFalse( $validator->validate( '', 'required' ) );
		$this->assertFalse( $validator->validate( null, 'required' ) );
	}

	/**
	 * Test email validation.
	 *
	 * @return void
	 */
	public function testEmailValidation(): void {
		$validator = new Validator();

		$this->assertTrue( $validator->validate( 'test@example.com', 'email' ) );
		$this->assertFalse( $validator->validate( 'invalid-email', 'email' ) );
	}

	/**
	 * Test min length validation.
	 *
	 * @return void
	 */
	public function testMinValidation(): void {
		$validator = new Validator();

		$this->assertTrue( $validator->validate( '12345', 'min', array( 5 ) ) );
		$this->assertFalse( $validator->validate( '123', 'min', array( 5 ) ) );
	}

	/**
	 * Test validate all.
	 *
	 * @return void
	 */
	public function testValidateAll(): void {
		$validator = new Validator();

		$data = array(
			'email' => 'test@example.com',
			'name'  => 'Test',
		);

		$rules = array(
			'email' => 'email',
			'name'  => 'required',
		);

		$errors = $validator->validateAll( $data, $rules );
		$this->assertEmpty( $errors );
	}
}









