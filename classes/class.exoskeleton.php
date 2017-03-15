<?php

class Exoskeleton {

	private static $instance;

	const LOCK_PREFIX = 'exoskeleton_lock_';
	const COUNTER_PREFIX = 'exoskeleton_counter_';

	public $rules = [];

	private function __construct() {
		add_action( 'rest_api_init', [ $this, 'process_custom_routes' ], 999, 1 );
		add_filter( 'rest_pre_dispatch', [ $this, 'shields_up' ], 5, 3 );
	}

	public static function get_instance() {
		if ( !self::$instance ) {
			self::$instance = new Exoskeleton;
		}
		return self::$instance;
	}

	public function add_rule( $args ) {
		$defaults = [
			'method' => 'any',
			'treat_head_like_get' => true,
		];
		$args = array_merge( $defaults, $args );
		if ( !$this->validate_rule( $args ) ) {
			return false;
		}
		$key = $this->generate_rule_key( $args );
		$any_key = $this->anyize_rule_key( $key );
		if ( !isset( $this->rules[ $key ] ) && !isset( $this->rules[ $any_key ] ) ) {
			$this->rules[ $key ] = $args;
			return true;
		}else{
			return false;
		}
	}

	private function generate_rule_key( $rule ) {
		$method = $rule[ 'method' ];
		unset( $rule[ 'method' ] );
		return md5( serialize( $rule ) ) . '_' . $method;
	}

	private function anyize_rule_key( $key ) {
		$parts = explode( '_', $key );
		return $parts[0] . '_any';
	}

	//@todo make this
	public function validate_rule( $args ) {
		return true;
	}

	public function process_custom_routes( $server ) {
		foreach( $server->get_routes() as $path => $endpoints ) {
			foreach ( $endpoints as $endpoint ) {
				if ( isset( $endpoint[ 'exoskeleton' ] ) ) {
					$rule = $endpoint[ 'exoskeleton' ];
					//set rule methds -- turn multiple methods into a list
					if ( is_array( $endpoint[ 'methods' ] ) ) {
						$rule[ 'method' ] = implode( ',', array_keys( $endpoint[ 'methods' ] ) );
 					}else{
 						$rule[ 'method' ] = 'any';
 					}
 					//set rule path
					$rule[ 'route' ] = $path;

					if ( !$this->validate_rule( $rule ) ) {
						return false;
					}
					$this->add_rule( $rule );
				}
			}
		}
	}

	public function shields_up( $response, $handler, $request ) {

		// if there's already a nonempty response, someone else got here first, so bail
		if ( !empty( $response ) ) {
			return $response;
		}

		$path = $request->get_route();
		$method = $request->get_method();
		$matched_rule = [];

		foreach ( $this->rules as $key => $rule ) {
			$match = preg_match( '@^' . $rule[ 'route' ] . '$@i', $path, $match_result );
			if ( 1 === $match ) {
				if ( $this->match_rule_method( $method, $rule ) ) {
					$matched_rule = [ $key => $rule ];
					$this->maybe_back_off( $matched_rule, $request );	
				}
			}
		}
		return $response;
	}

	private function match_rule_method( $method, $rule ) {

		return ( 	$rule[ 'method' ] === 'any' || 
					$rule[ 'method' ] === $method || 
					in_array( $method, explode( ',', $rule[ 'method' ] ) ) 
		);
	}


	private function maybe_back_off( $matched_rule, $request ) {
		
		$rule_id = array_keys( $matched_rule )[0];
		$lock = $this->get_lock( $rule_id );

		if ( $lock ) {
			$this->back_off_please( $rule_id, $matched_rule, $lock );
		}

		// since there's no lock we'll allow the request to continue, but first increment the counter
		$counter = $this->increment_counter( $rule_id, $matched_rule );

		//now see if we need to lock things down for next time
		if ( $counter >= $matched_rule[ $rule_id ][ 'limit' ] ) {
			$this->set_lock( $rule_id, $matched_rule );
		}
	}

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

	private function set_lock( $rule_id, $matched_rule ) {
		$lock = self::LOCK_PREFIX . $rule_id;
		$lock_data = [ 'lockout' => $matched_rule[ $rule_id ]['lockout' ], 'lock_set' => time() ];
		return set_transient( $lock, $lock_data, $matched_rule[ $rule_id ]['lockout' ] );
	}


	private function back_off_please( $rule_id, $matched_rule, $lock ) {

		$lockout = $matched_rule[ $rule_id ][ 'lockout' ];
		$retry_after = ( isset( $lock['lock_set'] ) ) ? max( $lockout - ( time() - $lock['lock_set'] ), 1 ) : $lockout;
		status_header( 429, 'Exoskeleton: too many requests for this endpoint.  Please consult Retry-After and come back later.  Meanwhile enjoy a well-deserved REST');
		@header( "Retry-After: $retry_after");
		die();
	}

	private function increment_counter( $rule_id, $matched_rule ) {

		$counter_id = self::COUNTER_PREFIX . $rule_id;
		$counter = get_transient( $counter_id );

		$new_counter = [];
		$new_counter[ 'started_counting_at' ] = ( $counter === false ) ? time() : $counter[ 'started_counting_at' ];
		$new_counter[ 'value' ] = ( $counter === false ) ? 1 : $counter[ 'value' ] + 1;

		// the transient life.  This is either the rule window (for a new counter) or the window minus the number of seconds we've already been waiting.  Make sure we set the transient to last at least 1 second here.
		$time_left = ( $counter === false ) ? $matched_rule[ $rule_id ][ 'window' ] : max( 1, $matched_rule[ $rule_id ][ 'window' ] - ( time() - $counter[ 'started_counting_at' ] ) );

		set_transient( $counter_id, $new_counter, $time_left );
		return $new_counter[ 'value' ];
	}
}