<?php
/**
 * Plugin Name:  Active plugins
 * Description:  Disable plugins on the front end.
 * Version:      1.0.0
 * Author:       Metabolism
 * License:      MIT License
 */

$request_uri = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

$is_admin = strpos( $request_uri, '/wp-admin/' );

if( false === $is_admin ){

    add_filter( 'option_active_plugins', function( $plugins ){

        $myplugins = [
            "cache-warmer/cache-warmer.php",
            "wp-crontrol/wp-crontrol.php"
        ];

        return array_diff( $plugins, $myplugins );
    });
}