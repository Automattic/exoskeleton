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

    function setUp() {
		parent::setUp();

		// Clear existing rules.
		$instance = Exoskeleton::get_instance();
		$instance->rules = [];


    }

	/**
	 * Check that requests do get properly limited
     * @dataProvider limitTestingProvider
     */
    public function test_limits_are_applied( $rule, $test_method = false )
    {
		if ( empty( $test_method ) ) {
			$test_method = $rule['method'];
		}
		$this->assertTrue( exoskeleton_add_rule( $rule ) );
        for( $request = 1; $request <= $rule['limit'] + 1; ++$request ) {
			if ( $request < $rule['limit'] ) {
				$response = rest_do_request( new WP_REST_Request( $test_method, $rule['route'] ) );
				$this->assertEquals( 200, $response->status );
			} else {
				$this->expectException( Exception::class );
				$this->expectExceptionMessage( 'locked' );
				$response = rest_do_request( new WP_REST_Request( $test_method, $rule['route'] ) );
			}
		}
    }


	/**
	 * Make sure that requests falling outside the window do not get imited.
     * @dataProvider limitTestingProvider
     */
    public function test_limit_window_expiry( $rule, $test_method = false )
    {
		if ( empty( $test_method ) ) {
			$test_method = $rule['method'];
		}
		$this->assertTrue( exoskeleton_add_rule( $rule ) );
        for( $request = 1; $request <= $rule['limit'] + 1; ++$request ) {
			if ( $request < $rule['limit'] ) {
				$response = rest_do_request( new WP_REST_Request( $test_method, $rule['route'] ) );
				$this->assertEquals( 200, $response->status );
			} else {
				sleep( $rule['window'] + 1 );
				$response = rest_do_request( new WP_REST_Request( $test_method, $rule['route'] ) );
				$this->assertEquals( 200, $response->status );
			}
		}
    }

	/**
	 * Limits should only be applied if the correct method is requested
     * @dataProvider limitTestingProvider
     */
    public function test_different_methods_not_limited( $rule, $test_method = false )
    {
		// Change the test method
		if ( empty( $test_method ) ) {
			$test_method = 'HEAD';
		} else {
			$this->assertNull( null ); // TODO Test third request with valid method
			return;
		}
		$this->assertTrue( exoskeleton_add_rule( $rule ) );
        for( $request = 1; $request <= $rule['limit'] + 1; ++$request ) {
			$response = rest_do_request( new WP_REST_Request( $test_method, $rule['route'] ) );
			$this->assertEquals( 200, $response->status );
		}
    }



	public function limitTestingProvider()
    {
        return [
			[
				array(
					'route' => '/wp/v2/posts',
					'window' => 5,
					'limit'	=> 3,
					'lockout' => 5,
					'method' => 'GET',
					'treat_head_like_get' => false,
				)
			],
			[
				array(
					'route' => '/wp/v2/categories',
					'window' => 2,
					'limit'	=> 10,
					'lockout' => 5,
					'method' => 'GET',
					'treat_head_like_get' => false,
				)
			],
			[
				array(
					'route' => '/wp/v2/categories',
					'window' => 2,
					'limit'	=> 10,
					'lockout' => 5,
					'method' => 'GET',
					'treat_head_like_get' => true,
				),
				'HEAD',
			]
		];
    }

}