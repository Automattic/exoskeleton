<?php
/**
 * Class ExoskeletonTest
 *
 * @package Exoskeleton
 */

/**
 * Sample test case.
 */
class ExoskeletonAddRuleTest extends WP_UnitTestCase {

	/**
	 * One rule definition
	 *
	 * @var array A single valid rule definition
	 */
	protected static $single_valid_rule;

	/**
	 * Three rule definitions
	 *
	 * @var array Three valid rule definitions
	 */
	protected static $three_valid_rules;

	/**
	 * Define some valid rule sets to reduce duplication in tests.
	 */
	static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		self::$single_valid_rule = array(
			'route' => '/wp/v2/posts',
			'window' => 5,
			'limit'	=> 2,
			'lockout' => 30,
			'method' => 'any',
			'treat_head_like_get' => false,
		);

		self::$three_valid_rules = array(
			[
				'route' => '/wp/v2/posts',
				'window' => 10,
				'limit'	=> 25,
				'lockout' => 200,
				'method' => 'any',
			],[
				'route' => '/wp/v2/post/1',
				'window' => 100,
				'limit'	=> 5,
				'lockout' => 60,
				'method' => 'GET',
			],[
				'route' => '/wp/v2/post/2',
				'window' => 90,
				'limit'	=> 2,
				'lockout' => 30,
				'method' => 'GET',
			],
		);
	}

	/**
	 * Pre test setup
	 */
	function setUp() {
		$instance = Exoskeleton::get_instance();
		$instance->rules = [];

	}

	/**
	 * Get instance of Exoskeleton
	 */
	function test_get_instance() {
		$this->assertInstanceOf( Exoskeleton::class, Exoskeleton::get_instance() );
	}

	/**
	 * Test adding a valid rule
	 *
	 * @dataProvider validRuleProvider
	 * @param array $rule Valid exoskeleton rule definition.
	 */
	function test_add_valid_rule($rule) {

		$this->assertTrue( exoskeleton_add_rule( $rule ) );
	}

	/**
	 * Test adding rule fails if any required field is missing
	 *
	 * @dataProvider requiredFieldsProvider
	 * @param string $field required field names.
	 */
	public function test_adding_rule_fails_missing_required_fields( $field ) {
		$rule = $this->getValidRuleHelper();
		unset( $rule[ $field ] );
		$this->assertFalse( exoskeleton_add_rule( $rule ) );
	}

	/**
	 * Test adding multiple valid rules
	 */
	function test_adding_multiple_valid_rules() {
		$ruleset = $this->validRuleProvider();
		$this->assertNull( exoskeleton_add_rules( $ruleset[0] ) );
		$instance = Exoskeleton::get_instance();
		$this->assertEquals( 3, count( $instance->rules ) );
	}


	/**
	 * Test adding multiple rules including invalid
	 */
	function test_adding_multiple_rules_including_invalid() {
		$args =	$this::$three_valid_rules;
		unset( $args[1]['lockout'] );

		$this->assertNull( exoskeleton_add_rules( $args ) );
		$instance = Exoskeleton::get_instance();
		$this->assertEquals( 2, count( $instance->rules ) );
	}

	/**
	 * Test adding a valid rule for a custom route
	 * Exoskeleton makes no check for existence of custom route.
	 *
	 * @dataProvider validRuleProvider
	 * @param array $rule Valid exoskeleton rule definition.
	 */
	function test_add_valid_custom_route_rule($rule) {
		$rule['route'] = '/custom/route/that/does/not/exist';

		$this->assertTrue( exoskeleton_add_rule( $rule ) );
	}

	/**
	 * Check that exoskeleton will not overwrite an already existing rule
	 * @dataProvider validRuleProvider
	 * @param array $rule Valid exoskeleton rule definition.
	 */
	function test_internal_add_rule_method_fails_when_adding_existing_rule($rule) {
		$exoskeleton = Exoskeleton::get_instance();
		$this->assertTrue( exoskeleton_add_rule( $rule ) );
		$this->assertFalse( $exoskeleton->add_rule( $rule ) );
	}


	/**
	 * Provides required fields for exoskeleton_add_rule
	 * Both the method and treat_head_like_get fields are defaulted
	 * when called via the add_rule method so are not required here
	 *
	 * @return array Valid exoskeleton rule methods
	 */
	public function requiredFieldsProvider() {
		return [
			[ 'route' ],
			[ 'window' ],
			[ 'limit' ],
			[ 'lockout' ],
		];
	}


	/**
	 * Provides valid exoskeleton rule definitions
	 *
	 * @return array Valid exoskeleton rule methods
	 */
	public function validRuleProvider() {
		return [
			[
				[
					'route' => '/wp/v2/posts',
					'window' => 10,
					'limit'	=> 25,
					'lockout' => 200,
					'method' => 'any',
				],[
					'route' => '/wp/v2/post/1',
					'window' => 100,
					'limit'	=> 5,
					'lockout' => 60,
					'method' => 'GET',
				],[
					'route' => '/wp/v2/post/2',
					'window' => 90,
					'limit'	=> 2,
					'lockout' => 30,
					'method' => 'GET',
				],
			],
		];
	}

	/**
	 * Get a single valid rule definition from the validRuleProvider
	 *
	 * @return array Valid Exoskeleton rule definition
	 */
	public function getValidRuleHelper() {
		$rules = $this->validRuleProvider();
		return $rules[0][0];

	}

}
