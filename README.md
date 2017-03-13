# Exoskeleton
Exoskeleton provides self-contained, basic, configurable rate limiting for the WordPress REST API.  It works with both default and custom endpoints. 

## What does it do?
Exoskeleton counts each access to REST API endpoints you specify.  If the number of accesses exceeds a configured limit in a configured time window, Exoskeleton switches off the endpoint by not dispatching the route and ends execution after issuing a `429` (too many requests) response header along with a configured `Retry-After` header.

Exoskeleton is not a replacement for infrastructural rate-limiting or other security measures.  Rather, it is designed to politely enforce usage limits on REST API endpoints that might be particularly expensive, and promote good client behavior.  That said, it may, if properly used, prevent over-use of expensive REST API endpoints, resulting in better site performance and stability.

Exoskeleton employs the transient API to meter endpoints -- it will therefore use an object cache if one is available.  It is recommended that you use Exoskeleton with an object cache backend such as memcache -- doing so will ensure that the plugin generates no database activity.  If your site stores transients in the database, a lightweight mysql `UPDATE` query will be executed each time a metered endpoint is accessed (unless it is currently locked.)  This is still likely to be many times less expensive than whatever endpoint you're protecting, but you have been warned :-)

## Configuration

Metering is configured using the following array of arguments:

```php
array(
	'route' => '/my-namespace/v1/route/(?P<id>[\d]+)',		// the fully namespaced route to be filtered
	'window' => 10,										// the time window in seconds 
	'limit'	=> 5,										// the maximum number of requests allowed in the time window
	'lockout' => 20,									// the lockout time in seconds
	'method' => 'POST',									// the methods (endpoints) to meter.  'any' may be used to meter all methods for a route
)
```

### metering default endpoints

To meter a default REST API endpoint, use the provided utility function `exoskeleton_add_rule`.
Example:

```php

exoskeleton_add_rule(
	[
		'route' => '/wp/v2/posts',
		'window' => 10,
		'limit'	=> 7,
		'lockout' => 20,
		'method' => 'any',
	]
);
```
causes the `/wp/v2/posts` to be metered for any method.  In the example, if more than 7 calls to this endpoint are detected in a 10 second period, a lockout of 20 seconds will occur, during which the 429 responses will be issued and the endpoint will not be dispatched.

To set multiple endpoints, use `exoskeleton_add_rules` and pass an array of arrays like the above.

### metering custom endpoints

You can configure metering for custom endpoints during their definition, ie: when calling `register_rest_route`.  Just pass in a configuration array (minus the route) as `exoskeleton`.   Example:

```php
add_action( 'rest_api_init', function () {
  register_rest_route( 'myplugin/v1', '/author/(?P<id>\d+)', array(
    'methods' => 'GET',
    'callback' => 'my_awesome_func',
    'exoskeleton' => [ 'window' => 10, 'limit'	=> 5, 'lockout' => 20, 'method' => 'any' ],
  ) );
} );
```