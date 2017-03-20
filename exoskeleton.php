<?php

/**
 * Plugin Name: Exoskeleton
 * Version: 0.1-alpha
 * Description: Rate limiting for WordPress REST API
 * Author: Matt Perry
 * @package wp-exoskeleton
 */

require_once( plugin_dir_path( __FILE__ ) . 'classes/class.exoskeleton.php' );

/**
 * Add a rule to an existing endpoint
 * @param Array $args 
 * @return Bool whether or not the rule was added
 */
function exoskeleton_add_rule( $args ) {
	$e = Exoskeleton::get_instance();
	return $e->add_rule( $args );
}

/**
 * Add many rules at once
 * @param Array $rules  an array of rules arrays
 * @return null
 */
function exoskeleton_add_rules( $rules ) {
	foreach ( $rules as $rule ) {
		exoskeleton_add_rule( $rule );
	}
}

Exoskeleton::get_instance();