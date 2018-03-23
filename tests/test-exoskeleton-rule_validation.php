<?php
/**
 * Class ExoskeletonTest
 *
 * @package Exoskeleton
 */

/**
 * Sample test case.
 */
class ExoskeletonRuleValidationTest extends WP_UnitTestCase {

	/**
	 * Post test cleanup
	 */
	function tearDown() {
		parent::tearDown();
		// Clear up any added rules
		$instance = Exoskeleton::get_instance();
		$instance->rules = [];
	}


	/**
	 * Check that exoskeleton fails to validate an invalid rule
	 */
	function test_validate_rule_fails_when_adding_invalid_rule() {
		$exoskeleton = Exoskeleton::get_instance();
		$rule = $this->getValidRuleHelper();
		unset( $rule['method'] );
		$this->assertFalse( $exoskeleton->validate_rule( $rule ) );
	}

	/**
	 * Check that exoskeleton validates rules with valid rule
	 */
	function test_validate_rule_passes_when_adding_a_valid_rule() {
		$exoskeleton = Exoskeleton::get_instance();
		$rule = $this->getValidRuleHelper();
		$this->assertTrue( $exoskeleton->validate_rule( $rule ) );
	}


	/**
	 * Test validation passes available methods
	 *
	 * @dataProvider validationMethodsProvider
	 * @param string $method Valid method name for validation.
	 */
	public function test_validate_rule_passes_all_available_methods( $method ) {
		$rule = $this->getValidRuleHelper();
		$rule['method'] = $method;
		$exoskeleton = Exoskeleton::get_instance();
		$this->assertTrue( $this->invokeMethod( $exoskeleton, 'validate_rule', array( $rule ) ) );
	}

	/**
	 * Test validation fails is any required field is missing
	 *
	 * @dataProvider requiredFieldsProvider
	 * @param string $field required field names.
	 */
	public function test_validate_rule_fails_missing_required_fields( $field ) {
		$rule = $this->getValidRuleHelper();
		unset( $rule[ $field ] );
		$exoskeleton = Exoskeleton::get_instance();
		$this->assertFalse( $this->invokeMethod( $exoskeleton, 'validate_rule', array( $rule ) ) );
	}

	/**
	 * Test validation fails if fields contain invalid data
	 *
	 * @dataProvider invalidFieldValueProvider
	 * @param string $field array field_name => invalid_test_value.
	 */
	public function test_validate_rule_fails_invalid_field_values( $field ) {
		$rule = $this->getValidRuleHelper();
		$rule[ key( $field ) ] = $field;
		$exoskeleton = Exoskeleton::get_instance();
		$this->assertFalse( $this->invokeMethod( $exoskeleton, 'validate_rule', array( $rule ) ) );
	}


	/**
	 * Data provider
	 *
	 * @return array Valid exoskeleton rule methods
	 */
	public function validationMethodsProvider() {
		return [
			[ 'GET' ],
			[ 'POST' ],
			[ 'PUT' ],
			[ 'PATCH' ],
			[ 'DELETE' ],
			[ 'HEAD' ],
			[ 'any' ],
		];
	}

	/**
	 * Data provider
	 *
	 * @return array Valid exoskeleton rule methods
	 */
	public function requiredFieldsProvider() {
		return [
			[ 'route' ],
			[ 'method' ],
			[ 'window' ],
			[ 'limit' ],
			[ 'lockout' ],
			[ 'treat_head_like_get' ],
		];
	}

	/**
	 * Data provider
	 *
	 * @return array Valid exoskeleton rule methods
	 */
	public function invalidFieldValueProvider() {
		return [
			[
				[
					'window' => 'string',
				],
			],
			[
				[
					'window' => -1,
				],
			],
			[
				[
					'limit' => 'string',
				],
			],
			[
				[
					'limit' => -1,
				],
			],
			[
				[
					'lockout' => 'string',
				],
			],
			[
				[
					'treat_head_like_get' => 'string',
				],
			],
		];
	}


	/**
	 * Get a single valid rule definition 
	 *
	 * @return array Valid Exoskeleton rule definition
	 */
	public function getValidRuleHelper() {
		return [
			'route' => '/wp/v2/posts',
			'window' => 5,
			'limit'	=> 2,
			'lockout' => 30,
			'method' => 'any',
			'treat_head_like_get' => false,
		];

	}


	/**
	 * Call protected/private method of a class.
	 *
	 * @param object $object    Instantiated object that we will run method on.
	 * @param string $method_name Method name to call.
	 * @param array  $parameters Array of parameters to pass into method.
	 *
	 * @return mixed Method return.
	 */
	public function invokeMethod( &$object, $method_name, array $parameters = array() ) {
		$reflection = new \ReflectionClass( get_class( $object ) );
		$method = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $object, $parameters );
	}

}
