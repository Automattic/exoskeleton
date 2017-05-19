<?php
/**
 * Class ExoskeletonTest
 *
 * @package Exoskeleton
 */

/**
 * Sample test case.
 */
class ExoskeletonRestApiCallsTest extends WP_UnitTestCase {

	protected static $single_valid_rule;
	protected static $three_valid_rules;

	protected $server;

	/**
	 * Define some valid rule sets to reduce duplication in tests.
	 */
	static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		self::$single_valid_rule = array(
			'route' => '/wp/v2/posts',
			'window' => 5,
			'limit'	=> 1,
			'lockout' => 5,
			'method' => 'any',
		);
	}

    function setUp() {
		parent::setUp();

		// Clear existing rules.
		$instance = Exoskeleton::get_instance();
		$instance->rules = [];

		exoskeleton_add_rule( $this::$single_valid_rule );

    }

	/**
	 * Test route is not limited
	 */
	function test_route_is_not_limited() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$response = rest_do_request($request);
		$this->assertEquals( 200, $response->status );
	}

	/**
	 * Test route has been restricted
	 */
	function test_route_is_limited() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$response = rest_do_request($request);
		$this->assertEquals( 200, $response->status );

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('locked');
		$response = rest_do_request($request);
	}

}
