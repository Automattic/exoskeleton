<?php

/**
 * Plugin Name: Exoskeleton
 * Version: 0.1-alpha
 * Description: Rate limiting for WordPress REST API
 * Author: Matt Perry
 * @package wp-exoskeleton
 */

require_once( plugin_dir_path( __FILE__ ) . 'classes/class.exoskeleton.php' );

function exoskeleton_add_rule( $args ) {
	$e = Exoskeleton::get_instance();
	return $e->add_rule( $args );
}

function exoskeleton_add_rules( $rules ) {
	foreach ( $rules as $rule ) {
		exoskeleton_add_rule( $rule );
	}
}

Exoskeleton::get_instance();