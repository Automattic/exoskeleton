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

	protected static $single_valid_rule;
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
				]
		);
	}

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
	 */
	function test_add_valid_rule() {

		$this->assertTrue( exoskeleton_add_rule( $this::$single_valid_rule ) );
	}

	/**
	 * Test adding a valid rule - missing method
	 */
	function test_add_valid_rule_missing_method() {
		$args =	$this::$single_valid_rule;
		unset( $args['method'] );

		$this->assertTrue( exoskeleton_add_rule( $args ) );
	}

	/**
	 * Test adding an invalid rule - missing route
	 */
	function test_add_invalid_rule_missing_route() {
		$args =	$this::$single_valid_rule;
		unset( $args['route'] );

		$this->assertFalse( exoskeleton_add_rule( $args ) );
	}

	/**
	 * Test adding an invalid rule - missing window
	 */
	function test_add_invalid_rule_missing_window() {
		$args =	$this::$single_valid_rule;
		unset( $args['window'] );

		$this->assertFalse( exoskeleton_add_rule( $args ) );
	}

	/**
	 * Test adding an invalid rule - missing limit
	 */
	function test_add_invalid_rule_missing_limit() {
		$args =	$this::$single_valid_rule;
		unset( $args['limit'] );

		$this->assertFalse( exoskeleton_add_rule( $args ) );
	}

	/**
	 * Test adding an invalid rule - missing lockout
	 */
	function test_add_invalid_rule_missing_lockout() {
		$args =	$this::$single_valid_rule;
		unset( $args['lockout'] );

		$this->assertFalse( exoskeleton_add_rule( $args ) );
	}

	/**
	 * Test adding multiple valid rules
	 */
	function test_adding_multiple_valid_rules() {
		$args =	$this::$three_valid_rules;

		$this->assertNull( exoskeleton_add_rules( $args ) );
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
	 */
	function test_add_valid_custom_route_rule() {
		$args =	$this::$single_valid_rule;
		$args['route'] = '/custom/route/that/does/not/exist';

		$this->assertTrue( exoskeleton_add_rule( $args ) );
	}

	/**
	 * Test route is not limited
	 */
	function test_route_is_not_limited() {
		$this->assertTrue( exoskeleton_add_rule( $this::$single_valid_rule ) );

	}

}
