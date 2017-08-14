<?php

/**
 * The main Exoskeleton Class
 */
class Exoskeleton {

	/**
	 * @var Exoskeleton
	 */
	private static $instance;

	const LOCK_PREFIX = 'exoskeleton_lock_';
	const COUNTER_PREFIX = 'exoskeleton_counter_';

	/**
	 * @var Array
	 */
	public $rules = [];

	/**
	 * Constructor
	 * @return null
	 */
	private function __construct() {
		add_action( 'rest_api_init', [ $this, 'process_custom_routes' ], 999, 1 );
		add_filter( 'rest_pre_dispatch', [ $this, 'shields_up' ], 5, 3 );
	}

	/**
	 * Gets or makes an instance
	 * @return Exoskeleton
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Exoskeleton;
		}
		return self::$instance;
	}

	/**
	 * Add a rule after validating it
	 * @param Array $args
	 * @return Bool whether or not the rule was added
	 */
	public function add_rule( $args ) {
		$defaults = [
			'method' => 'any',
			'treat_head_like_get' => true,
		];
		$args = array_merge( $defaults, $args );

		if ( ! $this->validate_rule( $args ) ) {
			return false;
		}
		$key = $this->generate_rule_key( $args );
		$any_key = $this->anyize_rule_key( $key );
		if ( ! isset( $this->rules[ $key ] ) && ! isset( $this->rules[ $any_key ] ) ) {
			$this->rules[ $key ] = $args;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Makes a key for the rule based on its method and argyments
	 * @param Array $rule
	 * @return String the generated key
	 */
	private function generate_rule_key( $rule ) {
		$method = $rule['method'];
		unset( $rule['method'] );
		return md5( serialize( $rule ) ) . '_' . $method;
	}

	/**
	 * Turn a _method key into an _any key
	 * @param String $key
	 * @return String the key with a _any suffix
	 */
	private function anyize_rule_key( $key ) {
		$parts = explode( '_', $key );
		return $parts[0] . '_any';
	}

	/**
	 * Make sure a rule is valid.  Valid rules are those with a valid method or "any", a positive numeric argument for lockout, window and limit and a boolean argument for treat_head_like_get.  We don't validate routes.
	 * @param Array $args
	 * @return Bool whether the rule is valid
	 */
	public function validate_rule( $args ) {

		//every rule must have
		$required = [ 'route', 'method', 'window', 'limit', 'lockout', 'treat_head_like_get' ];

		//gets a list of the supported rules and tacks on 'HEAD' and the psedo-method 'any'
		if ( class_exists( 'WP_REST_Server' ) ) {
			$available_methods = WP_REST_Server::ALLMETHODS;
			if ( false === strpos( $available_methods, 'HEAD' ) ) {
				$available_methods .= ', HEAD';
			}
			$available_methods .= ', any';
		} else {
			$available_methods = 'GET, POST, PUT, PATCH, DELETE, HEAD, any';
		}

		$valid = true;  //innocent until proven guilty

		foreach ( $required as $key ) {

			if ( ! isset( $args[ $key ] ) ) {
				$valid = false;
			} else {
				switch ( $key ) {
					case 'method':
						$valid = ( false !== strpos( $available_methods, $args[ $key ] ) );
						break;
					case 'lockout':
					case 'window':
					case 'limit':
						$valid = ( is_numeric( $args[ $key ] ) && $args[ $key ] > 0 );
						break;
					case 'treat_head_like_get':
						$valid = is_bool( $args[ $key ] );
						break;
					case 'route':   //don't validate routes
					default:
						$valid = true;
						break;
				}
			}
			if ( ! (bool) $valid ) {
				return (bool) $valid;
			}
		}

		return (bool) $valid;
	}

	/**
	 * Custom (non-built-in) routes receive their rules when they are defined.  This callback sniffs out rules from all the server's endpoints and adds them.
	 * @param WP_REST_Server $server
	 * @return null
	 */
	public function process_custom_routes( $server ) {
		foreach ( $server->get_routes() as $path => $endpoints ) {
			foreach ( $endpoints as $endpoint ) {
				if ( isset( $endpoint['exoskeleton'] ) ) {
					$rule = $endpoint['exoskeleton'];
					//set rule methds -- turn multiple methods into a list
					if ( is_array( $endpoint['methods'] ) ) {
						$rule['method'] = implode( ',', array_keys( $endpoint['methods'] ) );
					} else {
						$rule['method'] = 'any';
					}
					 //set rule path
					$rule['route'] = $path;

					$this->add_rule( $rule );
				}
			}
		}
	}

	/**
	 * Decide whether a given WP_REST_Request is subject to an Exoskeleton Rule.  If so, it invoke the limiter.
	 * @param WP_REST_Response $response
	 * @param WP_REST_Server $handler
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response | null
	 */
	public function shields_up( $response, $handler, $request ) {

		// if there's already a nonempty response, someone else got here first, so bail
		if ( ! empty( $response ) ) {
			return $response;
		}

		$path = $request->get_route();
		$method = $request->get_method();
		$matched_rule = [];

		foreach ( $this->rules as $key => $rule ) {
			$match = preg_match( '@^' . $rule['route'] . '$@i', $path, $match_result );
			if ( 1 === $match ) {
				if ( $this->match_rule_method( $method, $rule ) ) {
					$matched_rule = [
						$key => $rule,
					];
					$this->maybe_back_off( $matched_rule );
				}
			}
		}
		return $response;
	}

	/**
	 * For a given rule, does the method match?
	 * @param String $method
	 * @param Array $rule
	 * @return Bool whether or not the method applies to the rule
	 */
	private function match_rule_method( $method, $rule ) {

		//by default we meter HEAD requests as though they were GETs  rules may override this for particular routes
		if ( 'HEAD' === $method && $rule['treat_head_like_get'] ) {
			$method = 'GET';
		}

		return (
			'any' === $rule['method'] ||
			$rule['method'] === $method ||
			in_array( $method, explode( ',', $rule['method'] ) )
		);
	}

	/**
	 * Decide whether to tell the request to back off based on the current state of the applicable rule's lock.  Also, increment the rule's counter, and set a lock if needed.
	 * @param Array $matched_rule
	 * @return null
	 */
	private function maybe_back_off( $matched_rule ) {

		$rule_id = array_keys( $matched_rule )[0];
		$lock = $this->get_lock( $rule_id );

		if ( $lock ) {
			$this->back_off_please( $rule_id, $matched_rule, $lock );
		}

		// since there's no lock we'll allow the request to continue, but first increment the counter
		$counter = $this->increment_counter( $rule_id, $matched_rule );

		//now see if we need to lock things down for next time
		if ( $counter >= $matched_rule[ $rule_id ]['limit'] ) {
			$this->set_lock( $rule_id, $matched_rule );
		}
	}

	/**
	 * Check for a lock on a given rule or it's universal version
	 * @param String $rule_id
	 * @return Array | Bool  the lock, or false if no lock is found
	 */
	private function get_lock( $rule_id ) {
		$lock = self::LOCK_PREFIX . $rule_id;
		//we might need to check for universal locks and not just the method requested
		$any_lock = self::LOCK_PREFIX . $this->anyize_rule_key( $rule_id );

		$found_lock = get_transient( $lock );
		// looking for the any method lock in the first place? then we're done
		if ( $lock === $any_lock ) {
			return $found_lock;
		}

		//if nothing could be found for the original lock, check the _any version of the lock instead
		if ( false === $found_lock ) {
			return get_transient( $any_lock );
		}
		return $found_lock;
	}

	/**
	 * Sets a lock (transient) lasting lockout seconds for a matched rule_id
	 * @param String $rule_id
	 * @param Array $matched_rule
	 * @return Bool whether or not the lock was successfully set
	 */
	private function set_lock( $rule_id, $matched_rule ) {
		$lock = self::LOCK_PREFIX . $rule_id;
		$lock_data = [
			'lockout' => $matched_rule[ $rule_id ]['lockout'],
			'lock_set' => time(),
		];
		return set_transient( $lock, $lock_data, $matched_rule[ $rule_id ]['lockout'] );
	}

	/**
	 * Terminate the request after sending a 429 and Retry-After header
	 * @param String $rule_id
	 * @param Array $matched_rule
	 * @param Array $lock
	 * @return null
	 */
	private function back_off_please( $rule_id, $matched_rule, $lock ) {

		$lockout = $matched_rule[ $rule_id ]['lockout'];
		$retry_after = ( isset( $lock['lock_set'] ) ) ? max( $lockout - ( time() - $lock['lock_set'] ), 1 ) : $lockout;
		status_header( 429, 'Exoskeleton: too many requests for this endpoint.  Please consult Retry-After and come back later.  Meanwhile enjoy a well-deserved REST' );
		@header( "Retry-After: $retry_after" );
		@header( "Cache-Control: public max-age=$retry_after" );
		status_header( 429, 'Exoskeleton: too many requests for this endpoint.  Please consult Retry-After and come back later.  Meanwhile enjoy a well-deserved REST' );
		@header( "Retry-After: $retry_after" );
		die();
	}

	/**
	 * Increment the counter for requests on the matched endpoint.
	 * @param String $rule_id
	 * @param Array $matched_rule
	 * @return Int the value of the counter after increment
	 */
	private function increment_counter( $rule_id, $matched_rule ) {

		$counter_id = self::COUNTER_PREFIX . $rule_id;
		$counter = get_transient( $counter_id );

		$new_counter = [];
		$new_counter['started_counting_at'] = ( false === $counter ) ? time() : $counter['started_counting_at'];
		$new_counter['value'] = ( false === $counter ) ? 1 : $counter['value'] + 1;

		// the transient life.  This is either the rule window (for a new counter) or the window minus the number of seconds we've already been waiting.  Make sure we set the transient to last at least 1 second here.
		$time_left = ( false === $counter ) ? $matched_rule[ $rule_id ]['window'] : max( 1, $matched_rule[ $rule_id ]['window'] - ( time() - $counter['started_counting_at'] ) );

		set_transient( $counter_id, $new_counter, $time_left );
		return $new_counter['value'];
	}
}
