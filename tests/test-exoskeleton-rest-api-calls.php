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

		add_action( 'rest_api_init', function () {
			register_rest_route( 'exoskeleton/v1', '/exoskeleton/(?P<id>\d+)', array(
			  'methods' => 'GET',
			  'callback' => [ $this, 'custom_route_callback' ],
			) );
		} );
    }


	/**
	 * Test key generation
     * @dataProvider limitTestingProvider
     */
	public function test_key_generation( $rule, $test ) {
		exoskeleton_add_rule( $rule );
		$exoskeleton = Exoskeleton::get_instance();
		$this->assertEquals( $test['key'], key($exoskeleton->rules) );
	}

	/**
	 * Simple method to provide a callback for custom routes during testing
	 * @param mixed $data
	 * @return mixed
	 */
	public function custom_route_callback( $data ) {
		return $data;
	}


	function test_pre_registered_custom_route_rules() {

		add_action( 'rest_api_init', function () {
			register_rest_route( 'exoskeleton/v1', '/test/(?P<id>\d+)', array(
			  'methods' => 'GET',
			  'callback' => [ $this, 'custom_route_callback' ],
			  'exoskeleton' => [ 'window' => 10, 'limit'    => 5, 'lockout' => 20 ],
			) );
		} );
        for( $request = 1; $request <= 6; ++$request ) {
			if ( $request < 5 ) {
				$response = rest_do_request( new WP_REST_Request( 'GET', '/exoskeleton/v1/test/1' ) );
				$this->assertEquals( 200, $response->status );
			} else {
				$this->expectException( Exception::class );
				$this->expectExceptionMessage( 'locked' );
				$response = rest_do_request( new WP_REST_Request( 'GET', '/exoskeleton/v1/test/1' ) );
			}
		}
	}


	/**
	 * Check that requests do get properly limited
     * @dataProvider limitTestingProvider
     */
    public function test_limits_are_applied( $rule, $test ) {
		if ( empty( $test['method'] ) ) {
			$test['method'] = $rule['method'];
		}
		$this->assertTrue( exoskeleton_add_rule( $rule ) );
        for( $request = 1; $request <= $rule['limit'] + 1; ++$request ) {
			if ( $request < $rule['limit'] ) {
				$response = rest_do_request( new WP_REST_Request( $test['method'], $rule['route'] ) );
				$this->assertEquals( 200, $response->status );
			} else {
				$this->expectException( Exception::class );
				$this->expectExceptionMessage( 'locked' );
				$response = rest_do_request( new WP_REST_Request( $test['method'], $rule['route'] ) );
			}
		}
    }


	/**
	 * Make sure that requests falling outside the window do not get imited.
     * @dataProvider limitTestingProvider
     */
    public function test_limit_window_expiry( $rule, $test ) {
		if ( empty( $test['method'] ) ) {
			$test['method'] = $rule['method'];
		}
		$this->assertTrue( exoskeleton_add_rule( $rule ) );
        for( $request = 1; $request <= $rule['limit'] + 1; ++$request ) {
			if ( $request < $rule['limit'] ) {
				$response = rest_do_request( new WP_REST_Request( $test['method'], $rule['route'] ) );
				$this->assertEquals( 200, $response->status );
			} else {
				sleep( $rule['window'] + 1 );
				$response = rest_do_request( new WP_REST_Request( $test['method'], $rule['route'] ) );
				$this->assertEquals( 200, $response->status );
			}
		}
    }

	/**
	 * Limits should only be applied if the correct method is requested
     * @dataProvider limitTestingProvider
     */
    public function test_different_methods_not_limited( $rule, $test ) {
		// Change the test method
		if ( empty( $test['method'] ) ) {
			$test['method'] = 'HEAD';
		} else {
			$rule['method'] = 'POST';
		}
		$this->assertTrue( exoskeleton_add_rule( $rule ) );
        for( $request = 1; $request <= $rule['limit'] + 1; ++$request ) {
			$response = rest_do_request( new WP_REST_Request( $test['method'], $rule['route'] ) );
			$this->assertEquals( 200, $response->status );
		}
    }




	public function limitTestingProvider() {
        return [
			[
				'rule' => [
					'route' => '/wp/v2/posts',
					'window' => 1,
					'limit'	=> 3,
					'lockout' => 5,
					'method' => 'GET',
					'treat_head_like_get' => false,
				],
				'test' => [
					'key' => '01dd28291a6b5b95802281831ec3d6f5_GET',
				]
			],
			[
				'rule' => [
					'route' => '/wp/v2/categories',
					'window' => 2,
					'limit'	=> 6,
					'lockout' => 5,
					'method' => 'GET',
					'treat_head_like_get' => false,
				],
				'test' => [
					'key' => 'fd568c1eb104fbad04765b9f2f0100ed_GET',
				]
			],
			[
				'rule' => [
					'route' => '/wp/v2/categories',
					'window' => 2,
					'limit'	=> 10,
					'lockout' => 5,
					'method' => 'GET',
					'treat_head_like_get' => true,
				],
				'test' => [
					'method' => 'HEAD',
					'key' => '6ae5a5054b0a306061f10b9c1b193183_GET',
				]
			],
			[
				'rule' => [
					'route' => '/exoskeleton/v1/exoskeleton/10',
					'window' => 1,
					'limit'	=> 3,
					'lockout' => 5,
					'method' => 'GET',
					'treat_head_like_get' => false,
				],
				'test' => [
					'key' => 'b09a74d523deb0962e21cafaadc63679_GET',
				]
			],
		];
    }

}